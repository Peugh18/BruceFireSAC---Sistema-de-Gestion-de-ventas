import InputError from '@/components/input-error';
import { ProgresoConEtiqueta } from '@/components/progreso';
import React, { useEffect, useRef } from 'react';
import { toast } from 'sonner';
import TomarOrden, { type AsignacionOrden } from '@/components/tomar-orden';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import TecnicoPlantaLayout from '@/layouts/tecnico-planta-layout';
import type { Team } from '@/types';
import {
    ArrowLeft,
    CheckCircle2,
    Printer,
    Building2,
    Calendar,
    AlertTriangle,
    Wrench,
    MessageSquare,
} from 'lucide-react';
import EquiposDeLaOrden, {
    type Casilleros,
} from '@/components/equipos-de-la-orden';
import RecepcionesRoutes from '@/routes/tecnico-planta/recepciones';
import Equipos2Routes from '@/routes/tecnico-planta/recepciones/equipos';
import ChecklistRoutes from '@/routes/tecnico-planta/checklist';

interface EquipmentItem {
    id: number;
    numero_serie: string;
    tipo_agente: string | null;
    capacidad: string | null;
    marca: string | null;
    serie_fabricante: string | null;
    anio_fabricacion: string | null;
    notas: string | null;
    recibido: boolean;
}

interface OrderDetail {
    id: number;
    codigo: string;
    cliente: {
        id: number;
        nombre: string;
        documento: string;
        telefono: string | null;
        direccion: string | null;
    };
    sede: string | null;
    tipo_servicio: string;
    fecha: string;
    prioridad: string;
    estado: string;
    observaciones: string | null;
    equipments: EquipmentItem[];
    eventos: Array<{
        id: number;
        tipo: string;
        payload: Record<string, string | number | boolean | null | undefined>;
        created_at: string;
    }>;
}

interface Props {
    asignacion: AsignacionOrden;
    order: OrderDetail;
    casilleros: Casilleros;
}

