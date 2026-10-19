import { router, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    Clock,
    Filter,
    Inbox,
    TrendingDown,
    Wallet,
} from 'lucide-react';
import { useState } from 'react';

import GerenteLayout from '@/layouts/gerente-layout';
import CajasRoutes from '@/routes/gerente/cajas';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { NativeSelect } from '@/components/ui/native-select';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type CashDetail = {
    id: number;
    vendedor: string;
    sede: string;
    fecha_apertura: string;
    fecha_cierre: string | null;
    monto_apertura: number;
    cobros_por_forma_pago: Record<string, number>;
    salidas: Array<{
        id: number;
        monto: number;
        motivo: string;
        fecha: string | null;
    }>;
    total_salidas: number;
    monto_esperado_calculado: number | null;
    monto_contado_cierre: number | null;
    diferencia: number | null;
    observacion: string;
};

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
    faltantesMes: number;
    sobrantesMes: number;
    turnosConDescuadreMes: number;
};

type PageProps = {
    currentTeam: { slug: string };
    cajas: PaginatedCashRegisters;
    detalle: CashDetail | null;
    filters: CashFilters;
    kpis: CashKpis;
    vendedores: Array<{ id: number; name: string }>;
    sedes: Array<{ id: number; nombre: string }>;
    [key: string]: unknown;
};

function formatCurrency(amount: number): string {
    // Un cero negativo (-0, o un -0.001 que redondea a cero) se renderiza como
    // "-S/ 0.00" en el servidor y como "S/ 0.00" en el navegador: React rompe la
    // hidratación. El cero no tiene signo, así que se normaliza antes de formatear
    // y el resultado es idéntico en ambos lados.
    const normalizado = Math.round(Math.abs(amount) * 100) === 0 ? 0 : amount;

    return new Intl.NumberFormat('es-PE', {
        style: 'currency',
        currency: 'PEN',
        minimumFractionDigits: 2,
    }).format(normalizado);
}

