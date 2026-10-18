# Bitácora — Bruce Fire SAC v2

> Una entrada por sesión. El historial anterior se resumió el 2026-10-18; el detalle completo sigue en git y en las pruebas.

## Hasta el 2026-10-18 — Resumen de lo construido (Claude, Codex/GPT y MiMo)

- **Base:** Laravel 13 + Inertia 3 + React 19, cinco roles, sedes (tienda con su almacén), auditoría y reglas en `.ai/rules`.
- **Comercial:** clientes con consulta DNI/RUC (APIsPeru), cotizaciones, ventas, caja con salidas y cierre explicado, cobranzas, notas de venta formalizables.
- **SUNAT:**
    - factura, boleta, NC y ND con Greenter (TLS verificado, envío diferido de 6 h);
    - crédito con cuotas;
    - detracción con catálogo 54 en BD, tasa por fecha y estado por tipo de servicio;
    - QR y PDF con copia histórica;
    - GRE por la API REST (sandbox).
- **Catálogo:** formularios cortos, códigos PRD/SRV, tipo de servicio obligatorio, catálogo 25 oficial (52 840 códigos, `sunat:importar-codigos-producto`), agentes, marcas y categorías administrables.
- **Operación técnica:** órdenes con varias líneas, recojo desde Campo, orden de taller por pasos en Planta, chat, casilleros, checklist, stickers de 80 × 50 mm y lector de barras en almacén.
- **Certificados:**
    - editor con vista previa en vivo (vertical u horizontal), tabla y pruebas editables con versionado;
    - una ficha por firmante, con el cargo en cada certificado;
    - Firmas y sellos queda como directorio de personas.
- **Gerencia:** dashboard de 8 métricas con IA de recompra, PDF mensual y sedes comparadas.
- **Verificación más reciente:** 360/360 pruebas de certificados y firmantes; suite completa anterior de 1294 (8 ajustadas); PHPStan 0; tipos y lint en verde.

## 2026-10-18 — Documentos ordenados y auditoría preparada (Claude)

- Se borraron `docs/FACTURACION_GREENTER_SUNAT.md`, `PLAN_CATALOGO_SUNAT.md`, `PLAN_IMPLEMENTACION_SUNAT.md` y `DECISIONES.md`. Sus decisiones vigentes pasaron a `PLAN.md`.
- **Nuevos:**
    - [docs/SUNAT_FACTURACION.md](../SUNAT_FACTURACION.md): normas vigentes con fuente oficial y fecha (plazo de 3 días leído en la R.S. 000003-2023; postergación al 01/01/2027 leída en la R.S. 000143-2026);
    - [docs/APIS.md](../APIS.md): Greenter, GRE API y APIsPeru, con solo los nombres de las variables;
    - [PROMPT_AUDITORIA.md](PROMPT_AUDITORIA.md): auditoría por rol para ChatGPT.
- `PLAN.md` y esta bitácora se reescribieron cortos.
- **Siguiente paso:** el usuario pasa el prompt a ChatGPT, trae los resultados, se priorizan y se corrige por flujo. Luego se cierra la rama.
- Ideas del usuario por rol ordenadas en [CONTEXTO_SISTEMA.md](CONTEXTO_SISTEMA.md), con 6 dudas abiertas (planta y técnicos, almacén por sede, librería de gráficos, edición del certificado, rollo de stickers, módulo de guía). El prompt de auditoría ahora compara contra ese documento.

## 2026-10-18 — Decisiones del dueño y prompt para GPT (Claude)

- §9 de CONTEXTO_SISTEMA con las 6 respuestas. Los lugares son Tienda, Almacén y **Taller** (donde se recarga; ahí trabajan los técnicos de planta y atiende a todas las tiendas).
- Se creó [PROMPT_GPT_IMPLEMENTACION.md](PROMPT_GPT_IMPLEMENTACION.md): GPT/Codex primero audita los 8 cambios de §10 y después los implementa por fases (TDD, chequeos, bitácora por fase).
- **Siguiente paso:** pegar ese prompt en Codex, revisar su auditoría y aprobar la Fase 1.

## 2026-10-18 — Relevo de Claude revisado (Codex)

- Se confirmó que `PROMPT_GPT_IMPLEMENTACION.md` existe y que PLAN y BITÁCORA ya apuntan a él: la preparación del relevo sí quedó guardada.
- Rama `feat/ordenes-varios-servicios`: 5 commits sobre `main` y 321 entradas en `git status --short`.
- Comprobación puntual: `StoreSedeRequest` aún admite solo tienda/almacén/mixta y `User::TIPOS_DE_SEDE_POR_ROL` aún asigna técnicos a almacén/mixta. `package.json` no declara recharts ni pdfme.
- Solo se revisó el estado; no se modificó código ni se ejecutaron pruebas. Los resultados anteriores siguen siendo evidencia histórica.
- **Siguiente paso:** realizar la auditoría de los ocho cambios; presentar los hallazgos antes de implementar fases.

