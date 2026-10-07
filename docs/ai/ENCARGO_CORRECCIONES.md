# Encargo: corregir todo lo de la auditoría (para el agente que ejecuta)

**Proyecto:** Sistema web para la gestión de ventas en BRUCE FIRE S.A.C.
**Repositorio:** `D:\TiomiguelonGgs\Documents\BRUCE FIRE\BruceFireSacv2`, rama `fix/correcciones-auditoria-completa`.
**Autorizado por el dueño (2026-10-06):** hacer todas las correcciones, incluso actualizar dependencias (Greenter v5 y `greenter/gre-api`).

**Condición del dueño:** seguir las reglas de SUNAT **al pie de la letra**. Ante la duda, no inventes: deja la opción más conservadora y anótala en "Dudas" (al final de `docs/ai/BITACORA.md`).

---

## 0. Antes de tocar código: lee en este orden

1. `CLAUDE.md`, `AGENTS.md` y `.ai/rules/*.md`: convenciones del proyecto (Laravel Boost, Pest, Pint, Wayfinder con `--with-form`, PHPStan sin agregar errores a la línea base).
2. `docs/ai/PROYECTO.md`: qué es el sistema y las **decisiones ya tomadas** (no las cambies).
3. `docs/ai/PLAN.md`: la lista de tareas por fases (A a H). Es tu checklist: marca `[x]` cada tarea terminada.
4. `docs/ai/AUDITORIA.md`: el porqué de cada tarea (IDs S#, C#, V#, T#, A#, X#) con archivo y línea.
5. `docs/ai/SUNAT.md`: **la ley.** Reglas vigentes (§1), errores de facturación (§3), checklist de producción (§4) y diseño de la guía de remisión (§5).
6. Para los detalles del XML: `documentos/sunat/*.md` (guías oficiales de SUNAT; mandan las normas posteriores citadas en `SUNAT.md`).
7. Para el detalle funcional: `documentos/BRUCE_FIRE_Documento_Maestro_v9.md` (lee la fe de erratas del inicio).

## 1. Reglas de trabajo (obligatorias)

1. **SUNAT solo en beta.** Ninguna prueba ni ejecución puede enviar nada a producción. Usa los clientes SUNAT simulados que ya existen en las pruebas (`SunatClientInterface`) y el certificado `tests/Fixtures/certificates/test-certificate.pem`.
2. **Pruebas primero (TDD).** Antes de cada corrección, escribe la prueba Pest que falla; después, el código. Crea las pruebas con `php artisan make:test --pest`.
3. **Al cerrar cada fase:**
    - `vendor/bin/pint --dirty --format agent`.
    - Si tocaste rutas: `php artisan wayfinder:generate --with-form`.
    - Corre `composer ci:check` y no sigas mientras no esté en verde.
    - Haz un commit con el mensaje `Fix(fase X): …` y la línea final `Co-Authored-By` de tu herramienta.
    - **No hagas push.**
4. **Base de datos de pruebas:** MySQL de Laragon (`bruce_fire_testing`). Si no responde, pide al usuario que pulse "Start All" en Laragon. No uses la base `bruce_fire` (datos del negocio) para pruebas.
5. **Migraciones:**
    - Solo agregan. No borres columnas con datos sin migrar antes su contenido.
    - Cada una debe tener un `down()` que funcione.
6. **No toques:**
    - El diseño de la barra lateral (`.bf-nav-active`).
    - La protección del historial (FK `restrict`).
    - Las decisiones de `PROYECTO.md` §4.
7. **Enlaces del frontend:** solo con rutas Wayfinder (`@/routes/...` o `@/actions/...`), nunca URLs armadas a mano. Así nacieron los 404.
8. **Textos de la interfaz:** en español claro y sin jerga técnica.
9. **Al terminar cada fase:**
    - Actualiza `docs/ai/PLAN.md` (checkboxes).
    - Agrega una entrada corta en `docs/ai/BITACORA.md` (qué hiciste, pruebas, dudas).

## 2bis. La fase A del commit f55fb77 quedó mal: corregir antes que nada

La revisión de Claude (2026-10-06) encontró atajos falsos en el commit f55fb77. Las pruebas pasan, pero no se cumple la regla:

1. **S12, regresión:** `prepararDocumento()` reutiliza el XML viejo después de editar una venta "por enviar" (`EditarVentaEmitida.php:175`). El XML solo se congela cuando ya se envió a SUNAT al menos una vez; un "por enviar" se regenera siempre.
2. **S1, TLS falso:** `verifyTlsConfig()` solo revisa que la URL empiece con `https`. El `SoapClient` de Greenter sigue con `verify_peer=false` y `See` no deja cambiarlo. Hay que armar nuestro `SoapClient` (con `verify_peer` y `verify_peer_name` en `true`) y enviar con `BillSender`.
3. **S7, baja falsa:** `VoidElectronicDocument` solo marca "anulado" en la base de datos y no avisa a SUNAT. Debe hacerse así:
    - **Facturas y sus notas:** comunicación de baja (RA) con `SummarySender`, ticket y `getStatus`.
    - **Boletas y sus notas:** resumen diario con estado 3.
    - **Condiciones:** el usuario declara que el comprobante no se entregó; hay CDR aceptado; se envía dentro de 7 días desde el día siguiente al CDR.
    - **Estados:** "baja pendiente" mientras se espera y "anulado" solo cuando SUNAT acepta. Entonces se revierte la venta.
4. **V2/S17, permisos falsos:** `authorize()` deja pasar a cualquier vendedor. Hay que exigir el permiso Spatie de verdad en las rutas sensibles. Además, la NC o ND de un vendedor queda "por aprobar" hasta que el Gerente la apruebe; la del Gerente sale directo.
5. **S8, aviso que nadie ve:** los métodos de plazo existen, pero no se muestran. Se necesita un aviso visible en Facturación y en el dashboard del Gerente (factura: 3 días desde el día siguiente; boleta: 5 días).
6. **S4:** el producto tiene `tipo_afectacion_igv`. Revisa que la venta y la nota usen ese campo, no el booleano `aplica_igv`.

## 2. Orden de las fases y criterios de "listo"

Respeta este orden. Cada fase debe terminar en verde antes de empezar la siguiente.

### Fase A: facturación según SUNAT (`SUNAT.md` §1 y §3)

| Tarea     | Regla exacta a cumplir                                                                                                                                                                                                                                                                                           |
| --------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| S1        | `GreenterSunatClient` elige el servidor con `config('billing.sunat.beta')`: `SunatEndpoints::FE_BETA` o `FE_PRODUCCION`. Verifica el certificado TLS del servidor; no parchees `vendor/`. Prueba: con `beta=false` el endpoint es el de producción (sin enviar nada).                                            |
| S2        | Rechaza NC con motivo **04, 05 u 08** cuando el documento afectado es una **boleta** (guía NC, línea 888).                                                                                                                                                                                                       |
| S3        | ND: el **03 = "Otros conceptos"** y el **13 = "Penalidades", con afectación inafecta (30)** (R.S. 000048-2026, vigente desde el 1/08/2026).                                                                                                                                                                      |
| S4        | Afectación del IGV por línea según el producto o servicio (catálogo 07: **10** gravado, **20** exonerado, **30** inafecto), con los totales separados por afectación. Por defecto 10. Agrega el campo y el select en el catálogo del Gerente (reemplaza el booleano `aplica_igv` sin perder los datos actuales). |
| S5        | Guarda la **fecha y la hora** de emisión (`datetime`) y usa la misma en el XML y en el PDF.                                                                                                                                                                                                                      |
| S6        | La fecha de la NC y la ND se guarda al crearla y se reutiliza en cada reintento (no `now()`).                                                                                                                                                                                                                    |
| S9 y S10  | Catálogo 09 completo (01 a 13, con las descripciones oficiales: el 07 es "Devolución parcial"). Las notas reproducen la afectación del comprobante original; nada de 1.18 fijo para todo.                                                                                                                        |
| S8        | Aviso visible (dashboard del vendedor y del Gerente) cuando un comprobante pendiente está a 1 día de su plazo: **factura 3 días** calendario desde el día siguiente; **boleta 5** desde la emisión.                                                                                                              |
| S7        | **Comunicación de baja (RA)** solo para comprobantes **no entregados** al cliente, con CDR aceptado y **dentro de 7 días** calendario. Envío asíncrono con ticket. Pasado el plazo, solo NC.                                                                                                                     |
| S12 y S13 | El comprobante emitido queda congelado: se guardan los datos del cliente y de las líneas tal como se enviaron, y el reenvío reutiliza el **mismo XML firmado**. Los errores de red se reintentan; no se acepta nada sin un CDR válido.                                                                           |
| V2 y S17  | Los permisos de Spatie se aplican en el servidor (`authorize()` en los FormRequest o middleware `can:`). La NC y la ND necesitan aprobación del Gerente antes de emitirse.                                                                                                                                       |

**Listo cuando:** cada fila tiene su prueba en verde y `composer ci:check` pasa.

### Fase A2: seguridad (`AUDITORIA.md` X1 a X5)

- **X1:** filtro de sede en la capa de datos (scope o trait) para `Sale`, `ServiceOrder`, `Certificate` y `Equipment`, con pruebas de acceso cruzado (otra sede → 404). Corrige la ficha del cliente.
- **X2:** firmas y sellos al disco **privado**, servidos por una ruta autenticada. Migra los archivos que ya existen.
- **X3:** cambio de contraseña obligatorio en el primer ingreso y 2FA obligatorio para el Gerente.
- **X4 y X5:** `.env.example` con valores de producción comentados y correctos, `trustProxies` configurable por `.env`, y cabeceras CSP y HSTS en `AddSecurityHeaders`, sin romper Vite en desarrollo.

### Fase B: certificados y tipo de extintor

- **C1:** campos `agente` (lista del enum `EquipmentType`) y `capacidad` en el **producto**.
    - Se copian a la unidad al recibir y al equipo al vender.
    - En las recargas, el técnico elige el agente de la misma lista.
    - **Nunca** se usa "PQS-ABC" por defecto: si falta el agente, el certificado no se emite y el sistema dice qué falta.
    - La presión de la P.H. depende del agente real.
- **C2:** no inventar datos técnicos.
    - Al cobrar puede salir el certificado de **operatividad y garantía** de un extintor **nuevo**.
    - El de **prueba hidrostática** y la **capacitación** quedan "pendientes de datos técnicos" hasta que un técnico registre la fecha, la presión y el resultado reales.
- **Una sola regla de certificados:** reutiliza `CertificateRuleEngine` o la regla por destino y borra la otra.
- **A4:** el formulario de servicios del Gerente permite elegir el tipo de certificado.

### Fase C: ventas, caja y cobranzas

- **V1:** línea serializada siempre con cantidad 1.
- **V3:** la cotización pasa completa a la venta (descuento, condición de pago, observaciones y vehículo).
- **V4 y S11:** las NC y ND aceptadas cambian el saldo por cobrar, con rastro.
- **V5:** el vencimiento del equipo se renueva al **cerrar** el trabajo técnico, no en un borrador.
- **V6:** un adicional autorizado genera una deuda real enlazada.
- **V7:** el pago queda ligado al turno de caja; bloqueo contra la doble apertura y la doble confirmación; numeración interna sin `max(id)+1`.
- **V8:** el vendedor ve en la ficha del cliente solo sus ventas, cotizaciones y certificados (regla §90.1); el Gerente ve todo.
- **X6:** la tarea diaria no vence cotizaciones "aceptadas".
- **X7 (KPI del proyecto):**
    - Medir el tiempo de registro de una venta (desde que se abre hasta que se confirma).
    - Medir el tiempo de una cotización (desde que se crea hasta que se emite).
    - Contar los clientes recuperados por alertas (la cotización guarda la alerta de origen y se cuenta cuando termina en venta).
    - Mostrarlos en el dashboard del Gerente con el diseño nuevo de la fase F.
- **X8 y X9:**
    - Alertas filtradas por sede o vendedor, con registro de "contactado".
    - "Ofrecer recarga" elige el servicio según la capacidad y el agente del equipo, no por nombre.

### Fase D: técnicos (`AUDITORIA.md` T1 a T5; diseño en `anexos/investigacion-campo-y-comunicacion.md`)

- **Evidencias únicas (§33):** tabla de evidencias con orden, equipo opcional, etapa, usuario, fecha y archivo **privado** comprimido. Cámara del celular con `<input type="file" accept="image/*" capture="environment">`. Audio con `MediaRecorder`, sin librerías nuevas.
- **Conversación de la orden** sobre `ServiceOrderEvent` (no un chat aparte):
    - Pueden escribir el vendedor, los técnicos y el Gerente.
    - Se puede adjuntar foto, audio o archivo, y etiquetar un equipo.
    - Muestra los eventos del sistema en la misma línea de tiempo.
- **Firma táctil** (canvas propio, sin dependencias) en recojo, entrega, instalación, inspección y mantenimiento. Se imprime en el acta PDF.
- **Motor único de visitas de campo:** recojo, entrega, instalación, inspección y **mantenimiento** (este es nuevo) comparten el flujo: escanear equipos → checklist por plantilla → fotos de antes y después → firma → acta. Las pantallas actuales se reutilizan.
- **T4:** la instalación escanea cada unidad vendida. **T5:** formulario para registrar una deficiencia fuera del checklist.
- **Requisitos para el DRS:** anota en `docs/ai/BITACORA.md` la lista de requisitos nuevos que el dueño debe agregar al DRS (evidencias, conversación, firma, mantenimiento).

### Fase E: almacén y Gerente

- **A2:** tabla `product_categories` (y categoría en servicios).
    - Junto al select, un botón **"Gestionar"** que abre un modal para crear, renombrar y desactivar.
    - Solo se borra una categoría que nadie usa.
    - La marca "genera alertas de vencimiento" reemplaza la búsqueda por el nombre "extintor".
    - Migra las 6 categorías actuales.
- **A3:** la unidad de medida del servicio usa un select con el **catálogo SUNAT 03**, el mismo de productos. Greenter rechaza unidades desconocidas en vez de cambiarlas sin avisar.
- **A5:** no dejar dar de baja dos veces la misma unidad. **A6:** el botón "Registrar nueva recepción" lleva al formulario; Cobranzas del Gerente tiene el botón "Anular pago" con confirmación.
- **A1:** costo de compra en la recepción (costo promedio en el producto). El reporte valoriza el inventario al **costo**; si falta el costo, lo advierte.
- **Stickers:**
    - Tamaño **5 × 5 cm** en hoja A4, **20 por hoja** (4 × 5).
    - Contenido: logo, código de barras Code 128 y la serie `BF-EQ-` debajo. **Sin** datos del producto, sin vencimiento y sin P.H.
    - Opción "empezar en la posición N" y reimpresión de uno suelto.
- **A7:** paginar en la base de datos (stock y reportes); quitar el N+1 de Consulta Rápida.

### Fase F: interfaz y diseño de los KPI — **POSPUESTA (no la hagas)**

El dueño decidió (2026-10-06) rediseñar las interfaces **al final**, a partir de **prototipos** que se aprueban antes. En este encargo:

- **No** cambies diseño, componentes visuales, tipografías ni nombres de menús.
- Las fases funcionales solo agregan la interfaz mínima que necesitan (botones, formularios, listas), con los componentes que ya existen y rutas Wayfinder.
- Lo pendiente de interfaz queda en `docs/ai/PLAN.md` (fase F) y en `AUDITORIA.md` (X13 a X18) para la etapa de prototipos.

### Fase H: guía de remisión electrónica (`SUNAT.md` §5)

1. Actualiza `greenter/lite` a **^5.3** y agrega **`greenter/gre-api`**. Vuelve a pasar **toda** la suite de facturación; corrige lo que cambie de v4 a v5.
2. Datos maestros nuevos: vehículos (placa y categoría M1, L o N), conductores (DNI, nombre y licencia), peso por producto y código de establecimiento anexo por sede.
3. Módulo "Guías de remisión" (`DispatchGuide`):
    - **Serie y datos:** serie **T001**, tipo **09**, motivo del **catálogo 20** y modalidad 01 o 02.
    - **Traslado:** partida y llegada con ubigeo, peso bruto, fecha de inicio del traslado, vehículo y conductor.
    - **Documento relacionado:** en ventas, el comprobante.
4. Envío por la API REST de SUNAT: token OAuth (credenciales de `config/billing.php`, desde `.env`: `SUNAT_GRE_CLIENT_ID` y `SUNAT_GRE_CLIENT_SECRET`), ZIP con hash y **ticket**. Se consulta hasta tener el CDR. **La guía solo queda "lista para trasladar" con el CDR aceptado.**
5. **Desde dónde nace la guía:**
    - Venta → motivo 01.
    - Orden de recojo y de entrega → motivo **13**, descripción "Recojo para recarga/mantenimiento" (está marcado "por confirmar con el contador": déjalo configurable).
    - Traslado entre sedes → motivo 04, con estado **"en tránsito"** hasta que el almacén destino confirme (A8).
6. **Pruebas solo con respuestas simuladas de la API.** No envíes guías reales a SUNAT.

**La fase G (servidor y paso a producción) NO la hagas:** la hace el dueño con su hosting.

## 3. Cuándo detenerte y preguntar

- Si una corrección contradice una regla de `SUNAT.md` o una decisión de `PROYECTO.md` §4.
- Si una migración puede perder datos.
- Si la actualización a Greenter v5 rompe algo que no sabes resolver sin cambiar el comportamiento fiscal.
- Si `composer ci:check` no pasa después de 3 intentos razonables.

Al terminar todo: un resumen en `docs/ai/BITACORA.md` con las fases hechas, las pruebas, las dudas abiertas y la lista de requisitos nuevos para el DRS. **No hagas push**; el dueño revisa primero.
