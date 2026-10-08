import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { useConfirmarAccion } from '@/hooks/use-confirmar-accion';
import { toast } from 'sonner';

import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import categoriasRoutes from '@/routes/gerente/categorias';

export type CategoriaItem = {
    id: number;
    clave: string;
    nombre: string;
    genera_alertas_vencimiento: boolean;
    activo: boolean;
    usos: number;
};

type Props = {
    value: string;
    onChange: (clave: string) => void;
    categorias: CategoriaItem[];
};

/**
 * Select de categoría con el botón "Gestionar": crear, renombrar, desactivar
 * y borrar (solo si nadie la usa).
 */
export function CategoriaSelect({ value, onChange, categorias }: Props) {
    const { pedirConfirmacion, dialogoConfirmacion } = useConfirmarAccion();
    const { currentTeam, errors } = usePage<{
        currentTeam: { slug: string };
        errors: Record<string, string>;
    }>().props;
    const [abierto, setAbierto] = useState(false);
    const [nombreNueva, setNombreNueva] = useState('');
    const [alertasNueva, setAlertasNueva] = useState(false);
    const [renombrando, setRenombrando] = useState<Record<number, string>>({});

    const params = { current_team: currentTeam.slug };
    const opciones = { preserveScroll: true, preserveState: true };

    const crear = () => {
        router.post(
            categoriasRoutes.store.url(params),
            {
                nombre: nombreNueva,
                genera_alertas_vencimiento: alertasNueva,
            },
            {
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ??
                            'No se pudo completar la acción.',
                    ),
                ...opciones,
                onSuccess: () => {
                    setNombreNueva('');
                    setAlertasNueva(false);
                },
            },
        );
    };

    const guardar = (
        c: CategoriaItem,
        cambios: Partial<
            Pick<
                CategoriaItem,
                'nombre' | 'genera_alertas_vencimiento' | 'activo'
            >
        >,
    ) => {
        router.put(
            categoriasRoutes.update.url({ ...params, categoria: c.id }),
            {
                nombre: c.nombre,
                genera_alertas_vencimiento: c.genera_alertas_vencimiento,
                activo: c.activo,
                ...cambios,
            },
            {
                ...opciones,
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ??
                            'No se pudo completar la acción.',
                    ),
            },
        );
    };

    const borrar = async (c: CategoriaItem) => {
        if (!(await pedirConfirmacion(`¿Borrar la categoría "${c.nombre}"?`))) {
            return;
        }
        router.delete(
            categoriasRoutes.destroy.url({ ...params, categoria: c.id }),
            {
                ...opciones,
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ??
                            'No se pudo completar la acción.',
                    ),
            },
        );
    };

    return (
        <div>
            {dialogoConfirmacion}
            <div className="flex items-center justify-between">
                <label
                    htmlFor="categoria-select"
                    className="text-foreground/80 block font-semibold"
                >
                    Categoría
                </label>
                <button
                    type="button"
                    onClick={() => setAbierto(true)}
                    className="text-primary-strong text-xs font-semibold hover:underline"
                >
                    Gestionar
                </button>
            </div>
            <select
                id="categoria-select"
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className="border-border focus:border-primary mt-1 w-full rounded-lg border px-3 py-2 focus:outline-none"
            >
                <option value="">Sin categoría</option>
                {categorias
                    .filter((c) => c.activo || c.clave === value)
                    .map((c) => (
                        <option key={c.clave} value={c.clave}>
                            {c.nombre}
                            {c.activo ? '' : ' (desactivada)'}
                        </option>
                    ))}
            </select>

            <Dialog open={abierto} onOpenChange={setAbierto}>
                <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Gestionar categorías</DialogTitle>
                        <DialogDescription>
                            Solo se borra una categoría que nadie usa; si la
                            usan productos o servicios, desactívala.
                        </DialogDescription>
                    </DialogHeader>

                    {errors.categoria && (
                        <p className="text-sm text-red-600" role="alert">
                            {errors.categoria}
                        </p>
                    )}
                    {errors.nombre && (
                        <p className="text-sm text-red-600" role="alert">
                            {errors.nombre}
                        </p>
                    )}

                    <ul className="divide-border divide-y text-sm">
                        {categorias.map((c) => (
                            <li key={c.id} className="space-y-2 py-3">
                                <div className="flex items-center gap-2">
                                    <input
                                        aria-label={`Nombre de ${c.nombre}`}
                                        value={renombrando[c.id] ?? c.nombre}
                                        onChange={(e) =>
                                            setRenombrando({
                                                ...renombrando,
                                                [c.id]: e.target.value,
                                            })
                                        }
                                        className="border-border w-full rounded-lg border px-3 py-1.5"
                                    />
                                    <button
                                        type="button"
                                        disabled={
                                            (renombrando[c.id] ?? c.nombre) ===
                                            c.nombre
                                        }
                                        onClick={() =>
                                            guardar(c, {
                                                nombre: renombrando[c.id],
                                            })
                                        }
                                        className="border-border rounded-lg border px-3 py-1.5 font-semibold disabled:opacity-40"
                                    >
                                        Renombrar
                                    </button>
                                </div>
                                <div className="text-muted-foreground flex flex-wrap items-center gap-4 text-xs">
                                    <label className="flex items-center gap-1.5">
                                        <input
                                            type="checkbox"
                                            checked={
                                                c.genera_alertas_vencimiento
                                            }
                                            onChange={(e) =>
                                                guardar(c, {
                                                    genera_alertas_vencimiento:
                                                        e.target.checked,
                                                })
                                            }
                                        />
                                        Genera alertas de vencimiento
                                    </label>
                                    <span>
                                        {c.usos === 0
                                            ? 'Sin uso'
                                            : `${c.usos} en uso`}
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            guardar(c, { activo: !c.activo })
                                        }
                                        className="font-semibold underline"
                                    >
                                        {c.activo ? 'Desactivar' : 'Activar'}
                                    </button>
                                    {c.usos === 0 && (
                                        <button
                                            type="button"
                                            onClick={() => borrar(c)}
                                            className="font-semibold text-red-600 underline"
                                        >
                                            Borrar
                                        </button>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>

                    <div className="border-border space-y-2 border-t pt-3 text-sm">
                        <label
                            htmlFor="categoria-nueva"
                            className="font-semibold"
                        >
                            Nueva categoría
                        </label>
                        <div className="flex items-center gap-2">
                            <input
                                id="categoria-nueva"
                                value={nombreNueva}
                                onChange={(e) => setNombreNueva(e.target.value)}
                                className="border-border w-full rounded-lg border px-3 py-1.5"
                            />
                            <button
                                type="button"
                                disabled={nombreNueva.trim() === ''}
                                onClick={crear}
                                className="bg-foreground text-background rounded-lg px-3 py-1.5 font-semibold disabled:opacity-40"
                            >
                                Crear
                            </button>
                        </div>
                        <label className="text-muted-foreground flex items-center gap-1.5 text-xs">
                            <input
                                type="checkbox"
                                checked={alertasNueva}
                                onChange={(e) =>
                                    setAlertasNueva(e.target.checked)
                                }
                            />
                            Genera alertas de vencimiento
                        </label>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    );
}
