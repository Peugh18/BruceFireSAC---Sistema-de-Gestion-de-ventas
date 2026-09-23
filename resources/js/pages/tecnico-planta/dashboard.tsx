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
                <span className="inline-flex items-center gap-1 rounded-full border border-amber-500/20 bg-amber-500/10 px-2 py-0.5 text-[10.5px] font-bold text-amber-600 dark:text-amber-400">
                    <Clock className="size-3" />
                    Pendiente Recepción
                </span>
            );
        case 'recibido_planta':
            return (
                <span className="inline-flex items-center gap-1 rounded-full border border-sky-500/20 bg-sky-500/10 px-2 py-0.5 text-[10.5px] font-bold text-blue-600 dark:text-blue-400">
                    <PackageCheck className="size-3" />
                    Recibido en Planta
                </span>
            );
        case 'en_revision':
        case 'en_proceso':
            return (
                <span className="inline-flex items-center gap-1 rounded-full border border-amber-500/20 bg-amber-500/10 px-2 py-0.5 text-[10.5px] font-bold text-amber-600 dark:text-amber-400">
                    <Wrench className="size-3" />
                    En Trabajo
                </span>
            );
        case 'esperando_autorizacion':
            return (
                <span className="inline-flex items-center gap-1 rounded-full border border-destructive/20 bg-destructive/10 px-2 py-0.5 text-[10.5px] font-bold text-destructive">
                    <AlertTriangle className="size-3" />
                    Esperando Autorización
                </span>
            );
        case 'autorizado':
            return (
                <span className="inline-flex items-center gap-1 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2 py-0.5 text-[10.5px] font-bold text-emerald-600 dark:text-emerald-400">
                    <CheckCircle2 className="size-3" />
                    Autorizado
                </span>
            );
        case 'trabajo_terminado':
        case 'listo_certificado':
        case 'listo_entrega':
            return (
                <span className="inline-flex items-center gap-1 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2 py-0.5 text-[10.5px] font-bold text-emerald-600 dark:text-emerald-400">
                    <CheckCircle2 className="size-3" />
                    Listo / Terminado
                </span>
            );
        default:
            return (
                <span className="inline-flex items-center rounded-full border border-border bg-muted/40 px-2 py-0.5 text-[10.5px] font-semibold text-muted-foreground">
                    {estado.replace('_', ' ')}
                </span>
            );
    }
}

function getPrioridadBadge(prioridad: string) {
    if (prioridad === 'urgente') {
        return (
            <span className="rounded-[6px] border border-destructive/20 bg-destructive/10 px-1.5 py-0.5 text-[9.5px] font-black text-destructive uppercase">
                Urgente
            </span>
        );
    }
    if (prioridad === 'alta') {
        return (
            <span className="rounded-[6px] border border-amber-500/20 bg-amber-500/10 px-1.5 py-0.5 text-[9.5px] font-bold text-amber-600 dark:text-amber-400 uppercase">
                Alta
            </span>
        );
    }
    return (
        <span className="rounded-[6px] border border-border bg-muted/40 px-1.5 py-0.5 text-[9.5px] font-semibold text-muted-foreground uppercase">
            Normal
        </span>
    );
}

