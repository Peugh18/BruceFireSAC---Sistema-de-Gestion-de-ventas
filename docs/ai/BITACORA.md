# BITÁCORA DE TRABAJO — Bruce Fire S.A.C.

## [2026-10-05] — Inicialización y Arranque de Auditoría Integral

- **Actividad:** Creación de memoria del proyecto con `proyecto-maestro` (`PROYECTO.md`, `PLAN.md`, `BITACORA.md`).
- **Alcance Definido:**
    1. Auditoría funcional profunda módulo por módulo (Ventas, SUNAT/Greenter, Almacén, Técnico Planta, Técnico Campo, Gerente, Público).
    2. Detección de errores lógicos, estados borde no contemplados, riesgos fiscales SUNAT y seguridad multi-sede.
    3. Propuestas de mejora técnica y funcional.
    4. Lineamientos de rediseño visual estilo Apple (tipografía, materiales, elevación, retroalimentación táctil).

## [2026-10-05] — Finalización de Auditoría y Detección de Hallazgos

- **Hallazgos Críticos/Altos Detectados:**
    1. _SUNAT/Greenter:_ Descarga de PDF de Notas de Crédito/Débito arroja 404 porque `EmitElectronicDocument` asigna `pdf_path = null` y no hay renderizador de NC/ND.
    2. _Multi-Sede (Campo):_ En `DeliveryController` y `InspectionController`, los contadores de badges/stats consultan sin el scope `accessibleToTechnician($user)`, filtrando métricas globales entre sedes.
    3. _Performance N+1 (Campo):_ `CollectionController` ejecuta un subquery individual por cada elemento paginado en `->through()` para obtener eventos de custodia.
    4. _Riesgo 500 (Taller):_ `ExecutionController` accede a `$service_order->service->nombre` sin eager-loading ni verificación null-safe cuando `service_id` es nullable.
    5. _Riesgo 500 (Gerente):_ `CashRegisterConsolidatedController` accede a `$cr->vendedor->id` directamente sin null-safe ante usuarios dados de baja.
    6. _Concurrencia (Facturación):_ `ReserveNextCorrelativo` tiene potencial conflicto en `DocumentSeries::create` si dos transacciones inicializan la serie al mismo milisegundo.

## [2026-10-05] — Ejecución de Corrección de Errores y Páginas de Error con Chispa

