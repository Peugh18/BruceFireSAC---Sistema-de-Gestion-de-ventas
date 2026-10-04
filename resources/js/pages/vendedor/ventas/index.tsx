import { Link, router, usePage } from '@inertiajs/react';
import {
    Banknote,
    CheckCircle2,
    Clock3,
    CreditCard,
    Eye,
    PencilLine,
    Plus,
    Search,
    ShoppingCart,
} from 'lucide-react';
import { FormEvent, useState } from 'react';

import { FilasCargando } from '@/components/cargando';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { useRecargando } from '@/hooks/use-recargando';
import VendedorLayout from '@/layouts/vendedor-layout';
import ventas from '@/routes/vendedor/ventas';
import type { Team } from '@/types';
import RangoFechas, { describirRango } from '@/components/rango-fechas';
import { fechaCorta } from '@/lib/utils';

type SaleRow = {
    id: number;
    numero_interno: string;
    numero_nota_venta?: string | null;
    cliente: string;
    fecha: string;
    comprobante_tipo: string;
    comprobante?: string | null;
    sunat_estado?: string | null;
    total: number | string;
    estado: string;
    editable: boolean;
};

/**
 * Estado del comprobante en SUNAT en palabras simples.
 */
function estadoSunat(estado: string): {
    texto: string;
    clase: string;
} {
    switch (estado) {
        case 'por_enviar':
            return {
                texto: 'Por enviar · editable',
                clase: 'text-warning-strong',
            };
        case 'aceptado':
        case 'observado':
            return {
                texto: 'Aceptado por SUNAT',
                clase: 'text-success-strong',
            };
        case 'rechazado':
        case 'excepcion':
            return {
                texto: 'Rechazado · corregir',
                clase: 'text-destructive-strong',
            };
        default:
            return {
                texto: estado.replace('_', ' '),
                clase: 'text-muted-foreground',
            };
    }
}

type PaginationLink = { url: string | null; label: string; active: boolean };

type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
    from?: number | null;
    to?: number | null;
    total?: number;
};

type Filters = {
    estado?: string;
    comprobante?: string;
    buscar?: string;
    desde: string;
    hasta: string;
};

type Props = {
    sales: Paginated<SaleRow>;
    filters: Filters;
    hoy: string;
    kpis: {
        total_vendido: number;
        ventas: number;
        contado: number;
        credito: number;
        por_enviar: number;
        borradores: number;
    };
};

const FILTERS = [
    { label: 'Todas', value: '' },
    { label: 'Borrador', value: 'borrador' },
    { label: 'Confirmadas', value: 'confirmada' },
    { label: 'Anuladas', value: 'anulada' },
];

function money(value: number | string) {
    return new Intl.NumberFormat('es-PE', {
        style: 'currency',
        currency: 'PEN',
    }).format(Number(value) || 0);
}

function cleanLabel(label: string) {
    return label.replace('&laquo;', '<').replace('&raquo;', '>');
}

function statusBadge(estado: string) {
    const normalized = estado?.toLowerCase();

    if (normalized === 'confirmada' || normalized === 'emitida') {
        return 'bg-emerald-500/10 text-success-strong border border-emerald-500/20 border-transparent';
    }

    if (normalized === 'borrador') {
        return 'bg-amber-500/10 text-warning-strong border border-amber-500/20 border-transparent';
    }

    if (normalized === 'anulada' || normalized === 'rechazada') {
        return 'bg-destructive/10 text-destructive-strong border border-destructive/20 border-transparent';
    }

    return 'bg-muted text-muted-foreground border-transparent';
}

