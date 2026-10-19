import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { BotonFoto } from '@/components/captura-evidencia';
import { store as guardarEvidencia } from '@/routes/ordenes/evidencias';
import { show as verEvidencia } from '@/routes/evidencias';
import type { Team } from '@/types';
import { toast } from 'sonner';
import { NativeSelect } from '@/components/ui/native-select';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

export interface EvidenciaListada {
    id: number;
    etapa: string;
    tipo: string;
    equipment_id: number | null;
}

interface Props {
    ordenId: number;
    etapa: string;
    titulo: string;
    evidencias?: EvidenciaListada[];
    equipos?: { id: number; serie: string }[];
    soloLectura?: boolean;
}

/** Sube fotos sueltas de la orden (antes, después…) con la cámara del celular. */
export default function SubirEvidencia({
    ordenId,
    etapa,
    titulo,
    evidencias = [],
    equipos = [],
    soloLectura = false,
}: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const equipo = currentTeam?.slug ?? '';
    const [equipoId, setEquipoId] = useState('');
    const [subiendo, setSubiendo] = useState(false);
    const propias = evidencias.filter((e) => e.etapa === etapa);

    const subir = (archivo: File | null) => {
        if (!archivo) {
            return;
        }

        router.post(
            guardarEvidencia.url({
                current_team: equipo,
                service_order: ordenId,
            }),
            { archivo, etapa, equipment_id: equipoId || null },
            {
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ??
                            'No se pudo completar la acción.',
                    ),
                forceFormData: true,
                preserveScroll: true,
                onStart: () => setSubiendo(true),
                onFinish: () => setSubiendo(false),
            },
        );
    };

    return (
        <div className="space-y-2">
            <div className="flex flex-wrap items-center gap-2">
                <span className="text-xs font-bold text-neutral-900 dark:text-neutral-100">
                    {titulo} ({propias.length})
                </span>
                {!soloLectura && equipos.length > 0 && (
                    <NativeSelect
                        aria-label="Extintor de la foto"
                        value={equipoId}
                        onChange={(e) => setEquipoId(e.target.value)}
                        className="min-h-[44px] rounded-xl border border-neutral-300 bg-white px-2 text-xs dark:border-neutral-600 dark:bg-neutral-900"
                    >
                        <option value="">Foto general</option>
                        {equipos.map((e) => (
                            <option key={e.id} value={e.id}>
                                {e.serie}
                            </option>
                        ))}
                    </NativeSelect>
                )}
                {!soloLectura && (
                    <BotonFoto onFoto={subir} etiqueta="Agregar foto" />
                )}
                {subiendo && (
                    <span className="text-xs text-neutral-500">
                        Subiendo...
                    </span>
                )}
            </div>
            {propias.length > 0 && (
                <div className="flex flex-wrap gap-2">
                    {propias.map((e) => (
                        <Dialog key={e.id}>
                            <DialogTrigger asChild>
                                <button
                                    type="button"
                                    aria-label={`Ver foto ${titulo.toLowerCase()} ${e.id}`}
                                    className="focus-visible:ring-ring rounded-lg focus-visible:ring-2 focus-visible:outline-none"
                                >
                                    <img
                                        src={verEvidencia.url({
                                            current_team: equipo,
                                            evidencia: e.id,
                                        })}
                                        alt={`Foto ${titulo.toLowerCase()}`}
                                        className="h-16 w-16 rounded-lg object-cover"
                                    />
                                </button>
                            </DialogTrigger>
                            <DialogContent className="max-h-[90dvh] overflow-y-auto">
                                <DialogTitle>{titulo}</DialogTitle>
                                <DialogDescription>
                                    Foto vinculada a esta orden
                                    {e.equipment_id ? ' y a su extintor' : ''}.
                                </DialogDescription>
                                <img
                                    src={verEvidencia.url({
                                        current_team: equipo,
                                        evidencia: e.id,
                                    })}
                                    alt={`Foto ${titulo.toLowerCase()}`}
                                    className="max-h-[70dvh] w-full object-contain"
                                />
                            </DialogContent>
                        </Dialog>
                    ))}
                </div>
            )}
        </div>
    );
}
