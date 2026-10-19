# Plan — Bruce Fire SAC v2

> Última actualización: 2026-10-18. Corto a propósito: el detalle de cada cambio está en el código, en las pruebas y en git.
> Referencias: [SUNAT_FACTURACION.md](../SUNAT_FACTURACION.md) · [APIS.md](../APIS.md) · [PROMPT_AUDITORIA.md](PROMPT_AUDITORIA.md)

## Dónde estamos

Los ocho cambios del relevo de Claude ya tienen implementación. Verificación: **149/149 pruebas / 1339 aserciones** en la regresión conjunta y **175/175 / 1167 aserciones** en la ampliación de certificados, firmas, PDF/Word, verificación pública y la agenda con otro servicio principal (lotes con pruebas en común). PHPStan 0, TypeScript, Pint, lint/formato y build pasan. La revisión móvil/oscuro/axe está bloqueada por el navegador deshabilitado en Work.

Investigación de orquestación (2026-10-18): candidatos [Orchestrator.inc](https://github.com/OrchestratorInc/agent-orchestrator) (supervisor y feedback de PR/CI) y [Orca de Stably](https://github.com/stablyai/orca) (entorno visual, CLI personalizable y coordinación supervisada experimental), ambos para Windows. Es comparación documental, no adopción aprobada ni ejecución probada. Antes de integrar: validar el perfil `codex -p mimo`, preservar cambios locales y respetar la restricción de MiMo a uso interactivo. En Orca, revisar argumentos/permisos de lanzamiento. Sin instalaciones ni cambios de dependencias realizados.

Rama `feat/ordenes-varios-servicios`: **5 commits sobre `main` y trabajo acumulado sin commit**. Evidencia histórica anterior al relevo: suite de 1294 pruebas (8 corregidas después) y, después de los últimos cambios, 360/360 en lo que toca certificados y firmantes. PHPStan 0, tipos y lint en verde.

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

    Editor: se conserva dompdf y se reutiliza dnd-kit instalado; textos editables en la hoja y bloques desplazables hasta ±24 pt con controles por teclado. El mismo diseño se guarda versionado y se aplica al PDF. [pdfme](https://pdfme.com/docs/getting-started) requeriría paquetes y motor JS adicionales. Word conserva el contenido, pero no replica los desplazamientos visuales del PDF. Las versiones nuevas incrustan la imagen de firma; imágenes borradas antes de esta corrección no pueden reconstruirse.

    Seguridad de dependencias: `npm audit --omit=dev` reporta tres paquetes con alertas preexistentes (concurrently, shell-quote, source-map-js); sus versiones coinciden con HEAD. Actualizarlos requiere autorización para cambiar dependencias.

1. **Auditoría completa por rol** con [PROMPT_AUDITORIA.md](PROMPT_AUDITORIA.md) (ChatGPT). Luego priorizar los hallazgos, comparar con otros sistemas peruanos del rubro y corregir flujo por flujo.
2. **Cerrar la rama:** suite completa y `composer ci:check` en verde → commit → PR a `main` con el CI en verde (solo cuando el usuario lo pida).
3. **Datos del negocio:**
    - la contadora confirma el código de detracción de cada tipo (020 o 022) y los supuestos de GRE;
    - subir las fotos de las firmas en Firmas y sellos.
4. **Producción:** hosting (supuesto: no es Laravel Cloud), certificado `.pem` real, usuario SOL secundario, credenciales GRE, `SUNAT_BETA=false`.
5. **Antes del 01/01/2027:** revisar la R.S. 000048-2026 (bajas y boletas) y la R.S. 108-2026 (GRE).

## Decisiones vigentes (no reabrir)

- Las cinco funciones del negocio son Vendedor, Almacén, Gerente, Técnico de Planta y Técnico de Campo. Gerencia supervisa; no aprueba cada operación diaria.
- Cada punto de venta tiene su almacén; el Taller es un lugar aparte que atiende a todas las tiendas. Los técnicos pertenecen al Taller. Solo el gerente crea productos; Almacén liga los códigos de barras al recibir.
- Detracción "por definir" hasta que la contadora confirme. Nada se clasifica por el nombre del servicio.
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
