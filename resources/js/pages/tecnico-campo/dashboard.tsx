import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import TecnicoCampoLayout from '@/layouts/tecnico-campo-layout';
import type { Team } from '@/types';
import {
    Search,
    MapPin,
    Phone,
    Truck,
    PackageCheck,
    ClipboardList,
    Wrench,
    ArrowRight,
    Car,
} from 'lucide-react';
import TecnicoCampoRoutes from '@/routes/tecnico-campo';
import RecojosRoutes from '@/routes/tecnico-campo/recojos';
import EntregasRoutes from '@/routes/tecnico-campo/entregas';
import InspeccionesRoutes from '@/routes/tecnico-campo/inspecciones';
import InstalacionesRoutes from '@/routes/tecnico-campo/instalaciones';
import MantenimientosRoutes from '@/routes/tecnico-campo/mantenimientos';
import { KpiFiltro } from '@/components/kpi-filtro';
import { etiquetaEstadoOrden } from '@/lib/estados-orden';

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
    orders: {
        data: OrderItem[];
        current_page: number;
        last_page: number;
        total: number;
    };
    kpis: {
        total_servicios: number;
        pendientes: number;
        en_proceso: number;
        finalizados: number;
    };
    filters: {
        fecha: string;
        tab: string;
        tipo: string;
        search: string;
    };
}

