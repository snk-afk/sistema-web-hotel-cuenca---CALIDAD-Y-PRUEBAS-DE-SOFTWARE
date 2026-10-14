# SPEC-0027: Interfaz Completa de Tienda POS (`/tienda`)

- **Fecha:** 2026-08-22
- **Estado:** Borrador (Esperando Aprobación del Usuario)
- **Módulo:** Interfaz Frontend de Punto de Venta, Catálogo de Productos, Carrito y Ventas
- **Dependencia:** SPEC-0006 (Backend de Tienda POS — Implementado)

---

## 1. Objetivo

Construir la **interfaz completa y funcional** del Punto de Venta / Tienda POS (`/tienda`) para que el recepcionista pueda:

1. **Ver el catálogo** de productos activos en un grid visual con nombre, precio, stock y alerta de stock bajo.
2. **Agregar productos al carrito** con control de cantidad, subtotales dinámicos y total acumulado.
3. **Procesar ventas directas** al contado (vía `POST /api/store/sales`).
4. **Cargar consumos a una habitación** ocupada (vía `POST /api/store/charge-to-room`), seleccionando la habitación destino.
5. **Registrar nuevos productos** al catálogo con modal de creación (vía `POST /api/products`).
6. **Ver métricas** de la tienda: total de productos, productos con stock bajo, y valor del inventario.

> **Nota:** La lógica de backend (Models, Repositories, Services, Controllers, APIs) ya está 100% implementada en SPEC-0006. Este spec cubre la **capa de presentación** completa.

---

## 2. Alcance

### Dentro del Alcance:
- Reescritura completa de `views/pages/store.php` con métricas, catálogo grid, carrito interactivo y modales.
- JavaScript `public/assets/js/pages/store.js` con lógica de carrito, procesamiento de ventas, carga a habitación y CRUD de productos.
- CSS `public/assets/css/pages/store.css` con dark theme coherente.
- Pruebas Feature para verificar renderizado correcto.

### Fuera del Alcance:
- Modificaciones al backend de la tienda (ya implementado).
- Facturación SUNAT de ventas (módulo 10).

---

## 3. Reglas de Negocio e Invariantes de la Vista

1. **Catálogo dinámico vía API:**
   - Los productos se cargan desde `GET /api/products`.
   - Solo se muestran productos activos (`is_active = 1`).
   - Productos con `stock <= min_stock` muestran alerta visual de stock bajo.

2. **Carrito de compras en memoria (JS):**
   - Al hacer clic en "Agregar" un producto se añade al carrito con cantidad 1.
   - Si ya existe, se incrementa la cantidad (sin exceder el stock disponible).
   - El total del carrito se recalcula dinámicamente.
   - Se puede modificar la cantidad o eliminar ítems del carrito.

3. **Dos modalidades de venta:**
   - **Venta Directa POS:** Requiere turno de caja abierto. Envía `POST /api/store/sales` con `items` y `payment_method`.
   - **Cargar a Habitación:** Requiere seleccionar una habitación ocupada. Envía `POST /api/store/charge-to-room` con `room_id` e `items`.

4. **Creación de productos:**
   - Modal con formulario: nombre, SKU (opcional), precio venta, precio costo, stock inicial, stock mínimo, descripción.
   - Envía `POST /api/products`.

---

## 4. Componentes de la Vista

### 4.1 Header
```
🛒 Punto de Venta (POS) — Tienda del Hotel
Venta directa, carga a habitación y gestión de inventario.
[➕ Nuevo Producto]  [📊 Ir al Dashboard]
```

### 4.2 Métricas (3 tarjetas)
| Tarjeta | Icono | Dato |
|---------|-------|------|
| Productos Activos | 📦 | Cantidad de productos en catálogo |
| Stock Bajo | ⚠️ | Productos con stock <= min_stock |
| Valor del Inventario | 💰 | Σ (sale_price × stock) de todos los productos |

### 4.3 Layout POS (2 columnas)
- **Columna izquierda (catálogo):** Grid de product cards con nombre, precio, stock, botón "➕ Agregar".
- **Columna derecha (carrito):** Lista de ítems del carrito, controles de cantidad (+/-/eliminar), total, botones de acción.

### 4.4 Carrito de Compras
| Elemento | Contenido |
|----------|-----------|
| Línea de ítem | Nombre, cant × precio = subtotal, botones +/−/🗑️ |
| Total | S/ XX.XX (actualizado dinámicamente) |
| Botón 1 | `💵 Venta Directa (Contado)` → procesa venta POS |
| Botón 2 | `🏨 Cargar a Habitación` → abre selector de habitación ocupada |

