# Investigación: técnicos y comunicación (2026-10-06)

Comparación de Bruce Fire con el software de servicio en campo usado por empresas grandes, para no reinventar nada.

## Qué se revisó

- **Especializados en extintores e incendios:** Uptick, Inspect Point, BuildingReports (ScanSeries).
- **Servicio en campo en general:** ServiceTitan, Salesforce Field Service, Odoo Field Service, Jobber.
- **Nuestro sistema:** controladores y pantallas de `TecnicoPlanta` y `TecnicoCampo`, contra el Documento Maestro §17, §19–§25 y §33 y las notas de voz.

## Lo que hacen todos (el patrón común)

1. **Una conversación por orden de trabajo.** Salesforce y Odoo la llaman _Chatter_; ServiceTitan, _chat del trabajo_. Ahí escriben la oficina y el técnico, adjuntan fotos, videos y archivos, y además aparecen solos los eventos del sistema (cambio de estado, deficiencia, reporte firmado). No es un chat general de la empresa: cada conversación vive dentro de su orden.
2. **Todo gira alrededor del equipo (activo).** Se escanea el código del extintor, se abre su checklist, las deficiencias quedan con foto y todo se suma a su historial.
3. **Un solo motor de visitas con plantillas.** Odoo usa _worksheets_ configurables por tipo de servicio, y Salesforce, _work order line items_. No hay una pantalla distinta para cada tipo de trabajo: cambia la plantilla del checklist, no el flujo.
4. **Estados de la visita:** asignada → en camino → en sitio → terminada (ServiceTitan: Dispatch → Arrive → Close out). Así la oficina sabe dónde está cada técnico.
5. **Cierre con firma en pantalla.** El cliente firma en el celular del técnico y el sistema genera y envía solo el reporte o acta en PDF.
6. **Deficiencia → presupuesto → aprobación.** Uptick y Inspect Point convierten la deficiencia en una cotización que el cliente aprueba.
7. **Trabajo sin señal.** Las apps nativas guardan offline y sincronizan después.
8. **Extintor de reemplazo (préstamo).** En la industria de recarga, cuando se recoge un extintor se deja uno igual, cargado y etiquetado, para que el cliente no quede desprotegido.

## Comparación con Bruce Fire

| Tema                     | Empresas grandes                                                                       | Bruce Fire hoy                                                                                                             | Veredicto                                                                                                        |
| ------------------------ | -------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------- |
| Comunicación             | Conversación por orden con texto, fotos, audio y archivos, más los eventos del sistema | Bitácora de eventos por orden. El vendedor escribe notas; el técnico de planta **solo lee** (últimos 10). No hay adjuntos. | ⚠️ La base está bien (por orden y trazable). Falta que todos puedan escribir y adjuntar fotos, audio y archivos. |
| Identificar el equipo    | Escaneo de código → ficha del equipo                                                   | Código BF-EQ y alta técnica rápida                                                                                         | ✅ Bien                                                                                                          |
| Checklist por equipo     | Sí, con plantilla según tipo de servicio                                               | Sí en planta (14 ítems fijos en código) y en inspección                                                                    | ⚠️ Funciona, pero la plantilla está fija en el código; las notas piden reutilizarla para otros servicios.        |
| Fotos                    | En cada deficiencia, antes y después, por equipo                                       | **Ninguna pantalla sube fotos.** Solo hay campos `foto_path` sueltos sin usar.                                             | ❌ Falta (§33)                                                                                                   |
| Firma                    | Táctil, en pantalla, impresa en el acta                                                | Solo una casilla "conformidad" y el nombre                                                                                 | ❌ Falta                                                                                                         |
| Acta o reporte           | PDF automático al cerrar                                                               | Acta de entrega y constancia de recojo en PDF, sin fotos ni firma                                                          | ⚠️ Existe, incompleta                                                                                            |
| Tipos de visita          | Un motor con plantillas                                                                | Cuatro pantallas separadas (recojo, entrega, instalación, inspección), cada una con su lógica                              | ⚠️ Hay código duplicado. **El mantenimiento en sitio no existe.**                                                |
| Estados de la visita     | Asignada → en camino → en sitio → terminada                                            | Los estados de la orden piensan en planta. Campo no informa "en camino" ni "en sitio".                                     | ⚠️ Falta                                                                                                         |
| Deficiencia → aprobación | Cotización que aprueba el cliente                                                      | La deficiencia avisa al vendedor y él registra la autorización (§21)                                                       | ✅ Bien, es el mismo modelo                                                                                      |
| Instalación              | Por unidad, pasa al registro de equipos del cliente                                    | Por orden, con una lista suelta de equipos                                                                                 | ⚠️ Debería escanear cada unidad vendida (§25)                                                                    |
| Extintor de préstamo     | Práctica común en recargas                                                             | No existe                                                                                                                  | ❓ Decisión del negocio                                                                                          |
| Sin señal                | Offline y sincroniza después                                                           | Web; necesita conexión                                                                                                     | ⚠️ Limitación conocida. Se puede resolver más adelante con una PWA.                                              |

