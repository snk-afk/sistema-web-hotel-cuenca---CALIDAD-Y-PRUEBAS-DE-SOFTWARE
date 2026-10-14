# SPEC-0049: Auditoría Integral — Integridad Relacional y Pruebas MySQL

- **Fecha:** 2026-09-07
- **Estado:** Aprobado — implementación verificada
- **Estado de trabajo:** Único SPEC activo; no se abrirá otro SPEC hasta cerrarlo o
  bloquearlo explícitamente
- **Módulo:** Base de datos, migraciones, relaciones, pruebas de integración y feature
- **Dependencias:** SPEC-0001, SPEC-0013 y SPEC-0036
- **Origen:** Auditoría integral del proyecto y verificación contra la base de datos de desarrollo

## 1. Objetivo

Corregir la divergencia entre el modelo relacional declarado por el sistema y el esquema
MySQL efectivo, y garantizar que las pruebas que ejercitan persistencia validen el mismo
motor que se utilizará en desarrollo y producción.

El resultado debe impedir registros huérfanos en relaciones críticas, alinear los valores
ENUM con los estados que realmente usa la aplicación y ejecutar las pruebas de
integración/feature con una base MySQL dedicada (`cs_hotel_test`).

Este SPEC no autoriza cambios de seguridad HTTP, routing, reportes, facturación ni UI;
esos hallazgos se mantienen pendientes para los siguientes SPEC y no deben inventarse ni
incorporarse silenciosamente en este cambio.

## 2. Contexto y alcance

### 2.1 Dentro del alcance

1. Crear y preparar la base `cs_hotel_test` usando las credenciales existentes del
   entorno de pruebas, con una guardia que impida apuntar a `cs_hotel` o a cualquier otra
   base no dedicada.
2. Ejecutar las migraciones sobre `cs_hotel_test` y dejar el esquema reproducible desde
   cero mediante un comando explícito y protegido.
3. Migrar las pruebas `tests/Integration/` y las pruebas `tests/Feature/` que consultan
   o modifican persistencia desde SQLite en memoria a MySQL.
4. Mantener SQLite únicamente para pruebas unitarias de componentes aislados y para las
   pruebas unitarias del migrador que necesiten un motor efímero.
5. Agregar una migración nueva, sin modificar migraciones históricas ya ejecutadas, para
   completar claves foráneas e índices necesarios.
6. Alinear `folio_charges.charge_type` con los valores aceptados por `FolioService`.
7. Probar explícitamente que las restricciones rechazan referencias inexistentes y que
   los datos semilla no generan huérfanos.
   Los seeders deben resolver las referencias mediante claves estables de negocio y
   no asumir valores de autoincremento.
8. Mantener una lista de criterios, pruebas y pendientes; ningún punto se puede marcar
   como terminado sin evidencia.
9. Corregir incompatibilidades de prepared statements que MySQL revele al migrar las
   pruebas, únicamente cuando preserven el comportamiento funcional existente y no
   introduzcan reglas de negocio nuevas. También se corrigen composiciones de
   transacciones que fallen al ejecutar servicios dentro del rollback transaccional
   de las pruebas, preservando la atomicidad del flujo cuando el servicio es la
   transacción propietaria.

### 2.2 Fuera del alcance

- No se elimina ni recrea `cs_hotel` como parte de la suite automatizada.
- No se modifican migraciones históricas para ocultar diferencias ya aplicadas.
- No se borran ni relajan pruebas existentes para adaptarlas al nuevo motor.
- No se agregan endpoints, reglas de negocio, dependencias ni cambios visuales.
- No se corrigen todavía traversal de storage, routing dinámico, headers, login rate
  limiting, reportes, XML/SUNAT, XSS o versiones de PHPUnit; se registran como pendientes
  para SPEC posteriores.

## 3. Reglas de negocio e integridad

1. Toda referencia obligatoria a una entidad existente debe estar protegida por una clave
   foránea MySQL.
2. Las entidades históricas de caja, inventario, folios, estancias, auditoría y gastos
   no pueden quedar huérfanas por eliminación del usuario que las creó; sus relaciones
   usan `ON DELETE RESTRICT`.
3. Una tarea de housekeeping puede conservarse aunque se elimine o desactive el usuario
   asignado; `assigned_to_user_id` usa `ON DELETE SET NULL`.
4. `audit_logs.entity_id` y `cash_movements.reference_id` son referencias polimórficas;
   no se les agrega una FK falsa porque su tabla destino depende de `entity_name` o del
   tipo de movimiento.
5. `folio_charges.charge_type` acepta exactamente: `accommodation`, `store_item`,
   `service`, `extension`, `other`, `penalty` y `adjustment`.
