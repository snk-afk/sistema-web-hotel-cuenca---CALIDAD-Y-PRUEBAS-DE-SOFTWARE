# SPEC-0023: Directorio Maestro de Huéspedes, CRM Hotelero, Historial de Estancias y Control de Perfiles

- **Fecha:** 2026-08-21
- **Estado:** En Revisión (Esperando Aprobación del Usuario)
- **Módulo:** Maestro de Huéspedes (`/huespedes`): CRM Hotelero, Padrón Central e Historial de Estancias

---

## 1. Objetivo

Transformar `/huespedes` en un módulo completo de CRM y Padrón Maestro Hotelero que permita:
1. **Directorio Central de Huéspedes:** Registro, edición y búsqueda instantánea de clientes particulares (DNI, Pasaporte, CE) y empresas (RUC, Razón Social).
2. **Historial de Estancias & Consumos:** Visualizar la ficha completa de cada cliente con el detalle de todas sus visitas pasadas, habitaciones ocupadas, fechas y monto total acumulado gastado en el hotel (LTV).
3. **Control de Perfiles y Notas Operativas:** Clasificar clientes con notas internas (Cliente Frecuente, VIP, Observaciones especiales o alertas de conducta/pago).
4. **Sincronización Bidireccional con Check-In:** Conexión en tiempo real con el selector del Dashboard para agilizar el ingreso con autocompletado de datos.

---

## 2. Alcance y Arquitectura

### 2.1 Backend (`App\Controllers`, `App\Services`, `App\Repositories`):
- **Repositorio de Huéspedes (`GuestRepository`):**
  - Métodos `findAll()`, `findById()`, `findByDocument()`, `create()`, `update()`.
  - Método `getStaysHistoryByGuestId(int $guest_id)` para obtener el historial de visitas con datos de estancia, habitación y folio.
- **Servicio de Huéspedes (`GuestService`):**
  - `getGuestsCatalog(?string $search = null)`: catálogo con conteo de estancias y total facturado.
  - `getGuestProfile(int $guest_id)`: información detallada del huésped con su historial de hospedaje.
  - `saveGuest(array $data)`: validación de tipos de documento, formato y unicidad.
- **Controladores y Rutas (`GuestController`, `ViewController`, `routes/web.php`):**
  - `GET /huespedes` (renderizado completo de la vista con catálogo inicial y métricas).
  - `GET /api/guests` (búsqueda y catálogo JSON).
  - `GET /api/guests/{id}` (ficha detallada e historial de estancias).
  - `POST /api/guests` (crear y actualizar huéspedes).

### 2.2 Frontend (`views/pages/guests.php`, `public/assets/js/pages/guests.js`, `public/assets/css/pages/guests.css`):
- **Tarjetas de Métricas:** Total huéspedes, Clientes frecuentes/VIP, Visitas totales y Facturación acumulada.
- **Buscador Reactivo:** Búsqueda en vivo por nombre, DNI/RUC, teléfono o notas.
- **Tabla Maestra:** Lista detallada de clientes con chips de documento, contacto, visitas y monto total.
- **Modal 1 (Registrar / Editar Huésped):** Formulario con validación DNI (8 dígitos) / RUC (11 dígitos), nombres, teléfono, correo, dirección y notas.
- **Modal 2 (Ficha Histórica del Huésped):** Historial con tabla de estancias (Fechas ingreso/salida, Habitación, Categoría, Tarifa, Total pagado).

---

## 3. Criterios de Aceptación (Given / When / Then)

### Criterio 1: Búsqueda y Registro de Huésped
- **Given:** El usuario se encuentra en `/huespedes`.
- **When:** Abre "➕ Nuevo Huésped" y registra DNI `75849302`, Nombre `Claudia Salazar`, Teléfono `987654321`.
- **Then:** El cliente se guarda en la base de datos y aparece en la tabla maestro y en el padrón de Check-In.

### Criterio 2: Visualización de Ficha e Historial de Estancias
- **Given:** Un cliente que se ha hospedado en 1 o más habitaciones.
- **When:** El usuario hace clic en "📜 Ficha / Historial".
- **Then:** El modal muestra los datos del cliente, el total histórico gastado y el desglose de todas sus visitas pasadas.

### Criterio 3: Edición de Datos y Notas
- **Given:** Un cliente existente.
- **When:** Se edita su teléfono y se añade una nota "Cliente VIP - Cama matrimonial".
- **Then:** Los datos se actualizan y se reflejan en el sistema.

### Criterio 4: Verificación Integral `make verify`
- **Given:** Todo el código implementado con pruebas unitarias y de integración.
- **When:** Se ejecuta `make verify`.
- **Then:** Lint, PHPStan nivel 9 y PHPUnit pasan al 100% en verde.
