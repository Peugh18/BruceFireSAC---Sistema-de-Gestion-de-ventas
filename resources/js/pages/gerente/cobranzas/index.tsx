import { router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    BadgeAlert,
    Calendar,
    CheckCircle2,
    CircleDollarSign,
    Clock,
    CreditCard,
    Eye,
    Filter,
    HelpCircle,
    Phone,
    Search,
    TrendingUp,
    Users,
    X,
} from 'lucide-react';
import { useState } from 'react';

import GerenteLayout from '@/layouts/gerente-layout';

type PaymentItem = {
    id: number;
    forma_pago: string;
    monto: number;
    numero_operacion: string | null;
    fecha: string;
};

type InstallmentItem = {
    id: number;
    sale_id: number;
    sale_numero: string;
    numero_cuota: number;
    monto: number;
    monto_pagado: number;
    saldo_pendiente: number;
    fecha_vencimiento: string;
    estado: string;
    dias_vencido: number;
    cliente: {
        id?: number;
        razon_social: string;
        numero_documento?: string;
        telefono?: string;
    };
    vendedor: {
        id?: number;
        name: string;
    };
    pagos: PaymentItem[];
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedInstallments = {
    data: InstallmentItem[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    total: number;
};

type CollectionFilters = {
    buscar: string;
    vendedor_id: string | null;
    estado: string;
    periodo: string;
};

type CollectionKpis = {
    totalPorCobrar: number;
    vencidoTotal: number;
    venceEstaSemana: number;
    cobradoEsteMes: number;
    clientesConDeuda: number;
};

type PageProps = {
    currentTeam: { slug: string };
    cuotas: PaginatedInstallments;
    filters: CollectionFilters;
    kpis: CollectionKpis;
    vendedores: Array<{ id: number; name: string }>;
    [key: string]: unknown;
};

function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('es-PE', {
        style: 'currency',
        currency: 'PEN',
        minimumFractionDigits: 2,
    }).format(amount);
}

export default function CobranzasConsolidadasIndex() {
    const { currentTeam, cuotas, filters, kpis, vendedores } =
        usePage<PageProps>().props;

    const [buscar, setBuscar] = useState(filters.buscar || '');
    const [vendedorId, setVendedorId] = useState(filters.vendedor_id || '');
    const [estado, setEstado] = useState(filters.estado || 'todos');
    const [periodo, setPeriodo] = useState(filters.periodo || 'todos');
    const [viewingPayments, setViewingPayments] =
        useState<InstallmentItem | null>(null);

    const applyFilters = () => {
        router.get(
            `/${currentTeam.slug}/gerente/cobranzas`,
            {
                buscar: buscar || undefined,
                vendedor_id: vendedorId || undefined,
                estado,
                periodo,
            },
            { preserveState: true },
        );
    };

    const resetFilters = () => {
        setBuscar('');
        setVendedorId('');
        setEstado('todos');
        setPeriodo('todos');
        router.get(
            `/${currentTeam.slug}/gerente/cobranzas`,
            {},
            { preserveState: true },
        );
    };

    return (
        <GerenteLayout title="Cobranzas Consolidadas">
            <div className="space-y-6">
                {/* Cabecera */}
                <div>
                    <h1 className="font-['Oswald',sans-serif] text-2xl font-bold tracking-wide text-foreground uppercase">
                        Cartera Consolidada de Cobranzas
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Seguimiento centralizado de cuentas por cobrar,
                        vencimientos de cuotas y abonos recibidos.
                    </p>
                </div>

                {/* KPI Cards */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between text-xs text-muted-foreground">
                            <span className="font-medium uppercase">
                                Por Cobrar Total
                            </span>
                            <Clock className="size-4 text-blue-600" />
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-foreground">
                            {formatCurrency(kpis.totalPorCobrar)}
                        </div>
                        <p className="mt-0.5 text-[11px] text-muted-foreground">
                            Saldo pendiente en cartera
                        </p>
                    </div>

                    <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between text-xs text-muted-foreground">
                            <span className="font-medium uppercase">
                                Vencido
                            </span>
                            <BadgeAlert className="size-4 text-primary" />
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-primary">
                            {formatCurrency(kpis.vencidoTotal)}
                        </div>
                        <p className="mt-0.5 text-[11px] text-muted-foreground">
                            Cuotas con fecha pasada
                        </p>
                    </div>

                    <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between text-xs text-muted-foreground">
                            <span className="font-medium uppercase">
                                Vence Esta Semana
                            </span>
                            <Calendar className="size-4 text-amber-600" />
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-amber-700">
                            {formatCurrency(kpis.venceEstaSemana)}
                        </div>
                        <p className="mt-0.5 text-[11px] text-muted-foreground">
                            Vencimientos hasta domingo
                        </p>
                    </div>

                    <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between text-xs text-muted-foreground">
                            <span className="font-medium uppercase">
                                Cobrado en el Mes
                            </span>
                            <TrendingUp className="size-4 text-emerald-600" />
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-emerald-700">
                            {formatCurrency(kpis.cobradoEsteMes)}
                        </div>
                        <p className="mt-0.5 text-[11px] text-muted-foreground">
                            Abonos ingresados en el mes
                        </p>
                    </div>

                    <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between text-xs text-muted-foreground">
                            <span className="font-medium uppercase">
                                Clientes con Deuda
                            </span>
                            <Users className="size-4 text-purple-600" />
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-foreground">
                            {kpis.clientesConDeuda}
                        </div>
                        <p className="mt-0.5 text-[11px] text-muted-foreground">
                            Empresas en cartera activa
                        </p>
                    </div>
                </div>

                {/* Filtros */}
                <div className="space-y-3 rounded-xl border border-border bg-card p-4 shadow-xs">
                    <div className="grid grid-cols-1 gap-3 text-xs sm:grid-cols-2 lg:grid-cols-4">
                        <div className="relative">
                            <label className="block font-semibold text-foreground/80">
                                Buscar
                            </label>
                            <div className="relative mt-1">
                                <Search className="absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground" />
                                <input
                                    type="text"
                                    value={buscar}
                                    onChange={(e) => setBuscar(e.target.value)}
                                    placeholder="Cliente o N° de venta..."
                                    className="w-full rounded-lg border border-border bg-muted/40 py-1.5 pr-3 pl-8 text-xs focus:border-primary focus:outline-none"
                                />
                            </div>
                        </div>

                        <div>
                            <label className="block font-semibold text-foreground/80">
                                Vendedor
                            </label>
                            <select
                                value={vendedorId}
                                onChange={(e) => setVendedorId(e.target.value)}
                                className="mt-1 w-full rounded-lg border border-border bg-muted/40 px-3 py-1.5 text-xs focus:border-primary focus:outline-none"
                            >
                                <option value="">Todos los vendedores</option>
                                {vendedores.map((v) => (
                                    <option key={v.id} value={v.id}>
                                        {v.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="block font-semibold text-foreground/80">
                                Estado de Cuota
                            </label>
                            <select
                                value={estado}
                                onChange={(e) => setEstado(e.target.value)}
                                className="mt-1 w-full rounded-lg border border-border bg-muted/40 px-3 py-1.5 text-xs focus:border-primary focus:outline-none"
                            >
                                <option value="todos">Todos los estados</option>
                                <option value="vencido">Vencidos</option>
                                <option value="pendiente">Pendientes</option>
                                <option value="parcial">
                                    Con Pago Parcial
                                </option>
                                <option value="pagado">Pagados</option>
                            </select>
                        </div>

                        <div>
                            <label className="block font-semibold text-foreground/80">
                                Periodo / Vencimiento
                            </label>
                            <select
                                value={periodo}
                                onChange={(e) => setPeriodo(e.target.value)}
                                className="mt-1 w-full rounded-lg border border-border bg-muted/40 px-3 py-1.5 text-xs focus:border-primary focus:outline-none"
                            >
                                <option value="todos">
                                    Todos los periodos
                                </option>
                                <option value="vencido">Solo vencidos</option>
                                <option value="vence_semana">
                                    Vence esta semana
                                </option>
                                <option value="mes">Vence este mes</option>
                            </select>
                        </div>
                    </div>

                    <div className="flex justify-end gap-2 border-t border-border pt-2">
                        <button
                            type="button"
                            onClick={resetFilters}
                            className="rounded-lg border border-border bg-card px-3 py-1.5 text-xs font-semibold text-muted-foreground hover:bg-background"
                        >
                            Limpiar
                        </button>
                        <button
                            type="button"
                            onClick={applyFilters}
                            className="inline-flex items-center gap-1.5 rounded-lg bg-card px-4 py-1.5 text-xs font-semibold text-white hover:bg-foreground/90"
                        >
                            <Filter className="size-3.5" />
                            <span>Aplicar Filtros</span>
                        </button>
                    </div>
                </div>

                {/* Tabla de Cuotas */}
                <div className="overflow-hidden rounded-xl border border-border bg-card shadow-xs">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="border-b border-border bg-muted/40 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-3">Venta / Cuota</th>
                                    <th className="px-4 py-3">Cliente</th>
                                    <th className="px-4 py-3">Vendedor</th>
                                    <th className="px-4 py-3">Vencimiento</th>
                                    <th className="px-4 py-3 text-right">
                                        Monto Cuota
                                    </th>
                                    <th className="px-4 py-3 text-right">
                                        Pagado
                                    </th>
                                    <th className="px-4 py-3 text-right">
                                        Saldo
                                    </th>
                                    <th className="px-4 py-3 text-center">
                                        Estado
                                    </th>
                                    <th className="px-4 py-3 text-center">
                                        Pagos
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {cuotas.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={9}
                                            className="py-8 text-center text-muted-foreground"
                                        >
                                            No se encontraron cuotas con los
                                            filtros seleccionados.
                                        </td>
                                    </tr>
                                ) : (
                                    cuotas.data.map((c) => {
                                        const isVencido =
                                            c.estado === 'vencido' ||
                                            c.dias_vencido > 0;

                                        return (
                                            <tr
                                                key={c.id}
                                                className="transition-colors hover:bg-muted/40"
                                            >
                                                <td className="px-4 py-3">
                                                    <div className="font-mono font-bold text-foreground">
                                                        {c.sale_numero}
                                                    </div>
                                                    <div className="text-[11px] text-muted-foreground">
                                                        Cuota #{c.numero_cuota}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="font-medium text-foreground">
                                                        {c.cliente.razon_social}
                                                    </div>
                                                    <div className="flex items-center gap-2 text-[11px] text-muted-foreground">
                                                        {c.cliente
                                                            .numero_documento && (
                                                            <span>
                                                                RUC/DNI:{' '}
                                                                {
                                                                    c.cliente
                                                                        .numero_documento
                                                                }
                                                            </span>
                                                        )}
                                                        {c.cliente.telefono && (
                                                            <span className="flex items-center gap-0.5">
                                                                <Phone className="size-3" />
                                                                {
                                                                    c.cliente
                                                                        .telefono
                                                                }
                                                            </span>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3 text-foreground/80">
                                                    {c.vendedor.name}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="font-mono text-[11px] text-foreground">
                                                        {c.fecha_vencimiento}
                                                    </div>
                                                    {isVencido &&
                                                        c.estado !==
                                                            'pagado' && (
                                                            <div className="text-[10px] font-bold text-primary">
                                                                {c.dias_vencido}{' '}
                                                                días de mora
                                                            </div>
                                                        )}
                                                </td>
                                                <td className="px-4 py-3 text-right font-mono font-semibold text-foreground">
                                                    {formatCurrency(c.monto)}
                                                </td>
                                                <td className="px-4 py-3 text-right font-mono text-emerald-700">
                                                    {formatCurrency(
                                                        c.monto_pagado,
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-right font-mono font-bold text-foreground">
                                                    {formatCurrency(
                                                        c.saldo_pendiente,
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-center">
                                                    <span
                                                        className={`inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold uppercase ${
                                                            c.estado ===
                                                            'pagado'
                                                                ? 'bg-emerald-100 text-emerald-800'
                                                                : c.estado ===
                                                                    'vencido'
                                                                  ? 'bg-red-100 text-primary'
                                                                  : c.estado ===
                                                                      'parcial'
                                                                    ? 'bg-amber-100 text-amber-800'
                                                                    : 'bg-blue-100 text-blue-800'
                                                        }`}
                                                    >
                                                        {c.estado}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 text-center">
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            setViewingPayments(
                                                                c,
                                                            )
                                                        }
                                                        className="inline-flex items-center gap-1 rounded-md border border-border bg-card px-2 py-1 text-[11px] font-semibold text-foreground/80 hover:bg-background"
                                                    >
                                                        <Eye className="size-3 text-muted-foreground" />
                                                        <span>
                                                            {c.pagos.length}
                                                        </span>
                                                    </button>
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Paginación */}
                    {cuotas.links && cuotas.links.length > 3 && (
                        <div className="flex items-center justify-between border-t border-border bg-muted/40 px-4 py-3 text-xs text-muted-foreground">
                            <div>Total: {cuotas.total} cuotas</div>
                            <div className="flex items-center gap-1">
                                {cuotas.links.map((link, i) => {
                                    if (!link.url) {
                                        return (
                                            <span
                                                key={i}
                                                dangerouslySetInnerHTML={{
                                                    __html: link.label,
                                                }}
                                                className="px-2.5 py-1 text-zinc-400"
                                            />
                                        );
                                    }
                                    return (
                                        <button
                                            key={i}
                                            type="button"
                                            onClick={() =>
                                                router.get(
                                                    link.url!,
                                                    {},
                                                    { preserveState: true },
                                                )
                                            }
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                            className={`rounded-md px-2.5 py-1 text-xs transition-colors ${
                                                link.active
                                                    ? 'bg-card font-bold text-white'
                                                    : 'text-foreground/80 hover:bg-muted/40'
                                            }`}
                                        />
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </div>

                {/* Modal Detalle de Pagos / Abonos */}
                {viewingPayments && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                        <div className="w-full max-w-lg rounded-xl border border-border bg-card p-6 shadow-xl">
                            <div className="flex items-center justify-between border-b border-border pb-3">
                                <div>
                                    <h3 className="font-['Oswald',sans-serif] text-base font-bold text-foreground uppercase">
                                        Historial de Pagos —{' '}
                                        {viewingPayments.sale_numero}
                                    </h3>
                                    <p className="text-xs text-muted-foreground">
                                        Cuota #{viewingPayments.numero_cuota} de{' '}
                                        {viewingPayments.cliente.razon_social}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setViewingPayments(null)}
                                    className="rounded-md p-1 text-muted-foreground hover:bg-background"
                                >
                                    <X className="size-4" />
                                </button>
                            </div>

                            <div className="mt-4 space-y-3">
                                <div className="grid grid-cols-3 gap-2 rounded-lg bg-muted/40 p-3 text-xs">
                                    <div>
                                        <span className="text-muted-foreground">
                                            Total Cuota:
                                        </span>
                                        <p className="font-mono font-bold text-foreground">
                                            {formatCurrency(
                                                viewingPayments.monto,
                                            )}
                                        </p>
                                    </div>
                                    <div>
                                        <span className="text-muted-foreground">
                                            Abonado:
                                        </span>
                                        <p className="font-mono font-bold text-emerald-700">
                                            {formatCurrency(
                                                viewingPayments.monto_pagado,
                                            )}
                                        </p>
                                    </div>
                                    <div>
                                        <span className="text-muted-foreground">
                                            Saldo Restante:
                                        </span>
                                        <p className="font-mono font-bold text-primary">
                                            {formatCurrency(
                                                viewingPayments.saldo_pendiente,
                                            )}
                                        </p>
                                    </div>
                                </div>

                                <div className="divide-y divide-border pt-2">
                                    {viewingPayments.pagos.length === 0 ? (
                                        <p className="py-6 text-center text-xs text-muted-foreground">
                                            Aún no se han registrado abonos para
                                            esta cuota.
                                        </p>
                                    ) : (
                                        viewingPayments.pagos.map((p, idx) => (
                                            <div
                                                key={p.id}
                                                className="flex items-center justify-between py-2 text-xs"
                                            >
                                                <div className="flex items-center gap-2">
                                                    <span className="font-mono font-bold text-muted-foreground">
                                                        #{idx + 1}
                                                    </span>
                                                    <div>
                                                        <div className="font-semibold text-foreground uppercase">
                                                            {p.forma_pago.replace(
                                                                '_',
                                                                ' ',
                                                            )}
                                                        </div>
                                                        {p.numero_operacion && (
                                                            <div className="text-[10px] text-muted-foreground">
                                                                Op:{' '}
                                                                {
                                                                    p.numero_operacion
                                                                }
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>
                                                <div className="text-right">
                                                    <div className="font-mono font-bold text-emerald-700">
                                                        +
                                                        {formatCurrency(
                                                            p.monto,
                                                        )}
                                                    </div>
                                                    <div className="text-[10px] text-muted-foreground">
                                                        {p.fecha}
                                                    </div>
                                                </div>
                                            </div>
                                        ))
                                    )}
                                </div>
                            </div>

                            <div className="mt-6 flex justify-end border-t border-border pt-3">
                                <button
                                    type="button"
                                    onClick={() => setViewingPayments(null)}
                                    className="rounded-lg bg-card px-4 py-1.5 text-xs font-semibold text-white hover:bg-foreground/90"
                                >
                                    Cerrar
                                </button>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </GerenteLayout>
    );
}
