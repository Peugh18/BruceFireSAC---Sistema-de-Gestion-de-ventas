import { pageTitleClass } from '@/components/page-header';
import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    Calendar,
    CheckCircle2,
    ClipboardList,
    CreditCard,
    MessageSquare,
    Plus,
    UserPlus,
    Wallet,
    Wrench,
} from 'lucide-react';
import {
    Apilada,
    Atajos,
    Atencion,
    cantidad,
    formatoBi,
    Indicador,
    Ranking,
    Tarjeta,
    Tendencia,
    Vacio,
    type Aviso,
    type Punto,
} from '@/components/bi';
import CashRegisterController from '@/actions/App/Http/Controllers/Vendedor/CashRegisterController';
import CollectionController from '@/actions/App/Http/Controllers/Vendedor/CollectionController';
import QuoteController from '@/actions/App/Http/Controllers/Vendedor/QuoteController';
import SaleController from '@/actions/App/Http/Controllers/Vendedor/SaleController';
import ServiceOrderController from '@/actions/App/Http/Controllers/Vendedor/ServiceOrderController';
import ChispaAvatar from '@/components/chispa-avatar';
import alertas from '@/routes/vendedor/alertas';
import clientes from '@/routes/vendedor/clientes';
import facturacion from '@/routes/vendedor/facturacion';
import VendedorLayout from '@/layouts/vendedor-layout';
import { fechaCorta, fechaLocal, iniciales, soles } from '@/lib/utils';
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
    graficos: {
        ventas: Punto[];
        cotizaciones: Punto[];
        cobranza: Punto[];
        acumulado: {
            nombre: string;
            valor: number | null;
            secundario: number;
        }[];
    };
    ventas_hoy: VentasHoy;
    caja_hoy: CajaHoy;
    cotizaciones_mes: Record<string, number>;
    cobros_pendientes: CobroPendiente[];
    alertas_top: AlertaItem[];
    agenda_hoy: AgendaItem[];
    pendientes: Pendientes;
    por_renovar: number;
    oportunidades: Oportunidad[];
};

