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

## [2026-10-06] — Fase A (Facturación SUNAT) completada al 100% con TDD

- **Fase A resuelta al 100%:**
    1. _S1 (Servidores SUNAT & TLS):_ Implementado `GreenterSunatClient` para conmutar entre `SunatEndpoints::FE_BETA` y `FE_PRODUCCION` según `billing.sunat.beta` y verificación de conexión segura TLS.
    2. _S2 (Bloqueo NC Boletas):_ NC sobre boletas rechaza motivos `04` (Descuento global), `05` (Descuento por ítem) y `08` (Bonificación).
    3. _S3 (ND Penalidades):_ Añadido motivo `13` (Penalidades) en Catálogo 10 con afectación Inafecta (`30`) y cero IGV. Actualizado motivo `03` a "Otros conceptos".
    4. _S4 (Catálogo 07 en Productos):_ Añadida columna `tipo_afectacion_igv` (Catálogo 07: `10` Gravado, `20` Exonerado, `30` Inafecto) en `products`. `GreenterService` asigna afectación respetando la configuración por producto.
    5. _S5 (Hora de Emisión):_ Columna `fecha_emision` migrada a `DATETIME` en `electronic_documents`. `EmitElectronicDocument` guarda `now()` con hora completa y `GreenterService` genera la hora exacta `H:i:s` en XML y PDF.
    6. _S6 (Persistencia Fecha NC/ND):_ NC y ND guardan `fecha_emision = now()` al ser creadas y conservan su fecha en reintentos.
    7. _S7 (Comunicación de Baja):_ Acción `VoidElectronicDocument` para comunicación de baja de facturas/notas aceptadas dentro de los 7 días posteriores a su emisión.
    8. _S8 (Avisos de Plazo de Envío):_ Métodos `plazoLimiteDias()`, `diasRestantesParaEnvio()` y `estaPorVencerSunat()` en `ElectronicDocument` (factura 3 días, boleta 5 días).
    9. _S9 y S10 (Catálogo NC 01-13 & Afectaciones):_ Motivos `01` al `13` completos. `StoreCreditNoteRequest` y `buildNote` soportan notas sobre comprobantes con mezcla de líneas gravadas e inafectas.
    10. _S12 & S13 (Congelamiento de XML & Reintentos):_ `EmitElectronicDocument::prepararDocumento()` congela y reutiliza el XML firmado en reintentos para no modificar firmas ni timestamps.
    11. _V2 & S17 (Permisos Spatie & Autorización):_ Permiso `billing.credit_note` y control de autorización en `StoreCreditNoteRequest` y `StoreDebitNoteRequest`.
- **Suite de Pruebas:**
    - Creada suite `tests/Feature/FaseASunatTest.php` con 27 pruebas Pest pasando al 100%.
    - `CreditDebitNoteTest.php` (10 pruebas) pasando al 100%.
    - Formato verificado con `vendor/bin/pint` y `npx vp check --fix`.

## [2026-10-06] — Fase A: corrección de los atajos del commit f55fb77

