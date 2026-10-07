import { useForm, usePage } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { useState } from 'react';
import { BotonFoto } from '@/components/captura-evidencia';
import { store as registrarDeficiencia } from '@/routes/tecnico-planta/deficiencias';
import type { Team } from '@/types';

interface Props {
    ordenId: number;
    equipos: { id: number; serie: string }[];
}

/** T5: registra una deficiencia que no salió del checklist, con foto obligatoria. */
export default function DeficienciaSuelta({ ordenId, equipos }: Props) {
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

    const enviar = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(
            registrarDeficiencia.url({
                current_team: currentTeam?.slug ?? '',
                service_order: ordenId,
            }),
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    form.reset();
                    setAbierto(false);
                },
            },
        );
    };

    const campo =
        'w-full min-h-[44px] rounded-xl border border-neutral-300 bg-white px-2.5 py-2 text-xs dark:border-neutral-600 dark:bg-neutral-900';

    if (!abierto) {
        return (
            <button
                type="button"
                onClick={() => setAbierto(true)}
                className="inline-flex min-h-[44px] items-center gap-1.5 rounded-xl border border-amber-500 px-3 text-xs font-bold text-amber-800 dark:text-amber-300"
            >
                <AlertTriangle className="h-4 w-4" />
                Registrar una deficiencia
            </button>
        );
    }

    return (
        <form
            onSubmit={enviar}
            className="space-y-2.5 rounded-2xl border border-amber-300 bg-amber-50/50 p-3.5 dark:border-amber-800 dark:bg-amber-950/20"
        >
            <h3 className="text-xs font-bold text-neutral-900 dark:text-neutral-100">
                Nueva deficiencia
            </h3>
            <label className="block text-[11px] font-semibold">
                Extintor
                <select
                    value={form.data.equipment_id}
                    onChange={(e) => form.setData('equipment_id', e.target.value)}
                    className={campo}
                >
                    {equipos.map((e) => (
                        <option key={e.id} value={e.id}>
                            {e.serie}
                        </option>
                    ))}
                </select>
            </label>
            <label className="block text-[11px] font-semibold">
                Componente *
                <input
                    required
                    value={form.data.componente}
                    onChange={(e) => form.setData('componente', e.target.value)}
                    placeholder="Ej: Manguera, válvula..."
                    className={campo}
                />
            </label>
            <label className="block text-[11px] font-semibold">
                ¿Qué le pasa? *
                <input
                    required
                    value={form.data.condicion}
                    onChange={(e) => form.setData('condicion', e.target.value)}
                    placeholder="Ej: Fisurada, sin presión..."
                    className={campo}
                />
            </label>
            <label className="block text-[11px] font-semibold">
                Qué se recomienda
                <input
                    value={form.data.accion_recomendada}
                    onChange={(e) => form.setData('accion_recomendada', e.target.value)}
                    className={campo}
                />
            </label>
            <label className="block text-[11px] font-semibold">
                Repuesto sugerido
                <input
                    value={form.data.repuesto_sugerido}
                    onChange={(e) => form.setData('repuesto_sugerido', e.target.value)}
                    className={campo}
                />
            </label>
            <label className="flex min-h-[44px] items-center gap-2 text-xs font-semibold">
                <input
                    type="checkbox"
                    checked={form.data.requiere_autorizacion}
                    onChange={(e) => form.setData('requiere_autorizacion', e.target.checked)}
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
                    <span className="text-[11px] text-neutral-700 dark:text-neutral-300">
                        {form.data.foto.name}
                    </span>
                )}
            </div>
            {Object.values(form.errors)[0] && (
                <p className="text-[11px] font-semibold text-red-600" role="alert">
                    {Object.values(form.errors)[0]}
                </p>
            )}
            <div className="flex gap-2">
                <button
                    type="submit"
                    disabled={form.processing || !form.data.foto}
                    className="min-h-[44px] flex-1 rounded-xl bg-amber-600 text-xs font-bold text-white disabled:opacity-50"
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
        </form>
    );
}
