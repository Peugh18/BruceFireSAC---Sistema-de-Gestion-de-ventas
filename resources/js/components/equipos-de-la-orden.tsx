import { router } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    CheckCircle2,
    Circle,
    PackagePlus,
    ScanBarcode,
    Trash2,
} from 'lucide-react';
import { useRef, useState } from 'react';
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
    const { lineas, equipos, esperados, identificados, editable, servicios } =
        casilleros;
    const [codigo, setCodigo] = useState('');
    const [enviando, setEnviando] = useState(false);
    const [abierto, setAbierto] = useState<number | 'extra' | null>(null);
    const [formulario, setFormulario] = useState<Formulario>(vacio);
    const [servicioExtra, setServicioExtra] = useState(servicios[0]?.id ?? 0);
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

    const guardarManual = (event: FormEvent) => {
        event.preventDefault();
        const extra = abierto === 'extra';
        enviar(
            {
                ...(extra
                    ? { adicional: true, service_id: servicioExtra }
                    : { service_order_line_id: abierto }),
                ...Object.fromEntries(
                    Object.entries(formulario).filter(([, v]) => v !== ''),
                ),
            },
            () => setAbierto(null),
        );
    };

    const mover = (indice: number, paso: -1 | 1) => {
        const ids = equipos.map((e) => e.id);
        const destino = indice + paso;

        if (destino < 0 || destino >= ids.length) {
            return;
        }

        [ids[indice], ids[destino]] = [ids[destino], ids[indice]];
        router.patch(
            rutas.reordenar,
            { equipos: ids },
            {
                preserveScroll: true,
                preserveState: true,
                onError: (errores) => toast.error(errorDe(errores)),
            },
        );
    };

    const quitar = (equipo: EquipoCasillero) =>
        router.delete(rutas.quitar(equipo.id), {
            preserveScroll: true,
            preserveState: true,
            onError: (errores) => toast.error(errorDe(errores)),
        });

    const posicionDe = (id: number) => equipos.findIndex((e) => e.id === id);
    const completo = esperados > 0 && identificados >= esperados;

    const formularioManual = (linea?: LineaCasilleros) => (
        <form
            onSubmit={guardarManual}
            className="bg-muted/40 border-border mt-2 grid gap-2 rounded-xl border p-3 sm:grid-cols-3"
        >
            {abierto === 'extra' ? (
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
            <label className="text-muted-foreground flex flex-col gap-1 text-xs">
                Marca
                <Input
                    value={formulario.marca}
                    autoFocus
                    onChange={(e) =>
                        setFormulario({ ...formulario, marca: e.target.value })
                    }
                    className="h-11 text-sm sm:h-9"
                />
            </label>
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
                    Guardar extintor
                </Button>
            </div>
            <p className="text-muted-foreground text-xs sm:col-span-3">
                Lo que no se lea queda como «No legible» y se corrige después.
                Al guardar recibe su código BF-EQ.
            </p>
        </form>
    );

    const filaEquipo = (equipo: EquipoCasillero) => {
        const indice = posicionDe(equipo.id);

        return (
            <li
                key={equipo.id}
                className="border-border flex flex-wrap items-center gap-x-3 gap-y-1 border-t py-2.5 first:border-t-0"
            >
                <span className="text-muted-foreground w-6 text-right font-['IBM_Plex_Mono',monospace] text-xs">
                    {indice + 1}
                </span>
                <CheckCircle2 className="text-success-strong size-4 shrink-0" />
                <div className="min-w-0 flex-1">
                    <p className="text-foreground font-['IBM_Plex_Mono',monospace] text-sm font-semibold">
                        {equipo.numero_serie}
                    </p>
                    <p className="text-muted-foreground truncate text-xs">
                        {dato(equipo.tipo_agente)} · {dato(equipo.capacidad)} ·{' '}
                        {dato(equipo.marca)} · Serie{' '}
                        {dato(equipo.serie_fabricante)} · Año{' '}
                        {dato(equipo.anio_fabricacion)}
                    </p>
                </div>
                {accionesEquipo?.(equipo)}
                {editable ? (
                    <div className="flex items-center gap-1">
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label={`Subir ${equipo.numero_serie}`}
                            disabled={indice === 0}
                            onClick={() => mover(indice, -1)}
                            className="size-11 sm:size-9"
                        >
                            <ArrowUp className="size-4" />
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label={`Bajar ${equipo.numero_serie}`}
                            disabled={indice === equipos.length - 1}
                            onClick={() => mover(indice, 1)}
                            className="size-11 sm:size-9"
                        >
                            <ArrowDown className="size-4" />
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label={`Quitar ${equipo.numero_serie} de la orden`}
                            onClick={() => quitar(equipo)}
                            className="text-destructive-strong size-11 sm:size-9"
                        >
                            <Trash2 className="size-4" />
                        </Button>
                    </div>
                ) : null}
            </li>
        );
    };

    const sinLinea = equipos.filter((e) => e.linea_id === null);

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-foreground text-sm font-semibold">
                    {identificados} de {esperados} extintores identificados
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

            {lineas.map((linea) => {
                const asignados = equipos.filter(
                    (e) => e.linea_id === linea.id,
                );
                const libres = Math.max(linea.cantidad - asignados.length, 0);

                return (
                    <section
                        key={linea.id}
                        className="border-border bg-card rounded-2xl border p-4"
                    >
                        <header className="mb-1 flex flex-wrap items-baseline justify-between gap-2">
                            <h3 className="text-foreground text-sm font-bold">
                                {linea.servicio}
                                {linea.adicional ? (
                                    <span className="text-warning-strong ml-2 text-xs font-semibold">
                                        No cotizado · se cobra aparte
                                    </span>
                                ) : null}
                            </h3>
                            <span className="text-muted-foreground text-xs font-semibold">
                                {asignados.length} de {linea.cantidad}
                            </span>
                        </header>
                        <ul>
                            {asignados.map(filaEquipo)}
                            {Array.from({ length: libres }).map((_, i) => (
                                <li
                                    key={`libre-${i}`}
                                    className="border-border border-t py-2.5 first:border-t-0"
                                >
                                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                                        <span className="w-6" />
                                        <Circle className="text-muted-foreground size-4 shrink-0" />
                                        <p className="text-muted-foreground min-w-0 flex-1 text-sm">
                                            Por identificar ·{' '}
                                            {linea.agente ??
                                                'agente del servicio'}{' '}
                                            {linea.capacidad ?? ''}
                                        </p>
                                        {editable &&
                                        abierto !== linea.id &&
                                        i === 0 ? (
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
                                    {abierto === linea.id && i === 0
                                        ? formularioManual(linea)
                                        : null}
                                </li>
                            ))}
                        </ul>
                    </section>
                );
            })}

            {sinLinea.length > 0 ? (
                <section className="border-border bg-card rounded-2xl border p-4">
                    <h3 className="text-foreground mb-1 text-sm font-bold">
                        Otros extintores de la orden
                    </h3>
                    <ul>{sinLinea.map(filaEquipo)}</ul>
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
                        Extintor no cotizado
                    </Button>
                )
            ) : null}
        </div>
    );
}