export default function CajasConsolidadasIndex() {
    const { currentTeam, cajas, detalle, filters, kpis, vendedores, sedes } =
        usePage<PageProps>().props;

    const [vendedorId, setVendedorId] = useState(filters.vendedor_id || '');
    const [sedeId, setSedeId] = useState(filters.sede_id || '');
    const [estado, setEstado] = useState(filters.estado || 'todos');
    const [conDiferencia, setConDiferencia] = useState(
        filters.con_diferencia || false,
    );
    const [fechaDesde, setFechaDesde] = useState(filters.fecha_desde || '');
    const [fechaHasta, setFechaHasta] = useState(filters.fecha_hasta || '');
    const [loadingDetail, setLoadingDetail] = useState<number | null>(null);

    const viewDetail = (id: number | null) => {
        router.get(
            CajasRoutes.index.url({ current_team: currentTeam.slug }),
            {
                ...filters,
                con_diferencia: filters.con_diferencia ? '1' : undefined,
                page: cajas.current_page,
                turno: id ?? undefined,
            },
            {
                only: ['detalle'],
                preserveState: true,
                preserveScroll: true,
                onStart: () => setLoadingDetail(id),
                onFinish: () => setLoadingDetail(null),
            },
        );
    };

    const detailButton = (caja: CashRegisterItem) =>
        caja.estado === 'cerrado' ? (
            <Button
                variant="outline"
                size="sm"
                disabled={loadingDetail !== null}
                aria-label={`Ver detalle del turno ${caja.id} de ${caja.vendedor.name}`}
                onClick={() => viewDetail(caja.id)}
            >
                {loadingDetail === caja.id ? 'Cargando…' : 'Ver detalle'}
            </Button>
        ) : (
            <span className="text-muted-foreground text-xs">
                Turno en curso
            </span>
        );

    const differenceText = (caja: CashRegisterItem) => {
        if (caja.estado === 'abierto') return 'En curso';
        if (caja.diferencia === null) return 'Sin dato';
        if (caja.diferencia === 0) return 'Sin diferencia';
        return `${caja.diferencia < 0 ? 'Faltante' : 'Sobrante'}: ${formatCurrency(Math.abs(caja.diferencia))}`;
    };

    const applyFilters = () => {
        router.get(
            CajasRoutes.index.url({ current_team: currentTeam.slug }),
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
            CajasRoutes.index.url({ current_team: currentTeam.slug }),
            {},
            { preserveState: true },
        );
    };

    return (
        <GerenteLayout title="Caja Consolidada">
            <div className="min-w-0 space-y-6 pb-24 sm:pb-0">
                {/* Cabecera */}
                <div>
                    <h1 className="text-foreground font-['Oswald',sans-serif] text-2xl font-bold tracking-wide uppercase">
                        Caja consolidada
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Consulta de turnos, cobros, salidas y diferencias de
                        cierre de los vendedores.
                    </p>
                </div>

                {/* KPI Cards */}
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div className="border-border bg-card rounded-xl border p-4 shadow-xs">
                        <div className="text-muted-foreground flex items-center justify-between text-xs">
                            <span className="font-medium uppercase">
                                Turnos Hoy
                            </span>
                            <Clock className="text-foreground size-4" />
                        </div>
                        <div className="text-foreground mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold">
                            {kpis.turnosHoy}
                        </div>
                        <p className="text-muted-foreground mt-0.5 text-xs">
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
                        <div className="text-success-strong mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold">
                            {kpis.turnosAbiertos}
                        </div>
                        <p className="text-muted-foreground mt-0.5 text-xs">
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
                        <div className="text-warning-strong mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold">
                            {kpis.turnosConDescuadreMes}
                        </div>
                        <p className="text-muted-foreground mt-0.5 text-xs">
                            Cierres con faltante o sobrante
                        </p>
                    </div>

                    <div className="border-border bg-card rounded-xl border p-4 shadow-xs">
                        <div className="text-muted-foreground flex items-center justify-between text-xs">
                            <span className="font-medium uppercase">
                                Faltantes de caja
                            </span>
                            <TrendingDown className="text-primary-strong size-4" />
                        </div>
                        <div
                            className={`mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold ${
                                kpis.faltantesMes > 0
                                    ? 'text-primary-strong'
                                    : 'text-success-strong'
                            }`}
                        >
                            {formatCurrency(kpis.faltantesMes)}
                        </div>
                        <p className="text-muted-foreground mt-0.5 text-xs">
                            Faltantes del mes · sobrantes{' '}
                            {formatCurrency(kpis.sobrantesMes)} (no se
                            compensan)
                        </p>
                    </div>
                </div>

                {/* Filtros */}
                <div className="border-border bg-card space-y-3 rounded-xl border p-4 shadow-xs">
                    <div className="grid grid-cols-1 gap-3 text-xs sm:grid-cols-2 lg:grid-cols-5">
                        <div>
                            <label
                                htmlFor="gerente-cajas-vendedor"
                                className="text-foreground/80 block font-semibold"
                            >
                                Vendedor
                            </label>
                            <NativeSelect
                                id="gerente-cajas-vendedor"
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
                            </NativeSelect>
                        </div>

                        <div>
                            <label
                                htmlFor="gerente-cajas-sede"
                                className="text-foreground/80 block font-semibold"
                            >
                                Sede
                            </label>
                            <NativeSelect
                                id="gerente-cajas-sede"
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
                            </NativeSelect>
                        </div>

                        <div>
                            <label
                                htmlFor="gerente-cajas-estado"
                                className="text-foreground/80 block font-semibold"
                            >
                                Estado
                            </label>
                            <NativeSelect
                                id="gerente-cajas-estado"
                                value={estado}
                                onChange={(e) => setEstado(e.target.value)}
                                className="border-border bg-muted/40 focus:border-primary mt-1 w-full rounded-lg border px-3 py-2 text-xs focus:outline-none"
                            >
                                <option value="todos">Todos los estados</option>
                                <option value="abierto">Solo Abiertas</option>
                                <option value="cerrado">Solo Cerradas</option>
                            </NativeSelect>
                        </div>

                        <div>
                            <label
                                htmlFor="gerente-cajas-fecha-desde"
                                className="text-foreground/80 block font-semibold"
                            >
                                Fecha Desde
                            </label>
                            <input
                                id="gerente-cajas-fecha-desde"
                                type="date"
                                value={fechaDesde}
                                onChange={(e) => setFechaDesde(e.target.value)}
                                className="border-border bg-muted/40 focus:border-primary mt-1 w-full rounded-lg border px-3 py-1.5 text-xs focus:outline-none"
                            />
                        </div>

                        <div>
                            <label
                                htmlFor="gerente-cajas-fecha-hasta"
                                className="text-foreground/80 block font-semibold"
                            >
                                Fecha Hasta
                            </label>
                            <input
                                id="gerente-cajas-fecha-hasta"
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

                <section
                    aria-label="Turnos de caja"
                    className="border-border bg-card min-w-0 rounded-xl border"
                >
                    {cajas.data.length === 0 ? (
                        <div className="space-y-3 p-6 text-center">
                            <Inbox
                                className="text-muted-foreground mx-auto size-6"
                                aria-hidden="true"
                            />
                            <p className="font-semibold">
                                No se encontraron turnos de caja
                            </p>
                            <p className="text-muted-foreground text-sm">
                                Prueba con otros filtros o consulta un período
                                más amplio.
                            </p>
                            <Button variant="outline" onClick={resetFilters}>
                                Limpiar filtros
                            </Button>
                        </div>
                    ) : (
                        <>
                            <ul className="divide-border divide-y lg:hidden">
                                {cajas.data.map((c) => (
                                    <li
                                        key={c.id}
                                        className="min-w-0 space-y-3 p-4"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <p className="font-semibold break-words">
                                                    {c.vendedor.name}
                                                </p>
                                                <p className="text-muted-foreground text-xs break-words">
                                                    {c.sede} · Turno #{c.id}
                                                </p>
                                            </div>
                                            <span className="bg-muted text-foreground shrink-0 rounded px-2 py-1 text-xs">
                                                {c.estado === 'cerrado'
                                                    ? 'Cerrado'
                                                    : 'Abierto'}
                                            </span>
                                        </div>
                                        <dl className="grid grid-cols-2 gap-2 text-xs">
                                            <div>
                                                <dt className="text-muted-foreground">
                                                    Apertura
                                                </dt>
                                                <dd>{c.fecha_apertura}</dd>
                                            </div>
                                            <div>
                                                <dt className="text-muted-foreground">
                                                    Contado al cierre
                                                </dt>
                                                <dd className="font-mono">
                                                    {c.monto_contado_cierre ===
                                                    null
                                                        ? '—'
                                                        : formatCurrency(
                                                              c.monto_contado_cierre,
                                                          )}
                                                </dd>
                                            </div>
                                        </dl>
                                        <p
                                            className={
                                                c.diferencia
                                                    ? 'text-warning-strong text-sm font-medium'
                                                    : 'text-muted-foreground text-sm'
                                            }
                                        >
                                            {differenceText(c)}
                                        </p>
                                        {detailButton(c)}
                                    </li>
                                ))}
                            </ul>
                            <div className="hidden lg:block">
                                <Table>
                                    <caption className="sr-only">
                                        Turnos de caja con importes registrados
                                        al cierre
                                    </caption>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead scope="col">
                                                Vendedor / sede
                                            </TableHead>
                                            <TableHead scope="col">
                                                Apertura / cierre
                                            </TableHead>
                                            <TableHead
                                                scope="col"
                                                className="text-right"
                                            >
                                                Esperado
                                            </TableHead>
                                            <TableHead
                                                scope="col"
                                                className="text-right"
                                            >
                                                Contado
                                            </TableHead>
                                            <TableHead scope="col">
                                                Diferencia
                                            </TableHead>
                                            <TableHead scope="col">
                                                Estado
                                            </TableHead>
                                            <TableHead scope="col">
                                                Acciones
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {cajas.data.map((c) => (
                                            <TableRow key={c.id}>
                                                <TableCell className="max-w-48 whitespace-normal">
                                                    <p className="font-medium break-words">
                                                        {c.vendedor.name}
                                                    </p>
                                                    <p className="text-muted-foreground text-xs break-words">
                                                        {c.sede}
                                                    </p>
                                                </TableCell>
                                                <TableCell className="text-xs">
                                                    <p>{c.fecha_apertura}</p>
                                                    <p className="text-muted-foreground">
                                                        {c.fecha_cierre ??
                                                            'En curso'}
                                                    </p>
                                                </TableCell>
                                                <TableCell className="text-right font-mono">
                                                    {c.monto_esperado_calculado ===
                                                    null
                                                        ? '—'
                                                        : formatCurrency(
                                                              c.monto_esperado_calculado,
                                                          )}
                                                </TableCell>
                                                <TableCell className="text-right font-mono">
                                                    {c.monto_contado_cierre ===
                                                    null
                                                        ? '—'
                                                        : formatCurrency(
                                                              c.monto_contado_cierre,
                                                          )}
                                                </TableCell>
                                                <TableCell className="max-w-44 text-xs whitespace-normal">
                                                    {differenceText(c)}
                                                </TableCell>
                                                <TableCell>
                                                    {c.estado === 'cerrado'
                                                        ? 'Cerrado'
                                                        : 'Abierto'}
                                                </TableCell>
                                                <TableCell>
                                                    {detailButton(c)}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        </>
                    )}
                    {cajas.last_page > 1 && (
                        <nav
                            aria-label="Paginación de turnos"
                            className="border-border flex flex-wrap items-center justify-between gap-3 border-t p-4 text-xs"
                        >
                            <span className="text-muted-foreground">
                                Página {cajas.current_page} de {cajas.last_page}{' '}
                                · {cajas.total} turnos
                            </span>
                            <div className="flex gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={cajas.current_page <= 1}
                                    onClick={() =>
                                        router.get(
                                            CajasRoutes.index.url({
                                                current_team: currentTeam.slug,
                                            }),
                                            {
                                                ...filters,
                                                con_diferencia:
                                                    filters.con_diferencia
                                                        ? '1'
                                                        : undefined,
                                                page: cajas.current_page - 1,
                                            },
                                            { preserveState: true },
                                        )
                                    }
                                >
                                    Anterior
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={
                                        cajas.current_page >= cajas.last_page
                                    }
                                    onClick={() =>
                                        router.get(
                                            CajasRoutes.index.url({
                                                current_team: currentTeam.slug,
                                            }),
                                            {
                                                ...filters,
                                                con_diferencia:
                                                    filters.con_diferencia
                                                        ? '1'
                                                        : undefined,
                                                page: cajas.current_page + 1,
                                            },
                                            { preserveState: true },
                                        )
                                    }
                                >
                                    Siguiente
                                </Button>
                            </div>
                        </nav>
                    )}
                </section>
            </div>
            <Dialog
                open={detalle !== null}
                onOpenChange={(open) => {
                    if (!open) viewDetail(null);
                }}
            >
                <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>
                            Detalle del cierre #{detalle?.id}
                        </DialogTitle>
                        <DialogDescription className="break-words">
                            {detalle?.vendedor} · {detalle?.sede}
                        </DialogDescription>
                    </DialogHeader>
                    {detalle && (
                        <div className="min-w-0 space-y-5 text-sm">
                            <dl className="grid grid-cols-1 gap-2 text-xs sm:grid-cols-2">
                                <div>
                                    <dt className="text-muted-foreground">
                                        Apertura
                                    </dt>
                                    <dd>{detalle.fecha_apertura}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Cierre
                                    </dt>
                                    <dd>
                                        {detalle.fecha_cierre ?? 'Sin fecha'}
                                    </dd>
                                </div>
                            </dl>
                            <dl className="bg-muted flex items-center justify-between gap-3 rounded-lg p-3">
                                <dt>Fondo inicial</dt>
                                <dd className="font-mono font-semibold">
                                    {formatCurrency(detalle.monto_apertura)}
                                </dd>
                            </dl>
                            <section aria-labelledby="caja-cobros">
                                <h2 id="caja-cobros" className="font-semibold">
                                    Cobros netos por medio de pago
                                </h2>
                                <p className="text-muted-foreground mt-1 text-xs">
                                    Cobros menos anulaciones y devoluciones
                                    registradas en este turno. Solo el efectivo
                                    interviene en el cierre.
                                </p>
                                {Object.keys(detalle.cobros_por_forma_pago)
                                    .length === 0 ? (
                                    <p className="text-muted-foreground mt-2">
                                        Sin cobros registrados.
                                    </p>
                                ) : (
                                    <dl className="mt-2 space-y-2">
                                        {Object.entries(
                                            detalle.cobros_por_forma_pago,
                                        ).map(([medio, monto]) => (
                                            <div
                                                key={medio}
                                                className="flex justify-between gap-3"
                                            >
                                                <dt className="min-w-0 break-words capitalize">
                                                    {medio.replaceAll('_', ' ')}
                                                </dt>
                                                <dd className="shrink-0 font-mono">
                                                    {formatCurrency(monto)}
                                                </dd>
                                            </div>
                                        ))}
                                    </dl>
                                )}
                            </section>
                            <section aria-labelledby="caja-salidas">
                                <h2 id="caja-salidas" className="font-semibold">
                                    Salidas de efectivo
                                </h2>
                                {detalle.salidas.length === 0 ? (
                                    <p className="text-muted-foreground mt-2">
                                        Sin salidas registradas.
                                    </p>
                                ) : (
                                    <ul className="divide-border mt-2 divide-y">
                                        {detalle.salidas.map((salida) => (
                                            <li
                                                key={salida.id}
                                                className="py-2"
                                            >
                                                <div className="flex items-start justify-between gap-3">
                                                    <p className="min-w-0 break-words whitespace-pre-wrap">
                                                        {salida.motivo}
                                                    </p>
                                                    <span className="shrink-0 font-mono">
                                                        {formatCurrency(
                                                            salida.monto,
                                                        )}
                                                    </span>
                                                </div>
                                                <p className="text-muted-foreground mt-1 text-xs">
                                                    {salida.fecha ??
                                                        'Sin fecha'}
                                                </p>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                                <dl className="mt-2 flex justify-between gap-3 font-medium">
                                    <dt>Total de salidas</dt>
                                    <dd className="font-mono">
                                        {formatCurrency(detalle.total_salidas)}
                                    </dd>
                                </dl>
                            </section>
                            <section
                                aria-labelledby="caja-cierre"
                                className="border-border rounded-lg border p-3"
                            >
                                <h2 id="caja-cierre" className="font-semibold">
                                    Importes registrados al cerrar
                                </h2>
                                <p className="text-muted-foreground mt-1 text-xs">
                                    Esperado: fondo inicial + efectivo neto −
                                    salidas.
                                </p>
                                <dl className="mt-3 space-y-2">
                                    {[
                                        [
                                            'Efectivo esperado',
                                            detalle.monto_esperado_calculado,
                                        ],
                                        [
                                            'Efectivo contado',
                                            detalle.monto_contado_cierre,
                                        ],
                                        [
                                            'Diferencia (contado − esperado)',
                                            detalle.diferencia,
                                        ],
                                    ].map(([label, value]) => (
                                        <div
                                            key={label}
                                            className="flex justify-between gap-3"
                                        >
                                            <dt>{label}</dt>
                                            <dd className="shrink-0 font-mono font-semibold">
                                                {typeof value === 'number'
                                                    ? formatCurrency(value)
                                                    : 'Sin dato'}
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                                {detalle.diferencia !== null &&
                                    detalle.diferencia !== 0 && (
                                        <p className="text-warning-strong mt-2 font-medium">
                                            {detalle.diferencia < 0
                                                ? 'Faltante de efectivo'
                                                : 'Sobrante de efectivo'}
                                        </p>
                                    )}
                            </section>
                            <section aria-labelledby="caja-observacion">
                                <h2
                                    id="caja-observacion"
                                    className="font-semibold"
                                >
                                    Observación del cierre
                                </h2>
                                <p className="text-muted-foreground mt-2 break-words whitespace-pre-wrap">
                                    {detalle.observacion}
                                </p>
                            </section>
                            <Button
                                className="w-full"
                                variant="outline"
                                onClick={() => viewDetail(null)}
                            >
                                Cerrar detalle
                            </Button>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </GerenteLayout>
    );
}
