# SUNAT: normativa vigente y estado de nuestra facturación

**Sistema web para la gestión de ventas en BRUCE FIRE S.A.C.** · Actualizado 2026-10-06

**Situación actual:** se trabaja **solo en el ambiente beta (pruebas) de SUNAT**. No se envía nada a producción hasta que todo el sistema esté validado y sin errores (sección 4).

**Fuentes de este documento:**

- Las guías XML UBL 2.1 oficiales de 2017–2018, convertidas en `documentos/sunat/`.
- La normativa posterior que las modificó, buscada el 2026-10-06 (sección 6).

Si una guía de 2017 choca con una norma posterior, **manda la norma posterior**.

---

## 1. Reglas vigentes que aplican a Bruce Fire

| Tema                                                  | Regla vigente                                                                                                                                                                                                                                                              | Norma o fuente                     |
| ----------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------- |
| Plazo de envío de **factura** y sus notas             | El día de emisión o hasta **3 días calendario** desde el día siguiente. Si se envía después, SUNAT la **rechaza**.                                                                                                                                                         | R.S. 000003-2023/SUNAT             |
| Plazo de envío de **boleta** y sus notas              | Envío individual: hasta **5 días calendario** desde la emisión. Por resumen diario: hasta **7 días calendario**.                                                                                                                                                           | Orientación SUNAT, operatividad    |
| Envío individual de boletas                           | Permitido; no hace falta el resumen diario                                                                                                                                                                                                                                 | R.S. 114-2019/SUNAT                |
| Boleta sin identificar al cliente                     | Solo hasta **S/ 700**; por encima, DNI obligatorio                                                                                                                                                                                                                         | Reglamento de comprobantes de pago |
| **Forma de pago** en la factura                       | Obligatoria: contado, o crédito con monto neto pendiente y cuotas con sus fechas                                                                                                                                                                                           | R.S. 000193-2020/SUNAT             |
| **Comunicación de baja** (anular sin nota de crédito) | Solo si el comprobante **no se entregó** al cliente y tiene CDR aceptado. Hasta **7 días calendario** desde el día siguiente al CDR. Después, solo con nota de crédito.                                                                                                    | Orientación SUNAT; guía factura §6 |
| NC sobre **boleta**                                   | No se permiten los motivos **04** (descuento global), **05** (descuento por ítem) ni **08** (bonificación)                                                                                                                                                                 | Guía NC, línea 888                 |
| Catálogo 09 (motivos de NC)                           | Del 01 al **13**. El 13 corrige el monto neto pendiente o las fechas y montos de las cuotas de una venta a crédito.                                                                                                                                                        | R.S. 000193-2020, anexo 3          |
| Catálogo 10 (motivos de ND), **desde el 1/08/2026**   | **03 = "Otros conceptos"** (ya no incluye penalidades). **13 = Penalidades, inafectas al IGV.**                                                                                                                                                                            | R.S. 000048-2026/SUNAT             |
| NC y ND, **desde el 1/08/2026**                       | Cada nota referencia **un solo** comprobante de origen                                                                                                                                                                                                                     | R.S. 000048-2026/SUNAT             |
| Código de producto SUNAT (UNSPSC, catálogo 25)        | Obligatorio solo para **ciertos bienes** listados por SUNAT, desde el **1/01/2027** (se postergó del 1/08/2026). Si se envía, debe tener **8 dígitos numéricos**.                                                                                                          | R.S. 000048-2026 y prórroga        |
| Reglas de validación de CPE                           | SUNAT las actualiza seguido (última publicada: julio de 2026). Hay que contrastarlas antes de pasar a producción.                                                                                                                                                          | Portal CPE, "Guías y manuales"     |
| **Guía de remisión electrónica** (GRE)                | **Obligatoria.** La tolerancia para la guía impresa del remitente venció el **31/08/2026**. Se exige para transportar bienes, incluido el traslado entre locales de la misma empresa (motivo 04). Usa la API REST de SUNAT con token, no el servicio SOAP de las facturas. | Portal CPE, "Guía de remisión"     |
| **Detracción**                                        | Código **020** (mantenimiento o reparación de bienes muebles), tasa **12 %**, si la operación supera **S/ 700**. Por confirmar con el contador si la recarga de extintores entra aquí o en el 022 o el 037.                                                                | Anexo 3, R.S. 183-2004/SUNAT       |

