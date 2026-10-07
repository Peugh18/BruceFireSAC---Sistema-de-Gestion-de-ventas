# Auditoría única del sistema

**Sistema web para la gestión de ventas en BRUCE FIRE S.A.C.** · Actualizado 2026-10-06 · Solo diagnóstico; los arreglos se ordenan en `PLAN.md`.

Equipo: Claude (técnicos, verificación y unión del reporte), GPT/Codex (vendedor), Antigravity (almacén y gerente), más 3 agentes que compararon la facturación con las guías de SUNAT. Los informes detallados están en `anexos/`.

**Leyenda:**

- ✅ **Verificado:** Claude lo comprobó leyendo el código.
- 📋 **Reportado:** lo encontró un agente y no se ha comprobado aparte.

## Veredicto

El sistema funciona en su recorrido principal, pero **no está listo para pasar a producción con SUNAT**:

1. **No se puede cambiar de beta a producción.** La configuración `SUNAT_BETA` no se aplica (S1).
2. **Los certificados pueden decir cosas falsas** sobre el tipo de extintor y sobre pruebas que no se hicieron (C1, C2).
3. **El IGV, la hora de emisión y algunos motivos de las notas** no cumplen las reglas de SUNAT (S2–S6).
4. **Las series no cuadran:** una línea serializada puede facturar más unidades de las que salen del Kardex (V1).

## 🔴 Crítico

| ID         | Problema                                                                                                                                                                                                                                            | Dónde                                                                                      | Estado |
| ---------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------ | ------ |
| S1–S6, S15 | **Facturación:** paso a producción, motivos de NC en boleta, ND 03 vs. 13, IGV, hora de emisión, fecha de las notas y guía de remisión. Detalle en **`SUNAT.md` §3**.                                                                               | `app/Services/Billing/*`                                                                   | ✅     |
| C1         | **El tipo de agente del extintor no tiene un origen.** El producto y la unidad no lo guardan; el equipo vendido queda sin agente; el certificado pone "PQS-ABC". Como no detecta CO2, también le aplica la presión de PQS (600 PSI en vez de 3000). | `ProcessSaleItem.php:149`, `IssueCertificate.php:231`, `EmitirCertificadosDeVenta.php:191` | ✅     |
| C2         | **Certificados emitidos al cobrar,** con P.H., presión, tiempo y fecha que nadie registró                                                                                                                                                           | `ConfirmSale.php:79`, `EmitirCertificadosDeVenta.php:190-202`                              | ✅     |
| V1         | **Línea serializada con cantidad mayor a 1:** factura N unidades y descuenta 1 del Kardex                                                                                                                                                           | `StoreSaleRequest.php:49`, `ProcessSaleItem.php:168,224`                                   | ✅     |
| V2         | **Los permisos individuales no protegen nada:** `authorize()` siempre es `true` y las rutas solo exigen el rol                                                                                                                                      | `StoreCreditNoteRequest.php:10`, `routes/vendedor.php:38`                                  | ✅     |
| A1         | **Inventario valorizado a precio de venta con IGV.** No existe costo de compra en ninguna tabla.                                                                                                                                                    | `Gerente/ReportController.php:226`                                                         | ✅     |

## 🟠 Importante

