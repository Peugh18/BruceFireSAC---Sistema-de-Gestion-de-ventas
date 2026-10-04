import React, { useState } from 'react';
import TomarOrden, { type AsignacionOrden } from '@/components/tomar-orden';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import TecnicoPlantaLayout from '@/layouts/tecnico-planta-layout';
import type { Team } from '@/types';
import {
    ArrowLeft,
    CheckCircle2,
    Wrench,
    Package,
    Sparkles,
    ShieldCheck,
    FileCheck2,
    ChevronRight,
    MessageSquare,
} from 'lucide-react';

interface EquipmentItem {
    id: number;
    numero_serie: string;
    tipo_agente: string | null;
    capacidad: string | null;
    marca: string | null;
}

interface DeficiencyItem {
    id: number;
    componente: string;
    condicion: string;
    estado: string;
    requiere_autorizacion: boolean;
    repuesto_sugerido: string | null;
    resolucion: string | null;
    authorization: {
        autorizado_por: string;
        canal: string;
        fecha: string;
    } | null;
}

interface CertificateItem {
    id: number;
    numero: string;
    tipo: string;
    fecha_emision: string;
    fecha_vigencia_hasta: string;
    estado: string;
}

interface ProductItem {
    id: number;
    codigo: string;
    nombre: string;
    precio: string | number;
}

interface OrderDetail {
    id: number;
    codigo: string;
    cliente: string;
    cliente_doc: string;
    telefono: string | null;
    sede: string | null;
    tipo_servicio: string;
    fecha: string;
    prioridad: string;
    estado: string;
    estado_coarse: string;
    observaciones: string | null;
    equipments: EquipmentItem[];
    deficiencies: DeficiencyItem[];
    events: Array<{
        id: number;
        tipo: string;
        payload: any;
        created_at: string;
    }>;
}

interface Props {
    asignacion: AsignacionOrden;
    order: OrderDetail;
    repuestos: ProductItem[];
    certificates: CertificateItem[];
}

