import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import {
    ArrowLeft,
    CheckCircle2,
    Clock,
    MessageCircle,
    PencilLine,
    Send,
} from 'lucide-react';
import { useState } from 'react';

import ServiceOrderController from '@/actions/App/Http/Controllers/Vendedor/ServiceOrderController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import comunicacion from '@/routes/vendedor/comunicacion';
import ordenes from '@/routes/vendedor/ordenes-servicio';
import VendedorLayout from '@/layouts/vendedor-layout';
import type { Team } from '@/types';
import OpcionesAgente from '@/components/opciones-agente';
import EntregaMostradorRoutes from '@/routes/vendedor/ordenes-servicio/entrega-mostrador';
import EquiposRoutes from '@/routes/vendedor/ordenes-servicio/equipos';
import VentasRoutes from '@/routes/vendedor/ventas';

export type ServiceOrderEvent = {
    id: number;
    tipo: string;
    payload?: {
        mensaje?: string;
        resultado?: string;
        [key: string]: unknown;
    };
    created_at: string;
    user?: {
        name: string;
    };
};

export type ServiceOrderDetails = {
    id: number;
    codigo: string;
    service?: { nombre: string } | null;
    service_id?: number | null;
    fecha: string;
    prioridad: string;
    departamento_tecnico?: string | null;
    estado: string;
    observaciones?: string | null;
    created_at: string;
    client?: {
        id: number;
        razon_social: string;
        numero_documento?: string;
        telefono?: string;
        direccion_fiscal?: string;
    };
    tecnico?: {
        id: number;
        name: string;
        email?: string;
    } | null;
    sale_id?: number | null;
    equipments: Array<{
        id: number;
        numero_serie: string;
        capacidad?: string;
        tipo_agente?: string;
        marca?: string;
    }>;
    events: ServiceOrderEvent[];
};

export type Props = {
    serviceOrder: ServiceOrderDetails;
    tecnicos: { id: number; name: string }[];
    services: { id: number; nombre: string }[];
};

/** Cada estado técnico dicho como lo entiende la vendedora. */
const ESTADOS: Record<string, string> = {
    pendiente_recepcion: 'Por recibir en taller',
    recibido_planta: 'Recibido en taller',
    en_revision: 'En revisión',
    esperando_autorizacion: 'Espera tu autorización',
    autorizado: 'Autorizado',
    en_proceso: 'En trabajo',
    trabajo_terminado: 'Trabajo terminado',
    pendiente_datos: 'Faltan datos del certificado',
    datos_completos: 'Datos completos',
    listo_certificado: 'Certificado listo',
    listo_entrega: 'Listo para entregar',
    entregado: 'Entregado',
    cerrado: 'Cerrado',
    anulada: 'Anulada',
};

/** Hasta aquí se puede anular: con el certificado emitido ya no. */
const ANULABLES = [
    'pendiente_recepcion',
    'recibido_planta',
    'en_revision',
    'esperando_autorizacion',
    'autorizado',
    'en_proceso',
    'trabajo_terminado',
    'pendiente_datos',
    'datos_completos',
];

const PRIORIDADES: Record<string, string> = {
    normal: 'Normal',
    alta: 'Alta',
    urgente: 'Urgente',
};

/** Título del evento de la bitácora en palabras simples. */
function tituloDeEvento(evt: ServiceOrderEvent): string {
    const accion =
        typeof evt.payload?.accion === 'string' ? evt.payload.accion : '';
    const porAccion: Record<string, string> = {
        tecnico_asignado: 'Técnico asignado',
        orden_tomada: 'El técnico tomó la orden',
        orden_editada: 'Orden editada por ventas',
        entrega_final_realizada: 'Entregado al cliente',
        consumo_repuesto_kardex: 'Repuesto instalado',
        alta_tecnica_rapida: 'Extintor registrado',
    };
    const porTipo: Record<string, string> = {
        creada: 'Orden creada',
        recibida: 'Recibida en taller',
        deficiencia_detectada: 'El técnico encontró un problema',
        autorizacion_pendiente: 'Espera tu autorización',
        notificacion_vendedor: 'Aviso para ventas',
        trabajo_completado: 'Trabajo terminado',
    };

    if (porAccion[accion]) {
        return porAccion[accion];
    }

    if (evt.payload?.origen === 'vendedor') {
        return 'Indicación de ventas';
    }

    return porTipo[evt.tipo] ?? 'Avance del técnico';
}

