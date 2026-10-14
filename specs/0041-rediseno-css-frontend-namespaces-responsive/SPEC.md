# SPEC-0041: Rediseño CSS Frontend — Namespaces BEM, Responsive Móvil y Desduplicación

- **Fecha:** 2026-08-23
- **Estado:** En Revisión (Esperando Aprobación del Usuario)
- **Módulo:** Frontend estático (`public/assets/css/`), vistas (`views/layouts/`, `views/components/`, `views/pages/`)
- **Spec base:** Construye sobre SPEC-0018 (arquitectura modular frontend CSS/JS), no lo reemplaza.

---

## 1. Objetivo

Resolver los problemas de diseño detectados en el frontend: estilos que se cruzan entre
páginas, tablas y modales descuadrados, clases inexistentes, y un menú lateral que no
funciona en móvil (no se oculta ni se puede abrir/cerrar).

El rediseño establece **tres pilares**:

1. **Namespaces BEM estrictos por componente de layout** (`ns-header__`, `ns-nav__`,
   `ns-footer__`) que viven en su propio archivo CSS y no se reutilizan fuera de él.
2. **Responsive completo** con menú lateral colapsable (hamburguesa + overlay) y modales
   que se adaptan a móvil.
3. **Desduplicación y normalización** de clases repetidas entre páginas (metric-cards,
   pill-groups, tab-bars) elevándolas a componentes reutilizables con un único origen.

---

## 2. Contexto / Alcance

### Dentro del alcance

1. **Layout responsive (móvil + desktop):**
   - `public/assets/css/layout.css`: media queries, sidebar colapsable, header adaptativo.
   - `views/layouts/header.php`: botón hamburguesa (toggle) con JS mínimo.
   - `views/layouts/sidebar.php`: clases namespace `ns-nav__*`.
   - `views/layouts/footer.php`: clases namespace `ns-footer__*`.
   - `public/assets/js/core/nav-toggle.js`: apertura/cierre del sidebar en móvil (sin
     librerías, JS vainilla).

2. **Archivos CSS de componentes de layout con namespace:**
   - `public/assets/css/layout/header.css` → `.ns-header__*` (brand, status, toggle).
   - `public/assets/css/layout/nav.css` → `.ns-nav__*` (list, item, link, active).
   - `public/assets/css/layout/footer.css` → `.ns-footer__*` (text, links).

3. **Rediseño de componentes compartidos (desduplicación):**
   - `public/assets/css/components/modal.css`: centrado vertical real, responsive móvil
     (max-width fluido, padding reducido), `.form-actions`, `.modal-footer`.
   - `public/assets/css/components/drawer.css`: ya existe; revisar responsive.
   - `public/assets/css/components/metric-card.css` (NUEVO): extrae las clases
     `.metric-icon`, `.metric-info`, `.metric-title`, `.metric-value` idénticas que hoy
     se repiten en reservations.css, users.css, cashbox.css, expenses.css, etc.
   - `public/assets/css/components/tab-bar.css` (NUEVO): extrae `.tab-btn`/`.tab-counter`
     que se repiten entre reservas, folios y otras páginas.

4. **Corrección de clases inexistentes:**
   - Definir `.btn-light`, `.font-bold`, `.bg-primary-soft`, `.bg-success-soft`,
     `.bg-danger-soft`, `.form-actions`, `.input-group` en `base.css` o componente
     correspondiente, o **eliminarlas del HTML** si no aportan valor y reemplazarlas por
     clases existentes.

5. **Carga consistente de CSS de páginas:**
   - `header.php` deja de cargar hardcoded solo 4 CSS de páginas.
   - Mecanismo de carga dinámica: cada vista declara su CSS vía una variable
     `$page_styles[]` antes de incluir el header, y el header las itera. Esto unifica el
     orden de cascada y elimina los `<link>` sueltos tras el header.

