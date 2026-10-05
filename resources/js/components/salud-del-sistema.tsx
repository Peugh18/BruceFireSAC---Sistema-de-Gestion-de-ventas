import { AlertTriangle, CheckCircle2 } from 'lucide-react';

export type SaludDelSistemaData = {
    programadorActivo: boolean;
    ultimoLatido: string | null;
    revisadoAt: string | null;
    problemas: { clave: string; descripcion: string; cantidad: number }[];
    respaldo: { ok: boolean; fecha: string | null; detalle: string } | null;
};

function fechaHora(valor: string | null): string {
    if (!valor) return 'nunca';
    const coincide = valor.match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/);
    return coincide
        ? `${coincide[3]}/${coincide[2]}/${coincide[1]} ${coincide[4]}:${coincide[5]}`
        : valor;
}

/**
 * Estado del sistema para el Gerente: si corren las tareas automáticas, si
 * hay respaldo al día y si los datos cuadran (revisión de cada noche).
 * Cuando todo está bien ocupa una sola línea.
 */
export default function SaludDelSistema({
    salud,
}: {
    salud: SaludDelSistemaData;
}) {
    const avisos: string[] = [];

    if (!salud.programadorActivo) {
        avisos.push(
            `Las tareas automáticas no están corriendo (último registro: ${fechaHora(salud.ultimoLatido)}). Sin ellas no se envían comprobantes a SUNAT, ni se hacen respaldos ni los cálculos de cada noche.`,
        );
    }

    if (salud.respaldo && !salud.respaldo.ok) {
        avisos.push(salud.respaldo.detalle);
    }

    salud.problemas.forEach((problema) =>
        avisos.push(`${problema.descripcion}: ${problema.cantidad}`),
    );

    if (avisos.length === 0) {
        return (
            <div className="flex items-center gap-2 rounded-lg border border-emerald-500/20 bg-emerald-500/5 px-3.5 py-2 text-[12.5px] text-emerald-700 dark:text-emerald-400">
                <CheckCircle2 className="size-4 shrink-0" />
                <span>
                    Sistema al día: tareas automáticas activas, respaldo al día
                    y los datos cuadran
                    {salud.revisadoAt
                        ? ` (revisado el ${fechaHora(salud.revisadoAt)})`
                        : ''}
                    .
                </span>
            </div>
        );
    }

    return (
        <div className="rounded-xl border border-amber-500/30 bg-amber-500/5 p-4">
            <div className="flex items-center gap-2 text-[13px] font-bold text-amber-800 dark:text-amber-400">
                <AlertTriangle className="size-4 shrink-0" />
                Revisa el sistema ({avisos.length})
            </div>
            <ul className="text-foreground/80 mt-2 flex list-disc flex-col gap-1 pl-6 text-[12.5px]">
                {avisos.map((aviso) => (
                    <li key={aviso}>{aviso}</li>
                ))}
            </ul>
            {salud.revisadoAt && (
                <p className="text-muted-foreground mt-2 text-[11px]">
                    Revisión de datos del {fechaHora(salud.revisadoAt)}.
                </p>
            )}
        </div>
    );
}
