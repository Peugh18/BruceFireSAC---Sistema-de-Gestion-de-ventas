import { Link, router } from '@inertiajs/react';
import { Search, UserRoundPlus } from 'lucide-react';
import { FormEvent, useState } from 'react';

import clientes from '@/routes/vendedor/clientes';

type ClientePorRegistrar = {
    documento: string;
    nombre: string;
    ultima_compra: string;
    compras: number;
    monto_total: number | string;
};

export type PorRegistrarData = {
    total: number;
    filtros: { buscar: string };
    clientes: {
        data: ClientePorRegistrar[];
        links: { url: string | null; label: string; active: boolean }[];
        from: number | null;
        to: number | null;
        total: number;
    };
};

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

function soles(monto: number | string): string {
    return `S/ ${Number(monto).toLocaleString('es-PE', { maximumFractionDigits: 0 })}`;
}

/**
 * Clientes que compraban en el sistema anterior y aún no están registrados,
 * de la compra más reciente a la más antigua. "Registrar" abre el alta con su
 * RUC ya puesto.
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

    const buscarAhora = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(
            clientes.index.url(teamSlug, {
                query: { vista: 'por-registrar', buscar: buscar || undefined },
            }),
            {},
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <div className="flex flex-col gap-3">
            <div className="flex flex-wrap items-center gap-3">
                <p className="text-muted-foreground min-w-[16rem] flex-1 text-[12.5px]">
                    Clientes que compraban en el sistema anterior y todavía no
                    están registrados. Primero los que compraron más
                    recientemente.
                </p>
                <form
                    onSubmit={buscarAhora}
                    className="border-border bg-muted/40 focus-within:border-ring focus-within:ring-ring/50 flex h-10 w-full items-center gap-2 rounded-lg border px-3 focus-within:ring-[3px] sm:w-[260px]"
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

            <div className="border-border overflow-hidden rounded-2xl border">
                <div className="text-muted-foreground hidden grid-cols-[minmax(0,2.4fr)_1fr_0.8fr_1fr_120px] gap-3 px-4 py-2.5 text-xs font-bold tracking-[0.05em] uppercase md:grid">
                    <span>Cliente</span>
                    <span>Última compra</span>
                    <span>Compras</span>
                    <span>Monto comprado</span>
                    <span />
                </div>

                {datos.clientes.data.length === 0 ? (
                    <div className="border-border text-muted-foreground border-t px-4 py-10 text-center text-[13px]">
                        {datos.filtros.buscar
                            ? 'No hay clientes con esa búsqueda.'
                            : 'Todos los clientes del sistema anterior ya están registrados.'}
                    </div>
                ) : (
                    datos.clientes.data.map((cliente) => (
                        <div
                            key={cliente.documento}
                            className="border-border hover:bg-muted/40 grid grid-cols-1 items-center gap-3 border-t px-4 py-3 transition-colors md:grid-cols-[minmax(0,2.4fr)_1fr_0.8fr_1fr_120px]"
                        >
                            <div className="flex min-w-0 items-center gap-2.5">
                                <div
                                    aria-hidden="true"
                                    className="bg-muted text-foreground/80 flex size-[34px] shrink-0 items-center justify-center rounded-full text-xs font-bold"
                                >
                                    {iniciales(cliente.nombre)}
                                </div>
                                <div className="min-w-0">
                                    <div className="text-foreground truncate text-[13px] font-bold">
                                        {cliente.nombre}
                                    </div>
                                    <div className="text-muted-foreground font-['IBM_Plex_Mono',monospace] text-xs">
                                        {cliente.documento}
                                    </div>
                                </div>
                            </div>
                            <div className="text-muted-foreground text-[12.5px]">
                                {hace(cliente.ultima_compra)}
                            </div>
                            <div className="text-muted-foreground text-[12.5px] tabular-nums">
                                {cliente.compras}
                            </div>
                            <div className="text-foreground text-[12.5px] font-semibold tabular-nums">
                                {soles(cliente.monto_total)}
                            </div>
                            <button
                                type="button"
                                onClick={() => onRegistrar(cliente.documento)}
                                className="border-border bg-card text-foreground hover:bg-muted inline-flex min-h-9 items-center justify-center gap-1.5 rounded-lg border px-3 text-[12px] font-semibold"
                            >
                                <UserRoundPlus className="size-3.5" />
                                Registrar
                            </button>
                        </div>
                    ))
                )}
            </div>

            {datos.clientes.total > 0 ? (
                <div className="text-muted-foreground flex flex-col gap-3 text-xs sm:flex-row sm:items-center sm:justify-between">
                    <span>
                        Mostrando {datos.clientes.from ?? 0}-
                        {datos.clientes.to ?? 0} de{' '}
                        {datos.clientes.total.toLocaleString('es-PE')}
                    </span>
                    <div className="flex flex-wrap gap-1.5">
                        {datos.clientes.links.map((link, index) =>
                            link.url ? (
                                <Link
                                    key={`${link.label}-${index}`}
                                    href={link.url}
                                    preserveScroll
                                    preserveState
                                    className={`flex h-8 min-w-8 items-center justify-center rounded-md px-2 font-['IBM_Plex_Mono',monospace] text-xs ${
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
                                    className="text-muted-foreground flex h-8 min-w-8 items-center justify-center px-2 font-['IBM_Plex_Mono',monospace] text-xs"
                                >
                                    {link.label
                                        .replace('&laquo;', '<')
                                        .replace('&raquo;', '>')}
                                </span>
                            ),
                        )}
                    </div>
                </div>
            ) : null}
        </div>
    );
}
