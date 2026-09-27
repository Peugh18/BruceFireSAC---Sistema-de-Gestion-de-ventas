import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowRight,
    ChevronRight,
    Flame,
    MapPin,
    Search,
    Wrench,
} from 'lucide-react';
import React, { useState } from 'react';

import TecnicoCampoLayout from '@/layouts/tecnico-campo-layout';
import type { Team } from '@/types';

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
    instalaciones: PaginatedData<ServiceOrder>;
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
    pendiente_recepcion: {
        label: 'Por Iniciar',
        bg: 'bg-amber-500/10',
        text: 'text-amber-600 dark:text-amber-400',
        border: 'border-amber-500/20',
    },
    recibido_planta: {
        label: 'Asignada',
        bg: 'bg-muted',
        text: 'text-muted-foreground',
        border: 'border-border',
    },
    en_revision: {
        label: 'En Ruta / En Sitio',
        bg: 'bg-sky-500/10',
        text: 'text-sky-700 dark:text-sky-400',
        border: 'border-sky-500/20',
    },
    en_proceso: {
        label: 'Instalando',
        bg: 'bg-sky-500/10',
        text: 'text-blue-600 dark:text-blue-400',
        border: 'border-sky-500/20',
    },
    esperando_autorizacion: {
        label: 'En Pausa',
        bg: 'bg-destructive/10',
        text: 'text-destructive',
        border: 'border-destructive/20',
    },
    listo_entrega: {
        label: 'Instalado',
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

export default function InstalacionesIndex({
    currentTeam,
    instalaciones,
    currentTab,
    search = '',
    stats,
}: Props) {
    const teamSlug = currentTeam?.slug ?? '';
    const [searchTerm, setSearchTerm] = useState(search);

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            `/${teamSlug}/tecnico-campo/instalaciones`,
            { tab: currentTab, q: searchTerm },
            { preserveState: true },
        );
    };

    const handleTabChange = (tab: string) => {
        router.get(
            `/${teamSlug}/tecnico-campo/instalaciones`,
            { tab, q: searchTerm },
            { preserveState: true },
        );
    };

    return (
        <TecnicoCampoLayout title="Instalaciones en Campo">
            <Head title="Instalaciones en Campo - Técnico de Campo" />

            {/* Header Mobile Title */}
            <div className="mb-4">
                <h1 className="text-foreground flex items-center gap-2 text-xl font-black">
                    <Wrench className="size-6 text-sky-600 dark:text-sky-400" />
                    Instalaciones en Campo
                </h1>
                <p className="text-muted-foreground text-xs">
                    Montaje técnico, fijación de soportes y registro de
                    extintores en cliente.
                </p>
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
                        Pendientes
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

            {/* Search Bar */}
            <form onSubmit={handleSearch} className="mb-4">
                <div className="relative">
                    <Search className="text-muted-foreground absolute top-1/2 left-3.5 size-4 -translate-y-1/2" />
                    <input
                        type="text"
                        placeholder="Buscar por orden, RUC o cliente..."
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        className="border-border bg-card w-full rounded-[10px] border py-2 pr-4 pl-9 text-xs focus:ring-2 focus:ring-[#0284C7] focus:outline-hidden"
                    />
                </div>
            </form>

            {/* Cards List */}
            <div className="space-y-3">
                {instalaciones.data.length === 0 ? (
                    <div className="border-border bg-card rounded-[12px] border p-8 text-center">
                        <Wrench className="text-muted-foreground/40 mx-auto mb-2 size-10" />
                        <p className="text-foreground text-xs font-semibold">
                            No hay instalaciones en este filtro
                        </p>
                        <p className="text-muted-foreground mt-0.5 text-[11px]">
                            Selecciona otro filtro o busca por cliente.
                        </p>
                    </div>
                ) : (
                    instalaciones.data.map((order) => {
                        const estadoMeta = ESTADOS_MAP[order.estado] || {
                            label: order.estado,
                            bg: 'bg-muted',
                            text: 'text-muted-foreground',
                            border: 'border-border',
                        };

                        return (
                            <Link
                                key={order.id}
                                href={`/${teamSlug}/tecnico-campo/instalaciones/${order.id}`}
                                className="border-border bg-card block rounded-[14px] border p-4 shadow-xs transition-all hover:border-sky-500/20 active:scale-[0.99]"
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
                                        <h2 className="text-foreground mt-0.5 line-clamp-1 text-sm font-bold">
                                            {order.client?.razon_social}
                                        </h2>
                                        {order.client?.numero_documento && (
                                            <span className="text-muted-foreground font-mono text-[11px]">
                                                Doc:{' '}
                                                {order.client.numero_documento}
                                            </span>
                                        )}
                                    </div>
                                    <ChevronRight className="text-muted-foreground mt-1 size-5 shrink-0" />
                                </div>

                                <div className="text-muted-foreground mb-3 flex items-center gap-1 text-[11px]">
                                    <MapPin className="size-3.5 shrink-0 text-sky-600 dark:text-sky-400" />
                                    <span className="line-clamp-1">
                                        {order.sede?.direccion ||
                                            order.client?.direccion_fiscal ||
                                            'Dirección no especificada'}
                                    </span>
                                </div>

                                <div className="border-border flex items-center justify-between border-t pt-2.5">
                                    <div className="text-foreground flex items-center gap-1.5 text-[11px] font-medium">
                                        <Flame className="size-3.5 text-amber-600 dark:text-amber-400" />
                                        <span>
                                            {order.equipments?.length || 0}{' '}
                                            {order.equipments?.length === 1
                                                ? 'equipo'
                                                : 'equipos'}{' '}
                                            a instalar
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-1 text-[11px] font-bold text-sky-600 dark:text-sky-400">
                                        <span>Registrar instalación</span>
                                        <ArrowRight className="size-3.5" />
                                    </div>
                                </div>
                            </Link>
                        );
                    })
                )}
            </div>

            {/* Pagination */}
            {instalaciones.last_page > 1 && (
                <div className="mt-6 flex items-center justify-center gap-2">
                    {instalaciones.prev_page_url && (
                        <Link
                            href={instalaciones.prev_page_url}
                            className="border-border bg-card text-foreground rounded-[8px] border px-3 py-1.5 text-xs font-bold"
                        >
                            Anterior
                        </Link>
                    )}
                    <span className="text-muted-foreground text-xs">
                        Página {instalaciones.current_page} de{' '}
                        {instalaciones.last_page}
                    </span>
                    {instalaciones.next_page_url && (
                        <Link
                            href={instalaciones.next_page_url}
                            className="border-border bg-card text-foreground rounded-[8px] border px-3 py-1.5 text-xs font-bold"
                        >
                            Siguiente
                        </Link>
                    )}
                </div>
            )}
        </TecnicoCampoLayout>
    );
}
