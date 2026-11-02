# SPEC-0036: Hardening de Seguridad Crítica y Corrección de Money Bugs

- **Fecha:** 2026-08-22
- **Estado:** Implementado (Pendiente de Commit)
- **Módulo:** Seguridad de Autenticación/Autorización, CSRF, Integridad Transaccional de Caja/Stock/Facturación, Auditoría
- **Dependencias:** SPEC-0009 (RBAC + Audit), SPEC-0014 (Sesión/Login), SPEC-0005 (Caja), SPEC-0006 (Tienda), SPEC-0010 (Facturación)
- **Origen:** Auditoría rigurosa del proyecto (27 hallazgos). Este SPEC aborda los 8 hallazgos críticos (C1-C8).

---

## 1. Objetivo

Cerrar las vulnerabilidades críticas de seguridad e integridad financiera detectadas en la auditoría del 2026-08-22. El sistema actualmente permite acceso sin autenticación a todos los endpoints, impersonación de usuarios vía `user_id` del body, no tiene CSRF, tiene condiciones de carrera en inventario, transacciones BD abiertas durante llamadas HTTP a SUNAT, numeración fiscal no atómica, y el sistema de auditoría completamente desconectado.

Este SPEC consolida y **ejecuta** lo que los SPEC-0009 y SPEC-0014 definieron pero quedó sin conectar: middleware de autenticación, RBAC funcional, sesiones seguras, y registro de auditoría real.

---

## 2. Alcance

### Dentro del Alcance:

#### Bloque A — Autenticación y Autorización (C1, C2, C3, M7)
1. **Middleware de Autenticación:** Todo endpoint `/api/*` (excepto `/api/auth/login` y `/api/status`) requiere sesión activa. Sin sesión → `401 JSON {"error":"No autenticado"}`.
2. **Middleware de RBAC:** Todo endpoint que cambia estado verifica permisos vía `PermissionGuard::can()`. Sin permiso → `403 JSON {"error":"Permiso insuficiente"}`.
3. **Eliminación de `user_id` del request:** El `user_id` se obtiene SIEMPRE de `$_SESSION['user_id']`. Se elimina la lectura de `user_id` de `$_GET`/`$_POST`/`body` en TODOS los controladores. Default `1` prohibido.
4. **CSRF Token:** Generación de token CSRF en sesión, meta-tag en vistas, y verificación obligatoria en todo `POST`/`PUT`/`PATCH`/`DELETE`. Excepción: `/api/auth/login` (no tiene sesión previa) y endpoints API consumidos vía token Bearer (futuro).
5. **Cookies de sesión seguras:** `session_set_cookie_params()` con `httponly=true`, `samesite=Lax`, `secure=true` en HTTPS.

#### Bloque B — Integridad Transaccional (C4, C5, C6)
6. **Stock atómico:** `ProductRepository::updateStock()` cambia a `UPDATE products SET stock = stock - :quantity WHERE id = :id AND stock >= :quantity`. Validación de stock queda en SQL, no en PHP.
7. **SUNAT fuera de transacción:** `InvoiceService::issueInvoice()` reordena: (a) `beginTransaction` → reservar número de serie con `SELECT ... FOR UPDATE` + `UPDATE` → `commit` (transacción corta); (b) luego, fuera de transacción, enviar a SUNAT, generar PDF/XML, almacenar archivos; (c) `beginTransaction` → crear `invoices` + `invoice_items` → actualizar `sunat_status` → `commit`.
8. **Numeración fiscal atómica:** `InvoiceSeriesRepository::getNextNumberAndIncrement()` usa `SELECT ... FOR UPDATE` + `UPDATE ... SET current_number = current_number + 1` en una transacción corta y dedicada.

#### Bloque C — Auditoría y Anti-Falsificación (C7, C8)
9. **Conexión de AuditLogService:** Todos los Services llaman `AuditLogService::logAction()` en operaciones críticas: occupyRoom, checkoutRoom, transferRoom, extendStay, reverseCharge, addPayment, closeShift, sellDirectPOS, chargeToRoom, issueInvoice, recordExpense, recordOtherIncome, createTask, completeTask.
10. **Eliminación de generación de comprobantes falsos:** `InvoiceController::download()` si el archivo no existe retorna `404`. Prohibido generar PDFs/XML con datos vacíos y totales en cero.

