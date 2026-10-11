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

## [2026-10-07] — Métricas e instrumentos terminados 07/10/2026

- Entregados dos DOCX en Entregables/Semana 8: Métricas de Calidad (12 páginas) e Instrumentos de medición (21 páginas), versión 1.1.

- Cinco métricas del producto vinculadas a ISO, SUS complementario y cinco VD; ocho campos, unidades, fórmulas y criterios completos.

- Trece formatos A1–A13; inventario A1 de los 122 REQ del DRS v3.1, pruebas funcionales, tiempos, SUS, VD, diagnóstico, expertos y piloto.

- Protocolos propuestos: 30 intentos medidos por escenario y 14 días de seguimiento; revisar antes de aplicación.

- Conservada la matriz 3.7 y todas las partes de la plantilla salvo word/document.xml; no se afirman certificación ISO ni resultados medidos.

- QA: 33 páginas revisadas visualmente, fórmulas y denominadores comprobados; Word exportó en solo lectura y Poppler renderizó. LibreOffice del runtime no disponible.

- Versiones anteriores respaldadas en la carpeta de trabajo de esta entrega; SHA de los archivos finales coincide con QA.

- Pendiente: aplicación real, revisión de expertos y piloto; continuar informe, Scrum y demo. No se ejecutaron pruebas del software.

## [2026-10-07] — Aclaración instrumentos de tesis y cinco VI

- Pedido nuevo: exactamente cinco VI y cinco VD, coherentes también en Informe Final; primero comprender, investigar y responder.

- Contrastados ejemplos de tesis UPT/UNE, investigación comercial con Scrum, validez de contenido UDG, guía institucional Huaraz y fuentes oficiales PMI/ISO.

- Confirmado: métricas se definen y aplican durante desarrollo; resultados finales VD requieren C1/C2 reales y comparables.

- Formatos actuales útiles; pendiente añadir fichas técnicas uniformes y separar medición, diagnóstico y formatos de validación.

- Recomendación presentada: retirar PTR como ficha independiente y mantener SUS dentro de cinco VI. Falta aplicar la corrección y concordar Informe Final.

- Investigación guardada en la carpeta de trabajo semana8-metricas-instrumentos-v2. No se modificaron DOCX, Excel ni código en esta investigación.

## [2026-10-07] — Corrección concordante de cinco VI y cinco VD — 07/10/2026

- Instalados tres DOCX: métricas v1.2 en Semana 8, Informe Final en Semana 6 y matriz en Semana 2; originales respaldados y SHA verificados.

- Cinco VI: PRI, PCF, PEU, SUS y TRP. Cinco VD conservados; fórmulas, unidades y metas concordantes entre documentos.

- Informe: 3.7, 3.8, 3.9, 3.10, 5.14 y notas de resultados pendientes; tres dimensiones, alcance de 122 REQ/11 RNF y muestra planificada distinta de 942.

- Ocho instrumentos principales declarados I01–I08; fichas y registros del documento de instrumentos pendientes de elaboración por pedido del usuario.

- Se retiró el plazo no conciliado de catorce días; definir plazo común compatible con C1/C2 antes de medir. Cronograma sin cambios.

- QA: 109 páginas revisadas visualmente con Word/Poppler; diez fichas, 18 ecuaciones nativas, índice, fórmulas y coherencia cruzada comprobados.

- Se conservan todas las partes ZIP excepto el cuerpo XML; resultados, expertos y piloto pendientes. Sin cambios de código ni pruebas del sistema.

- Siguiente paso: elaborar instrumentos; después informe de estado, Scrum y demostración. La consulta de Semana 13 no modifica fechas.

## [2026-10-07] — Correcciones de la auditoría externa (9 hallazgos)

- **Suite rota reparada y dos correcciones previas commiteadas:** `RevertSale` revierte dentro de `DB::transaction` con `lockForUpdate` y sale si la venta ya está anulada; el helper `usuarioConRol` de `FaseDEvidenciasYVisitasTest` pasó a `usuarioDeVisita` (el nombre repetido con firma distinta rompía toda la suite con `Cannot redeclare usuarioConRol()`).

- **1. Doble emisión ante SUNAT:** nuevo `ConsultarCdrSunat` y `SunatClientInterface::consultCdr()` (Greenter `ConsultCdrService` con el cliente TLS). Un comprobante ya enviado nunca se reemplaza sin consultar antes su estado; `EditarVentaEmitida` consulta **antes** de la transacción para que el CDR recuperado no se pierda con el rollback. Si SUNAT lo tenía, se exige nota de crédito.

- **2. Comprobantes `pendiente`/`excepcion` estancados:** `billing:enviar-programados` ahora también los toma. Si ya hubo un intento consulta el CDR y solo reenvía el mismo XML firmado cuando SUNAT confirma que no lo tiene.

- **3. GRE:** un error de comunicación ya no es un rechazo. Solo `codRespuesta 99` rechaza; el resto queda «enviada» y se reintenta (`GreApiClient::SIN_RESPUESTA`, `GuiaRemisionService::consultar`).

- **4. Detracción:** verificado y **sin cambios**, por decisión. `DetraccionCalculator.php:33` usa `$total > 700` («mayor a S/ 700», Anexo 3 de la R.S. 183-2004/SUNAT), que es lo que manda la norma. Sigue pendiente con el contador si la recarga entra en el 020, 022 o 037 y si aplica a bienes del Catálogo 54.

- **5. Carrera al tomar órdenes:** `TechnicalOrderAssignmentController::take` con `DB::transaction` + `lockForUpdate` y la orden releída bajo bloqueo.

- **6. Envío a SUNAT idempotente:** `EmitElectronicDocument::sendDocument` con `Cache::lock` por documento: un doble clic o el programador y un clic a la vez ya no envían dos veces.

- **7. Saldo de cobranzas:** `ClientController` y `StoreCollectionPaymentRequest` calculaban el saldo sin restar `monto_acreditado`, con deuda inflada y cobros de más permitidos. Ahora usan `Installment::saldo()`.

- **8. Token de APIsPeru:** `RucLookupController` devuelve un mensaje genérico al navegador y deja en el log el error con el token tachado.

- **9. Aislamiento por sede:** `InventoryLookupController::bySerial` (también el escáner de series de ventas) acota con `almacenRestringidoId()` y ya no confía en el `sede_almacen_id` que manda el formulario.

- **Extra (no era hallazgo):** `GuiaRemisionService` pasaba `CarbonImmutable` a los setters de Greenter, que exigen `DateTime`; la GRE no llegaba a emitirse. Convertido con `->toDateTime()`. Se comprobó que el fallo era anterior a esta tanda.

- **Verificación:** `pint --dirty` limpio, PHPStan nivel 7 con **0 errores**, 118 pruebas de las áreas tocadas en verde (617 aserciones) y `tsc` sin cambios de frontend. PHPStan sin línea base sobre los archivos tocados: 0 errores introducidos; quedan 14 preexistentes en `ClientController.php`, ya cubiertos por la línea base.

- **Pendiente:** la entrada de la línea base de PHPStan para `InventoryLookupController` quedó obsoleta (ese error ya no existe) y puede retirarse en la próxima pasada de reducción de la línea base.

## [2026-10-07] — Plan de elaboración de instrumentos por bloques — 07/10/2026

- Por pedido del usuario, planificación primero. Guardado PLAN_INSTRUMENTOS_SEMANA8.md con cinco bloques y controles para retomar.

- Definidos ocho instrumentos: ficha técnica, instrucciones, formatos de registro y consolidación; diez indicadores, SUS VI04 y software/procesos diferenciados.

- Revisado el borrador A1–A13: reusar formatos y 122 REQ, corregir VI06/PTR/catorce días, separar diagnóstico y validación.

- Registrado el nombre VD04 aprobado: porcentaje de productos con diferencias de stock en el inventario; pendiente ajuste de redacción mínimo en documentos relacionados.

- QA del plan: ocho códigos, cinco bloques, archivos/fuentes, pruebas de cálculo, respaldo, revisión visual y punto de reanudación definidos.

- Sin cambios a Word, calendario, código ni resultados. Siguiente: Bloque 1; ejecución y mediciones reales pendientes.

## [2026-10-07] — Investigación de formatos personalizados — 07/10/2026

- Pedido: investigar cómo recolectar y personalizar los instrumentos; guardado informe en anexos/INVESTIGACION_FORMATOS_RECOLECCION.md.

- Consultados extractos indexados de dos tesis UPN; sus PDF directos fallaron. Consultados PDF de gestión comercial/SUS y texto del artículo UDG; límites de acceso documentados.

- Definidos como propuesta siete instrumentos propios/adaptados y SUS con diezítems/puntuación conservados. Campos específicos derivados de DRS, fórmulas, límites de tareas y fuentes de evidencia.

- Flujo: protocolo, registro original, revisión, consolidación, cálculo y estado de aplicación; Word principal y Excel opcional.

- Sin cambios a Word/Excel ni datos, calendario o código. Actualizada memoria y nota local de Obsidian. Elaboración sigue pendiente, próxima acción Bloque1.

## [2026-10-07] — Instrumentos listos para completar con datos reales — 07/10/2026

- Autorización «Vale hazlo»: entregado Word instrumentos v1.2 de50páginas en Semana8; ocho principales con fichas, instrucciones, registros y consolidaciones de cincoVI/cincoVD.

- I01 conserva122REQ; I02 índice122casosbase, ficha por caso, sieteescenarioscríticos y diezintentosinválidos/controles. Datos/pasos detallados se preparan antes de ejecutar; no se afirman122pruebas realizadas.

- I03 conserva10ítemsSUS; I04 respuesta en segundos; I05 ventas/cotizaciones separadas; I06 montos; I07 productos distintos; I08 clientes únicos con plazo común a fijar.

- Apoyos de entrevista, observación, expertos y piloto separados. Resultados, firmas, validación, piloto y aplicación pendientes.

- Corregido únicamente nombreVD04 en métricas;18ecuaciones conservadas. Informe/matrices concordantes y sin modificación. Cronograma/muestras previstos sin cambios.

