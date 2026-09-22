import { router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Award,
    BarChart3,
    Boxes,
    Building2,
    Calendar,
    CheckCircle2,
    CircleDollarSign,
    Download,
    FileBarChart,
    FileSpreadsheet,
    FileText,
    Filter,
    Layers,
    Percent,
    TrendingUp,
    Users,
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
    const { currentTeam, tipo, reporteComercial, reporteInventario, vendedores, sedes, filters } =
        usePage<PageProps>().props;

    // Filtros Comercial
    const [fechaDesde, setFechaDesde] = useState((filters.fecha_desde as string) || '');
    const [fechaHasta, setFechaHasta] = useState((filters.fecha_hasta as string) || '');
    const [vendedorId, setVendedorId] = useState((filters.vendedor_id as string) || '');

    // Filtros Inventario
    const [sedeId, setSedeId] = useState((filters.sede_id as string) || '');
    const [soloBajoMinimo, setSoloBajoMinimo] = useState((filters.solo_bajo_minimo as boolean) || false);

    const switchTipo = (newTipo: 'comercial' | 'inventario') => {
        router.get(`/${currentTeam.slug}/gerente/reportes`, { tipo: newTipo }, { preserveState: true });
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
            { preserveState: true }
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
            { preserveState: true }
        );
    };

    const handleDownloadComercialPdf = () => {
        const query = new URLSearchParams({
            fecha_desde: fechaDesde,
            fecha_hasta: fechaHasta,
            ...(vendedorId ? { vendedor_id: vendedorId } : {}),
        }).toString();

        window.open(`/${currentTeam.slug}/gerente/reportes/comercial/pdf?${query}`, '_blank');
    };

    const handleDownloadInventarioPdf = () => {
        const query = new URLSearchParams({
            ...(sedeId ? { sede_id: sedeId } : {}),
            ...(soloBajoMinimo ? { solo_bajo_minimo: '1' } : {}),
        }).toString();

        window.open(`/${currentTeam.slug}/gerente/reportes/inventario/pdf?${query}`, '_blank');
    };

    return (
        <GerenteLayout title="Reportes Gerenciales">
            <div className="space-y-6">
                {/* Cabecera y Selector de Reporte */}
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div>
                        <h1 className="font-['Oswald',sans-serif] text-2xl font-bold tracking-wide text-[#201F1D] uppercase">
                            Reportes Gerenciales
                        </h1>
                        <p className="mt-1 text-sm text-[#8A8680]">
                            Análisis comercial, conversión de cotizaciones, valorización de inventario y rotación (§34).
                        </p>
                    </div>

                    {/* Botones de Selección de Reporte */}
                    <div className="inline-flex rounded-xl border border-[#E7E4DE] bg-white p-1 shadow-xs text-xs font-semibold">
                        <button
                            type="button"
                            onClick={() => switchTipo('comercial')}
                            className={`inline-flex items-center gap-2 rounded-lg px-4 py-2 transition-colors ${
                                tipo === 'comercial'
                                    ? 'bg-[#201F1D] text-white'
                                    : 'text-[#6B6965] hover:text-[#201F1D]'
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
                                    ? 'bg-[#201F1D] text-white'
                                    : 'text-[#6B6965] hover:text-[#201F1D]'
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
                        <div className="flex flex-col gap-3 rounded-xl border border-[#E7E4DE] bg-white p-4 shadow-xs md:flex-row md:items-end md:justify-between text-xs">
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3 flex-1">
                                <div>
                                    <label className="block font-semibold text-[#4A4742]">Fecha Desde</label>
                                    <input
                                        type="date"
                                        value={fechaDesde}
                                        onChange={(e) => setFechaDesde(e.target.value)}
                                        className="mt-1 w-full rounded-lg border border-[#E7E4DE] bg-[#FAFAF8] px-3 py-1.5 focus:border-[#201F1D] focus:outline-none"
                                    />
                                </div>
                                <div>
                                    <label className="block font-semibold text-[#4A4742]">Fecha Hasta</label>
                                    <input
                                        type="date"
                                        value={fechaHasta}
                                        onChange={(e) => setFechaHasta(e.target.value)}
                                        className="mt-1 w-full rounded-lg border border-[#E7E4DE] bg-[#FAFAF8] px-3 py-1.5 focus:border-[#201F1D] focus:outline-none"
                                    />
                                </div>
                                <div>
                                    <label className="block font-semibold text-[#4A4742]">Vendedor</label>
                                    <select
                                        value={vendedorId}
                                        onChange={(e) => setVendedorId(e.target.value)}
                                        className="mt-1 w-full rounded-lg border border-[#E7E4DE] bg-[#FAFAF8] px-3 py-1.5 focus:border-[#201F1D] focus:outline-none"
                                    >
                                        <option value="">Todos los vendedores</option>
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
                                    className="inline-flex items-center gap-1.5 rounded-lg border border-[#E7E4DE] bg-white px-3.5 py-1.5 font-semibold text-[#4A4742] hover:bg-[#F3F1ED]"
                                >
                                    <Filter className="size-3.5" />
                                    <span>Filtrar</span>
                                </button>
                                <button
                                    type="button"
                                    onClick={handleDownloadComercialPdf}
                                    className="inline-flex items-center gap-1.5 rounded-lg bg-[#E31E24] px-4 py-1.5 font-semibold text-white hover:bg-[#c9181d] shadow-xs"
                                >
                                    <Download className="size-3.5" />
                                    <span>Exportar PDF</span>
                                </button>
                            </div>
                        </div>

                        {/* KPIs Comerciales */}
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div className="rounded-xl border border-[#E7E4DE] bg-white p-4 shadow-xs">
                                <div className="flex items-center justify-between text-xs text-[#8A8680]">
                                    <span className="font-medium uppercase">Ventas Totales</span>
                                    <CircleDollarSign className="size-4 text-emerald-600" />
                                </div>
                                <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-[#201F1D]">
                                    {formatCurrency(reporteComercial.totalVentas)}
                                </div>
                                <p className="mt-0.5 text-[11px] text-[#8A8680]">Monto neto sin notas anuladas</p>
                            </div>

                            <div className="rounded-xl border border-[#E7E4DE] bg-white p-4 shadow-xs">
                                <div className="flex items-center justify-between text-xs text-[#8A8680]">
                                    <span className="font-medium uppercase">Total de Operaciones</span>
                                    <FileText className="size-4 text-blue-600" />
                                </div>
                                <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-[#201F1D]">
                                    {reporteComercial.cantidadVentas}
                                </div>
                                <p className="mt-0.5 text-[11px] text-[#8A8680]">Ventas confirmadas en el período</p>
                            </div>

                            <div className="rounded-xl border border-[#E7E4DE] bg-white p-4 shadow-xs">
                                <div className="flex items-center justify-between text-xs text-[#8A8680]">
                                    <span className="font-medium uppercase">Ticket Promedio</span>
                                    <TrendingUp className="size-4 text-purple-600" />
                                </div>
                                <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-[#201F1D]">
                                    {formatCurrency(reporteComercial.ticketPromedio)}
                                </div>
                                <p className="mt-0.5 text-[11px] text-[#8A8680]">Promedio por comprobante</p>
                            </div>

                            <div className="rounded-xl border border-[#E7E4DE] bg-white p-4 shadow-xs">
                                <div className="flex items-center justify-between text-xs text-[#8A8680]">
                                    <span className="font-medium uppercase">Conversión Cotizaciones</span>
                                    <Percent className="size-4 text-amber-600" />
                                </div>
                                <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-[#201F1D]">
                                    {reporteComercial.tasaConversion}%
                                </div>
                                <p className="mt-0.5 text-[11px] text-[#8A8680]">Efectividad de cierre comercial</p>
                            </div>
                        </div>

                        {/* Tablas de Desglose Comercial */}
                        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                            {/* Desglose por Vendedor */}
                            <div className="rounded-xl border border-[#E7E4DE] bg-white p-5 shadow-xs">
                                <h3 className="font-['Oswald',sans-serif] text-base font-bold text-[#201F1D] uppercase">
                                    Ventas por Vendedor
                                </h3>
                                <p className="text-xs text-[#8A8680] mb-4">Rendimiento individual del equipo de ventas</p>

                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-xs">
                                        <thead className="border-b border-[#E7E4DE] bg-[#FAFAF8] text-[10px] text-[#8A8680] uppercase">
                                            <tr>
                                                <th className="px-3 py-2">Vendedor</th>
                                                <th className="px-3 py-2 text-center">Operaciones</th>
                                                <th className="px-3 py-2 text-right">Total Facturado</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-[#F3F1ED]">
                                            {reporteComercial.porVendedor.length === 0 ? (
                                                <tr>
                                                    <td colSpan={3} className="py-4 text-center text-[#8A8680]">
                                                        Sin ventas en este período.
                                                    </td>
                                                </tr>
                                            ) : (
                                                reporteComercial.porVendedor.map((v) => (
                                                    <tr key={v.nombre}>
                                                        <td className="px-3 py-2.5 font-medium text-[#201F1D]">
                                                            {v.nombre}
                                                        </td>
                                                        <td className="px-3 py-2.5 text-center font-mono">{v.cantidad}</td>
                                                        <td className="px-3 py-2.5 text-right font-mono font-bold text-[#201F1D]">
                                                            {formatCurrency(v.monto)}
                                                        </td>
                                                    </tr>
                                                ))
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {/* Top Clientes */}
                            <div className="rounded-xl border border-[#E7E4DE] bg-white p-5 shadow-xs">
                                <h3 className="font-['Oswald',sans-serif] text-base font-bold text-[#201F1D] uppercase">
                                    Top Clientes
                                </h3>
                                <p className="text-xs text-[#8A8680] mb-4">Clientes con mayor volumen facturado</p>

                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-xs">
                                        <thead className="border-b border-[#E7E4DE] bg-[#FAFAF8] text-[10px] text-[#8A8680] uppercase">
                                            <tr>
                                                <th className="px-3 py-2">Cliente</th>
                                                <th className="px-3 py-2 text-center">Compras</th>
                                                <th className="px-3 py-2 text-right">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-[#F3F1ED]">
                                            {reporteComercial.topClientes.length === 0 ? (
                                                <tr>
                                                    <td colSpan={3} className="py-4 text-center text-[#8A8680]">
                                                        Sin datos de clientes.
                                                    </td>
                                                </tr>
                                            ) : (
                                                reporteComercial.topClientes.map((c, i) => (
                                                    <tr key={c.cliente}>
                                                        <td className="px-3 py-2.5 font-medium text-[#201F1D] truncate max-w-xs">
                                                            <b className="mr-1 text-[#E31E24]">#{i + 1}</b> {c.cliente}
                                                        </td>
                                                        <td className="px-3 py-2.5 text-center font-mono">{c.cantidad}</td>
                                                        <td className="px-3 py-2.5 text-right font-mono font-bold text-[#201F1D]">
                                                            {formatCurrency(c.total)}
                                                        </td>
                                                    </tr>
                                                ))
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        {/* Top Ítems Vendidos */}
                        <div className="rounded-xl border border-[#E7E4DE] bg-white p-5 shadow-xs">
                            <h3 className="font-['Oswald',sans-serif] text-base font-bold text-[#201F1D] uppercase">
                                Top Productos y Servicios Vendidos
                            </h3>
                            <p className="text-xs text-[#8A8680] mb-4">Ranking de artículos con mayor recaudación</p>

                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-xs">
                                    <thead className="border-b border-[#E7E4DE] bg-[#FAFAF8] text-[10px] text-[#8A8680] uppercase">
                                        <tr>
                                            <th className="px-4 py-2.5">Ítem</th>
                                            <th className="px-4 py-2.5 text-center">Cantidad Total</th>
                                            <th className="px-4 py-2.5 text-right">Monto Facturado</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-[#F3F1ED]">
                                        {reporteComercial.topItems.length === 0 ? (
                                            <tr>
                                                <td colSpan={3} className="py-6 text-center text-[#8A8680]">
                                                    Sin ítems registrados.
                                                </td>
                                            </tr>
                                        ) : (
                                            reporteComercial.topItems.map((item, i) => (
                                                <tr key={item.nombre}>
                                                    <td className="px-4 py-3 font-medium text-[#201F1D]">
                                                        <b className="mr-2 text-[#E31E24]">#{i + 1}</b> {item.nombre}
                                                    </td>
                                                    <td className="px-4 py-3 text-center font-mono font-semibold text-[#4A4742]">
                                                        {item.cantidad}
                                                    </td>
                                                    <td className="px-4 py-3 text-right font-mono font-bold text-[#201F1D]">
                                                        {formatCurrency(item.monto)}
                                                    </td>
                                                </tr>
                                            ))
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
                        <div className="flex flex-col gap-3 rounded-xl border border-[#E7E4DE] bg-white p-4 shadow-xs md:flex-row md:items-end md:justify-between text-xs">
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 flex-1 max-w-xl">
                                <div>
                                    <label className="block font-semibold text-[#4A4742]">Sede / Almacén</label>
                                    <select
                                        value={sedeId}
                                        onChange={(e) => setSedeId(e.target.value)}
                                        className="mt-1 w-full rounded-lg border border-[#E7E4DE] bg-[#FAFAF8] px-3 py-1.5 focus:border-[#201F1D] focus:outline-none"
                                    >
                                        <option value="">Todas las sedes</option>
                                        {sedes.map((s) => (
                                            <option key={s.id} value={s.id}>
                                                {s.nombre}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div className="flex items-center pt-5">
                                    <label className="flex items-center gap-2 cursor-pointer font-medium text-[#4A4742]">
                                        <input
                                            type="checkbox"
                                            checked={soloBajoMinimo}
                                            onChange={(e) => setSoloBajoMinimo(e.target.checked)}
                                            className="rounded border-[#E7E4DE] text-[#E31E24]"
                                        />
                                        <span>Solo productos bajo stock mínimo</span>
                                    </label>
                                </div>
                            </div>

                            <div className="flex items-center gap-2 pt-2 md:pt-0">
                                <button
                                    type="button"
                                    onClick={applyInventarioFilters}
                                    className="inline-flex items-center gap-1.5 rounded-lg border border-[#E7E4DE] bg-white px-3.5 py-1.5 font-semibold text-[#4A4742] hover:bg-[#F3F1ED]"
                                >
                                    <Filter className="size-3.5" />
                                    <span>Filtrar</span>
                                </button>
                                <button
                                    type="button"
                                    onClick={handleDownloadInventarioPdf}
                                    className="inline-flex items-center gap-1.5 rounded-lg bg-[#E31E24] px-4 py-1.5 font-semibold text-white hover:bg-[#c9181d] shadow-xs"
                                >
                                    <Download className="size-3.5" />
                                    <span>Exportar PDF</span>
                                </button>
                            </div>
                        </div>

                        {/* KPIs de Inventario */}
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div className="rounded-xl border border-[#E7E4DE] bg-white p-4 shadow-xs">
                                <div className="flex items-center justify-between text-xs text-[#8A8680]">
                                    <span className="font-medium uppercase">Valorización Total</span>
                                    <CircleDollarSign className="size-4 text-emerald-600" />
                                </div>
                                <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-[#201F1D]">
                                    {formatCurrency(reporteInventario.valorizacionTotal)}
                                </div>
                                <p className="mt-0.5 text-[11px] text-[#8A8680]">Stock disponible × precio venta</p>
                            </div>

                            <div className="rounded-xl border border-[#E7E4DE] bg-white p-4 shadow-xs">
                                <div className="flex items-center justify-between text-xs text-[#8A8680]">
                                    <span className="font-medium uppercase">Productos en Catálogo</span>
                                    <Layers className="size-4 text-blue-600" />
                                </div>
                                <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-[#201F1D]">
                                    {reporteInventario.totalProductos}
                                </div>
                                <p className="mt-0.5 text-[11px] text-[#8A8680]">Productos activos registrados</p>
                            </div>

                            <div className="rounded-xl border border-[#E7E4DE] bg-white p-4 shadow-xs">
                                <div className="flex items-center justify-between text-xs text-[#8A8680]">
                                    <span className="font-medium uppercase">Unidades Físicas</span>
                                    <Boxes className="size-4 text-purple-600" />
                                </div>
                                <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-[#201F1D]">
                                    {reporteInventario.totalUnidades}
                                </div>
                                <p className="mt-0.5 text-[11px] text-[#8A8680]">Disponibles para venta/operación</p>
                            </div>

                            <div className="rounded-xl border border-[#E7E4DE] bg-white p-4 shadow-xs">
                                <div className="flex items-center justify-between text-xs text-[#8A8680]">
                                    <span className="font-medium uppercase">Bajo Stock Mínimo</span>
                                    <AlertTriangle className="size-4 text-[#E31E24]" />
                                </div>
                                <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-[#E31E24]">
                                    {reporteInventario.totalBajoMinimo}
                                </div>
                                <p className="mt-0.5 text-[11px] text-[#8A8680]">Requieren reposición urgente</p>
                            </div>
                        </div>

                        {/* Tabla de Existencias de Inventario */}
                        <div className="overflow-hidden rounded-xl border border-[#E7E4DE] bg-white shadow-xs">
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-xs">
                                    <thead className="border-b border-[#E7E4DE] bg-[#FAFAF8] text-[11px] font-semibold text-[#8A8680] uppercase tracking-wider">
                                        <tr>
                                            <th className="px-4 py-3">Código</th>
                                            <th className="px-4 py-3">Producto</th>
                                            <th className="px-4 py-3 text-center">Tipo</th>
                                            <th className="px-4 py-3 text-center">U.M.</th>
                                            <th className="px-4 py-3 text-right">Precio Venta</th>
                                            <th className="px-4 py-3 text-center">Stock Mínimo</th>
                                            <th className="px-4 py-3 text-center">Stock Actual</th>
                                            <th className="px-4 py-3 text-right">Valorización</th>
                                            <th className="px-4 py-3 text-center">Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-[#F3F1ED]">
                                        {reporteInventario.productos.length === 0 ? (
                                            <tr>
                                                <td colSpan={9} className="py-8 text-center text-[#8A8680]">
                                                    No se encontraron productos con los filtros seleccionados.
                                                </td>
                                            </tr>
                                        ) : (
                                            reporteInventario.productos.map((p) => (
                                                <tr key={p.id} className="hover:bg-[#FAFAF8] transition-colors">
                                                    <td className="px-4 py-3 font-mono font-bold text-[#201F1D]">
                                                        {p.codigo}
                                                    </td>
                                                    <td className="px-4 py-3 font-medium text-[#201F1D]">{p.nombre}</td>
                                                    <td className="px-4 py-3 text-center">
                                                        <span className="rounded-md border border-[#E7E4DE] bg-[#F8F9FA] px-2 py-0.5 text-[10px] text-[#6B6965]">
                                                            {p.serializado ? 'Serializado' : 'A Granel'}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-3 text-center font-mono text-[#4A4742]">
                                                        {p.unidad_medida}
                                                    </td>
                                                    <td className="px-4 py-3 text-right font-mono font-semibold text-[#201F1D]">
                                                        {formatCurrency(p.precio_venta)}
                                                    </td>
                                                    <td className="px-4 py-3 text-center font-mono text-[#4A4742]">
                                                        {p.stock_minimo !== null ? p.stock_minimo : '—'}
                                                    </td>
                                                    <td className="px-4 py-3 text-center">
                                                        <span
                                                            className={`inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 font-mono text-[11px] font-bold ${
                                                                p.bajo_minimo
                                                                    ? 'bg-red-100 text-[#E31E24]'
                                                                    : 'bg-[#F3F1ED] text-[#201F1D]'
                                                            }`}
                                                        >
                                                            {p.bajo_minimo && (
                                                                <AlertTriangle className="size-3 text-[#E31E24]" />
                                                            )}
                                                            {p.stock_disponible}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-3 text-right font-mono font-bold text-[#201F1D]">
                                                        {formatCurrency(p.valorizacion)}
                                                    </td>
                                                    <td className="px-4 py-3 text-center">
                                                        <span
                                                            className={`inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold uppercase ${
                                                                p.bajo_minimo
                                                                    ? 'bg-red-100 text-[#E31E24]'
                                                                    : 'bg-emerald-100 text-emerald-800'
                                                            }`}
                                                        >
                                                            {p.bajo_minimo ? 'Bajo Mínimo' : 'Normal'}
                                                        </span>
                                                    </td>
                                                </tr>
                                            ))
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
