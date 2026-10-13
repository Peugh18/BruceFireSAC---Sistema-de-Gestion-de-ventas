import { InputPeru } from '@/components/ui/input-peru';
import React from 'react';
import ConversacionOrden, {
    type ConversacionProps,
} from '@/components/conversacion-orden';
import FirmaCanvas from '@/components/firma-canvas';
import SubirEvidencia, {
    type EvidenciaListada,
} from '@/components/subir-evidencia';
import guias from '@/routes/guias';
import recojos from '@/routes/tecnico-campo/recojos';
import TomarOrden, { type AsignacionOrden } from '@/components/tomar-orden';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import TecnicoCampoLayout from '@/layouts/tecnico-campo-layout';
import type { Team } from '@/types';
import {
    ArrowLeft,
    Truck,
    MapPin,
    Phone,
    CheckCircle2,
    ShieldCheck,
    MessageSquare,
} from 'lucide-react';
import AlertError from '@/components/alert-error';
import OpcionesAgente from '@/components/opciones-agente';
import { toast } from 'sonner';
import EquiposRoutes from '@/routes/tecnico-campo/recojos/equipos';
import { NativeSelect } from '@/components/ui/native-select';

interface CustodyEventItem {
    id: number;
    eslabon: string;
    etapa: string;
    responsable: string;
    fecha: string;
    payload: Record<string, string | number | boolean | null | undefined>;
}

interface Props {
    asignacion: AsignacionOrden;
    order: {
        id: number;
        codigo: string;
        cliente: {
            nombre: string;
            documento: string;
            direccion: string | null;
            telefono: string | null;
        };
        vehiculo: string | null;
        tipo_servicio: string;
        fecha: string;
        prioridad: string;
        estado: string;
        observaciones: string | null;
        notas_vendedor?: Array<{
            id: number;
            mensaje: string;
            fecha: string;
        }>;
        equipments: Array<{
            id: number;
            numero_serie: string;
            tipo_agente: string | null;
            capacidad: string | null;
            marca: string | null;
        }>;
    };
    custodyEvents: CustodyEventItem[];
    conversacion: ConversacionProps;
    evidencias: EvidenciaListada[];
}

