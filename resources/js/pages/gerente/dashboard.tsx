import { pageTitleClass } from '@/components/page-header';
import { router, usePage } from '@inertiajs/react';
import { Boxes, FileDown, HandCoins, Landmark, Wrench } from 'lucide-react';

import {
    Anillo,
    Apilada,
    Atajos,
    Atencion,
    cantidad,
    cambioPorcentual,
    Columnas,
    Indicador,
    Ranking,
    Tarjeta,
    Tendencia,
    Vacio,
    type Aviso,
    type Punto,
} from '@/components/bi';
import SaludDelSistema, {
    type SaludDelSistemaData,
} from '@/components/salud-del-sistema';
import GerenteLayout from '@/layouts/gerente-layout';
import { soles } from '@/lib/utils';
import { dashboard } from '@/routes/gerente';
import CajasRoutes from '@/routes/gerente/cajas';
import CobranzasRoutes from '@/routes/gerente/cobranzas';
import { detalle as detalleRuta } from '@/routes/gerente/dashboard';
import ProductosRoutes from '@/routes/gerente/productos';
import ReportesRoutes from '@/routes/gerente/reportes';
import ServiciosRoutes from '@/routes/gerente/servicios';

type Metrica = {
    clave: string;
    etiqueta: string;
    valor: number | null;
    variacion: number | null;
    formato: 'moneda' | 'porcentaje' | 'dias';
    detalle: string;
    disponible: boolean;
};

type Props = {
    graficos: {
        ventas: Punto[];
        conversion: Punto[];
        cobranza: Punto[];
        servicios: Punto[];
        pasos: Punto[];
        tendencias: {
            cobrado: number[];
            margenBruto: number[];
            conversionCotizaciones: number[];
        };
    };
    mes: string;
    mesActual: string;
    periodo: string;
    sedeNombre: string;
    metricas: Metrica[];
    metrics: {
        vencidoPorCobrar: number;
        cotizacionesPendientes: number;
        stockCritico: number;
        documentosSunatError: number;
        plazoSunat: { por_vencer: number; vencidos: number };
    };
    sedes: { id: number; nombre: string }[];
    sedeSeleccionada: number | null;
    mostrarComparacionSedes: boolean;
    comparacionSedes: { id: number; nombre: string; ventasNetas: number }[];
    sistema: SaludDelSistemaData;
    kpisProyecto: { venta_minutos: number | null };
};

const MESES = [
    'Ene',
    'Feb',
    'Mar',
    'Abr',
    'May',
    'Jun',
    'Jul',
    'Ago',
    'Set',
    'Oct',
    'Nov',
    'Dic',
];

/** "10/2026" → "Oct". */
function mesCorto(nombre: string): string {
    return MESES[Number(nombre.split('/')[0]) - 1] ?? nombre;
}

function formatear(metrica: Metrica | undefined): string {
    if (!metrica || metrica.valor === null) {
        return 'Sin datos';
    }
    if (metrica.formato === 'moneda') {
        return soles(metrica.valor);
    }

    return metrica.formato === 'porcentaje'
        ? `${metrica.valor} %`
        : `${metrica.valor}`;
}

/**
 * BI del gerente: lo que requiere atención, 4 indicadores con su tendencia y
 * los gráficos del mes. La recompra y la IA viven en su propio módulo.
 */
