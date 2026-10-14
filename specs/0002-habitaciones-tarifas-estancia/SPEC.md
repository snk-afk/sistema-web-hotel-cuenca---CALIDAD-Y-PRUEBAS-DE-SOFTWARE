# SPEC-0002: Habitaciones, Categorías, Tarifas y Control de Estancia

- **Fecha:** 2026-08-21
- **Estado:** En Revisión (Esperando Aprobación del Usuario)
- **Módulo:** Operación de Habitaciones y Tarifas

---

## 1. Objetivo

Establecer la estructura de datos, reglas de negocio y contratos para gestionar las categorías de habitaciones, la matriz de tarifas dinámicas, el control de estados de las habitaciones en tiempo real (Libre, Ocupada, Limpieza, Mantenimiento, Reservada), el cálculo del temporizador de estancia con precisión de segundos, el registro de extensiones de tiempo y los ajustes manuales de tarifa con registro de auditoría.

---

## 2. Alcance

### Dentro del Alcance:
- Gestión CRUD de Categorías de Habitación (Simple, Matrimonial, Jacuzzi, etc.).
- Gestión CRUD de Habitaciones asociadas a su categoría.
- Configuración de la Matriz de Tarifas (12h, 23h, Noche, Promoción, Personalizada).
- Transición estricta de estados de habitación.
- Cálculo de hora exacta de salida (`check_in_at + duration_seconds + extensions_seconds`).
- Cálculo en tiempo real de tiempo restante (`HH:MM:SS`) o tiempo transcurrido en vencimiento.
- Registro de extensiones de estancia (generando cargos independientes sin modificar la tarifa inicial).
- Registro de sobreprecio o descuento manual con justificación obligatoria y traza de usuario.

### Fuera del Alcance (Módulos posteriores):
- Apertura/cierre de caja (Módulo 4).
- Registro del huésped y autocompletado DNI/RUC (Módulo 3).
- Cobro en efectivo/Yape del adelanto o saldo (Módulo 4).

---

## 3. Reglas de Negocio

1. **Invariante de Estado de Habitación:**
   - Una habitación en estado `free` (Libre) solo puede pasar a `occupied` (Ocupada), `maintenance` (Mantenimiento) o `reserved` (Reservada).
   - Una habitación en estado `occupied` solo puede pasar a `cleaning` (Limpieza tras checkout) o mantenerse ocupada.
   - Una habitación en estado `cleaning` solo puede pasar a `free` o `maintenance`.
   - Una habitación en estado `maintenance` está bloqueada para asignación hasta ser liberada por un usuario autorizado.

2. **Cálculo Exacto de Vencimiento:**
   - La fecha/hora de salida prevista se calcula como:
     $$\text{expected\_check\_out\_at} = \text{check\_in\_at} + \text{duracion\_tarifa\_segundos} + \sum \text{extensiones\_segundos}$$
   - El temporizador calcula la diferencia en segundos entre $\text{expected\_check\_out\_at}$ y el tiempo actual del servidor (`NOW()`).

3. **Inmutabilidad de Tarifa Inicial:**
   - Al hospedar, la tarifa seleccionada fija el costo base del hospedaje.
   - Las extensiones posteriores agregan cargos adicionales al folio sin modificar el registro de la tarifa inicial.

4. **Auditoría de Modificación Manual de Precio:**
   - Si el precio aplicado difiere del precio de lista de la tarifa seleccionada, el sistema exige ingresar un motivo obligatorio.
   - Se almacena: `base_price`, `applied_price`, `discount_reason`, `created_by_user_id`, `created_at`.

---

## 4. Modelo de Datos (SQL DDL)

