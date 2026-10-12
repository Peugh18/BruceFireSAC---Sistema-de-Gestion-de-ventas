import { Pasos, pasosTaller } from '@/components/progreso';
import {
    Dialog,
    DialogContent,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import React, { useRef, useState } from 'react';
import { toast } from 'sonner';
import TomarOrden, { type AsignacionOrden } from '@/components/tomar-orden';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import ConversacionOrden, {
    type ConversacionProps,
} from '@/components/conversacion-orden';
import AlertError from '@/components/alert-error';
import DeficienciaSuelta from '@/components/deficiencia-suelta';
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
    MessageSquare,
} from 'lucide-react';
import TecnicoPlantaRoutes from '@/routes/tecnico-planta';
import EjecucionRoutes from '@/routes/tecnico-planta/ejecucion';
import { NativeSelect } from '@/components/ui/native-select';
import { Empty } from '@/components/ui/empty';

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
        payload: Record<string, string | number | boolean | null | undefined>;
        created_at: string;
    }>;
}

interface Props {
    conversacion: ConversacionProps;
    asignacion: AsignacionOrden;
    order: OrderDetail;
    repuestos: ProductItem[];
    certificates: CertificateItem[];
}

export default function EjecucionShow({
    conversacion,
    asignacion,
    order,
    repuestos,
    certificates,
}: Props) {
    const modalTrigger = useRef<HTMLElement | null>(null);
    const { currentTeam, errors } = usePage<{
        currentTeam?: Team | null;
        errors: Record<string, string>;
    }>().props;
    const teamSlug = currentTeam?.slug ?? '';

    const [selectedDeficiency, setSelectedDeficiency] =
        useState<DeficiencyItem | null>(null);
    // Extintores a los que se les hizo la Prueba Hidrostática (uno por uno).
    const [conPh, setConPh] = useState<number[]>([]);
    const [avanzando, setAvanzando] = useState(false);

    // Form for consuming spare parts
    const spareForm = useForm({
        product_id: repuestos[0]?.id || '',
        cantidad: 1,
        observacion: '',
    });

    const handleConsumeSpare = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedDeficiency) return;

        spareForm.post(
            EjecucionRoutes.consumeSpare.url({
                current_team: teamSlug,
                service_order: order.id,
                deficiency: selectedDeficiency.id,
            }),
            {
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ??
                            'No se pudo completar la acción.',
                    ),
                onSuccess: () => {
                    setSelectedDeficiency(null);
                    spareForm.reset();
                },
            },
        );
    };

    // Un solo envío a la vez: con mala señal el técnico no emite dos veces.
    const handleAdvance = (targetState: string) => {
        if (avanzando) return;
        router.post(
            EjecucionRoutes.advance.url({
                current_team: teamSlug,
                service_order: order.id,
            }),
            { target_state: targetState, ph_equipos: conPh },
            {
                preserveScroll: true,
                onStart: () => setAvanzando(true),
                onFinish: () => setAvanzando(false),
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ??
                            'No se pudo avanzar la orden.',
                    ),
            },
        );
    };

    return (
        <TecnicoPlantaLayout>
            <Head title={`Taller ${order.codigo} - Ejecución Planta`} />

            <div className="space-y-4 pb-16">
                {/* Back Link */}
                <Link
                    href={TecnicoPlantaRoutes.dashboard.url({
                        current_team: teamSlug,
                    })}
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
                                <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-extrabold text-amber-900 uppercase dark:bg-amber-950/60 dark:text-amber-300">
                                    {order.estado.replace('_', ' ')}
                                </span>
                            </div>
                            <h2 className="mt-1 text-base font-semibold text-neutral-900 dark:text-neutral-100">
                                {order.cliente}
                            </h2>
                            <p className="text-muted-foreground font-mono text-xs">
                                RUC/DNI: {order.cliente_doc}{' '}
                                {order.telefono ? `• ${order.telefono}` : ''}
                            </p>
                        </div>
                    </div>

                    <Pasos
                        pasos={pasosTaller(
                            order.estado,
                            certificates.length > 0,
                        )}
                        label="Avance del trabajo en taller"
                        variante="numerada"
                    />
                </div>
                <TomarOrden asignacion={asignacion} />

                <ConversacionOrden
                    ordenId={order.id}
                    conversacion={conversacion}
                />

                {order.equipments.length > 0 && (
                    <DeficienciaSuelta
                        ordenId={order.id}
                        equipos={conversacion.equipos}
                    />
                )}

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

                            {notasVendedor.length > 0 && (
                                <div className="mt-3 space-y-2">
                                    <span className="text-foreground text-xs font-bold uppercase">
                                        Notas dejadas por el Vendedor:
                                    </span>
                                    {notasVendedor.map((n) => (
                                        <div
                                            key={n.id}
                                            className="border-border rounded-xl border bg-white/80 p-2.5 text-xs text-neutral-800 shadow-2xs dark:bg-neutral-900/80 dark:text-neutral-200"
                                        >
                                            <p className="font-medium">
                                                {n.payload?.mensaje ||
                                                    n.payload?.descripcion ||
                                                    JSON.stringify(n.payload)}
                                            </p>
                                            <span className="mt-1 block text-xs text-neutral-400">
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

                    {errors.target_state ? (
                        <p
                            role="alert"
                            className="rounded-xl border border-red-200 bg-red-50 p-3 text-xs font-semibold text-red-700 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-300"
                        >
                            {errors.target_state}
                        </p>
                    ) : null}

                    {order.estado === 'recibido_planta' ||
                    order.estado === 'autorizado' ? (
                        <button
                            aria-label="Iniciar Trabajo en Taller (En Proceso)"
                            type="button"
                            onClick={() => handleAdvance('en_proceso')}
                            disabled={avanzando}
                            className="bg-primary text-primary-foreground hover:bg-primary/90 flex w-full items-center justify-center gap-2 rounded-xl py-3 text-xs font-bold shadow-sm"
                        >
                            <Wrench className="h-4 w-4" />
                            <span>Iniciar Trabajo en Taller (En Proceso)</span>
                        </button>
                    ) : order.estado === 'en_proceso' ||
                      order.estado === 'trabajo_terminado' ||
                      order.estado === 'pendiente_datos' ||
                      order.estado === 'datos_completos' ? (
                        <div className="space-y-3">
                            {order.equipments.length > 0 ? (
                                <div className="bg-card space-y-1.5 rounded-xl border border-neutral-200 p-3 text-xs dark:border-neutral-700 dark:bg-neutral-800">
                                    <p className="font-semibold text-neutral-800 dark:text-neutral-200">
                                        Prueba Hidrostática (P.H.): marca solo
                                        los cilindros a los que se les hizo
                                    </p>
                                    {order.equipments.map((eq) => (
                                        <label
                                            key={eq.id}
                                            className="flex cursor-pointer items-center gap-2"
                                        >
                                            <input
                                                aria-label={`Solicitar datos de placa del extintor ${eq.numero_serie}`}
                                                type="checkbox"
                                                checked={conPh.includes(eq.id)}
                                                onChange={(e) =>
                                                    setConPh((actual) =>
                                                        e.target.checked
                                                            ? [...actual, eq.id]
                                                            : actual.filter(
                                                                  (id) =>
                                                                      id !==
                                                                      eq.id,
                                                              ),
                                                    )
                                                }
                                                className="text-warning-strong focus:ring-ring h-4 w-4 rounded border-neutral-300"
                                            />
                                            <span className="font-mono font-bold">
                                                {eq.numero_serie}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {eq.tipo_agente} {eq.capacidad}
                                            </span>
                                        </label>
                                    ))}
                                </div>
                            ) : null}

                            <button
                                type="button"
                                onClick={() =>
                                    handleAdvance('listo_certificado')
                                }
                                disabled={avanzando}
                                className="flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 py-3.5 text-xs font-extrabold text-white shadow-md hover:bg-emerald-700 active:bg-emerald-800"
                            >
                                <Sparkles className="h-4 w-4" />
                                <span>
                                    {order.estado === 'en_proceso'
                                        ? 'Terminar trabajo y emitir certificados'
                                        : 'Cerrar Taller y Emitir Certificados Automáticos'}
                                </span>
                            </button>
                            {order.estado === 'en_proceso' ? (
                                <button
                                    type="button"
                                    onClick={() =>
                                        handleAdvance('trabajo_terminado')
                                    }
                                    disabled={avanzando}
                                    className="text-muted-foreground w-full text-center text-xs underline"
                                >
                                    Solo marcar el trabajo como terminado
                                </button>
                            ) : null}
                        </div>
                    ) : order.estado === 'listo_certificado' ? (
                        <div className="space-y-2">
                            <div className="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-800 dark:border-emerald-800/60 dark:bg-emerald-950/40 dark:text-emerald-300">
                                <ShieldCheck className="text-success-strong h-5 w-5 flex-shrink-0" />
                                <span>
                                    Certificado emitido. Al marcarla lista para
                                    entrega, el vendedor recibe el aviso en su
                                    panel.
                                </span>
                            </div>
                            <button
                                type="button"
                                onClick={() => handleAdvance('listo_entrega')}
                                disabled={avanzando}
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
                                        <div className="text-muted-foreground text-xs">
                                            Vigente hasta:{' '}
                                            {cert.fecha_vigencia_hasta}
                                        </div>
                                    </div>
                                    <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-800 uppercase dark:bg-emerald-900/60 dark:text-emerald-300">
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
                        <Empty
                            icon={<ShieldCheck />}
                            title="No hay deficiencias registradas en esta orden"
                        />
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
                                            className={`rounded-full px-2 py-0.5 text-xs font-bold uppercase ${
                                                d.estado === 'resuelta'
                                                    ? 'bg-muted text-foreground '
                                                    : d.estado === 'autorizada'
                                                      ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                                                      : 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300'
                                            }`}
                                        >
                                            {d.estado.replace('_', ' ')}
                                        </span>
                                    </div>

                                    {d.repuesto_sugerido && (
                                        <div className="text-muted-foreground text-xs">
                                            <b>Repuesto requerido: </b>{' '}
                                            {d.repuesto_sugerido}
                                        </div>
                                    )}

                                    {d.resolucion ? (
                                        <div className="bg-muted text-foreground rounded-lg p-2 text-xs">
                                            <b>Solución: </b> {d.resolucion}
                                        </div>
                                    ) : (
                                        <button
                                            aria-label="Consumir Repuesto de Almacén (Kardex)"
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
                    <Dialog
                        open
                        onOpenChange={(open) => {
                            if (!open && !spareForm.processing)
                                setSelectedDeficiency(null);
                        }}
                    >
                        <DialogContent
                            onOpenAutoFocus={() => {
                                modalTrigger.current =
                                    document.activeElement as HTMLElement;
                            }}
                            onCloseAutoFocus={(event) => {
                                event.preventDefault();
                                modalTrigger.current?.focus();
                            }}
                            className="border-border bg-card max-h-[90dvh] space-y-4 overflow-y-auto overscroll-contain sm:max-w-lg"
                        >
                            <div className="flex items-center justify-between border-b border-neutral-100 pb-3 dark:border-neutral-800">
                                <div>
                                    <DialogTitle className="text-base font-bold text-neutral-900 dark:text-neutral-100">
                                        Consumo de Repuesto
                                    </DialogTitle>
                                    <DialogDescription className="text-muted-foreground text-xs">
                                        Kardex Almacén para{' '}
                                        {selectedDeficiency.componente}
                                    </DialogDescription>
                                </div>
                            </div>

                            <form
                                onSubmit={handleConsumeSpare}
                                className="space-y-3"
                            >
                                <AlertError
                                    errors={Object.values(spareForm.errors)}
                                />
                                <div>
                                    <label className="mb-1 block text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                                        Producto / Repuesto de Almacén *
                                    </label>
                                    <NativeSelect
                                        aria-label="Producto / Repuesto de Almacén *"
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
                                    </NativeSelect>
                                </div>

                                <div>
                                    <label className="mb-1 block text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                                        Cantidad a Descontar del Kardex *
                                    </label>
                                    <input
                                        aria-label="Cantidad a Descontar del Kardex *"
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
                                        aria-label="Observación Técnica"
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
                                    className="bg-primary text-primary-foreground hover:bg-primary/90 flex w-full items-center justify-center gap-2 rounded-xl py-3 text-xs font-bold shadow-sm"
                                >
                                    <Package className="h-4 w-4" />
                                    <span>
                                        Registrar Salida en Kardex y Resolver
                                    </span>
                                </button>
                            </form>
                        </DialogContent>
                    </Dialog>
                )}
            </div>
        </TecnicoPlantaLayout>
    );
}