function soloFecha(valor: string): string {
    return valor ? valor.slice(0, 10) : '';
}

function fechaLegible(valor: string): string {
    const fecha = soloFecha(valor);

    return fecha
        ? new Date(`${fecha}T00:00:00`).toLocaleDateString('es-PE', {
              weekday: 'long',
              day: 'numeric',
              month: 'long',
              year: 'numeric',
          })
        : '—';
}

const STEPS = [
    { key: 'asignada', label: '1. Asignada' },
    { key: 'en_proceso', label: '2. En proceso' },
    { key: 'completada', label: '3. Completada' },
    { key: 'cerrada', label: '4. Cerrada' },
];

function getCoarseStep(estado: string): number {
    const map: Record<string, number> = {
        pendiente_recepcion: 1,
        recibido_planta: 1,
        en_revision: 1,
        esperando_autorizacion: 1,
        autorizado: 2,
        en_proceso: 2,
        trabajo_terminado: 2,
        pendiente_datos: 2,
        datos_completos: 2,
        listo_certificado: 3,
        listo_entrega: 3,
        entregado: 3,
        cerrado: 4,
    };
    return map[estado] ?? 1;
}

export default function ServiceOrderShow({
    serviceOrder,
    tecnicos,
    services,
}: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug =
        currentTeam?.slug ||
        (typeof window !== 'undefined'
            ? window.location.pathname.split('/')[1]
            : '');

    const currentStep = getCoarseStep(serviceOrder.estado);
    const editable = !['entregado', 'cerrado', 'anulada'].includes(
        serviceOrder.estado,
    );
    const [anulando, setAnulando] = useState(false);
    const [motivoAnulacion, setMotivoAnulacion] = useState('');
    const [errorAnulacion, setErrorAnulacion] = useState<string | null>(null);

    const anularOrden = () => {
        router.post(
            ordenes.anular.url({
                current_team: teamSlug,
                service_order: serviceOrder.id,
            }),
            { motivo: motivoAnulacion },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setAnulando(false);
                    setMotivoAnulacion('');
                },
                onError: (errores) =>
                    setErrorAnulacion(errores.motivo ?? 'No se pudo anular.'),
            },
        );
    };
    const [editando, setEditando] = useState(false);
    const [tecnicoElegido, setTecnicoElegido] = useState('');
    const edicion = useForm({
        service_id: serviceOrder.service_id
            ? String(serviceOrder.service_id)
            : '',
        fecha: soloFecha(serviceOrder.fecha),
        prioridad: serviceOrder.prioridad || 'normal',
        departamento_tecnico: serviceOrder.departamento_tecnico || 'planta',
        tecnico_id: serviceOrder.tecnico?.id
            ? String(serviceOrder.tecnico.id)
            : '',
        observaciones: serviceOrder.observaciones ?? '',
    });
    const nota = useForm({ mensaje: '' });
    const equipo = useForm({
        numero_serie: '',
        tipo_agente: '',
        capacidad: '',
        marca: '',
        serie_fabricante: '',
        observaciones_recepcion: '',
    });
    const ruta = { current_team: teamSlug, service_order: serviceOrder.id };

    function asignar() {
        if (!tecnicoElegido) {
            return;
        }

        router.post(
            ordenes.asignarTecnico.url(ruta),
            { tecnico_id: tecnicoElegido },
            {
                preserveScroll: true,
                onSuccess: () => setTecnicoElegido(''),
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ??
                            'No se pudo asignar el técnico.',
                    ),
            },
        );
    }

    function guardarEdicion(event: React.FormEvent) {
        event.preventDefault();
        edicion.put(ordenes.update.url(ruta), {
            onError: (errors) =>
                toast.error(
                    Object.values(errors)[0] ??
                        'No se pudo completar la acción.',
                ),
            preserveScroll: true,
            onSuccess: () => setEditando(false),
        });
    }

    function enviarNota(event: React.FormEvent) {
        event.preventDefault();
        nota.post(comunicacion.nota.url(ruta), {
            onError: (errors) =>
                toast.error(
                    Object.values(errors)[0] ??
                        'No se pudo completar la acción.',
                ),
            preserveScroll: true,
            onSuccess: () => nota.reset(),
        });
    }

    function agregarEquipo(event: React.FormEvent) {
        event.preventDefault();
        equipo.post(
            EquiposRoutes.store.url({
                current_team: teamSlug,
                service_order: serviceOrder.id,
            }),
            {
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ??
                            'No se pudo completar la acción.',
                    ),
                preserveScroll: true,
                onSuccess: () => equipo.reset(),
            },
        );
    }

    return (
        <VendedorLayout title={`Orden ${serviceOrder.codigo}`}>
            <Head title={`Orden ${serviceOrder.codigo}`} />

            <div className="flex flex-col gap-4">
                {/* Header Back & Info */}
                <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <div className="flex items-center gap-3">
                        <Button
                            asChild
                            variant="outline"
                            size="icon"
                            className="border-border bg-card text-foreground/80 size-8 rounded-[8px] shadow-none"
                        >
                            <Link
                                href={ServiceOrderController.index.url(
                                    teamSlug,
                                )}
                                aria-label="Volver a órdenes de servicio"
                            >
                                <ArrowLeft className="size-4" />
                            </Link>
                        </Button>
                        <div>
                            <div className="flex items-center gap-2">
                                <h2 className="text-foreground font-['Oswald',sans-serif] text-[22px] font-semibold">
                                    {serviceOrder.codigo}
                                </h2>
                                <Badge className="border border-blue-500/20 bg-blue-500/10 text-blue-600 dark:text-blue-400">
                                    {ESTADOS[serviceOrder.estado] ??
                                        serviceOrder.estado.replace(/_/g, ' ')}
                                </Badge>
                            </div>
                            <p className="text-muted-foreground text-[12px]">
                                Creada el{' '}
                                {new Date(
                                    serviceOrder.created_at,
                                ).toLocaleDateString('es-PE')}
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {ANULABLES.includes(serviceOrder.estado) ? (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    setErrorAnulacion(null);
                                    setAnulando(true);
                                }}
                                className="border-destructive/40 text-destructive-strong rounded-[9px] text-xs font-semibold"
                            >
                                Anular orden
                            </Button>
                        ) : null}
                        {editable ? (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setEditando(true)}
                                className="border-border bg-card text-foreground rounded-[9px] text-xs font-semibold"
                            >
                                <PencilLine className="mr-1.5 size-3.5" />
                                Editar orden
                            </Button>
                        ) : null}
                        {[
                            'listo_certificado',
                            'listo_entrega',
                            'entregado',
                            'cerrado',
                        ].includes(serviceOrder.estado) && (
                            <Button asChild size="sm">
                                <Link
                                    href={EntregaMostradorRoutes.show.url({
                                        current_team: teamSlug,
                                        service_order: serviceOrder.id,
                                    })}
                                >
                                    Entrega en mostrador
                                </Link>
                            </Button>
                        )}
                        <Button
                            asChild
                            variant="outline"
                            className="border-border bg-card text-foreground/80 rounded-[9px] text-xs font-semibold"
                        >
                            <Link
                                href={comunicacion.index.url({
                                    current_team: teamSlug,
                                })}
                            >
                                <MessageCircle className="mr-1.5 size-3.5" />
                                <span>Ver en Seguimiento de taller</span>
                            </Link>
                        </Button>
                    </div>
                </div>

                <Card className="space-y-4 p-5">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 className="font-bold">Extintores recibidos</h3>
                            <p className="text-muted-foreground text-xs">
                                Escanea un BF-EQ existente o registra uno nuevo.
                            </p>
                        </div>
                        <div className="flex gap-2">
                            <Button asChild variant="outline">
                                <a
                                    href={ordenes.constanciaRecepcion.url({
                                        current_team: teamSlug,
                                        service_order: serviceOrder.id,
                                    })}
                                >
                                    Constancia PDF
                                </a>
                            </Button>
                            {serviceOrder.sale_id ? (
                                <Button asChild variant="outline">
                                    <Link
                                        href={VentasRoutes.show.url({
                                            current_team: teamSlug,
                                            sale: serviceOrder.sale_id,
                                        })}
                                    >
                                        Ver venta
                                    </Link>
                                </Button>
                            ) : serviceOrder.estado === 'anulada' ? null : (
                                <Button asChild>
                                    <Link
                                        href={VentasRoutes.create.url(
                                            { current_team: teamSlug },
                                            {
                                                query: {
                                                    orden_servicio:
                                                        serviceOrder.id,
                                                },
                                            },
                                        )}
                                    >
                                        Cobrar
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </div>
                    <div className="space-y-1 text-sm">
                        {serviceOrder.equipments.length ? (
                            serviceOrder.equipments.map((item) => (
                                <div
                                    key={item.id}
                                    className="rounded-lg border p-2"
                                >
                                    <strong>{item.numero_serie}</strong> ·{' '}
                                    {item.capacidad} · {item.tipo_agente} ·{' '}
                                    {item.marca}
                                </div>
                            ))
                        ) : (
                            <p className="text-muted-foreground">
                                Aún no hay extintores registrados.
                            </p>
                        )}
                    </div>
                    {editable && (
                        <form
                            onSubmit={agregarEquipo}
                            className="grid gap-2 sm:grid-cols-3"
                        >
                            <input
                                aria-label="Sticker BF-EQ (si existe)"
                                className="rounded-md border p-2 text-sm"
                                placeholder="Sticker BF-EQ (si existe)"
                                value={equipo.data.numero_serie}
                                onChange={(e) =>
                                    equipo.setData(
                                        'numero_serie',
                                        e.target.value,
                                    )
                                }
                            />
                            <input
                                aria-label="Capacidad"
                                className="rounded-md border p-2 text-sm"
                                placeholder="Capacidad"
                                value={equipo.data.capacidad}
                                onChange={(e) =>
                                    equipo.setData('capacidad', e.target.value)
                                }
                            />
                            <select
                                aria-label="Agente extintor"
                                className="rounded-md border p-2 text-sm"
                                value={equipo.data.tipo_agente}
                                onChange={(e) =>
                                    equipo.setData(
                                        'tipo_agente',
                                        e.target.value,
                                    )
                                }
                            >
                                <OpcionesAgente vacia="Agente" />
                            </select>
                            <input
                                aria-label="Marca"
                                className="rounded-md border p-2 text-sm"
                                placeholder="Marca"
                                value={equipo.data.marca}
                                onChange={(e) =>
                                    equipo.setData('marca', e.target.value)
                                }
                            />
                            <input
                                aria-label="Serie fabricante"
                                className="rounded-md border p-2 text-sm"
                                placeholder="Serie fabricante"
                                value={equipo.data.serie_fabricante}
                                onChange={(e) =>
                                    equipo.setData(
                                        'serie_fabricante',
                                        e.target.value,
                                    )
                                }
                            />
                            <Button type="submit" disabled={equipo.processing}>
                                Agregar extintor
                            </Button>
                        </form>
                    )}
                </Card>

                {/* 4-Step Progress Tracker Banner */}
                <Card className="border-border bg-card rounded-[16px] p-5 shadow-none">
                    <div className="text-muted-foreground mb-3 text-xs font-bold uppercase">
                        Progreso de atención
                    </div>
                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        {STEPS.map((stepItem, idx) => {
                            const stepNum = idx + 1;
                            const isDone = stepNum < currentStep;
                            const isCurrent = stepNum === currentStep;

                            return (
                                <div
                                    key={stepItem.key}
                                    className={`flex items-center gap-2 rounded-[10px] p-3 text-xs font-bold transition-all ${
                                        isCurrent
                                            ? 'border border-blue-500/20 bg-blue-500/10 text-blue-600 ring-1 ring-blue-300 dark:text-blue-400'
                                            : isDone
                                              ? 'text-success-strong border border-emerald-500/20 bg-emerald-500/10'
                                              : 'bg-muted/40 text-muted-foreground'
                                    }`}
                                >
                                    <div
                                        className={`flex size-6 shrink-0 items-center justify-center rounded-full text-[11px] ${
                                            isCurrent
                                                ? 'bg-blue-600 text-white'
                                                : isDone
                                                  ? 'bg-emerald-600 text-white'
                                                  : 'bg-muted/40 text-muted-foreground'
                                        }`}
                                    >
                                        {isDone ? (
                                            <CheckCircle2 className="size-3.5" />
                                        ) : (
                                            stepNum
                                        )}
                                    </div>
                                    <span className="truncate">
                                        {stepItem.key === 'asignada' &&
                                        !serviceOrder.tecnico
                                            ? '1. Por asignar'
                                            : stepItem.label}
                                    </span>
                                </div>
                            );
                        })}
                    </div>
                </Card>

                {/* 2-Column Info & Bitácora */}
                <div className="grid gap-4 lg:grid-cols-12">
                    {/* Left Column: Detalle del Servicio & Cliente (5 cols) */}
                    <div className="flex flex-col gap-4 lg:col-span-5">
                        <Card className="border-border bg-card rounded-[16px] p-5 shadow-none">
                            <div className="text-foreground text-[13.5px] font-bold">
                                Detalles del servicio
                            </div>
                            <div className="divide-border mt-3 divide-y text-[12.5px]">
                                <div className="py-2">
                                    <div className="text-muted-foreground text-[11px]">
                                        Tipo de servicio
                                    </div>
                                    <div className="text-foreground font-semibold">
                                        {serviceOrder.service?.nombre ?? '—'}
                                    </div>
                                </div>
                                <div className="py-2">
                                    <div className="text-muted-foreground text-[11px]">
                                        Fecha programada
                                    </div>
                                    <div className="text-foreground capitalize">
                                        {fechaLegible(serviceOrder.fecha)}
                                    </div>
                                </div>
                                <div className="py-2">
                                    <div className="text-muted-foreground text-[11px]">
                                        Departamento / Prioridad
                                    </div>
                                    <div className="text-foreground capitalize">
                                        {serviceOrder.departamento_tecnico ||
                                            'General'}{' '}
                                        · Prioridad{' '}
                                        {PRIORIDADES[serviceOrder.prioridad] ??
                                            serviceOrder.prioridad}
                                    </div>
                                </div>
                                <div className="py-2">
                                    <div className="text-muted-foreground text-[11px]">
                                        Técnico asignado
                                    </div>
                                    <div className="text-foreground font-semibold">
                                        {serviceOrder.tecnico?.name ||
                                            'Pendiente de asignación'}
                                    </div>
                                    {editable && tecnicos.length > 0 ? (
                                        <div className="mt-2 flex gap-2">
                                            <select
                                                aria-label="Técnico asignado"
                                                value={tecnicoElegido}
                                                onChange={(event) =>
                                                    setTecnicoElegido(
                                                        event.target.value,
                                                    )
                                                }
                                                className="border-input bg-background h-9 flex-1 rounded-md border px-3 text-sm"
                                            >
                                                <option value="">
                                                    {serviceOrder.tecnico
                                                        ? 'Cambiar de técnico…'
                                                        : 'Elegir técnico…'}
                                                </option>
                                                {tecnicos
                                                    .filter(
                                                        (tecnico) =>
                                                            tecnico.id !==
                                                            serviceOrder.tecnico
                                                                ?.id,
                                                    )
                                                    .map((tecnico) => (
                                                        <option
                                                            key={tecnico.id}
                                                            value={tecnico.id}
                                                        >
                                                            {tecnico.name}
                                                        </option>
                                                    ))}
                                            </select>
                                            <Button
                                                type="button"
                                                size="sm"
                                                disabled={!tecnicoElegido}
                                                onClick={asignar}
                                                className="h-9"
                                            >
                                                {serviceOrder.tecnico
                                                    ? 'Cambiar'
                                                    : 'Asignar'}
                                            </Button>
                                        </div>
                                    ) : null}
                                    {editable && tecnicos.length === 0 ? (
                                        <p className="mt-1 text-[11.5px] text-amber-700 dark:text-amber-400">
                                            No hay técnicos de{' '}
                                            {serviceOrder.departamento_tecnico ===
                                            'campo'
                                                ? 'campo'
                                                : 'planta'}{' '}
                                            para esta sede. Pídele al Gerente
                                            que asigne uno en Usuarios.
                                        </p>
                                    ) : null}
                                </div>
                                {serviceOrder.observaciones && (
                                    <div className="py-2">
                                        <div className="text-muted-foreground text-[11px]">
                                            Observaciones
                                        </div>
                                        <div className="text-foreground/80">
                                            {serviceOrder.observaciones}
                                        </div>
                                    </div>
                                )}
                            </div>
                        </Card>

                        {serviceOrder.client && (
                            <Card className="border-border bg-card rounded-[16px] p-5 shadow-none">
                                <div className="text-foreground text-[13.5px] font-bold">
                                    Datos del cliente
                                </div>
                                <div className="mt-3 space-y-1.5 text-[12.5px]">
                                    <div className="text-foreground font-bold">
                                        {serviceOrder.client.razon_social}
                                    </div>
                                    {serviceOrder.client.numero_documento && (
                                        <div className="text-muted-foreground font-mono text-xs">
                                            RUC/DNI:{' '}
                                            {
                                                serviceOrder.client
                                                    .numero_documento
                                            }
                                        </div>
                                    )}
                                    {serviceOrder.client.direccion_fiscal && (
                                        <div className="text-foreground/80 text-xs">
                                            {
                                                serviceOrder.client
                                                    .direccion_fiscal
                                            }
                                        </div>
                                    )}
                                    {serviceOrder.client.telefono && (
                                        <div className="text-muted-foreground text-xs">
                                            Teléfono:{' '}
                                            {serviceOrder.client.telefono}
                                        </div>
                                    )}
                                </div>
                            </Card>
                        )}
                    </div>

                    {/* Right Column: Bitácora de Eventos (7 cols) */}
                    <div className="flex flex-col lg:col-span-7">
                        <Card className="border-border bg-card rounded-[16px] p-5 shadow-none">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <Clock className="text-muted-foreground size-4" />
                                    <span className="text-foreground text-[13.5px] font-bold">
                                        Bitácora y eventos de orden
                                    </span>
                                </div>
                                <span className="text-muted-foreground text-[11px]">
                                    {serviceOrder.events?.length ?? 0} eventos
                                </span>
                            </div>

                            {editable ? (
                                <form
                                    onSubmit={enviarNota}
                                    className="mt-3 flex gap-2"
                                >
                                    <input
                                        aria-label="Mensaje"
                                        value={nota.data.mensaje}
                                        onChange={(event) =>
                                            nota.setData(
                                                'mensaje',
                                                event.target.value,
                                            )
                                        }
                                        maxLength={500}
                                        placeholder="Escribe una indicación para el técnico (la verá en su pantalla)…"
                                        className="border-input bg-background h-9 min-w-0 flex-1 rounded-md border px-3 text-sm"
                                    />
                                    <Button
                                        type="submit"
                                        size="sm"
                                        disabled={
                                            nota.processing ||
                                            nota.data.mensaje.trim() === ''
                                        }
                                        className="h-9"
                                    >
                                        <Send className="size-3.5" />
                                        Enviar
                                    </Button>
                                </form>
                            ) : null}

                            <div className="mt-4 space-y-4">
                                {serviceOrder.events &&
                                serviceOrder.events.length > 0 ? (
                                    serviceOrder.events.map((evt) => (
                                        <div
                                            key={evt.id}
                                            className="flex gap-3 text-[12.5px]"
                                        >
                                            <div className="bg-muted text-muted-foreground mt-1 flex size-6 shrink-0 items-center justify-center rounded-full">
                                                <CheckCircle2 className="size-3.5" />
                                            </div>
                                            <div className="border-border bg-muted/40 flex-1 rounded-[10px] border p-3">
                                                <div className="flex items-center justify-between">
                                                    <span className="text-foreground font-bold capitalize">
                                                        {tituloDeEvento(evt)}
                                                    </span>
                                                    <span className="text-muted-foreground font-mono text-[10.5px]">
                                                        {new Date(
                                                            evt.created_at,
                                                        ).toLocaleString(
                                                            'es-PE',
                                                            {
                                                                month: 'short',
                                                                day: 'numeric',
                                                                hour: '2-digit',
                                                                minute: '2-digit',
                                                            },
                                                        )}
                                                    </span>
                                                </div>
                                                <div className="text-foreground/80 mt-1">
                                                    {evt.payload?.mensaje ||
                                                        (evt.payload
                                                            ?.numero_serie
                                                            ? `Extintor ${evt.payload.numero_serie}`
                                                            : null) ||
                                                        (evt.payload?.resultado
                                                            ? `Resultado: ${evt.payload.resultado}`
                                                            : 'Evento registrado en orden de servicio')}
                                                </div>
                                                {evt.user?.name && (
                                                    <div className="text-muted-foreground mt-1 text-[11px]">
                                                        Registrado por:{' '}
                                                        <b>{evt.user.name}</b>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    ))
                                ) : (
                                    <div className="text-muted-foreground py-8 text-center text-xs">
                                        No se han registrado eventos adicionales
                                        en esta orden.
                                    </div>
                                )}
                            </div>
                        </Card>
                    </div>
                </div>
            </div>
            <Dialog open={editando} onOpenChange={setEditando}>
                <DialogContent className="sm:max-w-[480px]">
                    <DialogHeader>
                        <DialogTitle>Editar {serviceOrder.codigo}</DialogTitle>
                    </DialogHeader>
                    <form
                        onSubmit={guardarEdicion}
                        className="flex flex-col gap-3 text-[13px]"
                    >
                        <label className="flex flex-col gap-1">
                            <span className="text-muted-foreground text-[11px] font-bold uppercase">
                                Servicio del catálogo
                            </span>
                            <select
                                aria-label="Servicio del catálogo"
                                value={edicion.data.service_id}
                                onChange={(event) =>
                                    edicion.setData(
                                        'service_id',
                                        event.target.value,
                                    )
                                }
                                className="border-input bg-background h-9 rounded-md border px-3"
                                required
                            >
                                <option value="">Seleccionar servicio</option>
                                {services.map((service) => (
                                    <option key={service.id} value={service.id}>
                                        {service.nombre}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="flex flex-col gap-1">
                            <span className="text-muted-foreground text-[11px] font-bold uppercase">
                                Fecha programada
                            </span>
                            <input
                                aria-label="Fecha programada"
                                type="date"
                                value={edicion.data.fecha}
                                onChange={(event) =>
                                    edicion.setData('fecha', event.target.value)
                                }
                                className="border-input bg-background h-9 rounded-md border px-3"
                            />
                        </label>
                        <div className="grid grid-cols-2 gap-3">
                            <label className="flex flex-col gap-1">
                                <span className="text-muted-foreground text-[11px] font-bold uppercase">
                                    Prioridad
                                </span>
                                <select
                                    aria-label="Prioridad"
                                    value={edicion.data.prioridad}
                                    onChange={(event) =>
                                        edicion.setData(
                                            'prioridad',
                                            event.target.value,
                                        )
                                    }
                                    className="border-input bg-background h-9 rounded-md border px-3"
                                >
                                    <option value="normal">Normal</option>
                                    <option value="alta">Alta</option>
                                    <option value="urgente">Urgente</option>
                                </select>
                            </label>
                            <label className="flex flex-col gap-1">
                                <span className="text-muted-foreground text-[11px] font-bold uppercase">
                                    Área
                                </span>
                                <select
                                    aria-label="Área"
                                    value={edicion.data.departamento_tecnico}
                                    disabled={
                                        serviceOrder.estado !==
                                        'pendiente_recepcion'
                                    }
                                    onChange={(event) =>
                                        edicion.setData((datos) => ({
                                            ...datos,
                                            departamento_tecnico:
                                                event.target.value,
                                            tecnico_id: '',
                                        }))
                                    }
                                    className="border-input bg-background h-9 rounded-md border px-3 disabled:opacity-60"
                                >
                                    <option value="planta">
                                        Planta (taller)
                                    </option>
                                    <option value="campo">
                                        Campo (en el local del cliente)
                                    </option>
                                </select>
                            </label>
                        </div>
                        {edicion.data.departamento_tecnico ===
                        serviceOrder.departamento_tecnico ? (
                            <label className="flex flex-col gap-1">
                                <span className="text-muted-foreground text-[11px] font-bold uppercase">
                                    Técnico
                                </span>
                                <select
                                    aria-label="Técnico"
                                    value={edicion.data.tecnico_id}
                                    onChange={(event) =>
                                        edicion.setData(
                                            'tecnico_id',
                                            event.target.value,
                                        )
                                    }
                                    className="border-input bg-background h-9 rounded-md border px-3"
                                >
                                    <option value="">
                                        Sin asignar (lo toma el primero libre)
                                    </option>
                                    {tecnicos.map((tecnico) => (
                                        <option
                                            key={tecnico.id}
                                            value={tecnico.id}
                                        >
                                            {tecnico.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        ) : (
                            <p className="text-muted-foreground text-[11.5px]">
                                Al cambiar de área, asigna el técnico después de
                                guardar.
                            </p>
                        )}
                        <label className="flex flex-col gap-1">
                            <span className="text-muted-foreground text-[11px] font-bold uppercase">
                                Indicaciones para el técnico
                            </span>
                            <textarea
                                aria-label="Indicaciones para el técnico"
                                value={edicion.data.observaciones}
                                onChange={(event) =>
                                    edicion.setData(
                                        'observaciones',
                                        event.target.value,
                                    )
                                }
                                rows={3}
                                className="border-input bg-background rounded-md border px-3 py-2"
                            />
                        </label>
                        {Object.values(edicion.errors)[0] ? (
                            <p
                                className="text-destructive-strong text-[11.5px]"
                                role="alert"
                            >
                                {Object.values(edicion.errors)[0]}
                            </p>
                        ) : null}
                        <div className="flex justify-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setEditando(false)}
                            >
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={edicion.processing}>
                                {edicion.processing
                                    ? 'Guardando…'
                                    : 'Guardar cambios'}
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>
            <Dialog open={anulando} onOpenChange={setAnulando}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Anular la orden {serviceOrder.codigo}
                        </DialogTitle>
                    </DialogHeader>
                    <p className="text-muted-foreground text-xs">
                        Úsalo cuando el cliente se arrepiente. Los extintores
                        que estén en el taller se le devuelven sin servicio. Si
                        la orden ya se cobró, primero anula la venta.
                    </p>
                    <textarea
                        aria-label="Motivo de anulación"
                        value={motivoAnulacion}
                        onChange={(e) => setMotivoAnulacion(e.target.value)}
                        placeholder="Motivo de la anulación"
                        className="border-border bg-card min-h-[80px] w-full rounded-lg border p-2 text-sm"
                    />
                    {errorAnulacion ? (
                        <p
                            className="text-destructive-strong text-xs font-semibold"
                            role="alert"
                        >
                            {errorAnulacion}
                        </p>
                    ) : null}
                    <div className="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setAnulando(false)}
                        >
                            Volver
                        </Button>
                        <Button
                            type="button"
                            onClick={anularOrden}
                            className="bg-destructive hover:bg-destructive/90 text-white"
                        >
                            Anular orden
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>
        </VendedorLayout>
    );
}
