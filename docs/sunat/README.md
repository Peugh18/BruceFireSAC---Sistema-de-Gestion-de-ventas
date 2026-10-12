# Fuentes oficiales SUNAT: facturación electrónica

Manuales oficiales de SUNAT convertidos de PDF a Markdown (2026-10-06) para consultarlos al programar la facturación.

**Regla:** todo cambio en facturación (XML, envío, anulación, representación impresa) se contrasta con estas guías. Si una regla de aquí choca con el código, manda la guía, salvo una norma SUNAT posterior que la haya cambiado. En ese caso, se cita la norma en el código o en `docs/ai/`.

| Archivo                          | Documento original                                                 | Versión         | Cubre                                                            |
| -------------------------------- | ------------------------------------------------------------------ | --------------- | ---------------------------------------------------------------- |
| `guia-xml-factura-ubl21.md`      | Guía de elaboración de documentos XML: Factura Electrónica UBL 2.1 | v1.0, mayo 2017 | Estructura, normas de uso y validaciones de la factura (tipo 01) |
| `guia-xml-boleta-ubl21.md`       | Guía XML: Boleta de Venta Electrónica UBL 2.1                      | v1.0            | Boleta (tipo 03)                                                 |
| `guia-xml-nota-credito-ubl21.md` | Guía XML: Nota de Crédito Electrónica UBL 2.1                      | v1.0            | Nota de crédito (tipo 07), catálogo 09                           |
| `guia-xml-nota-debito-ubl21.md`  | Guía XML: Nota de Débito Electrónica UBL 2.1                       | v1.0            | Nota de débito (tipo 08), catálogo 10                            |
| `guia-resumen-diario-boletas.md` | Guía de elaboración del Resumen Diario de Boletas                  | 11-01-2018      | Envío y anulación de boletas y sus notas por resumen (RC)        |

Los PDF originales están en `Downloads/MANUAL DE FACTURACIÓN`.

## Advertencias

- Estas guías son de 2017–2018. **Normas posteriores las modificaron.** Las más importantes:
    - La forma de pago (contado o crédito con cuotas) es obligatoria en facturas desde 2021 (R.S. 193-2020/SUNAT).
    - Las validaciones vigentes están en las listas de "Reglas de validación" que SUNAT publica en su portal de CPE.
    - Antes de dar por buena una regla, compárala con esa lista vigente.
- La conversión de PDF a texto puede desordenar algunas tablas. Ante la duda, abre el PDF original.
- La guía de remisión electrónica (GRE) **no** está en estas fuentes: usa otra API de SUNAT (REST con OAuth).

## Relacionado

- Comparación entre estas guías y nuestro código: `docs/ai/SUNAT.md`.
- Guía técnica interna: `docs/FACTURACION_GREENTER_SUNAT.md`.