export default function RecojoShow({
    asignacion,
    order,
    custodyEvents,
    conversacion,
    evidencias,
}: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug = currentTeam?.slug ?? '';

    const form = useForm({
        contacto_nombre: '',
        contacto_telefono: order.cliente.telefono || '',
        observaciones: '',
        conformidad_cliente: false,
        firma: null as string | null,
    });

    const equipmentForm = useForm({
        numero_serie: '',
        tipo_agente: '',
        capacidad: '',
        marca: '',
        serie_fabricante: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(
            recojos.store.url({
                current_team: teamSlug,
                service_order: order.id,
            }),
            {
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ??
                            'No se pudo completar la acción.',
                    ),
                preserveScroll: true,
            },
        );
    };

    const yaRecogido = custodyEvents.some((e) => e.eslabon === 'recojo_campo');

    return (
        <TecnicoCampoLayout>
            <Head title={`Recojo ${order.codigo} - Técnico de Campo`} />

            <div className="space-y-4 pb-16">
                {/* Back Link */}
                <Link
                    href={recojos.index.url({ current_team: teamSlug })}
                    className="inline-flex items-center gap-1.5 text-xs font-semibold text-neutral-600 hover:text-neutral-900 dark:text-neutral-400"
                >
                    <ArrowLeft className="h-4 w-4" />
                    <span>Volver a Lista de Recojos</span>
                </Link>
                <Link
                    href={guias.create.url(teamSlug, {
                        query: { origen: 'recojo', id: order.id },
                    })}
                    className="bg-primary inline-flex h-10 items-center rounded-xl px-4 text-xs font-bold text-white transition hover:brightness-95"
                >
                    Emitir guía de remisión (antes de salir)
                </Link>

                {/* Client & Service Info Card */}
                <div className="bg-card space-y-3 rounded-2xl border border-neutral-200 p-4 shadow-sm dark:border-neutral-700/80 dark:bg-neutral-800">
                    <div className="flex items-start justify-between gap-2">
                        <div>
                            <div className="flex items-center gap-2">
                                <span className="font-mono text-base font-extrabold text-neutral-900 dark:text-neutral-100">
                                    {order.codigo}
                                </span>
                                {yaRecogido ? (
                                    <span className="text-success-strong rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold dark:bg-emerald-950/60 dark:text-emerald-300">
                                        Recojo Registrado
                                    </span>
                                ) : (
                                    <span className="text-warning-strong rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold dark:bg-amber-950/60 dark:text-amber-300">
                                        Pendiente de Recojo
                                    </span>
                                )}
                            </div>
                            <h2 className="mt-1 text-base font-bold text-neutral-900 dark:text-neutral-100">
                                {order.cliente.nombre}
                            </h2>
                            <p className="font-mono text-xs text-neutral-500">
                                RUC/DNI: {order.cliente.documento}
                            </p>
                        </div>
                    </div>

                    <div className="space-y-2 border-t border-neutral-100 pt-2 text-xs text-neutral-600 dark:border-neutral-700/60 dark:text-neutral-400">
                        {order.cliente.direccion && (
                            <div className="flex items-start gap-1.5">
                                <MapPin className="text-foreground mt-0.5 h-4 w-4 flex-shrink-0" />
                                <a
                                    href={`https://maps.google.com/?q=${encodeURIComponent(order.cliente.direccion)}`}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="text-foreground hover:underline"
                                >
                                    {order.cliente.direccion}
                                </a>
                            </div>
                        )}
                        {order.cliente.telefono && (
                            <div className="flex items-center gap-1.5">
                                <Phone className="text-success-strong h-4 w-4 flex-shrink-0" />
                                <a
                                    href={`tel:${order.cliente.telefono}`}
                                    className="font-medium text-neutral-800 dark:text-neutral-200"
                                >
                                    {order.cliente.telefono}
                                </a>
                            </div>
                        )}
                        {order.vehiculo && (
                            <div className="text-xs text-neutral-500">
                                🚗 Vehículo asignado: {order.vehiculo}
                            </div>
                        )}
                    </div>
                </div>

                <TomarOrden asignacion={asignacion} />

                {/* Indicaciones de Ventas / Notas de Coordinación */}
                {(() => {
                    const notas = order.notas_vendedor || [];
                    if (!order.observaciones && notas.length === 0) {
                        return null;
                    }

                    return (
                        <div className="border-border bg-muted rounded-2xl border p-4">
                            <div className="text-foreground flex items-center gap-2 text-xs font-bold">
                                <MessageSquare className="text-foreground size-4" />
                                <span>
                                    Indicaciones de Ventas y Coordinación
                                </span>
                            </div>

                            {order.observaciones && (
                                <p className="text-foreground mt-2 text-xs">
                                    <span className="text-foreground font-semibold">
                                        Observación inicial:{' '}
                                    </span>
                                    {order.observaciones}
                                </p>
                            )}

                            {notas.length > 0 && (
                                <div className="mt-3 space-y-2">
                                    <span className="text-foreground text-xs font-bold uppercase">
                                        Notas de Ventas:
                                    </span>
                                    {notas.map((n) => (
                                        <div
                                            key={n.id}
                                            className="border-border rounded-xl border bg-white/80 p-2.5 text-xs text-neutral-800 shadow-2xs dark:bg-neutral-900/80 dark:text-neutral-200"
                                        >
                                            <p className="font-medium">
                                                {n.mensaje}
                                            </p>
                                            <span className="mt-1 block text-xs text-neutral-400">
                                                {n.fecha
                                                    ? new Date(
                                                          n.fecha,
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

                {/* Cadena de Custodia Timeline (§22.4) */}
                <div className="bg-card space-y-3 rounded-2xl border border-neutral-200 p-4 shadow-sm dark:border-neutral-700/80 dark:bg-neutral-800">
                    <h3 className="flex items-center gap-1.5 text-xs font-bold tracking-wider text-neutral-700 uppercase dark:text-neutral-300">
                        <ShieldCheck className="text-foreground h-4 w-4" />
                        <span>Cadena de Custodia</span>
                    </h3>

                    {custodyEvents.length === 0 ? (
                        <p className="text-xs text-neutral-400 italic">
                            Aún no se han registrado eslabones de custodia para
                            esta orden.
                        </p>
                    ) : (
                        <div className="relative space-y-3 before:absolute before:inset-0 before:left-2.5 before:w-0.5 before:bg-neutral-200 dark:before:bg-neutral-700">
                            {custodyEvents.map((ev) => (
                                <div
                                    key={ev.id}
                                    className="relative space-y-1 pl-7"
                                >
                                    <div className="bg-primary absolute top-1 left-1 h-3.5 w-3.5 rounded-full ring-4 ring-white dark:ring-neutral-800" />
                                    <div className="text-xs font-bold text-neutral-900 dark:text-neutral-100">
                                        {ev.etapa || ev.eslabon}
                                    </div>
                                    <div className="text-xs text-neutral-500">
                                        Por: {ev.responsable} •{' '}
                                        {new Date(ev.fecha).toLocaleString(
                                            'es-PE',
                                            {
                                                dateStyle: 'short',
                                                timeStyle: 'short',
                                            },
                                        )}
                                    </div>
                                    {ev.payload?.contacto_cliente && (
                                        <div className="text-xs text-neutral-600 dark:text-neutral-400">
                                            Entregado por cliente:{' '}
                                            {ev.payload.contacto_cliente}
                                        </div>
                                    )}
                                    {ev.payload?.observaciones && (
                                        <div className="rounded-lg bg-neutral-50 p-1.5 text-xs text-neutral-600 dark:bg-neutral-900 dark:text-neutral-400">
                                            {ev.payload.observaciones}
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Recojo Form (§22.1, §85.6.2) */}
                {!yaRecogido ? (
                    <div className="border-border from-muted via-muted space-y-3 rounded-2xl border bg-gradient-to-br to-transparent p-4">
                        <h3 className="text-foreground flex items-center gap-1.5 text-xs font-bold tracking-wider uppercase">
                            <Truck className="h-4 w-4" />
                            <span>Registro de Recojo Físico en Sitio</span>
                        </h3>

                        <form
                            className="border-border space-y-2 rounded-xl border bg-white/70 p-3 dark:bg-neutral-900/60"
                            onSubmit={(event) => {
                                event.preventDefault();
                                equipmentForm.post(
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
                                        preserveScroll: true,
                                        onSuccess: () => equipmentForm.reset(),
                                    },
                                );
                            }}
                        >
                            <AlertError
                                errors={Object.values(equipmentForm.errors)}
                            />
                            <p className="text-xs font-bold text-neutral-800 dark:text-neutral-200">
                                Extintores recogidos ({order.equipments.length})
                            </p>
                            {order.equipments.length > 0 && (
                                <ul
                                    aria-label="Extintores agregados al recojo"
                                    className="space-y-2"
                                >
                                    {order.equipments.map((equipment) => (
                                        <li
                                            key={equipment.id}
                                            className="border-border bg-card rounded-xl border p-3 text-xs"
                                        >
                                            <p className="font-mono font-semibold">
                                                {equipment.numero_serie}
                                            </p>
                                            <p className="text-muted-foreground">
                                                {equipment.tipo_agente} ·{' '}
                                                {equipment.capacidad}
                                                {equipment.marca
                                                    ? ` · ${equipment.marca}`
                                                    : ''}
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            <p className="text-muted-foreground text-xs">
                                Si el equipo ya está registrado, escribe su
                                código BF-EQ. Para uno nuevo, elige agente y
                                capacidad; se generará su código interno.
                            </p>
                            <input
                                aria-label="Numero serie"
                                value={equipmentForm.data.numero_serie}
                                onChange={(event) =>
                                    equipmentForm.setData(
                                        'numero_serie',
                                        event.target.value,
                                    )
                                }
                                placeholder="Escanear BF-EQ existente"
                                className="w-full rounded-xl border border-neutral-300 bg-white p-2.5 text-xs dark:border-neutral-700 dark:bg-neutral-900"
                            />
                            <div className="grid grid-cols-2 gap-2">
                                <NativeSelect
                                    aria-label="Agente extintor"
                                    value={equipmentForm.data.tipo_agente}
                                    onChange={(event) =>
                                        equipmentForm.setData(
                                            'tipo_agente',
                                            event.target.value,
                                        )
                                    }
                                    className="rounded-xl border border-neutral-300 bg-white p-2.5 text-xs dark:border-neutral-700 dark:bg-neutral-900"
                                >
                                    <OpcionesAgente vacia="Agente (alta nueva)" />
                                </NativeSelect>
                                <input
                                    aria-label="Capacidad"
                                    value={equipmentForm.data.capacidad}
                                    onChange={(event) =>
                                        equipmentForm.setData(
                                            'capacidad',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Capacidad"
                                    className="rounded-xl border border-neutral-300 bg-white p-2.5 text-xs dark:border-neutral-700 dark:bg-neutral-900"
                                />
                            </div>
                            <button
                                type="submit"
                                disabled={equipmentForm.processing}
                                className="hover:bg-muted/60 rounded-xl bg-neutral-900 px-3 py-2 text-xs font-bold text-white transition-colors disabled:opacity-50 dark:bg-white dark:text-neutral-900"
                            >
                                Agregar extintor
                            </button>
                            {equipmentForm.errors.numero_serie && (
                                <p
                                    className="text-destructive-strong text-xs"
                                    role="alert"
                                >
                                    {equipmentForm.errors.numero_serie}
                                </p>
                            )}
                        </form>

                        <form
                            onSubmit={handleSubmit}
                            className="space-y-3 pt-1"
                        >
                            <AlertError errors={Object.values(form.errors)} />
                            <div className="grid grid-cols-2 gap-2">
                                <div>
                                    <label className="mb-1 block text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                                        Teléfono Contacto
                                    </label>
                                    <InputPeru
                                        tipo="telefono"
                                        aria-label="Teléfono Contacto"
                                        value={form.data.contacto_telefono}
                                        onChange={(e) =>
                                            form.setData(
                                                'contacto_telefono',
                                                e.target.value,
                                            )
                                        }
                                        className="bg-card w-full rounded-xl border border-neutral-300 p-2.5 text-xs dark:border-neutral-700 dark:bg-neutral-900"
                                    />
                                </div>
                            </div>

                            <a
                                href={recojos.constanciaRecepcion.url({
                                    current_team: teamSlug,
                                    service_order: order.id,
                                })}
                                target="_blank"
                                rel="noreferrer"
                                className="text-foreground inline-flex text-xs font-bold hover:underline"
                            >
                                Descargar constancia para firma
                            </a>

                            <div>
                                <label className="mb-1 block text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                                    Nombre del Responsable que Entrega (Cliente)
                                    *
                                </label>
                                <input
                                    aria-label="Contacto nombre"
                                    type="text"
                                    value={form.data.contacto_nombre}
                                    onChange={(e) =>
                                        form.setData(
                                            'contacto_nombre',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Nombre completo de quien entrega en el local"
                                    className="bg-card w-full rounded-xl border border-neutral-300 p-2.5 text-xs dark:border-neutral-700 dark:bg-neutral-900"
                                    required
                                />
                            </div>

                            <div>
                                <label className="mb-1 block text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                                    Observaciones de Estado en Recojo
                                </label>
                                <textarea
                                    aria-label="Observaciones de Estado en Recojo"
                                    value={form.data.observaciones}
                                    onChange={(e) =>
                                        form.setData(
                                            'observaciones',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Detalles sobre el estado físico de los extintores al retirarlos..."
                                    rows={2}
                                    className="bg-card w-full rounded-xl border border-neutral-300 p-2.5 text-xs dark:border-neutral-700 dark:bg-neutral-900"
                                />
                            </div>

                            {/* Conformidad / Firma por defecto (§85.6.2) */}
                            <div className="bg-card space-y-1 rounded-xl border border-neutral-200 p-3 dark:border-neutral-700 dark:bg-neutral-900">
                                <label className="flex cursor-pointer items-start gap-2.5 text-xs font-semibold text-neutral-800 dark:text-neutral-200">
                                    <input
                                        type="checkbox"
                                        checked={form.data.conformidad_cliente}
                                        onChange={(e) =>
                                            form.setData(
                                                'conformidad_cliente',
                                                e.target.checked,
                                            )
                                        }
                                        className="text-foreground focus:ring-ring mt-0.5 h-4 w-4 rounded border-neutral-300"
                                        required
                                    />
                                    <span>
                                        Conformidad del cliente: El responsable
                                        declara haber entregado los equipos
                                        detallados para su traslado a Planta
                                        Bruce Fire.
                                    </span>
                                </label>
                            </div>

                            <SubirEvidencia
                                ordenId={order.id}
                                etapa="recojo"
                                titulo="Fotos del recojo"
                                evidencias={evidencias}
                                equipos={conversacion.equipos}
                            />

                            <FirmaCanvas
                                etiqueta="Firma de quien entrega"
                                onChange={(f) => form.setData('firma', f)}
                            />

                            <button
                                type="submit"
                                disabled={form.processing || !form.data.firma}
                                className="bg-primary hover:bg-primary/90 active:bg-primary flex w-full items-center justify-center gap-2 rounded-xl py-3.5 text-xs font-bold text-white shadow-sm disabled:opacity-50"
                            >
                                <CheckCircle2 className="h-4 w-4" />
                                <span>
                                    Confirmar Recojo y Cadena de Custodia
                                </span>
                            </button>
                            {!form.data.conformidad_cliente ? (
                                <p
                                    className="text-center text-xs font-semibold text-red-500"
                                    role="alert"
                                >
                                    Marca la conformidad del cliente antes de
                                    registrar el recojo.
                                </p>
                            ) : null}
                        </form>
                    </div>
                ) : (
                    <div className="text-success-strong flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-xs dark:border-emerald-800/50 dark:bg-emerald-950/30 dark:text-emerald-300">
                        <CheckCircle2 className="text-success-strong h-5 w-5 flex-shrink-0" />
                        <span>
                            Recojo formalizado. Los equipos se encuentran en
                            custodia rumbo a Planta.
                        </span>
                    </div>
                )}

                <ConversacionOrden
                    ordenId={order.id}
                    conversacion={conversacion}
                />
            </div>
        </TecnicoCampoLayout>
    );
}
