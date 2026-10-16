# SPEC-0053: Preparación integral para operar CS-Hotel en producción

- **Fecha:** 2026-09-07
- **Estado:** Implementado localmente — pendiente de staging/UAT y autorización de commit
- **Módulo:** Seguridad, configuración, operación hotelera, dinero, integraciones y despliegue
- **Dependencias:** SPEC-0001 a SPEC-0052, especialmente SPEC-0036, SPEC-0049, SPEC-0051 y SPEC-0052
- **Prioridad:** Bloqueante para operar con huéspedes, comprobantes y dinero real

## 1. Objetivo

Dejar CS-Hotel preparado para operar un hotel real en producción, con controles
verificables de seguridad, caja, reservas, folios, POS, inventario, housekeeping,
facturación, auditoría, integraciones externas, base de datos, despliegue, respaldo y
recuperación.

La aprobación de este SPEC no autoriza todavía el despliegue. El despliegue solo se
autoriza después de completar todos los criterios de aceptación, ejecutar `make verify`,
completar la prueba de restauración y obtener evidencia del gate final de go-live.

## 2. Alcance

### 2.1 Incluido

1. Cierre de bloqueadores de seguridad HTTP y de archivos.
2. Manejo global de errores, logs seguros y respuestas genéricas en producción.
3. HTTPS obligatorio, cabeceras de seguridad, cookies seguras, CSRF, RBAC y rate
   limiting para autenticación y acciones sensibles.
4. Configuración de producción validada al arrancar, sin valores inseguros por defecto.
5. Eliminación de credenciales de usuario conocidas en el seeder de producción y
   bootstrap seguro del primer administrador.
6. Migraciones con bloqueo contra ejecuciones concurrentes, respaldo previo y rollback
   operativo documentado.
7. Protección de la base de datos, verificación de relaciones, índices, modo estricto,
   charset y reconciliación de datos monetarios.
8. Validación de los procesos hoteleros completos: reserva, adelanto, check-in,
   estancia, folio, consumos, POS, checkout, caja, cierre, cancelación, no-show,
   devolución, gastos, otros ingresos, housekeeping e inventario.
9. Integración real de SUNAT u operador autorizado, sin respuestas simuladas disfrazadas
   de aceptación.
10. Almacenamiento local protegido o subida real y comprobable a R2/S3 compatible.
11. Rutas dinámicas, descargas protegidas, health checks y respuestas HTTP consistentes.
12. Pruebas unitarias, integración MySQL, feature/e2e, pruebas de concurrencia, UAT
   operativa y smoke test sobre un entorno de staging equivalente a producción.
13. Runbook de instalación, migración, respaldo, restauración, monitoreo, incidentes y
   rollback.
14. Gate final que impida declarar el sistema listo si queda un criterio sin evidencia.

### 2.2 Fuera del alcance

- Rediseños visuales que no sean necesarios para ejecutar o controlar una operación.
- Nuevos módulos comerciales no descritos en este SPEC.
- Cambio de framework o incorporación de un framework PHP.
- Borrado de datos de producción como método de corrección.
- Uso de datos, contraseñas, tokens o certificados reales dentro del repositorio.

## 3. Reglas de trabajo obligatorias

1. Este es el único SPEC activo durante toda la implementación. No se crean SPEC-0054,
   SPEC-0055 ni derivados para dividir el alcance.
2. No se escribe código de aplicación hasta la aprobación explícita de este SPEC.
3. Cada criterio de aceptación tendrá al menos una prueba automatizada o una evidencia
   operativa reproducible.
4. Cada cambio seguirá TDD: prueba roja, implementación mínima, prueba verde y
   refactor dentro de las capas existentes.
5. Después de cada tarea se ejecutará la prueba afectada y al finalizar se ejecutará
   `make verify` con salida real.
6. No se debilitan, eliminan ni saltan pruebas para conseguir verde.
7. Si aparece un bug de seguridad, pérdida de datos, crash o dinero, se reporta y se
   corrige dentro de este SPEC antes de continuar.
8. Si una decisión implica borrar datos, cambiar una columna con datos existentes,
   añadir una dependencia o modificar credenciales de producción, se detiene el paso y
   se solicita confirmación explícita.
9. No se declara "listo" por compilación o PHPUnit solamente: también deben estar en
   verde la seguridad, la configuración, la restauración y la operación hotelera.
10. La modificación previa no relacionada de `phpstan.neon` se conserva fuera del
    alcance y no se mezcla con el commit de este SPEC.

## 4. Arquitectura y archivos objetivo

La dirección de dependencias permanece:

```text
Request → public/index.php → Router → Controller → Service → Repository → PDO → MySQL
```

Archivos nuevos o modificados previstos:

- `public/index.php` — bootstrap, HTTPS, cabeceras y manejadores globales.
- `src/Support/Router.php` — rutas dinámicas seguras y respuestas 404/500.
- `src/Support/ErrorHandler.php` — logging y respuesta genérica de errores.
- `src/Support/SecurityHeaders.php` — políticas HTTP por entorno.
- `src/Support/HttpsRedirect.php` — redirección segura a HTTPS.
- `src/Support/ProductionGuard.php` — validación fail-fast de configuración.
- `src/Support/RateLimiter.php` — contrato de límites para login y acciones sensibles.
- `src/Support/Session.php` — cookie segura y detección de HTTPS detrás de proxy
  confiable.
- `src/Support/StorageProvider.php` — almacenamiento local protegido y subida R2 real.
- `src/Support/SunatProvider.php` — cliente real, timeout, respuesta y estados de
  contingencia.
- `src/Controllers/AuthController.php` — rate limiting y mensajes uniformes.
- `src/Controllers/StorageController.php` — descarga autorizada de comprobantes.
- `src/Controllers/HealthController.php` — liveness y readiness sin filtrar secretos.
- `src/Services/InvoiceService.php` — idempotencia y estados de envío.
- `src/Repositories/LoginAttemptRepository.php` — SQL preparado de intentos.
- `src/Repositories/InvoiceTransmissionRepository.php` — SQL y mapeo de envíos.
- `src/Repositories/HealthRepository.php` — comprobaciones de dependencias.
- `src/Repositories/StoreSaleRepository.php` — idempotencia de venta POS.
- `database/migrations/2026_09_07_000004_create_production_readiness_tables.php` —
  intentos de login y trazabilidad de envíos externos.
- `database/migrations/2026_09_07_000005_add_store_sale_idempotency.php` —
  clave única para evitar duplicar stock y caja en reintentos POS.
- `database/seeds/ProductionSeeder.php` — roles, permisos y bootstrap seguro sin
  contraseñas conocidas.
- `database/migrate.php`, `src/Database/Migrator.php` — lock de migración y salida
  operativa.
- `config/app.php`, `config/database.php`, `config/services.php`, `.env.example` —
  configuración explícita y validable.
- `bin/verify-production-config.php` — validación fail-fast del contrato de producción
  antes de migrar o atender tráfico.
- `deploy/nginx/cs-hotel.conf.example` — plantilla de Nginx con `public/` como única
  raíz web y un único front controller PHP.
- `deploy/php-fpm/cs-hotel.conf.example` — pool PHP-FPM con errores no visibles y
  usuario de proceso sin privilegios de aplicación.
- `deploy/scripts/deploy.sh` — instalación reproducible de release, rutas persistentes,
  validación de configuración, migración con lock y cambio atómico del symlink.
- `deploy/scripts/backup.sh`, `deploy/scripts/restore-verify.sh` y
  `deploy/scripts/health-check.sh` — backup cifrado, restore únicamente aislado y
  readiness verificable sin imprimir secretos.
- `routes/web.php` — health checks, descargas y rutas dinámicas autorizadas.
- `docs/operations/deployment.md` — instalación del servidor, secretos, permisos,
  Nginx, PHP-FPM, releases y primera puesta en marcha.
- `docs/operations/production-runbook.md` — despliegue, respaldo, restore, incidentes
  y rollback.
- `docs/operations/hotel-uat-checklist.md` — escenarios operativos firmables.

No se agrega una dependencia Composer para rate limiting, logging o HTTP. Para R2 se
usará un adaptador S3-compatible con una implementación revisada y pruebas de contrato;
si la implementación requiere el SDK oficial de AWS, la dependencia `aws/aws-sdk-php`
deberá justificarse en el mismo cambio, aprobarse y quedar cubierta por pruebas.

## 5. Reglas de negocio e invariantes

### 5.1 Seguridad HTTP y sesiones

1. En producción, `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` debe usar HTTPS y
   `APP_KEY` debe existir, tener entropía suficiente y no ser un valor de ejemplo.
2. Producción responde siempre con `Content-Security-Policy`,
   `Strict-Transport-Security`, `X-Content-Type-Options`, `X-Frame-Options` y
   `Referrer-Policy` compatibles con la aplicación.
3. Una petición HTTP de producción se redirige a HTTPS antes de ejecutar una operación.
   `X-Forwarded-Proto` solo se acepta desde proxies configurados explícitamente.
4. Las cookies de sesión son `HttpOnly`, `SameSite=Lax` o `Strict` y `Secure` en
   producción.
5. Todas las mutaciones autenticadas requieren CSRF y permiso RBAC persistido. El rol,
   usuario, monto, caja o permiso nunca se acepta desde el body del cliente.
6. El login limita intentos por usuario normalizado y origen de red, responde de forma
   genérica y no revela si existe el usuario.
