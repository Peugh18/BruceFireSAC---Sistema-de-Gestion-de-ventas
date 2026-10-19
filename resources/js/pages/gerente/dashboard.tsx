import GraficosDashboard, {
    type DatosGraficos,
} from '@/components/graficos-dashboard';
import { Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowDownRight,
    ArrowUpRight,
    FileDown,
    Minus,
} from 'lucide-react';
import type { ReactNode } from 'react';

import SaludDelSistema, {
    type SaludDelSistemaData,
} from '@/components/salud-del-sistema';
import GerenteLayout from '@/layouts/gerente-layout';
import { soles } from '@/lib/utils';
import CobranzasRoutes from '@/routes/gerente/cobranzas';
import { dashboard, inteligencia } from '@/routes/gerente';
import { detalle as detalleRuta } from '@/routes/gerente/dashboard';
import ProductosRoutes from '@/routes/gerente/productos';
import { cliente as consultaCliente } from '@/routes/gerente/consulta';
import ReportesRoutes from '@/routes/gerente/reportes';

type Metrica = {
    clave: string;
    etiqueta: string;
    valor: number | null;
    anterior: number | null;
    variacion: number | null;
    formato: 'moneda' | 'porcentaje' | 'dias';
    detalle: string;
    disponible: boolean;
    productosSinCosto?: number;
};

type Props = {
    graficos: DatosGraficos;
    mes: string;
    periodo: string;
    metricas: Metrica[];
    metrics: {
        vencidoPorCobrar: number;
        cotizacionesPendientes: number;
        ordenesEnProceso: number;
        stockCritico: number;
        documentosSunatError: number;
        plazoSunat: { por_vencer: number; vencidos: number };
    };
    sedes: { id: number; nombre: string }[];
    sedeSeleccionada: number | null;
    mostrarComparacionSedes: boolean;
    comparacionSedes: {
        id: number;
        nombre: string;
        ventasNetas: number;
        ticketPromedio: number | null;
        conversionCotizaciones: number | null;
        cobrado: number;
    }[];
    clientesConDeuda: {
        cliente_id: number;
        cliente: string;
        saldo: number;
        fecha_mas_antigua: string;
    }[];
    ordenesPorPaso: { paso: string; cantidad: number }[];
    recompra: {
        dias: number;
        cantidad: number;
        clientes: number;
        monto_estimado: number;
    }[];
    ventasPorVendedor: { vendedor: string; monto: number }[];
    serviciosMasVendidos: {
        servicio: string;
        cantidad: number;
        monto: number;
    }[];
    sistema: SaludDelSistemaData;
    kpisProyecto: {
        venta_minutos: number | null;
        ventas_medidas: number;
        cotizacion_minutos: number | null;
        cotizaciones_medidas: number;
        clientes_recuperados: number;
    };
    iaRecompra: {
        alta: number;
        media: number;
        baja: number;
        calculado: string | null;
        clientes: {
            cliente_id: number;
            cliente: string;
            porcentaje: number;
            categoria: string;
            resumen: string;
        }[];
    };
};

/** Para estas métricas, bajar es bueno (menos días). */
const MENOS_ES_MEJOR = ['diasPromedioCobro', 'diasPromedioServicio'];

const PASOS: Record<string, string> = {
    recibir: 'Por recibir',
    revisar: 'En revisión',
    hallazgos: 'Esperando autorización',
    reparar: 'En reparación',
    cierre: 'Por cerrar',
};

function formatear(valor: number | null, formato: Metrica['formato']): string {
    if (valor === null) {
        return 'Sin datos';
    }

    if (formato === 'moneda') {
        return soles(valor);
    }

    return formato === 'porcentaje' ? `${valor} %` : `${valor} días`;
}

