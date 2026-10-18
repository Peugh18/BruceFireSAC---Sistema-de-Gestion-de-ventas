import { useId } from 'react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Funnel,
    FunnelChart,
    Line,
    LineChart,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

export type PuntoGrafico = {
    nombre: string;
    valor: number;
    secundario?: number;
};
export type DatosGraficos = Record<string, PuntoGrafico[]>;
type Configuracion = {
    clave: string;
    titulo: string;
    tipo?: 'barras' | 'linea' | 'dona' | 'embudo';
    moneda?: boolean;
    series?: [string, string];
    nota?: string;
};

const COLORES = [3, 2, 4, 5, 1].map((indice) => `var(--chart-${indice})`);
const CONFIGURACION: Record<string, Configuracion[]> = {
    inteligencia: [
        {
            clave: 'categorias',
            titulo: 'Probabilidad de recompra estimada',
            tipo: 'dona',
            nota: 'Una puntuación por cliente activo. Orienta el contacto; no garantiza una compra.',
        },
        {
            clave: 'vencimientos',
            titulo: 'Certificados por vencer',
            nota: 'Fechas registradas en certificados, sin anulados. Tramos sin superposición.',
        },
    ],
    gerente: [
        {
            clave: 'ventas',
            titulo: 'Ventas netas · últimos 6 meses',
            tipo: 'linea',
            moneda: true,
        },
        {
            clave: 'conversion',
            titulo: 'De cotización a venta',
            tipo: 'embudo',
            nota: 'Cohorte del mes elegido: enviadas, aceptadas y con venta confirmada.',
        },
        {
            clave: 'cobranza',
            titulo: 'Cobranza de las ventas del mes',
            tipo: 'dona',
            moneda: true,
        },
        {
            clave: 'sedes',
            titulo: 'Ventas netas por punto de venta',
            moneda: true,
        },
        {
            clave: 'servicios',
            titulo: 'Servicios vendidos por tipo',
            tipo: 'dona',
        },
        {
            clave: 'ordenes',
            titulo: 'Órdenes del mes',
            series: ['En plazo', 'Atrasadas'],
            nota: 'Las terminadas figuran por separado; cada orden se cuenta una vez.',
        },
        {
            clave: 'vencimientos',
            titulo: 'Extintores por vencer',
            nota: 'Tramos sin superposición: 1–30, 31–60 y 61–90 días.',
        },
    ],
    almacen: [
        {
            clave: 'categorias',
            titulo: 'Stock disponible por categoría',
            tipo: 'dona',
            nota: 'Unidades con serie y cantidades del Kardex.',
        },
        {
            clave: 'minimos',
            titulo: 'Productos por reponer',
            nota: 'Stock disponible; consulta la lista de mínimos para registrar el ajuste.',
        },
        {
            clave: 'movimientos',
            titulo: 'Entradas y salidas · últimas 8 semanas',
            tipo: 'linea',
            series: ['Entradas', 'Salidas'],
        },
        {
            clave: 'estados',
            titulo: 'Extintores por estado',
            nota: 'Disponibles y reservados del inventario; en taller son equipos de clientes en atención.',
        },
    ],
    vendedor: [
        {
            clave: 'ventas',
            titulo: 'Mis ventas · mes anterior y actual',
            moneda: true,
        },
        {
            clave: 'cotizaciones',
            titulo: 'Mis cotizaciones de este mes',
            tipo: 'dona',
        },
        {
            clave: 'cobranza',
            titulo: 'Mi cartera por cobrar',
            tipo: 'dona',
            moneda: true,
            nota: 'Incluye todas las cuotas propias, también las que no caben en la lista de hoy.',
        },
    ],
    planta: [
        { clave: 'prioridades', titulo: 'Trabajo pendiente por prioridad' },
        {
            clave: 'atrasadas',
            titulo: 'Plazo del trabajo pendiente',
            tipo: 'dona',
        },
        { clave: 'terminadas', titulo: 'Trabajos terminados · últimos 7 días' },
    ],
    campo: [
        { clave: 'prioridades', titulo: 'Trabajo pendiente por prioridad' },
        {
            clave: 'atrasadas',
            titulo: 'Plazo del trabajo pendiente',
            tipo: 'dona',
        },
        { clave: 'terminadas', titulo: 'Trabajos terminados · últimos 7 días' },
        {
            clave: 'visitas',
            titulo: 'Visitas por tipo · últimos 7 días',
            tipo: 'dona',
            nota: 'Se cuentan visitas registradas, no el nombre del servicio.',
        },
    ],
};

