import { Minus, Plus, Trash2, X } from 'lucide-react';
import { usePage } from '@inertiajs/react';
import { useState } from 'react';

import BotonAgentes from '@/components/agentes-modal';
import BotonCapacidades from '@/components/capacidades-modal';
import { useAgentesExtintor } from '@/components/opciones-agente';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';

export type GrupoRecojo = {
    tipo_agente: string;
    capacidad: string;
    cantidad: number;
    precio: string;
};

const botonChip = (activo: boolean) =>
    `min-h-11 rounded-xl border px-1.5 text-[13px] leading-tight font-semibold focus-visible:ring-ring focus-visible:ring-2 focus-visible:outline-none ${activo ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-card text-foreground'}`;

function Contador({
    valor,
    nombre,
    onChange,
}: {
    valor: number;
    nombre: string;
    onChange: (valor: number) => void;
}) {
    return (
        <div className="flex items-center gap-2">
            <button
                type="button"
                onClick={() => onChange(Math.max(1, valor - 1))}
                aria-label={`Uno menos de ${nombre}`}
                className="border-border bg-card focus-visible:ring-ring flex size-11 items-center justify-center rounded-xl border focus-visible:ring-2 focus-visible:outline-none"
            >
                <Minus className="size-4" aria-hidden="true" />
            </button>
            <span
                aria-live="polite"
                className="w-7 text-center text-lg font-bold tabular-nums"
            >
                {valor}
            </span>
            <button
                type="button"
                onClick={() => onChange(Math.min(100, valor + 1))}
                aria-label={`Uno más de ${nombre}`}
                className="bg-primary text-primary-foreground focus-visible:ring-ring flex size-11 items-center justify-center rounded-xl focus-visible:ring-2 focus-visible:outline-none"
            >
                <Plus className="size-4" aria-hidden="true" />
            </button>
        </div>
    );
}

/**
 * Lo que campo se lleva, por tipo y capacidad: "PQS ABC 6 kg × 2". No se
 * identifica cada extintor; eso lo hace planta al recibirlos.
 */
