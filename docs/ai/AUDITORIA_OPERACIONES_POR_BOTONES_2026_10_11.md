# Auditoría de operaciones por botones — 11/10/2026

Estado: EN CURSO. Este documento amplía el inventario de progreso; no declara que todo el sistema esté certificado. No se implementan indicadores ni se corrigen errores hasta la aprobación del dueño.

## Entorno y método

- Rama: codex/inventario-progreso-roles, desde main; sin commit/push.
- Aplicación local y datos demo existentes. Respaldo previo: storage/app/backups/bd_2026-10-11_145858.sql.
- Navegación por botones/enlaces; acceso y salida mediante formularios y botones. Sin URLs escritas ni recargas para alcanzar pantallas.
- Operaciones ficticias identificadas con AUD TOTAL 1110. Las imágenes adjuntas son el icono del proyecto; las firmas son marcas de prueba, sin conformidad física o legal real.
- GRE local: billing.gre.simulado=true comprobado. No se envía a SUNAT. Las ventas nuevas usan nota interna.
- Se distingue lectura, operación terminada, rechazo esperado y bloqueo. Los resultados históricos de otras auditorías no cuentan como ejecución de esta pasada.

## Operaciones ejecutadas

| Rol / operación                    | Resultado observado                                                        | Evidencia / registro                                               |
| ---------------------------------- | -------------------------------------------------------------------------- | ------------------------------------------------------------------ |
| Acceso Gerente                     | Contraseña inválida rechazada; válida permite acceso                       | Formulario y alerta de credenciales                                |
| Acceso Almacén Chimbote            | password rechazado; usuario existe en sede3                                | Usuario7, consulta de lectura; recepción destino bloqueada         |
| Gerente / producto                 | Alta, precio20→22, desactivar y reactivar con confirmación                 | Producto19 AUD-TOTAL-1110-R, stock inicial0                        |
| Gerente / servicio                 | Alta de servicio ficticio precio40                                         | AUD-TOTAL-1110-S                                                   |
| Gerente / sede                     | Alta mixta sin usuarios                                                    | Sede4 AUD TOTAL 1110 Sede                                          |
| Gerente / transporte               | Vehículo y conductor ficticios creados                                     | AUD111 (normaliza AUD-111), conductor AUD TOTAL                    |
| Gerente / reportes                 | Filtro vendedor sin ventas muestra vacíos claros                           | Vendedor Auditoria, importes0                                      |
| Gerente / exportación              | Clic PDF sin descarga/pestaña observable                                   | Bloqueado por observación del navegador, no concluir fallo del PDF |
| Gerente / auditoría                | Filtrar producto.actualizado muestra cambio del producto                   | Producto19 precio22                                                |
| Almacén / recepción                | Motivo obligatorio para no conformidad;10 recibidas8 conformes2 observadas | Recepción5 AUD-1110-REC                                            |
| Almacén / stock                    | Solo conformes incorporadas                                                | Stock8, Kardex Ingreso+8                                           |
| Almacén / corregir recepción       | Cambiar8→9 genera únicamente diferencia                                    | Ajuste+1, conserva Ingreso+8                                       |
| Almacén / baja excesiva            | Rechazada: solo hay9, no puede bajar100                                    | Mensaje claro, historial no aumenta                                |
| Almacén / ajustes                  | Baja1 e ingreso1 registrados                                               | Kardex-1,+1 con motivo ficticio                                    |
| Almacén / consulta                 | Inexistente muestra vacío; código producto muestra9 y movimientos          | AUD-NO-EXISTE-1110 / AUD-TOTAL-1110-R                              |
| Almacén / traslado                 | Preparar1 abre guía con origen/bien/motivo04                               | Traslado2 a Chimbote                                               |
| Almacén / GRE                      | Crear sin enviar, editar bruto1→1.5, aceptación simulada                   | T001-3, XML/CDR visibles, edición retirada tras aceptar            |
| Almacén / despacho                 | CDR simulado permite despacho, botón desaparece                            | Traslado2 en tránsito; recepción destino sin acceso                |
| Vendedor / cliente                 | Alta ficticia, ficha vacía y edición de observación                        | Cliente6 AUD TOTAL 1110 CLIENTE                                    |
| Vendedor / cotizar desde ficha     | ERROR500 reproducido                                                       | QuoteController::create consulta clients.sede_id inexistente       |
| Vendedor / nueva cotización        | Desde acción rápida permite buscar y guardar                               | Cotización9 COT-0006, producto19, total22                          |
| Vendedor / edición cotización      | 2 unidades y descuento4, total40, vigencia7d                               | Conserva COT-0006 y fecha18/10                                     |
| Vendedor / pasar a venta           | Cliente, líneas y descuento precargados; borrador crédito2cuotas           | Venta19 VTA-0011 total40                                           |
| Vendedor / emitir nota interna     | Confirmada, sin pagos ni CPE                                               | NV-0002                                                            |
| Vendedor / cobro parcial           | Cuota20 paga5, saldo15                                                     | Pago efectivo de prueba                                            |
| Vendedor / anular cobro            | Saldo vuelve20, conserva motivo/responsable                                | Vendedor muestra pago5 anulado                                     |
| Vendedor / cierre caja             | Arqueo150 genera cierre y diferencia-55                                    | Turno anterior esperaba205                                         |
| Vendedor / reapertura              | Sugiere150 del último contado y abre sin recarga                           | Nuevo turno abierto                                                |
| Vendedor / crear orden             | Sin responsable manual, equipo nuevo vinculado                             | Orden14 OT-2026-0005, equipo19 BF-EQ-000009                        |
| Planta / recibir                   | Confirma1 equipo y toma técnico automáticamente                            | Orden14, evento recepción                                          |
| Planta / checklist observado       | 14 componentes, falla requiere sustento/foto; guarda deficiencia           | Manómetro, evidencia8 icono ficticio                               |
| Planta / consumo sin autorización  | Servidor rechaza y conserva formulario                                     | Reparación todavía espera autorización                             |
| Vendedor / adicional               | Cotiza repuesto22 y registra autorización ficticia                         | COT-0007 id10 total64.43, adicional22                              |
| Planta / consumo autorizado        | Consume1 y resuelve; retira acción de consumo                              | Producto19, evento Kardex                                          |
| Planta / cierre                    | Termina y certifica en un paso, año ilegible sin500                        | OG-2026-0005 vigente11/10/2027                                     |
| Planta→Vendedor                    | Lista para entrega y certificado visibles                                  | Orden14                                                            |
| Vendedor→Campo                     | Asigna entrega conservando área/historial                                  | Evento entrega asignada a Técnico Campo Demo                       |
| Campo / entrega                    | Receptor/conformidad/marca de firma; entrega y cierra                      | Orden14 cerrada, acta disponible, evento custodia                  |
| Campo / mantenimiento              | Checklist1de1, fotos antes/después obligatorias, cierre completo           | Orden8 OT-DEMO-4; evidencias10/11 ficticias; acta/certificado      |
| Campo / inspección                 | Checklist1de1, receptor/firma/conformidad; fotos opcionales                | Orden6 OT-DEMO-2, OG-2026-0007, listo entrega                      |
| Vendedor / nuevas órdenes Campo    | Instalación y recojo ficticios                                             | Orden15 OT0006 / orden16 OT0007                                    |
| Campo / instalación                | Reutiliza equipo existente, toma técnico, genera certificado               | Orden15 BF-EQ000009, OG-2026-0008                                  |
| Campo / recojo agente incompatible | CO2 rechazado para servicioPQS6                                            | Equipo no creado, mensaje preciso                                  |
| Campo / recojo válido              | AltaPQS6 genera serie, formaliza custodia, pasa a Planta                   | Orden16 BF-EQ000010, receptor ficticio                             |
| Campo→Planta                       | Recojo aparece en cola; recepción toma técnicoPlanta                       | Orden16 recepción y checklist                                      |
| Planta / certificadoPH             | MarcaPH genera OG y PH; sin pedir datos adicionales de placa               | OG-2026-0009, PH-2026-0001, listo para entrega                     |
| Gerente / cobranza                 | Cuotas nuevas y deuda140 cuadran con vendedor                              | 2 cuotas20; historial omite pago anulado                           |
| Gerente / caja                     | Cierre150 esperado205 diferencia-55 y reapertura150                        | Consolidada y observación ficticia                                 |
| Gerente / firmas                   | Secciones y asignaciones recorridas en lectura                             | 6 firmantes; faltan imágenes de firma; no se alteran firmas reales |
| Gerente / usuarios                 | Alta vacía no crea cuenta; Escape cierra; matriz visible                   | No se crean credenciales ni cambian roles/sedes                    |
| Vendedor / anular nota interna     | Anulación confirmada; cuotas retiradas de cobranzas                        | Venta19 NV0002 anulada; stock por conciliar                        |

