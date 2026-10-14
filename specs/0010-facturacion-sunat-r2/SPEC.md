# SPEC-0010: Facturación, Comprobantes SUNAT (Simulado/Real) y Almacenamiento Cloudflare R2

- **Fecha:** 2026-08-21
- **Estado:** En Revisión (Esperando Aprobación del Usuario)
- **Módulo:** Emisión de Comprobantes (Boletas, Facturas, Notas de Venta), Desglose de IGV 18%, Adaptador SUNAT y Storage Provider R2

---

## 1. Objetivo

Establecer la estructura de datos, servicios y contratos para la emisión de comprobantes de pago de hospedaje y tienda (Boleta Electrónica `B001`, Factura Electrónica `F001`, Nota de Venta Ticket `NV01`), con desglose normativo de IGV 18%, serie y correlativo automatizado, integración híbrida con SUNAT (`simulated` vs `real`) y proveedor de almacenamiento para archivos PDF/XML (`local` vs `Cloudflare R2`).

---

## 2. Alcance

### Dentro del Alcance:
- Gestión de Series y Correlativos (`invoice_series`) por tipo de comprobante (`BOLETA`, `FACTURA`, `NOTA_VENTA`).
- Cálculo automático de impuestos peruanos:
  $$\text{Subtotal Gravado} = \frac{\text{Total}}{1.18}$$
  $$\text{IGV (18\%)} = \text{Total} - \text{Subtotal Gravado}$$
- Registro de Comprobantes (`invoices`, `invoice_items`) vinculados a la Estancia (`stay_id`) o Venta de Tienda (`store_sale_id`).
- Adaptador Híbrido SUNAT (`Support\SunatProvider`):
  - `simulated`: Genera representación simulada con código de respuesta CDR exitoso sin requerir firma digital remota en pruebas.
  - `real`: Consume endpoint de facturador electrónico configurado en `SUNAT_ENDPOINT` y `SUNAT_TOKEN`.
- Adaptador de Almacenamiento (`Support\StorageProvider`):
  - `local`: Guarda PDF/XML en `storage/app/comprobantes/`.
  - `r2`: Sube archivos a bucket Cloudflare R2 retornando la URL pública en CDN.
- Cierre definitivo del Folio de estancia al emitir el comprobante final.

### Fuera del Alcance:
- Declaración jurada mensual ante contador externo (proceso administrativo manual).

---

## 3. Reglas de Negocio

1. **Correlativo Ininterrumpido:**
   - La numeración de correlativos (`sequence_number`) por serie es estricta e incremental (`1, 2, 3...`).
   - Se asegura mediante bloqueos PDO en la transacción de emisión.

2. **Cálculo Normativo de IGV (18% Perú):**
   - Todo comprobante electrónico calcula el valor venta gravado y la cuota tributaria IGV 18%.
   - Las Notas de Venta internas desglosan opcionalmente el valor de ticket informativo.

3. **Inmutabilidad Fiscal:**
   - Un comprobante emitido en serie oficial (`B001` o `F001`) no se puede modificar ni eliminar.

4. **Almacenamiento y Recuperación:**
   - Los comprobantes guardan el Hash de Firma / CDR y la URL de descarga directa del documento PDF/XML.

---

## 4. Modelo de Datos (SQL DDL)

```sql
-- Series y Correlativos de Facturación
CREATE TABLE invoice_series (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_type ENUM('BOLETA', 'FACTURA', 'NOTA_VENTA') NOT NULL,
    series VARCHAR(10) NOT NULL UNIQUE,
    current_number INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Encabezado de Comprobantes Emitidos
CREATE TABLE invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stay_id INT NULL,
    store_sale_id INT NULL,
    document_type ENUM('BOLETA', 'FACTURA', 'NOTA_VENTA') NOT NULL,
    series VARCHAR(10) NOT NULL,
    number INT NOT NULL,
    full_number VARCHAR(20) NOT NULL UNIQUE,
    customer_document_type ENUM('DNI', 'RUC', 'CE', 'PASAPORTE') NOT NULL,
    customer_document_number VARCHAR(20) NOT NULL,
    customer_name VARCHAR(150) NOT NULL,
    customer_address TEXT NULL,
    taxable_amount DECIMAL(10, 2) NOT NULL,
    igv_amount DECIMAL(10, 2) NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    sunat_status ENUM('simulated', 'accepted', 'rejected', 'pending') NOT NULL DEFAULT 'pending',
    sunat_response_code VARCHAR(20) NULL,
    pdf_url TEXT NULL,
    xml_url TEXT NULL,
    created_by_user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (stay_id) REFERENCES stays(id),
    FOREIGN KEY (store_sale_id) REFERENCES store_sales(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Detalle de Ítems del Comprobante
CREATE TABLE invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10, 2) NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 5. Endpoints / Métodos de Servicios

### Rutas HTTP
- `POST /api/invoices/issue` — Emite un nuevo comprobante (`BOLETA`, `FACTURA`, `NOTA_VENTA`) para un Folio de estancia o Venta POS.
- `GET /api/invoices` — Listado de comprobantes emitidos.
- `GET /api/invoices/{id}` — Detalle completo del comprobante con URLs de descarga PDF/XML.

---

## 6. Criterios de Aceptación (Given / When / Then)

### Criterio 1: Emisión de Boleta con Desglose IGV y Correlativo
- **Given:** Estancia finalizada con total a cobrar de `S/ 118.00`. Serie `B001` con último correlativo `00000005`.
- **When:** El recepcionista emite una `BOLETA` a nombre del huésped con DNI `72819283`.
- **Then:** Se asigna el comprobante `B001-00000006`, `taxable_amount = 100.00`, `igv_amount = 18.00`, `total_amount = 118.00`. Se genera la respuesta SUNAT y la URL del PDF.

### Criterio 2: Almacenamiento en Provider Configurado
- **Given:** `STORAGE_PROVIDER=local` en la configuración.
- **When:** Se emite la factura `F001-00000001`.
- **Then:** El archivo PDF se genera en `storage/app/comprobantes/F001-00000001.pdf` y la URL guardada referencia la ruta accesible localmente.

---

## 7. Casos Límite y Errores

1. **Factura con DNI (Sin RUC 11 dígitos):**
   - Intentar emitir una `FACTURA` a un cliente que solo presentó `DNI` (8 dígitos) sin RUC de 11 dígitos debe rebotar con `HTTP 422 Unprocessable Entity`.
2. **Folio Inexistente o Ya Cerrado:**
   - Rebona con `HTTP 409 Conflict`.

---

## 8. Transacciones PDO

- La emisión del comprobante (`POST /api/invoices/issue`) se ejecuta en una transacción PDO atómica:
  ```php
  $db->beginTransaction();
  // 1. Obtener e incrementar correlativo en invoice_series (FOR UPDATE)
  // 2. Insertar encabezado en invoices
  // 3. Insertar ítems en invoice_items
  // 4. Invocar SunatProvider y StorageProvider
  // 5. Cerrar el Folio de la estancia
  $db->commit();
  ```
