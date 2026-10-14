# SPEC-0014: Auditoría Completa, Login por Roles, Redirecciones, Errores y Testing E2E de Cliente Web

- **Fecha:** 2026-08-21
- **Estado:** En Revisión (Esperando Aprobación del Usuario)
- **Módulo:** Autenticación/Login por Roles, Redirecciones, Manejo de Errores, Pruebas de Casos Límite y Cobertura E2E de Cliente Web

---

## 1. Objetivo

Implementar la vista cliente de Login (`views/pages/login.php`), la lógica de redirecciones por estado de sesión (`GET /login` vs rutas protegidas), el manejo de errores en frontera (credenciales inválidas, permisos denegados 403, validación de stock, habitaciones ocupadas) y la prueba navegada cliente/servidor del 100% de los módulos y formularios del sistema en `http://127.0.0.1:8000`.

---

## 2. Alcance

### Dentro del Alcance:
1. **Poblamiento de Usuarios Iniciales para los 4 Roles (`ProductionSeeder` & `DevelopmentSeeder`):**
   - **Administrador General:** `admin` / `Admin123!`
   - **Recepcionista:** `receptionist` / `Recep123!`
   - **Limpieza / Mucama:** `housekeeper` / `Clean123!`
   - **Contador / Auditor:** `accountant` / `Count123!`

2. **Vista de Login, Redirecciones y Control de Sesión:**
   - Vista [login.php](file:///home/style/Documents/lenguajes/php/vanilla/cs-hotel/views/pages/login.php) en la ruta `GET /login`.
   - **Redirección si no está autenticado:** Cualquier intento de ingresar a `/`, `/habitaciones`, `/tienda`, etc. sin sesión activa redirige a `/login`.
   - **Redirección si ya está autenticado:** Intentar ingresar a `/login` con sesión activa redirige al Dashboard `/`.
   - **Manejo de Errores en Login:** Intento con contraseña errónea o usuario inexistente muestra alerta flotante `"Usuario o contraseña incorrectos"`.
   - **Cierre de Sesión:** Botón de Logout redirige a `/login`.

3. **Manejo de Errores y Casos Límite en Formularios:**
   - **Error en Check-In:** Intentar hospedar una habitación que ya está en estado `occupied` o `cleaning` muestra mensaje de error sin romper el sistema.
   - **Error en Búsqueda DNI/RUC:** Documentos con longitud inválida muestran advertencia.
   - **Error en POS Tienda:** Intentar vender productos superando el stock disponible muestra alerta de stock insuficiente.
   - **Error en Arqueo de Caja:** Validaciones de importes numéricos.
   - **Control de Permisos RBAC (403):** Si un usuario con rol `housekeeper` intenta realizar acciones administrativas, se bloquea la acción con mensaje de permiso denegado.

4. **Testing Navegado E2E (`http://127.0.0.1:8000`):**
   - Servidor web local de prueba.
   - Ejecución de pruebas HTTP simulando interacción de cliente navegable sobre las 13 rutas y sus formularios.

---

## 3. Reglas de Negocio

1. **Sesión Segura (`Support\Session`):**
   - Uso de `session_start()`, almacenamiento seguro del id de usuario y token de sesión.
2. **Mensajes de Error Claros y descriptivos:**
   - Cero filtrados de trazas SQL o errores internos PHP al usuario. Respuestas controladas en formato JSON o alertas en UI.

---

## 4. Criterios de Aceptación (Given / When / Then)

### Criterio 1: Redirección por Estado de Sesión
- **Given:** Un usuario sin sesión iniciada.
- **When:** Intenta navegar a `http://127.0.0.1:8000/` o `http://127.0.0.1:8000/tienda`.
- **Then:** Es redirigido automáticamente a `http://127.0.0.1:8000/login`.

### Criterio 2: Login por Roles y Redirección al Panel
- **Given:** La pantalla de login `GET /login`.
- **When:** Ingresa el usuario `admin` y clave `Admin123!`.
- **Then:** Inicia sesión exitosamente y es redirigido a `/` (Dashboard).

### Criterio 3: Manejo de Errores de Validación
- **Given:** Formulario de Check-in o Venta POS.
- **When:** Se ingresan datos inválidos o stock insuficiente.
- **Then:** El sistema responde de manera controlada sin errores `500` y muestra la alerta correspondiente en la interfaz gráfica.

### Criterio 4: Verificación Completa `make verify`
- **Given:** El código implementado de login, middleware de sesión y vistas.
- **When:** Se ejecuta `make verify`.
- **Then:** Sintaxis PHP, PHPStan Nivel 9 y pruebas unitarias/feature en verde al 100%.
