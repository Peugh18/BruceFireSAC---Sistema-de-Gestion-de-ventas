# Propuesta de mejora de interfaz con shadcn/ui y Tremor

Fecha: 11/10/2026. Estado: propuesta para aprobación; ninguna mejora de aplicación implementada.

## Resultado propuesto

Cada pantalla debe responder tres preguntas: qué está hecho, qué falta y quién continúa. La mejora es reorganizar y expresar datos existentes, con componentes compartidos. No agrega clics, pantallas, campos obligatorios, requisitos fiscales ni cambios de permisos o inventario.

Esta propuesta usa el inventario y la auditoría por botones de los cinco roles, incluido el traslado recibido en Chimbote. La auditoría tiene límites explícitos: no están comprobadas todas las combinaciones, accesibilidad integral, hardware o emisión externa. Los problemas observados no se dan por corregidos.

## Qué tomar de los enlaces

| Referencia verificada                                                         | Qué tomar                                                           | Cómo aplicarlo en Bruce Fire                                                                                                                 |
| ----------------------------------------------------------------------------- | ------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------- |
| [shadcn Progress](https://ui.shadcn.com/docs/components/progress)             | Composición de barra, etiqueta y valor                              | Familia propia con datos reales y API value/max; Tailwind, sin instalar una implementación nueva                                             |
| [shadcn Card](https://ui.shadcn.com/docs/components/card)                     | Título, descripción, contenido y acciones con jerarquía consistente | Reutilizar ui/card.tsx para paneles, revisión, estado y responsable                                                                          |
| [shadcn Badge](https://ui.shadcn.com/docs/components/badge)                   | Estado compacto con texto                                           | Reutilizar ui/badge.tsx; variantes semánticas compartidas y texto español                                                                    |
| [Tremor KPI Cards](https://blocks.tremor.so/blocks/kpi-cards)                 | Cifra destacada con contexto y comparación cuando existe            | Paneles con título, valor, período, sede y explicación del universo                                                                          |
| [Tremor Status Monitoring](https://blocks.tremor.so/blocks/status-monitoring) | Lectura rápida de estados y excepciones                             | Colas de trabajo, autorización, rechazo y respuesta fiscal; sin inventar monitorización en tiempo real                                       |
| [Tremor Tables](https://blocks.tremor.so/blocks/tables)                       | Filas claras, alineación y lectura de datos                         | Mejorar tablas existentes y su vista móvil conservando filtros, acciones y paginación                                                        |
| [Tremor Bar Lists](https://blocks.tremor.so/blocks/bar-lists)                 | Comparación visual de cantidades                                    | Rankings que ya reciben cifras; sin convertirlos en porcentaje de tarea completada                                                           |
| [Tremor Overview / Planner / Insights](https://blocks.tremor.so/templates)    | Organización de paneles, tablas y filtros                           | Overview como referencia de resumen; Planner para colas; Insights para filtros y estados. No migrar a Next.js ni incorporar sus dependencias |

Los componentes y bloques son referencias de forma. No se ejecutarán comandos de instalación de sus ejemplos, ni se incorporará @tremor/react, Recharts o Radix Progress. Los pasos en cheurón, la segmentada y el anillo serán variantes propias; no se presentan como componentes oficiales copiados de shadcn.

## Lenguaje visual compartido

- Fondo y superficies con background, card, muted y border existentes. Rojo primary para marca y etapa actual; success-strong para éxito; warning-strong para atención; destructive para rechazo/error, siempre acompañado de texto o icono.
- Mantener tipografía y densidad de las pantallas vecinas. Cabecera con page-header.tsx, tarjetas con ui/card.tsx y pestañas con ui/tabs-bf.tsx.
- Una acción principal visible cuando ya existe; acciones secundarias en su ubicación actual. Los indicadores son informativos y no son botones.
- Textos como En espera de autorización, Listo para entregar, Recibido y Cerrado. Eliminar etiquetas técnicas crudas en la presentación, conservando sus valores internos.
- Cada contador identifica período y alcance: esta sede, tus órdenes, este mes o el filtro vigente. No mezclar cifras globales con las de una sede o fila con total paginado.
- A 375 px, pasos apilables y etiquetas que envuelvan. Las tablas largas pueden tener una vista de filas en tarjetas con las mismas acciones; solo una representación accesible activa por tamaño. Datos densos que necesitan comparación pueden conservar desplazamiento horizontal identificado.
- Chispa debe respetar el espacio de las acciones y contenido sin taparlos. Conservar el acceso a ayuda existente.

## Plan por rol y pantalla

Esfuerzo incluye integración y comprobación; bajo, medio y alto son estimaciones de alcance, no promesas de tiempo. Las rutas de frontend son relativas a resources/js; el inventario previo contiene el mapa completo de controladores y props.

| Pantalla / archivo                                            | Mejora concreta                                                                      | Patrón / referencia                                  | Datos actuales                                           | Esfuerzo   | Riesgo o condición                                                                               |
| ------------------------------------------------------------- | ------------------------------------------------------------------------------------ | ---------------------------------------------------- | -------------------------------------------------------- | ---------- | ------------------------------------------------------------------------------------------------ |
| Vendedor: pages/vendedor/ordenes-servicio/show.tsx            | Resumen de etapa, pendiente y responsable, junto al bloque de continuación existente | 7, Pasos cheurón + Card/Badge                        | Estado, técnico, certificados y eventos                  | Medio      | El check de Por asignar hoy es falso; etapa no prueba cobro ni entrega                           |
| Vendedor: ordenes-servicio/index.tsx y comunicacion/index.tsx | Estado compacto de cola y mismos hitos que el detalle                                | 9 en lista; 4/7 en seguimiento                       | estado_coarse y datos del seguimiento                    | Medio      | Retirar STEPS/getCoarseStep duplicados; conservar actividades y permisos                         |
| Planta: tecnico-planta/ejecucion/show.tsx                     | Taller, En proceso, Terminado y Certificado con pendiente visible                    | 4, numerado + estado compacto                        | order.estado, certificados y autorización                | Medio      | Terminar/certificar ya es una acción conjunta; no dividirla en trámites                          |
| Planta: recepción                                             | Recibidos frente a esperados, más diferencia textual                                 | 3, barra con etiqueta                                | Equipos de la orden y recepción existente                | Bajo/medio | Distinguir selección sin guardar de recepción confirmada                                         |
| Planta/Campo: checklist-extintor y checklist/create           | Distribuir Conforme, Observado y No aplica con leyenda y conteos                     | 1, segmentada                                        | Los 14 valores del checklist                             | Medio      | Precargado no equivale a revisado. No usar 9/14 revisados sin dato guardado                      |
| Campo: inspecciones/show y mantenimientos/show                | Resumen único de revisados, observados y pendientes                                  | 8, anillo + texto                                    | Checklists guardados por equipo de esta orden            | Medio      | Observado cuenta como revisado. Mantener la misma lógica en ambos                                |
| Campo: evidencias de inspección/mantenimiento                 | Antes y Después con cantidad y presencia, junto a controles actuales                 | Card/Badge; 3 solo si hay total exigido real         | Evidencias por tipo/etapa/equipo                         | Bajo       | No hay meta universal de fotos. No crear porcentaje ni campo obligatorio                         |
| Campo: instalaciones/show y recojos/show                      | Identificación de unidades y custodia con etiquetas claras                           | 3 y 4                                                | Unidades vendidas, selección actual y evento de custodia | Medio      | Identificado no significa instalado; no inventar recojo para una visita en sitio                 |
| Campo: entregas/show; Vendedor: mostrador                     | Estado de entrega, documento y receptor registrados                                  | 4 + Card/Badge                                       | Estado, acta y eventos                                   | Medio      | El cierre técnico no acredita entrega; no pedir segunda conformidad                              |
| Almacén: recepciones/show                                     | Separar recibidas, conformes y observadas de un vistazo                              | 1/3, segmentada con cantidades                       | cantidad y cantidad_conforme                             | Bajo       | No mezclar observadas con ingresadas a stock                                                     |
| Almacén: recepción serializada y stickers                     | Estado de registro y etiquetas listas; misma acción de impresión                     | Badge/estado, sin barra decorativa                   | Unidades creadas y códigos BF-EQ                         | Bajo       | BF-EQ lo genera el servidor; serie fabricante puede estar vacía                                  |
| Almacén: traslados/index                                      | Preparado, guía, en tránsito y recibido donde exista historial                       | 7 + Status Monitoring                                | estado, tiene_guia y guia_aceptada                       | Medio      | El recibido desaparece de esta cola; no fingir historial con datos ausentes                      |
| Guías: pages/guias/show.tsx                                   | Secuencia y resultado fiscal visibles; simulación explícita                          | 7 + Badge/Alert                                      | estado_sunat, ticket, CDR y lista_para_trasladar         | Medio      | Aceptada/CDR no agregan otra confirmación; peso de traslado proviene del origen                  |
| Notas Gerente y venta Vendedor                                | Solicitud, decisión y respuesta; mostrar motivo de rechazo                           | 4 + Badge/Alert                                      | Solicitudes y documentos de cada pantalla                | Medio      | La vinculación completa solicitud-documento no llega en todos los casos                          |
| Facturación y components/cargando.tsx                         | Carga con nombre de operación y resultado, sin demora ficticia                       | 6 indeterminado, estilo Progress/Skeleton existentes | Petición activa y estado final                           | Medio      | No hay porcentaje interno ni ETA de generar/firmar/CDR; conservar protección doble envío         |
| Deficiencias: components/deficiencias-vendedor.tsx            | Detectada, autorización y resolución en la tarjeta compartida                        | 4 compacto + Badge/Alert                             | estado, autorización y pendiente                         | Medio      | Puede no necesitar autorización o estar rechazada; no imponer secuencia única                    |
| Cotizaciones: vendedor/cotizaciones/index.tsx                 | Borrador, enviada, aceptada y vendida con estado compacto                            | 9 + Badge                                            | Estado y venta vinculada                                 | Bajo       | Rechazada/vencida/anulada son salidas explícitas. Validar venta anulada antes de mostrar vendida |
| Cuotas Vendedor/Gerente                                       | Importe pagado, ajuste y saldo claramente separados                                  | 3 + Card                                             | monto, acreditado, saldo y pagos                         | Medio      | Acreditado con nota no es dinero pagado; cuota pagada sin alerta de deuda vencida                |
| Caja Vendedor/Gerente                                         | Resumen de cierre con contado, esperado y diferencia                                 | 2 después de cerrar + KPI Card                       | Datos del cierre registrado                              | Medio      | No mostrar esperado antes ni durante arqueo ciego; no es meta comercial                          |
| Stock y catálogo                                              | Existencias, marca del mínimo y aviso En el mínimo                                   | 2 + Badge                                            | stock disponible, stock_minimo y sede                    | Medio      | Mínimo es umbral, no capacidad máxima. Mantener regla inclusiva vigente                          |
| Vendedor: Por vencer                                          | Fecha y días con urgencia textual, agrupación y cotización en un clic                | 3 como etiqueta temporal                             | días, fecha y origen                                     | Bajo       | Sin inicio de período no hay porcentaje de vida útil. Mantener datos estimados identificados     |
| Paneles de los cinco roles                                    | Unificar cifra, contexto y acción existente; colas por actividad real                | KPI Cards + Status Monitoring                        | Props de cada DashboardController                        | Medio      | Antes corregir universos distintos de Campo y destinos de visitas                                |
| Gerente: reportes y rankings                                  | Panel de filtros, valores con contexto y comparación de cantidades                   | Overview + Bar Lists/Tables                          | Métricas y rankings enviados                             | Medio      | Sin historia temporal no agregar curva/sparkline. No confundir netos y brutos                    |
| Certificados: listado y ficha/QR                              | Vigente/por vencer/vencido con fecha y documentos visibles                           | Badge + Card                                         | Estado, vigencia y equipo por registro                   | Bajo       | No sumar una página como total global; firmas sin imagen siguen siendo faltantes                 |
| Usuarios: primer acceso y seguridad                           | Explicar requisito actual y su resultado dentro de pantallas existentes              | 4 compacto condicionado                              | must_change_password y twoFactorEnabled                  | Medio      | Confirmación es temporal de sesión; 2FA no obligatorio a todos. Sin onboarding nuevo             |
| Clientes, sedes, vehículos, catálogo y configuración          | Encabezados, estados, vacíos y mensajes consistentes                                 | Card/Badge, tablas y filtros existentes              | Datos de cada formulario/lista                           | Bajo/medio | Barra de campos completados no vale la pena; no crear requisitos nuevos                          |

## Qué debe mostrar cada panel

| Rol      | Organización propuesta con los datos disponibles                                                                                | Qué queda fuera                                                                                           |
| -------- | ------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------- |
| Gerente  | Métricas comerciales con neto/período, cobranzas con significado, stock crítico, pendientes de aprobación y rankings existentes | Cumplimiento mensual sin meta; agregado de certificados ausente; márgenes o series temporales no enviados |
| Vendedor | Ventas de hoy, cuotas por cobrar, caja, cotizaciones por estado, agenda y recargas próximas                                     | Ventas mensuales no enviadas al rol; porcentaje global de cobro sin obligación ajustada canónica          |
| Almacén  | Stock por sede, recepciones, bajo/en mínimo y últimos movimientos permitidos                                                    | Mezclar otras sedes en consulta restringida sin política validada                                         |
| Planta   | Recepción, trabajo activo, autorización pendiente y equipos listos, según conteos existentes                                    | Inventar revisión progresiva del checklist o total de fotos                                               |
| Campo    | Colas de recojo, inspección, mantenimiento, instalación y entrega con misma definición de total/pendiente/finalizado            | Porcentajes sobre los universos actualmente contradictorios; recojo forzado de visitas                    |

Ejemplos de composición, no datos nuevos: una tarjeta de revisión presenta N de M revisados, K con observaciones y P pendientes. Una tarjeta de cuota presenta importe original, pagado, ajustes y saldo. Un estado de orden presenta etapa, qué falta y responsable existente. Los números se sustituyen por datos del servidor, nunca por ejemplos del diseño.

## Familia única y migración

Crear únicamente la familia de progreso en resources/js/components/progreso/:

- Progreso: value/max y variante indeterminada para una operación activa.
- ProgresoConEtiqueta: compone la misma barra con cantidad, unidad y nombre.
- ProgresoSegmentado: segmentos y leyenda explícita, sin duplicar la base.
- Pasos: variantes numerada, cheurón y puntos; mismo estado semántico y reglas de representación.
- AnilloProgreso: representación circular con SVG nativo de los mismos valores; no gráfico dependiente de Recharts.

Los nueve patrones: 1 segmentada, 2 barra con marcador, 3 barra etiquetada, 4 numerado, 5 excluido por pedido, 6 espera con texto, 7 cheurón, 8 anillo y 9 puntos. El marcador de stock es un umbral de inventario; no convierte el mínimo en max de una tarea ni agrega semántica engañosa de completado.

ResumenRevision conservará su responsabilidad de contar los checklists válidos de la orden y delegará la representación en la familia. Solo una fórmula para inspección y mantenimiento. Retirar el progress nativo azul, los mapas STEPS/getCoarseStep duplicados y avances sueltos del alcance aprobado. Card, Badge, TabsBf, Alert, Skeleton y PageHeader se reutilizan; no se crea una segunda biblioteca paralela. KpiCard, si se aprueba, será composición de Card existente.

La variante numerada indica hitos de negocio; no fuerza etapas iguales ni pesos iguales. Anulada, rechazada y esperando autorización se leen como estados y excepciones, nunca como paso exitoso.

## Correcciones que condicionan el diseño

| Problema comprobado                                     | Tratamiento recomendado                                                    | Relación con la propuesta                            |
| ------------------------------------------------------- | -------------------------------------------------------------------------- | ---------------------------------------------------- |
| 500 al cotizar desde ficha: clients.sede_id inexistente | Corrección funcional específica con prueba de ese acceso                   | Un indicador no resuelve el enlace roto              |
| Movimientos de Consulta Rápida fuera de la sede         | Validar política y filtrar conforme a ella                                 | No cambiar permisos por iniciativa de diseño         |
| Campo ofrece Iniciar Recojo a visitas en sitio          | Corregir orientación por actividad                                         | Ningún paso de recojo adicional                      |
| KPI de Campo/Entregas con criterios diferentes          | Alinear consultas/etiquetas al universo acordado                           | Impide porcentajes honestos hasta corregir           |
| Por asignar marcado como hecho                          | Reflejar ausencia de técnico                                               | Piloto de la familia de pasos                        |
| Cuota pagada aún rotulada vencida                       | Alerta solo para deuda vigente; mantener historia                          | Presentación financiera coherente                    |
| GRE descarta peso editable                              | Exponer que proviene del producto/origen o impedir esa edición aparente    | Preservar autoridad del traslado y reglas fiscales   |
| Edición de cotización habla de renovación               | Texto específico de editar, sin prometer nueva numeración                  | Cambio de copy; no modificar vigencia o negocio      |
| Firmas faltantes y marca puntual de firma               | Mostrar condición real; cualquier validación nueva requiere alcance aparte | No dar por válida una firma por embellecer el estado |
| Chispa sobre contenido móvil                            | Ajuste de posición y espacio de contenido                                  | Acciones legibles a 375 px                           |

La corrección funcional/permisos debe aprobarse expresamente en su alcance. Una mejora visual no autoriza cambiar aritmética financiera, devoluciones, umbrales ni autorización de repuestos.

## Fases propuestas

### Fase A: base compartida y piloto

- Familia en progreso/, catálogo de estados y reutilización de componentes existentes.
- Orden del Vendedor como piloto: etapa correcta, pendiente y responsable; corregir el check falso dentro del alcance aprobado.
- Comprobar lista/seguimiento afectados por la migración. No dejar versiones duplicadas activas.
- Usar estados sin técnico, autorización pendiente, trabajo terminado, entrega y anulación.

Aceptación: responde qué falta y quién continúa sin clic nuevo, con semántica correcta, claro/oscuro, 375 px, teclado y comprobaciones de accesibilidad documentadas.

### Fase B: procesos largos

- Taller, campo y ResumenRevision; recepción; guías/traslados; deficiencias; cotizaciones y notas aprobadas para el alcance.
- Ajustes de orientación de Campo y estados inconsistentes solo cuando se aprueben.
- Tablas/Chispa de las pantallas tocadas; estados vacíos, error, rechazo y simulación visibles.
- Se conserva terminar/certificar en un paso, cotizar recarga agrupada y entregar con las condiciones vigentes.

Aceptación: relevo Vendedor-Planta-Campo y Almacén-origen/destino por botones, sin variaciones de reglas o duplicar consumo, cobro, certificado o conformidad.

### Fase C: paneles y KPI

- KpiCard compuesto de Card, cifras con período/sede/universo, rankings y stock con marcador.
- Cuotas y cierres registrados con significado financiero exacto.
- Unificar presentaciones de reportes/colas con las referencias Overview, Tables y Bar Lists.
- Los agregados no enviados se anotan como pendientes, no se sintetizan desde una página parcial.

Aceptación: cada cifra se puede explicar con datos existentes y coincide con el listado bajo sus mismos filtros. Ninguna meta ni comparación histórica inventada.

## Accesibilidad y comprobación

- Barras/anillos determinados: nombre accesible, role progressbar, aria-valuemin=0, aria-valuemax real positivo y aria-valuenow real; texto equivalente de cantidad. Con total 0, mostrar estado vacío en vez de 0/0.
- Espera indeterminada: nombre y texto de operación activa, sin aria-valuenow inventado, sin porcentaje/ETA. Hitos: lista ordenada y etapa actual identificada; no introducir Tab en elementos informativos.
- Estado nunca solo por color; contraste medido en claro/oscuro. Foco visible y avisos legibles por lector de pantalla.
- Movimiento reducido: barra estática y texto; sin animación infinita forzada. No animar datos ni transición como si fuera avance del servidor.
- Cada pantalla tocada: claro/oscuro, 375 px, teclado completo y axe sin infracciones nuevas. Si axe no está disponible sin agregar dependencia, registrar el bloqueo y acordar el medio de ejecución; no afirmar cero errores.
- Recorridos por botones y formularios, Wayfinder en cambios de navegación. Casos demo sin reponer o borrar estados automáticamente.
- npm run check, npx tsc --noEmit, Pint si hay PHP, PHPStan y Pest afectado por cambios de comportamiento. Suite completa una sola vez al final del trabajo aprobado, sin otra prueba paralela. La corrida anterior de auditoría no acredita código futuro.
- PLAN y BITACORA por fase, sin commit/push salvo pedido.

## Qué no vale la pena

Barra de campos de cliente completados, un paso por foto, wizard para una venta existente, onboarding obligatorio adicional, gráfico temporal sin historia, barras de cumplimiento sin meta, anillos en cada fila y porcentajes del checklist precargado. Son decoración o alargan el flujo y se descartan.

La aprobación puede separar tres alcances: componentes/indicadores, adaptación móvil/consistencia y correcciones funcionales. Este reporte no constituye autorización de implementación ni exige que se aprueben todos juntos.

## Base y verificación de este reporte

- Basado en INVENTARIO_PROGRESO_POR_ROLES_2026_10_11.md y AUDITORIA_OPERACIONES_POR_BOTONES_2026_10_11.md; referencias oficiales anteriores revisadas el 11/10/2026.
- Cambios de esta entrega: solo reporte y memoria de proyecto. No componentes, dependencias, configuración, credenciales ni operaciones nuevas.
- Pruebas de la auditoría previa: check, tipos, PHPStan, Pint y 1028 pruebas pasaron. No se repite Pest por esta modificación documental.

- Formato de la entrega pasó; npm run check pasó con 230 archivos formateados y 180 sin avisos/errores de lint. git diff --check pasó. No repetir suite por cambios documentales; no código de aplicación modificado.