export default function TecnicoPlantaDashboard({
    orders,
    kpis,
    filters,
}: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug = currentTeam?.slug ?? '';

    const [search, setSearch] = useState(filters.search || '');
    const currentTab = filters.tab || 'todas';

    const handleSearch = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            `/${teamSlug}/tecnico-planta/dashboard`,
            { tab: currentTab, search: search.trim() },
            { preserveState: true },
        );
    };

    const changeTab = (newTab: string) => {
        router.get(
            `/${teamSlug}/tecnico-planta/dashboard`,
            { tab: newTab, search },
            { preserveState: true },
        );
    };

    return (
        <TecnicoPlantaLayout title="Taller y Planta">
            <Head title="Taller Principal - Planta" />

            <div className="space-y-4">
                {/* Greeting & Quick Action */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-lg font-black tracking-tight text-foreground">
                            Cola Operativa de Taller
                        </h1>
                        <p className="text-[11px] text-muted-foreground">
                            Órdenes de servicio asignadas al departamento de
                            planta.
                        </p>
                    </div>

                    <Link
                        href={`/${teamSlug}/tecnico-planta/recepcion`}
                        className="inline-flex items-center gap-1.5 rounded-[10px] bg-primary px-3.5 py-2 text-xs font-bold text-white shadow-xs transition-all hover:bg-primary/90 active:scale-95"
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
                                ? 'border-amber-500/20 bg-amber-500/10 ring-2 ring-[#F59E0B]/30'
                                : 'border-border bg-card hover:bg-muted/40'
                        }`}
                    >
                        <div className="flex items-center justify-between text-amber-600 dark:text-amber-400">
                            <span className="text-[10.5px] font-bold tracking-wider uppercase">
                                Pendientes
                            </span>
                            <Clock className="size-4" />
                        </div>
                        <div className="mt-2 text-2xl font-black text-foreground">
                            {kpis.pendientes_recepcion}
                        </div>
                        <span className="text-[10px] text-muted-foreground">
                            Por recibir
                        </span>
                    </button>

                    <button
                        type="button"
                        onClick={() => changeTab('en_taller')}
                        className={`flex flex-col justify-between rounded-[12px] border p-3 text-left transition-all ${
                            currentTab === 'en_taller'
                                ? 'border-blue-500/20 bg-sky-500/10 ring-2 ring-[#2563EB]/30'
                                : 'border-border bg-card hover:bg-muted/40'
                        }`}
                    >
                        <div className="flex items-center justify-between text-blue-600 dark:text-blue-400">
                            <span className="text-[10.5px] font-bold tracking-wider uppercase">
                                En Taller
                            </span>
                            <Wrench className="size-4" />
                        </div>
                        <div className="mt-2 text-2xl font-black text-foreground">
                            {kpis.en_taller}
                        </div>
                        <span className="text-[10px] text-muted-foreground">
                            En proceso
                        </span>
                    </button>

                    <button
                        type="button"
                        onClick={() => changeTab('esperando_autorizacion')}
                        className={`flex flex-col justify-between rounded-[12px] border p-3 text-left transition-all ${
                            currentTab === 'esperando_autorizacion'
                                ? 'border-destructive/20 bg-destructive/10 ring-2 ring-[#DC2626]/30'
                                : 'border-border bg-card hover:bg-muted/40'
                        }`}
                    >
                        <div className="flex items-center justify-between text-destructive">
                            <span className="text-[10.5px] font-bold tracking-wider uppercase">
                                Por Autorizar
                            </span>
                            <AlertTriangle className="size-4" />
                        </div>
                        <div className="mt-2 text-2xl font-black text-foreground">
                            {kpis.esperando_autorizacion}
                        </div>
                        <span className="text-[10px] text-muted-foreground">
                            Deficiencias
                        </span>
                    </button>

                    <button
                        type="button"
                        onClick={() => changeTab('por_entregar')}
                        className={`flex flex-col justify-between rounded-[12px] border p-3 text-left transition-all ${
                            currentTab === 'por_entregar'
                                ? 'border-emerald-500/20 bg-emerald-500/10 ring-2 ring-[#16A34A]/30'
                                : 'border-border bg-card hover:bg-muted/40'
                        }`}
                    >
                        <div className="flex items-center justify-between text-emerald-600 dark:text-emerald-400">
                            <span className="text-[10.5px] font-bold tracking-wider uppercase">
                                Listas
                            </span>
                            <CheckCircle2 className="size-4" />
                        </div>
                        <div className="mt-2 text-2xl font-black text-foreground">
                            {kpis.listas}
                        </div>
                        <span className="text-[10px] text-muted-foreground">
                            Certif / Entrega
                        </span>
                    </button>
                </div>

                {/* Search Bar */}
                <form onSubmit={handleSearch} className="relative">
                    <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-gray-400" />
                    <Input
                        type="text"
                        placeholder="Buscar por código OS o nombre de cliente..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="h-10 rounded-[10px] bg-card pr-8 pl-9 text-xs"
                    />
                    {search && (
                        <button
                            type="button"
                            onClick={() => {
                                setSearch('');
                                router.get(
                                    `/${teamSlug}/tecnico-planta/dashboard`,
                                    { tab: currentTab },
                                );
                            }}
                            className="absolute top-1/2 right-2.5 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                        >
                            <X className="size-4" />
                        </button>
                    )}
                </form>

                {/* Mobile Filter Pills (Scrollable horizontally) */}
                <div className="no-scrollbar flex items-center gap-1.5 overflow-x-auto pb-1">
                    {[
                        { id: 'todas', label: 'Todas' },
                        { id: 'pendientes', label: 'Pendientes' },
                        { id: 'en_taller', label: 'En Taller' },
                        {
                            id: 'esperando_autorizacion',
                            label: 'Esperando Autoriz.',
                        },
                        { id: 'por_entregar', label: 'Por Entregar' },
                    ].map((t) => (
                        <button
                            key={t.id}
                            type="button"
                            onClick={() => changeTab(t.id)}
                            className={`rounded-full px-3 py-1 text-[11px] font-bold whitespace-nowrap transition-all ${
                                currentTab === t.id
                                    ? 'bg-foreground text-background shadow-xs'
                                    : 'border border-border bg-card text-muted-foreground hover:bg-background'
                            }`}
                        >
                            {t.label}
                        </button>
                    ))}
                </div>

                {/* Order List: Mobile Cards (Never horizontal tables) */}
                <div className="space-y-3">
                    {orders.data.length === 0 ? (
                        <Card className="rounded-[14px] border border-dashed border-border bg-card p-8 text-center">
                            <div className="mx-auto mb-2 flex size-12 items-center justify-center rounded-full bg-muted/30 text-destructive">
                                <Wrench className="size-6" />
                            </div>
                            <h3 className="text-sm font-bold text-foreground">
                                No hay órdenes en esta cola
                            </h3>
                            <p className="mt-1 text-xs text-muted-foreground">
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
                                className="block rounded-[14px] border border-border bg-card p-4 shadow-xs transition-all hover:border-destructive/20/40 hover:shadow-sm active:scale-[0.99]"
                            >
                                <div className="mb-2.5 flex items-start justify-between gap-2 border-b border-border pb-2.5">
                                    <div>
                                        <div className="flex items-center gap-1.5">
                                            <span className="font-mono text-xs font-black text-destructive">
                                                {order.codigo}
                                            </span>
                                            {getPrioridadBadge(order.prioridad)}
                                        </div>
                                        <h3 className="mt-0.5 line-clamp-1 text-sm leading-snug font-bold text-foreground">
                                            {order.cliente}
                                        </h3>
                                    </div>

                                    <div>{getEstadoBadge(order.estado)}</div>
                                </div>

                                <div className="grid grid-cols-2 gap-2 text-[11px] text-muted-foreground">
                                    <div className="flex items-center gap-1.5 truncate">
                                        <Wrench className="size-3.5 shrink-0 text-gray-400" />
                                        <span className="truncate capitalize">
                                            {order.tipo_servicio}
                                        </span>
                                    </div>

                                    <div className="flex items-center justify-end gap-1.5 truncate">
                                        <Calendar className="size-3.5 shrink-0 text-gray-400" />
                                        <span>{order.fecha}</span>
                                    </div>
                                </div>

                                {/* Banner condicional si requiere autorización */}
                                {order.requiere_autorizacion_count > 0 && (
                                    <div className="mt-3 flex items-center justify-between rounded-[8px] border border-destructive/20 bg-destructive/10 px-2.5 py-1.5 text-[11px] text-destructive">
                                        <div className="flex items-center gap-1.5 font-bold">
                                            <AlertTriangle className="size-3.5 shrink-0" />
                                            <span>
                                                {
                                                    order.requiere_autorizacion_count
                                                }{' '}
                                                deficiencia(s) esperando
                                                autorización
                                            </span>
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
                        <span className="text-xs text-muted-foreground">
                            {orders.total} órdenes totales
                        </span>
                        <div className="flex gap-1">
                            {orders.links.map((link, i) => (
                                <Button
                                    key={i}
                                    variant={
                                        link.active ? 'default' : 'outline'
                                    }
                                    size="sm"
                                    disabled={!link.url}
                                    onClick={() =>
                                        link.url && router.get(link.url)
                                    }
                                    className="h-7 px-2.5 text-xs"
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </TecnicoPlantaLayout>
    );
}