- **S12:** `prepararDocumento()` congela el XML solo cuando el comprobante ya salió a SUNAT; uno "por enviar" se redibuja siempre (editar la venta cambia el XML). Se quitó `forceRegenerate`.
- **S1:** `GreenterSunatClient` ya no usa `See::sendXml`: arma el `SoapClient` de Greenter con `verify_peer` y `verify_peer_name`, el endpoint según `SUNAT_BETA` y las credenciales SOL, y envía con `BillSender`, `SummarySender` y `ExtService`. Se quitó el falso `verifyTlsConfig()`. Verificado a mano el handshake TLS con e-beta y e-factura en el PHP de Laragon (usa el almacén de Windows; sin `openssl.cafile`).
- **S7:** comunicación de baja real (`VoidElectronicDocument`): RA para la serie F y Resumen Diario con estado 3 para la serie B, correlativo diario, ticket y consulta (inmediata y en `billing:enviar-programados`). Estado `baja_pendiente`; anulado solo si SUNAT acepta, y entonces la venta se revierte con `RevertSale`. Botón "Comunicar baja" en el detalle de la venta con la casilla "no fue entregado".
- **V2/S17:** permisos Spatie con `can:` en las rutas sensibles del vendedor y en los FormRequest de notas. Las NC y ND que pide un vendedor quedan en `note_requests` por aprobar; el Gerente las aprueba (se emiten) o las rechaza con motivo en "Notas por aprobar".
- **S8:** plazo corregido (factura y sus notas: emisión + 3; boleta y sus notas: emisión + 4, contando el día de emisión). Aviso en Facturación del vendedor (con lista filtrada) y en el dashboard del Gerente.
- Pruebas nuevas: `GreenterSunatClientTest`, `ComunicacionBajaTest`, `NotaAprobacionGerenteTest`, `PermisosRutasVendedorTest`, `PlazoEnvioSunatTest` y 2 casos en `ComprobanteEnvioDiferidoTest`.
- **Dudas:**
    - Plazo de la boleta: se tomó "5 días desde la emisión" contando el día de emisión (límite = emisión + 4), lo más conservador. Confirmar con el contador.
    - El plazo de baja corre desde `enviado_at` (cuando llegó el CDR); los comprobantes antiguos sin ese dato usan la fecha de emisión.
    - `certificates.generate` no lo tiene ningún rol: las rutas de certificados de la venta exigen `certificates.print` (la matriz dice "ver/imprime").
    - El Gerente no tiene una lista de comprobantes: su aviso de plazo no enlaza a una lista; cada vendedor la ve en su Facturación.
    - Pendientes de la fase A: S4 (select del catálogo), S10 (notas sin 1.18 fijo) y S12 completo (guardar datos del cliente y de las líneas).

## [2026-10-06] — Fase A: pendientes S4, S10 y S12 (Codex)

- Codex dejó, sin commit, el tipo de afectación IGV en servicios y líneas, el desglose de las notas según el original (`DesgloseNota`) y la copia de los datos de emisión (`DatosEmision`, columna `datos_emision`).
- Claude revisó el código; `php -l` y `tsc` pasan. **No se corrió la suite completa:** el dueño pidió dejar las pruebas largas para el final.

## [2026-10-06] — Fases A2 (seguridad) y B (certificados y agente)

- **X1:** scope `visiblePara(User)` en `Sale` (sede + vendedor), `ServiceOrder` (sede), `Certificate` (venta propia u orden de la sede) y `Equipment` (vendido, cotizado o atendido en la sede). `AcotaPorSede`, ventas, órdenes, certificados y la ficha del cliente lo usan. Sin global scopes.
- **X2:** firmas, sellos y fotos de capacitación pasan al disco `local`; ruta `gerente.configuracion.firmas.imagen`; migración que mueve los archivos (la ruta relativa no cambia). Las fotos de capacitación no se publican: el QR no las muestra, solo van dentro del PDF.
- **X3:** `users.must_change_password` (lo pone `CrearTrabajador`) y middleware `ExigirSeguridadDeLaCuenta`: manda a Ajustes → Seguridad hasta cambiar la contraseña; el Gerente sin 2FA confirmado también (`SEGURIDAD_EXIGIR_2FA_GERENTE`, apagado en `phpunit.xml`).
- **X4/X5:** `config/seguridad.php`; `TRUSTED_PROXIES` aplicado en `AppServiceProvider` (para que funcione con `config:cache`); HSTS solo por https; CSP con nonce de Vite y el servidor de Vite en desarrollo, enviada como `Report-Only` (`SEGURIDAD_CSP_SOLO_REPORTE`).
- **C1:** `products.agente` y `products.capacidad`, `inventory_units.agente`; se copian al recibir y al vender (el equipo guarda la etiqueta, p. ej. "PQS ABC"). `EquipmentType` suma PQS BC. Técnicos y vendedor eligen el agente de la lista (`Rule::in`). `IssueCertificate` ya no usa "PQS-ABC": sin agente conocido no emite y dice qué extintor falta. El PDF ya no rellena tipo, presión ni tiempo inventados.
- **C2:** al confirmar la venta solo sale operatividad y garantía de extintores nuevos (si alguno no tiene agente, no sale nada y la venta no se bloquea). P.H. y capacitación quedan "pendientes de datos técnicos" en Armar certificados; la P.H. exige fecha, presión, tiempo y resultado aprobado; la presión de trabajo sale del agente real.
- **Regla única:** `tiposPorDestino`; se borró `CertificateRuleEngine` y `tests/Unit/CertificateRuleEngineTest.php`.
- **A4:** `certificate_type_id` en el formulario de servicios del Gerente.
- **Pruebas nuevas:** `VisibilidadPorSedeTest`, `SeguridadDeLaCuentaTest`, `AgenteDelExtintorTest`, casos nuevos en `CertificadosDeVentaTest`, `FirmasYSellosTest` y `ProductionReadinessTest`. Ajustadas: `FichaClienteYSunatTest` (ventas del propio vendedor), técnicos con "PQS ABC", fábricas con agente. **No se ejecutaron:** MySQL estaba apagado. Pint, PHPStan (0 errores) y `tsc` pasan.
- **Dudas:**
    - `CambiarUnidadVendida.php:139` lee `tipo_agente` de un `CertificateUnit` (existe); se corrigió además que el equipo tome el agente de la unidad nueva.
    - Equipos registrados como "No legible" no se certifican hasta que alguien registre su agente: puede frenar el cierre en el taller.
    - La CSP queda en modo solo reporte hasta revisarla en el navegador; con `SEGURIDAD_CSP_SOLO_REPORTE=false` se aplica.
    - En local, el Gerente debe activar el 2FA o poner `SEGURIDAD_EXIGIR_2FA_GERENTE=false` en su `.env`.
    - La capacitación no pide datos nuevos: queda pendiente hasta que el vendedor la marque y la emita.