## 2026-10-18 — Investigación de orquestadores (Codex)

- Comparados repositorios/documentación y partes del código de Ruflo, Orchestrator.inc, agtx y CCPM; revisados también Agent of Empires y Claude Squad.
- Primera opción documental: Orchestrator.inc, anteriormente Composio Agent Orchestrator; Windows nativo, supervisor, workers con worktrees y feedback de CI/review. Fuente: https://docs.orchestrator.inc/.
- Ruflo tiene ejecución real por su orquestador dual; `agent_spawn` MCP solo registra coordinación. CCPM depende del harness para ejecutar; no demuestra por sí solo lanzar Claude y Codex externos.
- No se confirmó integración del perfil `codex -p mimo`; MiMo Code es otro adaptador. Conservar uso interactivo de MiMo, sin bucles automáticos.
- Entorno: Claude, Codex, Git, gh y Node presentes en PATH; tmux y Orca no encontrados. Git muestra 344 entradas pendientes en esta comprobación.
- Solo investigación y memoria; sin instalar, ejecutar orquestadores, modificar aplicación, hacer commit/push ni correr pruebas de aplicación.
- Siguiente paso propuesto: piloto aislado con dos workers y revisión; validar perfiles, autenticación y reglas antes de adoptar.

## 2026-10-18 — Orca añadido a la comparación (Codex)

- Revisadas fuentes de Orca de Stably: https://www.onorca.dev/docs/cli/orchestration y https://www.onorca.dev/docs/agents/supported.
- Windows nativo, worktrees, terminales, navegador y CLI; coordinación con tareas, dependencias, mensajes y workers supervisados, marcada experimental.
- Permite personalizar argumentos y entorno del agente, pero `codex -p mimo` sigue pendiente de prueba, especialmente bajo supervisión y reanudación.
- Documenta bypass de permisos por defecto; revisar Settings antes del piloto. Worktree separado no equivale a sandbox.
- Candidato junto a Orchestrator.inc; sin instalación, ejecución ni cambios de aplicación. No se corrieron pruebas de aplicación.

## 2026-10-18 — Fase 1: Taller, traslados y formatos de stickers (Codex)

- Añadido `sedes.tipo=taller`; técnicos pertenecen al Taller y atienden todas las tiendas, conservando órdenes antiguas del lugar convertido. Los repuestos siguen usando el almacén de la orden.
- Menú y cuatro rutas de traslados exigen dos almacenes activos. Conversión/desactivación protegen stock reservado/en tránsito y traslados pendientes en origen/destino.
- Impresión común en rollo 80×50 o A4 2×5 desde recepción, unidad y taller; corregidas hojas térmicas vacías. Migración de Taller aplicada en local.
- RED reproducido y regresiones pasadas en lotes conjuntos (43/43 y 50/50, que incluyen fases posteriores); corrida final conjunta 149/149 aprobada.

## 2026-10-18 — Fase 2: BI de los cinco roles (Codex)

- Instalado únicamente `recharts`, dependencia aprobada. Cinco dashboards con gráficos reales, estados vacíos y tablas accesibles; ticket promedio retirado de los inicios.
- Se mantienen cálculos de reportes/PDF y filtros de sede/vendedor; Campo tolera órdenes sin servicio principal. Pruebas de los cinco dashboards verificadas en el lote inicial (77/79; los dos fallos eran mensajes antiguos de Sedes ya corregidos).
- TypeScript y PHPStan sin errores; compilación y formato/lint pasados. Revisión estática de permisos, datos agregados y no duplicar órdenes.

## 2026-10-18 — Fase 3: Recompra e IA (Codex)

- Pantalla propia `gerente/inteligencia`, menú y enlace desde dashboard. Reutiliza ClientRetentionScore e historial, con filtros, paginación y aviso de puntuaciones de más de 30 días.
- Probabilidad estimada separada de vencimientos reales; no se inventan resultados cuando no hay puntuaciones.
- Cuatro pruebas IA pasadas en lote conjunto. Dashboard e IA abiertos con cuenta demo en navegador; comprobado estado vacío en escritorio claro.

## 2026-10-18 — Fase 4: editor y preservación histórica (Codex)

- Eliminado iframe de formatos fijos: hoja editable, arrastre de bloques con dnd-kit y alternativa numérica/teclado; vista propia de diploma. Distribución JSON validada ±24 pt, versionada y aplicada a dompdf. Migración aplicada en local.
- Evaluado pdfme en su documentación oficial; se conserva el motor actual para evitar dependencias y migración de emisión. Word conserva textos pero no desplazamientos del PDF.
- Se reprodujo y corrigió que cambiar un firmante alterara nombre/CIP/imagen de versiones emitidas nuevas: ahora la firma se incrusta en la configuración y los cambios de persona incrementan versión. No se reconstruyen archivos borrados antes del arreglo.
- Lote 50/50 pasó con firmas, edición, PDF de tres familias y Campo; PHPStan 0 y TypeScript OK.

