import { pageTitleClass } from '@/components/page-header';
import { Form, Link, usePage } from '@inertiajs/react';
import { BrainCircuit, Search } from 'lucide-react';
import { useState } from 'react';

import {
    Apilada,
    cantidad,
    MiniLinea,
    Ranking,
    Tarjeta,
    Vacio,
} from '@/components/bi';
import { Button } from '@/components/ui/button';
import { Empty } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import GerenteLayout from '@/layouts/gerente-layout';
import { cn, soles } from '@/lib/utils';
import { inteligencia } from '@/routes/gerente';
import ChispaRoutes from '@/routes/gerente/chispa';

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
    filters: {
        buscar: string;
        categoria: string;
        vigencia: string;
        calendario: string;
    };
    resumen: {
        total: number;
        sinPuntuacion: number;
        calculado: string | null;
        desactualizados: number;
        fueraDelCalendario: number;
    };
    categorias: { nombre: string; total: number }[];
    modelo: {
        tipo: string;
        rocAuc: number | null;
        top100: number | null;
        muestras: number | null;
        entrenado: string | null;
        importancia: { nombre: string; porcentaje: number }[];
        historial: number[];
    } | null;
    chispa: {
        total: number;
        utiles: number;
        sinRespuesta: number;
        ultimas: { pregunta: string; rol: string }[];
    };
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

const fecha = (valor: string | null) =>
    valor
        ? new Intl.DateTimeFormat('es-PE', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
          }).format(new Date(`${valor.slice(0, 10)}T12:00:00`))
        : 'Sin registrar';

/** Días desde hoy hasta la fecha (para "en 12 días"). */
function diasHasta(valor: string): number {
    const hoy = new Date();
    hoy.setHours(12, 0, 0, 0);

    return Math.round(
        (new Date(`${valor.slice(0, 10)}T12:00:00`).getTime() - hoy.getTime()) /
            86_400_000,
    );
}

/** Lectura simple del ROC-AUC para quien no es técnico. */
function calidad(auc: number): { texto: string; clase: string } {
    if (auc >= 0.8) {
        return { texto: 'muy bueno', clase: 'text-success-strong' };
    }
    if (auc >= 0.7) {
        return { texto: 'bueno', clase: 'text-success-strong' };
    }

    return { texto: 'regular', clase: 'text-warning-strong' };
}

const CATEGORIAS = [
    ['alta', 'Alta'],
    ['media', 'Media'],
    ['baja', 'Baja'],
    ['todas', 'Todas'],
] as const;

/**
 * Recompra e IA: a quién llamar fuera de la recarga anual (esa está en "Por
 * vencer" del vendedor), por qué lo dice el modelo, cómo está el modelo y qué
 * no supo responder Chispa.
 */