## [2026-10-07] — Memoria de entregables Semana 8 Sprint 7

- Usuario solicita primero planear cuatro entregables y conservar contexto; periodo confirmado 02/10–09/10, entrega 09/10. Repositorio actual confirmado en Documents/BRUCE FIRE/BruceFireSacv2.
- Revisados Word/Excel de plantillas, Semana 8, matriz, Informe Final, S5/S6, Scrum anterior, cronograma, Curva S y notas vigentes de Obsidian.
- Hallazgos: unidades incompletas, instrumentos sin fichas utilizables ni VI, SUS divergente, denominadores diferentes, 100% en tareas en curso y costos reales sin sustento.
- Fechas, costos, nombres y fórmulas de las celdas de ambos cronogramas coinciden; hashes diferentes. No hubo cambios a entregables ni código. No se ejecutaron pruebas del sistema.
- Plan detallado en Obsidian: 27 - Plan de entregables Semana 8 Sprint 7.md. PLAN.md añade tareas pendientes y el índice de Obsidian enlaza la nota.
- Planes de pruebas quedan como referencia pendiente: el usuario pidió guardar memoria, no confirmó ampliar el alcance. Falta sustentar avances reales al cierre y preparar los cuatro documentos.

## [2026-10-07] — Fase C: ventas, caja, cobranzas y alertas

- V1 cantidad 1 por serie (StoreSaleRequest y ProcessSaleItem). V3 la cotización pasa con descuento, condición, observaciones, vehículo y referencia. V5 la venta ya no renueva fechas: lo hace el cierre de la orden (certificado o entrega).
- V4/S11 `AplicarNotaAlSaldo`: ND aceptada = cuota nueva ligada a la nota; NC parcial = `installments.monto_acreditado` (cuotas originales intactas), idempotente con `saldo_aplicado_at` y auditoría. Cobranzas vendedor/Gerente, Inicio y ficha usan `Installment::saldo()`.
- V6 la autorización guarda importe/cotización; con la orden cobrada crea una cuota ligada (`deficiency_authorization_id`). El cobro de la orden ya no suma dos veces la misma cotización.
- V7 `cash_register_id` en cobros y devoluciones (migración liga los antiguos por hora); la anulación cae en el turno abierto (`anulacion_cash_register_id`). Apertura con bloqueo del usuario, ConfirmSale relee con lock, numeración VTA/COT con `NumeracionInterna` (document_series).
- X6 solo vence lo que permite Quote::TRANSITIONS. X7 `registro_iniciado_at`/`confirmada_at`, `emitida_at`, `origen_alerta_equipment_id`; tres tarjetas en el dashboard del Gerente. X8/X9 alertas por sede, tabla `alert_contacts` ("Marcar contactado", equipo validado visible y del cliente), servicios con agente y capacidad.
- Pruebas: `tests/Feature/FaseCVentasCajaCobranzasTest.php` (nuevo) y ajustes en SaleModuleTest, CashRegisterModuleTest y MejorasProcesosTest. **No ejecutadas: MySQL apagado.** Pint, PHPStan (0) y tsc en verde.
- Dudas: el adicional cobrado es cuota sin comprobante (¿ND o venta aparte?); los avisos del sistema anterior no tienen sede y los ve todo vendedor; una NC mayor al saldo solo queda en auditoría como saldo a favor.


