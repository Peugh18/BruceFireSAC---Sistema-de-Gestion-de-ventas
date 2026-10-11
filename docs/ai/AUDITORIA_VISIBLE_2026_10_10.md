# Auditoría visible — 10/10/2026

Estado: en curso. Edge visible, cinco roles, datos exclusivamente en copia `bruce_fire_ui_audit_20261009`, aplicación en puerto 8001. No equivale a certificar todos los botones o todos los casos del sistema.

## Evidencia y cobertura actual

- 40 pantallas principales inventariadas, 436 controles visibles (botones, enlaces y campos; no son todos botones probados).
- 628 registros de acciones concretas en ledger, incluyendo repeticiones, verificaciones y navegaciones. No equivalen a ese número de botones distintos.
- 32 enlaces a fichas recorridos en una pasada adicional; estados HTTP en detail-checks.json.
- Ledger, inventario y detalles: storage/app/auditoria-visible-2026-10-10/. Capturas y tareas completas: C:/Users/migue/.codex/visualizations/2026/10/10/01a123fc-547e-7a40-ba9a-995b6007119a/visible-audit.
- Las excepciones task_NNN son fallos del guion de navegación: no se contabilizan automáticamente como defectos. Los errores de aplicación abajo fueron reproducidos y contrastados con logs/pruebas.

## Defectos corregidos y reproducidos

| Hallazgo                                                                                | Corrección                                                                                | Evidencia                                                             |
| --------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------- | --------------------------------------------------------------------- |
| Panel de vendedor y avisos 500 cuando un equipo no tiene producto de catálogo           | Nombre alternativo y relación nullable                                                    | AlertasEquipoSinCatalogoTest; navegador vuelve a 200                  |
| Checklist de campo 500: foto nula conservada, estado descartado por validación          | Validar y pasar el conjunto de ítems                                                      | ChecklistCampoFormularioTest; mantenimiento guardado desde formulario |
| Entrega mostraba todos conformes, PDF antes de existir y formulario después de entregar | Resultado real de checklist de la orden, PDF y formulario por evento; texto según cierre  | EntregaRevisionEquipoTest; órdenes 7/8 y actas abiertas               |
| Certificado emitido antes de cobrar ausente de la venta vinculada                       | Consulta incluye documentos de su orden; ajustes siguen usando solo certificados directos | VentaCertificadosOrdenTest; OG-2026-0005 visible en venta 10          |
| Mostrador ofrecía otra entrega después de entrega válida en campo                       | Reconocer el evento de entrega común, sin exigir un campo ausente en eventos de campo     | EntregaCampoMostradorTest; formulario desaparece y acta disponible    |
| Listado de traslados 500 al existir traslado pendiente                                  | Calificar columnas de latestOfMany de la guía                                             | InventoryTransferTest; regreso a lista 200                            |
| Cabecera duplicada en guías                                                             | Evitar segundo AppLayout global                                                           | Una sola cabecera observada                                           |
| Recarga se rotulaba Producto en nueva venta                                             | Usar clasificación existente de servicio                                                  | Cambio acotado de texto; sin alterar cobro/impuestos                  |

## Flujos efectivamente realizados en copia

- Taller: recepción física, 42 opciones de checklist, hallazgo observado con foto simulada, cotización adicional y autorización; cantidad de repuesto incorrecta rechazada y consumo correcto único; stock de válvula 11→10.
- Campo: tercer equipo revisado, 3/3 revisados y uno observado, fotos simuladas y firma dibujada, cierre de mantenimiento y emisión para conformes; entrega final cerrada con acta PDF. Inspección nueva con alta técnica, certificado, entrega sin cierre, cobro posterior por nota interna.
- Comercial/caja: cantidad +/−, nota interna S/ 45, anulación y reposición de pago S/25, arqueo ciego exacto S/55; no nueva emisión SUNAT.
- Administración: abrir/cancelar edición de productos/servicio/sedes; activar y restaurar estados; vehículo y conductor ficticios; usuario nuevo, cambios de rol/sede y contraseña inicial; permisos por rol y asignación comprobados.
- Almacén: sede de prueba con ubigeo, preparar traslado sin movimiento y despacho bloqueado sin CDR, formulario GRE y sus opciones; motivo de ajuste corto rechazado y movimientos +1/−1 compensatorios, saldo final válvula 10.
- Controles comunes: filtros, pestañas, temas, menú y Chispa con pregunta y nueva conversación. Corrección del registro anterior: las 57 selecciones de tipo de certificado en Firmas se guardan automáticamente en la copia; no fueron descartadas. No acreditan firma física ni emisión fiscal real.