## 2026-10-18 — Fase 5: Campo hacia certificados (Codex)

- Formularios dinámicos desde columnas/checklist del certificado asociado a cada servicio/línea; instalación y mantenimiento validan obligatorios, números y versión antes de cerrar.
- Las mediciones van una sola vez al certificado vinculado a orden y versión, también sin venta ni extintores. Se conservan evidencia, firma del cliente, controles de asignación, bloqueo transaccional y prevención de duplicados.
- Reutilizada validación del emisor existente; sin umbrales eléctricos inventados. Se muestran las mediciones ya emitidas al volver a abrir la ficha.
- Regresiones en lotes 43/43 y 50/50. Caso adicional de agenda con pozo en línea sin servicio principal reproducido y corregido; verificación conjunta final 149/149 y ampliación 175/175 aprobadas.

## 2026-10-18 — Fase 6: stickers por producto (Codex)

- Se reutiliza `products.codigo` PRD como identidad interna escaneable. Una etiqueta por producto distinto recibido por cantidad; se mantiene el barcode de fábrica y BF-EQ para unidades serializadas.
- Recepciones a granel visibles en Stickers; reimpresión PRD con formatos rollo/A4 y autorización de Almacén/Gerente. Pruebas de producto pasadas dentro del lote 43/43.
- Sin commits, push ni cambios de secretos. Corrida final de 21 archivos afectados: 149/149; ampliación posterior de certificados y agenda: 175/175. Una sola base de pruebas y sin paralelización.
- **Límite de verificación visual:** tras abrir dashboard e IA, el navegador respondió «Local browser use is disabled in Work settings». Móvil, tema oscuro y axe siguen pendientes; no se declaran aprobados.
- **Siguiente paso:** revisión visual móvil/claro/oscuro y axe cuando esté habilitado el navegador; suite completa/CI y cierre de rama cuando el usuario lo pida.

## 2026-10-18 — Verificación conjunta y revisión de seguridad del relevo (Codex)

- **149/149 pruebas, 1339 aserciones, 211.198 s** en 21 archivos afectados; una sola ejecución secuencial, sin pruebas paralelas. Ampliación posterior: **175/175 pruebas, 1167 aserciones, 258.366 s**; incluye consumidores PDF/Word, verificación pública y la variante de agenda con otro servicio principal. Ambos lotes comparten pruebas; no se suman como casos distintos.
- PHPStan: 0 errores; `npm run types:check`, Pint, `npm run check` (sin warnings) y `npm run build`: exit 0. `git diff --check` sin errores de espacios.
- Revisión de seguridad/cumplimiento: permisos por rol y asignación, formatos/posición validados, texto escapado en PDF, snapshots históricos y guardas de inventario/concurrencia; hallazgos corregidos con regresión. Sin cambios de emisión SUNAT.
- `npm audit --omit=dev`: 3 paquetes con alertas (2 críticas, 1 alta): concurrently 10.0.5, shell-quote 1.9.0 y source-map-js 1.2.1. Se contrastó con HEAD: las tres versiones ya eran esas. No se cambian dependencias adicionales sin autorización.
- No se ejecutó la suite completa ni CI, ni se hizo commit/push. Se conservan las entradas de investigación de orquestación de otra sesión compartida.

- Cierre: los ocho cambios tienen código y regresiones aprobadas. Limitaciones explícitas: sin revisión móvil/oscuro/axe por navegador deshabilitado, sin suite completa/CI y con tres paquetes de dependencia ya vulnerables en HEAD pendientes de autorización de actualización. Editor PDF por bloques (±24 pt); Word conserva contenido, sin esos desplazamientos. Sin commit/push.

## 2026-10-18 — Publicación e integración solicitadas por el usuario (Codex)

- Autorización explícita para subir todo a GitHub y unificar en main. Se reutiliza PR #36; PR #37 integrado con CI aprobado.
- Revisadas ramas y worktrees: cotizaciones ya está integrado; los cambios sin commit del worktree viejo de Claude son la implementación previa de órdenes multilínea y sus títulos de pruebas ya existen en la rama actual. Se preservan sin modificar. La rama de respaldo conserva historia anterior reescrita, no funciones nuevas que deban sobreescribir la implementación vigente.
- Se incorpora PLAN.md a Git para compartir el avance del proyecto; se mantienen los archivos personales excluidos.
- Antes de publicar: formato/lint y TypeScript aprobados; Pint aprobado y PHPStan 0. Suite completa local en ejecución; integración del PR condicionada a CI verde.
