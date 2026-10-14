# SPEC-0034: Interfaz Completa de Facturación Electrónica SUNAT y Comprobantes (`/comprobantes`)

- **Fecha:** 2026-08-22
- **Estado:** Borrador (Esperando Aprobación del Usuario)
- **Módulo:** Emisión, Consulta, Auditoría y Descarga de Comprobantes de Pago (Boletas, Facturas, Notas de Venta)
- **Dependencia:** SPEC-0010 (Backend de Facturación SUNAT — Implementado)

---

## 1. Objetivo

Construir la **interfaz completa y profesional** del módulo de Comprobantes y Facturación Electrónica (`/comprobantes`) para que el hotelero y administrador puedan:

1. **Monitorear Métricas de Facturación**:
   - Total Facturado Acumulado (S/).
   - Total Boletas Electrónicas Emitidas.
   - Total Facturas Electrónicas Emitidas.
   - Total Notas de Venta / Comprobantes Internos.
2. **Consultar y Auditar Comprobantes**:
   - Tabla interactiva con Serie-Número (`B001-000001`, `F001-000001`, `NV01-000001`), Tipo de Documento, Documento del Cliente (DNI/RUC), Razón Social/Nombre, Monto Total, Estado SUNAT (`ACCEPTED`, `PENDING`, `REJECTED`) y Enlaces de Descarga (PDF y XML).
   - Filtros por Tabs: `Todos`, `Boletas`, `Facturas`, `Notas de Venta`.
   - Buscador por número de comprobante, cliente o DNI/RUC.
3. **Modal de Detalle / Vista Previa de Comprobante (`#modal-invoice-detail`)**:
   - Desglose de ítems facturados (estancia, consumos, servicios), base imponible, IGV (18%) y total.
4. **Modal de Emisión Manual Directa (`#modal-issue-invoice`)**:
   - Permite emitir una Boleta, Factura o Nota de Venta libre con líneas de detalle dinámicas.

---

## 2. Alcance

### Dentro del Alcance:
- Vista `views/pages/invoices.php` rediseñada con métricas, tabs, tabla con badges SUNAT, modal de detalle y modal de emisión manual.
- JavaScript `public/assets/js/pages/invoices.js` consumiendo `GET /api/invoices`, `POST /api/invoices/issue`.
- CSS `public/assets/css/pages/invoices.css` con dark theme consistente.
- Pruebas automatizadas actualizadas.

---

## 3. Reglas de Negocio e Invariantes

1. **Formatos de Serie:**
   - Boleta: `B001-XXXXXX`
   - Factura: `F001-XXXXXX` (Requiere RUC de 11 dígitos y Razón Social)
   - Nota de Venta: `NV01-XXXXXX`
2. **Cálculo Tributario:**
   - $\text{Base Imponible} = \text{Total} / 1.18$
   - $\text{IGV} = \text{Total} - \text{Base Imponible}$

---

## 4. Componentes UI de la Vista

### 4.1 Header
```
🧾 Facturación Electrónica SUNAT y Comprobantes
Emisión de boletas, facturas, notas de venta y almacenamiento tributario.
[➕ Emitir Comprobante Manual]
```

### 4.2 Métricas (4 tarjetas)
| Tarjeta | Icono | Descripción |
|---------|-------|-------------|
| Total Facturado | 💰 | Suma de montos de comprobantes |
| Boletas Emitidas | 📄 | Total de boletas (B001) |
| Facturas Emitidas | 🏢 | Total de facturas (F001) |
| Aceptadas SUNAT | ✅ | Comprobantes en estado `ACCEPTED` |

### 4.3 Tabs y Tabla
- Tabs: `Todos`, `Boletas`, `Facturas`, `Notas de Venta`.
- Columnas: #, N° Comprobante, Tipo Doc, Doc Cliente, Cliente / Razón Social, Total (S/), Estado SUNAT, Acciones (👁️ Ver Detalle, 📄 PDF, 💻 XML).

---

## 5. Criterios de Aceptación (Given / When / Then)

### CA-1: Renderizado Completo de `/comprobantes`
- **Given:** El usuario navega a `/comprobantes`.
- **When:** La página carga.
- **Then:** Se muestran las 4 métricas, los tabs de tipos de documento, la barra de búsqueda y la tabla de comprobantes.

### CA-2: Emisión Manual de Comprobante
- **Given:** El usuario abre `#modal-issue-invoice`, selecciona Boleta, ingresa cliente y un ítem por S/ 50.00.
- **When:** Envía el formulario.
- **Then:** Se envía a `POST /api/invoices/issue`, la tabla se actualiza con el nuevo comprobante `B001-...` y se muestra toast de éxito.

### CA-3: Ver Detalle de Comprobante
- **Given:** Comprobantes en la tabla.
- **When:** El usuario hace clic en `👁️ Ver`.
- **Then:** Se abre `#modal-invoice-detail` mostrando los ítems, subtotal, IGV y total.

---

## 6. Archivos a Modificar / Crear

| Archivo | Acción |
|---------|--------|
| `views/pages/invoices.php` | **Reescribir** — Vista completa con métricas, tabs, tabla y modales |
| `public/assets/js/pages/invoices.js` | **Crear** — Lógica de listado, filtrado, detalle y emisión |
| `public/assets/css/pages/invoices.css` | **Crear** — Estilos para facturación |
| `tests/Feature/Controllers/ViewControllerTest.php` | **Modificar** — Aserciones para `/comprobantes` |
