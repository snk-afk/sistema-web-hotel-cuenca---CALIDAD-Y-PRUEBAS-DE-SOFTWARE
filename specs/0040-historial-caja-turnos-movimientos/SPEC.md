# SPEC-0040: Historial de Caja — Turnos Cerrados, Detalle y Movimientos

- **Fecha:** 2026-08-23
- **Estado:** Aprobado (Implementación en progreso)
- **Módulo:** Historial de Turnos de Caja y Trazabilidad de Movimientos
- **Dependencia:** SPEC-0005 (Backend de Caja — Implementado), SPEC-0030 (Interfaz de Caja — Implementada)

---

## 1. Objetivo

Resolver la limitación actual del módulo `/caja`: una vez cerrado un turno, sus
movimientos y arqueo quedan **inaccesibles** desde la interfaz. Actualmente solo existe
`GET /api/cashbox/current` (turno activo del usuario). No hay forma de:

- Ver la lista de turnos cerrados (historial).
- Abrir el detalle de un turno cerrado para revisar sus movimientos.
- Filtrar por fecha, responsable o estado de arqueo.
- Auditar descuadres (faltantes/sobrantes) históricos.

Este spec agrega las **capas de servicio, controller, rutas y frontend** necesarias para
consultar y visualizar el historial completo de turnos de caja, **sin modificar el
schema de base de datos** (las tablas `cashbox_shifts` y `cash_movements` ya contienen
toda la información requerida).

---

## 2. Alcance

### Dentro del Alcance:
- Endpoint `GET /api/cashbox/shifts` — Lista todos los turnos (abiertos y cerrados) con
  filtros opcionales por estado, responsable y rango de fechas.
- Endpoint `GET /api/cashbox/shift?id=N` — Detalle completo de un turno específico,
  incluyendo todos sus movimientos, arqueo, diferencia y estado de balance.
- Método `CashboxShiftRepository::findAll(...)` con filtros opcionales (prepared
  statements, sin `SELECT *`).
- Método `CashboxService::listShifts(...)` y `CashboxService::getShiftById(int $id)`.
- Vista `/caja` ampliada con una sección de **historial de turnos** debajo del turno
  activo: tabla con resumen (ID, responsable, apertura, cierre, estado, arqueo,
  diferencia) y modal de **detalle de turno cerrado** con la tabla de movimientos.
- JavaScript para cargar historial, aplicar filtros y abrir el modal de detalle.
- **Exportación del historial a Excel (CSV)** con BOM UTF-8 para compatibilidad con
  Microsoft Excel, respetando los filtros aplicados (estado, responsable, fechas).
- **Exportación del historial a PDF** con diseño corporativo (header azul oscuro,
  tabla de turnos, resumen de arqueo con semáforo de faltantes/sobrantes), alineado al
  patrón visual de `PdfGenerator::generateExecutiveReportPdf`.
- Pruebas unitarias (Service + Repository), de integración (Repository contra BD de
  test) y de feature (Controller + ViewController).

### Fuera del Alcance:
- Modificaciones al schema de base de datos (no se requiere migración).
- Edición o anulación de turnos cerrados (los turnos cerrados son inmutables per
  SPEC-0005 §3.4).

---

## 3. Reglas de Negocio e Invariantes

1. **Inmutabilidad de turnos cerrados:**
   - El historial es de **solo lectura**. No se permite editar, reabrir ni eliminar
     turnos cerrados. Los movimientos de un turno cerrado tampoco se modifican.
   - Cualquier corrección histórica requiere un movimiento inverso justificado en un
     turno nuevo (per SPEC-0005 §3.4).

2. **Visibilidad del historial:**
   - Todos los usuarios autenticados con acceso a `/caja` pueden ver el historial de
     turnos (propios y de otros recepcionistas), para fines de auditoría y
     trazabilidad.
   - El filtrado por `user_id` permite acotar a un responsable específico.

3. **Orden y paginación:**
   - El historial se ordena por `id DESC` (turno más reciente primero).
   - Se aplica un límite por defecto de 100 turnos para evitar consultas pesadas; los
     filtros de fecha acotan el resultado.

4. **Detalle de turno incluye:**
   - Datos del turno: ID, responsable (`user_id`), apertura, cierre, fondo inicial,
     efectivo esperado, efectivo declarado, diferencia, estado (`open`/`closed`),
     `balance_status` (`balanced`/`shortage`/`surplus`/`pending`), notas.
   - Lista completa de movimientos del turno (ya provista por `findById` existente).

