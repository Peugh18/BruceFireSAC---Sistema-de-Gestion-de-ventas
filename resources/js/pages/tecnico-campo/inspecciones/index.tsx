import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowRight,
    ChevronRight,
    ClipboardCheck,
    Flame,
    MapPin,
    RotateCcw,
    Search,
} from 'lucide-react';
import { useState } from 'react';

import TecnicoCampoLayout from '@/layouts/tecnico-campo-layout';
import type { Team } from '@/types';
import InspeccionesRoutes from '@/routes/tecnico-campo/inspecciones';
import { etiquetaEstadoOrden } from '@/lib/estados-orden';
import { Empty } from '@/components/ui/empty';

type Customer = {
    id: number;
    razon_social: string;
    numero_documento?: string;
    telefono?: string;
    direccion_fiscal?: string;
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
    client: Customer;
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
        text: 'text-warning-strong',
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
        bg: 'bg-muted',
        text: 'text-foreground ',
        border: 'border-border',
    },
    en_proceso: {
        label: 'En Proceso',
        bg: 'bg-muted',
        text: 'text-foreground ',
        border: 'border-border',
    },
    esperando_autorizacion: {
        label: 'Con Deficiencias',
        bg: 'bg-destructive/10',
        text: 'text-destructive-strong',
        border: 'border-destructive/20',
    },
    listo_entrega: {
        label: 'Inspeccionado',
        bg: 'bg-emerald-500/10',
        text: 'text-success-strong',
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
            InspeccionesRoutes.index.url({ current_team: teamSlug }),
            { tab: currentTab, q: searchTerm },
            { preserveState: true },
        );
    };

    const handleTabChange = (tab: string) => {
        router.get(
            InspeccionesRoutes.index.url({ current_team: teamSlug }),
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
                    <h1 className="text-foreground flex items-center gap-2 text-xl font-black">
                        <ClipboardCheck className="text-foreground size-6" />
                        Inspecciones de Campo
                    </h1>
                    <p className="text-muted-foreground text-xs">
                        Inspección física y checklist técnico de extintores en
                        sitio.
                    </p>
                </div>
            </div>

            {/* Tactical KPI Pills */}
            <div className="mb-4 grid grid-cols-4 gap-2">
                <button
                    onClick={() => handleTabChange('todos')}
                    className={`rounded-2xl border p-2.5 text-left transition-all ${
                        currentTab === 'todos'
                            ? 'border-border bg-primary text-white shadow-sm [&_span]:text-white!'
                            : 'border-border bg-card text-foreground'
                    }`}
                >
                    <span className="block text-xs font-bold uppercase opacity-80">
                        Todas
                    </span>
                    <span className="text-lg font-black">{stats.total}</span>
                </button>
                <button
                    onClick={() => handleTabChange('pendientes')}
                    className={`rounded-2xl border p-2.5 text-left transition-all ${
                        currentTab === 'pendientes'
                            ? 'border-border bg-primary text-white shadow-sm [&_span]:text-white!'
                            : 'border-border bg-card text-foreground'
                    }`}
                >
                    <span className="block text-xs font-bold uppercase opacity-80">
                        Por Iniciar
                    </span>
                    <span className="text-warning-strong text-lg font-black">
                        {stats.pendientes}
                    </span>
                </button>
                <button
                    onClick={() => handleTabChange('en_proceso')}
                    className={`rounded-2xl border p-2.5 text-left transition-all ${
                        currentTab === 'en_proceso'
                            ? 'border-border bg-primary text-white shadow-sm [&_span]:text-white!'
                            : 'border-border bg-card text-foreground'
                    }`}
                >
                    <span className="block text-xs font-bold uppercase opacity-80">
                        En Proceso
                    </span>
                    <span className="text-foreground text-lg font-black">
                        {stats.en_proceso}
                    </span>
                </button>
                <button
                    onClick={() => handleTabChange('finalizadas')}
                    className={`rounded-2xl border p-2.5 text-left transition-all ${
                        currentTab === 'finalizadas'
                            ? 'border-border bg-primary text-white shadow-sm [&_span]:text-white!'
                            : 'border-border bg-card text-foreground'
                    }`}
                >
                    <span className="block text-xs font-bold uppercase opacity-80">
                        Completas
                    </span>
                    <span className="text-success-strong text-lg font-black">
                        {stats.finalizadas}
                    </span>
                </button>
            </div>

            {/* Search Input */}
            <form onSubmit={handleSearch} className="mb-4">
                <div className="relative">
                    <Search className="text-muted-foreground absolute top-1/2 left-3.5 size-4 -translate-y-1/2" />
                    <input
                        aria-label="Buscar por orden, RUC o cliente..."
                        type="text"
                        placeholder="Buscar por orden, RUC o cliente..."
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        className="border-border bg-card focus:ring-ring w-full rounded-lg border py-2 pr-4 pl-9 text-xs focus:ring-2 focus:outline-hidden"
                    />
                </div>
            </form>

            {/* List of Inspection Orders (Touch Cards) */}
            <div className="space-y-3">
                {inspecciones.data.length === 0 ? (
                    <Empty
                        icon={<ClipboardCheck />}
                        title="No hay inspecciones en este filtro"
                        description={
                            searchTerm || currentTab !== 'todos'
                                ? 'No se encontraron inspecciones programadas para los filtros indicados.'
                                : 'Aún no se han registrado órdenes de inspección técnica.'
                        }
                    >
                        {(searchTerm || currentTab !== 'todos') && (
                            <button
                                type="button"
                                onClick={() => {
                                    setSearchTerm('');
                                    router.get(
                                        InspeccionesRoutes.index.url({
                                            current_team: teamSlug,
                                        }),
                                        { tab: 'todos' },
                                    );
                                }}
                                className="border-border bg-card text-foreground hover:bg-muted mt-3 inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold shadow-xs transition-colors"
                            >
                                <RotateCcw className="size-3" />
                                <span>Ver todas las inspecciones</span>
                            </button>
                        )}
                    </Empty>
                ) : (
                    inspecciones.data.map((order) => {
                        const estadoMeta = ESTADOS_MAP[order.estado] || {
                            label: etiquetaEstadoOrden(order.estado),
                            bg: 'bg-muted',
                            text: 'text-muted-foreground',
                            border: 'border-border',
                        };

                        return (
                            <Link
                                key={order.id}
                                href={InspeccionesRoutes.show.url({
                                    current_team: teamSlug,
                                    service_order: order.id,
                                })}
                                className="border-border bg-card hover:border-border block rounded-2xl border p-4 shadow-xs transition-all active:scale-[0.99]"
                            >
                                <div className="mb-2 flex items-start justify-between gap-2">
                                    <div>
                                        <div className="flex items-center gap-1.5">
                                            <span className="text-foreground font-mono text-xs font-black">
                                                {order.codigo}
                                            </span>
                                            <span
                                                className={`rounded-full border px-2 py-0.5 text-xs font-bold ${estadoMeta.bg} ${estadoMeta.text} ${estadoMeta.border}`}
                                            >
                                                {estadoMeta.label}
                                            </span>
                                        </div>
                                        <h2 className="text-foreground mt-0.5 line-clamp-1 text-sm font-bold">
                                            {order.client?.razon_social}
                                        </h2>
                                        {order.client?.numero_documento && (
                                            <span className="text-muted-foreground font-mono text-xs">
                                                {order.client.numero_documento}
                                            </span>
                                        )}
                                    </div>
                                    <ChevronRight className="text-muted-foreground mt-1 size-5 shrink-0" />
                                </div>

                                <div className="text-muted-foreground mb-3 flex items-center gap-1 text-xs">
                                    <MapPin className="text-foreground size-3.5 shrink-0" />
                                    <span className="line-clamp-1">
                                        {order.client?.direccion_fiscal ||
                                            'Sin dirección registrada'}
                                    </span>
                                </div>

                                <div className="border-border flex items-center justify-between border-t pt-2.5">
                                    <div className="text-foreground flex items-center gap-1.5 text-xs font-medium">
                                        <Flame className="text-warning-strong size-3.5" />
                                        <span>
                                            {order.equipments?.length || 0}{' '}
                                            {order.equipments?.length === 1
                                                ? 'extintor'
                                                : 'extintores'}{' '}
                                            registrados
                                        </span>
                                    </div>
                                    <div className="text-foreground flex items-center gap-1 text-xs font-bold">
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
                            className="border-border bg-card text-foreground hover:bg-muted/60 rounded-lg border px-3 py-1.5 text-xs font-bold transition-colors"
                        >
                            Anterior
                        </Link>
                    )}
                    <span className="text-muted-foreground text-xs">
                        Página {inspecciones.current_page} de{' '}
                        {inspecciones.last_page}
                    </span>
                    {inspecciones.next_page_url && (
                        <Link
                            href={inspecciones.next_page_url}
                            className="border-border bg-card text-foreground hover:bg-muted/60 rounded-lg border px-3 py-1.5 text-xs font-bold transition-colors"
                        >
                            Siguiente
                        </Link>
                    )}
                </div>
            )}
        </TecnicoCampoLayout>
    );
}
