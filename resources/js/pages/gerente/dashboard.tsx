import {
    AlertTriangle,
    ArrowUpRight,
    Award,
    BadgeAlert,
    BarChart3,
    Boxes,
    BrainCircuit,
    Building2,
    Calendar,
    CheckCircle2,
    CircleDollarSign,
    Clock,
    FileCheck,
    FileText,
    HelpCircle,
    Info,
    MessageCircle,
    Percent,
    ShieldAlert,
    Sparkles,
    TrendingDown,
    TrendingUp,
    Users,
    Wrench,
} from 'lucide-react';

import GerenteLayout from '@/layouts/gerente-layout';

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
    };
} | null;

type GerenteDashboardProps = {
    metrics: DashboardMetrics;
    charts: DashboardCharts;
    aiRetention?: AiRetentionData;
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
                        <h1 className="font-['Oswald',sans-serif] text-2xl font-bold tracking-wide text-foreground uppercase">
                            Control Gerencial & Rendimiento
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Supervisión unificada de facturación, cobranzas,
                            almacén y servicios técnicos.
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <div className="flex items-center gap-2 rounded-lg border border-border bg-card px-3.5 py-1.5 text-xs font-semibold text-foreground/80">
                            <Calendar className="size-3.5 text-primary" />
                            <span>
                                {new Date().toLocaleDateString('es-PE', {
                                    month: 'long',
                                    year: 'numeric',
                                })}
                            </span>
                        </div>
                    </div>
                </div>

                {/* Grid de 12 Tarjetas KPI */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    {/* 1. Ventas del Día */}
                    <div className="rounded-xl border border-border bg-card p-5 shadow-xs">
                        <div className="flex items-center justify-between text-xs text-muted-foreground">
                            <span className="font-medium tracking-wider uppercase">
                                Ventas de Hoy
                            </span>
                            <div className="flex size-7 items-center justify-center rounded-lg bg-muted/40 text-foreground">
                                <CircleDollarSign className="size-4 text-emerald-600" />
                            </div>
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-foreground">
                            {formatCurrency(metrics.ventasDia)}
                        </div>
                        <p className="mt-1 text-[11px] text-muted-foreground">
                            Monto total vendido hoy
                        </p>
                    </div>

                    {/* 2. Ventas del Mes */}
                    <div className="rounded-xl border border-border bg-card p-5 shadow-xs">
                        <div className="flex items-center justify-between text-xs text-muted-foreground">
                            <span className="font-medium tracking-wider uppercase">
                                Ventas del Mes
                            </span>
                            <div className="flex size-7 items-center justify-center rounded-lg bg-muted/40 text-foreground">
                                <TrendingUp className="size-4 text-emerald-600" />
                            </div>
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-foreground">
                            {formatCurrency(metrics.ventasMes)}
                        </div>
                        <p className="mt-1 text-[11px] text-muted-foreground">
                            Total acumulado en el mes
                        </p>
                    </div>

                    {/* 3. Facturación del Mes */}
                    <div className="rounded-xl border border-border bg-card p-5 shadow-xs">
                        <div className="flex items-center justify-between text-xs text-muted-foreground">
                            <span className="font-medium tracking-wider uppercase">
                                Facturación CPE Mes
                            </span>
                            <div className="flex size-7 items-center justify-center rounded-lg bg-muted/40 text-foreground">
                                <FileCheck className="size-4 text-blue-600" />
                            </div>
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-foreground">
                            {formatCurrency(metrics.facturacionMes)}
                        </div>
                        <p className="mt-1 text-[11px] text-muted-foreground">
                            Facturas y boletas emitidas
                        </p>
                    </div>

                    {/* 4. Monto Cobrado */}
                    <div className="rounded-xl border border-border bg-card p-5 shadow-xs">
                        <div className="flex items-center justify-between text-xs text-muted-foreground">
                            <span className="font-medium tracking-wider uppercase">
                                Monto Cobrado
                            </span>
                            <div className="flex size-7 items-center justify-center rounded-lg bg-muted/40 text-foreground">
                                <CheckCircle2 className="size-4 text-emerald-600" />
                            </div>
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-foreground">
                            {formatCurrency(metrics.montoCobrado)}
                        </div>
                        <p className="mt-1 text-[11px] text-muted-foreground">
                            Abonos ingresados este mes
                        </p>
                    </div>

                    {/* 5. Cuentas por Cobrar */}
                    <div className="rounded-xl border border-border bg-card p-5 shadow-xs">
                        <div className="flex items-center justify-between text-xs text-muted-foreground">
                            <span className="font-medium tracking-wider uppercase">
                                Por Cobrar Total
                            </span>
                            <div className="flex size-7 items-center justify-center rounded-lg bg-muted/40 text-foreground">
                                <Clock className="size-4 text-amber-600" />
                            </div>
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-foreground">
                            {formatCurrency(metrics.cuentasPorCobrar)}
                        </div>
                        <p className="mt-1 text-[11px] text-muted-foreground">
                            Saldo pendiente en cartera
                        </p>
                    </div>

                    {/* 6. Vencido por Cobrar */}
                    <div className="rounded-xl border border-border bg-card p-5 shadow-xs">
                        <div className="flex items-center justify-between text-xs text-muted-foreground">
                            <span className="font-medium tracking-wider uppercase">
                                Vencido por Cobrar
                            </span>
                            <div className="flex size-7 items-center justify-center rounded-lg bg-muted/40 text-foreground">
                                <BadgeAlert className="size-4 text-primary" />
                            </div>
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-primary">
                            {formatCurrency(metrics.vencidoPorCobrar)}
                        </div>
                        <p className="mt-1 text-[11px] text-muted-foreground">
                            Cuotas con mora superada
                        </p>
                    </div>

                    {/* 7. Cotizaciones Pendientes */}
                    <div className="rounded-xl border border-border bg-card p-5 shadow-xs">
                        <div className="flex items-center justify-between text-xs text-muted-foreground">
                            <span className="font-medium tracking-wider uppercase">
                                Cotizaciones Pendientes
                            </span>
                            <div className="flex size-7 items-center justify-center rounded-lg bg-muted/40 text-foreground">
                                <FileText className="size-4 text-indigo-600" />
                            </div>
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-foreground">
                            {formatNumber(metrics.cotizacionesPendientes)}
                        </div>
                        <p className="mt-1 text-[11px] text-muted-foreground">
                            En negociación comercial
                        </p>
                    </div>

                    {/* 8. Tasa de Conversión */}
                    <div className="rounded-xl border border-border bg-card p-5 shadow-xs">
                        <div className="flex items-center justify-between text-xs text-muted-foreground">
                            <span className="font-medium tracking-wider uppercase">
                                Tasa de Conversión
                            </span>
                            <div className="flex size-7 items-center justify-center rounded-lg bg-muted/40 text-foreground">
                                <Percent className="size-4 text-purple-600" />
                            </div>
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-foreground">
                            {metrics.tasaConversion}%
                        </div>
                        <p className="mt-1 text-[11px] text-muted-foreground">
                            Cotizaciones ganadas vs total
                        </p>
                    </div>

                    {/* 9. Órdenes en Proceso */}
                    <div className="rounded-xl border border-border bg-card p-5 shadow-xs">
                        <div className="flex items-center justify-between text-xs text-muted-foreground">
                            <span className="font-medium tracking-wider uppercase">
                                Órdenes en Proceso
                            </span>
                            <div className="flex size-7 items-center justify-center rounded-lg bg-muted/40 text-foreground">
                                <Wrench className="size-4 text-cyan-600" />
                            </div>
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-foreground">
                            {formatNumber(metrics.ordenesEnProceso)}
                        </div>
                        <p className="mt-1 text-[11px] text-muted-foreground">
                            En planta o campo activos
                        </p>
                    </div>

                    {/* 10. Equipos Próximos a Atención */}
                    <div className="rounded-xl border border-border bg-card p-5 shadow-xs">
                        <div className="flex items-center justify-between text-xs text-muted-foreground">
                            <span className="font-medium tracking-wider uppercase">
                                Próximos a Atención
                            </span>
                            <div className="flex size-7 items-center justify-center rounded-lg bg-muted/40 text-foreground">
                                <AlertTriangle className="size-4 text-amber-500" />
                            </div>
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-foreground">
                            {formatNumber(metrics.equiposProximosAtencion)}
                        </div>
                        <p className="mt-1 text-[11px] text-muted-foreground">
                            Vencimiento en los próx. 30 días
                        </p>
                    </div>

                    {/* 11. Stock Crítico */}
                    <div className="rounded-xl border border-border bg-card p-5 shadow-xs">
                        <div className="flex items-center justify-between text-xs text-muted-foreground">
                            <span className="font-medium tracking-wider uppercase">
                                Stock Crítico
                            </span>
                            <div className="flex size-7 items-center justify-center rounded-lg bg-muted/40 text-foreground">
                                <Boxes className="size-4 text-orange-600" />
                            </div>
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-foreground">
                            {formatNumber(metrics.stockCritico)}
                        </div>
                        <p className="mt-1 text-[11px] text-muted-foreground">
                            Productos bajo el stock mínimo
                        </p>
                    </div>

                    {/* 12. Documentos SUNAT con Error */}
                    <div className="rounded-xl border border-border bg-card p-5 shadow-xs">
                        <div className="flex items-center justify-between text-xs text-muted-foreground">
                            <span className="font-medium tracking-wider uppercase">
                                SUNAT con Error
                            </span>
                            <div className="flex size-7 items-center justify-center rounded-lg bg-muted/40 text-foreground">
                                <ShieldAlert className="size-4 text-primary" />
                            </div>
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-primary">
                            {formatNumber(metrics.documentosSunatError)}
                        </div>
                        <p className="mt-1 text-[11px] text-muted-foreground">
                            Rechazos o excepciones SUNAT
                        </p>
                    </div>
                </div>

                {/* Sección Gráfica y Analítica */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* Gráfico 1: Ventas Mensuales (Últimos 6 meses) */}
                    <div className="rounded-xl border border-border bg-card p-6 shadow-xs">
                        <div className="flex items-center justify-between border-b border-border pb-4">
                            <div>
                                <h2 className="font-['Oswald',sans-serif] text-base font-bold text-foreground uppercase">
                                    Ventas Mensuales
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    Evolución de ventas en los últimos 6 meses
                                </p>
                            </div>
                            <BarChart3 className="size-5 text-muted-foreground" />
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
                                        <span className="font-mono text-[10px] text-muted-foreground">
                                            {formatCurrency(item.monto)}
                                        </span>
                                        <div
                                            className="w-full rounded-t-md bg-card transition-all hover:bg-primary"
                                            style={{
                                                height: `${heightPercent}%`,
                                            }}
                                        />
                                        <span className="font-mono text-[10px] font-semibold text-foreground/80 uppercase">
                                            {item.mes}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    {/* Gráfico 2: Cartera por Estado */}
                    <div className="rounded-xl border border-border bg-card p-6 shadow-xs">
                        <div className="flex items-center justify-between border-b border-border pb-4">
                            <div>
                                <h2 className="font-['Oswald',sans-serif] text-base font-bold text-foreground uppercase">
                                    Estado de Cartera
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    Créditos al día vs vencidos vs recaudación
                                    mensual
                                </p>
                            </div>
                            <CircleDollarSign className="size-5 text-muted-foreground" />
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
                                            <span className="font-semibold text-foreground">
                                                {cartera.estado}
                                            </span>
                                            <span className="font-mono text-foreground/80">
                                                {formatCurrency(cartera.monto)}{' '}
                                                ({percent}%)
                                            </span>
                                        </div>
                                        <div className="h-3 w-full overflow-hidden rounded-full bg-background">
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
                    <div className="rounded-xl border border-border bg-card p-6 shadow-xs">
                        <div className="flex items-center justify-between border-b border-border pb-4">
                            <div>
                                <h2 className="font-['Oswald',sans-serif] text-base font-bold text-foreground uppercase">
                                    Top Productos / Servicios por Venta
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    Ítems con mayor facturación en ventas
                                </p>
                            </div>
                            <Award className="size-5 text-muted-foreground" />
                        </div>

                        <div className="mt-4 divide-y divide-border">
                            {charts.ventasPorItem.length === 0 ? (
                                <p className="py-6 text-center text-xs text-muted-foreground">
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
                                                <span className="truncate font-medium text-foreground">
                                                    <b className="mr-2 font-mono text-primary">
                                                        #{idx + 1}
                                                    </b>{' '}
                                                    {item.nombre}
                                                </span>
                                                <span className="font-mono font-semibold text-foreground">
                                                    {formatCurrency(item.monto)}
                                                </span>
                                            </div>
                                            <div className="mt-2 h-1.5 w-full rounded-full bg-background">
                                                <div
                                                    className="h-full rounded-full bg-primary"
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
                    <div className="rounded-xl border border-border bg-card p-6 shadow-xs">
                        <div className="flex items-center justify-between border-b border-border pb-4">
                            <div>
                                <h2 className="font-['Oswald',sans-serif] text-base font-bold text-foreground uppercase">
                                    Distribución de Servicios Técnicos
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    Órdenes según modalidad de atención técnica
                                </p>
                            </div>
                            <Wrench className="size-5 text-muted-foreground" />
                        </div>

                        <div className="mt-4 space-y-3">
                            {charts.serviciosPorTipo.length === 0 ? (
                                <p className="py-6 text-center text-xs text-muted-foreground">
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
                                            className="flex items-center justify-between rounded-lg border border-border p-3"
                                        >
                                            <div className="flex items-center gap-3">
                                                <div className="flex size-7 items-center justify-center rounded-md bg-muted/40 text-xs font-bold text-foreground">
                                                    {percent}%
                                                </div>
                                                <span className="text-xs font-medium text-foreground">
                                                    {item.tipo}
                                                </span>
                                            </div>
                                            <span className="font-mono text-xs font-bold text-foreground/80">
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
                    <div className="rounded-xl border border-border bg-card p-6 shadow-xs">
                        <div className="flex items-center justify-between border-b border-border pb-4">
                            <div>
                                <h2 className="font-['Oswald',sans-serif] text-base font-bold text-foreground uppercase">
                                    Top 5 Clientes en Facturación
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    Clientes con mayor volumen acumulado
                                </p>
                            </div>
                            <Users className="size-5 text-muted-foreground" />
                        </div>

                        <div className="mt-4 divide-y divide-border">
                            {charts.topClientes.length === 0 ? (
                                <p className="py-6 text-center text-xs text-muted-foreground">
                                    Sin historial de clientes aún
                                </p>
                            ) : (
                                charts.topClientes.map((c, idx) => (
                                    <div
                                        key={c.cliente}
                                        className="flex items-center justify-between py-3"
                                    >
                                        <div className="flex items-center gap-3">
                                            <div className="flex size-6 items-center justify-center rounded-full bg-card font-mono text-[10px] font-bold text-white">
                                                {idx + 1}
                                            </div>
                                            <span className="text-xs font-medium text-foreground">
                                                {c.cliente}
                                            </span>
                                        </div>
                                        <span className="font-mono text-xs font-bold text-foreground">
                                            {formatCurrency(c.total)}
                                        </span>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>

                    {/* Gráfico 6: Productos con Mayor Movimiento */}
                    <div className="rounded-xl border border-border bg-card p-6 shadow-xs">
                        <div className="flex items-center justify-between border-b border-border pb-4">
                            <div>
                                <h2 className="font-['Oswald',sans-serif] text-base font-bold text-foreground uppercase">
                                    Productos / Repuestos con Mayor Rotación
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    Mayor número de salidas registradas en
                                    Kardex
                                </p>
                            </div>
                            <Boxes className="size-5 text-muted-foreground" />
                        </div>

                        <div className="mt-4 divide-y divide-border">
                            {charts.productosMayorMovimiento.length === 0 ? (
                                <p className="py-6 text-center text-xs text-muted-foreground">
                                    Sin movimientos de Kardex aún
                                </p>
                            ) : (
                                charts.productosMayorMovimiento.map(
                                    (p, idx) => {
                                        const percent = Math.round(
                                            (p.cantidad / maxMovimiento) * 100,
                                        );
                                        return (
                                            <div
                                                key={p.producto}
                                                className="py-3"
                                            >
                                                <div className="flex items-center justify-between text-xs">
                                                    <span className="font-medium text-foreground">
                                                        {p.producto}
                                                    </span>
                                                    <span className="font-mono font-semibold text-foreground/80">
                                                        {formatNumber(
                                                            p.cantidad,
                                                        )}{' '}
                                                        unidades
                                                    </span>
                                                </div>
                                                <div className="mt-2 h-1.5 w-full rounded-full bg-background">
                                                    <div
                                                        className="h-full rounded-full bg-card"
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
                <div className="rounded-xl border border-border bg-card p-6 shadow-xs">
                    {/* Cabecera del Módulo IA */}
                    <div className="flex flex-col justify-between gap-4 border-b border-border pb-5 lg:flex-row lg:items-center">
                        <div className="space-y-1">
                            <div className="flex items-center gap-2.5">
                                <div className="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                    <BrainCircuit className="size-5 text-primary" />
                                </div>
                                <h2 className="font-['Oswald',sans-serif] text-lg font-bold tracking-wide text-foreground uppercase">
                                    Predicción de Recompra — Modelo IA (§39.1)
                                </h2>
                                <span className="inline-flex items-center gap-1 rounded-full border border-primary/20 bg-primary/5 px-2.5 py-0.5 text-[11px] font-semibold text-primary">
                                    <Sparkles className="size-3" />
                                    ML Local
                                </span>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Estimación probabilística de recompra en los próximos 6 meses mediante regresión logística entrenada con el histórico real de ventas pre-corte.
                            </p>
                        </div>

                        {aiRetention && (
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="rounded-md border border-border bg-muted/40 px-2.5 py-1 font-mono text-[11px] font-medium text-foreground">
                                    ROC-AUC: <b className="text-emerald-600 font-bold">{Math.round(aiRetention.modelo.aucRoc * 1000) / 10}%</b>
                                </span>
                                <span className="rounded-md border border-border bg-muted/40 px-2.5 py-1 font-mono text-[11px] font-medium text-foreground">
                                    Accuracy: <b className="text-foreground">{Math.round(aiRetention.modelo.accuracy * 1000) / 10}%</b>
                                </span>
                                <span className="rounded-md border border-border bg-muted/40 px-2.5 py-1 font-mono text-[11px] font-medium text-foreground">
                                    Precision: <b className="text-foreground">{Math.round(aiRetention.modelo.precision * 1000) / 10}%</b>
                                </span>
                                <span className="rounded-md border border-border bg-muted/40 px-2.5 py-1 font-mono text-[11px] text-muted-foreground">
                                    {formatNumber(aiRetention.totalEvaluados)} evaluados
                                </span>
                            </div>
                        )}
                    </div>

                    {!aiRetention || aiRetention.topClientes.length === 0 ? (
                        /* Estado Vacío Elegante */
                        <div className="flex flex-col items-center justify-center py-12 text-center">
                            <div className="flex size-12 items-center justify-center rounded-full bg-muted/50 text-muted-foreground">
                                <BrainCircuit className="size-6 text-muted-foreground" />
                            </div>
                            <h3 className="mt-3 text-sm font-semibold text-foreground">
                                Modelo de IA no ejecutado aún
                            </h3>
                            <p className="mt-1 max-w-md text-xs text-muted-foreground">
                                El modelo predictivo de retención está listo para procesar la cartera. Ejecuta el comando de scoring para generar las probabilidades de los clientes.
                            </p>
                            <code className="mt-3 rounded border border-border bg-muted px-3 py-1 font-mono text-[11px] text-foreground">
                                php artisan ml:score-clients
                            </code>
                        </div>
                    ) : (
                        <div className="mt-6 space-y-6">
                            {/* Distribución de la Cartera */}
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                {/* Alta */}
                                <div className="rounded-lg border border-emerald-500/20 bg-emerald-500/5 p-4">
                                    <div className="flex items-center justify-between">
                                        <span className="text-xs font-semibold text-emerald-700 dark:text-emerald-400 uppercase tracking-wider">
                                            Alta Oportunidad (P ≥ 60%)
                                        </span>
                                        <span className="rounded bg-emerald-500/20 px-1.5 py-0.5 font-mono text-[11px] font-bold text-emerald-700 dark:text-emerald-300">
                                            {aiRetention.distribucion.alta.porcentaje}%
                                        </span>
                                    </div>
                                    <div className="mt-2 font-mono text-2xl font-bold text-emerald-700 dark:text-emerald-300">
                                        {formatNumber(aiRetention.distribucion.alta.cantidad)} <span className="text-xs font-normal text-muted-foreground">empresas</span>
                                    </div>
                                    <p className="mt-1 text-[11px] text-muted-foreground">
                                        Clientes con alta recurrencia y recencia óptima
                                    </p>
                                </div>

                                {/* Media */}
                                <div className="rounded-lg border border-amber-500/20 bg-amber-500/5 p-4">
                                    <div className="flex items-center justify-between">
                                        <span className="text-xs font-semibold text-amber-700 dark:text-amber-400 uppercase tracking-wider">
                                            Media Probabilidad (35% ≤ P &lt; 60%)
                                        </span>
                                        <span className="rounded bg-amber-500/20 px-1.5 py-0.5 font-mono text-[11px] font-bold text-amber-700 dark:text-amber-300">
                                            {aiRetention.distribucion.media.porcentaje}%
                                        </span>
                                    </div>
                                    <div className="mt-2 font-mono text-2xl font-bold text-amber-700 dark:text-amber-300">
                                        {formatNumber(aiRetention.distribucion.media.cantidad)} <span className="text-xs font-normal text-muted-foreground">empresas</span>
                                    </div>
                                    <p className="mt-1 text-[11px] text-muted-foreground">
                                        Requieren seguimiento comercial activo
                                    </p>
                                </div>

                                {/* Baja */}
                                <div className="rounded-lg border border-border bg-muted/20 p-4">
                                    <div className="flex items-center justify-between">
                                        <span className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                            Baja Probabilidad (P &lt; 35%)
                                        </span>
                                        <span className="rounded bg-muted px-1.5 py-0.5 font-mono text-[11px] font-bold text-muted-foreground">
                                            {aiRetention.distribucion.baja.porcentaje}%
                                        </span>
                                    </div>
                                    <div className="mt-2 font-mono text-2xl font-bold text-foreground">
                                        {formatNumber(aiRetention.distribucion.baja.cantidad)} <span className="text-xs font-normal text-muted-foreground">empresas</span>
                                    </div>
                                    <p className="mt-1 text-[11px] text-muted-foreground">
                                        Inactividad prolongada o compras esporádicas
                                    </p>
                                </div>
                            </div>

                            {/* Top Clientes con Mayor Probabilidad de Recompra */}
                            <div className="space-y-3">
                                <div className="flex items-center justify-between">
                                    <h3 className="font-['Oswald',sans-serif] text-sm font-bold text-foreground uppercase tracking-wide">
                                        Top Clientes Prioritarios para Gestión Comercial
                                    </h3>
                                    <span className="text-[11px] text-muted-foreground">
                                        Ordenados por propensión matemática de recompra
                                    </span>
                                </div>

                                <div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                                    {aiRetention.topClientes.map((c) => {
                                        const isAlta = c.categoria === 'alta';
                                        const isMedia = c.categoria === 'media';

                                        return (
                                            <div
                                                key={c.clientId}
                                                className="flex flex-col justify-between rounded-lg border border-border bg-card p-4 transition-all hover:border-primary/40 hover:shadow-xs"
                                            >
                                                <div>
                                                    {/* Header de la tarjeta */}
                                                    <div className="flex items-start justify-between gap-2">
                                                        <div className="min-w-0 flex-1">
                                                            <h4 className="truncate text-xs font-bold text-foreground" title={c.cliente}>
                                                                {c.cliente}
                                                            </h4>
                                                            <p className="mt-0.5 font-mono text-[10px] text-muted-foreground">
                                                                RUC/Doc: {c.documento}
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
                                                                {c.probabilidad}%
                                                            </span>
                                                        </div>
                                                    </div>

                                                    {/* Métricas del cliente */}
                                                    <div className="mt-3 grid grid-cols-3 gap-2 rounded border border-border/60 bg-muted/20 p-2 text-center">
                                                        <div>
                                                            <div className="text-[10px] text-muted-foreground">Recencia</div>
                                                            <div className="font-mono text-xs font-bold text-foreground">
                                                                {c.recenciaDias}d
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <div className="text-[10px] text-muted-foreground">Compras</div>
                                                            <div className="font-mono text-xs font-bold text-foreground">
                                                                {c.frecuencia}
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <div className="text-[10px] text-muted-foreground">Total S/</div>
                                                            <div className="font-mono text-xs font-bold text-foreground">
                                                                {formatNumber(Math.round(c.montoTotal))}
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {/* Factores explicativos del modelo */}
                                                    <div className="mt-3 space-y-1.5">
                                                        <div className="text-[10px] font-semibold text-muted-foreground uppercase">
                                                            Factores determinantes:
                                                        </div>
                                                        {c.factores.positivos && c.factores.positivos.length > 0 ? (
                                                            c.factores.positivos.map((f, idx) => (
                                                                <div
                                                                    key={idx}
                                                                    className="flex items-center gap-1.5 text-[11px] text-emerald-700 dark:text-emerald-400"
                                                                >
                                                                    <CheckCircle2 className="size-3 shrink-0" />
                                                                    <span className="truncate">{f.factor}: {f.detalle}</span>
                                                                </div>
                                                            ))
                                                        ) : (
                                                            <div className="text-[11px] text-muted-foreground italic">
                                                                Sin factores dominantes
                                                            </div>
                                                        )}
                                                        {c.factores.negativos && c.factores.negativos.slice(0, 1).map((f, idx) => (
                                                            <div
                                                                key={idx}
                                                                className="flex items-center gap-1.5 text-[11px] text-amber-700 dark:text-amber-400"
                                                            >
                                                                <AlertTriangle className="size-3 shrink-0" />
                                                                <span className="truncate">{f.factor}: {f.detalle}</span>
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>

                                                {/* Acciones */}
                                                {c.telefono && (
                                                    <div className="mt-4 border-t border-border/60 pt-2.5">
                                                        <a
                                                            href={`https://wa.me/51${c.telefono.replace(/[^0-9]/g, '')}`}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            className="inline-flex w-full items-center justify-center gap-1.5 rounded-md border border-emerald-500/30 bg-emerald-500/10 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-500/20 dark:text-emerald-300"
                                                        >
                                                            <MessageCircle className="size-3.5" />
                                                            Contactar por WhatsApp
                                                        </a>
                                                    </div>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>

                            {/* Aviso de Responsabilidad y Apoyo a la Decisión (§39.1 y §39.5) */}
                            <div className="flex items-start gap-2.5 rounded-lg border border-border bg-muted/40 p-3.5 text-xs text-muted-foreground">
                                <Info className="size-4 shrink-0 text-primary mt-0.5" />
                                <div>
                                    <span className="font-semibold text-foreground">
                                        Nota de apoyo a la decisión comercial (§39.1):
                                    </span>{' '}
                                    Este modelo de regresión logística supervisado estima la probabilidad de recompra como apoyo analítico para priorizar contactos y campañas comerciales. <b>No constituye una verdad absoluta</b>. El mantenimiento y recarga de extintores sigue rigiéndose por la norma técnica de recarga cada 12 meses, la cual opera como regla fija del sistema (§39.5).
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </GerenteLayout>
    );
}