5. **Turno inexistente:**
   - Si se solicita `GET /api/cashbox/shift?id=N` y el turno no existe, se retorna
     `HTTP 404` con mensaje genérico.

6. **Filtros de fecha opcionales:**
   - `from` y `to` son opcionales. Si se omiten, no se filtran por fecha.
   - El filtro de fecha se aplica sobre `opened_at` (fecha de apertura del turno).

---

## 4. Modelo de Datos

**No se modifica el schema.** Las tablas existentes soportan todos los requisitos:

```text
cashbox_shifts
  id, user_id, opened_at, closed_at, initial_cash, expected_cash,
  real_cash_declared, difference, status, balance_status, notes,
  created_at, updated_at

cash_movements
  id, cashbox_shift_id, type, category, concept, amount, payment_method,
  reference_id, created_by_user_id, created_at
```

El método `findById(int $id)` ya carga los movimientos asociados vía
`CashMovementRepository::findByShiftId()`. No se requiere JOIN en el listado de turnos
(el listado trae solo metadatos del turno; el detalle trae movimientos).

---

## 5. Endpoints / Rutas

### 5.1 `GET /api/cashbox/shifts`

Lista de turnos de caja (historial + activos).

- **Query params (todos opcionales):**
  - `status` — `open` | `closed`. Si se omite, trae todos.
  - `user_id` — Filtra por responsable. Entero positivo.
  - `from` — Fecha de apertura desde (`YYYY-MM-DD`).
  - `to` — Fecha de apertura hasta (`YYYY-MM-DD`).
- **Output 200:**
  ```json
  {
    "status": "success",
    "data": [
      {
        "id": 5,
        "user_id": 2,
        "opened_at": "2026-08-23 08:00:00",
        "closed_at": "2026-08-23 20:00:00",
        "initial_cash": 100.00,
        "expected_cash": 140.00,
        "real_cash_declared": 138.00,
        "difference": -2.00,
        "status": "closed",
        "balance_status": "shortage",
        "notes": "Faltante de S/ 2.00"
      }
    ]
  }
  ```
- **Nota:** El listado **no** incluye el array de movimientos (se obtienen vía el
  endpoint de detalle) para mantener la respuesta liviana.

### 5.2 `GET /api/cashbox/shift?id=N`

Detalle completo de un turno específico, con todos sus movimientos.

- **Query params:**
  - `id` — Requerido. Entero positivo. ID del turno.
- **Output 200:**
  ```json
  {
    "status": "success",
    "data": {
      "id": 5,
      "user_id": 2,
      "opened_at": "2026-08-23 08:00:00",
      "closed_at": "2026-08-23 20:00:00",
      "initial_cash": 100.00,
      "expected_cash": 140.00,
      "real_cash_declared": 138.00,
      "difference": -2.00,
      "status": "closed",
      "balance_status": "shortage",
      "notes": "Faltante de S/ 2.00",
      "movements": [ { "id": 12, "type": "income", ... } ]
    }
  }
  ```
- **Output 400:** Falta `id` o no es entero válido.
  ```json
  { "status": "error", "message": "El parámetro id es obligatorio." }
  ```
- **Output 404:** Turno no encontrado.
  ```json
  { "status": "error", "message": "Turno de caja no encontrado." }
  ```

### 5.3 `GET /api/cashbox/export/csv`

Exporta el historial de turnos a CSV (compatible con Excel, con BOM UTF-8).

- **Query params (todos opcionales, igual que `GET /api/cashbox/shifts`):**
  - `status`, `user_id`, `from`, `to`.
- **Output 200:** `Content-Type: text/csv; charset=utf-8`,
  `Content-Disposition: attachment; filename="historial_caja.csv"`.
  El CSV incluye una fila de encabezado y una fila por turno, con columnas:
  `ID, Responsable (user_id), Apertura, Cierre, Fondo Inicial, Efectivo Esperado,
  Efectivo Declarado, Diferencia, Estado, Arqueo, Notas`.
- **Patrón:** Reutiliza `CsvExporter::generateCsv()` (ya existente) con BOM UTF-8.

### 5.4 `GET /api/cashbox/export/pdf`

Exporta el historial de turnos a PDF corporativo A4.

- **Query params (todos opcionales, igual que `GET /api/cashbox/shifts`):**
  - `status`, `user_id`, `from`, `to`.
