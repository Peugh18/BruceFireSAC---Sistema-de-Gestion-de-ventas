import {
    AlertTriangle,
    Award,
    BadgeAlert,
    BarChart3,
    Boxes,
    BrainCircuit,
    Calendar,
    CheckCircle2,
    CircleDollarSign,
    Clock,
    FileCheck,
    FileText,
    Info,
    MessageCircle,
    Percent,
    ShieldAlert,
    Sparkles,
    TrendingUp,
    Users,
    Wrench,
} from 'lucide-react';

import SaludDelSistema, {
    type SaludDelSistemaData,
} from '@/components/salud-del-sistema';
import GerenteLayout from '@/layouts/gerente-layout';
import { fechaCorta } from '@/lib/utils';

type DashboardMetrics = {
    ventasDia: number;
    ventasMes: number;
    facturacionMes: number;
    montoCobrado: number;
    cuentasPorCobrar: number;
    vencidoPorCobrar: number;
    cotizacionesPendientes: number;
    tasaConversion: number;
    ordenesEnProceso: number;
    equiposProximosAtencion: number;
    stockCritico: number;
    documentosSunatError: number;
};

type DashboardCharts = {
    ventasMensuales: Array<{ mes: string; monto: number }>;
    ventasPorItem: Array<{ nombre: string; monto: number }>;
    serviciosPorTipo: Array<{ tipo: string; cantidad: number }>;
    carteraPorEstado: Array<{ estado: string; monto: number }>;
    topClientes: Array<{ cliente: string; total: number }>;
    productosMayorMovimiento: Array<{ producto: string; cantidad: number }>;
};

type AiRetentionFactor = {
    factor: string;
    impacto: string;
    detalle: string;
};

type AiRetentionClient = {
    clientId: number;
    cliente: string;
    documento: string;
    telefono: string | null;
    probabilidad: number;
    categoria: 'alta' | 'media' | 'baja';
    recenciaDias: number;
    frecuencia: number;
    montoTotal: number;
    ticketPromedio: number;
    comproRecarga: boolean;
    factores: {
        positivos?: AiRetentionFactor[];
        negativos?: AiRetentionFactor[];
    };
};

type AiRetentionData = {
    totalEvaluados: number;
    distribucion: {
        alta: { cantidad: number; porcentaje: number };
        media: { cantidad: number; porcentaje: number };
        baja: { cantidad: number; porcentaje: number };
    };
    topClientes: AiRetentionClient[];
    modelo: {
        disponible: boolean;
        nombre: string;
        aucRoc: number;
        accuracy: number;
        precision: number;
        recall: number;
        fechaEntrenamiento: string | null;
        historial: {
            fecha: string;
            modelo: string;
            aucRoc: number;
            top100: number | null;
        }[];
    };
} | null;

type GerenteDashboardProps = {
    metrics: DashboardMetrics;
    charts: DashboardCharts;
    aiRetention?: AiRetentionData;
    sistema: SaludDelSistemaData;
};

function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('es-PE', {
        style: 'currency',
        currency: 'PEN',
        minimumFractionDigits: 2,
    }).format(amount);
}

function formatNumber(num: number): string {
    return new Intl.NumberFormat('es-PE').format(num);
}