| ID  | Problema                                                                                                                   | Dónde                                                        | Estado |
| --- | -------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------ | ------ |
| V3  | La cotización pierde el descuento, la condición de pago, las observaciones y el vehículo al convertirse en venta           | `SaleController::cotizacionParaVenta`                        | ✅     |
| V4  | Las notas no cambian la cobranza (también es S11)                                                                          | `IssueCreditNote.php:108`                                    | 📋     |
| V5  | Un borrador de recarga renueva el vencimiento del equipo antes de hacer el trabajo, y descartarlo no lo revierte           | `ProcessSaleItem.php:200`, `RevertSale.php:35`               | 📋     |
| V6  | Un adicional autorizado no genera deuda                                                                                    | `DeficiencyAuthorizationController.php:71`                   | 📋     |
| V7  | **Caja:** el pago no está ligado al turno; anular un pago puede cambiar una caja cerrada; dos clics pueden abrir dos cajas | `CashRegister.php:93`, `OpenCashRegister.php:13`             | 📋     |
| V8  | El vendedor ve en la ficha del cliente ventas y certificados de otros vendedores de su sede                                | `ClientController.php:166`                                   | 📋     |
| T1  | **Ninguna pantalla de técnicos sube fotos ni recoge una firma** (el §85.6.2 dejó la firma para después)                    | `tecnico-campo/*`, `DeficiencyController.php:117`            | ✅     |
| T2  | **El mantenimiento en sitio no existe**                                                                                    | `routes/tecnico-campo.php`                                   | ✅     |
| T3  | Comunicación de una sola vía: el técnico de planta solo lee las últimas 10 notas, sin poder responder ni adjuntar          | `ExecutionController.php:31`                                 | ✅     |
| T4  | La instalación se registra por orden, no escaneando cada unidad vendida                                                    | `InstallationController.php:119-141`                         | ✅     |
| T5  | No existe formulario para registrar una deficiencia fuera del checklist                                                    | `routes/tecnico-planta.php:37`                               | ✅     |
| A2  | Categorías fijas en el código; los servicios no tienen categoría                                                           | `Product.php:82`                                             | ✅     |
| A3  | Unidad de medida del servicio en texto libre; Greenter cambia sin avisar las unidades desconocidas por NIU o ZZ            | `GreenterService.php:399,405`                                | ✅     |
| A4  | El servicio no permite elegir su tipo de certificado (la columna existe, el formulario no la pide)                         | `StoreServiceRequest.php`                                    | ✅     |
| A5  | El ajuste de stock deja dar de baja dos veces la misma unidad                                                              | `StockAdjustmentController.php:43`                           | ✅     |
| A6  | Botones sin acción: "Registrar nueva recepción" lleva al listado; Cobranzas del Gerente no tiene el botón "anular pago"    | `almacen/dashboard.tsx:303`, `routes/gerente.php:39`         | ✅     |
| A7  | Stock paginado en memoria y bucle N+1 en Consulta Rápida                                                                   | `StockController.php:65-109`, `StockLookupController.php:98` | 📋     |
| A8  | Traslados al instante, sin "en tránsito" ni guía de remisión (motivo 04)                                                   | `TransferInventory.php:23`                                   | ✅     |

## 🟢 Mejoras

- **Stickers:** 5 × 5 cm con código de barras, serie BF-EQ y logo; 20 por hoja A4 (hoy 4); empezar en la posición N y reimprimir uno suelto.
- **Compras:** maestro de proveedores y costo de compra en la recepción.
- **Inventario:** toma de inventario físico masiva.
- **Reportes:** exportar a Excel.
- **Menús:** usar rutas Wayfinder (las armadas a mano causaron los 404) e íconos distintos para Ajustes y Traslados.
- **Cotizaciones:** editar, duplicar y anular; precargar el cliente desde su ficha.
- **Cantidades:** decimales para lo que se vende por metro.
- **Visitas:** estados "en camino" y "en sitio", y modo sin señal (PWA).
- **Nombres de módulos:** "Cajas" en vez de "Caja Consolidada & Control de Arqueos", "Inventario" y un grupo "Empresa".

## Datos duplicados (vista transversal)

