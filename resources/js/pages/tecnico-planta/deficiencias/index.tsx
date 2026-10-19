import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowRight, Camera, Search } from 'lucide-react';
import { useState } from 'react';

import { Empty } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { useBusquedaDinamica } from '@/hooks/use-busqueda-dinamica';
import TecnicoPlantaLayout from '@/layouts/tecnico-planta-layout';
import DeficienciasRoutes from '@/routes/tecnico-planta/deficiencias';
import OrdenesRoutes from '@/routes/tecnico-planta/ordenes';
import type { Team } from '@/types';

interface DeficiencyItem {
    id: number;
    orden_id: number;
    orden_codigo: string;
    cliente: string;
    equipo_serie: string | null;
    componente: string;
    condicion: string;
    repuesto_sugerido: string | null;
    estado: string;
    resolucion: string | null;
    authorization: {
        autorizado_por: string;
        canal: string;
    } | null;
    created_at: string | null;
    foto_url: string | null;
}

interface Props {
    deficiencies: {
        data: DeficiencyItem[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    counts: {
        todas: number;
        para_reparar: number;
        esperando_autorizacion: number;
        rechazada: number;
        resuelta: number;
    };
    filters: { estado: string; search: string };
}

const FILTROS = [
    { clave: 'para_reparar', texto: 'Para reparar' },
    { clave: 'esperando_autorizacion', texto: 'En espera' },
    { clave: 'rechazada', texto: 'Rechazadas' },
    { clave: 'resuelta', texto: 'Reparadas' },
] as const;

const ETIQUETAS: Record<string, { texto: string; clase: string }> = {
    detectada: {
        texto: 'Para reparar',
        clase: 'bg-primary/10 text-primary-strong',
    },
    autorizada: {
        texto: 'Autorizada',
        clase: 'bg-emerald-500/15 text-success-strong',
    },
    esperando_autorizacion: {
        texto: 'Espera al vendedor',
        clase: 'bg-amber-500/15 text-warning-strong',
    },
    rechazada: { texto: 'Rechazada', clase: 'bg-muted text-muted-foreground' },
    resuelta: {
        texto: 'Reparada',
        clase: 'bg-emerald-500/15 text-success-strong',
    },
};

function hace(fecha: string | null): string {
    if (!fecha) {
        return '';
    }

    const minutos = Math.max(
        0,
        Math.round((Date.now() - new Date(fecha).getTime()) / 60000),
    );

    if (minutos < 60) {
        return `hace ${minutos} min`;
    }

    const horas = Math.round(minutos / 60);

    return horas < 24 ? `hace ${horas} h` : `hace ${Math.round(horas / 24)} d`;
}

/** Las fallas de todas las órdenes del técnico; cada una lleva a su orden. */
export default function DeficienciasIndex({
    deficiencies,
    counts,
    filters,
}: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const equipo = currentTeam?.slug ?? '';
    const [search, setSearch] = useState(filters.search);

    const filtrar = (estado: string, texto = search) =>
        router.get(
            DeficienciasRoutes.index.url({ current_team: equipo }),
            { estado, search: texto.trim() },
            { preserveState: true, preserveScroll: true },
        );
    const buscar = useBusquedaDinamica(search, () => filtrar(filters.estado));

    return (
        <TecnicoPlantaLayout>
            <Head title="Hallazgos - Planta" />

            <div className="space-y-4">
                <div>
                    <h1 className="text-foreground text-lg font-black tracking-tight">
                        Hallazgos
                    </h1>
                    <p className="text-muted-foreground text-xs">
                        Las fallas de todas tus órdenes. Toca una para ir a su
                        orden.
                    </p>
                </div>

                <form onSubmit={buscar} className="relative">
                    <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                    <Input
                        aria-label="Buscar por orden, extintor o falla"
                        placeholder="Buscar por orden, extintor o falla"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="bg-card h-11 pl-9 text-sm"
                    />
                </form>

                <div
                    role="group"
                    aria-label="Filtrar hallazgos"
                    className="border-border/60 bg-muted/50 grid grid-cols-2 gap-1.5 rounded-2xl border p-1.5"
                >
                    {FILTROS.map((f) => {
                        const activo = filters.estado === f.clave;

                        return (
                            <button
                                key={f.clave}
                                type="button"
                                aria-pressed={activo}
                                onClick={() => filtrar(f.clave)}
                                className={`focus-visible:ring-ring flex min-h-12 min-w-0 items-center justify-between gap-2 rounded-xl px-3 py-2 text-xs focus-visible:ring-2 focus-visible:outline-none ${
                                    activo
                                        ? 'bg-card text-foreground font-bold shadow-sm'
                                        : 'text-muted-foreground hover:bg-card/60 font-medium'
                                }`}
                            >
                                <span>{f.texto}</span>
                                <span
                                    className={`flex min-w-6 shrink-0 items-center justify-center rounded-lg px-1.5 py-0.5 text-xs tabular-nums ${activo ? 'bg-primary/10 text-primary-strong' : 'bg-background/70 text-muted-foreground'}`}
                                >
                                    {counts[f.clave]}
                                </span>
                            </button>
                        );
                    })}
                </div>

                {deficiencies.data.length === 0 ? (
                    <Empty
                        icon={<Camera />}
                        title="Nada en este grupo"
                        description={
                            filters.estado === 'para_reparar'
                                ? 'No hay fallas listas para reparar.'
                                : 'No hay fallas con este estado.'
                        }
                    />
                ) : (
                    <ul className="space-y-3">
                        {deficiencies.data.map((d) => {
                            const etiqueta = ETIQUETAS[d.estado] ?? {
                                texto: d.estado,
                                clase: 'bg-muted text-muted-foreground',
                            };

                            return (
                                <li key={d.id}>
                                    <Link
                                        href={OrdenesRoutes.show.url({
                                            current_team: equipo,
                                            service_order: d.orden_id,
                                        })}
                                        className="border-border bg-card flex gap-3 rounded-2xl border p-3"
                                    >
                                        {d.foto_url ? (
                                            <img
                                                src={d.foto_url}
                                                alt={`Foto de ${d.componente}`}
                                                className="size-16 shrink-0 rounded-xl object-cover"
                                            />
                                        ) : (
                                            <span className="bg-muted text-muted-foreground flex size-16 shrink-0 items-center justify-center rounded-xl">
                                                <Camera
                                                    className="size-5"
                                                    aria-label="Sin foto"
                                                />
                                            </span>
                                        )}
                                        <span className="min-w-0 flex-1 space-y-0.5">
                                            <span className="flex items-start gap-2">
                                                <span className="text-foreground flex-1 text-sm font-bold">
                                                    {d.componente}
                                                </span>
                                                <span
                                                    className={`shrink-0 rounded-full px-2 py-0.5 text-xs font-bold ${etiqueta.clase}`}
                                                >
                                                    {etiqueta.texto}
                                                </span>
                                            </span>
                                            <span className="text-muted-foreground block text-xs">
                                                {d.orden_codigo} · {d.cliente}
                                            </span>
                                            <span className="text-muted-foreground block text-xs">
                                                {[
                                                    d.equipo_serie,
                                                    d.condicion,
                                                    d.repuesto_sugerido,
                                                    d.estado ===
                                                        'esperando_autorizacion' &&
                                                        hace(d.created_at),
                                                    d.authorization &&
                                                        `Autorizó ${d.authorization.autorizado_por}`,
                                                    d.resolucion,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' · ')}
                                            </span>
                                            {(d.estado === 'detectada' ||
                                                d.estado === 'autorizada') && (
                                                <span className="text-destructive-strong inline-flex items-center gap-1 text-sm font-bold">
                                                    Reparar
                                                    <ArrowRight className="size-4" />
                                                </span>
                                            )}
                                        </span>
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                )}

                {deficiencies.last_page > 1 && (
                    <div className="flex gap-2">
                        {[
                            ['Anterior', deficiencies.prev_page_url],
                            ['Siguiente', deficiencies.next_page_url],
                        ].map(([texto, url]) => (
                            <button
                                key={texto}
                                type="button"
                                disabled={!url}
                                onClick={() => url && router.get(url)}
                                className="border-border text-foreground min-h-11 flex-1 rounded-xl border text-sm font-bold disabled:opacity-40"
                            >
                                {texto}
                            </button>
                        ))}
                    </div>
                )}
            </div>
        </TecnicoPlantaLayout>
    );
}