- QA61páginas, fórmulas, denominadores, estados, casos y paquete comprobados. LibreOfficeausente: exportaciónWordoculto+Poppler. Últimos ajustes23/33/38/46 revisados; demás páginas idénticas al QAinspeccionado.

- DosDOCX instalados con respaldo y SHA verificados; memoria/Obsidianactualizados. Sin cambios al código ni ejecución de pruebas del sistema; Excel sigue como apoyo opcional.

- Siguiente: revisiónreal y piloto, protocolo/marcos antes de aplicar; informe de estado, Scrum y demo con evidencia.

## [2026-10-07] — Ocho instrumentos en Word separados — 07/10/2026

- Atendida la preferencia de unWord por instrumento: I01–I08 instalados en Semana8/Instrumentos individuales.

- Cada archivo conserva íntegros ficha, instrucciones, registros y cálculo; añadida identificación y nota de fuentes. Campos de datos/resultados/firmas vacíos.

- QA41páginas: 8/11/3/5/4/3/3/4; todas revisadas. SUS quedó en3páginas al mover la referencia que generaba una página adicional.

- Texto/tablescotejadoscontraelcompendio; partes ZIP salvo document.xml idénticas; SHA de ocho destinos comprobados.

- Compendio de50páginas sin modificar; apoyos de diagnóstico y validación permanecen allí. Memoria y Obsidianactualizados.

- Sin cambios de métricas, calendario, código o resultados. Aplicación, expertos y piloto siguen pendientes.

## [2026-10-07] — Scrum S7 e informe de estado Semana 8

- Entregados en Semana 8: Scrum Diario por Sprint - BRUCE FIRE.xlsx e Informe de Estado - Sprint 07 - BRUCE FIRE.docx (34 páginas). Periodo 02–09/10/2026; evidencia al 07/10; cierre 08–09/10 pendiente.

- Conservados S1–S6 íntegros (XML, datos, formatos y combinaciones); añadidos S7 y TRAZABILIDAD S7. Diario distingue programación diaria de evidencia documentada; 03–04/10 no acredita Daily ni jornada del equipo.

- Tabla principal global: 387 actividades hoja de 129 paquetes, ocho fases y 539 códigos EDT. Anexos semanales: 41 actividades con trabajo temporal positivo (16 fines previstos y 25 continuidades) + 13 inicios del 09/10. Ambos documentos comparten códigos, fechas y estados.

- Curva S vigente: PV S7 S/ 890.585714, acumulado previsto al 09/10 S/ 7,551.526190; BAC S/ 14,250. Costos semanales por paquete sin redistribución; actas 8.2 sin PV en S7 conforme al modelo. AC, EV, SPI/CPI y porcentajes reales no acreditados: N/D. No se copia AC=PV del informe anterior.

- Calidad: selección/diseño de cinco VI/cinco VD e instrumentos I01–I08 técnicamente elaborados al 07/10. Revisión, juicio experto, piloto, mediciones y firmas siguen pendientes. Avances técnicos se refieren a la bitácora del 07/10, sin convertir pruebas parciales en aceptación de módulos o 122 REQ.

- QA: investigación Guía Scrum/PMI; fuentes locales y Obsidian contrastadas; códigos/fechas/PV cruzados; 34 páginas Word y hojas nuevas revisadas; índice interno válido, imágenes/estilos preservados. Excel abre normalmente y recalcula PV; prueba de cambio de +10 y restauración correcta, AC permanece vacío. Exportación inicial incompatible corregida mediante ensamblaje de las hojas nuevas en la estructura original.

- Instalación con SHA verificados y respaldos cuando corresponde. Métricas, compendio y ocho Word individuales ya permanecen en Semana 8. Demostración del software sin evidencia de archivo S7: pendiente; no se inventa entrega o presentación al Gerente.

- Siguiente: incorporar registros reales de 02–09/10, horas/costos, evidencia de revisión 5.3.3 y conformidades; actualizar Scrum e informe juntos al cierre. Preparar/registrar la demostración del avance. No se alteró cronograma, EDT, Curva S ni código.

## [2026-10-07] — Revisión del paralelismo Scrum/cronograma S7

- Por pedido del usuario, reextraído el cronograma vigente de forma independiente y contrastado con los archivos instalados, no solo con el modelo de generación.

- Confirmadas 387 actividades únicas de 129 paquetes, con nombres y fechas planificadas idénticos al cronograma. La tabla global cubre los 539 códigos EDT mediante sus niveles de resumen y actividades hoja; no se limita al Sprint.

- Anexos alcance/tiempo y grupos de costo coinciden con S7: 41 actividades con solapamiento positivo (16 fines planificados y 25 continuidades) y 13 inicios del 09/10. TRAZABILIDAD S7 conserva los mismos códigos y fechas; sin actividades ajenas al periodo.

- Corregidos 11 textos del Scrum: HACIENDO detalla todos los frentes paralelos para cada uno de los ocho días, incluyendo transiciones entre lógica/pantallas, integraciones, planes de pruebas/calidad y gestión. Códigos abreviados ambiguos de 05–06/10 sustituidos por códigos completos; eliminada la referencia genérica a otras pantallas.

- TERMINADO conserva exclusivamente las subtareas/cierres documentados; no se convierten fines del cronograma en terminación real. Evidencia al 07/10, 08–09 previstos. Sin datos reales adicionales ni cambios en alcance, presupuesto, cronograma o resultados.

- QA: conjuntos diarios de códigos exactos, 11 celdas modificadas, siete hojas restantes y todas las demás partes ZIP intactas; apertura normal/PV 890.585714 en Excel; alturas cotejadas y tres páginas nuevas revisadas. Word sin modificación y SHA del Scrum instalado comprobado; respaldo de la versión anterior guardado.

- Pendiente: confirmar cierres y avances reales del equipo antes de completar el cierre 09/10; continuar actualización conjunta de Scrum e informe.

## [2026-10-07] — Sprint 7 rehecho como el Sprint 6

- Por instrucción explícita del usuario, sustituida la redacción extensa de S7 por el modelo real de S6: mismas columnas, anchos, fuentes, colores, alineaciones y combinaciones; frases breves por actividad y cierre en la última fila.

- Periodo 02–09/10/2026, con siete registros 03–09/10, siguiendo la convención del S6 que abre el periodo el 25/09 y registra 26/09–02/10. El cierre S6 del 02/10 no se duplica.

- HACIENDO conserva los trabajos comerciales, operativos, integraciones/PDF y pruebas que corren en paralelo. Los inicios del 09/10 están en HACER del cierre y continúan en el siguiente Sprint. Se retiran listados extensos repetidos y notas de auditoría dentro del tablero.

- TERMINADO solo acredita el diseño de calidad/instrumentos elaborado al 07/10. Cierres de lógica/integraciones sin respaldo quedan en EN ESPERA. La última fila identifica expresamente el cierre previsto del 09/10; no se afirman presentación, validación ni firmas realizadas.

- QA: todas las propiedades de formato de B2:F13 coinciden con S6, salvo alturas ajustadas para legibilidad; datos/textos cotejados, tres páginas nativas Excel revisadas; apertura normal y PV 890.585714 conservado. S1–S6, TRAZABILIDAD S7 y otras partes ZIP sin modificación; Word de estado sin cambios y concordancia de alcance conservada.

- Reinstalado Scrum en Semana8 con respaldo y SHA comprobado. Esta versión reemplaza las revisiones anteriores del tablero, manteniendo sus evidencias en memoria. Próximo: incorporar cierres reales al 09/10 y actualizar el informe conjuntamente.

## [2026-10-07] — Scrum S7: actividades individuales y concretas

- Corrección explícita del usuario tras señalar una celda de HACIENDO: retirar todos los rangos/agrupaciones EDT y descripciones genéricas como construcción de pantallas; explicar una actividad por código con contenido funcional concreto.

- Reescritas HACER, EN ESPERA, HACIENDO y TERMINADO: registro/búsqueda de clientes, datos del catálogo, cotización con importes, venta contado/crédito y series, emisión/estado de comprobantes, abonos/saldo, arqueo, Kardex, recepción, órdenes, deficiencias, certificados y alertas. Pruebas, administración y gestión también se describen individualmente.

- Fuente de alcance: DRS v3.1 de Semana1; fechas/códigos: cronograma vigente; avance técnico de 07/10: bitácora (saldos acreditados, consulta por sede, CDR/reintentos y protección del token). Se distingue detalle del trabajo programado de evidencia de ejecución; no se inventan transacciones, mediciones, pruebas completas ni firmas.

- Cobertura: los mismos 54 códigos semanales y siete días. Columnas, anchos y estilos S6 conservados; añadidas filas de continuación por día para no exceder la altura máxima de Excel ni cortar el texto. Cada entrada contiene un solo código EDT; sin rangos, abreviaciones ni tareas de varios módulos en una línea.

- QA: 33 filas de contenido, nueve páginas Excel revisadas; todas las descripciones presentes en PDF, alturas nativas sin recorte, apertura normal/PV 890.585714. S1–S6, TRAZABILIDAD S7 y resto del paquete sin modificación. Informe Word sin cambios.

- Instalado en Semana8 con respaldo y SHA comprobado. Esta preferencia reemplaza la redacción resumida y los rangos de revisiones anteriores. Mantener descripciones individuales en próximas actualizaciones; cierre 08–09, presentación y aceptación siguen pendientes de evidencia.

## [2026-10-07] — Investigación de análisis, diseño y programación

- Revisión exacta del cronograma y captura: clientes2.4.1 requisitos/casos/validación, 3.5.1 prototipo/tablas/rutas/revisión, 4.1.1 código/pantallas/pruebas. No confundir diseño terminado con implementación completada ni todo el módulo con una actividad.
- Consultadas Guía Scrum2020 oficial, publicación WBS de PMI y Practice Standard for Scheduling segunda edición. Regla aplicada por usuario: nombres literales y códigos individuales; Scrum exige estado real y criterios de terminado, no prescribe copiar títulos de cronograma.
- Verificados plazos de clientes y faseDiseño global; plan de seguridad23–30/10. Actividad4.1.1.2 fin previsto10/10, módulo4.1.1 fin previsto16/10; no se dan por terminados09/10 por haber un prototipo.
- El usuario corrigió que solo quería desglosar rangos y pidió primero comprensión. La consulta de estado fue respondida pidiendo revisar el cronograma, no confirmando ejecución real. Se continúa la explicación sin otra pregunta de ese tipo.
- Registro de investigación: ACLARACION_ANALISIS_DISENO_PROGRAMACION.md en staging de semana8-scrum-informe. No se modificaron Word, Excel, código ni cronograma en esta consulta. Pendiente rehacer S7 con nombres literales, una fila diaria, estilo previo y paralelismo, reemplazando revisión04.

