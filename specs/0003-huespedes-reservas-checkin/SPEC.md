# SPEC-0003: Huéspedes, Consultas DNI/RUC y Reservas

- **Fecha:** 2026-08-21
- **Estado:** En Revisión (Esperando Aprobación del Usuario)
- **Módulo:** Maestro de Huéspedes, Integración JSONPE y Ciclo de Reservas

---

## 1. Objetivo

Establecer la estructura de datos, servicios y contratos para la gestión del maestro de huéspedes, la integración transparente con la API JSONPE para autocompletado de datos de DNI y RUC (con fallback manual inmediato ante fallas o falta de conexión), el registro de acompañantes por estancia y el manejo del ciclo de vida completo de reservas (Pendiente, Confirmada, Check-in, No-show y Cancelada).

---

## 2. Alcance

### Dentro del Alcance:
- Registro y edición del Maestro de Huéspedes (DNI, RUC, Nombres, Apellidos, Razón Social, Teléfono, Correo, Dirección).
- Búsqueda de huésped por documento, nombre o teléfono.
- Adaptador de Consulta DNI/RUC (`Support\DocumentLookup`) que consume la API JSONPE si `JSONPE_ENABLED=true`.
- Fallback a llenado manual transparente sin bloquear el formulario ante timeouts ($>2$ segundos), errores o falta de credenciales.
- Registro de acompañantes en una estancia (`stay_guests`).
- Reservas futuras con estado (`pending`, `confirmed`, `checked_in`, `no_show`, `cancelled`).
- Registro del adelanto de reserva abonado.
- Conversión de Reserva a Estancia Ocupada (Check-in de Reserva).

### Fuera del Alcance (Módulos posteriores):
- Apertura y cierre de caja registradora (Módulo 4).
- Generación de comprobantes de pago (Módulo 10).

---

## 3. Reglas de Negocio

1. **Unicidad de Documento:**
   - Cada huésped posee un tipo de documento (`DNI`, `RUC`, `CE`, `PASAPORTE`) y un número de documento único.

2. **Integración Transparente de Consulta DNI/RUC (JSONPE):**
   - Al pulsar el botón **Autocompletar**, el sistema realiza una llamada a la API externa JSONPE.
   - Si la API responde con éxito en menos de 2 segundos, el sistema llena automáticamente los campos de nombres, razón social y dirección.
   - Si la API falla, no responde, está desactivada (`JSONPE_ENABLED=false`) o el documento no es encontrado, el sistema no muestra alertas bloqueantes ni interrumpe la interfaz: el usuario completa el registro manualmente.

3. **Ciclo de Vida de Reservas:**
   - `pending`: Reserva creada sin pago inicial.
   - `confirmed`: Reserva con adelanto confirmado.
   - `checked_in`: El huésped llega; la reserva cambia a `checked_in` y se genera la estancia ocupada en la habitación asignada.
   - `no_show`: El huésped no llegó en el rango de gracia establecido.
   - `cancelled`: La reserva se anula voluntariamente.

4. **Inmutabilidad Financiera de Adelantos:**
   - Los adelantos de reserva se registran con su respectivo timestamp y usuario actuante para ser rendidos en la caja activa del momento.

---

## 4. Modelo de Datos (SQL DDL)

```sql
-- Maestro de Huéspedes
CREATE TABLE guests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_type ENUM('DNI', 'RUC', 'CE', 'PASAPORTE') NOT NULL DEFAULT 'DNI',
    document_number VARCHAR(20) NOT NULL UNIQUE,
    first_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NULL,
    company_name VARCHAR(150) NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(100) NULL,
    address TEXT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Acompañantes por Estancia
CREATE TABLE stay_guests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stay_id INT NOT NULL,
    guest_id INT NOT NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (stay_id) REFERENCES stays(id),
    FOREIGN KEY (guest_id) REFERENCES guests(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reservas de Habitaciones
CREATE TABLE reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    guest_id INT NOT NULL,
    room_tariff_id INT NOT NULL,
    reserved_check_in_at DATETIME NOT NULL,
    reserved_check_out_at DATETIME NOT NULL,
    advance_payment DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    status ENUM('pending', 'confirmed', 'checked_in', 'no_show', 'cancelled') NOT NULL DEFAULT 'pending',
    notes TEXT NULL,
    created_by_user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id),
    FOREIGN KEY (guest_id) REFERENCES guests(id),
    FOREIGN KEY (room_tariff_id) REFERENCES room_tariffs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 5. Endpoints / Métodos de Servicios

### Rutas HTTP
- `GET /api/guests/lookup?document_number=72819283` — Busca en la BD local o consulta la API JSONPE si se solicita autocompletado.
- `GET /api/guests` — Listado y búsqueda de huéspedes por término.
- `POST /api/guests` — Registra un nuevo huésped.
- `GET /api/reservations` — Listado de reservas activas/filtradas por fecha.
- `POST /api/reservations` — Crea una nueva reserva.
- `POST /api/reservations/{id}/checkin` — Ejecuta el Check-in de una reserva (convirtiéndola en estancia ocupada).
- `POST /api/reservations/{id}/cancel` — Cancela una reserva con motivo registrado.

---

## 6. Criterios de Aceptación (Given / When / Then)

### Criterio 1: Búsqueda y Autocompletado de Documento con Fallback
- **Given:** Un usuario ingresa el DNI `72819283`.
- **When:** El recepcionista presiona **Autocompletar**.
- **Then:** Si la API JSONPE está activa y responde, retorna los nombres formateados; si falla o está desactivada, retorna respuesta nula para permitir el llenado manual sin error.

### Criterio 2: Registro de Reserva y Garantía de Adelanto
- **Given:** Una habitación libre `201` y un huésped registrado.
- **When:** Se crea una reserva para mañana con adelanto de `S/ 20.00`.
- **Then:** La reserva se registra con estado `confirmed` y monto abonado `20.00`.

### Criterio 3: Conversion de Reserva a Check-in (Ocupación)
- **Given:** Una reserva confirmada en estado `confirmed` para la habitación `201`.
- **When:** El recepcionista ejecuta `POST /api/reservations/{id}/checkin`.
- **Then:** La reserva pasa a `checked_in`, la habitación pasa a `occupied`, y se genera la estancia activa vinculada al huésped titular.

---

## 7. Casos Límite y Errores

1. **Documento Duplicado:**
   - Si se intenta crear un huésped con un documento ya existente, se retorna el huésped ya registrado (`HTTP 200` o `HTTP 409` con referencia).
2. **Reserva Solapada:**
   - Si se intenta reservar una habitación en un horario que colisiona con otra reserva o estancia ocupada, la API retorna `HTTP 409 Conflict`.
3. **Timeout de API JSONPE:**
   - Definido a máximo 2.0 segundos mediante `curl` o HTTP client para evitar congelamientos de interfaz.

---

## 8. Transacciones PDO

- La conversión de reserva a check-in (`POST /api/reservations/{id}/checkin`) se ejecuta en una transacción PDO atómica:
  ```php
  $db->beginTransaction();
  // 1. Actualizar estado de reserva a 'checked_in'
  // 2. Actualizar estado de habitación a 'occupied'
  // 3. Crear registro en stays
  // 4. Registrar acompañantes en stay_guests
  $db->commit();
  ```