## 2. Qué hacemos bien

| Tema                   | Detalle                                                                                                                        |
| ---------------------- | ------------------------------------------------------------------------------------------------------------------------------ |
| Firma y estructura     | Greenter arma el UBL 2.1 y lo firma con el certificado                                                                         |
| Series                 | Factura F001 (01), boleta B001 (03), NC FC01/BC01 y ND FD01/BD01. Cumplen la regla de la F o la B según el documento afectado. |
| Tipo de operación      | `0101`, o `1001` cuando hay detracción                                                                                         |
| Totales                | Se suman línea por línea, así cuadran con el detalle; el precio unitario va con IGV                                            |
| Leyendas               | 1000 (monto en letras) y 2006 (detracción)                                                                                     |
| Forma de pago          | Contado, o crédito con cuotas netas de detracción                                                                              |
| Datos del cliente      | La factura exige RUC Activo y Habido, y dirección. La boleta a "clientes varios" se bloquea por encima de S/ 700.              |
| Plazos                 | El envío diferido espera 6 h, dentro de cualquier plazo legal                                                                  |
| Anular antes de enviar | Descarta el comprobante y libera el número                                                                                     |

## 3. Qué hacemos mal o falta

Leyenda: ✅ verificado por Claude en el código · 📋 reportado por un agente, sin verificar aparte.

