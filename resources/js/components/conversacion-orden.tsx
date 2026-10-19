import { useForm, usePage } from '@inertiajs/react';
import { Check, Paperclip, Send, X } from 'lucide-react';
import { Fragment, useEffect, useRef } from 'react';
import { BotonFoto, GrabadoraAudio } from '@/components/captura-evidencia';
import { show as verEvidencia } from '@/routes/evidencias';
import { store as enviarMensaje } from '@/routes/ordenes/mensajes';
import type { Team } from '@/types';
import { toast } from 'sonner';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

interface EventoConversacion {
    id: number;
    titulo: string;
    mensaje: string | null;
    autor: string;
    origen: string;
    es_sistema: boolean;
    equipo: string | null;
    fecha: string | null;
    adjuntos: { id: number; tipo: string; nombre: string | null }[];
}

export interface ConversacionProps {
    eventos: EventoConversacion[];
    equipos: { id: number; serie: string }[];
    no_leidos?: number;
}

const ORIGENES: Record<string, string> = {
    vendedor: 'Ventas',
    planta: 'Planta',
    campo: 'Campo',
    gerente: 'Gerencia',
};

const soloHora = (fecha: string | null) =>
    fecha
        ? new Date(fecha).toLocaleTimeString('es-PE', {
              hour: '2-digit',
              minute: '2-digit',
          })
        : '';

/** "HOY", "AYER" o la fecha, como los separadores de WhatsApp. */
function dia(fecha: string | null): string {
    if (!fecha) {
        return '';
    }

    const d = new Date(fecha);
    const hoy = new Date();
    const ayer = new Date();
    ayer.setDate(hoy.getDate() - 1);

    if (d.toDateString() === hoy.toDateString()) {
        return 'HOY';
    }

    if (d.toDateString() === ayer.toDateString()) {
        return 'AYER';
    }

    return d
        .toLocaleDateString('es-PE', {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
        })
        .toUpperCase();
}

/** Un adjunto del mensaje: foto, audio o archivo. */
function Adjunto({
    adjunto,
    equipo,
}: {
    adjunto: EventoConversacion['adjuntos'][number];
    equipo: string;
}) {
    const url = verEvidencia.url({
        current_team: equipo,
        evidencia: adjunto.id,
    });

    if (adjunto.tipo === 'foto') {
        return (
            <Dialog>
                <DialogTrigger asChild>
                    <button
                        type="button"
                        aria-label={`Abrir foto: ${adjunto.nombre ?? 'Foto adjunta'}`}
                        className="focus-visible:ring-ring block w-full rounded-md focus-visible:ring-2 focus-visible:outline-none"
                    >
                        <img
                            src={url}
                            alt={adjunto.nombre ?? 'Foto adjunta'}
                            className="max-h-60 w-full rounded-md object-cover"
                        />
                    </button>
                </DialogTrigger>
                <DialogContent className="max-h-[95dvh] w-[calc(100vw-2rem)] max-w-5xl overflow-y-auto border-0 bg-zinc-950 p-4 text-white sm:max-w-5xl">
                    <DialogTitle className="pr-10 text-sm">
                        {adjunto.nombre ?? 'Foto adjunta'}
                    </DialogTitle>
                    <DialogDescription className="sr-only">
                        Vista ampliada de la foto de la conversación. Cierra
                        para volver al chat.
                    </DialogDescription>
                    <img
                        src={url}
                        alt={adjunto.nombre ?? 'Foto adjunta'}
                        className="max-h-[75dvh] w-full object-contain"
                    />
                    <a
                        href={url}
                        download
                        className="focus-visible:ring-ring w-fit rounded-lg px-3 py-2 text-sm font-semibold underline focus-visible:ring-2 focus-visible:outline-none"
                    >
                        Descargar foto
                    </a>
                </DialogContent>
            </Dialog>
        );
    }

    if (adjunto.tipo === 'audio') {
        return <audio src={url} controls className="h-10 w-60 max-w-full" />;
    }

    return (
        <a
            href={url}
            target="_blank"
            rel="noreferrer"
            className="inline-flex items-center gap-1 font-semibold underline"
        >
            <Paperclip className="h-3.5 w-3.5" />
            {adjunto.nombre ?? 'Archivo adjunto'}
        </a>
    );
}

/**
 * Conversación de la orden con el aspecto de WhatsApp: los mensajes de
 * ventas, planta, campo y gerencia, y los avisos del sistema como notas al
 * centro. Con `panel` ocupa todo el alto que le den: los mensajes se
 * desplazan solos y la caja para escribir queda siempre abajo.
 */