1. **Agente del extintor:** está en 5 lugares con calidades distintas. Debe tener **un solo origen: el producto**, que se copia a la unidad y al equipo; `certificate_units` guarda la foto legal (C1).
2. **Dos reglas de certificados:** `CertificateRuleEngine` (sin uso) y una regla fija por destino. Debe quedar una sola.
3. **Fotos:** columnas `foto_*` sueltas en varias tablas y sin uso. Debe ser un solo registro de evidencias (§33).
4. **Comunicación:** reutilizar `ServiceOrderEvent`; no crear un chat aparte.
5. **Checklists separados** en planta e inspección. Debe ser una plantilla por tipo de servicio.
6. **Venta y orden con doble enlace** (`service_orders.sale_id` y `sales.service_order_id`). Revisar antes de normalizar.
7. **Copias correctas que se mantienen:** `certificate_units` y las versiones de plantillas. Lo contrario: el comprobante **sí** debería congelarse y hoy no se congela (S12).

## Auditoría transversal: seguridad, clientes y experiencia de uso (2026-10-06)

Hallazgos nuevos; los que ya están arriba no se repiten.

### Seguridad y arquitectura (comparado con OWASP, Odoo y Salesforce)

| ID  | Problema                                                                                                                                                                                                                                                                  | Dónde                                                                | Estado |
| --- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------- | ------ |
| X1  | 🔴 **El aislamiento por sede depende de recordarlo en cada controlador** (`asegurarSede`); no hay un filtro global. Ya falló: la ficha del cliente muestra equipos y certificados de otras sedes. Odoo y Salesforce lo resuelven en la capa de datos, con _record rules_. | `Vendedor/Concerns/AcotaPorSede.php`, `ClientController.php:167,169` | ✅     |
| X2  | 🔴 **Firmas y sellos de los firmantes en el disco público:** se pueden descargar por URL sin iniciar sesión                                                                                                                                                               | `GuardarImagenDeFirma.php:48`, `SignerController.php:40`             | ✅     |
| X3  | 🟠 La contraseña inicial la pone el Gerente y no se obliga a cambiarla al primer ingreso; el 2FA es opcional incluso para el Gerente                                                                                                                                      | `CrearTrabajador.php:31`                                             | 📋     |
| X4  | 🟠 `.env.example` trae `APP_DEBUG=true`; `trustProxies` solo acepta localhost, así que detrás de un proxy fallan los límites de intentos de login                                                                                                                         | `bootstrap/app.php:23`                                               | 📋     |
| X5  | 🟢 Faltan las cabeceras CSP y HSTS; casi nada va en cola (PDF y SUNAT corren dentro de la petición)                                                                                                                                                                       | `AddSecurityHeaders`                                                 | 📋     |

**Bien hecho:** consultas SQL con parámetros, sin XSS (no hay `{!! !!}` ni `dangerouslySetInnerHTML`), XML, CDR y PDF en disco privado con descarga autorizada, límite de intentos de login, 2FA y passkeys disponibles, verificación pública con token UUID y límite por IP, Chispa sin datos del negocio y 48 transacciones en operaciones de dinero y stock.

### Clientes, cotizaciones, alertas, certificados e IA (comparado con HubSpot, Salesforce, Odoo CRM y Uptick)

| ID  | Problema                                                                                                                                                                                                   | Dónde                                                     | Estado |
| --- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------- | ------ |
| X6  | 🔴 **La tarea diaria vence también las cotizaciones "aceptadas"** que aún no se convierten, y una vencida ya no se puede reactivar. Se salta la regla del propio modelo (aceptada solo pasa a convertida). | `Console/Commands/ExpireQuotes.php:19`, `Quote.php:58,60` | ✅     |
| X7  | 🔴 **Tres KPI del proyecto no existen:** tiempo de registro de venta, tiempo de cotización y clientes recuperados por alertas. La cotización no guarda de qué alerta vino.                                 | `Gerente/DashboardController`                             | 📋     |
| X8  | 🟠 Las alertas "por vencer" no se filtran por sede ni por vendedor y no registran si ya se contactó al cliente                                                                                             | `AlertController.php:38`                                  | 📋     |
| X9  | 🟠 "Ofrecer recarga" busca un servicio con "recarga" en el nombre y usa el precio de catálogo, sin mirar la capacidad ni el agente                                                                         | `AlertController.php:21`                                  | 📋     |
| X10 | 🟠 No hay recordatorios automáticos al cliente (el DRS excluye los mensajes salientes) ni tareas de seguimiento para el vendedor                                                                           | —                                                         | 📋     |
| X11 | 🟢 Clientes: sin registro de llamadas ni notas, sin aviso de duplicados por nombre o teléfono, y se exporta solo la lista de clientes                                                                      | `ClientController.php`                                    | 📋     |
| X12 | 🟢 Chispa responde solo con el manual; no consulta datos ("¿qué vence esta semana?")                                                                                                                       | `ChispaAssistant.php:57-76`                               | 📋     |

