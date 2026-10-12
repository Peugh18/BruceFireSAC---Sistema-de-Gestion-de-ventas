import { Pasos, pasosOrdenAtencion } from '@/components/progreso';
import DeficienciaSuelta from '@/components/deficiencia-suelta';
import DeficiencyController from '@/actions/App/Http/Controllers/Vendedor/DeficiencyController';
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
import equiposRoutes from '@/routes/equipos';
import comunicacion from '@/routes/vendedor/comunicacion';
import ordenes from '@/routes/vendedor/ordenes-servicio';
import VendedorLayout from '@/layouts/vendedor-layout';
import type { Team } from '@/types';
import OpcionesAgente from '@/components/opciones-agente';
import EntregaMostradorRoutes from '@/routes/vendedor/ordenes-servicio/entrega-mostrador';
import {
    DeficienciaCard,
    type DeficiencyItem,
    useAccionesDeficiencia,
} from '@/components/deficiencias-vendedor';
import EquiposRoutes from '@/routes/vendedor/ordenes-servicio/equipos';
import VentasRoutes from '@/routes/vendedor/ventas';
import CotizacionesRoutes from '@/routes/vendedor/cotizaciones';
import CertificadosRoutes from '@/routes/vendedor/certificados';
import { NativeSelect } from '@/components/ui/native-select';

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
    sale?: { id: number; numero_interno: string; estado: string } | null;
    quote?: { id: number; numero: string; estado: string } | null;
    certificates?: { id: number; numero: string; estado: string }[];
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
    tecnicosEntrega: { id: number; name: string }[];
    services: { id: number; nombre: string }[];
    deficiencias: DeficiencyItem[];
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
        entrega_asignada: 'Entrega asignada a Campo',
        orden_cerrada: 'Orden cerrada',
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

