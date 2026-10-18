import GraficosDashboard, {
    type DatosGraficos,
} from '@/components/graficos-dashboard';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ChevronRight,
    CheckCircle2,
    Clock,
    MessageSquare,
    PackageCheck,
    Search,
    Wrench,
    X,
} from 'lucide-react';
import { useState } from 'react';

import { useBusquedaDinamica } from '@/hooks/use-busqueda-dinamica';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Empty } from '@/components/ui/empty';
import TecnicoPlantaLayout from '@/layouts/tecnico-planta-layout';
import { BarraPasos, type PasoTaller } from '@/components/pasos-taller';
import ordenes from '@/routes/tecnico-planta/ordenes';
import recepciones from '@/routes/tecnico-planta/recepciones';
import type { Team } from '@/types';
import TecnicoPlantaRoutes from '@/routes/tecnico-planta';

export type OrderItem = {
    id: number;
    codigo: string;
    cliente: string;
    cliente_doc: string;
    telefono: string | null;
    sede?: string | null;
    tipo_servicio: string;
    fecha: string;
    prioridad: string;
    estado: string;
    estado_coarse: string;
    observaciones?: string | null;
    deficiencias_count: number;
    requiere_autorizacion_count: number;
    ultimo_evento?: string | null;
    paso: PasoTaller;
    no_leidos: number;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Props = {
    graficos: DatosGraficos;
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
        atrasadas: number;
    };
    filters: {
        tab: string;
        search: string;
        fecha: string;
        hoy: string;
    };
};

function getEstadoBadge(estado: string) {
    switch (estado) {
        case 'pendiente_recepcion':
            return (
                <span className="text-warning-strong inline-flex items-center gap-1 rounded-full border border-amber-500/20 bg-amber-500/10 px-2 py-0.5 text-xs font-bold">
                    <Clock className="size-3" />
                    Pendiente Recepción
                </span>
            );
        case 'recibido_planta':
            return (
                <span className="border-primary/20 bg-primary/10 text-primary-strong inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-bold">
                    <PackageCheck className="size-3" />
                    Recibido en Planta
                </span>
            );
        case 'en_revision':
        case 'en_proceso':
            return (
                <span className="text-warning-strong inline-flex items-center gap-1 rounded-full border border-amber-500/20 bg-amber-500/10 px-2 py-0.5 text-xs font-bold">
                    <Wrench className="size-3" />
                    En Trabajo
                </span>
            );
        case 'esperando_autorizacion':
            return (
                <span className="border-destructive/20 bg-destructive/10 text-destructive-strong inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-bold">
                    <AlertTriangle className="size-3" />
                    Esperando Autorización
                </span>
            );
        case 'autorizado':
            return (
                <span className="text-success-strong inline-flex items-center gap-1 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2 py-0.5 text-xs font-bold">
                    <CheckCircle2 className="size-3" />
                    Autorizado
                </span>
            );
        case 'trabajo_terminado':
        case 'listo_certificado':
        case 'listo_entrega':
            return (
                <span className="text-success-strong inline-flex items-center gap-1 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2 py-0.5 text-xs font-bold">
                    <CheckCircle2 className="size-3" />
                    Listo / Terminado
                </span>
            );
        default:
            return (
                <span className="border-border bg-muted/40 text-muted-foreground inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-semibold">
                    {estado.replace('_', ' ')}
                </span>
            );
    }
}

function getPrioridadBadge(prioridad: string) {
    if (prioridad === 'urgente') {
        return (
            <span className="border-destructive/20 bg-destructive/10 text-destructive-strong rounded-md border px-1.5 py-0.5 text-xs font-black uppercase">
                Urgente
            </span>
        );
    }
    if (prioridad === 'alta') {
        return (
            <span className="text-warning-strong rounded-md border border-amber-500/20 bg-amber-500/10 px-1.5 py-0.5 text-xs font-bold uppercase dark:text-amber-400">
                Alta
            </span>
        );
    }
    return (
        <span className="bg-muted text-muted-foreground rounded-full px-2.5 py-1 text-xs font-medium">
            Normal
        </span>
    );
}

