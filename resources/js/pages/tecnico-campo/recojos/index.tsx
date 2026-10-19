import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useBusquedaDinamica } from '@/hooks/use-busqueda-dinamica';
import TecnicoCampoLayout from '@/layouts/tecnico-campo-layout';
import type { Team } from '@/types';
import {
    Truck,
    Search,
    MapPin,
    CheckCircle2,
    Clock,
    ArrowRight,
} from 'lucide-react';
import RecojosRoutes from '@/routes/tecnico-campo/recojos';
import { Empty } from '@/components/ui/empty';
import PaginacionOrdenes, {
    type PaginasOrdenes,
} from '@/components/paginacion-ordenes';

interface RecojoItem {
    id: number;
    codigo: string;
    cliente: string;
    direccion: string | null;
    telefono: string | null;
    fecha: string;
    prioridad: string;
    estado: string;
    vehiculo: string | null;
    equipos_count: number;
    ya_recogido: boolean;
    recogido_at: string | null;
}

interface Props {
    recojos: PaginasOrdenes & {
        data: RecojoItem[];
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: {
        search: string;
    };
}

export default function RecojosIndex({ recojos, filters }: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug = currentTeam?.slug ?? '';

    const [search, setSearch] = useState(filters.search || '');

    const aplicarBusqueda = () => {
        router.get(
            RecojosRoutes.index.url({ current_team: teamSlug }),
            { search: search.trim() },
            { preserveState: true },
        );
    };
    const handleSearch = useBusquedaDinamica(search, aplicarBusqueda);

    return (
        <TecnicoCampoLayout>
            <Head title="Recojos en Campo - Cadena de Custodia" />

            <div className="space-y-4">
                <div>
                    <h1 className="text-foreground text-xl font-bold tracking-tight">
                        Recojos de Extintores
                    </h1>
                    <p className="text-muted-foreground text-xs">
                        Cadena de custodia y recepción en instalaciones del
                        cliente
                    </p>
                </div>

                <Link
                    href={RecojosRoutes.create.url(teamSlug)}
                    className="bg-primary text-primary-foreground flex min-h-12 items-center justify-center gap-2 rounded-2xl px-4 text-sm font-semibold"
                >
                    Iniciar recojo
                </Link>

                {/* Search Bar */}
                <form onSubmit={handleSearch} className="relative">
                    <input
                        aria-label="Buscar por cliente, orden o dirección"
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Buscar por cliente, orden o dirección..."
                        className="bg-card focus:ring-ring border-border w-full rounded-xl border py-2.5 pr-4 pl-9 text-xs placeholder-neutral-400 focus:ring-2"
                    />
                    <Search className="text-muted-foreground absolute top-3.5 left-3 h-4 w-4" />
                </form>

                {/* List */}
                <div className="space-y-3">
                    {recojos.data.length === 0 ? (
                        <Empty
                            icon={<Truck />}
                            title="No hay recojos pendientes"
                        />
                    ) : (
                        recojos.data.map((r) => (
                            <Link
                                key={r.id}
                                href={RecojosRoutes.show.url({
                                    current_team: teamSlug,
                                    service_order: r.id,
                                })}
                                className="bg-card hover:bg-muted/60 border-border block space-y-2.5 rounded-3xl border p-4 shadow-sm transition-transform active:scale-[0.99]"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <span className="text-foreground font-mono text-sm font-extrabold">
                                                {r.codigo}
                                            </span>
                                            {r.ya_recogido ? (
                                                <span className="text-success-strong flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold dark:bg-emerald-950/60 dark:text-emerald-300">
                                                    <CheckCircle2 className="h-2.5 w-2.5" />
                                                    Recogido
                                                </span>
                                            ) : (
                                                <span className="text-warning-strong flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold dark:bg-amber-950/60 dark:text-amber-300">
                                                    <Clock className="h-2.5 w-2.5" />
                                                    Por Recoger
                                                </span>
                                            )}
                                        </div>
                                        <h3 className="text-foreground mt-1 line-clamp-1 text-sm font-semibold">
                                            {r.cliente}
                                        </h3>
                                    </div>
                                    <ArrowRight className="h-5 w-5 flex-shrink-0 text-neutral-300 dark:text-neutral-600" />
                                </div>

                                {r.direccion && (
                                    <div className="text-muted-foreground flex items-start gap-1.5 text-xs">
                                        <MapPin className="text-foreground mt-0.5 h-3.5 w-3.5 flex-shrink-0" />
                                        <span className="line-clamp-2">
                                            {r.direccion}
                                        </span>
                                    </div>
                                )}

                                <div className="border-border/70 text-muted-foreground flex items-center justify-between border-t pt-2 text-xs">
                                    <span>
                                        {r.equipos_count > 0
                                            ? `${r.equipos_count} equipo(s)`
                                            : 'Cantidad a confirmar'}
                                    </span>
                                    {r.vehiculo && (
                                        <span>Vehículo: {r.vehiculo}</span>
                                    )}
                                </div>
                            </Link>
                        ))
                    )}
                </div>
            </div>
            <PaginacionOrdenes paginas={recojos} />
        </TecnicoCampoLayout>
    );
}
