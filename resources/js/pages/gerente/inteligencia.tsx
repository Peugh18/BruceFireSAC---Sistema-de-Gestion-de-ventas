import { Form, Link, usePage } from '@inertiajs/react';
import { BrainCircuit, CalendarClock, Search } from 'lucide-react';

import GraficosDashboard from '@/components/graficos-dashboard';
import { Button } from '@/components/ui/button';
import { Empty } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import GerenteLayout from '@/layouts/gerente-layout';
import { inteligencia } from '@/routes/gerente';

type Cliente = {
    id: number;
    cliente: string;
    documento: string;
    porcentaje: number;
    categoria: string;
    resumen: string;
    razones: { texto: string; a_favor: boolean }[];
    compras: number;
    monto: number;
    recencia: number;
    calculado: string;
    desactualizado: boolean;
    proximoVencimiento: string | null;
    url: string;
};
type Props = {
    currentTeam: { slug: string };
    filters: { buscar: string; categoria: string; vigencia: string };
    resumen: {
        total: number;
        sinPuntuacion: number;
        calculado: string | null;
        desactualizados: number;
    };
    categorias: { nombre: string; total: number }[];
    vencimientos: { nombre: string; total: number }[];
    clientes: {
        data: Cliente[];
        total: number;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    [key: string]: unknown;
};
const moneda = (valor: number) =>
    new Intl.NumberFormat('es-PE', {
        style: 'currency',
        currency: 'PEN',
    }).format(valor);
const fecha = (valor: string | null) =>
    valor
        ? new Intl.DateTimeFormat('es-PE').format(
              new Date(`${valor.slice(0, 10)}T12:00:00`),
          )
        : 'Sin registrar';

export default function Inteligencia() {
    const {
        currentTeam,
        filters,
        resumen,
        categorias,
        vencimientos,
        clientes,
    } = usePage<Props>().props;
    const destino = inteligencia.url({ current_team: currentTeam.slug });
    return (
        <GerenteLayout title="Recompra e IA">
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6">
                <header className="space-y-2">
                    <div className="text-muted-foreground flex items-center gap-2 text-sm">
                        <BrainCircuit aria-hidden="true" className="size-5" />{' '}
                        Inteligencia comercial
                    </div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Recompra e IA
                    </h1>
                    <p className="text-muted-foreground max-w-3xl text-sm leading-relaxed">
                        Prioriza a quién contactar con las compras históricas y
                        las ventas del sistema. La probabilidad es una
                        estimación del modelo y no garantiza una compra. Los
                        vencimientos provienen de certificados registrados.
                    </p>
                </header>
                <section
                    aria-label="Resumen de clientes activos de toda la empresa"
                    className="grid gap-3 sm:grid-cols-3"
                >
                    {[
                        ['Clientes evaluados', resumen.total],
                        ['Sin puntuación', resumen.sinPuntuacion],
                        ['Cálculos de más de 30 días', resumen.desactualizados],
                    ].map(([titulo, valor]) => (
                        <div
                            key={titulo}
                            className="bg-card rounded-2xl border p-4"
                        >
                            <p className="text-muted-foreground text-sm">
                                {titulo}
                            </p>
                            <p className="mt-2 text-3xl font-semibold tabular-nums">
                                {valor}
                            </p>
                        </div>
                    ))}
                </section>
                <div className="bg-muted/40 text-muted-foreground rounded-xl border p-4 text-sm leading-relaxed">
                    <p>
                        Última puntuación registrada:{' '}
                        <strong className="text-foreground">
                            {resumen.calculado
                                ? fecha(resumen.calculado)
                                : 'Todavía no hay puntuaciones'}
                        </strong>
                        . Los gráficos resumen toda la empresa.
                    </p>
                    {resumen.desactualizados > 0 && (
                        <p className="text-foreground mt-2">
                            Hay cálculos de más de 30 días. Solicita su
                            actualización antes de priorizar contactos con
                            ellos.
                        </p>
                    )}
                    {resumen.total === 0 && (
                        <p className="mt-2">
                            Carga el histórico de compras y calcula las
                            puntuaciones para ver estimaciones. Las fechas de
                            certificados pueden consultarse aunque todavía no
                            haya puntuaciones.
                        </p>
                    )}
                </div>
                <GraficosDashboard
                    rol="inteligencia"
                    datos={{
                        categorias: categorias.map(({ nombre, total }) => ({
                            nombre,
                            valor: total,
                        })),
                        vencimientos: vencimientos.map(({ nombre, total }) => ({
                            nombre,
                            valor: total,
                        })),
                    }}
                />
                <section className="space-y-4" aria-labelledby="clientes-ia">
                    <div>
                        <h2 id="clientes-ia" className="text-lg font-semibold">
                            Clientes para contactar
                        </h2>
                        <p className="text-muted-foreground text-sm">
                            Ordenados por probabilidad estimada.{' '}
                            {clientes.total} resultados.
                        </p>
                    </div>
                    <Form
                        action={destino}
                        method="get"
                        className="bg-card grid items-end gap-3 rounded-2xl border p-4 sm:grid-cols-2 lg:grid-cols-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="space-y-2">
                                    <Label htmlFor="buscar-ia">
                                        Cliente o documento
                                    </Label>
                                    <Input
                                        id="buscar-ia"
                                        name="buscar"
                                        defaultValue={filters.buscar}
                                        maxLength={200}
                                        className="min-h-11"
                                    />
                                    {errors.buscar && (
                                        <p
                                            role="alert"
                                            className="text-destructive text-sm"
                                        >
                                            {errors.buscar}
                                        </p>
                                    )}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="categoria-ia">
                                        Probabilidad estimada
                                    </Label>
                                    <NativeSelect
                                        id="categoria-ia"
                                        name="categoria"
                                        defaultValue={filters.categoria}
                                        className="min-h-11 w-full"
                                    >
                                        <option value="todas">Todas</option>
                                        <option value="alta">Alta</option>
                                        <option value="media">Media</option>
                                        <option value="baja">Baja</option>
                                    </NativeSelect>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="vigencia-ia">
                                        Antigüedad del cálculo
                                    </Label>
                                    <NativeSelect
                                        id="vigencia-ia"
                                        name="vigencia"
                                        defaultValue={filters.vigencia}
                                        className="min-h-11 w-full"
                                    >
                                        <option value="todas">Todos</option>
                                        <option value="actualizados">
                                            Hasta 30 días
                                        </option>
                                        <option value="desactualizados">
                                            Más de 30 días
                                        </option>
                                    </NativeSelect>
                                </div>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="min-h-11"
                                >
                                    <Search
                                        aria-hidden="true"
                                        className="size-4"
                                    />
                                    {processing
                                        ? 'Buscando…'
                                        : 'Filtrar clientes'}
                                </Button>
                            </>
                        )}
                    </Form>
                    {clientes.data.length === 0 ? (
                        <Empty
                            icon={<BrainCircuit />}
                            title={
                                resumen.total === 0
                                    ? 'Todavía no hay estimaciones'
                                    : 'No hay clientes con estos filtros'
                            }
                            description={
                                resumen.total === 0
                                    ? 'Las puntuaciones aparecerán al procesar el historial de compras.'
                                    : 'Prueba otra categoría o busca por documento.'
                            }
                        >
                            {resumen.total > 0 && (
                                <Link
                                    href={destino}
                                    className="focus-visible:ring-ring rounded-lg border px-4 py-3 text-sm focus-visible:ring-2"
                                >
                                    Limpiar filtros
                                </Link>
                            )}
                        </Empty>
                    ) : (
                        <ul className="grid gap-4 lg:grid-cols-2">
                            {clientes.data.map((cliente) => (
                                <li
                                    key={cliente.id}
                                    className="bg-card min-w-0 space-y-4 rounded-2xl border p-4 sm:p-5"
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <Link
                                                href={cliente.url}
                                                className="focus-visible:ring-ring block rounded py-2 font-semibold wrap-break-word underline-offset-4 hover:underline focus-visible:ring-2"
                                            >
                                                {cliente.cliente}
                                            </Link>
                                            <p className="text-muted-foreground text-xs">
                                                {cliente.documento}
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <p className="text-2xl font-semibold tabular-nums">
                                                {cliente.porcentaje}%
                                            </p>
                                            <p className="text-muted-foreground text-xs capitalize">
                                                Probabilidad {cliente.categoria}
                                            </p>
                                        </div>
                                    </div>
                                    <dl className="grid grid-cols-2 gap-3 text-sm">
                                        <div>
                                            <dt className="text-muted-foreground">
                                                Compras registradas
                                            </dt>
                                            <dd className="font-medium tabular-nums">
                                                {cliente.compras}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground">
                                                Importe histórico
                                            </dt>
                                            <dd className="font-medium tabular-nums">
                                                {moneda(cliente.monto)}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground">
                                                Días sin comprar al calcular
                                            </dt>
                                            <dd>{cliente.recencia}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground">
                                                Calculado el
                                            </dt>
                                            <dd>
                                                {fecha(cliente.calculado)}
                                                {cliente.desactualizado && (
                                                    <span className="text-destructive block font-medium">
                                                        Más de 30 días
                                                    </span>
                                                )}
                                            </dd>
                                        </div>
                                    </dl>
                                    <p className="text-muted-foreground text-sm leading-relaxed">
                                        {cliente.resumen ||
                                            'Sin explicación registrada para esta puntuación.'}
                                    </p>
                                    {cliente.razones.length > 0 && (
                                        <details className="text-sm">
                                            <summary className="focus-visible:ring-ring cursor-pointer rounded py-3 focus-visible:ring-2">
                                                Ver factores del cálculo
                                            </summary>
                                            <ul className="text-muted-foreground space-y-2 pt-2">
                                                {cliente.razones.map(
                                                    (razon, indice) => (
                                                        <li key={indice}>
                                                            {razon.a_favor
                                                                ? 'A favor'
                                                                : 'En contra'}
                                                            : {razon.texto}
                                                        </li>
                                                    ),
                                                )}
                                            </ul>
                                        </details>
                                    )}
                                    <div className="text-muted-foreground flex items-center gap-2 border-t pt-3 text-sm">
                                        <CalendarClock
                                            aria-hidden="true"
                                            className="size-4 shrink-0"
                                        />
                                        <p>
                                            {cliente.proximoVencimiento
                                                ? `Próximo certificado: ${fecha(cliente.proximoVencimiento)}`
                                                : 'Sin vencimiento de certificado próximo registrado'}
                                        </p>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                    {clientes.last_page > 1 && (
                        <nav
                            aria-label="Páginas de clientes"
                            className="flex items-center justify-between gap-3"
                        >
                            {clientes.prev_page_url ? (
                                <Link
                                    href={clientes.prev_page_url}
                                    className="focus-visible:ring-ring rounded-xl border px-4 py-3 text-sm focus-visible:ring-2"
                                >
                                    Anterior
                                </Link>
                            ) : (
                                <span />
                            )}
                            <span className="text-muted-foreground text-sm">
                                {clientes.current_page} de {clientes.last_page}
                            </span>
                            {clientes.next_page_url ? (
                                <Link
                                    href={clientes.next_page_url}
                                    className="focus-visible:ring-ring rounded-xl border px-4 py-3 text-sm focus-visible:ring-2"
                                >
                                    Siguiente
                                </Link>
                            ) : (
                                <span />
                            )}
                        </nav>
                    )}
                </section>
            </div>
        </GerenteLayout>
    );
}
