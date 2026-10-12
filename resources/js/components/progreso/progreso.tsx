import type { ComponentProps, ReactNode } from 'react';
import { cn } from '@/lib/utils';

type DatosProgreso = {
    value?: number;
    max?: number;
    label: string;
    tone?: 'primary' | 'success' | 'warning' | 'neutral';
};

const colores = {
    neutral: 'bg-muted-foreground',
    primary: 'bg-primary',
    success: 'bg-success-strong',
    warning: 'bg-warning-strong',
};

function valores(value: number | undefined, max: number) {
    const total = Number.isFinite(max) && max > 0 ? max : 0;
    const actual =
        value !== undefined && Number.isFinite(value)
            ? Math.min(total, Math.max(0, value))
            : undefined;
    return {
        total,
        actual,
        porcentaje: total && actual !== undefined ? (actual / total) * 100 : 0,
    };
}

export function Progreso({
    value,
    max = 100,
    label,
    tone = 'primary',
    className,
    ...props
}: DatosProgreso & Omit<ComponentProps<'div'>, 'children'>) {
    const { total, actual, porcentaje } = valores(value, max);
    if (!total) {
        return (
            <p className="text-muted-foreground text-sm">
                {label}: sin elementos.
            </p>
        );
    }
    return (
        <div
            {...props}
            role="progressbar"
            aria-label={label}
            aria-valuemin={0}
            aria-valuemax={total}
            aria-valuenow={actual}
            className={cn(
                'bg-muted h-2 overflow-hidden rounded-full',
                className,
            )}
        >
            <div
                className={cn(
                    'h-full rounded-full',
                    colores[tone],
                    actual === undefined && 'w-1/3 motion-safe:animate-pulse',
                )}
                style={
                    actual === undefined
                        ? undefined
                        : { width: `${porcentaje}%` }
                }
            />
        </div>
    );
}

export function ProgresoConEtiqueta({
    label,
    value,
    max = 100,
    detalle,
    tone,
    className,
}: DatosProgreso & { detalle?: ReactNode; className?: string }) {
    const { total, actual } = valores(value, max);
    return (
        <div className={cn('space-y-2', className)}>
            <div className="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1 text-sm">
                <span className="font-medium">{label}</span>
                {actual !== undefined && total > 0 && (
                    <span className="text-muted-foreground tabular-nums">
                        {actual} de {total}
                    </span>
                )}
            </div>
            <Progreso label={label} value={value} max={max} tone={tone} />
            {detalle && (
                <div className="text-muted-foreground text-sm">{detalle}</div>
            )}
        </div>
    );
}

export function AnilloProgreso({
    value,
    max,
    label,
    subtitulo,
    className,
}: Required<Pick<DatosProgreso, 'value' | 'max' | 'label'>> & {
    subtitulo?: string;
    className?: string;
}) {
    const { actual = 0, total, porcentaje } = valores(value, max);
    if (!total)
        return (
            <p className="text-muted-foreground text-sm">
                {label}: sin elementos.
            </p>
        );
    return (
        <div className={cn('flex flex-wrap items-center gap-3', className)}>
            <div
                role="progressbar"
                aria-label={label}
                aria-valuemin={0}
                aria-valuemax={total}
                aria-valuenow={actual}
                className="relative size-20 shrink-0"
            >
                <svg
                    viewBox="0 0 100 100"
                    className="size-full -rotate-90"
                    aria-hidden="true"
                >
                    <circle
                        cx="50"
                        cy="50"
                        r="42"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="8"
                        className="text-muted"
                    />
                    <circle
                        cx="50"
                        cy="50"
                        r="42"
                        pathLength="100"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="8"
                        strokeDasharray={`${porcentaje} 100`}
                        strokeLinecap="round"
                        className="text-primary"
                    />
                </svg>
                <span
                    aria-hidden="true"
                    className="absolute inset-0 flex items-center justify-center text-xs font-semibold tabular-nums"
                >
                    {actual} de {total}
                </span>
            </div>
            <div className="min-w-0">
                <p className="font-medium">{label}</p>
                {subtitulo && (
                    <p className="text-muted-foreground text-sm">{subtitulo}</p>
                )}
            </div>
        </div>
    );
}

export function ProgresoSegmentado({
    label,
    segmentos,
    className,
}: {
    label: string;
    segmentos: {
        label: string;
        value: number;
        tone: NonNullable<DatosProgreso['tone']>;
    }[];
    className?: string;
}) {
    const datos = segmentos.map((segmento) => ({
        ...segmento,
        value: Number.isFinite(segmento.value)
            ? Math.max(0, segmento.value)
            : 0,
    }));
    const total = datos.reduce((suma, segmento) => suma + segmento.value, 0);
    return (
        <div
            className={cn('space-y-2', className)}
            role="group"
            aria-label={label}
        >
            <div
                className="bg-muted flex h-2 gap-0.5 overflow-hidden rounded-full"
                aria-hidden="true"
            >
                {datos
                    .filter((segmento) => segmento.value > 0)
                    .map((segmento) => (
                        <span
                            key={segmento.label}
                            className={colores[segmento.tone]}
                            style={{
                                width: `${(segmento.value / total) * 100}%`,
                            }}
                        />
                    ))}
            </div>
            <ul className="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                {datos.map((segmento) => (
                    <li
                        key={segmento.label}
                        className="flex items-center gap-1.5"
                    >
                        <span
                            aria-hidden="true"
                            className={cn(
                                'size-2 rounded-full',
                                colores[segmento.tone],
                            )}
                        />
                        {segmento.value} {segmento.label}
                    </li>
                ))}
            </ul>
        </div>
    );
}

export function ProgresoConMarcador({
    value,
    minimo,
    label,
}: {
    value: number;
    minimo: number;
    label: string;
}) {
    if (!Number.isFinite(value) || !Number.isFinite(minimo))
        return (
            <p className="text-muted-foreground text-sm">
                {label}: dato no disponible.
            </p>
        );
    const escala = Math.max(value, minimo, 1);
    const bajo = value < minimo;
    return (
        <div role="group" aria-label={label} className="space-y-2">
            <p className="flex flex-wrap justify-between gap-2 text-xs tabular-nums">
                <span>Disponibles: {value}</span>
                <span>Mínimo: {minimo}</span>
            </p>
            <div
                aria-hidden="true"
                className="bg-muted relative h-2 rounded-full"
            >
                <div
                    className={cn(
                        'h-full rounded-full',
                        bajo ? 'bg-warning-strong' : 'bg-success-strong',
                    )}
                    style={{ width: `${(Math.max(0, value) / escala) * 100}%` }}
                />
                <span
                    className="bg-foreground absolute top-[-3px] h-3.5 w-0.5 -translate-x-1/2"
                    style={{ left: `${(Math.max(0, minimo) / escala) * 100}%` }}
                />
            </div>
            <p
                className={cn(
                    'text-xs',
                    value <= minimo
                        ? 'text-warning-strong'
                        : 'text-muted-foreground',
                )}
            >
                {bajo
                    ? 'Bajo el mínimo'
                    : value === minimo
                      ? 'En el mínimo'
                      : 'Por encima del mínimo'}
            </p>
        </div>
    );
}
