import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import TecnicoCampoLayout from '@/layouts/tecnico-campo-layout';
import type { Team } from '@/types';
import {
    Truck,
    Search,
    MapPin,
    CheckCircle2,
    Clock,
    ArrowRight,
} from 'lucide-react';

interface RecojoItem {
    id: number;
    codigo: string;
    cliente: string;
    direccion: string | null;
    telefono: string | null;
    fecha: string;
    prioridad: string;
    estado: string;
    vehiculo: string | null;
    equipos_count: number;
    ya_recogido: boolean;
    recogido_at: string | null;
}

interface Props {
    recojos: {
        data: RecojoItem[];
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: {
        search: string;
    };
}

export default function RecojosIndex({ recojos, filters }: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug = currentTeam?.slug ?? '';
    const teamPrefix = `/${teamSlug}/tecnico-campo`;

    const [search, setSearch] = useState(filters.search || '');

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            `${teamPrefix}/recojos`,
            { search: search.trim() },
            { preserveState: true },
        );
    };

    return (
        <TecnicoCampoLayout>
            <Head title="Recojos en Campo - Cadena de Custodia" />

            <div className="space-y-4 pb-16">
                <div>
                    <h1 className="text-xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100">
                        Recojos de Extintores
                    </h1>
                    <p className="text-xs text-neutral-500">
                        Cadena de custodia y recepción en instalaciones del
                        cliente
                    </p>
                </div>

                {/* Search Bar */}
                <form onSubmit={handleSearch} className="relative">
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Buscar por cliente, orden o dirección..."
                        className="bg-card w-full rounded-xl border border-neutral-200 py-2.5 pr-24 pl-9 text-xs placeholder-neutral-400 focus:ring-2 focus:ring-blue-500 dark:border-neutral-700 dark:bg-neutral-800"
                    />
                    <Search className="absolute top-3.5 left-3 h-4 w-4 text-neutral-400" />
                    <button
                        type="submit"
                        className="absolute top-1.5 right-1.5 bottom-1.5 rounded-lg bg-neutral-900 px-3 text-xs font-medium text-white dark:bg-neutral-700"
                    >
                        Buscar
                    </button>
                </form>

                {/* List */}
                <div className="space-y-3">
                    {recojos.data.length === 0 ? (
                        <div className="bg-card space-y-2 rounded-2xl border border-dashed border-neutral-200 p-8 text-center dark:border-neutral-700 dark:bg-neutral-800/40">
                            <Truck className="mx-auto h-8 w-8 text-neutral-400" />
                            <p className="text-xs font-medium text-neutral-600 dark:text-neutral-300">
                                No hay recojos pendientes
                            </p>
                        </div>
                    ) : (
                        recojos.data.map((r) => (
                            <Link
                                key={r.id}
                                href={`${teamPrefix}/recojos/${r.id}`}
                                className="bg-card block space-y-2.5 rounded-2xl border border-neutral-200 p-4 shadow-sm transition-transform active:scale-[0.99] dark:border-neutral-700/80 dark:bg-neutral-800"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <span className="font-mono text-sm font-extrabold text-neutral-900 dark:text-neutral-100">
                                                {r.codigo}
                                            </span>
                                            {r.ya_recogido ? (
                                                <span className="flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                                    <CheckCircle2 className="h-2.5 w-2.5" />
                                                    Recogido
                                                </span>
                                            ) : (
                                                <span className="flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                                                    <Clock className="h-2.5 w-2.5" />
                                                    Por Recoger
                                                </span>
                                            )}
                                        </div>
                                        <h3 className="mt-1 line-clamp-1 text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                                            {r.cliente}
                                        </h3>
                                    </div>
                                    <ArrowRight className="h-5 w-5 flex-shrink-0 text-neutral-300 dark:text-neutral-600" />
                                </div>

                                {r.direccion && (
                                    <div className="flex items-start gap-1.5 text-xs text-neutral-600 dark:text-neutral-400">
                                        <MapPin className="mt-0.5 h-3.5 w-3.5 flex-shrink-0 text-blue-600" />
                                        <span className="line-clamp-2">
                                            {r.direccion}
                                        </span>
                                    </div>
                                )}

                                <div className="flex items-center justify-between border-t border-neutral-100 pt-2 text-[11px] text-neutral-500 dark:border-neutral-700/60">
                                    <span>
                                        {r.equipos_count > 0
                                            ? `${r.equipos_count} equipo(s)`
                                            : 'Cantidad a confirmar'}
                                    </span>
                                    {r.vehiculo && (
                                        <span>Vehículo: {r.vehiculo}</span>
                                    )}
                                </div>
                            </Link>
                        ))
                    )}
                </div>
            </div>
        </TecnicoCampoLayout>
    );
}
