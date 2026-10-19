# SUNAT — Facturación electrónica de Bruce Fire

> Fuentes consultadas el **2026-10-18**. Cada regla lleva su fuente.
> **Oficial** = leída en sunat.gob.pe, cpe.sunat.gob.pe, orientacion.sunat.gob.pe, gob.pe o El Peruano.
> **Por confirmar** = solo la vimos en fuentes secundarias; revisar el texto oficial antes de usarla.
> Este documento describe la norma y dónde la cumple el sistema. No reemplaza a la contadora.

Bruce Fire S.A.C. (RUC 20616376528) emite desde su **propio sistema** (SEE - Del contribuyente, R.S. 097-2012/SUNAT) usando Greenter (ver [APIS.md](APIS.md)).

---

## 1. Plazos de envío

| Documento                                     | Plazo                                                                           | Si no se cumple                                                                                           | Fuente                                                                                                                                                                                                                                                                                                     | En el sistema                                                                                                                                              |
| --------------------------------------------- | ------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Factura** y sus **notas de crédito/débito** | El día de emisión o hasta **3 días calendario** contados desde el día siguiente | Lo no enviado **no tiene calidad de factura ni de nota electrónica**, aunque se haya entregado al cliente | **Oficial:** [R.S. 000003-2023/SUNAT, art. 3 y 4](https://www.sunat.gob.pe/legislacion/superin/2023/000003-2023.pdf)                                                                                                                                                                                       | Envío diferido de 6 h para corregir sin nota (`config/billing.php` → `envio_diferido_horas`); el comando `EnviarComprobantesProgramados` envía y reintenta |
| **Boleta** y sus notas (por resumen diario)   | El día de emisión o hasta el **7.° día calendario** siguiente                   | —                                                                                                         | **Oficial:** [Guía Resumen de Boletas, cpe.sunat.gob.pe](https://cpe.sunat.gob.pe/sites/default/files/inline-files/GUIA_Resumen_de_Boletas_11-01-2018%20%282%29_2_0%20%281%29_0.pdf) · [Orientación SUNAT: resumen diario](https://orientacion.sunat.gob.pe/04-resumen-diario-boleta-de-venta-electronica) | Bruce Fire envía cada boleta **individualmente**, como la factura. No usa resumen diario                                                                   |

**Cambio que viene (2027):** la [R.S. 000048-2026/SUNAT](https://www.sunat.gob.pe/legislacion/superin/2026/000048-2026.pdf) modifica la normativa del SEE. La [R.S. 000143-2026/SUNAT](https://www.sunat.gob.pe/legislacion/superin/2026/000143-2026.pdf) (**oficial**, leída) **posterga su entrada en vigor al 1 de enero de 2027**, y algunas partes al 1 de abril de 2027.

- **Por confirmar:** según fuentes secundarias, desde 2027 cambia cómo se cuenta el plazo de la **comunicación de baja** (7 días desde la emisión) y la baja de boletas pasa por el resumen diario.
- **Tarea antes del 01/01/2027:** leer el texto oficial de la 000048-2026 y ajustar las bajas si corresponde.

## 2. Qué documento emitir

| Caso                                        | Documento                           | Fuente                                                                                                                                                             |
| ------------------------------------------- | ----------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Cliente con RUC que usa crédito fiscal      | **Factura** (01)                    | Reglamento de Comprobantes de Pago, [R.S. 007-99/SUNAT](https://www.sunat.gob.pe/legislacion/superin/1999/007_anterior.htm) (texto original; tiene modificatorias) |
| Consumidor final (DNI o sin documento)      | **Boleta** (03)                     | Ídem                                                                                                                                                               |
| Anular, devolver, descontar o corregir      | **Nota de crédito** (07)            | Catálogo 09 (motivos)                                                                                                                                              |
| Cobrar de más (intereses, penalidad)        | **Nota de débito** (08)             | Catálogo 10                                                                                                                                                        |
| Traslado de bienes cuando la norma lo exige | **Guía de remisión remitente** (09) | Ver §6                                                                                                                                                             |

**Nota de venta.** No figura entre los comprobantes de pago del Reglamento. Un **emisor electrónico** debe emitir el comprobante por el SEE. No hacerlo o reemplazarlo por otro documento puede configurar las infracciones del art. 174 del Código Tributario (**oficial:** [Informe SUNAT N.° 127-2020-SUNAT/7T0000](https://www.sunat.gob.pe/legislacion/oficios/2020/informe-oficios/i127-2020-7T0000.pdf)).
→ **En el sistema:** la nota de venta es interna. `FormalizarNotaVenta` la convierte en factura o boleta sin duplicar la venta, el stock ni los certificados.

## 3. Forma de pago: contado o crédito

- **Contado:** se paga en la fecha de emisión. **Crédito:** se paga total o parcialmente después.
- En crédito se consignan el **monto neto pendiente de pago**, la **fecha de vencimiento de cada cuota** y su **monto**.
- El **monto neto pendiente** es el saldo, descontadas la detracción y las retenciones.
- Un error en la forma de pago o en las cuotas se corrige con una **nota de crédito, motivo 13**.
- **Fuentes oficiales:** [R.S. 000193-2020/SUNAT](https://www.sunat.gob.pe/legislacion/superin/2020/193-2020.pdf) · [Anexo 1 (campos)](https://www.sunat.gob.pe/legislacion/superin/2020/anexo1-193-2020.pdf).
- **En el sistema:** `GreenterService` arma `FormaPago` y las cuotas. El PDF imprime el "neto pendiente" cuando hay detracción.

## 4. Detracción (SPOT) en servicios

| Regla               | Detalle                                                                                                                                                                                                 | Fuente                                                                                                                                                                                                                         |
| ------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Cuándo aplica       | Servicios del **Anexo 3** cuando el importe de la operación es **mayor a S/ 700** (S/ 700 exactos no)                                                                                                   | **Oficial:** [Orientación SUNAT — prestación de servicios](https://orientacion.sunat.gob.pe/3142-03-en-la-prestacion-de-servicios-empresas) · [R.S. 183-2004/SUNAT](https://www.sunat.gob.pe/legislacion/superin/2004/183.htm) |
| Tasas que nos tocan | **020** Mantenimiento y reparación de bienes muebles: **12 %** · **022** Otros servicios empresariales: **12 %** · **037** Demás servicios gravados con IGV: **12 %** · **019** Arrendamiento: **10 %** | Ídem                                                                                                                                                                                                                           |
| Código en el XML    | Catálogo **54** (código del bien o servicio)                                                                                                                                                            | Catálogo de códigos (Anexo V de la R.S. 340-2017/SUNAT): [Orientación SUNAT — anexos](https://orientacion.sunat.gob.pe/node/825) · [cpe.sunat.gob.pe — guías y manuales](https://cpe.sunat.gob.pe/guias-y-manuales)            |
| Tipo de operación   | **1001** (operación sujeta a detracción)                                                                                                                                                                | Catálogo 51                                                                                                                                                                                                                    |
| Leyenda             | **2006** "Operación sujeta al Sistema de Pago de Obligaciones Tributarias"                                                                                                                              | Catálogo 52                                                                                                                                                                                                                    |
| Medio de pago       | **001** depósito en cuenta del Banco de la Nación (catálogo 59). El depósito va a la **cuenta de detracciones del proveedor en el BN**                                                                  | **Oficial:** [Orientación SUNAT — depósito](https://orientacion.sunat.gob.pe/02-deposito-de-detracciones-aspecto-generales)                                                                                                    |

**Qué código lleva cada servicio de Bruce Fire: PENDIENTE de la contadora.**

- La recarga y el mantenimiento de extintores pueden ir en **020** o en **022**; los dos son del 12 %.
- En `config/billing.php` hay una nota antigua: "Recarga: 022 (Informe SUNAT 017-2006)". Hay que pedirle a la contadora que **confirme ese informe**.
- El código se elige en **Servicios → Gestionar tipos**. Mientras un tipo esté "Por definir", no se emiten facturas de más de S/ 700 con ese servicio.

**En el sistema:**

- `DetraccionCalculator` calcula la detracción con la tasa vigente a la fecha (tablas `sunat_detracciones` y `sunat_detraccion_reglas`).
- La factura emitida guarda su porcentaje, así que una reimpresión no cambia.
- La constancia de depósito se registra en Cobranzas (`ConstanciaDetraccionController`).

## 5. Código de producto SUNAT (catálogo 25)

- **Voluntario** en el comprobante: "no tiene la calidad de requisito mínimo" (art. 2 de la [R.S. 133-2019/SUNAT](https://www.sunat.gob.pe/legislacion/superin/2019/133-2019.pdf)).
- Puede ser **UNSPSC** o **GTIN** (EAN-13).
- **Por confirmar:** desde el 01/01/2027 sería obligatorio solo para los bienes de las listas 25.1, 25.2 y 25.3 (detracción, percepción y otros bienes listados). Los extintores no están en esas listas.
- **En el sistema:**
    - el catálogo oficial UNSPSC v14 (Excel `CCNU_MOD_2` de SUNAT) se convierte en `database/data/sunat_codigos_producto.csv` (52 840 códigos) y se carga con `php artisan sunat:importar-codigos-producto`;
    - se busca por nombre al crear productos y tipos de servicio;
    - el XML lo envía en `codProdSunat`;
    - el GTIN no se envía: Greenter no incluye el `schemeID`.

## 6. Guía de remisión electrónica (GRE)

- La **R.S. 000123-2022/SUNAT** hizo obligatoria la GRE electrónica y designó emisores por grupos desde el 01/01/2023. **Oficial:** [gob.pe — R.S. 000123-2022](https://www.gob.pe/institucion/sunat/normas-legales/3272377-000123-2022-sunat) · [Nota de prensa SUNAT 043-2022](https://www.sunat.gob.pe/salaprensa/2022/julio/NotaPrensaN0432022.doc).
- Desde el 01/01/2024 ya están incluidos todos los grupos (**oficial:** [RSNATI 000026-2025](https://cpe.sunat.gob.pe/sites/default/files/inline-files/RSNATI%20N.%2026-2025%20-%20El%20peruano.pdf)).
- La **R.S. 108-2026/SUNAT** modifica la 000123-2022 y agrega supuestos de GRE por evento ([cpe.sunat.gob.pe](https://cpe.sunat.gob.pe/sites/default/files/2026-06/Resoluci%C3%B3n%20de%20Superintendencia%20108-2026_0.PDF)). **Por confirmar** su detalle: el PDF bloqueó la lectura automática.
- La GRE se envía por la **API REST** de SUNAT, con credenciales propias (`client_id` y `client_secret`) distintas de la Clave SOL. Ver [APIS.md](APIS.md).
- **En Bruce Fire es una excepción:** solo cuando el cliente pide despacho por bus u otro supuesto que la contadora confirme. El delivery de cada día no fuerza una GRE. Motivo de traslado configurable: `GRE_MOTIVO_ORDENES` (catálogo 20).

## 7. Representación impresa y QR

El PDF lleva un QR con los datos del comprobante, separados por `|`:

```
RUC_EMISOR|TIPO_DOC|SERIE|CORRELATIVO|IGV|TOTAL|FECHA_EMISION|TIPO_DOC_CLIENTE|NUM_DOC_CLIENTE|HASH|
```

- **Fuente:** anexos de la R.S. 097-2012/SUNAT ([cpe.sunat.gob.pe — guías y manuales](https://cpe.sunat.gob.pe/guias-y-manuales)).
- **En el sistema:** `ComprobanteQrGenerator` y `pdf/comprobante.blade.php`.
- El PDF no prueba nada por sí solo: la validez la da el **CDR** de SUNAT, que el sistema guarda.

## 8. Catálogos que usa el sistema

| Catálogo | Uso                                     | Valores de Bruce Fire                                                                 |
| :------: | --------------------------------------- | ------------------------------------------------------------------------------------- |
|    01    | Tipo de documento                       | 01 factura · 03 boleta · 07 NC · 08 ND · 09 GRE                                       |
|    02    | Moneda                                  | PEN                                                                                   |
|    03    | Unidad de medida                        | NIU unidad (bienes) · ZZ servicio · otras de `UnidadMedidaSunat`                      |
|    06    | Documento de identidad                  | 6 RUC · 1 DNI · 4 carné de extranjería · 7 pasaporte                                  |
|    07    | Afectación del IGV                      | 10 gravado · 20 exonerado · 30 inafecto                                               |
| 09 / 10  | Motivo de NC / ND                       | 01 anulación · 06 devolución total · 07 devolución por ítem · 13 corrección de cuotas |
|    20    | Motivo de traslado (GRE)                | según el caso, a confirmar con la contadora                                           |
|    25    | Código de producto                      | UNSPSC (§5)                                                                           |
|    51    | Tipo de operación                       | 0101 venta interna · 1001 sujeta a detracción                                         |
|    52    | Leyendas                                | 1000 monto en letras · 2006 detracción                                                |
|    54    | Bienes y servicios sujetos a detracción | §4 (tabla `sunat_detracciones`)                                                       |
|    59    | Medio de pago                           | 001 depósito en cuenta                                                                |

Catálogos completos y reglas de validación vigentes: [cpe.sunat.gob.pe — guías y manuales](https://cpe.sunat.gob.pe/guias-y-manuales).

## 9. Pendientes con la contadora o SUNAT

1. El código de detracción de cada tipo de servicio (020 o 022) y el Informe 017-2006.
2. Los supuestos en que Bruce Fire debe emitir GRE y con qué motivo de traslado.
3. Antes del 01/01/2027: revisar la R.S. 000048-2026 (bajas y boletas) y la R.S. 108-2026 (GRE).
4. Para pasar a producción: certificado digital `.pem` y usuario SOL secundario con permiso de facturación (puede tardar 24 h). Ver [APIS.md](APIS.md).
