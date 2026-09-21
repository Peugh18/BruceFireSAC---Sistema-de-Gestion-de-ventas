import { Head, Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRight,
    Boxes,
    Building2,
    Calendar,
    History,
    PackageCheck,
    ScanBarcode,
    TrendingDown,
    Truck,
    User,
} from 'lucide-react';

import { Card } from '@/components/ui/card';
import AlmacenLayout from '@/layouts/almacen-layout';
import type { Team } from '@/types';

export type SedeStock = {
    sede_id: number;
    nombre: string;
    tipo: string;
    ciudad: string | null;
    unidades_disponibles: number;
};

export type StockKpis = {
    total_disponible: number;
    por_sede: SedeStock[];
};

export type MovimientoReciente = {
    id: number;
    fecha: string | null;
    tipo: string;
    cantidad: number;
    producto: {
        id: number;
        codigo: string;
        nombre: string;
        unidad_medida: string;
        serializado: boolean;
    };
    unidad_serie?: string | null;
    sede?: string | null;
    usuario?: string | null;
    observacion?: string | null;
};

export type ProductoBajoMinimo = {
    id: number;
    codigo: string;
    nombre: string;
    unidad_medida: string;
    stock_minimo: number;
    stock_disponible: number;
    diferencia: number;
};

export type AlmacenDashboardProps = {
    stock: StockKpis;
    recepciones_hoy: number;
    movimientos_recientes: MovimientoReciente[];
    productos_bajo_minimo: ProductoBajoMinimo[];
};

function formatNumber(value: number): string {
    return new Intl.NumberFormat('es-PE').format(value);
}