| #   | Problema                                                                                                                                                                                                                                                                | Regla que incumple                                              | Código                                                                        | Estado |
| --- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------- | ----------------------------------------------------------------------------- | ------ |
| S1  | **No se puede cambiar a producción.** No hay `setService`, así que `SUNAT_BETA=false` no hace nada. Hoy en beta es **correcto**; el día de pasar a producción, seguiría enviando a beta. Además, la librería conecta **sin verificar el certificado TLS** del servidor. | Envío a `FE_PRODUCCION`                                         | `app/Services/Billing/GreenterSunatClient.php:25`                             | ✅     |
| S2  | La NC acepta los motivos 04 y 05 sobre boletas                                                                                                                                                                                                                          | Prohibido (guía NC, línea 888)                                  | `app/Actions/Billing/IssueCreditNote.php:34`, `StoreCreditNoteRequest.php:21` | ✅     |
| S3  | **ND motivo 03 = "Penalidades u otros conceptos"** con IGV 18 %                                                                                                                                                                                                         | Desde el 1/08/2026, las penalidades van con el **13, inafecto** | `app/Services/Billing/GreenterService.php:291-297`                            | ✅     |
| S4  | **"Aplica IGV" del catálogo no se usa:** todo sale gravado (afectación 10)                                                                                                                                                                                              | Catálogo 07 por línea                                           | `GreenterService.php:248,319`                                                 | ✅     |
| S5  | **El XML sale con hora 00:00:00** (`fecha_emision` es solo fecha) y el PDF muestra otra hora                                                                                                                                                                            | La hora de emisión es obligatoria                               | Migración `2026_09_25_214512:22`, `GreenterService.php:101`                   | ✅     |
| S6  | La fecha de la NC y la ND es `now()` en cada reintento; puede cambiar de día o de mes                                                                                                                                                                                   | La fecha es la del ajuste                                       | `GreenterService.php:257`                                                     | ✅     |
| S7  | **No hay comunicación de baja.** Todo se anula con NC, aunque el comprobante no se haya entregado.                                                                                                                                                                      | Comunicación de baja en 7 días                                  | Búsqueda sin resultados en `app/`                                             | ✅     |
| S8  | **No hay alerta de plazo:** un comprobante pendiente puede pasarse de los 3 días (factura) o 5 (boleta) y SUNAT lo rechazaría                                                                                                                                           | R.S. 000003-2023                                                | `app/Console/Commands/EnviarComprobantesProgramados.php`                      | 📋     |
| S9  | Falta el catálogo 09 completo (08, 09, 10, 13). El rótulo del 07 debe decir "devolución parcial".                                                                                                                                                                       | Catálogo 09 vigente                                             | `GreenterService.php:277-288`                                                 | ✅     |
| S10 | La NC y la ND son una sola línea al 1.18 fijo. Descuadra si el original no es todo gravado.                                                                                                                                                                             | Totales coherentes con el original                              | `GreenterService.php:233-250`                                                 | ✅     |
| S11 | Las notas no cambian la cobranza: una NC parcial no reduce la deuda y una ND no la aumenta                                                                                                                                                                              | Coherencia contable                                             | `IssueCreditNote.php:108`, `IssueDebitNote.php:41`                            | 📋     |
| S12 | El comprobante **no queda congelado**: reenviar o regenerar el PDF lee el cliente y el producto actuales                                                                                                                                                                | El XML aceptado no cambia                                       | `EmitElectronicDocument.php:56`, `ComprobantePdfService.php:220`              | 📋     |
| S13 | Reintentos: un error de red sale de la cola automática; si falta el CDR, se toma como código 0                                                                                                                                                                          | Aceptar solo con un CDR válido                                  | `GreenterSunatClient.php:48,67`                                               | 📋     |
| S14 | Detracción sobre el total aunque la venta mezcle bienes y servicios; el código 020 falta confirmarlo                                                                                                                                                                    | Anexo 3 de detracciones                                         | `app/Services/Billing/DetraccionCalculator.php:23`                            | 📋     |
| S15 | **No existe la guía de remisión**                                                                                                                                                                                                                                       | GRE obligatoria                                                 | Solo hay serie y permisos                                                     | ✅     |
| S16 | Nuestra guía interna tenía mal la URL de producción (servicio de consulta)                                                                                                                                                                                              | —                                                               | `docs/FACTURACION_GREENTER_SUNAT.md:39` (**corregida**)                       | ✅     |
| S17 | Permisos: cualquier vendedor puede emitir NC sin aprobación del Gerente                                                                                                                                                                                                 | Matriz de permisos del proyecto                                 | `StoreCreditNoteRequest.php:10`                                               | ✅     |

**Descartado:** "se puede emitir una factura a un cliente sin RUC". Es falso: `ValidarComprobanteCliente.php:20` lo bloquea.

## 4. Paso de beta a producción (checklist)

No se envía nada a producción hasta cumplir **todo** esto:

1. **Código:**
    - S1: elegir el servidor según `SUNAT_BETA` y verificar TLS.
    - S2 a S6: corregidos y con pruebas.
2. **Pruebas en beta, cada caso con su CDR aceptado y guardado:**
    - Factura al contado y a crédito con cuotas.
    - Factura con detracción.
    - Boleta, boleta a "clientes varios" y boleta mayor a S/ 700.
    - NC total, NC parcial y NC sobre boleta.
    - ND.
    - Comunicación de baja.
    - Reenvío después de una caída de red.
3. **Reglas de validación vigentes:** contrastar con la versión de julio de 2026 del portal CPE.
4. **Datos reales:**
    - Certificado digital de producción (`.pem`).
    - Usuario SOL secundario con permiso de facturación.
    - RUC, dirección y ubigeo reales en la configuración.
    - Cuenta de detracción del Banco de la Nación.
5. **Servidor:**
    - Cron `schedule:run` cada minuto (el envío diferido y los reintentos dependen de él).
    - Respaldo de `storage/app/private/xml` y de los CDR fuera del servidor.
