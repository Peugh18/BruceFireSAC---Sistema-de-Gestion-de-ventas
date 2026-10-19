import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowRight,
    ChevronRight,
    Flame,
    MapPin,
    PackageCheck,
    RotateCcw,
    Search,
} from 'lucide-react';
import React, { useState } from 'react';

import { useBusquedaDinamica } from '@/hooks/use-busqueda-dinamica';
import TecnicoCampoLayout from '@/layouts/tecnico-campo-layout';
import type { Team } from '@/types';
import EntregasRoutes from '@/routes/tecnico-campo/entregas';
import { etiquetaEstadoOrden } from '@/lib/estados-orden';
import { Empty } from '@/components/ui/empty';

type Client = {
    id: number;
    razon_social: string;
    numero_documento?: string;
    telefono?: string;
    direccion_fiscal?: string;
};

type Sede = {
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
    fecha?: string;
    client: Client;
    sede?: Sede;
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
    entregas: PaginatedData<ServiceOrder>;
    currentTab: 'listas' | 'entregadas' | 'cerradas' | 'todos';
    search?: string | null;
    stats: {
        total: number;
        listas: number;
        entregadas: number;
        cerradas: number;
    };
};

const ESTADOS_MAP: Record<
    string,
    { label: string; bg: string; text: string; border: string }
> = {
    listo_certificado: {
        label: 'Certificado listo para entregar',
        bg: 'bg-amber-500/10',
        text: 'text-warning-strong',
        border: 'border-amber-500/20',
    },
    listo_entrega: {
        label: 'Lista para Entrega',
        bg: 'bg-amber-500/10',
        text: 'text-warning-strong',
        border: 'border-amber-500/20',
    },
    en_ruta_entrega: {
        label: 'En Ruta',
        bg: 'bg-muted',
        text: 'text-foreground ',
        border: 'border-border',
    },
    entregado: {
        label: 'Entregada',
        bg: 'bg-emerald-500/10',
        text: 'text-success-strong',
        border: 'border-emerald-500/20',
    },
    cerrado: {
        label: 'Cerrada',
        bg: 'bg-muted',
        text: 'text-muted-foreground',
        border: 'border-border',
    },
};

