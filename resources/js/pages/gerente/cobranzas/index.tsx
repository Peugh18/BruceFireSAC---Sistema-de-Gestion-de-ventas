import { router, usePage } from '@inertiajs/react';
import {
    BadgeAlert,
    Calendar,
    Clock,
    Eye,
    Filter,
    Phone,
    Search,
    TrendingUp,
    Users,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

import GerenteLayout from '@/layouts/gerente-layout';
import pagosRutas from '@/routes/gerente/cobranzas/pagos';
import CobranzasRoutes from '@/routes/gerente/cobranzas';

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

    const [anulandoId, setAnulandoId] = useState<number | null>(null);
    const [motivo, setMotivo] = useState('');

    const anularPago = (pagoId: number) => {
        router.delete(
            pagosRutas.anular.url({
                current_team: currentTeam.slug,
                payment: pagoId,
            }),
            {
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ??
                            'No se pudo anular el cobro.',
                    ),
                data: { motivo },
                preserveScroll: true,
                onSuccess: () => {
                    setAnulandoId(null);
                    setViewingPayments(null);
                },
            },
        );
    };

    const applyFilters = () => {
        router.get(
            CobranzasRoutes.index.url({ current_team: currentTeam.slug }),
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
            CobranzasRoutes.index.url({ current_team: currentTeam.slug }),
            {},
            { preserveState: true },
        );
    };

    return (
        <GerenteLayout title="Cobranzas Consolidadas">
            <div className="space-y-6">
                {/* Cabecera */}
                <div>
                    <h1 className="text-foreground font-['Oswald',sans-serif] text-2xl font-bold tracking-wide uppercase">
                        Cartera Consolidada de Cobranzas
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Seguimiento centralizado de cuentas por cobrar,
                        vencimientos de cuotas y abonos recibidos.
                    </p>
                </div>

                {/* KPI Cards */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <div className="border-border bg-card rounded-xl border p-4 shadow-xs">
                        <div className="text-muted-foreground flex items-center justify-between text-xs">
                            <span className="font-medium uppercase">
                                Por Cobrar Total
                            </span>
                            <Clock className="size-4 text-blue-600" />
                        </div>
                        <div className="text-foreground mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold">
                            {formatCurrency(kpis.totalPorCobrar)}
                        </div>
                        <p className="text-muted-foreground mt-0.5 text-[11px]">
                            Saldo pendiente en cartera
                        </p>
                    </div>

                    <div className="border-border bg-card rounded-xl border p-4 shadow-xs">
                        <div className="text-muted-foreground flex items-center justify-between text-xs">
                            <span className="font-medium uppercase">
                                Vencido
                            </span>
                            <BadgeAlert className="text-primary-strong size-4" />
                        </div>
                        <div className="text-primary-strong mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold">
                            {formatCurrency(kpis.vencidoTotal)}
                        </div>
                        <p className="text-muted-foreground mt-0.5 text-[11px]">
                            Cuotas con fecha pasada
                        </p>
                    </div>

                    <div className="border-border bg-card rounded-xl border p-4 shadow-xs">
                        <div className="text-muted-foreground flex items-center justify-between text-xs">
                            <span className="font-medium uppercase">
                                Vence Esta Semana
                            </span>
                            <Calendar className="text-warning-strong size-4" />
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-amber-700">
                            {formatCurrency(kpis.venceEstaSemana)}
                        </div>
                        <p className="text-muted-foreground mt-0.5 text-[11px]">
                            Vencimientos hasta domingo
                        </p>
                    </div>

                    <div className="border-border bg-card rounded-xl border p-4 shadow-xs">
                        <div className="text-muted-foreground flex items-center justify-between text-xs">
                            <span className="font-medium uppercase">
                                Cobrado en el Mes
                            </span>
                            <TrendingUp className="text-success-strong size-4" />
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-emerald-700">
                            {formatCurrency(kpis.cobradoEsteMes)}
                        </div>
                        <p className="text-muted-foreground mt-0.5 text-[11px]">
                            Abonos ingresados en el mes
                        </p>
                    </div>

                    <div className="border-border bg-card rounded-xl border p-4 shadow-xs">
                        <div className="text-muted-foreground flex items-center justify-between text-xs">
                            <span className="font-medium uppercase">
                                Clientes con Deuda
                            </span>
                            <Users className="size-4 text-purple-600" />
                        </div>
                        <div className="text-foreground mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold">
                            {kpis.clientesConDeuda}
                        </div>
                        <p className="text-muted-foreground mt-0.5 text-[11px]">
                            Empresas en cartera activa
                        </p>
                    </div>
                </div>

                {/* Filtros */}
                <div className="border-border bg-card space-y-3 rounded-xl border p-4 shadow-xs">
                    <div className="grid grid-cols-1 gap-3 text-xs sm:grid-cols-2 lg:grid-cols-4">
                        <div className="relative">
                            <label className="text-foreground/80 block font-semibold">
                                Buscar
                            </label>
                            <div className="relative mt-1">
                                <Search className="text-muted-foreground absolute top-1/2 left-3 size-3.5 -translate-y-1/2" />
                                <input
                                    aria-label="Buscar cobros"
                                    type="text"
                                    value={buscar}
                                    onChange={(e) => setBuscar(e.target.value)}
                                    placeholder="Cliente o N° de venta..."
                                    className="border-border bg-muted/40 focus:border-primary w-full rounded-lg border py-1.5 pr-3 pl-8 text-xs focus:outline-none"
                                />
                            </div>
                        </div>

                        <div>
                            <label
                                htmlFor="gerente-cobranzas-vendedor"
                                className="text-foreground/80 block font-semibold"
                            >
                                Vendedor
                            </label>
                            <select
                                id="gerente-cobranzas-vendedor"
                                value={vendedorId}
                                onChange={(e) => setVendedorId(e.target.value)}
                                className="border-border bg-muted/40 focus:border-primary mt-1 w-full rounded-lg border px-3 py-1.5 text-xs focus:outline-none"
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
                            <label
                                htmlFor="gerente-cobranzas-estado-de-cuota"
                                className="text-foreground/80 block font-semibold"
                            >
                                Estado de Cuota
                            </label>
                            <select
                                id="gerente-cobranzas-estado-de-cuota"
                                value={estado}
                                onChange={(e) => setEstado(e.target.value)}
                                className="border-border bg-muted/40 focus:border-primary mt-1 w-full rounded-lg border px-3 py-1.5 text-xs focus:outline-none"
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
                            <label
                                htmlFor="gerente-cobranzas-periodo-vencimiento"
                                className="text-foreground/80 block font-semibold"
                            >
                                Periodo / Vencimiento
                            </label>
                            <select
                                id="gerente-cobranzas-periodo-vencimiento"
                                value={periodo}
                                onChange={(e) => setPeriodo(e.target.value)}
                                className="border-border bg-muted/40 focus:border-primary mt-1 w-full rounded-lg border px-3 py-1.5 text-xs focus:outline-none"
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

                    <div className="border-border flex justify-end gap-2 border-t pt-2">
                        <button
                            type="button"
                            onClick={resetFilters}
                            className="border-border bg-card text-muted-foreground hover:bg-background rounded-lg border px-3 py-1.5 text-xs font-semibold"
                        >
                            Limpiar
                        </button>
                        <button
                            type="button"
                            onClick={applyFilters}
                            className="bg-foreground text-background hover:bg-foreground/90 inline-flex items-center gap-1.5 rounded-lg px-4 py-1.5 text-xs font-semibold shadow-xs transition-colors"
                        >
                            <Filter className="size-3.5" />
                            <span>Aplicar Filtros</span>
                        </button>
                    </div>
                </div>

                {/* Tabla de Cuotas */}
                <div className="border-border bg-card overflow-hidden rounded-xl border shadow-xs">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="border-border bg-muted/40 text-muted-foreground border-b text-[11px] font-semibold tracking-wider uppercase">
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
                            <tbody className="divide-border divide-y">
                                {cuotas.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={9}
                                            className="text-muted-foreground py-8 text-center"
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
                                                className="hover:bg-muted/40 transition-colors"
                                            >
                                                <td className="px-4 py-3">
                                                    <div className="text-foreground font-mono font-bold">
                                                        {c.sale_numero}
                                                    </div>
                                                    <div className="text-muted-foreground text-[11px]">
                                                        Cuota #{c.numero_cuota}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="text-foreground font-medium">
                                                        {c.cliente.razon_social}
                                                    </div>
                                                    <div className="text-muted-foreground flex items-center gap-2 text-[11px]">
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
                                                <td className="text-foreground/80 px-4 py-3">
                                                    {c.vendedor.name}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="text-foreground font-mono text-[11px]">
                                                        {c.fecha_vencimiento}
                                                    </div>
                                                    {isVencido &&
                                                        c.estado !==
                                                            'pagado' && (
                                                            <div className="text-primary-strong text-[10px] font-bold">
                                                                {c.dias_vencido}{' '}
                                                                días de mora
                                                            </div>
                                                        )}
                                                </td>
                                                <td className="text-foreground px-4 py-3 text-right font-mono font-semibold">
                                                    {formatCurrency(c.monto)}
                                                </td>
                                                <td className="px-4 py-3 text-right font-mono text-emerald-700">
                                                    {formatCurrency(
                                                        c.monto_pagado,
                                                    )}
                                                </td>
                                                <td className="text-foreground px-4 py-3 text-right font-mono font-bold">
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
                                                                  ? 'text-primary-strong bg-red-100'
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
                                                        className="border-border bg-card text-foreground/80 hover:bg-background inline-flex items-center gap-1 rounded-md border px-2 py-1 text-[11px] font-semibold"
                                                    >
                                                        <Eye className="text-muted-foreground size-3" />
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
                        <div className="border-border bg-muted/40 text-muted-foreground flex items-center justify-between border-t px-4 py-3 text-xs">
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
                                                    ? 'bg-foreground text-background font-bold shadow-xs'
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
                        <div className="border-border bg-card w-full max-w-lg rounded-xl border p-6 shadow-xl">
                            <div className="border-border flex items-center justify-between border-b pb-3">
                                <div>
                                    <h3 className="text-foreground font-['Oswald',sans-serif] text-base font-bold uppercase">
                                        Historial de Pagos —{' '}
                                        {viewingPayments.sale_numero}
                                    </h3>
                                    <p className="text-muted-foreground text-xs">
                                        Cuota #{viewingPayments.numero_cuota} de{' '}
                                        {viewingPayments.cliente.razon_social}
                                    </p>
                                </div>
                                <button
                                    aria-label="Cerrar detalle de pagos"
                                    type="button"
                                    onClick={() => setViewingPayments(null)}
                                    className="text-muted-foreground hover:bg-background rounded-md p-1"
                                >
                                    <X className="size-4" />
                                </button>
                            </div>

                            <div className="mt-4 space-y-3">
                                <div className="bg-muted/40 grid grid-cols-3 gap-2 rounded-lg p-3 text-xs">
                                    <div>
                                        <span className="text-muted-foreground">
                                            Total Cuota:
                                        </span>
                                        <p className="text-foreground font-mono font-bold">
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
                                        <p className="text-primary-strong font-mono font-bold">
                                            {formatCurrency(
                                                viewingPayments.saldo_pendiente,
                                            )}
                                        </p>
                                    </div>
                                </div>

                                <div className="divide-border divide-y pt-2">
                                    {viewingPayments.pagos.length === 0 ? (
                                        <p className="text-muted-foreground py-6 text-center text-xs">
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
                                                    <span className="text-muted-foreground font-mono font-bold">
                                                        #{idx + 1}
                                                    </span>
                                                    <div>
                                                        <div className="text-foreground font-semibold uppercase">
                                                            {p.forma_pago.replace(
                                                                '_',
                                                                ' ',
                                                            )}
                                                        </div>
                                                        {p.numero_operacion && (
                                                            <div className="text-muted-foreground text-[10px]">
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
                                                    <div className="text-muted-foreground text-[10px]">
                                                        {p.fecha}
                                                    </div>
                                                    {anulandoId === p.id ? (
                                                        <div className="mt-1 flex items-center justify-end gap-1">
                                                            <input
                                                                aria-label="Motivo de la anulación"
                                                                value={motivo}
                                                                onChange={(e) =>
                                                                    setMotivo(
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                                placeholder="Motivo"
                                                                className="border-border w-32 rounded border px-2 py-0.5 text-[11px]"
                                                            />
                                                            <button
                                                                type="button"
                                                                disabled={
                                                                    motivo.trim()
                                                                        .length <
                                                                    3
                                                                }
                                                                onClick={() =>
                                                                    anularPago(
                                                                        p.id,
                                                                    )
                                                                }
                                                                className="rounded bg-red-600 px-2 py-0.5 text-[11px] font-semibold text-white disabled:opacity-40"
                                                            >
                                                                Confirmar
                                                            </button>
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    setAnulandoId(
                                                                        null,
                                                                    )
                                                                }
                                                                className="text-[11px] underline"
                                                            >
                                                                Cancelar
                                                            </button>
                                                        </div>
                                                    ) : (
                                                        <button
                                                            type="button"
                                                            onClick={() => {
                                                                setMotivo('');
                                                                setAnulandoId(
                                                                    p.id,
                                                                );
                                                            }}
                                                            className="mt-1 text-[11px] font-semibold text-red-600 underline"
                                                        >
                                                            Anular pago
                                                        </button>
                                                    )}
                                                </div>
                                            </div>
                                        ))
                                    )}
                                </div>
                            </div>

                            <div className="border-border mt-6 flex justify-end border-t pt-3">
                                <button
                                    type="button"
                                    onClick={() => setViewingPayments(null)}
                                    className="bg-foreground text-background hover:bg-foreground/90 rounded-lg px-4 py-1.5 text-xs font-semibold shadow-xs transition-colors"
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
