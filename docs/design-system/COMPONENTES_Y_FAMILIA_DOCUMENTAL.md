# Sistema de diseño, componentes reutilizables y familia documental

> Estado: **propuesta para aprobar** (2026-10-28). Complementa [DESIGN.md](../../DESIGN.md) (marca, paleta y tipografía, vigentes) y `resources/css/app.css` (177 variables: tokens OKLCH claro/oscuro y variantes `*-strong` de contraste).
> Regla del prompt V3, aplicada aquí: **los documentos comparten presentación y comportamiento común; cada uno conserva sus propios campos, cálculos, estados y reglas tributarias.**

## 1. Lo que ya existe (no se reinventa)

| Pieza                           | Dónde                                                                                                                                                                                                                            | Estado                           |
| ------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------- |
| Primitivas shadcn/ui (new-york) | `resources/js/components/ui/` (26): button, input, dialog, sheet, dropdown-menu, table, badge, card, alert, empty, skeleton, spinner, tooltip, sidebar, breadcrumb, tabs-bf, native-select, input-peru, input-correo, input-otp… | Base aprobada                    |
| Estructura de la app            | `app-shell`, `app-sidebar` + un sidebar por rol, `mobile-nav-dock`, `page-header`, `breadcrumbs`, `theme-toggle`                                                                                                                 | En uso                           |
| Selectores de negocio           | `client-picker` (346 líneas), `catalog-picker` (273), `ubigeo-picker`, `categoria-select`, `codigo-sunat-input`, `campo-marca`                                                                                                   | En uso, reutilizables            |
| Piezas de documento             | `referencia-field`, `credito-dialog` (cuotas), `sale-notes-panel` (NC/ND), `resumen-revision`                                                                                                                                    | En uso                           |
| Evidencias y firma              | `captura-evidencia`, `subir-evidencia`, `firma-canvas`, `archivos-nota`                                                                                                                                                          | En uso                           |
| Indicadores                     | `kpi-card`, `kpi-filtro`, `bi.tsx` (gráficos accesibles con Recharts)                                                                                                                                                            | En uso                           |
| Estados                         | `ui/empty`, `ui/skeleton`, `cargando`, `alert-error`, `input-error`                                                                                                                                                              | **Uso irregular** (E-053, E-020) |

## 2. Lo que falta o está duplicado

| Necesidad                                                                   | Hoy                                                              | Propuesta                                                                                            |
| --------------------------------------------------------------------------- | ---------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------- |
| Error de formulario junto al campo, con `aria-invalid` y `aria-describedby` | Mezcla de error junto al campo y avisos que desaparecen          | Componente `CampoFormulario` (label + control + error) usado por todos los formularios               |
| Líneas del documento (ítems, cantidad, precio, descuento)                   | Repetido dentro de `ventas/nueva.tsx` y `cotizaciones/nueva.tsx` | `LineasDelDocumento` + hook `useTotalesDelDocumento` (un solo cálculo, tasa de IGV desde el backend) |
| Resumen de importes (gravado, exonerado, inafecto, IGV, total, detracción)  | Repetido                                                         | `ResumenDeImportes`                                                                                  |
| Confirmación de acciones irreversibles                                      | Diálogos propios por pantalla                                    | `ConfirmarAccion` (sobre Radix Dialog)                                                               |
| Tabla de datos con filtros y paginación del servidor                        | `ui/table` + paginación por pantalla                             | `TablaDeDatos` con barra de filtros, estado vacío y paginación de Laravel                            |
| Línea de tiempo e historial                                                 | Por pantalla (orden, venta)                                      | `HistorialDelDocumento`                                                                              |

## 3. Familia de documentos comerciales

### Componentes compartidos

`DocumentoLayout` (encabezado, acciones y estado) · `ClientPicker` · `CatalogPicker` · `LineasDelDocumento` · `ResumenDeImportes` · `CondicionDePago` (contado, crédito y `CreditoDialog`) · `ReferenciaField` · `Observaciones` · `VistaPrevia` (PDF) · `HistorialDelDocumento`.

### Matriz: qué comparte cada documento y qué conserva