6. **Tablas responsive:**
   - `base.css` `.data-table`: ya tiene `.table-responsive` con overflow-x. Reforzar con
     breakpoints donde las tablas críticas (reservas, caja, huéspedes) pasen a vista de
     tarjetas apiladas en móvil (`@media max-width: 640px`) o scrolleen correctamente sin
     romper el layout.

### Fuera del alcance

- Cambios en lógica de negocio, controladores, servicios, repositorios o base de datos.
- Nuevas rutas o endpoints.
- Cambios en el JS de drawer/modales salvo el nuevo `nav-toggle.js`.
- PDF, reportes exportados, comprobantes SUNAT (specs 0038/0039 los cubren).
- Reescritura completa de cada página; se ajusta layout, clases y responsive, no se
  rediseña el contenido funcional de cada vista.

---

## 3. Reglas de Negocio / Invariantes

1. **Namespace BEM obligatorio para layout.** Toda clase de header, nav y footer debe
   usar el prefijo `ns-header__`, `ns-nav__`, `ns-footer__` respectivamente. Estas clases
   **no se usan en ningún otro archivo** fuera del suyo. Las páginas NO referencian
   clases `ns-*` de layout.
2. **Un solo origen por clase compartida.** Una clase de componente (ej. `.metric-icon`)
   se define en un único archivo CSS. Las páginas solo la consumen, no la redefinen.
3. **Responsive no opcional.** Todo breakpoint debe cubrir como mínimo:
   - `≥ 1024px`: layout desktop completo (sidebar fijo 260px).
   - `768px–1023px`: sidebar fijo pero más estrecho (200px) o iconos.
   - `< 768px`: sidebar oculto por defecto, se abre con hamburguesa como overlay;
     header compacto; modales a ancho fluido; tablas con scroll horizontal o tarjetas.
4. **Sin estilos inline.** Se elimina `style="..."` de las vistas de layout y se migra a
   clases. Excepción: `style="display: none;"` que controla visibilidad por JS se permite
   temporalmente, pero se documenta y prefiere clase `.is-hidden`.
5. **Cero dependencias externas.** Sin Tailwind, Bootstrap ni frameworks CSS. PHP puro +
   CSS vainilla + JS vainilla (coherente con SPEC-0018 y AGENTS.md §0.1).
6. **No romper selectores de tests e2e.** Si los tests Feature/E2E usan selectores de
   clase o id, los cambios de clase deben actualizar los tests en el mismo commit.

---

## 4. Modelo de Datos

No aplica. No hay cambios de schema, tablas ni migraciones. Es un cambio puramente
frontend (CSS/JS/HTML de vistas).

---

## 5. Endpoints / Rutas

No aplica. No se agregan ni modifican rutas. Todas las rutas existentes (`/`, 
`/habitaciones`, `/reservas`, `/caja`, etc.) siguen sirviendo las mismas vistas, ahora con
el nuevo CSS.

---

## 6. Criterios de Aceptación (Given / When / Then)

### Criterio 1: Namespace BEM en layout
- **Given** los archivos `header.php`, `sidebar.php`, `footer.php`.
- **When** se inspecciona el HTML servido.
- **Then** toda clase del header usa prefijo `ns-header__`, toda del nav usa `ns-nav__`,
  toda del footer usa `ns-footer__`, y **ninguna** de estas clases aparece en archivos CSS
  fuera de `layout/header.css`, `layout/nav.css`, `layout/footer.css` respectivamente.

### Criterio 2: Menú lateral responsive en móvil
- **Given** un dispositivo con ancho `< 768px`.
- **When** se carga cualquier página autenticada.
- **Then** el sidebar está oculto por defecto, existe un botón hamburguesa visible en el
  header que al pulsarlo muestra el sidebar como overlay con fondo oscuro semi-transparente
  que cierra al pulsar fuera o al seleccionar una opción.

### Criterio 3: Sidebar fijo en desktop
- **Given** un dispositivo con ancho `≥ 1024px`.
- **When** se carga cualquier página autenticada.
- **Then** el sidebar es visible y fijo (260px) sin botón hamburguesa visible.

