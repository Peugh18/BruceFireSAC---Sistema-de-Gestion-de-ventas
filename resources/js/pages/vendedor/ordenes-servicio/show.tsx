import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Building2,
    Calendar,
    CheckCircle2,
    Clock,
    FileText,
    MessageCircle,
    PencilLine,
    Send,
    User,
    Wrench,
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
    tipo_servicio: string;
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
    events: ServiceOrderEvent[];
};

export type Props = {
    serviceOrder: ServiceOrderDetails;
    tecnicos: { id: number; name: string }[];
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
};

const PRIORIDADES: Record<string, string> = {
    normal: 'Normal',
    alta: 'Alta',
    urgente: 'Urgente',
};

/** Título del evento de la bitácora en palabras simples. */
function tituloDeEvento(evt: ServiceOrderEvent): string {
    const accion = String(evt.payload?.accion ?? '');
    const porAccion: Record<string, string> = {
        tecnico_asignado: 'Técnico asignado',
        orden_tomada: 'El técnico tomó la orden',
        orden_editada: 'Orden editada por ventas',
        entrega_final_realizada: 'Entregado al cliente',
        consumo_repuesto_kardex: 'Repuesto instalado',
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

export default function ServiceOrderShow({ serviceOrder, tecnicos }: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug =
        currentTeam?.slug ||
        (typeof window !== 'undefined'
            ? window.location.pathname.split('/')[1]
            : '');

    const currentStep = getCoarseStep(serviceOrder.estado);
    const editable = !['entregado', 'cerrado'].includes(serviceOrder.estado);
    const [editando, setEditando] = useState(false);
    const [tecnicoElegido, setTecnicoElegido] = useState('');
    const edicion = useForm({
        fecha: soloFecha(serviceOrder.fecha),
        prioridad: serviceOrder.prioridad || 'normal',
        departamento_tecnico: serviceOrder.departamento_tecnico || 'planta',
        tecnico_id: serviceOrder.tecnico?.id
            ? String(serviceOrder.tecnico.id)
            : '',
        observaciones: serviceOrder.observaciones ?? '',
    });
    const nota = useForm({ mensaje: '' });
    const ruta = { current_team: teamSlug, service_order: serviceOrder.id };

    function asignar() {
        if (!tecnicoElegido) {
            return;
        }

        router.post(
            ordenes.asignarTecnico.url(ruta),
            { tecnico_id: tecnicoElegido },
            { preserveScroll: true, onSuccess: () => setTecnicoElegido('') },
        );
    }

    function guardarEdicion(event: React.FormEvent) {
        event.preventDefault();
        edicion.put(ordenes.update.url(ruta), {
            preserveScroll: true,
            onSuccess: () => setEditando(false),
        });
    }

    function enviarNota(event: React.FormEvent) {
        event.preventDefault();
        nota.post(comunicacion.nota.url(ruta), {
            preserveScroll: true,
            onSuccess: () => nota.reset(),
        });
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
                            className="size-8 rounded-[8px] border-border bg-card text-foreground/80 shadow-none"
                        >
                            <Link
                                href={ServiceOrderController.index.url(
                                    teamSlug,
                                )}
                            >
                                <ArrowLeft className="size-4" />
                            </Link>
                        </Button>
                        <div>
                            <div className="flex items-center gap-2">
                                <h2 className="font-['Oswald',sans-serif] text-[22px] font-semibold text-foreground">
                                    {serviceOrder.codigo}
                                </h2>
                                <Badge className="border border-blue-500/20 bg-blue-500/10 text-blue-600 dark:text-blue-400">
                                    {ESTADOS[serviceOrder.estado] ??
                                        serviceOrder.estado.replace(/_/g, ' ')}
                                </Badge>
                            </div>
                            <p className="text-[12px] text-muted-foreground">
                                Creada el{' '}
                                {new Date(
                                    serviceOrder.created_at,
                                ).toLocaleDateString('es-PE')}
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {editable ? (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setEditando(true)}
                                className="rounded-[9px] border-border bg-card text-xs font-semibold text-foreground"
                            >
                                <PencilLine className="mr-1.5 size-3.5" />
                                Editar orden
                            </Button>
                        ) : null}
                        {['listo_entrega', 'entregado', 'cerrado'].includes(
                            serviceOrder.estado,
                        ) && (
                            <Button asChild size="sm">
                                <Link
                                    href={`/${teamSlug}/vendedor/ordenes-servicio/${serviceOrder.id}/entrega-mostrador`}
                                >
                                    Entrega en mostrador
                                </Link>
                            </Button>
                        )}
                        <Button
                            asChild
                            variant="outline"
                            className="rounded-[9px] border-border bg-card text-xs font-semibold text-foreground/80"
                        >
                            <Link href={`/${teamSlug}/vendedor/comunicacion`}>
                                <MessageCircle className="mr-1.5 size-3.5" />
                                <span>Ver en Seguimiento de taller</span>
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* 4-Step Progress Tracker Banner */}
                <Card className="rounded-[16px] border-border bg-card p-5 shadow-none">
                    <div className="mb-3 text-xs font-bold text-muted-foreground uppercase">
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
                                            ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20 ring-1 ring-blue-300'
                                            : isDone
                                              ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20'
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
                        <Card className="rounded-[16px] border-border bg-card p-5 shadow-none">
                            <div className="text-[13.5px] font-bold text-foreground">
                                Detalles del servicio
                            </div>
                            <div className="mt-3 divide-y divide-border text-[12.5px]">
                                <div className="py-2">
                                    <div className="text-[11px] text-muted-foreground">
                                        Tipo de servicio
                                    </div>
                                    <div className="font-semibold text-foreground">
                                        {serviceOrder.tipo_servicio}
                                    </div>
                                </div>
                                <div className="py-2">
                                    <div className="text-[11px] text-muted-foreground">
                                        Fecha programada
                                    </div>
                                    <div className="text-foreground capitalize">
                                        {fechaLegible(serviceOrder.fecha)}
                                    </div>
                                </div>
                                <div className="py-2">
                                    <div className="text-[11px] text-muted-foreground">
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
                                    <div className="text-[11px] text-muted-foreground">
                                        Técnico asignado
                                    </div>
                                    <div className="font-semibold text-foreground">
                                        {serviceOrder.tecnico?.name ||
                                            'Pendiente de asignación'}
                                    </div>
                                    {editable && tecnicos.length > 0 ? (
                                        <div className="mt-2 flex gap-2">
                                            <select
                                                value={tecnicoElegido}
                                                onChange={(event) =>
                                                    setTecnicoElegido(
                                                        event.target.value,
                                                    )
                                                }
                                                className="h-9 flex-1 rounded-md border border-input bg-background px-3 text-sm"
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
                                        <div className="text-[11px] text-muted-foreground">
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
                            <Card className="rounded-[16px] border-border bg-card p-5 shadow-none">
                                <div className="text-[13.5px] font-bold text-foreground">
                                    Datos del cliente
                                </div>
                                <div className="mt-3 space-y-1.5 text-[12.5px]">
                                    <div className="font-bold text-foreground">
                                        {serviceOrder.client.razon_social}
                                    </div>
                                    {serviceOrder.client.numero_documento && (
                                        <div className="font-mono text-xs text-muted-foreground">
                                            RUC/DNI:{' '}
                                            {
                                                serviceOrder.client
                                                    .numero_documento
                                            }
                                        </div>
                                    )}
                                    {serviceOrder.client.direccion_fiscal && (
                                        <div className="text-xs text-foreground/80">
                                            {
                                                serviceOrder.client
                                                    .direccion_fiscal
                                            }
                                        </div>
                                    )}
                                    {serviceOrder.client.telefono && (
                                        <div className="text-xs text-muted-foreground">
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
                        <Card className="rounded-[16px] border-border bg-card p-5 shadow-none">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <Clock className="size-4 text-muted-foreground" />
                                    <span className="text-[13.5px] font-bold text-foreground">
                                        Bitácora y eventos de orden
                                    </span>
                                </div>
                                <span className="text-[11px] text-muted-foreground">
                                    {serviceOrder.events?.length ?? 0} eventos
                                </span>
                            </div>

                            {editable ? (
                                <form
                                    onSubmit={enviarNota}
                                    className="mt-3 flex gap-2"
                                >
                                    <input
                                        value={nota.data.mensaje}
                                        onChange={(event) =>
                                            nota.setData(
                                                'mensaje',
                                                event.target.value,
                                            )
                                        }
                                        maxLength={500}
                                        placeholder="Escribe una indicación para el técnico (la verá en su pantalla)…"
                                        className="h-9 min-w-0 flex-1 rounded-md border border-input bg-background px-3 text-sm"
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
                                            <div className="mt-1 flex size-6 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                                <CheckCircle2 className="size-3.5" />
                                            </div>
                                            <div className="flex-1 rounded-[10px] border border-border bg-muted/40 p-3">
                                                <div className="flex items-center justify-between">
                                                    <span className="font-bold text-foreground capitalize">
                                                        {tituloDeEvento(evt)}
                                                    </span>
                                                    <span className="font-mono text-[10.5px] text-muted-foreground">
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
                                                <div className="mt-1 text-foreground/80">
                                                    {evt.payload?.mensaje ||
                                                        (evt.payload?.resultado
                                                            ? `Resultado: ${evt.payload.resultado}`
                                                            : 'Evento registrado en orden de servicio')}
                                                </div>
                                                {evt.user?.name && (
                                                    <div className="mt-1 text-[11px] text-muted-foreground">
                                                        Registrado por:{' '}
                                                        <b>{evt.user.name}</b>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    ))
                                ) : (
                                    <div className="py-8 text-center text-xs text-muted-foreground">
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
                            <span className="text-[11px] font-bold text-muted-foreground uppercase">
                                Fecha programada
                            </span>
                            <input
                                type="date"
                                value={edicion.data.fecha}
                                onChange={(event) =>
                                    edicion.setData('fecha', event.target.value)
                                }
                                className="h-9 rounded-md border border-input bg-background px-3"
                            />
                        </label>
                        <div className="grid grid-cols-2 gap-3">
                            <label className="flex flex-col gap-1">
                                <span className="text-[11px] font-bold text-muted-foreground uppercase">
                                    Prioridad
                                </span>
                                <select
                                    value={edicion.data.prioridad}
                                    onChange={(event) =>
                                        edicion.setData(
                                            'prioridad',
                                            event.target.value,
                                        )
                                    }
                                    className="h-9 rounded-md border border-input bg-background px-3"
                                >
                                    <option value="normal">Normal</option>
                                    <option value="alta">Alta</option>
                                    <option value="urgente">Urgente</option>
                                </select>
                            </label>
                            <label className="flex flex-col gap-1">
                                <span className="text-[11px] font-bold text-muted-foreground uppercase">
                                    Área
                                </span>
                                <select
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
                                    className="h-9 rounded-md border border-input bg-background px-3 disabled:opacity-60"
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
                                <span className="text-[11px] font-bold text-muted-foreground uppercase">
                                    Técnico
                                </span>
                                <select
                                    value={edicion.data.tecnico_id}
                                    onChange={(event) =>
                                        edicion.setData(
                                            'tecnico_id',
                                            event.target.value,
                                        )
                                    }
                                    className="h-9 rounded-md border border-input bg-background px-3"
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
                            <p className="text-[11.5px] text-muted-foreground">
                                Al cambiar de área, asigna el técnico después de
                                guardar.
                            </p>
                        )}
                        <label className="flex flex-col gap-1">
                            <span className="text-[11px] font-bold text-muted-foreground uppercase">
                                Indicaciones para el técnico
                            </span>
                            <textarea
                                value={edicion.data.observaciones}
                                onChange={(event) =>
                                    edicion.setData(
                                        'observaciones',
                                        event.target.value,
                                    )
                                }
                                rows={3}
                                className="rounded-md border border-input bg-background px-3 py-2"
                            />
                        </label>
                        {Object.values(edicion.errors)[0] ? (
                            <p className="text-[11.5px] text-destructive">
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
        </VendedorLayout>
    );
}
