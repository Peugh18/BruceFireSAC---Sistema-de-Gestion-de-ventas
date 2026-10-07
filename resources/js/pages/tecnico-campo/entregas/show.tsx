import { Head, Link, useForm, usePage } from '@inertiajs/react';
import TomarOrden, { type AsignacionOrden } from '@/components/tomar-orden';
import {
    ArrowLeft,
    CheckCircle2,
    FileText,
    Flame,
    MapPin,
    PackageCheck,
    Phone,
    Send,
    ShieldCheck,
    Truck,
    UserCheck,
    MessageSquare,
} from 'lucide-react';
import React from 'react';
import ConversacionOrden, { type ConversacionProps } from '@/components/conversacion-orden';
import FirmaCanvas from '@/components/firma-canvas';
import SubirEvidencia, { type EvidenciaListada } from '@/components/subir-evidencia';
import guias from '@/routes/guias';
import entregas from '@/routes/tecnico-campo/entregas';

import TecnicoCampoLayout from '@/layouts/tecnico-campo-layout';
import type { Team } from '@/types';

type Client = {
    id: number;
    razon_social: string;
    numero_documento?: string;
    telefono?: string;
    direccion_fiscal?: string;
};

type Sede = {
    id: number;
    nombre: string;
    direccion?: string;
};

type Equipment = {
    id: number;
    numero_serie: string;
    serie_fabricante?: string | null;
    tipo_agente?: string | null;
    capacidad?: string | null;
    marca?: string | null;
    ubicacion_actual?: string | null;
};

type CustodyEvent = {
    id: number;
    eslabon: string;
    responsable: string;
    fecha?: string;
    payload?: Record<string, unknown>;
};

type ServiceOrder = {
    id: number;
    codigo: string;
    estado: string;
    tipo_servicio?: string;
    fecha?: string;
    observaciones?: string | null;
    events?: Array<{
        id: number;
        tipo: string;
        payload: any;
        created_at: string;
    }>;
    client: Client;
    sede?: Sede;
    equipments: Equipment[];
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
    custodyEvents: CustodyEvent[];
    conversacion: ConversacionProps;
    evidencias: EvidenciaListada[];
};

const ESLABONES_MAP: Record<
    string,
    { label: string; icon: typeof Truck; color: string }
> = {
    recojo_campo: {
        label: 'Recojo en Campo',
        icon: Truck,
        color: 'text-sky-600 dark:text-sky-400 bg-sky-500/10',
    },
    recepcion_planta: {
        label: 'Recepción en Planta',
        icon: PackageCheck,
        color: 'text-warning-strong bg-amber-500/10',
    },
    procesado_planta: {
        label: 'Taller / Recarga',
        icon: Flame,
        color: 'text-warning-strong bg-amber-500/10',
    },
    instalacion_campo: {
        label: 'Instalación en Sitio',
        icon: ShieldCheck,
        color: 'text-success-strong bg-emerald-500/10',
    },
    inspeccion_campo: {
        label: 'Inspección en Sitio',
        icon: CheckCircle2,
        color: 'text-sky-700 dark:text-sky-400 bg-sky-500/10',
    },
    entrega_campo: {
        label: 'Entrega al Cliente',
        icon: UserCheck,
        color: 'text-success-strong bg-emerald-500/10',
    },
};

