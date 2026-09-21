import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowRight,
    CalendarCheck,
    CheckCircle2,
    ChevronRight,
    FileText,
    Flame,
    MapPin,
    PackageCheck,
    Search,
    Truck,
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
    entregas: PaginatedData<ServiceOrder>;
    currentTab: 'listas' | 'entregadas' | 'cerradas' | 'todos';
    search?: string;
    stats: {
        total: number;
        listas: number;
        entregadas: number;
        cerradas: number;
    };
};

const ESTADOS_MAP: Record<string, { label: string; bg: string; text: string; border: string }> = {
    listo_entrega: { label: 'Lista para Entrega', bg: 'bg-[#FEF3C7]', text: 'text-[#92400E]', border: 'border-[#FDE68A]' },
    en_ruta_entrega: { label: 'En Ruta', bg: 'bg-[#E0F2FE]', text: 'text-[#0369A1]', border: 'border-[#BAE6FD]' },
    entregado: { label: 'Entregada', bg: 'bg-[#DCFCE7]', text: 'text-[#166534]', border: 'border-[#BBF7D0]' },
    cerrado: { label: 'Cerrada', bg: 'bg-[#F3F4F6]', text: 'text-[#374151]', border: 'border-[#E5E7EB]' },
};

export default function EntregasIndex({
    currentTeam,
    entregas,
    currentTab,
    search = '',
    stats,
}: Props) {
    const teamSlug = currentTeam?.slug ?? '';
    const [searchTerm, setSearchTerm] = useState(search);

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            `/${teamSlug}/tecnico-campo/entregas`,
            { tab: currentTab, q: searchTerm },
            { preserveState: true }
        );
    };

    const handleTabChange = (tab: string) => {
        router.get(
            `/${teamSlug}/tecnico-campo/entregas`,
            { tab, q: searchTerm },
            { preserveState: true }
        );
    };

    return (
        <TecnicoCampoLayout title="Entregas y Actas de Conformidad">
            <Head title="Entregas y Actas - Técnico de Campo" />

            {/* Header Mobile Title */}
            <div className="mb-4">
                <h1 className="text-xl font-black text-[#201F1D] flex items-center gap-2">
                    <PackageCheck className="size-6 text-[#0284C7]" />
                    Entregas y Actas de Conformidad
                </h1>
                <p className="text-xs text-[#6B6965]">
                    Entrega final de extintores, cierre de custodia y firma de acta en sitio (§22.3, §23).
                </p>
            </div>

            {/* Tactical KPI Pills */}
            <div className="grid grid-cols-4 gap-2 mb-4">
                <button
                    onClick={() => handleTabChange('listas')}
                    className={`rounded-[12px] p-2.5 text-left border transition-all ${
                        currentTab === 'listas'
                            ? 'bg-[#0284C7] text-white border-[#0284C7] shadow-sm'
                            : 'bg-white text-[#201F1D] border-[#E4E1DC]'
                    }`}
                >
                    <span className="block text-[10px] uppercase font-bold opacity-80">Listas</span>
                    <span className="text-lg font-black text-[#D97706]">{stats.listas}</span>
                </button>
                <button
                    onClick={() => handleTabChange('entregadas')}
                    className={`rounded-[12px] p-2.5 text-left border transition-all ${
                        currentTab === 'entregadas'
                            ? 'bg-[#0284C7] text-white border-[#0284C7] shadow-sm'
                            : 'bg-white text-[#201F1D] border-[#E4E1DC]'
                    }`}
                >
                    <span className="block text-[10px] uppercase font-bold opacity-80">Entregadas</span>
                    <span className="text-lg font-black text-[#0284C7]">{stats.entregadas}</span>
                </button>
                <button
                    onClick={() => handleTabChange('cerradas')}
                    className={`rounded-[12px] p-2.5 text-left border transition-all ${
                        currentTab === 'cerradas'
                            ? 'bg-[#0284C7] text-white border-[#0284C7] shadow-sm'
                            : 'bg-white text-[#201F1D] border-[#E4E1DC]'
                    }`}
                >
                    <span className="block text-[10px] uppercase font-bold opacity-80">Cerradas</span>
                    <span className="text-lg font-black text-[#16A34A]">{stats.cerradas}</span>
                </button>
                <button
                    onClick={() => handleTabChange('todos')}
                    className={`rounded-[12px] p-2.5 text-left border transition-all ${
                        currentTab === 'todos'
                            ? 'bg-[#0284C7] text-white border-[#0284C7] shadow-sm'
                            : 'bg-white text-[#201F1D] border-[#E4E1DC]'
                    }`}
                >
                    <span className="block text-[10px] uppercase font-bold opacity-80">Todas</span>
                    <span className="text-lg font-black">{stats.total}</span>
                </button>
            </div>

            {/* Search Input */}
            <form onSubmit={handleSearch} className="mb-4">
                <div className="relative">
                    <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-[#6B6965]" />
                    <input
                        type="text"
                        placeholder="Buscar por orden, RUC o cliente..."
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        className="w-full pl-9 pr-4 py-2 text-xs rounded-[10px] border border-[#E4E1DC] bg-white focus:outline-hidden focus:ring-2 focus:ring-[#0284C7]"
                    />
                </div>
            </form>

            {/* Delivery Orders Cards List */}
            <div className="space-y-3">
                {entregas.data.length === 0 ? (
                    <div className="rounded-[12px] border border-[#E4E1DC] bg-white p-8 text-center">
                        <PackageCheck className="mx-auto size-10 text-[#6B6965]/40 mb-2" />
                        <p className="text-xs font-semibold text-[#201F1D]">No hay entregas en este filtro</p>
                        <p className="text-[11px] text-[#6B6965] mt-0.5">
                            Selecciona otra pestaña o busca por cliente.
                        </p>
                    </div>
                ) : (
                    entregas.data.map((order) => {
                        const estadoMeta = ESTADOS_MAP[order.estado] || {
                            label: order.estado,
                            bg: 'bg-[#F3F4F6]',
                            text: 'text-[#374151]',
                            border: 'border-[#E5E7EB]',
                        };

                        return (
                            <Link
                                key={order.id}
                                href={`/${teamSlug}/tecnico-campo/entregas/${order.id}`}
                                className="block rounded-[14px] border border-[#E4E1DC] bg-white p-4 shadow-xs transition-all active:scale-[0.99] hover:border-[#0284C7]"
                            >
                                <div className="flex items-start justify-between gap-2 mb-2">
                                    <div>
                                        <div className="flex items-center gap-1.5">
                                            <span className="font-mono text-xs font-black text-[#0284C7]">
                                                {order.codigo}
                                            </span>
                                            <span
                                                className={`rounded-full px-2 py-0.5 text-[9.5px] font-bold border ${estadoMeta.bg} ${estadoMeta.text} ${estadoMeta.border}`}
                                            >
                                                {estadoMeta.label}
                                            </span>
                                        </div>
                                        <h2 className="text-sm font-bold text-[#201F1D] line-clamp-1 mt-0.5">
                                            {order.client?.razon_social}
                                        </h2>
                                        {order.client?.numero_documento && (
                                            <span className="text-[11px] font-mono text-[#6B6965]">
                                                RUC / Doc: {order.client.numero_documento}
                                            </span>
                                        )}
                                    </div>
                                    <ChevronRight className="size-5 text-[#6B6965] shrink-0 mt-1" />
                                </div>

                                <div className="flex items-center gap-1 text-[11px] text-[#6B6965] mb-3">
                                    <MapPin className="size-3.5 text-[#0284C7] shrink-0" />
                                    <span className="line-clamp-1">
                                        {order.sede?.direccion || order.client?.direccion_fiscal || 'Dirección de sede cliente'}
                                    </span>
                                </div>

                                <div className="flex items-center justify-between border-t border-[#F3F1ED] pt-2.5">
                                    <div className="flex items-center gap-1.5 text-[11px] font-medium text-[#201F1D]">
                                        <Flame className="size-3.5 text-[#EA580C]" />
                                        <span>
                                            {order.equipments?.length || 0} {order.equipments?.length === 1 ? 'extintor' : 'extintores'} listos
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-1 text-[11px] font-bold text-[#0284C7]">
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
                            className="rounded-[8px] border border-[#E4E1DC] bg-white px-3 py-1.5 text-xs font-bold text-[#201F1D]"
                        >
                            Anterior
                        </Link>
                    )}
                    <span className="text-xs text-[#6B6965]">
                        Página {entregas.current_page} de {entregas.last_page}
                    </span>
                    {entregas.next_page_url && (
                        <Link
                            href={entregas.next_page_url}
                            className="rounded-[8px] border border-[#E4E1DC] bg-white px-3 py-1.5 text-xs font-bold text-[#201F1D]"
                        >
                            Siguiente
                        </Link>
                    )}
                </div>
            )}
        </TecnicoCampoLayout>
    );
}
