import { Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Building2,
    Calendar,
    CheckCircle2,
    ClipboardList,
    Clock3,
    CreditCard,
    FileText,
    MessageSquare,
    Plus,
    UserPlus,
    Wallet,
} from 'lucide-react';
import CashRegisterController from '@/actions/App/Http/Controllers/Vendedor/CashRegisterController';
import CollectionController from '@/actions/App/Http/Controllers/Vendedor/CollectionController';
import QuoteController from '@/actions/App/Http/Controllers/Vendedor/QuoteController';
import SaleController from '@/actions/App/Http/Controllers/Vendedor/SaleController';
import ServiceOrderController from '@/actions/App/Http/Controllers/Vendedor/ServiceOrderController';
import ChispaAvatar from '@/components/chispa-avatar';
import type { AlertItem } from '@/pages/vendedor/alertas/index';
import alertas from '@/routes/vendedor/alertas';
import clientes from '@/routes/vendedor/clientes';
import facturacion from '@/routes/vendedor/facturacion';
import VendedorLayout from '@/layouts/vendedor-layout';
import { fechaCorta, soles } from '@/lib/utils';
import type { Auth, Team } from '@/types';

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
    telefono?: string | null;
};

export type AlertaItem = {
    id: string;
    tipo: string;
    titulo: string;
    mensaje: string;
    url: string;
    fecha: string;
    urgencia: 'alta' | 'media' | 'baja';
};

export type AgendaItem = {
    id: number;
    codigo: string;
    cliente: string;
    tipo_servicio: string;
    tecnico: string | null;
    estado: string;
    prioridad: string;
};

export type Pendientes = {
    por_enviar: number;
    rechazados: number;
    cotizaciones_aceptadas: number;
    cuotas_vencidas: number;
    borradores: number;
};

export type Oportunidad = {
    client_id: number;
    cliente: string;
    numero_documento: string;
    whatsapp: string | null;
    extintores: number;
    vencidos: number;
    por_vencer: number;
    proximo_vencimiento: string | null;
    dias: number | null;
};

export type Props = {
    ventas_hoy: VentasHoy;
    caja_hoy: CajaHoy;
    cotizaciones_mes: Record<string, number>;
    cobros_pendientes: CobroPendiente[];
    alertas_top: AlertaItem[];
    agenda_hoy: AgendaItem[];
    por_vencer_semana: AlertItem[];
    pendientes: Pendientes;
    oportunidades: Oportunidad[];
};

function initials(value: string | undefined): string {
    return (
        value
            ?.split(/\s+/u)
            .filter(Boolean)
            .slice(0, 2)
            .map((part) => part[0]?.toUpperCase() ?? '')
            .join('') || 'CL'
    );
}

