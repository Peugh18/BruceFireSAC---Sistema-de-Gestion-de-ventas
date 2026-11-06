# Plan — Bruce Fire SAC v2

> Última actualización: 2026-10-23. Corto a propósito: el detalle de cada cambio está en el código, en las pruebas y en git.
> Referencias: [SUNAT_FACTURACION.md](../SUNAT_FACTURACION.md) · [APIS.md](../APIS.md) · [PROMPT_AUDITORIA.md](PROMPT_AUDITORIA.md)

## Relevo AWS — revisión de Codex (2026-11-06)

Responsable: Codex. Revisión inicial completada; el usuario autorizó continuar con correcciones, publicación y despliegue («hazlo»). Confirma que todavía no creó la instancia EC2.

- [x] Localizado el commit `d43c6dd` en `feat/despliegue-aws`: Docker, HTTPS, MySQL, cola, programador y cuentas de capacitación; SUNAT en beta.
- [x] Consultado el remoto con `git ls-remote`: `main` sigue en `163d07d`; no existe la rama remota de despliegue. La guía descarga desde `main`, donde el instalador aún no está publicado.
- [x] Verificación focalizada: `PrepararCapacitacionTest` pasa (1 prueba, 9 aserciones); `bash -n despliegue/instalar.sh` y `git diff --check` sin errores.
- [x] Certificado beta autofirmado generado en el servidor, credenciales públicas MODDATOS y montaje de certificados de solo lectura; los secretos no se publican.
- [x] Recuperación de instalación mediante marcador pendiente, sin regenerar claves ni volver a sembrar tras instalación completa. Cuatro escenarios de Bash pasan en Windows y Ubuntu/WSL.
- [x] Corregido el fixture aleatorio de `PlantaCorrigeConteoTest`: 3 pruebas, 14 aserciones; PHPStan 0. Sin cambios de lógica del taller.
- [x] Fallo al sembrar catálogo revierte cuentas/sedes para que el reintento pueda entregar sus contraseñas. RED comprobado; GREEN: 2 pruebas, 19 aserciones; Pint y PHPStan correctos.
- [ ] Verificar build y arranque Docker, capacitación de punta a punta y respaldo/restauración; no hay Docker disponible en este entorno. El respaldo externo sigue pendiente.
- [ ] Publicar los cambios mediante el flujo autorizado de GitHub y CI; crear EC2, dominio/IP y acceso SSM; instalar y verificar HTTPS, cola y programador. No se hizo commit ni push en esta revisión.

**En ejecución AWS:** publicar correcciones por PR/CI y verificar build/arranque nativos ARM; después crear EC2 e instalar. GitHub accesible con la credencial existente de Git y AWS con la sesión iniciada por el usuario en Chrome. Conservar SUNAT beta y los datos locales. No incluir `docs/ai/auditoria/` ajeno en commits.

Infraestructura comprobada: Ohio, Ubuntu 24.04 ARM, t4g.small, 30 GB gp3, administración SSM mediante rol existente y sin SSH; dominio `brucefire.duckdns.org` creado. Costo base consultado: US$ 18,31/mes a 730 horas, antes de impuestos y uso adicional. Usuario inició sesión en AWS; pendiente de creación tras verificar CI y confirmar el gasto y el acceso público.

## Dónde estamos

### Relevo de Claude: recarga, avisos y certificados (2026-10-23)

**Prioridad inmediata solicitada:** completar el relevo de Claude. El usuario activó Astra y autorizó continuar, pidiendo código cuidado. Implementados los avisos sin duplicados, la conexión del taller con los certificados de venta y las fechas reales del cilindro. Rama actual: `codex/certificados-dinamicos`; se conservan los cambios anteriores sin commit.