6. **Corte:** el primer día, emitir un comprobante real de bajo monto y confirmarlo en "Consulta de validez del CPE" de SUNAT.

## 5. Guía de remisión electrónica (GRE): qué exige y cómo debemos hacerla

### 5.1 ¿Bruce Fire está obligado?

**Sí.** La GRE es obligatoria y la tolerancia para la guía impresa del remitente venció el 31/08/2026. Sustenta todo traslado de bienes y debe tener su **CDR aceptado antes de que el vehículo salga**. Bruce Fire mueve bienes en 4 situaciones:

| Situación en Bruce Fire                                                       | ¿Quién emite?                                                                                         | Motivo (catálogo 20)                                                                                    | Documento relacionado                          |
| ----------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------- | ---------------------------------------------- |
| Entrega de una **venta** al local del cliente (extintores, EPP, señalización) | Bruce Fire (remitente)                                                                                | **01 Venta**                                                                                            | La factura o boleta                            |
| **Recojo** de extintores del cliente para recargarlos en el taller            | Bruce Fire, porque el servicio incluye el recojo (criterio de SUNAT para talleres que recogen bienes) | **13 Otros**: "recojo para recarga o mantenimiento". Algunos usan el 17. **Confirmar con el contador.** | Orden de servicio                              |
| **Devolución** de los extintores recargados al cliente                        | Bruce Fire                                                                                            | **13 Otros** (o el 07, según criterio del contador)                                                     | Orden de servicio, y la factura si ya se cobró |
| **Traslado entre sedes** (almacén → tienda)                                   | Bruce Fire                                                                                            | **04 Traslado entre establecimientos**                                                                  | —                                              |

- **Transporte privado (02):** con vehículo propio, la guía lleva la placa, el DNI y la licencia del conductor. Si el vehículo es **M1** (auto de hasta 8 asientos) o **L** (moto), no se pide la GRE-transportista, **pero la GRE-remitente sigue siendo obligatoria**.
- **Transporte público (01):** con una empresa de transporte, lleva su RUC y razón social; el transportista emite su propia GRE-transportista.

### 5.2 Datos obligatorios

- Serie **T001** (remitente) y correlativo; tipo **09**.
- Fecha de emisión y de **inicio del traslado**.
- Motivo (catálogo 20) y modalidad: 01 público o 02 privado.
- Destinatario: tipo y número de documento, y nombre.
- **Punto de partida y de llegada:** dirección y **ubigeo**. Si es un local propio, también el código de establecimiento anexo SUNAT.
- Bienes: descripción, cantidad, unidad de medida y **peso bruto total** (KGM).
- Privado: placa, y conductor con DNI, nombre y licencia. Público: RUC y razón social del transportista.
- Documento relacionado (factura o boleta) cuando el motivo es una venta.

### 5.3 Cómo funciona técnicamente (distinto de la factura)

| Paso         | Detalle                                                                                                                                                                           |
| ------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Credenciales | En **Menú SOL → Empresas → Credenciales de API SUNAT** se generan un `client_id` y un `client_secret`. Además se usan el usuario y la clave SOL.                                  |
| Token        | `POST https://api-seguridad.sunat.gob.pe/v1/clientessol/{client_id}/oauth2/token/` (`grant_type=password`, `scope=https://api-cpe.sunat.gob.pe`). Dura **1 hora** y se reutiliza. |
| Envío        | `POST https://api-cpe.sunat.gob.pe/v1/contribuyente/gem/comprobantes/{RUC}-09-T001-{número}`, con el ZIP del XML firmado en base64 y su hash SHA-256                              |
| Respuesta    | Un **ticket**. Se consulta en `GET .../comprobantes/envios/{ticket}`: **98** en proceso (reintentar), **0** aceptada (trae el CDR), **99** rechazada.                             |
| Constancia   | Representación impresa o **QR**, que acompaña el traslado                                                                                                                         |

