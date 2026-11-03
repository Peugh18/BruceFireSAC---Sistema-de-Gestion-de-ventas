# Plan: orden de servicio, certificados y clientes (2026-11-02)

Pedido del usuario, con lo que ya existe y se reutiliza.

## Fase 1 — Aprobado y claro (hecho 2026-11-03)
| # | Cambio | Se reutiliza |
|---|---|---|
| 1 | Deficiencias del vendedor con el dibujo por partes; bloque propio y adaptable; aviso si aún no hay extintores | `ExtintorPorPartes`, `DeficienciaSuelta` |
| 2 | Ficha del extintor sin "Próxima PH" ni "Próxima recarga": se calculan (PH +5 años, recarga +1 año) y se muestran en una línea con "Editar" | `FechasDelEquipo` |
| 3 | Año de fabricación hasta el año actual en todo el sistema | `FechasDelEquipo::reglas` |
| 4 | Stickers: fuera de planta; los imprime el vendedor desde la orden | `printStickers` de recepción |
| 5 | Antes de emitir, avisar lo que falta según el documento (boleta > S/ 700 nombre y DNI; certificado, dirección) | `ValidarComprobanteCliente` |
| 6 | Certificado sin "Vigencia" (repite el vencimiento), sin "Orden de servicio" y sin "Código BF-EQ" | `CertificateDocumentData` |
| 7 | XML solo cuando se envió a SUNAT y CDR solo cuando existe | `ArchivosNota` |
| 8 | Nuevo y recarga en el mismo certificado: columna "Atención" al final de la tabla, solo si hay mezcla | `TipoAtencionExtintor` |

## Fase 2 — Certificados por destino (hecho 2026-11-03)
- Local: operatividad + capacitación, las dos salen solas. La capacitación usa curso y horas de la empresa; participantes opcionales con "Agregar personas" y los modos con fotos ya existentes.
- Vehículo: operatividad + prueba hidrostática; la placa va como referencia.
- Servicios con certificado propio (`Service.certificate_type_id`): la venta muestra cuáles faltan emitir.

## Fase 3 — Clientes y locales (hecho 2026-11-03)
- Razón social y nombre comercial separados en el alta.
- Varias direcciones (locales) al crear el cliente.
- La venta elige el local; la factura siempre usa razón social y dirección fiscal; el certificado elige razón social o nombre comercial y usa la dirección del local.

## Fase 4 — Historial de compras del cliente (hecho 2026-11-03)
- Hoy: ficha del cliente → ventas (fecha, comprobante, total, saldo).
- Propuesta: "Qué compró" por producto o servicio, con fecha, y exportable.

## Pendiente de investigar
- Cómo muestran otros sistemas nuevo y recarga en un mismo certificado.
- Reglas del contador: dirección del adquirente en factura.