### Fuera del Alcance (specs posteriores):
- N+1 queries (M3) — spec de performance separado.
- Headers HTTP de seguridad (M6) — spec de hardening de infra.
- Rutas dinámicas con parámetros (A1, A2) — spec de router v2.
- Reportes sin filtro de fecha (A3) — spec de reportes.

---

## 3. Reglas de Negocio

1. **Todo `user_id` proviene de la sesión.** Ningún controlador lee `user_id` del request. Si no hay sesión, se rechaza con `401`.
2. **Todo cambio de estado requiere CSRF.** Sin token CSRF válido → `419`.
3. **El stock se decrementa atómicamente en SQL.** No se calcula en PHP ni se valida solo en PHP.
4. **Las llamadas HTTP externas (SUNAT) nunca ocurren dentro de una transacción BD abierta.**
5. **La numeración de comprobantes se reserva con `FOR UPDATE` en su propia transacción.** Si la emisión falla, el número se marca como anulado/observado, no se reutiliza.
6. **Toda operación financiera o de estado registra auditoría.** El `AuditLogService::logAction()` se invoca dentro de la misma transacción que la operación que audita.
7. **Nunca se generan comprobantes con datos ficticios.** Si un PDF/XML no existe, se retorna `404`.

---

## 4. Modelo de Datos (Cambios)

### 4.1 Migración: `invoice_series` — añadir `status` por número

No se añade tabla nueva. El control de numeración se hace con `SELECT ... FOR UPDATE` sobre `invoice_series.current_number`. No requiere cambio de schema.

### 4.2 Tabla `csrf_tokens` (opcional — si se elige almacenamiento en BD)

Si se elige CSRF con almacenamiento en BD en lugar de sesión:
```sql
CREATE TABLE csrf_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(64) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    used TINYINT(1) DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```
**Decisión:** CSRF token en sesión (más simple, sin tabla nueva). Si el proyecto requiere multi-dispositivo, se migra a tabla.

### 4.3 Sin cambios de schema en `audit_logs`
La tabla ya existe (migración 0008). Solo se conecta el `AuditLogService`.

---

## 5. Endpoints / Rutas

### 5.1 Sin rutas nuevas — se blindan las existentes

Todas las rutas `/api/*` existentes pasan por middleware de auth. No se añaden rutas nuevas en este SPEC. Los cambios son en el `Router::dispatch()` y en los Controladores.

### 5.2 Endpoint de logout (nuevo)
- `POST /api/auth/logout` — Cierra sesión, destruye `$_SESSION`, retorna `200 JSON {"status":"success"}`.

### 5.3 Endpoint de token CSRF (nuevo)
- `GET /api/auth/csrf-token` — Retorna `{"token":"..."}` para que el frontend JS lo incluya en headers `X-CSRF-Token` de peticiones POST.

---

## 6. Arquitectura de la Solución

### 6.1 `Support\Session` (nueva clase)

```php
final class Session
{
    public static function start(): void;
    public static function userId(): ?int;        // Lee $_SESSION['user_id']
    public static function requireAuth(): int;     // Lanza excepción si no hay sesión
    public static function csrfToken(): string;    // Genera o retorna token CSRF de sesión
    public static function verifyCsrf(string $token): bool;
    public static function regenerate(): void;    // Anti session-fixation
    public static function destroy(): void;
}
```

### 6.2 `Support\AuthMiddleware` (nueva clase)

Intercepta en `Router::dispatch()` antes de ejecutar el handler:
- Si la ruta requiere auth y no hay sesión → `401`.
- Si la ruta requiere permiso y el usuario no lo tiene → `403`.
- Si es POST/PUT/DELETE y no tiene CSRF válido → `419`.

### 6.3 Mapa de Permisos por Ruta

