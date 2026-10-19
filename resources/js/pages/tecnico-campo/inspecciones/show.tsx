import ChecklistExtintor from '@/components/checklist-extintor';
import { etiquetaEstadoOrden } from '@/lib/estados-orden';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ResumenRevision } from '@/components/resumen-revision';
import TomarOrden, { type AsignacionOrden } from '@/components/tomar-orden';
import {
    AlertCircle,
    ArrowLeft,
    CheckCircle2,
    ChevronDown,
    ChevronUp,
    FileCheck,
    Flame,
    MapPin,
    Phone,
    Plus,
    Send,
    UserCheck,
    MessageSquare,
} from 'lucide-react';
import React, { useState } from 'react';
import BarraOrdenCampo from '@/components/barra-orden-campo';
import type { ConversacionProps } from '@/components/conversacion-orden';
import FirmaCanvas from '@/components/firma-canvas';
import SubirEvidencia, {
    type EvidenciaListada,
} from '@/components/subir-evidencia';
import { toast } from 'sonner';

import AlertError from '@/components/alert-error';
import TecnicoCampoLayout from '@/layouts/tecnico-campo-layout';
import type { Team } from '@/types';
import OpcionesAgente from '@/components/opciones-agente';
import EquiposRoutes from '@/routes/tecnico-campo/inspecciones/equipos';
import InspeccionesRoutes from '@/routes/tecnico-campo/inspecciones';
import ChecklistRoutes from '@/routes/tecnico-campo/inspecciones/checklist';
import { NativeSelect } from '@/components/ui/native-select';

type TechnicalChecklistItem = {
    clave?: string;
    elemento: string;
    estado: 'conforme' | 'observado' | 'no_aplica';
    nota?: string | null;
};

type TechnicalChecklist = {
    id: number;
    resultado_general: string;
    items: TechnicalChecklistItem[];
    observaciones?: string | null;
    created_at: string;
};

type Deficiency = {
    id: number;
    equipment_id: number;
    componente: string;
    condicion: string;
    estado: string;
    requiere_autorizacion: boolean;
    equipment?: {
        numero_serie: string;
    };
};

type Equipment = {
    id: number;
    numero_serie: string;
    serie_fabricante?: string | null;
    tipo_agente?: string | null;
    capacidad?: string | null;
    marca?: string | null;
    ubicacion_actual?: string | null;
    anio_fabricacion?: number | null;
    checklists?: TechnicalChecklist[];
};

type ServiceOrder = {
    id: number;
    codigo: string;
    estado: string;
    tipo_servicio?: string;
    observaciones?: string | null;
    events?: Array<{
        id: number;
        tipo: string;
        payload: Record<string, string | number | boolean | null | undefined>;
        created_at: string;
    }>;
    client_id: number;
    client: {
        id: number;
        razon_social: string;
        numero_documento?: string;
        telefono?: string;
        direccion_fiscal?: string;
    };
    sede?: {
        id: number;
        nombre: string;
    };
    equipments: Equipment[];
    deficiencies: Deficiency[];
    certificates: Array<{
        id: number;
        numero: string;
        certificate_type?: { nombre: string };
    }>;
};

type Props = {
    asignacion: AsignacionOrden;
    currentTeam?: Team | null;
    order: ServiceOrder;
    customerEquipments: Equipment[];
    elementosChecklist: Record<string, string>;
    conversacion: ConversacionProps;
    evidencias: EvidenciaListada[];
};