function formatDate(isoString: string | null): string {
    if (!isoString) return '—';
    const date = new Date(isoString);
    return date.toLocaleString('es-PE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function getTipoMovimientoBadge(tipo: string): { label: string; className: string } {
    switch (tipo) {
        case 'ingreso':
            return {
                label: 'Ingreso',
                className: 'bg-[#E5F5EC] text-[#1E8E5A] border-[#C3E8D4]',
            };
        case 'salida_venta':
            return {
                label: 'Venta',
                className: 'bg-[#FBEAE9] text-[#E31E24] border-[#F5C6C5]',
            };
        case 'ajuste':
            return {
                label: 'Ajuste',
                className: 'bg-[#FEF6E9] text-[#B4690E] border-[#FCE1B6]',
            };
        case 'traslado':
            return {
                label: 'Traslado',
                className: 'bg-[#EFF6FF] text-[#2563EB] border-[#BFDBFE]',
            };
        default:
            return {
                label: tipo,
                className: 'bg-[#F1EFEC] text-[#6B6965] border-[#E4E1DC]',
            };
    }
}

export default function AlmacenDashboard({
    stock,
    recepciones_hoy,
    movimientos_recientes,
    productos_bajo_minimo,
}: AlmacenDashboardProps) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug =
        currentTeam?.slug ||
        (typeof window !== 'undefined'
            ? window.location.pathname.split('/')[1]
            : '');

    return (
        <AlmacenLayout title="Dashboard de Almacén">
            <Head title="Dashboard Almacén" />

            <div className="flex flex-col gap-6">
                {/* Banner de alerta si hay productos bajo el stock mínimo de almacén */}
                {productos_bajo_minimo.length > 0 && (
                    <div className="flex items-center gap-3 rounded-[14px] bg-gradient-to-r from-[#B9151A] to-[#E31E24] px-5 py-3.5 text-white shadow-xs">
                        <AlertTriangle className="size-5 shrink-0 text-white" />
                        <span className="flex-1 text-[13px]">
                            Hay <b>{productos_bajo_minimo.length} producto(s)</b> con existencias bajo el nivel mínimo en el almacén. Se requiere compra / reabastecimiento.
                        </span>
                        <Link
                            href={`/${teamSlug}/almacen/stock`}
                            className="inline-flex items-center gap-1.5 rounded-[8px] bg-white/20 px-3 py-1.5 text-xs font-bold text-white transition-colors hover:bg-white/30"
                        >
                            <span>Ver stock</span>
                            <ArrowRight className="size-3.5" />
                        </Link>
                    </div>
                )}

                {/* 3-Column Top Grid: Unidades en Stock, Recepciones de Hoy, Alerta Reabastecimiento */}
                <div className="grid gap-4 md:grid-cols-3">
                    {/* 1. Unidades Disponibles */}
                    <Card className="rounded-[16px] border-[#E7E4DE] bg-white p-5 shadow-none">
                        <div className="flex items-center justify-between">
                            <span className="text-[13.5px] font-bold text-[#201F1D]">Unidades disponibles</span>
                            <div className="flex size-7 items-center justify-center rounded-[8px] bg-[#F1EFEC] text-[#201F1D]">
                                <Boxes className="size-4" />
                            </div>
                        </div>

                        <div className="mt-4">
                            <div className="font-['Oswald',sans-serif] text-[34px] leading-none font-semibold text-[#201F1D]">
                                {formatNumber(stock.total_disponible)}
                            </div>
                            <div className="mt-2 text-[11.5px] text-[#8A8680]">
                                Unidades físicas serializadas en almacenes
                            </div>
                        </div>

                        <div className="mt-4 border-t border-[#F1EFEC] pt-3">
                            <div className="text-[11px] font-bold tracking-wider text-[#8A8680] uppercase">
                                Por almacén
                            </div>
                            <div className="mt-2 flex flex-col gap-1.5">
                                {stock.por_sede.map((sede) => (
                                    <div key={sede.sede_id} className="flex items-center justify-between text-[12px]">
                                        <span className="flex items-center gap-1.5 text-[#4A4742]">
                                            <Building2 className="size-3.5 text-[#8A8680]" />
                                            {sede.nombre}
                                        </span>
                                        <span className="font-bold text-[#201F1D]">
                                            {formatNumber(sede.unidades_disponibles)}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </Card>

                    {/* 2. Recepciones de Hoy */}
                    <Card className="rounded-[16px] border-[#E7E4DE] bg-white p-5 shadow-none">
                        <div className="flex items-center justify-between">
                            <span className="text-[13.5px] font-bold text-[#201F1D]">Recepciones de hoy</span>
                            <div className="flex size-7 items-center justify-center rounded-[8px] bg-[#E5F5EC] text-[#1E8E5A]">
                                <Truck className="size-4" />
                            </div>
                        </div>

                        <div className="mt-4">
                            <div className="font-['Oswald',sans-serif] text-[34px] leading-none font-semibold text-[#201F1D]">
                                {formatNumber(recepciones_hoy)}
                            </div>
                            <div className="mt-2 flex items-center gap-1.5 text-[11.5px] text-[#8A8680]">
                                <span className="inline-flex items-center gap-1 rounded-[6px] bg-[#E5F5EC] px-2 py-0.5 font-bold text-[#1E8E5A]">
                                    <Calendar className="size-3" />
                                    <span>Hoy</span>
                                </span>
                                <span>Ingresos registrados al Kardex</span>
                            </div>
                        </div>

                        <div className="mt-5 border-t border-[#F1EFEC] pt-4">
                            <Link
                                href={`/${teamSlug}/almacen/recepciones`}
                                className="inline-flex items-center gap-1.5 text-xs font-bold text-[#E31E24] hover:underline"
                            >
                                <span>Registrar nueva recepción</span>
                                <ArrowRight className="size-3.5" />
                            </Link>
                        </div>
                    </Card>

                    {/* 3. Reabastecimiento de Almacén */}
                    <Card className="rounded-[16px] border-[#E7E4DE] bg-white p-5 shadow-none">
                        <div className="flex items-center justify-between">
                            <span className="text-[13.5px] font-bold text-[#201F1D]">Bajo stock mínimo</span>
                            <div className="flex size-7 items-center justify-center rounded-[8px] bg-[#FBEAE9] text-[#E31E24]">
                                <TrendingDown className="size-4" />
                            </div>
                        </div>

                        <div className="mt-4">
                            <div className="font-['Oswald',sans-serif] text-[34px] leading-none font-semibold text-[#E31E24]">
                                {formatNumber(productos_bajo_minimo.length)}
                            </div>
                            <div className="mt-2 text-[11.5px] text-[#8A8680]">
                                Productos que requieren reposición
                            </div>
                        </div>

                        <div className="mt-5 border-t border-[#F1EFEC] pt-4">
                            <Link
                                href={`/${teamSlug}/almacen/stock`}
                                className="inline-flex items-center gap-1.5 text-xs font-bold text-[#201F1D] hover:underline"
                            >
                                <span>Ver reporte de inventario</span>
                                <ArrowRight className="size-3.5" />
                            </Link>
                        </div>
                    </Card>
                </div>

                {/* Bottom Section: Movimientos Recientes & Productos Bajo Mínimo Table */}
                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Movimientos Recientes del Kardex (2 cols) */}
                    <div className="lg:col-span-2">
                        <Card className="rounded-[16px] border-[#E7E4DE] bg-white p-5 shadow-none">
                            <div className="flex items-center justify-between border-b border-[#F1EFEC] pb-4">
                                <div className="flex items-center gap-2">
                                    <History className="size-4 text-[#8A8680]" />
                                    <span className="text-[14px] font-bold text-[#201F1D]">
                                        Últimos 10 movimientos del Kardex
                                    </span>
                                </div>
                                <span className="text-[11.5px] font-medium text-[#8A8680]">
                                    Tiempo real
                                </span>
                            </div>

                            {movimientos_recientes.length === 0 ? (
                                <div className="flex min-h-[220px] flex-col items-center justify-center text-center">
                                    <PackageCheck className="size-10 text-[#D5D2CB]" />
                                    <p className="mt-2 text-sm font-medium text-[#8A8680]">
                                        Aún no se registran movimientos en el Kardex.
                                    </p>
                                </div>
                            ) : (
                                <div className="divide-y divide-[#F1EFEC] overflow-x-auto">
                                    {movimientos_recientes.map((mov) => {
                                        const badge = getTipoMovimientoBadge(mov.tipo);
                                        return (
                                            <div
                                                key={mov.id}
                                                className="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between text-[12.5px]"
                                            >
                                                <div className="flex items-start gap-3 min-w-0">
                                                    <span
                                                        className={`inline-flex shrink-0 items-center rounded-[6px] border px-2 py-0.5 text-[10.5px] font-bold ${badge.className}`}
                                                    >
                                                        {badge.label}
                                                    </span>

                                                    <div className="min-w-0">
                                                        <div className="font-bold text-[#201F1D] truncate">
                                                            {mov.producto.nombre}
                                                        </div>
                                                        <div className="flex flex-wrap items-center gap-2 text-[11px] text-[#8A8680]">
                                                            <span>Cód: {mov.producto.codigo}</span>
                                                            {mov.unidad_serie && (
                                                                <span className="flex items-center gap-1 font-mono text-[#201F1D]">
                                                                    <ScanBarcode className="size-3" />
                                                                    {mov.unidad_serie}
                                                                </span>
                                                            )}
                                                            {mov.sede && (
                                                                <span className="flex items-center gap-1">
                                                                    <Building2 className="size-3" />
                                                                    {mov.sede}
                                                                </span>
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>

                                                <div className="flex items-center justify-between sm:flex-col sm:items-end gap-1 text-[11.5px] shrink-0">
                                                    <span className="font-mono font-bold text-[#201F1D]">
                                                        {mov.tipo === 'salida_venta' ? `-${mov.cantidad}` : `+${mov.cantidad}`}{' '}
                                                        <span className="text-[10px] font-normal text-[#8A8680]">
                                                            {mov.producto.unidad_medida}
                                                        </span>
                                                    </span>
                                                    <div className="flex items-center gap-1 text-[#8A8680] text-[10.5px]">
                                                        {mov.usuario && (
                                                            <span className="flex items-center gap-0.5">
                                                                <User className="size-3" />
                                                                {mov.usuario}
                                                            </span>
                                                        )}
                                                        <span>•</span>
                                                        <span>{formatDate(mov.fecha)}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </Card>
                    </div>

                    {/* Lista detallada de productos bajo stock mínimo (1 col) */}
                    <div className="lg:col-span-1">
                        <Card className="rounded-[16px] border-[#E7E4DE] bg-white p-5 shadow-none">
                            <div className="flex items-center justify-between border-b border-[#F1EFEC] pb-4">
                                <span className="text-[14px] font-bold text-[#201F1D]">
                                    Stock bajo el mínimo
                                </span>
                                <span className="rounded-full bg-[#FBEAE9] px-2 py-0.5 text-[11px] font-bold text-[#E31E24]">
                                    {productos_bajo_minimo.length}
                                </span>
                            </div>

                            {productos_bajo_minimo.length === 0 ? (
                                <div className="flex min-h-[220px] flex-col items-center justify-center text-center">
                                    <PackageCheck className="size-10 text-[#1E8E5A]/40" />
                                    <p className="mt-2 text-sm font-medium text-[#1E8E5A]">
                                        Todos los productos cumplen con el stock mínimo.
                                    </p>
                                </div>
                            ) : (
                                <div className="divide-y divide-[#F1EFEC]">
                                    {productos_bajo_minimo.map((prod) => (
                                        <div key={prod.id} className="py-3 text-[12px]">
                                            <div className="flex items-center justify-between">
                                                <span className="font-bold text-[#201F1D] truncate max-w-[180px]">
                                                    {prod.nombre}
                                                </span>
                                                <span className="font-mono text-[11px] text-[#E31E24] font-bold">
                                                    Faltan {prod.diferencia}
                                                </span>
                                            </div>
                                            <div className="mt-1 flex items-center justify-between text-[11px] text-[#8A8680]">
                                                <span>Cód: {prod.codigo}</span>
                                                <span>
                                                    Disp: <b className="text-[#201F1D]">{prod.stock_disponible}</b> / Mín: {prod.stock_minimo}
                                                </span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </Card>
                    </div>
                </div>
            </div>
        </AlmacenLayout>
    );
}