### 4.5 Modal: Selección de Habitación para Cargo
- Título: `🏨 Seleccionar Habitación para Cargar Consumo`
- Lista/select de habitaciones actualmente ocupadas (cargadas de `/api/rooms`).
- Muestra: número de hab, huésped, saldo pendiente actual.
- Botón confirmar.

### 4.6 Modal: Crear Producto
- Campos: nombre*, SKU, precio venta*, precio costo, stock inicial, stock mínimo, descripción.
- Envía `POST /api/products`.

---

## 5. Endpoints Consumidos (ya existentes)

| Método | Ruta | Uso en la Vista |
|--------|------|-----------------|
| `GET` | `/api/products` | Cargar catálogo de productos activos |
| `POST` | `/api/products` | Crear nuevo producto |
| `POST` | `/api/store/sales` | Procesar venta directa al contado |
| `POST` | `/api/store/charge-to-room` | Cargar productos a habitación ocupada |
| `GET` | `/api/rooms` | Obtener habitaciones ocupadas para selector |

---

## 6. Criterios de Aceptación (Given / When / Then)

### CA-1: La vista renderiza el POS completo
- **Given:** El usuario navega a `/tienda`.
- **When:** La página carga.
- **Then:** Se muestra el header `Punto de Venta (POS)`, las métricas, el grid de catálogo y el carrito vacío.

### CA-2: Catálogo muestra productos activos
- **Given:** Existen 3 productos activos en la base de datos.
- **When:** La vista carga los datos vía `/api/products`.
- **Then:** Se muestran 3 tarjetas de producto con nombre, precio y stock.

### CA-3: Agregar producto al carrito
- **Given:** El catálogo muestra "Coca-Cola 500ml" a S/ 4.00 con stock 10.
- **When:** El usuario hace clic en "➕ Agregar".
- **Then:** El carrito muestra 1x Coca-Cola = S/ 4.00 y el total es S/ 4.00.

### CA-4: Venta directa al contado
- **Given:** El carrito tiene 2x Coca-Cola (S/ 8.00) y hay turno de caja abierto.
- **When:** El usuario hace clic en "Venta Directa".
- **Then:** Se envía `POST /api/store/sales`, el carrito se vacía y se muestra mensaje de éxito.

### CA-5: Cargar consumo a habitación
- **Given:** El carrito tiene productos y hay habitaciones ocupadas.
- **When:** El usuario hace clic en "Cargar a Habitación" y selecciona la habitación 201.
- **Then:** Se envía `POST /api/store/charge-to-room` con `room_id` correspondiente, carrito se vacía.

### CA-6: Crear nuevo producto
- **Given:** El usuario hace clic en "Nuevo Producto".
- **When:** Llena el formulario y envía.
- **Then:** Se envía `POST /api/products`, el catálogo se recarga y muestra el nuevo producto.

### CA-7: Alerta de stock bajo visible
- **Given:** Un producto tiene `stock = 2` y `min_stock = 5`.
- **When:** El catálogo se renderiza.
- **Then:** La tarjeta del producto muestra un indicador visual de stock bajo (⚠️).

---

## 7. Casos Límite

1. **Carrito vacío al intentar vender:** Se bloquea el botón y muestra mensaje.
2. **Sin turno de caja al vender:** Se muestra error `409` del API.
3. **Stock insuficiente:** Se muestra error del API `409`.
4. **Sin habitaciones ocupadas al cargar:** El selector muestra mensaje vacío.

---

## 8. Seguridad

- `htmlspecialchars()` en todos los datos renderizados server-side.
- Los datos de productos se re-validan en backend (cantidad > 0, stock suficiente).
- El `user_id` se envía como 1 (hasta que exista autenticación real).

---

## 9. Archivos a Crear/Modificar

| Archivo | Acción |
|---------|--------|
| `views/pages/store.php` | **Reescribir** — POS completo con catálogo, carrito y modales |
| `public/assets/js/pages/store.js` | **Crear** — Carrito, ventas, carga a habitación, CRUD productos |
| `public/assets/css/pages/store.css` | **Crear** — Estilos POS dark theme |
| `tests/Feature/Controllers/ViewControllerTest.php` | **Modificar** — Test para verificar renderizado de `/tienda` |
