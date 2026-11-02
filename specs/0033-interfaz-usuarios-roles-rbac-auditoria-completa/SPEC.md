# SPEC-0033: Interfaz Completa de Usuarios, Roles (RBAC) y Seguridad (`/usuarios`)

- **Fecha:** 2026-08-22
- **Estado:** Borrador (Esperando Aprobación del Usuario)
- **Módulo:** Gestión de Cuentas de Usuario, Asignación de Roles y Permisos
- **Dependencia:** SPEC-0009 (Backend de Usuarios y RBAC — Implementado)

---

## 1. Objetivo

Construir la **interfaz completa y profesional** del módulo de Usuarios y Roles (`/usuarios`) para que el administrador pueda:

1. **Monitorear Métricas de Personal y Accesos**:
   - Total de Usuarios Registrados.
   - Usuarios Activos.
   - Administradores y Recepcionistas.
2. **Consultar y Filtrar la Lista de Usuarios**:
   - Tabla de usuarios con Username, Nombre Completo, Correo Electrónico, Rol asignado (con badges distintivos) y Estado (`🟢 Activo` / `🔴 Inactivo`).
   - Buscador reactivo por nombre, username o rol.
3. **Crear Nuevos Usuarios con Modal**:
   - Nombre completo, Username, Correo, Contraseña segura y Selección de Rol (`Administrador`, `Recepcionista`, `Personal de Limpieza`, `Mantenimiento`).
   - Envía a `POST /api/users`.

---

## 2. Alcance

### Dentro del Alcance:
- Vista `views/pages/users.php` rediseñada con métricas, tabla de usuarios, buscador reactivo y modal de creación.
- JavaScript `public/assets/js/pages/users.js` con consumo de `GET /api/users` y `POST /api/users`.
- CSS `public/assets/css/pages/users.css` con diseño dark theme coherente.
- Pruebas automatizadas actualizadas.

### Fuera del Alcance:
- Login/Sesión multi-tenant (módulo 9 auth ya cuenta con backend).

---

## 3. Reglas de Negocio e Invariantes

1. **Seguridad de Contraseñas:**
   - La contraseña se procesa en el backend usando `password_hash()` con `PASSWORD_DEFAULT` (Bcrypt/Argon2).
2. **Unicidad de Username:**
   - El `username` debe ser único en el sistema.
3. **Roles Estándar:**
   - `1: Administrador`
   - `2: Recepcionista`
   - `3: Limpieza / Housekeeping`
   - `4: Mantenimiento`

---

## 4. Componentes UI de la Vista

### 4.1 Header
```
👤 Usuarios del Sistema, Permisos (RBAC) y Seguridad
Gestión de credenciales, roles operativos y control de accesos al sistema.
[➕ Nuevo Usuario]
```

### 4.2 Métricas (3 tarjetas)
| Tarjeta | Icono | Descripción |
|---------|-------|-------------|
| Usuarios Totales | 👥 | Total de cuentas registradas |
| Usuarios Activos | 🟢 | Usuarios con `is_active = 1` |
| Roles Disponibles | 🛡️ | Cantidad de perfiles de acceso |

### 4.3 Tabla de Usuarios
| Columna | Contenido |
|---------|-----------|
| ID | ID de usuario |
| Usuario | `username` en negrita |
| Nombre Completo | Nombres y apellidos |
| Correo | Email de contacto |
| Rol | Badge con color (Administrador, Recepcionista, etc.) |
| Estado | Badge `🟢 Activo` o `🔴 Inactivo` |

### 4.4 Modal: Crear Usuario (`#modal-create-user`)
- Campos: Nombre completo, Nombre de usuario (login), Correo electrónico, Rol del sistema, Contraseña inicial.

---

## 5. Criterios de Aceptación (Given / When / Then)

### CA-1: Renderizado Completo de `/usuarios`
- **Given:** El usuario navega a `/usuarios`.
- **When:** La página carga.
- **Then:** Se muestran las 3 métricas, la barra de búsqueda, la tabla de usuarios y el botón de nuevo usuario.

### CA-2: Crear Nuevo Usuario Exitosamente
- **Given:** El usuario abre `#modal-create-user`.
- **When:** Completa el formulario con datos válidos y confirma.
- **Then:** Se envía a `POST /api/users`, la tabla se actualiza mostrando al nuevo usuario y se muestra toast de éxito.

---

## 6. Archivos a Modificar / Crear

| Archivo | Acción |
|---------|--------|
| `views/pages/users.php` | **Reescribir** — Vista completa con métricas, tabla y modal de creación |
| `public/assets/js/pages/users.js` | **Crear** — Lógica de listado, filtrado y creación de usuarios |
| `public/assets/css/pages/users.css` | **Crear** — Estilos para el módulo de usuarios |
| `tests/Feature/Controllers/ViewControllerTest.php` | **Modificar** — Aserciones para `/usuarios` |
