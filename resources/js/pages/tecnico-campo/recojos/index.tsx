import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import TecnicoCampoLayout from '@/layouts/tecnico-campo-layout';
import type { Team } from '@/types';
import {
    Truck,
    Search,
    MapPin,
    Phone,
    CheckCircle2,
    Clock,
    ArrowRight,
    Package,
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
            { preserveState: true }
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
                        Cadena de custodia y recepción en instalaciones del cliente (§22.1, §22.4)
                    </p>
                </div>

                {/* Search Bar */}
                <form onSubmit={handleSearch} className="relative">
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Buscar por cliente, orden o dirección..."
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

                {/* List */}
                <div className="space-y-3">
                    {recojos.data.length === 0 ? (
                        <div className="p-8 text-center bg-white dark:bg-neutral-800/40 rounded-2xl border border-dashed border-neutral-200 dark:border-neutral-700 space-y-2">
                            <Truck className="w-8 h-8 text-neutral-400 mx-auto" />
                            <p className="text-xs font-medium text-neutral-600 dark:text-neutral-300">
                                No hay recojos pendientes
                            </p>
                        </div>
                    ) : (
                        recojos.data.map((r) => (
                            <Link
                                key={r.id}
                                href={`${teamPrefix}/recojos/${r.id}`}
                                className="block p-4 bg-white dark:bg-neutral-800 rounded-2xl border border-neutral-200 dark:border-neutral-700/80 shadow-sm active:scale-[0.99] transition-transform space-y-2.5"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <span className="font-mono text-sm font-extrabold text-neutral-900 dark:text-neutral-100">
                                                {r.codigo}
                                            </span>
                                            {r.ya_recogido ? (
                                                <span className="px-2 py-0.5 text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 rounded-full flex items-center gap-1">
                                                    <CheckCircle2 className="w-2.5 h-2.5" />
                                                    Recogido
                                                </span>
                                            ) : (
                                                <span className="px-2 py-0.5 text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 rounded-full flex items-center gap-1">
                                                    <Clock className="w-2.5 h-2.5" />
                                                    Por Recoger
                                                </span>
                                            )}
                                        </div>
                                        <h3 className="font-semibold text-sm text-neutral-900 dark:text-neutral-100 mt-1 line-clamp-1">
                                            {r.cliente}
                                        </h3>
                                    </div>
                                    <ArrowRight className="w-5 h-5 text-neutral-300 dark:text-neutral-600 flex-shrink-0" />
                                </div>

                                {r.direccion && (
                                    <div className="flex items-start gap-1.5 text-xs text-neutral-600 dark:text-neutral-400">
                                        <MapPin className="w-3.5 h-3.5 text-blue-600 mt-0.5 flex-shrink-0" />
                                        <span className="line-clamp-2">{r.direccion}</span>
                                    </div>
                                )}

                                <div className="pt-2 border-t border-neutral-100 dark:border-neutral-700/60 flex items-center justify-between text-[11px] text-neutral-500">
                                    <span>{r.equipos_count > 0 ? `${r.equipos_count} equipo(s)` : 'Cantidad a confirmar'}</span>
                                    {r.vehiculo && <span>Vehículo: {r.vehiculo}</span>}
                                </div>
                            </Link>
                        ))
                    )}
                </div>
            </div>
        </TecnicoCampoLayout>
    );
}