function Variacion({ metrica }: { metrica: Metrica }) {
    if (metrica.variacion === null) {
        return (
            <span className="text-muted-foreground text-xs">
                Sin mes anterior para comparar
            </span>
        );
    }

    const sube = metrica.variacion > 0;
    const bueno = MENOS_ES_MEJOR.includes(metrica.clave) ? !sube : sube;
    const Icono =
        metrica.variacion === 0 ? Minus : sube ? ArrowUpRight : ArrowDownRight;

    return (
        <span
            className={`inline-flex items-center gap-0.5 text-xs font-semibold ${
                metrica.variacion === 0
                    ? 'text-muted-foreground'
                    : bueno
                      ? 'text-success-strong'
                      : 'text-destructive-strong'
            }`}
        >
            <Icono className="size-3.5" aria-hidden="true" />
            {Math.abs(metrica.variacion)} % vs. mes anterior
        </span>
    );
}

function Tarjeta({
    titulo,
    children,
}: {
    titulo: string;
    children: ReactNode;
}) {
    return (
        <section className="border-border bg-card rounded-2xl border p-5">
            <h2 className="text-foreground text-base font-semibold">
                {titulo}
            </h2>
            <div className="mt-3">{children}</div>
        </section>
    );
}

/**
 * Resumen del mes para el gerente: métricas comparadas con el mes anterior,
 * lo que requiere atención y el reporte en PDF. Con 2 o más sedes de venta
 * aparece solo la comparación entre sedes.
 */
