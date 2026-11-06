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

- Primer CI del PR #36: 1348 aprobadas y 5 fallos (7654 aserciones). Causas: CSV de fixture excluido por `*.csv` (tres fallos), sede mixta en prueba que ahora requiere Taller y servicio de instalación con nombre ambiguo de mantenimiento excluido correctamente del listado. Se versiona el fixture y se ajustan los datos de esos dos casos, conservando sus aserciones de auditoría, aislamiento y KPI.

- Trabajo acumulado publicado en PR #36; referencia de factura recuperada y PR #37 integrado. Build de producción repetido con éxito, 15/15 pruebas TypeScript y pruebas del runner PowerShell aprobadas. El PR enlazado conserva la evidencia definitiva de CI y merge; no se incluyen despliegue ni actualización de dependencias adicionales.

## 2026-10-18 — Dashboards BI rediseñados (Claude)

- Diseño aprobado por el usuario en Claude Design: [Dashboards BI Bruce Fire](https://claude.ai/artifact/DuX9RhKBpxsVRwiuh4KX8V).
- Nuevo `resources/js/components/bi.tsx` con atajos, "Requiere tu atención", indicador con mini línea, tendencia en área, anillo, ranking o embudo, barra apilada, columnas, mapa de calor y "Tu semana". Colores `--bi-*` en `app.css`, validados para daltonismo. Se borró `graficos-dashboard.tsx`.
- **Gerente:** solo BI. Las tarjetas de IA y recompra salen del dashboard y quedan en Recompra e IA. Atajos a productos, servicios, caja y cobranzas.
- **Almacén:** atajos a recepción, buscar, ajuste, stickers y traslados (este último solo con 2 o más almacenes). Indicadores de la semana y mapa de calor.
- **Vendedor:** atajos, incluida la orden de servicio; ventas acumuladas día a día contra el mes pasado; cotizaciones y cartera.
- **Planta y Campo:** la cola o la ruta primero, y "Tu semana" al final.
- **Backend:** datos nuevos `graficos.tendencias` y `graficos.pasos` del gerente, `graficos.acumulado` del vendedor y `graficos.dias` del almacén. Los 4 tienen prueba.
- **Verificación:**
    - 48/48 pruebas en los 7 archivos de dashboards, IA y conciliación;
    - PHPStan 0, Pint, tsc y `vp check` en verde;
    - gerente y Recompra e IA revisados en el navegador en escritorio y celular, claro y oscuro, con axe sin violaciones.
- **Pendientes:**
    - La base local no tiene ventas, así que los gráficos con datos solo se vieron en el canvas.
    - Almacén, vendedor y técnicos no se abrieron en el navegador: sus usuarios locales no son los de la semilla.
    - La prop `iaRecompra` sigue en el controlador del gerente, porque 3 pruebas la leen; quitarla requiere aprobar el cambio de esas pruebas.

## 2026-10-18 — Recompra e IA rediseñada (Claude)

- Diseño aprobado en Claude Design (artboard "Gerente · Recompra e IA").
- **Pantalla:**
    - tabla compacta de clientes y detalle del cliente elegido, con "Lo que dice la IA" y los factores a favor y en contra;
    - tarjetas del modelo (tipo, acierto, top 100, casos, fecha e historial), de lo que más pesa (SHAP) y de Chispa.
- **Sin duplicar la recarga anual**, que queda en "Por vencer" del vendedor:
    - indicador "Fuera del calendario": clientes con probabilidad alta sin extintores por vencer en 90 días;
    - filtro `calendario=fuera`.
- **Backend:** `InteligenciaController` agrega `modelo` (null si no hay archivo), `chispa` y `resumen.fueraDelCalendario`.
- **Verificación:**
    - 16/16 pruebas (`GerenteInteligenciaTest` y `MlRetentionModelTest`);
    - PHPStan 0, Pint, tsc y `vp check` en verde;
    - revisada en el navegador en escritorio y celular, claro y oscuro, con axe sin violaciones, usando el modelo local real (regresión logística, acierto 0.76).
- **Idea pendiente, a aprobar:** agregar al modelo los días que faltan para el vencimiento, para que separe solo la recarga anual de la recompra por uso.

## 2026-10-18 — Relevo de Claude Design y propuesta de fondos (Codex)

- Leído directamente en Claude el chat «Revisión de contexto GPT» y el artefacto «Fondo de certificados Bruce Fire»; el último pedido era corregir tipografías y diseño antes de continuar con los bloques.
- Preparada propuesta local A4 vertical/horizontal con Montserrat, Barlow e IBM Plex Mono del repositorio; logo a color, ASNEEX formal, franjas, marca de agua, sello y campos de referencia.
- El horizontal conserva la composición del diploma: logo centrado, título y cinta roja, verificación a la izquierda, sello central e instructor a la derecha.
- Entregable: `C:/Users/migue/.codex/visualizations/2026/10/19/01a15218-7815-70a3-a37d-19d3574d537a/fondos-certificados.html` y vista independiente `revision-certificados.html` en la misma carpeta.
- Playwright local: imágenes y fuentes cargadas, cambio de orientación, ocultar guías, anchos 320/390/736/1024 sin desbordes y sin errores JS. Ambos formatos inspeccionados en capturas.
- Sin cambios en las plantillas de emisión ni en el artefacto de Claude, sin nuevas dependencias ni commit/push. Pendiente revisión del fondo por el usuario y luego bloques e integración.

## 2026-10-18 — Corrección: recuperar el diseño exacto de Claude (Codex)

- Usuario indicó que la propuesta anterior no correspondía al diseño de Claude. Se verificó el artboard vertical ampliado y se recuperaron los HTML originales desde la ruta del scratchpad expuesta en el historial de la aplicación.
- Diferencias confirmadas: cabecera original en una sola fila (logo 268 px, ASNEEX al medio y contactos 196 px), título compacto y área de contenido más alta; Barlow Condensed itálica en vertical y Bebas Neue en horizontal. La propuesta previa de Montserrat y franja ASNEEX aparte no fue aceptada.
- Vista fiel `original-certificados-claude.html` en la carpeta de visualizaciones del chat. Se preservó el contenido/distribución de los originales; solo se resolvieron las imágenes y fuentes y se añadió el selector de formato y escalado del contenedor.
- Fuentes públicas originales incrustadas; sin dependencia de red en la vista final. Playwright: ambos formatos y sus imágenes cargados, sin errores JS ni solicitudes fallidas, contenedor sin desbordes a 320/390/736/1024 px; capturas contrastadas con Claude.
- No se modificó el original de Claude ni las plantillas de emisión. Sigue pendiente corregir las observaciones del usuario sobre ese original y luego continuar con los bloques. Sin commit/push.

## 2026-10-18 — Recuperado el acuerdo funcional anterior al fondo (Codex)

- En el historial de Claude se leyó «La idea: un fondo común y encima solo el contenido», seguido de confirmación del usuario. No se trata solo de dibujar fondos.
- Crear un certificado nuevo: elegir vertical/horizontal, bloques reutilizables y textos, y ligarlo al servicio o producto. Marco, ASNEEX, número/QR y posiciones de firmas protegidos; se eligen personas, cargos y CIP por certificado.
- Bloques: título/subtítulo/norma, cliente y servicio/venta, declaración, tabla configurable, pruebas, especificaciones, garantía/mantenimiento, resultado y texto final. Diploma como bloque horizontal.
- ASNEEX en todos confirmado explícitamente; posición de contactos dejada al criterio de diseño. Alcance registrado para retomar la implementación; en esta revisión no se cambió código.

## 2026-10-19 — Fondo común de certificados y arreglo del certificado dinámico de Codex (Claude)

- **Fondo aprobado en Claude Design** («Fondo de certificados Bruce Fire»): vertical igual al OG y horizontal igual a la capacitación, con las fuentes del sistema y la credencial formal de ASNEEX en todos.
- **Credencial ASNEEX** en `resources/views/pdf/partes/asneex.blade.php`, siempre presente: en la fila de firmas del OG y arriba a la derecha en la capacitación. Reemplaza el «ASOCIADO» que solo salía en el OG.
- **Certificado dinámico de Codex:**
    - se conservó su lógica (composición, ligar a productos y tipos de servicio, versiones);
    - se reemplazó su plantilla simplificada (`certificado-dinamico*.blade.php`, fuentes Barlow Condensed y Bebas no aprobadas). Ahora `certificado-operatividad.blade.php` se arma por bloques según la composición (o en el orden de siempre si no tiene) y los diplomas horizontales usan `certificado-diploma.blade.php`;
    - se borraron las fuentes nuevas y la vista previa del editor usa las fuentes del sistema.
- **Lógica que Codex dejó con pruebas en rojo:**
    - validaciones del creador: desde cero exige bloques y firmas; tabla con columnas; texto no vacío; pruebas con puntos; resultado con algo que evaluar; máximo ≥ mínimo; columna de resultado automática;
    - no declarar «operativo» con todas las pruebas en «No aplica»;
    - Campo ve las órdenes con plantilla por bloques sin tabla;
    - «Armar certificados» de la venta solo agrupa extintores y capacitación.
- `CertificatePdfService::html()` da el HTML que se convierte a PDF, porque dompdf vacía el cuerpo de su árbol al dibujar. Las pruebas dinámicas lo usan.
- **Verificación:**
    - 263/263 pruebas en los 32 archivos de certificados, firmas, campo y taller;
    - PHPStan 0, Pint, tsc y `vp check` en verde;
    - muestras de OG, LM y capacitación revisadas en el navegador: 1 hoja cada una, ASNEEX visible.
- **Pendiente:**
    - revisar en el navegador la vista previa del editor dinámico (hoja-dinamica);
    - quitar los botones «Mover» del editor de formatos fijos (falta aprobación);
    - asistente de creación con plantillas base y ficha técnica del producto para el certificado de mangueras.

## 2026-10-19 — Vista previa igual al PDF, marco fijo y limpieza de IA en el dashboard (Claude)

- **Vista previa del editor por bloques** (`hoja-dinamica-certificado.tsx`) reescrita como réplica del PDF, con las mismas medidas en mm/pt y las fuentes del sistema:
    - cabecera con logo, RUC, WhatsApp y Gmail;
    - título ajustado al ancho, tarjeta de QR y norma;
    - barras grafito con cola roja, datos en dos columnas, tabla, pruebas C/NC/NA y resultado;
    - firmas con ASNEEX a la izquierda y el sello al centro, y pie de página;
    - el diploma horizontal es igual a `certificado-diploma.blade.php`, con ASNEEX arriba a la derecha.
- **Diploma PDF:** el título se achica para entrar en la hoja (`tamano_titulo`), como ya pasaba con el nombre y el curso.
- **Marco fijo:**
    - se quitaron «Mover», los campos Horizontal/Vertical, «Restablecer bloque» y dnd-kit del editor de formatos fijos; las zonas solo dicen «Editar»;
    - el controlador rechaza `diseno` (`prohibited`) y lo borra al guardar; las plantillas Blade ya no aplican desplazamientos;
    - la vista previa de formatos fijos muestra ASNEEX en las firmas y en el diploma.
- **Dashboard del gerente:** ya no recibe `iaRecompra`; el dato sigue en el reporte PDF comercial. Las pruebas que lo leían ahora leen `datosMensuales()`.
- **Certificado de producto (mangueras):** ya funciona con lo que hay. Se arma la plantilla por bloques (datos, texto de especificaciones, tabla, texto de garantía, responsabilidad), se liga el producto y el vendedor lo emite desde la venta. La emisión automática al confirmar la venta y la ficha técnica en el producto quedan como propuesta: tocan la venta confirmada y necesitan migración.
- **Verificación:**
    - GestionDeCertificados 30/30, GerenteDashboard + MlRetentionModel 21/21;
    - PHPStan 0, Pint, tsc y `vp check` en verde;
    - editor nuevo (OG, LM y diploma) y editor fijo revisados en el navegador;
    - suite completa: ver el resultado al final de la sesión.

## 2026-10-19 — Retomado tras perder el historial del chat (Claude)

- El trabajo de ayer seguía intacto sin commit en `codex/certificados-dinamicos` (43 modificados, 8 nuevos). La migración de composición dinámica ya estaba aplicada.
- **Verificación:** 128/128 pruebas (1045 aserciones) en los 10 archivos tocados: certificados, dinámico, mediciones de campo, dashboards e IA. tsc, `vp check`, Pint y PHPStan 0 en verde. La suite completa queda para el CI.
- Borrado `debug.log` (log de Chromium, no del proyecto).
- **Revisión visual de dashboards bloqueada:** el navegador integrado rechaza localhost. Con Playwright el login falla porque la base local tiene 3 usuarios con contraseñas propias y 0 ventas, así que tampoco hay datos reales que mostrar.
- **Siguiente paso:** dar la contraseña local o sembrar una base de demostración y revisar los dashboards. Luego, commit y PR cuando el usuario lo pida.

## 2026-10-19 — Revisión visual de los dashboards (Claude)

- A pedido del usuario se crearon en la base local cuentas de prueba `claude.<rol>@brucefire.pe` (gerente, vendedor, almacén, planta y campo, con la contraseña del seeder) y la sede «Taller». No se tocaron las cuentas existentes.
- Playwright con axe-core: los cinco dashboards y «Recompra e IA», a 1366 px en tema claro y a 390 px en tema oscuro. Sin errores de JS, sin desbordes horizontales y sin violaciones de axe.
- **Corregido:**
    - los gráficos de Recharts (`bi.tsx`) estaban ocultos para lectores de pantalla pero recibían foco con el teclado (`aria-hidden-focus` en Vendedor, Planta y Campo); ahora llevan `accessibilityLayer={false}`;
    - el texto de comparación decía «Igual que el semana pasada»; ahora el sufijo trae su artículo («el mes anterior», «la semana pasada»).
- tsc y `vp check` en verde. La base no tiene ventas, así que solo se vieron los estados vacíos. Sin commit.

## 2026-10-19 — Certificado de producto que sale solo (Claude)

- **Diseño acordado con el usuario:** nadie llena certificados. Gerente liga producto ↔ plantilla; la plantilla define los campos (columnas de su tabla); Almacén llena la ficha técnica una vez por producto (no por unidad); la venta pone cliente, referencia, producto y cantidad.
- **Datos:** `products.ficha_tecnica` (json) y `sales.certificados_en_espera` (json). Migración `2026_10_19_120000`.
- **Emisión:** `EmitirCertificadosDeProducto` se llama desde `EmitirCertificadosDeVenta::automaticos()` (confirmar venta, nota de venta y edición de venta emitida). Un certificado por plantilla y venta, una fila por producto; idempotente (no emite si hay uno vigente). Las columnas `producto` y `cantidad` las llena la venta, `item` se numera solo. Sin «Tipo de atención».
- **Ficha incompleta o dato inválido:** la venta se confirma; el tipo queda en `certificados_en_espera` y se emite al guardar la ficha en Almacén (`almacen.fichas.update`).
- **Reglas:** un producto solo se liga a una plantilla por bloques sin pruebas, resultado ni diploma (formulario del producto y editor de certificados).
- **Pantallas:** Gerente → Productos («Certificado que lleva», «Crear certificado nuevo» abre el editor con el producto elegido, aviso «Falta la ficha técnica»); Almacén → «Fichas técnicas» (nueva).
- **Corrección a lo dicho en la conversación:** ligar el certificado al tipo de servicio sí pasa a sus servicios; no es una incoherencia.
- **Verificación:**
    - suite completa 1394/1394 en verde;
    - PHPStan 0, Pint, `vp check` y tsc en verde;
    - en el navegador: plantilla creada desde el producto (ver E-007 en ERRORES.md).

## 2026-10-19 — Auditoría completa en el navegador: arranque (Claude)

- **Registro compartido nuevo:** `docs/ai/ERRORES.md` (nunca se borra; estados Abierto → En curso → Solucionado). CLAUDE.md y AGENTS.md lo piden leer al empezar. `PROMPT_AUDITORIA.md` ahora apunta a las cuentas `claude.<rol>@brucefire.pe`.
- **Cómo entrar:** cuentas `claude.<rol>@brucefire.pe` con la contraseña del seeder; servidor `bruce-fire-claude` (8010) usando el Vite del usuario (5173). No levantar otro Vite: pisa `public/hot`.
- **Orden de la auditoría (sigue el flujo del negocio):** Gerente (catálogo: productos, servicios, certificados) → Almacén (recepción y stock) → Vendedor (cotización, venta, caja, SUNAT, certificados) → Técnico de Planta → Técnico de Campo → Gerente (reportes y control). La base local estaba casi vacía (1 producto, 0 servicios, 0 stock): los datos se crean por la interfaz, como lo haría la empresa.
- **Avance:** Vendedor → Clientes revisado (E-002 a E-005). Nueva venta: el aviso de caja cerrada sale antes que «elige un cliente» y se repite 3 veces (por registrar como error al cerrar esa pantalla).
- **Avance (actualizado):**
    - errores E-001 a E-021 en ERRORES.md; los críticos y altos son E-011 (arqueo ciego) y E-021 (margen con IGV);
    - flujo completo probado: plantilla «Garantía de mangueras» → ficha en Almacén → recepción de 20 → venta NV-0001 → certificado GM-2026-000001 automático;
    - suite completa 1394/1394 y certificados 106/106 tras los arreglos E-007 y E-008;
    - **regla del usuario:** auditar todo primero, registrar sin arreglar y analizar al final.
- **Datos creados en local para la auditoría:** cliente CLI-0001 (RUC ficticio 20600000013), PRD-0002 manguera, SRV-0001 a 0003, plantilla GM, recepción #1, venta VTA-0001/NV-0001, COT-0001, caja #1 con faltante de S/ 20.
- **Avance 2 (misma fecha):**
    - E-022 a E-036 registrados;
    - flujo de taller completo (OT-2026-0001: recibir → falla con foto → autorización del vendedor → reparar → OG-2026-0001 → cobro VTA-0002 → entrega por Campo con firma y acta);
    - **boleta B001-1 aceptada por SUNAT beta**;
    - permisos: Campo contra 11 pantallas de otros roles → 403;
    - axe-core WCAG 2.2 AA en 14 pantallas en celular y oscuro: limpio salvo E-035;
    - E-004 corregido de alcance (las pestañas sí tenían nombre).
- **Altos abiertos:** E-011 (arqueo ciego), E-021 (margen con IGV), E-023 (orden sin cantidad), E-024 (año futuro en taller y certificado), E-030 (acta con la dirección de la sede).
- **No se usaron datos de personas reales:** dos DNI de prueba resultaron reales en RENIEC y no se guardaron.
- **Cierre de la jornada:** 38 errores (5 altos, 18 medios, 15 bajos), 41 puntos revisados y 27 pendientes en la checklist. Servidor de pruebas apagado; `public/hot` apunta al Vite del usuario (5174).
- **Siguiente paso:** lo que falta de la checklist de ERRORES.md (2FA y passkey, guía desde boleta con producto, factura con RUC real habido, cotizar o rechazar adicional, extintor no apto, recojo completo, inspecciones e instalaciones, firmas, inteligencia, cobranzas, códigos SUNAT, Chispa, celular en el resto de pantallas). Después, analizar todos los errores con el usuario y priorizar.

## 2026-10-20 — Auditoría completa: cierre (Claude)

- **Terminada la checklist de ERRORES.md.** Solo quedan 3 puntos que no se pueden probar en local: llave de acceso (necesita un dispositivo real), GRE real a SUNAT (credenciales de producción) y traslados (hay un solo almacén).
- **Probado hoy:**
    - caja con cobro en efectivo y sobrante;
    - Inicio del vendedor (las ventas del mes cuadran con NC y ND);
    - apariencia y dos pasos;
    - axe-core en todas las pantallas y diálogos;
    - celular claro y oscuro en las 50 pantallas;
    - extintores con serie (recepción → venta escaneando BF-EQ → armar certificados);
    - instalación en Campo (servicio nuevo SRV-0005 → OT-2026-0006 → LS-2026-0001);
    - faltantes en el taller (OT-2026-0007).
- **Errores nuevos E-061 a E-072:**
    - E-062: un servicio en sitio queda «Listo para entregar»;
    - E-063: botones verdes sin contraste;
    - E-066: la orden se desborda en celular;
    - E-067: el lector de barras usa la búsqueda anterior;
    - E-068: número de cliente repetido;
    - E-069: certificado de extintor nuevo dice «mantenimiento y recarga»;
    - E-070: no valida el mínimo de las columnas;
    - E-071: la pantalla de instalación habla de extintores;
    - E-072: se recibe con 0 extintores y no se puede recibir después.
- **Total:** 72 errores (11 altos, 38 medios, 23 bajos); 3 solucionados (E-001, E-007, E-008), el resto abierto.
- **Altos:** E-011, E-021, E-023, E-024, E-030, E-039, E-044, E-049, E-051, E-055, E-057.
- **Datos de prueba creados hoy en local:**
    - marca «badger»;
    - recepción #2 (BF-EQ-000006 y 000007);
    - VTA-0007/NV-0003 con OG-2026-0003 y BF-UME-2026-0001;
    - SRV-0005, OT-2026-0006 (LS-2026-0001) y OT-2026-0007;
    - caja #2 con sobrante de S/ 5.
- **Ojo:** Vite recarga la página cada vez que cambia un archivo de `docs/` y se pierde lo que estaba a medio llenar en el navegador.
- **Siguiente paso:** analizar los 72 errores con el usuario, agruparlos por tema y priorizar (primero los altos y los que tocan dinero o documentos legales). No arreglar nada sin su aprobación.

## 2026-10-23 — Decisiones de la auditoría y plan de corrección (Claude)

- **Decidido con el usuario** cada error que necesitaba una regla del negocio; registrado en ERRORES.md → «Decisiones aprobadas (2026-10-23)», con fuentes (Odoo, OCA, SUNAT, software de protección contra incendios).
- **Corregido por la investigación:** la recarga de extintores va en detracción como «Otros servicios empresariales» (Informe SUNAT 017-2006), no como mantenimiento; los intereses moratorios de la ND van inafectos (RTF 00214-5-2000, confirma la contadora). PH cada 5 años para todos por política de la empresa.
- **Regla nueva de trabajo:** cada fase investiga primero cómo lo hacen los sistemas grandes y anota la fuente.
- **Plan:** cinco fases en PLAN.md (dinero y SUNAT → flujos que se traban → certificados → clientes → pantallas) y una final con la ficha RUC y la contadora.
- **No se tocó código.** Siguiente paso: fase 1, empezando por investigar y leer el código de caja (E-011).

## 2026-10-23 — Fase 1: E-011 arqueo ciego (Claude)

- **Investigado:** cierre ciego en Odoo (módulo OCA _POS Blind Session Closing_: solo un grupo autorizado ve el esperado), conteo por denominación de Odoo 17 y guías de Shopify y Microsoft Dynamics (el supervisor revisa la diferencia).
- **Hecho:** conteo por billetes y monedas guardado en `cash_register_counts` y `cash_register_count_lines`; el primer conteo no se cambia; el aviso no dice cuánto falta; sin cobros en efectivo ni salidas mientras espera la explicación; reconteo autorizado por el Gerente con los dos conteos a la vista. Detalle en ERRORES.md → E-011.
- **Verificado:** `CierreCiegoDeCajaTest` 9/9 (74 aserciones), regresión de caja 82/82, PHPStan y Pint en los archivos tocados, `types:check` y `vp check` en las dos pantallas, navegador (escritorio, celular oscuro, axe sin violaciones). No se corrió `composer ci:check` completo: queda para el cierre de la fase 1.
- **Incidente:** un `migrate:fresh --env=testing` borró la base de desarrollo `bruce_fire` (no existe `.env.testing`, así que usa `.env`). Eran datos de prueba; se resembró con `db:seed` y `DemoAuditoriaSeeder`. Usuarios de prueba: los del seeder (contraseña `password`). Regla: nunca `migrate:fresh` fuera de Pest.
- **Siguiente:** E-021 (Dashboard sin IGV, Productos y Servicios).
- **Corrección posterior (E-011):** el cierre con monedas fallaba (punto en la clave «0.10»); las denominaciones van en céntimos. «1 cierres» ya dice «1 cierre» en Caja (el resto de E-012 sigue abierto). Fila «Otros medios» en el arqueo cuando hay cobros con forma «otro».
- **Base local para la exposición:** `migrate:fresh --seed` + `CatalogoDemoSeeder` (nuevo): 19 productos y 12 servicios reales con precio promedio del histórico de CODEPLEX, 5 usuarios de prueba, sin clientes, ventas ni stock.
- **Regla nueva (decidida por el usuario):** toda venta exige turno de caja abierto, no solo el efectivo. Se reutilizó la validación existente (`exigirAbiertaParaEfectivo` → `exigirAbierta` común) y `CashRegister::abiertaDe` para el aviso de la pantalla. Pruebas: `VentaExigeCajaAbiertaTest` 3/3, `SaleConfirmationTest` ajustada (abre turno), 15 archivos de ventas en verde.

## 2026-10-23 — Análisis del relevo de Claude antes de activar Astra (Codex)

- **Pedido actual:** entender primero lo solicitado a Claude y cómo terminarlo; avisar al usuario para que active GPT Astra antes de implementar.
- **Fuente:** conversación abierta «Informe Final BRUCE FIRE: revisión y humanización», últimos mensajes leídos en Claude; contrastados con `git status`, memoria y código. No se enviaron mensajes a Claude.
- **Pedido original:** una recarga produce dos avisos; local debe llevar operatividad y capacitación, vehículo operatividad y PH.
- **Ya escrito por Claude:** exclusión del estimado en `ExtintoresPorVencer.php` cuando hay equipos en una orden de la venta. Falta regresión; revisar recepción parcial y ventas mixtas porque el filtro excluye toda la venta.
- **Pendiente confirmado:** el taller emite según el tipo del servicio; no usa `tiposPorDestino`. «Armar certificados» y `EmitirCertificadosDeVenta::handle` solo admiten los equipos de las líneas de venta, no los de las órdenes asociadas.
- **E-024 confirmado en código:** alta rápida con venta = hoy y PH = hoy + 5 años; recepción valida el año como texto. Aplicar la decisión ya registrada sobre última PH real, sin inventar fechas para registros existentes.
- **Plan:** pruebas primero; integrar regla y equipos del taller; conservar PH reutilizable, aptitud, historia y acceso por cliente/sede; corregir fechas; regresiones y `composer ci:check`. Detalle en PLAN.md.
- **Alcance de esta sesión:** solo análisis y actualización de PLAN/BITACORA. Sin pruebas ejecutadas, cambios de código, migraciones, modificaciones de datos, commit ni push. Se preserva el trabajo acumulado de Claude.
- **Siguiente:** continuar con Astra cuando el usuario lo active. No reconstruir el grafo en esta fase de análisis: no existe `graphify-out/graph.json`.

## 2026-10-23 — Implementación del relevo: recargas, certificados y fechas (Codex/Astra)

- Usuario autorizó continuar después del análisis en Claude y pidió código cuidado.
- Avisos: ambas consultas descuentan por venta y servicio los equipos ya identificados; conservan recepciones parciales, líneas repetidas y ventas mixtas. Regresión roja inicial (4 casos), luego 16/16 verdes.
- Certificados: equipos aptos de órdenes terminadas disponibles en Armar certificados; regla local/vehículo compartida, incluido destino de cotización con placa manual. Capacitación y PH siguen pendientes de datos reales. Se conservan número, QR, vínculo a la orden y fecha de recarga; PH vigente se reutiliza. Equipos ajenos, pendientes o no recibidos no se admiten.
- Seguridad: completar series y emitir se ejecutan en transacción; una petición rechazada no modifica equipos. Revisión independiente de los cambios frente al respaldo previo, con ajustes aplicados de destino, aptitud y snapshots desconocidos.
- E-024: fecha real de última PH, intervalo configurable, fechas próximas editables, año válido y sin venta ficticia en el alta de taller. Migración aditiva sin corregir fechas históricas por suposición. Datos del cilindro y documentos emitidos conservan su historial.
- Verificación final (2026-10-23): `composer ci:check` pasó con 1,433 pruebas y 8,151 aserciones. Después, los 11 archivos de regresión afectados pasaron con 122 pruebas y 642 aserciones. También pasan PHPStan (0 errores), TypeScript, formato/lint, Pint, build y `git diff --check`. Se aplicó revisión independiente y se corrigieron hallazgos de destino vehicular, aptitud y snapshots de fechas desconocidas.
- Pendiente para E-024: revisión visual y cotejo manual de registros históricos que no tienen fecha real documentada; no se alteraron esos datos. Work todavía informa que el navegador local está deshabilitado pese a que el usuario indicó que lo habilitaría. Sin commit ni push.
- Sin commit ni push. Los cambios preexistentes de Claude se conservaron; respaldo previo en C:/Users/migue/AppData/Local/Temp/brucefire-relevo-20261023-astra.

## 2026-10-23 (noche) — Flujo de recarga probado por ngrok (Claude)

- **Probado de punta a punta por el enlace público:** cliente por RUC → caja → orden de servicio → Planta (tomar, registrar a mano con última PH, checklist, cierre, certificado OG) → cobrar desde la orden (factura) → entrega en mostrador con acta PDF. PDF del certificado, Word y factura descargan.
- **Corregido en el camino (con prueba que fallaba):**
    - E-039: «Nueva venta» desde la ficha del cliente daba 500 (filtraba clientes por sede) → `NuevaVentaDesdeClienteTest`.
    - E-030 (parte): al entregar (mostrador o Campo) el extintor seguía en «Planta - Taller» → `ServiceOrder::marcarEquiposEntregados`, prueba en `EntregaCampoMostradorTest`.
    - E-069: el certificado OG decía «mantenimiento» en una recarga y mostraba el párrafo de CO2 sin CO2 → `CertificateDocumentData::segunLaAtencion` (recarga, nuevo, CO2). 174 pruebas de certificados en verde.
- **Pendiente visto:** vender una recarga directo (sin orden) no crea orden de taller; capacitación para destino local en órdenes de servicio; marca y año «No legible» si el técnico no los llena; E-025 (mayúsculas en la cola del taller); fecha técnica «Consultado el …T…» en la ficha del cliente; desborde horizontal de Nueva venta en pantallas angostas.
- **Base:** reiniciada con `migrate:fresh --seed` + `CatalogoDemoSeeder` para la exposición.

## 2026-10-28 — Prompt maestro V3: auditoría y planificación (Claude)

- **Pedido:** primera tarea del prompt V3, solo auditoría y planificación.
- **Hecho (solo documentación):**
    - auditoría con inventario funcional, clasificación, evidencia y riesgos;
    - inventario de vistas generado desde el código (76 rutas de pantalla, 85 páginas), con su script;
    - arquitectura objetivo, familia documental y registro de librerías, métricas financieras;
    - ADR-0001 (evolucionar el beta; propuesta);
    - gobernanza multi-IA: START_HERE, PROJECT_STATE, AI_RULES, OPEN_QUESTIONS (P-01 a P-12), DECISIONS_INDEX, HERRAMIENTAS_Y_SKILLS, SESSION_HANDOFF y GEMINI.md.
- **Hallazgos clave:**
    - el stack es React + shadcn/ui, no Vue;
    - IGV `1.18` repetido en 4 páginas y en el PDF;
    - totales duplicados entre Nueva venta y Cotización;
    - KPI calculados en 5 lugares;
    - páginas de hasta 2.890 líneas;
    - 50+ Skills ajenas al proyecto;
    - 116 archivos sin commit.
- **Sin código ni pruebas.** Siguiente: respuesta del usuario a P-01 (evolucionar o reescribir) y P-02 (commit).

## 2026-10-28 — Decisión: versión nueva aparte (Claude)

- El usuario decidió construir la versión nueva en `D:\TiomiguelonGgs\Desktop\BruceFireOriginal`. ADR-0001 rechazada; P-01 resuelta.
- La planificación completa (vistas, arquitectura, seguridad, diseño, SUNAT, BI e IA) vive en esa carpeta (`docs/00_START_HERE.md`). Este repositorio queda como referencia.

## 2026-10-28 — Recorrido funcional beta (Codex)

- Ejecutado recorrido UI de la beta local para 13 flujos y dashboards de los cinco roles; informe y capturas PNG: D:\TiomiguelonGgs\Desktop\BruceFireOriginal\docs\recorrido-beta\.
- Completados F-02, F-05–F-09 y dashboards; F-01, F-03, F-11 y F-13 parciales; F-04 y F-10 pendientes.
- Hallazgos observados: 1 bloqueo, 4 confusos y 2 datos inconsistentes; detalles en problemas.csv.
- QA: se registró la recepción, ventas, órdenes y certificados sintéticos; el cierre de caja con faltante de S/5 se identificó expresamente como simulación.
- B001-1 y B001-2 permanecen en autoenvío programado a SUNAT beta; no se adelantó su envío.
- Sin cambios de código/configuración, sin contraseñas existentes modificadas y sin pruebas ejecutadas.
- Siguiente: revisar respuesta automática de SUNAT, completar F-04 y la cobertura pendiente de controles.

## 2026-10-28 — Ampliación de auditoría funcional beta (Codex)

- Se recorrieron pantallas adicionales y filtros de los cinco roles; se revisaron formularios QA vacíos e inválidos sin guardar, y accesos cruzados ajenos al rol respondieron 403.
- Nuevo informe F-14, matrices de botones ampliadas, PNG de evidencia; revisión de referencias sin archivos faltantes.
- P-008–P-013 agregados: filtro de fechas invertido, vistas previas de documentos, stickers inexistentes, ubicación de equipo vendido, contador/lista de Planta y 500 en Seguimiento de taller.
- `problemas.csv`: 13 hallazgos (4 Bloqueo, 5 Confuso, 4 Dato mal). La cobertura no es exhaustiva; F-04 y F-10 quedan pendientes.
- Excel quedó no probado porque el navegador bloqueó la navegación. No se intentó por otra vía.
- No se cambió código, configuración ni roles; no hubo SQL de escritura ni pruebas automatizadas.
- B001-1/B001-2 siguen «Por enviar» en SUNAT beta; no se usó «Enviar ya».
- La interfaz del navegador dejó de estar disponible durante esta continuación; el informe marca las pruebas restantes como no realizadas.
- Siguiente: reanudar pruebas cuando vuelva la interfaz, terminar F-04 y observar la respuesta de SUNAT para F-10.

## 2026-10-29 — Reanudación del recorrido beta (Codex)

- El usuario pidió terminar toda la auditoría. Se intentó retomar el recorrido en el beta local.
- Estado de Work: navegador local deshabilitado; no hay superficie browser disponible. No se intentó eludir la restricción ni abrir el sitio por otra herramienta.
- Se actualizó `00_RESUMEN.md` para que el estado SUNAT sea histórico: última observación del 28/10, B001-1/B001-2 «Por enviar»; respuesta actual no confirmada.
- No se alteró ninguna boleta ni se cambió código, configuración, contraseñas o roles; no se ejecutaron pruebas.
- Estado global: informe ampliado, auditoría no exhaustiva. F-04, F-10 y controles restantes pendientes.
- Siguiente: el usuario debe habilitar el navegador local en Work; entonces seguir con verificación UI de SUNAT y flujos pendientes.

## 2026-10-29 — Auditoría completa y correcciones P-001 a P-030 (Claude)

- **Auditoría terminada:**
    - F-10: B001-3 aceptada → NC BC01-1 aceptada en SUNAT beta.
    - F-04: falla con foto → COT-0004 → autorización → reanuda.
    - 5 roles revisados en celular y tema oscuro con axe.
    - Casos vacíos e inválidos; Exportar Excel.
- **Correcciones** (detalle en `BruceFireOriginal/docs/recorrido-beta/CORRECCIONES.md`; pruebas en `tests/Feature/Auditoria/`):
    - D-10: la cotización se acepta con prueba (quién, canal, orden de compra opcional con PDF). Solo aceptada pasa a venta u orden.
    - D-10: «Nueva orden» nace de una cotización aceptada (acción `IniciarOrdenDesdeCotizacion`).
    - D-10: servicios y empresas con RUC se venden desde cotización (`billing.exigir_cotizacion`; las pruebas antiguas lo apagan en `tests/Pest.php`).
    - D-11: la orden de compra sale en el XML (`setCompra`) y en el PDF.
    - Checklist con pasador/precinto y etiqueta; revisión de caja del gerente; traducción de nombres de campo; error 500 de seguimiento; móvil del detalle de orden; textos y contraste.
- **Errores encontrados al probar:**
    - `ServiceOrderEvent` tomaba la hora de MySQL (no la de Lima): corría la fecha de recarga del certificado.
    - Dos docblocks fuera de lugar en `ServiceOrder` y `CreateSale` provocaban 33 errores de PHPStan.
- **Código muerto borrado:** `kpi-card.tsx`, `mobile-nav-dock.tsx`, `pages/gerente/reportes/index.tsx` e imports de React sin uso.
- **Migraciones nuevas** (aplicadas en `bruce_fire`):
    - `2026_10_29_120000_add_aceptacion_to_quotes_table`
    - `2026_10_29_130000_add_revision_to_cash_registers_table`
- **Pendiente:**
    - P-014: el servidor debe correr `php artisan schedule:work`.
    - Aprobar en Claude Design la familia documental (factura → NC/ND/guía en la misma hoja) antes de programarla.
    - Nada commiteado.

## 2026-10-29 — Hoja única de documentos y NC que copia los ítems (Claude)

- **Hoja única** (`resources/views/pdf/hoja/`: estilos, cabecera, pie, firmas; datos en `CompanySetting::identidadPdf()`, como el «Document Layout» de Odoo). Usan la misma hoja la factura, la boleta, las notas, la nota de venta, la cotización, la constancia de recepción, el acta y la guía. Los certificados siguen en su propia familia, con su arte y su rojo fijo.
- **Guía de remisión en PDF:** antes no existía. Ahora tiene `GuiaRemisionPdfService`, el botón «Ver PDF» y la ruta `guias.documento` con `tipo=pdf`. El QR lleva la URL de consulta del CDR.
- **NC 01/02/06:** antes salía una sola línea «NC-01 / Anulación». Ahora copia todas las líneas del comprobante (SUNAT, oficio 033-2002; Odoo revierte todas las líneas). La única excepción es anular el saldo que queda tras otra nota: va en una sola línea y no se reparte entre ítems (`DesgloseNota`).
- **Acta:** se quitaron los valores inventados («6 kg», «PQS»); ahora muestra «—».
- **Pantallas de guías:** usan `PageHeader` y el mismo contenedor que Envío SUNAT (se quitó `mx-auto max-w-5xl p-4`).
- **Gerente:** «Diseño de los documentos» indica qué documentos cubre.
- **Código muerto borrado:** la ruta `vendedor.ventas.escanear-serie` y `SaleItemScanController`.
- **Pruebas nuevas:** `Auditoria/NotaCreditoCopiaLineasTest`, `Auditoria/DocumentosHojaUnicaTest` y el PDF de la guía en `GuiaRemisionTest`.
- **Comprobante a media hoja:**
    - Factura, boleta, notas y nota de venta van completas en la mitad de arriba del A4, con el QR junto a los totales y una línea de corte para reutilizar la otra mitad.
    - Si no entra (muchos ítems o cuotas), `ComprobantePdfService::dibujar` lo imprime en la hoja completa y sin línea: primero lo prueba en papel de 148,5 mm.
    - La cabecera nueva (recuadro con banda del color del Gerente) está en `hoja/_cabecera` y la usan todos los documentos.
- **Media hoja también en la cotización y la guía:** la decisión de media hoja vive en `App\Support\HojaPdf::cargar` y la línea de corte en `hoja/_corte`. Los espaciados de `hoja/_estilos` son más compactos y el total va en la banda de color en todos los documentos. Constancia y acta quedan en hoja completa (llevan firmas y fotos).
- **Pantallas:** `pageTitleClass` (`page-header.tsx`) es el único estilo de título; lo usan 47 pantallas. No se combina con `cn()` porque tailwind-merge confunde la fuente Oswald con el peso y la borra.
- **Contenedores:** se quitó el relleno doble y el ancho angosto en las fichas y listas. Los formularios conservan su ancho de lectura.
- **Código muerto borrado:** `gerente/transporte` (pantalla, controlador y rutas a las que nadie podía entrar; el transporte se registra en la guía).
- **Pruebas nuevas:** `Auditoria/ComprobanteMediaHojaTest`.
- **Pendiente:**
    - La NC BC01-1 ya emitida conserva la línea antigua: SUNAT ya la aceptó y no se toca.
    - Nada commiteado.

## 2026-10-31 — Claude: relaciones entre documentos

- Se usa el beta como base; respaldo del trabajo sin commit en `Respaldos/beta-2026-10-31` (fuera del repositorio).
- PDF de notas: "Modifica a" muestra tipo y número completos.
- Venta: bloque de documentos relacionados. Envío SUNAT: cada nota indica qué comprobante modifica.
- Pruebas: 1.466 pasan (8.267 aserciones); PHPStan, Pint de archivos tocados y TypeScript en verde.
- Sin revisar en navegador. Falta unificar detalle de comprobante, ventana de notas y crear la web pública.

## 2026-10-31 — Claude: dashboard de vendedor, defectos y funciones repetidas

- Inicio del vendedor con cuatro indicadores enlazados y bloques ordenados: qué atender, a quién llamar, cómo voy, caja y agenda.
- Ventas y Envío SUNAT abren en "Este mes"; un filtro solo con fecha inicial llega hasta hoy (antes el aviso de borradores no mostraba nada).
- Cotización sin enlace a ventas de otro vendedor; `Nota de crédito` legible; fecha `dd/mm/aaaa`; notas sin repetirse en documentos relacionados.
- Unificado en `lib/utils.ts`: `soles`, `fechaHora`, `sumarDias`, `iniciales`, `etiquetaPaginacion`, `normalizePath`, `hace`, `nombreDocumento`. Además `error-de-campo.tsx` y trait `ValidaVentaVisible`.
- No unificado por no ser igual: `ServiceCertificateController::assertSedeAccess` valida solo sede; los `isActivePath`, `buildNavGroups`, `ESTADOS` y el `soles` sin decimales de `clientes-por-registrar`.
- Pruebas: suite completa 1.468 de 1.471; las 3 restantes esperaban "Hoy" o una venta sin dueño, se actualizaron y pasan por archivo. PHPStan y TypeScript en verde.
- Sin abrir en navegador una venta activa ni la pantalla de nota: gerente no entra a rutas de vendedor y la clave del otro vendedor demo no se conoce. Nada commiteado.

## 2026-11-01 — Claude: detalle de venta con saldo e historial

- Detalle de venta: saldo pendiente, registrar pago, accesos con contador, pasos de SUNAT e historial. Agregados `CarteraDeCobranzas::resumenDeVenta` y `AuditLog::texto()`; `HistorialActividad` compartido con la ficha del cliente, que mostraba la fecha cruda.
- Probado en navegador con una venta temporal F999 en la base demo, sin SUNAT, y borrada al terminar. Nota de crédito vista sin emitir.
- Suite completa: 1.471 de 1.473; las 2 fallas dependían del día 1 del mes (`AlertModuleTest`, `VendedorDashboardTest`). Se fijó la fecha y esos dos archivos pasan. PHPStan, TypeScript y lint en verde.
- Pendiente: efecto de la nota antes de emitir, orden por urgencia en Envío SUNAT, regla de acceso de `ServiceCertificateController`. Nada commiteado.

## 2026-11-01 — Claude: efecto de la nota y orden de Envío SUNAT

- La pantalla de nota muestra qué pasará al aceptarla SUNAT: comprobante, saldo, caja y stock.
- Corregido: el excedente de una nota podía registrar como devuelta la detracción, que no entró a caja.
- Envío SUNAT ordena rechazados, por enviar, observados y aceptados.
- Hallazgo sin cambiar: la devolución parcial (07) no regresa productos al stock. Falta decidir.
- Pruebas: suite completa 1.476 de 1.476 (8.376 aserciones); PHPStan, TypeScript y lint en verde. Nada commiteado.

## 2026-11-01 — Claude: corregir comprobante y stock al anular

- Regla del negocio: al anular, los productos siempre regresan al stock; los servicios no. Se quitó `devolucion_fisica` y la casilla de la pantalla.
- La nota de crédito pregunta qué pasó: corregir un error, devuelve todo o devuelve una parte. Tras anular ofrece "Rehacer venta".
- Auditoría: certificados, orden y cotización se pierden al corregir; devolución parcial sin stock y repetible; depósito de detracción valida mal el saldo; IGV de pantalla asume gravado. Pendientes, a la espera de aprobar la recomendación.
- Sin commit.

## 2026-11-01 — Claude: corrección con enlace y devolución parcial

- Nuevo: `sales.corrige_sale_id` y `sales.correccion_desde`; `CorregirVenta`, `DevolucionParcial` y `ventas:cerrar-correcciones` (diario, 7 días).
- Corregir no anula certificados: al emitir la venta nueva se compara por serie, conserva fechas y pasa orden y cotización. Si cambia el cliente, se cierra lo anterior.
- Devolución parcial: regresa stock, elige series y no deja pasar lo vendido.
- Registrados E-073 a E-081. Abiertos: E-077 a E-081.
- Requiere correr la migración en cada entorno. Respaldo previo en `Respaldos/beta-2026-11-01`.
- Sin commit.

## 2026-11-01 — Claude: cierre de E-077 a E-081

- E-077: el depósito de detracción valida con `saldoDeVenta`. E-078: `noGravados` y `desglosarIgv` para venta y cotización.
- E-079 descartado: era falsa alarma; igual se unificó con `ValidaVentaVisible`. E-080: contador por periodo con excepciones; la excepción sigue descontando lo disponible porque se reintenta. E-081: `mesActual` del servidor.
- Prueba nueva: `CierreErroresNotasTest`. Sin commit.

- Datos de revisión en la base demo local: series BF-REV-0001 a 0003 y 20 unidades de "Señal de vinil" (movimientos con observación "Stock de revisión"). Se pueden borrar al terminar.

## 2026-11-02 — Claude: cobrar una orden con lo cotizado

- Error: al cobrar una orden nacida de una cotización solo llegaban los servicios; los productos cotizados se perdían (caso real: B001-00000004 sin el extintor).
- Arreglo: `ordenParaCobro` agrega los productos sin serie y `seriesCotizadasDeLaOrden` reutiliza el panel de la cotización para escanear los extintores; no se emite hasta tenerlos.
- Los adicionales siguen entrando solo si el cliente los autorizó.
- Prueba: `CotizacionDestinoTest`. Sin commit.

## 2026-11-03 — Claude: fase 1 del plan de orden y certificados

- Deficiencias del vendedor y de planta con el extintor dibujado por partes (`DeficienciaSuelta` + `ExtintorPorPartes`); sin extintores registrados, avisa.
- Próxima PH y próxima recarga ocultas en "Cambiar": se calculan solas.
- Año de fabricación hasta el actual también en campo.
- Stickers de la orden: fuera de planta; los imprime el vendedor (`vendedor.ordenes-servicio.stickers`).
- Certificado sin vigencia, orden de servicio ni código BF-EQ; columna "Atención" cuando hay nuevos y recargados.
- XML solo después de enviar a SUNAT; CDR solo si existe.
- Plan completo en `docs/ai/PLAN_ORDEN_Y_CERTIFICADOS.md`. Sin commit.

## 2026-11-03 — Claude: fase 2, certificados por destino

- Local: operatividad y capacitación salen solas al confirmar la venta y al cerrar la orden de taller, sin duplicar la capacitación (`EmitirCertificadosDeVenta::datosDeCapacitacion` y `tieneCapacitacion`).
- Vehículo: la prueba hidrostática sigue pidiendo fecha, presión, tiempo y resultado reales; el sistema solo conoce la presión de PQS y CO2, y no se imprimen valores técnicos sin el ingeniero.
- Participantes y fotos: se agregan en "Armar certificados" (modos normal, con fotos y por trabajador, ya existentes).
- Pruebas ajustadas a la regla nueva: `CertificadosDeVentaTest`, `CertificadosDeRecargaTest`, `CorregirComprobanteTest`. Sin commit.

## 2026-11-03 — Claude: fases 3 y 4, clientes e historial de compras

- Cliente: elige si sus certificados salen con razón social o nombre comercial (`certificado_con`); la factura siempre usa razón social.
- Alta del cliente con varios locales y su dirección; el certificado de un local usa esa dirección y no repite la referencia.
- Ficha del cliente: tarjeta "Qué compró" (ventas confirmadas, línea por línea) y descarga en Excel (`vendedor.clientes.compras`).
- Error 500 que ya existía: la ficha caía si una orden no tenía servicio principal; ahora usa `resumenServicios()`.
- Pruebas: suite completa 1,505 de 1,508 (1 falla de orden en la propia prueba, corregida; 2 omitidas). TypeScript, lint, Pint y PHPStan en verde.
- Pendiente: contador valida notas de crédito; ingeniero aprueba presiones para la PH automática; órdenes de campo sin cambios.

## 2026-11-03 — Claude: dirección del local, caja de otro día y ajustes

- Dirección del comprobante (como Odoo: dirección de factura y local atendido): la venta y la cotización marcan "sale con la dirección de este local" (`direccion_del_local`); el local puede quedar "para facturar" y la marca viene sola. PDF y XML usan `direccionComprobante()`; la guía llega al local de la referencia.
- Error previo: al editar o al venir de una cotización, el cliente llegaba sin locales ni placas; ahora se usa `Client::ficha()` en todos los formularios.
- Cotización: sin "Forma de pago" en el PDF (la condición propuesta sigue sirviendo para precargar la venta).
- Deficiencia: el formulario tomaba un extintor vacío si la orden no tenía extintores al abrir la pantalla; salía el dibujo genérico en vez del CO2. Dibujo más grande y centrado.
- Próxima PH visible ya calculada (intervalo de la empresa).
- Fecha y hora del servidor arriba (vendedor, gerente, almacén). Caja abierta de otro día: aviso y no recibe ventas hasta cerrarla con arqueo; no se cierra sola.
- Pruebas: `DireccionDelLocalYCajaTest`.

## 2026-11-03 — Claude: rediseño de Caja y cobranzas y de Envío SUNAT

- Diseños aprobados en Claude Design: Caja (https://claude.ai/artifact/ArSDi5kZtDx8ddHQky22kZ) y Envío SUNAT (https://claude.ai/artifact/2jSUAy6Yxzegyo1TJ37Uy5).
- Caja en dos vistas con pestañas: Mi caja (/caja: turno, movimientos de hoy, turnos anteriores) y Cobranzas (/cobranzas: vencido, 7 días, total, cobrado del mes; filtros por vencimiento).
- Arqueo ciego (E-011) respetado: el efectivo sale sin monto en los movimientos; los turnos anteriores no muestran esperado ni diferencia.
- Nuevo: `CarteraDeCobranzas::resumenDelVendedor`, filtro en `cuotasPendientes`, `cobrado(..., vendedorId)` y `DatosDeCaja::movimientos`.
- Envío SUNAT: pestañas subrayadas, filtros por estado con su cuenta (rechazados y por enviar agrupados), aviso de rechazados, motivo debajo del estado, barra de descarga siempre visible y sin botones apagados.
- Guías: botón "Emitir guía desde una venta", filtros por estado, avance en 3 pasos y el siguiente paso en una línea.
- Otros: botones con texto en Certificados, tema oscuro con fondos más separados, vencimiento en gris si falta más de 7 días.
- Pruebas: `CajaYCobranzasVistasTest`. Sin commit.

## 2026-11-03 — Claude: un certificado por local y datos obligatorios del extintor

- Orden con extintores nuevos por vender: la operatividad (y la capacitación del local) esperan al cobro y sale un solo certificado con la recarga y los nuevos (`ServiceOrder::extintoresNuevosPorVender`, `EmitirCertificadosDeVenta::automaticos`). Si la orden ya tenía su certificado, el nuevo se suma al mismo número (revisión).
- Arreglo en el camino: al juntar, la capacitación que dejó la orden ya no se anula.
- Ningún certificado sale con datos pendientes: `Equipment::datosQueFaltan` y `faltantesParaCertificar` (agente, capacidad, marca, serie y año) al cerrar planta, al cobrar y en "Armar certificados".
- Año de fabricación obligatorio al registrar o corregir un extintor (`FechasDelEquipo::anioObligatorio`).
- La orden del vendedor avisa "Certificado en espera" mientras falte vender el extintor nuevo.
- Factories de equipos y de unidades de stock con datos completos. Pruebas: `CertificadoEsperaLaVentaTest`; ajustadas las que fijaban la regla vieja.
- Suite: 1,512 de 1,517 y las 5 restantes corregidas (37 de 37). Sin commit.

## 2026-11-05 — Claude: se retira Recompra e IA

- Fuera: pantalla del gerente y su menú, comandos `ml:score-clients`, `ml:reentrenar` y la exportación de entrenamiento, sus tareas programadas, el modelo (`RetentionModel`), el script de Python de XGBoost y las tablas `client_retention_scores` y `ml_entrenamientos` (migración `drop_ia_de_recompra`).
- Fuera también la etiqueta de probabilidad en la ficha del cliente, en "Por vencer" y la sección de IA del reporte comercial en PDF.
- Se conserva el historial del sistema anterior (`ml_*_historicos`) y su importación: la lista "Clientes del sistema anterior por registrar" sale ahora directo de la base, por compra más reciente (sin IA ni caché).
- Se mantiene la "recompra" del panel del gerente: extintores que vencen en 30, 60 y 90 días (sale de los certificados, no es IA).
- Siguiente: Chispa con consultas y acciones con confirmación; definir al final qué puede hacer cada rol.
- Pruebas: `ClientesAntiguosPorRegistrarTest`. Sin commit, rama `feat/quitar-recompra`.

## 2026-11-05 — Claude: campo con tres trabajos (recojo, entrega, instalación) según Claude Design

- Diseño aprobado en Claude Design: https://claude.ai/artifact/W6UJERoLWBZUkMHJr9iYYZ. Pantallas con el estilo del sistema (`components/paso-campo.tsx`: cabecera, avance, botón fijo abajo, pantalla de "listo").
- Recojo: cliente (alta completa del vendedor) → lugar (locales, dirección fiscal o local nuevo) → conteo por tipo y capacidad con agregar y quitar y foto de entrada → precio → firma.
- Precio que deja campo: sale del catálogo de recargas (`Service::tarifasDeRecarga`) y se guarda por línea (`service_order_lines.precio_lista/precio_unitario/precio_estado`). Si baja más del 10 % (`config/campo.php`), queda "por aprobar"; no se cobra hasta que el gerente lo aprueba o manda a cobrar el de lista.
- Entrega: marcar cada extintor, foto de salida y firma; solo si la orden está cobrada o a crédito ("Falta cobrar" en la lista y en la pantalla).
- Instalación: cada extintor (dónde quedó, altura máx. 1.53 m, señalización, acceso libre y foto), revisión con foto antes, y firma.
- Planta ve la foto de entrada y "Campo trajo X de Y" por tipo. El vendedor ve "Entrada y salida" y "Precio que dejó campo" en la orden. El gerente tiene un módulo nuevo, Operación → Órdenes, con filtros, detalle y aprobación del precio.
- Arreglo: la ficha de cliente de campo ahora trae sus locales.
- Pruebas: `PrecioCampoYCobroTest` (nueva) y las de campo, entrega, planta y líneas: 87 de 87. TypeScript, lint y PHPStan limpios. Simulación en navegador (celular) de los tres flujos: OT-2026-0003 y OT-2026-0004.
- Datos de prueba locales: se cargó `CatalogoDemoSeeder` y se simularon planta y cobro por tinker.
- Pendiente: suite completa, cobro real desde Caja con precio aprobado, y decidir si el cliente ve las fotos en su portal. Sin commit.

## 2026-11-05 — Claude: agentes y capacidades administrables desde el recojo

- En "Agregar tipo", junto a Tipo y a Capacidad hay el mismo botón de administrar que en marcas y agentes (`lista-administrable`).
- Capacidades: tabla nueva `capacidades` (arranca con 1–12 kg, 5–20 lb, 6 y 9 L), `Capacidad::normalizar` ("30lb" → "30 lb", "50 KG" → "50 kg"), `CapacidadController` y prop compartida `capacidadesExtintor`.
- Campo ahora también puede agregar agentes (antes no). Se cambió la prueba que lo prohibía.
- Una capacidad sin precio de lista (30 lb, 50 kg) no frena el recojo: campo pone el precio y queda "por aprobar" para el gerente.
- Pruebas: 18 de 18 (agentes, marcas, precio de campo) y PHPStan limpio. Revisado en el navegador en modo oscuro: se agregaron 30 lb y 50 kg. Sin commit.

## 2026-11-05 — Claude: precio de campo sin aprobación del gerente

- Pedido del usuario: nada de aprobar precios (mucha interacción con el gerente). El precio que firma el cliente en el recojo es el que se cobra.
- La línea guarda cómo quedó: `lista`, `rebajado` o `sin_lista`. El gerente solo lo ve en Órdenes (filtro "Precio de campo distinto"); se quitaron la ruta y los botones de aprobar, el bloqueo en la venta y `config/campo.php`.
- Sin precio de lista (30 lb, 50 kg): se propone lo último que campo le dejó a ese cliente por el mismo tipo y capacidad (`CollectionController::ultimosPrecios`); si no hay, el técnico lo escribe.
- Constancia de recepción: la dirección es la del local elegido; antes salía "—" si el cliente no tenía dirección fiscal.
- Botón de foto: dice "Tomar foto" y abre la cámara trasera en el celular (`capture="environment"`).
- Pruebas: 64 de 64 (campo, entrega, instalación, agentes, marcas, líneas y recepción en planta). TypeScript, lint y PHPStan limpios. Sin commit.

## 2026-11-05 — Claude: el recojo llega al vendedor correcto y con la capacidad real

- Causa de "no aparece para cobrar": la OT-2026-0005 se creó en la sede Almacén Central y el vendedor de prueba es de Tienda Trujillo Centro (cada vendedor ve su sede). El recojo ahora propone la tienda donde el cliente compró o pidió su última orden (`sede_habitual` en la ficha de campo) y el campo dice "Tienda que cobra".
- Capacidad que no estaba en el catálogo (CO2 15 lb): antes la línea usaba la recarga de 10 lb y así habría salido en la factura. Ahora se crea la recarga con su capacidad en el nombre y el precio de campo como precio de lista (`Service::crearRecarga`).
- Datos locales corregidos: OT-2026-0005 pasó a Tienda Trujillo Centro y su línea de CO2 a "CO2 15 lb" (SRV-0014); los estados viejos de precio pasaron a `lista` / `sin_lista`.
- Visto en el navegador como vendedor: la orden aparece en Servicios y "Cobrar" arma la boleta con S/ 310.00. No se emitió (falta caja abierta).
- Pruebas: 32 de 32 de campo y precio. PHPStan, TypeScript y lint limpios. Sin commit.

## 2026-11-05 — Claude: planta no registra en silencio algo distinto a lo que contó campo

- Si planta registra un extintor que no coincide con el casillero que dejó campo (contó CO2 15 lb, planta pone 10 lb), sale el aviso "Campo contó CO2 15 lb y tú registras CO2 10 lb…" con el botón "Sí, corregir" (`QuickRegisterEquipment::corregirConteo`).
- Al confirmar: ese extintor sale de su casillero y pasa a la recarga que le toca, con su precio de lista (o se crea si no existe). Al vendedor le llega en el chat de la orden "Planta corrigió el conteo… Revisa el precio antes de cobrar".
- Órdenes sin conteo de campo siguen con el mensaje de siempre.
- Pruebas: `PlantaCorrigeConteoTest` (nueva) + recepción, ejecución, fechas y precio de campo: 54 de 54. PHPStan limpio. Sin commit.
- Pendiente de datos locales: en la OT-2026-0005 los 3 CO2 siguen registrados como 10 lb (de antes del arreglo).

## 2026-11-06 — Claude: cobrar el recojo y certificados después del comprobante

- La boleta de una orden de campo no salía: la regla "servicios solo desde cotización" ahora acepta cobrar desde la orden (`CreateSale::exigirCotizacion` con `service_order_id`).
- Certificados: si planta termina sin cobro, quedan pendientes (`certificados_pendientes` en el evento de cierre) y salen al emitir factura, boleta o nota de venta (`ExecuteAndCloseServiceOrder::emitirCertificadosPendientes`, llamado desde `ConfirmSale`). Si la orden ya estaba cobrada, salen al cerrar planta como antes. El vendedor ve "Certificado: sale al emitir la factura, boleta o nota de venta".
- Pruebas: `CertificadoAlCobrarTest` (nueva). Se ajustaron 7 pruebas que esperaban el certificado sin cobro o mandaban inspecciones a campo. Corrida de 22 archivos + reintento: 27 de 27 en los ajustados. PHPStan, TypeScript y lint limpios. Sin commit.
- Pendiente de datos locales: la OT-2026-0005 ya tenía certificados emitidos antes del cambio (OG-2026-0002 y BF-UME-2026-0001).

## 2026-11-06 — Claude: revisión de todos los módulos en el navegador

- Barrido: todas las pantallas GET de los 5 roles cargan sin error 500. Los 404 son a propósito (nueva guía sale desde una venta; traslados solo con varios almacenes).
- Recorrido completo: cotización → aceptación → venta (frena si falta stock) → recepción en almacén (2 unidades, stickers PDF) → venta con serie (NV-0002, certificado al cobrar) → caja (salida con motivo, cierre con faltante de S/ 4 que pide explicación y le llega al gerente) → venta a crédito (NV-0003) → cobranza parcial (el servidor frena pagos mayores al saldo) → Por vencer → QR público y PDF → panel y configuración del gerente.
- Arreglado: "Crédito" sin productos abría las cuotas con total S/ 0 y no dejaba guardar (`ventas/nueva.tsx`: solo abre si el total es mayor que 0; si no, se piden al emitir).
- Arreglado: una instalación vendida desde cotización iba a planta y nunca le aparecía a campo. Ahora `Service::loAtiendeCampo()` (misma regla que la lista de Instalaciones) decide el área en `SaleController` e `IniciarOrdenDesdeCotizacion`. Pruebas nuevas en `CampoTresTrabajosTest` y `CertificadoAlCobrarTest`.
- Falta en el beta (no se construyó): portal del cliente y confirmación de salida física por el almacenero (hoy el stock baja al vender).
- Datos locales: OT-2026-0009 pasada a campo a mano; recepción #1, NV-0002, NV-0003, COT-0001 y COT-0002 son de prueba.
- PHPStan, TypeScript y lint limpios. Sin commit.

## 2026-11-06 — Claude: Chispa por voz que consulta y prepara (vendedor, almacén y gerente)

- Decisión del usuario: el panel del gerente se queda como está; Chispa solo para Vendedor, Almacén y Gerente. Diseño aprobado en Claude Design (artifact PPXAoXkpiB2gKgnsYPxTN7).
- Proveedor DeepSeek (`services.chispa.deepseek`, `DEEPSEEK_API_KEY` en `.env`) con herramientas por rol (`App\Services\Chispa\Herramientas`): consultas con los mismos límites de cada pantalla y borradores en sesión que abren Nueva venta (`?chispa=1`), Nueva cotización y Nueva recepción llenadas. Cambio de precio: solo el gerente lo confirma (`asistente.precio`). Chispa nunca emite, cobra ni guarda sola.
- Widget: micrófono (reconocimiento del navegador, es-PE), tarjetas con "Abrir … llenada" / "Confirmar cambio" y botón "Escuchar".
- Rutas de Chispa cerradas a técnicos. Migración: `chispa_registros.herramientas`.
- Pruebas: `ChispaHerramientasTest` (nueva, DeepSeek simulado) 5/5 y `ChispaAssistantTest` 15/15. PHPStan, TypeScript y lint limpios. Sin commit.

## 2026-11-06 — Claude: commit y suite completa

- Commit `ca4b6ae` (campo, certificados al cobrar, Chispa) en `feat/campo-recojo-entrega-instalacion`, sin push.
- Suite completa: 1.526 pruebas, 1.471 en verde. Arreglado: la entrega revisa quién tiene la orden antes de validar datos; recojo, entrega e instalación muestran qué falta junto al botón (`role="alert"`); nombres de funciones repetidos en pruebas. Los 52 errores eran choques de migración en la base de pruebas por una corrida detenida: al repetir los 11 archivos afectados, 77 de 77 en verde.
- `vp check` (formato y lint), PHPStan y TypeScript en verde.
- Pendiente para producción: ruta de envío SUNAT (preguntar a Codeplex si usan OSE), certificado y usuario SOL, series y correlativos, hosting con cola y programador, respaldo, base limpia con datos reales, rotar la clave de DeepSeek.

## 2026-11-06 — Codex: relevo del despliegue AWS de Claude

- Pedido: revisar dónde quedó Claude antes de subir Bruce Fire a AWS. Usuario confirma que todavía no creó EC2.
- Último trabajo real: `d43c6dd` en `feat/despliegue-aws`, posterior al merge #42; incluye `despliegue/` y `sistema:capacitacion`, con SUNAT beta.
- Remoto consultado: `main=163d07d`; la rama de despliegue no está publicada. La descarga desde `main` de la guía no dispone aún del instalador.
- Hallazgos: falta preparar certificado de firma para comprobantes beta; un fallo después de crear `app.env` hace que el reintento omita seed y usuarios.
- Pruebas: `php vendor/bin/pest tests/Feature/PrepararCapacitacionTest.php --compact` pasó: 1 prueba, 9 aserciones (117,7 s). `bash -n` y `git diff --check` sin errores.
- Sin build Docker ni comprobaciones en AWS: Docker y AWS CLI no disponibles; GitHub CLI no autenticado. No se afirma que el despliegue funcione.
- Se actualizan PLAN y SESSION_HANDOFF; sin cambios de aplicación, commit, push ni recursos AWS. Se conserva `docs/ai/auditoria/` sin seguimiento.
- Siguiente: corregir/validar el instalador, publicarlo por el flujo autorizado y después crear EC2, dominio/IP, instalar y comprobar los flujos.

## 2026-11-06 — Codex: ejecución autorizada del despliegue AWS

- Usuario autorizó continuar («hazlo»). GitHub recuperado con la credencial existente de Git; AWS abierto en Chrome con sesión iniciada por el usuario.
- Instalador: certificado beta autofirmado/MODDATOS, montaje de solo lectura y marcador de inicialización pendiente; no repite capacitación tras una instalación completa ni sobre SUNAT producción.
- Cuatro escenarios de recuperación pasan en Git Bash y Ubuntu/WSL. YAML de CI válido; se añadió build y smoke nativos ARM con MySQL, web, cola, programador y respaldo.
- CI previo de `main` tenía 1 fallo entre 1.526 pruebas: fixture aleatorio de PlantaCorrigeConteoTest. Se fijó service_id explícito; 3/3 pruebas y 14 aserciones; PHPStan 0.
- Revisión independiente encontró riesgo de cuentas sin claves visibles si falla el catálogo; corregido con transacción común. RED comprobado; GREEN 2/2 pruebas, 19 aserciones; Pint/PHPStan correctos.
- AWS: imagen Ubuntu ARM y rol SSM verificados; dominio brucefire.duckdns.org ya creado. Base mensual estimada US$ 18,31 con 730 h; impuestos/consumo adicional aparte.
- Sin Docker local: build y comprobación de servicios pendientes de CI ARM. No se han creado recursos AWS; se solicitará confirmación concreta del gasto y acceso público antes de hacerlo.