### Criterio 4: Modales centrados y responsive
- **Given** un modal abierto en desktop y en móvil (`< 640px`).
- **When** se inspecciona el modal.
- **Then** en desktop está centrado verticalmente (`align-items: center`); en móvil ocupa
  `max-width: 100%` con `margin: 1rem` y scroll interno sin desbordar el viewport.

### Criterio 5: Cero clases inexistentes
- **Given** cualquier clase de estilo referenciada en las vistas.
- **When** se hace un barrido de clases usadas vs. definidas en CSS.
- **Then** toda clase de estilo usada en HTML está definida en algún archivo CSS, o fue
  eliminada del HTML y reemplazada por una existente. No quedan clases "huérfanas".

### Criterio 6: Desduplicación de componentes compartidos
- **Given** los archivos CSS de páginas (`reservations.css`, `users.css`, `cashbox.css`,
  `expenses.css`, etc.).
- **When** se buscan definiciones duplicadas de `.metric-icon`, `.metric-title`,
  `.metric-value`, `.pill-group`, `.pill-option`, `.tab-btn`.
- **Then** existen definidas una sola vez en `components/metric-card.css` o
  `components/tab-bar.css`, y las páginas eliminan su duplicado.

### Criterio 7: Carga dinámica de CSS de páginas
- **Given** el archivo `header.php`.
- **When** se sirve cualquier página.
- **Then** los `<link>` de CSS de página se cargan iterando `$page_styles[]` (declarado
  por la vista antes de incluir el header), en orden consistente, sin `<link>` sueltos
  dispersos en el cuerpo de las vistas.

### Criterio 8: Sin estilos inline en layout
- **Given** los archivos `header.php`, `sidebar.php`, `footer.php`.
- **When** se inspecciona su código.
- **Then** no existen atributos `style="..."`. Las reglas visuales viven en CSS.

### Criterio 9: make verify en verde
- **Given** los cambios de frontend aplicados.
- **When** se ejecuta `make verify`.
- **Then** lint, PHPStan nivel 9 y PHPUnit pasan al 100% (los tests e2e actualizados si
  cambiaron selectores).

---

## 7. Casos Límite y Errores

1. **Sidebar abierto y se rota el móvil de vertical a horizontal:** debe cerrarse
   automáticamente o reajustarse a modo desktop sin quedar el overlay trabado.
2. **Modal más alto que el viewport en móvil:** scroll interno (`overflow-y: auto`), el
   header del modal permanece accesible, el botón cerrar siempre visible.
3. **Tabla con muchas columnas en móvil:** scroll horizontal dentro de `.table-responsive`
   sin romper el layout; opcionalmente se provee `data-label` para vista tarjeta.
4. **JS deshabilitado:** el sidebar debe seguir siendo navegable en desktop (no depende
   de JS para mostrarse). Solo el toggle de móvil requiere JS (degradación aceptable: en
   móvil sin JS el menú queda accesible vía enlace directo o se muestra expandido).
5. **Clases eliminadas del HTML:** si una vista usaba `.btn-light` y se decide eliminarla,
   reemplazar por `.btn-secondary` (existente) para no perder el botón visual.
6. **Conflictos de cascada:** al mover clases a componentes compartidos, verificar que la
   especificidad no rompa overrides legítimos de páginas (ej. una página que pinta el
   metric-icon de otro color). En caso de conflicto, la página usa una clase modificadora
   adicional (`.metric-icon--warning`), no redefine la base.

---

## 8. Seguridad

1. **Sin XSS nuevo:** todo output dinámico en las vistas sigue escapándose con
   `htmlspecialchars($v, ENT_QUOTES | ENT_HTML5, 'UTF-8')` (AGENTS.md §5.3). El rediseño
   no añade output sin escapar.