| Ruta | Método | Permiso requerido |
|------|--------|-------------------|
| `/api/rooms` | GET | `rooms.view` |
| `/api/rooms` | POST (create) | `rooms.manage` |
| `/api/rooms` | POST (occupy/checkout/transfer/extend) | `rooms.occupy` |
| `/api/reservations` | GET | `reservations.view` |
| `/api/reservations` | POST | `reservations.manage` |
| `/api/cashbox/open` | POST | `cashbox.manage` |
| `/api/cashbox/close` | POST | `cashbox.manage` |
| `/api/cashbox/movement` | POST | `cashbox.movement` |
| `/api/store/sales` | POST | `store.sell` |
| `/api/store/charge-to-room` | POST | `store.sell` |
| `/api/invoices/issue` | POST | `invoices.issue` |
| `/api/expenses` | POST | `expenses.manage` |
| `/api/other-incomes` | POST | `incomes.manage` |
| `/api/housekeeping/tasks` | POST | `housekeeping.manage` |
| `/api/users` | POST | `users.manage` |

Rutas GET de catálogo (`/api/categories`, `/api/tariffs`, `/api/products`, `/api/guests`) requieren auth pero cualquier usuario autenticado puede verlas.

### 6.4 Refactor de Controladores

Cada controlador reemplaza:
```php
// ANTES (vulnerable):
$user_id = is_numeric($body['user_id'] ?? null) ? (int) $body['user_id'] : 1;
```
por:
```php
// DESPUÉS (seguro):
$user_id = Session::requireAuth();
```

---

## 7. Criterios de Aceptación (Given / When / Then)

### CA-1: Acceso sin sesión rechazado
- **Given:** Un cliente sin sesión iniciada.
- **When:** Hace `GET /api/rooms` o `POST /api/cashbox/open`.
- **Then:** Recibe `401 Unauthorized` con `{"status":"error","message":"No autenticado"}`. No se ejecuta ninguna lógica de negocio.

### CA-2: Acceso con sesión pero sin permiso rechazado
- **Given:** Un usuario autenticado con rol `housekeeper`.
- **When:** Hace `POST /api/invoices/issue`.
- **Then:** Recibe `403 Forbidden` con `{"status":"error","message":"Permiso insuficiente"}`.

### CA-3: `user_id` proviene de la sesión, no del request
- **Given:** Un usuario autenticado con `user_id=5`.
- **When:** Hace `POST /api/cashbox/open` con `{"user_id":1}` en el body.
- **Then:** El turno de caja se abre para `user_id=5` (el de la sesión), ignorando el `user_id=1` del body.

### CA-4: CSRF requerido en POST
- **Given:** Un usuario autenticado sin token CSRF.
- **When:** Hace `POST /api/cashbox/open`.
- **Then:** Recibe `419 CSRF Token Mismatch`. La operación no se ejecuta.

### CA-5: Stock decrementado atómicamente
- **Given:** Un producto con `stock=3` y un turno de caja abierto.
- **When:** Dos peticiones `POST /api/store/sales` concurrentes intentan vender 2 unidades cada una.
- **Then:** La primera tiene éxito (stock queda en 1). La segunda recibe `409 Conflict` con `"Stock insuficiente"`. El stock nunca es negativo.

### CA-6: SUNAT fuera de transacción BD
- **Given:** Una emisión de factura.
- **When:** El proceso llama a `InvoiceService::issueInvoice()`.
- **Then:** La llamada a `SunatProvider::sendDocument()` ocurre DESPUÉS de que la transacción de reserva de número hizo `commit()`, no durante. No hay transacción BD abierta durante la llamada HTTP externa.

### CA-7: Numeración fiscal atómica
- **Given:** Dos peticiones `POST /api/invoices/issue` concurrentes para el mismo `document_type`.
- **When:** Ambas intentan obtener el siguiente número.
- **Then:** Cada una obtiene un número distinto y consecutivo. No hay duplicados ni saltos.

### CA-8: Auditoría registrada en operación crítica
- **Given:** Un usuario autenticado realiza un checkout.
- **When:** Se completa `RoomService::checkoutRoom()`.
- **Then:** Existe un registro en `audit_logs` con `action='CHECKOUT'`, `entity_name='stays'`, `entity_id=<stay_id>`, `user_id=<session_user_id>`, y `new_values` contiene el estado final.