export default function EntregaShow({
    asignacion,
    currentTeam,
    order,
    custodyEvents,
    conversacion,
    evidencias,
}: Props) {
    const teamSlug = currentTeam?.slug ?? '';
    const { flash } = usePage<{
        auth?: { user?: { name?: string } };
        flash?: { success?: string; error?: string };
    }>().props;

    const isCerrada = order.estado === 'cerrado';

    const form = useForm({
        receptor_nombre: '',
        receptor_dni: '',
        observaciones_entrega: '',
        conformidad_aceptada: false,
        cerrar_orden: true,
        firma: null as string | null,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(entregas.confirm.url({ current_team: teamSlug, service_order: order.id }));
    };

    return (
        <TecnicoCampoLayout title={`Entrega ${order.codigo}`}>
            <Head title={`Entrega ${order.codigo} - Técnico de Campo`} />

            {/* Back Bar */}
            <div className="mb-4 flex items-center justify-between">
                <Link
                    href={`/${teamSlug}/tecnico-campo/entregas`}
                    className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-xs font-bold"
                >
                    <ArrowLeft className="size-4" />
                    <span>Volver a entregas</span>
                </Link>
                <Link
                    href={guias.create.url(teamSlug, {
                        query: { origen: 'entrega', id: order.id },
                    })}
                    className="bg-primary inline-flex h-10 items-center rounded-xl px-4 text-xs font-bold text-white"
                >
                    Guía de remisión
                </Link>
                <span className="font-mono text-xs font-black text-sky-600 dark:text-sky-400">
                    {order.codigo}
                </span>
            </div>

            {flash?.success && (
                <div className="text-success-strong mb-4 rounded-[10px] border border-emerald-500/20 bg-emerald-500/10 p-3 text-xs font-medium">
                    {flash.success}
                </div>
            )}

            {/* Header Data Card (§22.3) */}
            <div className="border-border bg-card mb-5 rounded-[14px] border p-4 shadow-xs">
                <div className="border-border mb-3 flex items-start justify-between gap-2 border-b pb-3">
                    <div>
                        <span className="text-muted-foreground text-[10px] font-bold tracking-wider uppercase">
                            Cliente Receptor
                        </span>
                        <h1 className="text-foreground text-base leading-snug font-black">
                            {order.client.razon_social}
                        </h1>
                        {order.client.numero_documento && (
                            <span className="text-muted-foreground font-mono text-xs">
                                RUC / DNI: {order.client.numero_documento}
                            </span>
                        )}
                    </div>
                    <span
                        className={`rounded-full border px-2.5 py-1 text-[10px] font-black ${
                            isCerrada
                                ? 'border-border bg-muted text-muted-foreground'
                                : 'text-success-strong border-emerald-500/20 bg-emerald-500/10'
                        }`}
                    >
                        {order.estado.replace('_', ' ').toUpperCase()}
                    </span>
                </div>

                <div className="space-y-2 text-xs">
                    <div className="flex items-start gap-2">
                        <MapPin className="mt-0.5 size-4 shrink-0 text-sky-600 dark:text-sky-400" />
                        <div>
                            <span className="text-foreground font-semibold">
                                Lugar de Entrega:{' '}
                            </span>
                            <span className="text-muted-foreground">
                                {order.sede?.direccion ||
                                    order.client.direccion_fiscal ||
                                    'Sede principal'}
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
                                className="text-success-strong inline-flex items-center gap-1 rounded-[8px] border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-1 text-[11px] font-bold active:scale-95 dark:text-emerald-400"
                            >
                                <Phone className="size-3" />
                                Llamar
                            </a>
                        </div>
                    )}
                </div>

                {/* Acta de Conformidad PDF Action */}
                <div className="border-border mt-4 border-t pt-3">
                    <a
                        href={`/${teamSlug}/tecnico-campo/entregas/${order.id}/acta-pdf`}
                        target="_blank"
                        rel="noreferrer"
                        className="flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[10px] border border-sky-500/20 bg-sky-500/10 text-xs font-black text-sky-600 shadow-2xs transition-all hover:bg-sky-500/10 dark:text-sky-400"
                    >
                        <FileText className="size-4" />
                        <span>
                            Descargar / Imprimir Acta de Conformidad (PDF)
                        </span>
                    </a>
                </div>
            </div>

            <TomarOrden asignacion={asignacion} />

            {/* Indicaciones de Ventas / Notas de Coordinación */}
            {(() => {
                const notasVendedor = (order.events || []).filter(
                    (ev) =>
                        ev.payload?.origen === 'vendedor' ||
                        ev.tipo === 'notificacion_vendedor' ||
                        (ev.tipo === 'otro' && ev.payload?.mensaje),
                );

                if (!order.observaciones && notasVendedor.length === 0) {
                    return null;
                }

                return (
                    <div className="mb-5 rounded-[14px] border border-blue-200 bg-blue-50/70 p-4 dark:border-blue-900/50 dark:bg-blue-950/30">
                        <div className="flex items-center gap-2 text-xs font-bold text-blue-900 dark:text-blue-300">
                            <MessageSquare className="size-4 text-blue-600 dark:text-blue-400" />
                            <span>Indicaciones de Ventas y Coordinación</span>
                        </div>

                        {order.observaciones && (
                            <p className="mt-2 text-xs text-blue-800 dark:text-blue-200">
                                <span className="font-semibold text-blue-950 dark:text-blue-100">
                                    Observación inicial:{' '}
                                </span>
                                {order.observaciones}
                            </p>
                        )}

                        {notasVendedor.length > 0 && (
                            <div className="mt-3 space-y-2">
                                <span className="text-[11px] font-bold text-blue-900/70 uppercase dark:text-blue-300/70">
                                    Notas de Ventas:
                                </span>
                                {notasVendedor.map((n) => (
                                    <div
                                        key={n.id}
                                        className="rounded-xl border border-blue-100 bg-white/80 p-2.5 text-xs text-neutral-800 shadow-2xs dark:border-blue-800/40 dark:bg-neutral-900/80 dark:text-neutral-200"
                                    >
                                        <p className="font-medium">
                                            {n.payload?.mensaje ||
                                                n.payload?.descripcion ||
                                                JSON.stringify(n.payload)}
                                        </p>
                                        <span className="mt-1 block text-[10.5px] text-neutral-400">
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

            {/* Custody Chain Trail (§22.4, §85.6.3) */}
            <div className="border-border bg-card mb-5 rounded-[14px] border p-4 shadow-xs">
                <h2 className="text-foreground mb-3 flex items-center gap-2 text-xs font-black tracking-wider uppercase">
                    <Truck className="size-4 text-sky-600 dark:text-sky-400" />
                    Cadena de Custodia del Servicio
                </h2>

                {custodyEvents.length === 0 ? (
                    <p className="text-muted-foreground text-xs">
                        No hay eventos de custodia registrados aún.
                    </p>
                ) : (
                    <div className="before:bg-muted/40 relative space-y-4 pl-6 before:absolute before:top-2 before:bottom-2 before:left-2.5 before:w-0.5">
                        {custodyEvents.map((evt) => {
                            const meta = ESLABONES_MAP[evt.eslabon] || {
                                label: evt.eslabon,
                                icon: CheckCircle2,
                                color: 'text-muted-foreground bg-muted',
                            };
                            const Icon = meta.icon;

                            return (
                                <div key={evt.id} className="relative text-xs">
                                    <div
                                        className={`absolute top-0 -left-6 flex size-5 items-center justify-center rounded-full border border-white shadow-2xs ${meta.color}`}
                                    >
                                        <Icon className="size-3" />
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-foreground font-bold">
                                            {meta.label}
                                        </span>
                                        {evt.fecha && (
                                            <span className="text-muted-foreground font-mono text-[10px]">
                                                {new Date(
                                                    evt.fecha,
                                                ).toLocaleDateString('es-PE', {
                                                    day: '2-digit',
                                                    month: '2-digit',
                                                    hour: '2-digit',
                                                    minute: '2-digit',
                                                })}
                                            </span>
                                        )}
                                    </div>
                                    <p className="text-muted-foreground mt-0.5 text-[11px]">
                                        Responsable:{' '}
                                        <span className="text-foreground font-semibold">
                                            {evt.responsable}
                                        </span>
                                    </p>
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>

            {/* Equipments Table/Cards (§23) */}
            <div className="border-border bg-card mb-5 rounded-[14px] border p-4 shadow-xs">
                <h2 className="text-foreground mb-3 flex items-center gap-2 text-xs font-black tracking-wider uppercase">
                    <Flame className="text-warning-strong size-4" />
                    Equipos Entregados ({order.equipments.length})
                </h2>

                <div className="space-y-2">
                    {order.equipments.map((eq, _idx) => (
                        <div
                            key={eq.id}
                            className="border-border bg-muted/40 flex items-center justify-between rounded-[8px] border p-2.5 text-xs"
                        >
                            <div>
                                <div className="flex items-center gap-2">
                                    <span className="font-mono font-black text-sky-600 dark:text-sky-400">
                                        {eq.numero_serie}
                                    </span>
                                    <span className="py-0.2 border-border bg-card rounded border px-1.5 text-[9.5px] font-bold">
                                        {eq.tipo_agente} {eq.capacidad}
                                    </span>
                                </div>
                                <p className="text-muted-foreground mt-0.5 text-[10.5px]">
                                    {eq.marca || 'Bruce Fire'} •{' '}
                                    {eq.ubicacion_actual || 'Sede cliente'}
                                </p>
                            </div>
                            <span className="text-success-strong rounded-full bg-emerald-500/10 px-2 py-0.5 text-[9.5px] font-bold">
                                Conforme
                            </span>
                        </div>
                    ))}
                </div>
            </div>

            {/* Delivery Confirmation Form (§22.3, §85.6.2) */}
            {!isCerrada && (
                <div className="bg-card rounded-[14px] border border-sky-500/20 p-4 shadow-sm">
                    <h2 className="mb-1 flex items-center gap-2 text-sm font-black text-sky-700 dark:text-sky-400">
                        <UserCheck className="size-4 text-sky-600 dark:text-sky-400" />
                        Confirmar Entrega y Acta de Conformidad
                    </h2>
                    <p className="text-muted-foreground mb-4 text-[11px]">
                        Cierre final del servicio en sitio con firma/conformidad
                        del receptor.
                    </p>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <label className="text-foreground mb-1 block text-[11px] font-bold">
                                    Nombre del Receptor / Encargado en Sede{' '}
                                    <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    required
                                    value={form.data.receptor_nombre}
                                    onChange={(e) =>
                                        form.setData(
                                            'receptor_nombre',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Nombre de quien recibe los extintores"
                                    className="border-border bg-card w-full rounded-[8px] border p-2 text-xs"
                                />
                                {form.errors.receptor_nombre && (
                                    <p className="mt-0.5 text-[10px] text-red-500">
                                        {form.errors.receptor_nombre}
                                    </p>
                                )}
                            </div>

                            <div>
                                <label className="text-foreground mb-1 block text-[11px] font-bold">
                                    DNI / Cargo del Receptor
                                </label>
                                <input
                                    type="text"
                                    value={form.data.receptor_dni}
                                    onChange={(e) =>
                                        form.setData(
                                            'receptor_dni',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="ej. DNI 45892147 / Jefe de Almacén"
                                    className="border-border bg-card w-full rounded-[8px] border p-2 text-xs"
                                />
                            </div>
                        </div>

                        <div>
                            <label className="text-foreground mb-1 block text-[11px] font-bold">
                                Observaciones de Entrega
                            </label>
                            <textarea
                                rows={2}
                                value={form.data.observaciones_entrega}
                                onChange={(e) =>
                                    form.setData(
                                        'observaciones_entrega',
                                        e.target.value,
                                    )
                                }
                                placeholder="Notas de colocación, tarjetas selladas entregadas..."
                                className="border-border bg-card w-full rounded-[8px] border p-2 text-xs"
                            />
                        </div>

                        {/* Checkbox de Conformidad en Sitio (§85.6.2) */}
                        <div className="rounded-[10px] border border-sky-500/20 bg-sky-500/10 p-3">
                            <label className="flex cursor-pointer items-start gap-2.5">
                                <input
                                    type="checkbox"
                                    required
                                    checked={form.data.conformidad_aceptada}
                                    onChange={(e) =>
                                        form.setData(
                                            'conformidad_aceptada',
                                            e.target.checked,
                                        )
                                    }
                                    className="mt-0.5 size-4 rounded border-sky-500/20 text-sky-600 dark:text-sky-400"
                                />
                                <span className="text-xs leading-tight font-semibold text-sky-700 dark:text-sky-400">
                                    Conformidad en sitio: El receptor declara
                                    recibir los extintores detallados
                                    debidamente inspeccionados, cargados y
                                    operativos según NTP 350.043.
                                </span>
                            </label>
                            {form.errors.conformidad_aceptada && (
                                <p className="mt-1 text-[10px] text-red-500">
                                    {form.errors.conformidad_aceptada}
                                </p>
                            )}
                        </div>

                        <SubirEvidencia
                            ordenId={order.id}
                            etapa="entrega"
                            titulo="Fotos de la entrega"
                            evidencias={evidencias}
                            equipos={conversacion.equipos}
                        />

                        <FirmaCanvas
                            etiqueta="Firma de quien recibe"
                            onChange={(f) => form.setData('firma', f)}
                        />

                        <label className="text-foreground flex cursor-pointer items-center gap-2 text-xs font-bold">
                            <input
                                type="checkbox"
                                checked={form.data.cerrar_orden}
                                onChange={(e) =>
                                    form.setData(
                                        'cerrar_orden',
                                        e.target.checked,
                                    )
                                }
                                className="size-4 rounded border-sky-500/20 text-sky-600 dark:text-sky-400"
                            />
                            <span>
                                Cerrar la Orden de Servicio definitivamente
                                (estado: cerrado)
                            </span>
                        </label>

                        <button
                            type="submit"
                            disabled={form.processing || !form.data.firma}
                            className="flex min-h-[48px] w-full items-center justify-center gap-2 rounded-[10px] bg-sky-600 text-xs font-bold text-white shadow-sm transition-all hover:bg-sky-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <Send className="size-4" />
                            <span>
                                Confirmar Entrega y Cerrar Orden de Servicio
                            </span>
                        </button>
                        {!form.data.conformidad_aceptada ? (
                            <p
                                className="text-center text-[11px] font-semibold text-red-500"
                                role="alert"
                            >
                                Marca la conformidad del receptor antes de
                                confirmar la entrega.
                            </p>
                        ) : null}
                    </form>
                </div>
            )}

            <div className="mt-5">
                <ConversacionOrden ordenId={order.id} conversacion={conversacion} />
            </div>
        </TecnicoCampoLayout>
    );
}
