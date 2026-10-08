import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { useRef } from 'react';
import type { HTMLAttributes, KeyboardEvent, ReactNode } from 'react';

import { cn } from '@/lib/utils';

/**
 * Pestaña de un grupo `TabsBf`.
 *
 * - Si trae `href`, se renderiza como enlace de Inertia (pestañas de
 *   navegación entre pantallas).
 * - Si no, es un botón que cambia el contenido con `onCambiar` (pestañas de
 *   estado local).
 */
export type PestanaBf<T extends string = string> = {
    id: T;
    titulo: ReactNode;
    icono?: LucideIcon;
    /** Contador pequeño a la derecha de la etiqueta (se oculta si es 0). */
    contador?: number;
    /** Destino de navegación; si existe, la pestaña es un enlace. */
    href?: string;
};

export type TabsBfProps<T extends string = string> = {
    pestanas: readonly PestanaBf<T>[];
    /** Pestaña activa. */
    activa: T;
    /** Se ejecuta al elegir una pestaña de estado local. */
    onCambiar?: (id: T) => void;
    /** Nombre accesible del grupo de pestañas (aria-label). */
    etiqueta: string;
    /** Prefijo único para los ids accesibles (tab / tabpanel). */
    idBase: string;
    className?: string;
    /** Reparte el ancho entre las pestañas (para selectores compactos). */
    anchoCompleto?: boolean;
    /** Props extra del contenedor (por compatibilidad con componentes viejos). */
    resto?: HTMLAttributes<HTMLDivElement>;
};

/** Props accesibles para el panel de contenido de un grupo de `TabsBf`. */
export function propsPanel(idBase: string, activa: string) {
    return {
        role: 'tabpanel' as const,
        id: `${idBase}-panel`,
        'aria-labelledby': `${idBase}-tab-${activa}`,
    };
}

/**
 * Barra de pestañas reutilizable de la aplicación (variante segmentada: chip
 * activo sobre fondo apagado). Unifica las pestañas de navegación y las de
 * estado local con roles `tablist`/`tab` y foco por teclado (flechas, Inicio,
 * Fin).
 */
export function TabsBf<T extends string = string>({
    pestanas,
    activa,
    onCambiar,
    etiqueta,
    idBase,
    className,
    anchoCompleto = false,
    resto,
}: TabsBfProps<T>) {
    const botones = useRef<(HTMLElement | null)[]>([]);

    const enfocar = (indice: number) => {
        const pestana = pestanas[indice];
        if (!pestana) {
            return;
        }

        botones.current[indice]?.focus();
        // Las pestañas de navegación solo mueven el foco: navega el enlace.
        if (!pestana.href) {
            onCambiar?.(pestana.id);
        }
    };

    const alTeclear = (evento: KeyboardEvent<HTMLDivElement>) => {
        const actual = pestanas.findIndex((p) => p.id === activa);
        let destino = actual;

        switch (evento.key) {
            case 'ArrowRight':
            case 'ArrowDown':
                destino = (actual + 1) % pestanas.length;
                break;
            case 'ArrowLeft':
            case 'ArrowUp':
                destino = (actual - 1 + pestanas.length) % pestanas.length;
                break;
            case 'Home':
                destino = 0;
                break;
            case 'End':
                destino = pestanas.length - 1;
                break;
            default:
                return;
        }

        evento.preventDefault();
        enfocar(destino);
    };

    return (
        <div
            role="tablist"
            aria-label={etiqueta}
            aria-orientation="horizontal"
            onKeyDown={alTeclear}
            className={cn(
                'bg-muted inline-flex max-w-full overflow-x-auto rounded-[9px] p-[3px]',
                anchoCompleto ? 'w-full' : 'w-fit',
                className,
            )}
            {...resto}
        >
            {pestanas.map((pestana, indice) => {
                const Icono = pestana.icono;
                const esActiva = pestana.id === activa;

                const clases = cn(
                    'inline-flex items-center gap-2 rounded-[7px] px-3.5 py-1.5 text-xs font-bold whitespace-nowrap transition-all',
                    anchoCompleto && 'flex-1 justify-center',
                    esActiva
                        ? 'bg-card text-foreground shadow-[0_1px_2px_rgba(0,0,0,0.06)]'
                        : 'text-muted-foreground hover:text-foreground',
                );

                const contenido = (
                    <>
                        {Icono ? <Icono className="size-4 shrink-0" /> : null}
                        <span>{pestana.titulo}</span>
                        {pestana.contador !== undefined &&
                        pestana.contador > 0 ? (
                            <span className="bg-muted text-muted-foreground rounded-full px-1.5 py-0.5 text-[10px] font-extrabold">
                                {pestana.contador}
                            </span>
                        ) : null}
                    </>
                );

                const comunes = {
                    role: 'tab' as const,
                    id: `${idBase}-tab-${pestana.id}`,
                    'aria-selected': esActiva,
                    tabIndex: esActiva ? 0 : -1,
                    className: clases,
                    ref: (nodo: HTMLElement | null) => {
                        botones.current[indice] = nodo;
                    },
                };

                return pestana.href ? (
                    <Link
                        key={pestana.id}
                        href={pestana.href}
                        preserveScroll
                        aria-current={esActiva ? 'page' : undefined}
                        {...comunes}
                    >
                        {contenido}
                    </Link>
                ) : (
                    <button
                        key={pestana.id}
                        type="button"
                        aria-controls={`${idBase}-panel`}
                        onClick={() => onCambiar?.(pestana.id)}
                        {...comunes}
                    >
                        {contenido}
                    </button>
                );
            })}
        </div>
    );
}

export default TabsBf;
