import { Link, router, usePage } from '@inertiajs/react';
import { AlertTriangle, Check, ExternalLink, Wrench, X } from 'lucide-react';

import { TarjetaCargando } from '@/components/cargando';
import ConversacionOrden, {
    type ConversacionProps,
} from '@/components/conversacion-orden';
import { PageHeader } from '@/components/page-header';
import { ServiciosTabs } from '@/components/servicios-tabs';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { useRecargando } from '@/hooks/use-recargando';
import VendedorLayout from '@/layouts/vendedor-layout';
import comunicacion from '@/routes/vendedor/comunicacion';
import deficiencias from '@/routes/vendedor/deficiencias';
import ordenesServicio from '@/routes/vendedor/ordenes-servicio';
import type { Team } from '@/types';

type Tarjeta = {
    id: number;
    codigo: string;
    cliente: string;
    servicio: string;
    tecnico: string | null;
    area: string;
    prioridad: string;
    dias: number;
    por_autorizar: boolean;
};

type Detalle = Tarjeta & {
    estado: string;
    referencia: string | null;
    observaciones: string | null;
    etapas: string[];
    etapa_actual: number;
    conversacion: ConversacionProps;
};

type Props = {
    columnas: { clave: string; titulo: string; ordenes: Tarjeta[] }[];
    seleccionada: Detalle | null;
};

/**
 * Seguimiento de taller: tablero de las órdenes de la sede por etapa y, al
 * elegir una, su avance, historial y notas para el técnico.
 */
