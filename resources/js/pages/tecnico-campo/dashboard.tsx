import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import TecnicoCampoLayout from '@/layouts/tecnico-campo-layout';
import type { Team } from '@/types';
import {
    Calendar,
    Search,
    MapPin,
    Phone,
    Truck,
    PackageCheck,
    ClipboardList,
    Wrench,
    ArrowRight,
    AlertCircle,
    CheckCircle2,
    Clock,
    Layers,
    Car,
} from 'lucide-react';

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
    accion_sugerida: 'recojo' | 'entrega' | 'inspeccion' | 'instalacion' | 'ver';
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

export default function TecnicoCampoDashboard({ orders, kpis, filters }: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug = currentTeam?.slug ?? '';
    const teamPrefix = `/${teamSlug}/tecnico-campo`;

    const [search, setSearch] = useState(filters.search || '');

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            `${teamPrefix}/dashboard`,
            { ...filters, search: search.trim() },
            { preserveState: true }
        );
    };

    const handleStatusTab = (tab: string) => {
        router.get(
            `${teamPrefix}/dashboard`,
            { ...filters, tab },
            { preserveState: true }
        );
    };

    const handleTipoFiltro = (tipo: string) => {
        router.get(
            `${teamPrefix}/dashboard`,
            { ...filters, tipo },
            { preserveState: true }
        );
    };

    const getActionRoute = (order: OrderItem) => {
        switch (order.accion_sugerida) {
            case 'recojo':
                return `${teamPrefix}/recojos/${order.id}`;
            case 'entrega':
                return `${teamPrefix}/entregas/${order.id}`;
            case 'inspeccion':
                return `${teamPrefix}/inspecciones/${order.id}`;
            case 'instalacion':
                return `${teamPrefix}/instalaciones/${order.id}`;
            default:
                return `${teamPrefix}/recojos/${order.id}`;
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
                            Ruta diaria, recojos, entregas e inspecciones (§5.5)
                        </p>
                    </div>
                    <div className="px-2.5 py-1 bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 rounded-xl text-xs font-semibold flex items-center gap-1.5 border border-blue-200 dark:border-blue-900/60">
                        <Calendar className="w-3.5 h-3.5" />
                        <span>Hoy</span>
                    </div>
                </div>

                {/* KPIs Grid (2x2) (§5.5) */}
                <div className="grid grid-cols-2 gap-2.5">
                    <button
                        type="button"
                        onClick={() => handleStatusTab('todos')}
                        className={`p-3.5 rounded-2xl border text-left transition-all ${
                            filters.tab === 'todos' || !filters.tab
                                ? 'bg-neutral-900 dark:bg-neutral-100 text-white dark:text-neutral-900 border-neutral-900 shadow-sm'
                                : 'bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 border-neutral-200 dark:border-neutral-700'
                        }`}
                    >
                        <div className="text-[11px] font-semibold opacity-70">Total Servicios</div>
                        <div className="text-2xl font-black mt-1">{kpis.total_servicios}</div>
                    </button>

                    <button
                        type="button"
                        onClick={() => handleStatusTab('pendientes')}
                        className={`p-3.5 rounded-2xl border text-left transition-all ${
                            filters.tab === 'pendientes'
                                ? 'bg-amber-600 text-white border-amber-600 shadow-sm'
                                : 'bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 border-neutral-200 dark:border-neutral-700'
                        }`}
                    >
                        <div className="text-[11px] font-semibold text-amber-500">Pendientes</div>
                        <div className="text-2xl font-black mt-1 text-amber-600 dark:text-amber-400">{kpis.pendientes}</div>
                    </button>

                    <button
                        type="button"
                        onClick={() => handleStatusTab('en_proceso')}
                        className={`p-3.5 rounded-2xl border text-left transition-all ${
                            filters.tab === 'en_proceso'
                                ? 'bg-blue-600 text-white border-blue-600 shadow-sm'
                                : 'bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 border-neutral-200 dark:border-neutral-700'
                        }`}
                    >
                        <div className="text-[11px] font-semibold text-blue-500">En Ruta / Proceso</div>
                        <div className="text-2xl font-black mt-1 text-blue-600 dark:text-blue-400">{kpis.en_proceso}</div>
                    </button>

                    <button
                        type="button"
                        onClick={() => handleStatusTab('finalizados')}
                        className={`p-3.5 rounded-2xl border text-left transition-all ${
                            filters.tab === 'finalizados'
                                ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm'
                                : 'bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 border-neutral-200 dark:border-neutral-700'
                        }`}
                    >
                        <div className="text-[11px] font-semibold text-emerald-500">Finalizados</div>
                        <div className="text-2xl font-black mt-1 text-emerald-600 dark:text-emerald-400">{kpis.finalizados}</div>
                    </button>
                </div>

                {/* Service Type Pills (§5.5) */}
                <div className="flex gap-1.5 overflow-x-auto pb-1 no-scrollbar">
                    <button
                        type="button"
                        onClick={() => handleTipoFiltro('todos')}
                        className={`px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-colors ${
                            filters.tipo === 'todos' || !filters.tipo
                                ? 'bg-neutral-900 dark:bg-neutral-100 text-white dark:text-neutral-900 shadow-xs'
                                : 'bg-white dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 border border-neutral-200 dark:border-neutral-700'
                        }`}
                    >
                        Todos
                    </button>
                    <button
                        type="button"
                        onClick={() => handleTipoFiltro('recojos')}
                        className={`px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap flex items-center gap-1.5 transition-colors ${
                            filters.tipo === 'recojos'
                                ? 'bg-blue-600 text-white shadow-xs'
                                : 'bg-white dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 border border-neutral-200 dark:border-neutral-700'
                        }`}
                    >
                        <Truck className="w-3.5 h-3.5" />
                        <span>Recojos</span>
                    </button>
                    <button
                        type="button"
                        onClick={() => handleTipoFiltro('entregas')}
                        className={`px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap flex items-center gap-1.5 transition-colors ${
                            filters.tipo === 'entregas'
                                ? 'bg-blue-600 text-white shadow-xs'
                                : 'bg-white dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 border border-neutral-200 dark:border-neutral-700'
                        }`}
                    >
                        <PackageCheck className="w-3.5 h-3.5" />
                        <span>Entregas</span>
                    </button>
                    <button
                        type="button"
                        onClick={() => handleTipoFiltro('inspecciones')}
                        className={`px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap flex items-center gap-1.5 transition-colors ${
                            filters.tipo === 'inspecciones'
                                ? 'bg-blue-600 text-white shadow-xs'
                                : 'bg-white dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 border border-neutral-200 dark:border-neutral-700'
                        }`}
                    >
                        <ClipboardList className="w-3.5 h-3.5" />
                        <span>Inspecciones</span>
                    </button>
                    <button
                        type="button"
                        onClick={() => handleTipoFiltro('instalaciones')}
                        className={`px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap flex items-center gap-1.5 transition-colors ${
                            filters.tipo === 'instalaciones'
                                ? 'bg-blue-600 text-white shadow-xs'
                                : 'bg-white dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 border border-neutral-200 dark:border-neutral-700'
                        }`}
                    >
                        <Wrench className="w-3.5 h-3.5" />
                        <span>Instalaciones</span>
                    </button>
                </div>

                {/* Search Bar */}
                <form onSubmit={handleSearch} className="relative">
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Buscar cliente, dirección o código de orden..."
                        className="w-full pl-9 pr-24 py-2.5 bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-xl text-xs placeholder-neutral-400 focus:ring-2 focus:ring-blue-500"
                    />
                    <Search className="w-4 h-4 text-neutral-400 absolute left-3 top-3.5" />
                    <button
                        type="submit"
                        className="absolute right-1.5 top-1.5 bottom-1.5 px-3 bg-neutral-900 dark:bg-neutral-700 text-white rounded-lg text-xs font-medium"
                    >
                        Buscar
                    </button>
                </form>

                {/* Orders Cards List */}
                <div className="space-y-3">
                    {orders.data.length === 0 ? (
                        <div className="p-8 text-center bg-white dark:bg-neutral-800/40 rounded-2xl border border-dashed border-neutral-200 dark:border-neutral-700 space-y-2">
                            <Truck className="w-8 h-8 text-neutral-400 mx-auto" />
                            <p className="text-xs font-medium text-neutral-600 dark:text-neutral-300">
                                No hay servicios programados en esta cola
                            </p>
                        </div>
                    ) : (
                        orders.data.map((order) => {
                            const isFinished = order.estado === 'entregado' || order.estado === 'cerrado';

                            return (
                                <div
                                    key={order.id}
                                    className="p-4 bg-white dark:bg-neutral-800 rounded-2xl border border-neutral-200 dark:border-neutral-700/80 shadow-sm space-y-3"
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <span className="font-mono text-sm font-extrabold text-neutral-900 dark:text-neutral-100">
                                                    {order.codigo}
                                                </span>
                                                <span className={`px-2 py-0.5 text-[10px] font-bold rounded-full uppercase ${
                                                    isFinished
                                                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                                                        : order.estado === 'en_proceso'
                                                            ? 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300'
                                                            : 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300'
                                                }`}>
                                                    {order.estado.replace('_', ' ')}
                                                </span>
                                            </div>
                                            <h3 className="font-semibold text-sm text-neutral-900 dark:text-neutral-100 mt-1 line-clamp-1">
                                                {order.cliente}
                                            </h3>
                                        </div>

                                        <span className="text-xs font-semibold text-neutral-500 whitespace-nowrap">
                                            {order.tipo_servicio}
                                        </span>
                                    </div>

                                    {/* Address & Quick Phone */}
                                    <div className="space-y-1.5 text-xs text-neutral-600 dark:text-neutral-400">
                                        {order.direccion && (
                                            <div className="flex items-start gap-1.5">
                                                <MapPin className="w-3.5 h-3.5 text-blue-600 mt-0.5 flex-shrink-0" />
                                                <span className="line-clamp-2">{order.direccion}</span>
                                            </div>
                                        )}
                                        {order.telefono && (
                                            <div className="flex items-center gap-1.5">
                                                <Phone className="w-3.5 h-3.5 text-emerald-600 flex-shrink-0" />
                                                <a href={`tel:${order.telefono}`} className="text-blue-600 dark:text-blue-400 font-medium hover:underline">
                                                    {order.telefono}
                                                </a>
                                            </div>
                                        )}
                                        {order.vehiculo && (
                                            <div className="flex items-center gap-1.5 text-[11px] text-neutral-500">
                                                <Car className="w-3 h-3 text-neutral-400" />
                                                <span>Vehículo: {order.vehiculo}</span>
                                            </div>
                                        )}
                                    </div>

                                    {/* Quick Touch Action Button */}
                                    <div className="pt-2 border-t border-neutral-100 dark:border-neutral-700/60 flex items-center justify-between">
                                        <span className="text-[11px] text-neutral-500">
                                            {order.equipos_count > 0 ? `${order.equipos_count} equipo(s)` : 'Sin equipos registrados'}
                                        </span>

                                        <Link
                                            href={getActionRoute(order)}
                                            className="px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold flex items-center gap-1.5 shadow-sm active:scale-95 transition-transform"
                                        >
                                            <span>{getActionLabel(order)}</span>
                                            <ArrowRight className="w-3.5 h-3.5" />
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
