import { router, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    CheckCircle2,
    Clock,
    Filter,
    Inbox,
    MinusCircle,
    PlusCircle,
    RotateCcw,
    TrendingDown,
    Wallet,
} from 'lucide-react';
import { useState } from 'react';

import GerenteLayout from '@/layouts/gerente-layout';

type CashRegisterItem = {
    id: number;
    vendedor: {
        id: number;
        name: string;
    };
    sede: string;
    estado: string;
    fecha_apertura: string;
    fecha_cierre: string | null;
    monto_apertura: number;
    monto_contado_cierre: number | null;
    monto_esperado_calculado: number | null;
    diferencia: number | null;
    tipo_diferencia: 'cuadrado' | 'sobrante' | 'faltante' | null;
    observacion: string | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedCashRegisters = {
    data: CashRegisterItem[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    total: number;
};

type CashFilters = {
    vendedor_id: string | null;
    sede_id: string | null;
    estado: string;
    con_diferencia: boolean;
    fecha_desde: string | null;
    fecha_hasta: string | null;
};

type CashKpis = {
    turnosHoy: number;
    turnosAbiertos: number;
    totalDiferenciasMes: number;
    turnosConDescuadreMes: number;
};

type PageProps = {
    currentTeam: { slug: string };
    cajas: PaginatedCashRegisters;
    filters: CashFilters;
    kpis: CashKpis;
    vendedores: Array<{ id: number; name: string }>;
    sedes: Array<{ id: number; nombre: string }>;
    [key: string]: unknown;
};

function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('es-PE', {
        style: 'currency',
        currency: 'PEN',
        minimumFractionDigits: 2,
    }).format(amount);
}

export default function CajasConsolidadasIndex() {
    const { currentTeam, cajas, filters, kpis, vendedores, sedes } =
        usePage<PageProps>().props;

    const [vendedorId, setVendedorId] = useState(filters.vendedor_id || '');
    const [sedeId, setSedeId] = useState(filters.sede_id || '');
    const [estado, setEstado] = useState(filters.estado || 'todos');
    const [conDiferencia, setConDiferencia] = useState(
        filters.con_diferencia || false,
    );
    const [fechaDesde, setFechaDesde] = useState(filters.fecha_desde || '');
    const [fechaHasta, setFechaHasta] = useState(filters.fecha_hasta || '');

    const applyFilters = () => {
        router.get(
            `/${currentTeam.slug}/gerente/cajas`,
            {
                vendedor_id: vendedorId || undefined,
                sede_id: sedeId || undefined,
                estado,
                con_diferencia: conDiferencia ? '1' : undefined,
                fecha_desde: fechaDesde || undefined,
                fecha_hasta: fechaHasta || undefined,
            },
            { preserveState: true },
        );
    };

    const resetFilters = () => {
        setVendedorId('');
        setSedeId('');
        setEstado('todos');
        setConDiferencia(false);
        setFechaDesde('');
        setFechaHasta('');
        router.get(
            `/${currentTeam.slug}/gerente/cajas`,
            {},
            { preserveState: true },
        );
    };

    return (
        <GerenteLayout title="Caja Consolidada">
            <div className="space-y-6">
                {/* Cabecera */}
                <div>
                    <h1 className="text-foreground font-['Oswald',sans-serif] text-2xl font-bold tracking-wide uppercase">
                        Caja Consolidada & Control de Arqueos
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Supervisión de turnos de caja de todos los vendedores,
                        validación de diferencias y arqueos ciegos.
                    </p>
                </div>

                {/* KPI Cards */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="border-border bg-card rounded-xl border p-4 shadow-xs">
                        <div className="text-muted-foreground flex items-center justify-between text-xs">
                            <span className="font-medium uppercase">
                                Turnos Hoy
                            </span>
                            <Clock className="size-4 text-blue-600" />
                        </div>
                        <div className="text-foreground mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold">
                            {kpis.turnosHoy}
                        </div>
                        <p className="text-muted-foreground mt-0.5 text-[11px]">
                            Aperturas registradas hoy
                        </p>
                    </div>

                    <div className="border-border bg-card rounded-xl border p-4 shadow-xs">
                        <div className="text-muted-foreground flex items-center justify-between text-xs">
                            <span className="font-medium uppercase">
                                Cajas Abiertas Ahora
                            </span>
                            <Wallet className="text-success-strong size-4" />
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-emerald-700">
                            {kpis.turnosAbiertos}
                        </div>
                        <p className="text-muted-foreground mt-0.5 text-[11px]">
                            Puntos de cobro activos
                        </p>
                    </div>

                    <div className="border-border bg-card rounded-xl border p-4 shadow-xs">
                        <div className="text-muted-foreground flex items-center justify-between text-xs">
                            <span className="font-medium uppercase">
                                Descuadres en el Mes
                            </span>
                            <AlertCircle className="text-warning-strong size-4" />
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-amber-700">
                            {kpis.turnosConDescuadreMes}
                        </div>
                        <p className="text-muted-foreground mt-0.5 text-[11px]">
                            Cierres con faltante o sobrante
                        </p>
                    </div>

                    <div className="border-border bg-card rounded-xl border p-4 shadow-xs">
                        <div className="text-muted-foreground flex items-center justify-between text-xs">
                            <span className="font-medium uppercase">
                                Diferencia Neta Acumulada
                            </span>
                            <TrendingDown className="text-primary-strong size-4" />
                        </div>
                        <div
                            className={`mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold ${
                                kpis.totalDiferenciasMes < 0
                                    ? 'text-primary-strong'
                                    : kpis.totalDiferenciasMes > 0
                                      ? 'text-warning-strong'
                                      : 'text-emerald-700'
                            }`}
                        >
                            {formatCurrency(kpis.totalDiferenciasMes)}
                        </div>
                        <p className="text-muted-foreground mt-0.5 text-[11px]">
                            Balance neto en el mes actual
                        </p>
                    </div>
                </div>

                {/* Filtros */}
                <div className="border-border bg-card space-y-3 rounded-xl border p-4 shadow-xs">
                    <div className="grid grid-cols-1 gap-3 text-xs sm:grid-cols-2 lg:grid-cols-5">
                        <div>
                            <label className="text-foreground/80 block font-semibold">
                                Vendedor
                            </label>
                            <select
                                value={vendedorId}
                                onChange={(e) => setVendedorId(e.target.value)}
                                className="border-border bg-muted/40 focus:border-primary mt-1 w-full rounded-lg border px-3 py-2 text-xs focus:outline-none"
                            >
                                <option value="">Todos los vendedores</option>
                                {vendedores.map((v) => (
                                    <option key={v.id} value={v.id}>
                                        {v.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="text-foreground/80 block font-semibold">
                                Sede
                            </label>
                            <select
                                value={sedeId}
                                onChange={(e) => setSedeId(e.target.value)}
                                className="border-border bg-muted/40 focus:border-primary mt-1 w-full rounded-lg border px-3 py-2 text-xs focus:outline-none"
                            >
                                <option value="">Todas las sedes</option>
                                {sedes.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.nombre}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="text-foreground/80 block font-semibold">
                                Estado
                            </label>
                            <select
                                value={estado}
                                onChange={(e) => setEstado(e.target.value)}
                                className="border-border bg-muted/40 focus:border-primary mt-1 w-full rounded-lg border px-3 py-2 text-xs focus:outline-none"
                            >
                                <option value="todos">Todos los estados</option>
                                <option value="abierto">Solo Abiertas</option>
                                <option value="cerrado">Solo Cerradas</option>
                            </select>
                        </div>

                        <div>
                            <label className="text-foreground/80 block font-semibold">
                                Fecha Desde
                            </label>
                            <input
                                type="date"
                                value={fechaDesde}
                                onChange={(e) => setFechaDesde(e.target.value)}
                                className="border-border bg-muted/40 focus:border-primary mt-1 w-full rounded-lg border px-3 py-1.5 text-xs focus:outline-none"
                            />
                        </div>

                        <div>
                            <label className="text-foreground/80 block font-semibold">
                                Fecha Hasta
                            </label>
                            <input
                                type="date"
                                value={fechaHasta}
                                onChange={(e) => setFechaHasta(e.target.value)}
                                className="border-border bg-muted/40 focus:border-primary mt-1 w-full rounded-lg border px-3 py-1.5 text-xs focus:outline-none"
                            />
                        </div>
                    </div>

                    <div className="border-border flex flex-wrap items-center justify-between gap-3 border-t pt-2">
                        <label className="text-foreground/80 flex cursor-pointer items-center gap-2 text-xs font-medium">
                            <input
                                type="checkbox"
                                checked={conDiferencia}
                                onChange={(e) =>
                                    setConDiferencia(e.target.checked)
                                }
                                className="border-border text-primary-strong rounded"
                            />
                            <span>
                                Ver solo turnos con diferencia (faltante o
                                sobrante)
                            </span>
                        </label>

                        <div className="flex items-center gap-2">
                            <button
                                type="button"
                                onClick={resetFilters}
                                className="border-border bg-card text-muted-foreground hover:bg-background rounded-lg border px-3 py-1.5 text-xs font-semibold"
                            >
                                Limpiar
                            </button>
                            <button
                                type="button"
                                onClick={applyFilters}
                                className="bg-foreground text-background hover:bg-foreground/90 inline-flex items-center gap-1.5 rounded-lg px-4 py-1.5 text-xs font-semibold shadow-xs transition-colors"
                            >
                                <Filter className="size-3.5" />
                                <span>Aplicar Filtros</span>
                            </button>
                        </div>
                    </div>
                </div>

                {/* Tabla de Cajas */}
                <div className="border-border bg-card overflow-hidden rounded-xl border shadow-xs">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="border-border bg-muted/40 text-muted-foreground border-b text-[11px] font-semibold tracking-wider uppercase">
                                <tr>
                                    <th className="px-4 py-3">Vendedor</th>
                                    <th className="px-4 py-3">Sede</th>
                                    <th className="px-4 py-3">Apertura</th>
                                    <th className="px-4 py-3">Cierre</th>
                                    <th className="px-4 py-3 text-right">
                                        Fondo Apertura
                                    </th>
                                    <th className="px-4 py-3 text-right">
                                        Contado Cierre
                                    </th>
                                    <th className="px-4 py-3 text-right">
                                        Calculado Sistema
                                    </th>
                                    <th className="px-4 py-3 text-center">
                                        Diferencia
                                    </th>
                                    <th className="px-4 py-3 text-center">
                                        Estado
                                    </th>
                                    <th className="px-4 py-3">Observación</th>
                                </tr>
                            </thead>
                            <tbody className="divide-border divide-y">
                                {cajas.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={10}
                                            className="text-muted-foreground py-12 text-center"
                                        >
                                            <div className="flex flex-col items-center justify-center gap-2">
                                                <div className="bg-muted/50 flex size-10 items-center justify-center rounded-full">
                                                    <Inbox className="text-muted-foreground size-5" />
                                                </div>
                                                <p className="text-foreground text-sm font-semibold">
                                                    No se encontraron turnos de
                                                    caja
                                                </p>
                                                <p className="text-muted-foreground max-w-sm text-xs">
                                                    {vendedorId ||
                                                    sedeId ||
                                                    estado !== 'todos' ||
                                                    conDiferencia ||
                                                    fechaDesde ||
                                                    fechaHasta
                                                        ? 'No hay registros que coincidan con los filtros aplicados.'
                                                        : 'Aún no se han registrado turnos de caja para este periodo.'}
                                                </p>
                                                {(vendedorId ||
                                                    sedeId ||
                                                    estado !== 'todos' ||
                                                    conDiferencia ||
                                                    fechaDesde ||
                                                    fechaHasta) && (
                                                    <button
                                                        type="button"
                                                        onClick={resetFilters}
                                                        className="border-border bg-card text-foreground hover:bg-muted mt-2 inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold shadow-xs transition-colors"
                                                    >
                                                        <RotateCcw className="size-3.5" />
                                                        <span>
                                                            Limpiar filtros
                                                        </span>
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    cajas.data.map((c) => (
                                        <tr
                                            key={c.id}
                                            className="hover:bg-muted/40 transition-colors"
                                        >
                                            <td className="text-foreground px-4 py-3 font-semibold">
                                                {c.vendedor.name}
                                            </td>
                                            <td className="text-muted-foreground px-4 py-3">
                                                {c.sede}
                                            </td>
                                            <td className="text-foreground/80 px-4 py-3 font-mono text-[11px]">
                                                {c.fecha_apertura}
                                            </td>
                                            <td className="text-foreground/80 px-4 py-3 font-mono text-[11px]">
                                                {c.fecha_cierre || '—'}
                                            </td>
                                            <td className="text-foreground px-4 py-3 text-right font-mono">
                                                {formatCurrency(
                                                    c.monto_apertura,
                                                )}
                                            </td>
                                            <td className="text-foreground px-4 py-3 text-right font-mono font-medium">
                                                {c.monto_contado_cierre !== null
                                                    ? formatCurrency(
                                                          c.monto_contado_cierre,
                                                      )
                                                    : '—'}
                                            </td>
                                            <td className="text-muted-foreground px-4 py-3 text-right font-mono">
                                                {c.monto_esperado_calculado !==
                                                null
                                                    ? formatCurrency(
                                                          c.monto_esperado_calculado,
                                                      )
                                                    : '—'}
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                {c.estado === 'abierto' ? (
                                                    <span className="rounded-full bg-blue-50 px-2 py-0.5 font-mono text-[10px] font-semibold text-blue-700">
                                                        En curso
                                                    </span>
                                                ) : c.tipo_diferencia ===
                                                  'cuadrado' ? (
                                                    <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 font-mono text-[10px] font-bold text-emerald-700">
                                                        <CheckCircle2 className="size-3" />
                                                        Cuadra exacto
                                                    </span>
                                                ) : c.tipo_diferencia ===
                                                  'faltante' ? (
                                                    <span className="text-primary-strong inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-0.5 font-mono text-[11px] font-bold">
                                                        <MinusCircle className="size-3" />
                                                        {formatCurrency(
                                                            c.diferencia!,
                                                        )}
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 font-mono text-[11px] font-bold text-amber-800">
                                                        <PlusCircle className="size-3" />
                                                        +
                                                        {formatCurrency(
                                                            c.diferencia!,
                                                        )}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                <span
                                                    className={`inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold uppercase ${
                                                        c.estado === 'abierto'
                                                            ? 'bg-emerald-100 text-emerald-800'
                                                            : 'bg-zinc-100 text-zinc-700'
                                                    }`}
                                                >
                                                    {c.estado}
                                                </span>
                                            </td>
                                            <td className="text-muted-foreground max-w-xs truncate px-4 py-3 text-[11px]">
                                                {c.observacion || '—'}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Paginación */}
                    {cajas.links && cajas.links.length > 3 && (
                        <div className="border-border bg-muted/40 text-muted-foreground flex items-center justify-between border-t px-4 py-3 text-xs">
                            <div>Total: {cajas.total} turnos registrados</div>
                            <div className="flex items-center gap-1">
                                {cajas.links.map((link, i) => {
                                    if (!link.url) {
                                        return (
                                            <span
                                                key={i}
                                                dangerouslySetInnerHTML={{
                                                    __html: link.label,
                                                }}
                                                className="px-2.5 py-1 text-zinc-400"
                                            />
                                        );
                                    }
                                    return (
                                        <button
                                            key={i}
                                            type="button"
                                            onClick={() =>
                                                router.get(
                                                    link.url!,
                                                    {},
                                                    { preserveState: true },
                                                )
                                            }
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                            className={`rounded-md px-2.5 py-1 text-xs transition-colors ${
                                                link.active
                                                    ? 'bg-foreground text-background font-bold shadow-xs'
                                                    : 'text-foreground/80 hover:bg-muted/40'
                                            }`}
                                        />
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </GerenteLayout>
    );
}
