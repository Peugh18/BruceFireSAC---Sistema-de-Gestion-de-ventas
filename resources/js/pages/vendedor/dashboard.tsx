import { Head, Link, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowRight,
    Award,
    Calendar,
    CheckCircle2,
    Clock,
    TrendingUp,
    Wallet,
    X,
} from 'lucide-react';
import { useState } from 'react';

import CashRegisterController from '@/actions/App/Http/Controllers/Vendedor/CashRegisterController';
import CollectionController from '@/actions/App/Http/Controllers/Vendedor/CollectionController';
import QuoteController from '@/actions/App/Http/Controllers/Vendedor/QuoteController';
import SaleController from '@/actions/App/Http/Controllers/Vendedor/SaleController';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import VendedorLayout from '@/layouts/vendedor-layout';
import type { Team } from '@/types';

export type VentasHoy = {
    total: number;
    count: number;
    ticket_promedio: number;
};

export type CajaHoy = {
    turno_id: number;
    estado: string;
    fecha_apertura: string | null;
    monto_apertura: number;
    total_efectivo: number;
    total_esperado_corriente: number;
    por_forma_pago: {
        efectivo: number;
        transferencia: number;
        tarjeta_yape: number;
        otros: number;
    };
} | null;

export type CobroPendiente = {
    id: number;
    sale_id: number;
    sale_numero?: string;
    numero_cuota: number;
    monto: number;
    fecha_vencimiento: string;
    estado: string;
    dias_vencido: number;
    cliente?: string;
};

export type Props = {
    ventas_hoy: VentasHoy;
    caja_hoy: CajaHoy;
    cotizaciones_mes: Record<string, number>;
    cobros_pendientes: CobroPendiente[];
    alertas_top: unknown[];
    agenda_hoy: unknown[];
};