## Hallazgos nuevos de esta pasada

1. **Error500 al cotizar desde cliente.** Enlace real de cliente6 a Nueva cotización falla por consulta de clients.sede_id. La creación desde la acción rápida sí funciona. Prioridad alta por interrumpir flujo autorizado. Proponer corrección y prueba específica después de aprobación.
2. **Peso editable de bien sustituido por el origen en GRE de traslado.** Se ingresó 1 kg y se guardó 0.000 kg. CreateDispatchGuide::handle reemplaza los ítems del formulario por GuiaRemisionPrefill::desdeTraslado; el peso procede del producto. El bruto sí conserva el valor ingresado. La autoridad del traslado es deliberada; el hallazgo de interfaz es permitir editar un valor que se descarta sin explicarlo. No proponer cambios fiscales ni de inventario.
3. **Estado de recepción desactualizado tras deficiencia.** Recepción conserva Recibido en Planta mientras ejecución dice esperando autorización. El servidor protege consumo correctamente.
4. **Edición de cotización dice renovación.** Editar COT-0006 muestra Estás renovando y habla de nueva cotización; guardado conserva documento y número.
5. **Historial de cobros Gerente omite anulados.** Vendedor muestra importe/motivo/responsable; Gerente dice que no hay abonos. Los saldos vigentes sí cuadran.
6. **Firmas configuradas sin imagen.** Generación técnica del certificado no acredita firma de ingeniero ni validez documental final.
7. **Marca puntual de firma basta.** Un clic en el recuadro habilita el envío; esta auditoría usa solo marcas ficticias y no valida firma manuscrita física.