2. **Token CSRF:** el meta `csrf-token` del header se conserva intacto; el botón
   hamburguesa no toca autenticación ni sesión.
3. **No se introducen `eval`, `innerHTML` con datos de usuario, ni imports dinámicos.** El
   `nav-toggle.js` solo manipula clases CSS (`classList.toggle`), no interpreta HTML.
4. **Sin cambios en auth/permisos:** el sidebar sigue mostrando los mismos ítems según rol;
   no se altera la lógica de autorización.

---

## 9. Transacciones

No aplica. No hay operaciones de base de datos ni transacciones involucradas. Es un cambio
puramente de presentación.

---

## 10. Plan de Implementación (TDD)

1. **Tests primero (rojo):**
   - Test Feature/E2E que verifique presencia de clases `ns-nav__*` en el HTML servido.
   - Test que verifique que el botón hamburguesa existe con id/clase esperada.
   - Test que verifique que no quedan `<link>` sueltos en el cuerpo de una página muestra
     (cashbox).
   - Test que verifique que las clases `.metric-icon` etc. están en archivo de componente,
     no duplicadas.
2. **Implementación (verde):**
   - Crear `public/assets/css/layout/{header,nav,footer}.css` con namespaces BEM.
   - Migrar `header.php`, `sidebar.php`, `footer.php` a las nuevas clases.
   - Crear `public/assets/js/core/nav-toggle.js` y registrarlo en `footer.php`.
   - Extraer `components/metric-card.css` y `components/tab-bar.css`; limpiar duplicados de
     páginas.
   - Rediseñar `components/modal.css` (centrado + responsive).
   - Definir/eliminar clases huérfanas (`btn-light`, `form-actions`, etc.).
   - Implementar carga dinámica `$page_styles[]` en `header.php` y migrar las 14 vistas.
3. **Refactor:** revisar especificidad y consistencia.
4. **Verificación:** `make verify` en verde con evidencia real.

---

## 11. Archivos Afectados (resumen)

**Nuevos:**
- `public/assets/css/layout/header.css`
- `public/assets/css/layout/nav.css`
- `public/assets/css/layout/footer.css`
- `public/assets/css/components/metric-card.css`
- `public/assets/css/components/tab-bar.css`
- `public/assets/js/core/nav-toggle.js`
- `tests/Feature/FrontendCssRedesignTest.php` (o equivalente)

**Modificados:**
- `public/assets/css/layout.css` (se convierte en importador o se reestructura)
- `public/assets/css/base.css` (clases faltantes, utilities responsive)
- `public/assets/css/components/modal.css` (centrado + responsive)
- `public/assets/css/components/drawer.css` (responsive)
- `public/assets/css/pages/*.css` (eliminar duplicados, ajustar)
- `views/layouts/header.php`, `sidebar.php`, `footer.php` (namespaces + carga dinámica)
- `views/components/*.php` (clases de modales)
- `views/pages/*.php` (carga de CSS, eliminación de estilos inline)

---

## 12. Riesgos y Mitigaciones

| Riesgo | Mitigación |
|--------|------------|
| Cambiar clases rompe tests e2e existentes | Actualizar selectores en tests en el mismo commit; correr `make verify`. |
| Cascada alterada afecta páginas no revisadas | Barrido completo de clases usadas vs. definidas antes de cerrar. |
| JS de toggle falla en navegadores antiguos | Usar `classList` (soporte universal) sin polyfills; degradación: sidebar visible en desktop sin JS. |
| Scope creciente ("rediseño total") | Este spec acota a layout/responsive/desduplicación; cada página se ajusta, no se reescribe. |

---

## 13. Verificación Final

- `make verify` exitoso (lint + PHPStan 9 + PHPUnit).
- Inspección visual manual de al menos: dashboard, reservas, caja, usuarios, tienda en
  desktop y en viewport móvil (DevTools 375px).
- Confirmar que el sidebar se abre/cierra en móvil y los modales se ven centrados.