**Bien hecho:** alertas de recarga anual y P.H. a 5 años con historial importado; cotización de recarga con un clic; consulta de RUC o DNI que primero busca en la base local; certificados con numeración bloqueada por tipo y año, versión de plantilla, QR, PDF y Word, firmantes y correcciones con motivo; modelo de recompra con su ciclo completo (entrenar, medir, reentrenar cada mes).

### Experiencia de uso, accesibilidad y rendimiento (comparado con Odoo, Shopify Polaris y Salesforce Lightning)

| ID  | Problema                                                                                                                                                    | Dónde                                                                                                | Estado |
| --- | ----------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------- | ------ |
| X13 | 🔴 Hay **7 `confirm()` nativos** del navegador para acciones destructivas, sin un diálogo propio que nombre lo que se va a borrar o anular                  | `vendedor/clientes/show.tsx:452`, `vendedor/ventas/show.tsx:213`, `gerente/productos/index.tsx:225`… | 📋     |
| X14 | 🔴 **No hay un componente de tabla común:** 30 tablas escritas a mano y 35 páginas con scroll horizontal en el celular                                      | `resources/js/pages/**`                                                                              | 📋     |
| X15 | 🔴 **Páginas gigantes:** `vendedor/clientes/show.tsx` tiene 2588 líneas y otras 10 pasan de 960; no se usan _deferred props_                                | `resources/js/pages/**`                                                                              | 📋     |
| X16 | 🟠 **Texto muy pequeño:** 569 usos de 8 a 11 px; en total, 1160 `text-[..px]` y 613 `rounded-[..]` sueltos en vez de tokens                                 | `resources/js/**`                                                                                    | 📋     |
| X17 | 🟠 Sin `StatusBadge` ni `Kpi` comunes (unas 59 píldoras a mano); unos 10 `<img>` sin `alt`; campos sin `htmlFor`; el asterisco de obligatorio es solo color | `app-nav-sidebar.tsx:130`, `firmas.tsx:244`…                                                         | 📋     |
| X18 | 🟢 Imagen de Chispa animada de 1.4 MB; el enlace "saltar al contenido" solo está en el layout del vendedor; pocos toasts y pocos skeletons                  | `public/brand/chispa/animado-v2.webp`                                                                | 📋     |

**Bien hecho:** diálogos Radix accesibles (los 21 tienen título), protección contra doble envío (`processing`) en casi todos los formularios, campos de 16 px en el celular (sin zoom en iOS), foco visible en los campos base y textos sin jerga técnica.

## Comparación general con sistemas grandes

Escala: 🟢 a la altura de un sistema grande · 🟡 funciona pero le falta · 🔴 no está listo.

