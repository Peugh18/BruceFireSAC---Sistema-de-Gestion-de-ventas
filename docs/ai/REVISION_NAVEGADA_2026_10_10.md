# Revisión navegada y correcciones de usabilidad — 09–10/10/2026

## Resultado y alcance

Se recorrió la aplicación real con Edge mediante Playwright, se corrigieron defectos reproducidos y se rediseñó el registro/edición de productos. No es una certificación de todos los botones, estados y datos posibles: se distinguen acciones probadas, revisión de código y casos pendientes.

Checkout: `D:/TiomiguelonGgs/Documents/BRUCE FIRE/BruceFireSacv2`, base HEAD `53cda2b`. La ruta Desktop configurada en el chat no existe. Se conservaron las correcciones previas de detracción, caja, contraste y avisos; sin commit, push ni dependencias nuevas. No se cambió el diseño de la barra lateral.

La navegación inicial usó puerto 8000. Las operaciones de cobro, recepción y taller se ejecutaron después en puerto 8001, contra una copia separada `bruce_fire_ui_audit_20261009` y almacenamiento separado. No se emitió un nuevo comprobante a SUNAT ni se enviaron mensajes externos.

## Cobertura real

| Rol               | Pantallas principales |
| ----------------- | --------------------: |
| Gerente           |                    11 |
| Vendedor          |                    12 |
| Almacén           |                     8 |
| Técnico de planta |                     3 |
| Técnico de campo  |                     6 |

- 40 pantallas distintas desde los enlaces reales del menú, todas con respuesta 200 en el recorrido final.
- 274 botones/enlaces identificados; 68 acciones de filtros, búsqueda y apertura/cierre registradas. Identificar un control no equivale a probarlo.
- 25 aperturas de fichas, correspondientes a 15 URLs distintas; algunas fichas aparecen en varios recorridos.
- Pruebas adicionales de producto, descargas, recepción, taller, crédito, pagos e historial y caja, detalladas abajo.
- No hubo errores JavaScript en los estados del recorrido final. Los timeouts, nombres de selector incorrectos y cuatro rutas inicialmente supuestas que no existen fueron errores del sondeo, no defectos de la aplicación.

## Defectos corregidos

