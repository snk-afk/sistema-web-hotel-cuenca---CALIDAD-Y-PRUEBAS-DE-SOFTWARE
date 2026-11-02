# SPEC-0050: Redirección de vistas UI sin sesión

- **Fecha:** 2026-09-07
- **Estado:** Aprobado — implementado y verificado
- **Módulo:** Router, middleware de autenticación y vistas web
- **Dependencias:** SPEC-0009, SPEC-0014 y SPEC-0036

## 1. Objetivo

Evitar que una persona sin sesión vea un error fatal al entrar a una vista web protegida. Las rutas UI deben enviarla a `/login`, mientras que las rutas API deben conservar respuestas JSON `401`.

## 2. Alcance

### Dentro del alcance

- Proteger las vistas UI autenticadas (`/`, `/habitaciones`, `/huespedes`, `/reservas`, `/folios`, `/tienda`, `/caja`, `/egresos`, `/limpieza`, `/usuarios`, `/comprobantes` y `/reportes`).
- Redirigir una petición UI sin sesión a `/login` con HTTP `302` y una ubicación fija.
- Mantener `/login`, `/api/status`, `/api/auth/login` y `/api/auth/csrf-token` como rutas públicas según su contrato actual.
- Mantener el rechazo JSON `401` para `/api/*` protegidas.
- Agregar pruebas unitarias y feature del comportamiento diferenciado UI/API.

### Fuera del alcance

- Cambios en credenciales, roles, permisos, CSRF, sesiones o esquema de base de datos.
- Parámetros de retorno (`return_url`) o redirecciones controladas por el cliente.
- Cambios visuales de la pantalla de login.
- Cambios en rutas inexistentes: deben continuar respondiendo `404`.

## 3. Reglas de negocio

1. Una vista UI protegida sin sesión nunca ejecuta su controlador ni servicios; redirige a `/login`.
2. Una ruta API protegida sin sesión nunca redirige a HTML; responde `401` JSON con el contrato vigente.
3. La redirección siempre usa el destino fijo `/login`; no se acepta una URL de redirección desde query string, body o header del cliente.
4. Una sesión válida conserva el comportamiento actual de la vista solicitada.
5. `/login` permanece accesible sin sesión para evitar bucles de redirección.

## 4. Modelo de datos

No hay cambios de esquema, migraciones ni seeds.

## 5. Contratos HTTP

| Método | Ruta | Sin sesión | Con sesión |
|---|---|---|---|
| `GET` | `/` y vistas UI protegidas | `302 Location: /login` | Renderiza la vista |
| `GET` | `/login` | Renderiza login | Mantiene comportamiento actual |
| `GET` | `/api/rooms` y APIs protegidas | `401 application/json` | Ejecuta el endpoint |
| `GET` | Ruta inexistente | `404` | `404` |

La respuesta de redirección no debe incluir trazas, SQL, mensajes internos ni contenido parcial de la vista.

## 6. Criterios de aceptación

### CA-1 — Raíz sin sesión redirige al login

- **Given:** Un cliente sin sesión.
- **When:** Solicita `GET /`.
- **Then:** Recibe `302`, `Location: /login`, y no se ejecuta `ViewController::dashboard()`.

### CA-2 — Vista UI sin sesión redirige al login

- **Given:** Un cliente sin sesión.
- **When:** Solicita cualquier vista UI protegida.
- **Then:** Recibe `302 Location: /login` y no se ejecuta lógica de negocio.

### CA-3 — Login permanece público

- **Given:** Un cliente sin sesión.
- **When:** Solicita `GET /login`.
- **Then:** Recibe `200` y el formulario de login.

### CA-4 — API sin sesión conserva contrato JSON

- **Given:** Un cliente sin sesión.
- **When:** Solicita una API protegida, por ejemplo `GET /api/rooms`.
- **Then:** Recibe `401 application/json` con el mensaje `No autenticado`, sin redirección.

### CA-5 — Sesión válida conserva acceso

- **Given:** Un cliente con una sesión de usuario activa.
- **When:** Solicita `GET /`.
- **Then:** La vista se procesa normalmente y no recibe redirección.

### CA-6 — Ruta inexistente sigue siendo 404

- **Given:** Un cliente con o sin sesión.
- **When:** Solicita una ruta no registrada.
- **Then:** Recibe `404` y no recibe `Location: /login`.

## 7. Casos límite y errores

- No se debe generar un bucle al solicitar `/login`.
- Un `HEAD` de una vista UI debe conservar la semántica de redirección sin ejecutar servicios.
- Si el cliente usa `Accept: application/json` contra una ruta UI, el alcance actual mantiene la redirección UI; no se crea un tercer contrato.
- Un fallo real de base de datos después de una sesión válida no se convierte en redirección de autenticación.

## 8. Seguridad

- El destino de redirección es constante y evita open redirect.
- No se exponen excepciones, trazas ni detalles internos al cliente.
- Las APIs no se debilitan: continúan requiriendo sesión, permisos y CSRF según SPEC-0036.
- No se modifica la configuración de cookies ni el almacenamiento de sesión.

## 9. Transacciones

No aplica. La decisión de autenticación ocurre antes de ejecutar servicios o abrir transacciones.
