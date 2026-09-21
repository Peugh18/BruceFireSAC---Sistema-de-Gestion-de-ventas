import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import TecnicoPlantaLayout from '@/layouts/tecnico-planta-layout';
import type { Team } from '@/types';
import { Search, Inbox, CheckCircle2, QrCode, ArrowRight, PackageOpen, AlertCircle } from 'lucide-react';

interface OrderItem {
    id: number;
    codigo: string;
    cliente: string;
    cliente_doc: string;
    telefono: string | null;
    sede: string | null;
    tipo_servicio: string;
    fecha: string;
    prioridad: string;
    estado: string;
    equipos_count: number;
}

interface Props {
    orders: {
        data: OrderItem[];
        current_page: number;
        last_page: number;
        total: number;
    };
    counts: {
        pendientes: number;
        recibidas: number;
    };
    filters: {
        search: string;
        filter: string;
    };
}

export default function RecepcionesIndex({ orders, counts, filters }: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug = currentTeam?.slug ?? '';
    const [search, setSearch] = useState(filters.search || '');

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            `/${teamSlug}/tecnico-planta/recepciones`,
            { search, filter: filters.filter },
            { preserveState: true }
        );
    };

    const handleFilterChange = (newFilter: string) => {
        router.get(
            `/${teamSlug}/tecnico-planta/recepciones`,
            { search, filter: newFilter },
            { preserveState: true }
        );
    };

    return (
        <TecnicoPlantaLayout>
            <Head title="Recepciones en Planta - Taller" />

            <div className="space-y-4">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100">
                            Recepción en Planta
                        </h1>
                        <p className="text-xs text-neutral-500 dark:text-neutral-400">
                            Ingreso y verificación de equipos en taller (§18)
                        </p>
                    </div>
                </div>

                {/* Filter Pills */}
                <div className="flex gap-2 p-1 bg-neutral-100 dark:bg-neutral-800/60 rounded-xl">
                    <button
                        type="button"
                        onClick={() => handleFilterChange('pendientes')}
                        className={`flex-1 py-2 text-xs font-semibold rounded-lg flex items-center justify-center gap-1.5 transition-all ${
                            filters.filter === 'pendientes'
                                ? 'bg-white dark:bg-neutral-700 text-amber-700 dark:text-amber-400 shadow-sm'
                                : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900'
                        }`}
                    >
                        <Inbox className="w-3.5 h-3.5" />
                        <span>Por Recibir ({counts.pendientes})</span>
                    </button>
                    <button
                        type="button"
                        onClick={() => handleFilterChange('recibidas')}
                        className={`flex-1 py-2 text-xs font-semibold rounded-lg flex items-center justify-center gap-1.5 transition-all ${
                            filters.filter === 'recibidas'
                                ? 'bg-white dark:bg-neutral-700 text-emerald-700 dark:text-emerald-400 shadow-sm'
                                : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900'
                        }`}
                    >
                        <CheckCircle2 className="w-3.5 h-3.5" />
                        <span>Recibidas ({counts.recibidas})</span>
                    </button>
                    <button
                        type="button"
                        onClick={() => handleFilterChange('todas')}
                        className={`py-2 px-3 text-xs font-semibold rounded-lg transition-all ${
                            filters.filter === 'todas'
                                ? 'bg-white dark:bg-neutral-700 text-neutral-900 dark:text-neutral-100 shadow-sm'
                                : 'text-neutral-600 dark:text-neutral-400'
                        }`}
                    >
                        Todas
                    </button>
                </div>

                {/* Search Bar */}
                <form onSubmit={handleSearch} className="relative">
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Buscar por orden, cliente, serie BF-EQ..."
                        className="w-full pl-9 pr-24 py-2.5 bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-xl text-sm placeholder-neutral-400 focus:outline-none focus:ring-2 focus:ring-amber-500"
                    />
                    <Search className="w-4 h-4 text-neutral-400 absolute left-3 top-3.5" />
                    <button
                        type="submit"
                        className="absolute right-1.5 top-1.5 bottom-1.5 px-3 bg-neutral-900 dark:bg-neutral-700 text-white rounded-lg text-xs font-medium"
                    >
                        Buscar
                    </button>
                </form>

                {/* Orders List */}
                <div className="space-y-3">
                    {orders.data.length === 0 ? (
                        <div className="p-8 text-center bg-white dark:bg-neutral-800/50 rounded-2xl border border-dashed border-neutral-200 dark:border-neutral-700">
                            <PackageOpen className="w-10 h-10 text-neutral-300 dark:text-neutral-600 mx-auto mb-2" />
                            <p className="text-sm font-medium text-neutral-600 dark:text-neutral-300">
                                No hay órdenes en esta lista
                            </p>
                            <p className="text-xs text-neutral-400 mt-1">
                                Las órdenes asignadas a Planta aparecerán aquí para su recepción física
                            </p>
                        </div>
                    ) : (
                        orders.data.map((order) => {
                            const isPendiente = order.estado === 'pendiente_recepcion';
                            const url = `/${currentTeam?.slug || currentTeam?.id}/tecnico-planta/recepciones/${order.id}`;

                            return (
                                <Link
                                    key={order.id}
                                    href={url}
                                    className="block p-4 bg-white dark:bg-neutral-800 rounded-2xl border border-neutral-200 dark:border-neutral-700/80 shadow-sm active:scale-[0.99] transition-transform"
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <span className="font-mono text-sm font-bold text-neutral-900 dark:text-neutral-100">
                                                    {order.codigo}
                                                </span>
                                                {isPendiente ? (
                                                    <span className="px-2 py-0.5 text-[10px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 rounded-full flex items-center gap-1">
                                                        <AlertCircle className="w-2.5 h-2.5" />
                                                        Por Recibir
                                                    </span>
                                                ) : (
                                                    <span className="px-2 py-0.5 text-[10px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 rounded-full flex items-center gap-1">
                                                        <CheckCircle2 className="w-2.5 h-2.5" />
                                                        Recibido en Planta
                                                    </span>
                                                )}
                                            </div>
                                            <div className="font-medium text-sm text-neutral-800 dark:text-neutral-200 mt-1 line-clamp-1">
                                                {order.cliente}
                                            </div>
                                        </div>
                                        <ArrowRight className="w-5 h-5 text-neutral-300 dark:text-neutral-600 flex-shrink-0" />
                                    </div>

                                    <div className="mt-3 pt-3 border-t border-neutral-100 dark:border-neutral-700/60 flex items-center justify-between text-xs text-neutral-500 dark:text-neutral-400">
                                        <div className="flex items-center gap-1.5">
                                            <QrCode className="w-3.5 h-3.5 text-amber-600" />
                                            <span>
                                                {order.equipos_count > 0
                                                    ? `${order.equipos_count} equipo(s)`
                                                    : 'Sin equipos registrados'}
                                            </span>
                                        </div>
                                        <span>{order.tipo_servicio}</span>
                                    </div>
                                </Link>
                            );
                        })
                    )}
                </div>
            </div>
        </TecnicoPlantaLayout>
    );
}