export default function EjecucionShow({
    asignacion,
    order,
    repuestos,
    certificates,
}: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug = currentTeam?.slug ?? '';
    const teamPrefix = `/${teamSlug}/tecnico-planta`;

    const [selectedDeficiency, setSelectedDeficiency] =
        useState<DeficiencyItem | null>(null);
    const [phRealizada, setPhRealizada] = useState(false);

    // Form for consuming spare parts
    const spareForm = useForm({
        product_id: repuestos[0]?.id || '',
        cantidad: 1,
        observacion: '',
    });

    // Form for advancing state
    const advanceForm = useForm({
        target_state: '',
        ph_realizada: false,
    });

    const handleConsumeSpare = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedDeficiency) return;

        spareForm.post(
            `${teamPrefix}/ordenes/${order.id}/deficiencias/${selectedDeficiency.id}/consumir-repuesto`,
            {
                onSuccess: () => {
                    setSelectedDeficiency(null);
                    spareForm.reset();
                },
            },
        );
    };

    const handleAdvance = (targetState: string) => {
        advanceForm.setData({
            target_state: targetState,
            ph_realizada: phRealizada,
        });
        advanceForm.post(`${teamPrefix}/ordenes/${order.id}/avanzar-estado`);
    };

    return (
        <TecnicoPlantaLayout>
            <Head title={`Taller ${order.codigo} - Ejecución Planta`} />

            <div className="space-y-4 pb-16">
                {/* Back Link */}
                <Link
                    href={`${teamPrefix}/dashboard`}
                    className="inline-flex items-center gap-1.5 text-xs font-semibold text-neutral-600 hover:text-neutral-900 dark:text-neutral-400"
                >
                    <ArrowLeft className="h-4 w-4" />
                    <span>Volver a Órdenes de Planta</span>
                </Link>

                {/* Header Card */}
                <div className="bg-card space-y-3 rounded-2xl border border-neutral-200 p-4 shadow-sm dark:border-neutral-700/80 dark:bg-neutral-800">
                    <div className="flex items-start justify-between gap-2">
                        <div>
                            <div className="flex items-center gap-2">
                                <span className="font-mono text-base font-extrabold text-neutral-900 dark:text-neutral-100">
                                    {order.codigo}
                                </span>
                                <span className="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-extrabold text-amber-900 uppercase dark:bg-amber-950/60 dark:text-amber-300">
                                    {order.estado.replace('_', ' ')}
                                </span>
                            </div>
                            <h2 className="mt-1 text-base font-semibold text-neutral-900 dark:text-neutral-100">
                                {order.cliente}
                            </h2>
                            <p className="font-mono text-xs text-neutral-500">
                                RUC/DNI: {order.cliente_doc}{' '}
                                {order.telefono ? `• ${order.telefono}` : ''}
                            </p>
                        </div>
                    </div>

                    {/* Progress steps (§16.2) */}
                    <div className="border-t border-neutral-100 pt-2 dark:border-neutral-700/60">
                        <div className="flex items-center justify-between text-[11px] font-bold text-neutral-500">
                            <span
                                className={
                                    order.estado === 'recibido_planta' ||
                                    order.estado === 'en_revision'
                                        ? 'text-warning-strong font-extrabold'
                                        : ''
                                }
                            >
                                1. Taller
                            </span>
                            <ChevronRight className="h-3 w-3 text-neutral-300" />
                            <span
                                className={
                                    order.estado === 'en_proceso'
                                        ? 'text-warning-strong font-extrabold'
                                        : ''
                                }
                            >
                                2. En Proceso
                            </span>
                            <ChevronRight className="h-3 w-3 text-neutral-300" />
                            <span
                                className={
                                    order.estado === 'trabajo_terminado'
                                        ? 'text-warning-strong font-extrabold'
                                        : ''
                                }
                            >
                                3. Terminado
                            </span>
                            <ChevronRight className="h-3 w-3 text-neutral-300" />
                            <span
                                className={
                                    order.estado === 'listo_certificado'
                                        ? 'text-success-strong font-extrabold'
                                        : ''
                                }
                            >
                                4. Certificado
                            </span>
                        </div>
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
                        <div className="rounded-2xl border border-blue-200 bg-blue-50/70 p-4 dark:border-blue-900/50 dark:bg-blue-950/30">
                            <div className="flex items-center gap-2 text-xs font-bold text-blue-900 dark:text-blue-300">
                                <MessageSquare className="size-4 text-blue-600 dark:text-blue-400" />
                                <span>
                                    Indicaciones de Ventas y Coordinación
                                </span>
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
                                        Notas dejadas por el Vendedor:
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

                {/* Primary Action Buttons Bar */}
                <div className="space-y-3 rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-500/10 via-amber-500/5 to-transparent p-4 dark:border-amber-900/40">
                    <h3 className="flex items-center gap-1.5 text-xs font-bold tracking-wider text-amber-900 uppercase dark:text-amber-300">
                        <Wrench className="h-4 w-4" />
                        <span>Control de Ejecución en Planta</span>
                    </h3>

                    {order.estado === 'recibido_planta' ||
                    order.estado === 'autorizado' ? (
                        <button
                            type="button"
                            onClick={() => handleAdvance('en_proceso')}
                            className="flex w-full items-center justify-center gap-2 rounded-xl bg-amber-600 py-3 text-xs font-bold text-white shadow-sm hover:bg-amber-700"
                        >
                            <Wrench className="h-4 w-4" />
                            <span>Iniciar Trabajo en Taller (En Proceso)</span>
                        </button>
                    ) : order.estado === 'en_proceso' ? (
                        <button
                            type="button"
                            onClick={() => handleAdvance('trabajo_terminado')}
                            className="flex w-full items-center justify-center gap-2 rounded-xl bg-neutral-900 py-3 text-xs font-bold text-white shadow-sm hover:bg-neutral-800 dark:bg-neutral-100 dark:text-neutral-900"
                        >
                            <CheckCircle2 className="h-4 w-4" />
                            <span>Marcar Trabajo como Terminado en Taller</span>
                        </button>
                    ) : order.estado === 'trabajo_terminado' ||
                      order.estado === 'pendiente_datos' ||
                      order.estado === 'datos_completos' ? (
                        <div className="space-y-3">
                            <label className="bg-card flex cursor-pointer items-center gap-2 rounded-xl border border-neutral-200 p-3 text-xs font-semibold text-neutral-800 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200">
                                <input
                                    type="checkbox"
                                    checked={phRealizada}
                                    onChange={(e) =>
                                        setPhRealizada(e.target.checked)
                                    }
                                    className="text-warning-strong h-4 w-4 rounded border-neutral-300 focus:ring-amber-500"
                                />
                                <span>
                                    Se realizó Prueba Hidrostática (P.H.) en el
                                    cilindro
                                </span>
                            </label>

                            <button
                                type="button"
                                onClick={() =>
                                    handleAdvance('listo_certificado')
                                }
                                className="flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 py-3.5 text-xs font-extrabold text-white shadow-md hover:bg-emerald-700 active:bg-emerald-800"
                            >
                                <Sparkles className="h-4 w-4" />
                                <span>
                                    Cerrar Taller y Emitir Certificados
                                    Automáticos
                                </span>
                            </button>
                        </div>
                    ) : order.estado === 'listo_certificado' ? (
                        <div className="space-y-2">
                            <div className="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-800 dark:border-emerald-800/60 dark:bg-emerald-950/40 dark:text-emerald-300">
                                <ShieldCheck className="text-success-strong h-5 w-5 flex-shrink-0" />
                                <span>
                                    Certificado emitido. Avisa al vendedor que
                                    los equipos están listos.
                                </span>
                            </div>
                            <button
                                type="button"
                                onClick={() => handleAdvance('listo_entrega')}
                                className="flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 py-3 text-xs font-bold text-white shadow-sm hover:bg-emerald-700"
                            >
                                <CheckCircle2 className="h-4 w-4" />
                                <span>Marcar lista para entrega</span>
                            </button>
                        </div>
                    ) : order.estado === 'esperando_autorizacion' ? (
                        <div className="flex items-center gap-2 rounded-xl border border-amber-300 bg-amber-50 p-3 text-xs text-amber-900 dark:border-amber-800/60 dark:bg-amber-950/40 dark:text-amber-300">
                            <ShieldCheck className="h-5 w-5 flex-shrink-0" />
                            <span>
                                Esperando que el cliente autorice las
                                reparaciones (lo registra el vendedor).
                            </span>
                        </div>
                    ) : ['listo_entrega', 'entregado', 'cerrado'].includes(
                          order.estado,
                      ) ? (
                        <div className="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-800 dark:border-emerald-800/60 dark:bg-emerald-950/40 dark:text-emerald-300">
                            <ShieldCheck className="text-success-strong h-5 w-5 flex-shrink-0" />
                            <span>
                                Trabajo terminado y certificado emitido: la
                                orden ya está para entregar.
                            </span>
                        </div>
                    ) : (
                        <div className="text-muted-foreground rounded-xl border p-3 text-xs">
                            Primero recibe la orden en planta para empezar el
                            trabajo.
                        </div>
                    )}
                </div>

                {/* Certificates Section */}
                {certificates.length > 0 && (
                    <div className="bg-card space-y-3 rounded-2xl border border-neutral-200 p-4 shadow-sm dark:border-neutral-700/80 dark:bg-neutral-800">
                        <div className="flex items-center gap-2 text-sm font-bold text-neutral-900 dark:text-neutral-100">
                            <FileCheck2 className="text-success-strong h-4 w-4" />
                            <span>
                                Certificados Emitidos ({certificates.length})
                            </span>
                        </div>

                        <div className="space-y-2">
                            {certificates.map((cert) => (
                                <div
                                    key={cert.id}
                                    className="flex items-center justify-between gap-2 rounded-xl border border-emerald-200 bg-emerald-50/60 p-3 dark:border-emerald-800/50 dark:bg-emerald-950/20"
                                >
                                    <div>
                                        <div className="font-mono text-xs font-bold text-emerald-900 dark:text-emerald-300">
                                            {cert.numero}
                                        </div>
                                        <div className="text-xs font-medium text-neutral-800 dark:text-neutral-200">
                                            {cert.tipo}
                                        </div>
                                        <div className="text-[11px] text-neutral-500">
                                            Vigente hasta:{' '}
                                            {cert.fecha_vigencia_hasta}
                                        </div>
                                    </div>
                                    <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800 uppercase dark:bg-emerald-900/60 dark:text-emerald-300">
                                        {cert.estado}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Deficiencies & Spare Parts Consumption Section (§85, Fase 5) */}
                <div className="space-y-3">
                    <div className="flex items-center justify-between">
                        <h3 className="flex items-center gap-1.5 text-sm font-bold text-neutral-900 dark:text-neutral-100">
                            <Wrench className="text-warning-strong h-4 w-4" />
                            <span>
                                Repuestos y Deficiencias (
                                {order.deficiencies.length})
                            </span>
                        </h3>
                    </div>

                    {order.deficiencies.length === 0 ? (
                        <div className="bg-card rounded-2xl border border-dashed border-neutral-200 p-6 text-center dark:border-neutral-700 dark:bg-neutral-800/40">
                            <ShieldCheck className="mx-auto mb-1.5 h-8 w-8 text-emerald-500" />
                            <p className="text-xs font-medium text-neutral-700 dark:text-neutral-300">
                                No hay deficiencias registradas en esta orden
                            </p>
                        </div>
                    ) : (
                        <div className="space-y-2.5">
                            {order.deficiencies.map((d) => (
                                <div
                                    key={d.id}
                                    className="bg-card space-y-2 rounded-2xl border border-neutral-200 p-3.5 shadow-sm dark:border-neutral-700/80 dark:bg-neutral-800"
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <div className="text-xs font-bold text-neutral-900 dark:text-neutral-100">
                                                {d.componente}
                                            </div>
                                            <p className="text-xs text-neutral-600 dark:text-neutral-400">
                                                {d.condicion}
                                            </p>
                                        </div>
                                        <span
                                            className={`rounded-full px-2 py-0.5 text-[10px] font-bold uppercase ${
                                                d.estado === 'resuelta'
                                                    ? 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300'
                                                    : d.estado === 'autorizada'
                                                      ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                                                      : 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300'
                                            }`}
                                        >
                                            {d.estado.replace('_', ' ')}
                                        </span>
                                    </div>

                                    {d.repuesto_sugerido && (
                                        <div className="text-[11px] text-neutral-500">
                                            <b>Repuesto requerido: </b>{' '}
                                            {d.repuesto_sugerido}
                                        </div>
                                    )}

                                    {d.resolucion ? (
                                        <div className="rounded-lg bg-blue-50 p-2 text-xs text-blue-900 dark:bg-blue-950/30 dark:text-blue-300">
                                            <b>Solución: </b> {d.resolucion}
                                        </div>
                                    ) : (
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setSelectedDeficiency(d)
                                            }
                                            className="flex w-full items-center justify-center gap-1.5 rounded-xl bg-neutral-900 py-2 text-xs font-bold text-white shadow-xs hover:bg-neutral-800 dark:bg-neutral-100 dark:text-neutral-900"
                                        >
                                            <Package className="h-3.5 w-3.5" />
                                            <span>
                                                Consumir Repuesto de Almacén
                                                (Kardex)
                                            </span>
                                        </button>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* MODAL: Consumir Repuesto de Almacén (§85) */}
                {selectedDeficiency && (
                    <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/60 p-0 backdrop-blur-xs sm:items-center sm:p-4">
                        <div className="animate-in slide-in-from-bottom bg-card w-full space-y-4 rounded-t-3xl border border-neutral-200 p-5 shadow-xl duration-200 sm:max-w-md sm:rounded-2xl dark:border-neutral-800 dark:bg-neutral-900">
                            <div className="flex items-center justify-between border-b border-neutral-100 pb-3 dark:border-neutral-800">
                                <div>
                                    <h3 className="text-base font-bold text-neutral-900 dark:text-neutral-100">
                                        Consumo de Repuesto
                                    </h3>
                                    <p className="text-xs text-neutral-500">
                                        Kardex Almacén para{' '}
                                        {selectedDeficiency.componente}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setSelectedDeficiency(null)}
                                    className="p-1 text-neutral-400"
                                >
                                    ✕
                                </button>
                            </div>

                            <form
                                onSubmit={handleConsumeSpare}
                                className="space-y-3"
                            >
                                <div>
                                    <label className="mb-1 block text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                                        Producto / Repuesto de Almacén *
                                    </label>
                                    <select
                                        value={spareForm.data.product_id}
                                        onChange={(e) =>
                                            spareForm.setData(
                                                'product_id',
                                                Number(e.target.value),
                                            )
                                        }
                                        className="w-full rounded-xl border border-neutral-300 bg-neutral-50 p-2.5 text-xs dark:border-neutral-700 dark:bg-neutral-800"
                                        required
                                    >
                                        {repuestos.map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.codigo
                                                    ? `[${p.codigo}] `
                                                    : ''}
                                                {p.nombre}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="mb-1 block text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                                        Cantidad a Descontar del Kardex *
                                    </label>
                                    <input
                                        type="number"
                                        min={1}
                                        value={spareForm.data.cantidad}
                                        onChange={(e) =>
                                            spareForm.setData(
                                                'cantidad',
                                                Number(e.target.value),
                                            )
                                        }
                                        className="w-full rounded-xl border border-neutral-300 bg-neutral-50 p-2 text-xs dark:border-neutral-700 dark:bg-neutral-800"
                                        required
                                    />
                                </div>

                                <div>
                                    <label className="mb-1 block text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                                        Observación Técnica
                                    </label>
                                    <input
                                        type="text"
                                        value={spareForm.data.observacion}
                                        onChange={(e) =>
                                            spareForm.setData(
                                                'observacion',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Ej: Reemplazo por fisura de manómetro..."
                                        className="w-full rounded-xl border border-neutral-300 bg-neutral-50 p-2 text-xs dark:border-neutral-700 dark:bg-neutral-800"
                                    />
                                </div>

                                <button
                                    type="submit"
                                    disabled={spareForm.processing}
                                    className="flex w-full items-center justify-center gap-2 rounded-xl bg-amber-600 py-3 text-xs font-bold text-white shadow-sm hover:bg-amber-700"
                                >
                                    <Package className="h-4 w-4" />
                                    <span>
                                        Registrar Salida en Kardex y Resolver
                                    </span>
                                </button>
                            </form>
                        </div>
                    </div>
                )}
            </div>
        </TecnicoPlantaLayout>
    );
}
