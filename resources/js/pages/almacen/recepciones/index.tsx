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
    const [sedeId, setSedeId] = useState(
        filters.sede_id ? String(filters.sede_id) : '',
    );
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
                        <h1 className="text-foreground text-xl font-bold">
                            Recepciones de Mercadería
                        </h1>
                        <p className="text-muted-foreground text-xs">
                            Registro de ingresos de proveedores, captura de
                            unidades serializadas y control de no conformidades.
                        </p>
                    </div>

                    <Link
                        href={recepciones.create.url(teamSlug)}
                        className="bg-primary hover:bg-primary/90 inline-flex items-center justify-center gap-1.5 rounded-[9px] px-4 py-2.5 text-xs font-bold text-white shadow-xs transition-colors"
                    >
                        <Plus className="size-4" />
                        <span>Nueva Recepción</span>
                    </Link>
                </div>

                {/* 3 KPI Cards */}
                <div className="grid gap-4 sm:grid-cols-3">
                    <Card className="border-border bg-card rounded-[16px] p-5 shadow-none">
                        <div className="text-muted-foreground flex items-center justify-between">
                            <span className="text-xs font-bold tracking-wider uppercase">
                                Total recepciones
                            </span>
                            <Truck className="text-foreground size-4" />
                        </div>
                        <div className="text-foreground mt-3 font-['Oswald',sans-serif] text-[28px] font-semibold">
                            {kpis.total_recepciones}
                        </div>
                        <div className="text-muted-foreground mt-1 text-[11.5px]">
                            Historial completo acumulado
                        </div>
                    </Card>

                    <Card className="border-border bg-card rounded-[16px] p-5 shadow-none">
                        <div className="text-muted-foreground flex items-center justify-between">
                            <span className="text-xs font-bold tracking-wider uppercase">
                                Recepciones de hoy
                            </span>
                            <Calendar className="text-success-strong size-4" />
                        </div>
                        <div className="text-success-strong mt-3 font-['Oswald',sans-serif] text-[28px] font-semibold">
                            {kpis.recepciones_hoy}
                        </div>
                        <div className="text-muted-foreground mt-1 text-[11.5px]">
                            Ingresos registrados en la fecha
                        </div>
                    </Card>

                    <Card className="border-border bg-card rounded-[16px] p-5 shadow-none">
                        <div className="text-muted-foreground flex items-center justify-between">
                            <span className="text-xs font-bold tracking-wider uppercase">
                                Unidades conformes (mes)
                            </span>
                            <Package className="size-4 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div className="mt-3 font-['Oswald',sans-serif] text-[28px] font-semibold text-blue-600 dark:text-blue-400">
                            {kpis.unidades_recibidas_mes}
                        </div>
                        <div className="text-muted-foreground mt-1 text-[11.5px]">
                            Incorporadas al stock disponible este mes
                        </div>
                    </Card>
                </div>

                {/* Table Card */}
                <Card className="border-border bg-card rounded-[16px] p-5 shadow-none">
                    {/* Filters Form */}
                    <form
                        onSubmit={handleFilter}
                        className="border-border flex flex-wrap items-end gap-3 border-b pb-5"
                    >
                        <div className="min-w-[200px] flex-1">
                            <Label className="text-foreground/80 text-xs font-bold">
                                Proveedor / Doc. Ref.
                            </Label>
                            <div className="relative mt-1">
                                <Search className="text-muted-foreground absolute top-2.5 left-3 size-4" />
                                <Input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Buscar por proveedor o documento..."
                                    className="h-9 pl-9 text-xs"
                                />
                            </div>
                        </div>

                        <div className="w-[180px]">
                            <Label className="text-foreground/80 text-xs font-bold">
                                Sede almacén
                            </Label>
                            <select
                                value={sedeId}
                                onChange={(e) => setSedeId(e.target.value)}
                                className="border-border bg-card text-foreground focus:border-primary mt-1 h-9 w-full rounded-md border px-3 text-xs focus:outline-none"
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
                            <Label className="text-foreground/80 text-xs font-bold">
                                Desde
                            </Label>
                            <Input
                                type="date"
                                value={fechaDesde}
                                onChange={(e) => setFechaDesde(e.target.value)}
                                className="mt-1 h-9 text-xs"
                            />
                        </div>

                        <div className="w-[130px]">
                            <Label className="text-foreground/80 text-xs font-bold">
                                Hasta
                            </Label>
                            <Input
                                type="date"
                                value={fechaHasta}
                                onChange={(e) => setFechaHasta(e.target.value)}
                                className="mt-1 h-9 text-xs"
                            />
                        </div>

                        <div className="flex items-center gap-2">
                            <Button
                                type="submit"
                                size="sm"
                                className="bg-foreground text-background hover:bg-foreground/90 h-9 gap-1.5"
                            >
                                <Filter className="size-3.5" />
                                <span>Filtrar</span>
                            </Button>
                            {(search !== '' ||
                                sedeId !== '' ||
                                fechaDesde !== '' ||
                                fechaHasta !== '') && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={handleReset}
                                    className="text-muted-foreground h-9 gap-1.5"
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
                            <Truck className="text-muted-foreground size-10" />
                            <p className="text-muted-foreground mt-2 text-sm font-medium">
                                No se encontraron recepciones con los filtros
                                indicados.
                            </p>
                        </div>
                    ) : (
                        <div className="mt-4 overflow-x-auto">
                            <table className="w-full text-left text-[12.5px]">
                                <thead>
                                    <tr className="border-border text-muted-foreground border-b text-[11px] font-bold tracking-wider uppercase">
                                        <th className="py-2.5 pr-3">N° Doc.</th>
                                        <th className="px-3 py-2.5">Fecha</th>
                                        <th className="px-4 py-2.5">
                                            Proveedor
                                        </th>
                                        <th className="px-3 py-2.5">
                                            Doc. Referencia
                                        </th>
                                        <th className="px-3 py-2.5">
                                            Sede Almacén
                                        </th>
                                        <th className="px-3 py-2.5 text-center">
                                            Cant. Recibida
                                        </th>
                                        <th className="px-3 py-2.5 text-center">
                                            Conforme
                                        </th>
                                        <th className="px-3 py-2.5 text-center">
                                            Estado Conformidad
                                        </th>
                                        <th className="py-2.5 pl-4 text-right">
                                            Acción
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-border divide-y">
                                    {receptions.data.map((rec) => (
                                        <tr
                                            key={rec.id}
                                            className="hover:bg-muted/40 transition-colors"
                                        >
                                            <td className="text-foreground py-3 pr-3 font-mono font-bold">
                                                #{rec.id}
                                            </td>
                                            <td className="text-foreground/80 px-3 py-3 font-mono text-[11.5px] whitespace-nowrap">
                                                {formatDate(rec.fecha)}
                                            </td>
                                            <td className="text-foreground px-4 py-3 font-bold">
                                                {rec.proveedor}
                                            </td>
                                            <td className="text-muted-foreground px-3 py-3 font-mono text-[11.5px]">
                                                {rec.documento_referencia ||
                                                    '—'}
                                            </td>
                                            <td className="text-foreground/80 px-3 py-3">
                                                <div className="flex items-center gap-1.5">
                                                    <Building2 className="text-muted-foreground size-3.5" />
                                                    <span>{rec.sede}</span>
                                                </div>
                                            </td>
                                            <td className="text-foreground px-3 py-3 text-center font-mono font-bold">
                                                {rec.total_recibido}
                                            </td>
                                            <td className="text-success-strong px-3 py-3 text-center font-mono font-bold">
                                                {rec.total_conforme}
                                            </td>
                                            <td className="px-3 py-3 text-center">
                                                {rec.tiene_no_conforme ? (
                                                    <span className="border-destructive/20 bg-destructive/10 text-primary-strong inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-[11px] font-bold">
                                                        <AlertTriangle className="size-3" />
                                                        <span>
                                                            {
                                                                rec.total_no_conforme
                                                            }{' '}
                                                            No Conforme
                                                        </span>
                                                    </span>
                                                ) : (
                                                    <span className="text-success-strong inline-flex items-center gap-1 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-0.5 text-[11px] font-bold">
                                                        <CheckCircle2 className="size-3" />
                                                        <span>
                                                            100% Conforme
                                                        </span>
                                                    </span>
                                                )}
                                            </td>
                                            <td className="py-3 pl-4 text-right">
                                                <Link
                                                    href={recepciones.show.url({
                                                        current_team: teamSlug,
                                                        reception: rec.id,
                                                    })}
                                                    className="border-border bg-card text-foreground hover:bg-background inline-flex items-center gap-1 rounded-md border px-2.5 py-1 text-xs font-bold"
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
                                <div className="border-border text-muted-foreground mt-4 flex items-center justify-between border-t pt-4 text-xs">
                                    <span>
                                        Mostrando{' '}
                                        <b>{receptions.data.length}</b> de{' '}
                                        <b>{receptions.total}</b> recepciones
                                    </span>
                                    <div className="flex items-center gap-1">
                                        {receptions.links.map((link, idx) => (
                                            <button
                                                key={idx}
                                                type="button"
                                                disabled={!link.url}
                                                onClick={() => {
                                                    if (link.url) {
                                                        router.get(
                                                            link.url,
                                                            {},
                                                            {
                                                                preserveState: true,
                                                                preserveScroll: true,
                                                            },
                                                        );
                                                    }
                                                }}
                                                dangerouslySetInnerHTML={{
                                                    __html: link.label,
                                                }}
                                                className={[
                                                    'h-8 min-w-[32px] rounded-md px-2 font-medium transition-colors',
                                                    link.active
                                                        ? 'bg-foreground text-background font-bold'
                                                        : link.url
                                                          ? 'hover:bg-muted text-foreground'
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