## [2026-10-07] — Sprint7 con la lista del cronograma revisada por el usuario

- Autorización «ya viste ahora ya puedes hacer el sprint», después de enviar la lista completa de 4 fines el09/10, 12 fines anteriores dentro deS7, 25 continuidades y 13 inicios del09/10.
- Rehecho S7 con códigos/nombres literales de cada actividad, individualmente y sin rangos, paráfrasis del DRS ni continuaciones. Siete filas03–09/10 con periodo02–09/10, siguiendo el modelo deS6. Nuevos inicios en HACER, tareas que continúan en HACIENDO y fines del cronograma en TERMINADO, expresamente identificados como previstos.
- Cierre09/10: 4 actividades con fin previsto; 25 siguen después del corte; 13 inicios más 3 gestiones de presentación/aceptación en HACER. Se muestran también los 3 entregables con fin previsto09/10:4.5.1,4.5.2 y5.3. Scrum e informe se distinguen como entrega semanal, sin cerrar sus paquetes globales.
- Columnas y estilos deS6 conservados; anchos C/D/E/F ajustados para caber una sola fila por día. Alturas nativas300–356pt, debajo del límite409, sin reducir fuentes. No se inventan fechas reales, aceptación, firmas ni costos; los cierres del08–09 siguen previstos al corte07/10.
- QA: todas las entradas con un código y nombre exacto; cobertura54códigos igual al registro y anexos del informe; fechas/inicios/continuidades/fines contrastados. Cuatro páginas nativas Excel revisadas y todas las líneas presentes; apertura normal y PV890.585714 conservado. S1–S6, TRAZABILIDAD S7 y otras partes del paquete intactas; informe, cronograma y código sin cambios.
- Instalado en Semana8 con respaldo y SHA comprobado. Esta versión reemplaza las revisiones anteriores del Scrum. Pendiente incorporar ejecución real al cierre09/10 y actualizar datos de avance/costos/conformidad junto con el informe.

## [2026-10-08] — Informe S7 alineado con el Scrum vigente

- Corregido por pedido del usuario siguiendo los informes S4–S6. Versión 1.1 instalada en Semana 8; 41 páginas nativas Word revisadas. Tabla de hitos y entregables: los 539 códigos de la EDT/cronograma, incluidos resúmenes, fases, entregables y 387 actividades hoja, con nombres y fines planificados literales.
- Anexos de alcance, tiempo y costo: las mismas 54 actividades individuales del Scrum S7, sin rangos ni paráfrasis; 12 fines previstos antes del 09/10, cuatro el 09/10, 25 continuidades y 13 inicios en el corte. Los cinco entregables de cierre/entrega semanal se muestran aparte, sin duplicar trabajo ni presupuesto. No se cierran globalmente los paquetes recurrentes Scrum/Informe por entregar una versión semanal.
- El tiempo se limita a la ventana 02–09/10. Costos: una fila por actividad y celda PV combinada por paquete; se conservan una sola vez los 35 importes del modelo semanal de Curva S. PV S7 S/ 890.59, PV acumulado previsto S/ 7,551.53 y BAC S/ 14,250.00. Sin redistribuir presupuesto ni copiar AC=PV. Duraciones/costos reales, EV, porcentajes e índices sin respaldo permanecen N/D.
- Actualización 08/10; evidencia disponible al 07/10 y cierre 09/10 previsto, pendiente de corroborar. Cierres históricos consignados en los Scrum e informes anteriores se identifican como declaraciones, no aceptación acreditada. Los estados previstos coinciden con el Scrum vigente.
- QA independiente contra archivos fuente: 539 nombres/códigos/fines, igualdad de los tres anexos con Scrum, importes Curva S, índice/campos/bookmarks válidos; imágenes, estilos y relaciones del modelo S6 preservados. Paginación nativa y legibilidad revisadas; comprobación final idéntica. Instalación con copia de respaldo y SHA verificado. No se modifican Scrum, cronograma, Curva S ni código.
- Esta versión sustituye el informe de 34 páginas: ahora se muestran todos los niveles de la EDT, cada actividad también en costos y solo la ventana semanal en tiempos. Pendiente: confirmar ejecución/costos/conformidad al cierre 09/10 y actualizar ambos documentos con evidencia real; demostración del software pendiente de registro.

## [2026-10-08] — Porcentajes, historial y agrupación del informe S7 v1.2

- Pedido del usuario: reproducir porcentajes del resumen, explicaciones y sustento como informes S4–S6; recuperar fechas/entregas anteriores, agrupar celdas repetidas e investigar indicadores. Informe v1.2 instalado en Semana8 con respaldo, SHA y 32 páginas revisadas.
- Corrección histórica: expansión de rangos de códigos en Scrum S1–S6, fechas reales de informes S4–S6 y actas. Recuperados 259 cierres de niveles/actividades, incluidos 61 entregables. Entregado no presupone firma de aceptación. Cuando falta fecha individual se muestra ≤ fecha de cierre del paquete; no se inventa un día exacto. Paquetes recurrentes no se cierran globalmente por una entrega semanal.
- Tabla global conserva los 539 códigos/nombres/fines del cronograma y agrupa la columna Entregable en 129 paquetes. Anexos mantienen las mismas 54 actividades S7; alcance y costos agrupados en 35 paquetes, porcentajes individuales de referencia y duración de ventana semanal. Sin cambios en Scrum, cronograma, CurvaS o código.
- Porcentajes: alcance/tiempo 100% previsto al cierre09/10, distinguidos del avance global presupuestado52.99%. Costo100% es referencia presupuestaria890.59, no AC realizado. Se pidió aclaración opcional de ejecución semanal y no se recibió confirmación; datos reales S7 siguen vacíos en CurvaS. No cambiar a resultados reales por inferencia del calendario.
- PMI oficial consultado: How to make earned value work on your project (2012), y Project communication—foundation for project success (2003). Indicadores agrupados por aspecto: cumplimiento/avance presupuestado, SV/SPI y costo relativo/CV/CPI. Con cumplimiento semanal proyectado100%, EV acumulado7547.03, SV−4.50 y SPI99.94%; se arrastra diferencia histórica, no se fija SV=0 ni SPI=1 por redondeo. CV/CPI pendientes de AC S7.
- QA independiente: igualdad de conjuntos/códigos/nombres/fechas, 129 agrupaciones globales, 35 semanales, ocho indicadores agrupados en tres aspectos, preservación de imágenes/estilos/relaciones, campos/índice/bookmarks válidos y paginación nativa final. Ajustado flujo para evitar párrafo aislado en página casi vacía. SHA final 4767068eb98d293b383aa0933975e4f7ae73885cdd1f1794d6ed5befd0784eec.
- Sustituye v1.1 (41 páginas) y su historial restrictivo. Pendiente: confirmar cumplimiento semanal al09/10, registrar horas/costos/EV/AC, presentación, validación y firmas; actualizar informe y Scrum con resultados reales. Demo pendiente de registro.

## 2026-10-09 — Auditoría E2E por roles (navegador, servidor :8010)

- Recorrido real con los 5 usuarios: producto/servicio, recepción y stickers, venta boleta aceptada en SUNAT beta, NC pedida por vendedor → aprobada por Gerente (BC01-1 aceptada), orden de planta completa (recepción, checklist con foto obligatoria, deficiencia, autorización, resolución, terminado), recojo de campo con firma, ajuste/baja (doble baja bloqueada), aislamiento por rol (403 correctos).
- Corregido (commit b79f7de): cobro de orden anulada, adicional resuelto no cobrado, aviso de adicionales sin cotización, extintor en dos órdenes abiertas, anuladas y KPIs ajenos en paneles de técnicos, detalle/foto de deficiencia al vendedor, tipo de servicio vacío, rótulos de bitácora, fecha cruda en custodia, botón "Tomar orden" de otra área.
- Pendiente: KPIs del Gerente no restan notas de crédito; botones/tabs sin nombre accesible; traslado sin mensaje cuando no hay otro almacén; certificado mal configurado da 500; títulos de eventos sin tildes; scheduler sin correr.

## 2026-10-09 — Continuación GPT: scheduler, KPI y configuración SUNAT

- Repo real en Documents; carpeta Desktop configurada en el chat inexistente. Rama `fix/correcciones-auditoria-completa`; `CONTEXTO_GPT.md` preexistía sin seguimiento. Sin commit ni push.
- MySQL local apagado: iniciado con configuración de Laragon. `composer run dev` omitía scheduler: agregado `DevCommands::artisan('schedule:work', 'scheduler')`; servidor 8000/cola/Vite iniciados. Latido activo y ejecución automática de envío diferido observados; envío manual 0/0 pendientes y respaldo real nuevo.
- KPI del Gerente y gráfico mensual restan NC aceptadas/observadas por fecha de emisión, conservando bruto fiscal de anulaciones con NC para no descontar dos veces. Regresión incluye nota sobre venta de mes anterior y anulación total.
- `GreenterService::sign()` devuelve validación clara ante ruta ausente/ilegible o PEM inválido, sin exponer claves. Tildes de eventos corregidas. Nombres accesibles en técnicos, estado de filtros/ajustes y aviso de destinos vacíos; inspección de código, sin acreditar auditoría WCAG integral.
- Verificado `greenter/lite` 5.3.0 y `greenter/gre-api` 1.0.2/OAuth2; ambas credenciales GRE locales ausentes, comprobadas sin mostrar secretos. Solo beta. `SUNAT.md` actualizado con prórroga remitente 31/08/2026/transportista 28/02/2027, fuente SUNAT oficial.
- Brechas pendientes: traslado registra tránsito/recepción sin exigir GRE aceptada; rotulado de servicio/collar/tarjeta no acreditados (sticker de identificación 50×50 mm). NTP 833.030 consultada en edición 2012; confirmar vigente/muestras del dueño. Mantener cinco años de PH hasta decisión del dueño; QR/barras intactos.
- Pruebas: 8 iniciales pasaron; ampliación 34/35 detectó aviso OpenSSL con archivo inválido, corregido y sus 2 pruebas pasaron. Primera ejecución falló al preparar base testing; corrida posterior migró correctamente. Pint y formato TS focalizados aplicados.
- CI final aprobado: 877/877 pruebas, 4,670 aserciones (433.9 s en Pest); formato/lint/TypeScript/Pint/PHPStan aprobados. Se corrigieron formato Markdown, comprobación de tipo de serie y tipado Eloquent; sin supresiones ni línea base nueva. Primera corrida de 12 trabajadores interrumpida por preparación lenta; repetida con 2 mediante ajuste temporal, `composer.json` restaurado byte por byte. Diff revisado en seguridad/complejidad; sin cambio de permisos ni secretos expuestos.

