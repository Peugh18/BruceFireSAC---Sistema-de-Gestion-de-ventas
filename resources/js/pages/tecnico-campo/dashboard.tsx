import GraficosDashboard, {
    type DatosGraficos,
} from '@/components/graficos-dashboard';
import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useBusquedaDinamica } from '@/hooks/use-busqueda-dinamica';
import TecnicoCampoLayout from '@/layouts/tecnico-campo-layout';
import type { Team } from '@/types';
import {
    Search,
    MapPin,
    Phone,
    Truck,
    ArrowRight,
    Car,
    Clock,
    Wrench,
    CheckCircle2,
    CalendarDays,
} from 'lucide-react';
import TecnicoCampoRoutes from '@/routes/tecnico-campo';
import RecojosRoutes from '@/routes/tecnico-campo/recojos';
import EntregasRoutes from '@/routes/tecnico-campo/entregas';
import InspeccionesRoutes from '@/routes/tecnico-campo/inspecciones';
import InstalacionesRoutes from '@/routes/tecnico-campo/instalaciones';
import MantenimientosRoutes from '@/routes/tecnico-campo/mantenimientos';
import FiltroSelect from '@/components/filtro-select';
import { KpiFiltro } from '@/components/kpi-filtro';
import { etiquetaEstadoOrden } from '@/lib/estados-orden';
import { Empty } from '@/components/ui/empty';
import PaginacionOrdenes from '@/components/paginacion-ordenes';

interface OrderItem {
    id: number;
    codigo: string;
    cliente: string;
    cliente_doc: string;
    telefono: string | null;
    direccion: string | null;
    sede: string | null;
    tipo_servicio: string;
    fecha: string;
    prioridad: string;
    estado: string;
    estado_coarse: string;
    vehiculo: string | null;
    equipos_count: number;
    accion_sugerida:
        | 'recojo'
        | 'entrega'
        | 'inspeccion'
        | 'instalacion'
        | 'mantenimiento'
        | 'ver';
    observaciones: string | null;
}

