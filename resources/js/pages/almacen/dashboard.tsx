import { pageTitleClass } from '@/components/page-header';
import { cn } from '@/lib/utils';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeftRight,
    Building2,
    PackageCheck,
    PackagePlus,
    ScanBarcode,
    SlidersHorizontal,
    Sticker,
    User,
} from 'lucide-react';

import {
    Apilada,
    Atajos,
    Atencion,
    cantidad,
    cambioPorcentual,
    Indicador,
    MapaDeCalor,
    Ranking,
    Tarjeta,
    Tendencia,
    Vacio,
    type Atajo,
    type Aviso,
    type Punto,
} from '@/components/bi';
import { Empty } from '@/components/ui/empty';
import AlmacenLayout from '@/layouts/almacen-layout';
import AjustesRoutes from '@/routes/almacen/ajustes';
import ConsultaRoutes from '@/routes/almacen/consulta';
import recepcionesRutas from '@/routes/almacen/recepciones';
import StickersRoutes from '@/routes/almacen/stickers';
import StockRoutes from '@/routes/almacen/stock';
import TrasladosRoutes from '@/routes/almacen/traslados';
import type { Team } from '@/types';

export type SedeStock = {
    sede_id: number;
    nombre: string;
    tipo: string;
    ciudad: string | null;
    unidades_disponibles: number;
};

export type MovimientoReciente = {
    id: number;
    fecha: string | null;
    tipo: string;
    cantidad: number;
    producto: {
        id: number;
        codigo: string;
        nombre: string;
        unidad_medida: string;
        serializado: boolean;
    };
    unidad_serie?: string | null;
    sede?: string | null;
    usuario?: string | null;
    observacion?: string | null;
};

export type ProductoBajoMinimo = {
    id: number;
    codigo: string;
    nombre: string;
    unidad_medida: string;
    stock_minimo: number;
    stock_disponible: number;
    diferencia: number;
};

export type ReceptionToday = {
    id: number;
    proveedor: string;
    documento_referencia: string | null;
    sede: string | null;
};

export type LotePorVencer = {
    id: number;
    producto: string;
    unidad_medida: string;
    lote: string;
    sede: string;
    fecha_vencimiento: string | null;
    vencido: boolean;
    saldo: number;
};

export type AlmacenDashboardProps = {
    graficos: {
        categorias: Punto[];
        estados: Punto[];
        movimientos: Punto[];
        dias: { fecha: string; valor: number }[];
    };
    stock: { total_disponible: number; por_sede: SedeStock[] };
    recepciones_hoy: number;
    recepciones_de_hoy: ReceptionToday[];
    traslados_pendientes: number;
    movimientos_recientes: MovimientoReciente[];
    productos_bajo_minimo: ProductoBajoMinimo[];
    lotes_por_vencer: LotePorVencer[];
};

const ETIQUETA_MOVIMIENTO: Record<string, string> = {
    ingreso: 'Ingreso',
    salida_venta: 'Venta',
    salida_servicio: 'Consumo de taller',
    ajuste: 'Ajuste',
    traslado: 'Traslado',
};