## 2026-10-09 — Nombres accesibles, controles y etiquetas (WCAG 2.2 AA)

- Tarea acotada a accesibilidad; 38 archivos de interfaz. Listado de cada archivo/nombre en `ACCESIBILIDAD_CONTROLES.md`. Se preservan los cambios anteriores; sin commit, push ni dependencias nuevas.
- Nombres españoles en iconos, menús técnicos/contraídos, pestañas, deficiencias, recepción, ejecución, almacén/caja y acciones de catálogo/ventas/certificados. Checklist: nombre del componente y `aria-pressed`; casillas técnicas con equipo identificado.
- Etiquetas de campos y formulario compartido de cliente con `htmlFor`/ID único por instancia; controles y textos visibles conservados. Fondo de notificaciones convertido de `div` a botón, con foco; ID ARIA del menú corregido y área desplazable del Gerente enfocable.
- Barrido final páginas/layouts: cero iconos interactivos sin nombre detectados y cero `div`/`span` con `onClick`. TypeScript, formato y lint aprobados. No se repite suite PHP porque no cambió backend.
- Axe desde cdnjs/Edge instalado: cinco dashboards sin hallazgos. Modal de cliente sin hallazgos; campos activos con una etiqueta asociada. Flecha derecha de Servicios enfoca Seguimiento; menús técnicos móviles anuncian todas sus opciones. No se guardaron datos del negocio.
- Revisión adicional encontró contraste en Nueva orden, Deficiencias y globo Chispa de Ajustes/Planta móvil. Colores conservados por instrucción explícita; no se acredita conformidad WCAG integral. Evidencias JSON en `storage/app/a11y-*`.

## 2026-10-09 — Publicación autorizada para relevo de agente

- El usuario pidió guardar, subir y fusionar los cambios en `main` como respaldo, sin repetir CI local. Se conserva la verificación previa de accesibilidad y la suite completa anterior.
- El hook local de pre-push se omite únicamente en estas subidas mediante configuración temporal de Git; no se modifica el hook ni se desactivan los flujos remotos de GitHub.
- Se incluyen el código y el informe de accesibilidad; los archivos ignorados del entorno, secretos y respaldos de base de datos permanecen locales.

## 2026-10-09 — Decisiones del dueño e investigación de detracciones

- Usuario confirma cotizar recargas y adicionales antes de convertir a venta; precios con IGV desde catálogo, editables por el vendedor; contado/crédito por el flujo existente.
- Correcciones por el vendedor con historial, sin nuevas aprobaciones; autorización de cliente con persona/canal/fecha/importe ya disponible en controlador y formulario.
- Datos actuales son pruebas. Cinco años de hidrostática conservados; vendedor registra hallazgos, técnico determina aptitud, aviso de caja abierta y costos/márgenes diferidos.
- Cuenta de detracciones existente; vendedor revisa constancia y comparte captura al gerente por WhatsApp manual. Sin integración de mensajería autorizada.
- Revisados config/billing.php y DetraccionCalculator: 020 global por defecto, tasa 12%, mínimo S/700; no hay clasificación por servicio en ese cálculo.
- SUNAT mantiene recarga de extintores en numeral 5 (otros servicios empresariales): https://orientacion.sunat.gob.pe/3350-consultas-sunat-empresas e Informe 017-2006 https://www.sunat.gob.pe/legislacion/oficios/2006/oficios/i0172006.htm . Inferencia de código: 022 para recarga, 020 para mantenimiento/reparación; no extender la antigua exclusión de mantenimiento del informe de 2006 a la normativa actual.
- Tabla actual y códigos: https://orientacion.sunat.gob.pe/apendices-del-sistema-de-detracciones (020/022/037, 12%, clasificación distinta). Operaciones combinadas y otros servicios pendientes; configuración fiscal conservada.
- Solo actualizados PLAN y BITACORA. Sin pruebas ejecutadas ni cambios de código/configuración, commit o push en esta aclaración.

## 2026-10-09 — Detracción: el repuesto instalado va con el servicio

- Regla confirmada por el dueño con su contador. El repuesto o producto que se
  usa o instala **dentro del servicio** es parte del servicio: va en la **misma
  factura** y la detracción se calcula sobre el **TOTAL** con el código del
  servicio (`services.codigo_detraccion`). El producto **suelto** que no se
  instala (extintor nuevo, accesorio vendido aparte) **no puede** acompañar a un
  servicio sujeto a detracción: se emite en **factura aparte**.
- Se considera "parte del servicio" en `DetraccionCalculator`:
    1. la línea de servicio (`SaleItem::esServicio()`);
    2. la línea con `sale_items.deficiency_id` (adicional detectado en una orden);
    3. el repuesto consumido en una orden de servicio de esa misma venta
       (`InventoryMovement` `tipo=salida_servicio` referenciando una `Deficiency`
       cuyo `ServiceOrder.sale_id` es esta venta).
- Sin cambios: boleta y nota de venta nunca llevan detracción; hasta S/700 no
  hay detracción; la factura emitida conserva su snapshot
  (`codigo_detraccion_snapshot`, `monto_detraccion_snapshot`); servicios con
  códigos distintos o sin clasificar siguen bloqueados como antes.
- Archivos: `app/Services/Billing/DetraccionCalculator.php` (nueva regla +
  `esParteDelServicio` / `esRepuestoConsumidoEnLaVenta`),
  `resources/js/pages/vendedor/ventas/nueva.tsx` (aviso antes de emitir),
  `tests/Feature/DetraccionParteDelServicioTest.php` (9 pruebas nuevas).
  `GreenterService` ya usaba `codigo_bien` y el monto sobre el total: sin cambios.
- TDD: las pruebas se escribieron primero y fallaron con el mensaje antiguo
  ("combina servicios con códigos distintos o conceptos no sujetos"); después de
  la implementación pasan.
- Verificación: 44/44 pruebas (154 aserciones) en detracción + `SaleModuleTest`;
  Pint ✅ · PHPStan 0 errores ✅ · `tsc --noEmit` ✅ · `vp check` 212 archivos ✅.
  Sunat solo beta; sin push.

### 2026-10-09 — Auditoría independiente de Codex sobre 53cda2b

- Informe: `docs/ai/AUDITORIA_CODEX_2026_10_09.md`; evidencia local ignorada en `storage/app/auditoria-independiente/`.
- Suite 944/944, 5.060 aserciones; Pint, PHPStan 0 y tsc pasan. CI original detenido por formato de esta bitácora; composer.json restaurado byte por byte.
- Navegación: 37 rutas, cinco roles; cuatro accesos cruzados 403. Axe: contraste insuficiente en Deficiencias del vendedor; otras cuatro pantallas sin infracciones automáticas.
- Flujo UI: COT-0001 → OT-0004 → checklist/foto → válvula S/15 autorizada → consumo -1 → NV-0001 S/60 y pago efectivo S/60; stock 10→9, sin doble salida al cobrar.
- Límite: equipo ID 7 quedó sin marcar recibido; cierre bloqueado correctamente. No se verificó entrega/certificado ni nuevo envío de red a SUNAT/GRE.
- Dos regresiones nuevas fallan: consumir una unidad acredita diez vendidas u otra línea del mismo producto en detracción. También difieren criterios entre frontend y calculador.
- Confirmados: hidratación de cero negativo en Cajas, aviso fiscal repetido tres veces, toast anterior visible inmediatamente tras éxito. Errores inline del producto sí se limpian.
- Scheduler activo y respaldo de hoy; cuenta BN no configurada, credenciales GRE ausentes y servicio combinado sin clasificación. No se inventaron datos.
- Tras formatear la documentación actualizada, npm run check pasa (213 archivos de formato, 175 de lint).
- Datos de prueba identificados AUD-CODEX; sin código de aplicación modificado, dependencias, commit ni push. Pendientes y prompt de reauditación incluidos en informe.

### 2026-10-09 — Corrección de Informe de Estado y Scrum Semana 8

- Usuario confirmó cierres según cronograma hasta el 09/10 y costos reales iguales al presupuesto. Anexos limitados a actividades con fin del 02 al 09 inclusive.
- Se corrigieron copias del Informe Sprint 07 y Scrum: 20 actividades, S/902 de costo completo, fechas y códigos concordantes. El PV semanal de Curva S S/890,59 conserva otro alcance.
- Hitos actualizados al corte; sustento e indicadores del conjunto cerrado; Hacer=inicios del día, En espera=predecesoras pendientes, Haciendo=ejecución y Terminado=cierres del día.
- Verificación: 539 fechas planeadas iguales al cronograma, tres anexos y Scrum con los mismos 20 cierres; Excel nativo recalcula totales, sin errores y con Sprints 1–6 conservados; Word renderizado, tablas e índice revisados.
- Archivos en Entregables/Semana 8/Corregidos 09-10-2026; originales intactos. Métricas/instrumentos no se presentaron como mediciones aplicadas. Sin código de aplicación, commit o push.

### 2026-10-10 — Revisión navegada, productos y coherencia de cobros

