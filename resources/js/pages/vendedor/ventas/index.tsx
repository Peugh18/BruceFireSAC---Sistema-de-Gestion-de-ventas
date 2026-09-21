import { Link, router, usePage } from '@inertiajs/react';
import { Banknote, CheckCircle2, Eye, FileText, Plus, ReceiptText, ShoppingCart } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import VendedorLayout from '@/layouts/vendedor-layout';
import ventas from '@/routes/vendedor/ventas';
import type { Team } from '@/types';

type SaleRow = {
    id: number;
    numero_interno: string;
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
    filters: { estado?: string };
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
    return new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(Number(value) || 0);
}

function cleanLabel(label: string) {
    return label.replace('&laquo;', '<').replace('&raquo;', '>');
}

function statusBadge(estado: string) {
    const normalized = estado?.toLowerCase();

    if (normalized === 'confirmada' || normalized === 'emitida') {
        return 'bg-[#E5F5EC] text-[#1E8E5A] border-transparent';
    }

    if (normalized === 'borrador') {
        return 'bg-[#FDF1E0] text-[#B45309] border-transparent';
    }

    if (normalized === 'anulada' || normalized === 'rechazada') {
        return 'bg-[#FBE7E7] text-[#B91C1C] border-transparent';
    }

    return 'bg-[#F1EFEC] text-[#6B6862] border-transparent';
}

