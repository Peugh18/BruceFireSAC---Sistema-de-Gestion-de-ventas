import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import TecnicoPlantaLayout from '@/layouts/tecnico-planta-layout';
import type { Team } from '@/types';
import {
    Search,
    Inbox,
    CheckCircle2,
    QrCode,
    ArrowRight,
    PackageOpen,
    AlertCircle,
    RotateCcw,
} from 'lucide-react';

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
            { preserveState: true },
        );
    };

    const handleFilterChange = (newFilter: string) => {
        router.get(
            `/${teamSlug}/tecnico-planta/recepciones`,
            { search, filter: newFilter },
            { preserveState: true },
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
                            Ingreso y verificación de equipos en taller
                        </p>
                    </div>
                </div>

                {/* Filter Pills */}
                <div className="flex gap-2 rounded-xl bg-neutral-100 p-1 dark:bg-neutral-800/60">
                    <button
                        type="button"
                        onClick={() => handleFilterChange('pendientes')}
                        className={`flex flex-1 items-center justify-center gap-1.5 rounded-lg py-2 text-xs font-semibold transition-all ${
                            filters.filter === 'pendientes'
                                ? 'bg-card text-amber-700 shadow-sm dark:bg-neutral-700 dark:text-amber-400'
                                : 'text-neutral-600 hover:text-neutral-900 dark:text-neutral-400'
                        }`}
                    >
                        <Inbox className="h-3.5 w-3.5" />
                        <span>Por Recibir ({counts.pendientes})</span>
                    </button>
                    <button
                        type="button"
                        onClick={() => handleFilterChange('recibidas')}
                        className={`flex flex-1 items-center justify-center gap-1.5 rounded-lg py-2 text-xs font-semibold transition-all ${
                            filters.filter === 'recibidas'
                                ? 'bg-card text-emerald-700 shadow-sm dark:bg-neutral-700 dark:text-emerald-400'
                                : 'text-neutral-600 hover:text-neutral-900 dark:text-neutral-400'
                        }`}
                    >
                        <CheckCircle2 className="h-3.5 w-3.5" />
                        <span>Recibidas ({counts.recibidas})</span>
                    </button>
                    <button
                        type="button"
                        onClick={() => handleFilterChange('todas')}
                        className={`rounded-lg px-3 py-2 text-xs font-semibold transition-all ${
                            filters.filter === 'todas'
                                ? 'bg-card text-neutral-900 shadow-sm dark:bg-neutral-700 dark:text-neutral-100'
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
                        className="bg-card w-full rounded-xl border border-neutral-200 py-2.5 pr-24 pl-9 text-sm placeholder-neutral-400 focus:ring-2 focus:ring-amber-500 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800"
                    />
                    <Search className="absolute top-3.5 left-3 h-4 w-4 text-neutral-400" />
                    <button
                        type="submit"
                        className="absolute top-1.5 right-1.5 bottom-1.5 rounded-lg bg-neutral-900 px-3 text-xs font-medium text-white dark:bg-neutral-700"
                    >
                        Buscar
                    </button>
                </form>

                {/* Orders List */}
                <div className="space-y-3">
                    {orders.data.length === 0 ? (
                        <div className="bg-card rounded-2xl border border-dashed border-neutral-200 p-8 text-center dark:border-neutral-700 dark:bg-neutral-800/50">
                            <PackageOpen className="mx-auto mb-2 h-10 w-10 text-neutral-300 dark:text-neutral-600" />
                            <p className="text-sm font-medium text-neutral-600 dark:text-neutral-300">
                                No hay órdenes en esta lista
                            </p>
                            <p className="mt-1 text-xs text-neutral-400">
                                {search || filters.filter !== 'pendientes'
                                    ? 'No se encontraron órdenes con los criterios aplicados.'
                                    : 'Las órdenes asignadas a Planta aparecerán aquí para su recepción física.'}
                            </p>
                            {(search || filters.filter !== 'pendientes') && (
                                <button
                                    type="button"
                                    onClick={() => {
                                        setSearch('');
                                        router.get(
                                            `/${teamSlug}/tecnico-planta/recepciones`,
                                            { filter: 'pendientes' },
                                        );
                                    }}
                                    className="border-border bg-card text-foreground hover:bg-muted mt-3 inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold shadow-xs transition-colors"
                                >
                                    <RotateCcw className="size-3" />
                                    <span>Ver órdenes pendientes</span>
                                </button>
                            )}
                        </div>
                    ) : (
                        orders.data.map((order) => {
                            const isPendiente =
                                order.estado === 'pendiente_recepcion';
                            const url = `/${currentTeam?.slug || currentTeam?.id}/tecnico-planta/recepciones/${order.id}`;

                            return (
                                <Link
                                    key={order.id}
                                    href={url}
                                    className="bg-card block rounded-2xl border border-neutral-200 p-4 shadow-sm transition-transform active:scale-[0.99] dark:border-neutral-700/80 dark:bg-neutral-800"
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <span className="font-mono text-sm font-bold text-neutral-900 dark:text-neutral-100">
                                                    {order.codigo}
                                                </span>
                                                {isPendiente ? (
                                                    <span className="flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                                                        <AlertCircle className="h-2.5 w-2.5" />
                                                        Por Recibir
                                                    </span>
                                                ) : (
                                                    <span className="flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                                        <CheckCircle2 className="h-2.5 w-2.5" />
                                                        Recibido en Planta
                                                    </span>
                                                )}
                                            </div>
                                            <div className="mt-1 line-clamp-1 text-sm font-medium text-neutral-800 dark:text-neutral-200">
                                                {order.cliente}
                                            </div>
                                        </div>
                                        <ArrowRight className="h-5 w-5 flex-shrink-0 text-neutral-300 dark:text-neutral-600" />
                                    </div>

                                    <div className="mt-3 flex items-center justify-between border-t border-neutral-100 pt-3 text-xs text-neutral-500 dark:border-neutral-700/60 dark:text-neutral-400">
                                        <div className="flex items-center gap-1.5">
                                            <QrCode className="text-warning-strong h-3.5 w-3.5" />
                                            <span>
                                                {order.equipos_count > 0
                                                    ? `${order.equipos_count} equipo(s)`
                                                    : 'Sin equipos registrados'}
                                            </span>
                                        </div>
                                        <span className="capitalize">
                                            {order.tipo_servicio}
                                        </span>
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
