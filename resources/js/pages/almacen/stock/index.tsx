import { Head, router } from '@inertiajs/react';
import {
    ArrowUpDown,
    Boxes,
    Building2,
    Calendar,
    CheckCircle2,
    Clock,
    Filter,
    History,
    Layers,
    Package,
    RotateCcw,
    ScanBarcode,
    Search,
    TrendingDown,
    User,
    Wrench,
} from 'lucide-react';
import { FormEvent, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AlmacenLayout from '@/layouts/almacen-layout';

export type SedeInfo = {
    id: number;
    nombre: string;
    tipo: string;
    ciudad: string | null;
};

export type StockItem = {
    id: number;
    tipo: 'producto' | 'servicio';
    codigo: string;
    nombre: string;
    unidad_medida: string;
    precio_venta: number;
    serializado: boolean;
    stock_minimo: number | null;
    stock_disponible_total: number | null;
    stock_por_sede: Record<number, number | null>;
};

export type KardexItem = {
    id: number;
    fecha: string | null;
    tipo: 'ingreso' | 'salida_venta' | 'salida_servicio' | 'ajuste' | 'traslado' | string;
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

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type PaginatedKardex = {
    data: KardexItem[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    total: number;
};

export type StockPageProps = {
    items: StockItem[];
    sedes: SedeInfo[];
    filters: {
        search: string;
        tipo: string;
    };
    kardex: PaginatedKardex;
    kardex_filters: {
        product_id: number | null;
        sede_id: number | null;
        fecha_desde: string | null;
        fecha_hasta: string | null;
        tipo: string;
    };
    product_list: Array<{ id: number; codigo: string; nombre: string }>;
    kpis: {
        total_productos: number;
        total_servicios: number;
        unidades_en_stock: number;
        bajo_minimo: number;
    };
};

function formatCurrency(amount: number): string {
    return `S/ ${amount.toLocaleString('es-PE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
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

function getTipoBadge(tipo: 'producto' | 'servicio') {
    if (tipo === 'producto') {
        return (
            <span className="inline-flex items-center gap-1 rounded-[6px] border border-[#BFDBFE] bg-[#EFF6FF] px-2 py-0.5 text-[10.5px] font-bold text-[#2563EB]">
                <Package className="size-3" />
                Producto
            </span>
        );
    }
    return (
        <span className="inline-flex items-center gap-1 rounded-[6px] border border-[#DDD6FE] bg-[#F5F3FF] px-2 py-0.5 text-[10.5px] font-bold text-[#7C3AED]">
            <Wrench className="size-3" />
            Servicio
        </span>
    );
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
        case 'salida_servicio':
            return {
                label: 'Consumo Taller',
                className: 'bg-[#F5F3FF] text-[#7C3AED] border-[#DDD6FE]',
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

export default function StockIndex({
    items,
    sedes,
    filters,
    kardex,
    kardex_filters,
    product_list,
    kpis,
}: StockPageProps) {
    const [activeTab, setActiveTab] = useState<'stock' | 'kardex'>('stock');

    // Filtros de Stock
    const [stockSearch, setStockSearch] = useState(filters.search || '');
    const [stockTipo, setStockTipo] = useState(filters.tipo || 'todos');

    // Filtros de Kardex
    const [kProductId, setKProductId] = useState(kardex_filters.product_id ? String(kardex_filters.product_id) : '');
    const [kSedeId, setKSedeId] = useState(kardex_filters.sede_id ? String(kardex_filters.sede_id) : '');
    const [kFechaDesde, setKFechaDesde] = useState(kardex_filters.fecha_desde || '');
    const [kFechaHasta, setKFechaHasta] = useState(kardex_filters.fecha_hasta || '');
    const [kTipo, setKTipo] = useState(kardex_filters.tipo || 'todos');

    // Búsqueda en Stock
    const handleStockFilter = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            window.location.pathname,
            {
                search: stockSearch || undefined,
                tipo: stockTipo !== 'todos' ? stockTipo : undefined,
                // Mantener filtros de kardex en la URL
                kardex_product_id: kardex_filters.product_id || undefined,
                kardex_sede_id: kardex_filters.sede_id || undefined,
                kardex_fecha_desde: kardex_filters.fecha_desde || undefined,
                kardex_fecha_hasta: kardex_filters.fecha_hasta || undefined,
                kardex_tipo: kardex_filters.tipo !== 'todos' ? kardex_filters.tipo : undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const handleResetStock = () => {
        setStockSearch('');
        setStockTipo('todos');
        router.get(
            window.location.pathname,
            {
                kardex_product_id: kardex_filters.product_id || undefined,
                kardex_sede_id: kardex_filters.sede_id || undefined,
                kardex_fecha_desde: kardex_filters.fecha_desde || undefined,
                kardex_fecha_hasta: kardex_filters.fecha_hasta || undefined,
                kardex_tipo: kardex_filters.tipo !== 'todos' ? kardex_filters.tipo : undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    // Búsqueda en Kardex
    const handleKardexFilter = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            window.location.pathname,
            {
                search: filters.search || undefined,
                tipo: filters.tipo !== 'todos' ? filters.tipo : undefined,
                kardex_product_id: kProductId ? Number(kProductId) : undefined,
                kardex_sede_id: kSedeId ? Number(kSedeId) : undefined,
                kardex_fecha_desde: kFechaDesde || undefined,
                kardex_fecha_hasta: kFechaHasta || undefined,
                kardex_tipo: kTipo !== 'todos' ? kTipo : undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const handleResetKardex = () => {
        setKProductId('');
        setKSedeId('');
        setKFechaDesde('');
        setKFechaHasta('');
        setKTipo('todos');
        router.get(
            window.location.pathname,
            {
                search: filters.search || undefined,
                tipo: filters.tipo !== 'todos' ? filters.tipo : undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <AlmacenLayout title="Stock y Kardex">
            <Head title="Stock y Kardex - Almacén" />

            <div className="flex flex-col gap-6">
                {/* 4 KPI Cards */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card className="rounded-[16px] border-[#E7E4DE] bg-white p-5 shadow-none">
                        <div className="flex items-center justify-between text-[#8A8680]">
                            <span className="text-xs font-bold uppercase tracking-wider">Productos en catálogo</span>
                            <Package className="size-4 text-[#201F1D]" />
                        </div>
                        <div className="mt-3 font-['Oswald',sans-serif] text-[28px] font-semibold text-[#201F1D]">
                            {kpis.total_productos}
                        </div>
                        <div className="mt-1 text-[11.5px] text-[#8A8680]">
                            Productos físicos y repuestos
                        </div>
                    </Card>

                    <Card className="rounded-[16px] border-[#E7E4DE] bg-white p-5 shadow-none">
                        <div className="flex items-center justify-between text-[#8A8680]">
                            <span className="text-xs font-bold uppercase tracking-wider">Servicios en catálogo</span>
                            <Wrench className="size-4 text-[#7C3AED]" />
                        </div>
                        <div className="mt-3 font-['Oswald',sans-serif] text-[28px] font-semibold text-[#201F1D]">
                            {kpis.total_servicios}
                        </div>
                        <div className="mt-1 text-[11.5px] text-[#8A8680]">
                            Recargas, pruebas y mantenimientos
                        </div>
                    </Card>

                    <Card className="rounded-[16px] border-[#E7E4DE] bg-white p-5 shadow-none">
                        <div className="flex items-center justify-between text-[#8A8680]">
                            <span className="text-xs font-bold uppercase tracking-wider">Unidades disponibles</span>
                            <Boxes className="size-4 text-[#1E8E5A]" />
                        </div>
                        <div className="mt-3 font-['Oswald',sans-serif] text-[28px] font-semibold text-[#1E8E5A]">
                            {kpis.unidades_en_stock}
                        </div>
                        <div className="mt-1 text-[11.5px] text-[#8A8680]">
                            Total en todos los almacenes
                        </div>
                    </Card>

                    <Card className="rounded-[16px] border-[#E7E4DE] bg-white p-5 shadow-none">
                        <div className="flex items-center justify-between text-[#8A8680]">
                            <span className="text-xs font-bold uppercase tracking-wider">Bajo stock mínimo</span>
                            <TrendingDown className="size-4 text-[#E31E24]" />
                        </div>
                        <div className="mt-3 font-['Oswald',sans-serif] text-[28px] font-semibold text-[#E31E24]">
                            {kpis.bajo_minimo}
                        </div>
                        <div className="mt-1 text-[11.5px] text-[#8A8680]">
                            Requieren reabastecimiento
                        </div>
                    </Card>
                </div>

                {/* Tabs Switcher: [Stock Actual] | [Movimientos Kardex] */}
                <div className="flex items-center gap-2 border-b border-[#E7E4DE] pb-2">
                    <button
                        type="button"
                        onClick={() => setActiveTab('stock')}
                        className={[
                            'flex items-center gap-2 rounded-lg px-4 py-2 text-[13px] font-bold transition-all',
                            activeTab === 'stock'
                                ? 'bg-[#18181B] text-white shadow-xs'
                                : 'text-[#6B6965] hover:bg-[#F3F1ED] hover:text-[#201F1D]',
                        ].join(' ')}
                    >
                        <Boxes className="size-4" />
                        <span>Stock de Productos y Servicios</span>
                        <span className="rounded-full bg-white/20 px-2 py-0.5 text-[10.5px]">
                            {items.length}
                        </span>
                    </button>

                    <button
                        type="button"
                        onClick={() => setActiveTab('kardex')}
                        className={[
                            'flex items-center gap-2 rounded-lg px-4 py-2 text-[13px] font-bold transition-all',
                            activeTab === 'kardex'
                                ? 'bg-[#18181B] text-white shadow-xs'
                                : 'text-[#6B6965] hover:bg-[#F3F1ED] hover:text-[#201F1D]',
                        ].join(' ')}
                    >
                        <History className="size-4" />
                        <span>Historial de Kardex</span>
                        <span className="rounded-full bg-white/20 px-2 py-0.5 text-[10.5px]">
                            {kardex.total}
                        </span>
                    </button>
                </div>

                {/* TAB 1: STOCK Y CATÁLOGO */}
                {activeTab === 'stock' && (
                    <Card className="rounded-[16px] border-[#E7E4DE] bg-white p-5 shadow-none">
                        {/* Filters Bar */}
                        <form onSubmit={handleStockFilter} className="flex flex-wrap items-end gap-3 pb-5 border-b border-[#F1EFEC]">
                            <div className="flex-1 min-w-[220px]">
                                <Label className="text-xs font-bold text-[#4A4742]">Buscar ítem</Label>
                                <div className="relative mt-1">
                                    <Search className="absolute top-2.5 left-3 size-4 text-[#8A8680]" />
                                    <Input
                                        value={stockSearch}
                                        onChange={(e) => setStockSearch(e.target.value)}
                                        placeholder="Código o nombre del producto..."
                                        className="h-9 pl-9 text-xs"
                                    />
                                </div>
                            </div>

                            <div className="w-[180px]">
                                <Label className="text-xs font-bold text-[#4A4742]">Tipo de ítem</Label>
                                <select
                                    value={stockTipo}
                                    onChange={(e) => setStockTipo(e.target.value)}
                                    className="mt-1 h-9 w-full rounded-md border border-[#E4E1DC] bg-white px-3 text-xs text-[#201F1D] focus:border-[#E31E24] focus:outline-none"
                                >
                                    <option value="todos">Todos los tipos</option>
                                    <option value="producto">Solo Productos</option>
                                    <option value="servicio">Solo Servicios</option>
                                </select>
                            </div>

                            <div className="flex items-center gap-2">
                                <Button type="submit" size="sm" className="h-9 gap-1.5 bg-[#18181B] text-white hover:bg-[#27272A]">
                                    <Filter className="size-3.5" />
                                    <span>Filtrar</span>
                                </Button>
                                {(stockSearch !== '' || stockTipo !== 'todos') && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={handleResetStock}
                                        className="h-9 gap-1.5 text-[#6B6965]"
                                    >
                                        <RotateCcw className="size-3.5" />
                                        <span>Limpiar</span>
                                    </Button>
                                )}
                            </div>
                        </form>

                        {/* Stock Table */}
                        {items.length === 0 ? (
                            <div className="flex min-h-[260px] flex-col items-center justify-center text-center">
                                <Package className="size-10 text-[#D5D2CB]" />
                                <p className="mt-2 text-sm font-medium text-[#8A8680]">
                                    No se encontraron productos ni servicios con los filtros indicados.
                                </p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto mt-4">
                                <table className="w-full text-left text-[12.5px]">
                                    <thead>
                                        <tr className="border-b border-[#E7E4DE] text-[11px] font-bold text-[#8A8680] uppercase tracking-wider">
                                            <th className="py-2.5 pr-4">Código</th>
                                            <th className="py-2.5 px-4">Ítem / Descripción</th>
                                            <th className="py-2.5 px-3">Tipo</th>
                                            <th className="py-2.5 px-3 text-center">U.M.</th>
                                            <th className="py-2.5 px-3 text-right">Precio Ref.</th>
                                            {sedes.map((sede) => (
                                                <th key={sede.id} className="py-2.5 px-3 text-center">
                                                    <div className="flex items-center justify-center gap-1">
                                                        <Building2 className="size-3 text-[#8A8680]" />
                                                        <span>{sede.nombre}</span>
                                                    </div>
                                                </th>
                                            ))}
                                            <th className="py-2.5 pl-4 text-center">Stock Total</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-[#F1EFEC]">
                                        {items.map((item) => {
                                            const isBajoMinimo =
                                                item.tipo === 'producto' &&
                                                item.stock_minimo !== null &&
                                                item.stock_minimo > 0 &&
                                                (item.stock_disponible_total ?? 0) <= item.stock_minimo;

                                            return (
                                                <tr key={`${item.tipo}-${item.id}`} className="hover:bg-[#FAFAF8] transition-colors">
                                                    <td className="py-3 pr-4 font-mono font-bold text-[#201F1D]">
                                                        {item.codigo}
                                                    </td>
                                                    <td className="py-3 px-4">
                                                        <div className="font-bold text-[#201F1D]">{item.nombre}</div>
                                                        {item.serializado && (
                                                            <div className="flex items-center gap-1 text-[10.5px] text-[#6B6965] mt-0.5">
                                                                <ScanBarcode className="size-3 text-[#1E8E5A]" />
                                                                <span>Unidad serializada con código de barras</span>
                                                            </div>
                                                        )}
                                                    </td>
                                                    <td className="py-3 px-3">
                                                        {getTipoBadge(item.tipo)}
                                                    </td>
                                                    <td className="py-3 px-3 text-center font-mono text-[#6B6965]">
                                                        {item.unidad_medida}
                                                    </td>
                                                    <td className="py-3 px-3 text-right font-mono font-medium text-[#201F1D]">
                                                        {formatCurrency(item.precio_venta)}
                                                    </td>

                                                    {/* Stock por sede */}
                                                    {sedes.map((sede) => {
                                                        const qty = item.stock_por_sede[sede.id];
                                                        return (
                                                            <td key={sede.id} className="py-3 px-3 text-center">
                                                                {item.tipo === 'servicio' ? (
                                                                    <span className="font-mono text-[11px] text-[#B9B7B2]">N/A</span>
                                                                ) : (
                                                                    <span
                                                                        className={[
                                                                            'font-mono font-bold text-[12px]',
                                                                            qty && qty > 0 ? 'text-[#201F1D]' : 'text-[#B9B7B2]',
                                                                        ].join(' ')}
                                                                    >
                                                                        {qty ?? 0}
                                                                    </span>
                                                                )}
                                                            </td>
                                                        );
                                                    })}

                                                    {/* Stock Total */}
                                                    <td className="py-3 pl-4 text-center">
                                                        {item.tipo === 'servicio' ? (
                                                            <span className="font-mono text-[11px] text-[#B9B7B2]">N/A</span>
                                                        ) : (
                                                            <div className="flex flex-col items-center">
                                                                <span
                                                                    className={[
                                                                        'inline-flex items-center rounded-full px-2.5 py-0.5 font-mono text-[12px] font-bold',
                                                                        isBajoMinimo
                                                                            ? 'bg-[#FBEAE9] text-[#E31E24] border border-[#F5C6C5]'
                                                                            : 'bg-[#E5F5EC] text-[#1E8E5A] border border-[#C3E8D4]',
                                                                    ].join(' ')}
                                                                >
                                                                    {item.stock_disponible_total ?? 0}
                                                                </span>
                                                                {item.stock_minimo !== null && item.stock_minimo > 0 && (
                                                                    <span className="text-[10px] text-[#8A8680] mt-0.5">
                                                                        Mín: {item.stock_minimo}
                                                                    </span>
                                                                )}
                                                            </div>
                                                        )}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Card>
                )}

                {/* TAB 2: HISTORIAL DE KARDEX */}
                {activeTab === 'kardex' && (
                    <Card className="rounded-[16px] border-[#E7E4DE] bg-white p-5 shadow-none">
                        {/* Kardex Filters Bar */}
                        <form onSubmit={handleKardexFilter} className="flex flex-wrap items-end gap-3 pb-5 border-b border-[#F1EFEC]">
                            <div className="w-[200px]">
                                <Label className="text-xs font-bold text-[#4A4742]">Producto</Label>
                                <select
                                    value={kProductId}
                                    onChange={(e) => setKProductId(e.target.value)}
                                    className="mt-1 h-9 w-full rounded-md border border-[#E4E1DC] bg-white px-3 text-xs text-[#201F1D] focus:border-[#E31E24] focus:outline-none"
                                >
                                    <option value="">Todos los productos</option>
                                    {product_list.map((prod) => (
                                        <option key={prod.id} value={prod.id}>
                                            {prod.codigo} - {prod.nombre}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="w-[160px]">
                                <Label className="text-xs font-bold text-[#4A4742]">Sede / Almacén</Label>
                                <select
                                    value={kSedeId}
                                    onChange={(e) => setKSedeId(e.target.value)}
                                    className="mt-1 h-9 w-full rounded-md border border-[#E4E1DC] bg-white px-3 text-xs text-[#201F1D] focus:border-[#E31E24] focus:outline-none"
                                >
                                    <option value="">Todas las sedes</option>
                                    {sedes.map((sede) => (
                                        <option key={sede.id} value={sede.id}>
                                            {sede.nombre}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="w-[150px]">
                                <Label className="text-xs font-bold text-[#4A4742]">Tipo movimiento</Label>
                                <select
                                    value={kTipo}
                                    onChange={(e) => setKTipo(e.target.value)}
                                    className="mt-1 h-9 w-full rounded-md border border-[#E4E1DC] bg-white px-3 text-xs text-[#201F1D] focus:border-[#E31E24] focus:outline-none"
                                >
                                    <option value="todos">Todos los tipos</option>
                                    <option value="ingreso">Ingreso (Recepción)</option>
                                    <option value="salida_venta">Salida Venta</option>
                                    <option value="salida_servicio">Consumo en Taller</option>
                                    <option value="ajuste">Ajuste de inventario</option>
                                    <option value="traslado">Traslado entre sedes</option>
                                </select>
                            </div>

                            <div className="w-[130px]">
                                <Label className="text-xs font-bold text-[#4A4742]">Desde</Label>
                                <Input
                                    type="date"
                                    value={kFechaDesde}
                                    onChange={(e) => setKFechaDesde(e.target.value)}
                                    className="mt-1 h-9 text-xs"
                                />
                            </div>

                            <div className="w-[130px]">
                                <Label className="text-xs font-bold text-[#4A4742]">Hasta</Label>
                                <Input
                                    type="date"
                                    value={kFechaHasta}
                                    onChange={(e) => setKFechaHasta(e.target.value)}
                                    className="mt-1 h-9 text-xs"
                                />
                            </div>

                            <div className="flex items-center gap-2">
                                <Button type="submit" size="sm" className="h-9 gap-1.5 bg-[#18181B] text-white hover:bg-[#27272A]">
                                    <Filter className="size-3.5" />
                                    <span>Filtrar</span>
                                </Button>
                                {(kProductId !== '' || kSedeId !== '' || kTipo !== 'todos' || kFechaDesde !== '' || kFechaHasta !== '') && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={handleResetKardex}
                                        className="h-9 gap-1.5 text-[#6B6965]"
                                    >
                                        <RotateCcw className="size-3.5" />
                                        <span>Limpiar</span>
                                    </Button>
                                )}
                            </div>
                        </form>

                        {/* Kardex Records Table */}
                        {kardex.data.length === 0 ? (
                            <div className="flex min-h-[260px] flex-col items-center justify-center text-center">
                                <History className="size-10 text-[#D5D2CB]" />
                                <p className="mt-2 text-sm font-medium text-[#8A8680]">
                                    No se encontraron movimientos registrados en el Kardex con estos filtros.
                                </p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto mt-4">
                                <table className="w-full text-left text-[12.5px]">
                                    <thead>
                                        <tr className="border-b border-[#E7E4DE] text-[11px] font-bold text-[#8A8680] uppercase tracking-wider">
                                            <th className="py-2.5 pr-4">Fecha</th>
                                            <th className="py-2.5 px-3">Tipo</th>
                                            <th className="py-2.5 px-4">Producto</th>
                                            <th className="py-2.5 px-3">Unidad / Serie</th>
                                            <th className="py-2.5 px-3">Sede</th>
                                            <th className="py-2.5 px-3 text-right">Cantidad</th>
                                            <th className="py-2.5 px-4">Usuario</th>
                                            <th className="py-2.5 pl-4">Observación</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-[#F1EFEC]">
                                        {kardex.data.map((mov) => {
                                            const badge = getTipoMovimientoBadge(mov.tipo);
                                            const isSalida = mov.tipo === 'salida_venta';

                                            return (
                                                <tr key={mov.id} className="hover:bg-[#FAFAF8] transition-colors">
                                                    <td className="py-3 pr-4 font-mono text-[11.5px] text-[#4A4742] whitespace-nowrap">
                                                        {formatDate(mov.fecha)}
                                                    </td>
                                                    <td className="py-3 px-3">
                                                        <span
                                                            className={`inline-flex items-center rounded-[6px] border px-2 py-0.5 text-[10.5px] font-bold ${badge.className}`}
                                                        >
                                                            {badge.label}
                                                        </span>
                                                    </td>
                                                    <td className="py-3 px-4">
                                                        <div className="font-bold text-[#201F1D]">
                                                            {mov.producto.nombre}
                                                        </div>
                                                        <div className="font-mono text-[11px] text-[#8A8680]">
                                                            Cód: {mov.producto.codigo}
                                                        </div>
                                                    </td>
                                                    <td className="py-3 px-3">
                                                        {mov.unidad_serie ? (
                                                            <span className="flex items-center gap-1 font-mono text-[11.5px] font-bold text-[#201F1D]">
                                                                <ScanBarcode className="size-3 text-[#1E8E5A]" />
                                                                {mov.unidad_serie}
                                                            </span>
                                                        ) : (
                                                            <span className="text-[11px] text-[#B9B7B2]">—</span>
                                                        )}
                                                    </td>
                                                    <td className="py-3 px-3 text-[#4A4742]">
                                                        {mov.sede || '—'}
                                                    </td>
                                                    <td className="py-3 px-3 text-right font-mono font-bold text-[12.5px]">
                                                        <span className={isSalida ? 'text-[#E31E24]' : 'text-[#1E8E5A]'}>
                                                            {isSalida ? `-${mov.cantidad}` : `+${mov.cantidad}`}
                                                        </span>{' '}
                                                        <span className="text-[10px] font-normal text-[#8A8680]">
                                                            {mov.producto.unidad_medida}
                                                        </span>
                                                    </td>
                                                    <td className="py-3 px-4 text-[#6B6965] text-[11.5px]">
                                                        {mov.usuario || 'Sistema'}
                                                    </td>
                                                    <td className="py-3 pl-4 text-[#8A8680] text-[11.5px] max-w-[200px] truncate">
                                                        {mov.observacion || '—'}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>

                                {/* Paginador del Kardex */}
                                {kardex.links.length > 3 && (
                                    <div className="mt-4 flex items-center justify-between border-t border-[#F1EFEC] pt-4 text-xs text-[#8A8680]">
                                        <span>
                                            Mostrando <b>{kardex.data.length}</b> de <b>{kardex.total}</b> movimientos
                                        </span>
                                        <div className="flex items-center gap-1">
                                            {kardex.links.map((link, idx) => (
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
                )}
            </div>
        </AlmacenLayout>
    );
}
