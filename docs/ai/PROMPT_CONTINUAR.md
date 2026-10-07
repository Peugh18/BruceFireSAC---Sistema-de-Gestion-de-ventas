# Prompt para continuar (si Claude se queda sin uso)

Copia y pega todo el bloque en otra IA (Codex, Gemini o Antigravity) abierta en `D:\TiomiguelonGgs\Documents\BRUCE FIRE\BruceFireSacv2`.

```
Continúa las correcciones del "Sistema web para la gestión de ventas en BRUCE FIRE S.A.C." (Laravel 13, PHP 8.3, Inertia + React 19, MySQL de Laragon, Pest, Greenter).

LEE PRIMERO: CLAUDE.md, AGENTS.md, .ai/rules/*.md, docs/ai/PROYECTO.md, docs/ai/ENCARGO_CORRECCIONES.md (reglas y fases), docs/ai/PLAN.md (qué está hecho [x] y qué falta [ ]), docs/ai/AUDITORIA.md y docs/ai/SUNAT.md (reglas SUNAT al pie de la letra).

ESTADO (2026-10-07), rama principal fix/correcciones-auditoria-completa:
- HECHO y con commit: fase A (facturación SUNAT), A2 (seguridad) y B (certificados y tipo de extintor). Commits fc024ec y 2b0fd02.
- FASE C (ventas, caja, cobranzas, KPI): HECHA con commit (pruebas escritas sin ejecutar).
- FASE E (almacén y Gerente): HECHA y unida a la rama principal (merge d4a36ed).
- FASE D (técnicos) y FASE H (guía de remisión, Greenter 5.3 + gre-api): HECHAS y unidas a la rama principal (último commit 2a290db).
- PENDIENTE: subir la rama (git push), correr la suite completa (`composer ci:check`, con MySQL encendido) y corregir lo que falle; después, la fase F (interfaces con prototipos). Pendientes menores anotados en docs/ai/PLAN.md: motor único de visitas, estados "en camino"/"en sitio", firma vacía rechazada en servidor, PDF/QR de la guía y reintento automático de su consulta.
- NO hacer: fase F (rediseño de interfaces: va al final, con prototipos) ni G (servidor).

REGLAS DEL DUEÑO:
- SUNAT al pie de la letra; solo beta; en las pruebas, solo respuestas simuladas.
- Pruebas breves: escribe las pruebas Pest, pero corre solo las del área tocada. La suite completa (`composer ci:check`) se corre una sola vez al final, antes de subir.
- Al cerrar cada fase: pint --dirty, phpstan (0 errores, sin baseline) y `npx tsc --noEmit`; commit "Fix(fase X): ..."; actualiza docs/ai/PLAN.md y docs/ai/BITACORA.md. NO hagas push.
- Textos en español con tildes; enlaces del frontend solo con Wayfinder (`php artisan wayfinder:generate --with-form`).
- Nunca marques [x] algo sin código real.
```