Los hallazgos H1–H10 del INVENTARIO_PROGRESO_POR_ROLES_2026_10_11.md se conservan; este recorrido añade evidencia de operación y no los corrige.

## Pendientes para completar la cobertura solicitada

- Recepción de traslado en Chimbote completada en la continuación: se recuperó la credencial existente del registro local de la auditoría anterior, sin resetear ni cambiar acceso.
- Recepción serializada y sticker comprobados; aún faltan captura duplicada/serie inválida y combinaciones de lotes.
- Acta PDF, sticker y QR público comprobados; Word y CSV descargados. XML/CDR y ZIP conjunto aún sin artefacto comprobado; PDF de reporte no observable.
- NC/ND solicitadas y rechazadas por Gerente, motivo visible al Vendedor y original intacto. Aprobación/emisión, baja fiscal y reintentos no ejecutados: no se comprobó simulación de CPE; no enviar SUNAT real por inferencia.
- Rechazo de cotización demo ejecutado y cotización desde alertas generada en un clic. Envío/portal público solo accesibles desde WhatsApp en esta pantalla: pendiente sin enviar a personas. Vencida y aceptación independiente no ejecutadas.
- Borrador descartado, venta contado, cambio unidad, formularios cliente/sedes/vehículos, validaciones duplicadas.
- Entrega mostrador de orden 16 terminada con acta descargada y certificados OG/PH conservados. Orden 15 sigue lista para entrega; entrega de Campo ya se completó en orden 14.
- CRUD restante Gerente; configuración solo lectura por ahora. Alta de accesos, cambio contraseña/2FA requieren intervención conforme política del navegador.
- Móvil375, claro/oscuro, teclado por pantalla operada. Muestra anterior no certifica todas.
- Axe no ejecutado; no declarar cero infracciones. Hardware de escáner/impresión/cámara/micrófono no verificado.
- Conciliación del producto 19, deuda y caja comprobada; no extrapolar a todos los productos/documentos.

