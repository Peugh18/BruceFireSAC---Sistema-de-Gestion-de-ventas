# Traspaso de sesión

> La IA que termina llena la sección «Última sesión» (reemplaza la anterior; el historial queda en BITACORA.md). La que empieza la lee después de [START_HERE.md](START_HERE.md).

## Plantilla

- **Quién y cuándo:**
- **Pedido del usuario (con sus palabras):**
- **Archivos revisados:**
- **Archivos cambiados:**
- **Pruebas ejecutadas y resultado:**
- **Lo que no se verificó:**
- **Decisiones tomadas o pendientes:**
- **Riesgos:**
- **Siguiente paso concreto:**

## Última sesión

- **Quién y cuándo:** Codex, 2026-11-06.
- **Pedido del usuario:** «Necesito subir este proyecto a AWS; revisa en qué se quedó Claude». Confirma que todavía no creó la instancia.
- **Estado verificado:** rama local `feat/despliegue-aws`, commit `d43c6dd`; `git ls-remote` confirma `main=163d07d` y ausencia de la rama remota de despliegue.
- **Archivos revisados:** memoria del proyecto, commit de despliegue completo, `despliegue/*`, seeders, comando y prueba de capacitación, configuración SUNAT, firma de comprobantes, respaldo, Docker ignore y CI.
- **Archivos cambiados:** PLAN.md, BITACORA.md y este relevo; solo documentación. Se conservó `docs/ai/auditoria/` sin seguimiento.
- **Pruebas:** `PrepararCapacitacionTest`: 1 prueba y 9 aserciones, aprobada (117,7 s); sintaxis Bash y `git diff --check` sin errores.
- **Hallazgos pendientes:** el instalador no proporciona certificado para firmar en beta; el marcador de primera instalación se crea antes del build y al reintentar puede omitir seed/usuarios; respaldo externo pendiente.
- **Lo que no se verificó:** build/arranque Docker, EC2, DNS, HTTPS, cola, programador y restauración. Docker y AWS CLI no disponibles; `gh` no autenticado. No se crearon recursos ni se hizo commit/push.
- **Siguiente paso concreto:** corregir y verificar el instalador, publicarlo mediante el flujo autorizado y después crear EC2, dominio/IP, instalar y verificar. Mantener SUNAT beta para capacitación; producción fiscal requiere configuración y datos reales aparte.
