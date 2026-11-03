import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { useId, type ReactNode } from 'react';
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

import { cn, soles } from '@/lib/utils';

/**
 * Piezas del BI de los dashboards (diseño "Dashboards BI Bruce Fire" en
 * Claude Design). Los colores salen de los tokens --bi-* de app.css: azul y
 * naranja para series, rojo solo para lo vencido o atrasado.
 */
export type Punto = { nombre: string; valor: number; secundario?: number };

/** Punto de una tendencia: `valor` en null cuando aún no ocurre (días futuros). */
type PuntoTendencia = {
    nombre: string;
    valor: number | null;
    secundario?: number;
};

export type ColorBi =
    | 'bi-1'
    | 'bi-2'
    | 'bi-3'
    | 'bi-4'
    | 'bi-neutro'
    | 'bi-alerta';

const color = (clave: ColorBi) => `var(--${clave})`;

/** "1 producto", "4 productos". */
export function cantidad(numero: number, singular: string, plural: string) {
    return `${numero} ${numero === 1 ? singular : plural}`;
}

export function formatoBi(valor: number, moneda = false): string {
    return moneda
        ? soles(valor)
        : new Intl.NumberFormat('es-PE', { maximumFractionDigits: 1 }).format(
              valor,
          );
}

export function Tarjeta({
    titulo,
    extra,
    children,
    className,
}: {
    titulo: string;
    extra?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    const id = useId();

    return (
        <section
            aria-labelledby={id}
            className={cn(
                'border-border bg-card text-card-foreground flex min-w-0 flex-col gap-3 rounded-2xl border p-5',
                className,
            )}
        >
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id={id} className="text-base font-bold">
                    {titulo}
                </h2>
                {extra && (
                    <span className="text-muted-foreground text-xs">
                        {extra}
                    </span>
                )}
            </div>
            {children}
        </section>
    );
}

export function Vacio({ children }: { children: ReactNode }) {
    return <p className="text-muted-foreground py-6 text-sm">{children}</p>;
}

export type Aviso = {
    texto: string;
    accion: string;
    href: string;
    alerta?: boolean;
};

/** Franja "Requiere tu atención": un enlace por aviso, rojo solo si es urgente. */
export function Atencion({
    avisos,
    vacio = 'Todo al día: nada pendiente por ahora.',
}: {
    avisos: Aviso[];
    vacio?: string;
}) {
    if (avisos.length === 0) {
        return <p className="text-muted-foreground text-sm">{vacio}</p>;
    }

    return (
        <section
            aria-label="Requiere tu atención"
            className="grid grid-cols-[repeat(auto-fit,minmax(230px,1fr))] gap-2.5"
        >
            {avisos.map((aviso) => (
                <Link
                    key={aviso.texto}
                    href={aviso.href}
                    className={cn(
                        'focus-visible:ring-ring flex min-h-11 items-center justify-between gap-3 rounded-xl border px-3.5 py-3 text-sm font-semibold focus-visible:ring-2 focus-visible:outline-none',
                        aviso.alerta
                            ? 'border-destructive/40 bg-destructive/10 text-destructive-strong hover:bg-destructive/15'
                            : 'border-border bg-card text-foreground hover:bg-accent',
                    )}
                >
                    <span>{aviso.texto}</span>
                    <span
                        className={cn(
                            'shrink-0 text-xs',
                            !aviso.alerta && 'text-muted-foreground',
                        )}
                    >
                        {aviso.accion} ›
                    </span>
                </Link>
            ))}
        </section>
    );
}

export type Atajo = {
    texto: string;
    href: string;
    icono: LucideIcon;
    principal?: boolean;
};

/** Atajos rápidos del rol: la acción principal en rojo, el resto en contorno. */
export function Atajos({ atajos }: { atajos: Atajo[] }) {
    return (
        <nav aria-label="Atajos" className="flex flex-wrap gap-2">
            {atajos.map(({ texto, href, icono: Icono, principal }) => (
                <Link
                    key={texto}
                    href={href}
                    className={cn(
                        'focus-visible:ring-ring inline-flex min-h-11 items-center gap-2 rounded-xl px-4 text-sm font-semibold focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none',
                        principal
                            ? 'bg-primary text-primary-foreground hover:bg-primary/90'
                            : 'border-border bg-card text-foreground hover:bg-accent border',
                    )}
                >
                    <Icono className="size-4" aria-hidden="true" />
                    {texto}
                </Link>
            ))}
        </nav>
    );
}

export type Cambio = { texto: string; tono: 'bueno' | 'malo' | 'neutro' };

export function MiniLinea({ serie }: { serie: number[] }) {
    if (serie.length < 2 || serie.every((valor) => valor === 0)) {
        return null;
    }

    const max = Math.max(...serie);
    const min = Math.min(...serie);
    const rango = max - min || 1;
    const puntos = serie.map((valor, indice) => [
        2 + (indice * 80) / (serie.length - 1),
        28 - ((valor - min) / rango) * 24,
    ]);
    const [ultimoX, ultimoY] = puntos[puntos.length - 1];

    return (
        <svg
            width="84"
            height="32"
            viewBox="0 0 84 32"
            aria-hidden="true"
            className="shrink-0"
        >
            <path
                d={puntos
                    .map(([x, y], i) => `${i === 0 ? 'M' : 'L'}${x} ${y}`)
                    .join(' ')}
                fill="none"
                stroke={color('bi-1')}
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <circle cx={ultimoX} cy={ultimoY} r="3" fill={color('bi-1')} />
        </svg>
    );
}

/** Indicador: número grande, mini tendencia y cambio contra el periodo anterior. */
export function Indicador({
    etiqueta,
    valor,
    cambio,
    detalle,
    serie = [],
}: {
    etiqueta: string;
    valor: string;
    cambio?: Cambio | null;
    detalle?: string;
    serie?: number[];
}) {
    return (
        <div className="border-border bg-card flex min-w-0 flex-col gap-1.5 rounded-2xl border px-5 py-4">
            <span className="text-muted-foreground text-sm font-medium">
                {etiqueta}
            </span>
            <div className="flex items-end justify-between gap-2">
                <span className="text-foreground text-[28px] leading-tight font-bold tracking-tight tabular-nums">
                    {valor}
                </span>
                <MiniLinea serie={serie} />
            </div>
            {cambio && (
                <span
                    className={cn(
                        'text-sm font-semibold',
                        cambio.tono === 'bueno' && 'text-success-strong',
                        cambio.tono === 'malo' && 'text-destructive-strong',
                        cambio.tono === 'neutro' && 'text-muted-foreground',
                    )}
                >
                    {cambio.texto}
                </span>
            )}
            {detalle && (
                <span className="text-muted-foreground text-xs">{detalle}</span>
            )}
        </div>
    );
}

function estiloTooltip() {
    return {
        contentStyle: {
            background: 'var(--popover)',
            color: 'var(--popover-foreground)',
            border: '1px solid var(--border)',
            borderRadius: 10,
            fontSize: 12,
        },
        labelStyle: { color: 'var(--muted-foreground)' },
        cursor: { stroke: 'var(--border)' },
    };
}

/** Tabla oculta con los mismos valores, para lectores de pantalla. */
function TablaOculta({
    titulo,
    datos,
    series,
    moneda,
}: {
    titulo: string;
    datos: PuntoTendencia[];
    series: [string, string?];
    moneda?: boolean;
}) {
    return (
        <table className="sr-only">
            <caption>{titulo}</caption>
            <thead>
                <tr>
                    <th scope="col">Periodo</th>
                    <th scope="col">{series[0]}</th>
                    {series[1] && <th scope="col">{series[1]}</th>}
                </tr>
            </thead>
            <tbody>
                {datos.map((punto) => (
                    <tr key={punto.nombre}>
                        <th scope="row">{punto.nombre}</th>
                        <td>
                            {punto.valor === null
                                ? '—'
                                : formatoBi(punto.valor, moneda)}
                        </td>
                        {series[1] && (
                            <td>{formatoBi(punto.secundario ?? 0, moneda)}</td>
                        )}
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

/** Tendencia en área con degradado; la segunda serie va punteada en naranja. */
export function Tendencia({
    titulo,
    datos,
    series = ['Total'],
    moneda = false,
    alto = 240,
}: {
    titulo: string;
    datos: PuntoTendencia[];
    series?: [string, string?];
    moneda?: boolean;
    alto?: number;
}) {
    const id = useId().replaceAll(':', '');
    // El punto resaltado va en el último valor conocido (los días futuros vienen en null).
    const ultimo = datos.reduce(
        (ultimoConValor, punto, indice) =>
            punto.valor === null ? ultimoConValor : indice,
        -1,
    );
    const tooltip = estiloTooltip();

    return (
        <div>
            <div style={{ height: alto }} aria-hidden="true">
                <ResponsiveContainer width="100%" height="100%" minWidth={0}>
                    <AreaChart
                        accessibilityLayer={false}
                        data={datos}
                        margin={{ top: 16, right: 12, bottom: 0, left: 0 }}
                    >
                        <defs>
                            <linearGradient
                                id={`${id}-a`}
                                x1="0"
                                y1="0"
                                x2="0"
                                y2="1"
                            >
                                <stop
                                    offset="0"
                                    stopColor={color('bi-1')}
                                    stopOpacity={0.32}
                                />
                                <stop
                                    offset="1"
                                    stopColor={color('bi-1')}
                                    stopOpacity={0}
                                />
                            </linearGradient>
                            <linearGradient
                                id={`${id}-b`}
                                x1="0"
                                y1="0"
                                x2="0"
                                y2="1"
                            >
                                <stop
                                    offset="0"
                                    stopColor={color('bi-2')}
                                    stopOpacity={0.22}
                                />
                                <stop
                                    offset="1"
                                    stopColor={color('bi-2')}
                                    stopOpacity={0}
                                />
                            </linearGradient>
                        </defs>
                        <CartesianGrid
                            vertical={false}
                            stroke="var(--border)"
                        />
                        <XAxis
                            dataKey="nombre"
                            tickLine={false}
                            axisLine={{ stroke: 'var(--border)' }}
                            tick={{
                                fill: 'var(--muted-foreground)',
                                fontSize: 12,
                            }}
                        />
                        <YAxis
                            width={moneda ? 64 : 36}
                            tickLine={false}
                            axisLine={false}
                            tick={{
                                fill: 'var(--muted-foreground)',
                                fontSize: 11,
                            }}
                            tickFormatter={(valor: number) =>
                                moneda
                                    ? `S/ ${new Intl.NumberFormat('es-PE', { notation: 'compact' }).format(valor)}`
                                    : formatoBi(valor)
                            }
                        />
                        <Tooltip
                            {...tooltip}
                            formatter={(valor) =>
                                formatoBi(Number(valor), moneda)
                            }
                        />
                        {series[1] && (
                            <Area
                                type="monotone"
                                dataKey="secundario"
                                name={series[1]}
                                stroke={color('bi-2')}
                                strokeWidth={2}
                                strokeDasharray="6 4"
                                fill={`url(#${id}-b)`}
                                isAnimationActive={false}
                                connectNulls
                            />
                        )}
                        <Area
                            type="monotone"
                            dataKey="valor"
                            name={series[0]}
                            stroke={color('bi-1')}
                            strokeWidth={2.5}
                            fill={`url(#${id}-a)`}
                            isAnimationActive={false}
                            activeDot={{
                                r: 5,
                                stroke: 'var(--card)',
                                strokeWidth: 2,
                            }}
                            dot={(props: {
                                cx?: number;
                                cy?: number;
                                index?: number;
                            }) =>
                                props.index === ultimo &&
                                props.cx !== undefined &&
                                props.cy !== undefined ? (
                                    <circle
                                        key="ultimo"
                                        cx={props.cx}
                                        cy={props.cy}
                                        r={6}
                                        fill={color('bi-1')}
                                        stroke="var(--card)"
                                        strokeWidth={3}
                                    />
                                ) : (
                                    <g key={props.index} />
                                )
                            }
                            connectNulls={false}
                        />
                    </AreaChart>
                </ResponsiveContainer>
            </div>
            <TablaOculta
                titulo={titulo}
                datos={datos}
                series={series}
                moneda={moneda}
            />
        </div>
    );
}

/** Columnas apiladas: lo que va bien en azul y lo atrasado en rojo encima. */
export function Columnas({
    titulo,
    datos,
    series,
    alto = 180,
}: {
    titulo: string;
    datos: Punto[];
    series: [string, string?];
    alto?: number;
}) {
    const tooltip = estiloTooltip();

    return (
        <div>
            <div style={{ height: alto }} aria-hidden="true">
                <ResponsiveContainer width="100%" height="100%" minWidth={0}>
                    <BarChart
                        accessibilityLayer={false}
                        data={datos}
                        margin={{ top: 8, right: 4, bottom: 0, left: -24 }}
                    >
                        <CartesianGrid
                            vertical={false}
                            stroke="var(--border)"
                        />
                        <XAxis
                            dataKey="nombre"
                            interval={0}
                            tickLine={false}
                            axisLine={{ stroke: 'var(--border)' }}
                            tick={{
                                fill: 'var(--muted-foreground)',
                                fontSize: 11,
                            }}
                        />
                        <YAxis
                            allowDecimals={false}
                            tickLine={false}
                            axisLine={false}
                            tick={{
                                fill: 'var(--muted-foreground)',
                                fontSize: 11,
                            }}
                        />
                        <Tooltip
                            {...tooltip}
                            cursor={{ fill: 'var(--accent)' }}
                        />
                        <Bar
                            dataKey="valor"
                            name={series[0]}
                            stackId="a"
                            fill={color('bi-1')}
                            radius={series[1] ? 0 : [5, 5, 0, 0]}
                            maxBarSize={36}
                            isAnimationActive={false}
                        />
                        {series[1] && (
                            <Bar
                                dataKey="secundario"
                                name={series[1]}
                                stackId="a"
                                fill={color('bi-alerta')}
                                radius={[5, 5, 0, 0]}
                                maxBarSize={36}
                                isAnimationActive={false}
                            />
                        )}
                    </BarChart>
                </ResponsiveContainer>
            </div>
            {series[1] && (
                <Leyenda
                    items={[
                        { nombre: series[0], color: 'bi-1' },
                        { nombre: series[1], color: 'bi-alerta' },
                    ]}
                />
            )}
            <TablaOculta titulo={titulo} datos={datos} series={series} />
        </div>
    );
}

export function Leyenda({
    items,
}: {
    items: { nombre: string; color: ColorBi; valor?: string }[];
}) {
    return (
        <ul className="text-muted-foreground flex flex-wrap gap-x-4 gap-y-1.5 text-xs">
            {items.map((item) => (
                <li key={item.nombre} className="flex items-center gap-1.5">
                    <span
                        aria-hidden="true"
                        className="size-2.5 rounded-[3px]"
                        style={{ background: color(item.color) }}
                    />
                    {item.nombre}
                    {item.valor && (
                        <b className="text-foreground tabular-nums">
                            {item.valor}
                        </b>
                    )}
                </li>
            ))}
        </ul>
    );
}

/** Anillo de avance para un porcentaje (cobrado, en plazo). */
export function Anillo({
    porcentaje,
    etiqueta,
    tamano = 128,
}: {
    porcentaje: number;
    etiqueta: string;
    tamano?: number;
}) {
    const valor = Math.max(0, Math.min(100, Math.round(porcentaje)));
    const radio = 52;
    const circunferencia = 2 * Math.PI * radio;

    return (
        <svg
            width={tamano}
            height={tamano}
            viewBox="0 0 128 128"
            role="img"
            aria-label={`${valor} % ${etiqueta}`}
            className="shrink-0"
        >
            <circle
                cx="64"
                cy="64"
                r={radio}
                fill="none"
                stroke="var(--bi-pista)"
                strokeWidth="14"
            />
            <circle
                cx="64"
                cy="64"
                r={radio}
                fill="none"
                stroke={color('bi-1')}
                strokeWidth="14"
                strokeLinecap="round"
                strokeDasharray={`${(valor / 100) * circunferencia} ${circunferencia}`}
                transform="rotate(-90 64 64)"
            />
            <text
                x="64"
                y="66"
                textAnchor="middle"
                fontSize="26"
                fontWeight="700"
                fill="var(--foreground)"
            >
                {valor}%
            </text>
            <text
                x="64"
                y="86"
                textAnchor="middle"
                fontSize="11"
                fill="var(--muted-foreground)"
            >
                {etiqueta}
            </text>
        </svg>
    );
}

/**
 * Barras horizontales redondeadas con el valor al costado. Con `embudo`, cada
 * fila muestra su % frente a la primera (enviadas → aceptadas → vendidas).
 */
export function Ranking({
    datos,
    moneda = false,
    embudo = false,
    colorDe,
}: {
    datos: Punto[];
    moneda?: boolean;
    embudo?: boolean;
    colorDe?: (punto: Punto) => ColorBi;
}) {
    const maximo = Math.max(...datos.map((punto) => punto.valor), 1);
    const base = datos[0]?.valor || 1;

    return (
        <ul className="flex flex-col gap-3 text-sm">
            {datos.map((punto, indice) => (
                <li key={punto.nombre} className="flex flex-col gap-1.5">
                    <span className="flex justify-between gap-3">
                        <span className="min-w-0 truncate">{punto.nombre}</span>
                        <b className="shrink-0 tabular-nums">
                            {formatoBi(punto.valor, moneda)}
                            {embudo && indice > 0 && (
                                <span className="text-muted-foreground font-normal">
                                    {' '}
                                    · {Math.round((punto.valor / base) * 100)} %
                                </span>
                            )}
                        </b>
                    </span>
                    <span
                        aria-hidden="true"
                        className="block h-3 rounded-full"
                        style={{ background: 'var(--bi-pista)' }}
                    >
                        <span
                            className="block h-3 rounded-full"
                            style={{
                                width: `${Math.max(2, (punto.valor / maximo) * 100)}%`,
                                background: color(colorDe?.(punto) ?? 'bi-1'),
                                opacity: embudo ? 1 - indice * 0.2 : 1,
                            }}
                        />
                    </span>
                </li>
            ))}
        </ul>
    );
}

export type Parte = { nombre: string; valor: number; color: ColorBi };

/** Barra apilada para las partes de un total, con su leyenda y montos. */
export function Apilada({
    partes,
    moneda = false,
}: {
    partes: Parte[];
    moneda?: boolean;
}) {
    const visibles = partes.filter((parte) => parte.valor > 0);

    return (
        <div className="flex flex-col gap-3">
            <div
                aria-hidden="true"
                className="flex h-3.5 gap-0.5 overflow-hidden rounded-full"
                style={{ background: 'var(--bi-pista)' }}
            >
                {visibles.map((parte) => (
                    <span
                        key={parte.nombre}
                        style={{
                            flex: parte.valor,
                            background: color(parte.color),
                        }}
                    />
                ))}
            </div>
            <ul className="flex flex-col gap-2 text-sm">
                {partes.map((parte) => (
                    <li key={parte.nombre} className="flex items-center gap-2">
                        <span
                            aria-hidden="true"
                            className="size-2.5 shrink-0 rounded-[3px]"
                            style={{ background: color(parte.color) }}
                        />
                        <span className="text-muted-foreground">
                            {parte.nombre}
                        </span>
                        <b className="ml-auto tabular-nums">
                            {formatoBi(parte.valor, moneda)}
                        </b>
                    </li>
                ))}
            </ul>
        </div>
    );
}

const DIAS = ['Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá', 'Do'];

/**
 * Mapa de calor por día: columnas = semanas, filas = días de la semana. Un
 * solo tono (azul) que se intensifica con la cantidad.
 */
export function MapaDeCalor({
    dias,
    unidad = 'movimientos',
}: {
    dias: { fecha: string; valor: number }[];
    unidad?: string;
}) {
    const maximo = Math.max(...dias.map((dia) => dia.valor), 1);
    const semanas: { fecha: string; valor: number }[][] = [];
    dias.forEach((dia, indice) => {
        (semanas[Math.floor(indice / 7)] ??= []).push(dia);
    });

    return (
        <div className="flex flex-col gap-2">
            <div
                className="grid gap-1 text-[11px]"
                style={{
                    gridTemplateColumns: `26px repeat(${semanas.length}, minmax(0, 1fr))`,
                }}
            >
                {DIAS.map((nombre, fila) => (
                    <div key={nombre} className="contents">
                        <span className="text-muted-foreground leading-[18px]">
                            {nombre}
                        </span>
                        {semanas.map((semana) => {
                            const dia = semana[fila];

                            return dia ? (
                                <span
                                    key={dia.fecha}
                                    title={`${dia.fecha}: ${dia.valor} ${unidad}`}
                                    className="h-[18px] rounded"
                                    style={{
                                        background:
                                            dia.valor === 0
                                                ? 'var(--bi-pista)'
                                                : `color-mix(in oklab, var(--bi-1) ${30 + Math.round((dia.valor / maximo) * 70)}%, var(--bi-pista))`,
                                    }}
                                />
                            ) : (
                                <span key={`${fila}-vacio`} />
                            );
                        })}
                    </div>
                ))}
            </div>
            <span className="text-muted-foreground text-xs">
                Más intenso = más {unidad}
            </span>
        </div>
    );
}

/**
 * "Tu semana" de los técnicos: va debajo de la cola o la ruta, compacto. En
 * plazo contra atrasado, pendientes por prioridad, terminadas por día y, en
 * Campo, visitas por tipo.
 */
export function TuSemana({
    graficos,
}: {
    graficos: {
        prioridades: Punto[];
        atrasadas: Punto[];
        terminadas: Punto[];
        visitas?: Punto[];
    };
}) {
    const [enPlazo, atrasadas] = graficos.atrasadas.map((p) => p.valor);
    const pendientes = (enPlazo ?? 0) + (atrasadas ?? 0);
    const terminadas = graficos.terminadas.reduce((s, p) => s + p.valor, 0);
    const visitas = graficos.visitas?.filter((p) => p.valor > 0) ?? [];
    const coloresVisitas: ColorBi[] = [
        'bi-1',
        'bi-2',
        'bi-3',
        'bi-4',
        'bi-neutro',
    ];

    return (
        <section aria-labelledby="tu-semana" className="flex flex-col gap-3">
            <div className="flex items-center gap-3">
                <h2
                    id="tu-semana"
                    className="font-['Oswald',sans-serif] text-lg font-semibold"
                >
                    Tu semana
                </h2>
                <span aria-hidden="true" className="bg-border h-px flex-1" />
            </div>
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <Tarjeta titulo="Trabajo pendiente">
                    {pendientes > 0 ? (
                        <div className="flex items-center gap-4">
                            <Anillo
                                porcentaje={((enPlazo ?? 0) / pendientes) * 100}
                                etiqueta="en plazo"
                                tamano={104}
                            />
                            <div className="min-w-0 flex-1">
                                <Ranking
                                    datos={graficos.prioridades}
                                    colorDe={(p) =>
                                        p.nombre === 'Urgente'
                                            ? 'bi-alerta'
                                            : p.nombre === 'Alta'
                                              ? 'bi-2'
                                              : 'bi-1'
                                    }
                                />
                            </div>
                        </div>
                    ) : (
                        <Vacio>Sin trabajo pendiente.</Vacio>
                    )}
                    {(atrasadas ?? 0) > 0 && (
                        <span className="text-destructive-strong text-sm font-semibold">
                            {atrasadas} atrasadas
                        </span>
                    )}
                </Tarjeta>
                <Tarjeta
                    titulo="Terminadas · 7 días"
                    extra={String(terminadas)}
                >
                    <Columnas
                        titulo="Trabajos terminados por día"
                        datos={graficos.terminadas}
                        series={['Terminadas']}
                        alto={140}
                    />
                </Tarjeta>
                {graficos.visitas && (
                    <Tarjeta titulo="Visitas por tipo · 7 días">
                        {visitas.length > 0 ? (
                            <Apilada
                                partes={visitas.map((p, i) => ({
                                    nombre: p.nombre,
                                    valor: p.valor,
                                    color: coloresVisitas[
                                        i % coloresVisitas.length
                                    ],
                                }))}
                            />
                        ) : (
                            <Vacio>Sin visitas registradas esta semana.</Vacio>
                        )}
                    </Tarjeta>
                )}
            </div>
        </section>
    );
}

/** Texto y tono del cambio contra el periodo anterior. */
export function cambioPorcentual(
    variacion: number | null,
    menosEsMejor = false,
    sufijo = 'vs. el mes anterior',
): Cambio | null {
    if (variacion === null) {
        return null;
    }
    if (variacion === 0) {
        return {
            texto: `Igual que ${sufijo.replace('vs. ', '')}`,
            tono: 'neutro',
        };
    }

    const sube = variacion > 0;

    return {
        texto: `${sube ? '▲' : '▼'} ${Math.abs(variacion)} % ${sufijo}`,
        tono: sube !== menosEsMejor ? 'bueno' : 'malo',
    };
}
