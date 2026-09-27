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

export default function DeficienciasIndex({
    deficiencies,
    counts,
    filters,
}: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug = currentTeam?.slug ?? '';
    const teamPrefix = `/${teamSlug}/tecnico-planta`;

    const [search, setSearch] = useState(filters.search || '');
    const [resolveModalItem, setResolveModalItem] =
        useState<DeficiencyItem | null>(null);

    const resolveForm = useForm({
        resolucion: '',
    });

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            `${teamPrefix}/deficiencias`,
            { estado: filters.estado, search: search.trim() },
            { preserveState: true },
        );
    };

    const handleFilterChange = (nuevoEstado: string) => {
        router.get(
            `${teamPrefix}/deficiencias`,
            { estado: nuevoEstado, search },
            { preserveState: true },
        );
    };

    const handleResolve = (e: React.FormEvent) => {
        e.preventDefault();
        if (!resolveModalItem) return;

        resolveForm.post(
            `${teamPrefix}/deficiencias/${resolveModalItem.id}/resolver`,
            {
                onSuccess: () => {
                    setResolveModalItem(null);
                    resolveForm.reset();
                },
            },
        );
    };

    const getEstadoBadge = (estado: string) => {
        switch (estado) {
            case 'esperando_autorizacion':
                return (
                    <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-900 dark:bg-amber-950/60 dark:text-amber-300">
                        <Clock className="h-3 w-3" />
                        Por Autorizar
                    </span>
                );
            case 'autorizada':
                return (
                    <span className="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-900 dark:bg-emerald-950/60 dark:text-emerald-300">
                        <CheckCircle2 className="h-3 w-3" />
                        Autorizada
                    </span>
                );
            case 'rechazada':
                return (
                    <span className="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-900 dark:bg-rose-950/60 dark:text-rose-300">
                        <XCircle className="h-3 w-3" />
                        Rechazada
                    </span>
                );
            case 'resuelta':
                return (
                    <span className="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-bold text-blue-900 dark:bg-blue-950/60 dark:text-blue-300">
                        <PackageCheck className="h-3 w-3" />
                        Resuelta
                    </span>
                );
            default:
                return (
                    <span className="inline-flex items-center gap-1 rounded-full bg-neutral-100 px-2 py-0.5 text-[10px] font-semibold text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200">
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
                        Gestión de observaciones, autorizaciones de Vendedor y
                        reparaciones
                    </p>
                </div>

                {/* Filter Pills */}
                <div className="no-scrollbar flex gap-1.5 overflow-x-auto pb-1">
                    <button
                        type="button"
                        onClick={() => handleFilterChange('todas')}
                        className={`rounded-xl px-3 py-1.5 text-xs font-bold whitespace-nowrap transition-colors ${
                            filters.estado === 'todas' || !filters.estado
                                ? 'bg-neutral-900 text-white shadow-xs dark:bg-neutral-100 dark:text-neutral-900'
                                : 'bg-card border border-neutral-200 text-neutral-600 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-400'
                        }`}
                    >
                        Todas ({counts.todas})
                    </button>
                    <button
                        type="button"
                        onClick={() =>
                            handleFilterChange('esperando_autorizacion')
                        }
                        className={`rounded-xl px-3 py-1.5 text-xs font-bold whitespace-nowrap transition-colors ${
                            filters.estado === 'esperando_autorizacion'
                                ? 'bg-amber-600 text-white shadow-xs'
                                : 'bg-card border border-neutral-200 text-neutral-600 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-400'
                        }`}
                    >
                        Por Autorizar ({counts.esperando_autorizacion})
                    </button>
                    <button
                        type="button"
                        onClick={() => handleFilterChange('autorizada')}
                        className={`rounded-xl px-3 py-1.5 text-xs font-bold whitespace-nowrap transition-colors ${
                            filters.estado === 'autorizada'
                                ? 'bg-emerald-600 text-white shadow-xs'
                                : 'bg-card border border-neutral-200 text-neutral-600 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-400'
                        }`}
                    >
                        Autorizadas ({counts.autorizada})
                    </button>
                    <button
                        type="button"
                        onClick={() => handleFilterChange('resuelta')}
                        className={`rounded-xl px-3 py-1.5 text-xs font-bold whitespace-nowrap transition-colors ${
                            filters.estado === 'resuelta'
                                ? 'bg-blue-600 text-white shadow-xs'
                                : 'bg-card border border-neutral-200 text-neutral-600 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-400'
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
                        className="bg-card w-full rounded-xl border border-neutral-200 py-2.5 pr-24 pl-9 text-xs placeholder-neutral-400 focus:ring-2 focus:ring-amber-500 dark:border-neutral-700 dark:bg-neutral-800"
                    />
                    <Search className="absolute top-3.5 left-3 h-4 w-4 text-neutral-400" />
                    <button
                        type="submit"
                        className="absolute top-1.5 right-1.5 bottom-1.5 rounded-lg bg-neutral-900 px-3 text-xs font-medium text-white dark:bg-neutral-700"
                    >
                        Buscar
                    </button>
                </form>

                {/* Deficiencies List */}
                <div className="space-y-3">
                    {deficiencies.data.length === 0 ? (
                        <div className="bg-card space-y-2 rounded-2xl border border-dashed border-neutral-200 p-8 text-center dark:border-neutral-700 dark:bg-neutral-800/40">
                            <ShieldAlert className="mx-auto h-8 w-8 text-neutral-400" />
                            <p className="text-xs font-medium text-neutral-600 dark:text-neutral-300">
                                No se encontraron deficiencias en este filtro
                            </p>
                        </div>
                    ) : (
                        deficiencies.data.map((d) => (
                            <div
                                key={d.id}
                                className="bg-card space-y-3 rounded-2xl border border-neutral-200 p-4 shadow-sm dark:border-neutral-700/80 dark:bg-neutral-800"
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
                                        <div className="mt-0.5 line-clamp-1 text-xs text-neutral-500">
                                            {d.cliente}
                                        </div>
                                    </div>

                                    {d.estado !== 'resuelta' &&
                                        d.repuesto_sugerido && (
                                            <Link
                                                href={`${teamPrefix}/ordenes/${d.orden_id}/ejecucion`}
                                                className="flex flex-shrink-0 items-center gap-1 rounded-xl bg-neutral-900 px-2.5 py-1.5 text-[11px] font-bold text-white hover:bg-neutral-800 dark:bg-neutral-100 dark:text-neutral-900"
                                                title="Requiere repuesto: resuélvela desde Ejecución para descontar el Kardex de Almacén"
                                            >
                                                <PackageCheck className="h-3 w-3" />
                                                <span>
                                                    Resolver con repuesto
                                                </span>
                                            </Link>
                                        )}
                                    {d.estado !== 'resuelta' &&
                                        !d.repuesto_sugerido && (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setResolveModalItem(d)
                                                }
                                                className="flex flex-shrink-0 items-center gap-1 rounded-xl bg-neutral-900 px-2.5 py-1.5 text-[11px] font-bold text-white hover:bg-neutral-800 dark:bg-neutral-100 dark:text-neutral-900"
                                            >
                                                <Wrench className="h-3 w-3" />
                                                <span>Resolver</span>
                                            </button>
                                        )}
                                </div>

                                {/* Component & Condition */}
                                <div className="space-y-1 rounded-xl bg-neutral-50 p-2.5 dark:bg-neutral-900/60">
                                    <div className="flex items-center gap-1.5 text-xs font-bold text-neutral-900 dark:text-neutral-100">
                                        <AlertTriangle className="h-3.5 w-3.5 text-amber-600" />
                                        <span>{d.componente}</span>
                                    </div>
                                    <p className="text-xs text-neutral-700 dark:text-neutral-300">
                                        {d.condicion}
                                    </p>
                                </div>

                                {/* Actions / Spare Parts */}
                                {(d.accion_recomendada ||
                                    d.repuesto_sugerido) && (
                                    <div className="grid grid-cols-2 gap-2 text-[11px] text-neutral-600 dark:text-neutral-400">
                                        {d.accion_recomendada && (
                                            <div>
                                                <span className="text-neutral-400">
                                                    Acción:{' '}
                                                </span>
                                                <span className="font-medium text-neutral-800 dark:text-neutral-200">
                                                    {d.accion_recomendada}
                                                </span>
                                            </div>
                                        )}
                                        {d.repuesto_sugerido && (
                                            <div>
                                                <span className="text-neutral-400">
                                                    Repuesto:{' '}
                                                </span>
                                                <span className="font-medium text-neutral-800 dark:text-neutral-200">
                                                    {d.repuesto_sugerido}
                                                </span>
                                            </div>
                                        )}
                                    </div>
                                )}

                                {/* Authorization Banner from Vendedor (§17, §20) */}
                                {d.authorization && (
                                    <div className="space-y-1 rounded-xl border border-emerald-200 bg-emerald-50 p-2.5 text-xs text-emerald-900 dark:border-emerald-800/60 dark:bg-emerald-950/30 dark:text-emerald-300">
                                        <div className="flex items-center gap-1.5 font-bold">
                                            <UserCheck className="h-3.5 w-3.5 text-emerald-600" />
                                            <span>
                                                Autorizado por{' '}
                                                {d.authorization.autorizado_por}{' '}
                                                ({d.authorization.canal})
                                            </span>
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
                                    <div className="rounded-xl border border-blue-200 bg-blue-50 p-2.5 text-xs text-blue-900 dark:border-blue-800/60 dark:bg-blue-950/30 dark:text-blue-300">
                                        <span className="font-bold">
                                            Resolución:{' '}
                                        </span>
                                        <span>{d.resolucion}</span>
                                    </div>
                                )}
                            </div>
                        ))
                    )}
                </div>

                {/* MODAL: Resolver Deficiencia */}
                {resolveModalItem && (
                    <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/60 p-0 backdrop-blur-xs sm:items-center sm:p-4">
                        <div className="bg-card w-full space-y-4 rounded-t-3xl border border-neutral-200 p-5 shadow-xl sm:max-w-md sm:rounded-2xl dark:border-neutral-800 dark:bg-neutral-900">
                            <div className="flex items-center justify-between border-b border-neutral-100 pb-3 dark:border-neutral-800">
                                <div>
                                    <h3 className="text-base font-bold text-neutral-900 dark:text-neutral-100">
                                        Resolver Deficiencia
                                    </h3>
                                    <p className="text-xs text-neutral-500">
                                        {resolveModalItem.componente} -{' '}
                                        {resolveModalItem.orden_codigo}
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

                            <form
                                onSubmit={handleResolve}
                                className="space-y-3"
                            >
                                <div>
                                    <label className="mb-1 block text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                                        Descripción del trabajo ejecutado *
                                    </label>
                                    <textarea
                                        value={resolveForm.data.resolucion}
                                        onChange={(e) =>
                                            resolveForm.setData(
                                                'resolucion',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Ej: Se reemplazó el manómetro y se presurizó a 195 PSI..."
                                        rows={3}
                                        className="w-full rounded-xl border border-neutral-300 bg-neutral-50 p-2.5 text-xs focus:ring-2 focus:ring-amber-500 dark:border-neutral-700 dark:bg-neutral-800"
                                        required
                                    />
                                </div>

                                <button
                                    type="submit"
                                    disabled={resolveForm.processing}
                                    className="flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 py-3 text-xs font-bold text-white shadow-sm hover:bg-emerald-700 active:bg-emerald-800"
                                >
                                    <CheckCircle2 className="h-4 w-4" />
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