- [x] Entender el pedido original: una recarga aparece dos veces en «Por vencer»; local requiere operatividad y capacitación, vehículo requiere operatividad y PH. Reutilizar la regla existente.
- [x] Alertas: ambas consultas descuentan los equipos ya identificados por venta y orden; cubiertas líneas repetidas, recepciones parciales y ventas mixtas en `AlertModuleTest`.
- [x] Certificados de taller: «Armar certificados» admite equipos aptos de órdenes terminadas; local/vehículo reutiliza `tiposPorDestino`, conserva historia, referencia y PH válida, y no inventa capacitación ni una PH realizada. El cambio de series y emisión es transaccional y respeta cliente/sede.
- [x] E-024: alta y edición validan fechas/año; los equipos de cliente no reciben fecha de venta ficticia; la última PH real alimenta el cálculo configurable y editable. Las nuevas fechas quedan en snapshot del certificado. No se actualizaron fechas históricas sin evidencia.
- [x] Regresiones y CI: `composer ci:check` pasó con 1,433 pruebas y 8,151 aserciones; la última tanda dirigida pasó 122 pruebas y 642 aserciones. `npm run types:check`, formato/lint, Pint, PHPStan, build y `git diff --check` pasaron. No ejecutar `migrate:fresh` fuera de Pest ni alterar los datos locales de la exposición.

**Pendiente para cerrar E-024:** cotejo manual de fechas desconocidas en equipos existentes y revisión visual en navegador. Work todavía informa que el navegador local está deshabilitado incluso después de que el usuario indicó que lo habilitaría. No se marca el error como solucionado hasta resolver esos dos puntos.

**Siguiente paso para cerrar E-024:** habilitar la revisión del navegador en Work y cotejar con el cilindro los equipos históricos sin fecha real de última PH. La migración aditiva ya se aplicó en la base local; no se asignan fechas a registros sin evidencia. El código y las regresiones están terminados y verificados. Sin commit ni push.

Los ocho cambios del relevo de Claude ya tienen implementación. Verificación: **149/149 pruebas / 1339 aserciones** en la regresión conjunta y **175/175 / 1167 aserciones** en la ampliación de certificados, firmas, PDF/Word, verificación pública y la agenda con otro servicio principal (lotes con pruebas en común). PHPStan 0, TypeScript, Pint, lint/formato y build pasan. La revisión móvil/oscuro/axe está bloqueada por el navegador deshabilitado en Work.

