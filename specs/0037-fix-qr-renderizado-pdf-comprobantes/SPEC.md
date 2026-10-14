# SPEC-0037: Corrección de Renderizado de Código QR en Comprobantes PDF

## 1. Objetivo
Corregir el renderizado de la imagen del código QR en los comprobantes de pago PDF generados por `PdfGenerator::generateInvoicePdf()` para que no se muestre como un bloque negro sólido y sea un código QR legible y escaneable.

## 2. Contexto y Alcance
- **Archivo afectado:** `src/Support/PdfGenerator.php`
- **Causa raíz:** La librería `Endroid\QrCode` genera imágenes PNG con paleta de color indexada (palette-based / 8-bit). La función `imagecolorat()` en imágenes indexadas retorna el índice de la paleta (ej. `0` o `1`) en lugar del entero de 24 bits RGB (`0xRRGGBB`). Al aplicar desplazamiento de bits `($rgb >> 16) & 0xFF`, todos los canales resultaban en `0x00`, escribiendo un cuadro negro continuo en el objeto XObject del PDF.
- **Alcance:**
  - Conversión de la imagen a TrueColor (`imagepalettetotruecolor($img)`) antes del muestreo de píxeles en `PdfGenerator::buildPngImageObject()`.
  - Regeneración del comprobante `storage/comprobantes/F001-00000001.pdf`.
  - Pruebas unitarias que validen que los bytes del stream de imagen contienen píxeles no negros (RGB blanco `255, 255, 255` y negro `0, 0, 0` según el patrón del QR).

## 3. Reglas de Negocio e Invariantes
1. Todo comprobante emitido en formato PDF debe contener un código QR válido, legible y con contraste correcto (módulos negros sobre fondo blanco).
2. El QR debe contener la cadena SUNAT estructurada (`RUC|TIPO|SERIE-NUMERO|TOTAL|DOC_CLIENTE|FECHA`).
3. El archivo PDF generado debe ser un PDF-1.4 válido sin dependencias externas pesadas ni llamadas a APIs externas.

## 4. Criterios de Aceptación

### Escenario 1: Generación de objeto de imagen QR con canales RGB válidos
- **Given** una imagen PNG de QR indexada o truecolor.
- **When** se procesa mediante `PdfGenerator::buildPngImageObject()`.
- **Then** el stream comprimido resultante debe contener píxeles con valores blancos (`\xFF\xFF\xFF`) y negros (`\x00\x00\x00`), evitando que todos los píxeles sean negros.

### Escenario 2: Generación integral de comprobante PDF con QR escaneable
- **Given** datos válidos de una factura/boleta.
- **When** se invoca `PdfGenerator::generateInvoicePdf($data)`.
- **Then** el PDF resultante incluye el objeto XObject `/QR1` embebido con dimensiones y stream de imagen válidos.

## 5. Seguridad y Rendimiento
- Procesamiento en memoria sin escribir archivos temporales en disco innecesarios.
- Manejo estricto de memoria GD liberando recursos si es necesario.
