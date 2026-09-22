import {
    AlertTriangle,
    ArrowUpRight,
    Award,
    BadgeAlert,
    BarChart3,
    Boxes,
    Building2,
    Calendar,
    CheckCircle2,
    CircleDollarSign,
    Clock,
    FileCheck,
    FileText,
    Percent,
    ShieldAlert,
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

type GerenteDashboardProps = {
    metrics: DashboardMetrics;
    charts: DashboardCharts;
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

export default function GerenteDashboard({ metrics, charts }: GerenteDashboardProps) {
    const maxVentaMensual = Math.max(...charts.ventasMensuales.map((m) => m.monto), 1);
    const maxVentaItem = Math.max(...charts.ventasPorItem.map((item) => item.monto), 1);
    const maxMovimiento = Math.max(...charts.productosMayorMovimiento.map((p) => p.cantidad), 1);
    const totalServicios = charts.serviciosPorTipo.reduce((acc, curr) => acc + curr.cantidad, 0) || 1;

    return (
        <GerenteLayout title="Dashboard Gerencial">
            <div className="space-y-7">
                {/* Cabecera del Dashboard */}
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div>
                        <h1 className="font-['Oswald',sans-serif] text-2xl font-bold tracking-wide text-[#201F1D] uppercase">
                            Control Gerencial & Rendimiento
                        </h1>
                        <p className="mt-1 text-sm text-[#8A8680]">
                            Supervisión unificada de facturación, cobranzas, almacén y servicios técnicos.
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <div className="flex items-center gap-2 rounded-lg border border-[#E7E4DE] bg-white px-3.5 py-1.5 text-xs font-semibold text-[#4A4742]">
                            <Calendar className="size-3.5 text-[#E31E24]" />
                            <span>{new Date().toLocaleDateString('es-PE', { month: 'long', year: 'numeric' })}</span>
                        </div>
                    </div>
                </div>

                {/* Grid de 12 Tarjetas KPI */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    {/* 1. Ventas del Día */}
                    <div className="rounded-xl border border-[#E7E4DE] bg-white p-5 shadow-xs">
                        <div className="flex items-center justify-between text-xs text-[#8A8680]">
                            <span className="font-medium uppercase tracking-wider">Ventas de Hoy</span>
                            <div className="flex size-7 items-center justify-center rounded-lg bg-[#F8F9FA] text-[#201F1D]">
                                <CircleDollarSign className="size-4 text-emerald-600" />
                            </div>
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-[#201F1D]">
                            {formatCurrency(metrics.ventasDia)}
                        </div>
                        <p className="mt-1 text-[11px] text-[#8A8680]">Monto total vendido hoy</p>
                    </div>

                    {/* 2. Ventas del Mes */}
                    <div className="rounded-xl border border-[#E7E4DE] bg-white p-5 shadow-xs">
                        <div className="flex items-center justify-between text-xs text-[#8A8680]">
                            <span className="font-medium uppercase tracking-wider">Ventas del Mes</span>
                            <div className="flex size-7 items-center justify-center rounded-lg bg-[#F8F9FA] text-[#201F1D]">
                                <TrendingUp className="size-4 text-emerald-600" />
                            </div>
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-[#201F1D]">
                            {formatCurrency(metrics.ventasMes)}
                        </div>
                        <p className="mt-1 text-[11px] text-[#8A8680]">Total acumulado en el mes</p>
                    </div>

                    {/* 3. Facturación del Mes */}
                    <div className="rounded-xl border border-[#E7E4DE] bg-white p-5 shadow-xs">
                        <div className="flex items-center justify-between text-xs text-[#8A8680]">
                            <span className="font-medium uppercase tracking-wider">Facturación CPE Mes</span>
                            <div className="flex size-7 items-center justify-center rounded-lg bg-[#F8F9FA] text-[#201F1D]">
                                <FileCheck className="size-4 text-blue-600" />
                            </div>
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-[#201F1D]">
                            {formatCurrency(metrics.facturacionMes)}
                        </div>
                        <p className="mt-1 text-[11px] text-[#8A8680]">Facturas y boletas emitidas</p>
                    </div>

                    {/* 4. Monto Cobrado */}
                    <div className="rounded-xl border border-[#E7E4DE] bg-white p-5 shadow-xs">
                        <div className="flex items-center justify-between text-xs text-[#8A8680]">
                            <span className="font-medium uppercase tracking-wider">Monto Cobrado</span>
                            <div className="flex size-7 items-center justify-center rounded-lg bg-[#F8F9FA] text-[#201F1D]">
                                <CheckCircle2 className="size-4 text-emerald-600" />
                            </div>
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-[#201F1D]">
                            {formatCurrency(metrics.montoCobrado)}
                        </div>
                        <p className="mt-1 text-[11px] text-[#8A8680]">Abonos ingresados este mes</p>
                    </div>

                    {/* 5. Cuentas por Cobrar */}
                    <div className="rounded-xl border border-[#E7E4DE] bg-white p-5 shadow-xs">
                        <div className="flex items-center justify-between text-xs text-[#8A8680]">
                            <span className="font-medium uppercase tracking-wider">Por Cobrar Total</span>
                            <div className="flex size-7 items-center justify-center rounded-lg bg-[#F8F9FA] text-[#201F1D]">
                                <Clock className="size-4 text-amber-600" />
                            </div>
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-[#201F1D]">
                            {formatCurrency(metrics.cuentasPorCobrar)}
                        </div>
                        <p className="mt-1 text-[11px] text-[#8A8680]">Saldo pendiente en cartera</p>
                    </div>

                    {/* 6. Vencido por Cobrar */}
                    <div className="rounded-xl border border-[#E7E4DE] bg-white p-5 shadow-xs">
                        <div className="flex items-center justify-between text-xs text-[#8A8680]">
                            <span className="font-medium uppercase tracking-wider">Vencido por Cobrar</span>
                            <div className="flex size-7 items-center justify-center rounded-lg bg-[#F8F9FA] text-[#201F1D]">
                                <BadgeAlert className="size-4 text-[#E31E24]" />
                            </div>
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-[#E31E24]">
                            {formatCurrency(metrics.vencidoPorCobrar)}
                        </div>
                        <p className="mt-1 text-[11px] text-[#8A8680]">Cuotas con mora superada</p>
                    </div>

                    {/* 7. Cotizaciones Pendientes */}
                    <div className="rounded-xl border border-[#E7E4DE] bg-white p-5 shadow-xs">
                        <div className="flex items-center justify-between text-xs text-[#8A8680]">
                            <span className="font-medium uppercase tracking-wider">Cotizaciones Pendientes</span>
                            <div className="flex size-7 items-center justify-center rounded-lg bg-[#F8F9FA] text-[#201F1D]">
                                <FileText className="size-4 text-indigo-600" />
                            </div>
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-[#201F1D]">
                            {formatNumber(metrics.cotizacionesPendientes)}
                        </div>
                        <p className="mt-1 text-[11px] text-[#8A8680]">En negociación comercial</p>
                    </div>

                    {/* 8. Tasa de Conversión */}
                    <div className="rounded-xl border border-[#E7E4DE] bg-white p-5 shadow-xs">
                        <div className="flex items-center justify-between text-xs text-[#8A8680]">
                            <span className="font-medium uppercase tracking-wider">Tasa de Conversión</span>
                            <div className="flex size-7 items-center justify-center rounded-lg bg-[#F8F9FA] text-[#201F1D]">
                                <Percent className="size-4 text-purple-600" />
                            </div>
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-[#201F1D]">
                            {metrics.tasaConversion}%
                        </div>
                        <p className="mt-1 text-[11px] text-[#8A8680]">Cotizaciones ganadas vs total</p>
                    </div>

                    {/* 9. Órdenes en Proceso */}
                    <div className="rounded-xl border border-[#E7E4DE] bg-white p-5 shadow-xs">
                        <div className="flex items-center justify-between text-xs text-[#8A8680]">
                            <span className="font-medium uppercase tracking-wider">Órdenes en Proceso</span>
                            <div className="flex size-7 items-center justify-center rounded-lg bg-[#F8F9FA] text-[#201F1D]">
                                <Wrench className="size-4 text-cyan-600" />
                            </div>
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-[#201F1D]">
                            {formatNumber(metrics.ordenesEnProceso)}
                        </div>
                        <p className="mt-1 text-[11px] text-[#8A8680]">En planta o campo activos</p>
                    </div>

                    {/* 10. Equipos Próximos a Atención */}
                    <div className="rounded-xl border border-[#E7E4DE] bg-white p-5 shadow-xs">
                        <div className="flex items-center justify-between text-xs text-[#8A8680]">
                            <span className="font-medium uppercase tracking-wider">Próximos a Atención</span>
                            <div className="flex size-7 items-center justify-center rounded-lg bg-[#F8F9FA] text-[#201F1D]">
                                <AlertTriangle className="size-4 text-amber-500" />
                            </div>
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-[#201F1D]">
                            {formatNumber(metrics.equiposProximosAtencion)}
                        </div>
                        <p className="mt-1 text-[11px] text-[#8A8680]">Vencimiento en los próx. 30 días</p>
                    </div>

                    {/* 11. Stock Crítico */}
                    <div className="rounded-xl border border-[#E7E4DE] bg-white p-5 shadow-xs">
                        <div className="flex items-center justify-between text-xs text-[#8A8680]">
                            <span className="font-medium uppercase tracking-wider">Stock Crítico</span>
                            <div className="flex size-7 items-center justify-center rounded-lg bg-[#F8F9FA] text-[#201F1D]">
                                <Boxes className="size-4 text-orange-600" />
                            </div>
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-[#201F1D]">
                            {formatNumber(metrics.stockCritico)}
                        </div>
                        <p className="mt-1 text-[11px] text-[#8A8680]">Productos bajo el stock mínimo</p>
                    </div>

                    {/* 12. Documentos SUNAT con Error */}
                    <div className="rounded-xl border border-[#E7E4DE] bg-white p-5 shadow-xs">
                        <div className="flex items-center justify-between text-xs text-[#8A8680]">
                            <span className="font-medium uppercase tracking-wider">SUNAT con Error</span>
                            <div className="flex size-7 items-center justify-center rounded-lg bg-[#F8F9FA] text-[#201F1D]">
                                <ShieldAlert className="size-4 text-[#E31E24]" />
                            </div>
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-[#E31E24]">
                            {formatNumber(metrics.documentosSunatError)}
                        </div>
                        <p className="mt-1 text-[11px] text-[#8A8680]">Rechazos o excepciones SUNAT</p>
                    </div>
                </div>

                {/* Sección Gráfica y Analítica */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* Gráfico 1: Ventas Mensuales (Últimos 6 meses) */}
                    <div className="rounded-xl border border-[#E7E4DE] bg-white p-6 shadow-xs">
                        <div className="flex items-center justify-between border-b border-[#F0EEEA] pb-4">
                            <div>
                                <h2 className="font-['Oswald',sans-serif] text-base font-bold text-[#201F1D] uppercase">
                                    Ventas Mensuales
                                </h2>
                                <p className="text-xs text-[#8A8680]">Evolución de ventas en los últimos 6 meses</p>
                            </div>
                            <BarChart3 className="size-5 text-[#8A8680]" />
                        </div>

                        <div className="mt-6 flex h-48 items-end gap-3 pt-4">
                            {charts.ventasMensuales.map((item) => {
                                const heightPercent = Math.max(Math.round((item.monto / maxVentaMensual) * 100), 6);
                                return (
                                    <div key={item.mes} className="flex flex-1 flex-col items-center gap-2">
                                        <span className="font-mono text-[10px] text-[#8A8680]">
                                            {formatCurrency(item.monto)}
                                        </span>
                                        <div className="w-full rounded-t-md bg-[#201F1D] transition-all hover:bg-[#E31E24]" style={{ height: `${heightPercent}%` }} />
                                        <span className="font-mono text-[10px] font-semibold text-[#4A4742] uppercase">
                                            {item.mes}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    {/* Gráfico 2: Cartera por Estado */}
                    <div className="rounded-xl border border-[#E7E4DE] bg-white p-6 shadow-xs">
                        <div className="flex items-center justify-between border-b border-[#F0EEEA] pb-4">
                            <div>
                                <h2 className="font-['Oswald',sans-serif] text-base font-bold text-[#201F1D] uppercase">
                                    Estado de Cartera
                                </h2>
                                <p className="text-xs text-[#8A8680]">Créditos al día vs vencidos vs recaudación mensual</p>
                            </div>
                            <CircleDollarSign className="size-5 text-[#8A8680]" />
                        </div>

                        <div className="mt-6 space-y-4">
                            {charts.carteraPorEstado.map((cartera) => {
                                const totalCartera = charts.carteraPorEstado.reduce((acc, curr) => acc + curr.monto, 0) || 1;
                                const percent = Math.round((cartera.monto / totalCartera) * 100);
                                const isVencido = cartera.estado.toLowerCase().includes('vencido');
                                const isCobrado = cartera.estado.toLowerCase().includes('cobrado');

                                return (
                                    <div key={cartera.estado} className="space-y-1.5">
                                        <div className="flex justify-between text-xs">
                                            <span className="font-semibold text-[#201F1D]">{cartera.estado}</span>
                                            <span className="font-mono text-[#4A4742]">
                                                {formatCurrency(cartera.monto)} ({percent}%)
                                            </span>
                                        </div>
                                        <div className="h-3 w-full overflow-hidden rounded-full bg-[#F3F1ED]">
                                            <div
                                                className={`h-full rounded-full transition-all ${
                                                    isVencido ? 'bg-[#E31E24]' : isCobrado ? 'bg-emerald-600' : 'bg-[#201F1D]'
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
                    <div className="rounded-xl border border-[#E7E4DE] bg-white p-6 shadow-xs">
                        <div className="flex items-center justify-between border-b border-[#F0EEEA] pb-4">
                            <div>
                                <h2 className="font-['Oswald',sans-serif] text-base font-bold text-[#201F1D] uppercase">
                                    Top Productos / Servicios por Venta
                                </h2>
                                <p className="text-xs text-[#8A8680]">Ítems con mayor facturación en ventas</p>
                            </div>
                            <Award className="size-5 text-[#8A8680]" />
                        </div>

                        <div className="mt-4 divide-y divide-[#F3F1ED]">
                            {charts.ventasPorItem.length === 0 ? (
                                <p className="py-6 text-center text-xs text-[#8A8680]">Sin ventas registradas aún</p>
                            ) : (
                                charts.ventasPorItem.map((item, idx) => {
                                    const percent = Math.round((item.monto / maxVentaItem) * 100);
                                    return (
                                        <div key={item.nombre} className="py-3">
                                            <div className="flex items-center justify-between text-xs">
                                                <span className="truncate font-medium text-[#201F1D]">
                                                    <b className="mr-2 font-mono text-[#E31E24]">#{idx + 1}</b> {item.nombre}
                                                </span>
                                                <span className="font-mono font-semibold text-[#201F1D]">
                                                    {formatCurrency(item.monto)}
                                                </span>
                                            </div>
                                            <div className="mt-2 h-1.5 w-full rounded-full bg-[#F3F1ED]">
                                                <div className="h-full rounded-full bg-[#E31E24]" style={{ width: `${percent}%` }} />
                                            </div>
                                        </div>
                                    );
                                })
                            )}
                        </div>
                    </div>

                    {/* Gráfico 4: Servicios por Tipo */}
                    <div className="rounded-xl border border-[#E7E4DE] bg-white p-6 shadow-xs">
                        <div className="flex items-center justify-between border-b border-[#F0EEEA] pb-4">
                            <div>
                                <h2 className="font-['Oswald',sans-serif] text-base font-bold text-[#201F1D] uppercase">
                                    Distribución de Servicios Técnicos
                                </h2>
                                <p className="text-xs text-[#8A8680]">Órdenes según modalidad de atención técnica</p>
                            </div>
                            <Wrench className="size-5 text-[#8A8680]" />
                        </div>

                        <div className="mt-4 space-y-3">
                            {charts.serviciosPorTipo.length === 0 ? (
                                <p className="py-6 text-center text-xs text-[#8A8680]">Sin servicios registrados aún</p>
                            ) : (
                                charts.serviciosPorTipo.map((item) => {
                                    const percent = Math.round((item.cantidad / totalServicios) * 100);
                                    return (
                                        <div key={item.tipo} className="flex items-center justify-between rounded-lg border border-[#F3F1ED] p-3">
                                            <div className="flex items-center gap-3">
                                                <div className="flex size-7 items-center justify-center rounded-md bg-[#F8F9FA] text-xs font-bold text-[#201F1D]">
                                                    {percent}%
                                                </div>
                                                <span className="text-xs font-medium text-[#201F1D]">{item.tipo}</span>
                                            </div>
                                            <span className="font-mono text-xs font-bold text-[#4A4742]">
                                                {formatNumber(item.cantidad)} órdenes
                                            </span>
                                        </div>
                                    );
                                })
                            )}
                        </div>
                    </div>

                    {/* Gráfico 5: Top Clientes */}
                    <div className="rounded-xl border border-[#E7E4DE] bg-white p-6 shadow-xs">
                        <div className="flex items-center justify-between border-b border-[#F0EEEA] pb-4">
                            <div>
                                <h2 className="font-['Oswald',sans-serif] text-base font-bold text-[#201F1D] uppercase">
                                    Top 5 Clientes en Facturación
                                </h2>
                                <p className="text-xs text-[#8A8680]">Clientes con mayor volumen acumulado</p>
                            </div>
                            <Users className="size-5 text-[#8A8680]" />
                        </div>

                        <div className="mt-4 divide-y divide-[#F3F1ED]">
                            {charts.topClientes.length === 0 ? (
                                <p className="py-6 text-center text-xs text-[#8A8680]">Sin historial de clientes aún</p>
                            ) : (
                                charts.topClientes.map((c, idx) => (
                                    <div key={c.cliente} className="flex items-center justify-between py-3">
                                        <div className="flex items-center gap-3">
                                            <div className="flex size-6 items-center justify-center rounded-full bg-[#201F1D] font-mono text-[10px] font-bold text-white">
                                                {idx + 1}
                                            </div>
                                            <span className="text-xs font-medium text-[#201F1D]">{c.cliente}</span>
                                        </div>
                                        <span className="font-mono text-xs font-bold text-[#201F1D]">
                                            {formatCurrency(c.total)}
                                        </span>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>

                    {/* Gráfico 6: Productos con Mayor Movimiento */}
                    <div className="rounded-xl border border-[#E7E4DE] bg-white p-6 shadow-xs">
                        <div className="flex items-center justify-between border-b border-[#F0EEEA] pb-4">
                            <div>
                                <h2 className="font-['Oswald',sans-serif] text-base font-bold text-[#201F1D] uppercase">
                                    Productos / Repuestos con Mayor Rotación
                                </h2>
                                <p className="text-xs text-[#8A8680]">Mayor número de salidas registradas en Kardex</p>
                            </div>
                            <Boxes className="size-5 text-[#8A8680]" />
                        </div>

                        <div className="mt-4 divide-y divide-[#F3F1ED]">
                            {charts.productosMayorMovimiento.length === 0 ? (
                                <p className="py-6 text-center text-xs text-[#8A8680]">Sin movimientos de Kardex aún</p>
                            ) : (
                                charts.productosMayorMovimiento.map((p, idx) => {
                                    const percent = Math.round((p.cantidad / maxMovimiento) * 100);
                                    return (
                                        <div key={p.producto} className="py-3">
                                            <div className="flex items-center justify-between text-xs">
                                                <span className="font-medium text-[#201F1D]">{p.producto}</span>
                                                <span className="font-mono font-semibold text-[#4A4742]">
                                                    {formatNumber(p.cantidad)} unidades
                                                </span>
                                            </div>
                                            <div className="mt-2 h-1.5 w-full rounded-full bg-[#F3F1ED]">
                                                <div className="h-full rounded-full bg-[#201F1D]" style={{ width: `${percent}%` }} />
                                            </div>
                                        </div>
                                    );
                                })
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </GerenteLayout>
    );
}