## Pendientes y mejoras de coherencia

1. Recojo e instalación guardados en copia (órdenes 9/10). Relevo y entrega de taller 5 y cierre posterior de orden 8 ya comprobados; quedan combinaciones adicionales de sedes, responsables y estados. Inventario de controles no prueba todas las operaciones.
2. Relevo Planta→Campo corregido y navegado: asignación explícita en órdenes listas, responsable anterior conservado en evento y acceso exclusivo del elegido. Queda probar concurrencia simultánea real; la regresión usa un modelo capturado antes del cambio.
3. Las colas de campo comparten órdenes por departamento; contrastar intención de inspección/instalación/mantenimiento para que la clasificación se entienda y no ofrezca acciones equivocadas.
4. Guías y Mi cuenta ya conservan el layout de los cinco roles; textos de cuenta/llaves traducidos. GRE aún necesita origen visible, regreso al proceso, paginación y detalle de la guía. Véase anexo CODEPLEX_GRE_NOTAS_2026_10_10.md.
5. Certificados de orden son consultables desde venta; distinguir claramente preparación de nuevos certificados de revisión de los existentes, sin duplicar documentos emitidos.
6. GRE/CDR y respuestas SUNAT reales pendientes: no enviados. Cámara, micrófono, lector físico, impresión real, passkey y autenticador requieren dispositivo/configuración; fotos de esta auditoría son simuladas.
7. Configuración: mensaje de empresa guardado/restaurado, banco ficticio creado/eliminado, firmante ficticio creado/editado/asignado con imagen y desactivado; seis previsualizaciones. Esto no certifica todas las combinaciones de configuración, cierres multisedes o anulación fiscal. Mantener matriz antes de declarar auditoría total.

## Verificación de cambios

Regresiones rojo→verde: alertas, checklist, estado mostrado, relación certificado/venta, entrega campo→mostrador y listado de traslados. Grupos verdes de esta ejecución: alertas 16/62, checklist y campo 24/179, entrega/revisión 6/49, certificados 26/120, traslados/entregas 23/123 (pruebas/aserciones; grupos solapados, no sumar como suite única).

PHPStan 0, Pint, lint de archivos afectados, TypeScript y build pasan. La suite histórica de 957 no se repitió. Revisión independiente final sin P1/P2. No dependencias nuevas, commit ni push. Se preservan cambios anteriores y datos originales.

## Continuación de tarde: flujos y CODEPLEX (10/10)

