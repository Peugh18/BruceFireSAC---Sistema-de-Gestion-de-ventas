import CampoMarca from '@/components/campo-marca';
import {
    closestCenter,
    DndContext,
    KeyboardSensor,
    PointerSensor,
    TouchSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import type { DragEndEvent } from '@dnd-kit/core';
import {
    arrayMove,
    SortableContext,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { router } from '@inertiajs/react';
import {
    CheckCircle2,
    Circle,
    GripVertical,
    PackagePlus,
    Pencil,
    ScanBarcode,
    Trash2,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { toast } from 'sonner';

import BotonAgentes from '@/components/agentes-modal';
import OpcionesAgente from '@/components/opciones-agente';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';

export type LineaCasilleros = {
    id: number;
    servicio: string;
    agente: string | null;
    capacidad: string | null;
    cantidad: number;
    adicional: boolean;
    asignados: number;
};

export type EquipoCasillero = {
    id: number;
    numero_serie: string;
    tipo_agente: string | null;
    capacidad: string | null;
    marca: string | null;
    serie_fabricante: string | null;
    anio_fabricacion: string | null;
    linea_id: number | null;
    recibido: boolean;
};

export type Casilleros = {
    lineas: LineaCasilleros[];
    equipos: EquipoCasillero[];
    esperados: number;
    identificados: number;
    editable: boolean;
    servicios: { id: number; nombre: string }[];
};

type Rutas = {
    agregar: string;
    reordenar: string;
    editar: (equipoId: number) => string;
    quitar: (equipoId: number) => string;
};

const NO_LEGIBLE = 'No legible / Pendiente de verificar';
const dato = (valor: string | null) =>
    !valor || valor === NO_LEGIBLE ? '—' : valor;

const errorDe = (errores: Record<string, string>) =>
    Object.values(errores)[0] ?? 'No se pudo guardar.';

type Formulario = {
    marca: string;
    serie_fabricante: string;
    anio_fabricacion: string;
    tipo_agente: string;
    capacidad: string;
};

const vacio: Formulario = {
    marca: '',
    serie_fabricante: '',
    anio_fabricacion: '',
    tipo_agente: '',
    capacidad: '',
};

const serie = (equipos: EquipoCasillero[], id: unknown) =>
    equipos.find((e) => e.id === id)?.numero_serie ?? 'el extintor';

/** Una fila que se toma del asa de puntos y se arrastra (o con el teclado). */
function FilaEquipo({
    equipo,
    posicion,
    linea,
    editable,
    acciones,
    onEditar,
    onQuitar,
    children,
}: {
    equipo: EquipoCasillero;
    posicion: number;
    linea?: LineaCasilleros;
    editable: boolean;
    acciones?: ReactNode;
    onEditar: () => void;
    onQuitar: () => void;
    children?: ReactNode;
}) {
    const {
        attributes,
        listeners,
        setNodeRef,
        setActivatorNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({ id: equipo.id, disabled: !editable });

    return (
        <li
            ref={setNodeRef}
            style={{
                transform: CSS.Transform.toString(transform),
                transition,
            }}
            className={`border-border bg-card flex flex-wrap items-center gap-x-3 gap-y-1 border-t py-2.5 first:border-t-0 ${
                isDragging
                    ? 'border-primary relative z-10 rounded-xl border-2 shadow-lg'
                    : ''
            }`}
        >
            {editable ? (
                <button
                    type="button"
                    ref={setActivatorNodeRef}
                    {...attributes}
                    {...listeners}
                    aria-label={`Mover ${equipo.numero_serie}: Espacio para tomarlo y flechas para moverlo`}
                    className="text-muted-foreground hover:text-foreground focus-visible:ring-ring flex size-11 shrink-0 cursor-grab touch-none items-center justify-center rounded-lg focus-visible:ring-2 focus-visible:outline-none active:cursor-grabbing sm:size-9"
                >
                    <GripVertical className="size-5" />
                </button>
            ) : null}
            <span className="text-muted-foreground w-6 text-right font-['IBM_Plex_Mono',monospace] text-xs">
                {posicion}
            </span>
            <CheckCircle2 className="text-success-strong size-4 shrink-0" />
            <div className="min-w-0 flex-1">
                <p className="text-foreground flex flex-wrap items-center gap-x-2 font-['IBM_Plex_Mono',monospace] text-sm font-semibold">
                    {equipo.numero_serie}
                    {linea ? (
                        <span
                            className={`rounded-full px-2 py-0.5 font-sans text-xs font-semibold ${
                                linea.adicional
                                    ? 'text-warning-strong bg-amber-500/15'
                                    : 'bg-muted text-muted-foreground'
                            }`}
                        >
                            {linea.servicio}
                            {linea.adicional ? ' · no cotizado' : ''}
                        </span>
                    ) : (
                        <span className="bg-muted text-muted-foreground rounded-full px-2 py-0.5 font-sans text-xs font-semibold">
                            Servicio pendiente de cotizar
                        </span>
                    )}
                </p>
                <p className="text-muted-foreground truncate text-xs">
                    {dato(equipo.tipo_agente)} · {dato(equipo.capacidad)} ·{' '}
                    {dato(equipo.marca)} · Serie {dato(equipo.serie_fabricante)}{' '}
                    · Año {dato(equipo.anio_fabricacion)}
                </p>
            </div>
            {acciones}
            {editable ? (
                <>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label={`Corregir datos de ${equipo.numero_serie}`}
                        onClick={onEditar}
                        className="size-11 sm:size-9"
                    >
                        <Pencil className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label={`Quitar ${equipo.numero_serie} de la orden`}
                        onClick={onQuitar}
                        className="text-destructive-strong size-11 sm:size-9"
                    >
                        <Trash2 className="size-4" />
                    </Button>
                </>
            ) : null}
            {children ? <div className="w-full">{children}</div> : null}
        </li>
    );
}

/**
 * Los extintores de una orden, en casilleros por línea de servicio: lo que ya
 * se sabe (agente, capacidad) viene puesto; se escanea el sticker BF-EQ o la
 * serie de fábrica y se completa solo. La usan el vendedor y el técnico de
 * planta: es la misma lista, no dos copias.
 */
export default function EquiposDeLaOrden({
    casilleros,
    rutas,
    accionesEquipo,
}: {
    casilleros: Casilleros;
    rutas: Rutas;
    accionesEquipo?: (equipo: EquipoCasillero) => ReactNode;
}) {
    const { lineas, esperados, identificados, editable, servicios } =
        casilleros;
    // El orden se ve al instante al soltar; el servidor lo confirma después.
    const [orden, setOrden] = useState(casilleros.equipos);
    useEffect(() => setOrden(casilleros.equipos), [casilleros.equipos]);
    const equipos = orden;
    const sensores = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
        useSensor(TouchSensor, {
            activationConstraint: { delay: 150, tolerance: 5 },
        }),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        }),
    );
    const [codigo, setCodigo] = useState('');
    const [enviando, setEnviando] = useState(false);
    // Un casillero (id de línea), un extintor no cotizado o uno que se corrige.
    const [abierto, setAbierto] = useState<
        number | 'extra' | { equipo: number } | null
    >(null);
    const [formulario, setFormulario] = useState<Formulario>(vacio);
    const [servicioExtra, setServicioExtra] = useState(servicios[0]?.id ?? 0);
    const sinServicioDefinido = lineas.length === 0;
    const caja = useRef<HTMLInputElement>(null);

    const enviar = (
        datos: Record<string, string | number | boolean | null>,
        alTerminar?: () => void,
    ) => {
        setEnviando(true);
        router.post(rutas.agregar, datos, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                alTerminar?.();
                caja.current?.focus();
            },
            onError: (errores) => toast.error(errorDe(errores)),
            onFinish: () => setEnviando(false),
        });
    };

    const escanear = (event: FormEvent) => {
        event.preventDefault();
        const valor = codigo.trim();

        if (valor === '') {
            return;
        }

        enviar({ numero_serie: valor }, () => setCodigo(''));
    };

    const abrir = (destino: number | 'extra', linea?: LineaCasilleros) => {
        setAbierto(destino);
        setFormulario({
            ...vacio,
            tipo_agente: linea?.agente ?? '',
            capacidad: linea?.capacidad ?? '',
        });
    };

    const corregir = (equipo: EquipoCasillero) => {
        const limpio = (valor: string | null) =>
            !valor || valor === NO_LEGIBLE ? '' : valor;
        setAbierto({ equipo: equipo.id });
        setFormulario({
            marca: limpio(equipo.marca),
            serie_fabricante: limpio(equipo.serie_fabricante),
            anio_fabricacion: limpio(equipo.anio_fabricacion),
            tipo_agente: limpio(equipo.tipo_agente),
            capacidad: limpio(equipo.capacidad),
        });
    };

    const guardarManual = (event: FormEvent) => {
        event.preventDefault();

        if (abierto !== null && typeof abierto === 'object') {
            setEnviando(true);
            router.patch(rutas.editar(abierto.equipo), formulario, {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => setAbierto(null),
                onError: (errores) => toast.error(errorDe(errores)),
                onFinish: () => setEnviando(false),
            });

            return;
        }

        const extra = abierto === 'extra';
        enviar(
            {
                ...(extra
                    ? sinServicioDefinido
                        ? {}
                        : { adicional: true, service_id: servicioExtra }
                    : { service_order_line_id: abierto }),
                ...Object.fromEntries(
                    Object.entries(formulario).filter(([, v]) => v !== ''),
                ),
            },
            () => setAbierto(null),
        );
    };

    // Una sola lista: su orden es el del certificado del cliente.
    const soltar = ({ active, over }: DragEndEvent) => {
        const desde = equipos.findIndex((e) => e.id === active.id);
        const hasta = equipos.findIndex((e) => e.id === over?.id);

        if (desde < 0 || hasta < 0 || desde === hasta) {
            return;
        }

        const nuevo = arrayMove(equipos, desde, hasta);
        setOrden(nuevo);
        router.patch(
            rutas.reordenar,
            { equipos: nuevo.map((e) => e.id) },
            {
                preserveScroll: true,
                preserveState: true,
                onError: (errores) => {
                    setOrden(casilleros.equipos);
                    toast.error(errorDe(errores));
                },
            },
        );
    };

    const anuncios = {
        onDragStart: ({ active }: { active: { id: unknown } }) =>
            `Tomaste ${serie(equipos, active.id)}.`,
        onDragOver: ({ over }: { over: { id: unknown } | null }) =>
            over ? `Sobre la posición de ${serie(equipos, over.id)}.` : '',
        onDragEnd: ({ active }: { active: { id: unknown } }) =>
            `Soltaste ${serie(equipos, active.id)}.`,
        onDragCancel: () => 'Se canceló: el orden no cambió.',
    };

    const quitar = (equipo: EquipoCasillero) =>
        router.delete(rutas.quitar(equipo.id), {
            preserveScroll: true,
            preserveState: true,
            onError: (errores) => toast.error(errorDe(errores)),
        });

    const completo = esperados > 0 && identificados >= esperados;

    const formularioManual = (linea?: LineaCasilleros) => (
        <form
            onSubmit={guardarManual}
            className="bg-muted/40 border-border mt-2 grid gap-2 rounded-xl border p-3 sm:grid-cols-3"
        >
            {abierto === 'extra' && !sinServicioDefinido ? (
                <label className="text-muted-foreground flex flex-col gap-1 text-xs sm:col-span-3">
                    Servicio del extintor no cotizado
                    <NativeSelect
                        value={servicioExtra}
                        onChange={(e) =>
                            setServicioExtra(Number(e.target.value))
                        }
                        className="text-foreground h-11 text-sm sm:h-9"
                    >
                        {servicios.map((s) => (
                            <option key={s.id} value={s.id}>
                                {s.nombre}
                            </option>
                        ))}
                    </NativeSelect>
                </label>
            ) : null}
            <label className="text-muted-foreground flex flex-col gap-1 text-xs">
                Agente
                <div className="flex gap-1.5">
                    <NativeSelect
                        value={formulario.tipo_agente}
                        onChange={(e) =>
                            setFormulario({
                                ...formulario,
                                tipo_agente: e.target.value,
                            })
                        }
                        className="text-foreground h-11 min-w-0 flex-1 text-sm sm:h-9"
                    >
                        <OpcionesAgente
                            vacia={linea?.agente ?? 'Del servicio'}
                        />
                    </NativeSelect>
                    <BotonAgentes />
                </div>
            </label>
            <label className="text-muted-foreground flex flex-col gap-1 text-xs">
                Capacidad
                <Input
                    value={formulario.capacidad}
                    placeholder="6 kg"
                    onChange={(e) =>
                        setFormulario({
                            ...formulario,
                            capacidad: e.target.value,
                        })
                    }
                    className="h-11 text-sm sm:h-9"
                />
            </label>
            <div className="text-muted-foreground flex flex-col gap-1 text-xs">
                Marca
                <CampoMarca
                    value={formulario.marca}
                    onChange={(marca) =>
                        setFormulario({ ...formulario, marca })
                    }
                />
            </div>
            <label className="text-muted-foreground flex flex-col gap-1 text-xs">
                Serie de fábrica
                <Input
                    value={formulario.serie_fabricante}
                    onChange={(e) =>
                        setFormulario({
                            ...formulario,
                            serie_fabricante: e.target.value,
                        })
                    }
                    className="h-11 text-sm sm:h-9"
                />
            </label>
            <label className="text-muted-foreground flex flex-col gap-1 text-xs">
                Año de fabricación
                <Input
                    value={formulario.anio_fabricacion}
                    inputMode="numeric"
                    maxLength={4}
                    placeholder="2024"
                    onChange={(e) =>
                        setFormulario({
                            ...formulario,
                            anio_fabricacion: e.target.value.replace(/\D/g, ''),
                        })
                    }
                    className="h-11 text-sm sm:h-9"
                />
            </label>
            <div className="flex items-end gap-2 sm:col-span-3 sm:justify-end">
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => setAbierto(null)}
                    className="h-11 sm:h-9"
                >
                    Cancelar
                </Button>
                <Button
                    type="submit"
                    loading={enviando}
                    className="h-11 sm:h-9"
                >
                    {typeof abierto === 'object' && abierto !== null
                        ? 'Guardar cambios'
                        : 'Guardar extintor'}
                </Button>
            </div>
            <p className="text-muted-foreground text-xs sm:col-span-3">
                {typeof abierto === 'object' && abierto !== null
                    ? 'Lo que dejes vacío queda como «No legible».'
                    : 'Lo que no se lea queda como «No legible» y se corrige después. Al guardar recibe su código BF-EQ.'}
            </p>
        </form>
    );

    const lineaDe = (equipo: EquipoCasillero) =>
        lineas.find((l) => l.id === equipo.linea_id);
    const pendientes = lineas
        .map((linea) => ({
            linea,
            libres: Math.max(
                linea.cantidad -
                    equipos.filter((e) => e.linea_id === linea.id).length,
                0,
            ),
        }))
        .filter((p) => p.libres > 0);

    return (
        <DndContext
            sensors={sensores}
            collisionDetection={closestCenter}
            onDragEnd={soltar}
            accessibility={{
                announcements: anuncios,
                screenReaderInstructions: {
                    draggable:
                        'Para mover un extintor, presiona Espacio, muévelo con las flechas y presiona Espacio para soltarlo. Escape cancela.',
                },
            }}
        >
            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="text-foreground text-sm font-semibold">
                        {identificados} de {esperados} extintores registrados
                    </p>
                    <div className="bg-muted h-2 min-w-32 flex-1 overflow-hidden rounded-full sm:max-w-xs">
                        <div
                            className={`h-full rounded-full transition-all ${completo ? 'bg-success-strong' : 'bg-primary'}`}
                            style={{
                                width: `${esperados > 0 ? Math.min((identificados / esperados) * 100, 100) : 0}%`,
                            }}
                        />
                    </div>
                </div>

                {editable ? (
                    <form
                        onSubmit={escanear}
                        className="flex flex-col gap-2 sm:flex-row"
                    >
                        <div className="border-border bg-card focus-within:border-ring focus-within:ring-ring/50 flex min-h-12 flex-1 items-center gap-2 rounded-xl border px-3 focus-within:ring-[3px]">
                            <ScanBarcode className="text-muted-foreground size-5 shrink-0" />
                            <input
                                ref={caja}
                                aria-label="Escanea el sticker BF-EQ o escribe la serie de fábrica"
                                value={codigo}
                                onChange={(e) => setCodigo(e.target.value)}
                                placeholder="Escanea el sticker BF-EQ o escribe la serie de fábrica"
                                className="text-foreground placeholder:text-muted-foreground h-full min-w-0 flex-1 bg-transparent text-sm outline-none"
                            />
                        </div>
                        <Button
                            type="submit"
                            loading={enviando}
                            disabled={codigo.trim() === ''}
                            className="h-12 px-5"
                        >
                            Agregar
                        </Button>
                    </form>
                ) : null}

                {pendientes.length > 0 ? (
                    <section className="border-border rounded-2xl border border-dashed p-4">
                        <h3 className="text-foreground mb-1 text-sm font-bold">
                            Por identificar
                        </h3>
                        <ul>
                            {pendientes.map(({ linea, libres }) => (
                                <li
                                    key={linea.id}
                                    className="border-border border-t py-2.5 first:border-t-0"
                                >
                                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                                        <Circle className="text-muted-foreground size-4 shrink-0" />
                                        <p className="text-muted-foreground min-w-0 flex-1 text-sm">
                                            <span className="text-foreground font-semibold">
                                                {linea.servicio}
                                            </span>{' '}
                                            · faltan {libres} de{' '}
                                            {linea.cantidad}
                                        </p>
                                        {editable && abierto !== linea.id ? (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() =>
                                                    abrir(linea.id, linea)
                                                }
                                                className="h-11 sm:h-9"
                                            >
                                                Registrar a mano
                                            </Button>
                                        ) : null}
                                    </div>
                                    {abierto === linea.id
                                        ? formularioManual(linea)
                                        : null}
                                </li>
                            ))}
                        </ul>
                    </section>
                ) : null}

                {equipos.length > 0 ? (
                    <section className="border-border bg-card rounded-2xl border p-4">
                        <h3 className="text-foreground mb-1 text-sm font-bold">
                            Orden del certificado
                        </h3>
                        <SortableContext
                            items={equipos.map((e) => e.id)}
                            strategy={verticalListSortingStrategy}
                        >
                            <ul>
                                {equipos.map((equipo, indice) => (
                                    <FilaEquipo
                                        key={equipo.id}
                                        equipo={equipo}
                                        posicion={indice + 1}
                                        linea={lineaDe(equipo)}
                                        editable={editable}
                                        acciones={accionesEquipo?.(equipo)}
                                        onEditar={() => corregir(equipo)}
                                        onQuitar={() => quitar(equipo)}
                                    >
                                        {typeof abierto === 'object' &&
                                        abierto?.equipo === equipo.id
                                            ? formularioManual(lineaDe(equipo))
                                            : null}
                                    </FilaEquipo>
                                ))}
                            </ul>
                        </SortableContext>
                    </section>
                ) : null}

                {editable ? (
                    abierto === 'extra' ? (
                        formularioManual()
                    ) : (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => abrir('extra')}
                            className="h-11 self-start sm:h-9"
                        >
                            <PackagePlus className="size-4" />
                            {sinServicioDefinido
                                ? 'Registrar extintor'
                                : 'Extintor no cotizado'}
                        </Button>
                    )
                ) : null}
                {editable && equipos.length > 1 ? (
                    <p className="text-muted-foreground text-xs">
                        Arrastra desde los puntos para cambiar el orden: es el
                        mismo del certificado.
                    </p>
                ) : null}
            </div>
        </DndContext>
    );
}
