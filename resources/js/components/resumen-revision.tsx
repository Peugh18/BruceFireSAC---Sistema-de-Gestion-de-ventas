import { AnilloProgreso } from '@/components/progreso';

type EquipoRevisable = { checklists?: { resultado_general: string }[] };

export function resumirRevision<T extends EquipoRevisable>(equipos: T[]) {
    const revisados = equipos.filter(
        (equipo) => (equipo.checklists?.length ?? 0) > 0,
    );
    return {
        total: equipos.length,
        revisados: revisados.length,
        observados: revisados.filter(
            (equipo) =>
                equipo.checklists?.[0]?.resultado_general !== 'conforme',
        ).length,
        pendientes: equipos.filter((equipo) => !equipo.checklists?.length),
    };
}

/** Un checklist guardado cuenta como revisión, también cuando tiene observaciones. */
export function ResumenRevision({ equipos }: { equipos: EquipoRevisable[] }) {
    const { total, revisados, observados, pendientes } =
        resumirRevision(equipos);
    if (total === 0) return null;
    return (
        <div className="bg-muted/40 rounded-lg p-3">
            <AnilloProgreso
                value={revisados}
                max={total}
                label="Equipos revisados"
                subtitulo={`${observados} con observaciones · ${pendientes.length} ${pendientes.length === 1 ? 'pendiente' : 'pendientes'} de revisar`}
            />
        </div>
    );
}