6. La base de pruebas debe tener el mismo charset, collation, modo SQL estricto,
   tablas InnoDB, índices y restricciones que la base de desarrollo.
7. Toda operación de prueba que modifique datos debe limpiar su estado mediante
   transacción con rollback o fixtures deterministas; no se reutilizan datos de
   desarrollo.
8. Los comandos que creen, borren o reconstruyan la base de pruebas deben rechazar la
   ejecución si `APP_ENV` no es `testing` o si el nombre no coincide exactamente con
   `DB_TEST_DATABASE` y el prefijo permitido `cs_hotel_test`.

## 4. Modelo de datos

### 4.1 Claves foráneas nuevas

La migración nueva debe crear las siguientes restricciones, conservando los tipos de las
columnas existentes y usando `ON DELETE RESTRICT` salvo donde se indique lo contrario:

| Tabla hija | Columna | Tabla padre | Acción al borrar |
|---|---|---|---|
| `stays` | `guest_id` | `guests.id` | `RESTRICT` |
| `stays` | `created_by_user_id` | `users.id` | `RESTRICT` |
| `stay_extensions` | `created_by_user_id` | `users.id` | `RESTRICT` |
| `reservations` | `created_by_user_id` | `users.id` | `RESTRICT` |
| `room_transfers` | `created_by_user_id` | `users.id` | `RESTRICT` |
| `cashbox_shifts` | `user_id` | `users.id` | `RESTRICT` |
| `cash_movements` | `created_by_user_id` | `users.id` | `RESTRICT` |
| `folio_charges` | `created_by_user_id` | `users.id` | `RESTRICT` |
| `folio_payments` | `created_by_user_id` | `users.id` | `RESTRICT` |
| `store_sales` | `created_by_user_id` | `users.id` | `RESTRICT` |
| `inventory_movements` | `created_by_user_id` | `users.id` | `RESTRICT` |
| `expenses` | `created_by_user_id` | `users.id` | `RESTRICT` |
| `other_incomes` | `created_by_user_id` | `users.id` | `RESTRICT` |
| `housekeeping_tasks` | `created_by_user_id` | `users.id` | `RESTRICT` |
| `housekeeping_tasks` | `assigned_to_user_id` | `users.id` | `SET NULL` |
| `invoices` | `created_by_user_id` | `users.id` | `RESTRICT` |

Cada constraint debe tener nombre determinista, único dentro de la tabla y compatible
con MySQL/MariaDB. Antes de agregarla, la migración debe verificar o fallar de forma
explícita si existen datos huérfanos; no debe borrar ni corregir datos automáticamente.

### 4.2 Índices

La migración debe garantizar índices para las columnas FK nuevas cuando no estén ya
creados por MySQL o por las migraciones previas. No se deben crear índices duplicados.
Los índices deben cubrir también los filtros de integración que consulten por usuario,
entidad y fecha cuando el esquema actual no los tenga.

### 4.3 ENUM de cargos de folio

La migración debe ampliar `folio_charges.charge_type` con `penalty` y `adjustment`, sin
eliminar valores existentes ni convertir datos válidos. La rama de compatibilidad SQLite
usada por pruebas unitarias debe reflejar la misma lista canónica.

## 5. Configuración y comandos

### 5.1 Variables

Se conserva la configuración centralizada en `Support\\Config`:

- `DB_HOST`
- `DB_PORT`
- `DB_USERNAME`
- `DB_PASSWORD`
- `DB_TEST_DATABASE` con valor predeterminado `cs_hotel_test`

No se agregan credenciales nuevas si las existentes sirven para el servidor de pruebas.
`.env.example` debe documentar cualquier clave nueva que resulte estrictamente
necesaria, sin secretos reales.

### 5.2 Comando de reconstrucción de pruebas

Se agregará un comando explícito, por ejemplo `make db-test-reset`, que:

1. compruebe `APP_ENV=testing`;
2. compruebe que la base objetivo es exactamente `DB_TEST_DATABASE` y empieza por
   `cs_hotel_test`;
3. ejecute una reconstrucción determinista únicamente sobre esa base;
4. ejecute migraciones;
5. verifique charset, collation, modo SQL, motor, tablas, FKs y ENUMs;
6. falle con mensaje genérico y código distinto de cero ante cualquier incumplimiento.

El comando no puede leer el nombre de la base desde argumentos libres ni aceptar como
objetivo `cs_hotel`, una base de producción o una base cuyo nombre no sea de pruebas.

La guardia y la conexión serán responsabilidad de `App\\Database\\TestDatabaseManager`,
con una interfaz tipada equivalente a:

```php
public static function fromConfig(): self;
public function assertSafeEnvironment(string $environment): void;
public function recreate(string $migrations_dir): void;
public function connection(): PDO;
```

