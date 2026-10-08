import { useForm } from '@inertiajs/react';
import { BotonFoto } from '@/components/captura-evidencia';
import { toast } from 'sonner';

type Estado = 'conforme' | 'observado' | 'no_aplica';

interface Item {
    estado: Estado;
    condicion: string;
    requiere_autorizacion: boolean;
    foto: File | null;
}

interface Props {
    /** Clave y nombre de cada elemento (los mismos 14 del taller). */
    elementos: Record<string, string>;
    /** Dirección a la que se envía el checklist de este extintor. */
    url: string;
    serie: string;
    esCo2: boolean;
    onCerrar: () => void;
}

const ETIQUETAS: Record<Estado, string> = {
    conforme: 'Conforme',
    observado: 'Observado',
    no_aplica: 'No aplica',
};

/** Checklist por extintor, con foto en cada ítem observado. */
export default function ChecklistExtintor({
    elementos,
    url,
    serie,
    esCo2,
    onCerrar,
}: Props) {
    const inicial = Object.keys(elementos).reduce<Record<string, Item>>(
        (acc, clave) => {
            acc[clave] = {
                estado:
                    esCo2 && clave === 'manometro' ? 'no_aplica' : 'conforme',
                condicion: '',
                requiere_autorizacion: false,
                foto: null,
            };

            return acc;
        },
        {},
    );

    const form = useForm<{
        items: Record<string, Item>;
        observaciones: string;
    }>({
        items: inicial,
        observaciones: '',
    });

    const cambiar = (clave: string, cambios: Partial<Item>) =>
        form.setData('items', {
            ...form.data.items,
            [clave]: { ...form.data.items[clave], ...cambios },
        });

    const sinFoto = Object.values(form.data.items).some(
        (i) => i.estado === 'observado' && !i.foto,
    );

    const enviar = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(url, {
            onError: (errors) =>
                toast.error(
                    Object.values(errors)[0] ??
                        'No se pudo completar la acción.',
                ),
            forceFormData: true,
            preserveScroll: true,
            onSuccess: onCerrar,
        });
    };

    return (
        <form
            onSubmit={enviar}
            className="mt-3 space-y-2.5 rounded-xl border border-sky-500/30 bg-sky-500/5 p-3"
        >
            <div className="flex items-center justify-between">
                <span className="text-xs font-black">Checklist de {serie}</span>
                <button
                    type="button"
                    onClick={onCerrar}
                    className="min-h-[44px] px-2 text-[11px] font-bold"
                >
                    Cerrar
                </button>
            </div>

            {Object.entries(elementos).map(([clave, nombre]) => {
                const item = form.data.items[clave];

                return (
                    <fieldset
                        key={clave}
                        className="space-y-1.5 rounded-lg border border-neutral-200 p-2 dark:border-neutral-700"
                    >
                        <legend className="px-1 text-[11px] font-bold">
                            {nombre}
                        </legend>
                        <div className="grid grid-cols-3 gap-1">
                            {(Object.keys(ETIQUETAS) as Estado[]).map(
                                (estado) => (
                                    <button
                                        key={estado}
                                        type="button"
                                        aria-pressed={item.estado === estado}
                                        onClick={() =>
                                            cambiar(clave, { estado })
                                        }
                                        className={`min-h-[44px] rounded-lg text-[11px] font-bold ${
                                            item.estado === estado
                                                ? estado === 'observado'
                                                    ? 'bg-amber-600 text-white'
                                                    : estado === 'conforme'
                                                      ? 'bg-emerald-600 text-white'
                                                      : 'bg-neutral-600 text-white'
                                                : 'bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200'
                                        }`}
                                    >
                                        {ETIQUETAS[estado]}
                                    </button>
                                ),
                            )}
                        </div>
                        {item.estado === 'observado' && (
                            <div className="space-y-1.5">
                                <input
                                    required
                                    aria-label={`Qué le pasa a: ${nombre}`}
                                    value={item.condicion}
                                    onChange={(e) =>
                                        cambiar(clave, {
                                            condicion: e.target.value,
                                        })
                                    }
                                    placeholder="¿Qué le pasa?"
                                    className="min-h-[44px] w-full rounded-lg border border-neutral-300 bg-white px-2 text-xs dark:border-neutral-600 dark:bg-neutral-900"
                                />
                                <label className="flex min-h-[44px] items-center gap-2 text-[11px] font-semibold">
                                    <input
                                        type="checkbox"
                                        checked={item.requiere_autorizacion}
                                        onChange={(e) =>
                                            cambiar(clave, {
                                                requiere_autorizacion:
                                                    e.target.checked,
                                            })
                                        }
                                        className="h-4 w-4"
                                    />
                                    Necesita la autorización del cliente
                                </label>
                                <div className="flex flex-wrap items-center gap-2">
                                    <BotonFoto
                                        archivo={item.foto}
                                        etiqueta="Tomar foto *"
                                        onFoto={(f) =>
                                            cambiar(clave, { foto: f })
                                        }
                                    />
                                    <span className="text-[11px]">
                                        {item.foto
                                            ? item.foto.name
                                            : 'La foto es obligatoria.'}
                                    </span>
                                </div>
                            </div>
                        )}
                    </fieldset>
                );
            })}

            <label className="block text-[11px] font-semibold">
                Observaciones
                <textarea
                    value={form.data.observaciones}
                    onChange={(e) =>
                        form.setData('observaciones', e.target.value)
                    }
                    rows={2}
                    className="mt-1 w-full rounded-lg border border-neutral-300 bg-white p-2 text-xs dark:border-neutral-600 dark:bg-neutral-900"
                />
            </label>
            {Object.values(form.errors)[0] && (
                <p
                    className="text-[11px] font-semibold text-red-600"
                    role="alert"
                >
                    {Object.values(form.errors)[0]}
                </p>
            )}
            <button
                type="submit"
                disabled={form.processing || sinFoto}
                className="min-h-[48px] w-full rounded-xl bg-sky-600 text-xs font-bold text-white disabled:opacity-50"
            >
                Guardar checklist
            </button>
        </form>
    );
}
