import { MapPin, X } from 'lucide-react';
import { KeyboardEvent, useEffect, useState } from 'react';

import { Input } from '@/components/ui/input';
import ubigeos from '@/routes/ubigeos';

export type UbigeoOption = {
    codigo: string;
    departamento: string;
    provincia: string;
    distrito: string;
    etiqueta: string;
};

/**
 * Buscador del catálogo de ubigeos del INEI: se escribe el distrito, la
 * provincia o el código y se elige de la lista. Así departamento, provincia y
 * distrito siempre salen del catálogo y no se escriben a mano.
 */
export default function UbigeoPicker({
    value,
    onChange,
    error,
}: {
    value: UbigeoOption | null;
    onChange: (ubigeo: UbigeoOption | null) => void;
    error?: string;
}) {
    const [busqueda, setBusqueda] = useState('');
    const [resultados, setResultados] = useState<UbigeoOption[] | null>(null);
    const [activo, setActivo] = useState(0);

    useEffect(() => {
        const term = busqueda.trim();

        if (term.length < 2) {
            setResultados(null);
            return;
        }

        const controller = new AbortController();
        const timeout = window.setTimeout(async () => {
            try {
                const response = await fetch(
                    ubigeos.buscar.url({ query: { search: term } }),
                    { signal: controller.signal },
                );
                setResultados((await response.json()) as UbigeoOption[]);
                setActivo(0);
            } catch {
                if (!controller.signal.aborted) {
                    setResultados([]);
                }
            }
        }, 220);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [busqueda]);

    const elegir = (ubigeo: UbigeoOption) => {
        setBusqueda('');
        setResultados(null);
        onChange(ubigeo);
    };

    const alTeclear = (event: KeyboardEvent<HTMLInputElement>) => {
        if (!resultados || resultados.length === 0) {
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActivo((i) => Math.min(i + 1, resultados.length - 1));
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActivo((i) => Math.max(i - 1, 0));
        } else if (event.key === 'Enter') {
            event.preventDefault();
            elegir(resultados[activo]);
        } else if (event.key === 'Escape') {
            setResultados(null);
        }
    };

    if (value) {
        return (
            <div>
                <div className="border-input bg-background mt-1 flex h-9 items-center justify-between gap-2 rounded-md border px-3 text-sm">
                    <span className="flex min-w-0 items-center gap-2">
                        <MapPin className="text-muted-foreground size-3.5 shrink-0" />
                        <span className="truncate">{value.etiqueta}</span>
                        <span className="text-muted-foreground font-['IBM_Plex_Mono',monospace] text-[11px]">
                            {value.codigo}
                        </span>
                    </span>
                    <button
                        type="button"
                        onClick={() => onChange(null)}
                        className="text-muted-foreground hover:text-foreground"
                        aria-label="Quitar ubigeo"
                    >
                        <X className="size-3.5" />
                    </button>
                </div>
                {error && (
                    <p
                        className="text-destructive-strong mt-1 text-xs"
                        role="alert"
                    >
                        {error}
                    </p>
                )}
            </div>
        );
    }

    return (
        <div className="relative">
            <Input
                value={busqueda}
                onChange={(event) => setBusqueda(event.target.value)}
                onKeyDown={alTeclear}
                placeholder="Escribe el distrito, provincia o código"
                className="mt-1"
            />
            {resultados !== null && (
                <div className="bg-popover absolute z-20 mt-1 max-h-64 w-full overflow-y-auto rounded-md border shadow-md">
                    {resultados.length === 0 ? (
                        <div className="text-muted-foreground px-3 py-2 text-sm">
                            No se encontró ese ubigeo.
                        </div>
                    ) : (
                        resultados.map((ubigeo, index) => (
                            <button
                                key={ubigeo.codigo}
                                type="button"
                                onMouseDown={(event) => event.preventDefault()}
                                onClick={() => elegir(ubigeo)}
                                className={`flex w-full items-center justify-between gap-2 px-3 py-1.5 text-left text-sm ${index === activo ? 'bg-muted' : ''}`}
                            >
                                <span className="truncate">
                                    {ubigeo.etiqueta}
                                </span>
                                <span className="text-muted-foreground font-['IBM_Plex_Mono',monospace] text-[11px]">
                                    {ubigeo.codigo}
                                </span>
                            </button>
                        ))
                    )}
                </div>
            )}
            {error && (
                <p
                    className="text-destructive-strong mt-1 text-xs"
                    role="alert"
                >
                    {error}
                </p>
            )}
        </div>
    );
}