export default function GerenteDashboard({
    graficos,
    mes,
    mesActual,
    periodo,
    sedeNombre,
    metricas,
    metrics,
    sedes,
    sedeSeleccionada,
    mostrarComparacionSedes,
    comparacionSedes,
    sistema,
    kpisProyecto,
}: Props) {
    const { currentTeam } = usePage<{ currentTeam?: { slug: string } | null }>()
        .props;
    const equipo = currentTeam?.slug ?? '';
    const filtros = {
        mes,
        mesActual,
        ...(sedeSeleccionada ? { sede: sedeSeleccionada } : {}),
    };
    const cambiar = (cambios: Record<string, string | number | null>) =>
        router.get(
            dashboard.url(equipo),
            { ...filtros, ...cambios },
            { preserveScroll: true, preserveState: true },
        );
    const detalle = (tipo: string) =>
        detalleRuta.url(equipo, { query: { tipo, ...filtros } });
    const metrica = (clave: string) => metricas.find((m) => m.clave === clave);

    const avisos: Aviso[] = [
        {
            valor: metrics.plazoSunat.vencidos,
            texto: `${cantidad(metrics.plazoSunat.vencidos, 'comprobante vencido', 'comprobantes vencidos')} en SUNAT`,
            accion: 'Revisar',
            href: detalle('sunat_vencidos'),
            alerta: true,
        },
        {
            valor: metrics.documentosSunatError,
            texto: `${cantidad(metrics.documentosSunatError, 'comprobante', 'comprobantes')} con error en SUNAT`,
            accion: 'Corregir',
            href: detalle('sunat'),
            alerta: true,
        },
        {
            valor: metrics.vencidoPorCobrar,
            texto: `${soles(metrics.vencidoPorCobrar)} de deuda vencida`,
            accion: 'Cobranzas',
            href: CobranzasRoutes.index.url(equipo),
        },
        {
            valor: metrics.stockCritico,
            texto: `${cantidad(metrics.stockCritico, 'producto', 'productos')} bajo el mínimo`,
            accion: 'Stock',
            href: ProductosRoutes.index.url(equipo, {
                query: { bajo_minimo: 1 },
            }),
        },
        {
            valor: metrics.cotizacionesPendientes,
            texto: `${cantidad(metrics.cotizacionesPendientes, 'cotización', 'cotizaciones')} sin respuesta`,
            accion: 'Ver',
            href: detalle('cotizaciones'),
        },
    ]
        .filter((aviso) => aviso.valor > 0)
        .map(({ valor: _valor, ...aviso }) => aviso);

    const ventas = graficos.ventas.map((punto) => ({
        ...punto,
        nombre: mesCorto(punto.nombre),
    }));
    const [cobrado, pendiente, vencido] = graficos.cobranza.map(
        (punto) => punto.valor,
    );
    const totalCobranza = cobrado + pendiente + vencido;
    const servicios = [...graficos.servicios].sort((a, b) => b.valor - a.valor);
    const hayOrdenes = graficos.pasos.some(
        (paso) => paso.valor + (paso.secundario ?? 0) > 0,
    );
    const atrasadas = graficos.pasos.reduce(
        (suma, paso) => suma + (paso.secundario ?? 0),
        0,
    );
    const totalTiendas = comparacionSedes.reduce(
        (suma, sede) => suma + sede.ventasNetas,
        0,
    );
    const margen = metrica('margenBruto');
    const ventasNetas = metrica('ventasNetas');
    const indicadores = [
        {
            metrica: ventasNetas,
            serie: graficos.ventas.map((p) => p.valor),
        },
        {
            metrica: metrica('cobrado'),
            serie: graficos.tendencias.cobrado,
        },
        {
            metrica: margen,
            serie: graficos.tendencias.margenBruto,
            detalle:
                margen?.disponible && ventasNetas?.valor
                    ? `${Math.round(((margen.valor ?? 0) / ventasNetas.valor) * 100)} % de la venta`
                    : margen?.detalle,
        },
        {
            metrica: metrica('conversionCotizaciones'),
            serie: graficos.tendencias.conversionCotizaciones,
        },
    ];
    const tiempos = [
        {
            etiqueta: 'Días de cobro',
            valor: metrica('diasPromedioCobro')?.valor,
        },
        {
            etiqueta: 'Días por orden',
            valor: metrica('diasPromedioServicio')?.valor,
        },
        {
            etiqueta: 'Renovación',
            valor: metrica('tasaRenovacion')?.valor,
            sufijo: ' %',
        },
        {
            etiqueta: 'Registrar venta',
            valor: kpisProyecto.venta_minutos,
            sufijo: ' min',
        },
    ];
    const hayAlertasSistema =
        !sistema.programadorActivo ||
        (sistema.respaldo !== null && !sistema.respaldo.ok) ||
        sistema.problemas.length > 0;

    return (
        <GerenteLayout title="Resumen de la empresa">
            <div className="flex flex-col gap-5">
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="text-muted-foreground font-mono text-xs tracking-widest uppercase">
                            Gerencia
                        </p>
                        <h1 className={`${pageTitleClass} mt-1 capitalize`}>
                            {periodo}
                        </h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {sedeNombre} · comparado con el mes anterior
                        </p>
                    </div>
                    <div className="flex flex-wrap items-end gap-2.5">
                        <label className="text-muted-foreground flex flex-col gap-1.5 text-xs font-medium">
                            Mes
                            <input
                                type="month"
                                value={mes}
                                max={mesActual}
                                onChange={(e) =>
                                    e.target.value &&
                                    cambiar({ mes: e.target.value })
                                }
                                className="border-border bg-card text-foreground h-11 rounded-xl border px-3 text-sm"
                            />
                        </label>
                        {mostrarComparacionSedes && (
                            <label className="text-muted-foreground flex flex-col gap-1.5 text-xs font-medium">
                                Tienda
                                <select
                                    value={sedeSeleccionada ?? ''}
                                    onChange={(e) =>
                                        cambiar({
                                            sede: e.target.value || null,
                                        })
                                    }
                                    className="border-border bg-card text-foreground h-11 rounded-xl border px-3 text-sm"
                                >
                                    <option value="">Todas las tiendas</option>
                                    {sedes.map((sede) => (
                                        <option key={sede.id} value={sede.id}>
                                            {sede.nombre}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        )}
                        <a
                            href={ReportesRoutes.comercial.pdf.url(equipo, {
                                query: filtros,
                            })}
                            className="bg-primary text-primary-foreground focus-visible:ring-ring inline-flex h-11 items-center gap-2 rounded-xl px-4 text-sm font-semibold focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                        >
                            <FileDown className="size-4" aria-hidden="true" />
                            Reporte del mes (PDF)
                        </a>
                    </div>
                </header>

                <Atajos
                    atajos={[
                        {
                            texto: 'Productos',
                            href: ProductosRoutes.index.url(equipo),
                            icono: Boxes,
                        },
                        {
                            texto: 'Servicios',
                            href: ServiciosRoutes.index.url(equipo),
                            icono: Wrench,
                        },
                        {
                            texto: 'Caja consolidada',
                            href: CajasRoutes.index.url(equipo),
                            icono: Landmark,
                        },
                        {
                            texto: 'Cobranzas',
                            href: CobranzasRoutes.index.url(equipo),
                            icono: HandCoins,
                        },
                    ]}
                />

                {hayAlertasSistema && <SaludDelSistema salud={sistema} />}

                <Atencion avisos={avisos} />

                <section
                    aria-label="Indicadores del mes"
                    className="grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-3.5"
                >
                    {indicadores.map(({ metrica: m, serie, detalle: texto }) =>
                        m ? (
                            <Indicador
                                key={m.clave}
                                etiqueta={m.etiqueta}
                                valor={formatear(m)}
                                serie={m.valor === null ? [] : serie}
                                cambio={cambioPorcentual(m.variacion)}
                                detalle={texto}
                            />
                        ) : null,
                    )}
                </section>

                <div className="grid gap-3.5 lg:grid-cols-3">
                    <Tarjeta
                        titulo="Ventas netas · últimos 6 meses"
                        className="lg:col-span-2"
                    >
                        {ventas.some((p) => p.valor !== 0) ? (
                            <Tendencia
                                titulo="Ventas netas por mes"
                                datos={ventas}
                                series={['Ventas netas']}
                                moneda
                            />
                        ) : (
                            <Vacio>Aún no hay ventas en estos 6 meses.</Vacio>
                        )}
                    </Tarjeta>
                    <Tarjeta titulo="Cobranza de lo vendido">
                        {totalCobranza > 0 ? (
                            <>
                                <div className="flex items-center gap-4">
                                    <Anillo
                                        porcentaje={
                                            (cobrado / totalCobranza) * 100
                                        }
                                        etiqueta="cobrado"
                                    />
                                    <div className="min-w-0 flex-1">
                                        <Apilada
                                            moneda
                                            partes={[
                                                {
                                                    nombre: 'Cobrado',
                                                    valor: cobrado,
                                                    color: 'bi-1',
                                                },
                                                {
                                                    nombre: 'A tiempo',
                                                    valor: pendiente,
                                                    color: 'bi-2',
                                                },
                                                {
                                                    nombre: 'Vencido',
                                                    valor: vencido,
                                                    color: 'bi-alerta',
                                                },
                                            ]}
                                        />
                                    </div>
                                </div>
                            </>
                        ) : (
                            <Vacio>Sin ventas este mes.</Vacio>
                        )}
                    </Tarjeta>
                </div>

                <div className="grid gap-3.5 md:grid-cols-2 xl:grid-cols-3">
                    <Tarjeta titulo="De cotización a venta">
                        {graficos.conversion[0]?.valor ? (
                            <Ranking datos={graficos.conversion} embudo />
                        ) : (
                            <Vacio>Sin cotizaciones enviadas este mes.</Vacio>
                        )}
                    </Tarjeta>
                    <Tarjeta titulo="Servicios vendidos por tipo">
                        {servicios.length > 0 ? (
                            <Ranking datos={servicios.slice(0, 6)} />
                        ) : (
                            <Vacio>Sin servicios vendidos este mes.</Vacio>
                        )}
                    </Tarjeta>
                    <Tarjeta
                        titulo="Órdenes por paso del taller"
                        extra={
                            hayOrdenes
                                ? cantidad(atrasadas, 'atrasada', 'atrasadas')
                                : undefined
                        }
                    >
                        {hayOrdenes ? (
                            <Columnas
                                titulo="Órdenes abiertas por paso"
                                datos={graficos.pasos}
                                series={['En plazo', 'Atrasadas']}
                            />
                        ) : (
                            <Vacio>No hay órdenes abiertas.</Vacio>
                        )}
                    </Tarjeta>
                </div>

                <div className="grid gap-3.5 lg:grid-cols-3">
                    {mostrarComparacionSedes && (
                        <Tarjeta
                            titulo="Ventas por tienda"
                            className="lg:col-span-2"
                        >
                            <div className="grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-3">
                                {comparacionSedes.map((sede, indice) => (
                                    <div
                                        key={sede.id}
                                        className="bg-muted/60 flex flex-col gap-2 rounded-xl p-3.5"
                                    >
                                        <span className="text-muted-foreground text-sm">
                                            {sede.nombre}
                                        </span>
                                        <b className="text-xl tabular-nums">
                                            {soles(sede.ventasNetas)}
                                        </b>
                                        <span
                                            aria-hidden="true"
                                            className="block h-2 rounded-full"
                                            style={{
                                                background: 'var(--bi-pista)',
                                            }}
                                        >
                                            <span
                                                className="block h-2 rounded-full"
                                                style={{
                                                    width: `${totalTiendas ? (sede.ventasNetas / totalTiendas) * 100 : 0}%`,
                                                    background: `var(--bi-${(indice % 4) + 1})`,
                                                }}
                                            />
                                        </span>
                                        <span className="text-muted-foreground text-xs">
                                            {totalTiendas
                                                ? `${Math.round((sede.ventasNetas / totalTiendas) * 100)} % del total`
                                                : 'Sin ventas'}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </Tarjeta>
                    )}
                    <Tarjeta titulo="Tiempos de atención">
                        <dl className="grid grid-cols-2 gap-2.5">
                            {tiempos.map((tiempo) => (
                                <div
                                    key={tiempo.etiqueta}
                                    className="bg-muted/60 rounded-xl p-3"
                                >
                                    <dt className="text-muted-foreground text-xs">
                                        {tiempo.etiqueta}
                                    </dt>
                                    <dd className="text-xl font-bold tabular-nums">
                                        {tiempo.valor === null ||
                                        tiempo.valor === undefined
                                            ? 'Sin datos'
                                            : `${tiempo.valor}${tiempo.sufijo ?? ''}`}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </Tarjeta>
                </div>
            </div>
        </GerenteLayout>
    );
}