function formatDate(isoString: string | null): string {
    if (!isoString) {
        return '—';
    }

    return new Date(isoString).toLocaleString('es-PE', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function variacionSemanal(actual: number, anterior: number): number | null {
    if (anterior === 0) {
        return actual === 0 ? 0 : null;
    }

    return Math.round(((actual - anterior) / anterior) * 100);
}

/**
 * Inicio de Almacén: atajos, lo que requiere atención, indicadores de la
 * semana y el BI de stock y movimientos.
 */
export default function AlmacenDashboard({
    graficos,
    stock,
    recepciones_hoy,
    recepciones_de_hoy,
    traslados_pendientes,
    movimientos_recientes,
    productos_bajo_minimo,
    lotes_por_vencer,
}: AlmacenDashboardProps) {
    const { currentTeam, puedeTrasladar } = usePage<{
        currentTeam?: Team | null;
        puedeTrasladar?: boolean;
    }>().props;
    const teamSlug = currentTeam?.slug ?? '';
    const hoy = new Date().toLocaleDateString('es-PE', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        timeZone: 'America/Lima',
    });
    const lugar =
        stock.por_sede.length === 1
            ? stock.por_sede[0].nombre
            : 'Todos los almacenes';

    const atajos: Atajo[] = [
        {
            texto: 'Nueva recepción',
            href: recepcionesRutas.create.url(teamSlug),
            icono: PackagePlus,
            principal: true,
        },
        {
            texto: 'Buscar producto',
            href: ConsultaRoutes.index.url(teamSlug),
            icono: ScanBarcode,
        },
        {
            texto: 'Ajuste',
            href: AjustesRoutes.index.url(teamSlug),
            icono: SlidersHorizontal,
        },
        {
            texto: 'Stickers',
            href: StickersRoutes.index.url(teamSlug),
            icono: Sticker,
        },
        ...(puedeTrasladar
            ? [
                  {
                      texto: 'Traslados',
                      href: TrasladosRoutes.index.url(teamSlug),
                      icono: ArrowLeftRight,
                  },
              ]
            : []),
    ];

    const vencidos = lotes_por_vencer.filter((lote) => lote.vencido).length;
    const avisos: Aviso[] = [
        ...(productos_bajo_minimo.length > 0
            ? [
                  {
                      texto: `${cantidad(productos_bajo_minimo.length, 'producto', 'productos')} bajo el mínimo`,
                      accion: 'Reponer',
                      href: StockRoutes.index.url(teamSlug),
                      alerta: true,
                  },
              ]
            : []),
        ...(vencidos > 0
            ? [
                  {
                      texto: `${cantidad(vencidos, 'lote vencido', 'lotes vencidos')} con stock`,
                      accion: 'Dar de baja',
                      href: AjustesRoutes.index.url(teamSlug),
                      alerta: true,
                  },
              ]
            : []),
        ...(lotes_por_vencer.length - vencidos > 0
            ? [
                  {
                      texto: `${cantidad(lotes_por_vencer.length - vencidos, 'lote', 'lotes')} por vencer`,
                      accion: 'Ver',
                      href: StockRoutes.index.url(teamSlug),
                  },
              ]
            : []),
        ...(puedeTrasladar && traslados_pendientes > 0
            ? [
                  {
                      texto: `${cantidad(traslados_pendientes, 'traslado', 'traslados')} por despachar o recibir`,
                      accion: 'Abrir',
                      href: TrasladosRoutes.index.url(teamSlug),
                  },
              ]
            : []),
    ];

    const [disponibles, reservados, enTaller] = graficos.estados.map(
        (punto) => punto.valor,
    );
    const semanas = graficos.movimientos;
    const estaSemana = semanas[semanas.length - 1] ?? {
        valor: 0,
        secundario: 0,
    };
    const semanaPasada = semanas[semanas.length - 2] ?? {
        valor: 0,
        secundario: 0,
    };
    const categorias = [...graficos.categorias]
        .filter((punto) => punto.valor > 0)
        .sort((a, b) => b.valor - a.valor);
    const porReponer: Punto[] = productos_bajo_minimo
        .slice(0, 5)
        .map((producto) => ({
            nombre: `${producto.nombre} · ${producto.stock_disponible} de ${producto.stock_minimo}`,
            valor: Math.max(0, producto.stock_disponible),
            secundario: producto.stock_minimo,
        }));

    return (
        <AlmacenLayout title="Inicio de Almacén">
            <Head title="Inicio de Almacén" />

            <div className="flex flex-col gap-5">
                <header className="flex flex-col gap-4">
                    <div>
                        <p className="text-muted-foreground font-mono text-xs tracking-widest uppercase">
                            Almacén · {lugar}
                        </p>
                        <h1
                            className={cn(
                                pageTitleClass,
                                'mt-1 first-letter:uppercase',
                            )}
                        >
                            {hoy}
                        </h1>
                    </div>
                    <Atajos atajos={atajos} />
                </header>

                <Atencion avisos={avisos} />

                <section
                    aria-label="Indicadores"
                    className="grid grid-cols-[repeat(auto-fit,minmax(190px,1fr))] gap-3.5"
                >
                    <Indicador
                        etiqueta="Extintores disponibles"
                        valor={String(disponibles ?? 0)}
                        detalle={`${reservados ?? 0} reservados`}
                    />
                    <Indicador
                        etiqueta="Entradas esta semana"
                        valor={String(estaSemana.valor)}
                        serie={semanas.map((s) => s.valor)}
                        cambio={cambioPorcentual(
                            variacionSemanal(
                                estaSemana.valor,
                                semanaPasada.valor,
                            ),
                            false,
                            'vs. la semana pasada',
                        )}
                    />
                    <Indicador
                        etiqueta="Salidas esta semana"
                        valor={String(estaSemana.secundario ?? 0)}
                        serie={semanas.map((s) => s.secundario ?? 0)}
                        detalle="ventas, servicios y ajustes"
                    />
                    <Indicador
                        etiqueta="En taller (de clientes)"
                        valor={String(enTaller ?? 0)}
                        detalle="no cuentan como stock"
                    />
                </section>

                <div className="grid gap-3.5 lg:grid-cols-3">
                    <Tarjeta
                        titulo="Entradas y salidas · 8 semanas"
                        className="lg:col-span-2"
                    >
                        {semanas.some(
                            (s) => s.valor + (s.secundario ?? 0) > 0,
                        ) ? (
                            <Tendencia
                                titulo="Entradas y salidas por semana"
                                datos={semanas}
                                series={['Entradas', 'Salidas']}
                                alto={230}
                            />
                        ) : (
                            <Vacio>
                                Sin movimientos en las últimas 8 semanas.
                            </Vacio>
                        )}
                    </Tarjeta>
                    <Tarjeta titulo="Por reponer">
                        {porReponer.length > 0 ? (
                            <>
                                <Ranking
                                    datos={porReponer.map((p) => ({
                                        nombre: p.nombre,
                                        valor: p.valor,
                                    }))}
                                    colorDe={(p) => {
                                        const minimo =
                                            porReponer.find(
                                                (r) => r.nombre === p.nombre,
                                            )?.secundario ?? 1;

                                        return p.valor / minimo < 0.5
                                            ? 'bi-alerta'
                                            : 'bi-1';
                                    }}
                                />
                                <Link
                                    href={StockRoutes.index.url(teamSlug)}
                                    className="text-primary-strong text-sm font-semibold hover:underline"
                                >
                                    Ver los mínimos ›
                                </Link>
                            </>
                        ) : (
                            <Vacio>Todo el stock está sobre el mínimo.</Vacio>
                        )}
                    </Tarjeta>
                </div>

                <div className="grid gap-3.5 md:grid-cols-2 xl:grid-cols-3">
                    <Tarjeta titulo="Stock disponible por categoría">
                        {categorias.length > 0 ? (
                            <Ranking datos={categorias.slice(0, 6)} />
                        ) : (
                            <Vacio>Aún no hay stock disponible.</Vacio>
                        )}
                    </Tarjeta>
                    <Tarjeta titulo="Extintores por estado">
                        <b className="text-2xl tabular-nums">
                            {(disponibles ?? 0) +
                                (reservados ?? 0) +
                                (enTaller ?? 0)}{' '}
                            <span className="text-muted-foreground text-sm font-normal">
                                en total
                            </span>
                        </b>
                        <Apilada
                            partes={[
                                {
                                    nombre: 'Disponibles',
                                    valor: disponibles ?? 0,
                                    color: 'bi-1',
                                },
                                {
                                    nombre: 'Reservados',
                                    valor: reservados ?? 0,
                                    color: 'bi-2',
                                },
                                {
                                    nombre: 'En taller',
                                    valor: enTaller ?? 0,
                                    color: 'bi-neutro',
                                },
                            ]}
                        />
                    </Tarjeta>
                    <Tarjeta titulo="Movimientos por día" extra="6 semanas">
                        <MapaDeCalor dias={graficos.dias} />
                    </Tarjeta>
                </div>

                <div className="grid gap-3.5 lg:grid-cols-3">
                    <Tarjeta
                        titulo="Últimos movimientos del Kardex"
                        className="lg:col-span-2"
                    >
                        {movimientos_recientes.length === 0 ? (
                            <Empty
                                icon={<PackageCheck />}
                                title="Aún no se registran movimientos en el Kardex."
                            />
                        ) : (
                            <ul className="divide-border divide-y">
                                {movimientos_recientes.map((mov) => (
                                    <li
                                        key={mov.id}
                                        className="flex flex-col gap-1 py-2.5 text-sm sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div className="min-w-0">
                                            <span className="text-foreground block truncate font-semibold">
                                                {mov.producto.nombre}
                                            </span>
                                            <span className="text-muted-foreground flex flex-wrap items-center gap-2 text-xs">
                                                {ETIQUETA_MOVIMIENTO[
                                                    mov.tipo
                                                ] ?? mov.tipo}
                                                {mov.unidad_serie && (
                                                    <span className="font-mono">
                                                        {mov.unidad_serie}
                                                    </span>
                                                )}
                                                {mov.sede && (
                                                    <span className="flex items-center gap-1">
                                                        <Building2
                                                            className="size-3"
                                                            aria-hidden="true"
                                                        />
                                                        {mov.sede}
                                                    </span>
                                                )}
                                            </span>
                                        </div>
                                        <div className="text-muted-foreground flex shrink-0 items-center gap-2 text-xs">
                                            <b className="text-foreground font-mono text-sm">
                                                {mov.cantidad > 0
                                                    ? `+${mov.cantidad}`
                                                    : mov.cantidad}{' '}
                                                <span className="font-normal">
                                                    {mov.producto.unidad_medida}
                                                </span>
                                            </b>
                                            {mov.usuario && (
                                                <span className="flex items-center gap-0.5">
                                                    <User
                                                        className="size-3"
                                                        aria-hidden="true"
                                                    />
                                                    {mov.usuario}
                                                </span>
                                            )}
                                            <span>{formatDate(mov.fecha)}</span>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Tarjeta>
                    <div className="flex flex-col gap-3.5">
                        <Tarjeta
                            titulo="Recepciones de hoy"
                            extra={String(recepciones_hoy)}
                        >
                            {recepciones_de_hoy.length > 0 ? (
                                <ul className="divide-border divide-y text-sm">
                                    {recepciones_de_hoy.map((recepcion) => (
                                        <li key={recepcion.id} className="py-2">
                                            <span className="block font-semibold">
                                                {recepcion.proveedor}
                                            </span>
                                            <span className="text-muted-foreground text-xs">
                                                {recepcion.documento_referencia ??
                                                    'Sin documento'}{' '}
                                                · {recepcion.sede ?? 'Almacén'}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <Vacio>Aún no hay recepciones hoy.</Vacio>
                            )}
                        </Tarjeta>
                        {lotes_por_vencer.length > 0 && (
                            <Tarjeta titulo="Lotes vencidos o por vencer">
                                <ul className="divide-border divide-y text-sm">
                                    {lotes_por_vencer.map((lote) => (
                                        <li
                                            key={lote.id}
                                            className="flex items-center justify-between gap-2 py-2"
                                        >
                                            <span className="min-w-0">
                                                <span className="block truncate font-semibold">
                                                    {lote.producto}
                                                </span>
                                                <span className="text-muted-foreground text-xs">
                                                    Lote {lote.lote} ·{' '}
                                                    {lote.saldo}{' '}
                                                    {lote.unidad_medida}
                                                </span>
                                            </span>
                                            <span
                                                className={`shrink-0 rounded-full px-2 py-0.5 text-xs font-bold ${
                                                    lote.vencido
                                                        ? 'bg-destructive/10 text-destructive-strong'
                                                        : 'bg-muted text-foreground'
                                                }`}
                                            >
                                                {lote.vencido
                                                    ? 'Vencido'
                                                    : 'Vence'}{' '}
                                                {lote.fecha_vencimiento
                                                    ?.split('-')
                                                    .reverse()
                                                    .join('/')}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </Tarjeta>
                        )}
                    </div>
                </div>
            </div>
        </AlmacenLayout>
    );
}
