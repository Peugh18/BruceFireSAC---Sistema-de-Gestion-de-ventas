import { Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Check,
    ExternalLink,
    MessageSquarePlus,
    Send,
    Wrench,
    X,
} from 'lucide-react';
import { FormEvent, useState } from 'react';

import { TarjetaCargando } from '@/components/cargando';
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

type Evento = {
    id: number;
    titulo: string;
    mensaje: string | null;
    autor: string;
    de_ventas: boolean;
    fecha: string | null;
};

type Detalle = Tarjeta & {
    estado: string;
    referencia: string | null;
    observaciones: string | null;
    etapas: string[];
    etapa_actual: number;
    eventos: Evento[];
};

type Props = {
    columnas: { clave: string; titulo: string; ordenes: Tarjeta[] }[];
    seleccionada: Detalle | null;
};

function fecha(valor: string | null) {
    return valor
        ? new Intl.DateTimeFormat('es-PE', {
              day: '2-digit',
              month: '2-digit',
              hour: '2-digit',
              minute: '2-digit',
          }).format(new Date(valor))
        : '';
}

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
    const [nota, setNota] = useState('');
    const [enviando, setEnviando] = useState(false);
    const recargando = useRecargando();

    const abrir = (id: number | null) =>
        router.get(
            comunicacion.index.url(teamSlug, {
                query: { orden: id ?? undefined },
            }),
            {},
            { preserveScroll: true, preserveState: true },
        );

    const enviarNota = (event: FormEvent) => {
        event.preventDefault();
        if (!seleccionada || !nota.trim()) return;
        setEnviando(true);
        router.post(
            comunicacion.nota.url({
                current_team: teamSlug,
                service_order: seleccionada.id,
            }),
            { mensaje: nota.trim() },
            {
                preserveScroll: true,
                onSuccess: () => setNota(''),
                onFinish: () => setEnviando(false),
            },
        );
    };

    const total = columnas.reduce((s, c) => s + c.ordenes.length, 0);

    return (
        <VendedorLayout title="Seguimiento de taller">
            <div className="flex flex-col gap-4">
                <ServiciosTabs
                    teamSlug={teamSlug}
                    activa="taller"
                    deficienciasPendientes={sidebarCounts?.deficiencias}
                />
                <div>
                    <h1 className="font-['Oswald',sans-serif] text-[22px] font-semibold uppercase">
                        Seguimiento de taller
                    </h1>
                    <p className="text-[12.5px] text-muted-foreground">
                        {total} orden(es) de tu sede. Toca una para ver su
                        avance y dejar una nota al técnico.
                    </p>
                </div>

                <div className="grid gap-3 overflow-x-auto pb-1 [grid-template-columns:repeat(6,minmax(200px,1fr))]">
                    {columnas.map((columna) => (
                        <div
                            key={columna.clave}
                            className="flex min-h-[140px] flex-col gap-2 rounded-[14px] bg-muted/40 p-2.5"
                        >
                            <div className="flex items-center justify-between px-1">
                                <span className="text-[11.5px] font-bold text-foreground/80 uppercase">
                                    {columna.titulo}
                                </span>
                                <span className="rounded-full bg-card px-2 text-[11px] font-bold">
                                    {columna.ordenes.length}
                                </span>
                            </div>
                            {columna.ordenes.length === 0 ? (
                                <p className="px-1 text-[11.5px] text-muted-foreground">
                                    Sin órdenes
                                </p>
                            ) : null}
                            {columna.ordenes.map((orden) => (
                                <button
                                    key={orden.id}
                                    type="button"
                                    onClick={() => abrir(orden.id)}
                                    className={`rounded-[10px] border bg-card p-2.5 text-left transition-colors hover:border-primary/60 ${seleccionada?.id === orden.id ? 'border-primary' : 'border-border'}`}
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <span className="font-['IBM_Plex_Mono',monospace] text-[11px] font-bold">
                                            {orden.codigo}
                                        </span>
                                        {orden.por_autorizar ? (
                                            <AlertTriangle className="size-3.5 text-amber-500" />
                                        ) : null}
                                    </div>
                                    <div className="mt-0.5 truncate text-[12.5px] font-bold text-foreground">
                                        {orden.cliente}
                                    </div>
                                    <div className="truncate text-[11.5px] text-muted-foreground">
                                        {orden.servicio}
                                    </div>
                                    <div className="mt-1 flex flex-wrap gap-1 text-[10.5px]">
                                        <span className="rounded bg-muted px-1.5 py-0.5 capitalize">
                                            {orden.area}
                                        </span>
                                        {orden.prioridad !== 'normal' ? (
                                            <span className="rounded bg-destructive/10 px-1.5 py-0.5 text-destructive capitalize">
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
                    <Card className="gap-4 rounded-[16px] border-border bg-card p-5 shadow-none">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div className="flex items-center gap-2">
                                    <Wrench className="size-4 text-primary" />
                                    <span className="font-['IBM_Plex_Mono',monospace] text-[13px] font-bold">
                                        {seleccionada.codigo}
                                    </span>
                                </div>
                                <h2 className="text-[16px] font-bold text-foreground">
                                    {seleccionada.cliente}
                                </h2>
                                <p className="text-[12.5px] text-muted-foreground">
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
                                            className={`text-[11.5px] ${actual ? 'font-bold text-foreground' : 'text-muted-foreground'}`}
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
                            <p className="rounded-[10px] bg-muted/40 px-3 py-2 text-[12.5px]">
                                <b>Instrucciones:</b>{' '}
                                {seleccionada.observaciones}
                            </p>
                        ) : null}

                        <div className="space-y-2">
                            {seleccionada.eventos.map((evento) => (
                                <div
                                    key={evento.id}
                                    className={`rounded-[10px] border px-3 py-2 ${evento.de_ventas ? 'border-primary/30 bg-destructive/5' : 'border-border'}`}
                                >
                                    <div className="flex items-center justify-between gap-2 text-[11.5px]">
                                        <span className="font-bold text-foreground">
                                            {evento.titulo}
                                            <span className="font-normal text-muted-foreground">
                                                {' '}
                                                · {evento.autor}
                                            </span>
                                        </span>
                                        <span className="font-['IBM_Plex_Mono',monospace] text-muted-foreground">
                                            {fecha(evento.fecha)}
                                        </span>
                                    </div>
                                    {evento.mensaje ? (
                                        <p className="mt-0.5 text-[12.5px] text-foreground/90">
                                            {evento.mensaje}
                                        </p>
                                    ) : null}
                                </div>
                            ))}
                        </div>

                        <form onSubmit={enviarNota} className="flex gap-2">
                            <div className="flex min-w-0 flex-1 items-center gap-2 rounded-[9px] border border-border bg-muted/40 px-3">
                                <MessageSquarePlus className="size-4 shrink-0 text-muted-foreground" />
                                <input
                                    value={nota}
                                    maxLength={500}
                                    onChange={(e) => setNota(e.target.value)}
                                    placeholder="Nota para el técnico (ej. el cliente recoge el viernes)"
                                    className="h-10 min-w-0 flex-1 bg-transparent text-[13px] outline-none"
                                />
                            </div>
                            <Button
                                type="submit"
                                disabled={enviando || !nota.trim()}
                                className="h-10 rounded-[9px] bg-primary text-white shadow-none hover:bg-primary/90"
                            >
                                <Send className="size-4" />
                                Enviar
                            </Button>
                        </form>
                    </Card>
                ) : null}
            </div>
        </VendedorLayout>
    );
}