El script CLI solo orquesta esta clase y presenta mensajes; no duplica sus validaciones.

## 6. Pruebas y organización

### 6.1 Unitarias

- Las pruebas unitarias puras seguirán usando dobles o SQLite en memoria únicamente
  cuando no estén validando comportamiento específico de MySQL.
- `MigratorTest` y las pruebas de seed/migración pueden conservar SQLite como motor
  efímero, pero deben comprobar también que la rama MySQL contiene el mismo contrato.
- Las pruebas unitarias no pueden crear conexiones contra `cs_hotel`.

### 6.2 Integración y feature con persistencia

- Toda prueba de `tests/Integration/` y `tests/Feature/` que use Repository, Service o
  Controller con datos persistidos debe conectarse a `cs_hotel_test` mediante un helper
  común tipado.
- El helper debe rechazar `APP_ENV` distinto de `testing` y cualquier base distinta de
  `DB_TEST_DATABASE`.
- La suite debe preparar el esquema con las migraciones reales, no con CREATE TABLE
  simplificados que omitan FKs, ENUMs o índices.
- Cada caso debe aislar sus datos con rollback o fixtures con claves únicas deterministas.
- Las pruebas no pueden depender del orden de ejecución ni de datos sembrados en
  `cs_hotel`.

### 6.3 Pruebas de relaciones

Debe existir al menos una prueba por cada grupo de relaciones que:

1. inserte una referencia válida;
2. intente insertar una referencia inexistente;
3. verifique que MySQL rechaza la operación con una excepción de integridad;
4. confirme que no queda ninguna fila parcial.

También debe existir una prueba de `assigned_to_user_id` que compruebe el comportamiento
`ON DELETE SET NULL` y una prueba de cargos que persista `penalty` y `adjustment`.

## 7. Endpoints y rutas

Este SPEC no agrega ni modifica endpoints HTTP. La superficie operativa se limita a los
comandos CLI de desarrollo y pruebas descritos en la sección 5. Cualquier cambio de ruta
o comportamiento HTTP se rechaza por estar fuera del alcance.

## 8. Criterios de aceptación

### CA-01 — Base de pruebas dedicada

- **Given:** `APP_ENV=testing` y credenciales válidas.
- **When:** se ejecuta la preparación de `cs_hotel_test`.
- **Then:** la base existe con charset `utf8mb4`, collation `utf8mb4_unicode_ci`, modo
  SQL estricto y todas las migraciones aplicadas.

### CA-02 — Protección contra base equivocada

- **Given:** `APP_ENV=local`, `DB_DATABASE=cs_hotel` o un nombre no permitido.
- **When:** se ejecuta el comando de reconstrucción de pruebas.
- **Then:** termina con código distinto de cero, no ejecuta `DROP`, no ejecuta `CREATE`
  sobre la base de desarrollo y explica el bloqueo sin filtrar secretos.

### CA-03 — Relaciones completas

- **Given:** un esquema vacío reconstruido desde las migraciones.
- **When:** se inspeccionan las FKs de las tablas definidas en 4.1.
- **Then:** todas existen con columnas, tablas padre y acciones de borrado correctas.

### CA-04 — No hay huérfanos

- **Given:** la base de desarrollo migrada y sembrada.
- **When:** se ejecutan consultas de consistencia sobre todas las relaciones nuevas.
- **Then:** no existen filas huérfanas y el proceso termina exitosamente.

### CA-05 — ENUM canónico de folios

- **Given:** una conexión MySQL con el esquema actualizado.
- **When:** se insertan cargos `penalty` y `adjustment` con datos válidos.
- **Then:** ambos se persisten y los valores ajenos a la lista canónica son rechazados.

### CA-06 — Pruebas realmente MySQL

- **Given:** la suite de integración/feature configurada para pruebas.
- **When:** se ejecutan `make test-integration` y `make test-e2e`.
- **Then:** las pruebas con persistencia usan `cs_hotel_test`, no `sqlite::memory:`, y
  se ejecutan contra el esquema migrado real.

### CA-07 — Aislamiento de pruebas

- **Given:** dos pruebas consecutivas que modifican las mismas tablas.
- **When:** termina la primera y comienza la segunda.
- **Then:** la segunda observa un estado determinista, sin datos residuales ni dependencia
  del orden.

### CA-08 — Migraciones reproducibles

- **Given:** una base de pruebas recién creada.
- **When:** se ejecuta `make db-test-reset` y luego
  `APP_ENV=testing DB_DATABASE=cs_hotel_test make db-migrate` nuevamente.
- **Then:** el primer comando aplica todas las migraciones y el segundo informa que no
  quedan migraciones pendientes, sin duplicar tablas, constraints ni índices.

### CA-09 — Regresión completa