### CA-9: Descarga de comprobante no genera falsos
- **Given:** Un número de comprobante inexistente `F999-99999999`.
- **When:** Se solicita `GET /comprobantes/download?number=F999-99999999`.
- **Then:** Recibe `404 Not Found`. No se crea ningún archivo ficticio.

### CA-10: Cookies de sesión seguras
- **Given:** Una petición de login exitosa.
- **When:** Se establece la cookie de sesión.
- **Then:** La cookie tiene `HttpOnly`, `SameSite=Lax`, y `Secure` (si HTTPS).

### CA-11: Logout destruye sesión
- **Given:** Un usuario autenticado.
- **When:** Hace `POST /api/auth/logout`.
- **Then:** La sesión se destruye, la cookie se invalida, y peticiones posteriores reciben `401`.

### CA-12: `make verify` en verde
- **Given:** El código implementado.
- **When:** Se ejecuta `make verify`.
- **Then:** Sintaxis PHP, PHPStan nivel 9 y PHPUnit (con nuevas pruebas) en verde al 100%.

---

## 8. Casos Límite y Errores

1. **Sesión expirada mid-request:** Si la sesión expira entre el inicio y el procesamiento, el siguiente acceso a `Session::requireAuth()` lanza `401`.
2. **Token CSRF reutilizado:** Si se implementa token de un solo uso, una segunda petición con el mismo token recibe `419`. Si se implementa token por sesión (decisión: por sesión), el token es válido mientras la sesión exista.
3. **SUNAT cae durante emisión:** Si `SunatProvider::sendDocument()` lanza excepción, el número de serie ya está reservado (commit). Se debe registrar el comprobante con `sunat_status='pending'` y permitir reenvío. El número NO se reutiliza.
4. **Stock negativo por race:** El `UPDATE ... WHERE stock >= :quantity` previene stock negativo a nivel de SQL, incluso si dos peticiones leen el mismo stock.
5. **Audit log falla:** Si el `audit_log` falla dentro de una transacción, el rollback revierte la operación completa. La auditoría es obligatoria, no opcional.
6. **Usuario desactivado mid-sesión:** Si `is_active=0` en la BD pero la sesión sigue activa, el middleware verifica `is_active` en cada request. Si está desactivado → `403 "Cuenta desactivada"`.

---

## 9. Transacciones

1. **Reserva de número de serie (C6):** `beginTransaction` → `SELECT ... FOR UPDATE` → `UPDATE current_number` → `commit`. Transacción corta dedicada.
2. **Emisión de factura (C5):** Dos transacciones separadas:
   - TX-1: Reservar número (corta).
   - Fuera de TX: SUNAT + PDF + XML + storage.
   - TX-2: INSERT invoice + invoice_items + audit_log → `commit`.
3. **Venta POS (C4):** `beginTransaction` → `UPDATE products SET stock = stock - :qty WHERE id = :id AND stock >= :qty` → verificar `rowCount()` → si 0, rollback + `409` → si 1, continuar con INSERT sale + movements + audit_log → `commit`.
4. **Checkout (C7):** `beginTransaction` → completeStay + updateRoomStatus + closeFolio + createTask + audit_log → `commit`.
5. **Cualquier operación crítica:** El `audit_log` se inserta dentro de la misma transacción que la operación que audita.

---

## 10. Archivos a Modificar / Crear