- Autorización: revisar botones/coherencia, corregir errores y rediseñar registro de productos; comparación de empresas grandes queda para la siguiente fase.
- 40 pantallas/5 roles, 68 acciones, 15 fichas distintas; sin errores JS en ese recorrido. Alcance y límites en REVISION_NAVEGADA_2026_10_10.md.
- Corregido filtro SUNAT por día completo, con 4 regresiones rojo→verde y descargas de B001-3; producto rediseñado, errores/foco/móvil, estado sin falsa eliminación.
- Servicios/Sedes, alta/consumo/resolución e historial usan Dialog; foco devuelto y cierre de 44 px. Llegada de nuevo equipo sincronizada sin perder desmarcaciones.
- Cobranzas diferencia saldo de importe original, cuotas pagadas y pagos de hoy; historial identificado y aviso de certificado pendiente aclarado.
- Copia aislada: recepción 3/2/1 aumenta stock 9→11; COT-0003→OT-0006→VTA-0005/NV-0002→OG-2026-0003; pagos 20+25 y turno nuevo S/10. Original conserva stock 9 y su caja.
- Dos productos AUD-UI creados para probar el catálogo original quedaron desactivados. Sin eliminación de historia, dependencias, commit ni push; cambios previos preservados.
- Suite 957/957 (5.115 aserciones); 31 pruebas afectadas; PHPStan 0 y Pint pasan. Axe: 8 estados + producto oscuro sin infracciones automáticas; no certificación WCAG total.
- Verificación final: formato/lint, tipos y build pasan; revisión independiente detectó errores ocultos de compra y validación nativa, reproducidos y corregidos con verificación Edge rojo→verde; entrega con acta, deficiencias, GRE, hardware y benchmark pendientes.

### 2026-10-10 — Comparación externa de catálogo y procesos

- Pedido: investigar cómo sistemas consolidados registran productos y relacionan procesos, comparar con BRUCE FIRE y proponer mejoras.
- Fuentes oficiales consultadas: Shopify, Odoo 19, Zoho Inventory y Dynamics 365 Field Service; documentación pública, sin prueba de sus consolas ni afirmaciones sobre operaciones internas de clientes grandes.
- Contrastado con auditoría navegada y código actual: catálogo/unidad/equipo separados, estados/eventos, recepción, tránsito, costo promedio, venta/servicio, historial y seguimiento.
- Informe: docs/ai/anexos/COMPARACION_PROCESOS_2026_10_10.md; propuestas ordenadas para productos, documentos/siguiente acción, recepción, deficiencias/campo y cobros/gerencia.
- No atribuir todas las acciones como verificadas: 40 pantallas/68 acciones previas no prueban todos los botones; entrega/acta, deficiencias, GRE y hardware mantienen sus pendientes.
- Recomendaciones todavía no implementadas; funciones nuevas sujetas a REQ/historia/matriz. Traslados en tránsito y costos ya existen: no repetir ausencias de auditorías antiguas.
- Verificación documental: enlaces oficiales y contraste de archivos; sin cambios de código, datos, dependencias, commit ni push. No se repitieron pruebas del sistema para documentación.

### 2026-10-10 — Comparación especializada con software de extintores

- Aclaración del dueño: comparar software del mismo sector, como FireTrack; benchmark general anterior pasa a referencia secundaria.
- Consultadas fuentes oficiales de FireTrack, Inspect Point, Uptick, ServiceTrade y Simpro; distinguido manual público, publicidad y demo simulada.
- Edge: recorridos 5/4/3 pasos de inspector/administración/cliente de la demo FireTrack, más informe de ejemplo. Sin cuenta, contactos, GPS real ni emisión real.
- Contraste local: entidades comerciales/equipo, mantenimiento, ubicación, fotos/firma, alertas y flujo existente. No atribuir como ausentes QR/fotos/firma que ya tenemos.
- Informe: docs/ai/anexos/COMPARACION_SOFTWARE_EXTINTORES_2026_10_10.md; evidencia en storage/app/comparacion-sector-extintores-2026-10-10/.
- Prioridades: alta comercial vs técnica, guía por equipo, continuidad de deficiencias, sugerencias revisables y próximas atenciones; préstamos/portal/offline como candidatas de alcance separado.
- Verificación documental y de demo; no nuevas pruebas del sistema al no cambiar aplicación. Cobertura de auditoría previa mantiene sus límites.
- Sin implementación, datos locales alterados, dependencias, commit ni push; PLAN actualizado con la corrección de alcance.

### 2026-10-10 — Desglose por proveedor y brechas locales

- Pedido: desglosar cada software competidor y compararlo con BRUCE FIRE, proponiendo mejoras concretas.
- Informe: docs/ai/anexos/DESGLOSE_COMPETIDORES_2026_10_10.md; cinco proveedores, matrices por área, ejemplos y prioridades.
- Fuentes oficiales reconsultadas; se reutilizó evidencia de los 12 pasos de la demo reducida de FireTrack, sin nuevas operaciones externas.
- Contraste adicional: AlertController/alertas ya agrupan empresas y crean ofertas de recarga; CotizarDeficiencia ya enlaza la línea comercial; ReportController calcula valorización, no acredita costo total de orden.
- Mejoras UX diferenciadas de funciones nuevas: guía/ficha/conexiones primero; sugerencias, préstamos, portal, offline y automatización/costos completos requieren definición propia.
- Verificación documental y de fuentes/código puntual; sin nuevos tests de aplicación ni cambios funcionales o datos. Cobertura navegada anterior no ampliada.
- PLAN actualizado; sin dependencias, commit ni push. Propuestas todavía no implementadas.

### 2026-10-10 — Mejoras del benchmark integradas y cierre documental

- Autorización: mejorar lo que encaje. Perfiles del catálogo, orden conectada/siguiente paso y avance/resumen de mantenimiento en campo implementados.
- Backend: eager load de cotización/certificados; controles de sede conservados; nueva regresión roja por quote.id ausente y después verde.
- Verificación previa: 10 pruebas/69 aserciones; PHPStan 0, Pint, tipos, lint y build. Suite anterior de 957 no repetida por cambio acotado.
- Edge: 16 comprobaciones en copia, móvil, documentos y conteos; errores ocultos verdes; sin JS errors. Axe tres estados 0; 25 Tab contenidos.
- Contrastes corregidos y revisión independiente sin P1/P2. Cierre sigue requiriendo al menos un checklist; pendientes solo se advierten.
- Fixtures exclusivamente en copia bruce_fire_ui_audit_20261009; artículo de prueba desactivado. Original sin movimientos de esta fase.
- Guardado retomado tras fallo de revisión automática por cuota; script original tenía contenido nulo y se reconstruyó verificando evidencia.
- Informe MEJORAS_SECTOR_2026_10_10.md y evidencia storage/app/mejoras-sector-2026-10-10/; PLAN actualizado. Sin dependencias, commit ni push; cambios previos preservados.
- Pendientes anteriores de autorización/consumo, acta, GRE/dispositivos y candidatas de préstamos/portal/offline se conservan.

### 2026-10-10 — Auditoría visible de cinco roles (avance)

- Pedido: observar en navegador real botones, relaciones y flujos. Edge visible y todas las mutaciones en copia aislada, sin emisiones externas.
- Inventario de 40 pantallas y 436 controles; acciones y fichas documentadas con capturas y ledger. No equivale a botones únicos probados.
- Corregidos 500 de alertas sin catálogo, checklist con foto nula y SQL de traslado; entrega/resultados/acta, certificado previo en venta y doble entrega campo/mostrador.
- Autorización/consumo único de repuesto y entrega con acta antes pendientes ahora comprobados; cobro y arqueo exacto, usuario/contraseña/permisos y ajuste compensatorio.
- Regresiones rojas y verdes por grupos; PHPStan 0, Pint, lint, tipos y build. Revisión independiente final sin P1/P2; suite histórica completa no repetida.
- Informe AUDITORIA_VISIBLE_2026_10_10.md y evidencia storage/app/auditoria-visible-2026-10-10/; auditoría aún en curso con pendientes explícitos.
- Sin dependencias, commit ni push; datos originales y cambios previos conservados.

### 2026-10-10 — Continuación visible, CODEPLEX y notas/GRE

- Usuario retomó y abrió su cuenta real en Chrome: observar y probar formularios, sin facturar/boletar. Sin grabar, emitir, eliminar ni alterar configuración externa.
- Observados F/B/NC/ND, motivos/datos adicionales/referencias, lista/formulario GRE; cancelación y retorno al listado. CODEPLEX mantiene apartado GRE y entrada desde venta.
- GRE de equipo técnico sin producto daba500; corregido fallback técnico sin inventar peso/artículo. ND rechazada se anunciaba exitosa; corregido retorno del resultado fiscal. Etiqueta neutral de importe.
- Rojo2/2 y verde30/186; GRE posterior1/30; PHPStan 0, Pint, lint/tipos/build/diffcheck pasan. Revisión estática acotada sin P1/P2; suite histórica no repetida.
- Cobertura anterior actualizada: recojo/instalación, recepción/cambio de unidad, configuración ficticia y traslado ida/vuelta ya recorridos. Dos GRE/CDR son simulados en copia, nunca SUNAT real.
- Informe AUDITORIA_VISIBLE y anexo CODEPLEX_GRE_NOTAS actualizados; evidencia fresca copiada. Corregida afirmación anterior: las57 selecciones de firmas sí guardaban en copia.
- Pendientes: GRE contextual/paginación/detalle; NC por motivo/línea/cuotas; retorno físico vs ajuste monetario; saldo/reportes; relevo Planta→Campo y cierre posentrega. No declarar auditoría total.
- Código previo preservado, sin dependencias, commit ni push. Baseoriginal solo leída; pruebas fiscales en bruce_fire_testing con cliente simulado.

### 2026-10-10 —GRE contextual y continuidad de entrega

