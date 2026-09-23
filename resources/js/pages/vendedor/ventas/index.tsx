import { Link, router, usePage } from '@inertiajs/react';
import {
    Banknote,
    CheckCircle2,
    Eye,
    FileText,
    Plus,
    ReceiptText,
    ShoppingCart,
} from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import VendedorLayout from '@/layouts/vendedor-layout';
import ventas from '@/routes/vendedor/ventas';
import type { Team } from '@/types';

type SaleRow = {
    id: number;
    numero_interno: string;
    numero_nota_venta?: string | null;
    cliente: string;
    fecha: string;
    comprobante_tipo: string;
    total: number | string;
    estado: string;
};

type PaginationLink = { url: string | null; label: string; active: boolean };

type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
    from?: number | null;
    to?: number | null;
    total?: number;
};

type Props = {
    sales: Paginated<SaleRow>;
    filters: { estado?: string; comprobante?: string };
    kpis: {
        ventas_del_mes: number | string;
        comprobantes: number;
        pendientes_confirmar: number;
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
        return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 border-transparent';
    }

    if (normalized === 'borrador') {
        return 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 border-transparent';
    }

    if (normalized === 'anulada' || normalized === 'rechazada') {
        return 'bg-destructive/10 text-destructive border border-destructive/20 border-transparent';
    }

    return 'bg-muted text-muted-foreground border-transparent';
}

export default function VentasIndex({ sales, filters, kpis }: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug =
        currentTeam?.slug ??
        (typeof window !== 'undefined'
            ? window.location.pathname.split('/')[1]
            : '');
    const currentFilter = filters.estado ?? '';

    const currentComprobante = filters.comprobante ?? '';

    const changeFilter = (estado: string, comprobante = currentComprobante) => {
        router.get(
            ventas.index.url(teamSlug, {
                query: {
                    estado: estado || undefined,
                    comprobante: comprobante || undefined,
                },
            }),
            {},
            { preserveScroll: true, preserveState: true },
        );
    };

    const kpiItems = [
        {
            label: 'Ventas del mes',
            value: money(kpis.ventas_del_mes),
            icon: Banknote,
            bg: 'bg-emerald-500/10',
            text: 'text-emerald-600 dark:text-emerald-400',
        },
        {
            label: 'Comprobantes',
            value: kpis.comprobantes,
            icon: ReceiptText,
            bg: 'bg-blue-500/10',
            text: 'text-blue-600 dark:text-blue-400',
        },
        {
            label: 'Pendientes',
            value: kpis.pendientes_confirmar,
            icon: FileText,
            bg: 'bg-amber-500/10',
            text: 'text-amber-600 dark:text-amber-400',
        },
        {
            label: 'Registros',
            value: sales.total ?? sales.data.length,
            icon: ShoppingCart,
            bg: 'bg-muted',
            text: 'text-foreground/80',
        },
    ];

    return (
        <VendedorLayout title="Ventas">
            <div className="flex flex-col gap-4">
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {kpiItems.map((item) => {
                        const Icon = item.icon;

                        return (
                            <Card
                                key={item.label}
                                className="border-border bg-card flex-row items-center gap-3.5 rounded-[14px] px-[18px] py-4 shadow-none"
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
                                    <div className="text-muted-foreground text-[11px] font-bold tracking-[0.03em] uppercase">
                                        {item.label}
                                    </div>
                                    <div className="text-foreground font-['Oswald',sans-serif] text-[21px] leading-tight font-semibold">
                                        {item.value}
                                    </div>
                                </div>
                            </Card>
                        );
                    })}
                </div>

                <Card className="border-border bg-card gap-0 rounded-[16px] p-5 shadow-none">
                    <div className="mb-4 flex flex-wrap items-center gap-2.5">
                        <div className="bg-muted flex rounded-[9px] p-[3px]">
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
                        <div className="bg-muted flex rounded-[9px] p-[3px]">
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
                        <div className="flex-1" />
                        <Button
                            asChild
                            className="bg-primary hover:bg-primary/90 h-10 rounded-[9px] px-4 text-[13px] font-bold text-white shadow-none"
                        >
                            <Link href={ventas.create(teamSlug)}>
                                <Plus className="size-3.5" />
                                Nueva venta
                            </Link>
                        </Button>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-[12.5px]">
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
                                            className="border-border text-muted-foreground border-b px-2.5 py-2.5 text-left font-['IBM_Plex_Mono',monospace] text-[9.5px] font-bold tracking-[0.05em] whitespace-nowrap uppercase"
                                        >
                                            {column}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {sales.data.map((sale) => (
                                    <tr
                                        key={sale.id}
                                        className="hover:bg-muted/40"
                                    >
                                        <td className="border-border text-foreground border-b px-2.5 py-[13px] font-['IBM_Plex_Mono',monospace] font-bold">
                                            {sale.numero_interno}
                                        </td>
                                        <td className="border-border text-foreground border-b px-2.5 py-[13px] font-semibold">
                                            {sale.cliente}
                                        </td>
                                        <td className="border-border text-foreground/80 border-b px-2.5 py-[13px]">
                                            {sale.fecha}
                                        </td>
                                        <td className="border-border border-b px-2.5 py-[13px]">
                                            <span className="bg-muted text-foreground/80 rounded-[5px] px-2 py-1 font-['IBM_Plex_Mono',monospace] text-[10px] font-bold uppercase">
                                                {sale.numero_nota_venta ??
                                                    sale.comprobante_tipo.replace(
                                                        '_',
                                                        ' ',
                                                    )}
                                            </span>
                                        </td>
                                        <td className="border-border text-foreground border-b px-2.5 py-[13px] font-['IBM_Plex_Mono',monospace] font-bold">
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
                                            <Button
                                                asChild
                                                variant="outline"
                                                size="icon"
                                                className="border-border bg-card text-foreground/80 size-7 rounded-[7px] shadow-none"
                                            >
                                                <Link
                                                    href={ventas.show({
                                                        current_team: teamSlug,
                                                        sale: sale.id,
                                                    })}
                                                >
                                                    <Eye className="size-3.5" />
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
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