export default function VentasIndex({ sales, filters, hoy, kpis }: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug =
        currentTeam?.slug ??
        (typeof window !== 'undefined'
            ? window.location.pathname.split('/')[1]
            : '');
    const recargando = useRecargando();
    const currentFilter = filters.estado ?? '';

    const currentComprobante = filters.comprobante ?? '';

    const [buscar, setBuscar] = useState(filters.buscar ?? '');

    const filtrar = (cambios: Partial<Filters>) => {
        const query = {
            estado: currentFilter,
            comprobante: currentComprobante,
            buscar: filters.buscar ?? '',
            desde: filters.desde,
            hasta: filters.hasta,
            ...cambios,
        };

        router.get(
            ventas.index.url(teamSlug, {
                query: {
                    estado: query.estado || undefined,
                    comprobante: query.comprobante || undefined,
                    buscar: query.buscar || undefined,
                    desde: query.desde,
                    hasta: query.hasta,
                },
            }),
            {},
            { preserveScroll: true, preserveState: true },
        );
    };

    const changeFilter = (estado: string, comprobante = currentComprobante) =>
        filtrar({ estado, comprobante });

    const buscarVentas = (event: FormEvent) => {
        event.preventDefault();
        filtrar({ buscar: buscar.trim() });
    };

    const esHoy = filters.desde === hoy && filters.hasta === hoy;
    const periodo = describirRango(filters, hoy);

    const kpiItems = [
        {
            label: esHoy ? 'Vendido hoy' : 'Vendido en el periodo',
            value: money(kpis.total_vendido),
            detalle: `${kpis.ventas} venta(s) emitida(s)`,
            icon: Banknote,
            bg: 'bg-emerald-500/10',
            text: 'text-success-strong',
        },
        {
            label: 'Al contado',
            value: money(kpis.contado),
            detalle: 'Cobrado en caja',
            icon: ShoppingCart,
            bg: 'bg-blue-500/10',
            text: 'text-blue-600 dark:text-blue-400',
        },
        {
            label: 'A crédito',
            value: money(kpis.credito),
            detalle: 'Se cobra por cuotas',
            icon: CreditCard,
            bg: 'bg-muted',
            text: 'text-foreground/80',
        },
        {
            label: 'Por enviar a SUNAT',
            value: kpis.por_enviar,
            detalle:
                kpis.borradores > 0
                    ? `${kpis.borradores} borrador(es) sin emitir`
                    : 'Se envían solos',
            icon: Clock3,
            bg: 'bg-amber-500/10',
            text: 'text-warning-strong',
        },
    ];

    return (
        <VendedorLayout title="Ventas">
            <div className="flex flex-col gap-4">
                <PageHeader
                    title="Ventas"
                    description="Tus ventas con su comprobante. Se editan con el lápiz mientras SUNAT no las haya aceptado."
                    actions={
                        <Button
                            asChild
                            className="bg-primary hover:bg-primary/90 h-10 rounded-[9px] px-4 text-[13px] font-bold text-white shadow-none"
                        >
                            <Link href={ventas.create(teamSlug)}>
                                <Plus className="size-3.5" />
                                Nueva venta
                            </Link>
                        </Button>
                    }
                />
                <div className="bf-summary border-border bg-card grid grid-cols-2 gap-0 overflow-hidden rounded-xl border lg:grid-cols-4">
                    {kpiItems.map((item) => {
                        const Icon = item.icon;

                        return (
                            <Card
                                key={item.label}
                                className="border-border bg-card flex-row items-center gap-3.5 rounded-none border-0 border-r px-[18px] py-5 shadow-none last:border-r-0"
                            >
                                <div
                                    className={`flex size-[42px] shrink-0 items-center justify-center rounded-[11px] ${item.bg}`}
                                >
                                    <Icon
                                        className={`size-5 ${item.text}`}
                                        strokeWidth={2}
                                    />
                                </div>
                                <div className="min-w-0">
                                    <div className="text-muted-foreground text-xs font-medium">
                                        {item.label}
                                    </div>
                                    <div className="text-foreground text-[26px] leading-tight font-semibold tracking-tight tabular-nums">
                                        {item.value}
                                    </div>
                                    <div className="text-muted-foreground text-[11px]">
                                        {item.detalle}
                                    </div>
                                </div>
                            </Card>
                        );
                    })}
                </div>

                <Card className="border-border bg-card gap-0 rounded-[16px] p-5 shadow-none">
                    <form
                        onSubmit={buscarVentas}
                        className="border-border mb-4 flex flex-col gap-3 border-b pb-4 lg:flex-row lg:items-end"
                    >
                        <RangoFechas
                            rango={filters}
                            hoy={hoy}
                            onCambiar={filtrar}
                        />
                        <div className="flex flex-1 items-end gap-2">
                            <div className="border-border bg-muted/40 focus-within:border-ring flex h-9 min-w-0 flex-1 items-center gap-2 rounded-[9px] border px-3">
                                <Search className="text-muted-foreground size-3.5 shrink-0" />
                                <input
                                    value={buscar}
                                    onChange={(event) =>
                                        setBuscar(event.target.value)
                                    }
                                    placeholder="Cliente, RUC/DNI, N° de venta o comprobante..."
                                    className="placeholder:text-muted-foreground h-full min-w-0 flex-1 bg-transparent text-[13px] outline-none"
                                />
                            </div>
                            <Button
                                type="submit"
                                className="bg-foreground text-background hover:bg-foreground/90 h-9 rounded-[9px] px-4 text-[12.5px] font-bold shadow-none"
                            >
                                Buscar
                            </Button>
                        </div>
                    </form>
                    <p className="text-muted-foreground -mt-2 mb-3 text-[12px]">
                        Ventas de {periodo}
                        {filters.buscar
                            ? ` que coinciden con «${filters.buscar}»`
                            : ''}
                        .
                    </p>
                    <div className="mb-4 flex flex-wrap items-center gap-2.5">
                        <div className="bg-muted flex max-w-full overflow-x-auto rounded-[9px] p-[3px]">
                            {FILTERS.map((filter) => {
                                const active =
                                    filter.value === ''
                                        ? !currentFilter ||
                                          currentFilter === 'todas'
                                        : currentFilter === filter.value;

                                return (
                                    <button
                                        key={filter.label}
                                        type="button"
                                        onClick={() =>
                                            changeFilter(filter.value)
                                        }
                                        className={`rounded-[7px] px-3.5 py-1.5 text-xs font-bold whitespace-nowrap transition-all ${
                                            active
                                                ? 'bg-card text-foreground shadow-[0_1px_2px_rgba(0,0,0,0.06)]'
                                                : 'text-muted-foreground hover:text-foreground'
                                        }`}
                                    >
                                        {filter.label}
                                    </button>
                                );
                            })}
                        </div>
                        <div className="bg-muted flex max-w-full overflow-x-auto rounded-[9px] p-[3px]">
                            {[
                                { label: 'Todos', value: '' },
                                { label: 'Con comprobante', value: 'sunat' },
                                {
                                    label: 'Notas de venta',
                                    value: 'nota_venta',
                                },
                            ].map((option) => (
                                <button
                                    key={option.label}
                                    type="button"
                                    aria-pressed={
                                        currentComprobante === option.value
                                    }
                                    onClick={() =>
                                        changeFilter(
                                            currentFilter,
                                            option.value,
                                        )
                                    }
                                    className={`rounded-[7px] px-3.5 py-1.5 text-xs font-bold whitespace-nowrap transition-all ${
                                        currentComprobante === option.value
                                            ? 'bg-card text-foreground shadow-[0_1px_2px_rgba(0,0,0,0.06)]'
                                            : 'text-muted-foreground hover:text-foreground'
                                    }`}
                                >
                                    {option.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-[12.5px]">
                            <caption className="sr-only">
                                Ventas y estado de sus comprobantes
                            </caption>
                            <thead>
                                <tr>
                                    {[
                                        'Venta',
                                        'Cliente',
                                        'Fecha',
                                        'Comprobante',
                                        'Total',
                                        'Estado',
                                        'Acciones',
                                    ].map((column) => (
                                        <th
                                            key={column}
                                            scope="col"
                                            className="border-border text-muted-foreground border-b px-2.5 py-2.5 text-left text-[11px] font-medium whitespace-nowrap"
                                        >
                                            {column}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {recargando ? (
                                    <FilasCargando
                                        columnas={7}
                                        filas={sales.data.length}
                                    />
                                ) : sales.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="text-muted-foreground px-4 py-12 text-center"
                                        >
                                            <div className="flex flex-col items-center justify-center gap-2.5">
                                                <div className="bg-muted/60 text-muted-foreground flex size-12 items-center justify-center rounded-2xl">
                                                    <ShoppingCart className="size-6 opacity-75" />
                                                </div>
                                                <p className="text-foreground text-sm font-semibold">
                                                    {esHoy && !filters.buscar
                                                        ? 'Todavía no hay ventas hoy'
                                                        : 'No se encontraron ventas'}
                                                </p>
                                                <p className="text-muted-foreground max-w-sm text-xs">
                                                    {currentFilter &&
                                                    currentFilter !== 'todas'
                                                        ? 'No hay registros para el filtro seleccionado. Prueba limpiando los filtros.'
                                                        : 'Aún no tienes ventas registradas en este período. Puedes emitir una nueva factura o boleta de inmediato.'}
                                                </p>
                                                {currentFilter &&
                                                currentFilter !== 'todas' ? (
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            router.get(
                                                                ventas.index(
                                                                    teamSlug,
                                                                ),
                                                            );
                                                        }}
                                                        className="text-primary mt-1 cursor-pointer text-xs font-semibold hover:underline"
                                                    >
                                                        Ver todas las ventas
                                                    </button>
                                                ) : (
                                                    <Button
                                                        asChild
                                                        size="sm"
                                                        className="mt-2 rounded-xl"
                                                    >
                                                        <Link
                                                            href={ventas.create(
                                                                teamSlug,
                                                            )}
                                                        >
                                                            <Plus className="size-3.5" />
                                                            Emitir primera venta
                                                        </Link>
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    sales.data.map((sale) => (
                                        <tr
                                            key={sale.id}
                                            className="hover:bg-muted/40"
                                        >
                                            <td className="border-border text-foreground border-b px-2.5 py-[13px] font-['IBM_Plex_Mono',monospace] font-bold whitespace-nowrap tabular-nums">
                                                {sale.numero_interno}
                                            </td>
                                            <td className="border-border text-foreground border-b px-2.5 py-[13px] font-semibold">
                                                {sale.cliente}
                                            </td>
                                            <td className="border-border text-foreground/80 border-b px-2.5 py-[13px]">
                                                {fechaCorta(sale.fecha)}
                                            </td>
                                            <td className="border-border border-b px-2.5 py-[13px]">
                                                <span className="bg-muted text-foreground/80 rounded-[5px] px-2 py-1 font-['IBM_Plex_Mono',monospace] text-[10px] font-bold uppercase">
                                                    {sale.comprobante ??
                                                        sale.comprobante_tipo.replace(
                                                            '_',
                                                            ' ',
                                                        )}
                                                </span>
                                                {sale.sunat_estado ? (
                                                    <div
                                                        className={`mt-1 text-[10.5px] font-bold ${estadoSunat(sale.sunat_estado).clase}`}
                                                    >
                                                        {
                                                            estadoSunat(
                                                                sale.sunat_estado,
                                                            ).texto
                                                        }
                                                    </div>
                                                ) : null}
                                            </td>
                                            <td className="border-border text-foreground border-b px-2.5 py-[13px] font-['IBM_Plex_Mono',monospace] font-bold whitespace-nowrap tabular-nums">
                                                {money(sale.total)}
                                            </td>
                                            <td className="border-border border-b px-2.5 py-[13px]">
                                                <Badge
                                                    className={`rounded-full px-2.5 py-1 text-[10.5px] font-bold capitalize shadow-none ${statusBadge(sale.estado)}`}
                                                >
                                                    <CheckCircle2 className="size-3" />
                                                    {sale.estado}
                                                </Badge>
                                            </td>
                                            <td className="border-border border-b px-2.5 py-[13px]">
                                                <div className="flex gap-1.5">
                                                    <Button
                                                        asChild
                                                        variant="outline"
                                                        size="icon"
                                                        title="Ver venta"
                                                        className="border-border bg-card text-foreground/80 size-7 rounded-[7px] shadow-none"
                                                    >
                                                        <Link
                                                            href={ventas.show({
                                                                current_team:
                                                                    teamSlug,
                                                                sale: sale.id,
                                                            })}
                                                        >
                                                            <Eye className="size-3.5" />
                                                        </Link>
                                                    </Button>
                                                    {sale.editable ? (
                                                        <Button
                                                            asChild
                                                            variant="outline"
                                                            size="icon"
                                                            title="Editar"
                                                            className="border-border bg-card text-foreground/80 size-7 rounded-[7px] shadow-none"
                                                        >
                                                            <Link
                                                                href={ventas.edit.url(
                                                                    {
                                                                        current_team:
                                                                            teamSlug,
                                                                        sale: sale.id,
                                                                    },
                                                                )}
                                                            >
                                                                <PencilLine className="size-3.5" />
                                                            </Link>
                                                        </Button>
                                                    ) : sale.estado ===
                                                          'confirmada' &&
                                                      sale.sunat_estado ? (
                                                        <Button
                                                            asChild
                                                            variant="outline"
                                                            size="icon"
                                                            title="Corregir con nota de crédito"
                                                            className="border-border bg-card text-foreground/80 size-7 rounded-[7px] shadow-none"
                                                        >
                                                            <Link
                                                                href={ventas.show.url(
                                                                    {
                                                                        current_team:
                                                                            teamSlug,
                                                                        sale: sale.id,
                                                                    },
                                                                    {
                                                                        query: {
                                                                            corregir: 1,
                                                                        },
                                                                    },
                                                                )}
                                                            >
                                                                <PencilLine className="size-3.5" />
                                                            </Link>
                                                        </Button>
                                                    ) : null}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="text-muted-foreground mt-3.5 flex flex-col gap-3 text-[11.5px] sm:flex-row sm:items-center sm:justify-between">
                        <span>
                            Mostrando {sales.from ?? 0}-{sales.to ?? 0} de{' '}
                            {sales.total ?? sales.data.length} ventas
                        </span>
                        <div className="flex flex-wrap gap-1.5">
                            {sales.links.map((link, index) =>
                                link.url ? (
                                    <Link
                                        key={`${link.label}-${index}`}
                                        href={link.url}
                                        preserveScroll
                                        preserveState
                                        className={`flex h-[26px] min-w-[26px] items-center justify-center rounded-[7px] px-2 font-['IBM_Plex_Mono',monospace] text-[11.5px] no-underline ${link.active ? 'bg-primary font-bold text-white' : 'text-muted-foreground hover:bg-muted hover:text-foreground'}`}
                                    >
                                        {cleanLabel(link.label)}
                                    </Link>
                                ) : (
                                    <span
                                        key={`${link.label}-${index}`}
                                        className="text-muted-foreground flex h-[26px] min-w-[26px] items-center justify-center rounded-[7px] px-2 font-['IBM_Plex_Mono',monospace] text-[11.5px]"
                                    >
                                        {cleanLabel(link.label)}
                                    </span>
                                ),
                            )}
                        </div>
                    </div>
                </Card>
            </div>
        </VendedorLayout>
    );
}
