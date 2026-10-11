# Desglose de competidores y mejoras concretas para BRUCE FIRE

Fecha: 10/10/2026. Comparación por proveedor solicitada por el dueño. Complementa COMPARACION_SOFTWARE_EXTINTORES_2026_10_10.md; contiene propuestas, no implementaciones.

## Cómo leerlo

“Tenemos” significa observado en la auditoría o contrastado en el código; no significa que cada estado/botón haya sido validado. “Candidata nueva” requiere definir alcance y su REQ/historia/matriz. La demo de FireTrack es reducida y simulada; sus 12 pasos se recorrieron en navegador. Los demás proveedores se contrastaron con manuales y páginas oficiales. No se da por probada una promesa comercial de rendimiento ni se afirma que un competidor carezca de una función porque no la publica.

## 1. FireTrack: experiencia del inspector y entrega del resultado

**Lo que destaca:** en la demo el inspector atraviesa orden, checklist, fotos, firma e informe; administración revisa el resultado y el cliente consulta documentación. La página de la app anuncia captura sin señal y sincronización posterior, que no se probó en producción. [Demo](https://firetrack.pe/demo), [app](https://firetrack.pe/app-inspeccion-extintores).

| Área                   | FireTrack                                                         | BRUCE FIRE                                                                                   | Mejora concreta                                                                                                                                          |
| ---------------------- | ----------------------------------------------------------------- | -------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Inspección en celular  | Demo dividida en cinco pasos.                                     | Mantenimiento ya tiene checklist, fotos antes/después y conformidad.                         | Mostrar avance y un resumen del equipo actual; facilitar volver a corregir sin perder datos. No exigir una pantalla extra para cada acción pequeña.      |
| Identificación         | Página describe escaneo e historial.                              | BF-EQ, vinculación/alta de equipo y ficha del cliente existen.                               | Tras escanear, mostrar cliente, agente/capacidad, ubicación y última atención antes de continuar.                                                        |
| Evidencia y documento  | Ejemplo público reúne resultado, checklist, fotos y firma.        | Fotos/firma existen; certificado con snapshot y QR público.                                  | Revisar enlaces entre evidencia, equipo y orden; mostrar qué falta para emitir y separar informe de inspección de certificado.                           |
| Seguimiento            | Demo de administración muestra servicios y próximos vencimientos. | Alertas por empresa/plazo y oferta de recarga ya existen.                                    | Destacar siguiente acción y seguimiento comercial/operativo, en lugar de crear otro tablero paralelo.                                                    |
| Sin conexión y cliente | Páginas anuncian offline y portal.                                | Sincronización offline y portal privado completo no se identificaron en el alcance revisado. | Evaluar aparte: almacenamiento local/reintentos/conflictos y permisos del cliente. El QR público actual ya resuelve una parte de la consulta documental. |

**Ejemplo de propuesta:** una visita de 12 equipos muestra “8 revisados, 2 observados, 4 pendientes de revisar”. Revisados y observados son dimensiones distintas: los dos observados están incluidos en los ocho revisados. Al finalizar, se enumeran equipos sin checklist o evidencia requerida; se mantienen resultados individuales, sin aprobar a todos por defecto.

**Qué adoptar primero:** claridad de campo y revisión final. Offline/portal son ampliaciones posteriores, no cambios pequeños de apariencia.

## 2. Inspect Point: identidad técnica, reparaciones y equipos prestados

**Lo que destaca:** mantiene configuraciones de tipos, fabricantes y capacidades; tiene proyección de próximas atenciones y un circuito documentado de intercambio/préstamo temporal. [Configuración](https://support.inspectpoint.com/hc/en-us/articles/42233310251156-Equipment-Devices-Settings-Overview), [proyección](https://support.inspectpoint.com/hc/en-us/articles/23055471654164-Using-the-Extinguisher-Projection-Report), [préstamos](https://support.inspectpoint.com/hc/en-us/articles/6574947981332-Fire-Extinguisher-Swap-Loaner).

| Área                  | Inspect Point                                                                                               | BRUCE FIRE                                                                                          | Mejora concreta                                                                                                                                                    |
| --------------------- | ----------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Datos técnicos        | Configuración reutilizable de tipos, fabricantes y capacidades.                                             | Agente tipificado; marca/capacidad se guardan como textos.                                          | Ofrecer valores habituales y entrada válida excepcional; evitar variantes involuntarias de un mismo nombre. Una biblioteca persistente de fabricantes sería nueva. |
| Historia del hallazgo | Su plataforma describe deficiencia vinculada hasta resolución. [Plataforma](https://www.inspectpoint.com/). | Deficiencia, equipo, cotización, autorización y eventos enlazados.                                  | Ficha del hallazgo con foto, precio aprobado, repuesto usado y resultado; enlaces hacia cada documento existente.                                                  |
| Próxima atención      | Informe filtra equipos por cliente/edificio, fecha y programación.                                          | Próxima recarga/PH, alertas por empresa y contacto comercial.                                       | Distinguir requiere atención, cotizado y programado; conservar la fecha técnica y filtrar por local del cliente cuando su relación esté resuelta.                  |
| Préstamo              | Identifica temporalmente los equipos prestados.                                                             | Cambio de unidad vendida y custodia existen; no se identificó un circuito de préstamos equivalente. | Si se presta durante recarga, registrar retirado/sustituto, propiedad, responsable y devolución, sin tratarlo como venta.                                          |

**Ejemplo de propuesta:** el cliente deja BF-EQ-001 para recarga y recibe PR-008 temporalmente. La ficha debe mostrar ambas identidades y la fecha prevista de devolución; al retornar, queda constancia de la devolución del préstamo y la entrega del original. Es un circuito nuevo a definir, no una extensión automática del botón de corregir venta.

**Qué adoptar primero:** consistencia de datos e historia del hallazgo. Préstamos solo si esa operación ocurre realmente en BRUCE FIRE.

## 3. Uptick: catálogo comercial, activos y cotización desde el defecto

**Lo que destaca:** distingue productos con precio de activos ubicados en propiedades; permite alta móvil y sugerencias de productos por defecto. Documenta también citas recurrentes. [Catálogo](https://support.uptickhq.com/en/articles/6728409-create-and-manage-products-and-pricing), [alta de activo](https://support.uptickhq.com/en/articles/7225657-mobile-app-add-or-update-an-asset), [defectos](https://support.uptickhq.com/en/articles/6728207-understanding-defect-quoting-products-and-replacement-options), [agenda](https://support.uptickhq.com/en/articles/7936460-forecast-appointments-for-routine-servicing).

| Área                     | Uptick                                                | BRUCE FIRE                                                                                         | Mejora concreta                                                                                                                        |
| ------------------------ | ----------------------------------------------------- | -------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------- |
| Catálogo y equipo        | Catálogo comercial separado de activo.                | Product, InventoryUnit, Equipment y Service ya están separados.                                    | Explicar la elección y el resultado del registro; no fusionar entidades ni crear otro maestro.                                         |
| Alta del equipo          | Tipo/variante, ubicación y fechas desde la tarea.     | Alta rápida en recepción/mantenimiento y vinculación existente.                                    | Traer cliente/orden automáticamente, mostrar BF-EQ y serie de fabricante como conceptos diferentes y pedir solo los datos pertinentes. |
| Defecto y producto       | Productos sugeridos configurados por tipo de defecto. | CotizarDeficiencia ya vincula Product/Service con QuoteItem y la deficiencia.                      | Sugerencias compatibles revisables, stock/precio visibles y datos del equipo en contexto. El motor de sugerencias sí sería nuevo.      |
| Mantenimiento recurrente | Rutinas y citas futuras.                              | Alertas por empresa/plazo, contacto y creación de cotización de recarga ya existen.                | Mejorar continuidad cotizado→aceptado→programado; contratos/rutinas automáticas requieren alcance nuevo.                               |
| Fotos en catálogo        | Manual documenta imagen del producto en selectores.   | Fotos de equipo/evidencia existen; ficha de Product examinada no tiene la misma función comercial. | Considerar miniatura del repuesto para elegir correctamente; no confundir foto del modelo con evidencia de un equipo específico.       |

**Ejemplo de propuesta:** “manguera deteriorada” abre alternativas compatibles del catálogo con existencias. El vendedor confirma producto, cantidad y precio; la cotización mantiene el vínculo con la deficiencia. Se conserva la autorización actual antes de consumir materiales.

**Qué adoptar primero:** registro comprensible y selector contextual de adicionales. No copiar una opción de reparación inmediata que cambie nuestra autorización vigente.

## 4. ServiceTrade: continuidad del servicio y relación con el cliente

**Lo que destaca:** su solución sectorial describe programación recurrente, conexión de operaciones/ventas y un portal con informes, deficiencias, cotizaciones y facturas, con aprobaciones y pagos en línea. Son capacidades anunciadas públicamente; no se accedió a una cuenta operativa. [Solución de protección contra incendios](https://servicetrade.com/industries/fire-protection/).

| Área                             | ServiceTrade                                                | BRUCE FIRE                                                                | Mejora concreta                                                                                                           |
| -------------------------------- | ----------------------------------------------------------- | ------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------- |
| Reparación después de inspección | Continuidad entre hallazgo, cotización y servicio.          | Deficiencias y cotizaciones ya relacionadas; orden/venta/eventos existen. | Mostrar “qué falta y quién continúa” en una ficha conectada, usando relaciones actuales.                                  |
| Atención recurrente              | Programación por frecuencia de servicio.                    | Próximas fechas, alertas y contactos internos.                            | Lista operativa de trabajos aceptados por programar; evitar confundir haber enviado/cotizado con una visita agendada.     |
| Consulta del cliente             | Portal de documentos y solicitudes.                         | Consulta pública del certificado por QR; historial completo es interno.   | Primera mejora: entrega documental clara. Portal privado sería un producto adicional con acceso solo a datos del cliente. |
| Aprobación/pago externo          | Página describe aprobación de cotizaciones y pago en línea. | Autorización registrada por vendedor y pagos/caja internos.               | Mejorar evidencia y claridad de la aprobación existente. Autoservicio externo/pasarela son candidatas separadas.          |

**Ejemplo de propuesta:** la ficha de OT muestra “Adicional: pendiente de autorización · Trabajo: detenido · Responsable: vendedor”, y permite abrir la cotización del mismo hallazgo. Al aprobar, aparece la acción disponible para planta. Eso aporta continuidad aun sin construir portal.

**Qué adoptar primero:** seguimiento operativo y comercial visible; luego evaluar autoservicio del cliente por una necesidad real.

## 5. Simpro: conexión técnica/comercial y costo del trabajo

**Lo que destaca:** la solución sectorial describe presupuesto, programación, trabajo móvil, materiales, costos y facturación conectados; también compara costos estimados y reales. No se probó su cálculo en una cuenta operativa. [Software de protección contra incendios](https://www.simprogroup.com/industries/fire-protection-software).

| Área                    | Simpro                                         | BRUCE FIRE                                                                                | Mejora concreta                                                                                                                                        |
| ----------------------- | ---------------------------------------------- | ----------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Presupuesto hasta cobro | Trabajo y datos comerciales conectados.        | Cotización/orden/venta/cuotas/pagos enlazados.                                            | Cabecera de documentos relacionados e importes; explicar registrar venta, emitir y cobrar por separado.                                                |
| Materiales/costo        | Seguimiento de materiales y costo del trabajo. | Consumo de repuestos, costo promedio y valorización de stock existen.                     | Hacer visible el consumo autorizado por orden; revisar qué costos quedan registrados al consumir antes de diseñar un margen.                           |
| Rentabilidad            | Página anuncia comparación estimado/real.      | El reporte examinado valora inventario; eso no demuestra utilidad completa de cada orden. | Candidata: ingresos menos costos efectivamente registrados. Si faltan mano de obra, traslados o indirectos, mostrar cálculo parcial y datos faltantes. |
| Programación            | Asignación de técnicos y recursos.             | Órdenes y asignaciones por rol/sede existen.                                              | Agenda de trabajos/pendientes y alertas de conflicto, si se valida la necesidad; optimización de rutas y capacidad es una ampliación mayor.            |

**Ejemplo de propuesta:** para una orden, mostrar servicio vendido, adicionales aprobados, repuestos usados y saldo pendiente. Solo después, si hay costos históricos suficientes, comparar presupuesto y ejecución. El costo promedio actual del catálogo no sustituye automáticamente el costo que tuvo el material en una operación pasada.

**Qué adoptar primero:** resumen técnico/comercial; rentabilidad completa necesita datos, no únicamente un porcentaje en un dashboard.

## 6. Qué haría en nuestro sistema, en orden

| Orden   | Mejora                                                                                        | Reutiliza                                     | Cambia experiencia o agrega función                                 |
| ------- | --------------------------------------------------------------------------------------------- | --------------------------------------------- | ------------------------------------------------------------------- |
| 1       | Alta claramente separada: artículo, unidad nueva y equipo del cliente.                        | Catálogo, recepción y Equipment existentes.   | UX; bibliotecas adicionales/importación se evalúan aparte.          |
| 2       | Ficha del extintor: identificación, ubicación, historia, deficiencias y documentos enlazados. | Relaciones ya guardadas.                      | Principalmente UX; validar alcance de la ubicación y permisos.      |
| 3       | Recorrido de campo con avance, faltantes y revisión final por equipo.                         | Checklist, evidencias y firma existentes.     | UX y pruebas del flujo.                                             |
| 4       | Deficiencia de principio a fin, con sugerencias revisables.                                   | Cotización/autorización/consumo existentes.   | UX; sugerencias configurables son función nueva.                    |
| 5       | Próximas atenciones con seguimiento cotizado/aceptado/programado.                             | Alertas por empresa y cotización desde aviso. | UX; rutinas/contratos automáticos son ampliación.                   |
| 6       | Resumen por orden de venta, autorización, materiales y saldo.                                 | Eventos, venta, cuotas y Kardex.              | UX; margen total requiere definir/registrar más costos.             |
| Después | Préstamos, offline, portal privado y pagos externos.                                          | Parcialmente componentes existentes.          | Nuevos circuitos que necesitan alcance, permisos y pruebas propios. |

Ninguno de esos cambios justifica sustituir nuestra facturación SUNAT, snapshots de certificado o reglas de custodia por formularios extranjeros. Completar los casos pendientes de autorización/consumo y entrega con acta acompaña las mejoras de interfaz.

## 7. Contraste local adicional de esta sesión

- AlertController::index devuelve alertas y empresas; offerRecharge crea cotización para equipos seleccionados; contactado registra seguimiento. La pantalla ya filtra por empresa/plazo. Se precisa así la comparación anterior: esas funciones no faltan.
- CotizarDeficiencia enlaza QuoteItem con deficiency_id, reutiliza/actualiza la línea, y registra el evento comercial. No proponer reconstruir el vínculo básico.
- ReportController valora stock con costo_promedio y señala artículos sin costo. No confundir valorización con cálculo completo de utilidad por servicio.
- MaintenanceController y su pantalla contienen checklist, evidencia y firma; Equipment contiene identificación y ubicación. La mejora recomendada es continuidad y claridad, no crear esas funciones otra vez.

Validación: revisión de fuentes oficiales, evidencia previa de demo y lecturas puntuales del código actual. Esta sesión no volvió a ejecutar recorridos de datos ni pruebas de aplicación porque su entregable es análisis/documentación. Las capacidades nuevas y los recorridos navegados aún pendientes están diferenciados.