| Documento                   | Comparte                                                                      | Conserva (no se mezcla)                                                                                                                                             | Estados propios                                                                                                           |
| --------------------------- | ----------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------- |
| **Cotización** (`quotes`)   | Cliente, líneas, totales, condiciones, referencia, destino (local o vehículo) | Vigencia, conversión a venta u orden, envío al cliente (E-046)                                                                                                      | borrador · enviada · aceptada · rechazada · vencida · convertida                                                          |
| **Nota de venta** (interna) | Cliente, líneas, totales, pago                                                | **No es comprobante de pago** (Informe SUNAT 127-2020); se formaliza a factura o boleta sin duplicar la venta                                                       | confirmada · anulada · formalizada                                                                                        |
| **Factura** (01)            | Cliente con RUC, líneas, totales, pago, crédito, detracción                   | Serie F, correlativo, validación del RUC activo y habido, envío diferido, CDR                                                                                       | Venta: confirmada/anulada · Comprobante: por enviar/aceptado/observado/rechazado/de baja · Pago: pendiente/parcial/pagado |
| **Boleta** (03)             | Cliente (DNI o sin identificar hasta S/ 700), líneas, totales, pago           | Serie B; límite de cliente sin identificar                                                                                                                          | Igual que la factura                                                                                                      |
| **Nota de crédito** (07)    | Cliente y líneas del documento origen, resumen de importes                    | Documento origen obligatorio, motivo del catálogo 09, no supera lo vendido, devuelve stock solo si regresa la mercadería («Corregir» o «Anular», decidido el 23/10) | por enviar · aceptada · rechazada                                                                                         |
| **Nota de débito** (08)     | Cliente del documento origen, resumen de importes                             | Documento origen, motivo del catálogo 10, afectación según el motivo (mora inafecta, E-052)                                                                         | por enviar · aceptada · rechazada                                                                                         |
| **Guía de remisión** (09)   | Cliente, bienes del documento origen                                          | Transporte, conductor, ubigeos, peso; sin importes                                                                                                                  | por enviar · aceptada · rechazada                                                                                         |

**No se comparten:** cálculos tributarios, series y correlativos, transiciones de estado, motivos de NC/ND ni validaciones fiscales. Cada documento conserva su Form Request y su Action en el backend. Una tabla genérica no reemplaza a `quotes`, `sales` y `electronic_documents`.

### Recorrido común (14 pasos del prompt)

Listado → crear → cliente → líneas → condiciones → validar → vista previa → confirmar → resultado → detalle → historial → acciones posteriores → errores y recuperación.

Diferencias justificadas:

- La cotización no tiene paso de «envío a SUNAT».
- Las notas parten del detalle del comprobante origen; no se crean desde cero.
- La guía parte de una factura o boleta con productos.

## 4. Librerías: registro de decisiones

| Librería                            | Función                               | Estado                                                | Justificación                                                                                   |
| ----------------------------------- | ------------------------------------- | ----------------------------------------------------- | ----------------------------------------------------------------------------------------------- |
| shadcn/ui + Radix (React)           | Componentes accesibles                | **Aprobada (en uso)**                                 | Ya instalada; sus primitivas cubren diálogo, menú, tooltip y sheet                              |
| Lucide                              | Íconos                                | **Aprobada (en uso)**                                 | Un solo estilo de íconos                                                                        |
| Recharts 3                          | Gráficos                              | **Aprobada (en uso)**                                 | Ya en los dashboards, con versión accesible                                                     |
| Sonner                              | Avisos                                | **Aprobada, con regla**                               | Solo para confirmaciones; los errores de validación van junto al campo                          |
| Inertia `useForm` + Form Requests   | Formularios y validación              | **Aprobada (en uso)**                                 | La regla vive en el backend; no se duplica en el frontend                                       |
| react-hook-form / Zod               | Validación en el frontend             | **Descartada por ahora**                              | Duplicaría las reglas de los Form Requests                                                      |
| TanStack Table                      | Tablas avanzadas                      | **En evaluación**                                     | Útil si se necesita ordenar o seleccionar en el cliente; la paginación ya es del servidor       |
| Radix Popover / cmdk                | Combobox y buscador de comandos       | **En evaluación**                                     | `ClientPicker` y `CatalogPicker` ya resuelven la búsqueda                                       |
| Pest 4 browser testing (Playwright) | Pruebas de recorridos en el navegador | **Recomendada, requiere permiso** (dependencia nueva) | Pest ya está en el proyecto; evita otra herramienta de pruebas                                  |
| Storybook                           | Catálogo visual de componentes        | **Descartada por ahora**                              | Costo de mantenimiento alto para un equipo pequeño; se revisa al terminar la familia documental |

No se instala nada sin la aprobación del usuario (regla del proyecto).

## 5. Claude Design

- **Todavía no hay artefactos de Claude Design** para este sistema. No se declara ningún diseño creado.
- **Orden propuesto:**
    1. Cargar el sistema de diseño (tokens de `app.css`, `DESIGN.md`, logos de `public/brand`).
    2. Diseñar `DocumentoLayout` con Nueva venta como caso representativo.
    3. Validar las variantes de cotización, factura, boleta, NC y ND.
    4. Extender a órdenes de servicio y taller.
    5. Diseñar celular y estados de error.
- Cada artefacto aprobado se registra en esta sección con su enlace.
