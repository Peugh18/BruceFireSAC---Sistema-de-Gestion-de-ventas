# Bruce Fire SAC v2 — Prompt maestro

> Léelo antes de cualquier tarea. Es la fuente de verdad del proyecto para todos los agentes (Claude, Codex, MiMo).
> Última actualización: 2026-10-18

## Qué es

Sistema web interno de Bruce Fire S.A.C. (Trujillo, Perú): venta, recarga, mantenimiento e inspección de extintores, con facturación electrónica SUNAT, inventario por serie, trabajo técnico en planta y campo, y certificados.

## Objetivo de esta etapa

Terminar la rama `feat/ordenes-varios-servicios` (órdenes con varias líneas de servicio, orden de taller por pasos, marcas administrables, chat de la orden) y subirla a `main` con el CI en verde. Fecha límite: Por definir.

## Usuarios y casos principales

- Gerente: KPIs, reportes, usuarios, sedes, catálogo, auditoría e IA de recompra.
- Vendedor: clientes, cotizaciones, ventas, facturación, cobranzas, órdenes de servicio y certificados.
- Almacén: recepciones, stock, Kardex, ajustes y stickers.
- Técnico de Planta: recepción de equipos, checklist, deficiencias y ejecución de la orden de taller.
- Técnico de Campo: recojos, inspecciones, instalaciones, mantenimientos y entregas.

## Stack

- Backend: Laravel 13.32, PHP 8.3, Fortify, spatie/laravel-permission, Wayfinder.
- Frontend: Inertia 3 + React 19 + TypeScript, Tailwind 4, Radix y Recharts. Build con `vp` (Vite+).
- Base de datos: MySQL (local con Laragon). Colas en base de datos.
- Integraciones: SUNAT con Greenter (facturas, boletas, notas, guías GRE), dompdf, PhpWord, códigos QR y de barras, RENIEC/SUNAT para autocompletar clientes.
- CI: GitHub Actions (`.github/workflows/tests.yml`). El hook `pre-push` corre la revisión rápida.

## Arquitectura en corto

- Rutas por rol en `routes/` (vendedor, almacen, gerente, tecnico-planta, tecnico-campo, guias, settings).
- Controladores delgados en `app/Http/Controllers/<Rol>/`. La lógica de negocio va en `app/Actions/<Dominio>/` y `app/Services/`.
- Validación en Form Requests (`app/Http/Requests/`), permisos en Policies.
- Pantallas Inertia en `resources/js/pages/<rol>/`, componentes compartidos en `resources/js/components/`.
- Facturación y SUNAT: ver `docs/SUNAT_FACTURACION.md`. APIs (Greenter, GRE, APIsPeru): ver `docs/APIS.md`. Diseño: ver `DESIGN.md`.

## Comandos

| Para                                                | Comando                                                      |
| --------------------------------------------------- | ------------------------------------------------------------ |
| Instalar                                            | `composer setup`                                             |
| Levantar en local                                   | `composer dev`                                               |
| Pruebas afectadas                                   | `php artisan test --compact tests/Feature/<Archivo>Test.php` |
| Formato PHP                                         | `vendor/bin/pint --dirty --format agent`                     |
| Tipos y lint del frontend                           | `npm run types:check` · `npm run check`                      |
| Chequeo completo (cambios grandes o de facturación) | `composer ci:check`                                          |

## Convenciones

- Seguir las reglas de `AGENTS.md` (Laravel Boost) y de `.ai/rules/` (leer `.ai/rules/index.md` antes de editar).
- Nombres de dominio en español cuando el código vecino lo usa (`PasoDelTaller`, `ConversacionDeLaOrden`).
- Colores, espacios y componentes: usar los tokens de `DESIGN.md`; no poner valores sueltos.
- Textos de la interfaz en español claro, sin jerga técnica.
- No agregar errores a `phpstan-baseline.neon`.

## No tocar

- `.env` y los certificados reales de SUNAT. Las pruebas usan `tests/Fixtures/certificates/test-certificate.pem`.
- La facturación (Greenter, series, correlativos) sin pruebas y sin correr `composer ci:check`.
- `phpstan-baseline.neon` (solo se reduce).

## Orquestación de agentes (jefe, respaldo y obrero)

| Rol              | Agente                                      | Cómo se usa                                                                    |
| ---------------- | ------------------------------------------- | ------------------------------------------------------------------------------ |
| Jefe             | Claude Code                                 | Planea, reparte, revisa y decide. Hace lo difícil.                             |
| Jefe de respaldo | Codex (GPT)                                 | Toma el mando cuando a Claude se le acaba el plan. Sigue estas mismas reglas.  |
| Obrero           | MiMo `mimo-v2.6-pro` (Token Plan de Xiaomi) | `codex exec -p mimo -s workspace-write "<tarea>"` desde la raíz del repo       |
| Obrero alterno   | Command Code                                | `command-code -p "<tarea>" -t --skip-onboarding --max-turns 30 --accept-edits` |

- **Al obrero van las tareas fáciles:** cambios de texto o estilo, renombrar, una prueba suelta, ajustes mecánicos en 1 a 4 archivos. Lo difícil (facturación, permisos, migraciones, bugs de causa desconocida) lo hace el jefe.
- **Cómo pedirle algo al obrero:** escribe el objetivo, los archivos exactos, las restricciones (no leer `.env`, no hacer commit, no agregar dependencias) y el criterio de aceptación.
- **El jefe siempre revisa lo que hizo el obrero:** `git diff`, `npm run types:check`, `vendor/bin/pint --dirty --format agent` y las pruebas afectadas. Nada se da por bueno sin esa revisión.
- **Relevo:** antes de cerrar la sesión, el jefe deja una entrada en `BITACORA.md` con el estado y el siguiente paso. El jefe nuevo lee `PLAN.md`, la última entrada de la bitácora y `git status`, y sigue desde ahí.
- **Uso del plan de MiMo:** el plan es para uso interactivo con herramientas de código. No lo uses en bucles automáticos ni en scripts que corran solos.

## Skills para este proyecto

| Fase      | Skills                                                                                                                           |
| --------- | -------------------------------------------------------------------------------------------------------------------------------- |
| Planear   | grill-me, brainstorming, writing-plans                                                                                           |
| Construir | karpathy-guidelines, laravel-best-practices, inertia-react-development, test-driven-development, impeccable                      |
| Revisar   | verification-before-completion, spec-to-code-compliance, differential-review, sharp-edges, ponytail-review, accessibility-review |
| Memoria   | proyecto-maestro (guardar avance), graphify (opcional)                                                                           |

## Reglas de trabajo

1. Para tareas no triviales: planear primero y esperar la aprobación.
2. Ante un bug: primero la causa raíz, luego la prueba que falla y después el arreglo.
3. Antes de decir que algo está listo: correr las pruebas afectadas y los chequeos, y mostrar la evidencia. La suite completa la corre el CI.
4. No hacer commit ni push sin pedirlo. No agregar dependencias sin permiso.
5. Al terminar una tarea: actualizar `docs/ai/PLAN.md` y `docs/ai/BITACORA.md`.

## Preguntas abiertas

- Fecha límite de esta etapa y qué sigue después de la rama actual.
- Hosting de producción (supuesto: no es Laravel Cloud).