function formatOrderDate(value: string): string {
    const dateOnly = value.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (!dateOnly) {
        return value;
    }

    const date = new Date(
        Number(dateOnly[1]),
        Number(dateOnly[2]) - 1,
        Number(dateOnly[3]),
    );

    return new Intl.DateTimeFormat('es-PE', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(date);
}

export default function TecnicoPlantaDashboard({
    graficos,
    orders,
    kpis,
    filters,
}: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug = currentTeam?.slug ?? '';

    const [search, setSearch] = useState(filters.search || '');
    const currentTab = filters.tab || 'todas';

    const aplicarBusqueda = () => {
        router.get(
            TecnicoPlantaRoutes.dashboard.url({ current_team: teamSlug }),
            { tab: currentTab, search: search.trim(), fecha: filters.fecha },
            { preserveState: true },
        );
    };
    const handleSearch = useBusquedaDinamica(search, aplicarBusqueda);

    const changeTab = (newTab: string) => {
        router.get(
            TecnicoPlantaRoutes.dashboard.url({ current_team: teamSlug }),
            { tab: newTab, search, fecha: filters.fecha },
            { preserveState: true },
        );
    };

    return (
        <TecnicoPlantaLayout title="Taller y Planta">
            <Head title="Taller Principal - Planta" />

            <div className="space-y-4 sm:space-y-5">
                <GraficosDashboard rol="planta" datos={graficos} />
                <div className="flex items-center justify-between gap-4 pb-1">
                    <div className="min-w-0">
                        <p className="text-primary-strong mb-1 text-xs font-bold tracking-wider uppercase">
                            Planta
                        </p>
                        <h1 className="text-foreground text-2xl font-bold tracking-tight sm:text-3xl">
                            Cola de taller
                        </h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Pendientes de hoy y días anteriores. Urgentes
                            primero.
                        </p>
                    </div>

                    <Link
                        href={recepciones.index.url(teamSlug)}
                        className="focus-visible:ring-ring flex shrink-0 flex-col items-center gap-1.5 rounded-2xl text-xs font-semibold focus-visible:ring-2 focus-visible:outline-none"
                    >
                        <span className="bg-primary text-primary-foreground flex size-12 items-center justify-center rounded-full shadow-sm sm:size-14">
                            <PackageCheck
                                className="size-6"
                                aria-hidden="true"
                            />
                        </span>
                        Recepción
                    </Link>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-2">
                    <label
                        htmlFor="fecha-cola"
                        className="text-sm font-semibold"
                    >
                        {filters.fecha === filters.hoy
                            ? 'Hoy y atrasadas'
                            : 'Consultar día'}
                    </label>
                    <div className="flex items-center gap-2">
                        <input
                            id="fecha-cola"
                            type="date"
                            aria-label="Fecha de la cola de trabajo"
                            value={filters.fecha}
                            onChange={(event) =>
                                router.get(
                                    TecnicoPlantaRoutes.dashboard.url({
                                        current_team: teamSlug,
                                    }),
                                    {
                                        tab: currentTab,
                                        search,
                                        fecha:
                                            event.target.value || filters.hoy,
                                    },
                                    { preserveState: true },
                                )
                            }
                            className="border-input bg-background min-h-11 min-w-0 rounded-xl border px-3 text-base"
                        />
                        {filters.fecha !== filters.hoy && (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.get(
                                        TecnicoPlantaRoutes.dashboard.url({
                                            current_team: teamSlug,
                                        }),
                                        {
                                            tab: 'todas',
                                            search,
                                            fecha: filters.hoy,
                                        },
                                        { preserveState: true },
                                    )
                                }
                            >
                                Hoy
                            </Button>
                        )}
                    </div>
                </div>

                <div
                    className="border-border bg-card grid grid-cols-2 overflow-hidden rounded-3xl border shadow-xs sm:grid-cols-4"
                    aria-label="Filtrar órdenes por estado"
                >
                    {[
                        {
                            tab: 'pendientes',
                            label: 'Pendientes',
                            value: kpis.pendientes_recepcion,
                            detail: 'Por recibir',
                            icon: Clock,
                            tone: 'bg-amber-500/10 text-warning-strong',
                        },
                        {
                            tab: 'en_taller',
                            label: 'En taller',
                            value: kpis.en_taller,
                            detail: 'En proceso',
                            icon: Wrench,
                            tone: 'bg-primary/10 text-primary-strong',
                        },
                        {
                            tab: 'esperando_autorizacion',
                            label: 'Por autorizar',
                            value: kpis.esperando_autorizacion,
                            detail: 'Deficiencias',
                            icon: AlertTriangle,
                            tone: 'bg-destructive/10 text-destructive-strong',
                        },
                        {
                            tab: 'atrasadas',
                            label: 'Atrasadas',
                            value: kpis.atrasadas,
                            detail: 'Pendientes anteriores',
                            icon: Clock,
                            tone: 'bg-amber-500/10 text-warning-strong',
                        },
                    ].map((filter, index) => (
                        <button
                            key={filter.tab}
                            type="button"
                            role="checkbox"
                            aria-checked={currentTab === filter.tab}
                            onClick={() =>
                                changeTab(
                                    currentTab === filter.tab
                                        ? 'todas'
                                        : filter.tab,
                                )
                            }
                            className={`border-border focus-visible:ring-ring hover:bg-muted/40 flex items-start gap-3 p-4 text-left transition-colors focus-visible:ring-2 focus-visible:outline-none focus-visible:ring-inset ${index % 2 === 0 ? 'border-r' : ''} ${index < 2 ? 'border-b sm:border-b-0' : ''} ${index === 1 ? 'sm:border-r' : ''} ${currentTab === filter.tab ? 'bg-primary/5 ring-primary/30 ring-2 ring-inset' : ''}`}
                        >
                            <span
                                className={`flex size-10 shrink-0 items-center justify-center rounded-full ${filter.tone}`}
                            >
                                <filter.icon
                                    className="size-5"
                                    aria-hidden="true"
                                />
                            </span>
                            <span className="flex min-w-0 flex-col gap-0.5">
                                <span className="text-foreground text-2xl leading-none font-bold tabular-nums">
                                    {filter.value}
                                </span>
                                <span className="text-foreground text-sm font-semibold">
                                    {filter.label}
                                </span>
                                <span className="text-muted-foreground text-xs leading-relaxed">
                                    {filter.detail}
                                </span>
                            </span>
                        </button>
                    ))}
                </div>

                {/* Search Bar */}
                <form onSubmit={handleSearch} className="relative">
                    <Search className="text-muted-foreground absolute top-1/2 left-4 size-4 -translate-y-1/2" />
                    <Input
                        aria-label="Buscar"
                        type="text"
                        placeholder="Buscar orden o cliente..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="bg-card h-12 rounded-2xl pr-12 pl-11 text-sm shadow-xs"
                    />
                    {search && (
                        <button
                            type="button"
                            onClick={() => {
                                setSearch('');
                                router.get(
                                    TecnicoPlantaRoutes.dashboard.url({
                                        current_team: teamSlug,
                                    }),
                                    { tab: currentTab, fecha: filters.fecha },
                                );
                            }}
                            className="text-muted-foreground hover:text-foreground absolute top-1/2 right-1 flex size-11 -translate-y-1/2 items-center justify-center rounded-xl"
                        >
                            <X className="size-4" />
                            <span className="sr-only">Limpiar búsqueda</span>
                        </button>
                    )}
                </form>

                {currentTab !== 'todas' ? (
                    <button
                        type="button"
                        onClick={() => changeTab('todas')}
                        className="text-primary-strong -mt-1 self-start text-sm font-medium hover:underline"
                    >
                        Quitar filtro y ver todas
                    </button>
                ) : null}

                {/* Order List: Mobile Cards (Never horizontal tables) */}
                <div className="space-y-3">
                    {orders.data.length === 0 ? (
                        <Empty
                            icon={<Wrench />}
                            title="No hay órdenes en esta cola"
                            description={
                                search
                                    ? 'No se encontraron órdenes que coincidan con la búsqueda.'
                                    : 'Todas las órdenes de esta cola están atendidas o en otra etapa.'
                            }
                        />
                    ) : (
                        orders.data.map((order) => (
                            <Link
                                key={order.id}
                                href={ordenes.show.url([teamSlug, order.id])}
                                className="border-border bg-card hover:border-primary/30 focus-visible:ring-ring block rounded-3xl border p-4 shadow-xs transition-colors focus-visible:ring-2 focus-visible:outline-none sm:p-5"
                            >
                                <div className="flex flex-wrap items-center gap-x-2 gap-y-1.5">
                                    <span className="text-primary-strong text-xs font-bold">
                                        {order.codigo}
                                    </span>
                                    {getPrioridadBadge(order.prioridad)}
                                    <span className="text-muted-foreground ml-auto text-xs">
                                        {formatOrderDate(order.fecha)}
                                    </span>
                                </div>
                                <h3 className="text-foreground mt-2 line-clamp-2 text-base leading-snug font-bold break-words">
                                    {order.cliente}
                                </h3>
                                <div className="mt-3 flex flex-wrap items-center justify-between gap-2">
                                    <span className="bg-muted/70 text-muted-foreground inline-flex max-w-full items-center gap-2 rounded-full px-3 py-1.5 text-xs">
                                        <Wrench
                                            className="size-3.5 shrink-0"
                                            aria-hidden="true"
                                        />
                                        <span className="break-words capitalize">
                                            {order.tipo_servicio}
                                        </span>
                                    </span>
                                    {getEstadoBadge(order.estado)}
                                </div>
                                {order.no_leidos > 0 && (
                                    <span className="bg-primary/10 text-primary-strong mt-2 inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold">
                                        <MessageSquare
                                            className="size-3.5"
                                            aria-hidden="true"
                                        />
                                        {order.no_leidos === 1
                                            ? '1 mensaje nuevo'
                                            : `${order.no_leidos} mensajes nuevos`}
                                    </span>
                                )}
                                <div className="mt-4 flex items-end gap-3">
                                    <div className="min-w-0 flex-1">
                                        <BarraPasos paso={order.paso} />
                                    </div>
                                    <ChevronRight
                                        className="text-muted-foreground size-5 shrink-0"
                                        aria-hidden="true"
                                    />
                                </div>
                            </Link>
                        ))
                    )}
                </div>

                {/* Pagination */}
                {orders.links.length > 3 && (
                    <div className="flex flex-wrap items-center justify-between gap-3 pt-2">
                        <span className="text-muted-foreground text-xs">
                            {orders.total} órdenes totales
                        </span>
                        <div className="flex flex-wrap gap-1">
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
                                    className="min-h-11 min-w-11 px-2.5 text-xs"
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
