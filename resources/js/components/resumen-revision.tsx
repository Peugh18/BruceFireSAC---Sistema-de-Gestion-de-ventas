type EquipoRevisable = {
    checklists?: { resultado_general: string }[];
};

/**
 * Avance de una visita con varios extintores: cuántos se revisaron, cuántos
 * quedan y cuántos salieron con observaciones (estos últimos ya cuentan entre
 * los revisados). Se ve de un vistazo antes de cerrar la visita.
 */
export function ResumenRevision({ equipos }: { equipos: EquipoRevisable[] }) {
    const total = equipos.length;
    const revisados = equipos.filter((e) => (e.checklists?.length ?? 0) > 0);
    const observados = revisados.filter(
        (e) => e.checklists?.[0]?.resultado_general !== 'conforme',
    ).length;
    const pendientes = total - revisados.length;

    if (total === 0) {
        return null;
    }

    return (
        <div
            className="bg-muted/40 space-y-2 rounded-lg p-3"
            aria-label="Avance de la revisión"
        >
            <div className="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                <span>
                    <strong>{revisados.length}</strong> de {total} revisados
                </span>
                <span>
                    <strong>{observados}</strong> con observaciones
                </span>
                <span>
                    <strong>{pendientes}</strong>{' '}
                    {pendientes === 1 ? 'pendiente' : 'pendientes'} de revisar
                </span>
            </div>
            <progress
                className="h-2 w-full accent-sky-600"
                aria-label="Equipos revisados"
                value={revisados.length}
                max={Math.max(1, total)}
            />
        </div>
    );
}
