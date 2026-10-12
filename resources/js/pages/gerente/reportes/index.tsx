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
    Inbox,
} from 'lucide-react';
import { useState } from 'react';

import GerenteLayout from '@/layouts/gerente-layout';
import ReportesRoutes from '@/routes/gerente/reportes';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { NativeSelect } from '@/components/ui/native-select';
import { Empty } from '@/components/ui/empty';

type ComercialData = {
    fechaDesde: string;
    fechaHasta: string;
    totalVentas: number;
    ventasOriginales: number;
    notasCredito: number;
    notasDebito: number;
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
    productosSinCosto: number;
    paginacion: {
        pagina: number;
        ultima: number;
        total: number;
        anterior: string | null;
        siguiente: string | null;
    } | null;
    productos: Array<{
        id: number;
        codigo: string;
        nombre: string;
        unidad_medida: string;
        costo_promedio: number | null;
        sin_costo: boolean;
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
            ReportesRoutes.index.url({ current_team: currentTeam.slug }),
            { tipo: newTipo },
            { preserveState: true },
        );
    };

    const applyComercialFilters = () => {
        router.get(
            ReportesRoutes.index.url({ current_team: currentTeam.slug }),
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
            ReportesRoutes.index.url({ current_team: currentTeam.slug }),
            {
                tipo: 'inventario',
                sede_id: sedeId || undefined,
                solo_bajo_minimo: soloBajoMinimo ? '1' : undefined,
            },
            { preserveState: true },
        );
    };

    const handleDownloadComercialPdf = () => {
        window.open(
            ReportesRoutes.comercial.pdf.url(
                { current_team: currentTeam.slug },
                {
                    query: {
                        fecha_desde: fechaDesde,
                        fecha_hasta: fechaHasta,
                        ...(vendedorId ? { vendedor_id: vendedorId } : {}),
                    },
                },
            ),
            '_blank',
        );
    };

    const handleDownloadInventarioPdf = () => {
        window.open(
            ReportesRoutes.inventario.pdf.url(
                { current_team: currentTeam.slug },
                {
                    query: {
                        ...(sedeId ? { sede_id: sedeId } : {}),
                        ...(soloBajoMinimo ? { solo_bajo_minimo: '1' } : {}),
                    },
                },
            ),
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
                                    <label
                                        htmlFor="gerente-reportes-fecha-desde"
                                        className="text-foreground/80 block font-semibold"
                                    >
                                        Fecha Desde
                                    </label>
                                    <input
                                        id="gerente-reportes-fecha-desde"
                                        type="date"
                                        value={fechaDesde}
                                        onChange={(e) =>
                                            setFechaDesde(e.target.value)
                                        }
                                        className="border-border bg-muted/40 focus:border-primary mt-1 w-full rounded-lg border px-3 py-1.5 focus:outline-none"
                                    />
                                </div>
                                <div>
                                    <label
                                        htmlFor="gerente-reportes-fecha-hasta"
                                        className="text-foreground/80 block font-semibold"
                                    >
                                        Fecha Hasta
                                    </label>
                                    <input
                                        id="gerente-reportes-fecha-hasta"
                                        type="date"
                                        value={fechaHasta}
                                        onChange={(e) =>
                                            setFechaHasta(e.target.value)
                                        }
                                        className="border-border bg-muted/40 focus:border-primary mt-1 w-full rounded-lg border px-3 py-1.5 focus:outline-none"
                                    />
                                </div>
                                <div>
                                    <label
                                        htmlFor="gerente-reportes-vendedor"
                                        className="text-foreground/80 block font-semibold"
                                    >
                                        Vendedor
                                    </label>
                                    <NativeSelect
                                        id="gerente-reportes-vendedor"
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
                                    </NativeSelect>
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
                                        Ventas netas
                                    </span>
                                    <CircleDollarSign className="text-success-strong size-4" />
                                </div>
                                <div className="text-foreground mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold">
                                    {formatCurrency(
                                        reporteComercial.totalVentas,
                                    )}
                                </div>
                                <p className="text-muted-foreground mt-0.5 text-xs">
                                    Originales{' '}
                                    {formatCurrency(
                                        reporteComercial.ventasOriginales,
                                    )}{' '}
                                    − notas de crédito{' '}
                                    {formatCurrency(
                                        reporteComercial.notasCredito,
                                    )}{' '}
                                    + notas de débito{' '}
                                    {formatCurrency(
                                        reporteComercial.notasDebito,
                                    )}
                                    , aceptadas en el período.
                                </p>
                            </div>

                            <div className="border-border bg-card rounded-xl border p-4 shadow-xs">
                                <div className="text-muted-foreground flex items-center justify-between text-xs">
                                    <span className="font-medium uppercase">
                                        Total de Operaciones
                                    </span>
                                    <FileText className="text-foreground size-4" />
                                </div>
                                <div className="text-foreground mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold">
                                    {reporteComercial.cantidadVentas}
                                </div>
                                <p className="text-muted-foreground mt-0.5 text-xs">
                                    Ventas confirmadas en el período
                                </p>
                            </div>

                            <div className="border-border bg-card rounded-xl border p-4 shadow-xs">
                                <div className="text-muted-foreground flex items-center justify-between text-xs">
                                    <span className="font-medium uppercase">
                                        Ticket Promedio
                                    </span>
                                    <TrendingUp className="text-foreground size-4" />
                                </div>
                                <div className="text-foreground mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold">
                                    {formatCurrency(
                                        reporteComercial.ticketPromedio,
                                    )}
                                </div>
                                <p className="text-muted-foreground mt-0.5 text-xs">
                                    Promedio por comprobante
                                </p>
                            </div>

                            <div className="border-border bg-card rounded-xl border p-4 shadow-xs">
                                <div className="text-muted-foreground flex items-center justify-between text-xs">
                                    <span className="font-medium uppercase">
                                        Conversión Cotizaciones
                                    </span>
                                    <Percent className="text-warning-strong size-4" />
                                </div>
                                <div className="text-foreground mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold">
                                    {reporteComercial.tasaConversion}%
                                </div>
                                <p className="text-muted-foreground mt-0.5 text-xs">
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
                                    <Table className="w-full text-left text-xs">
                                        <TableHeader className="border-border bg-muted/40 text-muted-foreground border-b text-xs uppercase">
                                            <TableRow>
                                                <TableHead>Vendedor</TableHead>
                                                <TableHead className="text-center">
                                                    Operaciones
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    Total Facturado
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody className="divide-border divide-y">
                                            {reporteComercial.porVendedor
                                                .length === 0 ? (
                                                <TableRow>
                                                    <TableCell colSpan={3}>
                                                        <Empty
                                                            icon={<Inbox />}
                                                            title="Sin ventas en este período."
                                                        />
                                                    </TableCell>
                                                </TableRow>
                                            ) : (
                                                reporteComercial.porVendedor.map(
                                                    (v) => (
                                                        <TableRow
                                                            key={v.nombre}
                                                        >
                                                            <TableCell className="text-foreground px-3 py-2.5 font-medium">
                                                                {v.nombre}
                                                            </TableCell>
                                                            <TableCell className="px-3 py-2.5 text-center font-mono">
                                                                {v.cantidad}
                                                            </TableCell>
                                                            <TableCell className="text-foreground px-3 py-2.5 text-right font-mono font-bold">
                                                                {formatCurrency(
                                                                    v.monto,
                                                                )}
                                                            </TableCell>
                                                        </TableRow>
                                                    ),
                                                )
                                            )}
                                        </TableBody>
                                    </Table>
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
                                    <Table className="w-full text-left text-xs">
                                        <TableHeader className="border-border bg-muted/40 text-muted-foreground border-b text-xs uppercase">
                                            <TableRow>
                                                <TableHead>Cliente</TableHead>
                                                <TableHead className="text-center">
                                                    Compras
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    Total
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody className="divide-border divide-y">
                                            {reporteComercial.topClientes
                                                .length === 0 ? (
                                                <TableRow>
                                                    <TableCell colSpan={3}>
                                                        <Empty
                                                            icon={<Inbox />}
                                                            title="Sin datos de clientes."
                                                        />
                                                    </TableCell>
                                                </TableRow>
                                            ) : (
                                                reporteComercial.topClientes.map(
                                                    (c, i) => (
                                                        <TableRow
                                                            key={c.cliente}
                                                        >
                                                            <TableCell className="text-foreground max-w-xs truncate px-3 py-2.5 font-medium">
                                                                <b className="text-primary-strong mr-1">
                                                                    #{i + 1}
                                                                </b>{' '}
                                                                {c.cliente}
                                                            </TableCell>
                                                            <TableCell className="px-3 py-2.5 text-center font-mono">
                                                                {c.cantidad}
                                                            </TableCell>
                                                            <TableCell className="text-foreground px-3 py-2.5 text-right font-mono font-bold">
                                                                {formatCurrency(
                                                                    c.total,
                                                                )}
                                                            </TableCell>
                                                        </TableRow>
                                                    ),
                                                )
                                            )}
                                        </TableBody>
                                    </Table>
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
                                <Table className="w-full text-left text-xs">
                                    <TableHeader className="border-border bg-muted/40 text-muted-foreground border-b text-xs uppercase">
                                        <TableRow>
                                            <TableHead>Ítem</TableHead>
                                            <TableHead className="text-center">
                                                Cantidad Total
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Monto Facturado
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody className="divide-border divide-y">
                                        {reporteComercial.topItems.length ===
                                        0 ? (
                                            <TableRow>
                                                <TableCell colSpan={3}>
                                                    <Empty
                                                        icon={<Inbox />}
                                                        title="Sin ítems registrados."
                                                    />
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            reporteComercial.topItems.map(
                                                (item, i) => (
                                                    <TableRow key={item.nombre}>
                                                        <TableCell className="text-foreground px-4 py-3 font-medium">
                                                            <b className="text-primary-strong mr-2">
                                                                #{i + 1}
                                                            </b>{' '}
                                                            {item.nombre}
                                                        </TableCell>
                                                        <TableCell className="text-foreground/80 px-4 py-3 text-center font-mono font-semibold">
                                                            {item.cantidad}
                                                        </TableCell>
                                                        <TableCell className="text-foreground px-4 py-3 text-right font-mono font-bold">
                                                            {formatCurrency(
                                                                item.monto,
                                                            )}
                                                        </TableCell>
                                                    </TableRow>
                                                ),
                                            )
                                        )}
                                    </TableBody>
                                </Table>
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
                                    <label
                                        htmlFor="gerente-reportes-sede-almacen"
                                        className="text-foreground/80 block font-semibold"
                                    >
                                        Sede / Almacén
                                    </label>
                                    <NativeSelect
                                        id="gerente-reportes-sede-almacen"
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
                                    </NativeSelect>
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
                                            className="border-border text-primary-strong rounded"
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

                        {reporteInventario.productosSinCosto > 0 && (
                            <div
                                role="alert"
                                className="rounded-xl border border-amber-500/30 bg-amber-500/10 p-3 text-xs font-medium"
                            >
                                {reporteInventario.productosSinCosto}{' '}
                                producto(s) con stock no tienen costo de compra:
                                la valorización está incompleta. Registra el
                                costo al recibir la mercadería.
                            </div>
                        )}

                        {/* KPIs de Inventario */}
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div className="border-border bg-card rounded-xl border p-4 shadow-xs">
                                <div className="text-muted-foreground flex items-center justify-between text-xs">
                                    <span className="font-medium uppercase">
                                        Valorización Total
                                    </span>
                                    <CircleDollarSign className="text-success-strong size-4" />
                                </div>
                                <div className="text-foreground mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold">
                                    {formatCurrency(
                                        reporteInventario.valorizacionTotal,
                                    )}
                                </div>
                                <p className="text-muted-foreground mt-0.5 text-xs">
                                    Stock disponible × costo promedio de compra
                                </p>
                            </div>

                            <div className="border-border bg-card rounded-xl border p-4 shadow-xs">
                                <div className="text-muted-foreground flex items-center justify-between text-xs">
                                    <span className="font-medium uppercase">
                                        Productos en Catálogo
                                    </span>
                                    <Layers className="text-foreground size-4" />
                                </div>
                                <div className="text-foreground mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold">
                                    {reporteInventario.totalProductos}
                                </div>
                                <p className="text-muted-foreground mt-0.5 text-xs">
                                    Productos activos registrados
                                </p>
                            </div>

                            <div className="border-border bg-card rounded-xl border p-4 shadow-xs">
                                <div className="text-muted-foreground flex items-center justify-between text-xs">
                                    <span className="font-medium uppercase">
                                        Unidades Físicas
                                    </span>
                                    <Boxes className="text-foreground size-4" />
                                </div>
                                <div className="text-foreground mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold">
                                    {reporteInventario.totalUnidades}
                                </div>
                                <p className="text-muted-foreground mt-0.5 text-xs">
                                    Disponibles para venta/operación
                                </p>
                            </div>

                            <div className="border-border bg-card rounded-xl border p-4 shadow-xs">
                                <div className="text-muted-foreground flex items-center justify-between text-xs">
                                    <span className="font-medium uppercase">
                                        Bajo Stock Mínimo
                                    </span>
                                    <AlertTriangle className="text-primary-strong size-4" />
                                </div>
                                <div className="text-primary-strong mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold">
                                    {reporteInventario.totalBajoMinimo}
                                </div>
                                <p className="text-muted-foreground mt-0.5 text-xs">
                                    Requieren reposición urgente
                                </p>
                            </div>
                        </div>

                        {/* Tabla de Existencias de Inventario */}
                        <div className="border-border bg-card overflow-hidden rounded-xl border shadow-xs">
                            <div className="overflow-x-auto">
                                <Table className="w-full text-left text-xs">
                                    <TableHeader className="border-border bg-muted/40 text-muted-foreground border-b text-xs font-semibold tracking-wider uppercase">
                                        <TableRow>
                                            <TableHead>Código</TableHead>
                                            <TableHead>Producto</TableHead>
                                            <TableHead className="text-center">
                                                Tipo
                                            </TableHead>
                                            <TableHead className="text-center">
                                                U.M.
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Costo promedio
                                            </TableHead>
                                            <TableHead className="text-center">
                                                Stock Mínimo
                                            </TableHead>
                                            <TableHead className="text-center">
                                                Stock Actual
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Valorización
                                            </TableHead>
                                            <TableHead className="text-center">
                                                Estado
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody className="divide-border divide-y">
                                        {reporteInventario.productos.length ===
                                        0 ? (
                                            <TableRow>
                                                <TableCell colSpan={9}>
                                                    <Empty
                                                        icon={<Inbox />}
                                                        title="No se encontraron productos con los filtros seleccionados."
                                                    />
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            reporteInventario.productos.map(
                                                (p) => (
                                                    <TableRow
                                                        key={p.id}
                                                        className="hover:bg-muted/40 transition-colors"
                                                    >
                                                        <TableCell className="text-foreground px-4 py-3 font-mono font-bold">
                                                            {p.codigo}
                                                        </TableCell>
                                                        <TableCell className="text-foreground px-4 py-3 font-medium">
                                                            {p.nombre}
                                                        </TableCell>
                                                        <TableCell className="px-4 py-3 text-center">
                                                            <span className="border-border bg-muted/40 text-muted-foreground rounded-md border px-2 py-0.5 text-xs">
                                                                {p.serializado
                                                                    ? 'Serializado'
                                                                    : 'A Granel'}
                                                            </span>
                                                        </TableCell>
                                                        <TableCell className="text-foreground/80 px-4 py-3 text-center font-mono">
                                                            {p.unidad_medida}
                                                        </TableCell>
                                                        <TableCell className="text-foreground px-4 py-3 text-right font-mono font-semibold">
                                                            {p.costo_promedio !==
                                                            null
                                                                ? formatCurrency(
                                                                      p.costo_promedio,
                                                                  )
                                                                : 'Sin costo'}
                                                        </TableCell>
                                                        <TableCell className="text-foreground/80 px-4 py-3 text-center font-mono">
                                                            {p.stock_minimo !==
                                                            null
                                                                ? p.stock_minimo
                                                                : '—'}
                                                        </TableCell>
                                                        <TableCell className="px-4 py-3 text-center">
                                                            <span
                                                                className={`inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 font-mono text-xs font-bold ${
                                                                    p.bajo_minimo
                                                                        ? 'text-primary-strong bg-red-100'
                                                                        : 'bg-background text-foreground'
                                                                }`}
                                                            >
                                                                {p.bajo_minimo && (
                                                                    <AlertTriangle className="text-primary-strong size-3" />
                                                                )}
                                                                {
                                                                    p.stock_disponible
                                                                }
                                                            </span>
                                                        </TableCell>
                                                        <TableCell className="text-foreground px-4 py-3 text-right font-mono font-bold">
                                                            {formatCurrency(
                                                                p.valorizacion,
                                                            )}
                                                        </TableCell>
                                                        <TableCell className="px-4 py-3 text-center">
                                                            <span
                                                                className={`inline-flex rounded-full px-2 py-0.5 text-xs font-bold uppercase ${
                                                                    p.bajo_minimo
                                                                        ? 'text-primary-strong bg-red-100'
                                                                        : 'bg-emerald-100 text-emerald-800'
                                                                }`}
                                                            >
                                                                {p.bajo_minimo
                                                                    ? 'Bajo Mínimo'
                                                                    : 'Normal'}
                                                            </span>
                                                        </TableCell>
                                                    </TableRow>
                                                ),
                                            )
                                        )}
                                    </TableBody>
                                </Table>
                            </div>
                            {reporteInventario.paginacion &&
                                reporteInventario.paginacion.ultima > 1 && (
                                    <div className="border-border text-muted-foreground flex items-center justify-between border-t px-4 py-3 text-xs">
                                        <span>
                                            Página{' '}
                                            {
                                                reporteInventario.paginacion
                                                    .pagina
                                            }{' '}
                                            de{' '}
                                            {
                                                reporteInventario.paginacion
                                                    .ultima
                                            }{' '}
                                            (
                                            {reporteInventario.paginacion.total}{' '}
                                            productos)
                                        </span>
                                        <div className="flex gap-2">
                                            <button
                                                type="button"
                                                disabled={
                                                    !reporteInventario
                                                        .paginacion.anterior
                                                }
                                                onClick={() =>
                                                    reporteInventario.paginacion
                                                        ?.anterior &&
                                                    router.get(
                                                        reporteInventario
                                                            .paginacion
                                                            .anterior,
                                                    )
                                                }
                                                className="border-border rounded-md border px-3 py-1 font-semibold disabled:opacity-40"
                                            >
                                                Anterior
                                            </button>
                                            <button
                                                type="button"
                                                disabled={
                                                    !reporteInventario
                                                        .paginacion.siguiente
                                                }
                                                onClick={() =>
                                                    reporteInventario.paginacion
                                                        ?.siguiente &&
                                                    router.get(
                                                        reporteInventario
                                                            .paginacion
                                                            .siguiente,
                                                    )
                                                }
                                                className="border-border rounded-md border px-3 py-1 font-semibold disabled:opacity-40"
                                            >
                                                Siguiente
                                            </button>
                                        </div>
                                    </div>
                                )}
                        </div>
                    </div>
                )}
            </div>
        </GerenteLayout>
    );
}
