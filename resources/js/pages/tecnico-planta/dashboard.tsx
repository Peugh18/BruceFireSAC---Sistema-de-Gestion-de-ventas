import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    AlertTriangle,
    ArrowRight,
    Boxes,
    Building2,
    Calendar,
    CheckCircle2,
    Clock,
    Factory,
    Filter,
    PackageCheck,
    Search,
    ShieldAlert,
    User,
    Wrench,
    X,
} from 'lucide-react';
import { FormEvent, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import TecnicoPlantaLayout from '@/layouts/tecnico-planta-layout';
import type { Team } from '@/types';

export type OrderItem = {
    id: number;
    codigo: string;
    cliente: string;
    cliente_doc: string;
    telefono: string | null;
    sede?: string | null;
    tipo_servicio: string;
    fecha: string;
    prioridad: 'normal' | 'alta' | 'urgente' | string;
    estado: string;
    estado_coarse: string;
    observaciones?: string | null;
    deficiencias_count: number;
    requiere_autorizacion_count: number;
    ultimo_evento?: string | null;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Props = {
    orders: {
        data: OrderItem[];
        links: PaginationLink[];
        current_page: number;
        last_page: number;
        total: number;
    };
    kpis: {
        pendientes_recepcion: number;
        en_taller: number;
        esperando_autorizacion: number;
        listas: number;
    };
    filters: {
        tab: string;
        search: string;
    };
};

function getEstadoBadge(estado: string) {
    switch (estado) {
        case 'pendiente_recepcion':
            return (
                <span className="inline-flex items-center gap-1 rounded-full border border-[#FDE68A] bg-[#FEF3C7] px-2 py-0.5 text-[10.5px] font-bold text-[#B45309]">
                    <Clock className="size-3" />
                    Pendiente Recepción
                </span>
            );
        case 'recibido_planta':
            return (
                <span className="inline-flex items-center gap-1 rounded-full border border-[#BFDBFE] bg-[#EFF6FF] px-2 py-0.5 text-[10.5px] font-bold text-[#1D4ED8]">
                    <PackageCheck className="size-3" />
                    Recibido en Planta
                </span>
            );
        case 'en_revision':
        case 'en_proceso':
            return (
                <span className="inline-flex items-center gap-1 rounded-full border border-[#FED7AA] bg-[#FFF7ED] px-2 py-0.5 text-[10.5px] font-bold text-[#C2410C]">
                    <Wrench className="size-3" />
                    En Trabajo
                </span>
            );
        case 'esperando_autorizacion':
            return (
                <span className="inline-flex items-center gap-1 rounded-full border border-[#FCA5A5] bg-[#FEF2F2] px-2 py-0.5 text-[10.5px] font-bold text-[#DC2626]">
                    <AlertTriangle className="size-3" />
                    Esperando Autorización
                </span>
            );
        case 'autorizado':
            return (
                <span className="inline-flex items-center gap-1 rounded-full border border-[#86EFAC] bg-[#F0FDF4] px-2 py-0.5 text-[10.5px] font-bold text-[#15803D]">
                    <CheckCircle2 className="size-3" />
                    Autorizado
                </span>
            );
        case 'trabajo_terminado':
        case 'listo_certificado':
        case 'listo_entrega':
            return (
                <span className="inline-flex items-center gap-1 rounded-full border border-[#86EFAC] bg-[#F0FDF4] px-2 py-0.5 text-[10.5px] font-bold text-[#16A34A]">
                    <CheckCircle2 className="size-3" />
                    Listo / Terminado
                </span>
            );
        default:
            return (
                <span className="inline-flex items-center rounded-full border border-[#E4E1DC] bg-[#FAF9F7] px-2 py-0.5 text-[10.5px] font-semibold text-[#6B6965]">
                    {estado.replace('_', ' ')}
                </span>
            );
    }
}

function getPrioridadBadge(prioridad: string) {
    if (prioridad === 'urgente') {
        return (
            <span className="rounded-[6px] bg-[#FEF2F2] border border-[#F87171] px-1.5 py-0.5 text-[9.5px] font-black uppercase text-[#DC2626]">
                Urgente
            </span>
        );
    }
    if (prioridad === 'alta') {
        return (
            <span className="rounded-[6px] bg-[#FFF7ED] border border-[#FDBA74] px-1.5 py-0.5 text-[9.5px] font-bold uppercase text-[#C2410C]">
                Alta
            </span>
        );
    }
    return (
        <span className="rounded-[6px] bg-[#FAF9F7] border border-[#E4E1DC] px-1.5 py-0.5 text-[9.5px] font-semibold uppercase text-[#6B6965]">
            Normal
        </span>
    );
}

export default function TecnicoPlantaDashboard({ orders, kpis, filters }: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug = currentTeam?.slug ?? '';

    const [search, setSearch] = useState(filters.search || '');
    const currentTab = filters.tab || 'todas';

    const handleSearch = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            `/${teamSlug}/tecnico-planta/dashboard`,
            { tab: currentTab, search: search.trim() },
            { preserveState: true }
        );
    };

    const changeTab = (newTab: string) => {
        router.get(
            `/${teamSlug}/tecnico-planta/dashboard`,
            { tab: newTab, search },
            { preserveState: true }
        );
    };

    return (
        <TecnicoPlantaLayout title="Taller y Planta">
            <Head title="Taller Principal - Planta" />

            <div className="space-y-4">
                {/* Greeting & Quick Action */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-lg font-black tracking-tight text-[#201F1D]">
                            Cola Operativa de Taller
                        </h1>
                        <p className="text-[11px] text-[#6B6965]">
                            Órdenes de servicio asignadas al departamento de planta.
                        </p>
                    </div>

                    <Link
                        href={`/${teamSlug}/tecnico-planta/recepcion`}
                        className="inline-flex items-center gap-1.5 rounded-[10px] bg-[#E31E24] px-3.5 py-2 text-xs font-bold text-white shadow-xs hover:bg-[#C0181D] active:scale-95 transition-all"
                    >
                        <PackageCheck className="size-4" />
                        <span>Recepción</span>
                    </Link>
                </div>

                {/* Mobile-First KPI Grid (2x2 on phone) */}
                <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                    <button
                        type="button"
                        onClick={() => changeTab('pendientes')}
                        className={`flex flex-col justify-between rounded-[12px] border p-3 text-left transition-all ${
                            currentTab === 'pendientes'
                                ? 'border-[#F59E0B] bg-[#FFFBEB] ring-2 ring-[#F59E0B]/30'
                                : 'border-[#E4E1DC] bg-white hover:bg-[#FAF9F7]'
                        }`}
                    >
                        <div className="flex items-center justify-between text-[#B45309]">
                            <span className="text-[10.5px] font-bold uppercase tracking-wider">Pendientes</span>
                            <Clock className="size-4" />
                        </div>
                        <div className="mt-2 text-2xl font-black text-[#201F1D]">
                            {kpis.pendientes_recepcion}
                        </div>
                        <span className="text-[10px] text-[#6B6965]">Por recibir</span>
                    </button>

                    <button
                        type="button"
                        onClick={() => changeTab('en_taller')}
                        className={`flex flex-col justify-between rounded-[12px] border p-3 text-left transition-all ${
                            currentTab === 'en_taller'
                                ? 'border-[#2563EB] bg-[#EFF6FF] ring-2 ring-[#2563EB]/30'
                                : 'border-[#E4E1DC] bg-white hover:bg-[#FAF9F7]'
                        }`}
                    >
                        <div className="flex items-center justify-between text-[#1D4ED8]">
                            <span className="text-[10.5px] font-bold uppercase tracking-wider">En Taller</span>
                            <Wrench className="size-4" />
                        </div>
                        <div className="mt-2 text-2xl font-black text-[#201F1D]">
                            {kpis.en_taller}
                        </div>
                        <span className="text-[10px] text-[#6B6965]">En proceso</span>
                    </button>

                    <button
                        type="button"
                        onClick={() => changeTab('esperando_autorizacion')}
                        className={`flex flex-col justify-between rounded-[12px] border p-3 text-left transition-all ${
                            currentTab === 'esperando_autorizacion'
                                ? 'border-[#DC2626] bg-[#FEF2F2] ring-2 ring-[#DC2626]/30'
                                : 'border-[#E4E1DC] bg-white hover:bg-[#FAF9F7]'
                        }`}
                    >
                        <div className="flex items-center justify-between text-[#DC2626]">
                            <span className="text-[10.5px] font-bold uppercase tracking-wider">Por Autorizar</span>
                            <AlertTriangle className="size-4" />
                        </div>
                        <div className="mt-2 text-2xl font-black text-[#201F1D]">
                            {kpis.esperando_autorizacion}
                        </div>
                        <span className="text-[10px] text-[#6B6965]">Deficiencias</span>
                    </button>

                    <button
                        type="button"
                        onClick={() => changeTab('por_entregar')}
                        className={`flex flex-col justify-between rounded-[12px] border p-3 text-left transition-all ${
                            currentTab === 'por_entregar'
                                ? 'border-[#16A34A] bg-[#F0FDF4] ring-2 ring-[#16A34A]/30'
                                : 'border-[#E4E1DC] bg-white hover:bg-[#FAF9F7]'
                        }`}
                    >
                        <div className="flex items-center justify-between text-[#15803D]">
                            <span className="text-[10.5px] font-bold uppercase tracking-wider">Listas</span>
                            <CheckCircle2 className="size-4" />
                        </div>
                        <div className="mt-2 text-2xl font-black text-[#201F1D]">
                            {kpis.listas}
                        </div>
                        <span className="text-[10px] text-[#6B6965]">Certif / Entrega</span>
                    </button>
                </div>

                {/* Search Bar */}
                <form onSubmit={handleSearch} className="relative">
                    <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                    <Input
                        type="text"
                        placeholder="Buscar por código OS o nombre de cliente..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="h-10 pl-9 pr-8 text-xs rounded-[10px] bg-white"
                    />
                    {search && (
                        <button
                            type="button"
                            onClick={() => {
                                setSearch('');
                                router.get(`/${teamSlug}/tecnico-planta/dashboard`, { tab: currentTab });
                            }}
                            className="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                        >
                            <X className="size-4" />
                        </button>
                    )}
                </form>

                {/* Mobile Filter Pills (Scrollable horizontally) */}
                <div className="flex items-center gap-1.5 overflow-x-auto pb-1 no-scrollbar">
                    {[
                        { id: 'todas', label: 'Todas' },
                        { id: 'pendientes', label: 'Pendientes' },
                        { id: 'en_taller', label: 'En Taller' },
                        { id: 'esperando_autorizacion', label: 'Esperando Autoriz.' },
                        { id: 'por_entregar', label: 'Por Entregar' },
                    ].map((t) => (
                        <button
                            key={t.id}
                            type="button"
                            onClick={() => changeTab(t.id)}
                            className={`whitespace-nowrap rounded-full px-3 py-1 text-[11px] font-bold transition-all ${
                                currentTab === t.id
                                    ? 'bg-[#201F1D] text-white shadow-xs'
                                    : 'bg-white border border-[#E4E1DC] text-[#6B6965] hover:bg-[#F3F1ED]'
                            }`}
                        >
                            {t.label}
                        </button>
                    ))}
                </div>

                {/* Order List: Mobile Cards (Never horizontal tables) */}
                <div className="space-y-3">
                    {orders.data.length === 0 ? (
                        <Card className="rounded-[14px] border border-dashed border-[#D5D3CE] bg-white p-8 text-center">
                            <div className="mx-auto flex size-12 items-center justify-center rounded-full bg-[#F4EFEB] text-[#A8201A] mb-2">
                                <Wrench className="size-6" />
                            </div>
                            <h3 className="text-sm font-bold text-[#201F1D]">
                                No hay órdenes en esta cola
                            </h3>
                            <p className="mt-1 text-xs text-[#6B6965]">
                                {search
                                    ? 'No se encontraron órdenes que coincidan con la búsqueda.'
                                    : 'Todas las órdenes de esta cola están atendidas o en otra etapa.'}
                            </p>
                        </Card>
                    ) : (
                        orders.data.map((order) => (
                            <Link
                                key={order.id}
                                href={`/${teamSlug}/tecnico-planta/ordenes/${order.id}`}
                                className="block rounded-[14px] border border-[#E4E1DC] bg-white p-4 shadow-xs transition-all hover:border-[#A8201A]/40 hover:shadow-sm active:scale-[0.99]"
                            >
                                <div className="flex items-start justify-between gap-2 border-b border-[#F4EFEB] pb-2.5 mb-2.5">
                                    <div>
                                        <div className="flex items-center gap-1.5">
                                            <span className="font-mono text-xs font-black text-[#A8201A]">
                                                {order.codigo}
                                            </span>
                                            {getPrioridadBadge(order.prioridad)}
                                        </div>
                                        <h3 className="text-sm font-bold text-[#201F1D] leading-snug mt-0.5 line-clamp-1">
                                            {order.cliente}
                                        </h3>
                                    </div>

                                    <div>
                                        {getEstadoBadge(order.estado)}
                                    </div>
                                </div>

                                <div className="grid grid-cols-2 gap-2 text-[11px] text-[#6B6965]">
                                    <div className="flex items-center gap-1.5 truncate">
                                        <Wrench className="size-3.5 text-gray-400 shrink-0" />
                                        <span className="truncate">{order.tipo_servicio}</span>
                                    </div>

                                    <div className="flex items-center gap-1.5 truncate justify-end">
                                        <Calendar className="size-3.5 text-gray-400 shrink-0" />
                                        <span>{order.fecha}</span>
                                    </div>
                                </div>

                                {/* Banner condicional si requiere autorización */}
                                {order.requiere_autorizacion_count > 0 && (
                                    <div className="mt-3 flex items-center justify-between rounded-[8px] bg-[#FEF2F2] border border-[#FCA5A5] px-2.5 py-1.5 text-[11px] text-[#DC2626]">
                                        <div className="flex items-center gap-1.5 font-bold">
                                            <AlertTriangle className="size-3.5 shrink-0" />
                                            <span>{order.requiere_autorizacion_count} deficiencia(s) esperando autorización</span>
                                        </div>
                                        <ArrowRight className="size-3" />
                                    </div>
                                )}
                            </Link>
                        ))
                    )}
                </div>

                {/* Pagination */}
                {orders.links.length > 3 && (
                    <div className="flex items-center justify-between pt-2">
                        <span className="text-xs text-[#6B6965]">
                            {orders.total} órdenes totales
                        </span>
                        <div className="flex gap-1">
                            {orders.links.map((link, i) => (
                                <Button
                                    key={i}
                                    variant={link.active ? 'default' : 'outline'}
                                    size="sm"
                                    disabled={!link.url}
                                    onClick={() => link.url && router.get(link.url)}
                                    className="h-7 px-2.5 text-xs"
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </TecnicoPlantaLayout>
    );
}
