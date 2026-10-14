# SPEC-0038: Flujo Integral de Comprobantes, Vouchers Térmicos e Impresión Directa

## 1. Objetivo
Unificar y perfeccionar la experiencia de emisión, visualización e impresión de comprobantes de pago (Boleta, Factura, Nota de Venta) y vouchers térmicos de 80mm en todas las áreas operativas del hotel (Recepción/Check-out, Tienda POS y Módulo de Facturación), asegurando que el cliente consuma directamente los archivos generados y almacenados en el servidor, con soporte para comandos de impresión térmica directa (ESC/POS).

## 2. Contexto y Alcance
- **Módulos Afectados:**
  - `src/Services/InvoiceService.php`, `src/Controllers/InvoiceController.php`, `src/Controllers/RoomController.php`.
  - `src/Support/EscPosFormatter.php` (Nuevo formateador binario de comandos ESC/POS).
  - Frontend: `public/assets/js/pages/invoices.js`, `public/assets/js/pages/store.js`, `public/assets/js/components/drawer.js`, `views/pages/invoices.php`.
- **Alcance Funcional:**
  1. **Visualización Directa del Voucher Almacenado:** En el modal de visualización, el visor por defecto debe ser el Voucher Térmico (`_voucher.pdf`) de 80mm almacenado, con toggle para ver el PDF A4 oficial.
  2. **Emisión y Presentación Automática en Tienda POS:** Al completar una venta directa en tienda con comprobante, se retorna la información del comprobante emitido y se despliega el voucher para impresión/entrega inmediata.
  3. **Emisión de Comprobante en Checkout de Habitación:** Permitir que el recepcionista elija el tipo de comprobante (Boleta / Factura / Nota) al hacer checkout, emitiendo el comprobante oficial con el detalle de la estancia/consumos y desplegando el voucher térmico.
  4. **Formateador ESC/POS para Impresión Térmica:** Proveer un endpoint `/api/invoices/{id}/escpos` y utilidades para generar los bytes binarios estándar (corte de papel `GS V 66 0`, texto centrado/enfatizado, apertura de cajón `ESC p 0 25 250`).

## 3. Reglas de Negocio e Invariantes
1. **Consumo de Archivos Físicos:** El frontend nunca debe recrear o redibujar el contenido del comprobante en HTML; siempre debe consumir las rutas `/storage/comprobantes/..._voucher.pdf` o `.pdf` previamente almacenadas.
2. **Estructura SUNAT Obligatoria:** Todo comprobante (Boleta B001, Factura F001) debe incluir RUC emisor, numeración correlativa, desglose IGV, código QR oficial y leyenda de autorización.
3. **Atocimidad Financiera:** La emisión de comprobantes en checkout o tienda POS debe sincronizarse con el estado del folio y el turno de caja abierta.
4. **Impresión Térmica Silenciosa:** El sistema debe suministrar la cadena ESC/POS para integración con impresoras térmicas en red o USB.

## 4. Endpoints y Rutas
- `GET /api/invoices/{id}/escpos`: Retorna el payload binario/base64 formateado en ESC/POS con corte de papel y comando de apertura de gaveta.
- `POST /api/rooms` (action `checkout`): Admite parámetros opcionales `document_type`, `customer_document_type`, `customer_document_number`, `customer_name` para emitir comprobante automático si hay liquidación o consumos.

## 5. Criterios de Aceptación

### Escenario 1: Visualización del voucher térmico almacenado por defecto
- **Given** un comprobante emitido con ID `#1` y su archivo `_voucher.pdf` en `storage/comprobantes/`.
- **When** el usuario hace clic en "Ver" en la tabla de comprobantes.
- **Then** el modal carga directamente la URL del voucher térmico en el iframe, permitiendo alternar al PDF A4 si se requiere.

### Escenario 2: Emisión y apertura de voucher al vender en Tienda POS
- **Given** productos en el carrito de tienda y pago confirmado con Boleta/Factura.
- **When** se envía `POST /api/store/sales`.
- **Then** la respuesta contiene la URL del comprobante/voucher y el frontend abre el modal de voucher inmediatamente.

### Escenario 3: Generación de comandos binarios ESC/POS
- **Given** un comprobante registrado con ítems y totales.
- **When** se solicita `GET /api/invoices/{id}/escpos`.
- **Then** se recibe un payload con comandos ESC/POS válidos que incluyen cabecera, tabla de ítems, totales, texto legal y bytes de corte de papel.

## 6. Seguridad y Estándares
- Validación estricta de tipos (`declare(strict_types=1);`).
- PHPStan nivel 9 sin advertencias.
- Pruebas unitarias para `EscPosFormatter` y pruebas de integración para el endpoint ESC/POS.
