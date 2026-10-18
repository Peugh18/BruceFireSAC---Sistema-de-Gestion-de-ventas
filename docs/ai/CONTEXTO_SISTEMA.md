# Contexto del sistema — Bruce Fire, rol por rol

> Ideas del usuario (2026-10-18) ordenadas y completadas con la revisión del código y con lo que hacen otras empresas del rubro.
> Es la **referencia de lo esperado**: la auditoría ([PROMPT_AUDITORIA.md](PROMPT_AUDITORIA.md)) compara el sistema contra este documento.
> Las decisiones del usuario están en **§9**.

## 1. Principios (valen para todo)

1. **Simple.** Cada rol ve solo lo suyo y lo encuentra rápido.
2. **Todo relacionado.** Cliente → cotización → venta → comprobante → orden → equipos → certificado → entrega están unidos. El que sigue en la cadena recibe los datos; no los vuelve a escribir.
3. **Ningún dato repetido.** Cada dato tiene un solo dueño y un solo lugar. Ejemplos ya resueltos: una persona que firma = una ficha, aunque firme con cargos distintos; agentes, marcas y tipos de servicio administrables.
4. **Legal.** Comprobantes, plazos, detracción y catálogos según [SUNAT_FACTURACION.md](../SUNAT_FACTURACION.md), para no recibir multas.
5. **Un buen BI en cada rol.** Pocas métricas, las que sirven para decidir, **con gráficos** (no solo números sueltos).

## 2. Lugares: puntos de venta y planta

Así lo explicó el usuario:

- **Sede = punto de venta.** Una tienda que vende y **tiene su propio almacén** junto a ella. Es lo que necesita SUNAT: cada local anexo tiene su código de establecimiento (`sedes.cod_establecimiento_anexo`), que va en el comprobante.
- **Planta = el taller.** Tiene su propia dirección y está cerca del punto de venta para recoger y entregar. **No vende.**
- **Con una sola sede no hay traslados entre sedes.** La opción aparece cuando exista una segunda.

**Problema encontrado en el código:** `User::TIPOS_DE_SEDE_POR_ROL` obliga a que Almacén, Técnico de Planta y Técnico de Campo elijan una sede de tipo "almacén". Como las sedes ahora son puntos de venta, **no se puede crear un técnico**.
**Propuesta:**

- Vendedor y Almacén pertenecen a su punto de venta.
- Los técnicos pertenecen a la **Planta**, que atiende a uno o más puntos de venta.
- Ver la duda §9.1.

## 3. Gerente

| Módulo                                              | Estado según el usuario                 | Qué falta                                                                                                                                                                                  |
| --------------------------------------------------- | --------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **Dashboard (BI)**                                  | Necesita mejorar                        | Gráficos de verdad y solo lo importante (ver abajo)                                                                                                                                        |
| **Módulo de IA** (nuevo)                            | Hoy es una tarjeta dentro del dashboard | Sacarlo a su **propia pantalla**: recompra y vencimientos predichos con los **datos históricos** que se cargarán (`ClientRetentionScore`, `ImportarHistoricoMl`), con un poco de BI propio |
| Productos                                           | Bien                                    | Extintor: agente y capacidad. Otro producto: con o sin código de barras (ver Almacén §4)                                                                                                   |
| Servicios                                           | Bien                                    | —                                                                                                                                                                                          |
| Caja consolidada                                    | Bien                                    | —                                                                                                                                                                                          |
| Cobranzas                                           | Bien                                    | —                                                                                                                                                                                          |
| Auditoría                                           | Bien                                    | —                                                                                                                                                                                          |
| Sedes                                               | Corregir                                | Solo **puntos de venta** (tienda + su almacén) y la **Planta** como lugar aparte                                                                                                           |
| Usuarios y roles                                    | Corregir                                | Al crear un técnico no debe pedir una sede de almacén (§2)                                                                                                                                 |
| Configuración: datos de la empresa, firmas y sellos | Bien                                    | —                                                                                                                                                                                          |
| Configuración: certificados                         | Corregir                                | El editor debe dejar **editar sobre la hoja** (§7)                                                                                                                                         |

**BI del gerente.** Qué va y en qué gráfico:

