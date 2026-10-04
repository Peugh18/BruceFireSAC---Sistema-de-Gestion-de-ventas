import { Box, ScanLine, Wrench } from 'lucide-react';
import { KeyboardEvent, useEffect, useRef, useState } from 'react';

import { Cargando } from '@/components/cargando';
import catalogo from '@/routes/vendedor/catalogo';

export type CatalogItem = {
    tipo: 'product' | 'service';
    id: number;
    codigo: string | null;
    codigo_barras?: string | null;
    nombre: string;
    precio_venta: number;
    serializado: boolean;
    stock: number | null;
};

export type CatalogUnit = {
    inventory_unit_id: number;
    numero_serie: string;
    product_id: number;
    nombre: string;
    precio_venta: number;
    capacidad?: string | null;
    marca?: string | null;
    serie_fabricante?: string | null;
};

type Respuesta = { unidad: CatalogUnit | null; items: CatalogItem[] };

function money(value: number) {
    return new Intl.NumberFormat('es-PE', {
        style: 'currency',
        currency: 'PEN',
    }).format(value || 0);
}

/**
 * Buscador de productos y servicios por nombre, código o código de barras.
 * Con un lector de barras: escanea y Enter. En Venta, una serie BF-EQ exacta
 * agrega directamente esa unidad.
 */
