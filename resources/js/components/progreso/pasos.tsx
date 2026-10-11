import { Check, ChevronRight } from 'lucide-react';
import { cn } from '@/lib/utils';

export type Paso = {
    id: string;
    label: string;
    estado: 'pendiente' | 'actual' | 'completado';
};

const estilos = {
    pendiente: 'border-border bg-muted/40 text-muted-foreground',
    actual: 'border-primary/40 bg-primary/5 text-foreground',
    completado:
        'border-success-strong/30 bg-success-strong/5 text-success-strong',
};
const nombres = {
    pendiente: 'Pendiente',
    actual: 'Etapa actual',
    completado: 'Completado',
};

export function Pasos({
    pasos,
    label,
    variante = 'numerada',
    className,
}: {
    pasos: Paso[];
    label: string;
    variante?: 'numerada' | 'cheuron' | 'puntos';
    className?: string;
}) {
    const compacto = variante === 'puntos';
    return (
        <ol
            aria-label={label}
            className={cn(
                compacto
                    ? 'inline-flex items-center gap-1.5'
                    : 'grid gap-2 sm:flex',
                className,
            )}
        >
            {pasos.map((paso, indice) => (
                <li
                    key={paso.id}
                    aria-current={paso.estado === 'actual' ? 'step' : undefined}
                    className={cn(
                        'relative min-w-0',
                        compacto
                            ? 'flex'
                            : 'flex flex-1 items-center gap-2 rounded-lg border p-3 text-sm',
                        !compacto && estilos[paso.estado],
                    )}
                >
                    <span
                        aria-hidden="true"
                        className={cn(
                            'flex shrink-0 items-center justify-center rounded-full',
                            compacto
                                ? 'size-3'
                                : 'size-6 text-xs font-semibold',
                            paso.estado === 'completado'
                                ? 'bg-success-strong text-background'
                                : paso.estado === 'actual'
                                  ? 'bg-primary text-primary-foreground'
                                  : compacto
                                    ? 'border-muted-foreground border bg-transparent'
                                    : 'bg-muted text-muted-foreground',
                        )}
                    >
                        {paso.estado === 'completado' ? (
                            <Check
                                className={compacto ? 'size-2.5' : 'size-3.5'}
                            />
                        ) : !compacto ? (
                            indice + 1
                        ) : null}
                    </span>
                    <span
                        className={cn(
                            compacto
                                ? 'sr-only'
                                : 'flex min-w-0 flex-col font-medium',
                        )}
                    >
                        {paso.label}
                        <span
                            className={
                                compacto || paso.estado !== 'actual'
                                    ? 'sr-only'
                                    : 'text-xs font-normal'
                            }
                        >
                            : {nombres[paso.estado]}
                        </span>
                    </span>
                    {!compacto &&
                        variante === 'cheuron' &&
                        indice < pasos.length - 1 && (
                            <ChevronRight
                                aria-hidden="true"
                                className="text-muted-foreground ml-auto hidden size-4 shrink-0 sm:block"
                            />
                        )}
                </li>
            ))}
        </ol>
    );
}
