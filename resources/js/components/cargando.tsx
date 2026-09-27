import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

import ChispaAvatar from '@/components/chispa-avatar';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

type CargandoProps = {
    className?: string;
    /** Texto para lectores de pantalla. */
    etiqueta?: string;
};

/**
 * Spinner único del sistema: un aro fino que gira rápido. Toma el color del
 * texto (currentColor) y se ajusta con clases de tamaño (size-3, size-4…).
 */
export function Cargando({ className, etiqueta = 'Cargando' }: CargandoProps) {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            role="status"
            aria-label={etiqueta}
            className={cn('cargando size-4 shrink-0', className)}
        >
            <circle
                cx="12"
                cy="12"
                r="9"
                stroke="currentColor"
                strokeWidth="2.5"
                opacity="0.2"
            />
            <path
                d="M21 12a9 9 0 0 0-9-9"
                stroke="currentColor"
                strokeWidth="2.5"
                strokeLinecap="round"
            />
        </svg>
    );
}

/**
 * Muestra algo solo si la espera pasa de `retraso` ms y, una vez visible, lo
 * deja al menos `minimo` ms: así no parpadea en respuestas rápidas.
 */
function useVisibleSostenido(
    activo: boolean,
    retraso: number,
    minimo: number,
): boolean {
    const [visible, setVisible] = useState(false);
    const visibleDesde = useRef(0);

    useEffect(() => {
        if (activo) {
            if (visible) {
                return;
            }

            const temporizador = setTimeout(() => {
                visibleDesde.current = Date.now();
                setVisible(true);
            }, retraso);

            return () => clearTimeout(temporizador);
        }

        if (!visible) {
            return;
        }

        const restante = minimo - (Date.now() - visibleDesde.current);
        const temporizador = setTimeout(
            () => setVisible(false),
            Math.max(0, restante),
        );

        return () => clearTimeout(temporizador);
    }, [activo, visible, retraso, minimo]);

    return visible;
}

type CargaLargaProps = {
    activo: boolean;
    /** Qué está pasando, en palabras del usuario: "Enviando la factura a SUNAT". */
    titulo: string;
    /** Cuánto suele tardar o qué esperar. */
    detalle?: string;
};

/**
 * Loader de marca para esperas largas (SUNAT, emisión, certificados): Chispa
 * corriendo con una barra indeterminada. Cubre la pantalla para evitar doble
 * envío, aparece solo si la espera pasa de 400 ms y se queda al menos 700 ms.
 */
export function CargaLarga({ activo, titulo, detalle }: CargaLargaProps) {
    const visible = useVisibleSostenido(activo, 400, 700);

    if (!visible) {
        return null;
    }

    return createPortal(
        <div className="bg-background/75 animate-in fade-in-0 fixed inset-0 z-[60] grid place-items-center p-4 backdrop-blur-[2px] duration-200">
            <div
                role="status"
                aria-live="polite"
                className="border-border bg-card animate-in fade-in-0 zoom-in-95 grid w-full max-w-[320px] justify-items-center gap-3 rounded-[16px] border px-6 py-6 text-center shadow-xl duration-200 ease-out"
            >
                <div className="relative grid h-[104px] place-items-end">
                    <ChispaAvatar pose="corre" size={96} animado />
                    <span className="chispa-sombra" aria-hidden />
                </div>
                <p className="text-foreground text-[14px] font-bold text-balance">
                    {titulo}
                </p>
                <span className="barra-indeterminada" aria-hidden>
                    <span />
                </span>
                {detalle ? (
                    <p className="text-muted-foreground text-[12px]">
                        {detalle}
                    </p>
                ) : null}
            </div>
        </div>,
        document.body,
    );
}

/** Hueso de skeleton: neutro en claro y oscuro, sin pulso si se pidió menos movimiento. */
const HUESO = 'rounded-full bg-foreground/[0.08] motion-reduce:animate-none';
const ANCHOS = ['w-16', 'w-28', 'w-20', 'w-24', 'w-12', 'w-20', 'w-10'];

type FilasCargandoProps = {
    columnas: number;
    filas?: number;
};

/**
 * Filas skeleton para el tbody de una tabla mientras llegan los datos. Usa
 * tantas filas como las que ya había (entre 3 y 10) para que no salte el alto.
 */
export function FilasCargando({ columnas, filas = 6 }: FilasCargandoProps) {
    const total = Math.min(Math.max(filas, 3), 10);

    return (
        <>
            {Array.from({ length: total }, (_, fila) => (
                <tr key={fila}>
                    {Array.from({ length: columnas }, (_, columna) => (
                        <td
                            key={columna}
                            className="border-border border-b px-2.5 py-[15px]"
                        >
                            {fila === 0 && columna === 0 ? (
                                <span role="status" className="sr-only">
                                    Cargando resultados
                                </span>
                            ) : null}
                            <Skeleton
                                className={cn(
                                    'h-3',
                                    HUESO,
                                    ANCHOS[
                                        (fila + columna * 3) % ANCHOS.length
                                    ],
                                )}
                            />
                        </td>
                    ))}
                </tr>
            ))}
        </>
    );
}

/** Tarjeta skeleton para un panel de detalle que se está cargando. */
export function TarjetaCargando({ className }: { className?: string }) {
    return (
        <div
            className={cn(
                'border-border bg-card grid gap-3 rounded-[16px] border p-5',
                className,
            )}
        >
            <span role="status" className="sr-only">
                Cargando
            </span>
            <Skeleton className={cn('h-3 w-24', HUESO)} />
            <Skeleton className={cn('h-4 w-56 max-w-full', HUESO)} />
            <Skeleton className={cn('h-3 w-40', HUESO)} />
            <div className="mt-2 grid gap-2">
                <Skeleton className={cn('h-3 w-full', HUESO)} />
                <Skeleton className={cn('h-3 w-11/12', HUESO)} />
                <Skeleton className={cn('h-3 w-2/3', HUESO)} />
            </div>
        </div>
    );
}
