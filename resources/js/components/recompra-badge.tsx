import { TrendingUp } from 'lucide-react';

export type Recompra = {
    porcentaje: number;
    categoria: 'alta' | 'media' | 'baja';
    razones: { texto: string; a_favor: boolean }[];
};

const estilos: Record<Recompra['categoria'], string> = {
    alta: 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
    media: 'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-400',
    baja: 'border-border bg-muted text-muted-foreground',
};

/**
 * Probabilidad de que el cliente vuelva a comprar en 6 meses (modelo de
 * recompra). Al pasar el mouse muestra las razones que más pesaron.
 */
export function RecompraBadge({ recompra }: { recompra: Recompra }) {
    const detalle = recompra.razones
        .map((razon) => `${razon.a_favor ? '▲' : '▼'} ${razon.texto}`)
        .join('\n');

    return (
        <span
            title={`Probabilidad de volver a comprar en 6 meses\n${detalle}`}
            className={`inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-[10.5px] font-bold ${estilos[recompra.categoria]}`}
        >
            <TrendingUp className="size-3" />
            Recompra {recompra.categoria} · {recompra.porcentaje}%
        </span>
    );
}

/**
 * Lista de razones del modelo: a favor en verde, en contra en gris.
 */
export function RecompraRazones({ recompra }: { recompra: Recompra }) {
    if (recompra.razones.length === 0) {
        return null;
    }

    return (
        <ul className="mt-1.5 flex flex-col gap-0.5 text-[11.5px]">
            {recompra.razones.map((razon) => (
                <li
                    key={razon.texto}
                    className={
                        razon.a_favor
                            ? 'text-emerald-700 dark:text-emerald-400'
                            : 'text-muted-foreground'
                    }
                >
                    {razon.a_favor ? '▲' : '▼'} {razon.texto}
                </li>
            ))}
        </ul>
    );
}