```sql
-- Categorías de Habitación
CREATE TABLE room_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Habitaciones
CREATE TABLE rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_category_id INT NOT NULL,
    number VARCHAR(20) NOT NULL UNIQUE,
    status ENUM('free', 'occupied', 'cleaning', 'maintenance', 'reserved') NOT NULL DEFAULT 'free',
    floor VARCHAR(20) NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (room_category_id) REFERENCES room_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Matriz de Tarifas
CREATE TABLE room_tariffs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_category_id INT NOT NULL,
    name VARCHAR(100) NOT NULL, -- Ej: "12 Horas", "23 Horas", "Noche"
    duration_hours INT NOT NULL, -- Duración en horas (ej: 12, 23)
    price DECIMAL(10, 2) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (room_category_id) REFERENCES room_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Estancias Activas / Históricas
CREATE TABLE stays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    room_tariff_id INT NOT NULL,
    guest_id INT NULL,
    check_in_at DATETIME NOT NULL,
    expected_check_out_at DATETIME NOT NULL,
    actual_check_out_at DATETIME NULL,
    base_price DECIMAL(10, 2) NOT NULL,
    applied_price DECIMAL(10, 2) NOT NULL,
    discount_reason VARCHAR(255) NULL,
    status ENUM('active', 'completed', 'transferred', 'cancelled') NOT NULL DEFAULT 'active',
    created_by_user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id),
    FOREIGN KEY (room_tariff_id) REFERENCES room_tariffs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Extensiones de Estancia
CREATE TABLE stay_extensions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stay_id INT NOT NULL,
    added_hours INT NOT NULL,
    added_price DECIMAL(10, 2) NOT NULL,
    created_by_user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (stay_id) REFERENCES stays(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 5. Endpoints / Métodos de Servicios

### Rutas HTTP
- `GET /api/rooms` — Retorna el listado completo del mapa de habitaciones con su estado y temporizador en tiempo real.
- `GET /api/rooms/{id}` — Retorna el detalle de una habitación y su estancia activa.
- `POST /api/rooms/{id}/occupy` — Inicia una ocupación de habitación.
- `POST /api/rooms/{id}/extend` — Registra una extensión de horas.
- `POST /api/rooms/{id}/checkout` — Finaliza la estancia y pasa la habitación a estado `cleaning`.
- `PATCH /api/rooms/{id}/status` — Cambia manualmente el estado (ej: de `cleaning` a `free`).

---

## 6. Criterios de Aceptación (Given / When / Then)

### Criterio 1: Cambio de Estado a Ocupada y Cálculo de Vencimiento
- **Given:** Una habitación `102` de categoría `Matrimonial` en estado `free`.
- **When:** El recepcionista envía la solicitud de ocupación con la tarifa `12 Horas` (`S/ 45.00`) a las `10:00:00`.
- **Then:** La habitación pasa a estado `occupied`, se crea un registro en `stays` con `expected_check_out_at = 22:00:00`, y el API retorna `status: occupied` con temporizador calculando 43,200 segundos restantes.

### Criterio 2: Extensión de Estancia
- **Given:** Una estancia activa en la habitación `102` con `expected_check_out_at = 22:00:00`.
- **When:** El recepcionista registra una extensión de `+2 horas` con costo de `S/ 10.00`.
- **Then:** Se crea un registro en `stay_extensions`, `expected_check_out_at` se actualiza a `00:00:00` del día siguiente y el temporizador incrementa en 7,200 segundos.

### Criterio 3: Descuento / Modificación Manual de Precio Auditada
- **Given:** Una tarifa base de `S/ 60.00`.
- **When:** El recepcionista aplica un precio manual de `S/ 50.00` ingresando el motivo `"Cliente Frecuente"`.
- **Then:** El registro en `stays` guarda `base_price = 60.00`, `applied_price = 50.00` y `discount_reason = "Cliente Frecuente"`.

---

## 7. Casos Límite y Errores

1. **Intento de Ocupar una Habitación Ocupada / Mantenimiento:**
   - La API retorna `HTTP 409 Conflict` indicando que la habitación no está libre.
2. **Descuento sin Justificación:**
   - Si `applied_price < base_price` y no se envía `discount_reason`, retorna `HTTP 422 Unprocessable Entity`.
3. **Desconexión en Tiempo Real:**
   - Si Pusher no está activo, el endpoint `GET /api/rooms` responde con las marcas de tiempo servidor para que el frontend ejecute el temporizador localmente.

---

## 8. Seguridad

- Todos los endpoints requieren autenticación con sesión activa.
- Las modificaciones de precio requieren verificación de permisos de usuario (`can_change_prices` para descuentos).

---

## 9. Transacciones y PDO

- Las operaciones de ocupación (`POST /api/rooms/{id}/occupy`) se ejecutan dentro de una transacción PDO explícita:
  ```php
  $db->beginTransaction();
  // 1. Actualizar estado de habitación a 'occupied'
  // 2. Crear registro en stays
  // 3. Crear folio inicial (stub para Módulo 3)
  $db->commit();
  ```