- Continuación autorizada: Edge visible, copia aislada y CODEPLEX/SUNAT externos sin emisión.
- GRE con origen/ref., regreso por rol y paginación; corregido403 del Gerente.21 borradores AUD-VISIBLE en copia, 0 enviados.
- Notas muestran original/acción por rol; cuotas 13 deshabilitadas tras reproducción UI, aún no implementadas.
- Relevo taller 5 a Campo y cierre posterior 5/8 guardados; actas/certificados/fechas conservados y venta 10 mantiene S/ 45 pagados.
- Asignación final bloqueada, confirmación/relevo coordinados y cola lista coherente; responsable desactualizado reproducido con regresión.
- Rojo8/8 iniciales,403 Gerente, responsable viejo y cola vacía; verde final 33/285. Una comparación de factory se ajustó a snapshot persistido.
- PHPStan 0, Pint, lint/tipos/build y revisión acotada sin P1/P2. Tres exclusiones antiguas de tipos retiradas; ninguna nueva.
- Ledger actualizado a 628 registros, incluyendo repeticiones/guiones; no equivale a botones únicos. Informes/evidencia/PLAN actualizados.
- Auditoría global en curso; pendientes NC por motivo, detalle/estados GRE, colas/sedes y hardware. Sin dependencias, commit/push ni datos originales alterados.

### 2026-10-10 — Cierre de lo pendiente de GPT (notas por motivo, GRE, equipo)

- Pedido: terminar lo que GPT dejó a medias. Había 8 pruebas en rojo escritas antes de implementar y la ficha de GRE sin página.
- Notas de crédito: `DesgloseNota` calcula por línea los motivos 03, 05 y 07; `IssueCreditNote` exige el saldo completo en 01, 02 y 06 (acumulado contra el total de la venta). Panel de la venta con selección de líneas y casilla de recepción física.
- Reporte y dashboard: ventas netas con notas de crédito y débito del período.
- GRE: página `guias/show`, enlace desde el historial y reenvío visible para guías rechazadas.
- Campo: colas por actividad. Nueva ficha `equipos.show`.
- Pruebas corregidas por datos erróneos, no por lógica: motivos que ahora exigen líneas, venta de otro vendedor, servicio aleatorio incompatible con el equipo. La anulación sin recepción física conserva las unidades vendidas (decisión de GPT).
- Verificación: suite completa 1014/1014, 5560 aserciones; formato, lint, tipos, PHPStan y Pint. No se revisó en navegador y no se probó SUNAT real.
- Sin dependencias, commit ni push.

### 2026-10-10 — Revisión en navegador de lo nuevo

- Navegado por botones (no por URL) en la base de pruebas `bruce_fire`, con la migración `nota_datos` aplicada y el frontend compilado.
- Hallazgos corregidos: la ficha del equipo se anidaba en un layout doble (`app.tsx` no la incluía); ninguna pantalla enlazaba a la ficha del equipo (ahora desde la orden del vendedor y la entrega de campo); el documento del destinatario de la GRE salía como código; el Gerente no veía a qué línea se aplicaba la nota que aprobaba.
- Comprobado: devolución por ítem desde el formulario hasta "Notas por aprobar" del Gerente; reporte neto (315 − 20 = 295) igual al dashboard; historial y ficha de GRE; ficha del equipo.
- Sin aprobar notas (enviaría a SUNAT beta). Pendiente revisar en celular, tema oscuro y axe.

### 2026-10-10 — Auditoría por botones: automatización y errores del flujo de planta

- Criterio del dueño: automatizar, no alargar procesos, y corregir errores. Recorrido por clics de Gerente (menú completo, sin errores de JS), Vendedor (venta, cobro desde orden) y Técnico de planta (recepción → checklist → cierre → entrega).
- Error 500 al «Cerrar taller y emitir certificados»: un equipo de alta rápida guarda el año como «No legible / Pendiente de verificar» y `certificate_units.anio_fabricacion` es numérico. `IssueCertificate` ahora guarda solo años de cuatro cifras (si no, nulo). Prueba nueva en `CertificateModuleTest`.
- Proceso más corto: en `en_proceso` hay un solo botón «Terminar trabajo y emitir certificados» (antes dos pantallas con P.H. en medio); el servidor encadena `trabajo_terminado` y `listo_certificado` en una transacción con las mismas validaciones. El botón de solo terminar se conserva como enlace secundario.
- Tras guardar el checklist del último extintor recibido, se lleva a la ejecución (antes volvía a Recepción sin pista del paso siguiente).
- Texto engañoso corregido: planta decía «avisa al vendedor» pero el aviso «Listo para entregar» ya es automático.
- Ya automatizado y comprobado: confirmar recepción toma la orden; «Marcar todo conforme» en el checklist; el comprobante sale solo según el documento del cliente; «Cobrar» desde la orden precarga cliente y servicios.
- Pendiente anotado: la pantalla de error 500 de una acción POST ofrece «Intentar de nuevo» que recarga esa URL y da 404; el panel del Gerente avisa que las tareas automáticas no corren si no está `schedule:work`.

### 2026-10-11 — Entorno de auditoría por botones para los cinco roles

- Pedido del dueño: todos los roles operativos y un entorno donde cualquiera (GPT, Claude o una persona) pueda auditar los flujos por botones.
- Recorrido por clics de las pantallas de menú de los cinco roles: sin errores (Gerente 12, Vendedor 10, Almacén 8, Campo 6, Planta 3).
- Nuevo `database/seeders/DemoAuditoriaSeeder.php` (idempotente, no corre en producción): cotizaciones en 4 estados, 10 órdenes en distintas etapas (taller y las cuatro colas de campo, una lista para entrega con acta), una deficiencia esperando autorización, alertas a 5 y 20 días, venta a crédito con cuota vencida y otra por vencer, comprobantes pendiente, observado y rechazado, un traslado a un segundo almacén, un vehículo y un conductor.
- Programador de tareas (`schedule:work`) levantado; sin él el Gerente ve la alerta de tareas detenidas.
- Automatización: la guía de remisión elige sola el único vehículo y conductor activos. Comprobado: traslado → «Emitir guía» → «Crear guía» en 2 clics, con motivo 04, destinatario, direcciones, bien y peso ya cargados.
- Guía para auditores: `docs/ai/GUIA_AUDITORIA_POR_BOTONES.md` (arranque, cuentas, mapa de flujos, cómo registrar hallazgos).

### 2026-10-11 — Auditoría por botones: Almacén, campo, adicionales y cobro

- Recorrido solo con clics. Almacén: recepción de compra (genera BF-EQ y deja el botón de stickers), ajustes y traslado → «Emitir guía» → «Crear guía»; «Despachar» queda deshabilitado hasta tener CDR. Campo: recojo completo con firma sin tomar antes la orden (pasa solo a Planta), checklist de inspección que arranca en «conforme» (CO2: manómetro N/A) y entrega con acta que cierra la orden. Vendedor: deficiencia → «Cotizar adicional» (elige repuesto y se rellenan cantidad y precio) → «Aprobar» (cotización e importe preseleccionados) → la orden pasa sola a «autorizado»; «Registrar pago» de cuota con forma y monto ya cargados. Gerente: cobranzas y cajas cuadran con lo cobrado.
- Defecto corregido: una orden entregada, cerrada o anulada seguía ofreciendo «Tomar esta orden». `ServiceOrder::asignacionPara` devuelve `finalizada` y `TomarOrden` no se muestra en esos estados. Prueba nueva en `CierreRolesEquiposTest`.
- Error del seeder demo corregido: `Equipment::factory()` creaba un producto nuevo por equipo y ensuciaba el catálogo (11 «Extintor PQS ABC 6 Kg» duplicados); ahora usa el producto existente, y se limpiaron de la base. Las ventas demo quedan como `VTA-DEMO-n` y las órdenes como `OT-DEMO-n`.
- Observaciones menores sin cambio: el desplegable de aprobar adicional muestra el total de la cotización (servicio + adicional) mientras la tarjeta muestra solo el adicional; «Despachar» deshabilitado no explica por qué junto al botón (sí en el texto superior).

### 2026-10-11 — Auditoría por botones (segunda pasada): usuarios, productos, notas, caja, campo y alertas

- Gerente: «Nuevo usuario» (los roles salen sin tilde: «Almacen», «Tecnico Planta»; la contraseña inicial se inventa a mano), «Nuevo producto» (el código interno es obligatorio y manual) y «Rechazar» una nota por aprobar funcionan. Vendedor: «Pasar a venta» precarga cliente, líneas y precio cotizado (falta elegir la serie del extintor); cierre de caja con arqueo ciego (2 campos) y apertura de turno (el fondo arranca en 0.00); «Ofrecer recarga» en alertas crea la cotización en 1 clic.
- Instalación de campo: valida con mensajes claros, emite el certificado y deja la orden lista para entrega.
- Defecto corregido: el técnico de campo que registraba una instalación, inspección, mantenimiento o entrega sin haber «tomado» la orden la dejaba sin responsable. `ServiceOrder::tomarSiLibre` la toma al registrar el trabajo (como ya hacía planta al recibir) y deja el evento `orden_tomada`; no pisa a otro responsable. Comprobado en la entrega de OT-DEMO-3.
- Defecto corregido: la ficha del cliente del Vendedor mostraba como «Cancelado»/«Pagado» las ventas en borrador o anuladas; ahora dice «Borrador» o «Anulada».
- Seeder demo: los extintores de alerta se ligan a una cotización de la sede (el vendedor solo ve extintores de su sede por venta, orden o cotización, como debe ser) y se agrega el servicio «Recarga CO2 5 lb».
- Incidentes de entorno: MySQL de Laragon se cayó durante la sesión (por eso hubo 500 y pantallas en blanco) y Vite de desarrollo no volvió a arrancar; se levantó MySQL y se compiló con `npm run build` sin `public/hot`. Ejecutar pruebas a la vez que otra corrida de pruebas provoca bloqueos en `bruce_fire_testing`: una sola corrida a la vez.

### 2026-10-11 — Cierre de pendientes y preparación de la auditoría total