| Área               | Nota | Lo mejor                                                             | Lo peor                                                                                                  |
| ------------------ | ---- | -------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------- |
| Ventas y caja      | 🟡   | Arqueo ciego, cuotas, notas ligadas al comprobante                   | Series que no cuadran (V1), cotización que pierde datos (V3), caja sin enlace al turno (V7)              |
| Facturación SUNAT  | 🔴   | UBL 2.1 firmado, forma de pago, detracción y validación del RUC      | No se puede pasar a producción (S1); IGV, hora, motivos de las notas; no hay GRE ni comunicación de baja |
| Certificados       | 🟡   | Numeración, QR, revisiones, PDF y Word, firmantes                    | Se emiten al cobrar con datos inventados; el tipo de extintor sale "PQS-ABC" (C1, C2)                    |
| Clientes y alertas | 🟢   | Alertas de vencimiento, consulta RUC con caché local, IA de recompra | Faltan los KPI del proyecto (X7); cotizaciones aceptadas que vencen solas (X6)                           |
| Almacén            | 🟡   | Kardex, lotes, series BF-EQ, ajustes con motivo                      | Sin costo de compra, doble baja, traslados sin GRE                                                       |
| Técnicos           | 🔴   | Checklist por extintor, cadena de custodia con eventos               | Sin fotos ni firma; no existe el mantenimiento en sitio                                                  |
| Seguridad          | 🟡   | Sin inyección SQL ni XSS, 2FA, archivos fiscales privados            | Aislamiento por sede manual (X1), firmas públicas (X2), permisos que no se aplican (V2)                  |
| Interfaz           | 🟡   | Radix, protección contra doble envío, campos aptos para móvil        | Sin tabla común, páginas gigantes, texto muy pequeño, `confirm()` nativos                                |

## Falsos positivos descartados

| Afirmación                                                | Por qué no es cierto                                                                         |
| --------------------------------------------------------- | -------------------------------------------------------------------------------------------- |
| "Cajas consolidadas da error 500 si se borra el vendedor" | `vendedor_id` es obligatorio y está protegido con `restrict` (migración `2026_09_28_014309`) |
| "Borrar un usuario borra sus ventas en cascada"           | La misma migración lo impide; hay una prueba que lo asegura                                  |
| "Ya existe la guía de remisión"                           | No existe en el código                                                                       |
| "Se puede emitir una factura sin RUC"                     | `ValidarComprobanteCliente.php:20` lo bloquea                                                |
| "Los certificados no se pueden corregir ni reemitir"      | `IssueCertificate::corregir()` se usa en 3 lugares y guarda revisiones con motivo            |

## Respuestas a las preguntas del dueño

| Pregunta                                  | Respuesta corta                                                                                                                        |
| ----------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------- |
| Categoría con botón para crear o eliminar | Sí: botón "Gestionar" junto al select, que abre un modal (A2). Solo se elimina una categoría sin uso; si está en uso, se desactiva.    |
| Unidad de medida del servicio             | Select del catálogo SUNAT 03, como en productos (A3)                                                                                   |
| "Caja Consolidada & Control de Arqueos"   | Es un solo módulo; renombrarlo "Cajas"                                                                                                 |
| Reportes vs. Dashboard                    | El dashboard es lo de hoy y las alertas; los reportes, análisis por fechas. Se mantienen separados y cada tarjeta enlaza a su reporte. |
| Sedes dentro de Usuarios                  | No: la sede también es almacén y serie. Agrupar en "Empresa"; el usuario ya recibe su sede al crearlo.                                 |
| Stock y Kardex                            | Un solo módulo, "Inventario", con pestañas                                                                                             |
| ¿De dónde sale el tipo de extintor?       | De ningún lado (C1). Debe venir del producto.                                                                                          |
| Stickers de 5 × 5 cm                      | Sí: 20 por hoja, solo código, serie y logo                                                                                             |
| ¿Cómo funciona el ajuste de stock?        | Directo, con motivo y Kardex, sin aprobación (ya decidido). Corregir la doble baja (A5).                                               |
| ¿Cómo funcionan los traslados?            | Escaneo o cantidad; el stock pasa al instante. Falta "en tránsito" y la guía de remisión (A8, S15).                                    |
| Técnico de planta "no funciona"           | Había 2 enlaces 404, ya corregidos. Faltan fotos, mensajes de ida y vuelta y la deficiencia suelta (T1, T3, T5).                       |
| Técnico de campo                          | Faltan fotos, firma, mantenimiento e instalación por unidad (T1, T2, T4)                                                               |
