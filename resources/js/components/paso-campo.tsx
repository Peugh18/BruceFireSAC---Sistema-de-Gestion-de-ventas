import { Link } from '@inertiajs/react';
import { ArrowLeft, Check } from 'lucide-react';
import type { ReactNode } from 'react';
import { createPortal } from 'react-dom';

import { usePlantaPerfil } from '@/hooks/use-planta-perfil';

const estiloVolver =
    'border-border bg-card hover:bg-muted focus-visible:ring-ring flex size-11 shrink-0 items-center justify-center rounded-2xl border focus-visible:ring-2 focus-visible:outline-none';

/** Cabecera de un paso de campo: volver, etiqueta roja, título y avance. */
export function CabeceraPaso({
    etiqueta,
    titulo,
    volver,
    paso,
    total,
    extra,
}: {
    etiqueta: string;
    titulo: string;
    /** Enlace (Inertia) o acción para el botón de volver. */
    volver: string | (() => void);
    paso?: number;
    total?: number;
    extra?: ReactNode;
}) {
    const icono = <ArrowLeft className="size-5" aria-hidden="true" />;

    return (
        <div className="space-y-3.5">
            <div className="flex items-center gap-3">
                {typeof volver === 'string' ? (
                    <Link
                        href={volver}
                        aria-label="Volver"
                        className={estiloVolver}
                    >
                        {icono}
                    </Link>
                ) : (
                    <button
                        type="button"
                        onClick={volver}
                        aria-label="Volver"
                        className={estiloVolver}
                    >
                        {icono}
                    </button>
                )}
                <div className="min-w-0 flex-1">
                    <p className="text-primary-strong truncate text-xs font-bold tracking-wider uppercase">
                        {etiqueta}
                        {paso && total ? ` · ${paso} de ${total}` : ''}
                    </p>
                    <h1 className="text-foreground truncate font-['Oswald',sans-serif] text-[22px] leading-tight font-semibold">
                        {titulo}
                    </h1>
                </div>
                {extra}
            </div>
            {paso && total ? (
                <div
                    className="grid gap-1.5"
                    style={{
                        gridTemplateColumns: `repeat(${total}, minmax(0, 1fr))`,
                    }}
                    aria-hidden="true"
                >
                    {Array.from({ length: total }, (_, i) => (
                        <span
                            key={i}
                            className={`h-1 rounded-full ${i < paso ? 'bg-primary' : 'bg-border'}`}
                        />
                    ))}
                </div>
            ) : null}
        </div>
    );
}

/** Número grande a la derecha de la cabecera (total contado, 4/5…). */
export function Contador({ children }: { children: ReactNode }) {
    return (
        <span className="bg-foreground text-background flex h-11 min-w-11 items-center justify-center rounded-2xl px-3 font-['Oswald',sans-serif] text-xl font-semibold tabular-nums">
            {children}
        </span>
    );
}

/** Botón del paso, fijo abajo (la barra de campo está oculta en el trabajo). */
export function PiePaso({ children }: { children: ReactNode }) {
    const { contenedorPie } = usePlantaPerfil();
    const pie = (
        <div className="bg-background space-y-2.5 px-4 pt-3 pb-[max(1.5rem,env(safe-area-inset-bottom))]">
            {children}
        </div>
    );

    return contenedorPie ? createPortal(pie, contenedorPie) : pie;
}

export const botonPrincipal =
    'bg-primary text-primary-foreground hover:bg-primary/90 focus-visible:ring-ring flex min-h-13 w-full items-center justify-center gap-2 rounded-2xl text-base font-semibold focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:opacity-50';

export const botonSecundario =
    'border-border bg-card text-foreground hover:bg-muted focus-visible:ring-ring flex min-h-13 w-full items-center justify-center gap-2 rounded-2xl border text-base font-semibold focus-visible:ring-2 focus-visible:outline-none';

export const tarjeta = 'border-border bg-card rounded-3xl border';

/** Pantalla final de un trabajo: check verde, código, título y resumen. */
export function TrabajoListo({
    codigo,
    titulo,
    filas,
}: {
    codigo: string;
    titulo: string;
    filas: [string, ReactNode][];
}) {
    return (
        <div className="flex flex-col items-center gap-2.5 py-8 text-center">
            <span className="flex size-20 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">
                <Check
                    className="size-10"
                    strokeWidth={2.5}
                    aria-hidden="true"
                />
            </span>
            <p className="text-primary-strong mt-1.5 text-xs font-bold tracking-wider uppercase">
                {codigo}
            </p>
            <h1 className="font-['Oswald',sans-serif] text-[28px] font-semibold">
                {titulo}
            </h1>
            <dl
                className={`${tarjeta} divide-border mt-4 w-full divide-y overflow-hidden text-left`}
            >
                {filas.map(([nombre, valor]) => (
                    <div
                        key={nombre}
                        className="flex justify-between gap-3 px-4 py-3.5 text-sm"
                    >
                        <dt className="text-muted-foreground">{nombre}</dt>
                        <dd className="text-right font-bold">{valor}</dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}

/** Mensaje por WhatsApp al cliente; sin teléfono abre WhatsApp para elegir. */
export function enlaceWhatsApp(
    telefono: string | null | undefined,
    texto: string,
) {
    const numero = (telefono ?? '').replace(/\D/g, '');
    const conPais = numero.length === 9 ? `51${numero}` : numero;

    return `https://wa.me/${conPais}?text=${encodeURIComponent(texto)}`;
}
