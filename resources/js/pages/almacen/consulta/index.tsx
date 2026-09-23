import { Head, router, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowDownRight,
    ArrowUpRight,
    Barcode,
    Boxes,
    Building2,
    Calendar,
    Clock,
    Eye,
    Filter,
    History,
    Layers,
    Package,
    RotateCcw,
    Scan,
    ScanBarcode,
    Search,
    Shield,
    Tag,
    User,
    Wrench,
    X,
} from 'lucide-react';
import { FormEvent, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AlmacenLayout from '@/layouts/almacen-layout';
import type { Team } from '@/types';

export type SedeOption = {
    id: number;
    nombre: string;
    tipo: string;
    ciudad: string | null;
};

export type UnitMovement = {
    id: number;
    tipo: string;
    cantidad: number;
    fecha: string;
    sede: string;
    usuario: string;
    observacion: string | null;
    numero_serie?: string | null;
};

export type UnitResult = {
    id: number;
    numero_serie: string;
    estado: 'disponible' | 'reservado' | 'vendido' | 'baja' | string;
    marca: string | null;
    anio_fabricacion: number | null;
    fecha_ingreso: string | null;
    sede: {
        id: number;
        nombre: string;
        tipo: string;
        ciudad: string | null;
    };
    producto: {
        id: number;
        codigo: string;
        nombre: string;
        unidad_medida: string;
        serializado: boolean;
    };
    movimientos: UnitMovement[];
};

export type ProductResult = {
    id: number;
    codigo: string;
    nombre: string;
    serializado: boolean;
    unidad_medida: string;
    stock_total: number;
    stock_por_sede: Record<number, number>;
    movimientos: UnitMovement[];
};

export type Props = {
    sedes: SedeOption[];
    filters: {
        search: string;
        sede_id: number | null;
    };
    unitResult: UnitResult | null;
    productResult: ProductResult | null;
};

function formatDate(isoString: string): string {
    const d = new Date(isoString);
    return d.toLocaleString('es-PE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function getEstadoBadge(estado: string) {
    switch (estado) {
        case 'disponible':
            return (
                <span className="inline-flex items-center gap-1 rounded-[6px] border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-1 text-xs font-bold text-emerald-600 dark:text-emerald-400">
                    <span className="size-2 rounded-full bg-emerald-600" />
                    Disponible en Stock
                </span>
            );
        case 'reservado':
            return (
                <span className="inline-flex items-center gap-1 rounded-[6px] border border-amber-500/20 bg-amber-500/10 px-2.5 py-1 text-xs font-bold text-amber-600 dark:text-amber-400">
                    <span className="size-2 rounded-full bg-amber-600" />
                    Reservado
                </span>
            );
        case 'vendido':
            return (
                <span className="inline-flex items-center gap-1 rounded-[6px] border border-sky-500/20 bg-sky-500/10 px-2.5 py-1 text-xs font-bold text-blue-600 dark:text-blue-400">
                    <span className="size-2 rounded-full bg-blue-600" />
                    Vendido (Equipo de Cliente)
                </span>
            );
        case 'baja':
            return (
                <span className="inline-flex items-center gap-1 rounded-[6px] border border-destructive/20 bg-destructive/10 px-2.5 py-1 text-xs font-bold text-destructive">
                    <span className="size-2 rounded-full bg-destructive" />
                    Dado de Baja
                </span>
            );
        default:
            return (
                <span className="inline-flex items-center gap-1 rounded-[6px] border border-border bg-muted/30 px-2.5 py-1 text-xs font-bold text-muted-foreground">
                    {estado}
                </span>
            );
    }
}

export default function ConsultaIndex({
    sedes,
    filters,
    unitResult,
    productResult,
}: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug = currentTeam?.slug ?? '';

    const [search, setSearch] = useState(filters.search || '');
    const [sedeId, setSedeId] = useState(
        filters.sede_id ? String(filters.sede_id) : '',
    );

    const handleSearch = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            `/${teamSlug}/almacen/consulta`,
            {
                search: search.trim(),
                sede_id: sedeId || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const handleClear = () => {
        setSearch('');
        setSedeId('');
        router.get(
            `/${teamSlug}/almacen/consulta`,
            {},
            { preserveState: true },
        );
    };

    return (
        <AlmacenLayout title="Consulta Rápida por Serie o Código">
            <Head title="Consulta Rápida - Almacén" />

            <div className="mx-auto flex max-w-5xl flex-col gap-6">
                {/* Title Header */}
                <div>
                    <h1 className="text-xl font-bold tracking-tight text-foreground">
                        Consulta Rápida
                    </h1>
                    <p className="text-xs text-muted-foreground">
                        Escaneo y auditoría de unidades serializadas o productos
                        a granel. 100% modo lectura.
                    </p>
                </div>

                {/* Scanner / Search Input Box */}
                <Card className="rounded-[14px] border border-border bg-card p-5 shadow-sm">
                    <form
                        onSubmit={handleSearch}
                        className="flex flex-col gap-3 md:flex-row"
                    >
                        <div className="relative flex-1">
                            <ScanBarcode className="absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-destructive" />
                            <Input
                                autoFocus
                                type="text"
                                placeholder="Escanee el sticker de código de barras o digite BF-EQ-XXXXXX / código..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="h-11 pr-10 pl-11 font-mono text-sm tracking-wide placeholder:font-sans placeholder:text-xs"
                            />
                            {search && (
                                <button
                                    type="button"
                                    onClick={() => setSearch('')}
                                    className="absolute top-1/2 right-3 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                >
                                    <X className="size-4" />
                                </button>
                            )}
                        </div>

                        <div className="w-full md:w-56">
                            <select
                                value={sedeId}
                                onChange={(e) => setSedeId(e.target.value)}
                                className="h-11 w-full rounded-[9px] border border-border bg-card px-3 text-xs font-medium text-foreground focus:border-primary focus:outline-none"
                            >
                                <option value="">Todas las sedes</option>
                                {sedes.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.nombre} ({s.tipo})
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="flex gap-2">
                            <Button
                                type="submit"
                                className="h-11 gap-2 bg-primary px-5 text-xs font-bold text-white hover:bg-primary/90"
                            >
                                <Search className="size-4" />
                                <span>Consultar</span>
                            </Button>

                            {(search || sedeId) && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={handleClear}
                                    className="h-11 text-xs"
                                >
                                    Limpiar
                                </Button>
                            )}
                        </div>
                    </form>
                </Card>

                {/* RESULTADO: UNIDAD SERIALIZADA */}
                {unitResult && (
                    <div className="space-y-6">
                        <Card className="rounded-[14px] border-2 border-border bg-card p-6 shadow-sm">
                            <div className="mb-5 flex flex-wrap items-center justify-between gap-3 border-b border-border pb-4">
                                <div className="flex items-center gap-3">
                                    <div className="flex size-11 items-center justify-center rounded-[10px] bg-muted/30 text-destructive">
                                        <Barcode className="size-6" />
                                    </div>
                                    <div>
                                        <span className="text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                            Unidad Física Serializada
                                        </span>
                                        <div className="font-mono text-2xl font-black tracking-wider text-foreground">
                                            {unitResult.numero_serie}
                                        </div>
                                    </div>
                                </div>

                                <div className="flex items-center gap-2">
                                    {getEstadoBadge(unitResult.estado)}
                                </div>
                            </div>

                            {/* Detalles de la Unidad */}
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-4">
                                <div className="rounded-[9px] border border-border bg-muted/40 p-3">
                                    <span className="text-[10.5px] font-semibold text-muted-foreground uppercase">
                                        Producto
                                    </span>
                                    <div className="mt-0.5 text-xs font-bold text-foreground">
                                        [{unitResult.producto.codigo}]{' '}
                                        {unitResult.producto.nombre}
                                    </div>
                                </div>

                                <div className="rounded-[9px] border border-border bg-muted/40 p-3">
                                    <span className="text-[10.5px] font-semibold text-muted-foreground uppercase">
                                        Ubicación Actual
                                    </span>
                                    <div className="mt-0.5 flex items-center gap-1 text-xs font-bold text-foreground">
                                        <Building2 className="size-3.5 text-destructive" />
                                        <span>
                                            {unitResult.sede.nombre} (
                                            {unitResult.sede.tipo})
                                        </span>
                                    </div>
                                </div>

                                <div className="rounded-[9px] border border-border bg-muted/40 p-3">
                                    <span className="text-[10.5px] font-semibold text-muted-foreground uppercase">
                                        Marca & Año
                                    </span>
                                    <div className="mt-0.5 text-xs font-bold text-foreground">
                                        {unitResult.marca || '—'} /{' '}
                                        {unitResult.anio_fabricacion || '—'}
                                    </div>
                                </div>

                                <div className="rounded-[9px] border border-border bg-muted/40 p-3">
                                    <span className="text-[10.5px] font-semibold text-muted-foreground uppercase">
                                        Fecha de Ingreso
                                    </span>
                                    <div className="mt-0.5 text-xs font-bold text-foreground">
                                        {unitResult.fecha_ingreso || '—'}
                                    </div>
                                </div>
                            </div>

                            {/* Auditoría Kardex: Últimos 5 Movimientos */}
                            <div className="mt-6">
                                <div className="mb-3 flex items-center gap-2 border-b border-border pb-2 text-xs font-bold text-foreground">
                                    <History className="size-4 text-destructive" />
                                    <span>
                                        Historial de Kardex de esta Unidad
                                        (Últimos 5 movimientos)
                                    </span>
                                </div>

                                {unitResult.movimientos.length === 0 ? (
                                    <p className="py-2 text-xs text-muted-foreground">
                                        No hay movimientos registrados para esta
                                        unidad.
                                    </p>
                                ) : (
                                    <div className="divide-y divide-border overflow-hidden rounded-[9px] border border-border text-xs">
                                        {unitResult.movimientos.map((m) => (
                                            <div
                                                key={m.id}
                                                className="flex flex-wrap items-center justify-between gap-3 bg-card p-3 hover:bg-muted/40"
                                            >
                                                <div className="flex items-center gap-3">
                                                    <span
                                                        className={`inline-flex items-center gap-1 rounded-[5px] px-2 py-0.5 text-[10.5px] font-black ${
                                                            m.cantidad > 0
                                                                ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                                                : 'bg-destructive/10 text-destructive'
                                                        }`}
                                                    >
                                                        {m.tipo.toUpperCase()}
                                                    </span>
                                                    <span className="font-mono text-[11px] text-muted-foreground">
                                                        {formatDate(m.fecha)}
                                                    </span>
                                                    <span className="text-foreground">
                                                        en <b>{m.sede}</b>
                                                    </span>
                                                </div>

                                                <div className="flex items-center gap-3 text-right">
                                                    <span className="max-w-xs truncate text-[11px] text-muted-foreground">
                                                        {m.observacion ||
                                                            'Sin observación'}
                                                    </span>
                                                    <span className="text-[10.5px] font-semibold text-muted-foreground">
                                                        Por {m.usuario}
                                                    </span>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>

                            {/* Banner Informativo de frontera de rol */}
                            <div className="mt-5 flex items-center gap-2 rounded-[8px] border border-border bg-muted/40 p-3 text-[11.5px] text-muted-foreground">
                                <Shield className="size-4 shrink-0 text-destructive" />
                                <span>
                                    <b>Modo Consulta Auditora:</b> Esta pantalla
                                    es estrictamente informativa. Para despachos
                                    y ventas a clientes, las operaciones se
                                    canalizan a través del rol Vendedor.
                                </span>
                            </div>
                        </Card>
                    </div>
                )}

                {/* RESULTADO: PRODUCTO A GRANEL */}
                {!unitResult && productResult && (
                    <Card className="rounded-[14px] border border-border bg-card p-6 shadow-sm">
                        <div className="mb-4 flex items-center justify-between border-b border-border pb-4">
                            <div className="flex items-center gap-3">
                                <div className="flex size-10 items-center justify-center rounded-[9px] bg-sky-500/10 text-blue-600 dark:text-blue-400">
                                    <Package className="size-5" />
                                </div>
                                <div>
                                    <span className="text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                        Producto / Repuesto a Granel
                                    </span>
                                    <h2 className="text-lg font-black text-foreground">
                                        [{productResult.codigo}]{' '}
                                        {productResult.nombre}
                                    </h2>
                                </div>
                            </div>
                            <Badge variant="outline" className="text-xs">
                                Stock Total: {productResult.stock_total}{' '}
                                {productResult.unidad_medida}
                            </Badge>
                        </div>

                        {/* Stock por Sede */}
                        <div className="mb-6 space-y-2">
                            <span className="text-xs font-bold text-foreground">
                                Disponibilidad por Sede:
                            </span>
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                {sedes.map((s) => (
                                    <div
                                        key={s.id}
                                        className="rounded-[9px] border border-border bg-muted/40 p-3 text-center"
                                    >
                                        <div className="truncate text-[11px] font-semibold text-muted-foreground">
                                            {s.nombre}
                                        </div>
                                        <div className="mt-1 text-base font-black text-foreground">
                                            {productResult.stock_por_sede[
                                                s.id
                                            ] ?? 0}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Últimos movimientos del producto */}
                        <div>
                            <span className="mb-2 block text-xs font-bold text-foreground">
                                Últimos Movimientos en Kardex:
                            </span>
                            <div className="divide-y divide-border overflow-hidden rounded-[9px] border border-border text-xs">
                                {productResult.movimientos.map((m) => (
                                    <div
                                        key={m.id}
                                        className="flex items-center justify-between bg-card p-2.5"
                                    >
                                        <div className="flex items-center gap-2">
                                            <span className="font-mono text-[11px] text-muted-foreground">
                                                {formatDate(m.fecha)}
                                            </span>
                                            <Badge
                                                variant="secondary"
                                                className="text-[10px]"
                                            >
                                                {m.tipo}
                                            </Badge>
                                            <span>en {m.sede}</span>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <span className="font-bold">
                                                {m.cantidad > 0
                                                    ? `+${m.cantidad}`
                                                    : m.cantidad}
                                            </span>
                                            <span className="text-[10.5px] text-gray-400">
                                                {m.usuario}
                                            </span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </Card>
                )}

                {/* EMPTY STATE */}
                {!unitResult && !productResult && (
                    <Card className="rounded-[14px] border border-dashed border-border bg-muted/40 p-12 text-center">
                        <div className="mx-auto mb-3 flex size-14 items-center justify-center rounded-full border border-border bg-card text-destructive shadow-sm">
                            <Scan className="size-7" />
                        </div>
                        <h3 className="text-base font-bold text-foreground">
                            {filters.search
                                ? 'No se encontraron registros'
                                : 'Listo para escanear o consultar'}
                        </h3>
                        <p className="mx-auto mt-1 max-w-sm text-xs text-muted-foreground">
                            {filters.search
                                ? `No existe ninguna unidad física ni producto que coincida con "${filters.search}". Verifique el número de serie.`
                                : 'Apunte el lector de código de barras a la etiqueta del equipo o ingrese el correlativo BF-EQ-XXXXXX en el campo superior.'}
                        </p>
                    </Card>
                )}
            </div>
        </AlmacenLayout>
    );
}
