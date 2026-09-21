import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Building2,
    Calendar,
    CheckCircle2,
    FileText,
    Filter,
    Package,
    Plus,
    RotateCcw,
    Search,
    Truck,
    User,
} from 'lucide-react';
import { FormEvent, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AlmacenLayout from '@/layouts/almacen-layout';
import recepciones from '@/routes/almacen/recepciones';
import type { Team } from '@/types';

export type ReceptionRow = {
    id: number;
    proveedor: string;
    documento_referencia: string | null;
    fecha: string;
    sede: string;
    usuario: string;
    total_recibido: number;
    total_conforme: number;
    total_no_conforme: number;
    tiene_no_conforme: boolean;
    items_count: number;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type PaginatedReceptions = {
    data: ReceptionRow[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    total: number;
};

export type SedeOption = {
    id: number;
    nombre: string;
    ciudad: string | null;
};

export type Props = {
    receptions: PaginatedReceptions;
    sedes: SedeOption[];
    filters: {
        search: string;
        sede_id: number | null;
        fecha_desde: string | null;
        fecha_hasta: string | null;
    };
    kpis: {
        total_recepciones: number;
        recepciones_hoy: number;
        unidades_recibidas_mes: number;
    };
};

function formatDate(dateStr: string): string {
    const [year, month, day] = dateStr.split('-');
    return `${day}/${month}/${year}`;
}

export default function RecepcionesIndex({
    receptions,
    sedes,
    filters,
    kpis,
}: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug =
        currentTeam?.slug ||
        (typeof window !== 'undefined'
            ? window.location.pathname.split('/')[1]
            : '');

    const [search, setSearch] = useState(filters.search || '');
    const [sedeId, setSedeId] = useState(filters.sede_id ? String(filters.sede_id) : '');
    const [fechaDesde, setFechaDesde] = useState(filters.fecha_desde || '');
    const [fechaHasta, setFechaHasta] = useState(filters.fecha_hasta || '');

    const handleFilter = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            recepciones.index.url(teamSlug),
            {
                search: search || undefined,
                sede_id: sedeId ? Number(sedeId) : undefined,
                fecha_desde: fechaDesde || undefined,
                fecha_hasta: fechaHasta || undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const handleReset = () => {
        setSearch('');
        setSedeId('');
        setFechaDesde('');
        setFechaHasta('');
        router.get(recepciones.index.url(teamSlug));
    };

    return (
        <AlmacenLayout title="Recepciones de Proveedor">
            <Head title="Recepciones - Almacén" />

            <div className="flex flex-col gap-6">
                {/* Header Actions */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-xl font-bold text-[#201F1D]">
                            Recepciones de Mercadería
                        </h1>
                        <p className="text-xs text-[#8A8680]">
                            Registro de ingresos de proveedores, captura de unidades serializadas y control de no conformidades.
                        </p>
                    </div>

                    <Link
                        href={recepciones.create.url(teamSlug)}
                        className="inline-flex items-center justify-center gap-1.5 rounded-[9px] bg-[#E31E24] px-4 py-2.5 text-xs font-bold text-white shadow-xs transition-colors hover:bg-[#C2171C]"
                    >
                        <Plus className="size-4" />
                        <span>Nueva Recepción</span>
                    </Link>
                </div>

                {/* 3 KPI Cards */}
                <div className="grid gap-4 sm:grid-cols-3">
                    <Card className="rounded-[16px] border-[#E7E4DE] bg-white p-5 shadow-none">
                        <div className="flex items-center justify-between text-[#8A8680]">
                            <span className="text-xs font-bold uppercase tracking-wider">Total recepciones</span>
                            <Truck className="size-4 text-[#201F1D]" />
                        </div>
                        <div className="mt-3 font-['Oswald',sans-serif] text-[28px] font-semibold text-[#201F1D]">
                            {kpis.total_recepciones}
                        </div>
                        <div className="mt-1 text-[11.5px] text-[#8A8680]">
                            Historial completo acumulado
                        </div>
                    </Card>

                    <Card className="rounded-[16px] border-[#E7E4DE] bg-white p-5 shadow-none">
                        <div className="flex items-center justify-between text-[#8A8680]">
                            <span className="text-xs font-bold uppercase tracking-wider">Recepciones de hoy</span>
                            <Calendar className="size-4 text-[#1E8E5A]" />
                        </div>
                        <div className="mt-3 font-['Oswald',sans-serif] text-[28px] font-semibold text-[#1E8E5A]">
                            {kpis.recepciones_hoy}
                        </div>
                        <div className="mt-1 text-[11.5px] text-[#8A8680]">
                            Ingresos registrados en la fecha
                        </div>
                    </Card>

                    <Card className="rounded-[16px] border-[#E7E4DE] bg-white p-5 shadow-none">
                        <div className="flex items-center justify-between text-[#8A8680]">
                            <span className="text-xs font-bold uppercase tracking-wider">Unidades conformes (mes)</span>
                            <Package className="size-4 text-[#2563EB]" />
                        </div>
                        <div className="mt-3 font-['Oswald',sans-serif] text-[28px] font-semibold text-[#2563EB]">
                            {kpis.unidades_recibidas_mes}
                        </div>
                        <div className="mt-1 text-[11.5px] text-[#8A8680]">
                            Incorporadas al stock disponible este mes
                        </div>
                    </Card>
                </div>

                {/* Table Card */}
                <Card className="rounded-[16px] border-[#E7E4DE] bg-white p-5 shadow-none">
                    {/* Filters Form */}
                    <form onSubmit={handleFilter} className="flex flex-wrap items-end gap-3 pb-5 border-b border-[#F1EFEC]">
                        <div className="flex-1 min-w-[200px]">
                            <Label className="text-xs font-bold text-[#4A4742]">Proveedor / Doc. Ref.</Label>
                            <div className="relative mt-1">
                                <Search className="absolute top-2.5 left-3 size-4 text-[#8A8680]" />
                                <Input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Buscar por proveedor o documento..."
                                    className="h-9 pl-9 text-xs"
                                />
                            </div>
                        </div>

                        <div className="w-[180px]">
                            <Label className="text-xs font-bold text-[#4A4742]">Sede almacén</Label>
                            <select
                                value={sedeId}
                                onChange={(e) => setSedeId(e.target.value)}
                                className="mt-1 h-9 w-full rounded-md border border-[#E4E1DC] bg-white px-3 text-xs text-[#201F1D] focus:border-[#E31E24] focus:outline-none"
                            >
                                <option value="">Todas las sedes</option>
                                {sedes.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.nombre}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="w-[130px]">
                            <Label className="text-xs font-bold text-[#4A4742]">Desde</Label>
                            <Input
                                type="date"
                                value={fechaDesde}
                                onChange={(e) => setFechaDesde(e.target.value)}
                                className="mt-1 h-9 text-xs"
                            />
                        </div>

                        <div className="w-[130px]">
                            <Label className="text-xs font-bold text-[#4A4742]">Hasta</Label>
                            <Input
                                type="date"
                                value={fechaHasta}
                                onChange={(e) => setFechaHasta(e.target.value)}
                                className="mt-1 h-9 text-xs"
                            />
                        </div>

                        <div className="flex items-center gap-2">
                            <Button type="submit" size="sm" className="h-9 gap-1.5 bg-[#18181B] text-white hover:bg-[#27272A]">
                                <Filter className="size-3.5" />
                                <span>Filtrar</span>
                            </Button>
                            {(search !== '' || sedeId !== '' || fechaDesde !== '' || fechaHasta !== '') && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={handleReset}
                                    className="h-9 gap-1.5 text-[#6B6965]"
                                >
                                    <RotateCcw className="size-3.5" />
                                    <span>Limpiar</span>
                                </Button>
                            )}
                        </div>
                    </form>

                    {/* Receptions Table */}
                    {receptions.data.length === 0 ? (
                        <div className="flex min-h-[260px] flex-col items-center justify-center text-center">
                            <Truck className="size-10 text-[#D5D2CB]" />
                            <p className="mt-2 text-sm font-medium text-[#8A8680]">
                                No se encontraron recepciones con los filtros indicados.
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto mt-4">
                            <table className="w-full text-left text-[12.5px]">
                                <thead>
                                    <tr className="border-b border-[#E7E4DE] text-[11px] font-bold text-[#8A8680] uppercase tracking-wider">
                                        <th className="py-2.5 pr-3">N° Doc.</th>
                                        <th className="py-2.5 px-3">Fecha</th>
                                        <th className="py-2.5 px-4">Proveedor</th>
                                        <th className="py-2.5 px-3">Doc. Referencia</th>
                                        <th className="py-2.5 px-3">Sede Almacén</th>
                                        <th className="py-2.5 px-3 text-center">Cant. Recibida</th>
                                        <th className="py-2.5 px-3 text-center">Conforme</th>
                                        <th className="py-2.5 px-3 text-center">Estado Conformidad</th>
                                        <th className="py-2.5 pl-4 text-right">Acción</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-[#F1EFEC]">
                                    {receptions.data.map((rec) => (
                                        <tr key={rec.id} className="hover:bg-[#FAFAF8] transition-colors">
                                            <td className="py-3 pr-3 font-mono font-bold text-[#201F1D]">
                                                #{rec.id}
                                            </td>
                                            <td className="py-3 px-3 font-mono text-[11.5px] text-[#4A4742] whitespace-nowrap">
                                                {formatDate(rec.fecha)}
                                            </td>
                                            <td className="py-3 px-4 font-bold text-[#201F1D]">
                                                {rec.proveedor}
                                            </td>
                                            <td className="py-3 px-3 font-mono text-[11.5px] text-[#6B6965]">
                                                {rec.documento_referencia || '—'}
                                            </td>
                                            <td className="py-3 px-3 text-[#4A4742]">
                                                <div className="flex items-center gap-1.5">
                                                    <Building2 className="size-3.5 text-[#8A8680]" />
                                                    <span>{rec.sede}</span>
                                                </div>
                                            </td>
                                            <td className="py-3 px-3 text-center font-mono font-bold text-[#201F1D]">
                                                {rec.total_recibido}
                                            </td>
                                            <td className="py-3 px-3 text-center font-mono font-bold text-[#1E8E5A]">
                                                {rec.total_conforme}
                                            </td>
                                            <td className="py-3 px-3 text-center">
                                                {rec.tiene_no_conforme ? (
                                                    <span className="inline-flex items-center gap-1 rounded-full border border-[#F5C6C5] bg-[#FBEAE9] px-2.5 py-0.5 text-[11px] font-bold text-[#E31E24]">
                                                        <AlertTriangle className="size-3" />
                                                        <span>{rec.total_no_conforme} No Conforme</span>
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1 rounded-full border border-[#C3E8D4] bg-[#E5F5EC] px-2.5 py-0.5 text-[11px] font-bold text-[#1E8E5A]">
                                                        <CheckCircle2 className="size-3" />
                                                        <span>100% Conforme</span>
                                                    </span>
                                                )}
                                            </td>
                                            <td className="py-3 pl-4 text-right">
                                                <Link
                                                    href={recepciones.show.url({ current_team: teamSlug, reception: rec.id })}
                                                    className="inline-flex items-center gap-1 rounded-md border border-[#E4E1DC] bg-white px-2.5 py-1 text-xs font-bold text-[#201F1D] hover:bg-[#F3F1ED]"
                                                >
                                                    <FileText className="size-3.5" />
                                                    <span>Detalle</span>
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>

                            {/* Paginador */}
                            {receptions.links.length > 3 && (
                                <div className="mt-4 flex items-center justify-between border-t border-[#F1EFEC] pt-4 text-xs text-[#8A8680]">
                                    <span>
                                        Mostrando <b>{receptions.data.length}</b> de <b>{receptions.total}</b> recepciones
                                    </span>
                                    <div className="flex items-center gap-1">
                                        {receptions.links.map((link, idx) => (
                                            <button
                                                key={idx}
                                                type="button"
                                                disabled={!link.url}
                                                onClick={() => {
                                                    if (link.url) {
                                                        router.get(link.url, {}, { preserveState: true, preserveScroll: true });
                                                    }
                                                }}
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                                className={[
                                                    'h-8 min-w-[32px] rounded-md px-2 font-medium transition-colors',
                                                    link.active
                                                        ? 'bg-[#18181B] text-white font-bold'
                                                        : link.url
                                                        ? 'hover:bg-[#F1EFEC] text-[#201F1D]'
                                                        : 'opacity-40 cursor-not-allowed',
                                                ].join(' ')}
                                            />
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    )}
                </Card>
            </div>
        </AlmacenLayout>
    );
}
