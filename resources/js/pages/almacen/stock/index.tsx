import { Head, router } from '@inertiajs/react';
import {
    Boxes,
    Building2,
    Filter,
    History,
    Package,
    RotateCcw,
    ScanBarcode,
    Search,
    TrendingDown,
} from 'lucide-react';
import { FormEvent, useState } from 'react';

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
    tipo: 'producto';
    codigo: string;
    nombre: string;
    unidad_medida: string;
    precio_venta: number;
    serializado: boolean;
    controla_lote?: boolean;
    stock_minimo: number | null;
    stock_disponible_total: number | null;
    stock_por_sede: Record<number, number | null>;
    lotes?: {
        lote: string;
        sede: string;
        fecha_vencimiento: string | null;
        vencido: boolean;
        por_vencer: boolean;
        saldo: number;
    }[];
};

export type KardexItem = {
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

export type PaginatedStock = {
    data: StockItem[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    total: number;
};

export type StockPageProps = {
    items: PaginatedStock;
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

function getTipoBadge() {
    return (
        <span className="inline-flex items-center gap-1 rounded-[6px] border border-sky-500/20 bg-sky-500/10 px-2 py-0.5 text-[10.5px] font-bold text-blue-600 dark:text-blue-400">
            <Package className="size-3" /> Producto
        </span>
    );
}

function getTipoMovimientoBadge(tipo: string): {
    label: string;
    className: string;
} {
    switch (tipo) {
        case 'ingreso':
            return {
                label: 'Ingreso',
                className:
                    'bg-emerald-500/10 text-success-strong border border-emerald-500/20 border-emerald-500/20',
            };
        case 'salida_venta':
            return {
                label: 'Venta',
                className:
                    'bg-destructive/10 text-primary-strong border-destructive/20',
            };
        case 'salida_servicio':
            return {
                label: 'Consumo Taller',
                className:
                    'bg-purple-500/10 text-purple-600 dark:text-purple-400 border-purple-500/20',
            };
        case 'ajuste':
            return {
                label: 'Ajuste',
                className:
                    'bg-amber-500/10 text-warning-strong border-amber-500/20',
            };
        case 'traslado':
            return {
                label: 'Traslado',
                className:
                    'bg-sky-500/10 text-blue-600 dark:text-blue-400 border-sky-500/20',
            };
        default:
            return {
                label: tipo,
                className: 'bg-muted text-muted-foreground border-border',
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
    const [kProductId, setKProductId] = useState(
        kardex_filters.product_id ? String(kardex_filters.product_id) : '',
    );
    const [kSedeId, setKSedeId] = useState(
        kardex_filters.sede_id ? String(kardex_filters.sede_id) : '',
    );
    const [kFechaDesde, setKFechaDesde] = useState(
        kardex_filters.fecha_desde || '',
    );
    const [kFechaHasta, setKFechaHasta] = useState(
        kardex_filters.fecha_hasta || '',
    );
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
                kardex_tipo:
                    kardex_filters.tipo !== 'todos'
                        ? kardex_filters.tipo
                        : undefined,
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
                kardex_tipo:
                    kardex_filters.tipo !== 'todos'
                        ? kardex_filters.tipo
                        : undefined,
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
                <div className="grid gap-4 sm:grid-cols-3">
                    <Card className="border-border bg-card rounded-[16px] p-5 shadow-none">
                        <div className="text-muted-foreground flex items-center justify-between">
                            <span className="text-xs font-bold tracking-wider uppercase">
                                Productos en catálogo
                            </span>
                            <Package className="text-foreground size-4" />
                        </div>
                        <div className="text-foreground mt-3 font-['Oswald',sans-serif] text-[28px] font-semibold">
                            {kpis.total_productos}
                        </div>
                        <div className="text-muted-foreground mt-1 text-[11.5px]">
                            Productos físicos y repuestos
                        </div>
                    </Card>

                    <Card className="border-border bg-card rounded-[16px] p-5 shadow-none">
                        <div className="text-muted-foreground flex items-center justify-between">
                            <span className="text-xs font-bold tracking-wider uppercase">
                                Unidades disponibles
                            </span>
                            <Boxes className="text-success-strong size-4" />
                        </div>
                        <div className="text-success-strong mt-3 font-['Oswald',sans-serif] text-[28px] font-semibold">
                            {kpis.unidades_en_stock}
                        </div>
                        <div className="text-muted-foreground mt-1 text-[11.5px]">
                            Total en todos los almacenes
                        </div>
                    </Card>

                    <Card className="border-border bg-card rounded-[16px] p-5 shadow-none">
                        <div className="text-muted-foreground flex items-center justify-between">
                            <span className="text-xs font-bold tracking-wider uppercase">
                                Bajo stock mínimo
                            </span>
                            <TrendingDown className="text-primary-strong size-4" />
                        </div>
                        <div className="text-primary-strong mt-3 font-['Oswald',sans-serif] text-[28px] font-semibold">
                            {kpis.bajo_minimo}
                        </div>
                        <div className="text-muted-foreground mt-1 text-[11.5px]">
                            Requieren reabastecimiento
                        </div>
                    </Card>
                </div>

                {/* Tabs Switcher: [Stock Actual] | [Movimientos Kardex] */}
                <div className="border-border flex items-center gap-2 border-b pb-2">
                    <button
                        type="button"
                        onClick={() => setActiveTab('stock')}
                        className={[
                            'flex items-center gap-2 rounded-lg px-4 py-2 text-[13px] font-bold transition-all',
                            activeTab === 'stock'
                                ? 'bg-foreground text-background shadow-xs'
                                : 'text-muted-foreground hover:bg-background hover:text-foreground',
                        ].join(' ')}
                    >
                        <Boxes className="size-4" />
                        <span>Stock de Productos y Servicios</span>
                        <span className="bg-card/20 rounded-full px-2 py-0.5 text-[10.5px]">
                            {items.total}
                        </span>
                    </button>

                    <button
                        type="button"
                        onClick={() => setActiveTab('kardex')}
                        className={[
                            'flex items-center gap-2 rounded-lg px-4 py-2 text-[13px] font-bold transition-all',
                            activeTab === 'kardex'
                                ? 'bg-foreground text-background shadow-xs'
                                : 'text-muted-foreground hover:bg-background hover:text-foreground',
                        ].join(' ')}
                    >
                        <History className="size-4" />
                        <span>Historial de Kardex</span>
                        <span className="bg-card/20 rounded-full px-2 py-0.5 text-[10.5px]">
                            {kardex.total}
                        </span>
                    </button>
                </div>

                {/* TAB 1: STOCK Y CATÁLOGO */}
                {activeTab === 'stock' && (
                    <Card className="border-border bg-card rounded-[16px] p-5 shadow-none">
                        {/* Filters Bar */}
                        <form
                            onSubmit={handleStockFilter}
                            className="border-border flex flex-wrap items-end gap-3 border-b pb-5"
                        >
                            <div className="min-w-[220px] flex-1">
                                <Label className="text-foreground/80 text-xs font-bold">
                                    Buscar ítem
                                </Label>
                                <div className="relative mt-1">
                                    <Search className="text-muted-foreground absolute top-2.5 left-3 size-4" />
                                    <Input
                                        value={stockSearch}
                                        onChange={(e) =>
                                            setStockSearch(e.target.value)
                                        }
                                        placeholder="Código o nombre del producto..."
                                        className="h-9 pl-9 text-xs"
                                    />
                                </div>
                            </div>

                            <div className="w-[180px]">
                                <Label
                                    htmlFor="almacen-stock-tipo-de-item"
                                    className="text-foreground/80 text-xs font-bold"
                                >
                                    Tipo de ítem
                                </Label>
                                <select
                                    id="almacen-stock-tipo-de-item"
                                    value={stockTipo}
                                    onChange={(e) =>
                                        setStockTipo(e.target.value)
                                    }
                                    className="border-border bg-card text-foreground focus:border-primary mt-1 h-9 w-full rounded-md border px-3 text-xs focus:outline-none"
                                >
                                    <option value="todos">
                                        Todos los tipos
                                    </option>
                                    <option value="producto">
                                        Solo Productos
                                    </option>
                                </select>
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
                                {(stockSearch !== '' ||
                                    stockTipo !== 'todos') && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={handleResetStock}
                                        className="text-muted-foreground h-9 gap-1.5"
                                    >
                                        <RotateCcw className="size-3.5" />
                                        <span>Limpiar</span>
                                    </Button>
                                )}
                            </div>
                        </form>

                        {/* Stock Table */}
                        {items.data.length === 0 ? (
                            <div className="flex min-h-[260px] flex-col items-center justify-center p-8 text-center">
                                <Package className="text-muted-foreground/60 size-10" />
                                <p className="text-foreground mt-2 text-sm font-semibold">
                                    No se encontraron productos en el inventario
                                </p>
                                <p className="text-muted-foreground mt-1 max-w-sm text-xs">
                                    {stockSearch || stockTipo !== 'todos'
                                        ? 'No hay productos que coincidan con los filtros aplicados.'
                                        : 'Aún no hay productos registrados en las sedes seleccionadas.'}
                                </p>
                                {(stockSearch !== '' ||
                                    stockTipo !== 'todos') && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={handleResetStock}
                                        className="border-border text-foreground hover:bg-muted mt-4 gap-1.5 text-xs font-semibold"
                                    >
                                        <RotateCcw className="size-3.5" />
                                        <span>Limpiar filtros</span>
                                    </Button>
                                )}
                            </div>
                        ) : (
                            <div className="mt-4 overflow-x-auto">
                                <table className="w-full text-left text-[12.5px]">
                                    <thead>
                                        <tr className="border-border text-muted-foreground border-b text-[11px] font-bold tracking-wider uppercase">
                                            <th className="py-2.5 pr-4">
                                                Código
                                            </th>
                                            <th className="px-4 py-2.5">
                                                Ítem / Descripción
                                            </th>
                                            <th className="px-3 py-2.5">
                                                Tipo
                                            </th>
                                            <th className="px-3 py-2.5 text-center">
                                                U.M.
                                            </th>
                                            <th className="px-3 py-2.5 text-right">
                                                Precio Ref.
                                            </th>
                                            {sedes.map((sede) => (
                                                <th
                                                    key={sede.id}
                                                    className="px-3 py-2.5 text-center"
                                                >
                                                    <div className="flex items-center justify-center gap-1">
                                                        <Building2 className="text-muted-foreground size-3" />
                                                        <span>
                                                            {sede.nombre}
                                                        </span>
                                                    </div>
                                                </th>
                                            ))}
                                            <th className="py-2.5 pl-4 text-center">
                                                Stock Total
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-border divide-y">
                                        {items.data.map((item) => {
                                            const isBajoMinimo =
                                                item.tipo === 'producto' &&
                                                item.stock_minimo !== null &&
                                                item.stock_minimo > 0 &&
                                                (item.stock_disponible_total ??
                                                    0) <= item.stock_minimo;

                                            return (
                                                <tr
                                                    key={`${item.tipo}-${item.id}`}
                                                    className="hover:bg-muted/40 transition-colors"
                                                >
                                                    <td className="text-foreground py-3 pr-4 font-mono font-bold">
                                                        {item.codigo}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <div className="text-foreground font-bold">
                                                            {item.nombre}
                                                        </div>
                                                        {item.serializado && (
                                                            <div className="text-muted-foreground mt-0.5 flex items-center gap-1 text-[10.5px]">
                                                                <ScanBarcode className="text-success-strong size-3" />
                                                                <span>
                                                                    Unidad
                                                                    serializada
                                                                    con código
                                                                    de barras
                                                                </span>
                                                            </div>
                                                        )}
                                                        {(item.lotes ?? []).map(
                                                            (lote) => (
                                                                <div
                                                                    key={`${lote.sede}-${lote.lote}`}
                                                                    className={`mt-0.5 text-[10.5px] ${
                                                                        lote.vencido
                                                                            ? 'text-destructive-strong font-bold'
                                                                            : lote.por_vencer
                                                                              ? 'text-warning-strong font-semibold'
                                                                              : 'text-muted-foreground'
                                                                    }`}
                                                                >
                                                                    Lote{' '}
                                                                    {lote.lote}{' '}
                                                                    ·{' '}
                                                                    {lote.saldo}{' '}
                                                                    ·{' '}
                                                                    {lote.vencido
                                                                        ? 'VENCIDO'
                                                                        : 'vence'}{' '}
                                                                    {lote.fecha_vencimiento
                                                                        ? lote.fecha_vencimiento
                                                                              .split(
                                                                                  '-',
                                                                              )
                                                                              .reverse()
                                                                              .join(
                                                                                  '/',
                                                                              )
                                                                        : '—'}
                                                                    {sedes.length >
                                                                    1
                                                                        ? ` (${lote.sede})`
                                                                        : ''}
                                                                </div>
                                                            ),
                                                        )}
                                                    </td>
                                                    <td className="px-3 py-3">
                                                        {getTipoBadge()}
                                                    </td>
                                                    <td className="text-muted-foreground px-3 py-3 text-center font-mono">
                                                        {item.unidad_medida}
                                                    </td>
                                                    <td className="text-foreground px-3 py-3 text-right font-mono font-medium">
                                                        {formatCurrency(
                                                            item.precio_venta,
                                                        )}
                                                    </td>

                                                    {/* Stock por sede */}
                                                    {sedes.map((sede) => {
                                                        const qty =
                                                            item.stock_por_sede[
                                                                sede.id
                                                            ];
                                                        return (
                                                            <td
                                                                key={sede.id}
                                                                className="px-3 py-3 text-center"
                                                            >
                                                                <span
                                                                    className={[
                                                                        'font-mono font-bold text-[12px]',
                                                                        qty &&
                                                                        qty > 0
                                                                            ? 'text-foreground'
                                                                            : 'text-muted-foreground',
                                                                    ].join(' ')}
                                                                >
                                                                    {qty ?? 0}
                                                                </span>
                                                            </td>
                                                        );
                                                    })}

                                                    {/* Stock Total */}
                                                    <td className="py-3 pl-4 text-center">
                                                        <div className="flex flex-col items-center">
                                                            <span
                                                                className={[
                                                                    'inline-flex items-center rounded-full px-2.5 py-0.5 font-mono text-[12px] font-bold',
                                                                    isBajoMinimo
                                                                        ? 'bg-destructive/10 text-primary-strong border border-destructive/20'
                                                                        : 'bg-emerald-500/10 text-success-strong border border-emerald-500/20 border border-emerald-500/20',
                                                                ].join(' ')}
                                                            >
                                                                {item.stock_disponible_total ??
                                                                    0}
                                                            </span>
                                                            {item.stock_minimo !==
                                                                null &&
                                                                item.stock_minimo >
                                                                    0 && (
                                                                    <span className="text-muted-foreground mt-0.5 text-[10px]">
                                                                        Mín:{' '}
                                                                        {
                                                                            item.stock_minimo
                                                                        }
                                                                    </span>
                                                                )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>

                                {/* Paginador del catálogo */}
                                {items.links.length > 3 && (
                                    <div className="border-border text-muted-foreground mt-4 flex items-center justify-between border-t pt-4 text-xs">
                                        <span>
                                            Mostrando <b>{items.data.length}</b>{' '}
                                            de <b>{items.total}</b> ítems
                                        </span>
                                        <div className="flex items-center gap-1">
                                            {items.links.map((link, idx) => (
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
                )}

                {/* TAB 2: HISTORIAL DE KARDEX */}
                {activeTab === 'kardex' && (
                    <Card className="border-border bg-card rounded-[16px] p-5 shadow-none">
                        {/* Kardex Filters Bar */}
                        <form
                            onSubmit={handleKardexFilter}
                            className="border-border flex flex-wrap items-end gap-3 border-b pb-5"
                        >
                            <div className="w-[200px]">
                                <Label
                                    htmlFor="almacen-stock-producto"
                                    className="text-foreground/80 text-xs font-bold"
                                >
                                    Producto
                                </Label>
                                <select
                                    id="almacen-stock-producto"
                                    value={kProductId}
                                    onChange={(e) =>
                                        setKProductId(e.target.value)
                                    }
                                    className="border-border bg-card text-foreground focus:border-primary mt-1 h-9 w-full rounded-md border px-3 text-xs focus:outline-none"
                                >
                                    <option value="">
                                        Todos los productos
                                    </option>
                                    {product_list.map((prod) => (
                                        <option key={prod.id} value={prod.id}>
                                            {prod.codigo} - {prod.nombre}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="w-[160px]">
                                <Label
                                    htmlFor="almacen-stock-sede-almacen"
                                    className="text-foreground/80 text-xs font-bold"
                                >
                                    Sede / Almacén
                                </Label>
                                <select
                                    id="almacen-stock-sede-almacen"
                                    value={kSedeId}
                                    onChange={(e) => setKSedeId(e.target.value)}
                                    className="border-border bg-card text-foreground focus:border-primary mt-1 h-9 w-full rounded-md border px-3 text-xs focus:outline-none"
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
                                <Label
                                    htmlFor="almacen-stock-tipo-movimiento"
                                    className="text-foreground/80 text-xs font-bold"
                                >
                                    Tipo movimiento
                                </Label>
                                <select
                                    id="almacen-stock-tipo-movimiento"
                                    value={kTipo}
                                    onChange={(e) => setKTipo(e.target.value)}
                                    className="border-border bg-card text-foreground focus:border-primary mt-1 h-9 w-full rounded-md border px-3 text-xs focus:outline-none"
                                >
                                    <option value="todos">
                                        Todos los tipos
                                    </option>
                                    <option value="ingreso">
                                        Ingreso (Recepción)
                                    </option>
                                    <option value="salida_venta">
                                        Salida Venta
                                    </option>
                                    <option value="salida_servicio">
                                        Consumo en Taller
                                    </option>
                                    <option value="ajuste">
                                        Ajuste de inventario
                                    </option>
                                    <option value="traslado">
                                        Traslado entre sedes
                                    </option>
                                </select>
                            </div>

                            <div className="w-[130px]">
                                <Label
                                    htmlFor="almacen-stock-desde"
                                    className="text-foreground/80 text-xs font-bold"
                                >
                                    Desde
                                </Label>
                                <Input
                                    id="almacen-stock-desde"
                                    type="date"
                                    value={kFechaDesde}
                                    onChange={(e) =>
                                        setKFechaDesde(e.target.value)
                                    }
                                    className="mt-1 h-9 text-xs"
                                />
                            </div>

                            <div className="w-[130px]">
                                <Label
                                    htmlFor="almacen-stock-hasta"
                                    className="text-foreground/80 text-xs font-bold"
                                >
                                    Hasta
                                </Label>
                                <Input
                                    id="almacen-stock-hasta"
                                    type="date"
                                    value={kFechaHasta}
                                    onChange={(e) =>
                                        setKFechaHasta(e.target.value)
                                    }
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
                                {(kProductId !== '' ||
                                    kSedeId !== '' ||
                                    kTipo !== 'todos' ||
                                    kFechaDesde !== '' ||
                                    kFechaHasta !== '') && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={handleResetKardex}
                                        className="text-muted-foreground h-9 gap-1.5"
                                    >
                                        <RotateCcw className="size-3.5" />
                                        <span>Limpiar</span>
                                    </Button>
                                )}
                            </div>
                        </form>

                        {/* Kardex Records Table */}
                        {kardex.data.length === 0 ? (
                            <div className="flex min-h-[260px] flex-col items-center justify-center p-8 text-center">
                                <History className="text-muted-foreground/60 size-10" />
                                <p className="text-foreground mt-2 text-sm font-semibold">
                                    No se encontraron movimientos de Kardex
                                </p>
                                <p className="text-muted-foreground mt-1 max-w-sm text-xs">
                                    {kProductId ||
                                    kSedeId ||
                                    kTipo !== 'todos' ||
                                    kFechaDesde ||
                                    kFechaHasta
                                        ? 'No hay transacciones registradas con los filtros seleccionados.'
                                        : 'Aún no se han registrado movimientos de entrada, salida o consumo.'}
                                </p>
                                {(kProductId !== '' ||
                                    kSedeId !== '' ||
                                    kTipo !== 'todos' ||
                                    kFechaDesde !== '' ||
                                    kFechaHasta !== '') && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={handleResetKardex}
                                        className="border-border text-foreground hover:bg-muted mt-4 gap-1.5 text-xs font-semibold"
                                    >
                                        <RotateCcw className="size-3.5" />
                                        <span>Limpiar filtros de Kardex</span>
                                    </Button>
                                )}
                            </div>
                        ) : (
                            <div className="mt-4 overflow-x-auto">
                                <table className="w-full text-left text-[12.5px]">
                                    <thead>
                                        <tr className="border-border text-muted-foreground border-b text-[11px] font-bold tracking-wider uppercase">
                                            <th className="py-2.5 pr-4">
                                                Fecha
                                            </th>
                                            <th className="px-3 py-2.5">
                                                Tipo
                                            </th>
                                            <th className="px-4 py-2.5">
                                                Producto
                                            </th>
                                            <th className="px-3 py-2.5">
                                                Unidad / Serie
                                            </th>
                                            <th className="px-3 py-2.5">
                                                Sede
                                            </th>
                                            <th className="px-3 py-2.5 text-right">
                                                Cantidad
                                            </th>
                                            <th className="px-4 py-2.5">
                                                Usuario
                                            </th>
                                            <th className="py-2.5 pl-4">
                                                Observación
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-border divide-y">
                                        {kardex.data.map((mov) => {
                                            const badge =
                                                getTipoMovimientoBadge(
                                                    mov.tipo,
                                                );
                                            const isSalida =
                                                Number(mov.cantidad) < 0;

                                            return (
                                                <tr
                                                    key={mov.id}
                                                    className="hover:bg-muted/40 transition-colors"
                                                >
                                                    <td className="text-foreground/80 py-3 pr-4 font-mono text-[11.5px] whitespace-nowrap">
                                                        {formatDate(mov.fecha)}
                                                    </td>
                                                    <td className="px-3 py-3">
                                                        <span
                                                            className={`inline-flex items-center rounded-[6px] border px-2 py-0.5 text-[10.5px] font-bold ${badge.className}`}
                                                        >
                                                            {badge.label}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <div className="text-foreground font-bold">
                                                            {
                                                                mov.producto
                                                                    .nombre
                                                            }
                                                        </div>
                                                        <div className="text-muted-foreground font-mono text-[11px]">
                                                            Cód:{' '}
                                                            {
                                                                mov.producto
                                                                    .codigo
                                                            }
                                                        </div>
                                                    </td>
                                                    <td className="px-3 py-3">
                                                        {mov.unidad_serie ? (
                                                            <span className="text-foreground flex items-center gap-1 font-mono text-[11.5px] font-bold">
                                                                <ScanBarcode className="text-success-strong size-3" />
                                                                {
                                                                    mov.unidad_serie
                                                                }
                                                            </span>
                                                        ) : (
                                                            <span className="text-muted-foreground text-[11px]">
                                                                —
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="text-foreground/80 px-3 py-3">
                                                        {mov.sede || '—'}
                                                    </td>
                                                    <td className="px-3 py-3 text-right font-mono text-[12.5px] font-bold">
                                                        <span
                                                            className={
                                                                isSalida
                                                                    ? 'text-primary-strong'
                                                                    : 'text-success-strong'
                                                            }
                                                        >
                                                            {isSalida
                                                                ? `${mov.cantidad}`
                                                                : `+${mov.cantidad}`}
                                                        </span>{' '}
                                                        <span className="text-muted-foreground text-[10px] font-normal">
                                                            {
                                                                mov.producto
                                                                    .unidad_medida
                                                            }
                                                        </span>
                                                    </td>
                                                    <td className="text-muted-foreground px-4 py-3 text-[11.5px]">
                                                        {mov.usuario ||
                                                            'Sistema'}
                                                    </td>
                                                    <td className="text-muted-foreground max-w-[200px] truncate py-3 pl-4 text-[11.5px]">
                                                        {mov.observacion || '—'}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>

                                {/* Paginador del Kardex */}
                                {kardex.links.length > 3 && (
                                    <div className="border-border text-muted-foreground mt-4 flex items-center justify-between border-t pt-4 text-xs">
                                        <span>
                                            Mostrando{' '}
                                            <b>{kardex.data.length}</b> de{' '}
                                            <b>{kardex.total}</b> movimientos
                                        </span>
                                        <div className="flex items-center gap-1">
                                            {kardex.links.map((link, idx) => (
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
                )}
            </div>
        </AlmacenLayout>
    );
}