export default function EntregasIndex({
    currentTeam,
    entregas,
    currentTab,
    search = '',
    stats,
}: Props) {
    const teamSlug = currentTeam?.slug ?? '';
    const [searchTerm, setSearchTerm] = useState(search ?? '');

    const aplicarBusqueda = () => {
        router.get(
            EntregasRoutes.index.url({ current_team: teamSlug }),
            { tab: currentTab, q: searchTerm },
            { preserveState: true },
        );
    };
    const handleSearch = useBusquedaDinamica(searchTerm, aplicarBusqueda);

    const handleTabChange = (tab: string) => {
        router.get(
            EntregasRoutes.index.url({ current_team: teamSlug }),
            { tab, q: searchTerm },
            { preserveState: true },
        );
    };

    return (
        <TecnicoCampoLayout title="Entregas y Actas de Conformidad">
            <Head title="Entregas y Actas - Técnico de Campo" />

            {/* Header Mobile Title */}
            <div className="mb-4">
                <h1 className="text-foreground flex items-center gap-2 text-xl font-black">
                    <PackageCheck className="text-foreground size-6" />
                    Entregas y Actas de Conformidad
                </h1>
                <p className="text-muted-foreground text-xs">
                    Entrega final de extintores, cierre de custodia y firma de
                    acta en sitio.
                </p>
            </div>

            {/* Tactical KPI Pills */}
            <div className="mb-4 grid grid-cols-4 gap-2">
                <button
                    onClick={() => handleTabChange('listas')}
                    className={`rounded-3xl border p-2.5 text-left transition-all ${
                        currentTab === 'listas'
                            ? 'border-border bg-primary text-white shadow-sm [&_span]:text-white!'
                            : 'border-border bg-card text-foreground'
                    }`}
                >
                    <span className="block text-xs font-bold uppercase opacity-80">
                        Listas
                    </span>
                    <span className="text-warning-strong text-lg font-black">
                        {stats.listas}
                    </span>
                </button>
                <button
                    onClick={() => handleTabChange('entregadas')}
                    className={`rounded-3xl border p-2.5 text-left transition-all ${
                        currentTab === 'entregadas'
                            ? 'border-border bg-primary text-white shadow-sm [&_span]:text-white!'
                            : 'border-border bg-card text-foreground'
                    }`}
                >
                    <span className="block text-xs font-bold uppercase opacity-80">
                        Entregadas
                    </span>
                    <span className="text-foreground text-lg font-black">
                        {stats.entregadas}
                    </span>
                </button>
                <button
                    onClick={() => handleTabChange('cerradas')}
                    className={`rounded-3xl border p-2.5 text-left transition-all ${
                        currentTab === 'cerradas'
                            ? 'border-border bg-primary text-white shadow-sm [&_span]:text-white!'
                            : 'border-border bg-card text-foreground'
                    }`}
                >
                    <span className="block text-xs font-bold uppercase opacity-80">
                        Cerradas
                    </span>
                    <span className="text-success-strong text-lg font-black">
                        {stats.cerradas}
                    </span>
                </button>
                <button
                    onClick={() => handleTabChange('todos')}
                    className={`rounded-3xl border p-2.5 text-left transition-all ${
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

            {/* Delivery Orders Cards List */}
            <div className="space-y-3">
                {entregas.data.length === 0 ? (
                    <Empty
                        icon={<PackageCheck />}
                        title="No hay entregas en este filtro"
                        description={
                            searchTerm || currentTab !== 'todos'
                                ? 'No se encontraron entregas que coincidan con los criterios.'
                                : 'Aún no se han generado órdenes de entrega para campo.'
                        }
                    >
                        {(searchTerm || currentTab !== 'todos') && (
                            <button
                                type="button"
                                onClick={() => {
                                    setSearchTerm('');
                                    router.get(
                                        EntregasRoutes.index.url({
                                            current_team: teamSlug,
                                        }),
                                        { tab: 'todos' },
                                    );
                                }}
                                className="border-border bg-card text-foreground hover:bg-muted mt-3 inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold shadow-xs transition-colors"
                            >
                                <RotateCcw className="size-3" />
                                <span>Ver todas las entregas</span>
                            </button>
                        )}
                    </Empty>
                ) : (
                    entregas.data.map((order) => {
                        const estadoMeta = ESTADOS_MAP[order.estado] || {
                            label: etiquetaEstadoOrden(order.estado),
                            bg: 'bg-muted',
                            text: 'text-muted-foreground',
                            border: 'border-border',
                        };

                        return (
                            <Link
                                key={order.id}
                                href={EntregasRoutes.show.url({
                                    current_team: teamSlug,
                                    service_order: order.id,
                                })}
                                className="border-border bg-card hover:border-border block rounded-3xl border p-4 shadow-xs transition-all active:scale-[0.99]"
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
                                                RUC / Doc:{' '}
                                                {order.client.numero_documento}
                                            </span>
                                        )}
                                    </div>
                                    <ChevronRight className="text-muted-foreground mt-1 size-5 shrink-0" />
                                </div>

                                <div className="text-muted-foreground mb-3 flex items-center gap-1 text-xs">
                                    <MapPin className="text-foreground size-3.5 shrink-0" />
                                    <span className="line-clamp-1">
                                        {order.sede?.direccion ||
                                            order.client?.direccion_fiscal ||
                                            'Dirección de sede cliente'}
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
                                            listos
                                        </span>
                                    </div>
                                    <div className="text-foreground flex items-center gap-1 text-xs font-bold">
                                        <span>Ver entrega & acta</span>
                                        <ArrowRight className="size-3.5" />
                                    </div>
                                </div>
                            </Link>
                        );
                    })
                )}
            </div>

            {/* Pagination */}
            {entregas.last_page > 1 && (
                <div className="mt-6 flex items-center justify-center gap-2">
                    {entregas.prev_page_url && (
                        <Link
                            href={entregas.prev_page_url}
                            className="border-border bg-card text-foreground hover:bg-muted/60 rounded-lg border px-3 py-1.5 text-xs font-bold transition-colors"
                        >
                            Anterior
                        </Link>
                    )}
                    <span className="text-muted-foreground text-xs">
                        Página {entregas.current_page} de {entregas.last_page}
                    </span>
                    {entregas.next_page_url && (
                        <Link
                            href={entregas.next_page_url}
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
