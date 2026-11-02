# SPEC-0028: Modal de Checkout y Pago Completo en Tienda POS (`/tienda`)

- **Fecha:** 2026-08-22
- **Estado:** Borrador (Esperando Aprobación del Usuario)
- **Módulo:** Punto de Venta (POS) — Flujo de Cobro, Comprobantes SUNAT y Métodos de Pago
- **Dependencia:** SPEC-0027 (Tienda POS UI)

---

## 1. Objetivo

Alinear la experiencia de cobro en la **Tienda POS (`/tienda`)** con el estándar operativo del Dashboard (`/`), incorporando:
1. Un **modal interactivo de checkout para Venta Directa**:
   - Selección de **Tipo de Comprobante**: Nota de Venta, Boleta Electrónica, Factura Electrónica (con campos dinámicos para RUC de 11 dígitos y Razón Social).
   - Selección de **Método de Pago**: 💵 Efectivo, 🟣 Yape / Plin, 💳 Tarjeta, 🏦 Transferencia.
   - Campo opcional para **N° de Operación / Referencia**.
2. **Manejo inteligente del estado de Caja Chica**:
   - Si no hay turno de caja abierto para el usuario, mostrar una alerta clara con botón de acceso directo para aperturar caja en `/caja`.
3. **Manejo de errores amigable y robusto**:
   - Mensajes precisos de validación (stock insuficiente, error de servidor, respuesta JSON de error del backend) evitando mensajes genéricos como "Error de conexión".

---

## 2. Alcance

### Dentro del Alcance:
- Actualización de `views/pages/store.php` con el modal `#modal-store-checkout` y alerta de estado de turno de caja.
- Actualización de `public/assets/js/pages/store.js`:
  - `openDirectSaleModal()`: Abre el modal mostrando el resumen total a cobrar.
  - `toggleStoreInvoiceFields()`: Muestra/oculta RUC y Razón Social cuando se selecciona "FACTURA".
  - `checkCashboxStatus()`: Valida si hay turno abierto consultando `GET /api/cashbox/current`.
  - `executeDirectSale()`: Envía la venta a `POST /api/store/sales` con `payment_method` seleccionado y datos de comprobante.
- Pruebas unitarias/feature actualizadas.

### Fuera del Alcance:
- Timbrado real XML/CDR con la API de SUNAT (cubierto en módulo 10).

---

## 3. Reglas de Negocio e Invariantes

1. **Selección de Factura:**
   - Si el usuario selecciona `FACTURA`, el campo RUC es obligatorio y debe tener 11 dígitos numéricos.
2. **Métodos de Pago:**
   - Acepta `cash`, `yape`, `plin`, `card`, `transfer`.
   - Si es `cash`, se registra el ingreso en la caja chica activa. Si es digital/tarjeta, no afecta el efectivo físico de caja pero sí el balance general.
3. **Validación de Caja Chica:**
   - Si el usuario intenta cobrar y no tiene turno abierto, se le avisa inmediatamente sin hacer llamadas fallidas y se le ofrece ir a `/caja`.

---

## 4. Componentes UI en la Vista

### 4.1 Modal de Cobro Venta Directa (`#modal-store-checkout`)
- **Resumen:** `Total a Cobrar: S/ XX.XX`
- **Selector de Comprobante:**
  - `NOTA_VENTA` — Nota de Venta Interna (default)
  - `BOLETA` — Boleta de Venta Electrónica
  - `FACTURA` — Factura Electrónica
- **Campos condicionales de Factura:** RUC (11 dígitos) y Razón Social.
- **Selector de Método de Pago:** Efectivo, Yape/Plin, Tarjeta, Transferencia.
- **Referencia/Operación:** Input de texto opcional.
- **Botón:** `💵 Procesar Venta y Cobrar (S/ XX.XX)`

---

## 5. Criterios de Aceptación (Given / When / Then)

### CA-1: Modal de Cobro con Métodos y Comprobantes
- **Given:** El carrito tiene 1 o más productos agregados.
- **When:** El usuario hace clic en `💵 Venta Directa (Contado)`.
- **Then:** Se abre `#modal-store-checkout` mostrando el total a cobrar, el selector de comprobantes y el selector de método de pago.

### CA-2: Factura muestra campos de RUC y Razón Social
- **Given:** El modal de checkout está abierto.
- **When:** El usuario cambia el comprobante a `Factura Electrónica`.
- **Then:** Aparecen los campos de RUC (máx 11 dígitos) y Razón Social.

### CA-3: Procesamiento exitoso de la Venta
- **Given:** Carrito con total S/ 10.00 y turno de caja abierto.
- **When:** El usuario selecciona "Yape", ingresa referencia "123456" y confirma.
- **Then:** Se ejecuta la venta, el stock se descuenta, el carrito se limpia, se cierra el modal y se muestra toast de éxito.

### CA-4: Alerta clara si no hay turno de caja
- **Given:** No hay turno de caja abierto.
- **When:** El usuario intenta procesar la venta.
- **Then:** El sistema notifica: "Debe abrir un turno de caja antes de realizar ventas" con enlace/botón a `/caja`.

---

## 6. Archivos a Modificar

| Archivo | Modificación |
|---------|-------------|
| `views/pages/store.php` | Agregar modal de checkout de tienda y alerta de caja |
| `public/assets/js/pages/store.js` | Lógica de checkout modal, comprobantes, validación de caja y fetch |
| `tests/Feature/Controllers/ViewControllerTest.php` | Assertions para el modal de checkout de tienda |