- **Given:** el cambio implementado.
- **When:** se ejecuta `make verify`.
- **Then:** lint, PHPStan nivel 9 y PHPUnit terminan con código cero, sin pruebas omitidas
  ni aserciones debilitadas.

## 9. Casos límite y errores

1. Si falta `DB_TEST_DATABASE`, se usa `cs_hotel_test`; si tiene un valor inseguro, el
   comando se detiene antes de conectarse a una base destructiva.
2. Si una FK no puede agregarse por datos huérfanos, la migración falla y reporta la
   tabla/columna afectada sin eliminar ni modificar filas automáticamente.
3. Si una constraint ya existe con definición distinta, la migración falla de forma
   explícita; no se ignora la diferencia.
4. Si un test intenta conectarse a `cs_hotel`, falla inmediatamente como error de
   configuración de pruebas.
5. Si MySQL no está disponible, las pruebas de persistencia fallan con diagnóstico
   controlado; no cambian silenciosamente a SQLite.
6. Si una prueba deja una transacción abierta, el helper la revierte y registra el fallo;
   nunca conserva cambios para que otra prueba los reutilice.

## 10. Seguridad y protección de datos

- El comando destructivo solo puede operar sobre una base de pruebas permitida.
- Nunca se imprimen `DB_PASSWORD`, DSN completos ni valores de `.env` en errores o logs.
- Las consultas de verificación usan SQL estático o statements preparados; no concatenan
  nombres provenientes del usuario.
- Las pruebas no utilizan datos reales ni se conectan a producción.
- Las claves foráneas protegen historial financiero y de auditoría frente a eliminaciones
  accidentales.

## 11. Transacciones

1. Cada migración se ejecuta dentro del comportamiento transaccional existente del
   `Migrator`; ante error, se hace rollback cuando el motor lo soporte.
2. Las pruebas de integración ejecutan cada caso dentro de una transacción y hacen
   rollback en `tearDown`, o usan limpieza explícita equivalente cuando el flujo pruebe
   commit/rollback.
3. La reconstrucción completa de `cs_hotel_test` es una operación exclusiva de entorno
   de pruebas y no comparte transacción ni conexión con `cs_hotel`.

## 12. Archivos previstos

| Archivo | Acción |
|---|---|
| `database/migrations/2026_09_07_000001_harden_relational_integrity.php` | Crear migración de FKs, índices y ENUM |
| `src/Database/TestDatabaseManager.php` | Crear guardia tipada y conexión exclusiva de `cs_hotel_test` |
| `tests/Support/MySqlTestDatabase.php` | Crear helper seguro de conexión, esquema y aislamiento |
| `tests/Support/MySqlTestCase.php` | Crear base común para pruebas con persistencia |
| `tests/Integration/**/*.php` | Migrar conexiones SQLite a helper MySQL donde corresponda |
| `tests/Feature/**/*.php` | Migrar conexiones SQLite a helper MySQL donde corresponda |
| `database/seeds/DevelopmentSeeder.php` | Resolver referencias por claves estables para evitar huérfanos por autoincremento |
| `src/Services/*.php` | Componer transacciones respetando la transacción propietaria |
| `src/Repositories/GuestRepository.php` | Mantener prepared statements compatibles con MySQL nativo |
| `tests/Unit/Database/MigratorTest.php` | Mantener SQLite solo para el contrato unitario del migrador y cubrir guardias |
| `phpunit.xml` | Configurar bootstrap/variables de test sin tocar secretos |
| `Makefile` | Agregar comando seguro de reconstrucción de base de pruebas |
| `.env.example` | Actualizar solo si se agregan variables nuevas |

No se modificará `AGENTS.md` dentro de este SPEC; sus reglas ya fueron aprobadas y
actualizadas por separado.

## 13. Orden TDD y definición de terminado

1. Crear primero las pruebas de guardia de base insegura, conexión MySQL y aislamiento.
2. Ejecutarlas en rojo por la ausencia del helper/configuración.
3. Implementar el helper mínimo y comprobar el verde.
4. Crear pruebas de FKs, ENUMs, índices y ausencia de huérfanos.
5. Ejecutarlas en rojo contra el esquema actual.
6. Crear la migración nueva y ejecutar las pruebas hasta verde.
7. Migrar las pruebas de integración/feature por grupos pequeños, verificando cada grupo.
8. Ejecutar `make test-unit`, `make test-integration`, `make test-e2e` y `make verify`.
9. Revisar esta lista completa de criterios y pendientes; si existe cualquier punto sin
   código, prueba o evidencia, el SPEC permanece incompleto.

El SPEC solo se considera implementado cuando todos los criterios CA-01 a CA-09 tienen
pruebas, la suite completa está en verde y el usuario autoriza el commit.