Investigación de orquestación (2026-10-18): candidatos [Orchestrator.inc](https://github.com/OrchestratorInc/agent-orchestrator) (supervisor y feedback de PR/CI) y [Orca de Stably](https://github.com/stablyai/orca) (entorno visual, CLI personalizable y coordinación supervisada experimental), ambos para Windows. Es comparación documental, no adopción aprobada ni ejecución probada. Antes de integrar: validar el perfil `codex -p mimo`, preservar cambios locales y respetar la restricción de MiMo a uso interactivo. En Orca, revisar argumentos/permisos de lanzamiento. Sin instalaciones ni cambios de dependencias realizados.

Rama `feat/ordenes-varios-servicios`: **5 commits sobre `main` y trabajo acumulado sin commit**. Evidencia histórica anterior al relevo: suite de 1294 pruebas (8 corregidas después) y, después de los últimos cambios, 360/360 en lo que toca certificados y firmantes. PHPStan 0, tipos y lint en verde.

## Prompt maestro V3 (2026-10-28): auditoría y planificación

Entregado (solo documentación, sin código): [auditoría](../audit/AUDITORIA_2026-10-28.md), [inventario de vistas](../views/INVENTARIO_VISTAS_ACTUAL.md), [arquitectura objetivo](../architecture/ARQUITECTURA_OBJETIVO.md), [componentes y familia documental](../design-system/COMPONENTES_Y_FAMILIA_DOCUMENTAL.md), [métricas](../finance-bi/METRICAS_FINANCIERAS.md), [ADR-0001](../../adr/0001-evolucionar-el-beta.md) y la gobernanza multi-IA ([START_HERE](START_HERE.md)).

Orden propuesto, sujeto a P-01 en [OPEN_QUESTIONS.md](OPEN_QUESTIONS.md):

1. **Gobernanza:** commit del trabajo acumulado, etiqueta `beta-2026-10` y depurar Skills.
2. **Fase 1 actual (dinero y SUNAT)** + servicio único de métricas (E-021).
3. **Familia documental:** `CampoFormulario`, `LineasDelDocumento`, `ResumenDeImportes`, tasa de IGV desde el backend; aplicar primero en Nueva venta y Cotización.
4. **Fases 2 a 5** de la tabla siguiente (flujos, certificados, clientes y pantallas), usando los componentes de la familia.
5. **Compras y proveedores** (si se aprueba P-05).
6. **Diseño con Claude Design** de las pantallas reestructuradas, módulo por módulo.
7. **Producción:** respaldo (P-06), certificado SUNAT real, GRE, R.S. 000048-2026.

Criterio de aceptación de cada paso: AI_RULES + `composer ci:check` en verde + navegador (escritorio, celular, claro y oscuro, axe) + documentación actualizada.

## En curso: corregir la auditoría (decidido con el usuario el 2026-10-23)

Las decisiones de cada error están en [ERRORES.md → Decisiones aprobadas](ERRORES.md#decisiones-aprobadas-2026-10-23). Reglas para cada fase:

1. **Investigar primero.** Antes de programar, revisar cómo lo resuelven los sistemas grandes (Odoo, SAP, software de protección contra incendios) y la norma (SUNAT, NTP). Anotar la fuente en ERRORES.md y decir dónde nos apartamos del estándar y por qué.
2. **Leer el código** que toca el cambio de punta a punta antes de editar.
3. **Prueba que falla primero** (Pest); después, el arreglo.
4. **Verificar:** pruebas afectadas, `composer ci:check` al cerrar la fase y revisión en el navegador (escritorio y celular, claro y oscuro, axe) si hay pantalla.
5. Cada error pasa a Solucionado según las reglas de ERRORES.md. Sin commit ni push sin pedido.

| Fase  | Tema                                                                                                                                                                                                                     | Errores                                                                                                                                                         |
| ----- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1     | **Dinero y SUNAT:** caja ciega por billetes y monedas; Corregir y Anular con nota de crédito; editar pago; dashboard sin IGV (Productos y Servicios); IGV de la ND por mora; detracción obligatoria por tipo de servicio | E-011, E-021, E-040, E-041, E-042, E-044, E-045, E-052, E-009                                                                                                   |
| 2     | **Flujos que se traban:** órdenes con cantidad, recepción con faltantes, reparación rechazada, recojo, inspección según lo encontrado, entrega «Por recoger», estados iguales para cada rol, checklist único             | E-022, E-023, E-026, E-027, E-028, E-029, E-030, E-036, E-039, E-046, E-047, E-048, E-049, E-054, E-055, E-057, E-060, E-062, E-072, E-013, E-033, E-050, E-067 |
| 3     | **Certificados:** texto según venta, recarga o mantenimiento; certificados por vehículo con su placa; nombre comercial; PH cada 5 años; mediciones con mínimo                                                            | E-024, E-035, E-051, E-068, E-069, E-070, E-071                                                                                                                 |
| 4     | **Clientes y catálogo:** sedes y vehículos al crear el cliente; buscador por sede; Clientes varios fuera de los conteos; nombres duplicados; cambio de puesto; matriz de permisos; código muerto                         | E-002, E-003, E-015, E-018, E-038, E-056, E-059                                                                                                                 |
| 5     | **Pantallas, textos y accesibilidad:** un solo patrón de errores junto al campo (resuelve varios a la vez), plurales, fechas, contraste, celular                                                                         | E-004, E-005, E-006, E-010, E-012, E-014, E-017, E-019, E-020, E-025, E-031, E-032, E-034, E-037, E-043, E-053, E-058, E-061, E-063, E-064, E-065, E-066        |
| Final | **Datos del negocio:** ficha RUC (código de establecimiento), contadora (detracción e IGV de la ND), credenciales GRE cuando el sistema salga de demo                                                                    | E-016                                                                                                                                                           |

## Hecho, por módulo

- **Ventas y facturación SUNAT:**
    - factura, boleta y notas con Greenter y envío diferido (6 h);
    - forma de pago al contado o a crédito con cuotas;
    - aviso y cálculo de detracción con la tasa vigente por código (catálogo 54 en BD) y estado sujeto, no sujeto o por definir;
    - constancia de depósito;
    - formalización de la nota de venta;
    - PDF y QR que conservan lo emitido.
- **Catálogo:**
    - productos y servicios con código correlativo (PRD-, SRV-) y formularios cortos sin scroll;
    - "Extintor" lleva sticker BF-EQ y "Otro producto" el código de barras que liga Almacén;
    - el tipo de servicio es obligatorio y trae la detracción, el certificado y el código SUNAT;
    - **catálogo 25 oficial** (52 840 códigos) con buscador separado para bienes y servicios;
    - agentes, marcas y categorías administrables.
- **Certificados:**
    - editor de página completa con vista previa en vivo, igual al PDF;
    - hoja vertical u horizontal;
    - tabla de equipos y pruebas editables, conservando las claves y lo emitido (versiones).
- **Firmas:**
    - una ficha por persona en Firmas y sellos;
    - el **cargo va en cada certificado** (pivote `CertificateTypeSigner`);
    - el editor solo asigna quién firma, el cargo y el orden.
- **Órdenes de servicio:**
    - varias líneas de servicio por orden;
    - recojo iniciado por Campo sin cotización;
    - orden de taller por pasos en Planta;
    - chat de la orden;
    - casilleros compartidos.
- **Gerencia:** dashboard mensual con 8 métricas e IA de recompra, reporte PDF del mes, sedes comparadas solas, caja con salidas y diferencias explicadas, auditoría.
- **Almacén:** recepciones con escaneo, stock y Kardex, traslados con lector de barras, stickers de 80 × 50 mm.

## Pendiente para cerrar el proyecto

### En ejecución: creador dinámico de certificados (autorizado 2026-10-18)

Objetivo: crear y editar plantillas por bloques y vincularlas con tipos de servicio o productos, usando el marco de Claude Design protegido y conservando versiones emitidas. Sin dependencias nuevas, commit ni push; conservar cambios de BI existentes.

- [ ] Configuración versionada `composicion`: lista ordenada de `{tipo, titulo?, texto?}`. Tipos: `datos`, `declaracion`, `tabla`, `pruebas`, `texto`, `resultado`, `responsabilidad`, `diploma`; texto puede repetirse. Ausencia/null conserva la representación histórica. Campos de marco no desplazables en plantillas nuevas.
- [ ] Editor: agregar, quitar y ordenar bloques, textos técnicos libres (especificaciones, garantía, mantenimiento), orientación, columnas/pruebas y firmantes; previsualización con el marco original. Base opcional para empezar desde cero. Relacionar tipos de servicio y productos desde el editor, mostrando asignaciones actuales.
- [ ] Backend: validar composición y relaciones, guardado transaccional, FK nullable de producto; herencia de tipos de servicio conservada. Campos nuevos dentro de `CAMPOS_PLANTILLA`, snapshots históricos intactos.
- [ ] Emisión: habilitar plantillas del producto/servicio vendido, formularios sin filas cuando no hay tabla, diploma con sus campos; denegar ventas ajenas y borradores. Reagrupar extintores no debe anular certificados dinámicos.
- [ ] PDF/Word: representar bloques en orden y solo los elegidos, orientación y ASNEEX común en nuevas plantillas. Ejemplos cliente/equipos y diploma; datos reales al emitir, sin inventar resultados.
- [ ] Verificación: regresiones de creación/validación/vinculación/emisión/historia y documentos; TypeScript, Pint, PHPStan, formato/lint, build y revisión visual. Una sola ejecución de pruebas BD a la vez.

Riesgos: tablas largas multipágina, textos extensos, columnas obligatorias, cambios de asignación concurrentes y familias históricas. Revisar snapshots y acceso por vendedor/sede; no modificar facturación SUNAT.

- **Fondos de certificados (2026-10-18):** el usuario corrigió la propuesta reinterpretada de Codex. Recuperados los originales `Main.dc.html` y `Horizontal.dc.html` del scratchpad de Claude; vista fiel entregada como `original-certificados-claude.html` en el chat, con Barlow Condensed y Bebas Neue originales. Conservar distribución compacta de cabecera y posiciones originales; pendiente resolver las observaciones de tipografía y luego bloques e integración. El artefacto de Claude no se modificó.
    - Alcance confirmado en el historial anterior de Claude: crear plantillas eligiendo orientación, bloques y textos; relacionarlas con servicio o producto. Marco común protegido (marca, ASNEEX en todos, correlativo/QR/vigencia y posiciones de firmas); contenido central reutilizable según el certificado. Ejemplos acordados: extintores con pruebas/resultado, mangueras con especificaciones/garantía, capacitación con diploma horizontal.
- **Dashboards BI rediseñados** (2026-10-18): revisados el 2026-10-19 en los cinco roles e IA, escritorio claro y celular oscuro, sin errores, sin desbordes y con axe limpio. La base local no tiene ventas: solo se vieron los estados vacíos. Falta verlos con datos.

Relevo ejecutado por Codex el 2026-10-18: auditoría y código de los ocho cambios implementados. Integración autorizada por el usuario: publicar todo y fusionar en `main` mediante el PR #36, tras aprobar CI. La verificación final conjunta y la ampliación de certificados pasaron; ver la última entrada de BITÁCORA para evidencia y límites.

### Auditoría de los ocho cambios — 2026-10-18 (Codex)

El usuario pidió terminar los pendientes después de revisar el relevo. Se conserva el orden de las seis fases. Evidencia inicial de código; la revisión de navegador y las pruebas se registran al ejecutarse.

| Cambio       | Hoy / problema                                                                                                                   | Archivos y solución prevista                                                                                                                                                                  | Riesgos a verificar                                                                                                                    |
| ------------ | -------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------- |
| 1. Taller    | `User.php:89` asigna técnicos a almacén/mixta; `StoreSedeRequest.php:26` y enum SQL rechazan taller.                             | Migración aditiva, Sede, User, ValidarSedeDelRol, Usuarios/Sedes; extender `idsAtendidosPor`, `idsQueAtienden` y `idsComercialesAtendidasPor`, compartidos por órdenes, asignación y recojos. | Conservar datos existentes y permisos de órdenes asignadas; repuestos del almacén de la orden; no convertir stock comercial en taller. |
| 2. Traslados | `routes/almacen.php:28` y `almacen-sidebar.tsx` los ofrecen siempre.                                                             | Una condición compartida de dos almacenes activos, aplicada a todas las rutas de traslado y al menú.                                                                                          | Las tiendas y talleres no cuentan como almacenes; inactivos excluidos.                                                                 |
| 3. Stickers  | `StickerPdfService.php:90` solo genera papel 80×50.                                                                              | Formato rollo/A4 en servicio, plantilla PDF y los tres llamadores (recepción, unidad, equipos de taller), selector compartido en sus pantallas.                                               | Preservar BF-EQ/datos; 10 etiquetas por A4, paginación y formato inválido.                                                             |
| 4. BI        | Cinco dashboards con métricas/listas, sin recharts; ticket promedio en Gerente y Vendedor.                                       | Cinco controladores/páginas, componente de gráficos accesibles, agregaciones reales por rol y dependencia aprobada.                                                                           | Aislamiento por vendedor/sede, notas y ventas netas, PDF mensual y vacíos; Campo tiene service nullable sin protección.                |
| 5. IA        | `Gerente/DashboardController.php:277` limita IA a tarjeta; ya existen ClientRetentionScore e importador histórico.               | Pantalla/controlador/consulta propios, filtros y paginación; historial y vencimientos reales separados de probabilidad.                                                                       | Sin datos o puntuaciones antiguas explícitos; permisos por rol/equipo; no recalcular ni inventar predicciones.                         |
| 6. Editor    | `certificado-editor.tsx:832` usa iframe en fijos; otras plantillas tienen vista previa. Versionado ya existe.                    | Evaluar pdfme frente a extender hoja actual; preferir contrato de medidas y distribución compartido entre edición y PDF, preservando snapshots.                                               | Compatibilidad de fijos, tablas multipágina, Word, firmantes históricos y certificados emitidos.                                       |
| 7. Campo     | Installation/RegisterInstallation y Maintenance almacenan pruebas en eventos; no pasan filas medidas a certificados de servicio. | Reutilizar validación de EmitirCertificadoDeServicio, columnas de plantilla como formulario y un solo almacenamiento de mediciones vinculado a orden/tipo/versión.                            | Cierre concurrente, datos obligatorios, múltiples tipos, órdenes sin venta confirmada; no inventar umbrales eléctricos.                |
| 8. Producto  | PRD correlativo y enlace barcode ya funcionan; stickers solo InventoryUnit/Equipment.                                            | Reutilizar products.codigo; sticker por producto no serializado en servicio/controlador y recepción, sin etiquetas por cada unidad.                                                           | Acceso del almacenero, escaneo PRD frente a BF-EQ y cantidades; reutilizar formatos de fase 1.                                         |

- [x] Fase 1: Taller, técnicos, traslados y formatos de stickers.
- [x] Fase 2: BI de los cinco roles.
- [x] Fase 3: IA del gerente.
- [x] Fase 4: editor profesional y preservación histórica.
- [x] Fase 5: mediciones de Campo → certificado.
- [x] Fase 6: sticker por producto sin código de fabricante.

0. **Los ocho cambios de CONTEXTO_SISTEMA §10:** código implementado y regresiones afectadas aprobadas (lotes 149/149 y 175/175). Queda pendiente la revisión visual completa en móvil, claro/oscuro y axe: el navegador local quedó deshabilitado en Work durante la revisión.

    Editor: se conserva dompdf; textos editables en la hoja y marco fijo (logo, QR, ASNEEX, sello y firmas no se mueven; `diseno` se rechaza). La vista previa del editor por bloques replica el PDF con las mismas medidas. [pdfme](https://pdfme.com/docs/getting-started) requeriría paquetes y motor JS adicionales. Las versiones nuevas incrustan la imagen de firma; imágenes borradas antes de esta corrección no pueden reconstruirse.

    Seguridad de dependencias: `npm audit --omit=dev` reporta tres paquetes con alertas preexistentes (concurrently, shell-quote, source-map-js); sus versiones coinciden con HEAD. Actualizarlos requiere autorización para cambiar dependencias.

1. **Auditoría completa por rol** con [PROMPT_AUDITORIA.md](PROMPT_AUDITORIA.md) (ChatGPT). Luego priorizar los hallazgos, comparar con otros sistemas peruanos del rubro y corregir flujo por flujo.
2. **Cerrar la rama:** suite completa y `composer ci:check` en verde → commit → PR a `main` con el CI en verde (solo cuando el usuario lo pida).
3. **Datos del negocio:**
    - la contadora confirma el código de detracción de cada tipo (020 o 022) y los supuestos de GRE;
    - subir las fotos de las firmas en Firmas y sellos.
4. **Producción:** hosting (supuesto: no es Laravel Cloud), certificado `.pem` real, usuario SOL secundario, credenciales GRE, `SUNAT_BETA=false`.
5. **Antes del 01/01/2027:** revisar la R.S. 000048-2026 (bajas y boletas) y la R.S. 108-2026 (GRE).
6. **Certificado de producto automático (hecho, 2026-10-19):** el Gerente liga el producto a una plantilla por bloques (o la crea desde el producto); Almacén llena una vez su ficha técnica en «Fichas técnicas» con los campos de la tabla de la plantilla; al confirmar la venta el certificado sale solo (una fila por producto, con la referencia de la venta). Ficha incompleta → la venta se confirma y el certificado sale al completar la ficha. Falta: revisión visual en el navegador.
7. **Auditoría completa del sistema (terminada, 2026-10-20):** 72 errores en [ERRORES.md](ERRORES.md) (11 altos, 38 medios, 23 bajos; 3 solucionados). **Siguiente:** analizarlos con el usuario, agruparlos y priorizar; no arreglar sin aprobación. Sin probar en local: llave de acceso, GRE real y traslados.
8. **Siguiente (por conversar):** partir el certificado de producto por sede o local (como «Armar certificados» de extintores) y revisar cómo la referencia y el destino de la venta (local o vehículo) aparecen en todos los certificados.

## Decisiones vigentes (no reabrir)

- Las cinco funciones del negocio son Vendedor, Almacén, Gerente, Técnico de Planta y Técnico de Campo. Gerencia supervisa; no aprueba cada operación diaria.
- Cada punto de venta tiene su almacén; el Taller es un lugar aparte que atiende a todas las tiendas. Los técnicos pertenecen al Taller. Solo el gerente crea productos; Almacén liga los códigos de barras al recibir.
- Detracción: al crear un tipo de servicio se elige su código y porcentaje SUNAT (ya no queda «por definir»; decisión del 2026-10-23). La recarga de extintores es «Otros servicios empresariales» (Informe SUNAT 017-2006); la contadora valida la tasa. Nada se clasifica por el nombre del servicio.
- La GRE es una excepción (despacho por bus), no parte del delivery diario.
- Plantilla general de certificados, sin librerías nuevas. Los formatos fijos (extintores, prueba hidrostática, capacitación) solo cambian textos y firmantes.
- Las personas que firman se registran una sola vez; el cargo depende del certificado.
- Sin commit, push ni dependencias nuevas sin permiso. Las pruebas usan el certificado de `tests/Fixtures`.

## Integración en GitHub — 2026-10-18

- Usuario autoriza commit, push y merge de todo el trabajo en main.
- PR #36 reúne el código acumulado; suite completa local en ejecución y CI obligatorio antes del merge.
- PR #37 (GitHub Actions) integrado tras comprobar CI aprobado.
- Worktree de cotizaciones limpio y ya integrado en main; worktree antiguo de Claude conserva una versión previa de órdenes multilínea ya cubierta por el código y los mismos casos de prueba actuales. Se preservan ambos worktrees.
- Referencia de factura de `diseno-gpt` recuperada; el respaldo anterior contiene historia reemplazada y funciones ya incorporadas, no se restaura código obsoleto.
- Todo el trabajo está publicado en [PR #36](https://github.com/Peugh18/BruceFireSAC---Sistema-de-Gestion-de-ventas/pull/36), que concentra el estado de CI y fusión. El cierre exige CI verde y comprobar origin/main; no hay despliegue incluido.
- Primer CI: 1348 aprobadas y 5 fallos por un fixture CSV excluido y dos datos de pruebas antiguos. Corregidos en `070a0f3`, sin relajar las aserciones ni cambiar código de negocio.
- Verificaciones adicionales: 15/15 pruebas TypeScript, pruebas del script PowerShell, build, formato/lint, TypeScript, Pint y PHPStan aprobados.
- Pendiente de producto: revisión visual móvil/oscuro/axe al habilitar el navegador y evaluar por separado las dependencias con alertas preexistentes.

## Recorrido funcional del beta (28/10/2026)

Estado: recorrido ejecutado parcialmente, informes en D:\TiomiguelonGgs\Desktop\BruceFireOriginal\docs\recorrido-beta\. Se completaron F-02, F-05–F-09 y la carga de los cinco dashboards; F-01, F-03, F-11 y F-13 quedaron a medias. F-04 y F-10 siguen pendientes. La cobertura de menús y controles no es exhaustiva.

No se modificaron código, configuración ni contraseñas existentes, y no se ejecutaron pruebas. Dos boletas QA siguen programadas para envío automático a SUNAT beta; F-10 depende de la respuesta aceptada. Siguiente: revisar esa respuesta, terminar F-04 y continuar la cobertura de botones antes de cerrar la auditoría.

### Ampliación de auditoría funcional beta — 2026-10-28

- Informe ampliado en `D:\TiomiguelonGgs\Desktop\BruceFireOriginal\docs\recorrido-beta\`: F-14, tablas por rol, hallazgos P-008–P-013 y capturas PNG.
- Cobertura adicional de menús para Gerente, Vendedor, Almacén, Técnico de planta y Técnico de campo; se probaron accesos cruzados 403 en los cinco roles.
- Estado: cobertura de menús ampliada, pero no exhaustiva ni validada pantalla por pantalla con datos válidos/vacíos/inválidos. F-04 y F-10 siguen pendientes; exportación Excel quedó no probada por política del navegador.
- Sin edición de código/configuración ni pruebas automatizadas. B001-1 y B001-2 siguen en autoenvío beta; no se adelantó el envío manual.
- Siguiente: cuando la interfaz vuelva a estar disponible, completar flujos/control pendientes y consultar la respuesta automática SUNAT antes de F-10.

### Reanudación 2026-10-29 — bloqueada por navegador deshabilitado

- Se intentó reanudar el recorrido beta, pero Work informó que el uso del navegador local está deshabilitado. No se accedió por otro medio ni se cambió esa política.
- Por eso no se comprobó el estado actual de B001-1/B001-2 tras la hora programada de autoenvío. El último estado visual documentado es del 28/10 («Por enviar»).
- Pendiente para terminar: habilitar navegador local, verificar SUNAT en UI, completar F-04 con caso QA apto, seguir F-10 según respuesta, y ejecutar la matriz completa de controles por rol/dispositivo/tema. No marcar auditoría exhaustiva hasta cubrirlos.