| Métrica                                                          | Gráfico                | Por qué                                          |
| ---------------------------------------------------------------- | ---------------------- | ------------------------------------------------ |
| Ventas del mes frente a los meses anteriores                     | Línea o barras por mes | Ver la tendencia                                 |
| **Conversión de cotizaciones** (enviadas → aceptadas → vendidas) | **Embudo**             | El usuario lo pidió explícitamente en un gráfico |
| Cobranza: cobrado frente a pendiente y vencido                   | **Dona** (círculo)     | Lo que importa del dinero                        |
| Ventas por punto de venta                                        | Barras                 | Aparece solo con 2 o más sedes                   |
| Servicios por tipo (recarga, mantenimiento, inspección…)         | Dona                   | Qué vende más la empresa                         |
| Órdenes abiertas, atrasadas y terminadas                         | Barras apiladas        | Atrasos de operación                             |
| Extintores por vencer (próximos 30, 60 y 90 días)                | Barras                 | Ventas futuras                                   |

**No va:** ticket promedio ni números sueltos sin contexto. **Referencia:** los tableros de servicio técnico contra incendios recomiendan pocos KPI por rol, tendencia de 8 semanas y alertas por umbral ([Fieldproxy — Fire Protection KPI](https://www.fieldproxy.ai/blueprints/fire-protection-kpi-dashboard), [Klipboard — KPIs de empresas de seguridad contra incendios](https://klipboard.io/?p=7988)). El ciclo cotización → trabajo → factura cobrada es el indicador financiero central del rubro (Klipboard).

## 4. Almacén

| Módulo               | Estado según el usuario              | Qué falta                                               |
| -------------------- | ------------------------------------ | ------------------------------------------------------- |
| **Dashboard (BI)**   | Solo texto                           | Gráficos (ver abajo)                                    |
| Recepciones          | Bien (serie de fábrica, marca y año) | Dejar claro el flujo por tipo de producto (ver abajo)   |
| Stickers             | Revisar                              | Ahorrar material: nada de A4 con 2 stickers (ver abajo) |
| Ajuste de stock      | Bien                                 | —                                                       |
| Traslado entre sedes | Corregir                             | **Ocultarlo** mientras haya una sola sede               |
| Consulta rápida      | Bien                                 | —                                                       |

**Cómo se recibe cada tipo de producto (sin datos repetidos):**

1. **Extintor** (por serie): cada unidad recibe su sticker **BF-EQ**, con marca, serie de fábrica, año, agente y capacidad.
2. **Producto que ya trae código de barras** (válvula, EPP, etc.), por **cantidad**: se escanea y se suma. La primera vez, el código se liga al producto y queda guardado.
3. **Producto sin código de barras**, por cantidad: el sistema le **genera un código interno** y se imprime su sticker, una vez por producto y no por unidad, salvo que se pida.

**Stickers:** ya salen en **rollo térmico de 80 × 50 mm, una etiqueta por corte** (`StickerPdfService`), sin desperdicio. El A4 desperdicia material y vinil, y no se usa. Si se ve una salida en A4, es un error. Ver §9.5 (ancho del rollo de la impresora que se comprará).

**BI de almacén:**

| Métrica                                                           | Gráfico                           |
| ----------------------------------------------------------------- | --------------------------------- |
| Stock por categoría                                               | Dona                              |
| Productos bajo el mínimo y agotados                               | Barras + lista con acceso directo |
| Entradas y salidas por semana                                     | Líneas                            |
| Extintores en stock por estado (disponible, reservado, en taller) | Barras                            |
| Recepciones pendientes de hoy                                     | Contador + lista                  |

**Referencia:** los tableros de inventario recomiendan pocas métricas (rotación, quiebres, bajo mínimo), separar "reponer ya" de "por agotarse" y vistas por rol ([Shopify — métricas de inventario](https://shopify.com/blog/inventory-metrics), [Omniful — KPIs de inventario](https://www.omniful.ai/blog/inventory-kpi-dashboards-turnover-stockouts-reorder)).

## 5. Vendedor

| Módulo                                | Estado según el usuario | Qué falta                  |
| ------------------------------------- | ----------------------- | -------------------------- |
| **Inicio (BI)**                       | Se ve bien              | Más gráficos ("alegrarlo") |
| Clientes                              | Bien                    | —                          |
| Por vencer                            | Bien                    | —                          |
| Cotizaciones                          | Bien                    | —                          |
| Ventas, facturación, caja y cobranzas | (en auditoría)          | Comprobantes según SUNAT   |

**BI del vendedor:**

| Métrica                                               | Gráfico                    |
| ----------------------------------------------------- | -------------------------- |
| Mis ventas del mes frente a la meta o al mes anterior | Barras o medidor           |
| Mis cotizaciones: pendientes, aceptadas y perdidas    | Embudo o dona              |
| Mis cobros pendientes y vencidos                      | Dona                       |
| Clientes con extintores por vencer este mes           | Lista con acción "cotizar" |

## 6. Técnico de Planta y Técnico de Campo

- **Planta:** recepción, orden de taller por pasos, checklist y deficiencias. El usuario la ve definida; se revisa en la auditoría.
- **Campo: hay que desarrollarlo más.** Hace más cosas: recojos, inspecciones, **instalaciones**, **pozos a tierra**, mantenimientos y entregas. Debe quedar bien documentado para que **el certificado salga solo**, sin volver a escribir datos.

**Cómo llenar los certificados sin repetir datos.** Cada certificado declara sus campos, y **cada campo dice de dónde sale**:

| Origen del dato          | Ejemplos                                                                   | Quién lo llena                               |
| ------------------------ | -------------------------------------------------------------------------- | -------------------------------------------- |
| Cliente                  | Razón social, RUC, dirección                                               | Nadie: ya está                               |
| Orden o servicio         | N.° de orden, fecha, tipo de servicio                                      | Nadie: ya está                               |
| Equipo                   | Serie, marca, agente, capacidad, año                                       | Recepción o alta del equipo (una vez)        |
| **Medición del técnico** | Presión, peso, autonomía, **resistencia del pozo a tierra (Ω)**, resultado | **El técnico en su pantalla**, en el momento |
| Plantilla                | Título, norma, firmantes, vigencia                                         | Gerente, en el editor                        |

En la práctica:

- Las **columnas** de la tabla del certificado (ya editables) son **el formulario del técnico**. Lo que el técnico mide en Campo o Planta llena esas columnas directamente.
- Al cerrar el servicio, el certificado sale completo.
- Si falta un dato obligatorio, el sistema avisa antes de emitir. No se emite incompleto.

**Referencia:** el software de inspección de extintores reutiliza los datos del equipo de una inspección a otra, precarga el fabricante al escanear y arma el informe con datos, fotos y deficiencias ([Inspect Point — software de inspección de extintores](https://www.inspectpoint.com/resources/articles/how-a-fire-extinguisher-inspection-software-can-improve-work-flow-and-increase-profitability)). NFPA 10 exige registrar la fecha, quién hizo el trabajo y la empresa (misma fuente; confirmar con la edición vigente).

**BI de los técnicos:** trabajo de hoy (urgente, alta, normal), atrasados, terminados en la semana (barras) y, en Campo, visitas por tipo (dona).

## 7. Editor de certificados

**Lo que vio el usuario:**

- En los certificados de **formato fijo** (Operatividad y Garantía, PH, Capacitación) el editor muestra el **PDF guardado**, que no se puede editar.
- En los demás, la hoja es una **vista previa** y se edita desde el panel de la izquierda.

**Lo que quiere:** que el certificado **se vaya llenando y se pueda editar de forma agradable**, con todos sus recursos (logo, franjas, sello, firmas).

**Propuesta:**

1. Todos los certificados, también los fijos, usan la **vista previa en vivo**, no el PDF en un iframe.
2. **Se edita directo sobre la hoja:** clic en el título, la norma, el texto o el nombre de una columna, y se escribe ahí. El panel queda para lo que no es texto (orden, firmantes, hoja vertical u horizontal).
3. Opción mayor, si se quiere mover y arrastrar elementos como en Canva: existe **pdfme**, un editor visual de plantillas PDF, libre (MIT), hecho para React, con campos dinámicos y formularios ([pdfme.com](https://pdfme.com/), [GitHub pdfme](https://github.com/pdfme)). Es más trabajo, reemplaza el PDF actual y agrega una dependencia. Ver §9.4.

## 8. Módulos transversales

- **BI por rol:** cada rol tiene su inicio con KPI y gráficos (§3 a §6). Hoy **no hay librería de gráficos instalada**; ver §9.3.
- **Módulo de IA del gerente** (§3), con los datos históricos.
- **Módulo de guía (ayuda por rol):** manual corto por pantalla y Chispa, el asistente actual, que responde solo sobre el rol de quien pregunta. Ver §9.6.
- **Guía de remisión (GRE):** es una excepción, solo cuando el cliente pide despacho por bus u otro caso que confirme la contadora. Ver [SUNAT_FACTURACION.md](../SUNAT_FACTURACION.md) §6.

## 9. Decisiones del usuario (2026-10-18)

1. **Planta y técnicos:** la Planta es **un lugar aparte**, con dirección propia, cerca de las tiendas. Hace las recargas y mantenimientos y **atiende a todos los puntos de venta**. Los técnicos de planta pertenecen a la Planta.
    - Supuesto: los técnicos de campo también salen de la Planta. Confirmar en la auditoría.
2. **Almacén:** **un usuario de almacén por cada punto de venta.**
3. **Gráficos:** se aprueba instalar **`recharts`**.
4. **Certificados:** **nivel profesional**, con un editor visual para mover y arrastrar elementos, campos dinámicos y vista igual al PDF. Se evalúa **pdfme** (§7.3).
5. **Stickers:** habrá **dos impresoras** (aún no se compran):
    - una de **rollo de 80 × 50 mm**;
    - una **A4**, que debe llevar **varias etiquetas por hoja** para no desperdiciar material.

    Al imprimir se elige el formato.

6. **"Módulo de guía" = guía de remisión**, que **ya está hecha**. No se agrega ayuda por rol.

## 10. Cambios a implementar (en orden)

| #   | Cambio                                                                                                                                                                                                        | Área                                                   |
| --- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------ |
| 1   | **Lugares:** "Punto de venta" (tienda + su almacén) y "Planta" (taller que atiende a todos). Vendedor y Almacén pertenecen a un punto de venta; los técnicos, a la Planta. Se arregla la creación de técnicos | `Sede`, `User::TIPOS_DE_SEDE_POR_ROL`, Sedes, Usuarios |
| 2   | Ocultar el traslado con un solo punto de venta                                                                                                                                                                | Menú y rutas de Almacén                                |
| 3   | Stickers: elegir rollo 80 × 50 mm o A4 con varias etiquetas por hoja                                                                                                                                          | `StickerPdfService`, pantallas de stickers             |
| 4   | Instalar `recharts` y armar el **BI con gráficos** de los 5 roles (§3 a §6)                                                                                                                                   | Dashboards e inicios                                   |
| 5   | **Módulo de IA** propio del gerente                                                                                                                                                                           | Gerente (nueva pantalla)                               |
| 6   | **Editor de certificados profesional** (evaluar pdfme frente a mejorar el editor actual)                                                                                                                      | Certificados                                           |
| 7   | Campo: cada servicio (instalación, pozo a tierra, mantenimiento) llena las columnas del certificado                                                                                                           | Técnico de Campo, certificados                         |
| 8   | Producto sin código de barras: código interno y sticker por producto                                                                                                                                          | Productos, recepciones                                 |

## 11. Checklist de ideas del usuario (que nada se pierda)

- [x] Dashboard del gerente con gráficos, solo lo importante y conversión de cotizaciones en gráfico; sin ticket promedio → §3
- [x] Crear producto: bien. Extintor con agente y capacidad; otros productos con o sin código de barras → §3, §4
- [x] Servicios, caja consolidada, cobranzas y auditoría: bien → §3
- [x] Sedes solo como puntos de venta (tienda + almacén); la planta aparte; error al crear un técnico → §2, §9.1
- [x] Datos de la empresa y firmas y sellos: bien → §3
- [x] El editor de certificados no deja editar; investigar cómo lo hacen otros; que se rellene de forma agradable → §7
- [x] BI de almacén con gráficos ("un buen BI para todos los roles") → §4, §8
- [x] Recepciones según el tipo de producto → §4
- [x] Stickers: ahorrar papel y vinil, nada de A4 → §4, §9.5
- [x] Traslado solo cuando haya otra sede → §2, §4
- [x] Con código de barras = escanear y sumar por cantidad → §4
- [x] Inicio del vendedor = BI, alegrarlo → §5
- [x] Módulo de IA propio del gerente con datos históricos → §3, §8
- [x] Clientes, por vencer y cotizaciones: bien → §5
- [x] Técnico de Campo más desarrollado (instalaciones, pozos); certificados que se llenan solos con los datos del técnico; sin repetir → §6
- [x] "Módulo de guía" = guía de remisión, ya hecha; BI por rol → §8, §9.6
- [x] Todo relacionado, sin datos duplicados y según SUNAT → §1