export default function ConversacionOrden({
    ordenId,
    conversacion,
    nota,
    panel = false,
}: {
    ordenId: number;
    conversacion: ConversacionProps;
    /** Indicaciones del vendedor: van fijas arriba de la conversación. */
    nota?: string | null;
    panel?: boolean;
}) {
    const { currentTeam, auth } = usePage<{
        currentTeam?: Team | null;
        auth?: { user?: { name?: string } };
    }>().props;
    const equipo = currentTeam?.slug ?? '';
    const yo = auth?.user?.name;
    const lista = useRef<HTMLOListElement>(null);
    const form = useForm<{
        mensaje: string;
        archivo: File | null;
    }>({ mensaje: '', archivo: null });

    // Lo último siempre a la vista, como en cualquier chat.
    useEffect(() => {
        lista.current?.scrollTo({ top: lista.current.scrollHeight });
    }, [conversacion.eventos.length]);

    const enviar = (e?: React.FormEvent) => {
        e?.preventDefault();

        if (!form.data.mensaje.trim() && !form.data.archivo) {
            return;
        }

        form.post(
            enviarMensaje.url({ current_team: equipo, service_order: ordenId }),
            {
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ??
                            'No se pudo completar la acción.',
                    ),
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => form.reset(),
            },
        );
    };

    const hayTexto = form.data.mensaje.trim() !== '' || !!form.data.archivo;
    const icono =
        'flex size-11 shrink-0 items-center justify-center rounded-full text-[#54656f] hover:bg-black/5 dark:text-[#aebac1] dark:hover:bg-white/10';

    return (
        <section
            aria-label="Conversación de la orden"
            className={`flex flex-col overflow-hidden rounded-2xl border border-[#d1d7db] dark:border-[#222d34] ${
                panel ? 'h-full min-h-0' : ''
            }`}
        >
            <ol
                ref={lista}
                aria-label="Mensajes"
                className={`flex flex-col gap-1 overflow-y-auto overscroll-contain bg-[#efeae2] px-3 py-3 dark:bg-[#0b141a] ${
                    panel ? 'min-h-0 flex-1' : 'max-h-96'
                }`}
            >
                {nota ? (
                    <li className="mx-auto my-1 max-w-[90%] rounded-lg bg-[#fff5c4] px-3 py-1.5 text-center text-xs text-[#54656f] shadow-sm dark:bg-[#3b3524] dark:text-[#e9d9a4]">
                        <span className="font-bold">Indicaciones: </span>
                        {nota}
                    </li>
                ) : null}
                {conversacion.eventos.length === 0 && (
                    <li className="mx-auto my-4 rounded-lg bg-white/90 px-3 py-1.5 text-xs text-[#54656f] dark:bg-[#182229] dark:text-[#8696a0]">
                        Todavía no hay mensajes. Escribe al equipo desde aquí.
                    </li>
                )}
                {conversacion.eventos.map((evento, i) => {
                    const separador =
                        i === 0 ||
                        dia(evento.fecha) !==
                            dia(conversacion.eventos[i - 1].fecha);
                    const mio = !evento.es_sistema && evento.autor === yo;

                    return (
                        <Fragment key={evento.id}>
                            {separador && evento.fecha ? (
                                <li className="mx-auto my-2 rounded-lg bg-white px-3 py-1 text-xs font-medium text-[#54656f] shadow-sm dark:bg-[#182229] dark:text-[#8696a0]">
                                    {dia(evento.fecha)}
                                </li>
                            ) : null}
                            {evento.es_sistema ? (
                                <li className="mx-auto my-0.5 max-w-[90%] rounded-lg bg-white/90 px-3 py-1 text-center text-xs text-[#54656f] shadow-sm dark:bg-[#182229] dark:text-[#8696a0]">
                                    {soloHora(evento.fecha)} · {evento.titulo}
                                    {evento.mensaje
                                        ? ` — ${evento.mensaje}`
                                        : ''}
                                    {evento.equipo ? ` · ${evento.equipo}` : ''}
                                </li>
                            ) : (
                                <li
                                    className={`my-0.5 max-w-[80%] space-y-1 rounded-lg px-2 pt-1.5 pb-1 text-[14.5px] text-[#111b21] shadow-sm dark:text-[#e9edef] ${
                                        mio
                                            ? 'self-end rounded-tr-none bg-[#d9fdd3] dark:bg-[#005c4b]'
                                            : 'self-start rounded-tl-none bg-white dark:bg-[#202c33]'
                                    }`}
                                >
                                    {!mio && (
                                        <p className="text-[13px] font-bold text-[#b91c1c] dark:text-[#ff8a8a]">
                                            {evento.autor} ·{' '}
                                            {ORIGENES[evento.origen] ?? ''}
                                        </p>
                                    )}
                                    {evento.equipo && (
                                        <span className="inline-block rounded bg-black/5 px-1.5 py-0.5 font-mono text-xs dark:bg-white/10">
                                            {evento.equipo}
                                        </span>
                                    )}
                                    {evento.adjuntos.map((adjunto) => (
                                        <Adjunto
                                            key={adjunto.id}
                                            adjunto={adjunto}
                                            equipo={equipo}
                                        />
                                    ))}
                                    {evento.mensaje && (
                                        <p className="whitespace-pre-line">
                                            {evento.mensaje}
                                        </p>
                                    )}
                                    <p className="flex items-center justify-end gap-1 text-[11px] text-[#667781] dark:text-[#8696a0]">
                                        {soloHora(evento.fecha)}
                                        {mio ? (
                                            <Check
                                                className="size-3.5"
                                                aria-label="enviado"
                                            />
                                        ) : null}
                                    </p>
                                </li>
                            )}
                        </Fragment>
                    );
                })}
            </ol>

            <form
                onSubmit={enviar}
                className="space-y-1.5 bg-[#f0f2f5] px-2 py-2 dark:bg-[#202c33]"
            >
                {form.data.archivo && (
                    <p className="flex items-center gap-2 px-2 text-xs text-[#54656f] dark:text-[#aebac1]">
                        <Paperclip className="size-3.5" />
                        <span className="min-w-0 flex-1 truncate">
                            {form.data.archivo.name}
                        </span>
                        <button
                            type="button"
                            aria-label="Quitar el adjunto"
                            onClick={() => form.setData('archivo', null)}
                            className="flex size-8 items-center justify-center rounded-full hover:bg-black/5"
                        >
                            <X className="size-4" />
                        </button>
                    </p>
                )}
                <div className="flex items-end gap-1">
                    <label className={`${icono} cursor-pointer`}>
                        <Paperclip className="size-5" />
                        <span className="sr-only">Adjuntar archivo</span>
                        <input
                            aria-label="Archivo"
                            type="file"
                            className="sr-only"
                            onChange={(e) =>
                                form.setData(
                                    'archivo',
                                    e.target.files?.[0] ?? null,
                                )
                            }
                        />
                    </label>
                    <BotonFoto
                        compacto
                        archivo={form.data.archivo}
                        onFoto={(f) => f && form.setData('archivo', f)}
                    />
                    <label htmlFor={`mensaje-${ordenId}`} className="sr-only">
                        Mensaje
                    </label>
                    <textarea
                        aria-label="Mensaje"
                        id={`mensaje-${ordenId}`}
                        value={form.data.mensaje}
                        onChange={(e) =>
                            form.setData('mensaje', e.target.value)
                        }
                        onKeyDown={(e) => {
                            if (e.key === 'Enter' && !e.shiftKey) {
                                e.preventDefault();
                                enviar();
                            }
                        }}
                        maxLength={500}
                        rows={1}
                        placeholder="Escribe un mensaje"
                        className="min-h-11 min-w-0 flex-1 resize-none rounded-lg border-0 bg-white px-3 py-2.5 text-[15px] text-[#111b21] outline-none placeholder:text-[#667781] dark:bg-[#2a3942] dark:text-[#e9edef] dark:placeholder:text-[#8696a0]"
                    />
                    {hayTexto ? (
                        <button
                            type="submit"
                            aria-label="Enviar"
                            disabled={form.processing}
                            className="flex size-11 shrink-0 items-center justify-center rounded-full bg-[#00a884] text-white disabled:opacity-50"
                        >
                            <Send className="size-5" />
                        </button>
                    ) : (
                        <GrabadoraAudio
                            compacto
                            archivo={form.data.archivo}
                            onAudio={(f) => f && form.setData('archivo', f)}
                        />
                    )}
                </div>
                {(form.errors.mensaje || form.errors.archivo) && (
                    <p
                        className="text-destructive-strong px-2 text-xs font-semibold"
                        role="alert"
                    >
                        {form.errors.mensaje ?? form.errors.archivo}
                    </p>
                )}
            </form>
        </section>
    );
}