export default function SeguimientoTaller({ columnas, seleccionada }: Props) {
    const { currentTeam, sidebarCounts } = usePage<{
        currentTeam?: Team | null;
        sidebarCounts?: { deficiencias?: number } | null;
    }>().props;
    const teamSlug = currentTeam?.slug ?? '';
    const recargando = useRecargando();

    const abrir = (id: number | null) =>
        router.get(
            comunicacion.index.url(teamSlug, {
                query: { orden: id ?? undefined },
            }),
            {},
            { preserveScroll: true, preserveState: true },
        );

    const total = columnas.reduce((s, c) => s + c.ordenes.length, 0);

    return (
        <VendedorLayout title="Servicios">
            <div className="flex flex-col gap-4">
                <ServiciosTabs
                    teamSlug={teamSlug}
                    activa="taller"
                    deficienciasPendientes={sidebarCounts?.deficiencias}
                />
                <PageHeader
                    title="Seguimiento de taller"
                    description={`${total} ${total === 1 ? 'orden' : 'órdenes'} de tu sede. Toca una para ver su avance y dejar una nota al técnico.`}
                />

                <div className="grid [grid-template-columns:repeat(6,minmax(200px,1fr))] gap-3 overflow-x-auto pb-1">
                    {columnas.map((columna) => (
                        <div
                            key={columna.clave}
                            className="bg-muted/40 flex min-h-[140px] flex-col gap-2 rounded-[14px] p-2.5"
                        >
                            <div className="flex items-center justify-between px-1">
                                <span className="text-foreground/80 text-[11.5px] font-bold uppercase">
                                    {columna.titulo}
                                </span>
                                <span className="bg-card rounded-full px-2 text-[11px] font-bold">
                                    {columna.ordenes.length}
                                </span>
                            </div>
                            {columna.ordenes.length === 0 ? (
                                <p className="text-muted-foreground px-1 text-[11.5px]">
                                    Sin órdenes
                                </p>
                            ) : null}
                            {columna.ordenes.map((orden) => (
                                <button
                                    key={orden.id}
                                    type="button"
                                    onClick={() => abrir(orden.id)}
                                    className={`bg-card hover:border-primary/60 rounded-[10px] border p-2.5 text-left transition-colors ${seleccionada?.id === orden.id ? 'border-primary' : 'border-border'}`}
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <span className="font-['IBM_Plex_Mono',monospace] text-[11px] font-bold">
                                            {orden.codigo}
                                        </span>
                                        {orden.por_autorizar ? (
                                            <AlertTriangle className="size-3.5 text-amber-500" />
                                        ) : null}
                                    </div>
                                    <div className="text-foreground mt-0.5 truncate text-[12.5px] font-bold">
                                        {orden.cliente}
                                    </div>
                                    <div className="text-muted-foreground truncate text-[11.5px]">
                                        {orden.servicio}
                                    </div>
                                    <div className="mt-1 flex flex-wrap gap-1 text-[10.5px]">
                                        <span className="bg-muted rounded px-1.5 py-0.5 capitalize">
                                            {orden.area}
                                        </span>
                                        {orden.prioridad !== 'normal' ? (
                                            <span className="bg-destructive/10 text-destructive-strong rounded px-1.5 py-0.5 capitalize">
                                                {orden.prioridad}
                                            </span>
                                        ) : null}
                                        <span className="text-muted-foreground">
                                            {orden.dias === 0
                                                ? 'hoy'
                                                : `hace ${orden.dias} d`}
                                        </span>
                                    </div>
                                </button>
                            ))}
                        </div>
                    ))}
                </div>

                {recargando ? (
                    <TarjetaCargando />
                ) : seleccionada ? (
                    <Card className="border-border bg-card gap-4 rounded-[16px] p-5 shadow-none">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div className="flex items-center gap-2">
                                    <Wrench className="text-primary-strong size-4" />
                                    <span className="font-['IBM_Plex_Mono',monospace] text-[13px] font-bold">
                                        {seleccionada.codigo}
                                    </span>
                                </div>
                                <h2 className="text-foreground text-[16px] font-bold">
                                    {seleccionada.cliente}
                                </h2>
                                <p className="text-muted-foreground text-[12.5px]">
                                    {seleccionada.servicio}
                                    {seleccionada.referencia
                                        ? ` · ${seleccionada.referencia}`
                                        : ''}
                                    {' · '}
                                    {seleccionada.tecnico ??
                                        `Todo el área de ${seleccionada.area}`}
                                </p>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                {seleccionada.por_autorizar ? (
                                    <Button
                                        asChild
                                        className="h-9 rounded-[9px] bg-amber-500 text-white shadow-none hover:bg-amber-600"
                                    >
                                        <Link
                                            href={deficiencias.index.url(
                                                teamSlug,
                                            )}
                                        >
                                            <AlertTriangle className="size-4" />
                                            Autorizar deficiencias
                                        </Link>
                                    </Button>
                                ) : null}
                                <Button
                                    asChild
                                    variant="outline"
                                    className="h-9 rounded-[9px] shadow-none"
                                >
                                    <Link
                                        href={ordenesServicio.show.url({
                                            current_team: teamSlug,
                                            service_order: seleccionada.id,
                                        })}
                                    >
                                        <ExternalLink className="size-4" />
                                        Ver orden
                                    </Link>
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => abrir(null)}
                                    className="h-9 rounded-[9px] shadow-none"
                                >
                                    <X className="size-4" />
                                </Button>
                            </div>
                        </div>

                        <div className="flex items-center gap-1 overflow-x-auto">
                            {seleccionada.etapas.map((etapa, i) => {
                                const hecha = i < seleccionada.etapa_actual;
                                const actual = i === seleccionada.etapa_actual;

                                return (
                                    <div
                                        key={etapa}
                                        className="flex min-w-[110px] flex-1 items-center gap-1"
                                    >
                                        <div
                                            className={`flex size-6 shrink-0 items-center justify-center rounded-full text-[11px] font-bold ${hecha ? 'bg-emerald-500 text-white' : actual ? 'bg-primary text-white' : 'bg-muted text-muted-foreground'}`}
                                        >
                                            {hecha ? (
                                                <Check className="size-3.5" />
                                            ) : (
                                                i + 1
                                            )}
                                        </div>
                                        <span
                                            className={`text-[11.5px] ${actual ? 'text-foreground font-bold' : 'text-muted-foreground'}`}
                                        >
                                            {etapa}
                                        </span>
                                        {i < seleccionada.etapas.length - 1 ? (
                                            <div
                                                className={`h-0.5 flex-1 ${hecha ? 'bg-emerald-500' : 'bg-border'}`}
                                            />
                                        ) : null}
                                    </div>
                                );
                            })}
                        </div>

                        {seleccionada.observaciones ? (
                            <p className="bg-muted/40 rounded-[10px] px-3 py-2 text-[12.5px]">
                                <b>Instrucciones:</b>{' '}
                                {seleccionada.observaciones}
                            </p>
                        ) : null}

                        <ConversacionOrden
                            ordenId={seleccionada.id}
                            conversacion={seleccionada.conversacion}
                        />
                    </Card>
                ) : null}
            </div>
        </VendedorLayout>
    );
}