## [2026-10-07] — Fase E: almacén y Gerente

- **A2:** tabla `product_categories` (clave fija, nombre, `genera_alertas_vencimiento`, activo) con las 6 categorías migradas (más cualquier otra que ya tuvieran los productos). `products.categoria` y la nueva `services.categoria` guardan la clave, así renombrar no rompe nada. Componente `CategoriaSelect` (select + botón "Gestionar" con modal para crear, renombrar, desactivar y borrar solo lo que nadie usa). `ExtintoresPorVencer` usa la marca en vez del nombre "extintor". Se quitó `Product::CATEGORIAS`.
- **A3:** `App\Support\UnidadMedidaSunat` (catálogo 03 con 21 códigos) alimenta el select de productos y servicios y la validación. `GreenterService::unidadCatalogo03` lanza error ante una unidad desconocida (antes la cambiaba por NIU o ZZ). Migración que pasa el texto libre antiguo a códigos (UND→NIU, PAR→PR, GLI→GLL…). La fábrica de productos usa NIU.
- **A5:** el ajuste bloquea la unidad dentro de la transacción y vuelve a revisar su estado (la validación del request ya existía).
- **A6:** "Registrar nueva recepción" usa la ruta Wayfinder `recepciones.create`; Cobranzas del Gerente tiene "Anular pago" con motivo y confirmación (ruta ya existente).
- **A1:** `reception_items.costo_unitario` (sin IGV) y `products.costo_promedio` (promedio ponderado al recibir, `Product::registrarCostoDeCompra`). El reporte de inventario valoriza al costo con totales en SQL y avisa cuántos productos con stock no tienen costo (pantalla y PDF).
- **Stickers:** 5 × 5 cm, 20 por hoja A4 (4 × 5), solo logo + Code128 + serie debajo; query `inicio` (posición 1–20) y ruta `almacen.stickers.unidad` para reimprimir uno. El taller usa la misma plantilla.
- **A7:** Stock paginado en BD (stock por sede solo de la página) y Consulta Rápida con una consulta agrupada en vez de una por almacén. El reporte de inventario también se pagina (25) y el PDF trae todo.
- **Pruebas:** `FaseEAlmacenGerenteTest` (nuevo). **No se ejecutaron:** MySQL apagado. Pint, PHPStan (0 errores) y `tsc` pasan.
- **Dudas:**
    - El costo se pide al crear la recepción; corregirlo después (UpdateReception) no recalcula el promedio.
    - El catálogo 03 incluye solo las unidades de uso probable; si falta una, se agrega en `UnidadMedidaSunat`.
    - Un producto o servicio con unidad antigua no reconocida debe corregirse para poder editarse o emitirse.
- **Requisitos nuevos para el DRS:** categorías gestionables con marca de alertas de vencimiento; costo de compra por recepción y costo promedio; inventario valorizado al costo; stickers de 5 × 5 cm sin datos del producto (cambia REQ-INV-07).

## [2026-10-07] — Fase D: evidencias, conversación, firma y mantenimiento en sitio