export default function VendedorDashboard({
    graficos,
    ventas_hoy,
    por_renovar,
    caja_hoy,
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
    const cuotasVencidas = cobros_pendientes.filter(
        (cuota) => cuota.dias_vencido > 0,
    ).length;
    const pagos = caja_hoy?.por_forma_pago;
    const haceUnAno = new Date();
    haceUnAno.setFullYear(haceUnAno.getFullYear() - 1);
    const avisos: Aviso[] = [
        {
            cantidad: pendientes.rechazados,
            texto: `${cantidad(pendientes.rechazados, 'comprobante rechazado', 'comprobantes rechazados')} por SUNAT`,
            accion: 'Corregir',
            href: facturacion.index.url(teamSlug, {
                query: { estado: 'rechazado' },
            }),
            alerta: true,
        },
        {
            cantidad: pendientes.cuotas_vencidas,
            texto: `${cantidad(pendientes.cuotas_vencidas, 'cuota vencida', 'cuotas vencidas')} de tus clientes`,
            accion: 'Cobrar',
            href: CollectionController.index.url(teamSlug),
            alerta: true,
        },
        {
            cantidad: pendientes.por_enviar,
            texto: `${cantidad(pendientes.por_enviar, 'comprobante', 'comprobantes')} por enviar a SUNAT`,
            accion: 'Revisar',
            href: facturacion.index.url(teamSlug, {
                query: { estado: 'por_enviar' },
            }),
        },
        {
            cantidad: pendientes.cotizaciones_aceptadas,
            texto: `${cantidad(pendientes.cotizaciones_aceptadas, 'cotización aceptada', 'cotizaciones aceptadas')} por vender`,
            accion: 'Vender',
            href: QuoteController.index.url(teamSlug, {
                query: { estado: 'aceptada' },
            }),
        },
        {
            cantidad: pendientes.borradores,
            texto: `${cantidad(pendientes.borradores, 'venta', 'ventas')} en borrador sin emitir`,
            accion: 'Emitir',
            href: SaleController.index.url(teamSlug, {
                query: {
                    estado: 'borrador',
                    desde: fechaLocal(haceUnAno),
                },
            }),
        },
    ]
        .filter((aviso) => aviso.cantidad > 0)
        .map(({ cantidad: _cantidad, ...aviso }) => aviso);

    const ventasMes = graficos.ventas[1]?.valor ?? 0;
    const diaDeHoy = graficos.acumulado.filter((d) => d.valor !== null).length;
    const mismaFechaMesPasado =
        graficos.acumulado[diaDeHoy - 1]?.secundario ?? 0;
    const diferencia = ventasMes - mismaFechaMesPasado;
    const [porVencer, vencida] = graficos.cobranza.map((p) => p.valor);

    const indicadores = [
        {
            etiqueta: 'Vendido hoy',
            valor: soles(ventas_hoy.total),
            detalle: cantidad(
                ventas_hoy.count,
                'venta emitida',
                'ventas emitidas',
            ),
            href: SaleController.index.url(teamSlug),
        },
        {
            etiqueta: 'Por cobrar',
            valor: soles(porVencer + vencida),
            detalle: `${soles(vencida)} vencido`,
            href: CollectionController.index.url(teamSlug),
        },
        {
            etiqueta: 'SUNAT por revisar',
            valor: String(pendientes.rechazados + pendientes.por_enviar),
            detalle: `${cantidad(pendientes.rechazados, 'rechazado', 'rechazados')} y ${pendientes.por_enviar} por enviar`,
            href: facturacion.index.url(teamSlug),
        },
        {
            etiqueta: 'Por renovar',
            valor: String(por_renovar),
            detalle: 'clientes con extintores por vencer',
            href: alertas.index.url(teamSlug),
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
                        <h1 id="saludo-vendedor" className={pageTitleClass}>
                            Hola, {primerNombre}
                        </h1>
                        <p className="text-muted-foreground mt-2 max-w-[65ch] text-[13px] leading-relaxed">
                            {hoy}. Tienes{' '}
                            <strong className="text-foreground">
                                {cantidad(
                                    agenda_hoy.length,
                                    'servicio',
                                    'servicios',
                                )}
                            </strong>{' '}
                            en agenda y{' '}
                            <strong className="text-foreground">
                                {cantidad(
                                    cobros_pendientes.length,
                                    'cuota',
                                    'cuotas',
                                )}
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
                    <div className="relative mt-5">
                        <Atajos
                            atajos={[
                                {
                                    texto: 'Nueva venta',
                                    href: SaleController.create.url(teamSlug),
                                    icono: Plus,
                                    principal: true,
                                },
                                {
                                    texto: 'Nueva cotización',
                                    href: QuoteController.create.url(teamSlug),
                                    icono: ClipboardList,
                                },
                                {
                                    texto: 'Orden de servicio',
                                    href: ServiceOrderController.index.url(
                                        teamSlug,
                                    ),
                                    icono: Wrench,
                                },
                                {
                                    texto: 'Cobrar una cuota',
                                    href: CollectionController.index.url(
                                        teamSlug,
                                    ),
                                    icono: CreditCard,
                                },
                                {
                                    texto: 'Clientes',
                                    href: clientes.index.url(teamSlug),
                                    icono: UserPlus,
                                },
                            ]}
                        />
                    </div>
                </section>

                <section
                    aria-label="Resumen de hoy"
                    className="grid gap-3.5 sm:grid-cols-2 xl:grid-cols-4"
                >
                    {indicadores.map(({ href, ...indicador }) => (
                        <Link
                            key={indicador.etiqueta}
                            href={href}
                            className="focus-visible:ring-ring hover:[&>div]:border-primary/40 rounded-2xl focus-visible:ring-2 focus-visible:outline-none"
                        >
                            <Indicador {...indicador} />
                        </Link>
                    ))}
                </section>

                <Atencion
                    avisos={avisos}
                    vacio="Todo al día: nada por enviar, cobrar ni pasar a venta."
                />

                <div className="grid items-start gap-5 xl:grid-cols-2">
                    <section
                        className="bf-data-panel"
                        aria-labelledby="vencimientos-heading"
                    >
                        <div className="flex items-center justify-between gap-3">
                            <h2 id="vencimientos-heading">A quién llamar</h2>
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
                                    Ningún cliente vence en los próximos 3 meses
                                </p>
                                <p>
                                    Revisa el parque completo de tus clientes en
                                    Por vencer.
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
                        aria-labelledby="cobros-heading"
                    >
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <h2 id="cobros-heading">Cobros pendientes</h2>
                            <Link
                                href={CollectionController.index.url(teamSlug)}
                                className="text-primary-strong text-xs font-medium hover:underline"
                            >
                                Ver cobranzas
                            </Link>
                        </div>
                        {cuotasVencidas > 0 && (
                            <p className="text-destructive-strong bg-destructive/10 mt-4 rounded-lg p-3 text-xs">
                                {cuotasVencidas} cuotas vencidas necesitan tu
                                atención.
                            </p>
                        )}
                        <div className="divide-border mt-4 divide-y">
                            {cobros_pendientes.length === 0 ? (
                                <div className="bf-empty">
                                    <CheckCircle2 className="text-success-strong mx-auto mb-2 size-6" />
                                    <p className="text-foreground font-medium">
                                        Sin cuotas pendientes
                                    </p>
                                    <p>Consulta el historial en cobranzas.</p>
                                </div>
                            ) : (
                                cobros_pendientes.map((cuota) => {
                                    const phoneDigits = cuota.telefono?.replace(
                                        /\D/g,
                                        '',
                                    );
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
                                                {iniciales(cuota.cliente)}
                                            </span>
                                            <div className="min-w-0 flex-1">
                                                <p className="text-[13px] font-semibold">
                                                    {cuota.cliente ||
                                                        'Cliente sin nombre'}
                                                </p>
                                                <p className="text-muted-foreground mt-1 text-xs leading-relaxed">
                                                    Cuota {cuota.numero_cuota}
                                                    {cuota.sale_numero
                                                        ? ` · ${cuota.sale_numero}`
                                                        : ''}{' '}
                                                    ·{' '}
                                                    {cuota.dias_vencido > 0 ? (
                                                        <span className="text-destructive-strong">
                                                            Vencida hace{' '}
                                                            {cuota.dias_vencido}{' '}
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
                                            {cuota.dias_vencido > 0 && phone ? (
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
                </div>

                <div className="grid gap-3.5 lg:grid-cols-3">
                    <Tarjeta
                        titulo="Mis ventas del mes"
                        className="lg:col-span-2"
                        extra="día a día contra el mes pasado"
                    >
                        <div className="flex flex-wrap items-end justify-between gap-2">
                            <div>
                                <b className="text-[30px] leading-tight font-bold tracking-tight tabular-nums">
                                    {soles(ventasMes)}
                                </b>
                                <p
                                    className={`text-sm font-semibold ${diferencia >= 0 ? 'text-success-strong' : 'text-destructive-strong'}`}
                                >
                                    {diferencia >= 0 ? '▲' : '▼'}{' '}
                                    {formatoBi(Math.abs(diferencia), true)}{' '}
                                    <span className="text-muted-foreground font-normal">
                                        {diferencia >= 0 ? 'más' : 'menos'} que
                                        el mes pasado a la misma fecha
                                    </span>
                                </p>
                            </div>
                        </div>
                        <Tendencia
                            titulo="Mis ventas acumuladas por día"
                            datos={graficos.acumulado}
                            series={['Este mes', 'Mes pasado']}
                            moneda
                            alto={210}
                        />
                    </Tarjeta>
                    <Tarjeta titulo="Mis cotizaciones del mes">
                        {graficos.cotizaciones.some((p) => p.valor > 0) ? (
                            <Ranking
                                datos={graficos.cotizaciones}
                                colorDe={(p) =>
                                    p.nombre === 'Perdidas'
                                        ? 'bi-neutro'
                                        : 'bi-1'
                                }
                            />
                        ) : (
                            <Vacio>Aún no hay cotizaciones este mes.</Vacio>
                        )}
                        <Link
                            href={QuoteController.index.url(teamSlug)}
                            className="text-primary-strong text-sm font-semibold hover:underline"
                        >
                            Ver cotizaciones ›
                        </Link>
                    </Tarjeta>
                </div>

                <div className="grid items-start gap-5 xl:grid-cols-2">
                    <Tarjeta
                        titulo="Mi cartera por cobrar"
                        extra="todas tus cuotas"
                    >
                        {porVencer + vencida > 0 ? (
                            <>
                                <b className="text-2xl tabular-nums">
                                    {soles(porVencer + vencida)}
                                </b>
                                <Apilada
                                    moneda
                                    partes={[
                                        {
                                            nombre: 'Por vencer',
                                            valor: porVencer,
                                            color: 'bi-1',
                                        },
                                        {
                                            nombre: 'Vencido',
                                            valor: vencida,
                                            color: 'bi-alerta',
                                        },
                                    ]}
                                />
                            </>
                        ) : (
                            <Vacio>No tienes cuotas por cobrar.</Vacio>
                        )}
                    </Tarjeta>
                    <section
                        className="bf-data-panel"
                        aria-labelledby="caja-heading"
                    >
                        <div className="flex items-center justify-between gap-3">
                            <h2 id="caja-heading">Tu turno de caja</h2>
                            <Link
                                href={CashRegisterController.show.url(teamSlug)}
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
                                        ['Transferencia', pagos.transferencia],
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
                                    Abre tu caja para registrar los cobros del
                                    turno.
                                </p>
                            </div>
                        )}
                    </section>
                </div>

                <div className="grid items-start gap-5 xl:grid-cols-2">
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
                                    Consulta las órdenes activas en Servicios.
                                </p>
                            </div>
                        ) : (
                            <div className="mt-4 grid gap-3">
                                {agenda_hoy.map((orden) => (
                                    <Link
                                        key={orden.id}
                                        href={ServiceOrderController.show.url({
                                            current_team: teamSlug,
                                            service_order: orden.id,
                                        })}
                                        className="bg-muted hover:bg-accent rounded-xl p-4"
                                    >
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <span className="text-muted-foreground text-xs">
                                                {orden.codigo}
                                            </span>
                                            <span className="text-xs font-medium">
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
                                        <p className="text-muted-foreground mt-2 text-xs">
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
                                    No hay servicios que requieran atención por
                                    ahora.
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
                                                <span className="text-warning-strong ml-2 text-xs">
                                                    Urgente
                                                </span>
                                            )}
                                        </p>
                                        <p className="text-muted-foreground mt-1 text-xs leading-relaxed">
                                            {alerta.mensaje}
                                        </p>
                                        <p className="text-muted-foreground mt-1 text-xs">
                                            {alerta.fecha}
                                        </p>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </section>
                </div>
            </div>
        </VendedorLayout>
    );
}