export default function Inteligencia() {
    const {
        currentTeam,
        filters,
        resumen,
        categorias,
        modelo,
        chispa,
        clientes,
    } = usePage<Props>().props;
    const destino = inteligencia.url({ current_team: currentTeam.slug });
    const [elegidoId, setElegidoId] = useState<number | null>(
        clientes.data[0]?.id ?? null,
    );
    const elegido =
        clientes.data.find((cliente) => cliente.id === elegidoId) ??
        clientes.data[0];
    const [alta, media, baja] = categorias.map((c) => c.total);

    return (
        <GerenteLayout title="Recompra e IA">
            <div className="flex flex-col gap-4">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p className="text-muted-foreground font-mono text-xs tracking-widest uppercase">
                            Gerencia · Recompra e IA
                        </p>
                        <h1 className={`${pageTitleClass} mt-1`}>
                            A quién llamar primero
                        </h1>
                        <p className="text-muted-foreground mt-1 max-w-3xl text-sm">
                            Clientes que probablemente vuelvan aunque no les
                            toque por fecha. La recarga anual está en «Por
                            vencer» del vendedor.
                        </p>
                    </div>
                    <span className="border-border bg-card text-muted-foreground rounded-full border px-3 py-2 text-sm">
                        Última puntuación:{' '}
                        <b className="text-foreground">
                            {resumen.calculado
                                ? fecha(resumen.calculado)
                                : 'todavía no hay'}
                        </b>{' '}
                        · se recalcula cada noche
                    </span>
                </header>

                <section
                    aria-label="Resumen"
                    className="grid grid-cols-[repeat(auto-fit,minmax(190px,1fr))] gap-3"
                >
                    <div className="border-border bg-card flex flex-col gap-1 rounded-2xl border px-4 py-3.5">
                        <span className="text-muted-foreground text-sm">
                            Clientes evaluados
                        </span>
                        <b className="text-2xl tabular-nums">{resumen.total}</b>
                        <span className="text-muted-foreground text-xs">
                            {resumen.sinPuntuacion} sin puntuación
                        </span>
                    </div>
                    <div className="border-border bg-card flex flex-col gap-2 rounded-2xl border px-4 py-3.5">
                        <span className="text-muted-foreground text-sm">
                            Probabilidad de recompra
                        </span>
                        {resumen.total > 0 ? (
                            <Apilada
                                partes={[
                                    {
                                        nombre: 'Alta',
                                        valor: alta,
                                        color: 'bi-1',
                                    },
                                    {
                                        nombre: 'Media',
                                        valor: media,
                                        color: 'bi-2',
                                    },
                                    {
                                        nombre: 'Baja',
                                        valor: baja,
                                        color: 'bi-neutro',
                                    },
                                ]}
                            />
                        ) : (
                            <span className="text-muted-foreground text-xs">
                                Sin puntuaciones todavía
                            </span>
                        )}
                    </div>
                    <div className="flex flex-col gap-1 rounded-2xl border border-[color-mix(in_oklab,var(--bi-1)_45%,transparent)] bg-[color-mix(in_oklab,var(--bi-1)_10%,var(--card))] px-4 py-3.5">
                        <span className="text-foreground text-sm font-medium">
                            Fuera del calendario
                        </span>
                        <b className="text-2xl tabular-nums">
                            {resumen.fueraDelCalendario}
                        </b>
                        <span className="text-muted-foreground text-xs">
                            alta probabilidad sin extintores por vencer:
                            recargas por uso u otras compras
                        </span>
                    </div>
                    <div className="border-border bg-card flex flex-col gap-1 rounded-2xl border px-4 py-3.5">
                        <span className="text-muted-foreground text-sm">
                            Cálculos de más de 30 días
                        </span>
                        <b className="text-2xl tabular-nums">
                            {resumen.desactualizados}
                        </b>
                        <span
                            className={cn(
                                'text-xs',
                                resumen.desactualizados === 0
                                    ? 'text-success-strong font-semibold'
                                    : 'text-destructive-strong font-semibold',
                            )}
                        >
                            {resumen.desactualizados === 0
                                ? 'Todo al día'
                                : 'Pide recalcular antes de llamar'}
                        </span>
                    </div>
                </section>

                <div
                    className={cn(
                        'grid items-start gap-3.5',
                        elegido &&
                            'xl:grid-cols-[minmax(0,2fr)_minmax(300px,1fr)]',
                    )}
                >
                    <section
                        aria-labelledby="clientes-ia"
                        className="border-border bg-card min-w-0 rounded-2xl border"
                    >
                        <h2 id="clientes-ia" className="sr-only">
                            Clientes para contactar
                        </h2>
                        <Form
                            action={destino}
                            method="get"
                            className="border-border flex flex-wrap items-end gap-2.5 border-b p-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="flex min-w-[200px] flex-1 flex-col gap-1.5">
                                        <Label htmlFor="buscar-ia">
                                            Cliente o documento
                                        </Label>
                                        <Input
                                            id="buscar-ia"
                                            name="buscar"
                                            defaultValue={filters.buscar}
                                            maxLength={200}
                                            placeholder="Razón social, RUC o DNI"
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
                                    <fieldset className="flex flex-col gap-1.5">
                                        <legend className="mb-1.5 text-sm font-medium">
                                            Probabilidad
                                        </legend>
                                        <div className="flex gap-1.5">
                                            {CATEGORIAS.map(
                                                ([valor, texto]) => (
                                                    <label
                                                        key={valor}
                                                        className="border-border has-[:checked]:border-primary has-[:checked]:bg-primary/10 has-[:checked]:text-primary-strong has-[:focus-visible]:ring-ring flex min-h-11 cursor-pointer items-center rounded-xl border px-3.5 text-sm font-medium has-[:focus-visible]:ring-2"
                                                    >
                                                        <input
                                                            type="radio"
                                                            name="categoria"
                                                            value={valor}
                                                            defaultChecked={
                                                                filters.categoria ===
                                                                valor
                                                            }
                                                            className="sr-only"
                                                        />
                                                        {texto}
                                                    </label>
                                                ),
                                            )}
                                        </div>
                                    </fieldset>
                                    <div className="flex flex-col gap-1.5">
                                        <Label htmlFor="vigencia-ia">
                                            Cálculo
                                        </Label>
                                        <NativeSelect
                                            id="vigencia-ia"
                                            name="vigencia"
                                            defaultValue={filters.vigencia}
                                            className="min-h-11"
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
                                    <label className="flex min-h-11 items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            name="calendario"
                                            value="fuera"
                                            defaultChecked={
                                                filters.calendario === 'fuera'
                                            }
                                            className="accent-primary size-4"
                                        />
                                        Solo fuera del calendario
                                    </label>
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="min-h-11"
                                    >
                                        <Search
                                            aria-hidden="true"
                                            className="size-4"
                                        />
                                        {processing ? 'Buscando…' : 'Filtrar'}
                                    </Button>
                                </>
                            )}
                        </Form>

                        {clientes.data.length === 0 ? (
                            <div className="p-4">
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
                                            : 'Prueba otra probabilidad o busca por documento.'
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
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <caption className="sr-only">
                                        Clientes ordenados por probabilidad
                                        estimada. Elige uno para ver por qué.
                                    </caption>
                                    <thead className="text-muted-foreground text-left text-xs">
                                        <tr>
                                            <th
                                                scope="col"
                                                className="px-4 py-2.5 font-medium"
                                            >
                                                Cliente
                                            </th>
                                            <th
                                                scope="col"
                                                className="w-44 px-2 py-2.5 font-medium"
                                            >
                                                Probabilidad
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-2 py-2.5 text-right font-medium"
                                            >
                                                Compras
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-2 py-2.5 text-right font-medium"
                                            >
                                                Importe
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-2 py-2.5 text-right font-medium"
                                            >
                                                Sin comprar
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-2.5 font-medium"
                                            >
                                                Próximo certificado
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-border divide-y border-t">
                                        {clientes.data.map((cliente) => {
                                            const activo =
                                                cliente.id === elegido?.id;
                                            const dias =
                                                cliente.proximoVencimiento
                                                    ? diasHasta(
                                                          cliente.proximoVencimiento,
                                                      )
                                                    : null;

                                            return (
                                                <tr
                                                    key={cliente.id}
                                                    className={cn(
                                                        activo &&
                                                            'bg-muted/70 shadow-[inset_0_0_0_1px_var(--bi-1)]',
                                                    )}
                                                >
                                                    <td className="px-4 py-2.5">
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                setElegidoId(
                                                                    cliente.id,
                                                                )
                                                            }
                                                            aria-pressed={
                                                                activo
                                                            }
                                                            className="focus-visible:ring-ring rounded text-left font-semibold hover:underline focus-visible:ring-2 focus-visible:outline-none"
                                                        >
                                                            {cliente.cliente}
                                                        </button>
                                                        <span className="text-muted-foreground block font-mono text-xs">
                                                            {cliente.documento}
                                                        </span>
                                                    </td>
                                                    <td className="px-2 py-2.5">
                                                        <span className="flex items-center gap-2">
                                                            <span
                                                                aria-hidden="true"
                                                                className="block h-2 flex-1 rounded-full"
                                                                style={{
                                                                    background:
                                                                        'var(--bi-pista)',
                                                                }}
                                                            >
                                                                <span
                                                                    className="block h-2 rounded-full"
                                                                    style={{
                                                                        width: `${cliente.porcentaje}%`,
                                                                        background:
                                                                            'var(--bi-1)',
                                                                    }}
                                                                />
                                                            </span>
                                                            <b className="tabular-nums">
                                                                {
                                                                    cliente.porcentaje
                                                                }
                                                                %
                                                            </b>
                                                        </span>
                                                    </td>
                                                    <td className="px-2 py-2.5 text-right tabular-nums">
                                                        {cliente.compras}
                                                    </td>
                                                    <td className="px-2 py-2.5 text-right tabular-nums">
                                                        {soles(cliente.monto)}
                                                    </td>
                                                    <td className="px-2 py-2.5 text-right tabular-nums">
                                                        {cantidad(
                                                            cliente.recencia,
                                                            'día',
                                                            'días',
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-2.5">
                                                        {dias === null ? (
                                                            <span className="text-muted-foreground">
                                                                Sin certificado
                                                                próximo
                                                            </span>
                                                        ) : dias <= 30 ? (
                                                            <span className="bg-destructive/10 text-destructive-strong rounded-full px-2 py-0.5 text-xs font-semibold">
                                                                {dias === 0
                                                                    ? 'hoy'
                                                                    : `en ${cantidad(dias, 'día', 'días')}`}
                                                            </span>
                                                        ) : (
                                                            `en ${dias} días`
                                                        )}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        {clientes.total > 0 && (
                            <nav
                                aria-label="Páginas de clientes"
                                className="border-border text-muted-foreground flex items-center justify-between gap-3 border-t px-4 py-3 text-sm"
                            >
                                <span>
                                    {cantidad(
                                        clientes.total,
                                        'cliente',
                                        'clientes',
                                    )}{' '}
                                    · página {clientes.current_page} de{' '}
                                    {clientes.last_page}
                                </span>
                                <span className="flex gap-2">
                                    {clientes.prev_page_url && (
                                        <Link
                                            href={clientes.prev_page_url}
                                            className="focus-visible:ring-ring rounded-xl border px-4 py-2.5 focus-visible:ring-2"
                                        >
                                            Anterior
                                        </Link>
                                    )}
                                    {clientes.next_page_url && (
                                        <Link
                                            href={clientes.next_page_url}
                                            className="text-foreground focus-visible:ring-ring rounded-xl border px-4 py-2.5 focus-visible:ring-2"
                                        >
                                            Siguiente
                                        </Link>
                                    )}
                                </span>
                            </nav>
                        )}
                    </section>

                    {elegido ? (
                        <aside
                            aria-label="Detalle del cliente elegido"
                            aria-live="polite"
                            className="border-border bg-card flex flex-col gap-3.5 rounded-2xl border p-5"
                        >
                            <div className="flex items-start justify-between gap-2">
                                <div>
                                    <b className="text-base">
                                        {elegido.cliente}
                                    </b>
                                    <span className="text-muted-foreground block text-xs">
                                        Calculado el {fecha(elegido.calculado)}
                                        {elegido.desactualizado &&
                                            ' · más de 30 días'}
                                    </span>
                                </div>
                                <span className="text-3xl font-bold tabular-nums">
                                    {elegido.porcentaje}%
                                </span>
                            </div>
                            <div className="flex flex-col gap-1 rounded-xl border border-[color-mix(in_oklab,var(--bi-1)_40%,transparent)] bg-[color-mix(in_oklab,var(--bi-1)_10%,var(--card))] p-3">
                                <span className="text-xs font-semibold tracking-wide uppercase">
                                    Lo que dice la IA
                                </span>
                                <p className="text-sm leading-relaxed">
                                    {elegido.resumen ||
                                        'Sin explicación registrada para esta puntuación.'}
                                </p>
                            </div>
                            {elegido.razones.length > 0 && (
                                <div className="flex flex-col gap-2">
                                    <span className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">
                                        A favor y en contra
                                    </span>
                                    <ul className="flex flex-col gap-1.5 text-sm">
                                        {elegido.razones.map((razon) => (
                                            <li
                                                key={razon.texto}
                                                className="flex gap-2"
                                            >
                                                <span
                                                    className={cn(
                                                        'font-bold',
                                                        razon.a_favor
                                                            ? 'text-[var(--bi-1)]'
                                                            : 'text-[var(--bi-2)]',
                                                    )}
                                                >
                                                    {razon.a_favor ? '+' : '−'}
                                                    <span className="sr-only">
                                                        {razon.a_favor
                                                            ? 'A favor:'
                                                            : 'En contra:'}
                                                    </span>
                                                </span>
                                                {razon.texto}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                            <dl className="grid grid-cols-2 gap-2">
                                <div className="bg-muted/60 rounded-xl p-2.5">
                                    <dt className="text-muted-foreground text-xs">
                                        Importe histórico
                                    </dt>
                                    <dd className="font-bold tabular-nums">
                                        {soles(elegido.monto)}
                                    </dd>
                                </div>
                                <div className="bg-muted/60 rounded-xl p-2.5">
                                    <dt className="text-muted-foreground text-xs">
                                        Próximo certificado
                                    </dt>
                                    <dd className="font-bold">
                                        {elegido.proximoVencimiento
                                            ? fecha(elegido.proximoVencimiento)
                                            : 'Ninguno'}
                                    </dd>
                                </div>
                            </dl>
                            <Link
                                href={elegido.url}
                                className="bg-primary text-primary-foreground focus-visible:ring-ring flex min-h-11 items-center justify-center rounded-xl text-sm font-semibold focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                            >
                                Ver ficha del cliente
                            </Link>
                        </aside>
                    ) : null}
                </div>

                <div className="grid gap-3.5 lg:grid-cols-3">
                    <Tarjeta
                        titulo="El modelo de recompra"
                        extra={
                            modelo
                                ? modelo.tipo === 'xgboost'
                                    ? 'XGBoost'
                                    : 'Regresión logística'
                                : undefined
                        }
                    >
                        {modelo ? (
                            <>
                                <p className="text-muted-foreground text-sm leading-relaxed">
                                    Predice quién vuelve a comprar en los
                                    próximos 6 meses y lo explica en frases.
                                    Cada mes compara XGBoost con la regresión
                                    logística y se queda con el que acierta más.
                                </p>
                                <ol className="text-muted-foreground grid grid-cols-2 gap-1.5 text-center text-[11px] sm:grid-cols-4 lg:grid-cols-2 2xl:grid-cols-4">
                                    {[
                                        ['1 · Datos', 'historial y ventas'],
                                        ['2 · Entrena', 'el 1 de cada mes'],
                                        ['3 · Se mide', 'contra el futuro'],
                                        ['4 · Puntúa', 'cada noche'],
                                    ].map(([paso, detalle]) => (
                                        <li
                                            key={paso}
                                            className="bg-muted/60 rounded-lg px-1 py-2"
                                        >
                                            <b className="text-foreground block text-xs">
                                                {paso}
                                            </b>
                                            {detalle}
                                        </li>
                                    ))}
                                </ol>
                                <dl className="grid grid-cols-2 gap-2">
                                    <div className="bg-muted/60 rounded-xl p-2.5">
                                        <dt className="text-muted-foreground text-xs">
                                            Acierto al ordenar
                                        </dt>
                                        <dd className="text-lg font-bold tabular-nums">
                                            {modelo.rocAuc ?? '—'}{' '}
                                            {modelo.rocAuc !== null && (
                                                <span
                                                    className={cn(
                                                        'text-xs font-semibold',
                                                        calidad(modelo.rocAuc)
                                                            .clase,
                                                    )}
                                                >
                                                    {
                                                        calidad(modelo.rocAuc)
                                                            .texto
                                                    }
                                                </span>
                                            )}
                                        </dd>
                                    </div>
                                    <div className="bg-muted/60 rounded-xl p-2.5">
                                        <dt className="text-muted-foreground text-xs">
                                            De los 100 más probables
                                        </dt>
                                        <dd className="text-lg font-bold">
                                            {modelo.top100 === null
                                                ? '—'
                                                : `${modelo.top100} volvieron`}
                                        </dd>
                                    </div>
                                    <div className="bg-muted/60 rounded-xl p-2.5">
                                        <dt className="text-muted-foreground text-xs">
                                            Aprendió de
                                        </dt>
                                        <dd className="text-lg font-bold tabular-nums">
                                            {modelo.muestras === null
                                                ? '—'
                                                : `${new Intl.NumberFormat('es-PE').format(modelo.muestras)} casos`}
                                        </dd>
                                    </div>
                                    <div className="bg-muted/60 rounded-xl p-2.5">
                                        <dt className="text-muted-foreground text-xs">
                                            Entrenado el
                                        </dt>
                                        <dd className="text-lg font-bold">
                                            {fecha(modelo.entrenado)}
                                        </dd>
                                    </div>
                                </dl>
                                {modelo.historial.length > 1 && (
                                    <div className="flex items-center justify-between gap-2">
                                        <span className="text-muted-foreground text-xs">
                                            Acierto en los últimos{' '}
                                            {modelo.historial.length}{' '}
                                            entrenamientos:{' '}
                                            {modelo.historial[0]} →{' '}
                                            {
                                                modelo.historial[
                                                    modelo.historial.length - 1
                                                ]
                                            }
                                        </span>
                                        <MiniLinea serie={modelo.historial} />
                                    </div>
                                )}
                            </>
                        ) : (
                            <Vacio>
                                Todavía no hay un modelo entrenado. Carga el
                                histórico de compras y se entrenará el 1 de mes.
                            </Vacio>
                        )}
                    </Tarjeta>

                    <Tarjeta
                        titulo="Qué pesa más en la predicción"
                        extra="SHAP, toda la empresa"
                    >
                        {modelo && modelo.importancia.length > 0 ? (
                            <Ranking
                                datos={modelo.importancia.map((variable) => ({
                                    nombre: variable.nombre,
                                    valor: variable.porcentaje,
                                }))}
                                colorDe={(p) =>
                                    p.nombre.startsWith('Otros')
                                        ? 'bi-neutro'
                                        : 'bi-1'
                                }
                            />
                        ) : (
                            <Vacio>
                                El modelo vigente no trae la importancia de las
                                variables.
                            </Vacio>
                        )}
                    </Tarjeta>

                    <Tarjeta
                        titulo="Chispa, el asistente"
                        extra="responde con el manual de cada rol"
                    >
                        <dl className="grid grid-cols-3 gap-2">
                            <div className="bg-muted/60 rounded-xl p-2.5">
                                <dt className="text-muted-foreground text-xs">
                                    Preguntas
                                </dt>
                                <dd className="text-lg font-bold tabular-nums">
                                    {chispa.total}
                                </dd>
                            </div>
                            <div className="bg-muted/60 rounded-xl p-2.5">
                                <dt className="text-muted-foreground text-xs">
                                    Le sirvieron
                                </dt>
                                <dd className="text-lg font-bold tabular-nums">
                                    {chispa.utiles}%
                                </dd>
                            </div>
                            <div
                                className={cn(
                                    'rounded-xl p-2.5',
                                    chispa.sinRespuesta > 0
                                        ? 'bg-destructive/10 text-destructive-strong'
                                        : 'bg-muted/60',
                                )}
                            >
                                <dt className="text-xs">Sin respuesta</dt>
                                <dd className="text-lg font-bold tabular-nums">
                                    {chispa.sinRespuesta}
                                </dd>
                            </div>
                        </dl>
                        {chispa.ultimas.length > 0 && (
                            <div className="flex flex-col gap-1.5 text-sm">
                                <span className="text-muted-foreground text-xs">
                                    Lo último que no supo responder
                                </span>
                                {chispa.ultimas.map((ultima) => (
                                    <span
                                        key={ultima.pregunta}
                                        className="bg-muted/60 rounded-lg px-2.5 py-2"
                                    >
                                        «{ultima.pregunta}» · {ultima.rol}
                                    </span>
                                ))}
                            </div>
                        )}
                        <Link
                            href={ChispaRoutes.index.url(currentTeam.slug)}
                            className="border-border hover:bg-accent focus-visible:ring-ring flex min-h-11 items-center justify-center rounded-xl border text-sm font-semibold focus-visible:ring-2 focus-visible:outline-none"
                        >
                            Ver preguntas para mejorar el manual
                        </Link>
                    </Tarjeta>
                </div>
            </div>
        </GerenteLayout>
    );
}