export default function ServiceOrderShow({
    serviceOrder,
    tecnicos,
    tecnicosEntrega,
    services,
    deficiencias,
}: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug =
        currentTeam?.slug ||
        (typeof window !== 'undefined'
            ? window.location.pathname.split('/')[1]
            : '');

    const acciones = useAccionesDeficiencia(teamSlug);

    const nextSteps: Record<string, { responsable: string; texto: string }> = {
        pendiente_recepcion: {
            responsable:
                serviceOrder.departamento_tecnico === 'campo'
                    ? 'Técnico de campo'
                    : 'Recepción de taller',
            texto:
                serviceOrder.departamento_tecnico === 'campo'
                    ? 'Coordinar la visita y revisar los equipos de esta orden.'
                    : 'Confirmar los equipos que llegaron antes de iniciar su revisión.',
        },
        recibido_planta: {
            responsable: 'Técnico de planta',
            texto: 'Revisar cada equipo y registrar los hallazgos del checklist.',
        },
        en_revision: {
            responsable: 'Técnico',
            texto: 'Completar la revisión y comunicar los adicionales que requieran autorización.',
        },
        esperando_autorizacion: {
            responsable: 'Vendedor',
            texto: 'Revisar los adicionales y registrar la decisión del cliente antes de continuar el trabajo autorizado.',
        },
        autorizado: {
            responsable: 'Técnico',
            texto: 'Continuar únicamente con el trabajo autorizado y registrar los repuestos utilizados.',
        },
        en_proceso: {
            responsable: 'Técnico',
            texto: 'Completar el trabajo y registrar su resultado. Revisa los eventos para conocer los pendientes.',
        },
        trabajo_terminado: {
            responsable: 'Técnico / Vendedor',
            texto: 'Revisar los datos técnicos y los requisitos pendientes para el cierre y el certificado.',
        },
        pendiente_datos: {
            responsable: 'Técnico / Vendedor',
            texto: 'Completar los datos requeridos del certificado sin inventar fechas ni resultados.',
        },
        datos_completos: {
            responsable: 'Responsable del certificado',
            texto: 'Revisar los equipos aptos y emitir el certificado cuando se cumplan los requisitos.',
        },
        listo_certificado: {
            responsable: 'Vendedor / Entrega',
            texto: 'Comprobar el certificado y coordinar la entrega con la conformidad del cliente.',
        },
        listo_entrega: {
            responsable: 'Vendedor / Técnico de campo',
            texto: 'Coordinar la entrega y registrar el acta o conformidad correspondiente.',
        },
        entregado: {
            responsable: 'Vendedor / Técnico de campo',
            texto: 'Revisar el acta y la conformidad antes del cierre administrativo.',
        },
        cerrado: {
            responsable: 'Orden cerrada',
            texto: 'Consulta los documentos y el historial. El cierre técnico no confirma por sí solo que la venta esté pagada.',
        },
        anulada: {
            responsable: 'Orden anulada',
            texto: 'Consulta el motivo y el historial de la anulación.',
        },
    };
    const nextStep = nextSteps[serviceOrder.estado];
    const pasosAtencion = pasosOrdenAtencion(
        serviceOrder.estado,
        Boolean(serviceOrder.tecnico),
    );
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
    const entrega = useForm({ tecnico_id: '' });
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
                {![
                    'anulada',
                    'cerrado',
                    'entregado',
                    'listo_certificado',
                    'listo_entrega',
                ].includes(serviceOrder.estado) && (
                    <DeficienciaSuelta
                        ordenId={serviceOrder.id}
                        equipos={serviceOrder.equipments.map((item) => ({
                            id: item.id,
                            serie: item.numero_serie,
                        }))}
                        destino={DeficiencyController.store.url({
                            current_team: teamSlug,
                            service_order: serviceOrder.id,
                        })}
                    />
                )}
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
                                <Badge className="border border-blue-500/20 bg-blue-500/10 text-blue-800 dark:text-blue-300">
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
                                href={comunicacion.index.url(
                                    { current_team: teamSlug },
                                    { query: { orden: serviceOrder.id } },
                                )}
                            >
                                <MessageCircle className="mr-1.5 size-3.5" />
                                <span>Ver en Seguimiento de taller</span>
                            </Link>
                        </Button>
                    </div>
                </div>

                <section
                    aria-labelledby="orden-continuar-titulo"
                    className="bg-card space-y-4 rounded-xl border p-4"
                >
                    <div>
                        <h3
                            id="orden-continuar-titulo"
                            className="font-semibold"
                        >
                            Para continuar esta orden
                        </h3>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {nextStep?.texto ??
                                'Revisa el estado y los eventos de esta orden.'}
                        </p>
                        {nextStep && (
                            <p className="mt-2 text-sm">
                                <strong>Responsable del siguiente paso:</strong>{' '}
                                {nextStep.responsable}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-3 sm:grid-cols-3">
                        <div className="rounded-lg border p-3">
                            <p className="text-muted-foreground text-xs">
                                Cotización vinculada
                            </p>
                            {serviceOrder.quote ? (
                                <a
                                    className="inline-flex min-h-11 items-center text-sm font-medium underline"
                                    href={CotizacionesRoutes.pdf.url({
                                        current_team: teamSlug,
                                        quote: serviceOrder.quote.id,
                                    })}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    {serviceOrder.quote.numero} · Ver PDF
                                </a>
                            ) : (
                                <p className="mt-2 text-sm">
                                    Sin cotización vinculada
                                </p>
                            )}
                        </div>
                        <div className="rounded-lg border p-3">
                            <p className="text-muted-foreground text-xs">
                                Venta vinculada
                            </p>
                            {serviceOrder.sale ? (
                                <Link
                                    className="inline-flex min-h-11 items-center text-sm font-medium underline"
                                    href={VentasRoutes.show.url({
                                        current_team: teamSlug,
                                        sale: serviceOrder.sale.id,
                                    })}
                                >
                                    {serviceOrder.sale.numero_interno} · Ver
                                    venta
                                </Link>
                            ) : (
                                <p className="mt-2 text-sm">
                                    Sin venta vinculada
                                </p>
                            )}
                            <p className="text-muted-foreground mt-1 text-xs">
                                Consulta el saldo y los pagos desde la venta.
                            </p>
                        </div>
                        <div className="rounded-lg border p-3">
                            <p className="text-muted-foreground text-xs">
                                Certificados de esta orden
                            </p>
                            {serviceOrder.certificates?.length ? (
                                serviceOrder.certificates.map((certificate) => (
                                    <Link
                                        key={certificate.id}
                                        className="flex min-h-11 items-center text-sm font-medium underline"
                                        href={CertificadosRoutes.show.url({
                                            current_team: teamSlug,
                                            certificate: certificate.id,
                                        })}
                                    >
                                        {certificate.numero} ·{' '}
                                        {certificate.estado}
                                    </Link>
                                ))
                            ) : (
                                <p className="mt-2 text-sm">
                                    Sin certificado emitido
                                </p>
                            )}
                        </div>
                    </div>
                    <p className="text-muted-foreground text-xs">
                        Trabajo, documentos, entrega y pago tienen avances
                        distintos. Usa la venta para comprobar el cobro y los
                        eventos para comprobar la entrega.
                    </p>
                </section>
                {deficiencias.length > 0 && (
                    <section
                        aria-label="Hallazgos de la orden"
                        className="space-y-3"
                    >
                        <div>
                            <h3 className="font-bold">
                                Hallazgos y adicionales de esta orden
                            </h3>
                            <p className="text-muted-foreground text-xs">
                                Cotiza el adicional y registra la decisión del
                                cliente aquí mismo.
                            </p>
                        </div>
                        {deficiencias.map((item) => (
                            <DeficienciaCard
                                key={item.id}
                                item={item}
                                teamSlug={teamSlug}
                                acciones={acciones}
                                enOrden
                            />
                        ))}
                    </section>
                )}
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
                                    <Link
                                        href={equiposRoutes.show.url({
                                            current_team: teamSlug,
                                            equipment: item.id,
                                        })}
                                        className="font-bold underline"
                                    >
                                        {item.numero_serie}
                                    </Link>{' '}
                                    · {item.capacidad} · {item.tipo_agente} ·{' '}
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
                            <NativeSelect
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
                            </NativeSelect>
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
                            <Button type="submit" loading={equipo.processing}>
                                Agregar extintor
                            </Button>
                        </form>
                    )}
                </Card>

                <Card className="border-border bg-card rounded-2xl p-5 shadow-none">
                    <h3 className="text-sm font-semibold">
                        Progreso de atención
                    </h3>
                    {pasosAtencion.length > 0 ? (
                        <Pasos
                            pasos={pasosAtencion}
                            label="Etapas de atención de la orden"
                            variante="cheuron"
                        />
                    ) : (
                        <p className="text-muted-foreground text-sm">
                            {ESTADOS[serviceOrder.estado] ??
                                'Estado sin etapa de progreso disponible'}
                        </p>
                    )}
                    {!serviceOrder.tecnico &&
                        serviceOrder.estado !== 'anulada' && (
                            <p className="text-warning-strong text-sm">
                                La orden todavía no tiene un técnico asignado.
                            </p>
                        )}
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
                                            <NativeSelect
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
                                            </NativeSelect>
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
                                {[
                                    'listo_certificado',
                                    'listo_entrega',
                                ].includes(serviceOrder.estado) && (
                                    <form
                                        className="border-border mt-3 space-y-2 border-t pt-3"
                                        onSubmit={(event) => {
                                            event.preventDefault();
                                            entrega.post(
                                                ordenes.asignarEntrega.url(
                                                    ruta,
                                                ),
                                                {
                                                    preserveScroll: true,
                                                    onSuccess: () =>
                                                        entrega.reset(),
                                                },
                                            );
                                        }}
                                    >
                                        <label
                                            htmlFor="entrega-tecnico"
                                            className="text-foreground block text-xs font-semibold"
                                        >
                                            Responsable de la entrega en Campo
                                        </label>
                                        <p className="text-muted-foreground text-xs">
                                            El técnico elegido podrá registrar
                                            la entrega. Se conserva el área y el
                                            historial del trabajo realizado.
                                        </p>
                                        <NativeSelect
                                            id="entrega-tecnico"
                                            value={entrega.data.tecnico_id}
                                            required
                                            disabled={
                                                tecnicosEntrega.length === 0
                                            }
                                            onChange={(event) =>
                                                entrega.setData(
                                                    'tecnico_id',
                                                    event.target.value,
                                                )
                                            }
                                            className="border-border bg-card h-10 w-full rounded-lg border px-3 text-xs"
                                        >
                                            <option value="">
                                                Selecciona un técnico de Campo…
                                            </option>
                                            {tecnicosEntrega.map((tecnico) => (
                                                <option
                                                    key={tecnico.id}
                                                    value={tecnico.id}
                                                >
                                                    {tecnico.name}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                        {entrega.errors.tecnico_id && (
                                            <p
                                                role="alert"
                                                className="text-destructive text-xs"
                                            >
                                                {entrega.errors.tecnico_id}
                                            </p>
                                        )}
                                        {tecnicosEntrega.length === 0 && (
                                            <p className="text-muted-foreground text-xs">
                                                No hay técnicos de Campo para
                                                esta sede. Puedes registrar la
                                                entrega en mostrador o pedir al
                                                Gerente que asigne uno.
                                            </p>
                                        )}
                                        <Button
                                            type="submit"
                                            disabled={!entrega.data.tecnico_id}
                                            loading={entrega.processing}
                                        >
                                            Asignar entrega a Campo
                                        </Button>
                                    </form>
                                )}
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
                                            nota.data.mensaje.trim() === ''
                                        }
                                        loading={nota.processing}
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
                                                        (typeof evt.payload
                                                            ?.numero_serie ===
                                                        'string'
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
                            <NativeSelect
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
                            </NativeSelect>
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
                                <NativeSelect
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
                                </NativeSelect>
                            </label>
                            <label className="flex flex-col gap-1">
                                <span className="text-muted-foreground text-[11px] font-bold uppercase">
                                    Área
                                </span>
                                <NativeSelect
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
                                </NativeSelect>
                            </label>
                        </div>
                        {edicion.data.departamento_tecnico ===
                        serviceOrder.departamento_tecnico ? (
                            <label className="flex flex-col gap-1">
                                <span className="text-muted-foreground text-[11px] font-bold uppercase">
                                    Técnico
                                </span>
                                <NativeSelect
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
                                </NativeSelect>
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
                            <Button type="submit" loading={edicion.processing}>
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
            {acciones.dialogos}
        </VendedorLayout>
    );
}
