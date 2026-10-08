import { Link, router } from '@inertiajs/react';
import { ChevronDown, Lightbulb, Search, UserRoundPlus } from 'lucide-react';
import { FormEvent, useState } from 'react';

import type { Recompra } from '@/components/recompra-badge';
import clientes from '@/routes/vendedor/clientes';

export type ClientePorRegistrar = Recompra & {
    documento: string;
    nombre: string;
    ultima_compra: string;
    compras: number;
    monto_total: number;
};

type Nivel = '' | 'alta' | 'media' | 'baja';

export type PorRegistrarData = {
    conteo: { todas: number; alta: number; media: number; baja: number };
    filtros: { nivel: Nivel; buscar: string };
    clientes: {
        data: ClientePorRegistrar[];
        links: { url: string | null; label: string; active: boolean }[];
        from: number | null;
        to: number | null;
        total: number;
    };
};

const colores: Record<Recompra['categoria'], { pill: string; barra: string }> =
    {
        alta: {
            pill: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
            barra: 'bg-emerald-500',
        },
        media: {
            pill: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
            barra: 'bg-amber-500',
        },
        baja: {
            pill: 'bg-muted text-muted-foreground',
            barra: 'bg-muted-foreground/40',
        },
    };

const etiquetaNivel = { alta: 'Alta', media: 'Media', baja: 'Baja' } as const;

function iniciales(nombre: string): string {
    return nombre
        .split(/\s+/u)
        .filter(Boolean)
        .slice(0, 2)
        .map((parte) => parte[0]?.toUpperCase() ?? '')
        .join('');
}

/**
 * "hace 16 días", "hace 4 meses", "hace 1 año".
 */
function hace(fecha: string): string {
    const dias = Math.max(
        0,
        Math.round(
            (Date.now() - new Date(`${fecha}T00:00:00`).getTime()) / 86_400_000,
        ),
    );

    if (dias === 0) return 'hoy';
    if (dias < 60) return `hace ${dias} ${dias === 1 ? 'día' : 'días'}`;
    if (dias < 365) return `hace ${Math.round(dias / 30)} meses`;
    const anios = Math.floor(dias / 365);
    return `hace ${anios} ${anios === 1 ? 'año' : 'años'}`;
}

function soles(monto: number): string {
    return `S/ ${monto.toLocaleString('es-PE', { maximumFractionDigits: 0 })}`;
}

/**
 * Clientes que compraban en el sistema anterior y aún no están registrados,
 * de mayor a menor probabilidad de volver a comprar. Al tocar una fila se ve
 * el porqué; "Registrar" abre el alta con su RUC ya puesto.
 */
