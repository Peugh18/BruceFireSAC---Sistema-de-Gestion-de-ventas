# ADR-0001 · Evolucionar el beta en el mismo repositorio en lugar de reescribirlo en otra carpeta

- **Estado:** **Rechazada** por el usuario el 2026-10-28: la versión nueva se construye aparte, en `D:\TiomiguelonGgs\Desktop\BruceFireOriginal`, porque el beta tiene muchos errores. Este repositorio queda como referencia de solo lectura.
- **Fecha:** 2026-10-28
- **Autor:** Claude

## Contexto

El «Prompt maestro V3» pide no modificar el beta y desarrollar la nueva versión en una carpeta independiente, después de auditar.

La auditoría (`docs/audit/AUDITORIA_2026-10-28.md`) muestra que el beta ya tiene:

- 1.433 pruebas automáticas que pasan, PHPStan sin errores y CI;
- facturación aceptada por SUNAT beta, con reglas documentadas con fuente oficial;
- el flujo de recarga completo funcionando;
- 72 errores catalogados con decisiones de negocio ya tomadas con el usuario.

## Opciones

| Opción                                                | Ventajas                                                                                       | Desventajas                                                                                                                                                           |
| ----------------------------------------------------- | ---------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **A. Reescribir en `nueva-version/`**                 | Empezar «limpio»                                                                               | Repite meses de trabajo y de reglas SUNAT ya probadas; dos sistemas a mantener; las pruebas actuales no protegen el código nuevo; alto riesgo de regresiones fiscales |
| **B. Evolucionar el mismo repositorio** (recomendada) | Conserva las 1.433 pruebas como red; cada mejora llega a producción por fases; un solo sistema | Exige disciplina: refactorizar por partes y no mezclar cambios                                                                                                        |
| C. Reescritura parcial (solo el frontend)             | Interfaz nueva sobre el backend probado                                                        | Duplica pantallas mientras dura la transición                                                                                                                         |

## Decisión propuesta

**Opción B.** Cumple el objetivo del prompt («no sobrescribir el beta») de otra manera:

1. Congelar el beta actual con una etiqueta de git (`beta-2026-10`) y una copia de la base, para poder volver a él en cualquier momento.
2. Construir la «nueva versión» en una rama de larga duración (`v2`) del mismo repositorio, fase por fase (ver `docs/ai/PLAN.md`).
3. Cada fase se integra solo con `composer ci:check` en verde, revisión en el navegador y el visto bueno del usuario.
4. Si una parte necesita un cambio grande (por ejemplo, la familia documental), se construye junto a la actual y se reemplaza al final, sin dejar dos versiones vivas.

## Consecuencias

- Todas las IA trabajan sobre el mismo código y las mismas pruebas.
- La carpeta `nueva-version/` del prompt no se crea.
- Hay que hacer commit por fase para no perder trabajo (hoy hay 116 archivos sin commit).