- **Output 200:** `Content-Type: application/pdf`,
  `Content-Disposition: inline; filename="historial_caja.pdf"`.
- **Estructura del PDF:**
  - Header corporativo (azul oscuro) con título "Historial de Turnos de Caja" y período.
  - Tabla de turnos con columnas: ID, Responsable, Apertura, Cierre, Fondo, Esperado,
    Declarado, Diferencia, Estado, Arqueo.
  - Resumen de arqueo al pie: total de turnos, cantidad de cuadrados/faltantes/sobrantes,
    monto acumulado de diferencias.
  - Footer institucional.
- **Patrón:** Nuevo método `PdfGenerator::generateCashboxHistoryPdf()` que construye un
  PDF-1.4 con `PdfStream`, alineado al estilo de `generateExecutiveReportPdf()`.

### Rutas a registrar en `routes/web.php`:

```php
$router->get('/api/cashbox/shifts', [CashboxController::class, 'history']);
$router->get('/api/cashbox/shift', [CashboxController::class, 'show']);
$router->get('/api/cashbox/export/csv', [CashboxController::class, 'exportCsv']);
$router->get('/api/cashbox/export/pdf', [CashboxController::class, 'exportPdf']);
```

---

## 6. Capas (layering)

Siguiendo §3 de AGENTS.md:

| Capa | Responsabilidad |
|------|-----------------|
| **Controller** (`CashboxController::history`, `::show`, `::exportCsv`, `::exportPdf`) | Leer query params (`$_GET`), validar/castear, llamar al Service, armar JSON response o emitir CSV/PDF con headers HTTP correspondientes. |
| **Service** (`CashboxService::listShifts`, `::getShiftById`, `::exportCsv`, `::exportPdf`) | Orquestar llamadas al Repository, aplicar validaciones de negocio (rango de fechas, límites). Para exportaciones, delegar el formateo a `CsvExporter`/`PdfGenerator`. |
| **Repository** (`CashboxShiftRepository::findAll`) | Ejecutar SQL con prepared statements y filtros dinámicos (placeholders generados para `IN`/`WHERE`). Mapear filas a `CashboxShift` (sin movimientos en el listado). |
| **Model** (`CashboxShift`) | Sin cambios. Ya contiene todos los campos. |
| **Support** (`CsvExporter`, `PdfGenerator`) | `CsvExporter::generateCsv()` ya existe (reutilizable). `PdfGenerator::generateCashboxHistoryPdf()` es nuevo, construye el PDF corporativo del historial. |

- El Controller no toca PDO ni SQL.
- El Service no conoce HTTP.
- El Repository no decide reglas de negocio.

---

## 7. Criterios de Aceptación (Given / When / Then)

### CA-1: Listar historial de turnos
- **Given:** Existen 3 turnos cerrados y 1 abierto en la base de datos.
- **When:** Se llama `GET /api/cashbox/shifts` (sin filtros).
- **Then:** Se retorna `HTTP 200` con un array de 4 turnos ordenados por `id DESC`,
  cada uno con sus metadatos pero **sin** el array `movements`.

### CA-2: Filtrar historial por estado
- **Given:** Existen turnos abiertos y cerrados.
- **When:** Se llama `GET /api/cashbox/shifts?status=closed`.
- **Then:** Se retorna `HTTP 200` con **solo** los turnos en estado `closed`.

### CA-3: Filtrar historial por responsable
- **Given:** Existen turnos de los usuarios 1 y 2.
- **When:** Se llama `GET /api/cashbox/shifts?user_id=2`.
- **Then:** Se retorna `HTTP 200` con **solo** los turnos donde `user_id = 2`.

### CA-4: Filtrar historial por rango de fechas
- **Given:** Existen turnos abiertos en distintas fechas.
- **When:** Se llama `GET /api/cashbox/shifts?from=2026-08-01&to=2026-08-31`.
- **Then:** Se retorna `HTTP 200` con **solo** los turnos cuya `opened_at` cae en el
  rango especificado (inclusive).

### CA-5: Detalle de turno con movimientos
- **Given:** Existe el turno ID 5 con 2 movimientos asociados.
- **When:** Se llama `GET /api/cashbox/shift?id=5`.
- **Then:** Se retorna `HTTP 200` con el turno completo **y** su array `movements` con
  2 elementos.