function Grafico({
    datos,
    configuracion,
}: {
    datos: PuntoGrafico[];
    configuracion: Configuracion;
}) {
    const tituloId = useId();
    const formato = (valor: number) =>
        new Intl.NumberFormat(
            'es-PE',
            configuracion.moneda
                ? {
                      style: 'currency',
                      currency: 'PEN',
                      maximumFractionDigits: 2,
                  }
                : { maximumFractionDigits: 0 },
        ).format(valor);
    const tooltip = (
        <Tooltip
            contentStyle={{
                background: 'var(--card)',
                color: 'var(--foreground)',
                borderColor: 'var(--border)',
                borderRadius: 12,
            }}
            formatter={(valor) => formato(Number(valor))}
        />
    );
    const vacio = !datos.some(
        (punto) => punto.valor !== 0 || (punto.secundario ?? 0) !== 0,
    );
    let grafico;
    if (configuracion.tipo === 'dona') {
        grafico = (
            <PieChart accessibilityLayer>
                {tooltip}
                <Pie
                    data={datos}
                    dataKey="valor"
                    nameKey="nombre"
                    innerRadius="50%"
                    outerRadius="80%"
                    isAnimationActive={false}
                >
                    {datos.map((punto, indice) => (
                        <Cell
                            key={punto.nombre}
                            fill={COLORES[indice % COLORES.length]}
                        />
                    ))}
                </Pie>
            </PieChart>
        );
    } else if (configuracion.tipo === 'embudo') {
        grafico = (
            <FunnelChart accessibilityLayer>
                {tooltip}
                <Funnel
                    data={datos.map((punto, indice) => ({
                        ...punto,
                        fill: COLORES[indice % COLORES.length],
                    }))}
                    dataKey="valor"
                    nameKey="nombre"
                    isAnimationActive={false}
                />
            </FunnelChart>
        );
    } else if (configuracion.tipo === 'linea') {
        grafico = (
            <LineChart data={datos} accessibilityLayer>
                <CartesianGrid vertical={false} stroke="var(--border)" />
                <XAxis
                    dataKey="nombre"
                    tick={{ fill: 'var(--muted-foreground)', fontSize: 11 }}
                />
                <YAxis
                    width={50}
                    tick={{ fill: 'var(--muted-foreground)', fontSize: 11 }}
                />
                {tooltip}
                <Line
                    type="monotone"
                    dataKey="valor"
                    name={configuracion.series?.[0] ?? 'Total'}
                    stroke={COLORES[0]}
                    strokeWidth={2}
                    isAnimationActive={false}
                />
                {configuracion.series && (
                    <Line
                        type="monotone"
                        dataKey="secundario"
                        name={configuracion.series[1]}
                        stroke={COLORES[1]}
                        strokeWidth={2}
                        isAnimationActive={false}
                    />
                )}
            </LineChart>
        );
    } else {
        grafico = (
            <BarChart data={datos} accessibilityLayer>
                <CartesianGrid vertical={false} stroke="var(--border)" />
                <XAxis
                    dataKey="nombre"
                    interval={0}
                    tick={{ fill: 'var(--muted-foreground)', fontSize: 11 }}
                />
                <YAxis
                    width={50}
                    tick={{ fill: 'var(--muted-foreground)', fontSize: 11 }}
                />
                {tooltip}
                <Bar
                    stackId="total"
                    dataKey="valor"
                    name={configuracion.series?.[0] ?? 'Total'}
                    fill={COLORES[0]}
                    isAnimationActive={false}
                />
                {configuracion.series && (
                    <Bar
                        stackId="total"
                        dataKey="secundario"
                        name={configuracion.series[1]}
                        fill={COLORES[1]}
                        isAnimationActive={false}
                    />
                )}
            </BarChart>
        );
    }
    return (
        <section
            aria-labelledby={tituloId}
            className="bg-card text-card-foreground min-w-0 rounded-2xl border p-4 sm:p-5"
        >
            <h2 id={tituloId} className="text-sm font-semibold">
                {configuracion.titulo}
            </h2>
            {configuracion.nota && (
                <p className="text-muted-foreground mt-1 text-xs">
                    {configuracion.nota}
                </p>
            )}
            {vacio ? (
                <p className="text-muted-foreground flex h-48 items-center justify-center text-sm">
                    Sin datos para este período.
                </p>
            ) : (
                <div
                    className="mt-4 h-52 min-w-0"
                    aria-label={configuracion.titulo}
                >
                    <ResponsiveContainer
                        width="100%"
                        height="100%"
                        minWidth={0}
                    >
                        {grafico}
                    </ResponsiveContainer>
                </div>
            )}
            {(configuracion.tipo === 'dona' ||
                configuracion.tipo === 'embudo') &&
                !vacio && (
                    <ul className="mt-3 flex flex-wrap gap-x-4 gap-y-2 text-xs">
                        {datos.map((punto, indice) => (
                            <li
                                key={punto.nombre}
                                className="flex items-center gap-1.5"
                            >
                                <span
                                    aria-hidden="true"
                                    className="size-2 rounded-full"
                                    style={{
                                        background:
                                            COLORES[indice % COLORES.length],
                                    }}
                                />
                                {punto.nombre}:{' '}
                                <span className="tabular-nums">
                                    {formato(punto.valor)}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            <details className="mt-3 text-xs">
                <summary className="text-muted-foreground focus-visible:outline-ring cursor-pointer rounded py-2 focus-visible:outline-2">
                    Ver valores del gráfico
                </summary>
                <table className="mt-2 w-full text-left">
                    <caption className="sr-only">
                        {configuracion.titulo}
                    </caption>
                    <thead>
                        <tr>
                            <th scope="col">Concepto</th>
                            <th scope="col" className="text-right">
                                {configuracion.series?.[0] ?? 'Total'}
                            </th>
                            {configuracion.series && (
                                <th scope="col" className="text-right">
                                    {configuracion.series[1]}
                                </th>
                            )}
                        </tr>
                    </thead>
                    <tbody>
                        {datos.map((punto) => (
                            <tr key={punto.nombre} className="border-t">
                                <th scope="row" className="py-2 font-normal">
                                    {punto.nombre}
                                </th>
                                <td className="text-right tabular-nums">
                                    {formato(punto.valor)}
                                </td>
                                {configuracion.series && (
                                    <td className="text-right tabular-nums">
                                        {formato(punto.secundario ?? 0)}
                                    </td>
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </details>
        </section>
    );
}

export default function GraficosDashboard({
    rol,
    datos,
}: {
    rol: keyof typeof CONFIGURACION;
    datos: DatosGraficos;
}) {
    return (
        <div className="grid min-w-0 gap-4 md:grid-cols-2 xl:grid-cols-3">
            {CONFIGURACION[rol]
                .filter(
                    (configuracion) =>
                        configuracion.clave !== 'sedes' ||
                        datos.sedes?.length > 1,
                )
                .map((configuracion) => (
                    <Grafico
                        key={configuracion.clave}
                        configuracion={configuracion}
                        datos={datos[configuracion.clave] ?? []}
                    />
                ))}
        </div>
    );
}