interface Props {
    graficos: DatosGraficos;
    orders: {
        data: OrderItem[];
        current_page: number;
        last_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    kpis: {
        total_servicios: number;
        pendientes: number;
        en_proceso: number;
        finalizados: number;
    };
    filters: {
        fecha: string;
        hoy: string;
        tab: string;
        tipo: string;
        search: string;
    };
}

export default function TecnicoCampoDashboard({
    graficos,
    orders,
    kpis,
    filters,
}: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug = currentTeam?.slug ?? '';

    const [search, setSearch] = useState(filters.search || '');

    const aplicarBusqueda = () => {
        router.get(
            TecnicoCampoRoutes.dashboard.url({ current_team: teamSlug }),
            { ...filters, search: search.trim() },
            { preserveState: true },
        );
    };
    const handleSearch = useBusquedaDinamica(search, aplicarBusqueda);

    const handleStatusTab = (tab: string) => {
        router.get(
            TecnicoCampoRoutes.dashboard.url({ current_team: teamSlug }),
            { ...filters, tab },
            { preserveState: true },
        );
    };

    const handleTipoFiltro = (tipo: string) => {
        router.get(
            TecnicoCampoRoutes.dashboard.url({ current_team: teamSlug }),
            { ...filters, tipo },
            { preserveState: true },
        );
    };

    const getActionRoute = (order: OrderItem) => {
        switch (order.accion_sugerida) {
            case 'recojo':
                return RecojosRoutes.show.url({
                    current_team: teamSlug,
                    service_order: order.id,
                });
            case 'entrega':
                return EntregasRoutes.show.url({
                    current_team: teamSlug,
                    service_order: order.id,
                });
            case 'inspeccion':
                return InspeccionesRoutes.show.url({
                    current_team: teamSlug,
                    service_order: order.id,
                });
            case 'instalacion':
                return InstalacionesRoutes.show.url({
                    current_team: teamSlug,
                    service_order: order.id,
                });
            case 'mantenimiento':
                return MantenimientosRoutes.show.url({
                    current_team: teamSlug,
                    service_order: order.id,
                });
            default:
                return RecojosRoutes.show.url({
                    current_team: teamSlug,
                    service_order: order.id,
                });
        }
    };

    const getActionLabel = (order: OrderItem) => {
        if (
            ['trabajo_terminado', 'entregado', 'cerrado'].includes(order.estado)
        ) {
            return 'Ver servicio';
        }
        switch (order.accion_sugerida) {
            case 'recojo':
                return 'Iniciar Recojo';
            case 'entrega':
                return 'Registrar Entrega';
            case 'inspeccion':
                return 'Checklist Campo';
            case 'instalacion':
                return 'Instalación';
            case 'mantenimiento':
                return 'Mantenimiento';
            default:
                return 'Ver Servicio';
        }
    };

    return (
        <TecnicoCampoLayout>
            <Head title="Ruta y Servicios de Campo" />

            <div className="space-y-4">
                <GraficosDashboard rol="campo" datos={graficos} />
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <p className="text-primary-strong mb-1 text-xs font-bold tracking-wider uppercase">
                            Campo
                        </p>
                        <h1 className="text-foreground text-2xl font-bold tracking-tight">
                            Servicios de Campo
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            Pendientes de hoy y anteriores. Urgentes primero.
                        </p>
                    </div>
                    <Link
                        href={RecojosRoutes.create.url(teamSlug)}
                        className="bg-primary text-primary-foreground ml-2 flex min-h-11 shrink-0 items-center gap-2 rounded-2xl px-3 text-sm font-semibold"
                    >
                        <Truck className="size-4" />
                        Iniciar recojo
                    </Link>
                </div>

                {/* KPIs Grid (2x2) (§5.5) */}
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <label
                        htmlFor="fecha-campo"
                        className="text-sm font-semibold"
                    >
                        {filters.fecha === filters.hoy
                            ? 'Hoy y atrasadas'
                            : 'Consultar día'}
                    </label>
                    <input
                        id="fecha-campo"
                        type="date"
                        value={filters.fecha}
                        aria-label="Fecha de los servicios de Campo"
                        onChange={(event) =>
                            router.get(
                                TecnicoCampoRoutes.dashboard.url(teamSlug),
                                {
                                    ...filters,
                                    fecha: event.target.value || filters.hoy,
                                    search,
                                },
                                { preserveState: true },
                            )
                        }
                        className="border-input bg-card min-h-11 min-w-0 rounded-xl border px-3 text-base"
                    />
                    {filters.fecha !== filters.hoy && (
                        <button
                            type="button"
                            className="border-border bg-card min-h-11 rounded-xl border px-3 text-sm"
                            onClick={() =>
                                router.get(
                                    TecnicoCampoRoutes.dashboard.url(teamSlug),
                                    { ...filters, fecha: filters.hoy, search },
                                    { preserveState: true },
                                )
                            }
                        >
                            Hoy
                        </button>
                    )}
                </div>
                <div
                    className="border-border bg-card grid grid-cols-2 overflow-hidden rounded-3xl border shadow-xs sm:grid-cols-4 [&>button]:rounded-none [&>button]:border-0 [&>button]:p-4 [&>button]:ring-inset [&>button:nth-child(-n+2)]:border-b [&>button:nth-child(odd)]:border-r"
                    aria-label="Filtrar servicios por estado"
                >
                    <KpiFiltro
                        titulo="Total"
                        valor={kpis.total_servicios}
                        detalle="Servicios"
                        icono={<CalendarDays className="size-5" />}
                        activo={filters.tab === 'todos' || !filters.tab}
                        onClick={() => handleStatusTab('todos')}
                    />
                    <KpiFiltro
                        titulo="Pendientes"
                        valor={kpis.pendientes}
                        detalle="Por atender"
                        icono={<Clock className="size-5" />}
                        tono="warning"
                        activo={filters.tab === 'pendientes'}
                        onClick={() => handleStatusTab('pendientes')}
                    />
                    <KpiFiltro
                        titulo="En proceso"
                        valor={kpis.en_proceso}
                        detalle="En ruta o en trabajo"
                        icono={<Wrench className="size-5" />}
                        tono="primary"
                        activo={filters.tab === 'en_proceso'}
                        onClick={() => handleStatusTab('en_proceso')}
                    />
                    <KpiFiltro
                        titulo="Finalizados"
                        valor={kpis.finalizados}
                        detalle="Terminados y cerrados"
                        icono={<CheckCircle2 className="size-5" />}
                        tono="success"
                        activo={filters.tab === 'finalizados'}
                        onClick={() => handleStatusTab('finalizados')}
                    />
                </div>

                <FiltroSelect
                    etiqueta="Tipo de servicio"
                    valor={filters.tipo || 'todos'}
                    opciones={[
                        { value: 'todos', label: 'Todos' },
                        { value: 'recojos', label: 'Recojos' },
                        { value: 'entregas', label: 'Entregas' },
                        { value: 'inspecciones', label: 'Inspecciones' },
                        { value: 'instalaciones', label: 'Instalaciones' },
                        { value: 'mantenimientos', label: 'Mantenimientos' },
                    ]}
                    onCambiar={handleTipoFiltro}
                />

                {/* Search Bar */}
                <form onSubmit={handleSearch} className="relative">
                    <input
                        aria-label="Buscar cliente, dirección o código de orden"
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Buscar cliente, dirección o código de orden..."
                        className="bg-card focus:ring-primary border-border min-h-12 w-full rounded-xl border py-2.5 pr-4 pl-9 text-xs placeholder-neutral-400 focus:ring-2"
                    />
                    <Search className="text-muted-foreground absolute top-4 left-3 h-4 w-4" />
                </form>

                {/* Orders Cards List */}
                <div className="space-y-3">
                    {orders.data.length === 0 ? (
                        <Empty
                            icon={<Truck />}
                            title="No hay servicios programados en esta cola"
                        />
                    ) : (
                        orders.data.map((order) => {
                            const isFinished =
                                order.estado === 'entregado' ||
                                order.estado === 'cerrado';

                            return (
                                <div
                                    key={order.id}
                                    className="bg-card border-border space-y-3 rounded-3xl border p-4 shadow-sm"
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <div className="min-w-0 flex-1 basis-48">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="text-primary-strong font-mono text-xs font-bold whitespace-nowrap">
                                                    {order.codigo}
                                                </span>
                                                {order.prioridad !==
                                                    'normal' && (
                                                    <span className="text-destructive-strong bg-destructive/10 rounded-full px-2 py-0.5 text-xs font-bold">
                                                        {order.prioridad ===
                                                        'urgente'
                                                            ? 'Urgente'
                                                            : 'Prioridad alta'}
                                                    </span>
                                                )}
                                                <span
                                                    className={`rounded-full px-2 py-0.5 text-xs font-bold uppercase ${
                                                        isFinished
                                                            ? 'text-success-strong bg-emerald-100 dark:bg-emerald-950/60 dark:text-emerald-300'
                                                            : order.estado ===
                                                                'en_proceso'
                                                              ? 'bg-primary/10 text-primary-strong'
                                                              : 'text-warning-strong bg-amber-100 dark:bg-amber-950/60 dark:text-amber-300'
                                                    }`}
                                                >
                                                    {etiquetaEstadoOrden(
                                                        order.estado,
                                                    )}
                                                </span>
                                            </div>
                                            <h3 className="text-foreground mt-1 line-clamp-1 text-sm font-semibold">
                                                {order.cliente}
                                            </h3>
                                        </div>

                                        <span className="text-muted-foreground text-xs font-semibold capitalize">
                                            {order.tipo_servicio}
                                        </span>
                                    </div>

                                    {/* Address & Quick Phone */}
                                    <div className="text-muted-foreground space-y-1.5 text-xs">
                                        {order.direccion && (
                                            <div className="flex items-start gap-1.5">
                                                <MapPin className="text-primary-strong mt-0.5 h-3.5 w-3.5 flex-shrink-0" />
                                                <span className="line-clamp-2">
                                                    {order.direccion}
                                                </span>
                                            </div>
                                        )}
                                        {order.telefono && (
                                            <div className="flex items-center gap-1.5">
                                                <Phone className="text-success-strong h-3.5 w-3.5 flex-shrink-0" />
                                                <a
                                                    href={`tel:${order.telefono}`}
                                                    className="text-primary-strong inline-flex min-h-11 items-center font-medium hover:underline"
                                                >
                                                    {order.telefono}
                                                </a>
                                            </div>
                                        )}
                                        {order.vehiculo && (
                                            <div className="text-muted-foreground flex items-center gap-1.5 text-xs">
                                                <Car className="text-muted-foreground h-3 w-3" />
                                                <span>
                                                    Vehículo: {order.vehiculo}
                                                </span>
                                            </div>
                                        )}
                                    </div>

                                    {/* Quick Touch Action Button */}
                                    <div className="border-border/70 flex items-center justify-between border-t pt-2">
                                        <span className="text-muted-foreground text-xs">
                                            {order.equipos_count > 0
                                                ? `${order.equipos_count} equipo(s)`
                                                : 'Sin equipos registrados'}
                                        </span>

                                        <Link
                                            href={getActionRoute(order)}
                                            className="bg-primary text-primary-foreground hover:bg-primary/90 flex min-h-11 items-center gap-1.5 rounded-xl px-4 py-2 text-xs font-bold shadow-sm transition-transform active:scale-95"
                                        >
                                            <span>{getActionLabel(order)}</span>
                                            <ArrowRight className="h-3.5 w-3.5" />
                                        </Link>
                                    </div>
                                </div>
                            );
                        })
                    )}
                </div>
                <PaginacionOrdenes paginas={orders} />
            </div>
        </TecnicoCampoLayout>
    );
}