| ID  | Severidad | Evidencia / antes                                                                                                                                                                             | Corrección                                                                                                                                                                                                                                                                                                                                             |
| --- | --------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| N-1 | Media     | B001-3 aceptada, emitida 09/10 a las 21:12:04; KPI 2 y tabla 0. La consulta comparaba DATETIME contra el día final a medianoche.                                                              | Comparar `DATE(COALESCE(fecha_emision, created_at))`; sirve al listado y a las exportaciones. Cuatro regresiones fallaron antes y pasan después.                                                                                                                                                                                                       |
| N-2 | Media     | Productos: unos veinte controles en ventana estrecha, códigos antes del nombre/precio, abreviaturas y opciones mezcladas; Escape no cerraba y el foco quedaba detrás.                         | Nombre y precio primero, unidad de venta con nombre legible, datos del extintor según categoría, inventario por cantidad/serie/lote con ejemplos, adicionales opcionales. Diálogo con scroll interno y pie fijo; errores junto al campo y foco en el primer error; confirmar descarte de cambios.                                                      |
| N-3 | Media     | Productos y Servicios mostraban “Eliminar” y una advertencia de rechazo por historial, pero el servidor siempre desactiva y conserva registros.                                               | Una sola acción Activar/Desactivar con confirmación y explicación correcta; endpoint histórico conservado.                                                                                                                                                                                                                                             |
| N-4 | Media     | Servicios, Sedes, alta rápida, consumo, resolución y pagos usaban overlays manuales sin la semántica y el manejo de teclado del diálogo del proyecto.                                         | Reutilizar Radix Dialog, limitar altura y permitir scroll. Devolver el foco al botón de apertura; cierre táctil de 44 px en el componente compartido. Escape/foco se probaron en producto, servicio, sede, alta rápida e historial de pagos; los otros dos formularios necesitan un caso con deficiencia autorizada para completar su prueba navegada. |
| N-5 | Media     | En OT-0005, registrar BF-EQ-000007 dejó el checkbox de llegada en false, pese al mensaje “desmarca los que faltan” y al recibido=true del servidor. Confirmar podía revertirlo a no recibido. | Al registrar/vincular, incorporar los equipos recién recibidos a la selección, manteniendo desmarcaciones previas. OT-0006/BF-EQ-000008 quedó seleccionado inmediatamente, se confirmó y llegó a certificado sin modificar directamente el pivot.                                                                                                      |
| N-6 | Media     | Una cuota de S/45 pagada mostraba S/0 y debajo “Saldo de S/45”. La columna estaba titulada Monto aunque mostraba el saldo.                                                                    | “Saldo por cobrar” e “Importe original”; cuota liquidada muestra “Pagada” y bloquea nuevos cobros. El listado explica que conserva los pagos de hoy. Cálculos y reglas de cobro intactos.                                                                                                                                                              |
| N-7 | Baja      | El botón del historial del gerente tenía como nombre solo la cantidad de pagos.                                                                                                               | Nombre accesible con venta/cuota y tooltip “Ver historial de pagos”; abierto con el pago de S/20 y foco de vuelta al cerrar.                                                                                                                                                                                                                           |
| N-8 | Baja      | Antes de cerrar el taller, la venta de recarga decía que no tenía extintores, aunque la orden tenía equipo recibido pendiente de certificado.                                                 | Aviso de certificados aún no emitidos, con explicación de cierre de taller y registro de capacitación.                                                                                                                                                                                                                                                 |
| N-9 | Media     | El peso inválido dentro de adicionales cerrado no abría el campo; un factor 1.5 oculto al seleccionar serie quedaba rechazado sin explicación concreta.                                       | Validación nativa abre y enfoca; campos de compra reaparecen si tienen errores del servidor y resumen muestra el mensaje concreto. Ambos casos reproducidos en Edge y verificados rojo→verde, sin crear productos.                                                                                                                                     |

## Operaciones comprobadas

**Productos.** Crear y editar desde navegador; código duplicado y código de barras inválido con error/foco en su campo; conservar presentación de compra; alternar cantidad/serie/lote; cancelar cambio de estado, desactivar y activar; confirmar descarte sin perder datos al cancelarlo. Escritorio 1440×960 y móvil 390×844: diálogo dentro de pantalla, guardar visible de 44 px y 25 pasos de Tab contenidos. Se comprobaron agente/capacidad y gestión anidada de categorías. Dos productos `AUD-UI-*` creados en la aplicación original quedaron desactivados, sin movimientos de stock.

**Comprobantes.** B001-00000003 visible y aceptada al filtrar 09/10. Los botones XML, CDR, PDF, ZIP y Excel produjeron archivos válidos; el CSV contiene `B001;00000003`. Al cambiar de día, corresponde usar 09/10: “Hoy” ya es 10/10.

**Recepción de Almacén.** Agregar y quitar línea, seleccionar producto, registrar 3 válvulas con 2 conformes y 1 observada con motivo/costo. La ficha conserva 3/2/1; stock de la copia 9→11. El original permanece en 9.

**Taller y certificado.** COT-0003 S/45 → OT-0006 → tomar orden → alta BF-EQ-000008 → llegada seleccionada → confirmar → checklist conforme → trabajo terminado → VTA-0005/NV-0002 a crédito → cierre de taller → OG-2026-0003 Operatividad y Garantía, una unidad → listo_entrega. Persistencia contrastada: orden 6 enlazada a venta 8 y certificado 3. No se completó la entrega física/acta.

**Cobros.** Cuota S/45: impedir monto mayor que saldo; transferencia S/20 deja S/25; el gerente consulta la operación AUD-UI-PAGO-20; efectivo S/25 liquida la cuota. Tras medianoche los pagos del 09/10 dejan de aparecer como pagos de hoy. VTA-0006, creada en la copia el 10/10, permitió comprobar el texto nuevo con saldo 0, original 45 y botón Pagada deshabilitado.

