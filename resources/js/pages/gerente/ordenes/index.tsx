import { Head, Link, router, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState, type FormEvent } from 'react';

import { pageTitleClass } from '@/components/page-header';
import GerenteLayout from '@/layouts/gerente-layout';
import { etiquetaEstadoOrden } from '@/lib/estados-orden';
import OrdenesRoutes from '@/routes/gerente/ordenes';
import type { Team } from '@/types';

type Fila = {
    id: number;
    codigo: string;
    cliente: string | null;
    lugar: string | null;
    extintores: number;
    fotos_entrada: number;
    fotos_salida: number;
    estado: string;
    precio_distinto: boolean;
};

type Props = {
    ordenes: {
        data: Fila[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filtros: { filtro: string; buscar: string };
    conPrecioDistinto: number;
};

const FILTROS = [
    ['todas', 'Todas'],
    ['precio_distinto', 'Precio de campo distinto'],
    ['recojos', 'Recojos'],
    ['planta', 'En planta'],
    ['entregas', 'Entregas'],
    ['cerradas', 'Cerradas'],
] as const;

const fotos = (n: number) =>
    n === 0 ? '—' : `${n} ${n === 1 ? 'foto' : 'fotos'}`;

/** Órdenes del gerente: todas, con su entrada y salida y el precio por aprobar. */
export default function OrdenesGerente({
    ordenes,
    filtros,
    conPrecioDistinto,
}: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const equipo = currentTeam?.slug ?? '';
    const [buscar, setBuscar] = useState(filtros.buscar);
    const ir = (cambios: Partial<Props['filtros']>) =>
        router.get(
            OrdenesRoutes.index.url(equipo),
            { ...filtros, ...cambios },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    const enviar = (e: FormEvent) => {
        e.preventDefault();
        ir({ buscar });
    };

    return (
        <GerenteLayout title="Órdenes">
            <Head title="Órdenes" />
            <div className="flex flex-col gap-4">
                <div>
                    <p className="text-primary-strong text-xs font-bold tracking-wider uppercase">
                        Operación
                    </p>
                    <h1 className={pageTitleClass}>Órdenes</h1>
                </div>

                <div className="flex flex-wrap gap-2">
                    <form
                        onSubmit={enviar}
                        className="relative min-w-60 flex-1"
                    >
                        <label htmlFor="buscar-orden" className="sr-only">
                            Buscar orden
                        </label>
                        <Search
                            className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2"
                            aria-hidden="true"
                        />
                        <input
                            id="buscar-orden"
                            value={buscar}
                            onChange={(e) => setBuscar(e.target.value)}
                            placeholder="Orden, cliente o RUC"
                            className="border-border bg-card min-h-11 w-full rounded-xl border pr-3 pl-9 text-sm"
                        />
                    </form>
                    <div
                        role="group"
                        aria-label="Filtrar órdenes"
                        className="flex flex-wrap gap-1.5"
                    >
                        {FILTROS.map(([valor, nombre]) => {
                            const activo = filtros.filtro === valor;

                            return (
                                <button
                                    key={valor}
                                    type="button"
                                    aria-pressed={activo}
                                    onClick={() => ir({ filtro: valor })}
                                    className={`focus-visible:ring-ring min-h-11 rounded-xl px-3.5 text-sm font-semibold focus-visible:ring-2 focus-visible:outline-none ${activo ? 'bg-foreground text-background' : 'border-border bg-card border'}`}
                                >
                                    {nombre}
                                    {valor === 'precio_distinto' &&
                                    conPrecioDistinto > 0 ? (
                                        <span className="ml-1.5 rounded-full bg-amber-200 px-1.5 text-xs text-amber-900">
                                            {conPrecioDistinto}
                                        </span>
                                    ) : null}
                                </button>
                            );
                        })}
                    </div>
                </div>

                <div className="border-border bg-card overflow-x-auto rounded-3xl border">
                    <table className="w-full min-w-[760px] text-sm">
                        <thead>
                            <tr className="text-muted-foreground border-border border-b text-left text-xs">
                                <th
                                    scope="col"
                                    className="px-4 py-3.5 font-semibold"
                                >
                                    Orden
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3.5 font-semibold"
                                >
                                    Cliente · lugar
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3.5 font-semibold"
                                >
                                    Extintores
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3.5 font-semibold"
                                >
                                    Entrada
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3.5 font-semibold"
                                >
                                    Salida
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3.5 font-semibold"
                                >
                                    Estado
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-border divide-y">
                            {ordenes.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="text-muted-foreground px-4 py-10 text-center"
                                    >
                                        No hay órdenes con este filtro.
                                    </td>
                                </tr>
                            ) : (
                                ordenes.data.map((o) => (
                                    <tr
                                        key={o.id}
                                        className="hover:bg-muted/40"
                                    >
                                        <td className="px-4 py-3.5">
                                            <Link
                                                href={OrdenesRoutes.show.url({
                                                    current_team: equipo,
                                                    service_order: o.id,
                                                })}
                                                className="text-primary-strong font-mono font-bold hover:underline"
                                            >
                                                {o.codigo}
                                            </Link>
                                        </td>
                                        <td className="px-4 py-3.5">
                                            <span className="block font-semibold">
                                                {o.cliente ?? '—'}
                                            </span>
                                            {o.lugar ? (
                                                <span className="text-muted-foreground block text-xs">
                                                    {o.lugar}
                                                </span>
                                            ) : null}
                                        </td>
                                        <td className="px-4 py-3.5 tabular-nums">
                                            {o.extintores}
                                        </td>
                                        <td className="px-4 py-3.5">
                                            {fotos(o.fotos_entrada)}
                                        </td>
                                        <td className="px-4 py-3.5">
                                            {fotos(o.fotos_salida)}
                                        </td>
                                        <td className="px-4 py-3.5">
                                            <span className="flex flex-wrap gap-1.5">
                                                <span className="bg-muted rounded-full px-2.5 py-0.5 text-xs font-bold">
                                                    {etiquetaEstadoOrden(
                                                        o.estado,
                                                    )}
                                                </span>
                                                {o.precio_distinto ? (
                                                    <span className="rounded-full bg-amber-200 px-2.5 py-0.5 text-xs font-bold text-amber-900">
                                                        Precio distinto
                                                    </span>
                                                ) : null}
                                            </span>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {ordenes.links.length > 3 ? (
                    <nav
                        aria-label="Páginas"
                        className="flex flex-wrap gap-1.5"
                    >
                        {ordenes.links.map((enlace, i) =>
                            enlace.url ? (
                                <Link
                                    key={i}
                                    href={enlace.url}
                                    preserveScroll
                                    aria-current={
                                        enlace.active ? 'page' : undefined
                                    }
                                    className={`min-h-10 min-w-10 rounded-xl px-3 py-2 text-center text-sm ${enlace.active ? 'bg-foreground text-background font-bold' : 'border-border bg-card border'}`}
                                >
                                    {enlace.label
                                        .replace('&laquo;', '«')
                                        .replace('&raquo;', '»')}
                                </Link>
                            ) : null,
                        )}
                    </nav>
                ) : null}
            </div>
        </GerenteLayout>
    );
}
