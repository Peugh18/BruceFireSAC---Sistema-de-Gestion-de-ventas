import { ExternalLink, Search, UserRoundPlus, X } from 'lucide-react';
import { KeyboardEvent, ReactNode, useEffect, useRef, useState } from 'react';

import { Cargando } from '@/components/cargando';
import ClientCreateDialog from '@/components/client-create-dialog';
import { Button } from '@/components/ui/button';
import clientes from '@/routes/vendedor/clientes';

export type ClientOption = {
    id: number;
    tipo_documento?: string;
    razon_social: string;
    numero_documento: string;
};

export type ClientFicha = ClientOption & {
    nombre_comercial?: string | null;
    direccion_fiscal?: string | null;
    estado_contribuyente?: string | null;
    condicion_domicilio?: string | null;
    vehiculos: { id: number; placa: string; descripcion?: string | null }[];
    sedes: { id: number; nombre: string; direccion?: string | null }[];
};

/**
 * Buscador único de clientes (Cotización, Venta y Orden de Servicio): filtra
 * letra por letra en la BD, ofrece "Agregar cliente" si no existe y, al
 * elegir, muestra la ficha con los datos fiscales y un botón para quitarlo.
 */
export default function ClientPicker({
    teamSlug,
    value,
    onChange,
    headerExtra,
    error,
    autoFocus = false,
}: {
    teamSlug: string;
    value: ClientFicha | null;
    onChange: (client: ClientFicha | null) => void;
    headerExtra?: ReactNode;
    error?: string;
    autoFocus?: boolean;
}) {
    const [busqueda, setBusqueda] = useState('');
    const [resultados, setResultados] = useState<ClientOption[] | null>(null);
    const [cargando, setCargando] = useState(false);
    const [activo, setActivo] = useState(0);
    const [creando, setCreando] = useState(false);
    const [cargandoFicha, setCargandoFicha] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        const term = busqueda.trim();

        if (term.length < 1) {
            setResultados(null);
            return;
        }

        const controller = new AbortController();
        const timeout = window.setTimeout(async () => {
            setCargando(true);
            try {
                const response = await fetch(
                    clientes.search.url(teamSlug, { query: { search: term } }),
                    { signal: controller.signal },
                );
                setResultados((await response.json()) as ClientOption[]);
                setActivo(0);
            } catch {
                if (!controller.signal.aborted) {
                    setResultados([]);
                }
            } finally {
                setCargando(false);
            }
        }, 220);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [busqueda, teamSlug]);

    const elegir = async (cliente: ClientOption) => {
        setBusqueda('');
        setResultados(null);
        setCargandoFicha(true);
        onChange({ ...cliente, vehiculos: [], sedes: [] });

        try {
            const response = await fetch(
                clientes.ficha.url({
                    current_team: teamSlug,
                    client: cliente.id,
                }),
            );
            onChange((await response.json()) as ClientFicha);
        } finally {
            setCargandoFicha(false);
        }
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
            void elegir(resultados[activo]);
        } else if (event.key === 'Escape') {
            setResultados(null);
        }
    };

    const noEncontrado = resultados !== null && resultados.length === 0;
    const esDocumento = /^\d{8}$|^\d{11}$/.test(busqueda.trim());
    const tieneRuc = value?.tipo_documento === 'ruc';
    const rucHabido =
        value?.estado_contribuyente === 'ACTIVO' &&
        value?.condicion_domicilio === 'HABIDO';

    return (
        <div>
            <div className="flex items-center justify-between gap-2">
                <span className="text-foreground/80 text-[11px] font-bold uppercase">
                    Cliente
                </span>
                {headerExtra}
            </div>

            {value ? (
                <div className="border-primary/40 bg-destructive/5 mt-1 rounded-[10px] border p-3">
                    <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0">
                            <div className="text-foreground text-[14px] font-bold">
                                {value.razon_social}
                            </div>
                            <div className="mt-0.5 flex flex-wrap items-center gap-2 text-[11.5px]">
                                <span className="bg-muted text-foreground/80 rounded-[5px] px-1.5 py-0.5 font-['IBM_Plex_Mono',monospace] font-bold uppercase">
                                    {value.tipo_documento === 'varios'
                                        ? 'Sin doc.'
                                        : value.tipo_documento}{' '}
                                    {value.numero_documento}
                                </span>
                                {tieneRuc && value.estado_contribuyente ? (
                                    <span
                                        className={`rounded-full px-2 py-0.5 text-[10.5px] font-bold ${rucHabido ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-destructive/10 text-destructive'}`}
                                    >
                                        {value.estado_contribuyente} ·{' '}
                                        {value.condicion_domicilio}
                                    </span>
                                ) : null}
                                {cargandoFicha ? (
                                    <Cargando className="text-muted-foreground size-3.5" />
                                ) : null}
                            </div>
                            {value.direccion_fiscal ? (
                                <div className="text-muted-foreground mt-1 text-[12px]">
                                    {value.direccion_fiscal}
                                </div>
                            ) : null}
                            {tieneRuc &&
                            value.estado_contribuyente &&
                            !rucHabido ? (
                                <div className="text-destructive mt-1 text-[11.5px] font-semibold">
                                    RUC no Activo y Habido: no se le puede
                                    emitir factura.
                                </div>
                            ) : null}
                        </div>
                        <div className="flex shrink-0 gap-1.5">
                            {value.tipo_documento !== 'varios' ? (
                                <Button
                                    asChild
                                    variant="outline"
                                    size="sm"
                                    className="border-border bg-card h-8 rounded-[8px] shadow-none"
                                >
                                    <a
                                        href={clientes.show.url({
                                            current_team: teamSlug,
                                            client: value.id,
                                        })}
                                        target="_blank"
                                        rel="noreferrer"
                                        title="Ver ficha del cliente"
                                    >
                                        <ExternalLink className="size-3.5" />
                                        Ficha
                                    </a>
                                </Button>
                            ) : null}
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    onChange(null);
                                    window.setTimeout(
                                        () => inputRef.current?.focus(),
                                        0,
                                    );
                                }}
                                className="border-border bg-card text-destructive h-8 rounded-[8px] shadow-none"
                                title="Quitar cliente"
                            >
                                <X className="size-3.5" />
                                Quitar
                            </Button>
                        </div>
                    </div>
                </div>
            ) : (
                <div className="relative mt-1">
                    <div className="flex items-stretch gap-2">
                        <div className="border-border bg-muted/40 flex min-w-0 flex-1 items-center gap-2 rounded-[9px] border px-3">
                            <Search className="text-muted-foreground size-3.5 shrink-0" />
                            <input
                                ref={inputRef}
                                autoFocus={autoFocus}
                                value={busqueda}
                                onChange={(event) =>
                                    setBusqueda(event.target.value)
                                }
                                onKeyDown={alTeclear}
                                placeholder="Escribe nombre, razón social, RUC o DNI..."
                                className="placeholder:text-muted-foreground h-10 min-w-0 flex-1 bg-transparent text-[13px] outline-none"
                            />
                            {cargando ? (
                                <Cargando className="text-muted-foreground size-3.5" />
                            ) : null}
                        </div>
                        {noEncontrado ? (
                            <Button
                                type="button"
                                onClick={() => setCreando(true)}
                                className="bg-primary hover:bg-primary/90 h-auto shrink-0 rounded-[9px] px-3.5 text-[12.5px] font-bold text-white shadow-none"
                            >
                                <UserRoundPlus className="size-3.5" />
                                Agregar cliente
                            </Button>
                        ) : null}
                    </div>

                    {resultados && resultados.length > 0 ? (
                        <div className="border-border bg-card absolute inset-x-0 top-full z-30 mt-1 max-h-[260px] overflow-y-auto rounded-[10px] border shadow-lg">
                            {resultados.map((cliente, index) => (
                                <button
                                    key={cliente.id}
                                    type="button"
                                    onMouseEnter={() => setActivo(index)}
                                    onClick={() => void elegir(cliente)}
                                    className={`border-border flex w-full items-center justify-between gap-3 border-b px-3 py-2.5 text-left last:border-b-0 ${index === activo ? 'bg-muted/60' : 'bg-card'}`}
                                >
                                    <span className="text-foreground truncate text-[13px] font-bold">
                                        {cliente.razon_social}
                                    </span>
                                    <span className="text-muted-foreground shrink-0 font-['IBM_Plex_Mono',monospace] text-[11px]">
                                        {cliente.numero_documento}
                                    </span>
                                </button>
                            ))}
                        </div>
                    ) : null}

                    {noEncontrado ? (
                        <p className="text-muted-foreground mt-1 text-[11.5px]">
                            No está registrado.{' '}
                            {esDocumento
                                ? 'Agrégalo: se autocompletan sus datos desde RENIEC/SUNAT.'
                                : 'Usa "Agregar cliente" para registrarlo.'}
                        </p>
                    ) : null}
                </div>
            )}

            {error ? (
                <p className="text-destructive mt-1 text-[11px] font-semibold">
                    {error}
                </p>
            ) : null}

            <ClientCreateDialog
                open={creando}
                onOpenChange={setCreando}
                teamSlug={teamSlug}
                initialDocumento={esDocumento ? busqueda.trim() : ''}
                onCreated={(cliente) => void elegir(cliente)}
            />
        </div>
    );
}