**Caja.** Arqueo ciego no revela esperado antes del conteo; cierre con monto simulado 0 y observación AUD-UI en la copia; apertura de turno nuevo con S/10. No se cerró ni modificó el turno del usuario en la aplicación original.

**Descartados como bugs.** Vincular equipo existente envió correctamente BF-EQ-000007: no se modificó ese envío por una sospecha de estado anterior. El checklist conforme avanza automáticamente la orden; no exige un segundo clic Iniciar. Conservar las cuotas pagadas hoy permite consultar/anular sus cobros, por lo que se aclaró el texto en vez de quitar esos registros.

## Verificación técnica y revisión del cambio

- Regresión nueva: 4/4 fallan antes por excluir el último comprobante del día. Factura, boleta, NC y ND; medianoche, 23:59:59, respaldo created_at, días fuera del rango y otro vendedor.
- Pruebas afectadas: 31/31, 192 aserciones.
- Suite completa: 957/957, 5.115 aserciones, 462 segundos; dos procesos y bases de testing, sin reset de la base original. Después solo cambiaron interfaz y textos; no se repitió la suite por esas modificaciones.
- PHPStan: 0 errores. Pint: passed. TypeScript pasó tras la conversión de diálogos y sincronización de llegada; se ejecuta comprobación final al cerrar.
- Axe WCAG A/AA, 2.1 y 2.2: ocho pantallas/estados y producto oscuro, sin infracciones automáticas. No certifica WCAG completo ni reemplaza NVDA o revisión de todos los estados.
- Revisión de seguridad acotada al diff: el filtro conserva vendedor y parámetros enlazados; no se quitaron validaciones, permisos ni restricciones de historial. La selección de llegada usa datos recibidos del servidor y no vuelve a marcar equipos que el operador había desmarcado. El cambio compartido de Dialog afecta cierre/título de todos sus consumidores; se verificaron teclado y anidación en el catálogo.
- Complejidad: se reutilizaron Dialog y la confirmación existentes; no se agregaron librerías, entidades ni un sistema de formularios. La mayor parte del diff de Servicios/Sedes es reindentación al quitar wrappers.
- Formato/lint: 214 archivos formateados y 175 examinados sin errores; TypeScript y build final pasan. PHPStan y Pint pasan. Revisión independiente de código sin otros P1/P2 tras corregir el caso N-9.

Referencia usada para revisar labels, foco, formularios y controles: [Web Interface Guidelines](https://raw.githubusercontent.com/vercel-labs/web-interface-guidelines/main/command.md). Implementación contrastada con [formularios de Inertia v3](https://inertiajs.com/docs/v3/the-basics/forms) y [HTTP tests de Laravel 13](https://laravel.com/framework/docs/13.x/http-tests).

## Pendientes para la siguiente revisión y comparación

1. Consumo y resolución con una deficiencia autorizada; verificar los dos diálogos convertidos y todo su guardado, no solo las reglas cubiertas por la suite.
2. Campo: recojo, inspección/instalación, entrega y acta con firma; las pantallas examinadas tenían listas vacías o no tenían un caso completo asignado.
3. GRE completa, RA/RC y SUNAT de producción: no hubo credenciales GRE ni autorización de pasar a producción; no inventar configuraciones o constancias. La tabla/descargas se probaron con comprobantes beta existentes.
4. Dispositivos: escáner físico, cámara y micrófono reales; accesibilidad con lector de pantalla, zoom y más tamaños/dispositivos.
5. Investigación con empresas grandes, en la siguiente fase: registro progresivo de productos (Odoo/Zoho/Shopify), separación saldo/historial en cobranzas y gestión de taller/custodia. Consultar documentación oficial y proponer únicamente lo aplicable al alcance BRUCE FIRE; todavía no se hizo esa comparación.

Evidencia local ignorada: `storage/app/revision-navegada-2026-10-10/`. La copia de pruebas permanece separada; el servidor 8001 se detiene al terminar. La base original conserva stock, ventas, pagos y turnos; solo los dos productos de prueba del catálogo quedaron archivados/desactivados.