export default function GerenteDashboard({
    metrics,
    charts,
    aiRetention,
    sistema,
}: GerenteDashboardProps) {
    const maxVentaMensual = Math.max(
        ...charts.ventasMensuales.map((m) => m.monto),
        1,
    );
    const maxVentaItem = Math.max(
        ...charts.ventasPorItem.map((item) => item.monto),
        1,
    );
    const maxMovimiento = Math.max(
        ...charts.productosMayorMovimiento.map((p) => p.cantidad),
        1,
    );
    const totalServicios =
        charts.serviciosPorTipo.reduce((acc, curr) => acc + curr.cantidad, 0) ||
        1;

    return (
        <GerenteLayout title="Dashboard Gerencial">
            <div className="space-y-7">
                {/* Cabecera del Dashboard */}
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div>
                        <h1 className="text-foreground font-['Oswald',sans-serif] text-2xl font-bold tracking-wide uppercase">
                            Control Gerencial & Rendimiento
                        </h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Supervisión unificada de facturación, cobranzas,
                            almacén y servicios técnicos.
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <div className="border-border bg-card text-foreground/80 flex items-center gap-2 rounded-lg border px-3.5 py-1.5 text-xs font-semibold">
                            <Calendar className="text-primary size-3.5" />
                            <span>
                                {new Date().toLocaleDateString('es-PE', {
                                    month: 'long',
                                    year: 'numeric',
                                })}
                            </span>
                        </div>
                    </div>
                </div>

                <SaludDelSistema salud={sistema} />

                {/* Grid de 12 Tarjetas KPI */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    {/* 1. Ventas del Día */}
                    <div className="border-border bg-card rounded-xl border p-5 shadow-xs">
                        <div className="text-muted-foreground flex items-center justify-between text-xs">
                            <span className="font-medium tracking-wider uppercase">
                                Ventas de Hoy
                            </span>
                            <div className="bg-muted/40 text-foreground flex size-7 items-center justify-center rounded-lg">
                                <CircleDollarSign className="size-4 text-emerald-600" />
                            </div>
                        </div>
                        <div className="text-foreground mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold">
                            {formatCurrency(metrics.ventasDia)}
                        </div>
                        <p className="text-muted-foreground mt-1 text-[11px]">
                            Monto total vendido hoy
                        </p>
                    </div>

                    {/* 2. Ventas del Mes */}
                    <div className="border-border bg-card rounded-xl border p-5 shadow-xs">
                        <div className="text-muted-foreground flex items-center justify-between text-xs">
                            <span className="font-medium tracking-wider uppercase">
                                Ventas del Mes
                            </span>
                            <div className="bg-muted/40 text-foreground flex size-7 items-center justify-center rounded-lg">
                                <TrendingUp className="size-4 text-emerald-600" />
                            </div>
                        </div>
                        <div className="text-foreground mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold">
                            {formatCurrency(metrics.ventasMes)}
                        </div>
                        <p className="text-muted-foreground mt-1 text-[11px]">
                            Total acumulado en el mes
                        </p>
                    </div>

                    {/* 3. Facturación del Mes */}
                    <div className="border-border bg-card rounded-xl border p-5 shadow-xs">
                        <div className="text-muted-foreground flex items-center justify-between text-xs">
                            <span className="font-medium tracking-wider uppercase">
                                Facturación CPE Mes
                            </span>
                            <div className="bg-muted/40 text-foreground flex size-7 items-center justify-center rounded-lg">
                                <FileCheck className="size-4 text-blue-600" />
                            </div>
                        </div>
                        <div className="text-foreground mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold">
                            {formatCurrency(metrics.facturacionMes)}
                        </div>
                        <p className="text-muted-foreground mt-1 text-[11px]">
                            Facturas y boletas emitidas
                        </p>
                    </div>

                    {/* 4. Monto Cobrado */}
                    <div className="border-border bg-card rounded-xl border p-5 shadow-xs">
                        <div className="text-muted-foreground flex items-center justify-between text-xs">
                            <span className="font-medium tracking-wider uppercase">
                                Monto Cobrado
                            </span>
                            <div className="bg-muted/40 text-foreground flex size-7 items-center justify-center rounded-lg">
                                <CheckCircle2 className="size-4 text-emerald-600" />
                            </div>
                        </div>
                        <div className="text-foreground mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold">
                            {formatCurrency(metrics.montoCobrado)}
                        </div>
                        <p className="text-muted-foreground mt-1 text-[11px]">
                            Abonos ingresados este mes
                        </p>
                    </div>

                    {/* 5. Cuentas por Cobrar */}
                    <div className="border-border bg-card rounded-xl border p-5 shadow-xs">
                        <div className="text-muted-foreground flex items-center justify-between text-xs">
                            <span className="font-medium tracking-wider uppercase">
                                Por Cobrar Total
                            </span>
                            <div className="bg-muted/40 text-foreground flex size-7 items-center justify-center rounded-lg">
                                <Clock className="size-4 text-amber-600" />
                            </div>
                        </div>
                        <div className="text-foreground mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold">
                            {formatCurrency(metrics.cuentasPorCobrar)}
                        </div>
                        <p className="text-muted-foreground mt-1 text-[11px]">
                            Saldo pendiente en cartera
                        </p>
                    </div>

                    {/* 6. Vencido por Cobrar */}
                    <div className="border-border bg-card rounded-xl border p-5 shadow-xs">
                        <div className="text-muted-foreground flex items-center justify-between text-xs">
                            <span className="font-medium tracking-wider uppercase">
                                Vencido por Cobrar
                            </span>
                            <div className="bg-muted/40 text-foreground flex size-7 items-center justify-center rounded-lg">
                                <BadgeAlert className="text-primary size-4" />
                            </div>
                        </div>
                        <div className="text-primary mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold">
                            {formatCurrency(metrics.vencidoPorCobrar)}
                        </div>
                        <p className="text-muted-foreground mt-1 text-[11px]">
                            Cuotas con mora superada
                        </p>
                    </div>

                    {/* 7. Cotizaciones Pendientes */}
                    <div className="border-border bg-card rounded-xl border p-5 shadow-xs">
                        <div className="text-muted-foreground flex items-center justify-between text-xs">
                            <span className="font-medium tracking-wider uppercase">
                                Cotizaciones Pendientes
                            </span>
                            <div className="bg-muted/40 text-foreground flex size-7 items-center justify-center rounded-lg">
                                <FileText className="size-4 text-indigo-600" />
                            </div>
                        </div>
                        <div className="text-foreground mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold">
                            {formatNumber(metrics.cotizacionesPendientes)}
                        </div>
                        <p className="text-muted-foreground mt-1 text-[11px]">
                            En negociación comercial
                        </p>
                    </div>

                    {/* 8. Tasa de Conversión */}
                    <div className="border-border bg-card rounded-xl border p-5 shadow-xs">
                        <div className="text-muted-foreground flex items-center justify-between text-xs">
                            <span className="font-medium tracking-wider uppercase">
                                Tasa de Conversión
                            </span>
                            <div className="bg-muted/40 text-foreground flex size-7 items-center justify-center rounded-lg">
                                <Percent className="size-4 text-purple-600" />
                            </div>
                        </div>
                        <div className="text-foreground mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold">
                            {metrics.tasaConversion}%
                        </div>
                        <p className="text-muted-foreground mt-1 text-[11px]">
                            Cotizaciones ganadas vs total
                        </p>
                    </div>

                    {/* 9. Órdenes en Proceso */}
                    <div className="border-border bg-card rounded-xl border p-5 shadow-xs">
                        <div className="text-muted-foreground flex items-center justify-between text-xs">
                            <span className="font-medium tracking-wider uppercase">
                                Órdenes en Proceso
                            </span>
                            <div className="bg-muted/40 text-foreground flex size-7 items-center justify-center rounded-lg">
                                <Wrench className="size-4 text-cyan-600" />
                            </div>
                        </div>
                        <div className="text-foreground mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold">
                            {formatNumber(metrics.ordenesEnProceso)}
                        </div>
                        <p className="text-muted-foreground mt-1 text-[11px]">
                            En planta o campo activos
                        </p>
                    </div>

                    {/* 10. Equipos Próximos a Atención */}
                    <div className="border-border bg-card rounded-xl border p-5 shadow-xs">
                        <div className="text-muted-foreground flex items-center justify-between text-xs">
                            <span className="font-medium tracking-wider uppercase">
                                Próximos a Atención
                            </span>
                            <div className="bg-muted/40 text-foreground flex size-7 items-center justify-center rounded-lg">
                                <AlertTriangle className="size-4 text-amber-500" />
                            </div>
                        </div>
                        <div className="text-foreground mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold">
                            {formatNumber(metrics.equiposProximosAtencion)}
                        </div>
                        <p className="text-muted-foreground mt-1 text-[11px]">
                            Vencimiento en los próx. 30 días
                        </p>
                    </div>

                    {/* 11. Stock Crítico */}
                    <div className="border-border bg-card rounded-xl border p-5 shadow-xs">
                        <div className="text-muted-foreground flex items-center justify-between text-xs">
                            <span className="font-medium tracking-wider uppercase">
                                Stock Crítico
                            </span>
                            <div className="bg-muted/40 text-foreground flex size-7 items-center justify-center rounded-lg">
                                <Boxes className="size-4 text-orange-600" />
                            </div>
                        </div>
                        <div className="text-foreground mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold">
                            {formatNumber(metrics.stockCritico)}
                        </div>
                        <p className="text-muted-foreground mt-1 text-[11px]">
                            Productos bajo el stock mínimo
                        </p>
                    </div>

                    {/* 12. Documentos SUNAT con Error */}
                    <div className="border-border bg-card rounded-xl border p-5 shadow-xs">
                        <div className="text-muted-foreground flex items-center justify-between text-xs">
                            <span className="font-medium tracking-wider uppercase">
                                SUNAT con Error
                            </span>
                            <div className="bg-muted/40 text-foreground flex size-7 items-center justify-center rounded-lg">
                                <ShieldAlert className="text-primary size-4" />
                            </div>
                        </div>
                        <div className="text-primary mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold">
                            {formatNumber(metrics.documentosSunatError)}
                        </div>
                        <p className="text-muted-foreground mt-1 text-[11px]">
                            Rechazos o excepciones SUNAT
                        </p>
                    </div>
                </div>

                {/* Sección Gráfica y Analítica */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* Gráfico 1: Ventas Mensuales (Últimos 6 meses) */}
                    <div className="border-border bg-card rounded-xl border p-6 shadow-xs">
                        <div className="border-border flex items-center justify-between border-b pb-4">
                            <div>
                                <h2 className="text-foreground font-['Oswald',sans-serif] text-base font-bold uppercase">
                                    Ventas Mensuales
                                </h2>
                                <p className="text-muted-foreground text-xs">
                                    Evolución de ventas en los últimos 6 meses
                                </p>
                            </div>
                            <BarChart3 className="text-muted-foreground size-5" />
                        </div>

                        <div className="mt-6 flex h-48 items-end gap-3 pt-4">
                            {charts.ventasMensuales.map((item) => {
                                const heightPercent = Math.max(
                                    Math.round(
                                        (item.monto / maxVentaMensual) * 100,
                                    ),
                                    6,
                                );
                                return (
                                    <div
                                        key={item.mes}
                                        className="flex flex-1 flex-col items-center gap-2"
                                    >
                                        <span className="text-muted-foreground font-mono text-[10px]">
                                            {formatCurrency(item.monto)}
                                        </span>
                                        <div
                                            className="bg-card hover:bg-primary w-full rounded-t-md transition-all"
                                            style={{
                                                height: `${heightPercent}%`,
                                            }}
                                        />
                                        <span className="text-foreground/80 font-mono text-[10px] font-semibold uppercase">
                                            {item.mes}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    {/* Gráfico 2: Cartera por Estado */}
                    <div className="border-border bg-card rounded-xl border p-6 shadow-xs">
                        <div className="border-border flex items-center justify-between border-b pb-4">
                            <div>
                                <h2 className="text-foreground font-['Oswald',sans-serif] text-base font-bold uppercase">
                                    Estado de Cartera
                                </h2>
                                <p className="text-muted-foreground text-xs">
                                    Créditos al día vs vencidos vs recaudación
                                    mensual
                                </p>
                            </div>
                            <CircleDollarSign className="text-muted-foreground size-5" />
                        </div>

                        <div className="mt-6 space-y-4">
                            {charts.carteraPorEstado.map((cartera) => {
                                const totalCartera =
                                    charts.carteraPorEstado.reduce(
                                        (acc, curr) => acc + curr.monto,
                                        0,
                                    ) || 1;
                                const percent = Math.round(
                                    (cartera.monto / totalCartera) * 100,
                                );
                                const isVencido = cartera.estado
                                    .toLowerCase()
                                    .includes('vencido');
                                const isCobrado = cartera.estado
                                    .toLowerCase()
                                    .includes('cobrado');

                                return (
                                    <div
                                        key={cartera.estado}
                                        className="space-y-1.5"
                                    >
                                        <div className="flex justify-between text-xs">
                                            <span className="text-foreground font-semibold">
                                                {cartera.estado}
                                            </span>
                                            <span className="text-foreground/80 font-mono">
                                                {formatCurrency(cartera.monto)}{' '}
                                                ({percent}%)
                                            </span>
                                        </div>
                                        <div className="bg-background h-3 w-full overflow-hidden rounded-full">
                                            <div
                                                className={`h-full rounded-full transition-all ${
                                                    isVencido
                                                        ? 'bg-primary'
                                                        : isCobrado
                                                          ? 'bg-emerald-600'
                                                          : 'bg-card'
                                                }`}
                                                style={{ width: `${percent}%` }}
                                            />
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    {/* Gráfico 3: Ventas por Producto / Servicio (Top 5) */}
                    <div className="border-border bg-card rounded-xl border p-6 shadow-xs">
                        <div className="border-border flex items-center justify-between border-b pb-4">
                            <div>
                                <h2 className="text-foreground font-['Oswald',sans-serif] text-base font-bold uppercase">
                                    Top Productos / Servicios por Venta
                                </h2>
                                <p className="text-muted-foreground text-xs">
                                    Ítems con mayor facturación en ventas
                                </p>
                            </div>
                            <Award className="text-muted-foreground size-5" />
                        </div>

                        <div className="divide-border mt-4 divide-y">
                            {charts.ventasPorItem.length === 0 ? (
                                <p className="text-muted-foreground py-6 text-center text-xs">
                                    Sin ventas registradas aún
                                </p>
                            ) : (
                                charts.ventasPorItem.map((item, idx) => {
                                    const percent = Math.round(
                                        (item.monto / maxVentaItem) * 100,
                                    );
                                    return (
                                        <div key={item.nombre} className="py-3">
                                            <div className="flex items-center justify-between text-xs">
                                                <span className="text-foreground truncate font-medium">
                                                    <b className="text-primary mr-2 font-mono">
                                                        #{idx + 1}
                                                    </b>{' '}
                                                    {item.nombre}
                                                </span>
                                                <span className="text-foreground font-mono font-semibold">
                                                    {formatCurrency(item.monto)}
                                                </span>
                                            </div>
                                            <div className="bg-background mt-2 h-1.5 w-full rounded-full">
                                                <div
                                                    className="bg-primary h-full rounded-full"
                                                    style={{
                                                        width: `${percent}%`,
                                                    }}
                                                />
                                            </div>
                                        </div>
                                    );
                                })
                            )}
                        </div>
                    </div>

                    {/* Gráfico 4: Servicios por Tipo */}
                    <div className="border-border bg-card rounded-xl border p-6 shadow-xs">
                        <div className="border-border flex items-center justify-between border-b pb-4">
                            <div>
                                <h2 className="text-foreground font-['Oswald',sans-serif] text-base font-bold uppercase">
                                    Distribución de Servicios Técnicos
                                </h2>
                                <p className="text-muted-foreground text-xs">
                                    Órdenes según modalidad de atención técnica
                                </p>
                            </div>
                            <Wrench className="text-muted-foreground size-5" />
                        </div>

                        <div className="mt-4 space-y-3">
                            {charts.serviciosPorTipo.length === 0 ? (
                                <p className="text-muted-foreground py-6 text-center text-xs">
                                    Sin servicios registrados aún
                                </p>
                            ) : (
                                charts.serviciosPorTipo.map((item) => {
                                    const percent = Math.round(
                                        (item.cantidad / totalServicios) * 100,
                                    );
                                    return (
                                        <div
                                            key={item.tipo}
                                            className="border-border flex items-center justify-between rounded-lg border p-3"
                                        >
                                            <div className="flex items-center gap-3">
                                                <div className="bg-muted/40 text-foreground flex size-7 items-center justify-center rounded-md text-xs font-bold">
                                                    {percent}%
                                                </div>
                                                <span className="text-foreground text-xs font-medium">
                                                    {item.tipo}
                                                </span>
                                            </div>
                                            <span className="text-foreground/80 font-mono text-xs font-bold">
                                                {formatNumber(item.cantidad)}{' '}
                                                órdenes
                                            </span>
                                        </div>
                                    );
                                })
                            )}
                        </div>
                    </div>

                    {/* Gráfico 5: Top Clientes */}
                    <div className="border-border bg-card rounded-xl border p-6 shadow-xs">
                        <div className="border-border flex items-center justify-between border-b pb-4">
                            <div>
                                <h2 className="text-foreground font-['Oswald',sans-serif] text-base font-bold uppercase">
                                    Top 5 Clientes en Facturación
                                </h2>
                                <p className="text-muted-foreground text-xs">
                                    Clientes con mayor volumen acumulado
                                </p>
                            </div>
                            <Users className="text-muted-foreground size-5" />
                        </div>

                        <div className="divide-border mt-4 divide-y">
                            {charts.topClientes.length === 0 ? (
                                <p className="text-muted-foreground py-6 text-center text-xs">
                                    Sin historial de clientes aún
                                </p>
                            ) : (
                                charts.topClientes.map((c, idx) => (
                                    <div
                                        key={c.cliente}
                                        className="flex items-center justify-between py-3"
                                    >
                                        <div className="flex items-center gap-3">
                                            <div className="bg-card flex size-6 items-center justify-center rounded-full font-mono text-[10px] font-bold text-white">
                                                {idx + 1}
                                            </div>
                                            <span className="text-foreground text-xs font-medium">
                                                {c.cliente}
                                            </span>
                                        </div>
                                        <span className="text-foreground font-mono text-xs font-bold">
                                            {formatCurrency(c.total)}
                                        </span>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>

                    {/* Gráfico 6: Productos con Mayor Movimiento */}
                    <div className="border-border bg-card rounded-xl border p-6 shadow-xs">
                        <div className="border-border flex items-center justify-between border-b pb-4">
                            <div>
                                <h2 className="text-foreground font-['Oswald',sans-serif] text-base font-bold uppercase">
                                    Productos / Repuestos con Mayor Rotación
                                </h2>
                                <p className="text-muted-foreground text-xs">
                                    Mayor número de salidas registradas en
                                    Kardex
                                </p>
                            </div>
                            <Boxes className="text-muted-foreground size-5" />
                        </div>

                        <div className="divide-border mt-4 divide-y">
                            {charts.productosMayorMovimiento.length === 0 ? (
                                <p className="text-muted-foreground py-6 text-center text-xs">
                                    Sin movimientos de Kardex aún
                                </p>
                            ) : (
                                charts.productosMayorMovimiento.map(
                                    (p, _idx) => {
                                        const percent = Math.round(
                                            (p.cantidad / maxMovimiento) * 100,
                                        );
                                        return (
                                            <div
                                                key={p.producto}
                                                className="py-3"
                                            >
                                                <div className="flex items-center justify-between text-xs">
                                                    <span className="text-foreground font-medium">
                                                        {p.producto}
                                                    </span>
                                                    <span className="text-foreground/80 font-mono font-semibold">
                                                        {formatNumber(
                                                            p.cantidad,
                                                        )}{' '}
                                                        unidades
                                                    </span>
                                                </div>
                                                <div className="bg-background mt-2 h-1.5 w-full rounded-full">
                                                    <div
                                                        className="bg-card h-full rounded-full"
                                                        style={{
                                                            width: `${percent}%`,
                                                        }}
                                                    />
                                                </div>
                                            </div>
                                        );
                                    },
                                )
                            )}
                        </div>
                    </div>
                </div>

                {/* Sección 3: IA Predictiva de Recompra (§39.1) */}
                <div className="border-border bg-card rounded-xl border p-6 shadow-xs">
                    {/* Cabecera del Módulo IA */}
                    <div className="border-border flex flex-col justify-between gap-4 border-b pb-5 lg:flex-row lg:items-center">
                        <div className="space-y-1">
                            <div className="flex items-center gap-2.5">
                                <div className="bg-primary/10 text-primary flex size-8 items-center justify-center rounded-lg">
                                    <BrainCircuit className="text-primary size-5" />
                                </div>
                                <h2 className="text-foreground font-['Oswald',sans-serif] text-lg font-bold tracking-wide uppercase">
                                    Predicción de recompra
                                </h2>
                                <span className="border-primary/20 bg-primary/5 text-primary inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-[11px] font-semibold">
                                    <Sparkles className="size-3" />
                                    ML Local
                                </span>
                            </div>
                            <p className="text-muted-foreground text-xs">
                                Probabilidad de que cada cliente vuelva a
                                comprar en los próximos 6 meses, calculada por
                                un modelo entrenado con el historial real de
                                ventas.
                            </p>
                            {aiRetention &&
                                aiRetention.modelo.historial.length > 0 && (
                                    <p className="text-muted-foreground mt-1 text-[11px]">
                                        Precisión por entrenamiento:{' '}
                                        {aiRetention.modelo.historial
                                            .map(
                                                (entrenamiento) =>
                                                    `${fechaCorta(entrenamiento.fecha)} ${Math.round(entrenamiento.aucRoc * 1000) / 10}%${entrenamiento.top100 !== null ? ` (${entrenamiento.top100}/100)` : ''}`,
                                            )
                                            .join(' · ')}
                                    </p>
                                )}
                        </div>

                        {aiRetention && (
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="border-border bg-muted/40 text-foreground rounded-md border px-2.5 py-1 font-mono text-[11px] font-medium">
                                    Modelo:{' '}
                                    <b className="text-foreground">
                                        {aiRetention.modelo.nombre}
                                    </b>
                                </span>
                                <span className="border-border bg-muted/40 text-foreground rounded-md border px-2.5 py-1 font-mono text-[11px] font-medium">
                                    ROC-AUC:{' '}
                                    <b className="font-bold text-emerald-600">
                                        {Math.round(
                                            aiRetention.modelo.aucRoc * 1000,
                                        ) / 10}
                                        %
                                    </b>
                                </span>
                                <span className="border-border bg-muted/40 text-foreground rounded-md border px-2.5 py-1 font-mono text-[11px] font-medium">
                                    Accuracy:{' '}
                                    <b className="text-foreground">
                                        {Math.round(
                                            aiRetention.modelo.accuracy * 1000,
                                        ) / 10}
                                        %
                                    </b>
                                </span>
                                <span className="border-border bg-muted/40 text-foreground rounded-md border px-2.5 py-1 font-mono text-[11px] font-medium">
                                    Precision:{' '}
                                    <b className="text-foreground">
                                        {Math.round(
                                            aiRetention.modelo.precision * 1000,
                                        ) / 10}
                                        %
                                    </b>
                                </span>
                                <span className="border-border bg-muted/40 text-muted-foreground rounded-md border px-2.5 py-1 font-mono text-[11px]">
                                    {formatNumber(aiRetention.totalEvaluados)}{' '}
                                    evaluados
                                </span>
                            </div>
                        )}
                    </div>

                    {!aiRetention || aiRetention.topClientes.length === 0 ? (
                        /* Estado Vacío Elegante */
                        <div className="flex flex-col items-center justify-center py-12 text-center">
                            <div className="bg-muted/50 text-muted-foreground flex size-12 items-center justify-center rounded-full">
                                <BrainCircuit className="text-muted-foreground size-6" />
                            </div>
                            <h3 className="text-foreground mt-3 text-sm font-semibold">
                                La predicción aún no se ha calculado
                            </h3>
                            <p className="text-muted-foreground mt-1 max-w-md text-xs">
                                El sistema la calcula solo cada noche con el
                                historial de ventas. Mañana verás aquí qué
                                clientes tienen más probabilidad de volver a
                                comprar.
                            </p>
                        </div>
                    ) : (
                        <div className="mt-6 space-y-6">
                            {/* Distribución de la Cartera */}
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                {/* Alta */}
                                <div className="rounded-lg border border-emerald-500/20 bg-emerald-500/5 p-4">
                                    <div className="flex items-center justify-between">
                                        <span className="text-xs font-semibold tracking-wider text-emerald-700 uppercase dark:text-emerald-400">
                                            Alta Oportunidad (P ≥ 60%)
                                        </span>
                                        <span className="rounded bg-emerald-500/20 px-1.5 py-0.5 font-mono text-[11px] font-bold text-emerald-700 dark:text-emerald-300">
                                            {
                                                aiRetention.distribucion.alta
                                                    .porcentaje
                                            }
                                            %
                                        </span>
                                    </div>
                                    <div className="mt-2 font-mono text-2xl font-bold text-emerald-700 dark:text-emerald-300">
                                        {formatNumber(
                                            aiRetention.distribucion.alta
                                                .cantidad,
                                        )}{' '}
                                        <span className="text-muted-foreground text-xs font-normal">
                                            empresas
                                        </span>
                                    </div>
                                    <p className="text-muted-foreground mt-1 text-[11px]">
                                        Clientes con alta recurrencia y recencia
                                        óptima
                                    </p>
                                </div>

                                {/* Media */}
                                <div className="rounded-lg border border-amber-500/20 bg-amber-500/5 p-4">
                                    <div className="flex items-center justify-between">
                                        <span className="text-xs font-semibold tracking-wider text-amber-700 uppercase dark:text-amber-400">
                                            Media Probabilidad (35% ≤ P &lt;
                                            60%)
                                        </span>
                                        <span className="rounded bg-amber-500/20 px-1.5 py-0.5 font-mono text-[11px] font-bold text-amber-700 dark:text-amber-300">
                                            {
                                                aiRetention.distribucion.media
                                                    .porcentaje
                                            }
                                            %
                                        </span>
                                    </div>
                                    <div className="mt-2 font-mono text-2xl font-bold text-amber-700 dark:text-amber-300">
                                        {formatNumber(
                                            aiRetention.distribucion.media
                                                .cantidad,
                                        )}{' '}
                                        <span className="text-muted-foreground text-xs font-normal">
                                            empresas
                                        </span>
                                    </div>
                                    <p className="text-muted-foreground mt-1 text-[11px]">
                                        Requieren seguimiento comercial activo
                                    </p>
                                </div>

                                {/* Baja */}
                                <div className="border-border bg-muted/20 rounded-lg border p-4">
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                            Baja Probabilidad (P &lt; 35%)
                                        </span>
                                        <span className="bg-muted text-muted-foreground rounded px-1.5 py-0.5 font-mono text-[11px] font-bold">
                                            {
                                                aiRetention.distribucion.baja
                                                    .porcentaje
                                            }
                                            %
                                        </span>
                                    </div>
                                    <div className="text-foreground mt-2 font-mono text-2xl font-bold">
                                        {formatNumber(
                                            aiRetention.distribucion.baja
                                                .cantidad,
                                        )}{' '}
                                        <span className="text-muted-foreground text-xs font-normal">
                                            empresas
                                        </span>
                                    </div>
                                    <p className="text-muted-foreground mt-1 text-[11px]">
                                        Inactividad prolongada o compras
                                        esporádicas
                                    </p>
                                </div>
                            </div>

                            {/* Top Clientes con Mayor Probabilidad de Recompra */}
                            <div className="space-y-3">
                                <div className="flex items-center justify-between">
                                    <h3 className="text-foreground font-['Oswald',sans-serif] text-sm font-bold tracking-wide uppercase">
                                        Top Clientes Prioritarios para Gestión
                                        Comercial
                                    </h3>
                                    <span className="text-muted-foreground text-[11px]">
                                        Ordenados por propensión matemática de
                                        recompra
                                    </span>
                                </div>

                                <div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                                    {aiRetention.topClientes.map((c) => {
                                        const isAlta = c.categoria === 'alta';
                                        const isMedia = c.categoria === 'media';

                                        return (
                                            <div
                                                key={c.clientId}
                                                className="border-border bg-card hover:border-primary/40 flex flex-col justify-between rounded-lg border p-4 transition-all hover:shadow-xs"
                                            >
                                                <div>
                                                    {/* Header de la tarjeta */}
                                                    <div className="flex items-start justify-between gap-2">
                                                        <div className="min-w-0 flex-1">
                                                            <h4
                                                                className="text-foreground truncate text-xs font-bold"
                                                                title={
                                                                    c.cliente
                                                                }
                                                            >
                                                                {c.cliente}
                                                            </h4>
                                                            <p className="text-muted-foreground mt-0.5 font-mono text-[10px]">
                                                                RUC/Doc:{' '}
                                                                {c.documento}
                                                            </p>
                                                        </div>

                                                        <div className="text-right">
                                                            <span
                                                                className={`inline-flex items-center rounded-full px-2 py-0.5 font-mono text-xs font-bold ${
                                                                    isAlta
                                                                        ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400'
                                                                        : isMedia
                                                                          ? 'bg-amber-500/15 text-amber-700 dark:text-amber-400'
                                                                          : 'bg-muted text-muted-foreground'
                                                                }`}
                                                            >
                                                                {c.probabilidad}
                                                                %
                                                            </span>
                                                        </div>
                                                    </div>

                                                    {/* Métricas del cliente */}
                                                    <div className="border-border/60 bg-muted/20 mt-3 grid grid-cols-3 gap-2 rounded border p-2 text-center">
                                                        <div>
                                                            <div className="text-muted-foreground text-[10px]">
                                                                Recencia
                                                            </div>
                                                            <div className="text-foreground font-mono text-xs font-bold">
                                                                {c.recenciaDias}
                                                                d
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <div className="text-muted-foreground text-[10px]">
                                                                Compras
                                                            </div>
                                                            <div className="text-foreground font-mono text-xs font-bold">
                                                                {c.frecuencia}
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <div className="text-muted-foreground text-[10px]">
                                                                Total S/
                                                            </div>
                                                            <div className="text-foreground font-mono text-xs font-bold">
                                                                {formatNumber(
                                                                    Math.round(
                                                                        c.montoTotal,
                                                                    ),
                                                                )}
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {/* Factores explicativos del modelo */}
                                                    <div className="mt-3 space-y-1.5">
                                                        <div className="text-muted-foreground text-[10px] font-semibold uppercase">
                                                            Factores
                                                            determinantes:
                                                        </div>
                                                        {c.factores.positivos &&
                                                        c.factores.positivos
                                                            .length > 0 ? (
                                                            c.factores.positivos.map(
                                                                (f, idx) => (
                                                                    <div
                                                                        key={
                                                                            idx
                                                                        }
                                                                        className="flex items-center gap-1.5 text-[11px] text-emerald-700 dark:text-emerald-400"
                                                                    >
                                                                        <CheckCircle2 className="size-3 shrink-0" />
                                                                        <span className="truncate">
                                                                            {
                                                                                f.factor
                                                                            }
                                                                            :{' '}
                                                                            {
                                                                                f.detalle
                                                                            }
                                                                        </span>
                                                                    </div>
                                                                ),
                                                            )
                                                        ) : (
                                                            <div className="text-muted-foreground text-[11px] italic">
                                                                Sin factores
                                                                dominantes
                                                            </div>
                                                        )}
                                                        {c.factores.negativos &&
                                                            c.factores.negativos
                                                                .slice(0, 1)
                                                                .map(
                                                                    (
                                                                        f,
                                                                        idx,
                                                                    ) => (
                                                                        <div
                                                                            key={
                                                                                idx
                                                                            }
                                                                            className="flex items-center gap-1.5 text-[11px] text-amber-700 dark:text-amber-400"
                                                                        >
                                                                            <AlertTriangle className="size-3 shrink-0" />
                                                                            <span className="truncate">
                                                                                {
                                                                                    f.factor
                                                                                }
                                                                                :{' '}
                                                                                {
                                                                                    f.detalle
                                                                                }
                                                                            </span>
                                                                        </div>
                                                                    ),
                                                                )}
                                                    </div>
                                                </div>

                                                {/* Acciones */}
                                                {c.telefono && (
                                                    <div className="border-border/60 mt-4 border-t pt-2.5">
                                                        <a
                                                            href={`https://wa.me/51${c.telefono.replace(/[^0-9]/g, '')}`}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            className="inline-flex w-full items-center justify-center gap-1.5 rounded-md border border-emerald-500/30 bg-emerald-500/10 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-500/20 dark:text-emerald-300"
                                                        >
                                                            <MessageCircle className="size-3.5" />
                                                            Contactar por
                                                            WhatsApp
                                                        </a>
                                                    </div>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>

                            {/* Aviso de Responsabilidad y Apoyo a la Decisión (§39.1 y §39.5) */}
                            <div className="border-border bg-muted/40 text-muted-foreground flex items-start gap-2.5 rounded-lg border p-3.5 text-xs">
                                <Info className="text-primary mt-0.5 size-4 shrink-0" />
                                <div>
                                    <span className="text-foreground font-semibold">
                                        Nota de apoyo a la decisión comercial:
                                    </span>{' '}
                                    Este modelo de regresión logística
                                    supervisado estima la probabilidad de
                                    recompra como apoyo analítico para priorizar
                                    contactos y campañas comerciales.{' '}
                                    <b>No constituye una verdad absoluta</b>. El
                                    mantenimiento y recarga de extintores sigue
                                    rigiéndose por la norma técnica de recarga
                                    cada 12 meses, la cual opera como regla fija
                                    del sistema.
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </GerenteLayout>
    );
}
