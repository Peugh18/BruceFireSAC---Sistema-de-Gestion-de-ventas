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