export default function RecepcionShow({
    asignacion,
    order,
    casilleros,
}: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug = currentTeam?.slug ?? '';
    const ruta = { current_team: teamSlug, service_order: order.id };
    const equiposAnteriores = useRef(
        order.equipments.map((equipo) => equipo.id),
    );

    // Form for Confirming Reception
    const confirmForm = useForm({
        observaciones: '',
        equipos_recibidos_count: order.equipments.length,
        // Solo lo que llegó físicamente se certifica y renueva.
        equipos_recibidos: order.equipments.map((eq) => eq.id),
        diferencias: '',
    });

    const alternarRecibido = (id: number) => {
        const actuales = confirmForm.data.equipos_recibidos;
        const siguientes = actuales.includes(id)
            ? actuales.filter((x) => x !== id)
            : [...actuales, id];
        confirmForm.setData((data) => ({
            ...data,
            equipos_recibidos: siguientes,
            equipos_recibidos_count: siguientes.length,
        }));
    };

    const isPendiente = order.estado === 'pendiente_recepcion';

    const handleConfirmReception = (e: React.FormEvent) => {
        e.preventDefault();
        confirmForm.post(
            RecepcionesRoutes.confirm.url({
                current_team: teamSlug,
                service_order: order.id,
            }),
            {
                preserveScroll: true,
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ??
                            'No se pudo confirmar la recepción.',
                    ),
            },
        );
    };

    useEffect(() => {
        const anteriores = equiposAnteriores.current;
        const ids = order.equipments.map((equipo) => equipo.id);
        const nuevos = order.equipments
            .filter(
                (equipo) => equipo.recibido && !anteriores.includes(equipo.id),
            )
            .map((equipo) => equipo.id);
        equiposAnteriores.current = ids;
        confirmForm.setData((data) => {
            const recibidos = [
                ...new Set([
                    ...data.equipos_recibidos.filter((id) => ids.includes(id)),
                    ...nuevos,
                ]),
            ];
            return {
                ...data,
                equipos_recibidos: recibidos,
                equipos_recibidos_count: recibidos.length,
            };
        });
    }, [order.equipments]);

    return (
        <TecnicoPlantaLayout>
            <Head title={`Recepción ${order.codigo} - Planta`} />

            <div className="space-y-4 pb-12">
                {/* Back Button */}
                <Link
                    href={RecepcionesRoutes.index.url({
                        current_team: teamSlug,
                    })}
                    className="inline-flex items-center gap-1.5 text-xs font-semibold text-neutral-600 hover:text-neutral-900 dark:text-neutral-400"
                >
                    <ArrowLeft className="h-4 w-4" />
                    <span>Volver a Recepciones</span>
                </Link>

                {/* Header Card */}
                <div className="bg-card space-y-3 rounded-2xl border border-neutral-200 p-4 shadow-sm dark:border-neutral-700/80 dark:bg-neutral-800">
                    <div className="flex items-start justify-between gap-2">
                        <div>
                            <div className="flex items-center gap-2">
                                <span className="font-mono text-base font-extrabold text-neutral-900 dark:text-neutral-100">
                                    {order.codigo}
                                </span>
                                {isPendiente ? (
                                    <span className="text-warning-strong rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold dark:bg-amber-950/60 dark:text-amber-300">
                                        Pendiente de Recepción
                                    </span>
                                ) : (
                                    <span className="text-success-strong rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold dark:bg-emerald-950/60 dark:text-emerald-300">
                                        Recibido en Planta
                                    </span>
                                )}
                            </div>
                            <h2 className="mt-1 text-base font-semibold text-neutral-900 dark:text-neutral-100">
                                {order.cliente.nombre}
                            </h2>
                            <p className="text-muted-foreground font-mono text-xs">
                                RUC/DNI: {order.cliente.documento}{' '}
                                {order.cliente.telefono
                                    ? `• Tel: ${order.cliente.telefono}`
                                    : ''}
                            </p>
                        </div>

                        {order.equipments.length > 0 && (
                            <a
                                href={RecepcionesRoutes.stickers.url({
                                    current_team: teamSlug,
                                    service_order: order.id,
                                })}
                                target="_blank"
                                rel="noreferrer"
                                className="flex flex-shrink-0 items-center justify-center rounded-xl bg-neutral-100 p-2.5 text-neutral-800 hover:bg-neutral-200 dark:bg-neutral-700 dark:text-neutral-200"
                                title="Imprimir stickers 2x2"
                                aria-label="Imprimir stickers 2x2"
                            >
                                <Printer className="h-4 w-4" />
                            </a>
                        )}
                    </div>

                    <div className="grid grid-cols-2 gap-2 border-t border-neutral-100 pt-2 text-xs dark:border-neutral-700/60">
                        <div className="flex items-center gap-1.5 text-neutral-600 dark:text-neutral-400">
                            <Building2 className="h-3.5 w-3.5 text-neutral-400" />
                            <span>{order.sede || 'Sede Principal'}</span>
                        </div>
                        <div className="flex items-center gap-1.5 text-neutral-600 dark:text-neutral-400">
                            <Calendar className="h-3.5 w-3.5 text-neutral-400" />
                            <span>{order.fecha}</span>
                        </div>
                    </div>

                    {order.observaciones && (
                        <div className="rounded-xl border border-neutral-100 bg-neutral-50 p-2.5 text-xs text-neutral-600 dark:border-neutral-800 dark:bg-neutral-900/50 dark:text-neutral-400">
                            <span className="font-semibold">
                                Observaciones:{' '}
                            </span>
                            {order.observaciones}
                        </div>
                    )}
                </div>

                {/* Status & Confirmation Section */}
                {isPendiente ? (
                    <div className="space-y-3 rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-500/10 via-amber-500/5 to-transparent p-4 dark:border-amber-900/40">
                        <div className="text-warning-strong flex items-center gap-2 dark:text-amber-300">
                            <AlertTriangle className="h-4 w-4" />
                            <h3 className="text-xs font-bold tracking-wider uppercase">
                                Confirmación de Ingreso a Taller
                            </h3>
                        </div>
                        <p className="text-warning-strong text-xs leading-relaxed dark:text-amber-300/90">
                            Verifique que los extintores físicos coincidan con
                            la orden. Puede agregar observaciones o registrar
                            faltantes/sobrantes.
                        </p>

                        <form
                            onSubmit={handleConfirmReception}
                            className="space-y-3 pt-1"
                        >
                            {order.equipments.length > 0 ? (
                                <div className="space-y-1">
                                    <p className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                                        Extintores que llegaron (desmarca los
                                        que faltan)
                                    </p>
                                    {order.equipments.map((eq) => (
                                        <label
                                            key={eq.id}
                                            className="bg-card flex items-center gap-2 rounded-lg border border-neutral-200 px-2.5 py-1.5 text-xs dark:border-neutral-700"
                                        >
                                            <input
                                                aria-label={`Confirmar recepción del extintor ${eq.numero_serie}`}
                                                type="checkbox"
                                                checked={confirmForm.data.equipos_recibidos.includes(
                                                    eq.id,
                                                )}
                                                onChange={() =>
                                                    alternarRecibido(eq.id)
                                                }
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

                            <div>
                                <label className="mb-1 block text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                                    Diferencias o novedades en la recepción
                                    física
                                </label>
                                <input
                                    aria-label="Diferencias"
                                    type="text"
                                    value={confirmForm.data.diferencias}
                                    onChange={(e) =>
                                        confirmForm.setData(
                                            'diferencias',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Ej: Se reciben 2 cilindros en vez de 3, o con manguera rota..."
                                    className="bg-card focus:ring-ring w-full rounded-xl border border-neutral-200 px-3 py-2 text-xs focus:ring-2 dark:border-neutral-700 dark:bg-neutral-900"
                                />
                                <InputError
                                    message={confirmForm.errors.diferencias}
                                />
                            </div>

                            <button
                                aria-label="Confirmar Recepción Física en Planta"
                                type="submit"
                                disabled={confirmForm.processing}
                                className="bg-primary text-primary-foreground hover:bg-primary/90 active:bg-primary/80 flex w-full items-center justify-center gap-2 rounded-xl py-3 text-xs font-bold shadow-sm transition-colors"
                            >
                                <CheckCircle2 className="h-4 w-4" />
                                <span>
                                    Confirmar Recepción Física en Planta
                                </span>
                            </button>
                        </form>
                    </div>
                ) : (
                    <div className="text-success-strong flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs dark:border-emerald-800/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                        <CheckCircle2 className="text-success-strong h-4 w-4 flex-shrink-0" />
                        <span>
                            Esta orden ya ingresó formalmente al taller de
                            Planta.
                        </span>
                    </div>
                )}

                <TomarOrden asignacion={asignacion} />

                {/* Indicaciones de Ventas / Notas de Coordinación */}
                {(() => {
                    const notasVendedor = (order.eventos || []).filter(
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

                <ProgresoConEtiqueta
                    label="Equipos recibidos registrados"
                    value={
                        order.equipments.filter((equipo) => equipo.recibido)
                            .length
                    }
                    max={order.equipments.length}
                    detalle="De los equipos vinculados a esta orden. La selección del formulario se registra al confirmar."
                />
                <section className="border-border bg-card space-y-4 rounded-2xl border p-4">
                    <h3 className="text-foreground text-sm font-bold">
                        Extintores de la orden
                    </h3>
                    <EquiposDeLaOrden
                        casilleros={casilleros}
                        rutas={{
                            agregar: Equipos2Routes.store.url(ruta),
                            reordenar: Equipos2Routes.reordenar.url(ruta),
                            quitar: (equipment) =>
                                Equipos2Routes.destroy.url({
                                    ...ruta,
                                    equipment,
                                }),
                        }}
                        accionesEquipo={(equipo) => (
                            <Link
                                href={ChecklistRoutes.create.url({
                                    ...ruta,
                                    equipment: equipo.id,
                                })}
                                className="bg-primary text-primary-foreground inline-flex min-h-11 items-center gap-1 rounded-xl px-3 text-xs font-semibold"
                            >
                                <Wrench className="size-4" />
                                Checklist
                            </Link>
                        )}
                    />
                </section>
            </div>
        </TecnicoPlantaLayout>
    );
}