export default function GerenteDashboard({
    graficos,
    mes,
    periodo,
    metricas,
    metrics,
    sedes,
    sedeSeleccionada,
    mostrarComparacionSedes,
    comparacionSedes,
    clientesConDeuda,
    ordenesPorPaso,
    recompra,
    ventasPorVendedor,
    serviciosMasVendidos,
    sistema,
    iaRecompra,
    kpisProyecto,
}: Props) {
    const { currentTeam } = usePage<{ currentTeam?: { slug: string } | null }>()
        .props;
    const equipo = currentTeam?.slug ?? '';
    const filtros = {
        mes,
        ...(sedeSeleccionada ? { sede: sedeSeleccionada } : {}),
    };
    const cambiar = (cambios: Record<string, string | number | null>) =>
        router.get(
            dashboard.url(equipo),
            { ...filtros, ...cambios },
            { preserveScroll: true, preserveState: true },
        );
    const detalle = (
        tipo: string,
        extra: Record<string, string | number> = {},
    ) => detalleRuta.url(equipo, { query: { tipo, ...filtros, ...extra } });
    const pdf = ReportesRoutes.comercial.pdf.url(equipo, { query: filtros });
    const atencion = [
        {
            texto: 'Productos bajo el mínimo',
            valor: metrics.stockCritico,
            href: ProductosRoutes.index.url(equipo, {
                query: { bajo_minimo: 1 },
            }),
        },
        {
            texto: 'Comprobantes con error en SUNAT',
            valor: metrics.documentosSunatError,
            href: detalle('sunat'),
        },
        {
            texto: 'Comprobantes vencidos en SUNAT',
            valor: metrics.plazoSunat.vencidos,
            href: detalle('sunat_vencidos'),
        },
        {
            texto: 'Cotizaciones sin respuesta hace más de 7 días',
            valor: metrics.cotizacionesPendientes,
            href: detalle('cotizaciones'),
        },
    ].filter((item) => item.valor > 0);
    const hayAlertasSistema =
        !sistema.programadorActivo ||
        (sistema.respaldo !== null && !sistema.respaldo.ok) ||
        sistema.problemas.length > 0;
    const maxVendedor = Math.max(...ventasPorVendedor.map((v) => v.monto), 1);

    return (
        <GerenteLayout title="Resumen de la empresa">
            <div className="space-y-6">
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-foreground text-2xl font-bold tracking-tight sm:text-3xl">
                            Resumen de {periodo}
                        </h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Cada número se compara con el mes anterior.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-end gap-2">
                        <label className="text-muted-foreground text-xs font-medium">
                            Mes
                            <input
                                type="month"
                                value={mes}
                                max={new Date().toISOString().slice(0, 7)}
                                onChange={(e) =>
                                    e.target.value &&
                                    cambiar({ mes: e.target.value })
                                }
                                className="border-border bg-card text-foreground mt-1 block h-10 rounded-xl border px-3 text-sm"
                            />
                        </label>
                        {mostrarComparacionSedes && (
                            <label className="text-muted-foreground text-xs font-medium">
                                Sede
                                <select
                                    value={sedeSeleccionada ?? ''}
                                    onChange={(e) =>
                                        cambiar({
                                            sede: e.target.value || null,
                                        })
                                    }
                                    className="border-border bg-card text-foreground mt-1 block h-10 rounded-xl border px-3 text-sm"
                                >
                                    <option value="">Toda la empresa</option>
                                    {sedes.map((sede) => (
                                        <option key={sede.id} value={sede.id}>
                                            {sede.nombre}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        )}
                        <a
                            href={pdf}
                            className="bg-primary text-primary-foreground focus-visible:ring-ring inline-flex h-10 items-center gap-2 rounded-xl px-4 text-sm font-semibold focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                        >
                            <FileDown className="size-4" aria-hidden="true" />
                            Descargar reporte del mes (PDF)
                        </a>
                    </div>
                </header>
                <GraficosDashboard rol="gerente" datos={graficos} />

                {hayAlertasSistema && <SaludDelSistema salud={sistema} />}

                <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    {metricas
                        .filter((metrica) => metrica.clave !== 'ticketPromedio')
                        .map((metrica) => (
                            <li
                                key={metrica.clave}
                                className="border-border bg-card rounded-2xl border p-4"
                            >
                                <p className="text-muted-foreground text-sm">
                                    {metrica.etiqueta}
                                </p>
                                <p className="text-foreground mt-1 text-2xl font-bold tabular-nums">
                                    {formatear(metrica.valor, metrica.formato)}
                                </p>
                                <div className="mt-1">
                                    <Variacion metrica={metrica} />
                                </div>
                                {metrica.detalle && (
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        {metrica.detalle}
                                    </p>
                                )}
                            </li>
                        ))}
                </ul>

                {atencion.length > 0 && (
                    <section
                        aria-labelledby="atencion-titulo"
                        className="rounded-2xl border border-amber-500/40 bg-amber-500/10 p-4"
                    >
                        <h2
                            id="atencion-titulo"
                            className="text-warning-strong flex items-center gap-2 text-sm font-bold"
                        >
                            <AlertTriangle
                                className="size-4"
                                aria-hidden="true"
                            />
                            Requiere atención
                        </h2>
                        <ul className="mt-2 grid gap-2 sm:grid-cols-2">
                            {atencion.map((item) => (
                                <li key={item.texto}>
                                    <Link
                                        href={item.href}
                                        className="bg-card text-foreground hover:bg-accent flex items-center justify-between gap-3 rounded-xl px-3 py-2 text-sm"
                                    >
                                        <span>{item.texto}</span>
                                        <span className="font-bold tabular-nums">
                                            {item.valor}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                <Tarjeta titulo="IA de recompra (toda la empresa): a quién llamar primero">
                    <Link
                        className="text-primary mb-3 inline-flex rounded py-2 text-sm font-medium underline underline-offset-4"
                        href={inteligencia.url({ current_team: equipo })}
                    >
                        Abrir Recompra e IA
                    </Link>
                    <p className="text-muted-foreground mb-3 text-xs">
                        Probabilidad de que el cliente vuelva a comprar, según
                        su historial. Alta: {iaRecompra.alta} · Media:{' '}
                        {iaRecompra.media} · Baja: {iaRecompra.baja}
                        {iaRecompra.calculado
                            ? ` · Calculado el ${iaRecompra.calculado.slice(0, 10)}`
                            : ''}
                    </p>
                    {iaRecompra.clientes.length === 0 ? (
                        <p className="text-muted-foreground text-sm">
                            La IA todavía no tiene clientes con probabilidad
                            alta o media.
                        </p>
                    ) : (
                        <ul className="divide-border divide-y text-sm">
                            {iaRecompra.clientes.map((c) => (
                                <li key={c.cliente_id}>
                                    <Link
                                        href={consultaCliente.url({
                                            current_team: equipo,
                                            client: c.cliente_id,
                                        })}
                                        className="hover:bg-accent flex items-start justify-between gap-3 rounded-lg px-1 py-2"
                                    >
                                        <span className="min-w-0">
                                            <span className="text-foreground block font-medium">
                                                {c.cliente}
                                            </span>
                                            <span className="text-muted-foreground block text-xs">
                                                {c.resumen}
                                            </span>
                                        </span>
                                        <span
                                            className={
                                                c.categoria === 'alta'
                                                    ? 'text-success-strong shrink-0 rounded-full bg-emerald-500/15 px-2 py-0.5 text-xs font-bold'
                                                    : 'text-warning-strong shrink-0 rounded-full bg-amber-500/15 px-2 py-0.5 text-xs font-bold'
                                            }
                                        >
                                            {c.porcentaje} %
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </Tarjeta>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Tarjeta titulo="Recompra: extintores que vencen pronto">
                        <ul className="grid grid-cols-3 gap-2">
                            {recompra.map((tramo) => (
                                <li key={tramo.dias}>
                                    <Link
                                        href={detalle('recompra', {
                                            tramo: tramo.dias,
                                        })}
                                        className="bg-muted/50 hover:bg-accent block rounded-xl p-3"
                                    >
                                        <span className="text-muted-foreground block text-xs">
                                            En {tramo.dias} días
                                        </span>
                                        <span className="text-foreground block text-xl font-bold tabular-nums">
                                            {tramo.cantidad}
                                        </span>
                                        <span className="text-muted-foreground block text-xs">
                                            {tramo.clientes} clientes ·{' '}
                                            {soles(tramo.monto_estimado)}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </Tarjeta>

                    <Tarjeta titulo="Clientes que más deben">
                        <p className="text-muted-foreground mb-2 text-xs">
                            Vencido por cobrar:{' '}
                            {soles(metrics.vencidoPorCobrar)}
                        </p>
                        {clientesConDeuda.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                Nadie tiene deuda vencida.
                            </p>
                        ) : (
                            <ul className="divide-border divide-y text-sm">
                                {clientesConDeuda.slice(0, 5).map((c) => (
                                    <li key={c.cliente_id}>
                                        <Link
                                            href={CobranzasRoutes.index.url(
                                                equipo,
                                            )}
                                            className="hover:bg-accent flex justify-between gap-3 rounded-lg px-1 py-2"
                                        >
                                            <span className="min-w-0 truncate">
                                                {c.cliente}
                                                <span className="text-muted-foreground block text-xs">
                                                    Debe desde{' '}
                                                    {c.fecha_mas_antigua}
                                                </span>
                                            </span>
                                            <span className="text-foreground shrink-0 font-semibold tabular-nums">
                                                {soles(c.saldo)}
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Tarjeta>

                    <Tarjeta titulo="Ventas por vendedor">
                        {ventasPorVendedor.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                Sin ventas este mes.
                            </p>
                        ) : (
                            <ul className="space-y-2 text-sm">
                                {ventasPorVendedor.map((v) => (
                                    <li key={v.vendedor}>
                                        <div className="flex justify-between gap-3">
                                            <span>{v.vendedor}</span>
                                            <span className="font-semibold tabular-nums">
                                                {soles(v.monto)}
                                            </span>
                                        </div>
                                        <div
                                            className="bg-muted mt-1 h-2 rounded-full"
                                            aria-hidden="true"
                                        >
                                            <div
                                                className="bg-primary h-2 rounded-full"
                                                style={{
                                                    width: `${(v.monto / maxVendedor) * 100}%`,
                                                }}
                                            />
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Tarjeta>

                    <Tarjeta titulo="Servicios más vendidos">
                        {serviciosMasVendidos.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                Sin servicios vendidos este mes.
                            </p>
                        ) : (
                            <ul className="divide-border divide-y text-sm">
                                {serviciosMasVendidos.slice(0, 6).map((s) => (
                                    <li
                                        key={s.servicio}
                                        className="flex justify-between gap-3 py-2"
                                    >
                                        <span className="min-w-0 truncate">
                                            {s.servicio}
                                        </span>
                                        <span className="text-muted-foreground shrink-0 tabular-nums">
                                            {s.cantidad} · {soles(s.monto)}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Tarjeta>

                    <Tarjeta titulo="Tiempos de atención">
                        <ul className="grid grid-cols-3 gap-2 text-sm">
                            <li className="bg-muted/50 rounded-xl p-3">
                                <span className="text-muted-foreground block text-xs">
                                    Registrar una venta
                                </span>
                                <span className="text-foreground text-lg font-bold tabular-nums">
                                    {kpisProyecto.venta_minutos === null
                                        ? 'Sin datos'
                                        : `${kpisProyecto.venta_minutos} min`}
                                </span>
                                <span className="text-muted-foreground block text-xs">
                                    {kpisProyecto.ventas_medidas} ventas medidas
                                </span>
                            </li>
                            <li className="bg-muted/50 rounded-xl p-3">
                                <span className="text-muted-foreground block text-xs">
                                    Emitir una cotización
                                </span>
                                <span className="text-foreground text-lg font-bold tabular-nums">
                                    {kpisProyecto.cotizacion_minutos === null
                                        ? 'Sin datos'
                                        : `${kpisProyecto.cotizacion_minutos} min`}
                                </span>
                                <span className="text-muted-foreground block text-xs">
                                    {kpisProyecto.cotizaciones_medidas}{' '}
                                    cotizaciones
                                </span>
                            </li>
                            <li className="bg-muted/50 rounded-xl p-3">
                                <span className="text-muted-foreground block text-xs">
                                    Clientes recuperados
                                </span>
                                <span className="text-foreground text-lg font-bold tabular-nums">
                                    {kpisProyecto.clientes_recuperados}
                                </span>
                                <span className="text-muted-foreground block text-xs">
                                    por alertas de vencimiento
                                </span>
                            </li>
                        </ul>
                    </Tarjeta>

                    <Tarjeta titulo="Órdenes de servicio en curso">
                        <p className="text-muted-foreground mb-2 text-xs">
                            {metrics.ordenesEnProceso} órdenes abiertas
                        </p>
                        <ul className="grid grid-cols-2 gap-2 text-sm sm:grid-cols-3">
                            {ordenesPorPaso.map((p) => (
                                <li key={p.paso}>
                                    <Link
                                        href={detalle('ordenes', {
                                            paso: p.paso,
                                        })}
                                        className="bg-muted/50 hover:bg-accent block rounded-xl p-3"
                                    >
                                        <span className="text-muted-foreground block text-xs">
                                            {PASOS[p.paso] ?? p.paso}
                                        </span>
                                        <span className="text-foreground text-lg font-bold tabular-nums">
                                            {p.cantidad}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </Tarjeta>
                </div>

                {mostrarComparacionSedes && (
                    <Tarjeta titulo="Comparación entre sedes">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="text-muted-foreground text-left text-xs">
                                    <tr>
                                        <th className="py-2 pr-3 font-medium">
                                            Sede
                                        </th>
                                        <th className="py-2 pr-3 text-right font-medium">
                                            Ventas
                                        </th>
                                        <th className="py-2 pr-3 text-right font-medium">
                                            Ticket
                                        </th>
                                        <th className="py-2 pr-3 text-right font-medium">
                                            Conversión
                                        </th>
                                        <th className="py-2 text-right font-medium">
                                            Cobrado
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-border divide-y">
                                    {comparacionSedes.map((s) => (
                                        <tr key={s.id}>
                                            <td className="py-2 pr-3">
                                                {s.nombre}
                                            </td>
                                            <td className="py-2 pr-3 text-right tabular-nums">
                                                {soles(s.ventasNetas)}
                                            </td>
                                            <td className="py-2 pr-3 text-right tabular-nums">
                                                {formatear(
                                                    s.ticketPromedio,
                                                    'moneda',
                                                )}
                                            </td>
                                            <td className="py-2 pr-3 text-right tabular-nums">
                                                {formatear(
                                                    s.conversionCotizaciones,
                                                    'porcentaje',
                                                )}
                                            </td>
                                            <td className="py-2 text-right tabular-nums">
                                                {soles(s.cobrado)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Tarjeta>
                )}
            </div>
        </GerenteLayout>
    );
}