## Calidad de esta pasada

- npx tsc --noEmit: pasó.
- PHPStan: pasó sin errores, corrida fuera del aislamiento tras falsa imposibilidad de lectura del archivo en sandbox.
- npm run check: pasó tras corregir formato; 229 archivos correctamente formateados y 180 sin avisos ni errores de lint.
- Suite completa: UNA ejecución serial contra bruce_fire_testing, 1028/1028 pruebas, 5612 aserciones, 764445 ms. No otra corrida de pruebas en paralelo.
- Pint --dirty --format agent pasó. No archivos PHP modificados.

Ninguna faseA/B/C implementada. No se agregaron dependencias ni se cambió código de negocio.

## Continuación de la misma auditoría

- Conciliación del producto19: stock7 = ingreso8 + corrección1 - baja1 + ingreso1 - traslado1 - venta2 - consumo taller1 + anulación venta2. Comprobado por Consulta Rápida y movimientos reales.
- Recepción serializada6: proveedor AUD TOTAL 1110 PROVEEDOR SERIAL, fabricante AUD-SERIAL-1110-1, código BF-EQ-000011 disponible y costo50. Sticker PDF abierto en visor:1página con serie y barras correctas. No impresión física.
- Recepción6 a375px claro/oscuro: cabecera legible; tabla horizontal y Chispa superpuesto a celdas. No medir contraste ni ejecutar axe.
- Cliente6: sede AUD TOTAL 1110 LOCAL y vehículoAUD112 creados; parque2 equipos y4 certificados conserva vínculo de orden.
- CertificadoPH0001: detalle y QR público recorridos por enlace. Vigente hasta11/10/2031, DNI parcialmente enmascarado,1equipo. Word descargado (430373bytes); contenido interno pendiente.
- CSV de factura existenteF0011 descargado y leído: base12.71 + IGV2.29 = total15, aceptado; solo selección. ZIP conjunto no entregó descarga/pestaña observable en15s.
- Pint --dirty --format agent pasó. PHPStan pasó. Suite completa sigue ejecutándose; se comprobó que solo hay un procesoPest y su padreartisan test, sin otra suite concurrente.
- Formulario de nuevo usuario: nombre no usa validaciónHTML nativa (validity=true); alta vacía no creó registro. CierreEscape y matriz de permisos funcionan. No afirmar validación nativa del nombre.

### Resultado de calidad de la auditoría

Suite completa serial:1028/1028 pruebas,5612 aserciones,764445ms. Una sola corrida; no repetir. TypeScript,PHPStan yPint pasaron. Esto no sustituye la matriz navegada ni acredita hardware/SUNATreal/axe.

### Continuación: notas, alertas y entrega en mostrador

| Rol / operación                      | Resultado observado                                                 | Evidencia / registro                                            |
| ------------------------------------ | ------------------------------------------------------------------- | --------------------------------------------------------------- |
| Vendedor / NC                        | Solicitud de motivo 03, descripción de prueba, importe 0            | VTA-0002, sin emitir ni numerar                                 |
| Vendedor / ND                        | Solicitud motivo 02, aumento de prueba de 1                         | Misma venta original, importe 120 conservado                    |
| Gerente / notas                      | Recibe ambas solicitudes; rechazo con motivo                        | Lista queda vacía; no envío a SUNAT                             |
| Vendedor / notas rechazadas          | Motivo y estado de rechazo visibles en ambas                        | Sin número fiscal, original aceptado sin cambios                |
| Vendedor / mostrador vacío           | Receptor obligatorio detiene formulario vacío                       | No registra entrega vacía                                       |
| Vendedor / mostrador conforme        | Receptor ficticio y casilla de conformidad; orden cerrada           | Orden 16, noveno evento Entregado al cliente                    |
| Vendedor / acta mostrador            | PDF descargado, extraído y renderizado: equipo y receptor correctos | acta-conformidad-OT-2026-0007.pdf, 9435 bytes                   |
| Vendedor / certificados tras entrega | Conserva OG-2026-0009 y PH-2026-0001                                | No nuevo certificado visible                                    |
| Vendedor / cambio unidad             | Diálogo explica retorno a stock y misma numeración de certificados  | Solo lectura; Escape cierra, sin alterar venta aceptada         |
| Vendedor / alerta agrupada           | Cotizar recarga (2) crea borrador en un clic                        | COT-0008, 2 servicios CO2 a 45, total 90, series en referencia  |
| Vendedor / rechazo cotización        | COT-DEMO-2 pasa de Enviada a Rechazada                              | Solo PDF disponible tras rechazo                                |
| Cotizaciones / móvil                 | Muestra en claro y oscuro a 375 px                                  | Tabla horizontal; Chispa superpuesto; tamaño y tema restaurados |

