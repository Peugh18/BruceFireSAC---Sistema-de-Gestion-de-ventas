import { TrendingUp } from 'lucide-react';

export type Recompra = {
    porcentaje: number;
    categoria: 'alta' | 'media' | 'baja';
    resumen: string;
    razones: { texto: string; a_favor: boolean }[];
};

const estilos: Record<Recompra['categoria'], string> = {
    alta: 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
    media: 'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-400',
    baja: 'border-border bg-muted text-muted-foreground',
};

/**
 * Probabilidad de que el cliente vuelva a comprar en 6 meses (modelo de
 * recompra). Al pasar el mouse muestra el porqué en una frase.
 */
export function RecompraBadge({ recompra }: { recompra: Recompra }) {
    return (
        <span
            title={`Probabilidad de volver a comprar en 6 meses. ${recompra.resumen}`}
            className={`inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-[10.5px] font-bold ${estilos[recompra.categoria]}`}
        >
            <TrendingUp className="size-3" />
            Recompra {recompra.categoria} · {recompra.porcentaje}%
        </span>
    );
}