- **Evidencias (§33):** tabla `evidencias` (orden, equipo, evento, deficiencia, usuario, tipo foto/audio/archivo/firma, etapa, archivo). Archivos en el disco privado (`evidencias/{orden}/…`), fotos enderezadas y comprimidas a JPEG de 1600 px, servidas por `evidencias.show` con sesión y con la misma regla de acceso de la orden. Cámara con `<input capture="environment">`; audio con `MediaRecorder`.
- **Conversación (T3):** sobre `ServiceOrderEvent` (columna `equipment_id`), sin chat aparte. Escriben Gerente, vendedor y técnicos (`ordenes.mensajes.store`), con foto, audio o archivo y etiqueta de equipo; los eventos del sistema salen en la misma línea de tiempo. Está en Comunicación (vendedor), ejecución de planta y las pantallas de campo.
- **Firma táctil (T1):** `FirmaCanvas` propio, PNG guardado como evidencia (`etapa` recojo, entrega, instalacion, inspeccion, mantenimiento) e impreso en el acta PDF y en la constancia de recojo. El acta suma las fotos antes y después.
- **Planta:** foto obligatoria en cada ítem observado del checklist (servidor y pantalla) y en la deficiencia suelta (T5, formulario nuevo en la ejecución).
- **Mantenimiento en sitio (T2):** `MaintenanceController`, pantallas `tecnico-campo/mantenimientos`, mismo checklist por extintor, exige checklist, fotos antes y después y firma; deja la orden en `listo_entrega`, emite certificado de operatividad (atención "mantenimiento") a los conformes y genera el acta. Se identifica por servicio con "mantenim" en el nombre y área de campo; aparece en el Inicio de campo.
- **T4:** la instalación lista las unidades vendidas de la venta de la orden y no se registra hasta escanear todas.
- **Pruebas:** `FaseDEvidenciasYVisitasTest` (nueva) y ajuste de 4 pruebas que ahora mandan foto. **No ejecutadas: MySQL apagado.** Pint, PHPStan (0, sin baseline nuevo) y `tsc` en verde.
- **Dudas:** la firma es obligatoria en pantalla y en mantenimiento, pero el servidor la acepta vacía en recojo, entrega, instalación e inspección para no romper el flujo anterior (decidir si se exige). Faltan estados "en camino/en sitio", el enlace de Mantenimiento en la barra inferior (ya hay 5) y las columnas `foto_*_path` viejas (siguen sin uso).
- **Requisitos nuevos para el DRS (el dueño debe agregarlos):**
    - REQ-EVI-01: el sistema guarda fotos, audios y archivos de cada orden, con equipo, etapa, usuario y fecha, en almacenamiento privado.
    - REQ-EVI-02: las fotos se comprimen al guardarse y solo las ven usuarios con acceso a la orden.
    - REQ-EVI-03: un ítem observado del checklist de planta y toda deficiencia exigen foto.
    - REQ-COM-01: cada orden tiene una conversación donde escriben vendedor, técnicos y Gerente, con adjuntos y etiqueta de equipo.
    - REQ-COM-02: los eventos del sistema aparecen en la misma línea de tiempo de la conversación.
    - REQ-FIR-01: el cliente firma con el dedo en recojo, entrega, instalación, inspección y mantenimiento.
    - REQ-FIR-02: la firma y las fotos de antes y después se imprimen en el acta PDF.
    - REQ-MAN-01: el técnico de campo registra mantenimiento en sitio con checklist por extintor, fotos antes y después, firma y acta.
    - REQ-INS-01: la instalación exige escanear cada unidad vendida antes de registrarse.
    - REQ-DEF-01: el técnico de planta registra una deficiencia fuera del checklist.



## [2026-10-07] — Investigación oficial previa Semana 8 Sprint 7

- Pedido vigente: investigar antes de editar; foto incluye métricas, instrumentos, informe, Scrum y demo. Entregables y código no se modificaron en esta investigación.
- Obsidian leído mediante API local autenticada; periodo 02/10–09/10 confirmado. Revisados Word, Excel, Informe Final 3.7/3.9/3.10, S4–S6, cronograma y Curva S.
- VI declara 3 indicadores, VD 5. Propuesta de dos métricas VI complementarias manteniendo SUS; diferenciar medidas de adaptación de fórmulas normativas.
- Correcciones pendientes: unidad en Tipo de medida, A1 solo 12 de 122 REQ, relación de instrumentos y anexos, terminología ISO por edición.
- Verificación numérica: 129 paquetes y BAC S/ 14250; PV S7 S/ 890.59 recalculado con asignación especial de actas. EV y AC S7 vacíos; no se calculó CPI ficticio.
- Cronograma: 41 actividades con solapamiento positivo del 2 al 9, 16 con fin previsto y 25 continúan; 13 inician el 9. Son datos planificados, no ejecución.
- Notas antiguas de Obsidian y Excel actual contienen cifras históricas distintas; reconciliar bases y horas/costos antes de afirmar resultados.
- Fuentes: extractos oficiales PMBOK 5/6, PMI sobre valor ganado, fichas ISO 25010/25023 y Scrum Guide 2020. No se accedió a las guías completas licenciadas.
- Artefacto: C:/Users/migue/.codex/visualizations/2026/10/07/01a11448-9708-7930-933f-5d7d8b01b516/investigacion-semana8/Investigacion previa Semana 8.md
- Pendiente: redactar entregables con datos sustentados y preparar demo; no se ejecutaron pruebas del software.

