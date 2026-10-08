import { useForm, usePage } from '@inertiajs/react';
import { Paperclip, Send } from 'lucide-react';
import { BotonFoto, GrabadoraAudio } from '@/components/captura-evidencia';
import { show as verEvidencia } from '@/routes/evidencias';
import { store as enviarMensaje } from '@/routes/ordenes/mensajes';
import type { Team } from '@/types';
import { toast } from 'sonner';

export interface EventoConversacion {
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
}

const ORIGENES: Record<string, string> = {
    vendedor: 'Ventas',
    planta: 'Planta',
    campo: 'Campo',
    gerente: 'Gerencia',
};

function hora(fecha: string | null): string {
    return fecha
        ? new Date(fecha).toLocaleString('es-PE', {
              day: '2-digit',
              month: 'short',
              hour: '2-digit',
              minute: '2-digit',
          })
        : '';
}

/**
 * Conversación de la orden: los mensajes de ventas, planta, campo y gerencia
 * junto con los eventos del sistema, en una sola línea de tiempo.
 */
export default function ConversacionOrden({
    ordenId,
    conversacion,
}: {
    ordenId: number;
    conversacion: ConversacionProps;
}) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const equipo = currentTeam?.slug ?? '';
    const form = useForm<{
        mensaje: string;
        equipment_id: string;
        archivo: File | null;
    }>({ mensaje: '', equipment_id: '', archivo: null });

    const enviar = (e: React.FormEvent) => {
        e.preventDefault();
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

    return (
        <section
            aria-label="Conversación de la orden"
            className="bg-card space-y-3 rounded-2xl border border-neutral-200 p-3.5 dark:border-neutral-700"
        >
            <h2 className="text-sm font-bold text-neutral-900 dark:text-neutral-100">
                Conversación de la orden
            </h2>

            <ol className="max-h-96 space-y-2 overflow-y-auto">
                {conversacion.eventos.length === 0 && (
                    <li className="text-xs text-neutral-500">
                        Todavía no hay mensajes en esta orden.
                    </li>
                )}
                {conversacion.eventos.map((evento) => (
                    <li
                        key={evento.id}
                        className={`rounded-xl p-2.5 text-xs ${
                            evento.es_sistema
                                ? 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300'
                                : 'border border-amber-200 bg-amber-50 text-neutral-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-neutral-100'
                        }`}
                    >
                        <div className="flex flex-wrap items-center justify-between gap-1 text-[11px] font-semibold">
                            <span>
                                {evento.es_sistema
                                    ? evento.titulo
                                    : `${evento.autor} · ${ORIGENES[evento.origen] ?? ''}`}
                            </span>
                            <span className="font-normal text-neutral-700 dark:text-neutral-300">
                                {hora(evento.fecha)}
                            </span>
                        </div>
                        {evento.equipo && (
                            <span className="mt-1 inline-block rounded bg-neutral-200 px-1.5 py-0.5 font-mono text-[10px] dark:bg-neutral-700">
                                {evento.equipo}
                            </span>
                        )}
                        {evento.mensaje && (
                            <p className="mt-1 whitespace-pre-line">
                                {evento.mensaje}
                            </p>
                        )}
                        {evento.adjuntos.map((adjunto) => {
                            const url = verEvidencia.url({
                                current_team: equipo,
                                evidencia: adjunto.id,
                            });

                            if (adjunto.tipo === 'foto') {
                                return (
                                    <a
                                        key={adjunto.id}
                                        href={url}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <img
                                            src={url}
                                            alt={
                                                adjunto.nombre ?? 'Foto adjunta'
                                            }
                                            className="mt-1.5 max-h-48 rounded-lg"
                                        />
                                    </a>
                                );
                            }

                            if (adjunto.tipo === 'audio') {
                                return (
                                    <audio
                                        key={adjunto.id}
                                        src={url}
                                        controls
                                        className="mt-1.5 w-full"
                                    />
                                );
                            }

                            return (
                                <a
                                    key={adjunto.id}
                                    href={url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="mt-1.5 inline-flex items-center gap-1 font-semibold underline"
                                >
                                    <Paperclip className="h-3.5 w-3.5" />
                                    {adjunto.nombre ?? 'Archivo adjunto'}
                                </a>
                            );
                        })}
                    </li>
                ))}
            </ol>

            <form onSubmit={enviar} className="space-y-2">
                <label htmlFor={`mensaje-${ordenId}`} className="sr-only">
                    Mensaje
                </label>
                <textarea
                    aria-label="Mensaje"
                    id={`mensaje-${ordenId}`}
                    value={form.data.mensaje}
                    onChange={(e) => form.setData('mensaje', e.target.value)}
                    maxLength={500}
                    rows={2}
                    placeholder="Escribe un mensaje para el equipo..."
                    className="w-full rounded-xl border border-neutral-300 bg-white p-2.5 text-sm dark:border-neutral-600 dark:bg-neutral-900"
                />
                <div className="flex flex-wrap items-center gap-2">
                    {conversacion.equipos.length > 0 && (
                        <>
                            <label
                                htmlFor={`equipo-${ordenId}`}
                                className="sr-only"
                            >
                                Extintor del que hablas
                            </label>
                            <select
                                aria-label="Extintor del que hablas"
                                id={`equipo-${ordenId}`}
                                value={form.data.equipment_id}
                                onChange={(e) =>
                                    form.setData('equipment_id', e.target.value)
                                }
                                className="min-h-[44px] rounded-xl border border-neutral-300 bg-white px-2 text-xs dark:border-neutral-600 dark:bg-neutral-900"
                            >
                                <option value="">Sin extintor</option>
                                {conversacion.equipos.map((e) => (
                                    <option key={e.id} value={e.id}>
                                        {e.serie}
                                    </option>
                                ))}
                            </select>
                        </>
                    )}
                    <BotonFoto
                        archivo={form.data.archivo}
                        onFoto={(f) => f && form.setData('archivo', f)}
                    />
                    <GrabadoraAudio
                        archivo={form.data.archivo}
                        onAudio={(f) => f && form.setData('archivo', f)}
                    />
                    <label className="inline-flex min-h-[44px] cursor-pointer items-center gap-1.5 rounded-xl border border-neutral-300 px-3 text-xs font-bold text-neutral-800 dark:border-neutral-600 dark:text-neutral-100">
                        <Paperclip className="h-4 w-4" />
                        Adjuntar archivo
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
                    <button
                        type="submit"
                        disabled={
                            form.processing ||
                            (!form.data.mensaje.trim() && !form.data.archivo)
                        }
                        className="ml-auto inline-flex min-h-[44px] items-center gap-1.5 rounded-xl bg-amber-600 px-4 text-xs font-bold text-white disabled:opacity-50"
                    >
                        <Send className="h-4 w-4" />
                        Enviar
                    </button>
                </div>
                {form.data.archivo && (
                    <p className="text-[11px] text-neutral-600 dark:text-neutral-300">
                        Adjunto: {form.data.archivo.name}
                    </p>
                )}
                {(form.errors.mensaje || form.errors.archivo) && (
                    <p
                        className="text-[11px] font-semibold text-red-600"
                        role="alert"
                    >
                        {form.errors.mensaje ?? form.errors.archivo}
                    </p>
                )}
            </form>
        </section>
    );
}