export default function ClientesPorRegistrar({
    teamSlug,
    datos,
    onRegistrar,
}: {
    teamSlug: string;
    datos: PorRegistrarData;
    onRegistrar: (documento: string) => void;
}) {
    const [buscar, setBuscar] = useState(datos.filtros.buscar);
    const [abierta, setAbierta] = useState<string | null>(
        datos.clientes.data[0]?.documento ?? null,
    );

    const filtrar = (cambios: { nivel?: Nivel; buscar?: string }) =>
        router.get(
            clientes.index.url(teamSlug, {
                query: {
                    vista: 'por-registrar',
                    nivel: (cambios.nivel ?? datos.filtros.nivel) || undefined,
                    buscar: (cambios.buscar ?? buscar) || undefined,
                },
            }),
            {},
            { preserveScroll: true, preserveState: true },
        );

    const buscarAhora = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        filtrar({ buscar });
    };

    const chips: { nivel: Nivel; etiqueta: string; cantidad: number }[] = [
        { nivel: '', etiqueta: 'Todas', cantidad: datos.conteo.todas },
        { nivel: 'alta', etiqueta: 'Alta', cantidad: datos.conteo.alta },
        { nivel: 'media', etiqueta: 'Media', cantidad: datos.conteo.media },
        { nivel: 'baja', etiqueta: 'Baja', cantidad: datos.conteo.baja },
    ];

    return (
        <div className="flex flex-col gap-3">
            <p className="text-muted-foreground text-[12.5px]">
                Clientes que compraban en el sistema anterior y todavía no están
                registrados, de mayor a menor probabilidad de volver a comprar
                en los próximos 6 meses.
            </p>

            <div className="flex flex-wrap items-center gap-2">
                {chips.map((chip) => {
                    const activo = datos.filtros.nivel === chip.nivel;
                    return (
                        <button
                            key={chip.etiqueta}
                            type="button"
                            onClick={() => filtrar({ nivel: chip.nivel })}
                            className={`rounded-full border px-3 py-1 text-[12px] font-semibold transition-colors ${
                                activo
                                    ? 'border-primary/30 bg-primary/10 text-primary-strong'
                                    : 'border-border text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            {chip.etiqueta}{' '}
                            <span className="font-normal opacity-80">
                                {chip.cantidad.toLocaleString('es-PE')}
                            </span>
                        </button>
                    );
                })}
                <form
                    onSubmit={buscarAhora}
                    className="border-border bg-muted/40 focus-within:border-ring focus-within:ring-ring/50 ml-auto flex h-9 w-full items-center gap-2 rounded-[9px] border px-3 focus-within:ring-[3px] sm:w-[240px]"
                >
                    <Search className="text-muted-foreground size-3.5 shrink-0" />
                    <input
                        aria-label="Buscar clientes por registrar"
                        value={buscar}
                        onChange={(event) => setBuscar(event.target.value)}
                        placeholder="Buscar nombre o RUC"
                        className="text-foreground placeholder:text-muted-foreground min-w-0 flex-1 bg-transparent text-[12.5px] outline-none"
                    />
                </form>
            </div>

            <div className="border-border overflow-hidden rounded-[12px] border">
                <div className="text-muted-foreground hidden grid-cols-[minmax(0,2.2fr)_1.3fr_1fr_1fr_120px] gap-3 px-4 py-2.5 font-['IBM_Plex_Mono',monospace] text-[9.5px] font-bold tracking-[0.05em] uppercase md:grid">
                    <span>Cliente</span>
                    <span>Probabilidad de volver</span>
                    <span>Última compra</span>
                    <span>Compras</span>
                    <span />
                </div>

                {datos.clientes.data.length === 0 ? (
                    <div className="border-border text-muted-foreground border-t px-4 py-10 text-center text-[13px]">
                        {datos.filtros.buscar || datos.filtros.nivel
                            ? 'No hay clientes con ese filtro.'
                            : 'Todos los clientes del sistema anterior ya están registrados.'}
                    </div>
                ) : (
                    datos.clientes.data.map((cliente) => {
                        const estaAbierta = abierta === cliente.documento;
                        const color = colores[cliente.categoria];

                        return (
                            <div
                                key={cliente.documento}
                                className="border-border hover:bg-muted/40 border-t transition-colors"
                            >
                                <div
                                    role="button"
                                    tabIndex={0}
                                    onClick={() =>
                                        setAbierta(
                                            estaAbierta
                                                ? null
                                                : cliente.documento,
                                        )
                                    }
                                    onKeyDown={(event) =>
                                        event.key === 'Enter' &&
                                        setAbierta(
                                            estaAbierta
                                                ? null
                                                : cliente.documento,
                                        )
                                    }
                                    className="grid cursor-pointer grid-cols-1 items-center gap-3 px-4 py-3 md:grid-cols-[minmax(0,2.2fr)_1.3fr_1fr_1fr_120px]"
                                >
                                    <div className="flex min-w-0 items-center gap-2.5">
                                        <div className="bg-muted text-foreground/80 flex size-[34px] shrink-0 items-center justify-center rounded-full text-[11px] font-bold">
                                            {iniciales(cliente.nombre)}
                                        </div>
                                        <div className="min-w-0">
                                            <div className="text-foreground truncate text-[13px] font-bold">
                                                {cliente.nombre}
                                            </div>
                                            <div className="text-muted-foreground font-['IBM_Plex_Mono',monospace] text-[11px]">
                                                {cliente.documento}
                                            </div>
                                        </div>
                                        <ChevronDown
                                            className={`text-muted-foreground ml-auto size-4 shrink-0 transition-transform md:hidden ${estaAbierta ? 'rotate-180' : ''}`}
                                        />
                                    </div>
                                    <div>
                                        <span
                                            className={`inline-block rounded-full px-2.5 py-0.5 text-[11px] font-bold ${color.pill}`}
                                        >
                                            {etiquetaNivel[cliente.categoria]} ·{' '}
                                            {cliente.porcentaje}%
                                        </span>
                                        <div className="bg-muted mt-1.5 h-1 max-w-[140px] overflow-hidden rounded-full">
                                            <div
                                                className={`h-1 rounded-full ${color.barra}`}
                                                style={{
                                                    width: `${cliente.porcentaje}%`,
                                                }}
                                            />
                                        </div>
                                    </div>
                                    <div className="text-muted-foreground text-[12.5px]">
                                        {hace(cliente.ultima_compra)}
                                    </div>
                                    <div className="text-muted-foreground text-[12.5px]">
                                        {cliente.compras} ·{' '}
                                        {soles(cliente.monto_total)}
                                    </div>
                                    <button
                                        type="button"
                                        onClick={(event) => {
                                            event.stopPropagation();
                                            onRegistrar(cliente.documento);
                                        }}
                                        className="border-border bg-card text-foreground/80 hover:bg-muted inline-flex h-8 items-center justify-center gap-1.5 rounded-[8px] border px-3 text-[12px] font-semibold"
                                    >
                                        <UserRoundPlus className="size-3.5" />
                                        Registrar
                                    </button>
                                </div>
                                {estaAbierta && (
                                    <div className="text-muted-foreground flex items-start gap-2 px-4 pb-3 text-[12.5px] md:pl-[62px]">
                                        <Lightbulb className="mt-0.5 size-3.5 shrink-0 text-amber-500" />
                                        <span>{cliente.resumen}</span>
                                    </div>
                                )}
                            </div>
                        );
                    })
                )}
            </div>

            <div className="text-muted-foreground flex flex-col gap-3 text-[11.5px] sm:flex-row sm:items-center sm:justify-between">
                <span>
                    Mostrando {datos.clientes.from ?? 0}-
                    {datos.clientes.to ?? 0} de{' '}
                    {datos.clientes.total.toLocaleString('es-PE')} · toca una
                    fila para ver por qué
                </span>
                <div className="flex flex-wrap gap-1.5">
                    {datos.clientes.links.map((link, index) =>
                        link.url ? (
                            <Link
                                key={`${link.label}-${index}`}
                                href={link.url}
                                preserveScroll
                                preserveState
                                className={`flex h-[26px] min-w-[26px] items-center justify-center rounded-[7px] px-2 font-['IBM_Plex_Mono',monospace] text-[11.5px] ${
                                    link.active
                                        ? 'bg-primary font-bold text-white'
                                        : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                                }`}
                            >
                                {link.label
                                    .replace('&laquo;', '<')
                                    .replace('&raquo;', '>')}
                            </Link>
                        ) : (
                            <span
                                key={`${link.label}-${index}`}
                                className="text-muted-foreground flex h-[26px] min-w-[26px] items-center justify-center px-2 font-['IBM_Plex_Mono',monospace] text-[11.5px]"
                            >
                                {link.label
                                    .replace('&laquo;', '<')
                                    .replace('&raquo;', '>')}
                            </span>
                        ),
                    )}
                </div>
            </div>
        </div>
    );
}
