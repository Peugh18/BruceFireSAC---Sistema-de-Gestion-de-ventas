# PLAN: Auditoría Integral Funcional, Resiliencia y Rediseño Estilo Apple

## Estado General

- **Fase Actual:** Fase 1 — Análisis y Auditoría Módulo por Módulo
- **Última Actualización:** 2026-10-05

---

## Fases del Plan

### Fase 1: Auditoría Técnica y Funcional Módulo por Módulo [COMPLETADA]

- [x] **1.1 Módulo Vendedor & Ventas:** Cotizaciones, CRM clientes, proceso de venta, caja, comprobantes de pago, notas de crédito.
- [x] **1.2 Módulo Facturación Electrónica SUNAT (Greenter):** Generación de XML UBL 2.1, firmas digitales, envío a WS SUNAT, manejo de CDR, timeouts, estado de contingencia. (Detectado bug 404 en PDF de Notas de Crédito).
- [x] **1.3 Módulo Almacén & Control de Lotes:** Movimientos de kardex, insumos (PQS, CO2), trazabilidad de lotes, transferencias entre sedes. (Detectado edge-case en `origen_sede_id`).
- [x] **1.4 Módulo Técnico de Planta (Taller):** Recepción de extintores, checklists técnicos (NTP 350.043), pruebas hidrostáticas, deficiencias y solicitudes a vendedor. (Detectado riesgo 500 en `service->nombre`).
- [x] **1.5 Módulo Técnico de Campo:** Rutas, entregas, firma táctil digital, actas y sincronización. (Detectado leak de conteos globales y N+1 en eventos de custodia).
- [x] **1.6 Módulo Gerente & Seguridad:** Auditoría de logs, arqueos ciegos, permisos Spatie, aislamiento multi-sede (`{current_team}`). (Detectado riesgo null en `cr->vendedor->id`).
- [x] **1.7 Verificación Pública & Asistente Chispa:** QR para ITSE, enlaces firmados, rate limiting y resiliencia.

### Fase 2: Auditoría de Usabilidad y Resiliencia de UI (4 Estados) [COMPLETADA]

- [x] **2.1 Estados Vacíos (Empty States vs Search Zero-Results):** Verificación de llamadas a la acción y mensajes claros en cada listado.
- [x] **2.2 Prevención de Errores y Doble Envío:** Bloqueo de botones `disabled={processing}` con loaders visuales.
- [x] **2.3 Modales de Confirmación:** Acciones destructivas (anulaciones, cierres de caja) con confirmación explícita.
- [x] **2.4 Ergonomía Móvil y Táctil:** Vistas operativas en teléfonos con áreas táctiles ≥ 44px e inputs sin auto-zoom.
- [x] **2.5 Integración de Hallazgos de Obsidian:**
    - Diagnóstico de widget Chispa tapando botones en móvil (375px).
    - Diagnóstico de 4 estilos inconsistentes de pestañas.
    - Falta de componente unificado para tarjetas KPI y tablas de datos.
    - Disparidad de tokens manuales (`text-[..px]` y `rounded-[..px]`).

### Fase 3: Especificación y Directrices de Rediseño Estilo Apple [COMPLETADA]

- [x] **3.1 Sistema de Tokens y Materiales Apple:** Translúcidos (`backdrop-blur`), bordes sutiles, sombras suaves difusas, curvaturas squircle (`rounded-2xl`).
- [x] **3.2 Tipografía y Ritmo Visual:** Escala tipográfica SF Pro / Inter, espaciado uniforme, pesos balanceados.
- [x] **3.3 Micro-interacciones Táctiles:** Efectos `active:scale-[0.98]`, transiciones de resorte sutiles con tiempos de respuesta inmediatos.
- [x] **3.4 Modo Oscuro Pulido:** Contrastes WCAG AA, superficies de profundidad diferenciada (fondo, barra lateral, tarjetas elevadas).
- [x] **3.5 Componente Maestro Apple Segmented Control:** Reemplazo de los 4 estilos dispares por una pastilla deslizante iOS.

### Fase 4: Plan de Ejecución Priorizado

- [x] **4.1 Corrección de Bugs Funcionales Críticos [COMPLETADA]:**
    - Generación de PDF y descarga de Notas de Crédito / Débito (reparado 404 en `EmitElectronicDocument` y `ComprobantePdfService`).
    - Corregida condición de carrera con `firstOrCreate` en `ReserveNextCorrelativo`.
    - Scope multi-sede estricto en contadores de campo (`DeliveryController`, `InspectionController`).
    - Optimización N+1 eliminada en eventos de custodia de campo (`CollectionController`).
    - Safe navigation y eager load de `service` en taller (`ExecutionController`).
    - Safe navigation en cajas consolidadas para usuarios dados de baja (`CashRegisterConsolidatedController`).
    - Manejo seguro y resiliente de sedes en traslados de almacén (`TransferController`).
    - Sincronización de KPIs según rol restringido vs gerente en ventas (`SaleController`).
    - **Mascota Chispa garantizada en errores 404, 500 y 403**: Creadas vistas Blade de respaldo con poses (`busca`, `corre`, `piensa`, `sentado`) y branding oficial.
- [ ] **4.2 Implementación del Sistema de Diseño Apple en UI:**
    - Componentes maestros: `AppleSegmentedTabs`, `AppleKpiCard`, `AppleTableCard`.
    - Reubicación ergonómica y no invasiva de la mascota Chispa en móviles.
    - Inyección de `currentSede` completa en `HandleInertiaRequests`.
    - Transición a materiales translúcidos (`backdrop-blur-xl`) y bordes hairline.
    - **REGLA DE ORO:** Respetar intacto al 100% el diseño del slider/sidebar curvo cóncavo (`.bf-nav-active`).

### 4.3 Bloqueantes para producción (auditoría 2026-10-05, 2.ª pasada)

- [x] Verificar con `composer ci:check` los cambios de la 1.ª pasada (fallaban formato y PHPStan).
- [x] PDF de notas de crédito/débito igual al XML (una línea: motivo × importe), sin cuotas ni vencimiento.
- [x] `ReserveNextCorrelativo`: `createOrFirst` + volver a bloquear la fila (el `firstOrCreate` no evitaba la carrera).
- [x] Traslados: estado vacío cuando no hay almacén activo (antes la pantalla se rompía con `sourceSede = null`).
- [x] **CRÍTICO:** quitar "Eliminar cuenta" (botón, ruta `profile.destroy` y validación) (Ajustes → Perfil). Hoy cualquier usuario puede borrarse y la BD borra en cascada sus ventas, comprobantes SUNAT, cajas y cotizaciones.
- [x] Borrado en cascada de usuarios y clientes: ya estaba bloqueado por la migración `2026_09_28_014309_proteger_historial_legal_y_financiero` (falso positivo de la auditoría). Se añadió `tests/Feature/BorradoProtegidoDeHistorialTest.php` para que no se pierda.
- [ ] Copia de respaldo fuera del servidor (hoy `backup:bd` guarda en `storage/app/backups` del mismo equipo) e incluir `storage/app/private/xml` y CDR.
- [ ] Revisar `trustProxies` según el hosting (Cloudflare o balanceador) y `SESSION_SECURE_COOKIE=true`.
- [ ] Servidor: cron `schedule:run` cada minuto y un worker de colas (`queue:work`) supervisado.