export default function ConteoRecojo({
    grupos,
    onChange,
}: {
    grupos: GrupoRecojo[];
    onChange: (grupos: GrupoRecojo[]) => void;
}) {
    const agentes = useAgentesExtintor();
    const capacidades = usePage().props.capacidadesExtintor ?? [];
    const [abierto, setAbierto] = useState(false);
    const [nuevo, setNuevo] = useState({
        tipo_agente: '',
        capacidad: '',
        cantidad: 1,
    });

    const agregar = () => {
        const existe = grupos.findIndex(
            (g) =>
                g.tipo_agente === nuevo.tipo_agente &&
                g.capacidad === nuevo.capacidad,
        );
        onChange(
            existe >= 0
                ? grupos.map((g, i) =>
                      i === existe
                          ? { ...g, cantidad: g.cantidad + nuevo.cantidad }
                          : g,
                  )
                : [...grupos, { ...nuevo, precio: '' }],
        );
        setNuevo({ tipo_agente: '', capacidad: '', cantidad: 1 });
        setAbierto(false);
    };

    return (
        <>
            <div className="border-border bg-card overflow-hidden rounded-3xl border">
                <div className="divide-border divide-y">
                    {grupos.map((grupo, indice) => {
                        const nombre = `${grupo.tipo_agente} ${grupo.capacidad}`;

                        return (
                            <div
                                key={nombre}
                                className="flex min-h-16 items-center gap-2 py-2 pr-2 pl-4"
                            >
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate text-sm font-bold">
                                        {grupo.tipo_agente}
                                    </span>
                                    <span className="text-muted-foreground block text-xs">
                                        {grupo.capacidad}
                                    </span>
                                </span>
                                <Contador
                                    valor={grupo.cantidad}
                                    nombre={nombre}
                                    onChange={(cantidad) =>
                                        onChange(
                                            grupos.map((g, i) =>
                                                i === indice
                                                    ? { ...g, cantidad }
                                                    : g,
                                            ),
                                        )
                                    }
                                />
                                <button
                                    type="button"
                                    onClick={() =>
                                        onChange(
                                            grupos.filter(
                                                (_, i) => i !== indice,
                                            ),
                                        )
                                    }
                                    aria-label={`Quitar ${nombre}`}
                                    className="text-primary-strong focus-visible:ring-ring flex size-11 items-center justify-center rounded-xl focus-visible:ring-2 focus-visible:outline-none"
                                >
                                    <Trash2
                                        className="size-[18px]"
                                        aria-hidden="true"
                                    />
                                </button>
                            </div>
                        );
                    })}
                </div>
                <button
                    type="button"
                    onClick={() => setAbierto(true)}
                    className={`text-primary-strong focus-visible:ring-ring flex min-h-14 w-full items-center justify-center gap-2 text-sm font-semibold focus-visible:ring-2 focus-visible:outline-none focus-visible:ring-inset ${grupos.length > 0 ? 'border-border border-t' : ''}`}
                >
                    <Plus className="size-[18px]" aria-hidden="true" />
                    Agregar tipo
                </button>
            </div>

            <Sheet open={abierto} onOpenChange={setAbierto}>
                <SheetContent
                    side="bottom"
                    className="bg-background mx-auto max-h-[92dvh] max-w-2xl gap-3 overflow-y-auto rounded-t-3xl border-0 px-4 pt-3 pb-0 [&>button]:hidden"
                >
                    <div
                        className="bg-border mx-auto h-1.5 w-10 rounded-full"
                        aria-hidden="true"
                    />
                    <div className="flex items-center justify-between">
                        <SheetTitle className="font-['Oswald',sans-serif] text-[22px] font-semibold">
                            Agregar tipo
                        </SheetTitle>
                        <button
                            type="button"
                            onClick={() => setAbierto(false)}
                            aria-label="Cerrar"
                            className="border-border bg-card focus-visible:ring-ring flex size-11 items-center justify-center rounded-2xl border focus-visible:ring-2 focus-visible:outline-none"
                        >
                            <X className="size-[18px]" aria-hidden="true" />
                        </button>
                    </div>
                    <SheetDescription className="sr-only">
                        Elige el tipo, la capacidad y cuántos extintores llevas.
                    </SheetDescription>

                    <div
                        role="group"
                        aria-label="Tipo"
                        className="border-border bg-card space-y-2 rounded-3xl border p-3"
                    >
                        <div className="flex items-center justify-between">
                            <p className="text-sm font-semibold">Tipo</p>
                            <BotonAgentes />
                        </div>
                        <div className="grid grid-cols-3 gap-1.5">
                            {agentes.map((agente) => (
                                <button
                                    key={agente}
                                    type="button"
                                    aria-pressed={nuevo.tipo_agente === agente}
                                    onClick={() =>
                                        setNuevo({
                                            ...nuevo,
                                            tipo_agente: agente,
                                        })
                                    }
                                    className={botonChip(
                                        nuevo.tipo_agente === agente,
                                    )}
                                >
                                    {agente}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div
                        role="group"
                        aria-label="Capacidad"
                        className="border-border bg-card space-y-2 rounded-3xl border p-3"
                    >
                        <div className="flex items-center justify-between">
                            <p className="text-sm font-semibold">Capacidad</p>
                            <BotonCapacidades />
                        </div>
                        <div className="grid grid-cols-4 gap-1.5">
                            {capacidades.map((capacidad) => (
                                <button
                                    key={capacidad}
                                    type="button"
                                    aria-pressed={nuevo.capacidad === capacidad}
                                    onClick={() =>
                                        setNuevo({ ...nuevo, capacidad })
                                    }
                                    className={botonChip(
                                        nuevo.capacidad === capacidad,
                                    )}
                                >
                                    {capacidad}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="border-border bg-card flex items-center justify-between rounded-3xl border py-2 pr-2 pl-4">
                        <span className="text-sm font-semibold">Cantidad</span>
                        <Contador
                            valor={nuevo.cantidad}
                            nombre="este tipo"
                            onChange={(cantidad) =>
                                setNuevo({ ...nuevo, cantidad })
                            }
                        />
                    </div>

                    <div className="bg-background sticky bottom-0 -mx-4 px-4 pt-1 pb-[max(1.25rem,env(safe-area-inset-bottom))]">
                        <button
                            type="button"
                            disabled={!nuevo.tipo_agente || !nuevo.capacidad}
                            onClick={agregar}
                            className="bg-primary text-primary-foreground focus-visible:ring-ring min-h-13 w-full rounded-2xl text-base font-semibold focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:opacity-50"
                        >
                            {nuevo.tipo_agente && nuevo.capacidad
                                ? `Agregar ${nuevo.tipo_agente} ${nuevo.capacidad}`
                                : 'Elige tipo y capacidad'}
                        </button>
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}
