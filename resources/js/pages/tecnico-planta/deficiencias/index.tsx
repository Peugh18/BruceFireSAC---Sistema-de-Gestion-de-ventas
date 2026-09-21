import React, { useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import TecnicoPlantaLayout from '@/layouts/tecnico-planta-layout';
import type { Team } from '@/types';
import {
    AlertTriangle,
    CheckCircle2,
    Clock,
    XCircle,
    Search,
    Wrench,
    ShieldAlert,
    UserCheck,
    MessageSquare,
    PackageCheck,
} from 'lucide-react';

interface DeficiencyItem {
    id: number;
    orden_id: number;
    orden_codigo: string;
    cliente: string;
    equipment_id: number | null;
    equipo_serie: string | null;
    equipo_tipo: string | null;
    componente: string;
    condicion: string;
    nota: string | null;
    accion_recomendada: string | null;
    repuesto_sugerido: string | null;
    requiere_autorizacion: boolean;
    estado: string;
    resolucion: string | null;
    authorization: {
        autorizado_por: string;
        canal: string;
        fecha: string;
        observacion: string | null;
        vendedor: string | null;
    } | null;
    created_at: string | null;
}

interface Props {
    deficiencies: {
        data: DeficiencyItem[];
        current_page: number;
        last_page: number;
        total: number;
    };
    counts: {
        todas: number;
        esperando_autorizacion: number;
        autorizada: number;
        rechazada: number;
        resuelta: number;
    };
    filters: {
        estado: string;
        search: string;
    };
}

export default function DeficienciasIndex({ deficiencies, counts, filters }: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug = currentTeam?.slug ?? '';
    const teamPrefix = `/${teamSlug}/tecnico-planta`;

    const [search, setSearch] = useState(filters.search || '');
    const [resolveModalItem, setResolveModalItem] = useState<DeficiencyItem | null>(null);

    const resolveForm = useForm({
        resolucion: '',
    });

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            `${teamPrefix}/deficiencias`,
            { estado: filters.estado, search: search.trim() },
            { preserveState: true }
        );
    };

    const handleFilterChange = (nuevoEstado: string) => {
        router.get(
            `${teamPrefix}/deficiencias`,
            { estado: nuevoEstado, search },
            { preserveState: true }
        );
    };

    const handleResolve = (e: React.FormEvent) => {
        e.preventDefault();
        if (!resolveModalItem) return;

        resolveForm.post(`${teamPrefix}/deficiencias/${resolveModalItem.id}/resolver`, {
            onSuccess: () => {
                setResolveModalItem(null);
                resolveForm.reset();
            },
        });
    };

    const getEstadoBadge = (estado: string) => {
        switch (estado) {
            case 'esperando_autorizacion':
                return (
                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-900 dark:bg-amber-950/60 dark:text-amber-300">
                        <Clock className="w-3 h-3" />
                        Por Autorizar
                    </span>
                );
            case 'autorizada':
                return (
                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-900 dark:bg-emerald-950/60 dark:text-emerald-300">
                        <CheckCircle2 className="w-3 h-3" />
                        Autorizada
                    </span>
                );
            case 'rechazada':
                return (
                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-900 dark:bg-rose-950/60 dark:text-rose-300">
                        <XCircle className="w-3 h-3" />
                        Rechazada
                    </span>
                );
            case 'resuelta':
                return (
                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-900 dark:bg-blue-950/60 dark:text-blue-300">
                        <PackageCheck className="w-3 h-3" />
                        Resuelta
                    </span>
                );
            default:
                return (
                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200">
                        {estado}
                    </span>
                );
        }
    };

    return (
        <TecnicoPlantaLayout>
            <Head title="Deficiencias - Taller Planta" />

            <div className="space-y-4 pb-12">
                {/* Title */}
                <div>
                    <h1 className="text-xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100">
                        Deficiencias de Taller
                    </h1>
                    <p className="text-xs text-neutral-500">
                        Gestión de observaciones, autorizaciones de Vendedor y reparaciones (§20)
                    </p>
                </div>

                {/* Filter Pills */}
                <div className="flex gap-1.5 overflow-x-auto pb-1 no-scrollbar">
                    <button
                        type="button"
                        onClick={() => handleFilterChange('todas')}
                        className={`px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-colors ${
                            filters.estado === 'todas' || !filters.estado
                                ? 'bg-neutral-900 dark:bg-neutral-100 text-white dark:text-neutral-900 shadow-xs'
                                : 'bg-white dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 border border-neutral-200 dark:border-neutral-700'
                        }`}
                    >
                        Todas ({counts.todas})
                    </button>
                    <button
                        type="button"
                        onClick={() => handleFilterChange('esperando_autorizacion')}
                        className={`px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-colors ${
                            filters.estado === 'esperando_autorizacion'
                                ? 'bg-amber-600 text-white shadow-xs'
                                : 'bg-white dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 border border-neutral-200 dark:border-neutral-700'
                        }`}
                    >
                        Por Autorizar ({counts.esperando_autorizacion})
                    </button>
                    <button
                        type="button"
                        onClick={() => handleFilterChange('autorizada')}
                        className={`px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-colors ${
                            filters.estado === 'autorizada'
                                ? 'bg-emerald-600 text-white shadow-xs'
                                : 'bg-white dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 border border-neutral-200 dark:border-neutral-700'
                        }`}
                    >
                        Autorizadas ({counts.autorizada})
                    </button>
                    <button
                        type="button"
                        onClick={() => handleFilterChange('resuelta')}
                        className={`px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-colors ${
                            filters.estado === 'resuelta'
                                ? 'bg-blue-600 text-white shadow-xs'
                                : 'bg-white dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 border border-neutral-200 dark:border-neutral-700'
                        }`}
                    >
                        Resueltas ({counts.resuelta})
                    </button>
                </div>

                {/* Search Bar */}
                <form onSubmit={handleSearch} className="relative">
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Buscar por componente, falla, orden, serie..."
                        className="w-full pl-9 pr-24 py-2.5 bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-xl text-xs placeholder-neutral-400 focus:ring-2 focus:ring-amber-500"
                    />
                    <Search className="w-4 h-4 text-neutral-400 absolute left-3 top-3.5" />
                    <button
                        type="submit"
                        className="absolute right-1.5 top-1.5 bottom-1.5 px-3 bg-neutral-900 dark:bg-neutral-700 text-white rounded-lg text-xs font-medium"
                    >
                        Buscar
                    </button>
                </form>

                {/* Deficiencies List */}
                <div className="space-y-3">
                    {deficiencies.data.length === 0 ? (
                        <div className="p-8 text-center bg-white dark:bg-neutral-800/40 rounded-2xl border border-dashed border-neutral-200 dark:border-neutral-700 space-y-2">
                            <ShieldAlert className="w-8 h-8 text-neutral-400 mx-auto" />
                            <p className="text-xs font-medium text-neutral-600 dark:text-neutral-300">
                                No se encontraron deficiencias en este filtro
                            </p>
                        </div>
                    ) : (
                        deficiencies.data.map((d) => (
                            <div
                                key={d.id}
                                className="p-4 bg-white dark:bg-neutral-800 rounded-2xl border border-neutral-200 dark:border-neutral-700/80 shadow-sm space-y-3"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <span className="font-mono text-xs font-extrabold text-neutral-900 dark:text-neutral-100">
                                                {d.orden_codigo}
                                            </span>
                                            {d.equipo_serie && (
                                                <span className="font-mono text-xs font-bold text-amber-600 dark:text-amber-400">
                                                    {d.equipo_serie}
                                                </span>
                                            )}
                                            {getEstadoBadge(d.estado)}
                                        </div>
                                        <div className="text-xs text-neutral-500 line-clamp-1 mt-0.5">
                                            {d.cliente}
                                        </div>
                                    </div>

                                    {d.estado !== 'resuelta' && d.repuesto_sugerido && (
                                        <Link
                                            href={`${teamPrefix}/ordenes/${d.orden_id}/ejecucion`}
                                            className="px-2.5 py-1.5 bg-neutral-900 dark:bg-neutral-100 hover:bg-neutral-800 text-white dark:text-neutral-900 rounded-xl text-[11px] font-bold flex items-center gap-1 flex-shrink-0"
                                            title="Requiere repuesto: resuélvela desde Ejecución para descontar el Kardex de Almacén"
                                        >
                                            <PackageCheck className="w-3 h-3" />
                                            <span>Resolver con repuesto</span>
                                        </Link>
                                    )}
                                    {d.estado !== 'resuelta' && !d.repuesto_sugerido && (
                                        <button
                                            type="button"
                                            onClick={() => setResolveModalItem(d)}
                                            className="px-2.5 py-1.5 bg-neutral-900 dark:bg-neutral-100 hover:bg-neutral-800 text-white dark:text-neutral-900 rounded-xl text-[11px] font-bold flex items-center gap-1 flex-shrink-0"
                                        >
                                            <Wrench className="w-3 h-3" />
                                            <span>Resolver</span>
                                        </button>
                                    )}
                                </div>

                                {/* Component & Condition */}
                                <div className="p-2.5 bg-neutral-50 dark:bg-neutral-900/60 rounded-xl space-y-1">
                                    <div className="flex items-center gap-1.5 text-xs font-bold text-neutral-900 dark:text-neutral-100">
                                        <AlertTriangle className="w-3.5 h-3.5 text-amber-600" />
                                        <span>{d.componente}</span>
                                    </div>
                                    <p className="text-xs text-neutral-700 dark:text-neutral-300">
                                        {d.condicion}
                                    </p>
                                </div>

                                {/* Actions / Spare Parts */}
                                {(d.accion_recomendada || d.repuesto_sugerido) && (
                                    <div className="grid grid-cols-2 gap-2 text-[11px] text-neutral-600 dark:text-neutral-400">
                                        {d.accion_recomendada && (
                                            <div>
                                                <span className="text-neutral-400">Acción: </span>
                                                <span className="font-medium text-neutral-800 dark:text-neutral-200">
                                                    {d.accion_recomendada}
                                                </span>
                                            </div>
                                        )}
                                        {d.repuesto_sugerido && (
                                            <div>
                                                <span className="text-neutral-400">Repuesto: </span>
                                                <span className="font-medium text-neutral-800 dark:text-neutral-200">
                                                    {d.repuesto_sugerido}
                                                </span>
                                            </div>
                                        )}
                                    </div>
                                )}

                                {/* Authorization Banner from Vendedor (§17, §20) */}
                                {d.authorization && (
                                    <div className="p-2.5 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/60 rounded-xl text-xs text-emerald-900 dark:text-emerald-300 space-y-1">
                                        <div className="flex items-center gap-1.5 font-bold">
                                            <UserCheck className="w-3.5 h-3.5 text-emerald-600" />
                                            <span>Autorizado por {d.authorization.autorizado_por} ({d.authorization.canal})</span>
                                        </div>
                                        {d.authorization.observacion && (
                                            <p className="text-[11px] text-emerald-800 dark:text-emerald-400">
                                                "{d.authorization.observacion}"
                                            </p>
                                        )}
                                    </div>
                                )}

                                {/* Resolution Note if Resolved */}
                                {d.resolucion && (
                                    <div className="p-2.5 bg-blue-50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-800/60 rounded-xl text-xs text-blue-900 dark:text-blue-300">
                                        <span className="font-bold">Resolución: </span>
                                        <span>{d.resolucion}</span>
                                    </div>
                                )}
                            </div>
                        ))
                    )}
                </div>

                {/* MODAL: Resolver Deficiencia */}
                {resolveModalItem && (
                    <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-end sm:items-center justify-center p-0 sm:p-4">
                        <div className="w-full sm:max-w-md bg-white dark:bg-neutral-900 rounded-t-3xl sm:rounded-2xl p-5 space-y-4 shadow-xl border border-neutral-200 dark:border-neutral-800">
                            <div className="flex items-center justify-between border-b border-neutral-100 dark:border-neutral-800 pb-3">
                                <div>
                                    <h3 className="text-base font-bold text-neutral-900 dark:text-neutral-100">
                                        Resolver Deficiencia
                                    </h3>
                                    <p className="text-xs text-neutral-500">
                                        {resolveModalItem.componente} - {resolveModalItem.orden_codigo}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setResolveModalItem(null)}
                                    className="p-1 text-neutral-400"
                                >
                                    ✕
                                </button>
                            </div>

                            <form onSubmit={handleResolve} className="space-y-3">
                                <div>
                                    <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                                        Descripción del trabajo ejecutado *
                                    </label>
                                    <textarea
                                        value={resolveForm.data.resolucion}
                                        onChange={(e) => resolveForm.setData('resolucion', e.target.value)}
                                        placeholder="Ej: Se reemplazó el manómetro y se presurizó a 195 PSI..."
                                        rows={3}
                                        className="w-full text-xs p-2.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-xl focus:ring-2 focus:ring-amber-500"
                                        required
                                    />
                                </div>

                                <button
                                    type="submit"
                                    disabled={resolveForm.processing}
                                    className="w-full py-3 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white rounded-xl text-xs font-bold flex items-center justify-center gap-2 shadow-sm"
                                >
                                    <CheckCircle2 className="w-4 h-4" />
                                    <span>Marcar como Resuelta en Taller</span>
                                </button>
                            </form>
                        </div>
                    </div>
                )}
            </div>
        </TecnicoPlantaLayout>
    );
}