## Propuesta

### 1. Conversación de la orden (reemplaza a la bitácora de solo lectura)

- Una línea de tiempo por orden, como un chat de WhatsApp dentro de la orden.
- **Pueden escribir:** vendedor, técnico de planta, técnico de campo y gerente.
- **Se puede adjuntar:** foto (cámara del celular), **audio** (grabado en el navegador con `MediaRecorder`, sin librerías nuevas), PDF u otro archivo.
- Cada mensaje puede **etiquetar un equipo** (BF-EQ) para saber de qué extintor habla.
- Los eventos del sistema aparecen en la misma línea de tiempo: recibido, deficiencia, autorización, listo, entregado.
- Avisos con el contador de no leídos en la campana que ya existe.
- Sigue respetando la regla del §17: no es un chat general de la empresa, todo queda dentro de su orden.

### 2. Evidencia única (§33)

- Un solo tipo de registro para fotos, audios y archivos: orden, equipo opcional, etapa (recepción, deficiencia, antes, después, entrega…), usuario, fecha y archivo comprimido.
- Lo usan la conversación, el checklist, las deficiencias y las visitas.

### 3. Motor único de visitas de campo

- Los tipos de visita son recojo, entrega, instalación, inspección y **mantenimiento**. Todas siguen el mismo flujo:

`Asignada → En camino → En sitio → escanear equipos → checklist según el tipo → fotos antes/después → deficiencias → firma del cliente → acta PDF → cierre`

- La plantilla del checklist depende del tipo de visita. Las notas de voz ya lo pedían: generalizar el checklist en vez de reinventarlo por servicio.
- Las pantallas actuales se conservan como entradas, pero comparten el mismo motor.

### 4. Técnico de planta

- Checklist por extintor con foto obligatoria en cada ítem observado.
- Puede escribir en la conversación de la orden y registrar una deficiencia fuera del checklist.

## Decisiones pendientes del usuario

1. ¿Se aprueba la conversación por orden con texto, foto, audio y archivos? Recomendado: sí.
2. ¿Se aprueba un solo motor de visitas para campo (recojo, entrega, instalación, inspección, mantenimiento)? Recomendado: sí.
3. ¿Bruce Fire deja extintores de préstamo cuando recoge para recarga?
4. ¿Los técnicos de campo trabajan en zonas sin señal? Si es seguido, se prioriza el modo offline (PWA).

## Fuentes

- [Uptick: app de inspección de extintores](https://www.uptickhq.com/blog/improve-safety-with-a-fire-extinguisher-inspection-app)
- [Inspect Point: comparación de software de inspección de incendios](https://www.inspectpoint.com/best-fire-inspection-software/)
- [BuildingReports ScanSeries](https://www2.buildingreports.com/services/scanseries/)
- [ServiceTitan: pantalla de detalle del trabajo](https://help.servicetitan.com/how-to/overview-of-the-servicetitan-field-mobile-app-job-details-screen)
- [Odoo Field Service: worksheets y firma](https://hibou.io/docs/field-service-69/sign-reports-worksheets-1364)
- [Salesforce Field Service (App Store)](https://apps.apple.com/us/app/-/id1163307568)
- [Préstamo de extintores durante la recarga](https://servicedfireequipment.com/fire-extinguisher-recharge/)
- [Odoo: recepción en dos pasos](https://odoo-users.readthedocs.io/en/stable/inventory/management/incoming/two_steps.html)