export default function TecnicoCampoDashboard({
    orders,
    kpis,
    filters,
}: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug = currentTeam?.slug ?? '';

    const [search, setSearch] = useState(filters.search || '');

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            TecnicoCampoRoutes.dashboard.url({ current_team: teamSlug }),
            { ...filters, search: search.trim() },
            { preserveState: true },
        );
    };

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

            <div className="space-y-4 pb-16">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100">
                            Servicios de Campo
                        </h1>
                        <p className="text-xs text-neutral-500">
                            Ruta diaria, recojos, entregas e inspecciones
                        </p>
                    </div>
                </div>

                {/* KPIs Grid (2x2) (§5.5) */}
                <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                    <KpiFiltro
                        titulo="Total"
                        valor={kpis.total_servicios}
                        detalle="Servicios"
                        activo={filters.tab === 'todos' || !filters.tab}
                        onClick={() => handleStatusTab('todos')}
                    />
                    <KpiFiltro
                        titulo="Pendientes"
                        valor={kpis.pendientes}
                        detalle="Por atender"
                        tono="warning"
                        activo={filters.tab === 'pendientes'}
                        onClick={() => handleStatusTab('pendientes')}
                    />
                    <KpiFiltro
                        titulo="En proceso"
                        valor={kpis.en_proceso}
                        detalle="En ruta o en trabajo"
                        tono="primary"
                        activo={filters.tab === 'en_proceso'}
                        onClick={() => handleStatusTab('en_proceso')}
                    />
                    <KpiFiltro
                        titulo="Finalizados"
                        valor={kpis.finalizados}
                        detalle="Terminados y cerrados"
                        tono="success"
                        activo={filters.tab === 'finalizados'}
                        onClick={() => handleStatusTab('finalizados')}
                    />
                </div>

                {/* Service Type Pills (§5.5) */}
                <div className="no-scrollbar flex gap-1.5 overflow-x-auto pb-1">
                    <button
                        type="button"
                        onClick={() => handleTipoFiltro('todos')}
                        className={`rounded-xl px-3 py-1.5 text-xs font-bold whitespace-nowrap transition-colors ${
                            filters.tipo === 'todos' || !filters.tipo
                                ? 'bg-neutral-900 text-white shadow-xs dark:bg-neutral-100 dark:text-neutral-900'
                                : 'bg-card border border-neutral-200 text-neutral-600 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-400'
                        }`}
                    >
                        Todos
                    </button>
                    <button
                        type="button"
                        onClick={() => handleTipoFiltro('recojos')}
                        className={`flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-bold whitespace-nowrap transition-colors ${
                            filters.tipo === 'recojos'
                                ? 'bg-primary text-primary-foreground shadow-xs'
                                : 'bg-card border border-neutral-200 text-neutral-600 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-400'
                        }`}
                    >
                        <Truck className="h-3.5 w-3.5" />
                        <span>Recojos</span>
                    </button>
                    <button
                        type="button"
                        onClick={() => handleTipoFiltro('entregas')}
                        className={`flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-bold whitespace-nowrap transition-colors ${
                            filters.tipo === 'entregas'
                                ? 'bg-primary text-primary-foreground shadow-xs'
                                : 'bg-card border border-neutral-200 text-neutral-600 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-400'
                        }`}
                    >
                        <PackageCheck className="h-3.5 w-3.5" />
                        <span>Entregas</span>
                    </button>
                    <button
                        type="button"
                        onClick={() => handleTipoFiltro('inspecciones')}
                        className={`flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-bold whitespace-nowrap transition-colors ${
                            filters.tipo === 'inspecciones'
                                ? 'bg-primary text-primary-foreground shadow-xs'
                                : 'bg-card border border-neutral-200 text-neutral-600 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-400'
                        }`}
                    >
                        <ClipboardList className="h-3.5 w-3.5" />
                        <span>Inspecciones</span>
                    </button>
                    <button
                        type="button"
                        onClick={() => handleTipoFiltro('instalaciones')}
                        className={`flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-bold whitespace-nowrap transition-colors ${
                            filters.tipo === 'instalaciones'
                                ? 'bg-primary text-primary-foreground shadow-xs'
                                : 'bg-card border border-neutral-200 text-neutral-600 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-400'
                        }`}
                    >
                        <Wrench className="h-3.5 w-3.5" />
                        <span>Instalaciones</span>
                    </button>
                </div>

                {/* Search Bar */}
                <form onSubmit={handleSearch} className="relative">
                    <input
                        aria-label="Buscar cliente, dirección o código de orden"
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Buscar cliente, dirección o código de orden..."
                        className="bg-card focus:ring-primary w-full rounded-xl border border-neutral-200 py-2.5 pr-24 pl-9 text-xs placeholder-neutral-400 focus:ring-2 dark:border-neutral-700 dark:bg-neutral-800"
                    />
                    <Search className="absolute top-3.5 left-3 h-4 w-4 text-neutral-400" />
                    <button
                        type="submit"
                        className="absolute top-1.5 right-1.5 bottom-1.5 rounded-lg bg-neutral-900 px-3 text-xs font-medium text-white dark:bg-neutral-700"
                    >
                        Buscar
                    </button>
                </form>

                {/* Orders Cards List */}
                <div className="space-y-3">
                    {orders.data.length === 0 ? (
                        <div className="bg-card space-y-2 rounded-2xl border border-dashed border-neutral-200 p-8 text-center dark:border-neutral-700 dark:bg-neutral-800/40">
                            <Truck className="mx-auto h-8 w-8 text-neutral-400" />
                            <p className="text-xs font-medium text-neutral-600 dark:text-neutral-300">
                                No hay servicios programados en esta cola
                            </p>
                        </div>
                    ) : (
                        orders.data.map((order) => {
                            const isFinished =
                                order.estado === 'entregado' ||
                                order.estado === 'cerrado';

                            return (
                                <div
                                    key={order.id}
                                    className="bg-card space-y-3 rounded-2xl border border-neutral-200 p-4 shadow-sm dark:border-neutral-700/80 dark:bg-neutral-800"
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <span className="font-mono text-sm font-extrabold text-neutral-900 dark:text-neutral-100">
                                                    {order.codigo}
                                                </span>
                                                <span
                                                    className={`rounded-full px-2 py-0.5 text-[10px] font-bold uppercase ${
                                                        isFinished
                                                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                                                            : order.estado ===
                                                                'en_proceso'
                                                              ? 'bg-primary/10 text-primary-strong'
                                                              : 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300'
                                                    }`}
                                                >
                                                    {etiquetaEstadoOrden(
                                                        order.estado,
                                                    )}
                                                </span>
                                            </div>
                                            <h3 className="mt-1 line-clamp-1 text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                                                {order.cliente}
                                            </h3>
                                        </div>

                                        <span className="text-xs font-semibold whitespace-nowrap text-neutral-500 capitalize">
                                            {order.tipo_servicio}
                                        </span>
                                    </div>

                                    {/* Address & Quick Phone */}
                                    <div className="space-y-1.5 text-xs text-neutral-600 dark:text-neutral-400">
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
                                                    className="text-primary-strong font-medium hover:underline"
                                                >
                                                    {order.telefono}
                                                </a>
                                            </div>
                                        )}
                                        {order.vehiculo && (
                                            <div className="flex items-center gap-1.5 text-[11px] text-neutral-500">
                                                <Car className="h-3 w-3 text-neutral-400" />
                                                <span>
                                                    Vehículo: {order.vehiculo}
                                                </span>
                                            </div>
                                        )}
                                    </div>

                                    {/* Quick Touch Action Button */}
                                    <div className="flex items-center justify-between border-t border-neutral-100 pt-2 dark:border-neutral-700/60">
                                        <span className="text-[11px] text-neutral-500">
                                            {order.equipos_count > 0
                                                ? `${order.equipos_count} equipo(s)`
                                                : 'Sin equipos registrados'}
                                        </span>

                                        <Link
                                            href={getActionRoute(order)}
                                            className="bg-primary text-primary-foreground hover:bg-primary/90 flex items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-bold shadow-sm transition-transform active:scale-95"
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
            </div>
        </TecnicoCampoLayout>
    );
}
