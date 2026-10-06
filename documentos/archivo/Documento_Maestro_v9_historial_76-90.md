# Documento Maestro v9: historial (secciones 76 a 90)

> **No es especificación.** Estas secciones eran adendas, auditorías, investigaciones, planes y bitácoras fechadas entre el 2026-09-19 y el 2026-10-04. Se movieron aquí desde `documentos/BRUCE_FIRE_Documento_Maestro_v9.md` el 2026-10-06, para que el Maestro quede solo con la especificación. Conservan sus números (§76–§90), porque el código y otros documentos los citan.
>
> Varias afirmaciones de aquí ya no son ciertas. Por ejemplo, §77.1 dice que la guía de remisión está construida, y no lo está. El estado real está en `docs/ai/PROYECTO.md`, `docs/ai/PLAN.md`, `docs/ai/AUDITORIA.md` y `docs/ai/SUNAT.md`.

# 76. ADENDA — AUDITORÍA TÉCNICA Y PLAN DE EJECUCIÓN (2026-09-19)

Este documento maestro (v9) ya existía en el repositorio y fue
eliminado sin commitear antes de esta sesión. Se restauró desde
`git show HEAD` porque sigue siendo la fuente funcional vigente: casi
todo lo descrito por voz el 2026-09-19 (roles, dashboards, clientes,
equipos, checklist, deficiencias, autorización de adicionales,
cotización→venta sin re-digitar, condición de pago a crédito con
cuotas, certificados con motor de reglas y QR propio, GRE, cobranzas)
ya estaba especificado aquí y, según auditoría de código, ya está
implementado en un alto porcentaje (roles Almacén/Gerente/Técnico de
Campo/Técnico de Planta/Vendedor con permisos granulares vía
spatie/laravel-permission, coinciden 1:1 con la sección 35/36).

Esta adenda no reemplaza el documento; registra qué se auditó, qué
está confirmado roto, qué es genuinamente nuevo respecto a v9, y en
qué orden se ejecuta, siguiendo la Regla de Control de Cambios
(sección 74).

## 76.1 Confirmado sólido (no se toca)

- Matriz de roles/permisos (`php artisan permission:show`) ya replica
  la sección 36 exactamente.
- `Equipment`/`EquipmentEvent`/`EquipmentTransfer`: modelo de equipo
  del cliente ya separado del catálogo, con historial y estados
  (sección 7-8).
- `InventoryUnit` (serie, marca, capacidad, año, barcode, estado) +
  `InventoryMovement` (Kardex con stock antes/después, motivo,
  referencia): la trazabilidad de fondo ya es correcta.
- `SaleItemProcessor`: al vender una unidad serializada, ya descuenta
  stock, marca la unidad `vendido` y crea el `Equipment` del cliente
  automáticamente (sección 13.2 y 50.1 ya conectadas).
- Cotización → conversión a venta sin re-digitar (`QuoteController`),
  condición de pago a crédito con `SaleInstallment`/`SalePayment`,
  notas de crédito/débito (`CreditDebitNote`), guía de remisión
  (`ShippingGuide*`) y checklist/deficiencias (`ChecklistItem`,
  `Deficiency`) ya existen como módulos separados — no se reinventan,
  solo se auditan puntualmente si el usuario reporta un bug concreto.
- El modal "agregar cliente" en Ventas/Cotizaciones ya reutiliza el
  mismo componente que la pantalla de Clientes (`InlineClientDialog`
  → `ClientForm`), tal como se pidió por voz — no se duplica.

## 76.2 Confirmado roto (con evidencia de código)

1. Gestión de Inventario fragmentada — el motivo original de esta
   conversación. Hoy conviven `CatalogItem` (tabla `catalog_items`,
   con flags `controla_stock`/`control_serializado`), una migración
   pendiente sin aplicar que separa `products`/`services`
   (`2026_09_19_065235_...`, `2026_09_19_065236_...`) con
   `legacy_catalog_item_id`, y una tabla `inventory_stocks` aparte
   1:1. Resultado: 4 pantallas para un mismo concepto (Catálogo →
   Inventario → Recepción de lote → Movimientos) y, tras crear un
   producto, no hay un número de stock editable visible en su ficha
   (solo checkboxes y páginas separadas para "ajuste" o "recepción").
   Decisión ya tomada con el usuario: terminar la separación
   Product/Service (no volver a un `CatalogItem` unificado),
   eliminando `CatalogItem`/`catalog_items`/`inventory_stocks`, y
   ejecutar por etapas (Inventario primero, luego reconexión a
   Ventas/Cotizaciones/Órdenes de servicio/Reportes/Facturación
   SUNAT).
2. `ClientController::documentLookup` no revisa la base local antes
   de llamar a la API externa (APIsPeru) —
   app/Http/Controllers/ClientController.php líneas 65-91. El usuario
   pidió explícitamente: "si ya tenemos un cliente agregado... no
   llamamos de nuevo, buscamos internamente". Hoy siempre golpea la
   API aunque el RUC/DNI ya exista en `clients.numero_documento`.
   Fix: antes de invocar `DocumentLookupService`, buscar
   `Client::where('numero_documento', $numero)->first()` y devolver
   esos datos si existe, sin llamar a la API.

## 76.3 Genuinamente nuevo respecto a v9 (se agrega al alcance)

- Impresión de stickers de código de barras en hoja de 4 al recibir
  un lote de extintores nuevos en Almacén: la sección 9 y 41.8 ya
  cubren que se genera barcode, pero no el layout de impresión. Se
  agrega: al confirmar una Recepción de unidades serializadas, botón
  "Imprimir stickers" que arma una hoja A4 con grilla de 4 etiquetas
  (código de barras + código interno) por página, para no desperdiciar
  una hoja completa por unidad.
- Ejemplos de certificado y checklist recibidos como archivos de
  referencia el 2026-09-19 (quedan como insumo de diseño, no se
  transcriben aquí por ser documentos de terceros):
    - Planilla de inspección con columnas Ítem, N° interno, N° serie,
      ubicación, agente, capacidad, manómetro, pasador, manguera,
      marca/procedencia, fabricación, tarjeta, fecha próx. recarga,
      vencimiento P.H., observación — confirma exactamente la sección
      24 ("por extintor") y valida que debe ser una tabla dinámica de N
      filas, no N plantillas.
    - Certificado de instalación de lámina de seguridad con datos del
      cliente, detalle de instalación (mampara/medida), características
      técnicas, QR de verificación y firma — encaja en la sección 26
      como un tipo más de "Otros configurables" del motor de reglas,
      mismo patrón que Operatividad/P.H./Capacitación.
- Confirmación explícita de que el checklist/certificado debe
  generalizarse para todos los servicios técnicos de campo
  (fumigación, desratización, pozos sépticos, sistema de detección,
  cámaras, pozo a tierra, lámina de seguridad), no solo extintores —
  la sección 15 ya decía "Otros configurables"; se confirma que el
  motor de reglas (26.1) debe resolver la plantilla por tipo de
  servicio, no por texto libre.

## 76.4 Plan de ejecución por etapas (aprobado)

Etapa 1 — Gestión de Inventario (en curso):
terminar migración products/services, retirar
CatalogItem/catalog_items/inventory_stocks; un solo formulario "Nuevo
registro" (Producto/Servicio) con stock inicial y mínimo numéricos;
ficha de producto con ajuste de stock numérico visible; recepción de
lote (series) accesible desde la propia ficha del producto; sticker
de código de barras en hoja de 4 al recibir lote serializado; 3
pestañas: Productos en Stock · Servicios de Taller · Kardex.

Etapa 2 — Reconexión: SaleItemProcessor, StoreSaleRequest,
StoreQuoteRequest, DeficiencyController,
ServiceOrderChecklistController, ReportService,
BillingService/SaleDocumentBuilder apuntando a Product/Service, sin
catalog_item_id; fix de documentLookup con consulta local antes de la
API externa.

Etapa 3 (a definir tras Etapa 1-2): certificados con plantilla
dinámica por tipo y generalización a servicios no-extintor;
investigación SUNAT de estados de comprobante/CDR y GRE electrónica
(secciones 30-31, aún pendientes de investigar en v9).

## 76.5 Bug crítico encontrado y resuelto antes de la Etapa 1