### CA-6: Detalle de turno inexistente
- **Given:** No existe un turno con ID 999.
- **When:** Se llama `GET /api/cashbox/shift?id=999`.
- **Then:** Se retorna `HTTP 404` con `{ "status": "error", "message": "Turno de caja
  no encontrado." }`.

### CA-7: Detalle sin parámetro id
- **Given:** Cualquier estado de la base de datos.
- **When:** Se llama `GET /api/cashbox/shift` (sin `?id=`).
- **Then:** Se retorna `HTTP 400` con `{ "status": "error", "message": "El parámetro id
  es obligatorio." }`.

### CA-8: Vista /caja muestra sección de historial
- **Given:** Existen turnos cerrados en la base de datos.
- **When:** El usuario navega a `/caja`.
- **Then:** Además del panel de turno activo (o bienvenida), la vista muestra una
  sección "Historial de Turnos" con una tabla de turnos (ID, responsable, apertura,
  cierre, estado, arqueo, diferencia) y un botón "Ver Detalle" por fila.

### CA-9: Modal de detalle de turno cerrado
- **Given:** El usuario está en `/caja` y hay turnos en el historial.
- **When:** Hace clic en "Ver Detalle" de un turno cerrado.
- **Then:** Se abre un modal que muestra los datos del arqueo (fondo inicial, esperado,
  declarado, diferencia con semáforo), las notas y la tabla completa de movimientos de
  ese turno.

### CA-10: Exportar historial a CSV
- **Given:** Existen 3 turnos cerrados en la base de datos.
- **When:** Se llama `GET /api/cashbox/export/csv`.
- **Then:** Se retorna `HTTP 200` con `Content-Type: text/csv`, BOM UTF-8 al inicio, una
  fila de encabezados y 3 filas de datos (una por turno).

### CA-11: Exportar historial a CSV con filtros
- **Given:** Existen turnos de varios responsables y estados.
- **When:** Se llama `GET /api/cashbox/export/csv?status=closed&user_id=2`.
- **Then:** El CSV contiene **solo** los turnos cerrados del usuario 2.

### CA-12: Exportar historial a PDF
- **Given:** Existen turnos cerrados en la base de datos.
- **When:** Se llama `GET /api/cashbox/export/pdf`.
- **Then:** Se retorna `HTTP 200` con `Content-Type: application/pdf`, el contenido
  empieza con `%PDF-1.4` y termina con `%%EOF`, y contiene el título "Historial de
  Turnos de Caja".

### CA-13: Exportar historial a PDF vacío
- **Given:** No existen turnos que cumplan los filtros.
- **When:** Se llama `GET /api/cashbox/export/pdf?from=2030-01-01`.
- **Then:** Se retorna `HTTP 200` con un PDF válido que indica "No hay turnos para el
  período/filtros seleccionados" (no se retorna error ni PDF corrupto).

### CA-14: Botones de exportación en la vista
- **Given:** El usuario navega a `/caja` y hay turnos en el historial.
- **When:** La sección de historial se renderiza.
- **Then:** Se muestran botones "Exportar CSV" y "Exportar PDF" que disparan la
  descarga del historial aplicando los filtros seleccionados.

---

## 8. Casos Límite y Errores

1. **Filtros combinados:**
   - `?status=closed&user_id=2&from=2026-08-01&to=2026-08-31` debe aplicar todos los
     filtros en conjunción (AND).
2. **Fechas inválidas:**
   - Si `from` o `to` no tienen formato `YYYY-MM-DD` válido, se ignoran (no se filtra
     por fecha) en lugar de error. El Service valida el formato con un regex simple.
3. **Sin resultados:**
   - Si los filtros no producen ningún turno, se retorna `HTTP 200` con `data: []`
     (array vacío), no error.
4. **Límite de resultados:**
   - El listado aplica `LIMIT 100` por defecto para evitar consultas pesadas.
5. **Turno abierto en el historial:**
   - Los turnos abiertos también aparecen en el historial (con `closed_at = null` y
     `status = open`), para que el recepcionista vea su turno activo en contexto.
6. **Parámetro `user_id` inválido:**
   - Si `user_id` no es entero positivo, se ignora el filtro.
7. **Parámetro `status` inválido:**
   - Si `status` no es `open` ni `closed`, se ignora el filtro (trae todos).

---

## 9. Seguridad

1. **Autenticación:**
   - Ambos endpoints requieren sesión autenticada (`Session::requireAuth()`), igual que
     el resto de `/api/cashbox/*`.
