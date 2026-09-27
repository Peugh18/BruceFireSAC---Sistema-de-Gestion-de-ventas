import { router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Boxes,
    CircleDollarSign,
    Download,
    FileText,
    Filter,
    Layers,
    Percent,
    TrendingUp,
} from 'lucide-react';
import { useState } from 'react';

import GerenteLayout from '@/layouts/gerente-layout';

type ComercialData = {
    fechaDesde: string;
    fechaHasta: string;
    totalVentas: number;
    cantidadVentas: number;
    ticketPromedio: number;
    tasaConversion: number;
    porVendedor: Array<{ nombre: string; cantidad: number; monto: number }>;
    topClientes: Array<{ cliente: string; cantidad: number; total: number }>;
    topItems: Array<{ nombre: string; cantidad: number; monto: number }>;
};

type InventarioData = {
    sedeNombre: string;
    valorizacionTotal: number;
    totalProductos: number;
    totalUnidades: number;
    totalBajoMinimo: number;
    productos: Array<{
        id: number;
        codigo: string;
        nombre: string;
        unidad_medida: string;
        precio_venta: number;
        serializado: boolean;
        stock_minimo: number | null;
        stock_disponible: number;
        bajo_minimo: boolean;
        valorizacion: number;
    }>;
};

type PageProps = {
    currentTeam: { slug: string };
    tipo: 'comercial' | 'inventario';
    reporteComercial?: ComercialData;
    reporteInventario?: InventarioData;
    vendedores: Array<{ id: number; name: string }>;
    sedes: Array<{ id: number; nombre: string }>;
    filters: Record<string, string | boolean | null | undefined>;
    [key: string]: unknown;
};

function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('es-PE', {
        style: 'currency',
        currency: 'PEN',
        minimumFractionDigits: 2,
    }).format(amount);
}