7. El rate limit se registra sin guardar contraseñas, tokens ni credenciales en claro.
8. Los errores internos se registran en `storage/logs/` con contexto no sensible y el
   cliente recibe un mensaje genérico con `request_id`.

### 5.2 Archivos y almacenamiento

1. Ningún request puede salir del directorio de almacenamiento permitido mediante `..`,
   codificación URL, enlaces simbólicos o nombres absolutos.
2. Nunca se sirven `.env`, logs, cache, backups, archivos PHP ni archivos fuera de
   `storage/comprobantes`.
3. Las descargas de comprobantes exigen autenticación y el permiso correspondiente;
   se valida el nombre contra una allowlist de comprobantes PDF/XML.
4. El almacenamiento R2 solo informa éxito después de confirmar la respuesta de subida.
   Una URL calculada sin archivo existente no es un éxito válido.
5. Fallas de almacenamiento dejan la factura en estado recuperable y no simulan una
   emisión completa.

### 5.3 Configuración y usuarios de producción

1. El arranque de producción falla antes de atender tráfico si falta una variable
   obligatoria, existe un valor de ejemplo, hay `APP_DEBUG=true`, la base no es MySQL o
   el modo SQL no es estricto.
2. `ProductionSeeder` no crea contraseñas fijas conocidas. Recibe el bootstrap inicial
   por variables protegidas de un solo uso o crea únicamente roles/permisos y obliga a
   crear el primer administrador mediante un flujo seguro.
3. El seeder de desarrollo nunca se ejecuta cuando `APP_ENV=production`.
4. La plantilla `.env.example` contiene valores vacíos o claramente ficticios y nunca
   contiene credenciales operables.
5. SUNAT/R2/JSONPE se habilitan separadamente; una integración deshabilitada no puede
   aparentar un resultado real.

### 5.4 Integridad monetaria y hotelera

1. Los movimientos originales de caja, pagos, cargos, adelantos, devoluciones y
   ventas son inmutables; una corrección usa un movimiento inverso autorizado.
2. Cada operación monetaria multi-paso mantiene la transacción hasta persistir sus
   referencias, auditoría y actualización de saldos.
3. Las operaciones concurrentes bloquean la reserva, folio, pago, turno o serie que
   corresponda y una sola operación gana.
4. La suma de movimientos por método cuadra con los esperados y declarados del turno,
   con tolerancia explícita de S/0.01.
5. No se permite dinero sin turno abierto, pago duplicado, sobrepago, devolución doble,
   adelanto aplicado dos veces ni checkout con saldo inconsistente.
6. Cancelación, no-show y devolución respetan íntegramente SPEC-0052.
7. Reserva, check-in, estancia, folio, POS, inventario, gastos, otros ingresos,
   housekeeping y checkout conservan usuario, fecha, origen, método y auditoría.
8. Una falla de auditoría, caja, inventario, folio, almacenamiento o proveedor externo
   revierte la operación local o deja un estado explícito de reintento; nunca confirma
   una operación parcialmente.

### 5.5 Facturación e integraciones

1. `SUNAT_MODE=real` nunca devuelve `accepted` sin una respuesta real y verificable del
   proveedor autorizado.
2. Cada comprobante tiene una clave idempotente y un registro de transmisión con
   estado `pending`, `sent`, `accepted`, `rejected` o `failed`.
3. Un timeout o error externo no crea una factura aceptada; permite consultar o reintentar
   sin duplicar serie y número.
4. La respuesta del proveedor conserva código y estado técnico sin guardar tokens ni
   credenciales.
5. Los XML/PDF/vouchers se almacenan antes de exponer sus URLs y se puede comprobar
   su existencia mediante una prueba de descarga autenticada.
6. JSONPE usa timeout, validación de respuesta, no registra tokens y su indisponibilidad
   no rompe una operación que permite captura manual.

## 6. Modelo de datos

### 6.1 `auth_login_attempts`

La migración `2026_09_07_000004_create_production_readiness_tables.php` crea:

```sql
CREATE TABLE auth_login_attempts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    username_hash CHAR(64) NOT NULL,
    source_hash CHAR(64) NOT NULL,
    attempted_at DATETIME NOT NULL,
    was_successful TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_auth_login_attempts_lookup (username_hash, source_hash, attempted_at),
    INDEX idx_auth_login_attempts_cleanup (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Los hashes se generan con una clave de aplicación y no permiten recuperar el usuario
ni la IP original. La rama SQLite debe conservar el mismo contrato lógico.

### 6.2 `invoice_transmissions`

La migración crea:

```sql
CREATE TABLE invoice_transmissions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL UNIQUE,
    provider VARCHAR(50) NOT NULL,
    idempotency_key CHAR(64) NOT NULL UNIQUE,
    status ENUM('pending', 'sent', 'accepted', 'rejected', 'failed') NOT NULL,
    provider_code VARCHAR(100) NULL,
    provider_message VARCHAR(500) NULL,
    attempts INT NOT NULL DEFAULT 0,
    last_attempt_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE RESTRICT,
    CHECK (attempts >= 0),
    INDEX idx_invoice_transmissions_status_attempt (status, last_attempt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Los nombres exactos de las columnas se mantienen en snake_case y los repositorios
seleccionan columnas explícitas. No se modifica ni elimina el historial existente.

### 6.3 Idempotencia de ventas POS

La migración `2026_09_07_000005_add_store_sale_idempotency.php` agrega a `store_sales`:

```sql
idempotency_key CHAR(64) NULL UNIQUE
```

Las rutas de venta directa exigen `Idempotency-Key`; el servicio guarda una huella
SHA-256 y un reintento de la misma operación devuelve la venta original sin volver a
descontar stock ni registrar caja. Las llamadas internas de pruebas o procesos que no
exponen HTTP pueden omitirla, pero una operación monetaria HTTP no puede hacerlo.

### 6.4 Integridad existente

Antes de aplicar la migración se verifica en la base de staging:

- `ENGINE=InnoDB` y `utf8mb4_unicode_ci` en tablas de negocio.
- `STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION` activo.
- Foreign keys, índices y `ON DELETE RESTRICT` de SPEC-0049, SPEC-0051 y SPEC-0052.
- Cero reservas duplicadas, devoluciones duplicadas, movimientos huérfanos o pagos sin
  origen.
- Cero usuarios con contraseñas conocidas del seeder de producción.

## 7. Rutas y contratos HTTP

### 7.1 Health checks

`GET /api/health/live`

- Público para el balanceador.
- Devuelve `200` y `{"status":"ok"}` si el proceso responde.
- No abre una conexión de base de datos ni expone versión, rutas o secretos.

`GET /api/health/ready`

- Requiere `X-Health-Token` válido desde el balanceador o red interna.
- Verifica conexión PDO, migraciones aplicadas, almacenamiento configurado y modo
  estricto.
- Devuelve `200` solo si todo está listo; `503` si una dependencia falla.
- Nunca devuelve DSN, contraseñas, SQL ni trazas.

### 7.2 Herramientas de despliegue

`php bin/verify-production-config.php` devuelve código `0` únicamente cuando el
contrato de configuración de producción es válido y código `1` cuando falta una
variable, HTTPS, MySQL estricto, SUNAT real, token de health o proveedor de storage.
Los scripts de `deploy/scripts/` no pueden apuntar a una base de restore como si fuera
producción, no pueden ejecutar `DROP DATABASE` y no pueden imprimir secretos.

### 7.3 Autenticación

`POST /api/auth/login`

- Mantiene el contrato actual de usuario y contraseña.
- Devuelve `200` solo con credenciales válidas y dentro del límite.
- Devuelve `401` para credenciales inválidas y `429` al superar el límite.
- El cuerpo y los logs no revelan si el usuario existe.

### 7.4 Descarga de comprobantes

`GET /storage/comprobantes/{file}`

- Requiere sesión autenticada y autorización de comprobantes.
- `{file}` solo acepta el nombre generado por el sistema.
- Devuelve `200` con MIME exacto, `404` si no existe y `403` si no hay permiso.
- Devuelve `400` para traversal, encoding inválido o extensión no permitida.

### 7.5 Facturación

`POST /api/invoices/issue`

- Mantiene validación de tipo, cliente, ítems y montos.
- Requiere el encabezado Idempotency-Key; el servidor lo normaliza y persiste como
  huella fija para reintentos seguros.
- Genera o recupera la misma transmisión al repetir la misma operación idempotente.
- Devuelve `200`/`201` solo para estado local consistente, y expone el estado externo
  sin llamar aceptado a un resultado simulado o desconocido.
- Devuelve `409` para conflicto de serie/idempotencia y `503` para indisponibilidad
  externa sin confirmar aceptación.

### 7.6 Rutas existentes

Todas las rutas definidas en `routes/web.php` deben probarse con el router real. Las
rutas con parámetros como `/api/invoices/{id}/escpos` deben resolver el parámetro y no
tratar `{id}` como texto literal. Los 404 no autenticados de vistas deben redirigir a
`/login`; los 401/403 de API deben conservar JSON genérico.

## 8. Criterios de aceptación

### CA-01 — Configuración segura de producción

- **Given:** falta `APP_KEY`, `APP_URL` usa HTTP o `APP_DEBUG=true`.
- **When:** arranca la aplicación con `APP_ENV=production`.
- **Then:** el proceso falla antes de aceptar tráfico y no expone credenciales ni
  trazas al cliente.

### CA-02 — Cabeceras y HTTPS

- **Given:** una petición llega por HTTP en producción.
- **When:** entra por el front controller.
- **Then:** se redirige a HTTPS y las respuestas HTTPS incluyen CSP, HSTS, nosniff,
  frame deny y política de referrer.

### CA-03 — Protección de archivos

- **Given:** un cliente solicita `/storage/../.env`, `/storage/logs/app.log` o un
  enlace simbólico fuera de comprobantes.
- **When:** se despacha la petición.
- **Then:** recibe `400` o `403`, no se lee el archivo y queda una auditoría técnica
  sin contenido sensible.

### CA-04 — Descarga autorizada

- **Given:** existe un comprobante generado en `storage/comprobantes`.
- **When:** un usuario autenticado y autorizado solicita el archivo válido.
- **Then:** recibe el archivo correcto; un usuario no autenticado recibe redirect/401 y
  un usuario sin permiso recibe `403`.

### CA-05 — Errores genéricos y auditables

- **Given:** un handler lanza una excepción inesperada.
- **When:** se procesa una petición web o API.
- **Then:** la respuesta es `500` genérica con `request_id`, el detalle se registra sin
  secretos y el proceso no muestra stack trace.

### CA-06 — Rate limiting de login

- **Given:** se envían intentos fallidos repetidos para la misma identidad y origen.
- **When:** se supera el límite configurado.
- **Then:** la API devuelve `429`, no ejecuta autenticación ilimitada y permite una
  recuperación controlada después de la ventana definida.

### CA-07 — Seeder de producción seguro

- **Given:** una base vacía en producción.
- **When:** se ejecuta el seeder permitido.
- **Then:** crea roles/permisos y un administrador bootstrap suministrado de forma
  segura; no crea `admin/Admin123!`, `receptionist/Recep123!`, ni otras credenciales
  fijas documentadas.

### CA-08 — Migración concurrente

- **Given:** dos procesos de despliegue ejecutan migraciones al mismo tiempo.
- **When:** ambos intentan adquirir el lock.
- **Then:** solo uno aplica la migración y el otro espera o termina de forma controlada;
  no hay tablas parciales ni registros duplicados en `migrations`.

### CA-09 — Backup y restauración

- **Given:** la base de staging contiene usuarios, reservas, caja, folios, facturas y
  movimientos de prueba.
- **When:** se ejecuta el backup, se restaura en una base aislada y se valida el conteo
  y las sumas financieras.
- **Then:** la restauración es utilizable, mantiene foreign keys y los totales por
  método coinciden con la base origen.

### CA-10 — Ciclo hotelero con dinero

- **Given:** una reserva con adelanto dividido entre Yape y tarjeta.
- **When:** se ejecutan reserva, check-in, folio, consumo, checkout y cierre de caja.
- **Then:** cada importe conserva método, turno, usuario y origen; no hay doble ingreso,
  el saldo queda en cero y el arqueo cuadra por método.

### CA-11 — Cancelación, no-show y devolución

- **Given:** existen adelantos y una caja abierta para el ejecutor.
- **When:** se ejecutan cancelación total, parcial, no-show y devolución concurrente.
- **Then:** se respetan las políticas de SPEC-0052, solo una devolución gana, cada
  movimiento inverso cuadra y los originales permanecen inmutables.

### CA-12 — POS, gastos, otros ingresos e inventario

- **Given:** existe producto con stock y turno abierto.
- **When:** se realiza venta directa, venta a habitación, gasto, otro ingreso y cierre.
- **Then:** stock, folio, caja, método de pago, usuario y auditoría se actualizan una
  sola vez; una falla revierte el conjunto correspondiente.

### CA-13 — Housekeeping y estado de habitación

- **Given:** una estancia llega a checkout.
- **When:** finaliza la operación y se registra la tarea de limpieza.
- **Then:** la habitación no vuelve a venderse antes de quedar liberada según el estado
  de housekeeping, y el historial conserva responsable y timestamp.

### CA-14 — SUNAT real sin falsos positivos

- **Given:** staging tiene credenciales de prueba reales del proveedor autorizado.
- **When:** se emite un comprobante, se repite la solicitud y se simula timeout.
- **Then:** aceptación solo ocurre con respuesta real, el reintento es idempotente y
  el timeout queda `failed` o `pending`, nunca `accepted`.

### CA-15 — R2 o almacenamiento local seguro

- **Given:** el proveedor de almacenamiento configurado está disponible y luego falla.
- **When:** se generan PDF, XML y voucher.
- **Then:** los tres archivos existen y se descargan con autorización; ante una falla,
  la factura no aparenta tener archivos disponibles.

### CA-16 — Rutas dinámicas y respuestas consistentes

- **Given:** se solicitan rutas con parámetros, rutas inexistentes, UI sin sesión y API
  sin sesión.
- **When:** las procesa el router.
- **Then:** los parámetros se resuelven, UI redirige a `/login`, API responde JSON
  `401/403/404` y ninguna respuesta filtra excepciones.

### CA-17 — Health checks

- **Given:** el proceso, base, migraciones o almacenamiento están disponibles y luego
  se fuerza una dependencia caída.
- **When:** se consultan liveness y readiness.
- **Then:** liveness sigue indicando proceso activo y readiness devuelve `503` sin
  revelar detalles internos cuando una dependencia no está lista.

### CA-18 — Auditoría y ausencia de secretos

- **Given:** se ejecutan login, pagos, caja, facturación, cancelación, devolución y
  errores.
- **When:** se revisan `audit_logs` y `storage/logs`.
- **Then:** existen eventos con usuario/entidad/acción, y no aparecen contraseñas,
  tokens, certificados, números de tarjeta ni SQL sensible.

### CA-19 — Smoke test de staging

- **Given:** un despliegue limpio sobre PHP 8.4+, MySQL/MariaDB estricto y HTTPS.
- **When:** se ejecutan migración, bootstrap, login, reserva, caja, facturación,
  descarga, health checks y cierre.
- **Then:** todas las rutas críticas funcionan con datos de staging y el resultado queda
  firmado en el runbook.

### CA-20 — Rollback operativo

- **Given:** el despliegue de una versión falla antes de atender usuarios.
- **When:** se ejecuta el rollback documentado sobre un snapshot restaurable.
- **Then:** la versión anterior atiende tráfico, la base conserva consistencia y el
  incidente queda registrado con hora, responsable y causa.

### CA-21 — Paquete reproducible de despliegue

- **Given:** existe un release aprobado y un servidor con PHP 8.4+, PHP-FPM, Nginx y
  MySQL/MariaDB.
- **When:** se ejecuta `deploy/scripts/deploy.sh` con un `.env` privado, rutas de
  storage persistentes y una base autorizada.
- **Then:** Composer instala sin dependencias de desarrollo, la configuración se
  valida antes de migrar, las migraciones usan lock, `current` cambia atómicamente y
  los archivos persistentes quedan fuera del release.

### CA-22 — Backup y restore operables

- **Given:** existen credenciales de MySQL por login-path, una clave pública `age` y
  una base de restore vacía cuyo nombre comienza por `railway_restore_`.
- **When:** se ejecutan `backup.sh` y `restore-verify.sh`.
- **Then:** el backup queda cifrado con checksum, el SQL temporal se elimina, el
  restore solo se realiza en la base aislada y la base operativa nunca se sobrescribe.

## 9. Casos límite y errores

- Proxy inverso mal configurado o `X-Forwarded-Proto` falsificado.
- `APP_KEY` vacío, corto, repetido o igual al ejemplo.
- Base MySQL caída, conexión perdida dentro de una transacción o modo SQL incorrecto.
- Migración bloqueada, incompleta o ejecutada dos veces.
- Archivo inexistente, enlace simbólico, traversal codificado o MIME incorrecto.
- Sesión expirada, CSRF ausente, usuario desactivado o permiso revocado.
- Rate limit con múltiples workers y reloj cambiado.
- Caja cerrada, métodos divididos, redondeo de centavos, sobrepago o reembolso doble.
- Reserva concurrente, check-in duplicado, habitación ocupada o folio ya cerrado.
- SUNAT timeout, rechazo, CDR inválido, respuesta malformada o repetición de número.
- R2 con credenciales inválidas, objeto faltante, respuesta parcial o URL falsa.
- Backup incompleto, restore con collation distinta o foreign key huérfana.
- Error de PHP, excepción del proveedor, disco lleno o permisos insuficientes en logs.

Cada caso debe terminar en respuesta controlada, rollback cuando corresponda, auditoría
segura y evidencia de prueba.

## 10. Seguridad

1. Secretos únicamente en variables de entorno o secret manager; nunca en git, fixtures,
   logs, respuestas, HTML o mensajes de excepción.
2. El directorio público contiene solo `public/`; `storage`, `.env`, `vendor` y la raíz
   del repositorio no se exponen por el servidor web.
3. Producción usa TLS válido, renovación documentada, HSTS y redirección HTTP.
4. Los endpoints de dinero requieren autenticación, CSRF, RBAC, turno y transacción.
5. Los endpoints de administración y readiness no se publican sin control de acceso.
6. Se limita login y se registra únicamente un hash de identidad y origen.
7. Los logs se rotan, tienen permisos restrictivos y no contienen PII innecesaria.
8. Las dependencias se instalan con `composer install --no-dev --prefer-dist
   --optimize-autoloader` y se revisan antes del release.
9. El servidor ejecuta PHP-FPM con usuario sin privilegios y permisos mínimos sobre
   `storage/`.
10. El backup se cifra, se restringe por rol y se prueba restaurándolo antes del go-live.

## 11. Transacciones, concurrencia y auditoría

### 11.1 Arranque y migración

`validar configuración → adquirir lock de migración → verificar backup registrado →
aplicar migración en transacción → registrar versión → liberar lock → readiness`

Si falla cualquier paso, se revierte la migración aplicable, se libera el lock y se
detiene el despliegue.

### 11.2 Dinero hotelero

`bloquear entidad financiera → validar estado y permisos → persistir operación y
referencias → actualizar totales → auditoría → commit`

El `catch` hace rollback si la transacción pertenece al servicio y relanza la excepción.

### 11.3 Integraciones externas

Las llamadas a SUNAT/R2 se ejecutan fuera de una transacción larga. La base conserva un
estado `pending/sent/accepted/rejected/failed`; un proceso repetido consulta la
idempotencia antes de generar un nuevo envío. Ningún error externo se convierte en
aceptación local.

## 12. Plan de implementación dentro de este único SPEC

### Fase 1 — Línea base y pruebas rojas

1. Confirmar rama de release y separar la modificación preexistente de `phpstan.neon`.
2. Crear pruebas rojas para CA-01 a CA-22, agrupadas por seguridad, dinero,
   integración y operación.
3. Ejecutar el conjunto y conservar evidencia de los fallos iniciales.

### Fase 2 — Hardening de HTTP, sesiones y errores

1. Implementar `ErrorHandler`, `SecurityHeaders` y `ProductionGuard`.
2. Eliminar el servicio de archivos directo del router y pasar descargas por
   `StorageController` con allowlist y autenticación.
3. Añadir resolución segura de parámetros dinámicos al router.
4. Implementar rate limiting con `LoginAttemptRepository` y su migración.
5. Ejecutar pruebas unitarias y feature de CA-01 a CA-06 y CA-16.

### Fase 3 — Configuración, usuarios y migraciones

1. Validar configuración de producción y sincronizar `.env.example`.
2. Reemplazar credenciales fijas del seeder por bootstrap seguro.
3. Añadir lock de migraciones y comprobar modo SQL, charset, claves e índices.
4. Ejecutar pruebas de CA-07, CA-08 y CA-18.

### Fase 4 — Dinero y operación hotelera

1. Crear pruebas de escenario para reserva, adelanto, check-in, folio, POS, checkout,
   cierre, gasto, ingreso, housekeeping, cancelación, no-show y devolución.
2. Ejecutar las pruebas contra MySQL de test, no solo SQLite.
3. Corregir cualquier desbalance o pérdida de trazabilidad dentro de este SPEC.
4. Ejecutar pruebas de CA-09 a CA-13 y CA-18.

### Fase 5 — SUNAT, R2 y rutas externas

1. Sustituir respuestas simuladas del modo real por adaptadores verificables y
   estados idempotentes.
2. Implementar `invoice_transmissions`, reintentos controlados y contrato de descarga.
3. Ejecutar pruebas con dobles deterministas y luego pruebas reales en staging con
   credenciales de prueba, sin guardarlas en el repositorio.
4. Ejecutar CA-14, CA-15 y revisar CA-18.

### Fase 6 — Operaciones y recuperación

1. Añadir liveness/readiness y logging seguro.
2. Escribir el runbook con comandos exactos de instalación, backup, restore, migración,
   monitoreo, rotación y rollback.
3. Ejecutar un backup y una restauración real en una base aislada.
4. Ejecutar CA-09, CA-17 y CA-20 con evidencia fechada.

### Fase 6.1 — Paquete de servidor

1. Validar el contrato de producción también desde CLI antes de migrar.
2. Mantener `STORAGE_ROOT` y `LOG_PATH` fuera del release para permitir rollback sin
   perder comprobantes ni logs.
3. Entregar plantillas de Nginx/PHP-FPM y scripts fail-closed para release, backup,
   restore aislado y readiness.
4. Probar sintaxis Bash, contrato de artefactos y ausencia de secretos en el paquete.

### Fase 7 — Staging y gate de producción

1. Desplegar una copia limpia con PHP 8.4+, MySQL/MariaDB estricto, HTTPS y secretos
   de staging.
2. Ejecutar el smoke test completo y la checklist UAT con un responsable de recepción,
   caja, contabilidad y housekeeping.
3. Confirmar que no quedan fallos, placeholders, credenciales conocidas, migraciones
   pendientes ni cambios sin commit.
4. Ejecutar `make verify` final y archivar sus salidas junto al release.
5. Solicitar autorización explícita para el commit final y, separadamente, para el
   despliegue.

## 13. Pruebas obligatorias

### Unitarias

- `tests/Unit/Support/ErrorHandlerTest.php`
- `tests/Unit/Support/SecurityHeadersTest.php`
- `tests/Unit/Support/ProductionGuardTest.php`
- `tests/Unit/Support/RateLimiterTest.php`
- `tests/Unit/Support/HttpsRedirectTest.php`
- `tests/Unit/Support/StorageProviderContractTest.php`
- `tests/Unit/Support/SunatProviderContractTest.php`
- `tests/Unit/Repositories/LoginAttemptRepositoryTest.php`
- `tests/Unit/Repositories/InvoiceTransmissionRepositoryTest.php`
- `tests/Unit/Repositories/HealthRepositoryTest.php`
- `tests/Unit/Database/MigratorLockTest.php`
- `tests/Unit/Database/DatabaseSeederTest.php`

### Integración MySQL

- `tests/Integration/Database/SchemaContractTest.php`
- `tests/Integration/Repositories/InvoiceTransmissionRepositoryTest.php`
- `tests/Integration/Financial/HotelMoneyReconciliationTest.php`
- `tests/Integration/Database/BackupRestoreContractTest.php`

### Feature/e2e

- `tests/Feature/Production/ProductionBootstrapTest.php`
- `tests/Feature/Production/SecurityHeadersTest.php`
- `tests/Feature/Security/DevelopmentRouterSecurityTest.php`
- `tests/Feature/Security/StorageTraversalTest.php`
- `tests/Feature/Controllers/HealthControllerTest.php`
- `tests/Feature/Controllers/StorageControllerTest.php`
- `tests/Feature/Controllers/AuthControllerRateLimitTest.php`
- `tests/Feature/Hotel/HotelOperationsProductionReadinessTest.php`
- `tests/Feature/Controllers/InvoiceProductionFlowTest.php`
- `tests/Feature/Browser/ClientE2ETest.php`
- `tests/Feature/Production/DeploymentArtifactsTest.php`

### Comandos de salida

```bash
make lint
make analyse
make test-unit
make test-integration
make test-e2e
make verify
```

Resultado obligatorio: todos los comandos terminan con código `0`, PHPUnit sin fallos,
PHPStan sin errores y evidencia de MySQL de test. Las pruebas externas de SUNAT/R2 y
backup/restore además deben tener un registro de staging reproducible.

## 14. Runbook y gate de go-live

El archivo `docs/operations/production-runbook.md` debe contener, como mínimo:

1. Requisitos del servidor y versión de PHP/FPM/MySQL.
2. Configuración de secretos y permisos de archivos.
3. Configuración HTTPS, proxy, headers y health checks.
4. Procedimiento de backup verificado antes de migrar.
5. Instalación de dependencias sin `require-dev`.
6. Migración con lock y validación posterior.
7. Bootstrap del primer administrador sin contraseña fija.
8. Smoke test de login, reserva, caja, folio, POS, comprobante y descarga.
9. Monitoreo de errores, disco, base, colas externas y readiness.
10. Rollback de aplicación y restauración de base con responsables definidos.
11. Procedimiento de incidente para descuadre de caja, duplicidad de pago, SUNAT caído,
    pérdida de almacenamiento o fuga de archivo.

El archivo `docs/operations/deployment.md` debe además contener la estructura de
releases, permisos exactos, conexión con login-path de MySQL, rutas persistentes,
instalación de Nginx/PHP-FPM, validación CLI y comandos de activación/rollback.

El go-live queda **bloqueado** si una casilla es falsa:

- [ ] SPEC-0053 implementado y aprobado.
- [ ] `make verify` verde con salida archivada.
- [ ] Cero bloqueadores de seguridad abiertos.
- [ ] Cero credenciales conocidas o de ejemplo en producción.
- [ ] HTTPS, cookies, CSP, HSTS y logs verificados.
- [ ] Backup y restore probados con sumas monetarias comparadas.
- [ ] Migraciones aplicadas en staging y producción con lock.
- [ ] Reserva → caja → folio → checkout → cierre cuadrado por método.
- [ ] Cancelación/no-show/devolución sin doble movimiento.
- [ ] POS, gastos, ingresos, inventario y housekeeping validados.
- [ ] SUNAT real y almacenamiento verificados en staging.
- [ ] Health checks, alertas y rollback probados.
- [ ] UAT firmada por recepción, caja, contabilidad y housekeeping.
- [ ] Commit/tag de release limpio y despliegue autorizado por separado.
- [ ] `bin/verify-production-config.php` y scripts de `deploy/` probados.
- [ ] `STORAGE_ROOT` y `LOG_PATH` persistentes fuera del release.

## 15. Definition of Done

Este SPEC solo se considera completo cuando todos los CA-01 a CA-22 están cubiertos,
los tests están verdes, el runbook se ejecutó en staging, el restore fue exitoso, no
quedan cambios de aplicación sin commit, el usuario autorizó el commit y existe una
aprobación separada para desplegar producción.