- Recepción de inventario: conformes/observados con motivo; una unidad BF-EQ creada, ficha/sticker/Kardex y edición sin duplicación. Cambio de extintor y retorno al original: versiones OG 1/2, movimientos compensados y comprobante original conservado.
- Traslados ida/vuelta: stock conservado y confirmación por destino. Dos GRE se simularon solo en copia con marcador explícito; sus CDR son archivos ficticios, no respuestas SUNAT. No validar aceptación real a partir de ellos.
- Venta interna S/14 anulada: repuesto reintegrado, devolución en caja y arqueo cero exacto; borrador adicional descartado sin emisión. Recojo e instalación con actas/fotos/firma ficticias completados.
- Notas: solicitud de NC parcial y rechazo del gerente con motivo visibles. NC/ND/baja abren y cancelan, Escape y foco contenido. Aprobar y emitir no se probó con SUNAT real.
- UI: etiquetas CSV y métricas aclaradas (no cambia aritmética), certificados de orden consultables sin confundir ajuste, etiquetas únicas de firmantes y detalles del recojo. Perfil de cinco roles con menú propio.
- Nuevo fallo GRE desde equipo técnico sin producto: navegador 500 y regresión roja; fallback usa identificación técnica y conserva BF-EQ, peso pendiente sin inventarlo. Repetición navegada 200.
- Nuevo fallo ND: controlador anunciaba éxito ante rechazo SUNAT; regresión roja con cliente simulado y mensaje de error corregido. Campo pasó a «Importe del ajuste» para no afirmar IGV en todos los casos.
- CODEPLEX en Chrome del usuario: formularios B/F/NC/ND, motivos, datos adicionales y referencias; listado/formulario GRE y cancelaciones. No se pulsó Grabar/Guardar/Enviar ni se crearon documentos. Es una cuenta real, no confundir con la demo limitada previa de Conta-Plex.
- Pruebas nuevas: rojo 2/2 fallos reproducidos; verde GRE+aprobación+notas 30 pruebas/186 aserciones; comprobación GRE posterior 1/30; PHPStan 0, Pint, lint afectado, TypeScript, build y diff check. Grupos anteriores se solapan; no sumar como suite total. Revisión estática acotada sin P1/P2.
- Investigación y pendientes detallados en anexos/CODEPLEX_GRE_NOTAS_2026_10_10.md. Auditoría global aún en curso; no afirmar que todos los botones o procesos fiscales están validados.

## Continuación: GRE contextual y entrega conectada (10/10, noche)

- GRE: origen y referencia del comprobante visibles; paginación real anterior/siguiente, incluso a 390px. El formulario identifica venta/recojo/entrega/traslado y cancela hacia el recorrido autorizado. Gerente sin rol operativo vuelve a guías; corregido403 de retorno reproducido en navegador y prueba. Se conserva el historial central; detalle/enlaces por guía siguen pendientes.
- Notas: original visible y acción «Solicitar al gerente»/«Emitir y enviar a SUNAT» según rol. Motivo13 rechazado desde UI por falta de desglose de cuotas; ahora deshabilitado y rotulado no disponible. No se implementaron cuotas ni notas por línea.
- Taller5: relevo explícito a Campo, sin cambiar departamento, equipos, fechas, venta o certificados; acceso404 antes y200 después para el responsable. Entrega con receptor/firma ficticios, después cierre separado; una sola acta y certificado OG-2026-0006 conservado. No se creó venta para cerrar.
- Inspección8: cierre posterior a su entrega previa; PDF original disponible, evento previo intacto y ninguna renovación repetida. Venta10 conserva S/ 45, pago registrado y certificado OG-2026-0005.
- Asignación ordinaria rechaza entregado/cerrado/anulada. Confirmación, relevo y cierre comparten bloqueo/relectura; regresión reproduce responsable desactualizado. La cola e indicadores incluyen listo_certificado y listo_entrega, igual que la confirmación.
- Pruebas finales:33/33, 285 aserciones, en bruce_fire_testing; SUNAT simulado. PHPStan 0, Pint, lint afectado, TypeScript y build. Revisión independiente acotada sin P1/P2 tras corregir concurrencia. No es prueba simultánea de carga ni suite global.
- 21 borradores GRE marcados AUD-VISIBLE creados únicamente en copia para verificar guía 21; 0 enviados. Dos CDR antiguos siguen siendo ficticios. Ninguna operación en CODEPLEX esta continuación, ni documentos fiscales emitidos.
- Guiones109/112 fallaron por URL duplicada y versión Inertia ausente antes de guardar; se corrigió el guion y se continuó. No se clasificaron como fallos del producto. Capturas y snapshots actualizados en la carpeta de evidencia.
- Dirección de producto y límites en anexos/EVOLUCION_BRUCE_FIRE_2026_10_10.md. Auditoría global en curso; funciones nuevas y notas/GRE fiscales conservan sus pendientes.
