# SPEC-0038: Rediseño de PDFs de Comprobantes y Voucher Térmico

## 1. Objetivo
Rediseñar por completo la generación de PDFs de comprobantes en `PdfGenerator` para que
el documento almacenado en disco sea profesional, con soporte correcto de caracteres
acentuados (UTF-8 → WinAnsiEncoding), wrap de texto, y layout limpio. Además, agregar
generación de **vouchers térmicos (80mm)** para impresoras de tickets, sin depender de
`window.print()` del navegador.

## 2. Contexto y Alcance

### Problema actual
- `PdfGenerator::generateInvoicePdf()` construye un PDF-1.4 crudo con operadores PDF
  manuales. Las fuentes Helvetica/Helvetica-Bold **no tienen** `/Encoding /WinAnsiEncoding`,
  por lo que caracteres acentuados (á, é, í, ó, ú, ñ, Ñ, ¿, ¡) **no renderizan**.
- El texto está hardcoded sin acentos: "Senores", "Direccion", "Descripcion" — incorrecto
  para un comprobante tributario peruano.
- No hay wrap de texto: descripciones largas se desbordan fuera del margen.
- No existe voucher térmico. No hay botón de imprimir voucher.
- El botón "Ver" ya carga el PDF almacenado en un iframe (correcto), pero el PDF en sí
  se ve horrible.

### Alcance
- **Dentro:** `PdfGenerator`, `PdfStream`, `InvoiceController`, `InvoiceService`,
  `StorageProvider`, vista `invoices.php`, JS `invoices.js`, rutas, migración de columna
  `voucher_url`, tests.
- **Fuera:** QR (ya corregido en SPEC-0037), XML SUNAT, lógica de negocio de facturación.

## 3. Reglas de Negocio e Invariantes
1. Todo comprobante emitido genera **dos archivos PDF**: el A4 completo (comprobante
   oficial) y el voucher térmico 80mm (para impresora de tickets).
2. Ambos PDFs se **almacenan en disco** al emitir el comprobante. El cliente solo
   muestra/abre lo almacenado, nunca regenera en runtime.
3. Los caracteres acentuados del español deben renderizarse correctamente en ambos PDFs.
4. El voucher térmico usa formato 80mm (~226pt de ancho), fondo blanco, texto negro,
  sin colores de fondo oscuros (impresoras térmicas no imprimen colores).
5. El botón "Imprimir Voucher" abre el PDF térmico en una nueva pestaña/ventana; no usa
  `window.print()` sobre la página HTML.

## 4. Modelo de Datos

### Migración: `invoices` table
```sql
ALTER TABLE invoices ADD COLUMN voucher_url VARCHAR(255) NULL AFTER pdf_url;
```

### StorageProvider
- Guarda 3 archivos: `{number}.pdf` (A4), `{number}.xml`, `{number}_voucher.pdf` (80mm).
- Retorna `voucher_url` además de `pdf_url` y `xml_url`.

## 5. Endpoints / Rutas

| Método | Path | Descripción |
|--------|------|-------------|
| GET | `/api/invoices` | Lista comprobantes (incluye `voucher_url`) |
| POST | `/api/invoices/issue` | Emite comprobante (genera PDF A4 + voucher térmico) |

El voucher se sirve via el Router estático (`/storage/comprobantes/{number}_voucher.pdf`)
igual que el PDF principal.

## 6. Criterios de Aceptación

### CA-1: PDF A4 con acentos correctos
- **Given** datos de comprobante con cliente "María José Ñahuincopa".
- **When** se invoca `PdfGenerator::generateInvoicePdf($data)`.
- **Then** el PDF contiene `/Encoding /WinAnsiEncoding` en los objetos de fuente.
- **And** el texto se convierte de UTF-8 a ISO-8859-1 antes de embeberse.

### CA-2: Wrap de texto en descripciones
- **Given** una descripción de ítem de 80+ caracteres.
- **When** se genera el PDF.
- **Then** el texto se envuelve en múltiples líneas dentro del ancho de columna.

### CA-3: Voucher térmico 80mm
- **Given** datos válidos de un comprobante.
- **When** se invoca `PdfGenerator::generateThermalVoucherPdf($data)`.
- **Then** el PDF tiene un ancho de página de ~226pt (80mm).
- **And** no contiene rectángulos de color de fondo (solo texto negro sobre blanco).
- **And** incluye el QR.
- **And** incluye los totales (base, IGV, total).

### CA-4: Almacenamiento de voucher al emitir
- **Given** una petición POST `/api/invoices/issue` con datos válidos.
- **When** se completa la emisión.
- **Then** se crean 3 archivos en `storage/comprobantes/`: `{number}.pdf`,
  `{number}.xml`, `{number}_voucher.pdf`.
- **And** la respuesta JSON incluye `voucher_url`.

### CA-5: Botón "Imprimir Voucher" en el frontend
- **Given** la página de comprobantes con al menos un comprobante emitido.
- **When** se renderiza la tabla.
- **Then** cada fila tiene un botón "🖨️ Voucher" que abre `voucher_url` en `_blank`.
- **And** no se usa `window.print()`.

### CA-6: Texto en español correcto
- **Given** cualquier comprobante generado.
- **When** se inspecciona el contenido del PDF.
- **Then** el texto contiene "Señores", "Dirección", "Descripción", "Impresión",
  "Representación" — con acentos correctos.

## 7. Casos Límite y Errores
- Si `voucher_url` es null (comprobante anterior a esta feature), el botón no se muestra.
- Si la descripción del ítem excede el ancho disponible, se trunca o envuelve.
- El voucher térmico no incluye secciones que no caben en 80mm (ej. dirección completa
  del hotel va en una sola línea truncada).

## 8. Seguridad
- Los PDFs se sirven con `Content-Type: application/pdf` y `Content-Disposition: inline`.
- El número de comprobante se sanea (regex `[^A-Za-z0-9_\-]`) antes de acceder al archivo.
- No se exponen datos sensibles adicionales en el voucher.

## 9. Transacciones
No aplica — la generación de PDFs ocurre fuera de la transacción de BD (como ya está
estructurado en `InvoiceService::issueInvoice`).