- **Greenter (comprobado 09/10/2026):** instalados `greenter/lite` v5.3.0 y `greenter/gre-api` v1.0.2. `GreApiClient` usa OAuth2; no hace falta agregar dependencias. En la configuración local faltan `SUNAT_GRE_CLIENT_ID` y `SUNAT_GRE_CLIENT_SECRET`. Se comprobó su presencia sin imprimir valores.
- **Pruebas (09/10/2026):** el proyecto configura GRE con `gre-test.nubefact.com` y facturación con `SUNAT_BETA=true`. Mantener solo pruebas; no emitir guías de producción en esta etapa. La falta de credenciales impide validar un envío GRE real.

### 5.4 Diseño propuesto dentro del sistema

- **Un solo módulo "Guías de remisión"** con modelo `DispatchGuide`: serie y correlativo, motivo, modalidad, partida, llegada, vehículo, conductor, peso, estado SUNAT, ticket, CDR y PDF. Sus ítems apuntan a productos o a equipos (BF-EQ).
- **Nace desde donde ocurre el movimiento**, sin volver a escribir datos:
    - **Venta:** botón "Emitir guía", que copia el cliente, los ítems y la dirección.
    - **Orden de servicio:** el técnico de campo la emite **antes de salir** al recojo y a la entrega, desde el celular.
    - **Traslado de almacén:** el traslado queda "en tránsito" y la guía sustenta el viaje; el almacén destino confirma la llegada.
- **Datos maestros que faltan:** vehículos de la empresa (placa y categoría M1, L o N), conductores (DNI y licencia), peso por producto y código de establecimiento anexo por sede.
- **Mientras el sistema no la tenga:** la GRE se puede emitir **gratis desde SUNAT** (portal SOL o la app _Emprender_). Conviene hacerlo ya para no arriesgar multas.

## 6. Preguntas para el contador

1. ¿Venden algo **exonerado o inafecto** de IGV? (S4)
2. Recarga y mantenimiento de extintores: ¿detracción **020, 022 o 037**? ¿Sobre el total o solo sobre los servicios? (S14)
3. ¿Cobran **penalidades** o intereses por mora? Desde agosto de 2026 van como ND 13, inafecta. (S3)
4. ¿Transportan bienes a clientes o entre sedes, con qué vehículo (auto M1, moto o camioneta)? ¿Emiten hoy la guía de remisión en el portal de SUNAT? ¿Qué motivo usar para el recojo y la devolución de extintores en recarga: 13 Otros, 17 o 07? (S15, §5)
5. ¿Hacen **bonificaciones** o descuentos después de vender? (S9)

## 7. Fuentes consultadas (2026-10-06)

