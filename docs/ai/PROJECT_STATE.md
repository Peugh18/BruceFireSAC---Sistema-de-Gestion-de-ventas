# Estado del proyecto

> Foto al **2026-10-28**. La actualiza cada IA al cerrar una tarea importante. Detalle en la [auditoría](../audit/AUDITORIA_2026-10-28.md).

- **Etapa:** beta funcional en demo (SUNAT beta, sin producción).
- **Versión de referencia:** rama `codex/certificados-dinamicos` sobre `main` (`c0af0c1`), con 116 archivos sin commit de Claude y Codex (pendiente P-02).
- **Calidad:** último `composer ci:check` verde (23/10): 1.433 pruebas, 8.151 aserciones; PHPStan 0; TypeScript, Pint y lint en verde.
- **Errores:** 72 registrados en [ERRORES.md](ERRORES.md); la tabla marca 4 como solucionados (E-001, E-007, E-008, E-011). También se corrigieron E-039, E-069, parte de E-030 y E-024 (Claude y Codex, 23/10), pero la tabla aún no lo refleja.
- **Funciona de punta a punta (verificado en el navegador):** recarga en taller (cliente → caja → orden → taller → certificado → cobro → entrega con acta), venta con Yape y cierre de caja ciego.
- **Fase en curso:** fase 1 del [PLAN](PLAN.md) (dinero y SUNAT). Siguiente: E-021 (métricas sin IGV), con el diseño de [METRICAS_FINANCIERAS.md](../finance-bi/METRICAS_FINANCIERAS.md).
- **Riesgos abiertos:** detracción por definir, cobros mal registrados (E-040, E-044), órdenes que se traban (E-049, E-055, E-072), certificados con datos «No legible», cambios SUNAT de 2027, falta de respaldo documentado.
- **Bloqueos:** decisiones P-01 a P-12 en [OPEN_QUESTIONS.md](OPEN_QUESTIONS.md).
- **Base local:** `migrate:fresh --seed` + `CatalogoDemoSeeder` (19 productos, 12 servicios, 5 usuarios con contraseña `password`).
