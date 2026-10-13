import { router } from '@inertiajs/react';
import { Check, Pencil, Plus, Settings2, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { leerCookie } from '@/lib/cookies';
import agentesRutas from '@/routes/agentes';

type Agente = {
    id: number;
    nombre: string;
    tipo_base: string;
    activo: boolean;
};

type Tipo = { valor: string; etiqueta: string };

async function pedir(url: string, method: string, body?: object) {
    const respuesta = await fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': leerCookie('XSRF-TOKEN'),
        },
        body: body ? JSON.stringify(body) : undefined,
    });
    const datos = await respuesta.json().catch(() => ({}));

    if (!respuesta.ok) {
        const errores = datos.errors as Record<string, string[]> | undefined;
        throw new Error(
            Object.values(errores ?? {})[0]?.[0] ??
                datos.message ??
                'No se pudo guardar.',
        );
    }

    return datos;
}

/**
 * Botón chico junto al selector de agente: abre un modal para agregar,
 * renombrar o quitar agentes sin salir del formulario.
 */
export default function BotonAgentes() {
    const [abierto, setAbierto] = useState(false);
    const [agentes, setAgentes] = useState<Agente[]>([]);
    const [tipos, setTipos] = useState<Tipo[]>([]);
    const [nuevo, setNuevo] = useState({ nombre: '', tipo_base: 'pqs' });
    const [editando, setEditando] = useState<Agente | null>(null);
    const [guardando, setGuardando] = useState(false);

    const cargar = async () => {
        const datos = await pedir(agentesRutas.index.url(), 'GET');
        setAgentes(datos.agentes);
        setTipos(datos.tipos);
    };

    const refrescarFormularios = () =>
        router.reload({ only: ['agentesExtintor'] });

    const accion = async (hacer: () => Promise<unknown>, aviso: string) => {
        setGuardando(true);

        try {
            await hacer();
            await cargar();
            refrescarFormularios();
            toast.success(aviso);

            return true;
        } catch (error) {
            toast.error((error as Error).message);

            return false;
        } finally {
            setGuardando(false);
        }
    };

    const agregar = async (event: FormEvent) => {
        event.preventDefault();

        if (
            await accion(
                () => pedir(agentesRutas.store.url(), 'POST', nuevo),
                'Agente agregado.',
            )
        ) {
            setNuevo({ ...nuevo, nombre: '' });
        }
    };

    const tipoDe = (valor: string) =>
        tipos.find((t) => t.valor === valor)?.etiqueta ?? valor;

    return (
        <>
            <Button
                type="button"
                variant="outline"
                size="icon"
                aria-label="Agregar o quitar agentes"
                title="Agregar o quitar agentes"
                onClick={() => {
                    setAbierto(true);
                    void cargar();
                }}
                className="size-11 shrink-0 sm:size-9"
            >
                <Settings2 className="size-4" />
            </Button>

            <Dialog open={abierto} onOpenChange={setAbierto}>
                <DialogContent className="border-border bg-card rounded-2xl sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="font-['Oswald',sans-serif] text-lg uppercase">
                            Agentes
                        </DialogTitle>
                        <DialogDescription className="text-xs">
                            Los que salen al registrar un extintor. Quitar uno
                            no cambia los extintores que ya lo tienen.
                        </DialogDescription>
                    </DialogHeader>

                    <form
                        onSubmit={agregar}
                        className="grid grid-cols-[minmax(0,1fr)_auto] gap-2"
                    >
                        <Input
                            aria-label="Nombre del agente nuevo"
                            placeholder="Nombre, por ejemplo Halotrón"
                            value={nuevo.nombre}
                            onChange={(e) =>
                                setNuevo({ ...nuevo, nombre: e.target.value })
                            }
                            className="h-11 sm:h-9"
                        />
                        <Button
                            type="submit"
                            loading={guardando}
                            disabled={nuevo.nombre.trim().length < 2}
                            className="h-11 sm:h-9"
                        >
                            <Plus className="size-4" />
                            Agregar
                        </Button>
                        <label className="text-muted-foreground col-span-2 flex items-center gap-2 text-xs">
                            Tipo
                            <NativeSelect
                                aria-label="Tipo del agente nuevo"
                                value={nuevo.tipo_base}
                                onChange={(e) =>
                                    setNuevo({
                                        ...nuevo,
                                        tipo_base: e.target.value,
                                    })
                                }
                                className="text-foreground h-11 flex-1 text-sm sm:h-9"
                            >
                                {tipos.map((t) => (
                                    <option key={t.valor} value={t.valor}>
                                        {t.etiqueta}
                                    </option>
                                ))}
                            </NativeSelect>
                        </label>
                    </form>

                    <ul className="divide-border max-h-72 divide-y overflow-y-auto">
                        {agentes
                            .filter((a) => a.activo)
                            .map((agente) => (
                                <li
                                    key={agente.id}
                                    className="flex items-center gap-2 py-2"
                                >
                                    {editando?.id === agente.id ? (
                                        <>
                                            <Input
                                                aria-label="Nuevo nombre"
                                                value={editando.nombre}
                                                autoFocus
                                                onChange={(e) =>
                                                    setEditando({
                                                        ...editando,
                                                        nombre: e.target.value,
                                                    })
                                                }
                                                className="h-9 flex-1"
                                            />
                                            <Button
                                                type="button"
                                                size="icon"
                                                aria-label="Guardar nombre"
                                                loading={guardando}
                                                onClick={async () => {
                                                    if (
                                                        await accion(
                                                            () =>
                                                                pedir(
                                                                    agentesRutas.update.url(
                                                                        agente.id,
                                                                    ),
                                                                    'PATCH',
                                                                    {
                                                                        nombre: editando.nombre,
                                                                        tipo_base:
                                                                            editando.tipo_base,
                                                                    },
                                                                ),
                                                            'Agente renombrado.',
                                                        )
                                                    ) {
                                                        setEditando(null);
                                                    }
                                                }}
                                                className="size-9"
                                            >
                                                <Check className="size-4" />
                                            </Button>
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                aria-label="Cancelar"
                                                onClick={() =>
                                                    setEditando(null)
                                                }
                                                className="size-9"
                                            >
                                                <X className="size-4" />
                                            </Button>
                                        </>
                                    ) : (
                                        <>
                                            <div className="min-w-0 flex-1">
                                                <p className="text-foreground truncate text-sm font-medium">
                                                    {agente.nombre}
                                                </p>
                                                <p className="text-muted-foreground text-xs">
                                                    Tipo:{' '}
                                                    {tipoDe(agente.tipo_base)}
                                                </p>
                                            </div>
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                aria-label={`Renombrar ${agente.nombre}`}
                                                onClick={() =>
                                                    setEditando(agente)
                                                }
                                                className="size-9"
                                            >
                                                <Pencil className="size-4" />
                                            </Button>
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                aria-label={`Quitar ${agente.nombre}`}
                                                onClick={() =>
                                                    void accion(
                                                        () =>
                                                            pedir(
                                                                agentesRutas.destroy.url(
                                                                    agente.id,
                                                                ),
                                                                'DELETE',
                                                            ),
                                                        'Agente quitado de la lista.',
                                                    )
                                                }
                                                className="text-destructive-strong size-9"
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </>
                                    )}
                                </li>
                            ))}
                    </ul>
                </DialogContent>
            </Dialog>
        </>
    );
}