- Fricciones menores resueltas: roles con tilde («Almacén», «Técnico de planta», «Técnico de campo»); botón «Generar» para la contraseña inicial (12 caracteres aleatorios); código de producto sugerido (`PRD-####` libre, con prueba); apertura de caja propone lo contado en el último cierre. Las tres primeras comprobadas en el navegador; la apertura de caja no se vio en pantalla porque el vendedor tenía turno abierto.
- Aprobar una nota de crédito en el navegador fue bloqueado por el control de permisos (envía un documento fiscal a SUNAT, aunque sea beta). La solicitud quedó creada y pendiente de aprobación del dueño. Recibir un traslado en destino depende del mismo envío.
- Observación: la lista de Ventas del vendedor abre filtrada en «Hoy» y, sin ventas ese día, ofrece «Emitir primera venta» aunque haya ventas anteriores.
- `docs/ai/GUIA_AUDITORIA_POR_BOTONES.md` incluye ahora los flujos y casos (OK, ERR, PERM y BORDE) por rol para la auditoría total.

### 2026-10-11 — Cierre final antes de la auditoría total

- Tras el reinicio de la PC se levantó MySQL, el servidor (8010) y el programador de tareas; datos demo intactos y los cinco roles responden 200 en todas sus pantallas.
- Apertura de caja: el fondo propuesto se calculaba al cargar la página y quedaba desactualizado al cerrar y abrir sin recargar; ahora se calcula al abrir el diálogo (comprobado: propone 150.00 tras cerrar con 150). Caja del vendedor abierta y operativa.
- Última suite completa: 1024/1024 (5589 aserciones). Después solo cambió una línea de `vendedor/cobranzas/index.tsx` (sin pruebas de frontend); tipos, lint y compilación pasan.
- Pendiente de decisión del dueño: aprobar la nota de crédito de prueba (envía a SUNAT beta) y, con ella, recibir en destino un traslado despachado.

### 2026-10-11 — Pruebas contra SUNAT beta, solo por botones (autorizadas por el dueño)

- Aprobar nota de crédito (Gerente, «Aprobar y emitir»): BC01-2 aceptada por SUNAT beta con CDR (código 0) en 15 s; la devolución de S/ 5 en efectivo quedó registrada.
- Boleta B001-4 y factura F001-1 emitidas desde «Nueva venta»: quedan «por enviar» (envío programado) y «Enviar ya» las envía; ambas aceptadas (10–12 s).
- Anulación total FC01-1 sobre F001-1 con «recibí físicamente los equipos»: aceptada; la venta quedó anulada, el stock volvió (7→8) y la devolución de S/ 15 en efectivo cayó en caja.
- Comunicación de baja de B001-4: SUNAT la aceptó, la venta quedó anulada, el stock volvió (8→9) y se registró la devolución.
- Guías de remisión: «Enviar a SUNAT» falla con mensaje claro porque faltan SUNAT_GRE_CLIENT_ID y SUNAT_GRE_CLIENT_SECRET (credenciales de Menú SOL, a cargo del dueño). Eso bloquea despachar y recibir traslados; no se simuló.
- Observaciones: los avisos (toasts) de éxito anteriores se acumulan en pantalla al emitir varios documentos seguidos; las boletas y facturas no salen a SUNAT hasta «Enviar ya» o el envío programado.

### 2026-10-11 — Avisos (toasts) y prueba de guías

- `use-flash-toast.ts`: un aviso nuevo reemplaza a los anteriores (`toast.dismiss()` y un `id` por mensaje, sin duplicados) y los errores duran 8 s. No se pudo comprobar la expiración visualmente: el panel del navegador de pruebas tiene la pestaña oculta (`document.hidden`) y Sonner pausa temporizadores y animaciones en ese estado, así que ahí los avisos nunca vencen. Revisar en un navegador visible.
- Guías de remisión: no existe un beta público de la API REST de guías. La librería `greenter/gre-api` pide `client_id` y `client_secret` generados en el Menú SOL de cada RUC y no publica valores de prueba; `gre-test.nubefact.com` es un servicio de terceros cuyo acceso no se pudo confirmar. Opciones: credenciales reales del Menú SOL, cuenta de pruebas de Nubefact o una GRE simulada solo en local (sin tocar SUNAT).

### 2026-10-11 — Guía de remisión simulada en la PC y traslado completo

- Nuevo `GreSimuladoClient` (acepta toda guía con un CDR de mentira y el mensaje «SIMULADO…»), activo solo con `SUNAT_GRE_SIMULADO=true` y únicamente en los entornos `local` y `testing`; en producción la bandera se ignora (`AppServiceProvider`). `phpunit.xml` fuerza `false`. `.env` local con `true`, `.env.example` con `false`. Dos pruebas nuevas en `GuiaRemisionTest`. La pantalla de guías muestra el aviso «SIMULADO».
- Recorrido por botones: traslado → «Enviar a SUNAT» (envía y consulta en un solo clic, queda «Lista para trasladar») → «Despachar» (en tránsito) → «Confirmar llegada» con un usuario de Almacén de la sede destino: la unidad pasó a Almacén Chimbote como disponible.
- Alta de ese usuario por Gerente → Usuarios con «Generar»; primer ingreso: confirmar contraseña y cambiarla. Funciona.

### 2026-10-11 — Mejora de la competencia #1: hallazgos y adicionales dentro de la orden

- Origen: ServiceTrade («qué falta y quién continúa» en una ficha conectada) y hueco hallado al auditar: la orden del vendedor decía «Espera tu autorización» pero no mostraba el hallazgo ni permitía decidir; había que ir a Servicios → pestaña Deficiencias.
- Cambio: la orden del vendedor muestra «Hallazgos y adicionales de esta orden» con las mismas tarjetas y acciones que la lista (Cotizar o Corregir adicional, Aprobar, Rechazar). Código compartido en `components/deficiencias-vendedor.tsx` (tarjeta, diálogos y lógica); la lista de deficiencias lo usa igual. Backend: `DeficiencyController::paraPantalla()` arma cada hallazgo para la lista y para la orden; `ServiceOrderController::show` envía `deficiencias`. Prueba nueva `DeficienciasEnLaOrdenTest`.
- Comprobado por botones, con cierre de sesión por el botón y entrada por el formulario: en OT-DEMO-7, «Cotizar adicional» (manómetro S/ 35) → «Aprobar» → «Autorizada», sin salir de la orden; la lista de Deficiencias sigue igual.
- Pendientes de la comparación: resumen de revisión en visitas (FireTrack), ofrecer recarga agrupada por empresa, repuestos sugeridos por hallazgo (Uptick; función nueva, pide REQ). Offline, préstamos, portal y margen por orden quedan a decisión del dueño.

### 2026-10-11 — Mejoras de la competencia #2 y #3

- #2 (FireTrack) Resumen de revisión: componente `components/resumen-revision.tsx` («N de M revisados, K con observaciones, P pendientes» y barra de avance; los observados cuentan entre los revisados). Lo usan la inspección de campo y mantenimiento en sitio (que tenía su propia versión, ahora única). Planta no lo muestra: su orden no trae los checklists por equipo.
- #3 (ServiceTrade/Uptick) Cotizar la recarga por empresa: en «Por vencer → Por empresa», el botón «Cotizar recarga (N)» crea una sola cotización con todos los extintores con serie vencidos o por vencer del cliente (el endpoint ya aceptaba varios; las líneas se agrupan por servicio con su cantidad). Comprobado por botones: COT-0005 por S/ 90 (2 extintores de CO2 × S/ 45) en un clic, antes dos ofertas. Prueba nueva en `MejorasProcesosTest`.
- Verificado por botones con cierre de sesión por el botón y entrada por el formulario.
- Sin hacer: repuestos sugeridos por hallazgo (función nueva, necesita REQ en el DRS, historia y fila en la Matriz); offline, préstamos, portal y margen por orden, a decisión del dueño.

### 2026-10-11 — Inventario de progreso con recorrido de los cinco roles

- El dueño pidió revisar todos los roles antes del informe; inventario anterior de solo código reemplazado por INVENTARIO_PROGRESO_POR_ROLES_2026_10_11.md.
- Gerente, Vendedor, Almacén, Planta y Campo recorridos por menús/enlaces/pestañas; acceso y salida con formulario/botón. Listas, fichas y formularios abiertos sin guardar operaciones del negocio.
- Servidor local iniciado; DemoAuditoriaSeeder confirmó datos existentes. Algunos estados demo ya avanzados no se repusieron. Guía aceptada observada explícitamente SIMULADO.
- Hallazgos H1–H10: check de Por asignar; visitas de campo enviadas a recojo; conteos/universos distintos; cuota pagada aún rotulada vencida; checklist precargado; aviso en el mínimo; móvil/Chispa; contexto financiero y etiquetas.
- Cinco pantallas representativas a 375 px en claro/oscuro y muestra de Tab/Escape del alta de usuario; tema devuelto a claro, viewport restaurado, sesión cerrada. Registro disponible de consola sin errores/avisos al finalizar.
- Sin axe, contraste medido, teclado completo, hardware, primer acceso/2FA ni emisión externa. Sin check/tsc/Pint/PHPStan/Pest: aplicación no modificada. No heredar como verificadas las pruebas previas.
- Rama codex/inventario-progreso-roles desde main; solo informe, PLAN y BITACORA, sin commit ni push.
- Pendiente aprobación del inventario/alcance; piloto recomendado ahora orden del Vendedor. No hay fases implementadas ni autorización nueva para corregir H1–H10.

### Auditoría de operaciones de todos los roles — 11/10/2026 (en curso)

- El dueño amplió el alcance: ejecutar procesos completos, no solo secciones principales. Informe en AUDITORIA_OPERACIONES_POR_BOTONES_2026_10_11.md; código de aplicación sin modificar.
- Operaciones ficticias AUD TOTAL1110 con respaldo previo. Recepción/no conformidad/corrección/ajuste/traslado con GRE simulada; cotización→nota interna crédito→pago parcial→anulación; arqueo/reapertura; taller→deficiencia→cotización/autorización→consumo→certificado→entregaCampo; mantenimiento, inspección, instalación y recojo→Planta ejecutados por botones.
- Nuevo500 reproducido al cotizar desde ficha (clients.sede_id inexistente); peso de líneaGRE no conservado; estados y textos inconsistentes descritos con evidencia. Sin correcciones hasta aprobación.
- CuentaChimbote existe pero password rechazado. Recepción destino bloqueada; sin cambiar credenciales. Envíos fiscales reales y cambios de acceso excluidos de esta ejecución.
- Stock7 conciliado tras todas las operaciones del producto19; venta19 anulada retira cuotas. Producto19, cliente6, recepción5/6 y órdenes14/15/16 son fixtures locales persistentes; no borrados.
- Tsc/PHPStan/Pint pasaron. Check señaló formato de informes. Suite completa iniciada una vez serial contra bruce_fire_testing; resultado pendiente. Sin commit/push/dependencias.
- Pendientes detallados en informe: combinaciones fiscales simuladas, otros controles/CRUD, móvil/teclado integral, axe, hardware y algunos documentos. Auditoría total NO terminada.