export default function VendedorDashboard({
    ventas_hoy,
    caja_hoy,
    cotizaciones_mes,
    cobros_pendientes,
    alertas_top,
    agenda_hoy,
    pendientes,
    oportunidades,
}: Props) {
    const { currentTeam, auth } = usePage<{
        currentTeam?: Team | null;
        auth: Auth;
    }>().props;
    const teamSlug = currentTeam?.slug ?? '';
    const primerNombre = auth.user.name.split(' ')[0];
    const hoy = new Date().toLocaleDateString('es-PE', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        timeZone: 'America/Lima',
    });
    const cotTotal = Object.values(cotizaciones_mes).reduce(
        (sum, count) => sum + count,
        0,
    );
    const cuotasVencidas = cobros_pendientes.filter(
        (cuota) => cuota.dias_vencido > 0,
    ).length;
    const porCobrar = cobros_pendientes.reduce(
        (sum, cuota) => sum + cuota.monto,
        0,
    );
    const estadosCotizacion: Record<string, string> = {
        borrador: 'Borrador',
        emitida: 'Emitidas',
        enviada: 'Enviadas',
        aceptada: 'Aceptadas',
        vendida: 'Vendidas',
        vencida: 'Vencidas',
        rechazada: 'Rechazadas',
        anulada: 'Anuladas',
    };
    const pagos = caja_hoy?.por_forma_pago;
    const haceUnAno = new Date();
    haceUnAno.setFullYear(haceUnAno.getFullYear() - 1);
    const listaPendientes = [
        {
            clave: 'rechazados',
            cantidad: pendientes.rechazados,
            texto: 'comprobante(s) rechazado(s) por SUNAT: corrígelos con «Editar»',
            href: facturacion.index.url(teamSlug, {
                query: { estado: 'rechazado' },
            }),
            icono: AlertTriangle,
            urgente: true,
        },
        {
            clave: 'por_enviar',
            cantidad: pendientes.por_enviar,
            texto: 'comprobante(s) por enviar a SUNAT (se envían solos; revísalos antes)',
            href: facturacion.index.url(teamSlug, {
                query: { estado: 'por_enviar' },
            }),
            icono: Clock3,
            urgente: false,
        },
        {
            clave: 'cuotas_vencidas',
            cantidad: pendientes.cuotas_vencidas,
            texto: 'cuota(s) vencida(s) por cobrar',
            href: CollectionController.index.url(teamSlug),
            icono: CreditCard,
            urgente: true,
        },
        {
            clave: 'cotizaciones_aceptadas',
            cantidad: pendientes.cotizaciones_aceptadas,
            texto: 'cotización(es) aceptada(s) por pasar a venta',
            href: QuoteController.index.url(teamSlug, {
                query: { estado: 'aceptada' },
            }),
            icono: ClipboardList,
            urgente: false,
        },
        {
            clave: 'borradores',
            cantidad: pendientes.borradores,
            texto: 'venta(s) en borrador sin emitir',
            href: SaleController.index.url(teamSlug, {
                query: {
                    estado: 'borrador',
                    desde: haceUnAno.toISOString().slice(0, 10),
                },
            }),
            icono: FileText,
            urgente: false,
        },
    ].filter((item) => item.cantidad > 0);
    const resumen = [
        {
            label: 'Ventas de hoy',
            value: soles(ventas_hoy.total),
            detail: `${ventas_hoy.count} ventas · ticket promedio ${soles(ventas_hoy.ticket_promedio)}`,
        },
        {
            label: 'Cotizaciones del mes',
            value: cotTotal,
            detail: cotTotal
                ? 'Conteo de tus cotizaciones por estado'
                : 'Aún no registras cotizaciones este mes',
        },
        {
            label: 'Cuotas por cobrar',
            value: soles(porCobrar),
            detail: `${cobros_pendientes.length} cuotas mostradas · ${cuotasVencidas} vencidas`,
        },
        {
            label: 'Caja',
            value: caja_hoy ? 'Abierta' : 'Sin turno',
            detail: caja_hoy
                ? `Fondo inicial ${soles(caja_hoy.monto_apertura)} · el esperado se ve al cerrar`
                : 'Abre tu caja para comenzar el turno',
        },
    ];

    return (
        <VendedorLayout title="Inicio">
            <div className="flex flex-col gap-5">
                <section
                    className="bf-data-panel relative overflow-hidden"
                    aria-labelledby="saludo-vendedor"
                >
                    <div className="pr-20 sm:pr-36">
                        <h1
                            id="saludo-vendedor"
                            className="text-[27px] leading-tight font-semibold tracking-[-0.04em] sm:text-[32px]"
                        >
                            Hola, {primerNombre}
                        </h1>
                        <p className="text-muted-foreground mt-2 max-w-[65ch] text-[13px] leading-relaxed">
                            {hoy}. Tienes{' '}
                            <strong className="text-foreground">
                                {agenda_hoy.length} servicios
                            </strong>{' '}
                            en agenda y{' '}
                            <strong className="text-foreground">
                                {cobros_pendientes.length} cuotas
                            </strong>{' '}
                            por cobrar.
                        </p>
                    </div>
                    <div className="absolute top-4 right-3 sm:top-3 sm:right-7">
                        <ChispaAvatar
                            pose="saludo"
                            size={112}
                            className="max-sm:origin-top-right max-sm:scale-75"
                        />
                    </div>
                    <nav
                        aria-label="Acciones rápidas"
                        className="relative mt-5 flex flex-wrap gap-2"
                    >
                        <Link
                            className="bf-action bf-action-primary"
                            href={SaleController.create.url(teamSlug)}
                        >
                            <Plus className="size-4" />
                            Nueva venta
                        </Link>
                        <Link
                            className="bf-action"
                            href={QuoteController.create.url(teamSlug)}
                        >
                            <ClipboardList className="size-4" />
                            Nueva cotización
                        </Link>
                        <Link
                            className="bf-action"
                            href={CollectionController.index.url(teamSlug)}
                        >
                            <CreditCard className="size-4" />
                            Cobrar una cuota
                        </Link>
                        <Link
                            className="bf-action"
                            href={clientes.index.url(teamSlug)}
                        >
                            <UserPlus className="size-4" />
                            Clientes
                        </Link>
                    </nav>
                </section>

                <section
                    aria-label="Resumen del día"
                    className="bf-summary border-border bg-card grid grid-cols-2 overflow-hidden rounded-xl border sm:grid-cols-4"
                >
                    {resumen.map((item) => (
                        <div
                            key={item.label}
                            className="border-border border-r p-4 last:border-r-0 lg:p-5"
                        >
                            <h2 className="text-muted-foreground text-xs">
                                {item.label}
                            </h2>
                            <p className="mt-2 text-[24px] leading-tight font-semibold tracking-tight tabular-nums lg:text-[28px]">
                                {item.value}
                            </p>
                            <p className="text-muted-foreground mt-2 text-[11px] leading-relaxed">
                                {item.detail}
                            </p>
                        </div>
                    ))}
                </section>

                <section
                    className="bf-data-panel"
                    aria-labelledby="pendientes-heading"
                >
                    <h2 id="pendientes-heading">Pendientes de hoy</h2>
                    {listaPendientes.length === 0 ? (
                        <p className="text-muted-foreground mt-2 flex items-center gap-2 text-[12.5px]">
                            <CheckCircle2 className="text-success-strong size-4" />
                            Todo al día: nada por enviar, cobrar ni pasar a
                            venta.
                        </p>
                    ) : (
                        <div className="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            {listaPendientes.map((item) => {
                                const Icono = item.icono;

                                return (
                                    <Link
                                        key={item.clave}
                                        href={item.href}
                                        className={`border-border hover:bg-muted/50 flex items-center gap-3 rounded-[11px] border px-3 py-2.5 transition-colors ${item.urgente ? 'border-l-destructive border-l-4' : ''}`}
                                    >
                                        <Icono
                                            className={`size-4 shrink-0 ${item.urgente ? 'text-destructive-strong' : 'text-warning-strong'}`}
                                        />
                                        <span className="text-[12.5px]">
                                            <strong className="text-foreground text-[15px] tabular-nums">
                                                {item.cantidad}
                                            </strong>{' '}
                                            {item.texto}
                                        </span>
                                    </Link>
                                );
                            })}
                        </div>
                    )}
                </section>

                <div className="grid items-start gap-5 xl:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
                    <div className="grid min-w-0 gap-5">
                        <section
                            className="bf-data-panel"
                            aria-labelledby="cobros-heading"
                        >
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <h2 id="cobros-heading">Cobros pendientes</h2>
                                <Link
                                    href={CollectionController.index.url(
                                        teamSlug,
                                    )}
                                    className="text-primary-strong text-xs font-medium hover:underline"
                                >
                                    Ver cobranzas
                                </Link>
                            </div>
                            {cuotasVencidas > 0 && (
                                <p className="text-destructive-strong bg-destructive/10 mt-4 rounded-lg p-3 text-xs">
                                    {cuotasVencidas} cuotas vencidas necesitan
                                    tu atención.
                                </p>
                            )}
                            <div className="divide-border mt-4 divide-y">
                                {cobros_pendientes.length === 0 ? (
                                    <div className="bf-empty">
                                        <CheckCircle2 className="text-success-strong mx-auto mb-2 size-6" />
                                        <p className="text-foreground font-medium">
                                            Sin cuotas pendientes
                                        </p>
                                        <p>
                                            Consulta el historial en cobranzas.
                                        </p>
                                    </div>
                                ) : (
                                    cobros_pendientes.map((cuota) => {
                                        const phoneDigits =
                                            cuota.telefono?.replace(/\D/g, '');
                                        const phone =
                                            phoneDigits?.length === 9
                                                ? `51${phoneDigits}`
                                                : phoneDigits;
                                        const waText = encodeURIComponent(
                                            `Hola ${cuota.cliente || ''}, le recordamos su cuota ${cuota.numero_cuota} de ${soles(cuota.monto)} que venció el ${fechaCorta(cuota.fecha_vencimiento)}. ¿Podría confirmarnos su fecha estimada de pago? Muchas gracias.`,
                                        );
                                        return (
                                            <div
                                                key={cuota.id}
                                                className="flex flex-wrap items-center gap-3 py-4"
                                            >
                                                <span
                                                    aria-hidden
                                                    className="bg-muted flex size-9 shrink-0 items-center justify-center rounded-xl text-xs font-semibold"
                                                >
                                                    {initials(cuota.cliente)}
                                                </span>
                                                <div className="min-w-0 flex-1">
                                                    <p className="text-[13px] font-semibold">
                                                        {cuota.cliente ||
                                                            'Cliente sin nombre'}
                                                    </p>
                                                    <p className="text-muted-foreground mt-1 text-[11px] leading-relaxed">
                                                        Cuota{' '}
                                                        {cuota.numero_cuota}
                                                        {cuota.sale_numero
                                                            ? ` · ${cuota.sale_numero}`
                                                            : ''}{' '}
                                                        ·{' '}
                                                        {cuota.dias_vencido >
                                                        0 ? (
                                                            <span className="text-destructive-strong">
                                                                Vencida hace{' '}
                                                                {
                                                                    cuota.dias_vencido
                                                                }{' '}
                                                                días
                                                            </span>
                                                        ) : (
                                                            `Vence el ${fechaCorta(cuota.fecha_vencimiento)}`
                                                        )}
                                                    </p>
                                                </div>
                                                <strong className="text-[14px] tabular-nums">
                                                    {soles(cuota.monto)}
                                                </strong>
                                                {cuota.dias_vencido > 0 &&
                                                phone ? (
                                                    <a
                                                        href={`https://wa.me/${phone}?text=${waText}`}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="bf-action text-success-strong"
                                                        aria-label={`Recordar cuota a ${cuota.cliente || 'cliente'} por WhatsApp`}
                                                    >
                                                        <MessageSquare className="size-4" />
                                                        <span className="sr-only">
                                                            WhatsApp
                                                        </span>
                                                    </a>
                                                ) : (
                                                    <Link
                                                        href={CollectionController.index.url(
                                                            teamSlug,
                                                        )}
                                                        className="bf-action"
                                                        aria-label={`Gestionar cuota ${cuota.numero_cuota} de ${cuota.cliente || 'cliente'}`}
                                                    >
                                                        Cobrar
                                                    </Link>
                                                )}
                                            </div>
                                        );
                                    })
                                )}
                            </div>
                        </section>

                        <section
                            className="bf-data-panel"
                            aria-labelledby="vencimientos-heading"
                        >
                            <div className="flex items-center justify-between gap-3">
                                <h2 id="vencimientos-heading">
                                    Clientes para ofrecer recarga
                                </h2>
                                <Link
                                    href={alertas.index.url(teamSlug)}
                                    className="text-primary-strong text-xs font-medium hover:underline"
                                >
                                    Ver todos
                                </Link>
                            </div>
                            {oportunidades.length === 0 ? (
                                <div className="bf-empty">
                                    <CheckCircle2 className="text-success-strong mx-auto mb-2 size-6" />
                                    <p className="text-foreground font-medium">
                                        Ningún cliente vence en los próximos 3
                                        meses
                                    </p>
                                    <p>
                                        Revisa el parque completo de tus
                                        clientes en Por vencer.
                                    </p>
                                </div>
                            ) : (
                                <div className="divide-border mt-3 divide-y">
                                    {oportunidades.map((empresa) => (
                                        <div
                                            key={empresa.client_id}
                                            className="flex flex-wrap items-center justify-between gap-2 py-3"
                                        >
                                            <div className="flex min-w-0 items-center gap-2.5">
                                                <Building2 className="text-muted-foreground size-4 shrink-0" />
                                                <div className="min-w-0">
                                                    <p className="truncate text-[13px] font-semibold">
                                                        {empresa.cliente}
                                                    </p>
                                                    <p className="text-muted-foreground text-xs">
                                                        {empresa.extintores}{' '}
                                                        extintor(es) ·{' '}
                                                        {empresa.vencidos > 0
                                                            ? `${empresa.vencidos} vencido(s)`
                                                            : `vence el ${fechaCorta(empresa.proximo_vencimiento)}`}
                                                    </p>
                                                </div>
                                            </div>
                                            <Link
                                                href={clientes.show.url({
                                                    current_team: teamSlug,
                                                    client: empresa.client_id,
                                                })}
                                                className="text-primary-strong text-xs font-medium hover:underline"
                                            >
                                                Ver cliente
                                            </Link>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </section>
                        <section
                            className="bf-data-panel"
                            aria-labelledby="cotizaciones-heading"
                        >
                            <div className="flex items-center justify-between gap-3">
                                <h2 id="cotizaciones-heading">
                                    Tus cotizaciones del mes
                                </h2>
                                <Link
                                    href={QuoteController.index.url(teamSlug)}
                                    className="text-primary-strong text-xs font-medium hover:underline"
                                >
                                    Ver cotizaciones
                                </Link>
                            </div>
                            {cotTotal === 0 ? (
                                <div className="bf-empty">
                                    <ClipboardList className="mx-auto mb-2 size-6" />
                                    <p className="text-foreground font-medium">
                                        Aún no hay cotizaciones este mes
                                    </p>
                                    <p>
                                        Crea una propuesta para tu próximo
                                        cliente.
                                    </p>
                                </div>
                            ) : (
                                <dl className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                                    {Object.entries(cotizaciones_mes).map(
                                        ([estado, count]) => (
                                            <div
                                                key={estado}
                                                className="bg-muted rounded-lg p-3"
                                            >
                                                <dt className="text-muted-foreground text-xs">
                                                    {estadosCotizacion[
                                                        estado
                                                    ] ??
                                                        estado.replaceAll(
                                                            '_',
                                                            ' ',
                                                        )}
                                                </dt>
                                                <dd className="mt-1 text-xl font-semibold tabular-nums">
                                                    {count}
                                                </dd>
                                            </div>
                                        ),
                                    )}
                                </dl>
                            )}
                        </section>

                        <section
                            className="bf-data-panel"
                            aria-labelledby="caja-heading"
                        >
                            <div className="flex items-center justify-between gap-3">
                                <h2 id="caja-heading">Tu turno de caja</h2>
                                <Link
                                    href={CashRegisterController.show.url(
                                        teamSlug,
                                    )}
                                    className="text-primary-strong text-xs font-medium hover:underline"
                                >
                                    Ver caja
                                </Link>
                            </div>
                            {caja_hoy && pagos ? (
                                <>
                                    <p className="text-muted-foreground mt-3 text-xs">
                                        Abierto
                                        {caja_hoy.fecha_apertura
                                            ? ` desde ${fechaCorta(caja_hoy.fecha_apertura.slice(0, 10))}`
                                            : ''}
                                        . Fondo inicial:{' '}
                                        {soles(caja_hoy.monto_apertura)}.
                                    </p>
                                    <dl className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                                        {[
                                            [
                                                'Transferencia',
                                                pagos.transferencia,
                                            ],
                                            [
                                                'POS, Yape y Plin',
                                                pagos.tarjeta_yape,
                                            ],
                                            ['Otros', pagos.otros],
                                        ].map(([label, amount]) => (
                                            <div key={label}>
                                                <dt className="text-muted-foreground text-xs">
                                                    {label}
                                                </dt>
                                                <dd className="mt-1 text-sm font-semibold tabular-nums">
                                                    {soles(Number(amount))}
                                                </dd>
                                            </div>
                                        ))}
                                    </dl>
                                </>
                            ) : (
                                <div className="bf-empty">
                                    <Wallet className="mx-auto mb-2 size-6" />
                                    <p className="text-foreground font-medium">
                                        No tienes un turno abierto
                                    </p>
                                    <p>
                                        Abre tu caja para registrar los cobros
                                        del turno.
                                    </p>
                                </div>
                            )}
                        </section>
                    </div>

                    <div className="grid min-w-0 gap-5">
                        <section
                            className="bf-data-panel"
                            aria-labelledby="agenda-heading"
                        >
                            <div className="flex items-center justify-between gap-3">
                                <h2 id="agenda-heading">Agenda de hoy</h2>
                                <Link
                                    href={ServiceOrderController.index.url(
                                        teamSlug,
                                    )}
                                    className="text-primary-strong text-xs font-medium hover:underline"
                                >
                                    Ver servicios
                                </Link>
                            </div>
                            {agenda_hoy.length === 0 ? (
                                <div className="bf-empty">
                                    <Calendar className="mx-auto mb-2 size-6" />
                                    <p className="text-foreground font-medium">
                                        Sin servicios programados para hoy
                                    </p>
                                    <p>
                                        Consulta las órdenes activas en
                                        Servicios.
                                    </p>
                                </div>
                            ) : (
                                <div className="mt-4 grid gap-3">
                                    {agenda_hoy.map((orden) => (
                                        <Link
                                            key={orden.id}
                                            href={ServiceOrderController.show.url(
                                                {
                                                    current_team: teamSlug,
                                                    service_order: orden.id,
                                                },
                                            )}
                                            className="bg-muted hover:bg-accent border-primary rounded-xl border-l-2 p-4"
                                        >
                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                <span className="text-muted-foreground text-[11px]">
                                                    {orden.codigo}
                                                </span>
                                                <span className="text-[11px] font-medium">
                                                    {orden.estado.replaceAll(
                                                        '_',
                                                        ' ',
                                                    )}
                                                </span>
                                            </div>
                                            <p className="mt-2 text-[13px] font-semibold">
                                                {orden.tipo_servicio}
                                            </p>
                                            <p className="mt-1 text-xs">
                                                {orden.cliente}
                                            </p>
                                            <p className="text-muted-foreground mt-2 text-[11px]">
                                                {orden.tecnico ??
                                                    'Sin técnico asignado'}
                                                {orden.prioridad === 'urgente'
                                                    ? ' · Urgente'
                                                    : ''}
                                            </p>
                                        </Link>
                                    ))}
                                </div>
                            )}
                        </section>

                        <section
                            className="bf-data-panel"
                            aria-labelledby="avisos-heading"
                        >
                            <div className="flex items-center justify-between gap-3">
                                <h2 id="avisos-heading">Avisos de servicios</h2>
                                <Link
                                    href={ServiceOrderController.index.url(
                                        teamSlug,
                                    )}
                                    className="text-primary-strong text-xs font-medium hover:underline"
                                >
                                    Ver servicios
                                </Link>
                            </div>
                            {alertas_top.length === 0 ? (
                                <div className="bf-empty">
                                    <CheckCircle2 className="text-success-strong mx-auto mb-2 size-6" />
                                    <p className="text-foreground font-medium">
                                        Sin avisos pendientes
                                    </p>
                                    <p>
                                        No hay servicios que requieran atención
                                        por ahora.
                                    </p>
                                </div>
                            ) : (
                                <div className="divide-border mt-3 divide-y">
                                    {alertas_top.map((alerta) => (
                                        <Link
                                            key={alerta.id}
                                            href={alerta.url}
                                            className="hover:bg-muted block rounded-lg py-3"
                                        >
                                            <p className="text-[13px] font-semibold">
                                                {alerta.titulo}
                                                {alerta.urgencia === 'alta' && (
                                                    <span className="text-warning-strong ml-2 text-[11px]">
                                                        Urgente
                                                    </span>
                                                )}
                                            </p>
                                            <p className="text-muted-foreground mt-1 text-xs leading-relaxed">
                                                {alerta.mensaje}
                                            </p>
                                            <p className="text-muted-foreground mt-1 text-[11px]">
                                                {alerta.fecha}
                                            </p>
                                        </Link>
                                    ))}
                                </div>
                            )}
                        </section>
                    </div>
                </div>
            </div>
        </VendedorLayout>
    );
}
