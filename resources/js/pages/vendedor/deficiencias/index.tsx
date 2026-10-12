import { Head, Link, router, usePage } from '@inertiajs/react';
import { CheckCircle2, RotateCcw } from 'lucide-react';

import DeficiencyController from '@/actions/App/Http/Controllers/Vendedor/DeficiencyController';
import {
    DeficienciaCard,
    type DeficiencyItem,
    useAccionesDeficiencia,
} from '@/components/deficiencias-vendedor';
import { PageHeader } from '@/components/page-header';
import { ServiciosTabs } from '@/components/servicios-tabs';
import { Empty } from '@/components/ui/empty';
import VendedorLayout from '@/layouts/vendedor-layout';
import type { Team } from '@/types';

export type { DeficiencyItem };

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Props = {
    deficiencies: {
        data: DeficiencyItem[];
        links: PaginationLink[];
        from?: number | null;
        to?: number | null;
        total: number;
    };
    filters: {
        estado?: string;
    };
};

const TABS = [
    { id: '', label: 'Todas' },
    { id: 'detectada', label: 'Detectadas' },
    { id: 'esperando_autorizacion', label: 'Esperando autorización' },
    { id: 'autorizada', label: 'Autorizadas' },
    { id: 'rechazada', label: 'Rechazadas' },
    { id: 'resuelta', label: 'Resueltas' },
] as const;

export default function DeficienciasIndex({ deficiencies, filters }: Props) {
    const { currentTeam, sidebarCounts } = usePage<{
        currentTeam?: Team | null;
        sidebarCounts?: { deficiencias?: number } | null;
    }>().props;
    const teamSlug =
        currentTeam?.slug ||
        (typeof window !== 'undefined'
            ? window.location.pathname.split('/')[1]
            : '');

    const currentEstado = filters?.estado || '';
    const acciones = useAccionesDeficiencia(teamSlug);

    const handleFilterChange = (tabId: string) => {
        router.get(
            DeficiencyController.index.url(teamSlug, {
                query: {
                    estado: tabId || undefined,
                },
            }),
            {},
            {
                preserveScroll: true,
                preserveState: true,
            },
        );
    };

    return (
        <VendedorLayout title="Servicios">
            <Head title="Deficiencias y adicionales" />

            <div className="flex flex-col gap-4">
                <ServiciosTabs
                    teamSlug={teamSlug}
                    activa="deficiencias"
                    deficienciasPendientes={sidebarCounts?.deficiencias}
                />

                <PageHeader
                    title="Deficiencias y adicionales"
                    description="Hallazgos que los técnicos reportaron en equipos de tus clientes. Si implica costo adicional, requiere autorización del cliente antes de proceder."
                />

                {/* Filter Tabs */}
                <div className="bg-muted flex w-fit max-w-full flex-wrap overflow-x-auto rounded-[9px] p-[3px]">
                    {TABS.map((tab) => {
                        const active = currentEstado === tab.id;
                        return (
                            <button
                                aria-pressed={currentEstado === tab.id}
                                aria-label={tab.label}
                                key={tab.id}
                                type="button"
                                onClick={() => handleFilterChange(tab.id)}
                                className={`cursor-pointer rounded-[7px] px-3.5 py-1.5 text-xs font-bold transition-all ${
                                    active
                                        ? 'bg-card text-foreground shadow-[0_1px_2px_rgba(0,0,0,0.06)]'
                                        : 'text-muted-foreground hover:text-foreground'
                                }`}
                            >
                                {tab.label}
                            </button>
                        );
                    })}
                </div>

                {/* List of Deficiency Cards */}
                <div className="flex flex-col gap-3">
                    {deficiencies.data.length === 0 ? (
                        <Empty
                            icon={<CheckCircle2 />}
                            title="No se encontraron deficiencias"
                            description={
                                currentEstado
                                    ? 'No hay registros bajo el estado seleccionado.'
                                    : 'No hay deficiencias ni adicionales pendientes reportados por los técnicos.'
                            }
                        >
                            {currentEstado && (
                                <button
                                    type="button"
                                    onClick={() => handleFilterChange('todos')}
                                    className="border-border bg-card text-foreground hover:bg-muted mt-2 inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold shadow-xs transition-colors"
                                >
                                    <RotateCcw className="size-3" />
                                    <span>Ver todas las deficiencias</span>
                                </button>
                            )}
                        </Empty>
                    ) : (
                        deficiencies.data.map((item) => (
                            <DeficienciaCard
                                key={item.id}
                                item={item}
                                teamSlug={teamSlug}
                                acciones={acciones}
                            />
                        ))
                    )}
                </div>

                {/* Pagination */}
                {deficiencies.links && deficiencies.links.length > 3 && (
                    <div className="text-muted-foreground flex flex-wrap items-center justify-between gap-2 pt-2 text-[11.5px]">
                        <span>
                            Mostrando {deficiencies.from ?? 0}-
                            {deficiencies.to ?? 0} de {deficiencies.total}{' '}
                            registros
                        </span>
                        <div className="flex gap-1">
                            {deficiencies.links.map((link, idx) =>
                                link.url ? (
                                    <Link
                                        key={idx}
                                        href={link.url}
                                        preserveScroll
                                        className={`inline-flex h-7 min-w-[28px] items-center justify-center rounded-[6px] px-2 font-mono text-xs ${
                                            link.active
                                                ? 'bg-foreground text-background font-bold shadow-xs'
                                                : 'border-border bg-card text-foreground/80 hover:bg-background border'
                                        }`}
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                ) : (
                                    <span
                                        key={idx}
                                        className="text-muted-foreground inline-flex h-7 min-w-[28px] items-center justify-center rounded-[6px] border border-transparent px-2 font-mono text-xs"
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                ),
                            )}
                        </div>
                    </div>
                )}

                {/* Flow note banner */}
                <div className="border-border bg-muted/30 text-muted-foreground rounded-[12px] border border-dashed p-3.5 text-xs">
                    <b>Flujo de deficiencias:</b> Detectada (técnico reporta en
                    Planta o Campo) &rarr; Si genera costo, pasa a{' '}
                    <b>Esperando autorización</b> (tú avisas al cliente y
                    obtienes su conformidad) &rarr; <b>Autorizada</b> (se
                    procede con la reparación) o <b>Rechazada</b> &rarr;{' '}
                    <b>Resuelta</b> al completarse el trabajo.
                </div>
            </div>

            {acciones.dialogos}
        </VendedorLayout>
    );
}
