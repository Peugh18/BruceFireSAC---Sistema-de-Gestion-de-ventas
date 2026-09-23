import { router, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    Building2,
    Calendar,
    CheckCircle2,
    Clock,
    Filter,
    HelpCircle,
    MinusCircle,
    PlusCircle,
    TrendingDown,
    User,
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
                    <h1 className="font-['Oswald',sans-serif] text-2xl font-bold tracking-wide text-foreground uppercase">
                        Caja Consolidada & Control de Arqueos
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Supervisión de turnos de caja de todos los vendedores,
                        validación de diferencias y arqueos ciegos.
                    </p>
                </div>

                {/* KPI Cards */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between text-xs text-muted-foreground">
                            <span className="font-medium uppercase">
                                Turnos Hoy
                            </span>
                            <Clock className="size-4 text-blue-600" />
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-foreground">
                            {kpis.turnosHoy}
                        </div>
                        <p className="mt-0.5 text-[11px] text-muted-foreground">
                            Aperturas registradas hoy
                        </p>
                    </div>

                    <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between text-xs text-muted-foreground">
                            <span className="font-medium uppercase">
                                Cajas Abiertas Ahora
                            </span>
                            <Wallet className="size-4 text-emerald-600" />
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-emerald-700">
                            {kpis.turnosAbiertos}
                        </div>
                        <p className="mt-0.5 text-[11px] text-muted-foreground">
                            Puntos de cobro activos
                        </p>
                    </div>

                    <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between text-xs text-muted-foreground">
                            <span className="font-medium uppercase">
                                Descuadres en el Mes
                            </span>
                            <AlertCircle className="size-4 text-amber-600" />
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-amber-700">
                            {kpis.turnosConDescuadreMes}
                        </div>
                        <p className="mt-0.5 text-[11px] text-muted-foreground">
                            Cierres con faltante o sobrante
                        </p>
                    </div>

                    <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between text-xs text-muted-foreground">
                            <span className="font-medium uppercase">
                                Diferencia Neta Acumulada
                            </span>
                            <TrendingDown className="size-4 text-primary" />
                        </div>
                        <div
                            className={`mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold ${
                                kpis.totalDiferenciasMes < 0
                                    ? 'text-primary'
                                    : kpis.totalDiferenciasMes > 0
                                      ? 'text-amber-600'
                                      : 'text-emerald-700'
                            }`}
                        >
                            {formatCurrency(kpis.totalDiferenciasMes)}
                        </div>
                        <p className="mt-0.5 text-[11px] text-muted-foreground">
                            Balance neto en el mes actual
                        </p>
                    </div>
                </div>

                {/* Filtros */}
                <div className="space-y-3 rounded-xl border border-border bg-card p-4 shadow-xs">
                    <div className="grid grid-cols-1 gap-3 text-xs sm:grid-cols-2 lg:grid-cols-5">
                        <div>
                            <label className="block font-semibold text-foreground/80">
                                Vendedor
                            </label>
                            <select
                                value={vendedorId}
                                onChange={(e) => setVendedorId(e.target.value)}
                                className="mt-1 w-full rounded-lg border border-border bg-muted/40 px-3 py-2 text-xs focus:border-primary focus:outline-none"
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
                            <label className="block font-semibold text-foreground/80">
                                Sede
                            </label>
                            <select
                                value={sedeId}
                                onChange={(e) => setSedeId(e.target.value)}
                                className="mt-1 w-full rounded-lg border border-border bg-muted/40 px-3 py-2 text-xs focus:border-primary focus:outline-none"
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
                            <label className="block font-semibold text-foreground/80">
                                Estado
                            </label>
                            <select
                                value={estado}
                                onChange={(e) => setEstado(e.target.value)}
                                className="mt-1 w-full rounded-lg border border-border bg-muted/40 px-3 py-2 text-xs focus:border-primary focus:outline-none"
                            >
                                <option value="todos">Todos los estados</option>
                                <option value="abierto">Solo Abiertas</option>
                                <option value="cerrado">Solo Cerradas</option>
                            </select>
                        </div>

                        <div>
                            <label className="block font-semibold text-foreground/80">
                                Fecha Desde
                            </label>
                            <input
                                type="date"
                                value={fechaDesde}
                                onChange={(e) => setFechaDesde(e.target.value)}
                                className="mt-1 w-full rounded-lg border border-border bg-muted/40 px-3 py-1.5 text-xs focus:border-primary focus:outline-none"
                            />
                        </div>

                        <div>
                            <label className="block font-semibold text-foreground/80">
                                Fecha Hasta
                            </label>
                            <input
                                type="date"
                                value={fechaHasta}
                                onChange={(e) => setFechaHasta(e.target.value)}
                                className="mt-1 w-full rounded-lg border border-border bg-muted/40 px-3 py-1.5 text-xs focus:border-primary focus:outline-none"
                            />
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border pt-2">
                        <label className="flex cursor-pointer items-center gap-2 text-xs font-medium text-foreground/80">
                            <input
                                type="checkbox"
                                checked={conDiferencia}
                                onChange={(e) =>
                                    setConDiferencia(e.target.checked)
                                }
                                className="rounded border-border text-primary"
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
                                className="rounded-lg border border-border bg-card px-3 py-1.5 text-xs font-semibold text-muted-foreground hover:bg-background"
                            >
                                Limpiar
                            </button>
                            <button
                                type="button"
                                onClick={applyFilters}
                                className="inline-flex items-center gap-1.5 rounded-lg bg-card px-4 py-1.5 text-xs font-semibold text-white hover:bg-foreground/90"
                            >
                                <Filter className="size-3.5" />
                                <span>Aplicar Filtros</span>
                            </button>
                        </div>
                    </div>
                </div>

                {/* Tabla de Cajas */}
                <div className="overflow-hidden rounded-xl border border-border bg-card shadow-xs">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="border-b border-border bg-muted/40 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
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
                            <tbody className="divide-y divide-border">
                                {cajas.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={10}
                                            className="py-8 text-center text-muted-foreground"
                                        >
                                            No se encontraron turnos de caja
                                            registrados con estos filtros.
                                        </td>
                                    </tr>
                                ) : (
                                    cajas.data.map((c) => (
                                        <tr
                                            key={c.id}
                                            className="transition-colors hover:bg-muted/40"
                                        >
                                            <td className="px-4 py-3 font-semibold text-foreground">
                                                {c.vendedor.name}
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {c.sede}
                                            </td>
                                            <td className="px-4 py-3 font-mono text-[11px] text-foreground/80">
                                                {c.fecha_apertura}
                                            </td>
                                            <td className="px-4 py-3 font-mono text-[11px] text-foreground/80">
                                                {c.fecha_cierre || '—'}
                                            </td>
                                            <td className="px-4 py-3 text-right font-mono text-foreground">
                                                {formatCurrency(
                                                    c.monto_apertura,
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right font-mono font-medium text-foreground">
                                                {c.monto_contado_cierre !== null
                                                    ? formatCurrency(
                                                          c.monto_contado_cierre,
                                                      )
                                                    : '—'}
                                            </td>
                                            <td className="px-4 py-3 text-right font-mono text-muted-foreground">
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
                                                    <span className="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-0.5 font-mono text-[11px] font-bold text-primary">
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
                                            <td className="max-w-xs truncate px-4 py-3 text-[11px] text-muted-foreground">
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
                        <div className="flex items-center justify-between border-t border-border bg-muted/40 px-4 py-3 text-xs text-muted-foreground">
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
                                                    ? 'bg-card font-bold text-white'
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