export default function ReportesIndex() {
    const {
        currentTeam,
        tipo,
        reporteComercial,
        reporteInventario,
        vendedores,
        sedes,
        filters,
    } = usePage<PageProps>().props;

    // Filtros Comercial
    const [fechaDesde, setFechaDesde] = useState(
        (filters.fecha_desde as string) || '',
    );
    const [fechaHasta, setFechaHasta] = useState(
        (filters.fecha_hasta as string) || '',
    );
    const [vendedorId, setVendedorId] = useState(
        (filters.vendedor_id as string) || '',
    );

    // Filtros Inventario
    const [sedeId, setSedeId] = useState((filters.sede_id as string) || '');
    const [soloBajoMinimo, setSoloBajoMinimo] = useState(
        (filters.solo_bajo_minimo as boolean) || false,
    );

    const switchTipo = (newTipo: 'comercial' | 'inventario') => {
        router.get(
            `/${currentTeam.slug}/gerente/reportes`,
            { tipo: newTipo },
            { preserveState: true },
        );
    };

    const applyComercialFilters = () => {
        router.get(
            `/${currentTeam.slug}/gerente/reportes`,
            {
                tipo: 'comercial',
                fecha_desde: fechaDesde || undefined,
                fecha_hasta: fechaHasta || undefined,
                vendedor_id: vendedorId || undefined,
            },
            { preserveState: true },
        );
    };

    const applyInventarioFilters = () => {
        router.get(
            `/${currentTeam.slug}/gerente/reportes`,
            {
                tipo: 'inventario',
                sede_id: sedeId || undefined,
                solo_bajo_minimo: soloBajoMinimo ? '1' : undefined,
            },
            { preserveState: true },
        );
    };

    const handleDownloadComercialPdf = () => {
        const query = new URLSearchParams({
            fecha_desde: fechaDesde,
            fecha_hasta: fechaHasta,
            ...(vendedorId ? { vendedor_id: vendedorId } : {}),
        }).toString();

        window.open(
            `/${currentTeam.slug}/gerente/reportes/comercial/pdf?${query}`,
            '_blank',
        );
    };

    const handleDownloadInventarioPdf = () => {
        const query = new URLSearchParams({
            ...(sedeId ? { sede_id: sedeId } : {}),
            ...(soloBajoMinimo ? { solo_bajo_minimo: '1' } : {}),
        }).toString();

        window.open(
            `/${currentTeam.slug}/gerente/reportes/inventario/pdf?${query}`,
            '_blank',
        );
    };

    return (
        <GerenteLayout title="Reportes Gerenciales">
            <div className="space-y-6">
                {/* Cabecera y Selector de Reporte */}
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div>
                        <h1 className="text-foreground font-['Oswald',sans-serif] text-2xl font-bold tracking-wide uppercase">
                            Reportes Gerenciales
                        </h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Análisis comercial, conversión de cotizaciones,
                            valorización de inventario y rotación.
                        </p>
                    </div>

                    {/* Botones de Selección de Reporte */}
                    <div className="border-border bg-card inline-flex rounded-xl border p-1 text-xs font-semibold shadow-xs">
                        <button
                            type="button"
                            onClick={() => switchTipo('comercial')}
                            className={`inline-flex items-center gap-2 rounded-lg px-4 py-2 transition-colors ${
                                tipo === 'comercial'
                                    ? 'bg-foreground text-background'
                                    : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            <TrendingUp className="size-4" />
                            <span>Comercial & Ventas</span>
                        </button>
                        <button
                            type="button"
                            onClick={() => switchTipo('inventario')}
                            className={`inline-flex items-center gap-2 rounded-lg px-4 py-2 transition-colors ${
                                tipo === 'inventario'
                                    ? 'bg-foreground text-background'
                                    : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            <Boxes className="size-4" />
                            <span>Inventario & Existencias</span>
                        </button>
                    </div>
                </div>

                {/* VISTA REPORTE COMERCIAL */}
                {tipo === 'comercial' && reporteComercial && (
                    <div className="space-y-6">
                        {/* Filtros Comercial */}
                        <div className="border-border bg-card flex flex-col gap-3 rounded-xl border p-4 text-xs shadow-xs md:flex-row md:items-end md:justify-between">
                            <div className="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-3">
                                <div>
                                    <label className="text-foreground/80 block font-semibold">
                                        Fecha Desde
                                    </label>
                                    <input
                                        type="date"
                                        value={fechaDesde}
                                        onChange={(e) =>
                                            setFechaDesde(e.target.value)
                                        }
                                        className="border-border bg-muted/40 focus:border-primary mt-1 w-full rounded-lg border px-3 py-1.5 focus:outline-none"
                                    />
                                </div>
                                <div>
                                    <label className="text-foreground/80 block font-semibold">
                                        Fecha Hasta
                                    </label>
                                    <input
                                        type="date"
                                        value={fechaHasta}
                                        onChange={(e) =>
                                            setFechaHasta(e.target.value)
                                        }
                                        className="border-border bg-muted/40 focus:border-primary mt-1 w-full rounded-lg border px-3 py-1.5 focus:outline-none"
                                    />
                                </div>
                                <div>
                                    <label className="text-foreground/80 block font-semibold">
                                        Vendedor
                                    </label>
                                    <select
                                        value={vendedorId}
                                        onChange={(e) =>
                                            setVendedorId(e.target.value)
                                        }
                                        className="border-border bg-muted/40 focus:border-primary mt-1 w-full rounded-lg border px-3 py-1.5 focus:outline-none"
                                    >
                                        <option value="">
                                            Todos los vendedores
                                        </option>
                                        {vendedores.map((v) => (
                                            <option key={v.id} value={v.id}>
                                                {v.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            <div className="flex items-center gap-2 pt-2 md:pt-0">
                                <button
                                    type="button"
                                    onClick={applyComercialFilters}
                                    className="border-border bg-card text-foreground/80 hover:bg-background inline-flex items-center gap-1.5 rounded-lg border px-3.5 py-1.5 font-semibold"
                                >
                                    <Filter className="size-3.5" />
                                    <span>Filtrar</span>
                                </button>
                                <button
                                    type="button"
                                    onClick={handleDownloadComercialPdf}
                                    className="bg-primary hover:bg-primary/90 inline-flex items-center gap-1.5 rounded-lg px-4 py-1.5 font-semibold text-white shadow-xs"
                                >
                                    <Download className="size-3.5" />
                                    <span>Exportar PDF</span>
                                </button>
                            </div>
                        </div>

                        {/* KPIs Comerciales */}
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div className="border-border bg-card rounded-xl border p-4 shadow-xs">
                                <div className="text-muted-foreground flex items-center justify-between text-xs">
                                    <span className="font-medium uppercase">
                                        Ventas Totales
                                    </span>
                                    <CircleDollarSign className="size-4 text-emerald-600" />
                                </div>
                                <div className="text-foreground mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold">
                                    {formatCurrency(
                                        reporteComercial.totalVentas,
                                    )}
                                </div>
                                <p className="text-muted-foreground mt-0.5 text-[11px]">
                                    Monto neto sin notas anuladas
                                </p>
                            </div>

                            <div className="border-border bg-card rounded-xl border p-4 shadow-xs">
                                <div className="text-muted-foreground flex items-center justify-between text-xs">
                                    <span className="font-medium uppercase">
                                        Total de Operaciones
                                    </span>
                                    <FileText className="size-4 text-blue-600" />
                                </div>
                                <div className="text-foreground mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold">
                                    {reporteComercial.cantidadVentas}
                                </div>
                                <p className="text-muted-foreground mt-0.5 text-[11px]">
                                    Ventas confirmadas en el período
                                </p>
                            </div>

                            <div className="border-border bg-card rounded-xl border p-4 shadow-xs">
                                <div className="text-muted-foreground flex items-center justify-between text-xs">
                                    <span className="font-medium uppercase">
                                        Ticket Promedio
                                    </span>
                                    <TrendingUp className="size-4 text-purple-600" />
                                </div>
                                <div className="text-foreground mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold">
                                    {formatCurrency(
                                        reporteComercial.ticketPromedio,
                                    )}
                                </div>
                                <p className="text-muted-foreground mt-0.5 text-[11px]">
                                    Promedio por comprobante
                                </p>
                            </div>

                            <div className="border-border bg-card rounded-xl border p-4 shadow-xs">
                                <div className="text-muted-foreground flex items-center justify-between text-xs">
                                    <span className="font-medium uppercase">
                                        Conversión Cotizaciones
                                    </span>
                                    <Percent className="size-4 text-amber-600" />
                                </div>
                                <div className="text-foreground mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold">
                                    {reporteComercial.tasaConversion}%
                                </div>
                                <p className="text-muted-foreground mt-0.5 text-[11px]">
                                    Efectividad de cierre comercial
                                </p>
                            </div>
                        </div>

                        {/* Tablas de Desglose Comercial */}
                        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                            {/* Desglose por Vendedor */}
                            <div className="border-border bg-card rounded-xl border p-5 shadow-xs">
                                <h3 className="text-foreground font-['Oswald',sans-serif] text-base font-bold uppercase">
                                    Ventas por Vendedor
                                </h3>
                                <p className="text-muted-foreground mb-4 text-xs">
                                    Rendimiento individual del equipo de ventas
                                </p>

                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-xs">
                                        <thead className="border-border bg-muted/40 text-muted-foreground border-b text-[10px] uppercase">
                                            <tr>
                                                <th className="px-3 py-2">
                                                    Vendedor
                                                </th>
                                                <th className="px-3 py-2 text-center">
                                                    Operaciones
                                                </th>
                                                <th className="px-3 py-2 text-right">
                                                    Total Facturado
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-border divide-y">
                                            {reporteComercial.porVendedor
                                                .length === 0 ? (
                                                <tr>
                                                    <td
                                                        colSpan={3}
                                                        className="text-muted-foreground py-4 text-center"
                                                    >
                                                        Sin ventas en este
                                                        período.
                                                    </td>
                                                </tr>
                                            ) : (
                                                reporteComercial.porVendedor.map(
                                                    (v) => (
                                                        <tr key={v.nombre}>
                                                            <td className="text-foreground px-3 py-2.5 font-medium">
                                                                {v.nombre}
                                                            </td>
                                                            <td className="px-3 py-2.5 text-center font-mono">
                                                                {v.cantidad}
                                                            </td>
                                                            <td className="text-foreground px-3 py-2.5 text-right font-mono font-bold">
                                                                {formatCurrency(
                                                                    v.monto,
                                                                )}
                                                            </td>
                                                        </tr>
                                                    ),
                                                )
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {/* Top Clientes */}
                            <div className="border-border bg-card rounded-xl border p-5 shadow-xs">
                                <h3 className="text-foreground font-['Oswald',sans-serif] text-base font-bold uppercase">
                                    Top Clientes
                                </h3>
                                <p className="text-muted-foreground mb-4 text-xs">
                                    Clientes con mayor volumen facturado
                                </p>

                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-xs">
                                        <thead className="border-border bg-muted/40 text-muted-foreground border-b text-[10px] uppercase">
                                            <tr>
                                                <th className="px-3 py-2">
                                                    Cliente
                                                </th>
                                                <th className="px-3 py-2 text-center">
                                                    Compras
                                                </th>
                                                <th className="px-3 py-2 text-right">
                                                    Total
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-border divide-y">
                                            {reporteComercial.topClientes
                                                .length === 0 ? (
                                                <tr>
                                                    <td
                                                        colSpan={3}
                                                        className="text-muted-foreground py-4 text-center"
                                                    >
                                                        Sin datos de clientes.
                                                    </td>
                                                </tr>
                                            ) : (
                                                reporteComercial.topClientes.map(
                                                    (c, i) => (
                                                        <tr key={c.cliente}>
                                                            <td className="text-foreground max-w-xs truncate px-3 py-2.5 font-medium">
                                                                <b className="text-primary mr-1">
                                                                    #{i + 1}
                                                                </b>{' '}
                                                                {c.cliente}
                                                            </td>
                                                            <td className="px-3 py-2.5 text-center font-mono">
                                                                {c.cantidad}
                                                            </td>
                                                            <td className="text-foreground px-3 py-2.5 text-right font-mono font-bold">
                                                                {formatCurrency(
                                                                    c.total,
                                                                )}
                                                            </td>
                                                        </tr>
                                                    ),
                                                )
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        {/* Top Ítems Vendidos */}
                        <div className="border-border bg-card rounded-xl border p-5 shadow-xs">
                            <h3 className="text-foreground font-['Oswald',sans-serif] text-base font-bold uppercase">
                                Top Productos y Servicios Vendidos
                            </h3>
                            <p className="text-muted-foreground mb-4 text-xs">
                                Ranking de artículos con mayor recaudación
                            </p>

                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-xs">
                                    <thead className="border-border bg-muted/40 text-muted-foreground border-b text-[10px] uppercase">
                                        <tr>
                                            <th className="px-4 py-2.5">
                                                Ítem
                                            </th>
                                            <th className="px-4 py-2.5 text-center">
                                                Cantidad Total
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                Monto Facturado
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-border divide-y">
                                        {reporteComercial.topItems.length ===
                                        0 ? (
                                            <tr>
                                                <td
                                                    colSpan={3}
                                                    className="text-muted-foreground py-6 text-center"
                                                >
                                                    Sin ítems registrados.
                                                </td>
                                            </tr>
                                        ) : (
                                            reporteComercial.topItems.map(
                                                (item, i) => (
                                                    <tr key={item.nombre}>
                                                        <td className="text-foreground px-4 py-3 font-medium">
                                                            <b className="text-primary mr-2">
                                                                #{i + 1}
                                                            </b>{' '}
                                                            {item.nombre}
                                                        </td>
                                                        <td className="text-foreground/80 px-4 py-3 text-center font-mono font-semibold">
                                                            {item.cantidad}
                                                        </td>
                                                        <td className="text-foreground px-4 py-3 text-right font-mono font-bold">
                                                            {formatCurrency(
                                                                item.monto,
                                                            )}
                                                        </td>
                                                    </tr>
                                                ),
                                            )
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                )}

                {/* VISTA REPORTE INVENTARIO */}
                {tipo === 'inventario' && reporteInventario && (
                    <div className="space-y-6">
                        {/* Filtros Inventario */}
                        <div className="border-border bg-card flex flex-col gap-3 rounded-xl border p-4 text-xs shadow-xs md:flex-row md:items-end md:justify-between">
                            <div className="grid max-w-xl flex-1 grid-cols-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <label className="text-foreground/80 block font-semibold">
                                        Sede / Almacén
                                    </label>
                                    <select
                                        value={sedeId}
                                        onChange={(e) =>
                                            setSedeId(e.target.value)
                                        }
                                        className="border-border bg-muted/40 focus:border-primary mt-1 w-full rounded-lg border px-3 py-1.5 focus:outline-none"
                                    >
                                        <option value="">
                                            Todas las sedes
                                        </option>
                                        {sedes.map((s) => (
                                            <option key={s.id} value={s.id}>
                                                {s.nombre}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div className="flex items-center pt-5">
                                    <label className="text-foreground/80 flex cursor-pointer items-center gap-2 font-medium">
                                        <input
                                            type="checkbox"
                                            checked={soloBajoMinimo}
                                            onChange={(e) =>
                                                setSoloBajoMinimo(
                                                    e.target.checked,
                                                )
                                            }
                                            className="border-border text-primary rounded"
                                        />
                                        <span>
                                            Solo productos bajo stock mínimo
                                        </span>
                                    </label>
                                </div>
                            </div>

                            <div className="flex items-center gap-2 pt-2 md:pt-0">
                                <button
                                    type="button"
                                    onClick={applyInventarioFilters}
                                    className="border-border bg-card text-foreground/80 hover:bg-background inline-flex items-center gap-1.5 rounded-lg border px-3.5 py-1.5 font-semibold"
                                >
                                    <Filter className="size-3.5" />
                                    <span>Filtrar</span>
                                </button>
                                <button
                                    type="button"
                                    onClick={handleDownloadInventarioPdf}
                                    className="bg-primary hover:bg-primary/90 inline-flex items-center gap-1.5 rounded-lg px-4 py-1.5 font-semibold text-white shadow-xs"
                                >
                                    <Download className="size-3.5" />
                                    <span>Exportar PDF</span>
                                </button>
                            </div>
                        </div>

                        {/* KPIs de Inventario */}
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div className="border-border bg-card rounded-xl border p-4 shadow-xs">
                                <div className="text-muted-foreground flex items-center justify-between text-xs">
                                    <span className="font-medium uppercase">
                                        Valorización Total
                                    </span>
                                    <CircleDollarSign className="size-4 text-emerald-600" />
                                </div>
                                <div className="text-foreground mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold">
                                    {formatCurrency(
                                        reporteInventario.valorizacionTotal,
                                    )}
                                </div>
                                <p className="text-muted-foreground mt-0.5 text-[11px]">
                                    Stock disponible × precio venta
                                </p>
                            </div>

                            <div className="border-border bg-card rounded-xl border p-4 shadow-xs">
                                <div className="text-muted-foreground flex items-center justify-between text-xs">
                                    <span className="font-medium uppercase">
                                        Productos en Catálogo
                                    </span>
                                    <Layers className="size-4 text-blue-600" />
                                </div>
                                <div className="text-foreground mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold">
                                    {reporteInventario.totalProductos}
                                </div>
                                <p className="text-muted-foreground mt-0.5 text-[11px]">
                                    Productos activos registrados
                                </p>
                            </div>

                            <div className="border-border bg-card rounded-xl border p-4 shadow-xs">
                                <div className="text-muted-foreground flex items-center justify-between text-xs">
                                    <span className="font-medium uppercase">
                                        Unidades Físicas
                                    </span>
                                    <Boxes className="size-4 text-purple-600" />
                                </div>
                                <div className="text-foreground mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold">
                                    {reporteInventario.totalUnidades}
                                </div>
                                <p className="text-muted-foreground mt-0.5 text-[11px]">
                                    Disponibles para venta/operación
                                </p>
                            </div>

                            <div className="border-border bg-card rounded-xl border p-4 shadow-xs">
                                <div className="text-muted-foreground flex items-center justify-between text-xs">
                                    <span className="font-medium uppercase">
                                        Bajo Stock Mínimo
                                    </span>
                                    <AlertTriangle className="text-primary size-4" />
                                </div>
                                <div className="text-primary mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold">
                                    {reporteInventario.totalBajoMinimo}
                                </div>
                                <p className="text-muted-foreground mt-0.5 text-[11px]">
                                    Requieren reposición urgente
                                </p>
                            </div>
                        </div>

                        {/* Tabla de Existencias de Inventario */}
                        <div className="border-border bg-card overflow-hidden rounded-xl border shadow-xs">
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-xs">
                                    <thead className="border-border bg-muted/40 text-muted-foreground border-b text-[11px] font-semibold tracking-wider uppercase">
                                        <tr>
                                            <th className="px-4 py-3">
                                                Código
                                            </th>
                                            <th className="px-4 py-3">
                                                Producto
                                            </th>
                                            <th className="px-4 py-3 text-center">
                                                Tipo
                                            </th>
                                            <th className="px-4 py-3 text-center">
                                                U.M.
                                            </th>
                                            <th className="px-4 py-3 text-right">
                                                Precio Venta
                                            </th>
                                            <th className="px-4 py-3 text-center">
                                                Stock Mínimo
                                            </th>
                                            <th className="px-4 py-3 text-center">
                                                Stock Actual
                                            </th>
                                            <th className="px-4 py-3 text-right">
                                                Valorización
                                            </th>
                                            <th className="px-4 py-3 text-center">
                                                Estado
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-border divide-y">
                                        {reporteInventario.productos.length ===
                                        0 ? (
                                            <tr>
                                                <td
                                                    colSpan={9}
                                                    className="text-muted-foreground py-8 text-center"
                                                >
                                                    No se encontraron productos
                                                    con los filtros
                                                    seleccionados.
                                                </td>
                                            </tr>
                                        ) : (
                                            reporteInventario.productos.map(
                                                (p) => (
                                                    <tr
                                                        key={p.id}
                                                        className="hover:bg-muted/40 transition-colors"
                                                    >
                                                        <td className="text-foreground px-4 py-3 font-mono font-bold">
                                                            {p.codigo}
                                                        </td>
                                                        <td className="text-foreground px-4 py-3 font-medium">
                                                            {p.nombre}
                                                        </td>
                                                        <td className="px-4 py-3 text-center">
                                                            <span className="border-border bg-muted/40 text-muted-foreground rounded-md border px-2 py-0.5 text-[10px]">
                                                                {p.serializado
                                                                    ? 'Serializado'
                                                                    : 'A Granel'}
                                                            </span>
                                                        </td>
                                                        <td className="text-foreground/80 px-4 py-3 text-center font-mono">
                                                            {p.unidad_medida}
                                                        </td>
                                                        <td className="text-foreground px-4 py-3 text-right font-mono font-semibold">
                                                            {formatCurrency(
                                                                p.precio_venta,
                                                            )}
                                                        </td>
                                                        <td className="text-foreground/80 px-4 py-3 text-center font-mono">
                                                            {p.stock_minimo !==
                                                            null
                                                                ? p.stock_minimo
                                                                : '—'}
                                                        </td>
                                                        <td className="px-4 py-3 text-center">
                                                            <span
                                                                className={`inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 font-mono text-[11px] font-bold ${
                                                                    p.bajo_minimo
                                                                        ? 'text-primary bg-red-100'
                                                                        : 'bg-background text-foreground'
                                                                }`}
                                                            >
                                                                {p.bajo_minimo && (
                                                                    <AlertTriangle className="text-primary size-3" />
                                                                )}
                                                                {
                                                                    p.stock_disponible
                                                                }
                                                            </span>
                                                        </td>
                                                        <td className="text-foreground px-4 py-3 text-right font-mono font-bold">
                                                            {formatCurrency(
                                                                p.valorizacion,
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3 text-center">
                                                            <span
                                                                className={`inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold uppercase ${
                                                                    p.bajo_minimo
                                                                        ? 'text-primary bg-red-100'
                                                                        : 'bg-emerald-100 text-emerald-800'
                                                                }`}
                                                            >
                                                                {p.bajo_minimo
                                                                    ? 'Bajo Mínimo'
                                                                    : 'Normal'}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                ),
                                            )
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </GerenteLayout>
    );
}