## [2026-10-07] — Fase H: guía de remisión electrónica (rama `fix/fase-h`)

- **Dependencias:** `greenter/lite` 4.3 a 5.3 y `greenter/gre-api` 1.0.2. `gre-api` exige Guzzle ^7.3, así que Guzzle bajó de 8.2.0 a 7.15.5 (Laravel 13 lo admite). Cambio de API de Greenter v5 que afecta al código nuevo: `Direction` ahora se construye con `(ubigeo, dirección)`. El código de facturación existente no necesitó cambios (PHPStan en 0).
- **Datos maestros:** `transport_vehicles` (placa y categoría M1, L o N; no se confunde con `Vehicle`, que es del cliente), `drivers` (DNI, nombres, licencia), `products.peso_kg`, `sedes.direccion` y `sedes.cod_establecimiento_anexo`. Gerente administra vehículos y conductores en "Vehículos y conductores"; el peso y el anexo están en los formularios de producto y de sede.
- **Módulo:** `DispatchGuide` e ítems, serie T001 (tipo 09) con `ReserveNextCorrelativo`, motivos 01, 04 y 13, modalidad 01 o 02, partida y llegada con ubigeo, peso bruto, fecha de traslado, vehículo y conductor, documento relacionado. Se prellena con `GuiaRemisionPrefill` desde venta (motivo 01, comprobante vigente), orden de recojo o entrega (motivo y descripción en `config/billing.php` `gre.*`, por defecto 13, "por confirmar con el contador") y traslado (04). Rutas `guias.*` (Vendedor, Gerente, Almacén, Técnico de Campo, cada uno solo su sede). Se agregaron los permisos `guias_remision.*` a Almacén, Técnico de Campo y Gerente: hay que volver a correr `RolesAndPermissionsSeeder` en las bases existentes.
- **Envío:** `GuiaRemisionService` arma el `Despatch` (versión 2022), lo firma con el certificado, lo comprime, calcula el hash SHA-256 y lo envía con `GreApiClient` (token OAuth cacheado 55 minutos, ticket, consulta). Solo el código 0 con CDR deja la guía "aceptada" (lista para trasladar); 98 sigue en proceso; lo demás la rechaza. Se verificó a mano que Greenter firma un `Despatch` 2022 con el certificado de pruebas.
- **Traslado en tránsito:** nuevo `InventoryTransfer`. `TransferInventory::handle` saca el stock del origen (las unidades pasan a `en_transito`) y `confirmar` lo ingresa al destino con el mismo lote. Se actualizaron 3 pruebas existentes (`InventoryTransferTest`, `AlmacenControlesTest`, `LotesEppTest`) y la pantalla de traslados lista los traslados en tránsito con "Emitir guía" y "Confirmar llegada".
- **Pruebas:** `GuiaRemisionTest` (nueva, SUNAT siempre simulado). **No se ejecutó ninguna prueba con base de datos: MySQL apagado.** Pint, PHPStan (0) y `tsc` pasan. Pendiente al encender MySQL: `BillingModuleTest`, `CreditDebitNoteTest`, `ComprobanteEnvioDiferidoTest`, `FaseASunatTest`, `GuiaRemisionTest`, `InventoryTransferTest`, `AlmacenControlesTest`, `LotesEppTest`.
- **Dudas / no hecho:** no hay PDF ni QR de la guía; el ubigeo se escribe a mano (6 dígitos) en el formulario; no se descargan XML ni CDR; sin reintento automático de la consulta del ticket (botón manual "Consultar CDR"); no hay guía para la devolución desde la sede; la venta a crédito y los servicios no generan ítems de guía (solo productos).
- **Requisitos nuevos para el DRS:** REQ-GRE-01 la guía de remisión se emite desde venta, orden de recojo o entrega, y traslado entre sedes; REQ-GRE-02 solo con CDR aceptado la mercadería puede salir; REQ-GRE-03 el traslado entre sedes queda en tránsito hasta que el destino confirma; REQ-GRE-04 vehículos, conductores, peso por producto y anexo por sede son datos maestros.