export default function InspeccionShow({
    asignacion,
    currentTeam,
    order,
    customerEquipments,
    elementosChecklist,
    conversacion,
    evidencias,
}: Props) {
    const teamSlug = currentTeam?.slug ?? '';
    const { auth, flash } = usePage<{
        auth?: { user?: { name?: string } };
        flash?: { success?: string; error?: string };
    }>().props;

    // Estado para extintor seleccionado para evaluar checklist
    const [evaluatingEquipmentId, setEvaluatingEquipmentId] = useState<
        number | null
    >(null);
    const [showAddEquipmentModal, setShowAddEquipmentModal] = useState(false);

    // Formulario para agregar extintor
    const addEquipmentForm = useForm({
        equipment_id: '',
        numero_serie: '',
        tipo_agente: '',
        capacidad: '6 kg',
        marca: 'Genérica',
        ubicacion_actual: '',
        anio_fabricacion: new Date().getFullYear().toString(),
    });

    const handleAddEquipment = (e: React.FormEvent) => {
        e.preventDefault();
        addEquipmentForm.post(
            EquiposRoutes.store.url({
                current_team: teamSlug,
                service_order: order.id,
            }),
            {
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ??
                            'No se pudo completar la acción.',
                    ),
                onSuccess: () => {
                    setShowAddEquipmentModal(false);
                    addEquipmentForm.reset();
                },
            },
        );
    };

    // Formulario para finalizar inspección con acta/conformidad (§24, §85.6.2)
    const completeForm = useForm({
        responsable: auth?.user?.name ?? 'Técnico de Campo',
        cargo: 'Técnico de Campo',
        conformidad_nombre: '',
        conformidad_aceptada: false,
        observaciones_generales: '',
        firma: null as string | null,
    });

    const handleComplete = (e: React.FormEvent) => {
        e.preventDefault();
        completeForm.post(
            InspeccionesRoutes.complete.url({
                current_team: teamSlug,
                service_order: order.id,
            }),
            {
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ??
                            'No se pudo completar la acción.',
                    ),
            },
        );
    };

    const isFinalizada = [
        'listo_certificado',
        'listo_entrega',
        'en_ruta_entrega',
        'entregado',
        'cerrado',
        'anulada',
    ].includes(order.estado);

    return (
        <TecnicoCampoLayout title={`Inspección ${order.codigo}`}>
            <Head title={`Inspección ${order.codigo} - Técnico de Campo`} />
            <BarraOrdenCampo
                ordenId={order.id}
                codigo={order.codigo}
                conversacion={conversacion}
            />

            {/* Back bar */}
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <Link
                    href={InspeccionesRoutes.index.url({
                        current_team: teamSlug,
                    })}
                    className="text-muted-foreground hover:text-foreground inline-flex min-h-11 items-center gap-1.5 text-xs font-bold"
                >
                    <ArrowLeft className="size-4" />
                    <span>Volver al listado</span>
                </Link>
                <span className="text-primary-strong font-mono text-xs font-black">
                    {order.codigo}
                </span>
            </div>

            {flash?.success && (
                <div className="text-success-strong mb-4 rounded-lg border border-emerald-500/20 bg-emerald-500/10 p-3 text-xs font-medium">
                    {flash.success}
                </div>
            )}

            {/* Header Data Card (§24) */}
            <div className="border-border bg-card mb-5 rounded-3xl border p-4 shadow-xs">
                <div className="border-border mb-3 flex items-start justify-between gap-2 border-b pb-3">
                    <div>
                        <span className="text-muted-foreground text-xs font-bold tracking-wider uppercase">
                            Cliente & Sede de Inspección
                        </span>
                        <h1 className="text-foreground text-base leading-snug font-black">
                            {order.client.razon_social}
                        </h1>
                        {order.client.numero_documento && (
                            <span className="text-muted-foreground font-mono text-xs">
                                RUC/DNI: {order.client.numero_documento}
                            </span>
                        )}
                    </div>
                    <span className="border-primary/20 bg-primary/5 text-primary-strong rounded-full border px-2.5 py-1 text-xs font-black">
                        {etiquetaEstadoOrden(order.estado)}
                    </span>
                </div>

                <div className="space-y-2 text-xs">
                    <div className="flex items-start gap-2">
                        <MapPin className="text-primary-strong mt-0.5 size-4 shrink-0" />
                        <div>
                            <span className="text-foreground font-semibold">
                                Dirección:{' '}
                            </span>
                            <span className="text-muted-foreground">
                                {order.client.direccion_fiscal ||
                                    'Sede principal del cliente'}
                            </span>
                        </div>
                    </div>

                    {order.client.telefono && (
                        <div className="flex items-center justify-between pt-1">
                            <div className="flex items-center gap-2">
                                <Phone className="text-success-strong size-4 shrink-0" />
                                <span className="text-foreground font-mono">
                                    {order.client.telefono}
                                </span>
                            </div>
                            <a
                                href={`tel:${order.client.telefono}`}
                                className="text-success-strong inline-flex min-h-11 items-center gap-1 rounded-lg border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-1 text-xs font-bold transition hover:brightness-95 active:scale-95 dark:text-emerald-400"
                            >
                                <Phone className="size-3" />
                                Llamar
                            </a>
                        </div>
                    )}
                </div>
            </div>

            <TomarOrden asignacion={asignacion} />

            {/* Indicaciones de Ventas / Notas de Coordinación */}
            {(() => {
                const notasVendedor = (order.events || [])
                    .filter(
                        (ev) =>
                            ev.payload?.origen === 'vendedor' ||
                            ev.tipo === 'notificacion_vendedor' ||
                            (ev.tipo === 'otro' && ev.payload?.mensaje),
                    )
                    .filter((ev) => {
                        const texto =
                            ev.payload?.mensaje || ev.payload?.descripcion;
                        return typeof texto === 'string' && texto.trim() !== '';
                    });

                if (!order.observaciones && notasVendedor.length === 0) {
                    return null;
                }

                return (
                    <div className="border-border bg-muted mb-6 rounded-3xl border p-4">
                        <div className="text-foreground flex items-center gap-2 text-xs font-bold">
                            <MessageSquare className="text-foreground size-4" />
                            <span>Indicaciones de Ventas y Coordinación</span>
                        </div>

                        {order.observaciones && (
                            <p className="text-foreground mt-2 text-xs">
                                <span className="text-foreground font-semibold">
                                    Observación inicial:{' '}
                                </span>
                                {order.observaciones}
                            </p>
                        )}

                        {notasVendedor.length > 0 && (
                            <div className="mt-3 space-y-2">
                                <span className="text-foreground text-xs font-bold uppercase">
                                    Notas de Ventas:
                                </span>
                                {notasVendedor.map((n) => (
                                    <div
                                        key={n.id}
                                        className="border-border rounded-xl border bg-white/80 p-2.5 text-xs text-neutral-800 shadow-2xs dark:bg-neutral-900/80 dark:text-neutral-200"
                                    >
                                        <p className="font-medium">
                                            {n.payload?.mensaje ||
                                                n.payload?.descripcion ||
                                                ''}
                                        </p>
                                        <span className="text-muted-foreground mt-1 block text-xs">
                                            {n.created_at
                                                ? new Date(
                                                      n.created_at,
                                                  ).toLocaleString('es-PE')
                                                : ''}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                );
            })()}

            {/* Extinguisher Cards Section (§24) */}
            <div className="mb-6">
                <div className="mb-3 flex items-center justify-between">
                    <div>
                        <h2 className="text-foreground flex items-center gap-1.5 text-sm font-black">
                            <Flame className="text-warning-strong size-4" />
                            Extintores a Inspeccionar ({order.equipments.length}
                            )
                        </h2>
                        <p className="text-muted-foreground text-xs">
                            Inspección física individual en sitio.
                        </p>
                    </div>
                    {!isFinalizada && (
                        <button
                            type="button"
                            onClick={() => setShowAddEquipmentModal(true)}
                            className="border-primary/20 bg-primary/5 text-foreground inline-flex min-h-11 items-center gap-1 rounded-lg border px-2.5 py-1.5 text-xs font-bold active:scale-95"
                        >
                            <Plus className="size-3.5" />
                            Agregar Extintor
                        </button>
                    )}
                </div>
                <div className="mb-3">
                    <ResumenRevision equipos={order.equipments} />
                </div>

                {/* Modal for adding/linking equipment */}
                {showAddEquipmentModal && (
                    <div className="bg-card border-primary/20 mb-4 rounded-3xl border-2 p-4 shadow-md">
                        <div className="border-border mb-3 flex items-center justify-between border-b pb-2">
                            <h3 className="text-foreground text-xs font-black">
                                Registrar Extintor en Inspección
                            </h3>
                            <button
                                type="button"
                                onClick={() => setShowAddEquipmentModal(false)}
                                className="text-muted-foreground text-xs font-bold"
                            >
                                Cancelar
                            </button>
                        </div>

                        <form
                            onSubmit={handleAddEquipment}
                            className="space-y-3"
                        >
                            <AlertError
                                errors={Object.values(addEquipmentForm.errors)}
                            />
                            {customerEquipments.length > 0 && (
                                <div>
                                    <label className="text-foreground mb-1 block text-xs font-bold">
                                        Seleccionar existente del cliente:
                                    </label>
                                    <NativeSelect
                                        aria-label="Seleccionar existente del cliente:"
                                        value={
                                            addEquipmentForm.data.equipment_id
                                        }
                                        onChange={(e) =>
                                            addEquipmentForm.setData(
                                                'equipment_id',
                                                e.target.value,
                                            )
                                        }
                                        className="border-border bg-card w-full rounded-lg border p-2 text-xs"
                                    >
                                        <option value="">
                                            -- Crear nuevo extintor en sitio --
                                        </option>
                                        {customerEquipments.map((eq) => (
                                            <option key={eq.id} value={eq.id}>
                                                {eq.numero_serie} -{' '}
                                                {eq.tipo_agente} {eq.capacidad}{' '}
                                                (
                                                {eq.ubicacion_actual ||
                                                    'Sin ubicación'}
                                                )
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </div>
                            )}

                            {!addEquipmentForm.data.equipment_id && (
                                <div className="grid grid-cols-2 gap-2">
                                    <div>
                                        <label className="text-muted-foreground mb-0.5 block text-xs font-bold">
                                            Agente
                                        </label>
                                        <NativeSelect
                                            aria-label="Agente"
                                            value={
                                                addEquipmentForm.data
                                                    .tipo_agente
                                            }
                                            onChange={(e) =>
                                                addEquipmentForm.setData(
                                                    'tipo_agente',
                                                    e.target.value,
                                                )
                                            }
                                            className="border-border bg-card w-full rounded-lg border p-2 text-xs"
                                        >
                                            <OpcionesAgente vacia="Sin registrar" />
                                        </NativeSelect>
                                    </div>
                                    <div>
                                        <label className="text-muted-foreground mb-0.5 block text-xs font-bold">
                                            Capacidad
                                        </label>
                                        <input
                                            aria-label="Capacidad"
                                            type="text"
                                            value={
                                                addEquipmentForm.data.capacidad
                                            }
                                            onChange={(e) =>
                                                addEquipmentForm.setData(
                                                    'capacidad',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="ej. 6 kg, 10 lbs"
                                            className="border-border bg-card w-full rounded-lg border p-2 text-xs"
                                        />
                                    </div>
                                    <div className="col-span-2">
                                        <label className="text-muted-foreground mb-0.5 block text-xs font-bold">
                                            Ubicación en Sede (Oficina, Pasillo,
                                            etc.)
                                        </label>
                                        <input
                                            aria-label="Ubicacion actual"
                                            type="text"
                                            value={
                                                addEquipmentForm.data
                                                    .ubicacion_actual
                                            }
                                            onChange={(e) =>
                                                addEquipmentForm.setData(
                                                    'ubicacion_actual',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="ej. Almacén 2do piso / Puerta Principal"
                                            className="border-border bg-card w-full rounded-lg border p-2 text-xs"
                                        />
                                    </div>
                                </div>
                            )}

                            <button
                                type="submit"
                                disabled={addEquipmentForm.processing}
                                className="bg-primary hover:bg-primary/90 min-h-[44px] w-full rounded-lg text-xs font-bold text-white shadow-sm transition-all"
                            >
                                Vincular a la Inspección
                            </button>
                        </form>
                    </div>
                )}

                {/* List of Extinguishers as Cards */}
                <div className="space-y-3">
                    {order.equipments.length === 0 ? (
                        <div className="border-border bg-card rounded-3xl border border-dashed p-6 text-center">
                            <p className="text-muted-foreground text-xs">
                                No hay extintores vinculados a esta orden
                                todavía.
                            </p>
                            {!isFinalizada && (
                                <button
                                    type="button"
                                    onClick={() =>
                                        setShowAddEquipmentModal(true)
                                    }
                                    className="text-foreground mt-2 text-xs font-bold underline"
                                >
                                    + Agregar primer extintor
                                </button>
                            )}
                        </div>
                    ) : (
                        order.equipments.map((eq) => {
                            const latestChecklist = eq.checklists?.[0];
                            const isEvaluating =
                                evaluatingEquipmentId === eq.id;

                            return (
                                <div
                                    key={eq.id}
                                    className="border-border bg-card rounded-3xl border p-4 shadow-xs"
                                >
                                    <div className="mb-2 flex items-start justify-between gap-2">
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <span className="text-foreground font-mono text-sm font-black">
                                                    {eq.numero_serie}
                                                </span>
                                                <span className="bg-muted text-muted-foreground rounded-md px-2 py-0.5 text-xs font-bold">
                                                    {eq.tipo_agente}{' '}
                                                    {eq.capacidad}
                                                </span>
                                            </div>
                                            <p className="text-muted-foreground mt-0.5 text-xs">
                                                <span className="text-foreground font-semibold">
                                                    Ubicación:{' '}
                                                </span>
                                                {eq.ubicacion_actual ||
                                                    'No asignada'}
                                            </p>
                                        </div>

                                        {latestChecklist ? (
                                            latestChecklist.resultado_general ===
                                            'conforme' ? (
                                                <span className="text-success-strong inline-flex min-h-11 items-center gap-1 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2 py-0.5 text-xs font-bold">
                                                    <CheckCircle2 className="size-3" />
                                                    Conforme
                                                </span>
                                            ) : (
                                                <span className="border-destructive/20 bg-destructive/10 text-destructive-strong inline-flex min-h-11 items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-bold">
                                                    <AlertCircle className="size-3" />
                                                    Con Observación
                                                </span>
                                            )
                                        ) : (
                                            <span className="text-warning-strong rounded-full border border-amber-500/20 bg-amber-500/10 px-2 py-0.5 text-xs font-bold">
                                                Pendiente
                                            </span>
                                        )}
                                    </div>

                                    {/* Action button for Checklist */}
                                    {!isFinalizada && (
                                        <div className="border-border mt-3 border-t pt-2.5">
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setEvaluatingEquipmentId(
                                                        isEvaluating
                                                            ? null
                                                            : eq.id,
                                                    )
                                                }
                                                className="bg-muted/40 hover:bg-primary/5 text-foreground flex min-h-[40px] w-full items-center justify-between rounded-lg px-3 text-xs font-bold"
                                            >
                                                <span>
                                                    {latestChecklist
                                                        ? 'Volver a Evaluar Checklist'
                                                        : 'Comenzar Checklist Técnico'}
                                                </span>
                                                {isEvaluating ? (
                                                    <ChevronUp className="size-4" />
                                                ) : (
                                                    <ChevronDown className="size-4" />
                                                )}
                                            </button>
                                        </div>
                                    )}

                                    {/* Inline Checklist Form */}
                                    {isEvaluating && (
                                        <ChecklistExtintor
                                            elementos={elementosChecklist}
                                            serie={eq.numero_serie}
                                            url={ChecklistRoutes.store.url({
                                                current_team: teamSlug,
                                                service_order: order.id,
                                                equipment: eq.id,
                                            })}
                                            esCo2={(eq.tipo_agente ?? '')
                                                .toUpperCase()
                                                .includes('CO2')}
                                            fotoObligatoria={false}
                                            onCerrar={() =>
                                                setEvaluatingEquipmentId(null)
                                            }
                                        />
                                    )}
                                </div>
                            );
                        })
                    )}
                </div>
            </div>

            {/* Deficiencies summary if any */}
            {order.deficiencies && order.deficiencies.length > 0 && (
                <div className="border-destructive/20 bg-destructive/10 mb-6 rounded-3xl border p-4">
                    <h3 className="text-destructive-strong mb-2 flex items-center gap-1.5 text-xs font-black">
                        <AlertCircle className="text-destructive-strong size-4" />
                        Deficiencias Detectadas ({order.deficiencies.length})
                    </h3>
                    <div className="space-y-2">
                        {order.deficiencies.map((def) => (
                            <div
                                key={def.id}
                                className="border-destructive/20 bg-card rounded-lg border p-2.5 text-xs"
                            >
                                <div className="flex items-center justify-between">
                                    <span className="text-foreground font-bold">
                                        {def.componente}
                                    </span>
                                    <span className="text-muted-foreground font-mono text-xs">
                                        {def.equipment?.numero_serie}
                                    </span>
                                </div>
                                <p className="text-muted-foreground mt-0.5 text-xs">
                                    {def.condicion}
                                </p>
                                {def.requiere_autorizacion && (
                                    <span className="text-destructive-strong mt-1 inline-block text-xs font-bold">
                                        Requiere cotización/autorización
                                        comercial
                                    </span>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {/* Certificates emitted if any */}
            {order.certificates && order.certificates.length > 0 && (
                <div className="mb-6 rounded-3xl border border-emerald-500/20 bg-emerald-500/10 p-4">
                    <h3 className="text-success-strong mb-2 flex items-center gap-1.5 text-xs font-black">
                        <FileCheck className="text-success-strong size-4" />
                        Certificados Emitidos
                    </h3>
                    <div className="space-y-1.5">
                        {order.certificates.map((cert) => (
                            <div
                                key={cert.id}
                                className="bg-card flex items-center justify-between rounded-lg border border-emerald-500/20 p-2.5 text-xs"
                            >
                                <span className="text-foreground font-bold">
                                    {cert.certificate_type?.nombre ||
                                        'Certificado'}
                                </span>
                                <span className="text-success-strong font-mono font-bold">
                                    {cert.numero}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {/* Finalize Inspection Form (§24, §85.6.2) */}
            {!isFinalizada && (
                <div className="border-border bg-card rounded-3xl border p-4 shadow-sm">
                    <h2 className="text-foreground mb-1 flex items-center gap-2 text-sm font-black">
                        <UserCheck className="text-primary-strong size-4" />
                        Finalizar Inspección y Conformidad en Sitio
                    </h2>
                    <p className="text-muted-foreground mb-4 text-xs">
                        Cierre técnico con conformidad del cliente.
                    </p>

                    <form onSubmit={handleComplete} className="space-y-4">
                        <div className="grid grid-cols-2 gap-2">
                            <div>
                                <label className="text-foreground mb-1 block text-xs font-bold">
                                    Responsable Técnico
                                </label>
                                <input
                                    aria-label="Responsable Técnico"
                                    type="text"
                                    value={completeForm.data.responsable}
                                    onChange={(e) =>
                                        completeForm.setData(
                                            'responsable',
                                            e.target.value,
                                        )
                                    }
                                    className="border-border bg-muted/40 w-full rounded-lg border p-2 text-xs"
                                />
                            </div>
                            <div>
                                <label className="text-foreground mb-1 block text-xs font-bold">
                                    Cargo
                                </label>
                                <input
                                    aria-label="Cargo"
                                    type="text"
                                    value={completeForm.data.cargo}
                                    onChange={(e) =>
                                        completeForm.setData(
                                            'cargo',
                                            e.target.value,
                                        )
                                    }
                                    className="border-border bg-muted/40 w-full rounded-lg border p-2 text-xs"
                                />
                            </div>
                        </div>

                        <div>
                            <label className="text-foreground mb-1 block text-xs font-bold">
                                Nombre del Receptor / Encargado de Sede{' '}
                                <span className="text-red-500">*</span>
                            </label>
                            <input
                                aria-label="Conformidad nombre"
                                type="text"
                                required
                                value={completeForm.data.conformidad_nombre}
                                onChange={(e) =>
                                    completeForm.setData(
                                        'conformidad_nombre',
                                        e.target.value,
                                    )
                                }
                                placeholder="Nombre completo de quien atiende la visita"
                                className="border-border bg-card w-full rounded-lg border p-2 text-xs"
                            />
                            {completeForm.errors.conformidad_nombre && (
                                <p className="mt-0.5 text-xs text-red-500">
                                    {completeForm.errors.conformidad_nombre}
                                </p>
                            )}
                        </div>

                        <div>
                            <label className="text-foreground mb-1 block text-xs font-bold">
                                Observaciones Generales de la Visita
                            </label>
                            <textarea
                                aria-label="Observaciones Generales de la Visita"
                                rows={2}
                                value={
                                    completeForm.data.observaciones_generales
                                }
                                onChange={(e) =>
                                    completeForm.setData(
                                        'observaciones_generales',
                                        e.target.value,
                                    )
                                }
                                placeholder="Notas sobre accesibilidad, señalética, altura de montaje..."
                                className="border-border bg-card w-full rounded-lg border p-2 text-xs"
                            />
                        </div>

                        <SubirEvidencia
                            ordenId={order.id}
                            etapa="antes"
                            titulo="Fotos antes"
                            evidencias={evidencias}
                            equipos={conversacion.equipos}
                        />
                        <SubirEvidencia
                            ordenId={order.id}
                            etapa="despues"
                            titulo="Fotos después"
                            evidencias={evidencias}
                            equipos={conversacion.equipos}
                        />

                        <FirmaCanvas
                            onChange={(f) => completeForm.setData('firma', f)}
                        />

                        {/* Checkbox de conformidad (§85.6.2) */}
                        <div className="border-primary/20 bg-primary/5 rounded-lg border p-3">
                            <label className="flex cursor-pointer items-start gap-2.5">
                                <input
                                    type="checkbox"
                                    required
                                    checked={
                                        completeForm.data.conformidad_aceptada
                                    }
                                    onChange={(e) =>
                                        completeForm.setData(
                                            'conformidad_aceptada',
                                            e.target.checked,
                                        )
                                    }
                                    className="border-primary/20 focus:ring-ring text-foreground mt-0.5 size-4 rounded"
                                />
                                <span className="text-primary-strong text-xs leading-tight font-semibold">
                                    Conformidad en sitio: El encargado del
                                    cliente valida la inspección y el inventario
                                    de extintores revisados.
                                </span>
                            </label>
                            {completeForm.errors.conformidad_aceptada && (
                                <p className="mt-1 text-xs text-red-500">
                                    {completeForm.errors.conformidad_aceptada}
                                </p>
                            )}
                        </div>

                        <button
                            type="submit"
                            disabled={
                                completeForm.processing ||
                                !completeForm.data.firma ||
                                !completeForm.data.conformidad_aceptada
                            }
                            className="bg-primary hover:bg-primary/90 flex min-h-[48px] w-full items-center justify-center gap-2 rounded-lg text-xs font-bold text-white shadow-sm transition-all disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <Send className="size-4" />
                            <span>Finalizar y Guardar Inspección de Campo</span>
                        </button>
                    </form>
                </div>
            )}
        </TecnicoCampoLayout>
    );
}