2. **CSRF:**
   - Al ser endpoints `GET` (solo lectura), no requieren token CSRF (los `GET` están
     exentos per el middleware existente). No cambian estado del servidor.
3. **Inyección SQL:**
   - Todos los filtros se pasan vía prepared statements con placeholders nombrados.
     Nunca se concatena input en SQL. Los valores de `status` se validan contra un
     allowlist (`['open', 'closed']`) antes de usarse.
4. **No exposición de datos sensibles:**
   - No se exponen contraseñas ni datos de usuario más allá del `user_id` (que ya es
     visible en los turnos existentes).
5. **Validación de input en la frontera:**
   - El Controller valida y castea todos los query params antes de pasarlos al Service.

---

## 10. Transacciones

- No se requieren transacciones. Los endpoints de historial y detalle son de **solo
  lectura** (`SELECT`). No modifican estado ni requieren atomicidad.

---

## 11. Archivos a Modificar / Crear

| Archivo | Acción |
|---------|--------|
| `src/Repositories/CashboxShiftRepository.php` | **Modificar** — Agregar método `findAll(?string $status, ?int $user_id, ?string $from, ?string $to, int $limit = 100): array` |
| `src/Services/CashboxService.php` | **Modificar** — Agregar `listShifts(...)` y `getShiftById(int $id): ?CashboxShift` |
| `src/Controllers/CashboxController.php` | **Modificar** — Agregar métodos `history()` y `show()` |
| `routes/web.php` | **Modificar** — Registrar `GET /api/cashbox/shifts` y `GET /api/cashbox/shift` |
| `views/pages/cashbox.php` | **Modificar** — Agregar sección de historial y modal de detalle |
| `public/assets/js/pages/cashbox.js` | **Modificar** — Agregar carga de historial, filtros y modal de detalle |
| `public/assets/css/pages/cashbox.css` | **Modificar** — Estilos para tabla de historial y modal de detalle |
| `tests/Integration/Repositories/CashboxShiftRepositoryTest.php` | **Modificar** — Pruebas de `findAll` con filtros |
| `tests/Unit/Services/CashboxServiceTest.php` | **Modificar** — Pruebas de `listShifts` y `getShiftById` |
| `tests/Feature/Controllers/CashboxControllerTest.php` | **Modificar** — Pruebas de `history()` y `show()` |
| `tests/Feature/Controllers/ViewControllerTest.php` | **Modificar** — Aserciones de la sección de historial en `/caja` |

---

## 12. Plan de Implementación (TDD)

1. **Tests primero (rojo):**
   - `CashboxShiftRepositoryTest::testFindAllReturnsAllShiftsOrderedDesc`
   - `CashboxShiftRepositoryTest::testFindAllFiltersByStatus`
   - `CashboxShiftRepositoryTest::testFindAllFiltersByUserId`
   - `CashboxShiftRepositoryTest::testFindAllFiltersByDateRange`
   - `CashboxShiftRepositoryTest::testFindAllReturnsEmptyArrayWhenNoMatches`
   - `CashboxServiceTest::testListShiftsDelegatesToRepository`
   - `CashboxServiceTest::testGetShiftByIdReturnsShiftWithMovements`
   - `CashboxServiceTest::testGetShiftByIdReturnsNullWhenNotFound`
   - `CashboxControllerTest::testHistoryReturnsListOfShifts`
   - `CashboxControllerTest::testHistoryFiltersByStatus`
   - `CashboxControllerTest::testShowReturnsShiftDetailWithMovements`
   - `CashboxControllerTest::testShowReturns404WhenNotFound`
   - `CashboxControllerTest::testShowReturns400WhenIdMissing`
   - `ViewControllerTest::testCashboxRendersHistorySection`
2. **Implementación (verde):**
   - `CashboxShiftRepository::findAll(...)`
   - `CashboxService::listShifts(...)` y `CashboxService::getShiftById(int $id)`
   - `CashboxController::history()` y `CashboxController::show()`
   - Rutas en `routes/web.php`
3. **Frontend:**
   - Sección de historial en `cashbox.php`
   - Lógica JS en `cashbox.js`
   - Estilos en `cashbox.css`
4. **Verificación:**
   - `make verify` en verde (lint + phpstan nivel 9 + phpunit)
5. **Aprobación de commit** → commit atómico `feat(cashbox): add shift history and
   detail with movements (SPEC-0040)`
