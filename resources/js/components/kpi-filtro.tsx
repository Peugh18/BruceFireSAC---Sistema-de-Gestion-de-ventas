import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

const tonos = {
    neutral: {
        activo: 'border-foreground/30 bg-muted ring-2 ring-foreground/15',
        texto: 'text-muted-foreground',
    },
    warning: {
        activo: 'border-amber-500/30 bg-amber-500/10 ring-2 ring-amber-500/30',
        texto: 'text-warning-strong',
    },
    primary: {
        activo: 'border-primary/30 bg-primary/10 ring-2 ring-primary/30',
        texto: 'text-primary-strong',
    },
    success: {
        activo: 'border-emerald-500/30 bg-emerald-500/10 ring-2 ring-emerald-500/30',
        texto: 'text-success-strong',
    },
    destructive: {
        activo: 'border-destructive/30 bg-destructive/10 ring-2 ring-destructive/30',
        texto: 'text-destructive-strong',
    },
};

/** Cifra que además filtra la lista de abajo; el estado activo se anuncia con aria-pressed. */
export function KpiFiltro({
    titulo,
    valor,
    detalle,
    icono,
    activo,
    tono = 'neutral',
    onClick,
}: {
    titulo: string;
    valor: ReactNode;
    detalle: string;
    icono?: ReactNode;
    activo: boolean;
    tono?: keyof typeof tonos;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            aria-pressed={activo}
            onClick={onClick}
            className={cn(
                'focus-visible:ring-ring flex flex-col justify-between rounded-xl border p-3 text-left transition-all focus-visible:ring-2 focus-visible:outline-none',
                activo
                    ? tonos[tono].activo
                    : 'border-border bg-card hover:bg-muted/40',
            )}
        >
            <span
                className={cn(
                    'flex items-center justify-between gap-2',
                    tonos[tono].texto,
                )}
            >
                <span className="text-xs font-bold tracking-wider uppercase">
                    {titulo}
                </span>
                {icono && <span aria-hidden="true">{icono}</span>}
            </span>
            <span className="text-foreground mt-2 text-2xl font-black tabular-nums">
                {valor}
            </span>
            <span className="text-muted-foreground text-xs">{detalle}</span>
        </button>
    );
}