Antes de tocar Inventario se detectó que **la base de datos de
desarrollo estaba desincronizada del código**: las migraciones
`2026_09_18_130001_create_sales_table.php`,
`2026_09_18_145714_create_electronic_documents_table.php` y
`2026_09_18_164400_create_credit_debit_notes_table.php` ya se habían
ejecutado, pero después alguien les agregó columnas nuevas editando el
archivo en vez de crear una migración nueva — esos cambios nunca
llegaron a la base real. Resultado: **toda venta, factura/boleta o
nota de crédito/débito fallaba con error SQL** ("no such column:
mto_valor_unitario", etc.) en la app real, aunque los tests pasaran
(usan una base efímera que sí corre todas las migraciones desde cero).

Se corrigió con una migración de reparación
(`2026_09_19_070000_repair_sales_billing_columns_drift.php`) que
agrega solo las columnas faltantes de forma idempotente, más el
arreglo del bug propio de
`2026_09_19_065236_add_product_service_and_tax_snapshots_to_sales_and_quotes.php`
(a `sale_items` le faltaban sus columnas de impuestos antes de que la
migración intentara actualizarlas). Regla para evitar que se repita:
**nunca editar una migración que ya corrió** (`php artisan
migrate:status` para verificar); si falta una columna, se crea una
migración nueva. Verificado: 445 tests pasan y el esquema de
`sales`/`sale_items`/`electronic_documents`/`credit_debit_notes` en la
base de desarrollo ya coincide con lo que el código escribe.

---

# 77. INVESTIGACIÓN CON FUENTES — GRE, CAJA Y DASHBOARDS (2026-09-19)

Por pedido explícito del usuario, esta sección documenta hallazgos con
fuente verificable, no supuestos.

## 77.1 Guía de Remisión Electrónica (GRE): ya está construida y bien

Auditoría de código: `ShippingGuide` (modelo), `ShippingGuideController`,
`ShippingService`, `ShippingGuideBuilder` y `GreenterService::sendDespatch()`
**ya existen y ya implementan exactamente el flujo de la sección 30-31**:

- Motivo de traslado con los códigos del Catálogo 20 de SUNAT (venta
  `01`, compra `02`, traslado entre establecimientos `04`, importación
  `08`, exportación `09`, otros `13`) — coincide con la fuente oficial
  ([Guía de Remisión | SUNAT](https://cpe.sunat.gob.pe/tipos_de_comprobantes/guiaderemision),
  [Guía de Remisión Electrónica - Modelo general | SUNAT](https://orientacion.sunat.gob.pe/guia-de-remision-electronica-modelo-general)).
- Usa el modelo `Despatch` de Greenter (no `Invoice`/`Note`), en un
  servicio separado (`ShippingService`), exactamente como pide la
  sección 31 ("Para GRE se mantendrá un servicio separado porque su
  envío utiliza flujo/API diferente").
- Guarda destinatario (cliente o datos libres), transportista
  (razón social/RUC), vehículo, conductor, modalidad
  (transporte público/privado) — todo lo que la sección 30 pedía.
- Asociada opcionalmente a una `Sale` (`sale_id` nullable), tal como
  el usuario intuía ("creo que va asociada a una venta").

**No hace falta investigarla ni reconstruirla — ya sigue el patrón
correcto.** Pendiente real (no arquitectónico): confirmar que la
serie/formato y los anexos técnicos de la Resolución N.º 000108-2026/SUNAT
(cambio de conductor en tránsito, vigente desde junio 2026) estén
cubiertos si BRUCE FIRE los necesita — la GRE-remitente es obligatoria
hasta el 31/08/2026 y la GRE-transportista hasta el 28/02/2027 con
periodo de discrecionalidad
([Guías de Remisión Electrónicas SUNAT: Cambios para 2026](https://llbsolutions.com/es/guias-remision-electronicas-sunat-cambios-clave-2026/),
[Guía de Remisión Electrónica 2026: Obligatoria Desde Julio](https://perugestiona.pe/tramites-sunat/guia-remision-electronica/)).
Esto se revisa cuando BRUCE FIRE empiece a emitir GRE en producción,
no ahora.

## 77.2 Control de caja: no existe, se agrega (patrón "arqueo ciego")

Confirmado por auditoría (`grep -ri caja`): **no existe ningún módulo
de control de caja** en el sistema. Se diseña con el patrón estándar
de POS ("arqueo ciego"), que es la práctica más usada porque fuerza
honestidad en el conteo (el sistema no le muestra al vendedor cuánto
_debería_ tener antes de que él cuente)
([Arqueo de caja: Checklist de control de efectivo en tienda](https://safetyculture.com/library/retail/arqueo-q5ufvffkecwk4ymc),
[Arqueo de caja: cómo hacerlo paso a paso](https://yo-facturo.com/blog/arqueo-de-caja-guia/)):

1.  **Apertura de turno**: el Vendedor cuenta el efectivo físico que
    tiene y lo declara antes de vender (fondo fijo inicial).
2.  Durante el turno, cada venta con forma de pago `efectivo` se suma
    automáticamente al esperado de esa caja (ya existe
    `SalePayment.forma_pago` para esto, no hay que inventar nada
    nuevo ahí).
3.  **Cierre de turno (arqueo ciego)**: el Vendedor cuenta el efectivo
    físico y lo declara **sin ver el total esperado**. El sistema
    calcula la diferencia después y la registra (sobrante/faltante).
4.  El Gerente ve el historial de aperturas/cierres de todos los
    vendedores, con diferencias resaltadas.

Tabla nueva sugerida: `cash_registers` (turno: usuario, fecha/hora
apertura, monto apertura, fecha/hora cierre, monto contado al cierre,
monto esperado calculado, diferencia, observación). No se mezcla con
`sale_payments` (que ya registra cada pago individual); `cash_registers`
solo agrupa el turno y hace el arqueo.

## 77.3 Dashboards por rol: hoy solo existe el de Gerente

Auditoría: `DashboardController` renderiza siempre el mismo dashboard
(`DashboardService::build()`), sin importar el rol — no hay
diferenciación por Vendedor/Almacén/Técnico. La sección 5 de este
documento ya especifica qué debe ver cada rol; falta implementarlo.
Regla confirmada con el usuario: el Vendedor ve el efectivo/ventas
**del día** (para su arqueo de caja) pero no el acumulado mensual de
la empresa; eso es exclusivo de Gerente/Administrador.

## 77.4 RUC/DNI: doble consumo de API — verificado, no hay llamada duplicada en el código propio

Se revisó `client-form.tsx` línea por línea: el lookup se dispara al
alcanzar el largo exacto (8 DNI / 11 RUC) y de nuevo en `onBlur`, pero
ambos casos están deduplicados por `lastQueriedDoc` (mismo
tipo+número no vuelve a llamar). El único modal de "agregar cliente"
en Ventas/Cotizaciones reutiliza el mismo componente, no lo duplica.
Con el fix de la sección 76.2 (consulta local antes de la API), un
mismo RUC/DNI ya conocido nunca vuelve a tocar la API externa. Si el
usuario sigue viendo consumo de 2 créditos por una consulta genuinamente
nueva, es del lado del proveedor (APIsPeru) — se debe confirmar en su
panel de facturación, no es un bug de este código.

## 77.5 Secuencia de trabajo actualizada

```text
Etapa 1 (en curso) — Gestión de Inventario: Product/Service, stock numérico, retirar CatalogItem.
Etapa 2 — Reconexión de Ventas/Cotizaciones/Reportes/Facturación a Product/Service.
Etapa 3 — Certificados dinámicos por tipo + generalización a servicios no-extintor.
Etapa 4 — Control de caja (arqueo ciego) + dashboards diferenciados por rol.
```

GRE (sección 77.1) no entra en esta secuencia porque ya está resuelta;
solo se revisa si se detecta un caso real no cubierto al usarla.

Nota: las notas de voz del usuario se guardan tal cual, sin editar, en
`documentos/notas_de_voz_usuario.md`, para no perder contexto entre
sesiones. Este documento (v9) es la versión ya organizada y con
fuentes; ese otro archivo es el material crudo de origen.

---

# 78. INVESTIGACIÓN ADICIONAL CON FUENTES — VACÍOS DETECTADOS (2026-09-19, continuación)

El usuario repitió por voz el mismo contexto de la sección 77 (roles,
catálogo muerto, certificados, cobranzas, técnicos) porque el
observador de memoria entre sesiones estuvo caído (ver aviso de
sistema) y temía que se hubiera perdido el hilo. **No se perdió**: todo
eso ya está resuelto en las secciones 76-77 con la decisión ya tomada
(catálogo se elimina, sección 76.2 punto 1) y el plan por etapas ya
aprobado (76.4/77.5). Esta sección solo documenta dos reglas de
negocio que el usuario mencionó por voz y que, al auditar el documento
completo, **no estaban capturadas en ningún lado todavía** — no son
opinión del usuario, son requisitos normativos verificables:

## 78.1 RUC debe estar "Activo" y "Habido" antes de facturar — regla dura, no solo advertencia

El usuario pidió explícitamente "no vamos a facturar a un RUC que está
inactivo" y pidió investigarlo a fondo. Confirmado con fuente: SUNAT
exige que, para incorporarse y operar en el sistema de emisión
electrónica, el RUC esté en condición **Activo** y con domicilio fiscal
**Habido**; si el domicilio pasa a "No habido", SUNAT bloquea la
emisión de comprobantes
([Condiciones para incorporarse al Sistema de Emisión Electrónica — SUNAT](https://orientacion.sunat.gob.pe/13-condiciones-para-incorporarse-el-sistema-de-emision-electronica),
[Qué significa estado activo en consulta RUC SUNAT](https://consultaruc-sunat.com/que-significa-estado-activo-consulta-ruc-sunat/)).

Esto no estaba en ninguna sección del documento (6, 12, 13, 28). Se
agrega como regla de negoción dura:

- El lookup de RUC (sección 6.1 / `DocumentLookupService`) debe guardar
  también `estado_contribuyente` (ej. `ACTIVO`) y
  `condicion_domicilio` (ej. `HABIDO`) devueltos por la fuente
  (APIsPeru u otra), no solo razón social y dirección.
- Al emitir **Factura o Boleta** (no aplica a Cotización, que es
  interna), si el cliente tiene `estado_contribuyente != ACTIVO` o
  `condicion_domicilio != HABIDO`, el sistema debe **bloquear el envío
  a SUNAT** con mensaje explícito, no solo mostrar una advertencia
  visual. Si el dato guardado tiene más de N días (a definir, sugerido
  30), se debe re-consultar antes de bloquear/permitir, para no quedar
  con un estado desactualizado indefinidamente.
- No aplica a Boleta con DNI (personas naturales no tienen estado
  RUC).

## 78.2 Prueba hidrostática de extintores: vencimiento a 5 años, no "a definir"

El usuario mencionó "vencimiento de la prueba aerostática" (aerostática
es un error de transcripción de voz; el término correcto es
**hidrostática**) como un campo del checklist (sección 24) y como tipo
de certificado (sección 15, 26) pero el documento nunca fijó la
periodicidad, dejándolo como dato libre. Confirmado con la norma
técnica peruana NTP 350.043-1 (INDECOPI): el intervalo máximo entre
pruebas hidrostáticas es de **5 años** para extintores portátiles, y
también de 5 años para los cilindros/botellas impulsoras de gas en
extintores de rueda
([NTP 350.043-1, 3ª edición 2011](https://www.regionpiura.gob.pe/documentos/dependencias/phpmZ0ZJJ.pdf),
resumen técnico en
[NTP 350.043: La Norma Peruana de Extintores, Explicada](https://www.firetrack.pe/recursos/ntp-350-043-extintores)).
La inspección visual, en cambio, es **mensual** según NFPA 10
([Checklist de auditoría de extintores según NFPA 10](https://nfpatoolkit.co/blog/checklist-auditoria-extintores.html)).

Se agrega como regla derivada, no como dato manual:

- `InventoryUnit` (o la unidad serializada del extintor) debe guardar
  `fecha_ultima_prueba_hidrostatica`. El sistema **calcula**
  `fecha_proxima_prueba_hidrostatica = fecha_ultima_prueba_hidrostatica + 5 años`
  — el técnico no la escribe a mano, para que las alertas de la
  sección 27 puedan dispararse solas.
- Igual patrón para la recarga anual (sección "vencimiento recurrente",
  50.4): la fecha de próxima recarga/certificado de operatividad se
  calcula a partir de la fecha de recarga/instalación + 1 año, no se
  captura como texto libre.

## 78.3 Confirmación: la decisión "matar catálogo" no requiere más investigación

El usuario volvió a insistir por voz en eliminar "Catálogo" como
concepto separado ("no catálogo, que eso no existe"). Esto ya es
exactamente la decisión tomada en la sección 76.2 punto 1 y el plan de
76.4/77.5 (Etapa 1): se retira `CatalogItem`/`catalog_items`, el
inventario pasa a un único flujo de alta "Producto o Servicio" con
stock numérico visible en la propia ficha, sin pantallas separadas. No
hay nada nuevo que decidir aquí — se reafirma para que quede explícito
que no es una opinión pendiente de validar, es una decisión ya cerrada
que solo falta ejecutar en la Etapa 1.

## 78.4 Corrección menor a la sección 41.3 / docs técnicos de Greenter

`docs/FACTURACION_GREENTER_SUNAT.md` (guía técnica de referencia para
implementación, separada de este documento) ya cubre en detalle:
tipos de documento (Catálogo 01), detracciones SPOT al 12% sobre
S/700, formas de pago contado/crédito con cuotas (RS 193-2020), notas
de crédito/débito con motivos (Catálogo 09), GRE remitente vía API
REST 2022+, ciclo de envío/CDR con clasificación de códigos de
respuesta, y QR tributario oficial. No se duplica aquí; se referencia
como la fuente técnica de implementación para la Etapa 3+ (Facturación
SUNAT).

## 78.5 Casos especiales de Greenter revisados con fuente oficial (2026-09-19)

Se investigó toda la documentación de `greenter.dev` (ejemplos de
exonerada, gratuita, descuento por línea, percepción, anticipo,
detracción, exportación, ICBPER, boleta, contingencia, forma de pago,
más `/usage`, `/starter`, `/production`, `/faq`, `/packages/xml`) y se
volcó el análisis completo en `docs/FACTURACION_GREENTER_SUNAT.md`
sección 12, con la decisión de qué sí construir y qué no, para no
sobre-construir casos que el negocio no tiene. Dos hallazgos relevantes
para este documento:

- **Anticipo** (§12.1 del doc técnico) sí aplica al negocio: las
  instalaciones (sistema de detección, cámaras, pozo a tierra) suelen
  cobrar un adelanto antes de ejecutar la obra. Se agrega a la sección
  12.2 (conversión de cotización a venta): cuando una cotización de
  instalación se acepta con anticipo, se emite primero la factura del
  anticipo y luego la factura final referenciándola (Greenter lo
  soporta de forma nativa vía `Prepayment`).
- **Código de detracción dudoso**: el código `022` que ya estaba en
  `docs/FACTURACION_GREENTER_SUNAT.md` §4 (Otros servicios
  empresariales) puede no ser el correcto — el más literal para
  "recarga y mantenimiento de extintores" sería `020` (Mantenimiento y
  reparación de bienes muebles) del Catálogo 54, pero esto **no se
  puede resolver con documentación pública genérica**; queda marcado
  como pendiente de confirmar con el contador de BRUCE FIRE antes de
  la Etapa 3, no se debe codificar a ciegas.
- Exonerada, percepción, exportación e ICBPER no aplican al modelo de
  negocio actual (venta/servicio local gravado con IGV, no agente de
  percepción, no exporta, no vende bolsas plásticas) — se documentan
  como descartados a propósito, no como pendientes.

---

# 79. VERIFICACIÓN CRUZADA — NOTA DE VOZ 2026-09-19 vs DOCUMENTO (2026-09-20)

Se auditó `documentos/notas_de_voz_usuario.md` (entrada del 2026-09-19)
punto por punto contra este documento para confirmar que nada quedó
sin integrar. Resultado: **todo el contenido funcional/de producto ya
estaba capturado**, con correspondencia exacta:

- Roles, almacenero, stickers de barcode en hoja de 4 → §76.3.
- Certificados por destino (local: Operatividad+Capacitación;
  vehículo: Operatividad+P.H.) → ya especificado literalmente en §26.1,
  coincide palabra por palabra con lo dictado.
- Otros servicios con certificado propio (fumigación, desratización,
  detección, pozo a tierra, cámaras, lámina de seguridad, etc.) → §76.3.
- Trazabilidad de venta (unidades serializadas agrupadas por cantidad,
  serie interna) → §76.1 (confirmado sólido, no se toca).
- Clientes: autocompletado RUC/DNI sin re-consultar si ya existe, RUC
  no habido bloqueado → §76.2 punto 2 (fix) y §78.1 (regla dura).
- Inventario (queja central), Producto/Servicio, stock numérico → §76.2
  punto 1 y §76.4 (Etapa 1, decisión ya cerrada).
- Checklist técnico generalizable a todos los servicios → §76.3.
- Gerente/Admin mismo rol, dashboards diferenciados sin acumulado
  mensual para Vendedor → §77.3.
- Control de caja (arqueo ciego, investigar) → §77.2.
- Guía de Remisión (investigar) → §77.1 (ya resuelta, no requiere
  reconstrucción).
- Cotización como documento interno que se envuelve en boleta/factura
  → §76.1.

Dos elementos de la nota de voz **no tenían ningún lugar en el
documento** porque no son especificación de producto sino principios
de cómo construir/colaborar. Se agregan aquí para que no se pierdan:

## 79.1 Principio de arquitectura: evitar hardcodeo (regla general, no solo RUC/API)

La nota de voz pide explícitamente evitar "harcodeo" como queja
general de arquitectura. Hasta ahora solo estaba resuelto el caso
puntual (RUC/DNI: no volver a llamar la API si el dato ya existe
localmente, §76.2 punto 2 y §78.1). Se eleva a regla de arquitectura
general, aplicable a todo el sistema, no solo a ese caso:

- Catálogos de SUNAT (tipos de documento, motivos de traslado, códigos
  de detracción, unidades de medida), tipos de certificado, roles y
  permisos, y cualquier lista que pueda cambiar por normativa o por
  decisión de negocio, se modelan como datos configurables (tabla,
  seeder, config de Laravel) — nunca como `match`/`switch`/arrays
  literales repetidos en varios controladores.
- Antes de llamar a una API externa (APIsPeru/RENIEC, Greenter,
  cualquier otra), siempre se verifica primero si el dato ya existe en
  la base local; la API es el último recurso, no el primero. Ya
  aplicado a clientes (§76.2.2); debe aplicarse igual a cualquier
  integración externa futura.
- Al detectar un valor fijo repetido en código que debería ser
  configurable, se reporta y se corrige en la misma tarea si el
  alcance lo permite, en vez de replicarlo.

## 79.2 Estilo de colaboración del usuario con Claude (meta, no producto)

Instrucción repetida varias veces por voz, registrada aquí de forma
explícita porque gobierna cómo se debe trabajar en todas las sesiones
de este proyecto, no solo esta: el usuario **no** quiere entregar todo
el contexto de golpe para que Claude "arregle todo" en un solo tiro.
Prefiere dar una idea vaga y que Claude:

1.  la mejore y complete los huecos con investigación propia (con
    fuentes verificables, como en §77-78, no supuestos);
2.  decida cómo conectar esa idea con lo que ya existe en el sistema;
3.  documente en Markdown (este documento y
    `documentos/notas_de_voz_usuario.md`) todo lo investigado, para no
    perder el hilo entre sesiones aunque el usuario no repita el
    contexto completo la próxima vez.

Esta preferencia ya se está seguiendo de facto desde §76 en adelante;
se deja explícita para que una sesión futura no vuelva a pedir "dame
todo el contexto" innecesariamente.

---

# 80. AUDITORÍA DEL ROL VENDEDOR Y VERIFICACIÓN DE GREENTER (2026-09-20)

Por pedido explícito del usuario, se auditó con evidencia de código
(no supuestos) el rol Vendedor completo (43 rutas:
`app/Http/Controllers/Vendedor/*`) y su integración real con Greenter
y con la API de RUC/DNI. Progresó desde la última auditoría (§76-78):
ya existe módulo de Caja (`CashRegisterController`/`CashRegister`,
contradice el "no existe" de §77.2) y el Dashboard de Vendedor ya está
correctamente aislado por rol (contradice el "no diferenciado" de
§77.3, ver `DashboardController` con comentario explícito en código:
"NUNCA expone acumulados mensuales ni datos globales de la empresa").

## 80.1 Confirmado sólido

- `RucLookupService` (reemplazó a `DocumentLookupService`): ya busca
  primero en `clients.numero_documento` local y solo golpea APIsPeru
  si no existe — cumple §76.2 punto 2. Ya captura y persiste
  `estado_contribuyente`/`condicion_domicilio` en cada consulta.
- Dashboard Vendedor (`DashboardController`): solo ventas/caja del día
  del vendedor autenticado (`where('vendedor_id', $user->id)`), cero
  acumulado mensual/empresa — cumple §77.3 al pie de la letra.
- Cobertura funcional completa: ventas, cotizaciones, clientes (+sedes
  +vehículos), caja (apertura/cierre con arqueo), cobranzas,
  certificados, deficiencias (+autorización), facturación SUNAT
  (PDF/XML/CDR/reenvío), notas de crédito, órdenes de servicio,
  comunicación, alertas, escaneo de series, búsqueda de inventario por
  serie. No hay ninguna función del flujo comercial sin ruta/controller.

## 80.2 Bug confirmado — KPIs del Vendedor filtran mal (viola la regla que el propio Dashboard sí respeta)

`SaleController::index()` (líneas 41-45) calcula `ventas_del_mes` con
`Sale::whereBetween('fecha', [...])->sum('total')` y `comprobantes` con
`Sale::count()` — **sin filtrar por `vendedor_id`**: suman ventas de
TODA la empresa, no las del vendedor logueado. `BillingController::index()`
(líneas 51-56) tiene el mismo problema con `emitidos_hoy`/`aceptados_hoy`/
`observados`/`rechazados` sobre `ElectronicDocument` global. Contradice
directamente la regla que `DashboardController` sí implementa
correctamente en la misma capa de rol. Fix: agregar
`->where('vendedor_id', $user->id)` (o el join correspondiente vía
`sale.vendedor_id` en Billing) a ambos bloques de KPIs.

## 80.3 Bug confirmado — regla §78.1 (RUC Activo/Habido) capturada pero no aplicada

El dato se guarda (`RucLookupService`, `Client::estado_contribuyente`/
`condicion_domicilio`) pero **no hay ningún bloqueo real** antes de
emitir: ni `StoreSaleRequest::rules()`, ni `CreateSale::handle()`, ni
`EmitElectronicDocument::handle()` verifican esos campos. Un cliente
`INACTIVO`/`NO HABIDO` puede facturar (Factura o Boleta) sin obstáculo,
contra la regla dura ya decidida en §78.1. Fix: agregar la validación
en `EmitElectronicDocument::handle()` (antes de `reserveNextCorrelativo`)
o como regla custom en `StoreSaleRequest` cuando `comprobante_tipo`
sea factura/boleta y el cliente tenga `tipo_documento = RUC`.

## 80.4 CRÍTICO — Greenter está instalado pero NO integrado de verdad: nunca se construye ni se firma un comprobante real

Verificado con `composer show`: `greenter/core`, `greenter/lite`,
`greenter/ws`, `greenter/xml`, `greenter/xmldsig` (v4.3.x) SÍ están
instalados. `GreenterSunatClient::send()` SÍ instancia `Greenter\See`
correctamente (certificado, Clave SOL) y llama a `$see->sendXml(...)`.
Pero el flujo completo tiene un hueco central, admitido en el propio
código:

- `GreenterService::build()` arma un **array PHP plano** a partir del
  `Sale`, no un objeto `Greenter\Model\Sale\Invoice` (ni `Note` para
  notas de crédito/débito, ni `Despatch` para GRE). El propio docblock
  de la clase lo dice: _"Esta capa no firma ni envía XML: solo
  transforma el Sale a datos de facturación para que luego puedan
  convertirse a objetos Greenter cuando exista certificado."_
- `GreenterService::signNormalizedPayload()` no firma nada: hace
  `json_encode(['document_name' => ..., 'payload' => $payload])` y
  llama a ese resultado "xmlSigned".
- `GreenterSunatClient::send(string $xmlSigned, ...)` recibe ese JSON y
  lo pasa tal cual a `$see->sendXml('invoice', $documentName, $xmlSigned)`
  — SUNAT recibiría un JSON etiquetado como XML firmado: **fallaría en
  producción real**, esto solo "funciona" hoy porque nunca se ejecuta
  contra SUNAT real.
- Los 445 tests que pasan (§76.5) nunca ejercitan este camino: cada
  test que toca facturación enlaza un fake de `SunatClientInterface`
  (`tests/Feature/BillingModuleTest.php` línea 51) que devuelve
  `codigo: 0, mensaje: 'Aceptado'` sin tocar Greenter real. Por eso el
  hueco no se detecta en CI.

**Qué falta realmente** (no es investigación nueva — `docs/FACTURACION_GREENTER_SUNAT.md`
ya documenta el diseño correcto en detalle, secciones 4-12; falta
ejecutarlo):

1. Un builder (`SaleDocumentBuilder` o similar, mencionado como
   objetivo en §76.4 pero no creado aún) que convierta el payload de
   `GreenterService::build()` en un objeto real
   `Greenter\Model\Sale\Invoice` (o `Note`/`Despatch` según tipo), con
   `Company`, `Client`, `SaleDetail[]`, `Legend[]`, `FormaPagos`,
   `Charge`s/detracción — todo lo que `docs/FACTURACION_GREENTER_SUNAT.md`
   ya especifica.
2. Reemplazar `signNormalizedPayload()`/`sendXml()` por el flujo
   estándar de Greenter: `$see->send($invoice)` (que internamente
   construye XML, firma con `xmldsig` y envía), usando el objeto real,
   no una firma manual de string.
3. Mantener un test de integración real (no solo con fake) contra el
   ambiente BETA de SUNAT (`SUNAT_BETA=true`, credenciales de prueba)
   antes de dar por cerrada la Etapa 3 (Facturación SUNAT, §76.4).

Esto no bloquea la Etapa 1 (Inventario, en curso) pero sí es
información crítica para no asumir que "facturación ya funciona" — el
95% del trabajo de research/config ya está (series, detracción, notas
de crédito, GRE, RucLookup), pero el paso de construir+firmar el
comprobante real con objetos Greenter está sin hacer.

## 80.5 Nota — Etapa 1/2 de Product/Service sigue pendiente

`catalog_item_id`/`catalogItem` sigue presente en decenas de
referencias, incluida la propia `GreenterService` (usa
`$item->catalogItem->codigo`/`nombre`/`unidad_medida`). Confirma que la
Etapa 2 (§76.4: reconexión de `GreenterService` a `Product`/`Service`)
sigue sin empezar — cuando se ejecute, `GreenterService` debe
actualizarse para leer de `Product`/`Service` en vez de `CatalogItem`.

---

# 81. CORRECCIÓN DEL ROL VENDEDOR — GREENTER REAL, RUC ACTIVO/HABIDO Y KPIs (2026-09-20)

A pedido explícito del usuario ("arregla corrige y alinea e integra que
todo ese rol esté funcionando todo"), se corrigieron los 3 hallazgos de
§80 más un cuarto encontrado durante la implementación. **Verificado
extremo a extremo contra el ambiente BETA real de SUNAT** (no solo con
tests): una Factura de prueba fue construida, firmada y enviada, y
SUNAT respondió con código 0 y observaciones reales de formato de
dirección — exactamente lo esperado con datos de empresa de prueba.
Detalle de cada corrección:

## 81.1 Integración Greenter real (cierra §80.4)

- `GreenterService::buildInvoice()` (antes `build()`) ahora construye
  un objeto real `Greenter\Model\Sale\Invoice` (Company, Client,
  SaleDetail[], FormaPago, Cuotas si es crédito, Detracción si aplica,
  Legends con monto en letras vía `NumberFormatter` con `SPELLOUT`) en
  vez de un array plano.
- `GreenterService::sign()` (antes `signNormalizedPayload()`, que solo
  hacía `json_encode`) ahora firma de verdad con `Greenter\See::getXmlSigned()`
  usando el certificado configurado.
- `EmitElectronicDocument` guarda `xml_path` (antes nunca se llenaba)
  y usa `$invoice->getName()` (formato `RUC-tipoDoc-Serie-Correlativo`)
  como nombre de documento — el código anterior armaba el nombre a mano
  como `"{serie}-{correlativo}"`, sin RUC ni tipoDoc.
- **Bug adicional encontrado en producción (no en tests) durante la
  verificación manual**: `GreenterSunatClient` llamaba a
  `$see->sendXml('invoice', ...)` con el alias corto `'invoice'`, pero
  `XmlBuilderResolver::findBuilderType()` de la versión instalada de
  Greenter (4.3.x) espera el FQCN completo (usa
  `substr(strrchr($docClass, '\\'), 1)` para extraer el nombre de
  clase) — con `'invoice'` (sin `\`) esto lanzaba
  `TypeError: substr(): Argument #1 ($string) must be of type string, false given`.
  Este bug ya existía antes de esta sesión (la ruta nunca se
  ejecutaba realmente porque el paso anterior devolvía JSON) y solo
  salió a la luz al hacer funcionar la firma real. Fix: usar
  `Greenter\Model\Sale\Invoice::class` en vez del string `'invoice'`.
- `ResponseClassifier::classify()` ahora distingue código 0 con notas
  de observación (`observado`) de código 0 sin notas (`aceptado`),
  igual que la clasificación oficial SUNAT documentada en
  `docs/FACTURACION_GREENTER_SUNAT.md` §9.
- Notas de crédito/débito siguen sin poder emitirse (no se puede
  construir un `Note` correcto porque `electronic_documents` no
  guarda su desglose de montos) — `buildInvoice()` lanza una excepción
  clara en vez de fabricar datos. `IssueCreditNote` sigue sin llamar a
  `EmitElectronicDocument`; sigue pendiente como trabajo futuro que
  requiere una migración nueva (columnas de montos en
  `electronic_documents` o una tabla de líneas de la nota).
- Certificado de prueba (`tests/Fixtures/certificates/test-certificate.pem`,
  autofirmado, generado con OpenSSL solo para firmar en tests/local, no
  sirve para producción) — necesario porque el certificado real de
  SUNAT no existe en el repo ni en `.env`.

## 81.2 Regla RUC Activo/Habido aplicada de verdad (cierra §80.3, ejecuta §78.1)

Se creó `App\Actions\Sales\ConfirmSale` y la ruta
`POST vendedor/ventas/{sale}/confirmar` (antes no existía ningún punto
de entrada que pasara una venta de `borrador` a `confirmada`, ni que
disparara la emisión — `EmitElectronicDocument::handle(Sale)` nunca se
llamaba desde ningún controlador). Ahora: confirmar bloquea con
`ValidationException` si el cliente tiene RUC (no aplica a Boleta con
DNI) y su `estado_contribuyente`/`condicion_domicilio` no son
`ACTIVO`/`HABIDO`; si pasa, cambia el estado y emite el comprobante
electrónico en la misma transacción (rollback completo si SUNAT
falla). Botón "Confirmar y emitir" agregado en
`resources/js/pages/vendedor/ventas/show.tsx`.

## 81.3 KPIs del Vendedor acotados a sus propias ventas (cierra §80.2)

`SaleController::index()` y `BillingController::index()` ahora filtran
por `vendedor_id`/`sale.vendedor_id` en vez de sumar la empresa
completa, igual que ya hacía `DashboardController`.

## 81.4 Verificación

- 165 tests (578 assertions) en verde, incluidos 3 tests nuevos de
  `SaleConfirmationTest` y 3 nuevos/reescritos en `BillingModuleTest`
  que verifican XML real (no JSON), nombre de documento con formato
  SUNAT, y clasificación `observado` vs `aceptado`.
- Verificación manual en navegador contra el ambiente BETA real de
  SUNAT: venta creada → RUC verificado localmente (sin llamar a
  APIsPeru, ya existía) → confirmada → Factura F001-1 construida,
  firmada y **aceptada por SUNAT con observaciones reales de formato**
  (esperado: `BILLING_COMPANY_UBIGEO`/`DEPARTAMENTO`/etc siguen vacíos
  en `.env`, son datos reales de la empresa pendientes de configurar
  antes de producción, no un bug). `vendor/bin/pint` y
  `npm run types:check` limpios. Datos de prueba y cambios temporales
  de `.env` revertidos tras la verificación.
- Pendiente real para producción (no de código): completar
  `SUNAT_RUC`/`USUARIO_SOL`/`CLAVE_SOL`/`CERT_PATH` y
  `BILLING_COMPANY_*` con los datos reales de BRUCE FIRE.

---

# 82. VERIFICACIÓN LEGAL — QUÉ DEBE LLEVAR UNA FACTURA/BOLETA PARA NO ARRIESGAR MULTA (2026-09-21)

A pedido explícito del usuario ("investiga qué debe tener una boleta y
factura para que no nos multen"), se auditó el PDF generado contra el
**Reglamento de Comprobantes de Pago** (Art. 8, 9 y 10 — fuente oficial:
[sunat.gob.pe/legislacion/comprob/regla/capituloIII.pdf](https://www.sunat.gob.pe/legislacion/comprob/regla/capituloIII.pdf))
y [orientacion.sunat.gob.pe](https://orientacion.sunat.gob.pe/03-boleta-de-venta),
numeral por numeral.

## 82.1 Ya cumplido (verificado campo por campo)

Factura (Art. 8 num. 1) y Boleta (num. 3): razón social/nombre comercial
emisor, dirección fiscal, RUC, denominación del comprobante,
serie-correlativo, datos del adquirente (nombre + RUC/DNI), descripción
del bien/servicio con cantidad y unidad de medida, precios unitarios,
valor de venta sin tributos, monto discriminado de IGV con la tasa,
importe total numérico **y literal** (monto en letras), fecha de
emisión, signo de moneda (S/), y placa del vehículo cuando el servicio
es de mantenimiento/reparación para vehículos automotores (Art. 8 num.
1.18). Todo esto ya estaba o quedó cubierto por el trabajo de esta
sesión (§81).

## 82.2 Vacío real encontrado y corregido: leyenda y cuenta de detracción

El Art. 8 exige que, cuando una operación está sujeta al Sistema de
Pago de Obligaciones Tributarias (SPOT/detracción — servicios de
recarga/mantenimiento sobre S/700), el comprobante lo declare. Ya se
generaba correctamente en el XML enviado a SUNAT (legenda 2006,
`GreenterService`), pero **la representación impresa (PDF) no lo
mostraba** — un comprobante que se ve "limpio" en papel pero cuya
versión electrónica dice otra cosa es exactamente el tipo de
inconsistencia que genera observaciones/riesgo en una fiscalización.

Se corrigió:

- Nuevo campo `CompanySetting.cuenta_detraccion` (cuenta del Banco de
  la Nación), editable desde Gerente → Datos de la empresa.
- El PDF ahora muestra, cuando aplica: la leyenda "Operación sujeta al
  Sistema de Pago de Obligaciones Tributarias", el código de bien
  (Catálogo 54) y el monto de la detracción, más la cuenta de depósito
  si está configurada.
- Test añadido (`BillingModuleTest`) que verifica que la leyenda y la
  cuenta aparecen en el HTML renderizado cuando el servicio supera
  S/700.

## 82.3 Verificado y confirmado como decisión ya tomada, no un vacío

El Art. 8 (num. 1.9 y 3.7) pide indicar el número de serie del bien
vendido "si se trata de un bien identificable". El PDF actual **no**
desglosa el número de serie por línea cuando se venden varias unidades
iguales — pero esto ya es una decisión de negocio explícita y
documentada del usuario (§76.1, notas de voz 2026-09-19: "al vender 9
extintores iguales se agrupan como 'cantidad 9'... cada uno mantiene su
serie individual de forma interna, no se desglosa en la factura salvo
que el ID sea distinto"), y la propia norma lo permite ("si no fuera
posible indicar el número de serie... al momento de la emisión, dicha
información se consignará al momento de la entrega del bien"). No se
toca sin que el usuario lo pida explícitamente.

## 82.4 Nota de cumplimiento aparte de la factura misma

El Art. 8 num. 3.10 exige, además, que el RUC del cliente esté
verificado como Activo/Habido antes de facturar — esa regla es
independiente del contenido del PDF y **ya está implementada y
verificada** en `ConfirmSale` (§78.1, §80.3/81.2), bloqueando la
emisión antes de siquiera generar el comprobante.

## 82.5 Contadores del sidebar del Vendedor eran placeholders fijos (corregido)

El usuario detectó que el badge "Clientes 1,240" del menú lateral no se
movía. Confirmado: `resources/js/components/vendedor-sidebar.tsx` tenía
`count: '1,240'`, `'7'`, `'4'`, `'3'` como strings literales para
Clientes/Cotizaciones/Alertas/Deficiencias — nunca estuvieron
conectados a datos reales, eran diseño de referencia sin cablear.

Se corrigió agregando un prop compartido `sidebarCounts` en
`HandleInertiaRequests::share()`, calculado solo cuando el usuario
tiene el rol Vendedor (evita consultas innecesarias en páginas de otros
roles), igual de lazy que `currentTeam`/`teams` ya existentes:

- `clientes`: `Client::where('activo', true)->count()` (global, no hay
  noción de "cliente asignado a un vendedor" en el modelo).
- `cotizaciones`: cotizaciones en estado `enviada` del vendedor
  autenticado (`vendedor_id`), mismo patrón que ya usa
  `DashboardController`.
- `alertas`: equipos con `proxima_fecha_atencion` o
  `proxima_prueba_hidrostatica` dentro de los próximos 7 días —
  aproximación liviana (conteo directo en BD) del mismo criterio que ya
  usa `AlertController` para el segmento "vencidas"+"esta_semana", sin
  duplicar su agrupación completa en PHP.
- `deficiencias`: `Deficiency::where('estado', 'esperando_autorizacion')`
  — las que están pendientes de que el Vendedor las autorice.

Verificado en navegador: con 5 clientes activos sembrados, el sidebar
mostró "5", no "1,240". Test de regresión en
`tests/Feature/SidebarCountsTest.php` (incluye caso de que el prop sea
`null` para un rol que no es Vendedor).

## 82.6 Certificados: confirmado que falta generación de PDF y verificación pública

El usuario preguntó si el módulo de Certificados también está
incompleto. Confirmado con auditoría de código (no se tocó en esta
sesión, queda documentado para la próxima):

- `CertificateController` solo tiene `index()`/`show()` — no hay
  `store()`/`create()`, no se generan certificados desde el panel
  Vendedor.
- El modelo `Certificate` ya tiene `qr_token` (campo listo), y
  `PublicCertificateVerificationController::show()` ya expone un
  endpoint público por `qr_token` — pero **devuelve JSON, no una
  página HTML de verificación** que un cliente pueda abrir escaneando
  el QR.
- **No existe generación de PDF del certificado** (búsqueda de
  `pdf|dompdf|barryvdh` en `app/` solo encuentra resultados en
  `Billing`, nada en `Certificados`).
- No hay ninguna clase en `app/Actions/Certificados/*` — el motor de
  reglas de certificados (§26.1, plantilla dinámica por tipo) sigue
  sin implementarse.

Es un vacío real y del mismo tamaño que la Guía de Remisión (§77.1
corregido, ver §80): requiere su propio ciclo de trabajo (plantilla
Blade por tipo de certificado, reutilizando el `ComprobantePdfService`
como patrón, más la página pública de verificación). Queda como
siguiente fase pendiente de decidir con el usuario, no se construye
sin confirmación explícita de alcance.

---

# 83. PLAN — CIERRE DE VACÍOS DEL ROL VENDEDOR (planificado 2026-09-21, sin construir todavía)

Roadmap de los 4 vacíos confirmados en §82.5-82.6 para el rol Vendedor,
en el orden recomendado (de menor a mayor esfuerzo, cada uno cierra un
riesgo real):

## 83.1 Orden recomendado y por qué

1. **Notas de crédito/débito** (esfuerzo bajo-medio): ya existe todo el
   flujo (`IssueCreditNote`, `CreditNoteController`, pantalla) salvo
   guardar el desglose de montos y llamar a `EmitElectronicDocument`.
   Es completar algo que ya está casi ahí, no construir de cero.
2. **Cotización — PDF** (esfuerzo bajo): la Cotización no es un CPE
   SUNAT, así que no necesita Greenter ni QR tributario — solo
   reutilizar `ComprobantePdfService`/plantilla Blade con una variante
   simple ("COTIZACIÓN — Documento interno, no válido como comprobante
   de pago"). El más rápido de los cuatro.
3. **Certificados** (esfuerzo medio-alto): motor de reglas por tipo de
   servicio (§26.1) + plantilla PDF por tipo + página pública de
   verificación (HTML, no JSON) usando el `qr_token` que ya existe.
4. **Guía de Remisión** (esfuerzo alto): único que requiere construir
   un módulo completo desde cero — modelo, migración, integración
   Greenter (`Despatch`, API REST GRE con credenciales propias, ya
   documentado en `docs/FACTURACION_GREENTER_SUNAT.md` §8), controlador,
   pantallas, y solo al final el PDF.

## 83.2 Notas de crédito/débito — plan técnico

1. Migración: agregar a `electronic_documents` (o tabla nueva
   `electronic_document_items` para no ensuciar la tabla genérica) los
   campos de montos: `subtotal`, `igv`, `total`, y una tabla de líneas
   (`codigo`, `descripcion`, `cantidad`, `precio_unitario`, `subtotal`)
   — igual forma que `sale_items`, porque una nota no siempre repite
   exactamente los ítems de la venta (puede ser descuento parcial).
2. `IssueCreditNote::handle()` ya recibe `$importe` pero lo descarta —
   debe persistirlo, y agregar parámetro de líneas afectadas (con
   default = las mismas líneas de la venta original para el caso de
   anulación total, que es el caso más común según los PDFs de
   referencia que pasó el usuario).
3. `GreenterService`: nuevo método `buildNote()` (hoy `buildInvoice()`
   lanza excepción para nota_credito/nota_debito a propósito) usando
   `Greenter\Model\Sale\Note`, ya documentado en
   `docs/FACTURACION_GREENTER_SUNAT.md` §7 con motivos del Catálogo 09.
4. `IssueCreditNote` debe llamar a `EmitElectronicDocument::sendDocument()`
   al final, igual que ya hace `ConfirmSale` para Factura/Boleta.
5. Reusar `ComprobantePdfService`/plantilla (ya tiene el bloque
   condicional para `nota_credito`/`nota_debito` con "DOCUMENTO QUE
   MODIFICA", solo falta que le lleguen datos reales).

## 83.3 Cotización — PDF, plan técnico

1. Nueva vista Blade `resources/views/pdf/cotizacion.blade.php`
   (variante simplificada de `comprobante.blade.php`: sin QR
   tributario, sin cuentas de detracción, con leyenda "COTIZACIÓN —
   documento interno, no es comprobante de pago", con vigencia).
2. `ComprobantePdfService` o un servicio hermano
   `CotizacionPdfService` — reutilizar `NumeroEnLetrasService` y
   `CompanySetting`/`CompanyBankAccount` igual que comprobantes.
3. Ruta `GET vendedor/cotizaciones/{quote}/pdf` +
   `QuoteController::downloadPdf()`.

## 83.4 Certificados — plan técnico

1. Página pública real: nueva ruta `GET /verificar-certificado/{token}`
   con vista Blade (no Inertia) que muestre los datos del certificado
   y del equipo — hoy `PublicCertificateVerificationController::show()`
   solo devuelve JSON.
2. `CertificateController::store()`/`generate()`: falta el motor de
   reglas (§26.1) que decida qué tipo de certificado corresponde según
   servicio + destino (local/vehículo) + si hubo P.H./capacitación —
   ya especificado en el doc maestro, solo falta implementarlo.
3. Una plantilla Blade por `CertificateType` (Operatividad y Garantía,
   Prueba Hidrostática, Capacitación, Operatividad de Sistemas de
   Detección, y las "Otros configurables" — fumigación, desratización,
   pozo a tierra, cámaras, lámina de seguridad, etc. de §76.3), todas
   reutilizando el patrón de tabla dinámica de N filas (§26.2, no crear
   una plantilla por cantidad de equipos).
4. QR de verificación usando `ComprobanteQrGenerator` como patrón (el
   `qr_token` ya existe en el modelo `Certificate`).

## 83.5 Guía de Remisión — plan técnico (resumen; se detalla cuando se empiece)

1. Migración + modelo `ShippingGuide`/`ShippingGuideDetail` (motivo de
   traslado, transportista, vehículo, conductor, partida/llegada,
   `sale_id` nullable — todo ya especificado en el doc maestro §30-31 y
   verificado contra fuente oficial SUNAT en §77.1).
2. `GreenterService::buildDespatch()` usando `Greenter\Model\Despatch\Despatch`
   vía la API REST 2022+ de SUNAT (credenciales `SUNAT_GRE_CLIENT_ID`/
   `SUNAT_GRE_CLIENT_SECRET`, ya reservadas en `config/billing.php` sin
   usar todavía).
3. `ShippingGuideController` + rutas en `routes/vendedor.php`, pantalla
   de creación desde el detalle de una Venta.
4. PDF con el mismo patrón visual que ya mostró el usuario (referencia
   TCPDF de "Bruce Cars", ver nota de Obsidian del 2026-09-20).

---

# 84. PLAN — ROL ALMACÉN (planificado 2026-09-21, sin construir todavía)

**Estado actual: no existe absolutamente nada del rol Almacén en el
código** — ni rutas, ni controlador, ni pantalla. Solo existe el rol
vacío en `RolesAndPermissionsSeeder` (sin permisos asignados) y los
modelos de datos de base que el rol va a usar:
`CatalogItem` (código, nombre, tipo, unidad_medida, precio_venta,
aplica_igv, activo), `InventoryUnit` (unidad serializada: catalog_item,
sede_almacén, numero_serie, estado, fecha_ingreso) e
`InventoryMovement` (Kardex: tipo, cantidad, unidad, sede, usuario,
observación). Esto reduce el trabajo real: no hay que diseñar el
modelo de datos desde cero, solo construir la capa de rutas/
controladores/pantallas encima.

## 84.1 Alcance del rol (ya definido en el doc maestro §9-11, §35.3, §36)

Almacén tiene, según la especificación ya escrita:
catálogo (solo lectura), stock, recepciones, movimientos, unidades
serializadas, repuestos. **Regla dura repetida tres veces en el
documento** (§11.3, §35.3, §49.6): _"Almacén NO pistolea/escanea
equipos para asignarlos a una venta — eso lo hace el Vendedor."_ Es la
frontera de responsabilidad más importante a respetar al construir
este rol: Almacén controla existencias, nunca decide qué se vende.

## 84.2 Módulos propuestos (orden de construcción sugerido)

1. **Dashboard de Almacén** — KPIs propios (unidades en stock,
   productos bajo el mínimo, recepciones del día, movimientos
   recientes), siguiendo el mismo patrón ya usado en
   `Vendedor\DashboardController` (dashboard propio por rol, nunca
   compartido — regla ya aplicada al Vendedor en §77.3).

2. **Catálogo (solo lectura)** — listado de `CatalogItem` con stock
   actual por sede, sin poder crear/editar precios (eso es de Gerente/
   Comercial). Búsqueda por código/nombre/tipo.

3. **Stock y Kardex** — vista de `InventoryMovement` filtrable por
   producto/sede/fecha/tipo de movimiento (entrada/salida/ajuste/
   traslado), con el saldo corriente. Es el módulo central del rol.

4. **Recepción de proveedor** (§11.2) — formulario: proveedor,
   documento de referencia, fecha, producto, cantidad, cantidad
   conforme, cantidad observada, observación. Para extintores nuevos
   (unidades serializadas): captura serie, marca, capacidad, año por
   cada unidad del lote — crea `InventoryUnit` + `InventoryMovement`
   tipo "entrada" en una transacción.

5. **Impresión de stickers de código de barras** — ya especificado en
   §76.3 y §9: al confirmar una recepción de unidades serializadas,
   botón "Imprimir stickers" que arma una hoja A4 en grilla de 4
   etiquetas (código de barras BF-EQ-XXXXXX + código interno) por
   página. Reutiliza `barryvdh/laravel-dompdf` (ya instalado) — mismo
   patrón que se acaba de construir para comprobantes, pero con
   `endroid/qr-code`/barcode en vez de QR tributario.

6. **Ajustes de stock autorizados** — corrección manual de cantidad
   con motivo obligatorio (merma, error de conteo, etc.), siempre deja
   rastro en el Kardex (nunca se edita el stock directo, solo se
   inserta un `InventoryMovement` tipo "ajuste" con la diferencia,
   igual patrón que ya usa `SaleItemProcessor` para ventas).

7. **Repuestos/componentes** (§10.3) — manguera, válvula, manómetro,
   pasador, precinto, boquilla, difusor, manija, empaques, O-ring. Es
   el mismo modelo `CatalogItem`/`InventoryUnit` (o solo `CatalogItem`
   con stock no serializado si un repuesto no lleva número de serie
   propio — a decidir caso por caso, sin sobre-construir), no una
   tabla nueva.

8. **Disponibilidad/consulta rápida** — buscador por número de serie o
   código de barras que muestre estado actual de una unidad (mismo
   patrón que `InventoryLookupController::bySerial()` que el Vendedor
   ya usa para escanear en una venta — Almacén necesita el equivalente
   de solo-consulta, sin poder "vender" desde ahí).

## 84.3 DECISIÓN (2026-09-21): ejecutar Etapa 1 antes de construir Almacén — investigado con fuentes

El usuario pidió explícitamente no adivinar esto y "revisar bien que
tenga sentido" antes de decidir, porque Almacén necesita conectar de
verdad con Vendedor (Producto/Servicio), Certificados y los stickers
de escaneo — no ser una pantalla aislada. Se investigaron prácticas
estándar de gestión de inventario para negocios con productos
identificables/serializados + servicios + cumplimiento normativo
([NetSuite — Serialized Inventory Tracking](https://www.netsuite.com/portal/resource/articles/inventory-management/serialized-tracking.shtml),
[Unleashed — Barcoding and Inventory Management](https://www.unleashedsoftware.com/blog/barcoding-and-inventory-management-the-ultimate-guide/)).

**Hallazgo clave**: el patrón correcto para este tipo de negocio es un
único **maestro de ítems** (lo que aquí sería `Product`/`Service`) del
que cuelgan tres capas — stock/Kardex (`InventoryMovement`), unidades
serializadas con su barcode (`InventoryUnit`), y los documentos que
consumen ese maestro (venta, certificado, orden de servicio). **No es
correcto tener un maestro de ítems por rol** (un "Catálogo" propio de
Almacén separado de lo que usa Vendedor) — eso es exactamente lo que
ya se había decidido evitar en §76.2/§78.3 ("matar catálogo") y nunca
se ejecutó.

**Decisión**: se ejecuta la Etapa 1 (separar `CatalogItem` en
`Product`/`Service`, retirar `CatalogItem`) **como parte de construir
Almacén, no después**. Construir Almacén sobre `CatalogItem` ahora
significaría reconstruir sus pantallas de stock apenas se ejecute la
Etapa 1 (documentado como riesgo explícito en la versión anterior de
esta sección) — con la señal clara del usuario de que Almacén debe
conectar con Vendedor/Certificados desde el día uno, ya no tiene
sentido posponerlo. Efectos concretos en el plan:

- El **Módulo 2 — Catálogo (§84.6) se elimina como pantalla separada**
  de Almacén: no existe un "catálogo" que Almacén vea distinto al que
  ve Vendedor. La función de "ver qué productos/servicios existen y
  cuánto stock tienen" se fusiona dentro del Módulo 3 — Stock (§84.7),
  que pasa a ser la única pantalla de "qué hay y cuánto hay" para
  Almacén.
- `Product`/`Service` reemplazan a `CatalogItem` en todos los módulos
  de este plan (§84.7-§84.12) y en los permisos (§84.4: se elimina la
  clave `catalog`, no hace falta).
- `GreenterService`, `ComprobantePdfService` y el resto de lo
  construido para Vendedor (§80-82) deben actualizarse a `Product`/
  `Service` en la misma etapa — ya estaba anotado como pendiente
  (§80.5/§81), ahora tiene una fecha de ejecución real en vez de
  quedar indefinido.
- `InventoryUnit`/`InventoryMovement` (el Kardex) **no cambian de
  diseño** — solo su FK pasa de `catalog_item_id` a apuntar al ítem
  correcto (`product_id` para productos serializados/no serializados,
  los servicios no tienen stock por definición).
- Certificados (§82.6, todavía sin construir) y Alertas de Vencimiento
  (ya construido para Vendedor) también leen de `Equipment`/
  `CatalogItem` hoy — se actualizan en la misma pasada, no en una
  aparte, para no dejar puntos sueltos.

Esto convierte "construir Almacén" en dos entregables secuenciales
dentro del mismo esfuerzo: (1) ejecutar la migración Product/Service
que ya estaba decidida, (2) construir las pantallas de Almacén ya
directamente sobre el modelo correcto. Es más trabajo inicial que
construir sobre `CatalogItem`, pero evita reconstruir dos veces y es
lo que el usuario pidió explícitamente verificar antes de avanzar.

## 84.4 Permisos sugeridos (siguiendo la matriz ya definida en §36)

Requiere ampliar las acciones de `inventory` en
`RolesAndPermissionsSeeder::MODULES`. **Ya no hace falta una clave
`catalog`** (decisión §84.3: no hay pantalla de catálogo separada,
todo vive bajo `inventory.view`).

```text
MODULES:
  inventory  => ['view', 'manage', 'receive', 'adjust',
                 'print_stickers', 'lookup']      // ampliada

ALMACEN_PERMISSIONS:
  dashboard.view_own
  inventory.view
  inventory.receive
  inventory.adjust
  inventory.print_stickers
  inventory.lookup
  sedes.view
```

Ningún permiso de `sales.*` ni `sales.scan_units` — esa es la barrera
que hace cumplir la regla "Almacén no pistolea para vender". El
detalle de qué permiso cubre cada módulo está en §84.5-§84.12.

## 84.5 Módulo 1 — Dashboard de Almacén

- **Ruta:** `GET almacen/dashboard` → nombre `almacen.dashboard`.
- **Controlador:** `App\Http\Controllers\Almacen\DashboardController`
  (invokable, mismo patrón que `Vendedor\DashboardController` — KPIs
  propios del rol, nunca acumulados de otros roles).
- **KPIs:**
    - Unidades disponibles en stock (`InventoryUnit::where('estado',
'disponible')->count()`, agrupable por sede).
    - Recepciones de hoy (`InventoryMovement::where('tipo',
'ingreso')->whereDate('created_at', today())->count()`).
    - Movimientos recientes: últimos 10 `InventoryMovement` (con
      `catalogItem`, `sede`, `user`).
    - Productos bajo el mínimo: **aclaración importante (2026-09-21)** —
      este KPI es sobre **reabastecer el almacén** (ej. "quedan 2
      extintores PQS 6kg en stock, hay que comprar más al proveedor"),
      **no tiene nada que ver** con que un extintor instalado en casa de
      un cliente necesite cambio/recarga/prueba hidrostática — eso ya es
      otro módulo completamente distinto (Alertas de Vencimiento, ya
      construido para Vendedor en `Vendedor\AlertController`, basado en
      `Equipment.proxima_fecha_atencion`/`proxima_prueba_hidrostatica`,
      nada que ver con `InventoryUnit`/stock de almacén). Son dos
      conceptos con el mismo verbo ("vencer") pero completamente
      separados: uno es inventario propio de la empresa, el otro es
      mantenimiento de equipos de clientes.
- **Modelos:** `InventoryUnit`, `InventoryMovement` (solo lectura).
- **Permiso:** `dashboard.view_own` (reutiliza el permiso ya existente,
  no hace falta uno nuevo).

## 84.6 Módulo 2 — [ELIMINADO] Catálogo ya no es una pantalla separada

Decisión tomada en §84.3: no existe un "catálogo" propio de Almacén.
Ver contenido fusionado del listado de productos/servicios con stock
dentro del Módulo 3 — Stock (§84.7) a continuación.

## 84.7 Módulo 3 — Stock (incluye qué existe y cuánto hay) y Kardex (módulo central)

- **Rutas:**
    - `GET almacen/stock` → `almacen.stock.index` — listado de
      Producto/Servicio con stock actual por sede (reemplaza al antiguo
      "Catálogo" del §84.6: es la única pantalla de "qué hay y cuánto
      hay" para Almacén, no hay una segunda pantalla de solo-catálogo).
    - `GET almacen/kardex` → `almacen.kardex.index` — historial de
      movimientos.
- **Controladores:** `Almacen\StockController@index`,
  `Almacen\KardexController@index`.
- **Filtros de Stock:** `search` (código o nombre), `tipo`
  (`producto`|`servicio`).
- **Columnas de Stock:** código, nombre, tipo, unidad de medida,
  precio de venta (solo lectura — editar precio sigue siendo de
  Gerente/Comercial, no construido todavía), stock disponible por sede
  (para `tipo = servicio` se muestra "N/A", los servicios no tienen
  stock por definición). Sin botones de crear/editar/eliminar el ítem.
- **Filtros de Kardex:** `product_id`, `sede_id`, `fecha_desde`,
  `fecha_hasta`, `tipo` (`ingreso`|`salida_venta`|`ajuste`|`traslado`
  — estos son los valores reales del enum en la migración, no
  "entrada"/"salida" genéricos).
- **Columnas de Kardex:** fecha, tipo, producto, unidad serializada
  (si aplica), sede, cantidad (con signo), usuario, observación,
  saldo corriente (calculado, no columna).
- **Modelos:** `Product` (tras Etapa 1, ver §84.3), `InventoryUnit`,
  `InventoryMovement`, `Sede` — todo de solo lectura en este módulo.
- **Permiso:** `inventory.view` (reemplaza a `catalog.view`, que se
  elimina de §84.4).

## 84.8 Módulo 4 — Recepción de proveedor

> Nota de consistencia: esta subsección (§84.8) y las siguientes
> (§84.9) se escribieron antes de la decisión de §84.3 y todavía
> nombran `catalog_items`/`CatalogItem` en el detalle técnico fino.
> Leer como `products`/`Product` en cada mención — el diseño
> conceptual no cambia, solo el nombre de la tabla/modelo tras
> ejecutar la Etapa 1. No se reescribió campo por campo para no
> introducir errores de edición en un documento de planificación.

Este es el módulo con más superficie nueva porque el modelo actual
(`CatalogItem`/`InventoryUnit`/`InventoryMovement`) no tiene forma de
agrupar varias líneas bajo un mismo documento de proveedor. Se
necesita una tabla nueva, pequeña, que actúa como cabecera y aprovecha
la referencia polimórfica que `InventoryMovement.referencia_type/id`
ya tiene:

```text
receptions (tabla nueva)
  id
  proveedor            string, required
  documento_referencia string, nullable   (guía/factura del proveedor)
  fecha                date, required
  sede_almacen_id       FK sedes, required
  user_id              FK users            (quién recibió)
  observacion          string, nullable
  timestamps
```

Cada línea de la recepción genera **un `InventoryMovement` tipo
`ingreso`** con `referencia_type = Reception::class` y
`referencia_id = $reception->id`:

- Línea de producto **no serializado** (repuestos, insumos): un solo
  `InventoryMovement` con `cantidad = N`, `inventory_unit_id = null`.
- Línea de producto **serializado** (extintores nuevos): se crean `N`
  filas de `InventoryUnit` (una por unidad física) + `N`
  `InventoryMovement` (uno por unidad, `cantidad = 1`,
  `inventory_unit_id` apuntando a cada unidad). Todo en una única
  transacción de BD (`DB::transaction`), igual patrón que
  `SaleItemProcessor` usa para ventas.

**Campo nuevo necesario:** `catalog_items.serializado` (boolean,
default `false`) — hoy no existe forma de saber si un `CatalogItem`
requiere captura de unidades individuales al recibir stock. Sin este
campo, la pantalla de Recepción no puede decidir qué sub-formulario
mostrar por línea.

**Campos nuevos necesarios en `inventory_units`:** `marca` (string,
nullable) y `anio_fabricacion` (smallint, nullable). La capacidad y el
tipo de extintor (PQS, CO2, etc.) **no** necesitan columna nueva:
según la convención ya usada en el catálogo, cada combinación
capacidad+tipo es su propio `CatalogItem` (ej. "Extintor PQS 6kg" y
"Extintor CO2 5kg" son dos códigos distintos), así que capacidad/tipo
ya quedan implícitos en `catalog_item_id`. Solo marca y año de
fabricación varían por lote/unidad y necesitan vivir en
`InventoryUnit`.

- **Ruta índice:** `GET almacen/recepciones` → `almacen.recepciones.index`.
- **Ruta formulario:** `GET almacen/recepciones/nueva` →
  `almacen.recepciones.create`.
- **Ruta guardar:** `POST almacen/recepciones` →
  `almacen.recepciones.store`.
- **Ruta detalle:** `GET almacen/recepciones/{reception}` →
  `almacen.recepciones.show`.
- **Controlador:** `Almacen\ReceptionController`.
- **Campos del formulario:**
    - `proveedor`: string, required, max:150.
    - `documento_referencia`: string, nullable, max:50.
    - `fecha`: date, required, `before_or_equal:today`.
    - `sede_almacen_id`: select, required, `exists:sedes,id` (solo
      sedes con `tipo` en `almacen`/`mixta`).
    - `items`: array, required, min 1 elemento. Cada item:
        - `catalog_item_id`: required, `exists:catalog_items,id`.
        - `cantidad`: integer, required, min:1.
        - `cantidad_conforme`: integer, required, min:0,
          `lte:cantidad`.
        - `observacion_item`: string, nullable, **required si
          `cantidad_conforme < cantidad`** (obliga a explicar por qué,
          para poder reclamarle al proveedor después).
        - Si `catalog_item.serializado === true`, además un array
          `unidades` de tamaño `cantidad_conforme`, cada una con:
          `marca` (string, required) y `anio_fabricacion` (integer,
          required, entre 1990 y el año actual). El `numero_serie` **no**
          lo captura el usuario — lo genera el backend (ver abajo).
- **Generación del código interno — DECIDIDO 2026-09-21:** en el
  momento de crear cada `InventoryUnit`, el backend genera
  `numero_serie` con el formato `BF-EQ-{secuencial autoincremental de
6 dígitos con ceros a la izquierda}` (ej. `BF-EQ-000123`),
  garantizado único y correlativo por un `autoincrement`/secuencia de
  BD, nunca por conteo de filas (para no repetir número si se borra
  una unidad). Confirmado con el usuario, sin cambios de formato.
- **Mercadería no conforme/dañada — DECIDIDO 2026-09-21, con
  investigación**: se preguntó explícitamente qué hacen las empresas
  reales antes de decidir. Práctica estándar de recepción de almacén
  ([Racklify — Quarantine Workflow](https://racklify.com/encyclopedia/from-inspection-to-disposition/),
  [FastTQM — Incoming Inspection Best Practices](https://www.fasttqmsoftware.com/learn/incoming-inspection-best-practices)):
  lo no conforme **se separa y se registra siempre** (nunca se
  descarta sin dejar rastro) para poder reclamarle al proveedor dentro
  de la ventana de disputa, pero **nunca se mezcla con el stock
  disponible para la venta**. Aplicado a este sistema, sin construir
  una zona de cuarentena completa (fuera de alcance, nadie la pidió):
    - La cantidad no conforme de cada línea queda registrada en la
      propia tabla `receptions`/línea (`cantidad` vs `cantidad_conforme`
        - `observacion_item` obligatoria) — es el respaldo para el
          reclamo al proveedor.
    - **Solo `cantidad_conforme` genera `InventoryUnit`/`InventoryMovement`**
      (entra al stock real). La diferencia (`cantidad - cantidad_conforme`)
      NO crea unidades ni movimiento — no ensucia el Kardex con algo que
      nunca estuvo disponible para vender, pero el hecho no se pierde
      porque vive en el documento de recepción.
- **Recepción confirmada: editable — DECIDIDO 2026-09-21.** A
  diferencia de una Venta (que se vuelve inmutable al confirmarse), una
  Recepción sí se puede corregir después (ej. error de tipeo en
  cantidad conforme). Implica: `ReceptionController@update`, y que
  cualquier corrección que cambie `cantidad_conforme` debe ajustar
  también los `InventoryMovement`/`InventoryUnit` ya creados (crear un
  movimiento de ajuste compensatorio, nunca editar un movimiento
  histórico ya guardado — mismo principio ya aplicado en
  facturación: "nunca editar algo que ya corrió", §76.5).
- **Modelos:** `Reception` (nuevo), `Product` (tras Etapa 1, §84.3),
  `InventoryUnit`, `InventoryMovement`.
- **Permiso:** `inventory.receive`.

## 84.9 Módulo 5 — Impresión de stickers de código de barras

- **Ruta:** `GET almacen/recepciones/{reception}/stickers` →
  `almacen.recepciones.stickers`, devuelve el PDF inline (como
  `BillingController::downloadPdf`).
- **Controlador:** `Almacen\ReceptionStickerController@show`.
- **Servicio nuevo:** `App\Services\Inventory\StickerPdfService`,
  mismo patrón que `ComprobantePdfService`: recibe la `Reception`,
  carga sus `InventoryUnit` (vía los `InventoryMovement` asociados),
  genera el PDF con `Pdf::loadView('pdf.stickers', [...])
->setPaper('a4')` y lo guarda/streamea.
- **Vista nueva:** `resources/views/pdf/stickers.blade.php`.
- **Layout de la hoja A4:** grilla 2×2 (4 etiquetas por hoja),
  cada etiqueta ~9.5cm × 6cm con margen entre celdas para no
  desperdiciar hoja. Contenido de cada etiqueta, de arriba a abajo:
    1. Logo BF pequeño (esquina superior, opcional, mismo
       `logoBase64()` que ya usa `ComprobantePdfService`).
    2. Código de barras 1D (Code128) del `numero_serie`.
    3. `numero_serie` en texto legible debajo del barcode (por si el
       lector falla).
    4. Nombre del `CatalogItem` (truncado a 1-2 líneas).
    5. Marca + año de fabricación, en fuente pequeña.
    - Si la recepción tiene más de 4 unidades, se repite la grilla en
      páginas siguientes (dompdf pagina automático con `page-break`).
- **Librería de barcode — dependencia nueva requerida:**
  `endroid/qr-code` (ya instalado) **solo genera códigos QR**, no
  sirve para Code128 1D. `bacon/bacon-qr-code` tampoco genera 1D
  (también es QR). No hay ninguna librería de barcode 1D instalada
  hoy. Se necesita agregar `picqer/php-barcode-generator` (MIT,
  genera PNG/SVG/HTML de Code128 sin depender de extensiones GD
  raras) — **esto es un cambio de dependencias y requiere aprobación
  explícita del usuario antes de instalarlo**, según la regla del
  proyecto de no tocar dependencias sin aprobación.
- **Modelos:** `Reception`, `InventoryUnit`, `CatalogItem` (solo
  lectura).
- **Permiso:** `inventory.print_stickers`.

## 84.10 Módulo 6 — Ajustes de stock autorizados

- **Rutas:**
    - `GET almacen/ajustes` → `almacen.ajustes.index`.
    - `POST almacen/ajustes` → `almacen.ajustes.store`.
- **Controlador:** `Almacen\StockAdjustmentController`.
- **Campos del formulario:**
    - `product_id`: required, `exists:products,id` (tras Etapa 1, §84.3).
    - `inventory_unit_id`: nullable, `exists:inventory_units,id` — solo
      cuando el ajuste es sobre una unidad serializada puntual (ej. dar
      de baja una unidad dañada).
    - `sede_id`: required, `exists:sedes,id`.
    - `tipo_ajuste`: required, `in:incremento,decremento`.
    - `cantidad`: integer, required, min:1.
    - `motivo`: string, required, min:10 — **obligatorio siempre**, es
      la regla explícita del doc maestro (§84.2 punto 6).
    - `observacion`: string, nullable.
- **Backend:** crea un `InventoryMovement` tipo `ajuste` con
  `cantidad` firmada según `tipo_ajuste` (positiva si incremento,
  negativa si decremento) y `observacion = motivo`. **Nunca** se
  edita el stock directamente — el saldo siempre se deriva de la suma
  de movimientos en el Kardex. Si `inventory_unit_id` viene informado
  y el ajuste es un decremento total de esa unidad, además se
  actualiza `InventoryUnit.estado = 'baja'`.
- **Modelos:** `InventoryMovement`, `InventoryUnit`, `Product`.
- **Permiso:** `inventory.adjust`.
- **DECIDIDO 2026-09-21: aplicación directa, sin doble aprobación.**
  El Almacenero aplica el ajuste directo (queda igual de trazable
  porque `motivo` es obligatorio y el movimiento queda en el Kardex
  con su usuario — la auditoría es el propio historial, no una
  aprobación previa). No se construye ningún flujo tipo
  `DeficiencyAuthorizationController` para esto.

## 84.11 Módulo 7 — Repuestos/componentes

No requiere tabla nueva ni controlador nuevo: reutiliza exactamente
`Almacen\StockController` (§84.7) y `Almacen\ReceptionController`
(§84.8) que ya operan sobre `Product`/`InventoryUnit`. La mayoría de
repuestos (manguera, válvula, manómetro, pasador, precinto, boquilla,
difusor, manija, empaques, O-ring) **no** llevan serie propia, así que
en la Recepción usan la rama "no serializado"
(`products.serializado = false`) del mismo formulario.

- **`categoria` — DECIDIDO 2026-09-21 (sin respuesta explícita del
  usuario, se aplica la convención ya establecida del proyecto de no
  sobre-construir)**: no se agrega la columna `categoria` por ahora.
  El código/nombre ya alcanza para diferenciar un repuesto de un
  extintor en el listado (filtro de texto libre sobre `codigo`/
  `nombre` ya cubre el caso). Si en el uso real hace falta agrupar por
  categoría, se agrega después con evidencia real de la necesidad, no
  por anticipación.
- **Permiso:** ninguno nuevo — usa `inventory.view` e
  `inventory.receive` ya definidos.

## 84.12 Módulo 8 — Consulta rápida por serie/código de barras

- **Ruta:** `GET almacen/consulta` → `almacen.consulta.index` (pantalla
  de búsqueda).
- **Ruta de resolución:** `GET almacen/consulta/buscar` →
  `almacen.consulta.buscar` (JSON, tipo autocompletar al escanear).
- **Controlador:** `Almacen\StockLookupController` (nuevo, **no**
  reutiliza `Vendedor\InventoryLookupController` directamente porque
  ese está pensado para venta: filtra `estaDisponible()` y exige
  `sede_almacen_id`). El de Almacén:
    - Busca por `numero_serie` exacto, sin exigir `sede_almacen_id`
      (opcional como filtro).
    - **No filtra por `estaDisponible()`** — Almacén debe poder
      consultar también unidades `vendido`/`baja`/`reservado` para dar
      soporte o auditar.
    - Devuelve: `numero_serie`, `product` (nombre, código),
      `sede_almacen`, `estado`, `fecha_ingreso`, `marca`,
      `anio_fabricacion`, y los últimos 5 `InventoryMovement` de esa
      unidad (historial).
    - **Regla dura:** esta pantalla es 100% de solo lectura. No expone
      ninguna acción de "reservar", "vender" ni "agregar a venta" — esa
      es exactamente la frontera de §11.3/§35.3/§49.6 que Almacén no
      puede cruzar. No comparte controlador ni lógica de escritura con
      `SaleItemScanController`.
- **Modelos:** `InventoryUnit`, `InventoryMovement` (solo lectura).
- **Permiso:** `inventory.lookup`.

## 84.13 Resumen de cambios de esquema y dependencias nuevas (actualizado 2026-09-21)

| Cambio                                                 | Tipo                                                      | Estado                                                                 | Módulo                                                                    |
| ------------------------------------------------------ | --------------------------------------------------------- | ---------------------------------------------------------------------- | ------------------------------------------------------------------------- |
| Migración `Product`/`Service` (retirar `CatalogItem`)  | Etapa 1 ya decidida (§76.2), ahora con fecha de ejecución | **A ejecutar primero, ver §84.3**                                      | Todos                                                                     |
| Tabla `receptions`                                     | tabla nueva                                               | por construir                                                          | 84.8                                                                      |
| `products.serializado` (boolean)                       | columna nueva                                             | por construir                                                          | 84.8                                                                      |
| `inventory_units.marca` (string nullable)              | columna nueva                                             | por construir                                                          | 84.8, 84.9                                                                |
| `inventory_units.anio_fabricacion` (smallint nullable) | columna nueva                                             | por construir                                                          | 84.8, 84.9                                                                |
| `products.stock_minimo` (integer nullable)             | columna nueva                                             | **aclarado qué es (84.5), falta que el usuario confirme si se agrega** | 84.5 (KPI "bajo mínimo" — reabastecer almacén, no vencimiento de equipos) |
| `products.categoria`                                   | —                                                         | **descartado por ahora** (§84.11), no sobre-construir                  | —                                                                         |
| `picqer/php-barcode-generator`                         | dependencia Composer nueva                                | **APROBADO por el usuario 2026-09-21**                                 | 84.9 (stickers)                                                           |
| Layout/sidebar `AlmacenLayout`/`AlmacenSidebar`        | frontend nuevo                                            | por construir                                                          | todos                                                                     |
| `routes/almacen.php`                                   | archivo nuevo                                             | por construir                                                          | todos                                                                     |

Todo lo demás (permisos, controladores, vistas Inertia) es capa nueva
sobre modelos ya existentes — ningún otro cambio de esquema es
necesario.

## 84.14 Preguntas — RESUELTAS 2026-09-21 (una sigue pendiente)

Todas las preguntas de la primera pasada del diseño quedaron
resueltas por el usuario, con investigación de por medio donde se le
pidió explícitamente:

1. ✅ **Secuencia de trabajo**: se ejecuta la Etapa 1 (`Product`/
   `Service`) como parte de construir Almacén, no después. Decisión
   con investigación de por medio, ver §84.3.
2. ✅ **Código interno**: `BF-EQ-000123`, correlativo por secuencia de
   BD. Confirmado sin cambios.
3. ✅ **Mercadería no conforme**: se registra en la línea de la
   recepción (nunca se pierde el dato, sirve para reclamo al
   proveedor) pero no entra al stock disponible — no se crea
   `InventoryUnit`/movimiento para la cantidad no conforme. Decisión
   con investigación de práctica real de almacenes, ver §84.8.
4. ✅ **Recepción confirmada**: editable (a diferencia de una venta).
5. ✅ **`picqer/php-barcode-generator`**: aprobado.
6. ✅ **Ajustes de stock**: aplicación directa, sin doble aprobación.
7. ✅ **Columna `categoria`**: no se agrega por ahora (sin
   sobre-construir); se reconsidera si aparece necesidad real de uso.

**Pendiente, la única que sigue abierta:**

8. **KPI "productos bajo el mínimo"**: ya se aclaró qué es (reabastecer
   almacén, nada que ver con vencimiento de extintores instalados en
   clientes — eso es Alertas de Vencimiento, módulo distinto ya
   construido para Vendedor). Falta que el usuario confirme si se
   agrega `products.stock_minimo` ahora (el KPI se muestra desde el
   día uno) o se pospone ese KPI puntual hasta que haga falta (el
   resto del dashboard de Almacén funciona igual sin él).

---

# 85. PLAN — ROLES TÉCNICO DE PLANTA Y TÉCNICO DE CAMPO (planificado 2026-09-21, sin construir todavía)

## 85.1 Por qué van juntos

Roadmap §58 Fase 4 ("Servicios") agrupa Planta y Campo porque ambos
ejecutan una única entidad ya existente en el backend: `ServiceOrder`
(máquina de 13 estados, §16.2), `Deficiency` y
`DeficiencyAuthorization`. El lado de **Vendedor ya está construido**:
crea la orden (`Vendedor\ServiceOrderController`), ve deficiencias y
las autoriza (`Vendedor\DeficiencyController`,
`DeficiencyAuthorizationController`). Falta el lado que **recibe,
ejecuta y cierra** esa orden — eso son estos dos roles. No hay
"catálogo propio" que decidir aquí (ya se resolvió esa clase de
problema en §84.3): ambos roles operan sobre `ServiceOrder`,
`Deficiency`, `Equipment` y `Product` ya existentes, nunca crean tablas
paralelas de esas entidades.

## 85.2 Regla dura (repetida en el doc, no negociable)

Mobile-first **obligatorio** para ambos roles (§70): la interfaz se
diseña primero para celular, nunca se "encoge" una pantalla de
escritorio. Cards, pasos, cámara accesible, escaneo rápido, selector
Conforme/Observado/N/A, sin tablas horizontales. El Técnico no negocia
precios ni emite CPE (§35.4, §35.5, §21).

## 85.3 Lo que ya existe (no reconstruir)

- `App\Models\ServiceOrder` con `ESTADOS` (13 pasos finos) y
  `coarseLabel()`.
- `App\Models\Deficiency` y `DeficiencyAuthorization`.
- `App\Models\ServiceOrderEvent` (bitácora append-only de eventos de
  la orden — es el mecanismo de "comunicación sin chat" de §17).
- `Vendedor\ServiceOrderController`, `DeficiencyController`,
  `DeficiencyAuthorizationController`, `CommunicationController`.
- `App\Models\Equipment` (equipos del cliente, para Alta Técnica
  Rápida §18).
- `App\Models\Certificate`/`CertificateType` y el servicio de PDF de
  certificados ya construido para Vendedor — hoy se dispara a mano,
  este plan lo conecta a `listo_certificado`.
- `App\Services\Inventory\*` y `Product`/`InventoryUnit`/
  `InventoryMovement` de Almacén — el consumo de repuestos en una
  reparación reutiliza esto, nunca una tabla de stock paralela.
- Patrón de PDF con dompdf (`ComprobantePdfService`,
  `StickerPdfService`) — el Acta de Conformidad (§23) reutiliza el
  mismo enfoque.

## 85.4 Lo que falta construir

- `routes/tecnico-planta.php`, `routes/tecnico-campo.php` +
  middleware `role:TecnicoPlanta` / `role:TecnicoCampo`.
- Permisos: expandir `service_orders` (`view,create,manage` hoy) y
  `deficiencies` (`view,create,authorize` hoy) a los verbos exactos
  de §36 (`assign`, `receive`, `execute`, `close`, `resolve`), más
  módulos nuevos según §85.6.
- Layouts/sidebars mobile-first para ambos roles (nunca reutilizar
  `AppLayout` genérico — recordar excluir `tecnico-planta/` y
  `tecnico-campo/` en `app.tsx`, el mismo bug que ya se corrigió para
  `almacen/`).
- Checklist digital dinámico (§19), Alta Técnica Rápida (§18),
  Recojo/Entrega con cadena de custodia (§22), Inspecciones (§24),
  Instalaciones (§25), Acta de Conformidad (§23).

## 85.5 Fases de ejecución (orden sugerido)

Detalle completo en el runbook de Obsidian
`Bruce Fire/2026-09-21 - Runbook Tecnico Planta y Campo.md` — aquí
solo el resumen:

0. Scaffolding de ambos roles (rutas, permisos, layouts/sidebars).
1. Dashboard Técnico de Planta (colas por estado, §5.4).
2. Recepción en Planta + Alta Técnica Rápida (§18).
3. Checklist Técnico Digital (§19).
4. Deficiencias desde Planta (§20) + notificación a Vendedor
   (reutiliza `CommunicationController`/`ServiceOrderEvent`).
5. Ejecución y cierre técnico: consumo de repuestos de Almacén,
   transición hasta `listo_certificado`, disparo automático del
   certificado.
6. Dashboard Técnico de Campo (servicios de hoy, §5.5).
7. Recojo con cadena de custodia (§22.1, §22.4).
8. Inspecciones de Campo (§24) — reutiliza el motor de checklist de
   la fase 3.
9. Instalaciones (§25) — equipos instalados pueden pasar a Equipos
   del Cliente.
10. Entrega final + Acta de Conformidad (§22.3, §23) — cierra la
    orden.

## 85.6 Preguntas abiertas (a resolver por el agente ejecutor solo si

bloquean una fase; si no, seguir con el valor por defecto indicado)

1. ¿Un técnico puede estar asignado a Planta y Campo a la vez, o son
   roles excluyentes por usuario? Por defecto: excluyentes (un
   usuario tiene un solo rol técnico), como ya aplica el patrón
   `role:X` de Vendedor/Almacén.
2. ¿El Acta de Conformidad requiere firma digital capturada
   (canvas táctil) o basta un checkbox de conformidad + nombre del
   receptor? Por defecto: checkbox + nombre, como ya se hizo con la
   conformidad de recepción en Almacén — firma digital se puede
   añadir después sin romper el esquema.
3. ¿La cadena de custodia (§22.4) es un modelo nuevo
   (`ServiceOrderCustody` o similar) o se modela como eventos
   tipados dentro de `ServiceOrderEvent` (`payload` json ya existe
   para eso)? Por defecto: reutilizar `ServiceOrderEvent` con `tipo`
   nuevo por cada eslabón (`recojo`, `recepcion_planta`,
   `entrega_final`) — evita una tabla nueva para algo que ya es,
   estructuralmente, una bitácora de eventos.

---

# 86. PLAN — ROL GERENTE (planificado 2026-09-21, sin construir todavía)

## 86.1 Por qué es distinto de los demás roles

Vendedor, Almacén, Técnico de Planta y Técnico de Campo **ejecutan**
operación (crean, reciben, resuelven). Gerente **no ejecuta nada
operativo** — es consulta amplia + configuración global (§35.1: "no
necesita necesariamente administrar credenciales técnicas SUNAT").
No hay una historia de "Gerente hace una venta"; hay una historia de
"Gerente necesita saber, sin pedírselo a nadie, si el mes va bien".
Por eso este plan no tiene una máquina de estados propia como
`ServiceOrder` — es, sobre todo, lectura agregada de datos que los
otros 4 roles ya generan, más un puñado de pantallas de administración
que hoy no tiene nadie (CRUD de Producto/Servicio, control de caja
consolidado, reportes, auditoría).

**Decisión ya tomada implícitamente por el propio proyecto**: el rol
"Administrador" del §35.6 (usuarios, roles, permisos, plantillas,
configuración, auditoría) **nunca se creó como rol Spatie separado**
— solo existen 5 roles reales:
`Vendedor, Almacen, Gerente, TecnicoPlanta, TecnicoCampo`. Y
Configuración de Empresa (que es territorio de "Administrador" según
el doc) ya se construyó bajo `routes/gerente.php`. Este plan mantiene
esa decisión: Gerente absorbe el alcance de Administrador, no se crea
un sexto rol. Si en el futuro hace falta separar (ej. un gerente
comercial sin acceso a usuarios/roles), la matriz de permisos
granulares (§36) ya permite hacerlo sin tocar la arquitectura.

## 86.2 Lo que ya existe (no reconstruir, no duplicar)

- `App\Models\CompanySetting`, `CompanyBankAccount` +
  `Gerente\CompanySettingController`, `CompanyBankAccountController`
  — única pantalla que Gerente tiene hoy.
- `App\Models\CashRegister` (turno de caja, patrón "arqueo ciego" ya
  implementado, §77.2) + `Vendedor\CashRegisterController` — Gerente
  solo necesita una vista de **consulta** sobre esto (historial de
  todos los vendedores con diferencias resaltadas, tal como pide
  §77.2 punto 4), nunca reconstruir el arqueo.
- Todos los datos fuente que Gerente va a agregar/reportar ya existen
  y ya se generan solos en los otros roles: `Sale`/`SalePayment`,
  `Quote`, `ServiceOrder`/`Deficiency`, `Certificate`,
  `ElectronicDocument` (estado SUNAT), `InventoryMovement`,
  `Product`/`Service` (incluye `stock_minimo`, agregado en la Etapa 1
  de Almacén pero nunca editable desde ninguna pantalla).
- Permisos ya reservados en `RolesAndPermissionsSeeder::MODULES` sin
  usar todavía: `dashboard.view_total` (vs. `view_own` que ya usan
  los demás roles — la distinción ya está pensada) y
  `roles_permissions.manage`.
- Patrón de gráficos/tarjetas de dashboard, patrón de exportación a
  PDF con dompdf (`ComprobantePdfService`, `StickerPdfService`,
  `ActaConformidadPdfService`) — los reportes exportables reutilizan
  este enfoque, no una librería nueva.

## 86.3 Lo que falta

- Dashboard real de Gerente (hoy `DashboardController` genérico solo
  redirige Vendedor/Almacén a los suyos — Gerente cae a una pantalla
  vacía del starter kit).
- CRUD de `Product`/`Service` — **el vacío más urgente**: hoy
  `stock_minimo` existe en la tabla pero nadie puede editarlo desde
  ninguna pantalla, lo mismo que precios (`precio_venta`). Sin esto,
  el KPI "bajo el mínimo" del dashboard de Almacén nunca mostrará
  nada en producción real.
- Reportes (§34): comerciales, inventario, servicios, equipos,
  certificados, facturación, cobranzas — con exportación a
  Excel/PDF "cuando aporte valor" (no todos necesitan Excel desde el
  día uno).
- Cobranzas consolidadas (§32): hoy Vendedor ya registra pagos
  (`collections.register_payment`), Gerente necesita la vista
  agregada de cartera (total por cobrar, vencido, vence esta semana,
  cobrado este mes) cruzando todos los vendedores, no solo el propio.
- Auditoría (§37): no existe ningún registro de acciones sensibles
  todavía en ningún rol. Este plan agrega la tabla y el visor para
  Gerente; conectar cada acción sensible de los otros 4 roles a este
  log es trabajo transversal, se hace incrementalmente (ver §86.6
  Fase 5), no de una sola vez.
- Gestión de usuarios/roles (asignar rol a un usuario, ver quién
  tiene qué permiso) — hoy solo existe por `php artisan tinker` o
  seeder.

**Fuera de alcance de este plan** (decisión, no olvido): las tarjetas
y gráficos de IA del §5.1 (proyección de ventas, demanda estimada,
riesgo de quiebre de stock, resumen ejecutivo). El propio doc maestro
lo dice en el §59: _"IA entra cuando la base de datos ya es
confiable"_ — y el roadmap (§58) pone IA en la Fase 8, la última. El
dashboard de Gerente se construye con las tarjetas y gráficos de
datos reales primero; el bloque de IA queda como sección vacía o
directamente omitido hasta que corresponda su fase.

## 86.4 Módulos (orden sugerido)

1. **Dashboard de Gerente** (§5.1, sin el bloque de IA) — tarjetas:
   ventas del día/mes, facturación del mes, monto cobrado, cuentas
   por cobrar, vencido, cotizaciones pendientes, tasa de conversión,
   órdenes en proceso, equipos próximos a atención, stock crítico,
   documentos SUNAT con error. Gráficos: ventas mensuales, ventas por
   producto/servicio, servicios por tipo, cartera por estado, top
   clientes, productos/repuestos con mayor movimiento.
2. **CRUD de Producto/Servicio** — crear/editar/desactivar (nunca
   eliminar si tiene historial, regla ya aplicada en Equipos §60),
   incluye `precio_venta` y `stock_minimo`. Esto es lo que
   desbloquea de verdad el KPI de Almacén.
3. **Caja consolidada** — vista de solo lectura sobre `CashRegister`
   de todos los vendedores, diferencias resaltadas (§77.2 punto 4).
4. **Cobranzas consolidadas** (§32) — cartera agregada de todos los
   vendedores, no solo la propia.
5. **Reportes** (§34) — empezar por comercial e inventario (los que
   ya tienen todo el dato fuente limpio), exportación PDF reutilizando
   dompdf; Excel solo donde aporte valor real (ej. reporte de ventas
   por periodo, no el dashboard).
6. **Auditoría** (§37) — tabla + visor. Conectar las acciones más
   sensibles primero (venta, ajuste de stock, autorización de
   adicional, cierre de orden, emisión/anulación de certificado,
   cambios de configuración) — no las 13 de la lista completa de una
   sola vez.
7. **Usuarios y roles** — listar usuarios del team, asignar/quitar
   rol, ver permisos efectivos. Sin crear un builder visual de
   permisos granulares — se editan por rol completo (los 5 roles ya
   fijos), no permiso por permiso por usuario (§36: "evitar permisos
   directos a usuarios salvo excepción justificada").

## 86.5 Preguntas abiertas (valor por defecto si no bloquean)

1. ¿El CRUD de Producto/Servicio lo usa _solo_ Gerente, o también
   Vendedor/Almacén pueden editar precios como se mencionó y quedó
   pendiente en la auditoría de Almacén (§84, "eso ya vemos cómo
   arreglar luego")? Por defecto: el CRUD (crear/desactivar producto)
   es exclusivo de Gerente; la edición de precio en el momento de una
   venta por Vendedor es un permiso granular aparte
   (`sales.override_price` o similar) que se decide cuando se
   retome ese pendiente — no bloquea construir el CRUD ahora.
2. ¿Reportes exportan a Excel desde la Fase 5, o se pospone Excel
   para después de tener PDF funcionando en los reportes principales?
   Por defecto: PDF primero (reutiliza dompdf sin dependencia nueva);
   Excel se agrega cuando un reporte concreto lo necesite de verdad
   (ej. `maatwebsite/excel` recién ahí, no antes — evita instalar una
   librería que después no se usa).
3. ¿Auditoría registra automáticamente vía Eloquent observers/events,
   o cada acción llama explícitamente a un `AuditLogger`? Por
   defecto: llamada explícita en el punto de la acción (mismo patrón
   que `ServiceOrderEvent::create()` ya usado en todo Planta/Campo),
   más predecible y más fácil de auditar el propio código que
   observers automáticos que capturan de más.

# 87. Un solo botón «Editar» para la venta (2026-10-03)

**Pedido del usuario:** "debería ser solo un botón para poder editar la
boleta, factura o nota de venta en general; no vamos a tener un botón por
cada cosa que queremos editar".

**Antes** había tres caminos distintos para corregir una venta:

| Botón                                    | Qué permitía                                             | Dónde                  |
| ---------------------------------------- | -------------------------------------------------------- | ---------------------- |
| Editar (borrador)                        | todo, con el formulario de venta                         | solo borradores        |
| Editar comprobante / Corregir y reemitir | solo factura↔boleta y cliente (modal)                    | por enviar o rechazado |
| Corregir productos o precios             | anulaba la venta y abría una copia (otro número interno) | por enviar             |

**Ahora** hay un único botón **Editar** (en el detalle y como lápiz en la
lista) que abre el mismo formulario de venta, ya lleno, y deja cambiar
todo: cliente, tipo de comprobante, productos/extintores, precios,
condición y medio de pago. Regla única en `Sale::sePuedeEditar()`:

- **Borrador** → se edita como siempre (puede guardarse o emitirse).
- **Nota de venta emitida** → se edita; conserva su número NV. Si pasa a
  factura/boleta, el número NV se libera y se programa el comprobante.
- **Factura/boleta por enviar** (SUNAT aún no la recibe) → se regenera
  con el **mismo número**; si cambia de tipo, el número vuelve a su serie
  y toma uno de la otra; conserva fecha y hora de envío programada.
- **Factura/boleta rechazada** → se emite una nueva con otro número y
  fecha de hoy (SUNAT no permite reutilizar el número).
- **Aceptada por SUNAT** → no se edita (regla §76.5: nunca editar algo
  que ya corrió); el lápiz de la lista lleva a la **nota de crédito**.
- **Anulada** → no se edita; se usa «Rehacer venta».

La venta conserva siempre su número interno (ya no se crea otra venta).
Al guardar: las unidades sacadas vuelven al stock y las nuevas salen
(Kardex limpio), el cobro al contado se recalcula manteniendo su fecha de
caja, los equipos pasan al cliente nuevo y los certificados se corrigen
(conservan su número con una revisión) o se anulan si ya no hay
extintores. No se puede editar si ya hay cuotas cobradas.

Código: `app/Actions/Sales/EditarVentaEmitida.php` (reemplaza a
`CorregirComprobante` y a la ruta `corregir-productos`),
`SaleController::edit/update`, `resources/js/pages/vendedor/ventas/`
(`show.tsx`, `index.tsx`, `nueva.tsx`).

# 88. Comprobante personalizable y con diseño de marca (2026-10-04)

**Pedido del usuario:** la factura/boleta impresa se veía plana (gris,
sin marca), el logo salía diminuto o no salía, "NIU" en vez de "UND",
campos vacíos como "Dirección: -" u "Obs:". Quiere que sea estética y
dinámica: **lo general se llena desde Gerente y lo particular sale de la
venta o cotización**.

**Lo general (Gerente → Configuración → Empresa → Diseño del
comprobante):** color de la marca (con sugerencias; el texto encima
pasa a blanco u oscuro según el contraste), página web, mensaje de
agradecimiento, condiciones de venta/garantía, leyenda de pie, cuentas
bancarias y logo. Botón **Vista previa de la factura** con datos de
ejemplo.

**Logo con mínimo y máximo:** se valida al subirlo (PNG/JPG, de 150×60 a
3000 px por lado, 2 MB). En el PDF se dibuja sin deformarse dentro de una
caja de hasta 190×80 px; si es muy alargado se le permite llegar a 230 px
de ancho para que no quede como una tira
(`CompanySetting::logoParaPdf()`).

**Lo particular (de la venta):** cliente, documento, dirección (solo si
existe), referencia/local/placa, condición y medio de pago, vencimiento,
vendedor que atendió, ítems, observaciones (solo si hay), cuotas,
detracción.

**Diseño:** cabecera con logo, nombre comercial y razón social; recuadro
tributario con el color de la marca; panel suave con cliente y datos de
la operación; tabla con encabezado de marca y filas alternadas; las
columnas Código y Dscto. solo aparecen si alguna línea las usa;
unidades legibles (NIU→UND, ZZ→SERV); cantidades sin decimales cuando
son enteras; monto en letras destacado; total resaltado; QR y leyenda
SUNAT al pie.

**PDFs ya emitidos:** al descargarlos (o en el ZIP masivo) se vuelven a
dibujar si la empresa o sus cuentas cambiaron después, usando el mismo
XML firmado (`ComprobantePdfService::vigente()`); lo enviado a SUNAT no
cambia.

Código: `resources/views/pdf/comprobante.blade.php`,
`app/Services/Billing/ComprobantePdfService.php`,
`app/Models/CompanySetting.php`, migración
`2026_10_04_010000_add_diseno_comprobante_to_company_settings_table`,
`resources/js/pages/gerente/configuracion/empresa.tsx`.

Pendiente: aplicar el mismo diseño a la nota de venta y a la
cotización; campos por venta como N° de orden de compra o de guía de
remisión si el negocio los usa.

## 88.1 Datos del cliente según SUNAT (2026-10-04)

Investigado en la normativa de la representación impresa (RS 097-2012 y
modificatorias, anexos de RS 114-2019 y RS 123-2022):

- **Factura:** RUC y razón social del cliente obligatorios. La dirección
  no la exige el XML, pero **Bruce Fire la vuelve obligatoria**: no se
  emite una factura si el cliente no tiene dirección fiscal. Si falta,
  la vendedora la completa en la misma venta y queda guardada en la
  ficha del cliente.
- **Boleta:** nombre y DNI solo obligatorios si la venta supera S/ 700
  (el sistema ya lo controla con el límite de CLIENTES VARIOS). La
  dirección es opcional; si el cliente la tiene, se imprime.
- El relleno "-" de CLIENTES VARIOS ya no se imprime como dirección
  (`Client::direccionImprimible()`).
- En una venta para vehículo, la referencia se imprime como **PLACA**.

## 88.2 Estilo formal y comprobante dinámico (2026-10-04)

Pedido del usuario, tomando como referencia las facturas de Codeplex
(F001-00000088 Acuario y F001-00000015 Mannucci): estilo elegante y
formal, y que cada dato aparezca solo si la venta lo tiene.

- **Estilo:** blanco, negro y gris; el color de la marca solo en la
  línea bajo la cabecera, el borde del recuadro del RUC y la franja del
  destino. Datos en formato "Etiqueta : valor".
- **Siempre:** emisor, recuadro RUC/tipo/número, Señor(es), RUC o DNI,
  Dirección (en blanco si falta), fecha de emisión, moneda, forma de
  pago, vendedor, detalle, SON, totales, QR y leyenda SUNAT.
- **Solo si existe:** placa (franja destacada, con la descripción del
  vehículo si está registrada), local/sede del cliente (franja
  destacada, con su dirección si está registrada), fecha de vencimiento
  y recuadro de cuotas (crédito), observaciones, columnas Código y
  Dscto., fila Descuentos, detracción, cuentas bancarias, condiciones,
  web y leyenda.

## 88.3 Por qué un PDF ya emitido seguía con el diseño viejo (2026-10-04)

Se decidía si redibujar comparando **fechas de archivos** (PDF contra
plantilla y datos de la empresa). Esas fechas no son confiables: un PDF
generado después del `git pull`, o una copia compilada de la plantilla
con fecha vieja, hacían que se siguiera entregando el diseño anterior.

Ahora cada comprobante guarda `pdf_firma`, una huella del **contenido**
de la plantilla y del servicio del PDF más los datos de la empresa y sus
cuentas. Si la huella cambia, el PDF se redibuja al descargarlo. Además
se descarta la copia compilada de la plantilla antes de dibujar y la
descarga va con `Cache-Control: no-store` para que el navegador no
muestre una copia guardada. `billing:redibujar-pdf` muestra la firma del
diseño y la ruta de la plantilla para diagnosticar.

# 89. Revisión del rol Vendedor: Ventas, Cotizaciones, Por vencer e Inicio (2026-10-04)

Pedido del usuario: analizar lo que hay, proponer mejoras y quitar lo
que no tiene sentido, sin romper nada. Un PR por mejora.

- **Ventas:** por defecto solo las del día (hora de Lima); filtro
  Desde/Hasta con atajos y buscador. Los recuadros suman las ventas
  **emitidas** del vendedor en el rango (contado / crédito) y lo que
  falta enviar a SUNAT. Se quitaron "Comprobantes" y "Registros" (eran
  el mismo número).
- **Cotizaciones:** si el cliente no tiene celular, el botón pasa a
  "Agregar número": se guarda en su ficha y se abre WhatsApp con la
  cotización. WhatsApp no permite envío automático sin la API de
  WhatsApp Business (de pago): siempre lo confirma el vendedor.
- **Por vencer:** solo mostraba lo vencido o lo de este mes y quedaba
  vacío (los extintores vendidos vencen al año). Vista principal "Por
  empresa" con todos los extintores de cada cliente y su próximo
  vencimiento; la lista anterior queda como "Avisos por extintor".
- **Inicio:** panel "Pendientes de hoy" (rechazados, por enviar,
  cuotas vencidas, cotizaciones aceptadas, borradores) y "Clientes para
  ofrecer recarga" (vencidos o próximos 3 meses).

**Cosas sin sentido corregidas:**

- El Inicio marcaba cuotas como vencidas cada vez que se abría (una
  pantalla de lectura cambiaba datos); eso lo hace `alerts:recompute`.
- "Ventas de hoy" sumaba borradores y ventas anuladas.
- La ficha del cliente decía "Al día con pagos" con deuda a crédito no
  vencida; ahora "Por cobrar · próxima cuota".

## 89.1 Segunda vuelta: fechas en Comprobantes, WhatsApp y Por vencer sin serie (2026-10-04)

- **Comprobantes SUNAT:** mismo filtro "Desde / Hasta" que Ventas (hoy
  por defecto, atajos Hoy/Ayer/Esta semana/Este mes) en lugar del mes.
  Filtra por la fecha de emisión del comprobante (`fecha_emision`, o la de
  registro si no la tiene); la descarga ZIP/Excel usa el mismo rango. El
  filtro es un componente compartido (`rango-fechas.tsx` + trait
  `FiltraPorFechas`) y se aplica al elegir la fecha, sin botón.
- **WhatsApp:** `Client::whatsappInternacional()` solo acepta un celular
  peruano (9 dígitos que empiezan con 9, con o sin 51). Antes un teléfono
  fijo (044-…) o un relleno contaba como WhatsApp y la cotización abría
  WhatsApp sin destino en vez de pedir "Agregar número". "Por empresa"
  también ofrece "Agregar número".
- **Por vencer sin serie:** "Por empresa" solo contaba extintores
  registrados con serie. Ahora suma lo vendido sin serie (líneas de
  recarga o productos de categoría extintor sin equipo; la última compra
  de cada producto por cliente, ventas confirmadas) y, para clientes sin
  nada en el sistema nuevo, su última compra del sistema anterior. Se
  marcan _estimado_ con la recarga al año de la compra. CLIENTES VARIOS
  no aparece.

## 88.4 La nota de venta usa el mismo diseño (2026-10-04)

La nota de venta tenía su propia plantilla (`pdf/nota-venta.blade.php`)
y no recibió el diseño de marca. Ahora se dibuja con
`pdf/comprobante.blade.php` (`ComprobantePdfService::notaDeVenta()`):
mismo logo, colores, datos del cliente, placa o sede, cuotas, cuentas
y condiciones. Por ser un documento interno no lleva QR ni la leyenda de
SUNAT, sino "Documento interno de control. No es un comprobante de pago
electrónico". La plantilla anterior se eliminó.

# 90. Auditoría por roles y correcciones (2026-10-04)

Se revisaron los cinco roles. Lo corregido, por parte:

- **A · Taller:** con el certificado emitido la orden se entrega en
  mostrador (`ServiceOrder::ESTADOS_LISTOS`) y aparece en los avisos del
  vendedor; botón "Marcar lista para entrega". Una orden no se entrega dos
  veces y la entrega no vuelve a renovar fechas. No se gastan repuestos en
  deficiencias sin autorizar, rechazadas o de otra orden; aprobar la última
  deficiencia libera la orden; el checklist solo es de equipos de la orden.
- **B · Cobranzas y caja:** solo cuotas de ventas `confirmada`; parciales
  vencidas cuentan como vencidas; KPIs por saldo. Devoluciones en
  `sale_refunds` (los cobros no admiten negativos): la caja descuenta el
  efectivo devuelto en el turno en que sale. Editar una venta cobrada en
  otro turno registra hoy solo la diferencia. Efectivo exige caja abierta
  también en el servidor. Arqueo ciego real. Un solo cálculo del turno:
  `CashRegister::movimientosPorFormaDePago()`.
- **C · Gerente:** ventas `confirmada`; cotizaciones pendientes = emitidas
  o enviadas; stock unificado `Product::conStock()/bajoMinimo()` (serie por
  unidades, sin serie por Kardex); reporte de inventario por sede sin error;
  el Inicio no modifica cuotas; faltantes y sobrantes de caja por separado.
- **D · Almacén:** salidas con serie restan (-1) y una migración corrige
  las grabadas; ajustes, recepciones y traslados por almacén propio, sin
  stock negativo (`InventoryMovement::exigirSaldo` con bloqueo); no se
  revive un extintor vendido; el Gerente elige el origen del traslado.
- **E · Técnico de campo:** solo extintores del cliente de la orden
  (`EquipoDeLaOrden`); serie repetida se reutiliza; acciones no se repiten;
  certificado de inspección solo con conformes (`tipo_atencion` inspección).
- **F · Notas de crédito:** motivos 01, 02 y 06 que cubren el total anulan
  la venta; Comprobantes muestra el importe de la nota.
- **G · EPP:** `products.codigo_barras` (único) y `categoria`; se escanea en
  Recepciones, Venta y Cotización. Cada modelo y talla de EPP es un producto
  sin serie. Casos de error: código no registrado (avisa, no inventa),
  cantidad mayor al stock (avisa al escanear y el servidor lo bloquea),
  código repetido entre productos (rechazado).

## 90.1 Ronda 2: permisos por vendedor y marcas de agua (2026-10-04)

- **Cada vendedor, sus ventas:** Ventas, edición, anulación, comprobantes,
  notas de crédito/débito y certificados de una venta solo los opera quien
  la hizo (`User::vendedorRestringidoId`, `AcotaPorSede::asegurarVenta`);
  lo ajeno responde 404. El Gerente opera todas.
- **Nada de otra sede:** una venta no cobra una orden de servicio ni
  convierte una cotización de otra sede; la cotización del adicional de una
  deficiencia debe ser del mismo cliente y sede de la orden; el técnico
  asignado al crear la orden debe atender su área y sede.
- **Descuentos:** se mantienen, pero ninguna línea puede quedar en S/ 0.00.
- **Marcas de agua:** factura, boleta o nota de venta anulada lleva
  «ANULADO» en rojo (la huella del PDF cambia y se redibuja); un certificado
  anulado o vencido lleva «ANULADO» o «VENCIDO».

## 90.2 Ronda 2: catálogo, devoluciones y sedes (2026-10-04)

- Una venta nueva no lleva productos ni servicios dados de baja, y su placa
  debe ser de un vehículo del mismo cliente.
- Al anular, se devuelve todo lo cobrado (también las cuotas pagadas de una
  venta a crédito) menos lo ya devuelto; si hay efectivo por devolver, la
  caja del vendedor debe estar abierta.
- Un producto con movimientos, unidades o ventas no cambia entre «con serie»
  y «sin serie»; cada cambio de producto (precio incluido) queda auditado.
- No se desactiva una sede con stock; el enlace público de una cotización
  anulada responde «anulada».
- Consulta y Stock de Almacén buscan también por código de barras.

## 90.3 Ronda 3: lotes de EPP y pendientes (2026-10-06)

- **Lotes con vencimiento:** `products.controla_lote`, tabla `product_lots`
  (producto, almacén, lote, vencimiento) e `inventory_movements.product_lot_id`.
  `App\Services\Inventory\StockPorLote` centraliza entradas y salidas sin
  serie: recepciones, ventas, repuestos del taller, traslados y ajustes. Sale
  primero el stock sin lote (anterior a activar lotes) y luego lo que vence
  primero; un lote vencido no se vende. La anulación devuelve al mismo lote.
  Inicio de Almacén avisa lotes vencidos o a 60 días.
- **Caja / par:** `unidad_compra` y `factor_compra` (1 CAJA = N); en
  Recepciones se cuentan cajas y el stock entra en la unidad de venta. Las
  unidades PR, BX, PK y DZN van a SUNAT con su código del Catálogo 03.
- **Cobro anulado:** `sale_payments` con borrado lógico, `anulado_por`,
  `anulado_motivo` y `user_id` de quien cobró; deja de contar en saldos y
  caja y queda tachado en Cobranzas.
- **Por vencer:** las consultas traen solo lo que entra en la lista.
- **Certificado público:** el PDF que se abre por QR muestra el DNI a medias.