| Archivo | Acción | Descripción |
|---------|--------|-------------|
| `src/Support/Session.php` | **Crear** | Gestión de sesión segura, CSRF, `requireAuth()` |
| `src/Support/AuthMiddleware.php` | **Crear** | Middleware de auth + RBAC + CSRF |
| `src/Support/Router.php` | **Modificar** | Integrar middleware antes de dispatch |
| `src/Support/PermissionGuard.php` | **Modificar** | Cargar permisos desde sesión si no están en cache |
| `src/Controllers/AuthController.php` | **Modificar** | Usar `Session`, añadir `logout()`, `csrfToken()` |
| `src/Controllers/*.php` (todos) | **Modificar** | Reemplazar `user_id` del body por `Session::requireAuth()` |
| `src/Services/RoomService.php` | **Modificar** | Invocar `AuditLogService`, recibir `user_id` del controller |
| `src/Services/CashboxService.php` | **Modificar** | Invocar `AuditLogService` en close/movement |
| `src/Services/FolioService.php` | **Modificar** | Invocar `AuditLogService` en reverse/addPayment |
| `src/Services/StoreService.php` | **Modificar** | Invocar `AuditLogService` en sell/charge |
| `src/Services/InvoiceService.php` | **Modificar** | Reordenar TX, sacar SUNAT fuera de TX |
| `src/Services/ExpenseService.php` | **Modificar** | Invocar `AuditLogService` |
| `src/Services/HousekeepingService.php` | **Modificar** | Invocar `AuditLogService` |
| `src/Repositories/ProductRepository.php` | **Modificar** | `updateStock` atómico con `stock - :qty WHERE stock >= :qty` |
| `src/Repositories/InvoiceSeriesRepository.php` | **Modificar** | `FOR UPDATE` + `current_number + 1` atómico |
| `src/Controllers/InvoiceController.php` | **Modificar** | `download()` retorna 404 si no existe |
| `routes/web.php` | **Modificar** | Añadir `/api/auth/logout`, `/api/auth/csrf-token` |
| `views/layouts/header.php` | **Modificar** | Meta-tag CSRF |
| `database/seeds/ProductionSeeder.php` | **Modificar** | Seed de permisos por rol |
| `tests/Feature/Flows/SecurityFlowsE2ETest.php` | **Crear** | Pruebas de auth, RBAC, CSRF, impersonación |
| `tests/Unit/Support/SessionTest.php` | **Crear** | Pruebas de sesión y CSRF |
| `tests/Unit/Support/AuthMiddlewareTest.php` | **Crear** | Pruebas de middleware |

---

## 11. Seguridad (amenazas mitigadas)

| Amenaza | Mitigación | Hallazgo auditoría |
|---------|-----------|-------------------|
| Acceso no autenticado | Middleware de auth en Router | C1 |
| Impersonación de usuario | `user_id` de sesión, no del request | C2 |
| CSRF | Token de sesión verificado en mutations | C3 |
| Race condition de stock | `UPDATE ... WHERE stock >= :qty` atómico | C4 |
| TX BD abierta durante HTTP externo | SUNAT fuera de transacción | C5 |
| Numeración fiscal duplicada | `SELECT ... FOR UPDATE` | C6 |
| Auditoría ausente | `AuditLogService::logAction()` en cada Service | C7 |
| Comprobantes falsos | `download()` retorna 404 si no existe | C8 |
| Cookies inseguras | `session_set_cookie_params` seguro | M7 |
| Usuario desactivado con sesión | Verificar `is_active` en cada request | — |

---

## 12. Orden de Implementación (TDD)

1. **Fase 1 — Session + AuthMiddleware:** Crear `Session.php`, `AuthMiddleware.php`. Tests primero: sin sesión → 401.
2. **Fase 2 — Eliminación de `user_id` del request:** Refactor de todos los controladores. Tests: impersonación bloqueada.
3. **Fase 3 — CSRF:** Generación y verificación. Tests: POST sin token → 419.
4. **Fase 4 — Stock atómico:** `ProductRepository::updateStock`. Tests: concurrencia simulada.
5. **Fase 5 — SUNAT fuera de TX + numeración atómica:** `InvoiceService`, `InvoiceSeriesRepository`. Tests: número único.
6. **Fase 6 — Auditoría conectada:** Inyectar `AuditLogService` en todos los Services. Tests: registro existe post-operación.
7. **Fase 7 — Anti-falsificación:** `InvoiceController::download` → 404. Tests: no genera archivo.
8. **Fase 8 — Cookies seguras + logout.** Tests: cookie segura, logout destruye sesión.
9. **Fase 9 — `make verify` verde.**