- **Correcciones Implementadas:**
    1. _SUNAT / Facturación:_ Se habilitó la generación y regeneración de PDF para Notas de Crédito y Débito en [EmitElectronicDocument.php](file:///d:/TiomiguelonGgs/Documents/BRUCE%20FIRE/BruceFireSacv2/app/Actions/Billing/EmitElectronicDocument.php) y [ComprobantePdfService.php](file:///d:/TiomiguelonGgs/Documents/BRUCE%20FIRE/BruceFireSacv2/app/Services/Billing/ComprobantePdfService.php). Se actualizaron los totales desglosados en [comprobante.blade.php](file:///d:/TiomiguelonGgs/Documents/BRUCE%20FIRE/BruceFireSacv2/resources/views/pdf/comprobante.blade.php).
    2. _Concurrencia SUNAT:_ En [ReserveNextCorrelativo.php](file:///d:/TiomiguelonGgs/Documents/BRUCE%20FIRE/BruceFireSacv2/app/Actions/Billing/ReserveNextCorrelativo.php), se reemplazó la creación directa por `firstOrCreate` para blindar contra race conditions en la inicialización de series.
    3. _Seguridad Multi-Sede (Campo):_ En [DeliveryController.php](file:///d:/TiomiguelonGgs/Documents/BRUCE%20FIRE/BruceFireSacv2/app/Http/Controllers/TecnicoCampo/DeliveryController.php) e [InspectionController.php](file:///d:/TiomiguelonGgs/Documents/BRUCE%20FIRE/BruceFireSacv2/app/Http/Controllers/TecnicoCampo/InspectionController.php), las estadísticas y tarjetas se acotaron con `accessibleToTechnician($user)`.
    4. _Performance N+1 (Campo):_ En [CollectionController.php](file:///d:/TiomiguelonGgs/Documents/BRUCE%20FIRE/BruceFireSacv2/app/Http/Controllers/TecnicoCampo/CollectionController.php), se añadió `'events'` al `with()` eager loading y se resolvió en memoria, eliminando 15 consultas por carga de página.
    5. _Resiliencia Taller (500):_ En [ExecutionController.php](file:///d:/TiomiguelonGgs/Documents/BRUCE%20FIRE/BruceFireSacv2/app/Http/Controllers/TecnicoPlanta/ExecutionController.php), se cargó `'service:id,nombre'` y se aplicó navegación null-safe para `tipo_servicio`.
    6. _Resiliencia Gerente (500):_ En [CashRegisterConsolidatedController.php](file:///d:/TiomiguelonGgs/Documents/BRUCE%20FIRE/BruceFireSacv2/app/Http/Controllers/Gerente/CashRegisterConsolidatedController.php), se protegió `$cr->vendedor?->id` contra usuarios eliminados.
    7. _Resiliencia Almacén (404):_ En [TransferController.php](file:///d:/TiomiguelonGgs/Documents/BRUCE%20FIRE/BruceFireSacv2/app/Http/Controllers/Almacen/TransferController.php), se reemplazó `findOrFail` con fallback seguro ante ausencia de sedes activas de almacén.
    8. _Consistencia KPIs Ventas:_ En [SaleController.php](file:///d:/TiomiguelonGgs/Documents/BRUCE%20FIRE/BruceFireSacv2/app/Http/Controllers/Vendedor/SaleController.php), se integró el filtro de vendedor restringido (`$soloDe`) y sede para que los contadores coincidan con la tabla mostrada.
    9. _Mascota Chispa en Errores 404, 500 y 403:_ Se crearon las vistas Blade de respaldo con la mascota Chispa ([resources/views/errors/layout.blade.php](file:///d:/TiomiguelonGgs/Documents/BRUCE%20FIRE/BruceFireSacv2/resources/views/errors/layout.blade.php), [404.blade.php](file:///d:/TiomiguelonGgs/Documents/BRUCE%20FIRE/BruceFireSacv2/resources/views/errors/404.blade.php), [500.blade.php](file:///d:/TiomiguelonGgs/Documents/BRUCE%20FIRE/BruceFireSacv2/resources/views/errors/500.blade.php), [403.blade.php](file:///d:/TiomiguelonGgs/Documents/BRUCE%20FIRE/BruceFireSacv2/resources/views/errors/403.blade.php) y [minimal.blade.php](file:///d:/TiomiguelonGgs/Documents/BRUCE%20FIRE/BruceFireSacv2/resources/views/errors/minimal.blade.php)), y se ajustó [bootstrap/app.php](file:///d:/TiomiguelonGgs/Documents/BRUCE%20FIRE/BruceFireSacv2/bootstrap/app.php) para asegurar que Chispa aparezca siempre.
    10. _Sidebar/Slider sagrado:_ Se mantiene intacto al 100% el diseño del slider curvo cóncavo (`.bf-nav-active`).

## [2026-10-05] — Auditoría de salida a producción (2.ª pasada)

- **Verificación de la 1.ª pasada:** `composer ci:check` fallaba (formato de `docs/ai/*.md` y 5 errores de PHPStan). Corregidos.
- **Errores corregidos:**
    1. PDF de notas de crédito/débito imprimía todas las líneas de la venta con el importe de la nota; no coincidía con el XML enviado a SUNAT. Ahora muestra la misma línea que el XML. Prueba nueva en `tests/Feature/CreditDebitNoteTest.php`.
    2. `ReserveNextCorrelativo`: el `firstOrCreate` no cerraba la carrera al estrenar una serie; se usa `createOrFirst` y se vuelve a bloquear la fila.
    3. Traslados (almacén): la pantalla se rompía si no había almacén activo; ahora muestra un estado vacío.
    4. Recojos (campo): se cargaban todos los eventos de cada orden; ahora solo los de `recojo_campo`.
    5. `ServiceOrder`: PHPDoc declara `service` y `service_id` como anulables (la columna lo es).
    6. Revertido el null-safe en cajas consolidadas: `vendedor_id` no es anulable (era código muerto).
- **Hallazgo crítico pendiente de decisión:** "Eliminar cuenta" + `cascadeOnDelete` sobre `users` y `clients` borra ventas y comprobantes SUNAT.
- **Producción:** `composer audit` y `npm audit` sin vulnerabilidades; `npm run build` compila.

## [2026-10-05] — Se quita "Eliminar cuenta"

- Decisión del usuario: no arriesgar datos fiscales. Se eliminó el botón de Ajustes → Perfil, la ruta `DELETE settings/profile`, `ProfileController::destroy` y `ProfileDeleteRequest`.
- La prueba ahora exige que borrar la propia cuenta responda 405 y que el usuario siga existiendo.
- Corrección: la migración de protección no hacía falta. `2026_09_28_014309_proteger_historial_legal_y_financiero` ya cambió esas claves a `restrict` (la auditoría solo miró las migraciones de creación). Se añadió una prueba que borra un usuario y un cliente con historial y verifica que la base lo impide.

## [2026-10-05] — Arranque de redes: perfil y guion del video de presentación

- Bruce Fire empieza en redes desde cero, en Trujillo. Se creó `../Contenido/` con el perfil de marca, los ganchos y el calendario.
- Decisiones: en redes se usa el isotipo y no el logo horizontal; Chispa (la mascota) presenta la solución; el primer video es 100 % generado (no hay grabaciones), con el gancho "el extintor que hace pfff" y dos variantes para probar.
- Pendiente del cliente: número de WhatsApp, confirmar recojo y entrega, y la acreditación ASNEEX.

## [2026-10-05] — Video de presentación armado (versión con imágenes provisionales)

- Proyecto HyperFrames en `../Contenido/piezas/2026-10-05-video-presentacion/video/`: 9 escenas, 38 s, 9:16. Pasa `hyperframes check` (0 errores; contraste AA 18/18).
- La generación de video con IA necesita un plan de pago en el conector creativo; las imágenes se generarán en ChatGPT con `prompts-imagenes.md`. Wan2GP no corre en esta PC (AMD de 4 GB).
- Falta: imágenes reales, voz en off, música y el render final.

## [2026-10-05] — Video v2: motion graphics con Chispa

- El usuario vio la v1 "básica" y pidió estilo motion graphics (referencias de TikTok hechas con HyperFrames). Se rehízo: Chispa y "el Vencido" generados en Gemini (fondo verde recortado localmente), escenas ilustradas, texto cinético, tarjetas, certificado con QR animado y 36 efectos de sonido. 31 s, 9:16. `hyperframes check` pasa (contraste AA 21/21).
- La v1 quedó en `../Contenido/piezas/2026-10-05-video-presentacion/index-v1.html`.
- Falta: música de fondo (cortes al golpe) y render final.

## [2026-10-05] — Voz, música y efectos

- Locución: Jair Solano (ElevenLabs, acento peruano), elegida entre 5 voces. Tiempos por palabra con ElevenLabs Scribe (Whisper no está instalado en la PC). Escenas reajustadas a la voz: 39 s, con pausas para el pfff y el sello.
- Música: 2 opciones de Eleven Music (`assets/musica/bgm-a.mp3`, `bgm-b.mp3`); efecto pfff generado. `hyperframes check` pasa (AA 25/25).
- Pendiente: elegir música, clips de Veo (prompts entregados) y render final.

## [2026-10-05] — Render final del video de presentación

- 3 clips de Veo (Gemini) con fondo verde recortado en ffmpeg (WebM con transparencia): pfff en el gancho, Chispa aterrizando (1.7x) y Chispa saludando en el cierre.
- Render: `../Contenido/piezas/2026-10-05-video-presentacion/video/renders/bruce-fire-presentacion-9x16.mp4` (1080x1920, 30 fps, 39 s, H.264 + AAC, volumen medio -17 dB, pico -1.5 dB). `hyperframes check` pasa.
- Siguiente: versión 1:1 para LinkedIn, captions por red y crear los perfiles.

## [2026-10-05] — Gancho más claro

- El usuario notó que el soplido parecía que el extintor sí funcionaba. Nuevo gancho con 2 clips de Veo: aprieta y no sale nada (clic, clic) → primer plano del manómetro cayendo al rojo con "SIN PRESIÓN" → cara triste con "…Y NADA.". Se quitó el círculo repetido de la escena 2.
- Final copiado a `Downloads/Bruce-Fire-presentacion-9x16.mp4` (39 s, 1080x1920, H.264 + AAC).

## [2026-10-05] — Manómetro con transiciones suaves

- El usuario sintió brusco el inserto del manómetro. Ahora: zoom al Vencido, la tarjeta crece desde su manómetro, 1.65 s a velocidad normal, "SIN PRESIÓN" entra deslizándose y sale con fundido hacia la cara triste. Video corrido 0.8 s (39.8 s). Copiado a Descargas.

## [2026-10-05] — Auditoría del video y 8 correcciones

- Revisión de 28 momentos (transiciones). Corregido: Chispa duplicada en el segundo 8, cabeza cortada del héroe, "Entonces" sin texto, rayos sueltos, pin tapado, celular asomado, extintores cortados y válvula asomada. Render final de 39.8 s copiado a Descargas.

## [2026-10-06] — Auditoría por roles en equipo (Claude, GPT y Antigravity)

- Reparto e instrucciones comunes: `docs/ai/auditoria/BRIEF.md`. Informes en `docs/ai/auditoria/`.
- Corregido: 2 enlaces 404 del técnico de planta (`resources/js/pages/tecnico-planta/dashboard.tsx`).
- Hecho: `tecnicos.md` (Claude), `vendedor.md` (GPT, 19 hallazgos). Antigravity sigue con `almacen-gerente.md`.
- Contexto de Obsidian: proyecto universitario de la UPN; alcance fijado en el DRS v3.1 (122 REQ). Los cambios nuevos necesitan su REQ y su historia en el backlog.
- Verificado por Claude en el código (no solo en el informe de GPT):
    - **V01:** Greenter envía siempre a `e-beta`. `billing.sunat.beta` no se usa y no hay `setService`. Bloquea la salida a producción.
    - **V04:** una línea serializada admite cantidad mayor a 1, pero descuenta solo 1 unidad.
    - **V05:** `aplica_igv` no se usa al facturar; todo sale gravado con afectación 10.
- Antigravity entregó `almacen-gerente.md`. Reporte unificado: `docs/ai/auditoria/REPORTE_CONSOLIDADO.md` (11 críticos, 15 importantes, 3 falsos positivos descartados). El usuario pidió no cambiar código hasta revisar el reporte.
- Manuales SUNAT (factura, boleta, NC, ND y resumen diario) convertidos a Markdown en `documentos/sunat/`, con `README.md` como índice. Comparación con el código en `docs/ai/auditoria/facturacion-sunat.md`. Verificado: NC con motivos 04 y 05 sobre boleta (prohibido), hora de emisión 00:00:00 en el XML y URL de producción equivocada en la guía interna.

## [2026-10-06] — Normativa SUNAT vigente y documentación unificada

- Nombre oficial del proyecto: **Sistema web para la gestión de ventas en BRUCE FIRE S.A.C.** SUNAT solo en beta hasta validar todo.
- Investigada la normativa vigente: plazos (factura 3 días, boleta 5 o 7), comunicación de baja (7 días, solo comprobantes no entregados), catálogo 09 del 01 al 13, R.S. 000048-2026 (ND 13 para penalidades; una sola referencia por nota), código de producto SUNAT postergado al 1/01/2027, GRE obligatoria (la tolerancia venció el 31/08/2026) y detracción 020 al 12 %.
- Estructura nueva de `docs/ai/`:
    - `PROYECTO.md`: qué es, decisiones y mapa de documentos.
    - `PLAN.md`: un solo backlog, fases A a H.
    - `AUDITORIA.md`: el único reporte.
    - `SUNAT.md`: normativa, estado y checklist de beta a producción.
    - `anexos/`: los informes por rol y la investigación.
    - `docs/ai/auditoria/` se eliminó; su contenido pasó a `AUDITORIA.md` y `SUNAT.md`.
- Corregida en `docs/FACTURACION_GREENTER_SUNAT.md` la URL de producción (era la de consulta). Las guías de `documentos/sunat/guia-*.md` quedan excluidas del formateador (`vite.config.ts`).

## [2026-10-06] — GRE, documentación unificada y auditoría transversal

- GRE investigada y diseñada en `docs/ai/SUNAT.md` §5:
    - Es obligatoria: la tolerancia de la guía impresa venció el 31/08/2026.
    - Bruce Fire la necesita en 4 casos: venta (01), recojo y devolución de extintores en recarga (13, por confirmar) y traslado entre sedes (04).
    - Se envía por API REST con OAuth y responde con ticket.
    - Greenter instalado: v4.3.1 (2021), que no trae la GRE. La versión actual es la v5.3.0 (2026) más `greenter/gre-api`. Requiere aprobación porque cambia dependencias.
- `documentos/` unificado:
    - El Documento Maestro queda como especificación (§1–75) con una fe de erratas al inicio.
    - §76–90 y `00_PLAN_POR_FASES.md` pasan a `documentos/archivo/`.
    - `01_MODULOS_ROLES_PERMISOS.md` queda marcado como parcialmente obsoleto.
- Auditoría transversal con 3 agentes (seguridad; clientes, certificados e IA; experiencia de uso), con sus IDs X1 a X18 en `AUDITORIA.md` y la comparación general con sistemas grandes.
- Verificado por Claude:
    - X1: la ficha del cliente no filtra por sede los equipos ni los certificados.
    - X2: las firmas están en el disco público.
    - X6: la tarea diaria vence cotizaciones aceptadas.
- Descartado: "los certificados no se pueden corregir".
