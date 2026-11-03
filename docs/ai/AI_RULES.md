# Reglas para todas las IA del proyecto

> Complementan [AGENTS.md](../../AGENTS.md) y [CLAUDE.md](../../CLAUDE.md) (reglas de Laravel Boost) y `.ai/rules/` (convenciones del código). Si chocan, manda la más estricta y se pregunta al usuario.

1. **Fuente de verdad única.** El repositorio manda: [START_HERE.md](START_HERE.md), [PLAN.md](PLAN.md) y los ADR en `adr/`. La memoria de una conversación no es fuente.
2. **Un responsable por tarea.** Antes de empezar, anota en PLAN.md quién la toma (Claude, Codex, Gemini). Dos IA no editan los mismos archivos a la vez.
3. **Investigar antes de decidir.** Cada regla del negocio se contrasta con cómo lo hacen los sistemas grandes (Odoo, SAP, software del rubro) y con la norma (SUNAT, NTP). La fuente se anota.
4. **No cambiar la arquitectura por cuenta propia.** Una propuesta nueva va como ADR en estado «Propuesta» y espera la aprobación del usuario.
5. **Una regla en un solo lugar.** Antes de escribir, busca si ya existe (helper, Action, componente). Prohibido repetir cálculos de IGV, totales o KPI.
6. **Prueba primero.** Lógica o error nuevo → prueba Pest que falla → arreglo → prueba en verde.
7. **Nada está terminado sin evidencia.** Se muestra la salida de las pruebas; si algo no se verificó, se dice.
8. **Datos y comandos peligrosos.** No hay `.env.testing`: nunca correr `migrate:fresh` ni `db:wipe` fuera de Pest. Revisar `php artisan db:show` antes de cualquier comando destructivo.
9. **Sin commit, push ni dependencias nuevas** sin permiso del usuario.
10. **Cierre de sesión.** Actualizar ERRORES.md, PLAN.md, BITACORA.md y SESSION_HANDOFF.md.
11. **Textos de la interfaz** en español claro, sin jerga técnica; accesibilidad WCAG 2.2 AA; tema claro y oscuro; celular.
