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

export type Elemento = {
    id: number;
    nombre: string;
    activo: boolean;
    tipo_base?: string;
};

type Tipo = { valor: string; etiqueta: string };

export type ConfigLista = {
    /** "Agentes", "Marcas". */
    titulo: string;
    /** "agente", "marca": para los textos. */
    singular: string;
    descripcion: string;
    ejemplo: string;
    /** Clave de la lista en la respuesta del servidor. */
    clave: string;
    /** Prop compartida que se recarga para que los formularios la vean. */
    prop: string;
    rutas: {
        index: string;
        store: string;
        update: (id: number) => string;
        destroy: (id: number) => string;
    };
    /** Solo agentes: el tipo base que decide las reglas. */
    conTipo?: boolean;
};

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
 * Botón chico junto a un selector: abre un modal para agregar, renombrar o
 * quitar elementos de la lista (agentes, marcas) sin salir del formulario.
 */
export default function BotonLista({ config }: { config: ConfigLista }) {
    const [abierto, setAbierto] = useState(false);
    const [elementos, setElementos] = useState<Elemento[]>([]);
    const [tipos, setTipos] = useState<Tipo[]>([]);
    const [nuevo, setNuevo] = useState({ nombre: '', tipo_base: 'pqs' });
    const [editando, setEditando] = useState<Elemento | null>(null);
    const [guardando, setGuardando] = useState(false);
    const { singular } = config;

    const cargar = async () => {
        const datos = await pedir(config.rutas.index, 'GET');
        setElementos(datos[config.clave]);
        setTipos(datos.tipos ?? []);
    };

    const accion = async (hacer: () => Promise<unknown>, aviso: string) => {
        setGuardando(true);

        try {
            await hacer();
            await cargar();
            router.reload({ only: [config.prop] });
            toast.success(aviso);

            return true;
        } catch (error) {
            toast.error((error as Error).message);

            return false;
        } finally {
            setGuardando(false);
        }
    };

    const datosDe = (nombre: string, tipo_base?: string) =>
        config.conTipo ? { nombre, tipo_base } : { nombre };

    const agregar = async (event: FormEvent) => {
        event.preventDefault();
        event.stopPropagation();

        if (
            await accion(
                () =>
                    pedir(
                        config.rutas.store,
                        'POST',
                        datosDe(nuevo.nombre, nuevo.tipo_base),
                    ),
                'Agregado a la lista.',
            )
        ) {
            setNuevo({ ...nuevo, nombre: '' });
        }
    };

    const tipoDe = (valor?: string) =>
        tipos.find((t) => t.valor === valor)?.etiqueta ?? valor;

    return (
        <>
            <Button
                type="button"
                variant="outline"
                size="icon"
                aria-label={`Agregar o quitar ${config.titulo.toLowerCase()}`}
                title={`Agregar o quitar ${config.titulo.toLowerCase()}`}
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
                            {config.titulo}
                        </DialogTitle>
                        <DialogDescription className="text-xs">
                            {config.descripcion}
                        </DialogDescription>
                    </DialogHeader>

                    <form
                        onSubmit={agregar}
                        className="grid grid-cols-[minmax(0,1fr)_auto] gap-2"
                    >
                        <Input
                            aria-label={`Nombre nuevo (${config.titulo.toLowerCase()})`}
                            placeholder={config.ejemplo}
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
                        {config.conTipo ? (
                            <label className="text-muted-foreground col-span-2 flex items-center gap-2 text-xs">
                                Tipo
                                <NativeSelect
                                    aria-label={`Tipo (${singular} nuevo)`}
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
                        ) : null}
                    </form>

                    <ul className="divide-border max-h-72 divide-y overflow-y-auto">
                        {elementos
                            .filter((e) => e.activo)
                            .map((elemento) => (
                                <li
                                    key={elemento.id}
                                    className="flex items-center gap-2 py-2"
                                >
                                    {editando?.id === elemento.id ? (
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
                                                                    config.rutas.update(
                                                                        elemento.id,
                                                                    ),
                                                                    'PATCH',
                                                                    datosDe(
                                                                        editando.nombre,
                                                                        editando.tipo_base,
                                                                    ),
                                                                ),
                                                            'Nombre cambiado.',
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
                                                    {elemento.nombre}
                                                </p>
                                                {config.conTipo ? (
                                                    <p className="text-muted-foreground text-xs">
                                                        Tipo:{' '}
                                                        {tipoDe(
                                                            elemento.tipo_base,
                                                        )}
                                                    </p>
                                                ) : null}
                                            </div>
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                aria-label={`Renombrar ${elemento.nombre}`}
                                                onClick={() =>
                                                    setEditando(elemento)
                                                }
                                                className="size-9"
                                            >
                                                <Pencil className="size-4" />
                                            </Button>
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                aria-label={`Quitar ${elemento.nombre}`}
                                                onClick={() =>
                                                    void accion(
                                                        () =>
                                                            pedir(
                                                                config.rutas.destroy(
                                                                    elemento.id,
                                                                ),
                                                                'DELETE',
                                                            ),
                                                        'Quitado de la lista.',
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