function formatCurrency(amount: number | string | null | undefined): string {
    const num = typeof amount === 'string' ? parseFloat(amount) : (amount ?? 0);
    if (isNaN(num)) return 'S/ 0.00';
    return `S/ ${num.toLocaleString('es-PE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

function initials(value: string | undefined): string {
    if (!value) return 'CL';
    return value
        .split(/\s+/u)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

export default function VendedorDashboard({
    ventas_hoy,
    caja_hoy,
    cotizaciones_mes,
    cobros_pendientes,
    alertas_top,
    agenda_hoy,
}: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug =
        currentTeam?.slug ||
        (typeof window !== 'undefined'
            ? window.location.pathname.split('/')[1]
            : '');

    const [bannerDismissed, setBannerDismissed] = useState(false);

    // Totales de cotizaciones del mes
    const cotAceptadas = cotizaciones_mes?.aceptada ?? 0;
    const cotPendientes =
        (cotizaciones_mes?.enviada ?? 0) + (cotizaciones_mes?.emitida ?? 0);
    const cotVencidas = cotizaciones_mes?.vencida ?? 0;
    const cotBorrador = cotizaciones_mes?.borrador ?? 0;
    const cotTotal = cotAceptadas + cotPendientes + cotVencidas + cotBorrador;

    // Cálculo porcentual para SVG donut de cotizaciones
    const percAceptadas = cotTotal > 0 ? (cotAceptadas / cotTotal) * 100 : 0;
    const percPendientes = cotTotal > 0 ? (cotPendientes / cotTotal) * 100 : 0;
    const percVencidas = cotTotal > 0 ? (cotVencidas / cotTotal) * 100 : 0;

    // Totales de formas de pago en caja
    const efec = caja_hoy?.por_forma_pago?.efectivo ?? 0;
    const trans = caja_hoy?.por_forma_pago?.transferencia ?? 0;
    const yape = caja_hoy?.por_forma_pago?.tarjeta_yape ?? 0;
    const otros = caja_hoy?.por_forma_pago?.otros ?? 0;
    const totalPagos = efec + trans + yape + otros;

    const percEfec = totalPagos > 0 ? (efec / totalPagos) * 100 : 0;
    const percTrans = totalPagos > 0 ? (trans / totalPagos) * 100 : 0;
    const percYape = totalPagos > 0 ? (yape / totalPagos) * 100 : 0;

    // Conteo de cuotas vencidas
    const totalCobrosVencidos = cobros_pendientes.filter(
        (c) => c.dias_vencido > 0,
    ).length;

    return (
        <VendedorLayout title="Dashboard">
            <Head title="Dashboard Vendedor" />

            <div className="flex flex-col gap-4">
                {/* Banner de alerta si hay cobros vencidos o cotizaciones pendientes */}
                {!bannerDismissed &&
                    (totalCobrosVencidos > 0 || cotPendientes > 0) && (
                        <div className="flex items-center gap-3 rounded-[14px] bg-gradient-to-r from-[#B9151A] to-[#E31E24] px-5 py-3.5 text-white shadow-xs">
                            <AlertCircle className="size-5 shrink-0 text-white" />
                            <span className="flex-1 text-[13px]">
                                {totalCobrosVencidos > 0 ? (
                                    <>
                                        Tienes{' '}
                                        <b>
                                            {totalCobrosVencidos} cuota(s)
                                            vencida(s)
                                        </b>{' '}
                                        por cobrar hoy.{' '}
                                        <Link
                                            href={CollectionController.index.url(
                                                teamSlug,
                                            )}
                                            className="font-bold underline underline-offset-2 hover:opacity-90"
                                        >
                                            Gestionar cobranzas &rarr;
                                        </Link>
                                    </>
                                ) : (
                                    <>
                                        Tienes{' '}
                                        <b>
                                            {cotPendientes} cotización(es)
                                            pendientes
                                        </b>{' '}
                                        de respuesta del cliente.{' '}
                                        <Link
                                            href={QuoteController.index.url(
                                                teamSlug,
                                            )}
                                            className="font-bold underline underline-offset-2 hover:opacity-90"
                                        >
                                            Revisar cotizaciones &rarr;
                                        </Link>
                                    </>
                                )}
                            </span>
                            <button
                                type="button"
                                onClick={() => setBannerDismissed(true)}
                                className="cursor-pointer text-white opacity-80 transition-opacity hover:opacity-100"
                                aria-label="Cerrar aviso"
                            >
                                <X className="size-4" />
                            </button>
                        </div>
                    )}

                {/* 3-Column Top Grid: Ventas Hoy, Cotizaciones Mes, Caja Hoy */}
                <div className="grid gap-4 lg:grid-cols-3">
                    {/* 1. Ventas de Hoy */}
                    <Card className="rounded-[16px] border-border bg-card p-5 shadow-none">
                        <div className="flex items-center justify-between">
                            <span className="text-[13.5px] font-bold text-foreground">
                                Ventas de hoy
                            </span>
                            <span className="text-[11.5px] font-medium text-muted-foreground">
                                Solo hoy
                            </span>
                        </div>

                        <div className="mt-4 flex flex-col gap-4 sm:flex-row sm:items-start">
                            <div className="flex-1">
                                <div className="font-['Oswald',sans-serif] text-[32px] leading-none font-semibold text-foreground">
                                    {formatCurrency(ventas_hoy.total)}
                                </div>
                                <div className="mt-2 flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                    <span className="inline-flex items-center gap-1 rounded-[6px] bg-emerald-500/10 px-2 py-0.5 font-bold text-emerald-600 dark:text-emerald-400">
                                        <TrendingUp className="size-3" />
                                        <span>{ventas_hoy.count} ventas</span>
                                    </span>
                                    <span>registradas hoy</span>
                                </div>

                                <Link
                                    href={SaleController.index.url(teamSlug)}
                                    className="mt-5 inline-flex items-center gap-1.5 rounded-[9px] bg-card px-3.5 py-2 text-[11.5px] font-bold text-white transition-colors hover:bg-foreground/90"
                                >
                                    <span>Ver mis ventas</span>
                                    <ArrowRight className="size-3" />
                                </Link>
                            </div>

                            <div className="flex flex-1 flex-col divide-y divide-border border-t border-border pt-3 text-[12px] sm:border-t-0 sm:border-l sm:pt-0 sm:pl-5">
                                <div className="flex items-center justify-between pb-2">
                                    <span className="text-muted-foreground">
                                        N° de ventas
                                    </span>
                                    <span className="font-bold text-foreground">
                                        {ventas_hoy.count}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between py-2">
                                    <span className="text-muted-foreground">
                                        Ticket promedio
                                    </span>
                                    <span className="font-bold text-foreground">
                                        {formatCurrency(
                                            ventas_hoy.ticket_promedio,
                                        )}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between pt-2">
                                    <span className="text-muted-foreground">
                                        Efectivo en caja
                                    </span>
                                    <span className="font-bold text-foreground">
                                        {formatCurrency(
                                            caja_hoy?.total_efectivo ?? 0,
                                        )}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </Card>

                    {/* 2. Cotizaciones (Este Mes) */}
                    <Card className="flex flex-col justify-between rounded-[16px] border-border bg-card p-5 shadow-none">
                        <div className="flex items-center justify-between">
                            <span className="text-[13.5px] font-bold text-foreground">
                                Cotizaciones
                            </span>
                            <span className="text-[11.5px] font-medium text-muted-foreground">
                                Este mes
                            </span>
                        </div>

                        <div className="relative my-2 flex size-[128px] items-center justify-center self-center">
                            <svg
                                className="size-full -rotate-90"
                                viewBox="0 0 42 42"
                            >
                                <circle
                                    cx="21"
                                    cy="21"
                                    r="15.9"
                                    fill="none"
                                    stroke="#F1EFEC"
                                    strokeWidth="5"
                                />
                                {cotTotal > 0 && (
                                    <>
                                        <circle
                                            cx="21"
                                            cy="21"
                                            r="15.9"
                                            fill="none"
                                            stroke="#1E8E5A"
                                            strokeWidth="5"
                                            strokeDasharray={`${percAceptadas} 100`}
                                            strokeDashoffset="0"
                                            strokeLinecap="round"
                                        />
                                        <circle
                                            cx="21"
                                            cy="21"
                                            r="15.9"
                                            fill="none"
                                            stroke="#B45309"
                                            strokeWidth="5"
                                            strokeDasharray={`${percPendientes} 100`}
                                            strokeDashoffset={`-${percAceptadas}`}
                                            strokeLinecap="round"
                                        />
                                        <circle
                                            cx="21"
                                            cy="21"
                                            r="15.9"
                                            fill="none"
                                            stroke="#B91C1C"
                                            strokeWidth="5"
                                            strokeDasharray={`${percVencidas} 100`}
                                            strokeDashoffset={`-${percAceptadas + percPendientes}`}
                                            strokeLinecap="round"
                                        />
                                    </>
                                )}
                            </svg>
                            <div className="absolute inset-0 flex flex-col items-center justify-center text-center">
                                <span className="font-['Oswald',sans-serif] text-[22px] leading-tight font-semibold text-foreground">
                                    {cotTotal}
                                </span>
                                <span className="text-[9.5px] font-bold tracking-wider text-muted-foreground uppercase">
                                    Total
                                </span>
                            </div>
                        </div>

                        <div className="space-y-1.5 text-[11.5px]">
                            <div className="flex items-center gap-2">
                                <span className="size-2 rounded-[2px] bg-emerald-600" />
                                <span className="text-muted-foreground">
                                    Aceptadas
                                </span>
                                <span className="ml-auto font-bold text-foreground">
                                    {cotAceptadas}
                                </span>
                            </div>
                            <div className="flex items-center gap-2">
                                <span className="size-2 rounded-[2px] bg-amber-600" />
                                <span className="text-muted-foreground">
                                    Pendientes
                                </span>
                                <span className="ml-auto font-bold text-foreground">
                                    {cotPendientes}
                                </span>
                            </div>
                            <div className="flex items-center gap-2">
                                <span className="size-2 rounded-[2px] bg-destructive" />
                                <span className="text-muted-foreground">Vencidas</span>
                                <span className="ml-auto font-bold text-foreground">
                                    {cotVencidas}
                                </span>
                            </div>
                        </div>
                    </Card>

                    {/* 3. Caja de Hoy */}
                    <Card className="flex flex-col justify-between rounded-[16px] border-border bg-card p-5 shadow-none">
                        <div className="flex items-center justify-between">
                            <span className="text-[13.5px] font-bold text-foreground">
                                Caja de hoy
                            </span>
                            <Badge
                                className={
                                    caja_hoy?.estado === 'abierto'
                                        ? 'border-none bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20'
                                        : 'border-none bg-muted text-muted-foreground'
                                }
                            >
                                {caja_hoy?.estado === 'abierto'
                                    ? 'Turno abierto'
                                    : 'Sin turno'}
                            </Badge>
                        </div>

                        {caja_hoy ? (
                            <>
                                <div className="relative my-2 flex size-[128px] items-center justify-center self-center">
                                    <svg
                                        className="size-full -rotate-90"
                                        viewBox="0 0 42 42"
                                    >
                                        <circle
                                            cx="21"
                                            cy="21"
                                            r="15.9"
                                            fill="none"
                                            stroke="#F1EFEC"
                                            strokeWidth="5"
                                        />
                                        {totalPagos > 0 && (
                                            <>
                                                <circle
                                                    cx="21"
                                                    cy="21"
                                                    r="15.9"
                                                    fill="none"
                                                    stroke="#1E8E5A"
                                                    strokeWidth="5"
                                                    strokeDasharray={`${percEfec} 100`}
                                                    strokeDashoffset="0"
                                                    strokeLinecap="round"
                                                />
                                                <circle
                                                    cx="21"
                                                    cy="21"
                                                    r="15.9"
                                                    fill="none"
                                                    stroke="#2563EB"
                                                    strokeWidth="5"
                                                    strokeDasharray={`${percTrans} 100`}
                                                    strokeDashoffset={`-${percEfec}`}
                                                    strokeLinecap="round"
                                                />
                                                <circle
                                                    cx="21"
                                                    cy="21"
                                                    r="15.9"
                                                    fill="none"
                                                    stroke="#B45309"
                                                    strokeWidth="5"
                                                    strokeDasharray={`${percYape} 100`}
                                                    strokeDashoffset={`-${percEfec + percTrans}`}
                                                    strokeLinecap="round"
                                                />
                                            </>
                                        )}
                                    </svg>
                                    <div className="absolute inset-0 flex flex-col items-center justify-center text-center">
                                        <span className="font-['Oswald',sans-serif] text-[17px] leading-tight font-semibold text-foreground">
                                            {formatCurrency(
                                                caja_hoy.total_esperado_corriente,
                                            )}
                                        </span>
                                        <span className="text-[9.5px] font-bold tracking-wider text-muted-foreground uppercase">
                                            Esperado
                                        </span>
                                    </div>
                                </div>

                                <div className="space-y-1.5 text-[11.5px]">
                                    <div className="flex items-center gap-2">
                                        <span className="size-2 rounded-[2px] bg-emerald-600" />
                                        <span className="text-muted-foreground">
                                            Efectivo
                                        </span>
                                        <span className="ml-auto font-bold text-foreground">
                                            {formatCurrency(efec)}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className="size-2 rounded-[2px] bg-blue-600" />
                                        <span className="text-muted-foreground">
                                            Transferencia
                                        </span>
                                        <span className="ml-auto font-bold text-foreground">
                                            {formatCurrency(trans)}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className="size-2 rounded-[2px] bg-amber-600" />
                                        <span className="text-muted-foreground">
                                            Tarjeta / Yape
                                        </span>
                                        <span className="ml-auto font-bold text-foreground">
                                            {formatCurrency(yape)}
                                        </span>
                                    </div>
                                </div>
                            </>
                        ) : (
                            <div className="flex flex-1 flex-col items-center justify-center gap-2.5 py-6 text-center">
                                <Wallet className="size-8 text-muted-foreground" />
                                <div>
                                    <p className="text-xs font-semibold text-foreground">
                                        No hay turno abierto
                                    </p>
                                    <p className="mt-0.5 text-[11px] text-muted-foreground">
                                        Abre tu turno de hoy para registrar
                                        cobros
                                    </p>
                                </div>
                                <Link
                                    href={CashRegisterController.show.url(
                                        teamSlug,
                                    )}
                                    className="mt-1 rounded-[8px] bg-primary px-3 py-1.5 text-[11.5px] font-bold text-white transition-colors hover:bg-primary/90"
                                >
                                    Ir a Caja
                                </Link>
                            </div>
                        )}
                    </Card>
                </div>

                {/* 2-Column Middle Grid: Cobros Pendientes + Quick Action Promos */}
                <div className="grid gap-4 lg:grid-cols-12">
                    {/* Cobros Pendientes (7 cols) */}
                    <Card className="rounded-[16px] border-border bg-card p-5 shadow-none lg:col-span-7">
                        <div className="flex items-center justify-between">
                            <span className="text-[13.5px] font-bold text-foreground">
                                Cobros pendientes / vencidos
                            </span>
                            <span className="text-[11.5px] font-medium text-muted-foreground">
                                {cobros_pendientes.length} registros
                            </span>
                        </div>

                        <div className="mt-3 divide-y divide-border">
                            {cobros_pendientes.length === 0 ? (
                                <div className="flex flex-col items-center justify-center gap-2 py-8 text-center text-muted-foreground">
                                    <CheckCircle2 className="size-8 text-emerald-600 dark:text-emerald-400" />
                                    <p className="text-xs font-bold text-foreground">
                                        ¡Excelente! Sin cobros pendientes
                                    </p>
                                    <p className="text-[11px]">
                                        Todas las cuotas de tus clientes están
                                        al día.
                                    </p>
                                </div>
                            ) : (
                                cobros_pendientes.slice(0, 5).map((inst) => {
                                    const isVencido = inst.dias_vencido > 0;
                                    return (
                                        <div
                                            key={inst.id}
                                            className="flex items-center gap-3 py-2.5 first:pt-1"
                                        >
                                            <div
                                                className={`flex size-[34px] shrink-0 items-center justify-center rounded-full text-[11px] font-bold ${
                                                    isVencido
                                                        ? 'bg-destructive/10 text-destructive border border-destructive/20'
                                                        : 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20'
                                                }`}
                                            >
                                                {initials(inst.cliente)}
                                            </div>

                                            <div className="min-w-0 flex-1">
                                                <div className="truncate text-[12.5px] font-bold text-foreground">
                                                    {inst.cliente ||
                                                        'Cliente sin nombre'}
                                                </div>
                                                <div className="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                                    <span>
                                                        Cuota{' '}
                                                        {inst.numero_cuota}
                                                    </span>
                                                    <span>&bull;</span>
                                                    <span
                                                        className={
                                                            isVencido
                                                                ? 'font-bold text-destructive'
                                                                : 'text-amber-600 dark:text-amber-400'
                                                        }
                                                    >
                                                        {isVencido
                                                            ? `Vencido hace ${inst.dias_vencido} día(s)`
                                                            : `Vence el ${inst.fecha_vencimiento}`}
                                                    </span>
                                                </div>
                                            </div>

                                            <span
                                                className={`rounded-full px-2.5 py-0.5 text-[11px] font-bold whitespace-nowrap ${
                                                    isVencido
                                                        ? 'bg-destructive/10 text-destructive border border-destructive/20'
                                                        : 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20'
                                                }`}
                                            >
                                                {formatCurrency(inst.monto)}
                                            </span>
                                        </div>
                                    );
                                })
                            )}
                        </div>

                        {cobros_pendientes.length > 0 && (
                            <div className="mt-3 border-t border-border pt-2.5 text-center">
                                <Link
                                    href={CollectionController.index.url(
                                        teamSlug,
                                    )}
                                    className="text-[12px] font-bold text-primary hover:underline"
                                >
                                    Ver todas las cobranzas &rarr;
                                </Link>
                            </div>
                        )}
                    </Card>

                    {/* Promos / Quick Actions (5 cols) */}
                    <div className="flex flex-col gap-4 lg:col-span-5">
                        <div className="flex min-h-[120px] flex-col justify-center rounded-[14px] bg-card p-5 text-white shadow-xs">
                            <h4 className="text-[14px] font-bold">
                                Arqueo de caja diario
                            </h4>
                            <p className="mt-1 text-[12px] text-muted-foreground">
                                Registra tus montos de apertura y realiza el
                                arqueo ciego antes del fin de turno.
                            </p>
                            <Link
                                href={CashRegisterController.show.url(teamSlug)}
                                className="mt-3 inline-flex self-start rounded-[8px] bg-card px-3.5 py-1.5 text-[11.5px] font-bold text-foreground transition-colors hover:bg-muted"
                            >
                                {caja_hoy?.estado === 'abierto'
                                    ? 'Cerrar turno'
                                    : 'Abrir turno'}
                            </Link>
                        </div>

                        <div className="flex min-h-[120px] flex-col justify-center rounded-[14px] bg-blue-600 p-5 text-white shadow-xs">
                            <h4 className="text-[14px] font-bold">
                                Alertas de Vencimiento
                            </h4>
                            <p className="mt-1 text-[12px] text-blue-100">
                                Revisa los extintores y pruebas hidrostáticas
                                que están por vencer en tus clientes.
                            </p>
                            <Link
                                href={`/${teamSlug}/vendedor/alertas`}
                                className="mt-3 inline-flex self-start rounded-[8px] bg-card px-3.5 py-1.5 text-[11.5px] font-bold text-blue-600 dark:text-blue-400 transition-colors hover:bg-blue-50"
                            >
                                Ver alertas
                            </Link>
                        </div>
                    </div>
                </div>

                {/* Bottom Row: Agenda de Hoy y Alertas Top (Elegant empty states as requested) */}
                <div className="grid gap-4 lg:grid-cols-2">
                    {/* Agenda de hoy */}
                    <Card className="rounded-[16px] border-border bg-card p-5 shadow-none">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Calendar className="size-4 text-muted-foreground" />
                                <span className="text-[13.5px] font-bold text-foreground">
                                    Agenda de hoy
                                </span>
                            </div>
                            <span className="text-[11.5px] text-muted-foreground">
                                Visitas e inspecciones
                            </span>
                        </div>

                        {agenda_hoy && agenda_hoy.length > 0 ? (
                            <div className="mt-3 space-y-2">
                                {/* Lista de eventos si vinieran */}
                            </div>
                        ) : (
                            <div className="flex flex-col items-center justify-center gap-2 py-8 text-center text-muted-foreground">
                                <Clock className="size-7 text-muted-foreground" />
                                <p className="text-xs font-semibold text-foreground">
                                    Sin visitas programadas para hoy
                                </p>
                                <p className="text-[11px] text-muted-foreground">
                                    Tu agenda del día está despejada. Puedes
                                    consultar tus órdenes de servicio activas.
                                </p>
                                <Link
                                    href={`/${teamSlug}/vendedor/ordenes-servicio`}
                                    className="mt-1 text-[11.5px] font-bold text-primary hover:underline"
                                >
                                    Ver órdenes de servicio &rarr;
                                </Link>
                            </div>
                        )}
                    </Card>

                    {/* Alertas Críticas de Equipos */}
                    <Card className="rounded-[16px] border-border bg-card p-5 shadow-none">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Award className="size-4 text-muted-foreground" />
                                <span className="text-[13.5px] font-bold text-foreground">
                                    Alertas de mantenimiento
                                </span>
                            </div>
                            <span className="text-[11.5px] text-muted-foreground">
                                Extintores y pruebas
                            </span>
                        </div>

                        {alertas_top && alertas_top.length > 0 ? (
                            <div className="mt-3 space-y-2">
                                {/* Lista de alertas si vinieran */}
                            </div>
                        ) : (
                            <div className="flex flex-col items-center justify-center gap-2 py-8 text-center text-muted-foreground">
                                <CheckCircle2 className="size-7 text-emerald-600 dark:text-emerald-400" />
                                <p className="text-xs font-semibold text-foreground">
                                    Equipos al día
                                </p>
                                <p className="text-[11px] text-muted-foreground">
                                    No hay avisos de vencimiento críticos
                                    pendientes de revisión para tus clientes
                                    hoy.
                                </p>
                                <Link
                                    href={`/${teamSlug}/vendedor/alertas`}
                                    className="mt-1 text-[11.5px] font-bold text-primary hover:underline"
                                >
                                    Ver panel de alertas &rarr;
                                </Link>
                            </div>
                        )}
                    </Card>
                </div>
            </div>
        </VendedorLayout>
    );
}
