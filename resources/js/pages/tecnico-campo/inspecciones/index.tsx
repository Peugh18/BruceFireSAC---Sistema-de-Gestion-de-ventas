import { Head, Link, router } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowRight,
    CheckCircle2,
    ChevronRight,
    ClipboardCheck,
    Clock,
    Flame,
    MapPin,
    Plus,
    Search,
} from 'lucide-react';
import { useState } from 'react';

import TecnicoCampoLayout from '@/layouts/tecnico-campo-layout';
import type { Team } from '@/types';

type Customer = {
    id: number;
    razon_social: string;
    ruc?: string;
    telefono?: string;
    direccion?: string;
};

type Branch = {
    id: number;
    nombre: string;
    direccion?: string;
};

type Equipment = {
    id: number;
    numero_serie: string;
    tipo_agente?: string;
    capacidad?: string;
};

type ServiceOrder = {
    id: number;
    codigo: string;
    estado: string;
    tipo_servicio?: string;
    departamento_tecnico?: string;
    fecha_programada?: string;
    customer: Customer;
    branch?: Branch;
    equipments: Equipment[];
};

type PaginatedData<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    next_page_url?: string;
    prev_page_url?: string;
    total: number;
};

type Props = {
    currentTeam?: Team | null;
    inspecciones: PaginatedData<ServiceOrder>;
    currentTab: 'todos' | 'pendientes' | 'en_proceso' | 'finalizadas';
    search?: string;
    stats: {
        total: number;
        pendientes: number;
        en_proceso: number;
        finalizadas: number;
    };
};

const ESTADOS_MAP: Record<
    string,
    { label: string; bg: string; text: string; border: string }
> = {
    pendiente_recojo: {
        label: 'Por Iniciar',
        bg: 'bg-amber-500/10',
        text: 'text-amber-600 dark:text-amber-400',
        border: 'border-amber-500/20',
    },
    registrado: {
        label: 'Registrado',
        bg: 'bg-muted',
        text: 'text-muted-foreground',
        border: 'border-border',
    },
    en_revision: {
        label: 'En Inspección',
        bg: 'bg-sky-500/10',
        text: 'text-sky-700 dark:text-sky-400',
        border: 'border-sky-500/20',
    },
    en_proceso: {
        label: 'En Proceso',
        bg: 'bg-sky-500/10',
        text: 'text-blue-600 dark:text-blue-400',
        border: 'border-sky-500/20',
    },
    esperando_autorizacion: {
        label: 'Con Deficiencias',
        bg: 'bg-destructive/10',
        text: 'text-destructive',
        border: 'border-destructive/20',
    },
    listo_entrega: {
        label: 'Inspeccionado',
        bg: 'bg-emerald-500/10',
        text: 'text-emerald-600 dark:text-emerald-400',
        border: 'border-emerald-500/20',
    },
    cerrado: {
        label: 'Cerrado',
        bg: 'bg-muted',
        text: 'text-muted-foreground',
        border: 'border-border',
    },
};

