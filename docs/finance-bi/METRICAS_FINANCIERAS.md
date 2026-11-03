# Métricas financieras: una sola definición para todo el sistema

> Estado: **propuesta** (2026-10-28). Base: E-021 y la decisión aprobada del Dashboard (ERRORES.md → Decisiones aprobadas).
> Regla: cada cifra se calcula en **un solo servicio del backend** y todas las pantallas, reportes PDF, exportaciones y Chispa lo usan. Ninguna pantalla vuelve a sumar `sales.total` por su cuenta.

## 1. Situación actual (evidencia)

| Dónde                                                                     | Qué calcula                      | Problema                                                                      |
| ------------------------------------------------------------------------- | -------------------------------- | ----------------------------------------------------------------------------- |
| `Gerente/DashboardController::ventasNetas()` y `margenBruto()` (privados) | Ventas y margen del Gerente      | Sumaban `total` con IGV (E-021); los servicios entraban completos como margen |
| `Vendedor/DashboardController`                                            | Ventas del mes del vendedor      | Fórmula propia                                                                |
| `Vendedor/ClientController`                                               | Total comprado por el cliente    | Fórmula propia                                                                |
| `Services/Chispa/DatosDelMomento`                                         | Cifras que responde el asistente | Fórmula propia                                                                |
| `Services/Ml/HistorialCompras`                                            | Historial para la IA de recompra | Fórmula propia                                                                |
| Reporte mensual PDF                                                       | Repite el Dashboard              | Hereda el error                                                               |

## 2. Vocabulario (lo que significa cada palabra en el sistema)

| Término                       | Definición                                                            | Fuente del dato                                                               |
| ----------------------------- | --------------------------------------------------------------------- | ----------------------------------------------------------------------------- |
| **Venta registrada**          | Venta confirmada (no borrador ni anulada) en el periodo, por su fecha | `sales` (estado `confirmada`)                                                 |
| **Valor de venta (sin IGV)**  | Base imponible + operaciones exoneradas o inafectas, menos descuentos | `sale_items` / `sales.subtotal`                                               |
| **IGV**                       | Impuesto de las operaciones gravadas                                  | `sales.igv`                                                                   |
| **Total facturado (con IGV)** | Importe de los comprobantes emitidos                                  | `electronic_documents` aceptados, menos notas de crédito, más notas de débito |
| **Cobrado**                   | Dinero recibido en el periodo, sin detracción                         | `sale_payments` menos `sale_refunds` y anulados                               |
| **Por cobrar**                | Saldo de cuotas pendientes a una fecha                                | `installments.saldo`                                                          |
| **Costo de productos**        | Costo promedio de compra sin IGV por unidad vendida                   | `products.costo_promedio`, Kardex                                             |
| **Costo de servicios**        | Repuestos usados en los servicios del periodo                         | Salidas de stock ligadas a órdenes                                            |
| **Margen bruto**              | Valor de venta sin IGV − costo, separado en Productos y Servicios     | Calculado                                                                     |
| **Ticket promedio**           | Valor de venta sin IGV ÷ número de ventas                             | Calculado                                                                     |

**Regla de etiquetas:** ninguna pantalla dice solo «Ventas». Dice «Ventas sin IGV» o «Total con IGV». La notas de venta todavía no formalizadas se muestran aparte, porque no son comprobante.

## 3. Diseño propuesto

- `app/Services/Metricas/MetricasDeVentas.php` (nombre a confirmar con las convenciones): métodos `valorDeVenta`, `igv`, `totalFacturado`, `cobrado`, `porCobrar`, `margen(productos|servicios)`, `ticketPromedio`, todos con filtros de periodo, sede y vendedor.
- Una prueba por definición, con un caso que mezcle factura, boleta, NC, ND, nota de venta, crédito y anulación.
- Migración por consumidor: primero el Dashboard del Gerente (E-021), luego Vendedor, reporte PDF, ficha del cliente, Chispa e IA.
- En el frontend, la tasa de IGV y el redondeo vienen del backend (`PrecioConIgv::TASA`), no de constantes `1.18` en cada página.

## 4. Pendiente

- Confirmar con la contadora el tratamiento de detracción, notas de débito por mora (inafectas) y operaciones exoneradas o inafectas en los KPI.
- Definir si el «mes» de una venta es la fecha de emisión del comprobante o la de la venta, cuando difieren (borradores emitidos otro día).