export default function VentasIndex({ sales, filters, kpis }: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug = currentTeam?.slug ?? (typeof window !== 'undefined' ? window.location.pathname.split('/')[1] : '');
    const currentFilter = filters.estado ?? '';

    const changeFilter = (estado: string) => {
        router.get(
            ventas.index.url(teamSlug, { query: { estado: estado || undefined } }),
            {},
            { preserveScroll: true, preserveState: true },
        );
    };

    const kpiItems = [
        { label: 'Ventas del mes', value: money(kpis.ventas_del_mes), icon: Banknote, bg: 'bg-[#E5F5EC]', text: 'text-[#1E8E5A]' },
        { label: 'Comprobantes', value: kpis.comprobantes, icon: ReceiptText, bg: 'bg-[#E9EFFD]', text: 'text-[#2563EB]' },
        { label: 'Pendientes', value: kpis.pendientes_confirmar, icon: FileText, bg: 'bg-[#FDF1E0]', text: 'text-[#B45309]' },
        { label: 'Registros', value: sales.total ?? sales.data.length, icon: ShoppingCart, bg: 'bg-[#F1EFEC]', text: 'text-[#4A4742]' },
    ];

    return (
        <VendedorLayout title="Ventas">
            <div className="flex flex-col gap-4">
                <div className="grid gap-4 lg:grid-cols-4">
                    {kpiItems.map((item) => {
                        const Icon = item.icon;

                        return (
                            <Card key={item.label} className="flex-row items-center gap-3.5 rounded-[14px] border-[#E7E4DE] bg-white px-[18px] py-4 shadow-none">
                                <div className={`flex size-[42px] shrink-0 items-center justify-center rounded-[11px] ${item.bg}`}>
                                    <Icon className={`size-5 ${item.text}`} strokeWidth={2} />
                                </div>
                                <div className="min-w-0">
                                    <div className="text-[11px] font-bold tracking-[0.03em] text-[#8A8680] uppercase">{item.label}</div>
                                    <div className="font-['Oswald',sans-serif] text-[21px] leading-tight font-semibold text-[#201F1D]">{item.value}</div>
                                </div>
                            </Card>
                        );
                    })}
                </div>

                <Card className="gap-0 rounded-[16px] border-[#E7E4DE] bg-white p-5 shadow-none">
                    <div className="mb-4 flex flex-wrap items-center gap-2.5">
                        <div className="flex rounded-[9px] bg-[#F1EFEC] p-[3px]">
                            {FILTERS.map((filter) => {
                                const active = filter.value === '' ? !currentFilter || currentFilter === 'todas' : currentFilter === filter.value;

                                return (
                                    <button
                                        key={filter.label}
                                        type="button"
                                        onClick={() => changeFilter(filter.value)}
                                        className={`rounded-[7px] px-3.5 py-1.5 text-xs font-bold whitespace-nowrap transition-all ${
                                            active ? 'bg-white text-[#201F1D] shadow-[0_1px_2px_rgba(0,0,0,0.06)]' : 'text-[#6B6862] hover:text-[#201F1D]'
                                        }`}
                                    >
                                        {filter.label}
                                    </button>
                                );
                            })}
                        </div>
                        <div className="flex-1" />
                        <Button asChild className="h-10 rounded-[9px] bg-[#E31E24] px-4 text-[13px] font-bold text-white shadow-none hover:bg-[#C9181D]">
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
                                    {['Venta', 'Cliente', 'Fecha', 'Comprobante', 'Total', 'Estado', 'Acciones'].map((column) => (
                                        <th key={column} className="border-b border-[#E7E4DE] px-2.5 py-2.5 text-left font-['IBM_Plex_Mono',monospace] text-[9.5px] font-bold tracking-[0.05em] whitespace-nowrap text-[#8A8680] uppercase">
                                            {column}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {sales.data.map((sale) => (
                                    <tr key={sale.id} className="hover:bg-[#FAFAF8]">
                                        <td className="border-b border-[#F1EFEC] px-2.5 py-[13px] font-['IBM_Plex_Mono',monospace] font-bold text-[#201F1D]">{sale.numero_interno}</td>
                                        <td className="border-b border-[#F1EFEC] px-2.5 py-[13px] font-semibold text-[#201F1D]">{sale.cliente}</td>
                                        <td className="border-b border-[#F1EFEC] px-2.5 py-[13px] text-[#4A4742]">{sale.fecha}</td>
                                        <td className="border-b border-[#F1EFEC] px-2.5 py-[13px]">
                                            <span className="rounded-[5px] bg-[#F1EFEC] px-2 py-1 font-['IBM_Plex_Mono',monospace] text-[10px] font-bold text-[#4A4742] uppercase">{sale.comprobante_tipo}</span>
                                        </td>
                                        <td className="border-b border-[#F1EFEC] px-2.5 py-[13px] font-['IBM_Plex_Mono',monospace] font-bold text-[#201F1D]">{money(sale.total)}</td>
                                        <td className="border-b border-[#F1EFEC] px-2.5 py-[13px]">
                                            <Badge className={`rounded-full px-2.5 py-1 text-[10.5px] font-bold capitalize shadow-none ${statusBadge(sale.estado)}`}>
                                                <CheckCircle2 className="size-3" />
                                                {sale.estado}
                                            </Badge>
                                        </td>
                                        <td className="border-b border-[#F1EFEC] px-2.5 py-[13px]">
                                            <Button asChild variant="outline" size="icon" className="size-7 rounded-[7px] border-[#E7E4DE] bg-white text-[#4A4742] shadow-none">
                                                <Link href={ventas.show({ current_team: teamSlug, sale: sale.id })}>
                                                    <Eye className="size-3.5" />
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className="mt-3.5 flex flex-col gap-3 text-[11.5px] text-[#8A8680] sm:flex-row sm:items-center sm:justify-between">
                        <span>Mostrando {sales.from ?? 0}-{sales.to ?? 0} de {sales.total ?? sales.data.length} ventas</span>
                        <div className="flex flex-wrap gap-1.5">
                            {sales.links.map((link, index) =>
                                link.url ? (
                                    <Link key={`${link.label}-${index}`} href={link.url} preserveScroll preserveState className={`flex h-[26px] min-w-[26px] items-center justify-center rounded-[7px] px-2 font-['IBM_Plex_Mono',monospace] text-[11.5px] no-underline ${link.active ? 'bg-[#E31E24] font-bold text-white' : 'text-[#8A8680] hover:bg-[#F1EFEC] hover:text-[#201F1D]'}`}>
                                        {cleanLabel(link.label)}
                                    </Link>
                                ) : (
                                    <span key={`${link.label}-${index}`} className="flex h-[26px] min-w-[26px] items-center justify-center rounded-[7px] px-2 font-['IBM_Plex_Mono',monospace] text-[11.5px] text-[#C9C5BE]">
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
