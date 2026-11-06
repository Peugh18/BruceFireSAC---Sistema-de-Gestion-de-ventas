import { usePage } from '@inertiajs/react';
import { Download, Upload } from 'lucide-react';

import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { show as verEvidencia } from '@/routes/evidencias';
import type { Team } from '@/types';

export type Tramo = {
    fecha: string | null;
    tecnico: string | null;
    lugar: string | null;
    persona: string | null;
    cantidad: number | null;
    fotos: number[];
    firma: number | null;
} | null;

export type EntradaSalidaProps = { entrada: Tramo; salida: Tramo };

function Tarjeta({
    titulo,
    tramo,
    persona,
    tono,
    Icono,
}: {
    titulo: string;
    tramo: Tramo;
    persona: string;
    tono: string;
    Icono: typeof Download;
}) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const equipo = currentTeam?.slug ?? '';
    const url = (id: number) =>
        verEvidencia.url({ current_team: equipo, evidencia: id });

    return (
        <section
            aria-label={titulo}
            className="border-border bg-card min-w-0 flex-[1_1_320px] space-y-4 rounded-3xl border p-5"
        >
            <div className="flex items-center gap-2.5">
                <span
                    className={`flex size-10 items-center justify-center rounded-2xl ${tono}`}
                >
                    <Icono className="size-5" aria-hidden="true" />
                </span>
                <span>
                    <span className="block font-bold">{titulo}</span>
                    <span className="text-muted-foreground block text-xs">
                        {tramo?.fecha ?? 'Todavía no'}
                    </span>
                </span>
            </div>
            {tramo ? (
                <>
                    {tramo.fotos.length > 0 ? (
                        <div className="grid grid-cols-4 gap-2">
                            {tramo.fotos.map((id, i) => (
                                <Dialog key={id}>
                                    <DialogTrigger asChild>
                                        <button
                                            type="button"
                                            aria-label={`Ver foto ${i + 1} de ${titulo.toLowerCase()}`}
                                            className="focus-visible:ring-ring aspect-square overflow-hidden rounded-xl focus-visible:ring-2 focus-visible:outline-none"
                                        >
                                            <img
                                                src={url(id)}
                                                alt=""
                                                className="size-full object-cover"
                                            />
                                        </button>
                                    </DialogTrigger>
                                    <DialogContent className="max-h-[90dvh] overflow-y-auto">
                                        <DialogTitle>{titulo}</DialogTitle>
                                        <DialogDescription>
                                            {tramo.fecha}
                                        </DialogDescription>
                                        <img
                                            src={url(id)}
                                            alt={`Foto ${i + 1} de ${titulo.toLowerCase()}`}
                                            className="max-h-[70dvh] w-full object-contain"
                                        />
                                    </DialogContent>
                                </Dialog>
                            ))}
                        </div>
                    ) : (
                        <p className="text-muted-foreground text-sm">
                            Sin fotos.
                        </p>
                    )}
                    <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                        <div>
                            <dt className="text-muted-foreground text-xs">
                                Técnico
                            </dt>
                            <dd className="font-semibold">
                                {tramo.tecnico ?? '—'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground text-xs">
                                Lugar
                            </dt>
                            <dd className="font-semibold">
                                {tramo.lugar ?? '—'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground text-xs">
                                {persona}
                            </dt>
                            <dd className="font-semibold">
                                {tramo.persona ?? '—'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground text-xs">
                                Extintores
                            </dt>
                            <dd className="font-semibold">
                                {tramo.cantidad ?? '—'}
                            </dd>
                        </div>
                    </dl>
                    {tramo.firma ? (
                        <img
                            src={url(tramo.firma)}
                            alt={`Firma de ${tramo.persona ?? 'quien firmó'}`}
                            className="border-border h-14 border-t pt-3 dark:invert"
                        />
                    ) : null}
                </>
            ) : null}
        </section>
    );
}

/** Fotos y firma de cuando entraron y salieron los extintores. */
export default function EntradaSalida({ entrada, salida }: EntradaSalidaProps) {
    if (!entrada && !salida) {
        return null;
    }

    return (
        <div className="flex flex-wrap gap-4">
            <Tarjeta
                titulo="Entrada · recojo"
                tramo={entrada}
                persona="Entregó"
                tono="bg-primary/10 text-primary-strong"
                Icono={Download}
            />
            <Tarjeta
                titulo="Salida · entrega"
                tramo={salida}
                persona="Recibió"
                tono="bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
                Icono={Upload}
            />
        </div>
    );
}
