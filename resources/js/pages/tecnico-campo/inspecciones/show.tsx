import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    Check,
    CheckCircle2,
    ChevronDown,
    ChevronUp,
    ClipboardCheck,
    Eye,
    FileCheck,
    Flame,
    HelpCircle,
    MapPin,
    Phone,
    Plus,
    Send,
    UserCheck,
    XCircle,
} from 'lucide-react';
import React, { useState } from 'react';

import TecnicoCampoLayout from '@/layouts/tecnico-campo-layout';
import type { Team } from '@/types';

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
        certificateType?: { nombre: string };
    }>;
};

type Props = {
    currentTeam?: Team | null;
    order: ServiceOrder;
    customerEquipments: Equipment[];
    elementosChecklist: Record<string, string>;
};

export default function InspeccionShow({
    currentTeam,
    order,
    customerEquipments,
    elementosChecklist,
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
        tipo_agente: 'PQS',
        capacidad: '6 kg',
        marca: 'Genérica',
        ubicacion_actual: '',
        anio_fabricacion: new Date().getFullYear().toString(),
    });

    const handleAddEquipment = (e: React.FormEvent) => {
        e.preventDefault();
        addEquipmentForm.post(
            `/${teamSlug}/tecnico-campo/inspecciones/${order.id}/equipos`,
            {
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
    });

    const handleComplete = (e: React.FormEvent) => {
        e.preventDefault();
        completeForm.post(
            `/${teamSlug}/tecnico-campo/inspecciones/${order.id}/finalizar`,
        );
    };

    const isFinalizada = [
        'listo_entrega',
        'en_ruta_entrega',
        'entregado',
        'cerrado',
    ].includes(order.estado);

    return (
        <TecnicoCampoLayout title={`Inspección ${order.codigo}`}>
            <Head title={`Inspección ${order.codigo} - Técnico de Campo`} />

            {/* Back bar */}
            <div className="mb-4 flex items-center justify-between">
                <Link
                    href={`/${teamSlug}/tecnico-campo/inspecciones`}
                    className="inline-flex items-center gap-1.5 text-xs font-bold text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" />
                    <span>Volver al listado</span>
                </Link>
                <span className="font-mono text-xs font-black text-sky-600 dark:text-sky-400">
                    {order.codigo}
                </span>
            </div>

            {flash?.success && (
                <div className="mb-4 rounded-[10px] border border-emerald-500/20 bg-emerald-500/10 p-3 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                    {flash.success}
                </div>
            )}

            {/* Header Data Card (§24) */}
            <div className="mb-5 rounded-[14px] border border-border bg-card p-4 shadow-xs">
                <div className="mb-3 flex items-start justify-between gap-2 border-b border-border pb-3">
                    <div>
                        <span className="text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                            Cliente & Sede de Inspección
                        </span>
                        <h1 className="text-base leading-snug font-black text-foreground">
                            {order.client.razon_social}
                        </h1>
                        {order.client.numero_documento && (
                            <span className="font-mono text-xs text-muted-foreground">
                                RUC/DNI: {order.client.numero_documento}
                            </span>
                        )}
                    </div>
                    <span className="rounded-full border border-sky-500/20 bg-sky-500/10 px-2.5 py-1 text-[10px] font-black text-sky-700 dark:text-sky-400">
                        {order.estado.replace('_', ' ').toUpperCase()}
                    </span>
                </div>

                <div className="space-y-2 text-xs">
                    <div className="flex items-start gap-2">
                        <MapPin className="mt-0.5 size-4 shrink-0 text-sky-600 dark:text-sky-400" />
                        <div>
                            <span className="font-semibold text-foreground">
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
                                <Phone className="size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                <span className="font-mono text-foreground">
                                    {order.client.telefono}
                                </span>
                            </div>
                            <a
                                href={`tel:${order.client.telefono}`}
                                className="inline-flex items-center gap-1 rounded-[8px] border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-1 text-[11px] font-bold text-emerald-600 dark:text-emerald-400 active:scale-95"
                            >
                                <Phone className="size-3" />
                                Llamar
                            </a>
                        </div>
                    )}
                </div>
            </div>

            {/* Extinguisher Cards Section (§24) */}
            <div className="mb-6">
                <div className="mb-3 flex items-center justify-between">
                    <div>
                        <h2 className="flex items-center gap-1.5 text-sm font-black text-foreground">
                            <Flame className="size-4 text-amber-600 dark:text-amber-400" />
                            Extintores a Inspeccionar ({order.equipments.length}
                            )
                        </h2>
                        <p className="text-[11px] text-muted-foreground">
                            Inspección física individual en sitio.
                        </p>
                    </div>
                    {!isFinalizada && (
                        <button
                            type="button"
                            onClick={() => setShowAddEquipmentModal(true)}
                            className="inline-flex items-center gap-1 rounded-[8px] border border-sky-500/20 bg-sky-500/10 px-2.5 py-1.5 text-xs font-bold text-sky-600 dark:text-sky-400 active:scale-95"
                        >
                            <Plus className="size-3.5" />
                            Agregar Extintor
                        </button>
                    )}
                </div>

                {/* Modal for adding/linking equipment */}
                {showAddEquipmentModal && (
                    <div className="mb-4 rounded-[14px] border-2 border-sky-500/20 bg-card p-4 shadow-md">
                        <div className="mb-3 flex items-center justify-between border-b border-border pb-2">
                            <h3 className="text-xs font-black text-foreground">
                                Registrar Extintor en Inspección
                            </h3>
                            <button
                                type="button"
                                onClick={() => setShowAddEquipmentModal(false)}
                                className="text-xs font-bold text-muted-foreground"
                            >
                                Cancelar
                            </button>
                        </div>

                        <form
                            onSubmit={handleAddEquipment}
                            className="space-y-3"
                        >
                            {customerEquipments.length > 0 && (
                                <div>
                                    <label className="mb-1 block text-[11px] font-bold text-foreground">
                                        Seleccionar existente del cliente:
                                    </label>
                                    <select
                                        value={
                                            addEquipmentForm.data.equipment_id
                                        }
                                        onChange={(e) =>
                                            addEquipmentForm.setData(
                                                'equipment_id',
                                                e.target.value,
                                            )
                                        }
                                        className="w-full rounded-[8px] border border-border bg-card p-2 text-xs"
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
                                    </select>
                                </div>
                            )}

                            {!addEquipmentForm.data.equipment_id && (
                                <div className="grid grid-cols-2 gap-2">
                                    <div>
                                        <label className="mb-0.5 block text-[10px] font-bold text-muted-foreground">
                                            Agente
                                        </label>
                                        <select
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
                                            className="w-full rounded-[8px] border border-border bg-card p-2 text-xs"
                                        >
                                            <option value="PQS">PQS</option>
                                            <option value="CO2">CO2</option>
                                            <option value="Agua">Agua</option>
                                            <option value="Acetato de Potasio">
                                                Acetato K
                                            </option>
                                        </select>
                                    </div>
                                    <div>
                                        <label className="mb-0.5 block text-[10px] font-bold text-muted-foreground">
                                            Capacidad
                                        </label>
                                        <input
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
                                            className="w-full rounded-[8px] border border-border bg-card p-2 text-xs"
                                        />
                                    </div>
                                    <div className="col-span-2">
                                        <label className="mb-0.5 block text-[10px] font-bold text-muted-foreground">
                                            Ubicación en Sede (Oficina, Pasillo,
                                            etc.)
                                        </label>
                                        <input
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
                                            className="w-full rounded-[8px] border border-border bg-card p-2 text-xs"
                                        />
                                    </div>
                                </div>
                            )}

                            <button
                                type="submit"
                                disabled={addEquipmentForm.processing}
                                className="min-h-[44px] w-full rounded-[10px] bg-sky-600 text-xs font-bold text-white shadow-sm transition-all hover:bg-sky-700"
                            >
                                Vincular a la Inspección
                            </button>
                        </form>
                    </div>
                )}

                {/* List of Extinguishers as Cards */}
                <div className="space-y-3">
                    {order.equipments.length === 0 ? (
                        <div className="rounded-[12px] border border-dashed border-border bg-card p-6 text-center">
                            <p className="text-xs text-muted-foreground">
                                No hay extintores vinculados a esta orden
                                todavía.
                            </p>
                            {!isFinalizada && (
                                <button
                                    type="button"
                                    onClick={() =>
                                        setShowAddEquipmentModal(true)
                                    }
                                    className="mt-2 text-xs font-bold text-sky-600 dark:text-sky-400 underline"
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
                                    className="rounded-[14px] border border-border bg-card p-4 shadow-xs"
                                >
                                    <div className="mb-2 flex items-start justify-between gap-2">
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <span className="font-mono text-sm font-black text-foreground">
                                                    {eq.numero_serie}
                                                </span>
                                                <span className="rounded-md bg-muted px-2 py-0.5 text-[10px] font-bold text-muted-foreground">
                                                    {eq.tipo_agente}{' '}
                                                    {eq.capacidad}
                                                </span>
                                            </div>
                                            <p className="mt-0.5 text-[11px] text-muted-foreground">
                                                <span className="font-semibold text-foreground">
                                                    Ubicación:{' '}
                                                </span>
                                                {eq.ubicacion_actual ||
                                                    'No asignada'}
                                            </p>
                                        </div>

                                        {latestChecklist ? (
                                            latestChecklist.resultado_general ===
                                            'conforme' ? (
                                                <span className="inline-flex items-center gap-1 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2 py-0.5 text-[10px] font-bold text-emerald-600 dark:text-emerald-400">
                                                    <CheckCircle2 className="size-3" />
                                                    Conforme
                                                </span>
                                            ) : (
                                                <span className="inline-flex items-center gap-1 rounded-full border border-destructive/20 bg-destructive/10 px-2 py-0.5 text-[10px] font-bold text-destructive">
                                                    <AlertCircle className="size-3" />
                                                    Con Observación
                                                </span>
                                            )
                                        ) : (
                                            <span className="rounded-full border border-amber-500/20 bg-amber-500/10 px-2 py-0.5 text-[10px] font-bold text-amber-600 dark:text-amber-400">
                                                Pendiente
                                            </span>
                                        )}
                                    </div>

                                    {/* Action button for Checklist */}
                                    {!isFinalizada && (
                                        <div className="mt-3 border-t border-border pt-2.5">
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setEvaluatingEquipmentId(
                                                        isEvaluating
                                                            ? null
                                                            : eq.id,
                                                    )
                                                }
                                                className="flex min-h-[40px] w-full items-center justify-between rounded-[8px] bg-muted/40 px-3 text-xs font-bold text-sky-600 dark:text-sky-400 hover:bg-sky-500/10"
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
                                        <InlineChecklistForm
                                            orderId={order.id}
                                            equipment={eq}
                                            teamSlug={teamSlug}
                                            elementosChecklist={
                                                elementosChecklist
                                            }
                                            onClose={() =>
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
                <div className="mb-6 rounded-[14px] border border-destructive/20 bg-destructive/10 p-4">
                    <h3 className="mb-2 flex items-center gap-1.5 text-xs font-black text-destructive">
                        <AlertCircle className="size-4 text-destructive" />
                        Deficiencias Detectadas ({order.deficiencies.length})
                    </h3>
                    <div className="space-y-2">
                        {order.deficiencies.map((def) => (
                            <div
                                key={def.id}
                                className="rounded-[8px] border border-destructive/20 bg-card p-2.5 text-xs"
                            >
                                <div className="flex items-center justify-between">
                                    <span className="font-bold text-foreground">
                                        {def.componente}
                                    </span>
                                    <span className="font-mono text-[10px] text-muted-foreground">
                                        {def.equipment?.numero_serie}
                                    </span>
                                </div>
                                <p className="mt-0.5 text-[11px] text-muted-foreground">
                                    {def.condicion}
                                </p>
                                {def.requiere_autorizacion && (
                                    <span className="mt-1 inline-block text-[9.5px] font-bold text-destructive">
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
                <div className="mb-6 rounded-[14px] border border-emerald-500/20 bg-emerald-500/10 p-4">
                    <h3 className="mb-2 flex items-center gap-1.5 text-xs font-black text-emerald-600 dark:text-emerald-400">
                        <FileCheck className="size-4 text-emerald-600 dark:text-emerald-400" />
                        Certificados Emitidos
                    </h3>
                    <div className="space-y-1.5">
                        {order.certificates.map((cert) => (
                            <div
                                key={cert.id}
                                className="flex items-center justify-between rounded-[8px] border border-emerald-500/20 bg-card p-2.5 text-xs"
                            >
                                <span className="font-bold text-foreground">
                                    {cert.certificateType?.nombre ||
                                        'Certificado'}
                                </span>
                                <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                    {cert.numero}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {/* Finalize Inspection Form (§24, §85.6.2) */}
            {!isFinalizada && (
                <div className="rounded-[14px] border border-border bg-card p-4 shadow-sm">
                    <h2 className="mb-1 flex items-center gap-2 text-sm font-black text-foreground">
                        <UserCheck className="size-4 text-sky-600 dark:text-sky-400" />
                        Finalizar Inspección y Conformidad en Sitio
                    </h2>
                    <p className="mb-4 text-[11px] text-muted-foreground">
                        Cierre técnico con conformidad del cliente.
                    </p>

                    <form onSubmit={handleComplete} className="space-y-4">
                        <div className="grid grid-cols-2 gap-2">
                            <div>
                                <label className="mb-1 block text-[11px] font-bold text-foreground">
                                    Responsable Técnico
                                </label>
                                <input
                                    type="text"
                                    value={completeForm.data.responsable}
                                    onChange={(e) =>
                                        completeForm.setData(
                                            'responsable',
                                            e.target.value,
                                        )
                                    }
                                    className="w-full rounded-[8px] border border-border bg-muted/40 p-2 text-xs"
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-[11px] font-bold text-foreground">
                                    Cargo
                                </label>
                                <input
                                    type="text"
                                    value={completeForm.data.cargo}
                                    onChange={(e) =>
                                        completeForm.setData(
                                            'cargo',
                                            e.target.value,
                                        )
                                    }
                                    className="w-full rounded-[8px] border border-border bg-muted/40 p-2 text-xs"
                                />
                            </div>
                        </div>

                        <div>
                            <label className="mb-1 block text-[11px] font-bold text-foreground">
                                Nombre del Receptor / Encargado de Sede{' '}
                                <span className="text-red-500">*</span>
                            </label>
                            <input
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
                                className="w-full rounded-[8px] border border-border bg-card p-2 text-xs"
                            />
                            {completeForm.errors.conformidad_nombre && (
                                <p className="mt-0.5 text-[10px] text-red-500">
                                    {completeForm.errors.conformidad_nombre}
                                </p>
                            )}
                        </div>

                        <div>
                            <label className="mb-1 block text-[11px] font-bold text-foreground">
                                Observaciones Generales de la Visita
                            </label>
                            <textarea
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
                                className="w-full rounded-[8px] border border-border bg-card p-2 text-xs"
                            />
                        </div>

                        {/* Checkbox de conformidad (§85.6.2) */}
                        <div className="rounded-[10px] border border-sky-500/20 bg-sky-500/10 p-3">
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
                                    className="mt-0.5 size-4 rounded border-sky-500/20 text-sky-600 dark:text-sky-400 focus:ring-[#0284C7]"
                                />
                                <span className="text-xs leading-tight font-semibold text-sky-700 dark:text-sky-400">
                                    Conformidad en sitio: El encargado del
                                    cliente valida la inspección y el inventario
                                    de extintores revisados.
                                </span>
                            </label>
                            {completeForm.errors.conformidad_aceptada && (
                                <p className="mt-1 text-[10px] text-red-500">
                                    {completeForm.errors.conformidad_aceptada}
                                </p>
                            )}
                        </div>

                        <button
                            type="submit"
                            disabled={
                                completeForm.processing ||
                                !completeForm.data.conformidad_aceptada
                            }
                            className="flex min-h-[48px] w-full items-center justify-center gap-2 rounded-[10px] bg-sky-600 text-xs font-bold text-white shadow-sm transition-all hover:bg-sky-700 disabled:cursor-not-allowed disabled:opacity-50"
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

/**
 * Formulario interactivo táctil para evaluar los 14 elementos del extintor
 */
function InlineChecklistForm({
    orderId,
    equipment,
    teamSlug,
    elementosChecklist,
    onClose,
}: {
    orderId: number;
    equipment: Equipment;
    teamSlug: string;
    elementosChecklist: Record<string, string>;
    onClose: () => void;
}) {
    const isCo2 = (equipment.tipo_agente || '').toUpperCase().includes('CO2');

    // Inicializar los estados de los 14 elementos
    const initialItems = Object.keys(elementosChecklist).reduce(
        (acc, clave) => {
            // En CO2, manómetro no aplica automáticamente (§19)
            const autoNoAplica = isCo2 && clave === 'manometro';
            acc[clave] = {
                estado: autoNoAplica ? 'no_aplica' : 'conforme',
                condicion: '',
                nota: '',
                requiere_autorizacion: false,
            };
            return acc;
        },
        {} as Record<
            string,
            {
                estado: string;
                condicion: string;
                nota: string;
                requiere_autorizacion: boolean;
            }
        >,
    );

    const checklistForm = useForm({
        items: initialItems,
        observaciones: '',
    });

    const setItemEstado = (clave: string, estado: string) => {
        checklistForm.setData('items', {
            ...checklistForm.data.items,
            [clave]: {
                ...checklistForm.data.items[clave],
                estado,
            },
        });
    };

    const setItemField = (clave: string, field: string, val: unknown) => {
        checklistForm.setData('items', {
            ...checklistForm.data.items,
            [clave]: {
                ...checklistForm.data.items[clave],
                [field]: val,
            },
        });
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        checklistForm.post(
            `/${teamSlug}/tecnico-campo/inspecciones/${orderId}/equipos/${equipment.id}/checklist`,
            {
                onSuccess: () => onClose(),
            },
        );
    };

    return (
        <form
            onSubmit={handleSubmit}
            className="mt-3 space-y-3 rounded-[10px] border border-sky-500/20 bg-sky-500/10 p-3"
        >
            <div className="flex items-center justify-between border-b border-sky-500/20 pb-2">
                <span className="text-xs font-black text-sky-700 dark:text-sky-400">
                    Evaluación Checklist: {equipment.numero_serie}
                </span>
                <button
                    type="button"
                    onClick={onClose}
                    className="text-[11px] font-bold text-muted-foreground"
                >
                    Cerrar
                </button>
            </div>

            <div className="space-y-2">
                {Object.entries(elementosChecklist).map(([clave, nombre]) => {
                    const item = checklistForm.data.items[clave] || {
                        estado: 'conforme',
                    };
                    const isObservado = item.estado === 'observado';

                    return (
                        <div
                            key={clave}
                            className="rounded-[8px] border border-border bg-card p-2.5"
                        >
                            <div className="mb-1.5 flex items-center justify-between gap-2">
                                <span className="text-[11px] font-bold text-foreground">
                                    {nombre}
                                </span>
                                <div className="flex items-center gap-1">
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setItemEstado(clave, 'conforme')
                                        }
                                        className={`min-h-[32px] rounded-[6px] px-2 py-1 text-[10px] font-bold transition-all ${
                                            item.estado === 'conforme'
                                                ? 'bg-emerald-600 text-white shadow-xs'
                                                : 'bg-muted text-muted-foreground'
                                        }`}
                                    >
                                        OK
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setItemEstado(clave, 'observado')
                                        }
                                        className={`min-h-[32px] rounded-[6px] px-2 py-1 text-[10px] font-bold transition-all ${
                                            item.estado === 'observado'
                                                ? 'bg-destructive text-white shadow-xs'
                                                : 'bg-muted text-muted-foreground'
                                        }`}
                                    >
                                        Obs
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setItemEstado(clave, 'no_aplica')
                                        }
                                        className={`min-h-[32px] rounded-[6px] px-2 py-1 text-[10px] font-bold transition-all ${
                                            item.estado === 'no_aplica'
                                                ? 'bg-muted-foreground text-white shadow-xs'
                                                : 'bg-muted text-muted-foreground'
                                        }`}
                                    >
                                        N/A
                                    </button>
                                </div>
                            </div>

                            {/* Detalle si está observado */}
                            {isObservado && (
                                <div className="mt-2 space-y-1.5 border-t border-destructive/20 pt-2">
                                    <input
                                        type="text"
                                        placeholder="Descripción de la condición observada..."
                                        value={item.condicion || ''}
                                        onChange={(e) =>
                                            setItemField(
                                                clave,
                                                'condicion',
                                                e.target.value,
                                            )
                                        }
                                        className="w-full rounded-[6px] border border-destructive/20 bg-destructive/10 p-1.5 text-xs"
                                    />
                                    <label className="flex items-center gap-1.5 text-[10.5px] font-semibold text-destructive">
                                        <input
                                            type="checkbox"
                                            checked={
                                                item.requiere_autorizacion ||
                                                false
                                            }
                                            onChange={(e) =>
                                                setItemField(
                                                    clave,
                                                    'requiere_autorizacion',
                                                    e.target.checked,
                                                )
                                            }
                                            className="size-3.5 rounded border-destructive/20 text-destructive"
                                        />
                                        <span>
                                            Requiere cotización/autorización de
                                            cliente
                                        </span>
                                    </label>
                                </div>
                            )}
                        </div>
                    );
                })}
            </div>

            <button
                type="submit"
                disabled={checklistForm.processing}
                className="min-h-[44px] w-full rounded-[8px] bg-emerald-600 text-xs font-bold text-white shadow-sm hover:bg-emerald-700"
            >
                Guardar Checklist de este Extintor
            </button>
        </form>
    );
}
