import { useForm, usePage } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { useState } from 'react';
import { BotonFoto } from '@/components/captura-evidencia';
import { store as registrarDeficiencia } from '@/routes/tecnico-planta/deficiencias';
import type { Team } from '@/types';
import { toast } from 'sonner';
import { NativeSelect } from '@/components/ui/native-select';
import ExtintorPorPartes, {
    PARTES,
    tipoDeExtintor,
} from '@/components/extintor-por-partes';

interface Props {
    destino?: string;
    ordenId: number;
    equipos: {
        id: number;
        serie: string;
        tipo_agente?: string | null;
        capacidad?: string | null;
    }[];
}

/** T5: registra una deficiencia que no salió del checklist, con foto obligatoria. */
export default function DeficienciaSuelta({
    ordenId,
    equipos,
    destino,
}: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const [abierto, setAbierto] = useState(false);
    const form = useForm<{
        equipment_id: string;
        componente: string;
        condicion: string;
        nota: string;
        accion_recomendada: string;
        repuesto_sugerido: string;
        requiere_autorizacion: boolean;
        foto: File | null;
    }>({
        equipment_id: equipos[0] ? String(equipos[0].id) : '',
        componente: '',
        condicion: '',
        nota: '',
        accion_recomendada: '',
        repuesto_sugerido: '',
        requiere_autorizacion: true,
        foto: null,
    });

    // El extintor que se ve en la lista: si la orden aún no tenía extintores al
    // abrir la pantalla, el valor inicial quedó vacío y el dibujo salía genérico.
    const equipoId = equipos.some(
        (e) => String(e.id) === form.data.equipment_id,
    )
        ? form.data.equipment_id
        : String(equipos[0]?.id ?? '');

    const enviar = (e: React.FormEvent) => {
        e.preventDefault();
        form.transform((datos) => ({ ...datos, equipment_id: equipoId }));
        form.post(
            destino ??
                registrarDeficiencia.url({
                    current_team: currentTeam?.slug ?? '',
                    service_order: ordenId,
                }),
            {
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ??
                            'No se pudo completar la acción.',
                    ),
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    form.reset();
                    setAbierto(false);
                },
            },
        );
    };

    const equipo = equipos.find((e) => String(e.id) === equipoId);
    const tipo = tipoDeExtintor(
        equipo?.tipo_agente ?? null,
        equipo?.capacidad ?? null,
    );
    const parte = PARTES[tipo].find((p) => p.nombre === form.data.componente);
    const elegirParte = (clave: string) => {
        const elegida = PARTES[tipo].find((p) => p.clave === clave);
        form.setData((datos) => ({
            ...datos,
            componente: elegida?.nombre ?? '',
            condicion: '',
        }));
    };

    if (equipos.length === 0) {
        return (
            <p className="text-muted-foreground text-xs">
                Registra el extintor en la orden para anotar una deficiencia.
            </p>
        );
    }

    const campo =
        'w-full min-h-[44px] rounded-xl border border-neutral-300 bg-white px-2.5 py-2 text-xs dark:border-neutral-600 dark:bg-neutral-900';

    if (!abierto) {
        return (
            <button
                type="button"
                onClick={() => setAbierto(true)}
                className="text-warning-strong inline-flex min-h-[44px] items-center gap-1.5 rounded-xl border border-amber-500 px-3 text-xs font-bold dark:text-amber-300"
            >
                <AlertTriangle className="h-4 w-4" />
                Registrar una deficiencia
            </button>
        );
    }

    return (
        <form
            onSubmit={enviar}
            className="grid w-full gap-3 rounded-2xl border border-amber-300 bg-amber-50/50 p-3.5 md:grid-cols-[minmax(0,300px)_minmax(0,1fr)] dark:border-amber-800 dark:bg-amber-950/20"
        >
            <h3 className="text-xs font-bold text-neutral-900 md:col-span-2 dark:text-neutral-100">
                Nueva deficiencia: toca la parte que falla
            </h3>
            <div className="mx-auto w-full max-w-[280px] self-center">
                <ExtintorPorPartes
                    tipo={tipo}
                    malas={parte ? { [parte.clave]: true } : {}}
                    onTocar={elegirParte}
                />
            </div>
            <div className="space-y-2.5">
                <label className="block text-xs font-semibold">
                    Extintor
                    <NativeSelect
                        value={equipoId}
                        onChange={(e) =>
                            form.setData('equipment_id', e.target.value)
                        }
                        className={campo}
                    >
                        {equipos.map((e) => (
                            <option key={e.id} value={e.id}>
                                {e.serie}
                            </option>
                        ))}
                    </NativeSelect>
                </label>
                <fieldset className="space-y-1.5">
                    <legend className="text-xs font-semibold">Parte *</legend>
                    <div className="flex flex-wrap gap-1.5">
                        {PARTES[tipo].map((p) => (
                            <button
                                key={p.clave}
                                type="button"
                                aria-pressed={form.data.componente === p.nombre}
                                onClick={() => elegirParte(p.clave)}
                                className={`min-h-9 rounded-lg border px-2.5 text-xs font-semibold ${form.data.componente === p.nombre ? 'border-destructive bg-destructive/10 text-destructive-strong' : 'border-neutral-300 dark:border-neutral-600'}`}
                            >
                                {p.nombre}
                            </button>
                        ))}
                    </div>
                    {parte ? (
                        <div className="flex flex-wrap gap-1.5">
                            {parte.opciones
                                .filter((opcion) => opcion !== 'Otro')
                                .map((opcion) => (
                                    <button
                                        key={opcion}
                                        type="button"
                                        aria-pressed={
                                            form.data.condicion === opcion
                                        }
                                        onClick={() =>
                                            form.setData('condicion', opcion)
                                        }
                                        className={`min-h-9 rounded-lg border px-2.5 text-xs ${form.data.condicion === opcion ? 'border-amber-600 bg-amber-100 font-semibold dark:bg-amber-900/40' : 'border-neutral-300 dark:border-neutral-600'}`}
                                    >
                                        {opcion}
                                    </button>
                                ))}
                        </div>
                    ) : null}
                </fieldset>
                <label className="block text-xs font-semibold">
                    ¿Qué le pasa? *
                    <input
                        required
                        disabled={!parte}
                        value={form.data.condicion}
                        onChange={(e) =>
                            form.setData('condicion', e.target.value)
                        }
                        placeholder="Ej: Fisurada, sin presión..."
                        className={campo}
                    />
                </label>
                <label className="block text-xs font-semibold">
                    Qué se recomienda
                    <input
                        value={form.data.accion_recomendada}
                        onChange={(e) =>
                            form.setData('accion_recomendada', e.target.value)
                        }
                        className={campo}
                    />
                </label>
                <label className="block text-xs font-semibold">
                    Repuesto sugerido
                    <input
                        value={form.data.repuesto_sugerido}
                        onChange={(e) =>
                            form.setData('repuesto_sugerido', e.target.value)
                        }
                        className={campo}
                    />
                </label>
                <label className="flex min-h-[44px] items-center gap-2 text-xs font-semibold">
                    <input
                        type="checkbox"
                        checked={form.data.requiere_autorizacion}
                        onChange={(e) =>
                            form.setData(
                                'requiere_autorizacion',
                                e.target.checked,
                            )
                        }
                        className="h-4 w-4"
                    />
                    Necesita la autorización del cliente
                </label>
                <div className="flex flex-wrap items-center gap-2">
                    <BotonFoto
                        archivo={form.data.foto}
                        onFoto={(f) => form.setData('foto', f)}
                        etiqueta="Tomar foto *"
                    />
                    {form.data.foto && (
                        <span className="text-xs text-neutral-700 dark:text-neutral-300">
                            {form.data.foto.name}
                        </span>
                    )}
                </div>
                {Object.values(form.errors)[0] && (
                    <p
                        className="text-destructive-strong text-xs font-semibold"
                        role="alert"
                    >
                        {Object.values(form.errors)[0]}
                    </p>
                )}
                <div className="flex gap-2">
                    <button
                        type="submit"
                        disabled={form.processing || !form.data.foto}
                        className="min-h-[44px] flex-1 rounded-xl bg-amber-600 text-xs font-bold text-white transition hover:brightness-95 disabled:opacity-50"
                    >
                        Guardar deficiencia
                    </button>
                    <button
                        type="button"
                        onClick={() => setAbierto(false)}
                        className="min-h-[44px] rounded-xl px-4 text-xs font-semibold text-neutral-700 dark:text-neutral-300"
                    >
                        Cancelar
                    </button>
                </div>
            </div>
        </form>
    );
}