export default function CatalogPicker({
    teamSlug,
    sedeId,
    onPick,
    onPickUnidad,
    placeholder = 'Buscar por nombre, código o código de barras...',
    disabled = false,
    soloServicios = false,
}: {
    teamSlug: string;
    sedeId?: number | '' | null;
    onPick: (item: CatalogItem) => void;
    onPickUnidad?: (unidad: CatalogUnit) => void;
    placeholder?: string;
    disabled?: boolean;
    soloServicios?: boolean;
}) {
    const [busqueda, setBusqueda] = useState('');
    const [respuesta, setRespuesta] = useState<Respuesta | null>(null);
    const [cargando, setCargando] = useState(false);
    const [activo, setActivo] = useState(0);
    const inputRef = useRef<HTMLInputElement>(null);

    const consultar = async (term: string, signal?: AbortSignal) => {
        const response = await fetch(
            catalogo.buscar.url(teamSlug, {
                query: {
                    search: term,
                    sede_id: sedeId || undefined,
                    tipo: soloServicios ? 'service' : undefined,
                },
            }),
            { signal },
        );

        return (await response.json()) as Respuesta;
    };

    useEffect(() => {
        const term = busqueda.trim();

        if (term.length < 1) {
            setRespuesta(null);
            return;
        }

        const controller = new AbortController();
        const timeout = window.setTimeout(async () => {
            setCargando(true);
            try {
                setRespuesta(await consultar(term, controller.signal));
                setActivo(0);
            } catch {
                if (!controller.signal.aborted) {
                    setRespuesta({ unidad: null, items: [] });
                }
            } finally {
                setCargando(false);
            }
        }, 200);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [busqueda, teamSlug, sedeId]);

    const limpiar = () => {
        setBusqueda('');
        setRespuesta(null);
        inputRef.current?.focus();
    };

    const elegir = (item: CatalogItem) => {
        onPick(item);
        limpiar();
    };

    const elegirUnidad = (unidad: CatalogUnit) => {
        onPickUnidad?.(unidad);
        limpiar();
    };

    const alTeclear = async (event: KeyboardEvent<HTMLInputElement>) => {
        const items = respuesta?.items ?? [];

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActivo((i) => Math.min(i + 1, Math.max(items.length - 1, 0)));
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActivo((i) => Math.max(i - 1, 0));
        } else if (event.key === 'Escape') {
            setRespuesta(null);
        } else if (event.key === 'Enter') {
            event.preventDefault();
            const term = busqueda.trim();

            if (!term) {
                return;
            }

            // El lector de barras escribe rápido y da Enter: se consulta al
            // momento sin esperar la pausa del buscador.
            const datos =
                respuesta && !cargando ? respuesta : await consultar(term);

            if (datos.unidad && onPickUnidad) {
                elegirUnidad(datos.unidad);
            } else if (datos.items.length > 0) {
                // Escaneado: el código interno o el de barras del fabricante.
                const exacto = datos.items.find(
                    (i) => i.codigo === term || i.codigo_barras === term,
                );
                elegir(exacto ?? datos.items[activo] ?? datos.items[0]);
            } else {
                setRespuesta(datos);
            }
        }
    };

    const items = respuesta?.items ?? [];
    const sinResultados =
        respuesta !== null && !respuesta.unidad && items.length === 0;

    return (
        <div className="relative">
            <div className="border-border bg-muted/40 focus-within:border-ring focus-within:ring-ring/50 flex items-center gap-2 rounded-[9px] border px-3 focus-within:ring-[3px]">
                <ScanLine className="text-muted-foreground size-4 shrink-0" />
                <input
                    ref={inputRef}
                    disabled={disabled}
                    value={busqueda}
                    onChange={(event) => setBusqueda(event.target.value)}
                    onKeyDown={(event) => void alTeclear(event)}
                    placeholder={placeholder}
                    className="placeholder:text-muted-foreground h-11 min-w-0 flex-1 bg-transparent text-[13px] outline-none disabled:cursor-not-allowed"
                />
                {cargando ? (
                    <Cargando className="text-muted-foreground size-3.5" />
                ) : null}
            </div>

            {respuesta && (respuesta.unidad || items.length > 0) ? (
                <div className="border-border bg-card absolute inset-x-0 top-full z-30 mt-1 max-h-[320px] overflow-y-auto rounded-[10px] border shadow-lg">
                    {respuesta.unidad && onPickUnidad ? (
                        <button
                            type="button"
                            onClick={() => elegirUnidad(respuesta.unidad!)}
                            className="border-border flex w-full items-center justify-between gap-3 border-b bg-emerald-500/5 px-3 py-2.5 text-left"
                        >
                            <span>
                                <span className="text-foreground block text-[13px] font-bold">
                                    {respuesta.unidad.nombre}
                                </span>
                                <span className="text-success-strong font-['IBM_Plex_Mono',monospace] text-[11px]">
                                    Serie {respuesta.unidad.numero_serie}
                                    {respuesta.unidad.marca
                                        ? ` · ${respuesta.unidad.marca}`
                                        : ''}
                                </span>
                            </span>
                            <span className="text-[12px] font-bold">
                                {money(respuesta.unidad.precio_venta)}
                            </span>
                        </button>
                    ) : null}
                    {items.map((item, index) => {
                        const sinStock =
                            item.tipo === 'product' &&
                            item.stock !== null &&
                            item.stock <= 0;

                        return (
                            <button
                                key={`${item.tipo}-${item.id}`}
                                type="button"
                                onMouseEnter={() => setActivo(index)}
                                onClick={() => elegir(item)}
                                className={`border-border flex w-full items-center justify-between gap-3 border-b px-3 py-2.5 text-left last:border-b-0 ${index === activo ? 'bg-muted/60' : 'bg-card'}`}
                            >
                                <span className="flex min-w-0 items-center gap-2.5">
                                    {item.tipo === 'service' ? (
                                        <Wrench className="size-4 shrink-0 text-blue-600 dark:text-blue-400" />
                                    ) : (
                                        <Box className="text-muted-foreground size-4 shrink-0" />
                                    )}
                                    <span className="min-w-0">
                                        <span className="text-foreground block truncate text-[13px] font-bold">
                                            {item.nombre}
                                        </span>
                                        <span className="text-muted-foreground font-['IBM_Plex_Mono',monospace] text-[11px]">
                                            {item.codigo ?? '—'}
                                            {item.tipo === 'service'
                                                ? ' · Servicio'
                                                : item.serializado
                                                  ? ' · Con serie'
                                                  : ''}
                                        </span>
                                    </span>
                                </span>
                                <span className="flex shrink-0 items-center gap-2">
                                    {item.stock !== null ? (
                                        <span
                                            className={`rounded-full px-2 py-0.5 text-[10.5px] font-bold ${sinStock ? 'bg-destructive/10 text-destructive-strong' : 'text-success-strong bg-emerald-500/10'}`}
                                        >
                                            Stock {item.stock}
                                        </span>
                                    ) : null}
                                    <span className="font-['IBM_Plex_Mono',monospace] text-[12px] font-bold">
                                        {money(item.precio_venta)}
                                    </span>
                                </span>
                            </button>
                        );
                    })}
                </div>
            ) : null}

            {sinResultados ? (
                <p className="text-muted-foreground mt-1 text-[11.5px]">
                    {/^\d{8,14}$/.test(busqueda.trim())
                        ? `El código ${busqueda.trim()} no está registrado: pide que lo agreguen al producto en Productos (Gerente) y vuelve a escanear.`
                        : `No hay productos, servicios ni series que coincidan con "${busqueda.trim()}".`}
                </p>
            ) : null}
        </div>
    );
}