- [SUNAT: Boleta de Venta Electrónica](https://cpe.sunat.gob.pe/tipos_de_comprobantes/boleta) · [Operatividad](https://orientacion.sunat.gob.pe/3529-operatividad) · [Preguntas frecuentes CPE](https://cpe.sunat.gob.pe/informacion_general/preguntas_frecuentes)
- [El Peruano: nuevo plazo de envío de facturas (R.S. 000003-2023)](https://busquedas.elperuano.pe/dispositivo/NL/2140551-1)
- [Anexo 3 de la R.S. 193-2020: catálogo 09](https://www.sunat.gob.pe/legislacion/superin/2020/anexo3-193-2020.pdf)
- [Comunicación de baja: plazo de 7 días (Nubefact)](https://www.nubefact.com/como-anular-una-factura-electronica-sunat)
- [R.S. 000048-2026: cambios desde agosto de 2026 (Revista de Consultoría)](https://revistadeconsultoria.com/resolucion-000048-2026-sunat-los-cambios-que-impactaran-la-facturacion-electronica-2026/) · [PeruSoftware](https://perusoftware.net.pe/blog/actualizacion-facturacion-electronica-sunat-1-agosto-2026)
- [Código de producto SUNAT: prórroga al 1/01/2027 (Nubefact)](https://www.nubefact.com/blog/actualizaciones-sunat/nuevos-codigos-de-productos-sunat-evita-el-rechazo-de-tus-comprobantes-desde-agosto-de-2026)
- [Reglas de validación CPE, febrero y julio de 2026 (Estela)](https://blog.estela.com/per%C3%BA/sunat-actualiza-las-reglas-de-validaci%C3%B3n-de-los-cpe-cambios-clave-a-partir-de-febrero-de-2026) · [Guías y manuales SUNAT](https://cpe.sunat.gob.pe/guias-y-manuales)
- [Guía de remisión electrónica (SUNAT)](https://cpe.sunat.gob.pe/tipos_de_comprobantes/guiaderemision) · [Tolerancia de la GRE impresa](https://tramitesperu.com/sunat/guia-remision/)
- [Detracciones: código 020 al 12 % (Factpro, catálogo 54)](https://docs.factpro.la/catalogos-sunat/catalogo-54-codigos-de-bienes-y-servicios-sujetos-a-detracciones)
- [Greenter: paso a producción](https://greenter.dev/production/)
- [Catálogo 20, motivos de traslado (anexo 1 de la R.S. 000240-2024)](https://www.sunat.gob.pe/legislacion/superin/2024/anexo1-000240-2024.pdf) · [Preguntas frecuentes GRE (SUNAT)](https://orientacion.sunat.gob.pe/sites/default/files/inline-files/PreguntasFrecuentesGREAspectosGenerales1012.pdf) · [GRE remitente (SUNAT)](https://orientacion.sunat.gob.pe/02-guia-de-remision-remitente)
- [GRE por API REST: token, envío y ticket](https://verifac.pe/blog/gre-api-rest-ticket-cdr-antes-del-traslado/) · [Greenter gre-api](https://github.com/thegreenter/gre-api) · [Ejemplo de Greenter: traslado entre establecimientos](https://github.com/thegreenter/demo/blob/master/examples/guia-misma-empresa.php)
- [Taller que recoge bienes: emite la guía (informe SUNAT)](https://www.sunat.gob.pe/legislacion/oficios/2004/oficios/i0612004.htm) · [Excepción M1 y L (La Cámara)](https://lacamara.pe/nuevos-requisitos-para-la-guia-de-remision-electronica/)

## Verificación del 09/10/2026

- La [R.S.N.A.T.I. 000031-2026](https://cpe.sunat.gob.pe/node/119) prorrogó la discrecionalidad GRE remitente hasta el 31/08/2026 y GRE transportista hasta el 28/02/2027. Corregir el traspaso que indicaba sanciones generales desde el 01/07/2026; no confundir discrecionalidad con eliminación de la obligación.
- `GuiaRemisionService` registra aceptación cuando la respuesta incluye CDR; `DispatchGuide::estaListaParaTrasladar()` solo consulta el estado. `TransferInventory::handle()` mueve stock a tránsito sin exigir GRE y `confirmar()` tampoco la exige. El bloqueo de despacho por CDR está pendiente; los estados visuales no lo prueban. No se localizó una ruta de impresión GRE que habilite ese despacho.
- Etiquetas: el PDF existente es un sticker de identificación de 50 × 50 mm, no acredita rotulado de mantenimiento. La [NTP 833.030:2012, copia consultada](https://servilex.pe/documents/seguridad/833.030.pdf), §4.1–4.7, contempla A7/A8, información en negro, collar y tarjeta de inspección. Falta cotejar la edición vigente autorizada y los rótulos físicos del dueño; no se modifica el sticker ni se agregan códigos.
- Prueba hidrostática: se mantiene el intervalo actual de cinco años. Decisión pendiente del dueño: confirmar norma, edición, agente y tipo de cilindro aplicables antes de cambiarlo. La comparación con NFPA del traspaso no se toma como regla verificada.
