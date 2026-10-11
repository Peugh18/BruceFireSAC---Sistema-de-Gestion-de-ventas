import type { ReactNode } from 'react';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';

export function KpiCard({
    titulo,
    valor,
    detalle,
    icono,
    className,
}: {
    titulo: string;
    valor: ReactNode;
    detalle: ReactNode;
    icono?: ReactNode;
    className?: string;
}) {
    return (
        <Card className={cn('h-full gap-3 p-5 shadow-none', className)}>
            <div className="flex items-start justify-between gap-3">
                <h2 className="text-muted-foreground text-sm font-medium">
                    {titulo}
                </h2>
                {icono && (
                    <span
                        aria-hidden="true"
                        className="bg-muted text-foreground flex size-8 shrink-0 items-center justify-center rounded-lg"
                    >
                        {icono}
                    </span>
                )}
            </div>
            <p className="text-foreground text-2xl font-semibold tracking-tight tabular-nums">
                {valor}
            </p>
            <div className="text-muted-foreground text-xs leading-relaxed">
                {detalle}
            </div>
        </Card>
    );
}
