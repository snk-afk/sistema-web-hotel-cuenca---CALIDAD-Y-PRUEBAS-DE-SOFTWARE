# SPEC-0017: Formularios Dinámicos Reactivos y Rediseño Visual Interactivo 3.0

- **Fecha:** 2026-08-21
- **Estado:** En Revisión (Esperando Aprobación del Usuario)
- **Módulo:** Formularios Dinámicos Reactivos (Visibilidad Condicional), UX Inteligente y Rediseño UI 3.0

---

## 1. Objetivo

Implementar **Formularios Dinámicos Reactivos con Visibilidad Condicional** en todo el sistema y mejorar la experiencia de usuario (UX 3.0), haciendo que los campos de entrada (`inputs`, fechas, precios, motivos, datos de documentos) aparezcan o se oculten automáticamente con animaciones según el tipo de operación seleccionada por el usuario (ej. Tarifa Estándar vs. Horario Personalizado, Precio Normal vs. Precio Libre con Motivo, Persona Natural vs. Empresa con RUC).

---

## 2. Alcance

### Dentro del Alcance:
1. **Controles Condicionales en Formulario de Check-In (`views/components/room_drawer.php`):**
   - **Selector de Modo de Estancia:**
     - `⚡ Tarifa Estándar`: Oculta los selectores de fechas manuales.
     - `📅 Horario Personalizado`: Muestra con transición suave los inputs `<input type="datetime-local">` de Entrada y Salida.
   - **Selector de Modo de Precio:**
     - `💵 Precio Oficial`: Aplica el precio de lista.
     - `✏️ Precio Manual / Descuento`: Muestra dinámicamente el input de Precio Libre y el campo obligatorio `Motivo de Modificación`.
   - **Selector de Tipo de Documento:**
     - `🪪 Persona Natural (DNI)`: Ajusta longitud a 8 dígitos y etiqueta "Nombres y Apellidos".
     - `🏢 Empresa (RUC)`: Ajusta longitud a 11 dígitos y muestra campos adicionales de "Razón Social" y "Dirección Fiscal".

2. **Controles Condicionales en Facturación / Checkout (`modal_checkout.php` & drawer):**
   - Si se elige `FACTURA`, muestra automáticamente los campos de RUC y Dirección Fiscal.
   - Si se elige `BOLETA` o `NOTA DE VENTA`, muestra opciones simplificadas.

3. **Mejoras Estéticas UI 3.0 (`public/assets/css/style.css`):**
   - Controles de selección tipo Toggle Switch (`radio pills`).
   - Transiciones fluidas de expansión y colapso de campos condicionales (`max-height`, `opacity`, `transition: 0.25s`).
   - Resaltado reactivo de campos requeridos.

---

## 3. Reglas de Negocio

1. **Campos Requeridos Dinámicos:** Cuando un campo condicional se vuelve visible (ej: Motivo de Descuento), su atributo `required` se activa automáticamente en el DOM.
2. **Cero Ruido Visual:** Los usuarios solo ven en pantalla los campos estrictamente necesarios para el tipo de operación que eligieron.

---

## 4. Criterios de Aceptación (Given / When / Then)

### Criterio 1: Despliegue Condicional de Horarios Manuales
- **Given:** El formulario de Check-in en el Drawer.
- **When:** El recepcionista cambia el modo de "Tarifa Estándar" a "Horario Personalizado".
- **Then:** Los inputs de fecha/hora de entrada y salida aparecen suavemente en pantalla.

### Criterio 2: Requisito Dinámico de Motivo de Descuento
- **Given:** El modo de precio en "Precio Manual / Descuento".
- **When:** Se ingresa un precio menor a la tarifa sin llenar el motivo.
- **Then:** El formulario valida y exige el motivo antes de permitir el envío.

### Criterio 3: Verificación Completa `make verify`
- **Given:** El código JS de reactividad de formularios y estilos CSS.
- **When:** Se ejecuta `make verify`.
- **Then:** Pruebas automatizadas, PHPStan Nivel 9 y sintaxis en verde al 100%.