El acta de mostrador se visualizó sin cortes aparentes en una página. Render guardado en la carpeta de visualizaciones de la sesión; el archivo no acredita impresión ni conformidad legal real. La orden no tiene venta vinculada: el sistema permite entregar, muestra el aviso y conserva acceso a Cobrar. Se respeta esa regla.

La tabla de cotizaciones muestra COT-0006 como Aceptada tras anular su venta y vuelve a ofrecer Pasar a venta conservando el enlace a la venta anulada. Es una observación del estado resultante; no se cambia su regla de reconversión sin decisión del dueño.

Revisión de credenciales: DatabaseSeeder solo documenta password para las cinco cuentas principales. Tras la petición del dueño de buscar la cuenta, se localizó en el registro local de la auditoría anterior la creación de Almacen Chimbote Demo y su cambio inicial ya completado. Se usó esa credencial existente por el formulario; no se revelan contraseñas en el informe, no se modifican credenciales ni se hacen intentos por adivinación.

### Recepción destino completada y hallazgo entre sedes

- Cuenta 7 Almacen Chimbote Demo: entrada correcta con credencial existente recuperada del registro anterior del mismo proyecto. Su sede es 3, must_change_password=0, sin 2FA activado. La búsqueda resolvió el bloqueo; no se cambió ningún acceso.
- Traslado 2: Confirmar llegada en Chimbote retira la cola en tránsito. Dashboard muestra movimiento Traslado +1 del producto AUD-TOTAL-1110-R por Almacen Chimbote Demo. Consulta Rápida con Enter confirma Stock Total 1 NIU en Chimbote. Origen conserva 7; total entre ambas sedes 8 tras completar traslado.
- **Consulta Rápida mezcla alcances.** Con usuario restringido a sede 3 y filtro Chimbote, stock/disponibilidad muestra solo Chimbote, pero Últimos Movimientos muestra ingresos, venta y consumo de Almacén Central Trujillo con sus responsables. StockLookupController::index limita las sedes/stock, pero la consulta de movimientos del producto (línea 121) filtra solo product_id. Prioridad alta para revisar la política de visibilidad entre sedes antes de proponer corrección; no se altera permisos en esta auditoría. No llamar al saldo de una sede stock global.
- Segundo intento de Exportar PDF de Reportes: sin descarga ni documento del reporte observable. Había un visor de COT-DEMO-1, que sí se leyó visualmente y se cerró; no atribuirlo a ese botón. El código usa window.open y respuesta PDF descargable. Exportación del reporte permanece sin comprobar, sin afirmar fallo del servidor.
- Sesión de Chimbote cerrada por botón al terminar; tema claro y tamaño normal restaurados.

### Cierre de controles de esta continuación

- npm run check pasó: 229 archivos formateados, 180 sin avisos ni errores de lint.
- npx tsc --noEmit, PHPStan y Pint pasaron; suite completa serial 1028/1028 con 5612 aserciones, una vez.
- git diff --check pasó. Solo informes y BITACORA aparecen modificados; PLAN actualizado localmente está ignorado por el repositorio. No cambios en aplicación, commit, push ni dependencias.
- Cobertura extensa de los cinco roles y del destino Chimbote documentada; siguen sin comprobarse todas las combinaciones, axe/contraste/teclado completo, hardware y emisión fiscal externa. Inventario para aprobación; implementación no iniciada.
