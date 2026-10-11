# Mejoras aplicadas desde la comparación del sector — 10/10/2026

El dueño autorizó aplicar las mejoras que encajan con BRUCE FIRE. Se implementaron tres recorridos existentes, conservando cambios previos, reglas comerciales/fiscales y permisos.

## Cambios entregados

1. **Productos:** perfiles Extintor nuevo, Repuesto o accesorio y Producto por lote preparan categoría disponible y control de inventario. Conservan nombre, precio e IGV. Se distingue artículo comercial, unidad física del almacén y equipo del cliente. El resumen explica cómo ingresará el stock y la equivalencia de compra. Los perfiles aparecen al crear; editar mantiene restricciones de historial. Alta del artículo no crea existencias.
2. **Orden del vendedor:** orientación del siguiente paso y responsable según estado/área. Enlaces de cotización PDF, venta y certificados de esa orden, con estados reales y alternativa cuando faltan. Seguimiento selecciona la orden actual. Se distingue trabajo, documentos, entrega y cobro, sin inferir que cerrado significa pagado. El controlador solo añade relaciones de lectura y conserva el aislamiento de sede.
3. **Campo:** conteos de revisados, pendientes y revisados con observaciones a partir del último checklist de esta orden; progreso y botón de siguiente pendiente. Resumen previo al cierre con equipos sin revisión, fotos antes/después, receptor, firma y conformidad. Observados están incluidos entre revisados; revisar no implica conformidad.
4. **Legibilidad:** contraste corregido en estado de la orden, botones de mantenimiento y pestaña superior activa de campo. Barra lateral conservada.

## Reglas conservadas

- Recepción/Kardex siguen gestionando existencias; no se inventan series, atributos técnicos ni afectación fiscal.
- Cierre de mantenimiento conserva validación del servidor: al menos un checklist, fotos antes/después, receptor/conformidad y firma. No se obliga a revisar todos los equipos; los pendientes se advierten para comprobar alcance.
- Autorizaciones, consumos, certificados, custodia, caja y pagos conservan sus reglas. Documentos y destinos mantienen sus controles de acceso.
- Préstamos, portal privado, offline, repuestos sugeridos automáticamente y recurrencia contractual siguen como candidatas separadas.

## Verificación realizada antes del cierre documental

- Regresión nueva: faltaba `serviceOrder.quote.id` antes del cambio; el rechazo de otra sede ya funcionaba. Después pasaron 10 pruebas afectadas/69 aserciones: ServiceOrderDocumentsTest, ServiceOrderModuleTest y TecnicoCampoControlesTest.
- TypeScript, formato/lint, PHPStan (0 errores), Pint y compilación final pasaron. Revisión independiente sin hallazgos P1/P2.
- Edge: 16 comprobaciones sobre copia aislada puerto 8001. Perfiles excluyentes, datos conservados, alta de artículo de prueba luego desactivado, móvil 390×844, documentos accesibles, conteos 2/3 revisados/1 pendiente/1 observado y apertura del equipo pendiente. Sin errores JavaScript.
- Regresiones de errores ocultos siguen verdes: adicionales se abren y enfocan peso inválido/factor rechazado.
- Axe: tres estados sin infracciones automáticas tras corregir contrastes; 25 pasos Tab permanecen dentro del formulario. No certifica WCAG completo ni sustituye pruebas con dispositivos físicos.
- La suite completa anterior de 957 no se repitió por esta modificación acotada. El cierre documental tampoco requiere repetir pruebas funcionales si el código se conserva.

## Evidencia y límites

Resultados y capturas: `storage/app/mejoras-sector-2026-10-10/`. Fixtures y producto de prueba se crearon exclusivamente en `bruce_fire_ui_audit_20261009`, con almacenamiento separado; no se alteraron stock, ventas, pagos ni caja originales y no se emitieron documentos a SUNAT.

La verificación navegada de esta fase cubre las mejoras de presentación. Entrega física con acta, autorización/consumo completo, GRE y dispositivos conservan pendientes de la auditoría previa. No se afirma que todos los botones del sistema estén certificados.

El script de cierre original quedó inutilizable (contenido nulo) tras la interrupción de la revisión automática; se reconstruyó un cierre que verifica evidencia y marcadores del código antes de guardar. Sin nuevas dependencias, commit ni push.
