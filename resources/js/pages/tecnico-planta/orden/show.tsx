import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Check,
    CheckCircle2,
    CircleX,
    FileCheck2,
    FireExtinguisher,
    Printer,
    UserRound,
    Wrench,
} from 'lucide-react';
import { createContext, useContext, useEffect, useRef, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { toast } from 'sonner';

import ChatFlotante from '@/components/chat-flotante';
import ChecklistPorPartes from '@/components/checklist-por-partes';
import { tipoDeExtintor } from '@/components/extintor-por-partes';
import type { ConversacionProps } from '@/components/conversacion-orden';
import DeficienciaSuelta from '@/components/deficiencia-suelta';
import EquiposDeLaOrden, {
    type Casilleros,
    type EquipoCasillero,
} from '@/components/equipos-de-la-orden';
import InputError from '@/components/input-error';
import SelectorFormatoStickers, {
    type FormatoStickers,
} from '@/components/formato-stickers';
import SubirEvidencia, {
    type EvidenciaListada,
} from '@/components/subir-evidencia';
import { NOMBRES_PASOS, type PasoTaller } from '@/components/pasos-taller';
import TomarOrden, { type AsignacionOrden } from '@/components/tomar-orden';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { NativeSelect } from '@/components/ui/native-select';
import TecnicoPlantaLayout from '@/layouts/tecnico-planta-layout';
import { usePlantaPerfil } from '@/hooks/use-planta-perfil';
import { dashboard } from '@/routes/tecnico-planta';
import ChecklistRoutes from '@/routes/tecnico-planta/checklist';
import DeficienciasRoutes from '@/routes/tecnico-planta/deficiencias';
import EjecucionRoutes from '@/routes/tecnico-planta/ejecucion';
import RecepcionesRoutes from '@/routes/tecnico-planta/recepciones';
import EquiposRoutes from '@/routes/tecnico-planta/recepciones/equipos';
import type { Team } from '@/types';

type Revision = EquipoCasillero & {
    es_co2: boolean;
    no_apto: boolean;
    checklist: 'conforme' | 'observado' | null;
};

type Deficiencia = {
    id: number;
    componente: string;
    condicion: string;
    estado:
        | 'detectada'
        | 'esperando_autorizacion'
        | 'autorizada'
        | 'rechazada'
        | 'resuelta';
    serie: string | null;
    repuesto_sugerido: string | null;
    resolucion: string | null;
    autorizado_por: string | null;
    canal: string | null;
    cotizado: { product_id: number; cantidad: number } | null;
    creada: string | null;
};

type Repuesto = {
    id: number;
    codigo: string | null;
    nombre: string;
    stock: number;
};

interface Props {
    asignacion: AsignacionOrden;
    paso: PasoTaller;
    order: {
        id: number;
        codigo: string;
        cliente: string;
        cliente_doc: string | null;
        referencia: string | null;
        direccion: string | null;
        telefono: string | null;
        tipo_servicio: string;
        estado: string;
        prioridad: string;
        observaciones: string | null;
    };
    casilleros: Casilleros;
    evidenciasRecojo: EvidenciaListada[];
    revision: Revision[];
    deficiencias: Deficiencia[];
    repuestos: Repuesto[];
    certificados: {
        id: number;
        numero: string;
        tipo: string | null;
    }[];
    conversacion: ConversacionProps;
}

const TITULOS = [
    'Recibir los extintores',
    'Checklist del extintor',
    'Hallazgos',
    'Reparar lo autorizado',
    'Cerrar la orden',
];

const ESTADOS_DE_TRABAJO = [
    'recibido_planta',
    'autorizado',
    'en_proceso',
    'trabajo_terminado',
    'pendiente_datos',
    'datos_completos',
];

const errorDe = (errores: Record<string, string>) =>
    Object.values(errores)[0] ?? 'No se pudo guardar.';

function hace(fecha: string | null): string {
    if (!fecha) {
        return '';
    }

    const minutos = Math.max(
        0,
        Math.round((Date.now() - new Date(fecha).getTime()) / 60000),
    );

    if (minutos < 60) {
        return `hace ${minutos} min`;
    }

    const horas = Math.round(minutos / 60);

    return horas < 24 ? `hace ${horas} h` : `hace ${Math.round(horas / 24)} d`;
}

const ETIQUETA_DEFICIENCIA: Record<
    Deficiencia['estado'],
    { texto: string; clase: string }
> = {
    esperando_autorizacion: {
        texto: 'Espera al vendedor',
        clase: 'bg-amber-500/15 text-warning-strong',
    },
    autorizada: {
        texto: 'Autorizada',
        clase: 'bg-emerald-500/15 text-success-strong',
    },
    detectada: {
        texto: 'Para reparar',
        clase: 'bg-primary/10 text-primary-strong',
    },
    rechazada: {
        texto: 'Rechazada',
        clase: 'bg-muted text-muted-foreground',
    },
    resuelta: {
        texto: 'Reparada',
        clase: 'bg-emerald-500/15 text-success-strong',
    },
};

/** Barra de acción principal pegada abajo de la pantalla. */
const ControlesOrden = createContext<ReactNode>(null);

function PerfilOrden() {
    const { abrirPerfil } = usePlantaPerfil();

    return (
        <button
            type="button"
            onClick={abrirPerfil}
            aria-label="Abrir perfil"
            className="border-border bg-card text-foreground focus-visible:ring-ring flex size-11 shrink-0 items-center justify-center rounded-full border shadow-sm focus-visible:ring-2 focus-visible:outline-none"
        >
            <UserRound className="size-5" aria-hidden="true" />
        </button>
    );
}

function BarraAccion({
    children,
    compacta = false,
}: {
    children: ReactNode;
    compacta?: boolean;
}) {
    const controles = useContext(ControlesOrden);
    const { contenedorPie } = usePlantaPerfil();
    if (!contenedorPie) {
        return null;
    }
    return createPortal(
        <div className="border-border bg-card/95 rounded-t-3xl border-t px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] shadow-[0_-4px_20px_rgb(0_0_0/0.04)] backdrop-blur-xl">
            <div
                className={
                    compacta
                        ? 'flex items-center gap-3'
                        : 'flex flex-wrap items-center gap-2 sm:flex-nowrap sm:gap-3'
                }
            >
                <div
                    className={
                        compacta
                            ? 'min-w-0 flex-1'
                            : 'w-full space-y-1.5 sm:min-w-0 sm:flex-1'
                    }
                >
                    {children}
                </div>
                <div className="ml-auto flex shrink-0 items-center gap-2">
                    {controles}
                </div>
            </div>
        </div>,
        contenedorPie,
    );
}

const botonPrincipal =
    'bg-primary text-primary-foreground flex min-h-14 w-full items-center justify-center gap-2 rounded-2xl text-base font-bold transition hover:brightness-95 disabled:bg-muted disabled:text-muted-foreground';

export default function OrdenTaller({
    asignacion,
    paso,
    order,
    casilleros,
    revision,
    deficiencias,
    repuestos,
    certificados,
    conversacion,
    evidenciasRecojo,
}: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const equipo = currentTeam?.slug ?? '';
    const ruta = { current_team: equipo, service_order: order.id };
    const [vista, setVista] = useState(paso.numero);

    // Al guardar, la orden se abre sola en el paso que sigue.
    useEffect(() => setVista(paso.numero), [paso.numero]);

    const enEspera = deficiencias.filter(
        (d) => d.estado === 'esperando_autorizacion',
    );
    const porReparar = deficiencias.filter(
        (d) => d.estado === 'detectada' || d.estado === 'autorizada',
    );

    return (
        <TecnicoPlantaLayout ocultarMenu>
            <Head title={`${order.codigo} · Taller`} />
            <ControlesOrden.Provider
                value={
                    <>
                        <ChatFlotante
                            ordenId={order.id}
                            codigo={order.codigo}
                            conversacion={conversacion}
                            inline
                        />
                        <PerfilOrden />
                    </>
                }
            >
                <div>
                    <header className="text-foreground space-y-4">
                        <div className="flex flex-wrap items-center gap-2.5">
                            <Link
                                href={dashboard.url(equipo)}
                                aria-label="Volver al inicio"
                                className="border-border bg-card focus-visible:ring-ring flex size-11 shrink-0 items-center justify-center rounded-xl border shadow-sm focus-visible:ring-2 focus-visible:outline-none"
                            >
                                <ArrowLeft className="size-5" />
                            </Link>
                            <div className="min-w-0 flex-1">
                                <p className="text-primary-strong text-sm font-bold">
                                    {order.codigo}
                                </p>
                            </div>
                            <span className="text-primary-strong shrink-0 text-xs font-semibold">
                                Paso {vista} de 5
                            </span>
                            {enEspera.length > 0 && (
                                <span className="text-warning-strong shrink-0 rounded-full bg-amber-500/15 px-2.5 py-1 text-xs font-bold">
                                    En pausa
                                </span>
                            )}
                        </div>
                        <div className="space-y-1 text-center">
                            <p className="text-xl leading-tight font-bold tracking-tight break-words">
                                {order.cliente}
                            </p>
                            <p className="text-muted-foreground text-sm">
                                {order.tipo_servicio}
                            </p>
                        </div>

                        <ol
                            aria-label="Pasos de la orden"
                            className="grid grid-cols-5 gap-0.5"
                        >
                            {NOMBRES_PASOS.map((nombre, i) => {
                                const numero = i + 1;
                                const hecho =
                                    numero < paso.numero || paso.completa;
                                const actual = numero === paso.numero && !hecho;
                                const espera =
                                    actual && paso.clave === 'hallazgos';

                                return (
                                    <li key={nombre} className="relative">
                                        {i < 4 && (
                                            <span
                                                aria-hidden="true"
                                                className={`absolute top-6 left-1/2 h-0.5 w-full ${hecho ? 'bg-success-strong' : 'bg-border'}`}
                                            />
                                        )}
                                        <button
                                            type="button"
                                            onClick={() => setVista(numero)}
                                            aria-current={
                                                actual ? 'step' : undefined
                                            }
                                            aria-pressed={vista === numero}
                                            className={`focus-visible:ring-ring relative flex min-h-16 w-full flex-col items-center gap-2 rounded-2xl py-2 focus-visible:ring-2 focus-visible:outline-none ${
                                                vista === numero
                                                    ? hecho
                                                        ? 'bg-emerald-500/10'
                                                        : espera
                                                          ? 'bg-amber-500/10'
                                                          : 'bg-primary/5'
                                                    : ''
                                            }`}
                                        >
                                            <span
                                                className={`flex size-9 items-center justify-center rounded-full text-sm font-bold ${
                                                    hecho
                                                        ? 'bg-success-strong text-background'
                                                        : actual
                                                          ? espera
                                                              ? 'bg-amber-500 text-black ring-4 ring-amber-500/15'
                                                              : 'bg-primary text-primary-foreground ring-primary/15 ring-4'
                                                          : 'bg-muted text-muted-foreground ring-background ring-2'
                                                }`}
                                            >
                                                {hecho ? (
                                                    <Check
                                                        className="size-4"
                                                        strokeWidth={3}
                                                        aria-label="hecho"
                                                    />
                                                ) : (
                                                    numero
                                                )}
                                            </span>
                                            <span
                                                className={`text-xs ${
                                                    actual
                                                        ? 'font-bold'
                                                        : 'text-muted-foreground'
                                                }`}
                                            >
                                                {nombre}
                                            </span>
                                        </button>
                                    </li>
                                );
                            })}
                        </ol>
                    </header>
                </div>

                <div className="space-y-4 pt-5">
                    <div>
                        <p
                            className={`sr-only text-xs font-bold tracking-wide ${
                                vista === 3
                                    ? 'text-warning-strong'
                                    : 'text-destructive-strong'
                            }`}
                        >
                            PASO {vista} DE 5
                            {vista === paso.numero && !paso.completa
                                ? ` · ${paso.detalle.toUpperCase()}`
                                : ''}
                        </p>
                        <h1 className="text-foreground text-xl font-bold">
                            {TITULOS[vista - 1]}
                        </h1>
                    </div>

                    <TomarOrden asignacion={asignacion} />

                    {order.referencia && (
                        <p className="border-border bg-card rounded-2xl border px-4 py-3 text-sm">
                            <span className="font-semibold">
                                Lugar de devolución:{' '}
                            </span>
                            {order.referencia}
                        </p>
                    )}
                    {!order.cliente_doc && (
                        <p className="text-warning-strong rounded-2xl bg-amber-500/10 px-4 py-3 text-sm">
                            DNI/RUC pendiente. Puedes trabajar; el vendedor debe
                            completar el mismo cliente antes de certificar.
                        </p>
                    )}
                    {evidenciasRecojo.length > 0 && (
                        <section className="border-border bg-card rounded-2xl border p-4">
                            <SubirEvidencia
                                ordenId={order.id}
                                etapa="recojo"
                                titulo="Así se recogieron los extintores"
                                evidencias={evidenciasRecojo}
                                soloLectura
                            />
                        </section>
                    )}

                    {order.observaciones && (
                        <p className="bg-muted text-foreground rounded-2xl p-3 text-sm leading-relaxed">
                            <span className="font-semibold">
                                Del vendedor:{' '}
                            </span>
                            {order.observaciones}
                        </p>
                    )}

                    {vista === 1 && (
                        <PasoRecibir
                            order={order}
                            casilleros={casilleros}
                            ruta={ruta}
                            onSeguir={() => setVista(2)}
                        />
                    )}
                    {vista === 2 && (
                        <PasoRevisar
                            estado={order.estado}
                            revision={revision}
                            ruta={ruta}
                        />
                    )}
                    {vista === 3 && (
                        <PasoHallazgos
                            ordenId={order.id}
                            deficiencias={deficiencias}
                            equipos={conversacion.equipos}
                            faltaRevisar={revision.some(
                                (r) => r.recibido && !r.checklist,
                            )}
                            hayParaReparar={porReparar.length > 0}
                            enEspera={enEspera.length}
                            irA={setVista}
                        />
                    )}
                    {vista === 4 && (
                        <PasoReparar
                            deficiencias={deficiencias}
                            porReparar={porReparar}
                            repuestos={repuestos}
                            ruta={ruta}
                            onSeguir={() => setVista(5)}
                        />
                    )}
                    {vista === 5 && (
                        <PasoCierre
                            estado={order.estado}
                            cliente={order.cliente}
                            direccion={order.direccion}
                            paso={paso}
                            revision={revision}
                            reparadas={
                                deficiencias.filter(
                                    (d) => d.estado === 'resuelta',
                                ).length
                            }
                            certificados={certificados}
                            ruta={ruta}
                        />
                    )}
                </div>
            </ControlesOrden.Provider>
        </TecnicoPlantaLayout>
    );
}

type Ruta = { current_team: string; service_order: number };

function PasoRecibir({
    order,
    casilleros,
    ruta,
    onSeguir,
}: {
    order: Props['order'];
    casilleros: Casilleros;
    ruta: Ruta;
    onSeguir: () => void;
}) {
    const [formato, setFormato] = useState<FormatoStickers>('rollo');
    const pendiente = order.estado === 'pendiente_recepcion';
    const equipos = casilleros.equipos;
    const anteriores = useRef(equipos.map((e) => e.id));
    const form = useForm({
        equipos_recibidos: equipos.map((e) => e.id),
        diferencias: '',
    });

    // Lo que se escanea después también cuenta como recibido.
    useEffect(() => {
        const ids = equipos.map((e) => e.id);
        const nuevos = ids.filter((id) => !anteriores.current.includes(id));
        anteriores.current = ids;
        form.setData('equipos_recibidos', [
            ...new Set([
                ...form.data.equipos_recibidos.filter((id) => ids.includes(id)),
                ...nuevos,
            ]),
        ]);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [equipos]);

    const alternar = (id: number) =>
        form.setData(
            'equipos_recibidos',
            form.data.equipos_recibidos.includes(id)
                ? form.data.equipos_recibidos.filter((x) => x !== id)
                : [...form.data.equipos_recibidos, id],
        );

    const faltan = equipos.length - form.data.equipos_recibidos.length;

    const confirmar = () => {
        form.post(RecepcionesRoutes.confirm.url(ruta), {
            preserveScroll: true,
            onError: (errores) => toast.error(errorDe(errores)),
        });
    };

    return (
        <div className="space-y-4">
            {!pendiente && (
                <p className="text-success-strong flex items-center gap-2 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3 text-sm font-semibold">
                    <CheckCircle2 className="size-5 shrink-0" />
                    Recibida en planta:{' '}
                    {equipos.filter((e) => e.recibido).length} de{' '}
                    {equipos.length} llegaron.
                </p>
            )}

            <section className="border-border bg-card space-y-3 rounded-2xl border p-3.5">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h2 className="text-foreground text-sm font-bold">
                        Escanea el sticker o la serie de fábrica
                    </h2>
                    {equipos.length > 0 && (
                        <>
                            <SelectorFormatoStickers
                                value={formato}
                                onChange={setFormato}
                            />
                            <a
                                href={RecepcionesRoutes.stickers.url(ruta, {
                                    query: { formato },
                                })}
                                target="_blank"
                                rel="noreferrer"
                                className="border-border text-foreground inline-flex min-h-11 shrink-0 items-center gap-1.5 rounded-xl border px-3 text-xs font-bold"
                            >
                                <Printer className="size-4" />
                                Stickers
                            </a>
                        </>
                    )}
                </div>
                <EquiposDeLaOrden
                    casilleros={casilleros}
                    rutas={{
                        agregar: EquiposRoutes.store.url(ruta),
                        reordenar: EquiposRoutes.reordenar.url(ruta),
                        editar: (equipment) =>
                            EquiposRoutes.update.url({ ...ruta, equipment }),
                        quitar: (equipment) =>
                            EquiposRoutes.destroy.url({ ...ruta, equipment }),
                    }}
                />
            </section>

            {pendiente && equipos.length > 0 && (
                <fieldset className="space-y-2">
                    <legend className="text-foreground mb-2 text-sm font-bold">
                        {form.data.equipos_recibidos.length} de {equipos.length}{' '}
                        llegaron · desmarca los que faltan
                    </legend>
                    {equipos.map((e) => (
                        <label
                            key={e.id}
                            className="border-border bg-card flex min-h-14 items-center gap-3 rounded-2xl border px-3 py-2"
                        >
                            <input
                                type="checkbox"
                                checked={form.data.equipos_recibidos.includes(
                                    e.id,
                                )}
                                onChange={() => alternar(e.id)}
                                className="accent-success-strong size-5"
                            />
                            <span className="min-w-0 flex-1">
                                <span className="text-foreground block font-mono text-sm font-bold">
                                    {e.numero_serie}
                                </span>
                                <span className="text-muted-foreground text-xs">
                                    {[e.tipo_agente, e.capacidad, e.marca]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </span>
                            </span>
                        </label>
                    ))}
                </fieldset>
            )}

            {pendiente && (
                <label className="text-foreground block text-sm font-bold">
                    Si falta alguno, ¿qué pasó?
                    <input
                        value={form.data.diferencias}
                        onChange={(e) =>
                            form.setData('diferencias', e.target.value)
                        }
                        required={faltan > 0}
                        placeholder="Ej.: 2 se quedaron en la sede del cliente"
                        className="border-input bg-card mt-1 min-h-11 w-full rounded-xl border px-3 text-sm font-normal"
                    />
                    <InputError message={form.errors.diferencias} />
                </label>
            )}

            <BarraAccion>
                {pendiente ? (
                    <button
                        type="button"
                        onClick={confirmar}
                        disabled={form.processing || equipos.length === 0}
                        className={botonPrincipal}
                    >
                        Confirmar recepción y continuar
                        <ArrowRight className="size-5" />
                    </button>
                ) : (
                    <button
                        type="button"
                        onClick={onSeguir}
                        className={botonPrincipal}
                    >
                        Ir a revisar
                        <ArrowRight className="size-5" />
                    </button>
                )}
                {pendiente && equipos.length === 0 && (
                    <p className="text-muted-foreground text-center text-xs">
                        Escanea al menos un extintor para recibir la orden.
                    </p>
                )}
            </BarraAccion>
        </div>
    );
}

function PasoRevisar({
    estado,
    revision,
    ruta,
}: {
    estado: string;
    revision: Revision[];
    ruta: Ruta;
}) {
    const recibidos = revision.filter((r) => r.recibido);
    const [elegido, setElegido] = useState<number | null>(null);
    const [repetir, setRepetir] = useState(false);

    if (estado === 'pendiente_recepcion') {
        return (
            <>
                <p className="bg-muted text-foreground rounded-xl p-3 text-sm">
                    Primero recibe la orden: el checklist se hace a lo que
                    llegó.
                </p>
                <BarraAccion compacta>
                    <p className="text-muted-foreground text-sm">
                        Pendiente de recepción
                    </p>
                </BarraAccion>
            </>
        );
    }

    if (recibidos.length === 0) {
        return (
            <>
                <p className="bg-muted text-foreground rounded-xl p-3 text-sm">
                    Aún no hay extintores recibidos. Escanéalos en el paso
                    Recibir.
                </p>
                <BarraAccion compacta>
                    <p className="text-muted-foreground text-sm">
                        Sin extintores recibidos
                    </p>
                </BarraAccion>
            </>
        );
    }

    const indice = Math.max(
        0,
        elegido !== null
            ? recibidos.findIndex((r) => r.id === elegido)
            : recibidos.findIndex((r) => !r.checklist),
    );
    const actual = recibidos[indice];
    const revisados = recibidos.filter((r) => r.checklist).length;
    const ir = (i: number) => {
        setElegido(recibidos[i].id);
        setRepetir(false);
    };

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between text-sm font-bold">
                <span className="text-foreground">
                    Extintor {indice + 1} de {recibidos.length}
                </span>
                <span className="text-muted-foreground">
                    {revisados} revisados
                </span>
            </div>
            <div
                className="grid gap-0.5"
                style={{
                    gridTemplateColumns: `repeat(${recibidos.length}, minmax(0, 1fr))`,
                }}
                aria-hidden="true"
            >
                {recibidos.map((r, i) => (
                    <span
                        key={r.id}
                        className={`h-1.5 rounded-full ${
                            i === indice
                                ? 'bg-primary'
                                : r.checklist
                                  ? 'bg-success-strong'
                                  : 'bg-muted'
                        }`}
                    />
                ))}
            </div>

            <div className="border-border bg-card flex items-center gap-3 rounded-2xl border p-3">
                <div className="min-w-0 flex-1">
                    <p className="text-foreground font-mono text-base font-bold">
                        {actual.numero_serie}
                    </p>
                    <p className="text-muted-foreground text-xs">
                        {[
                            actual.tipo_agente,
                            actual.capacidad,
                            actual.marca,
                            actual.serie_fabricante &&
                                `Serie ${actual.serie_fabricante}`,
                            actual.anio_fabricacion,
                        ]
                            .filter(Boolean)
                            .join(' · ')}
                    </p>
                </div>
                {actual.checklist && (
                    <span
                        className={`rounded-full px-2 py-0.5 text-xs font-bold ${
                            actual.no_apto
                                ? 'bg-destructive/10 text-destructive-strong'
                                : actual.checklist === 'observado'
                                  ? 'text-warning-strong bg-amber-500/15'
                                  : 'text-success-strong bg-emerald-500/15'
                        }`}
                    >
                        {actual.no_apto
                            ? 'No apto'
                            : actual.checklist === 'observado'
                              ? 'Observado'
                              : 'Conforme'}
                    </span>
                )}
            </div>

            {actual.checklist && !repetir ? (
                <div className="space-y-2">
                    <p className="text-muted-foreground text-sm">
                        Este extintor ya está revisado.
                    </p>
                    <BarraAccion compacta>
                        <button
                            type="button"
                            onClick={() => setRepetir(true)}
                            className="border-border text-foreground min-h-11 rounded-xl border px-3 text-sm font-bold"
                        >
                            Volver a revisar
                        </button>
                    </BarraAccion>
                </div>
            ) : (
                <ChecklistPorPartes
                    key={actual.id}
                    url={ChecklistRoutes.store.url({
                        ...ruta,
                        equipment: actual.id,
                    })}
                    serie={actual.numero_serie}
                    tipo={tipoDeExtintor(actual.tipo_agente, actual.capacidad)}
                    textoBoton={
                        revisados + (actual.checklist ? 0 : 1) <
                        recibidos.length
                            ? 'Guardar y siguiente'
                            : 'Guardar y continuar'
                    }
                    onGuardado={() => {
                        setElegido(null);
                        setRepetir(false);
                    }}
                    renderAccion={(accion) => (
                        <BarraAccion compacta>{accion}</BarraAccion>
                    )}
                />
            )}

            <div className="flex gap-2">
                <button
                    type="button"
                    onClick={() => ir(indice - 1)}
                    disabled={indice === 0}
                    className="border-border text-foreground inline-flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-xl border text-sm font-bold disabled:opacity-40"
                >
                    <ArrowLeft className="size-4" />
                    Anterior
                </button>
                <button
                    type="button"
                    onClick={() => ir(indice + 1)}
                    disabled={indice === recibidos.length - 1}
                    className="border-border text-foreground inline-flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-xl border text-sm font-bold disabled:opacity-40"
                >
                    Siguiente
                    <ArrowRight className="size-4" />
                </button>
            </div>
        </div>
    );
}

function TarjetaDeficiencia({
    d,
    children,
}: {
    d: Deficiencia;
    children?: ReactNode;
}) {
    const etiqueta = ETIQUETA_DEFICIENCIA[d.estado];

    return (
        <article
            className={`bg-card space-y-1.5 rounded-2xl border p-3 ${
                d.estado === 'esperando_autorizacion'
                    ? 'border-2 border-amber-500'
                    : 'border-border'
            }`}
        >
            <div className="flex items-start gap-2">
                <h3 className="text-foreground flex-1 text-sm font-bold">
                    {d.componente}
                </h3>
                <span
                    className={`shrink-0 rounded-full px-2 py-0.5 text-xs font-bold ${etiqueta.clase}`}
                >
                    {etiqueta.texto}
                </span>
            </div>
            <p className="text-muted-foreground text-xs">
                {[
                    d.serie,
                    d.condicion,
                    d.repuesto_sugerido && `Repuesto: ${d.repuesto_sugerido}`,
                    d.estado === 'esperando_autorizacion' &&
                        `avisado ${hace(d.creada)}`,
                    d.autorizado_por &&
                        `Autorizó ${d.autorizado_por}${d.canal ? ` por ${d.canal}` : ''}`,
                    d.estado === 'rechazada' && 'Se entrega así',
                    d.resolucion,
                ]
                    .filter(Boolean)
                    .join(' · ')}
            </p>
            {children}
        </article>
    );
}

function PasoHallazgos({
    ordenId,
    deficiencias,
    equipos,
    faltaRevisar,
    hayParaReparar,
    enEspera,
    irA,
}: {
    ordenId: number;
    deficiencias: Deficiencia[];
    equipos: { id: number; serie: string }[];
    faltaRevisar: boolean;
    hayParaReparar: boolean;
    enEspera: number;
    irA: (paso: number) => void;
}) {
    return (
        <div className="space-y-3">
            <p className="text-muted-foreground text-sm">
                Lo que necesita permiso del cliente espera al vendedor. Lo demás
                sigue.
            </p>

            {deficiencias.length === 0 ? (
                <p className="bg-muted text-foreground rounded-xl p-3 text-sm">
                    Sin hallazgos: los extintores revisados salieron conformes.
                </p>
            ) : (
                deficiencias.map((d) => <TarjetaDeficiencia key={d.id} d={d} />)
            )}

            {equipos.length > 0 && (
                <DeficienciaSuelta ordenId={ordenId} equipos={equipos} />
            )}

            {enEspera > 0 && faltaRevisar && (
                <button
                    type="button"
                    onClick={() => irA(2)}
                    className="text-primary-strong min-h-11 text-sm font-bold underline"
                >
                    Mientras esperas, seguir revisando otros extintores
                </button>
            )}

            <BarraAccion>
                {enEspera > 0 && (
                    <p className="text-warning-strong text-center text-xs font-bold">
                        {enEspera === 1
                            ? 'Falta 1 respuesta del vendedor'
                            : `Faltan ${enEspera} respuestas del vendedor`}
                    </p>
                )}
                <button
                    type="button"
                    onClick={() => irA(hayParaReparar ? 4 : 5)}
                    disabled={enEspera > 0 && !hayParaReparar}
                    className={botonPrincipal}
                >
                    {hayParaReparar ? 'Continuar a Reparar' : 'Ir al cierre'}
                    <ArrowRight className="size-5" />
                </button>
            </BarraAccion>
        </div>
    );
}

function FilaReparar({
    d,
    repuestos,
    ruta,
}: {
    d: Deficiencia;
    repuestos: Repuesto[];
    ruta: Ruta;
}) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const form = useForm({
        product_id: d.cotizado ? String(d.cotizado.product_id) : '',
        cantidad: d.cotizado?.cantidad ?? 1,
    });
    const sinRepuesto = useForm({ resolucion: 'Reparado en taller' });
    const opciones = {
        preserveScroll: true,
        onError: (e: Record<string, string>) => toast.error(errorDe(e)),
    };

    const reparar = (e: FormEvent) => {
        e.preventDefault();
        form.post(
            EjecucionRoutes.consumeSpare.url({ ...ruta, deficiency: d.id }),
            opciones,
        );
    };

    return (
        <TarjetaDeficiencia d={d}>
            <form onSubmit={reparar} className="space-y-2 pt-1">
                <div className="flex gap-2">
                    <label className="text-foreground flex-[3] text-xs font-bold">
                        Repuesto usado
                        <NativeSelect
                            value={form.data.product_id}
                            onChange={(e) =>
                                form.setData('product_id', e.target.value)
                            }
                            disabled={d.cotizado !== null}
                            required
                            className="mt-1 min-h-11 w-full rounded-xl text-sm"
                        >
                            <option value="">Elige el repuesto</option>
                            {repuestos.map((p) => (
                                <option
                                    key={p.id}
                                    value={p.id}
                                    disabled={p.stock <= 0}
                                >
                                    {p.nombre}{' '}
                                    {p.stock > 0
                                        ? `(stock ${p.stock})`
                                        : '(sin stock)'}
                                </option>
                            ))}
                        </NativeSelect>
                    </label>
                    <label className="text-foreground flex-1 text-xs font-bold">
                        Cant.
                        <input
                            type="number"
                            min={1}
                            inputMode="numeric"
                            value={form.data.cantidad}
                            onChange={(e) =>
                                form.setData('cantidad', Number(e.target.value))
                            }
                            disabled={d.cotizado !== null}
                            required
                            className="border-input bg-card mt-1 min-h-11 w-full rounded-xl border px-2 text-sm font-normal"
                        />
                    </label>
                </div>
                {d.cotizado && (
                    <p className="text-muted-foreground text-xs">
                        Es el repuesto que el cliente aceptó en la cotización.
                    </p>
                )}
                <button
                    type="submit"
                    disabled={form.processing}
                    className="bg-success-strong text-background min-h-12 w-full rounded-xl text-sm font-bold disabled:opacity-50"
                >
                    Marcar reparado
                </button>
                {!d.repuesto_sugerido && !d.cotizado && (
                    <button
                        type="button"
                        disabled={sinRepuesto.processing}
                        onClick={() =>
                            sinRepuesto.post(
                                DeficienciasRoutes.resolve.url({
                                    current_team: currentTeam?.slug ?? '',
                                    deficiency: d.id,
                                }),
                                opciones,
                            )
                        }
                        className="text-foreground min-h-11 w-full text-sm font-semibold underline"
                    >
                        Se reparó sin usar repuesto
                    </button>
                )}
            </form>
        </TarjetaDeficiencia>
    );
}

function PasoReparar({
    deficiencias,
    porReparar,
    repuestos,
    ruta,
    onSeguir,
}: {
    deficiencias: Deficiencia[];
    porReparar: Deficiencia[];
    repuestos: Repuesto[];
    ruta: Ruta;
    onSeguir: () => void;
}) {
    const reparadas = deficiencias.filter((d) => d.estado === 'resuelta');
    const rechazadas = deficiencias.filter((d) => d.estado === 'rechazada');
    const total = porReparar.length + reparadas.length;

    return (
        <div className="space-y-3">
            <p className="text-muted-foreground text-sm">
                El repuesto sale del almacén al marcarlo reparado.
            </p>
            {total > 0 && (
                <p className="text-foreground text-sm font-bold">
                    {reparadas.length} de {total} reparadas
                </p>
            )}

            {reparadas.map((d) => (
                <TarjetaDeficiencia key={d.id} d={d} />
            ))}
            {porReparar.map((d) => (
                <FilaReparar
                    key={d.id}
                    d={d}
                    repuestos={repuestos}
                    ruta={ruta}
                />
            ))}
            {total === 0 && (
                <p className="bg-muted text-foreground rounded-xl p-3 text-sm">
                    No hay nada que reparar en esta orden.
                </p>
            )}
            {rechazadas.length > 0 && (
                <p className="text-muted-foreground text-sm">
                    {rechazadas.map((d) => d.componente).join(', ')}: rechazado
                    por el cliente, no se repara.
                </p>
            )}

            <BarraAccion>
                <button
                    type="button"
                    onClick={onSeguir}
                    disabled={porReparar.length > 0}
                    className={botonPrincipal}
                >
                    {porReparar.length > 0
                        ? porReparar.length === 1
                            ? 'Falta 1 reparación para cerrar'
                            : `Faltan ${porReparar.length} reparaciones para cerrar`
                        : 'Continuar al cierre'}
                    {porReparar.length === 0 && (
                        <ArrowRight className="size-5" />
                    )}
                </button>
            </BarraAccion>
        </div>
    );
}

function PasoCierre({
    estado,
    cliente,
    direccion,
    paso,
    revision,
    reparadas,
    certificados,
    ruta,
}: {
    estado: string;
    cliente: string;
    direccion: string | null;
    paso: PasoTaller;
    revision: Revision[];
    reparadas: number;
    certificados: Props['certificados'];
    ruta: Ruta;
}) {
    const recibidos = revision.filter((r) => r.recibido);
    const noAptos = recibidos.filter((r) => r.no_apto).length;
    // Extintores a los que se les hizo la prueba hidrostática.
    const [conPh, setConPh] = useState<number[]>([]);
    const [enviando, setEnviando] = useState(false);
    const puedeTerminar =
        ESTADOS_DE_TRABAJO.includes(estado) && paso.clave === 'cierre';

    // El certificado sale completo: sin serie de fábrica o sin la dirección
    // del cliente no se emite; se piden aquí mismo antes de terminar.
    const sinSerie = recibidos.filter(
        (r) =>
            !r.no_apto &&
            (!r.serie_fabricante?.trim() ||
                r.serie_fabricante.startsWith('No legible')),
    );
    const sinDireccion = !direccion?.trim();
    const [aviso, setAviso] = useState(false);
    const [series, setSeries] = useState<Record<number, string>>({});
    const [nuevaDireccion, setNuevaDireccion] = useState('');
    const completo =
        sinSerie.every((r) => series[r.id]?.trim()) &&
        (!sinDireccion || nuevaDireccion.trim() !== '');

    const avanzar = (destino: 'listo_certificado' | 'listo_entrega') => {
        router.post(
            EjecucionRoutes.advance.url(ruta),
            {
                target_state: destino,
                ph_equipos: conPh,
                ...(destino === 'listo_certificado'
                    ? { series, direccion: nuevaDireccion || null }
                    : {}),
            },
            {
                preserveScroll: true,
                onStart: () => setEnviando(true),
                onFinish: () => setEnviando(false),
                onSuccess: () => setAviso(false),
                onError: (e) => toast.error(errorDe(e)),
            },
        );
    };

    const terminar = () =>
        sinSerie.length > 0 || sinDireccion
            ? setAviso(true)
            : avanzar('listo_certificado');

    return (
        <div className="space-y-3">
            <p className="text-muted-foreground text-sm leading-relaxed">
                {paso.completa
                    ? 'Trabajo terminado. Este es el resumen de los extintores recibidos.'
                    : 'Revisa el resumen. Al terminar se generan los certificados de los aptos.'}
            </p>
            <div className="border-border bg-card divide-border grid grid-cols-3 divide-x overflow-hidden rounded-2xl border shadow-sm">
                {[
                    {
                        titulo: 'Aptos',
                        valor: recibidos.length - noAptos,
                        tono: 'text-success-strong',
                        icono: CheckCircle2,
                    },
                    {
                        titulo: 'No aptos',
                        valor: noAptos,
                        tono: 'text-destructive-strong',
                        icono: CircleX,
                    },
                    {
                        titulo: 'Reparaciones',
                        valor: reparadas,
                        tono: 'text-muted-foreground',
                        icono: Wrench,
                    },
                ].map((k) => (
                    <div
                        key={k.titulo}
                        className="flex min-w-0 flex-col items-center gap-1.5 p-3"
                    >
                        <div className="flex items-center gap-2">
                            <k.icono
                                className={`size-5 shrink-0 ${k.tono}`}
                                aria-hidden="true"
                            />
                            <p className="text-foreground text-2xl font-bold tabular-nums">
                                {k.valor}
                            </p>
                        </div>
                        <p className={`text-xs font-semibold ${k.tono}`}>
                            {k.titulo}
                        </p>
                    </div>
                ))}
            </div>

            <h2 className="text-foreground pt-1 text-sm font-bold">
                Extintores
            </h2>
            <ul className="border-border bg-card divide-border divide-y overflow-hidden rounded-2xl border shadow-sm">
                {recibidos.map((r) => (
                    <li
                        key={r.id}
                        className="flex flex-wrap items-center gap-2 p-3"
                    >
                        <FireExtinguisher
                            className="text-primary-strong size-6 shrink-0"
                            aria-hidden="true"
                        />
                        <span className="min-w-0 flex-1">
                            <span className="text-foreground block font-mono text-sm font-bold">
                                {r.numero_serie}
                            </span>
                            <span className="text-muted-foreground text-xs">
                                {[r.tipo_agente, r.capacidad]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </span>
                        </span>
                        {puedeTerminar && !r.no_apto && (
                            <label className="text-foreground flex min-h-11 items-center gap-1.5 text-xs font-semibold">
                                <input
                                    type="checkbox"
                                    checked={conPh.includes(r.id)}
                                    onChange={(e) =>
                                        setConPh((actual) =>
                                            e.target.checked
                                                ? [...actual, r.id]
                                                : actual.filter(
                                                      (id) => id !== r.id,
                                                  ),
                                        )
                                    }
                                    className="size-4"
                                />
                                Se hizo P.H.
                            </label>
                        )}
                        <span
                            className={`flex items-center gap-1 rounded-full px-2.5 py-1.5 text-xs font-bold ${
                                r.no_apto
                                    ? 'bg-destructive/10 text-destructive-strong'
                                    : 'text-success-strong bg-emerald-500/15'
                            }`}
                        >
                            {r.no_apto ? (
                                <CircleX
                                    className="size-3.5"
                                    aria-hidden="true"
                                />
                            ) : (
                                <CheckCircle2
                                    className="size-3.5"
                                    aria-hidden="true"
                                />
                            )}
                            {r.no_apto ? 'No apto' : 'Apto'}
                        </span>
                    </li>
                ))}
            </ul>

            {certificados.length > 0 && (
                <section className="border-border bg-card space-y-2 rounded-2xl border p-3">
                    <h2 className="text-foreground flex items-center gap-1.5 text-sm font-bold">
                        <FileCheck2 className="text-success-strong size-4" />
                        Certificados emitidos ({certificados.length})
                    </h2>
                    {certificados.map((c) => (
                        <p key={c.id} className="text-foreground text-sm">
                            <span className="font-mono font-bold">
                                {c.numero}
                            </span>{' '}
                            <span className="text-muted-foreground">
                                {c.tipo}
                            </span>
                        </p>
                    ))}
                    <p className="text-muted-foreground text-xs">
                        Los imprime el vendedor: al marcar la orden lista para
                        entrega le llega el aviso.
                    </p>
                </section>
            )}

            <Dialog open={aviso} onOpenChange={setAviso}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            Antes de emitir,{' '}
                            {sinSerie.length + (sinDireccion ? 1 : 0) === 1
                                ? 'falta 1 dato'
                                : `faltan ${sinSerie.length + (sinDireccion ? 1 : 0)} datos`}
                        </DialogTitle>
                        <DialogDescription>
                            El certificado sale con todo completo. Llénalos aquí
                            y se guardan.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-3">
                        {sinDireccion ? (
                            <label className="text-foreground block text-sm font-bold">
                                Dirección del cliente · {cliente}
                                <input
                                    value={nuevaDireccion}
                                    onChange={(e) =>
                                        setNuevaDireccion(e.target.value)
                                    }
                                    placeholder="Ej.: Av. España 1245, Trujillo"
                                    className="border-input bg-card mt-1 min-h-11 w-full rounded-xl border px-3 text-sm font-normal"
                                />
                            </label>
                        ) : null}
                        {sinSerie.map((r) => (
                            <label
                                key={r.id}
                                className="text-foreground block text-sm font-bold"
                            >
                                Serie de fábrica ·{' '}
                                <span className="font-mono">
                                    {r.numero_serie}
                                </span>{' '}
                                <span className="text-muted-foreground font-normal">
                                    (
                                    {[r.tipo_agente, r.capacidad, r.marca]
                                        .filter(Boolean)
                                        .join(' · ')}
                                    )
                                </span>
                                <input
                                    value={series[r.id] ?? ''}
                                    onChange={(e) =>
                                        setSeries({
                                            ...series,
                                            [r.id]: e.target.value,
                                        })
                                    }
                                    placeholder="La del cilindro"
                                    className="border-input bg-card mt-1 min-h-11 w-full rounded-xl border px-3 text-sm font-normal"
                                />
                            </label>
                        ))}
                    </div>
                    <div className="flex justify-end gap-2">
                        <button
                            type="button"
                            onClick={() => setAviso(false)}
                            className="border-border text-foreground min-h-11 rounded-xl border px-4 text-sm font-semibold"
                        >
                            Ahora no
                        </button>
                        <button
                            type="button"
                            disabled={!completo || enviando}
                            onClick={() => avanzar('listo_certificado')}
                            className="bg-primary text-primary-foreground min-h-11 rounded-xl px-4 text-sm font-bold disabled:opacity-50"
                        >
                            Guardar y emitir certificados
                        </button>
                    </div>
                </DialogContent>
            </Dialog>

            <BarraAccion
                compacta={paso.completa && estado !== 'listo_certificado'}
            >
                {estado === 'listo_certificado' ? (
                    <button
                        type="button"
                        onClick={() => avanzar('listo_entrega')}
                        disabled={enviando}
                        className={botonPrincipal}
                    >
                        <CheckCircle2 className="size-5" />
                        Marcar lista para entrega
                    </button>
                ) : paso.completa ? (
                    <div className="flex items-center gap-2.5 py-1">
                        <span className="bg-success-strong text-background flex size-10 shrink-0 items-center justify-center rounded-full">
                            <Check className="size-5" aria-hidden="true" />
                        </span>
                        <div>
                            <p className="text-success-strong text-sm font-bold">
                                Lista para entrega
                            </p>
                            <p className="text-muted-foreground text-xs leading-snug">
                                El vendedor ya tiene el aviso.
                            </p>
                        </div>
                    </div>
                ) : (
                    <>
                        {!puedeTerminar && (
                            <p className="text-warning-strong text-center text-xs font-bold">
                                Primero: {NOMBRES_PASOS[paso.numero - 1]} —{' '}
                                {paso.detalle}
                            </p>
                        )}
                        <button
                            type="button"
                            onClick={terminar}
                            disabled={enviando || !puedeTerminar}
                            className={botonPrincipal}
                        >
                            Terminar y emitir certificados
                        </button>
                    </>
                )}
            </BarraAccion>
        </div>
    );
}
