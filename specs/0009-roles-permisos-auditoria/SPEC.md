# SPEC-0009: Usuarios, Roles, Permisos Granulares (RBAC) y Auditoría

- **Fecha:** 2026-08-21
- **Estado:** En Revisión (Esperando Aprobación del Usuario)
- **Módulo:** Gestión de Usuarios, Autenticación Segura, Control de Acceso por Roles/Permisos (RBAC) y Registro de Auditoría (Audit Logs)

---

## 1. Objetivo

Establecer la estructura de datos, servicios y utilidades para la autenticación de usuarios mediante `password_hash`, autorización de acciones por matriz granular de permisos (`role_permissions`), y el registro inmutable de auditoría (`audit_logs`) para toda operación financiera o crítica realizada en el sistema.

---

## 2. Alcance

### Dentro del Alcance:
- Gestión de Usuarios (`users`) con credenciales encriptadas mediante `password_hash($pw, PASSWORD_DEFAULT)` y `password_verify()`.
- Definición de Roles (`roles`) y Permisos Granulares (`permissions`, `role_permissions`).
- Guard / Evaluador de Permisos (`Support\PermissionGuard::can($user, $permission)`).
- Registro Inmutable de Auditoría (`audit_logs`) para eventos sensibles (descuentos en tarifas, reversos de cargos, transferencias de habitación, cierres de caja con desbalance).
- Endpoint de Autenticación (`POST /api/auth/login`) y Consulta de Auditoría (`GET /api/audit-logs`).

### Fuera del Alcance (Módulos posteriores):
- Emisión de Comprobantes Electrónicos SUNAT (Módulo 10).

---

## 3. Reglas de Negocio

1. **Seguridad de Credenciales:**
   - Queda estrictamente prohibido almacenar contraseñas en texto plano o con algoritmos MD5/SHA1. Se utiliza la función nativa de PHP `password_hash()`.

2. **Control de Acceso basado en Permisos Granulares (RBAC):**
   - Un usuario pertenece a un `role`. Un `role` posee 1 o más `permissions` asociados en `role_permissions`.
   - Si un usuario no posee el permiso requerido para una acción (ej: `folios.reverse`), la operación se bloquea inmediatamente lanzando `DomainException` ("Acceso denegado: permiso insuficiente").

3. **Auditoría Obligatoria de Eventos Sensibles:**
   - Toda acción que altere saldos, aplique descuentos no tarifados, revierta cargos, transfiera habitaciones o cierre cajas con descuadre genera de forma transparente una entrada inalterable en `audit_logs`.

---

## 4. Modelo de Datos (SQL DDL)

```sql
-- Roles de Usuario
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permisos Granulares
CREATE TABLE permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    key_name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Matriz Rol - Permisos
CREATE TABLE role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usuarios del Sistema
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bitácora Inmutable de Auditoría
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    entity_name VARCHAR(100) NOT NULL,
    entity_id INT NOT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 5. Endpoints / Métodos de Servicios

### Rutas HTTP
- `POST /api/auth/login` — Autentica usuario y verifica credenciales.
- `GET /api/users` — Lista usuarios registrados.
- `POST /api/users` — Registra un nuevo usuario con rol asignado.
- `GET /api/audit-logs` — Consulta el registro de auditoría con filtros por acción/usuario.

---

## 6. Criterios de Aceptación (Given / When / Then)

### Criterio 1: Autenticación Segura y Rechazo de Credenciales Inválidas
- **Given:** Un usuario registrado con hash bcrypt.
- **When:** Intenta iniciar sesión con la contraseña correcta.
- **Then:** La función `password_verify` confirma la validez y retorna datos del usuario sin exponer el hash. Si la clave es errónea, retorna `HTTP 401 Unauthorized`.

### Criterio 2: Verificación Granular de Permiso (RBAC)
- **Given:** Un usuario recepcionista cuyo rol no posee el permiso `folios.reverse`.
- **When:** Intenta realizar una anulación de cargo en un Folio.
- **Then:** El sistema detiene la ejecución retornando `HTTP 403 Forbidden`.

### Criterio 3: Registro de Auditoría en Transacciones Sensibles
- **Given:** Un recepcionista autoriza un descuento en tarifa con motivo ingresado.
- **When:** Se crea la estancia.
- **Then:** Se inserta automáticamente un registro en `audit_logs` con `action = 'OCCUPY_ROOM_DISCOUNT'`, guardando los valores previos y nuevos.

---

## 7. Casos Límite y Errores

1. **Usuario Inactivo (`is_active = 0`):**
   - El intento de login rechaza con `HTTP 403 Forbidden` ("Cuenta de usuario desactivada").
2. **Username o Email Duplicado:**
   - Crear usuario existente retorna `HTTP 409 Conflict`.

---

## 8. Transacciones PDO

- La creación de usuario con asignación inicial de rol/permisos y el registro de eventos de auditoría se aseguran dentro de transacciones PDO.