export default function InspeccionesIndex({
    currentTeam,
    inspecciones,
    currentTab,
    search = '',
    stats,
}: Props) {
    const teamSlug = currentTeam?.slug ?? '';
    const [searchTerm, setSearchTerm] = useState(search);

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            `/${teamSlug}/tecnico-campo/inspecciones`,
            { tab: currentTab, q: searchTerm },
            { preserveState: true },
        );
    };

    const handleTabChange = (tab: string) => {
        router.get(
            `/${teamSlug}/tecnico-campo/inspecciones`,
            { tab, q: searchTerm },
            { preserveState: true },
        );
    };

    return (
        <TecnicoCampoLayout title="Inspecciones de Campo">
            <Head title="Inspecciones de Campo - Técnico de Campo" />

            {/* Header Mobile Title */}
            <div className="mb-4 flex items-center justify-between">
                <div>
                    <h1 className="flex items-center gap-2 text-xl font-black text-foreground">
                        <ClipboardCheck className="size-6 text-sky-600 dark:text-sky-400" />
                        Inspecciones de Campo
                    </h1>
                    <p className="text-xs text-muted-foreground">
                        Inspección física y checklist técnico de extintores en
                        sitio.
                    </p>
                </div>
            </div>

            {/* Tactical KPI Pills */}
            <div className="mb-4 grid grid-cols-4 gap-2">
                <button
                    onClick={() => handleTabChange('todos')}
                    className={`rounded-[12px] border p-2.5 text-left transition-all ${
                        currentTab === 'todos'
                            ? 'border-sky-500/20 bg-sky-600 text-white shadow-sm'
                            : 'border-border bg-card text-foreground'
                    }`}
                >
                    <span className="block text-[10px] font-bold uppercase opacity-80">
                        Todas
                    </span>
                    <span className="text-lg font-black">{stats.total}</span>
                </button>
                <button
                    onClick={() => handleTabChange('pendientes')}
                    className={`rounded-[12px] border p-2.5 text-left transition-all ${
                        currentTab === 'pendientes'
                            ? 'border-sky-500/20 bg-sky-600 text-white shadow-sm'
                            : 'border-border bg-card text-foreground'
                    }`}
                >
                    <span className="block text-[10px] font-bold uppercase opacity-80">
                        Por Iniciar
                    </span>
                    <span className="text-lg font-black text-amber-600 dark:text-amber-400">
                        {stats.pendientes}
                    </span>
                </button>
                <button
                    onClick={() => handleTabChange('en_proceso')}
                    className={`rounded-[12px] border p-2.5 text-left transition-all ${
                        currentTab === 'en_proceso'
                            ? 'border-sky-500/20 bg-sky-600 text-white shadow-sm'
                            : 'border-border bg-card text-foreground'
                    }`}
                >
                    <span className="block text-[10px] font-bold uppercase opacity-80">
                        En Proceso
                    </span>
                    <span className="text-lg font-black text-sky-600 dark:text-sky-400">
                        {stats.en_proceso}
                    </span>
                </button>
                <button
                    onClick={() => handleTabChange('finalizadas')}
                    className={`rounded-[12px] border p-2.5 text-left transition-all ${
                        currentTab === 'finalizadas'
                            ? 'border-sky-500/20 bg-sky-600 text-white shadow-sm'
                            : 'border-border bg-card text-foreground'
                    }`}
                >
                    <span className="block text-[10px] font-bold uppercase opacity-80">
                        Completas
                    </span>
                    <span className="text-lg font-black text-emerald-600 dark:text-emerald-400">
                        {stats.finalizadas}
                    </span>
                </button>
            </div>

            {/* Search Input */}
            <form onSubmit={handleSearch} className="mb-4">
                <div className="relative">
                    <Search className="absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        type="text"
                        placeholder="Buscar por orden, RUC o cliente..."
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        className="w-full rounded-[10px] border border-border bg-card py-2 pr-4 pl-9 text-xs focus:ring-2 focus:ring-[#0284C7] focus:outline-hidden"
                    />
                </div>
            </form>

            {/* List of Inspection Orders (Touch Cards) */}
            <div className="space-y-3">
                {inspecciones.data.length === 0 ? (
                    <div className="rounded-[12px] border border-border bg-card p-8 text-center">
                        <ClipboardCheck className="mx-auto mb-2 size-10 text-muted-foreground/40" />
                        <p className="text-xs font-semibold text-foreground">
                            No hay inspecciones en este filtro
                        </p>
                        <p className="mt-0.5 text-[11px] text-muted-foreground">
                            Selecciona otro estado o busca por nombre de
                            cliente.
                        </p>
                    </div>
                ) : (
                    inspecciones.data.map((order) => {
                        const estadoMeta = ESTADOS_MAP[order.estado] || {
                            label: order.estado,
                            bg: 'bg-muted',
                            text: 'text-muted-foreground',
                            border: 'border-border',
                        };

                        return (
                            <Link
                                key={order.id}
                                href={`/${teamSlug}/tecnico-campo/inspecciones/${order.id}`}
                                className="block rounded-[14px] border border-border bg-card p-4 shadow-xs transition-all hover:border-sky-500/20 active:scale-[0.99]"
                            >
                                <div className="mb-2 flex items-start justify-between gap-2">
                                    <div>
                                        <div className="flex items-center gap-1.5">
                                            <span className="font-mono text-xs font-black text-sky-600 dark:text-sky-400">
                                                {order.codigo}
                                            </span>
                                            <span
                                                className={`rounded-full border px-2 py-0.5 text-[9.5px] font-bold ${estadoMeta.bg} ${estadoMeta.text} ${estadoMeta.border}`}
                                            >
                                                {estadoMeta.label}
                                            </span>
                                        </div>
                                        <h2 className="mt-0.5 line-clamp-1 text-sm font-bold text-foreground">
                                            {order.customer?.razon_social}
                                        </h2>
                                        {order.customer?.ruc && (
                                            <span className="font-mono text-[11px] text-muted-foreground">
                                                RUC: {order.customer.ruc}
                                            </span>
                                        )}
                                    </div>
                                    <ChevronRight className="mt-1 size-5 shrink-0 text-muted-foreground" />
                                </div>

                                <div className="mb-3 flex items-center gap-1 text-[11px] text-muted-foreground">
                                    <MapPin className="size-3.5 shrink-0 text-sky-600 dark:text-sky-400" />
                                    <span className="line-clamp-1">
                                        {order.branch?.direccion ||
                                            order.customer?.direccion ||
                                            'Dirección de sede cliente'}
                                    </span>
                                </div>

                                <div className="flex items-center justify-between border-t border-border pt-2.5">
                                    <div className="flex items-center gap-1.5 text-[11px] font-medium text-foreground">
                                        <Flame className="size-3.5 text-amber-600 dark:text-amber-400" />
                                        <span>
                                            {order.equipments?.length || 0}{' '}
                                            {order.equipments?.length === 1
                                                ? 'extintor'
                                                : 'extintores'}{' '}
                                            registrados
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-1 text-[11px] font-bold text-sky-600 dark:text-sky-400">
                                        <span>Realizar checklist</span>
                                        <ArrowRight className="size-3.5" />
                                    </div>
                                </div>
                            </Link>
                        );
                    })
                )}
            </div>

            {/* Pagination */}
            {inspecciones.last_page > 1 && (
                <div className="mt-6 flex items-center justify-center gap-2">
                    {inspecciones.prev_page_url && (
                        <Link
                            href={inspecciones.prev_page_url}
                            className="rounded-[8px] border border-border bg-card px-3 py-1.5 text-xs font-bold text-foreground"
                        >
                            Anterior
                        </Link>
                    )}
                    <span className="text-xs text-muted-foreground">
                        Página {inspecciones.current_page} de{' '}
                        {inspecciones.last_page}
                    </span>
                    {inspecciones.next_page_url && (
                        <Link
                            href={inspecciones.next_page_url}
                            className="rounded-[8px] border border-border bg-card px-3 py-1.5 text-xs font-bold text-foreground"
                        >
                            Siguiente
                        </Link>
                    )}
                </div>
            )}
        </TecnicoCampoLayout>
    );
}