### 2026-10-11 — Continuación de auditoría: notas, mostrador y calidad

- NC descripción e ND aumento ficticias solicitadas por Vendedor, rechazadas por Gerente, rechazo y motivo confirmados desde venta original; sin número ni envío fiscal. Orden 16 entregada en mostrador con receptor ficticio, cerrada, acta PDF descargada y renderizada; conserva OG y PH. No conformidad física real.
- Cotizar recarga desde Por vencer crea COT-0008 con dos servicios por 90 en un clic. COT-DEMO-2 rechazada, retira acciones de venta. Muestra de cotizaciones a 375 px claro/oscuro: desplazamiento horizontal y Chispa sobre contenido.
- Precisado GRE: el servidor reemplaza ítems del traslado por datos del origen; el campo de peso parece editable pero se descarta. No afirmar fallo genérico de persistencia ni cambiar reglas.
- Suite completa terminó: 1028/1028 pruebas, 5612 aserciones, 764445 ms, una única corrida serial. Tsc, PHPStan y Pint pasaron; formato de documentos corregido y check final pendiente de registrar.
- Auditoría total sigue abierta: destino Chimbote sin credencial válida, emisión fiscal externa, acceso/2FA, hardware, axe y teclado por cada pantalla, combinaciones indicadas en informe. No código de aplicación, dependencias, commit ni push.

### 2026-10-11 — Traslado recibido en Chimbote

- A pedido del dueño se buscó la cuenta en registros del proyecto. Se recuperó la credencial vigente de su auditoría anterior, sin adivinar/resetear/cambiar acceso. Login por formulario de Almacen Chimbote Demo; Confirmar llegada termina traslado 2, Kardex destino +1, Consulta Rápida con Enter muestra 1 en Chimbote. Saldo origen 7, total 8; sesión cerrada por botón.
- Hallazgo adicional: Consulta Rápida restringe stock por sede pero muestra movimientos de producto de otras sedes y responsables. Confirmado por pantalla y StockLookupController; requiere revisar política de visibilidad, sin cambiar permisos ni código.
- PDF de reporte aún no observable; cotización demo visualizada en su visor. Informe mantiene límites de exportaciones/accesibilidad/hardware y emisión real; no afirmar auditoría total concluida.

- Check final pasó: 229 archivos formateados y 180 sin avisos/errores de lint. Git diff --check pasó. Solo documentación; PLAN actualizado está ignorado por el repositorio. La suite no se repitió tras cambios únicamente documentales.

### 2026-10-11 — Reporte de mejoras con shadcn/ui y Tremor

- A pedido del dueño se redactó PROPUESTA_UI_SHADCN_TREMOR_2026_10_11.md: referencias oficiales concretas, adaptación de marca, plan por rol/pantalla con datos/esfuerzo/riesgo, familia compartida, límites y fases A/B/C.
- Skill design-system aplicada. Referencias verificadas: shadcn Progress/Card/Badge; Tremor KPI Cards, Status Monitoring, Tables, Bar Lists y templates Overview/Planner/Insights. Solo inspiración de composición; no instalar sus dependencias ni migrar el stack.
- Condiciones del diseño: sin clics/pasos/campos nuevos, metas/ETA inventadas ni cambios de reglas fiscales/inventario/permisos. Piloto recomendado: orden del Vendedor. Correcciones funcionales separadas para aprobación.
- Solo documentación; no implementación ni nueva operación del negocio. La suite completa anterior no se repite por el reporte. Formato/check de esta entrega se registrará al finalizar.

- Verificación del reporte: npm run check pasó (230 archivos formateados, 180 sin avisos/errores de lint), git diff --check pasó. Se comprobó y precisó recojos/show.tsx en el mapa; sin cambios de aplicación ni corrida nueva de Pest.

### 2026-10-11 — Inicio aprobado de componentes y piloto (Fase A)

- El dueño aprobó comenzar con «a ver go». Familia sin dependencias en components/progreso/: barra determinada/indeterminada, etiqueta, segmentos, anillo y Pasos numerado/cheurón/puntos.
- Piloto Vendedor: detalle y lista de órdenes comparten pasosOrdenAtencion; Por asignar no recibe check si falta técnico, incluso con estado avanzado. Anulada/desconocida no simula una secuencia exitosa. Seguimiento de taller migra a Pasos con la etapa enviada por servidor.
- ResumenRevision delega en Progreso: sin progress nativo ni azul; conserva conteos. Inspección y mantenimiento comprobados por botones con aria 1/1, móvil 375 claro/oscuro. OT-DEMO-7 probado sin guardar: falta técnico, etapa en proceso; Enter abre orden y edición, Tab a fecha, Escape cierra. Indicadores sin interacción/foco nuevo.
- Comprobaciones iniciales: 5 pruebas Node nativas de representación pasaron; Pest afectado 12/12 con 82 aserciones serial; tsc, check, build, Pint y PHPStan pasaron. Ajuste posterior de texto visible Etapa actual y consumidor seguimiento pendiente de nueva compilación/recorrido.
- Axe no instalado; no se añadió dependencia ni se ejecutó mediante inyección. Contraste medido y teclado integral aún pendientes; no declarar WCAG completa. Solo cambios frontend y prueba de representación; backend/reglas/accesos intactos.
- Continuar fases aprobadas y guardar por fase; no commit/push. Suite completa de implementación reservada al final, sin pruebas paralelas; no confundir con la corrida anterior de auditoría.

### 2026-10-11 — Revisión y cierre del trabajo de GPT (progreso y UX)

- Revisada la familia components/progreso (accesibilidad, tokens, motion-safe) y sus usos en los cinco roles: sin hallazgos bloqueantes.
- Añadido: pasosCotizacion (Borrador → Enviada → Aceptada → Vendida, compacto en la lista de cotizaciones; rechazada/vencida/anulada sin secuencia), etiquetas en español de estado de orden en Campo (lib/estados-orden.ts), limpieza del panel de Campo (chip «Hoy» falso, azules sueltos).
- Corregido: 500 al cotizar desde la ficha del cliente (QuoteController::create filtraba clients por sede, columna inexistente); H2 acción sugerida y filtro «Recojos» de Campo ya no tratan una inspección/instalación/mantenimiento por recibir como recojo; H3 total de servicios de Campo coincide con la suma de sus tarjetas; H5 cuota pagada ya no dice «Vencido hace Nd» (ahora «Pagada · vencía …»).
- Pruebas: Node 9/9, Pest TecnicoCampoDashboard (con caso nuevo) y CotizacionDesdeCliente en verde; tsc, check, Pint y PHPStan pasaron. Suite completa final lanzada una sola vez, pendiente de registrar.
- Pendiente (sin tocar): H4 entregas, H9, H10 restante, peso GRE editable que se descarta, Consulta Rápida por sede, hitos en recojos/entregas/instalaciones show, KpiCard en paneles restantes. No commit/push.

### 2026-10-11 — Cierre de la modernización de progreso/KPI (Fases B y C)

- Pasos «Por entregar → Entregada → Cerrada» en la ficha de entrega de Campo (compactos a 375 px) y etiqueta de estado en español en instalaciones.
- Nuevo KpiFiltro (cifra que filtra, aria-pressed, tokens de marca) en los paneles de Campo y Planta; azules sueltos de esos paneles pasan a tokens primary. Cajas, Cobranzas y Reportes del Gerente usan KpiCard; «Vencido» en cero ya no sale en rojo.
- Decisión: el azul restante (~170 usos) es el color informativo del sistema (token info); no se mezcla con el rojo de marca. Notas del Gerente y recojos no reciben pasos: solo hay una cola pendiente, sería decoración.
- Verificado por botones: Campo (375 px claro/oscuro, filtro Pendientes con aria-pressed), ficha de entrega, Gerente Cajas/Cobranzas/Reportes con axe (WCAG 2 A/AA/2.2 AA) sin violaciones en claro y oscuro. Login/logout por formulario y botón.
- Pruebas: Pest completo 1030/1030 (antes de estos cambios solo-frontend), Node 10/10, tsc, check, build, Pint y PHPStan en verde. No commit/push.
- Quedan fuera por decisión del dueño: Consulta Rápida por sede, peso GRE editable, H4/H9/H10.

### 2026-10-11 — Cierre de pendientes H4, H9, H10 y peso de guía

- H4: la lista de entregas de Campo en «Todas» usa el mismo universo que sus cifras (listas, entregadas, cerradas).
- H10: la tarjeta de instalación cerrada dice «Ver instalación» en vez de «Registrar instalación».
- H9: el resumen del Vendedor aclara «sin descontar notas de crédito»; el del Gerente ya decía «netas».
- Guía de remisión: el peso por línea es de solo lectura (viene del producto); el peso bruto total sigue editable.
- Pruebas de entrega de Campo 14/14, tsc, check, build, Pint y PHPStan en verde. Pendiente solo Consulta Rápida por sede (decisión del dueño). No commit/push.

### 2026-10-11 — Logo del comprobante

- Causa: el Gerente no había subido logo en Configuración y el comprobante solo usaba ese; la cotización siempre usa el de la marca. CompanySetting::logoParaPdf usa ahora el logo de la marca como respaldo y el del Gerente cuando existe. Prueba nueva en CompanySettingModuleTest.
- Los campos (teléfono, correo, web, condiciones, leyenda, cuenta de detracción) sí se leen de la configuración; están vacíos en la base y por eso no salen: se llenan en Gerente → Configuración.
- Pest completo 1030/1030 antes del cambio; después: CompanySettingModule, BillingModule y NotaVenta en verde.
