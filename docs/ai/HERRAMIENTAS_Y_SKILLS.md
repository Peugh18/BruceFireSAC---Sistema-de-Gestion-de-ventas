# Herramientas (MCP) y Skills del proyecto

> Auditoría del 2026-10-28. Regla: cada herramienta resuelve una necesidad identificada y con el permiso mínimo. Nada se instala sin aprobación (P-11, P-12).

## 1. MCP

| Herramienta                                               | Estado                                                 | Uso                                                                                                           | Permisos y riesgos                                     |
| --------------------------------------------------------- | ------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------ |
| **Laravel Boost** (`.mcp.json` → `php artisan boost:mcp`) | **En uso**                                             | Esquema y consultas de la base, búsqueda de documentación por versión instalada, errores del navegador y logs | Lee la base de desarrollo: no usarlo contra producción |
| GitHub (CLI `gh` o GitHub MCP)                            | `gh` disponible y autenticado                          | Issues, PR y CI. El repositorio tiene Issues activos                                                          | Escritura: solo con permiso del usuario                |
| Context7                                                  | Disponible en Claude                                   | Documentación de librerías externas                                                                           | Solo lectura                                           |
| Navegador                                                 | Navegador integrado de Claude; Playwright para pruebas | Revisión visual y axe-core                                                                                    | Solo local o test; nunca credenciales reales           |
| Figma MCP                                                 | **No aplica**                                          | No usamos Figma: el diseño se hace con Claude Design                                                          | —                                                      |
| Base de datos de producción                               | **No autorizado**                                      | —                                                                                                             | Requiere su propio control cuando exista producción    |

No se necesita un MCP propio: Laravel Boost y `gh` cubren la inspección de datos y el repositorio.

## 2. Skills

**Útiles para este proyecto (mantener):**

| Skill                                                                                                                                                                                                                                        | Dónde                            | Para qué                                        |
| -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------- | ----------------------------------------------- |
| `laravel-best-practices`, `inertia-react-development`, `tailwindcss-development`, `wayfinder-development`, `fortify-development`, `testing-best-practices`                                                                                   | `.agents/skills` (Laravel Boost) | Código del stack real                           |
| `infer-conventions`                                                                                                                                                                                                                          | `.agents/skills`                 | Detectar y registrar convenciones               |
| `cross-role-workflow-audit`, `system-usability-audit`, `ui-edge-case-audit`                                                                                                                                                                  | `.agents/skills`                 | Auditorías por rol, usabilidad y estados límite |
| `pick-ui-library`                                                                                                                                                                                                                            | `.agents/skills`                 | Elegir librerías con criterio                   |
| Skills globales del usuario: `systematic-debugging`, `test-driven-development`, `verification-before-completion`, `accessibility-review`, `design-critique`, `impeccable`, `security-review`, `code-review`, `proyecto-maestro`, `humanizer` | `~/.claude/skills`               | Proceso de trabajo de Claude                    |

**Ajenas al proyecto (proponer quitar del repositorio, P-11):** HyperFrames y sus bloques (`hyperframes*`, `canopy-part-title`, `code-slice-hero`, `cuboid-carousel`, `frost-sequence-camera-orbit`, `glass-shard-title`, `orbit-card`, `wireframe-portal-title`, `embedded-captions`, `faceless-explainer`, `general-video`, `media-use`, `motion-graphics`, `music-to-video`, `pr-to-video`, `product-launch-video`, `remotion-to-hyperframes`, `slideshow`, `talking-head-recut`), `write-swift`, `mobile-native`, `deploying-to-cloud` (no usamos Laravel Cloud), `figma` (es de HyperFrames, no de diseño de UI). Las de animación (`animate*`, `animation-vocabulary`, `improve-animations`, `review-animations`, `find-animation-opportunities`, `emil-design-eng`, `apple-design`) solo se usan con la restricción de movimiento de [DESIGN.md](../../DESIGN.md).

**Skills de proyecto que faltan (crear solo si se repite la necesidad):**

| Skill                    | Por qué                                                                                   | Base                                             |
| ------------------------ | ----------------------------------------------------------------------------------------- | ------------------------------------------------ |
| `sunat-greenter-review`  | Revisar cambios de facturación contra SUNAT_FACTURACION.md y la documentación de Greenter | Nueva, corta                                     |
| `session-handoff`        | Llenar SESSION_HANDOFF.md igual en todas las IA                                           | Nueva, corta                                     |
| `component-reuse-review` | Detectar componentes y cálculos duplicados antes de crear uno nuevo                       | Puede cubrirla `infer-conventions` + AI_RULES §5 |

Cada Skill nueva se guarda una sola vez en `.agents/skills/<nombre>/SKILL.md`, la ruta que leen Codex y Claude, y no se duplica por plataforma.
