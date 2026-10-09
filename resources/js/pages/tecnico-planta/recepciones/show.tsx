import React, { useState } from 'react';
import { toast } from 'sonner';
import TomarOrden, { type AsignacionOrden } from '@/components/tomar-orden';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import TecnicoPlantaLayout from '@/layouts/tecnico-planta-layout';
import type { Team } from '@/types';
import {
    ArrowLeft,
    CheckCircle2,
    QrCode,
    Plus,
    Printer,
    Building2,
    Calendar,
    AlertTriangle,
    Search,
    Sparkles,
    Wrench,
    MessageSquare,
} from 'lucide-react';
import OpcionesAgente from '@/components/opciones-agente';
import RecepcionesRoutes from '@/routes/tecnico-planta/recepciones';
import EquiposRoutes from '@/routes/tecnico-planta/equipos';
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
}

type EquipoEscaneado = {
    numero_serie: string;
    cliente?: string | null;
    tipo_agente?: string | null;
    capacidad?: string | null;
    marca?: string | null;
};

export default function RecepcionShow({ asignacion, order }: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug = currentTeam?.slug ?? '';

    const [showRegisterModal, setShowRegisterModal] = useState(false);
    const [registerTab, setRegisterTab] = useState<'scan' | 'new'>('new');
    const [barcodeScanInput, setBarcodeScanInput] = useState('');
    const [scanResult, setScanResult] = useState<EquipoEscaneado | null>(null);
    const [scanLoading, setScanLoading] = useState(false);
    const [scanError, setScanError] = useState<string | null>(null);

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

    // Form for Alta Técnica Rápida (Caso A and B)
    const equipmentForm = useForm({
        numero_serie: '',
        tipo_agente: '',
        capacidad: '6 kg',
        marca: '',
        serie_fabricante: '',
        anio_fabricacion: '',
        ubicacion_actual: 'Planta - Taller',
        notas: '',
        observaciones_recepcion: '',
    });

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

    const handleSearchExisting = async () => {
        if (!barcodeScanInput.trim()) return;
        setScanLoading(true);
        setScanError(null);
        try {
            const res = await fetch(
                EquiposRoutes.search.url(
                    { current_team: teamSlug },
                    {
                        query: {
                            code: encodeURIComponent(barcodeScanInput.trim()),
                        },
                    },
                ),
            );
            const data = await res.json();
            if (data.found) {
                setScanResult(data.equipment);
            } else {
                setScanResult(null);
                setScanError(
                    'No se encontró ningún equipo con este código Bruce Fire.',
                );
            }
        } catch {
            setScanError('Error de red al consultar el código.');
        } finally {
            setScanLoading(false);
        }
    };

    const handleLinkExisting = () => {
        if (!scanResult) return;
        equipmentForm.setData('numero_serie', scanResult.numero_serie);
        equipmentForm.post(
            Equipos2Routes.store.url({
                current_team: teamSlug,
                service_order: order.id,
            }),
            {
                onSuccess: () => {
                    setShowRegisterModal(false);
                    setScanResult(null);
                    setBarcodeScanInput('');
                },
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ??
                            'No se pudo registrar el equipo.',
                    ),
            },
        );
    };

    const handleCreateNew = (e: React.FormEvent) => {
        e.preventDefault();
        equipmentForm.post(
            Equipos2Routes.store.url({
                current_team: teamSlug,
                service_order: order.id,
            }),
            {
                onSuccess: () => {
                    setShowRegisterModal(false);
                    equipmentForm.reset();
                },
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ??
                            'No se pudo registrar el equipo.',
                    ),
            },
        );
    };

    const setUnreadable = (
        field: 'marca' | 'serie_fabricante' | 'anio_fabricacion',
    ) => {
        equipmentForm.setData(field, 'No legible / Pendiente de verificar');
    };

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
                                    <span className="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-900 dark:bg-amber-950/60 dark:text-amber-300">
                                        Pendiente de Recepción
                                    </span>
                                ) : (
                                    <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-900 dark:bg-emerald-950/60 dark:text-emerald-300">
                                        Recibido en Planta
                                    </span>
                                )}
                            </div>
                            <h2 className="mt-1 text-base font-semibold text-neutral-900 dark:text-neutral-100">
                                {order.cliente.nombre}
                            </h2>
                            <p className="font-mono text-xs text-neutral-500">
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
                        <div className="flex items-center gap-2 text-amber-900 dark:text-amber-300">
                            <AlertTriangle className="h-4 w-4" />
                            <h3 className="text-xs font-bold tracking-wider uppercase">
                                Confirmación de Ingreso a Taller
                            </h3>
                        </div>
                        <p className="text-xs leading-relaxed text-amber-800 dark:text-amber-300/90">
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
                                    <p className="text-[11px] font-semibold text-neutral-700 dark:text-neutral-300">
                                        Extintores que llegaron (desmarca los
                                        que faltan)
                                    </p>
                                    {order.equipments.map((eq) => (
                                        <label
                                            key={eq.id}
                                            className="bg-card flex items-center gap-2 rounded-lg border border-neutral-200 px-2.5 py-1.5 text-[11.5px] dark:border-neutral-700"
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
                                            <span className="text-neutral-500">
                                                {eq.tipo_agente} {eq.capacidad}
                                            </span>
                                        </label>
                                    ))}
                                </div>
                            ) : null}

                            <div>
                                <label className="mb-1 block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300">
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
                                    className="bg-card w-full rounded-xl border border-neutral-200 px-3 py-2 text-xs focus:ring-2 focus:ring-amber-500 dark:border-neutral-700 dark:bg-neutral-900"
                                />
                            </div>

                            <button
                                aria-label="Confirmar Recepción Física en Planta"
                                type="submit"
                                disabled={confirmForm.processing}
                                className="flex w-full items-center justify-center gap-2 rounded-xl bg-amber-600 py-3 text-xs font-bold text-white shadow-sm transition-colors hover:bg-amber-700 active:bg-amber-800"
                            >
                                <CheckCircle2 className="h-4 w-4" />
                                <span>
                                    Confirmar Recepción Física en Planta
                                </span>
                            </button>
                        </form>
                    </div>
                ) : (
                    <div className="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-800 dark:border-emerald-800/40 dark:bg-emerald-950/30 dark:text-emerald-300">
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

                {/* Equipments Section */}
                <div className="space-y-3">
                    <div className="flex items-center justify-between">
                        <div>
                            <h3 className="flex items-center gap-1.5 text-sm font-bold text-neutral-900 dark:text-neutral-100">
                                <QrCode className="text-warning-strong h-4 w-4" />
                                <span>
                                    Equipos en esta Orden (
                                    {order.equipments.length})
                                </span>
                            </h3>
                            <p className="text-[11px] text-neutral-500">
                                Identificación con código de barras Bruce Fire
                            </p>
                        </div>

                        <button
                            type="button"
                            onClick={() => setShowRegisterModal(true)}
                            className="flex items-center gap-1.5 rounded-xl bg-neutral-900 px-3 py-2 text-xs font-semibold text-white shadow-sm transition-transform active:scale-95 dark:bg-neutral-100 dark:text-neutral-900"
                        >
                            <Plus className="h-3.5 w-3.5" />
                            <span>Alta Rápida</span>
                        </button>
                    </div>

                    {order.equipments.length === 0 ? (
                        <div className="bg-card space-y-2 rounded-2xl border border-dashed border-neutral-300 p-6 text-center dark:border-neutral-700 dark:bg-neutral-800/40">
                            <QrCode className="mx-auto h-8 w-8 text-neutral-400" />
                            <p className="text-xs font-medium text-neutral-700 dark:text-neutral-300">
                                Aún no hay equipos vinculados a esta orden
                            </p>
                            <p className="text-[11px] text-neutral-500">
                                Presione "Alta Rápida" para escanear un equipo
                                existente o generar un sticker nuevo.
                            </p>
                        </div>
                    ) : (
                        <div className="space-y-2.5">
                            {order.equipments.map((eq) => (
                                <div
                                    key={eq.id}
                                    className="bg-card space-y-2 rounded-2xl border border-neutral-200 p-3.5 shadow-sm dark:border-neutral-700/80 dark:bg-neutral-800"
                                >
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <span className="text-warning-strong font-mono text-sm font-extrabold">
                                                {eq.numero_serie}
                                            </span>
                                            <span className="rounded bg-neutral-100 px-1.5 py-0.5 text-[10px] font-semibold text-neutral-700 dark:bg-neutral-700 dark:text-neutral-300">
                                                {eq.tipo_agente || 'Extintor'}
                                            </span>
                                            {eq.capacidad && (
                                                <span className="rounded bg-neutral-100 px-1.5 py-0.5 text-[10px] font-semibold text-neutral-700 dark:bg-neutral-700 dark:text-neutral-300">
                                                    {eq.capacidad}
                                                </span>
                                            )}
                                        </div>

                                        <Link
                                            aria-label="Completar checklist del extintor"
                                            href={ChecklistRoutes.create.url({
                                                current_team: teamSlug,
                                                service_order: order.id,
                                                equipment: eq.id,
                                            })}
                                            className="flex items-center gap-1 rounded-xl bg-amber-600 px-2.5 py-1.5 text-[11px] font-bold text-white shadow-xs hover:bg-amber-700"
                                        >
                                            <Wrench className="h-3.5 w-3.5" />
                                            <span>Checklist</span>
                                        </Link>
                                    </div>

                                    <div className="grid grid-cols-2 gap-2 text-[11px] text-neutral-600 dark:text-neutral-400">
                                        <div>
                                            <span className="text-neutral-400">
                                                Marca:{' '}
                                            </span>
                                            <span className="font-medium text-neutral-800 dark:text-neutral-200">
                                                {eq.marca || 'N/A'}
                                            </span>
                                        </div>
                                        <div>
                                            <span className="text-neutral-400">
                                                Serie Fab:{' '}
                                            </span>
                                            <span className="font-mono font-medium text-neutral-800 dark:text-neutral-200">
                                                {eq.serie_fabricante || 'N/A'}
                                            </span>
                                        </div>
                                        <div>
                                            <span className="text-neutral-400">
                                                Año Fab:{' '}
                                            </span>
                                            <span className="font-medium text-neutral-800 dark:text-neutral-200">
                                                {eq.anio_fabricacion || 'N/A'}
                                            </span>
                                        </div>
                                    </div>

                                    {eq.notas && (
                                        <p className="rounded-lg bg-neutral-50 p-2 text-[11px] text-neutral-500 dark:bg-neutral-900/40">
                                            {eq.notas}
                                        </p>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* MODAL: Alta Técnica Rápida (§18) */}
                {showRegisterModal && (
                    <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/60 p-0 backdrop-blur-xs sm:items-center sm:p-4">
                        <div className="animate-in slide-in-from-bottom bg-card max-h-[90vh] w-full space-y-4 overflow-y-auto rounded-t-3xl border border-neutral-200 p-5 shadow-xl duration-200 sm:max-w-md sm:rounded-2xl dark:border-neutral-800 dark:bg-neutral-900">
                            <div className="flex items-center justify-between border-b border-neutral-100 pb-3 dark:border-neutral-800">
                                <div>
                                    <h3 className="text-base font-bold text-neutral-900 dark:text-neutral-100">
                                        Alta Técnica Rápida
                                    </h3>
                                    <p className="text-xs text-neutral-500">
                                        Recepción de extintores en Planta
                                    </p>
                                </div>
                                <button
                                    aria-label="Cerrar alta rápida"
                                    type="button"
                                    onClick={() => setShowRegisterModal(false)}
                                    className="p-1 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200"
                                >
                                    ✕
                                </button>
                            </div>

                            {/* Tabs: Caso A vs Caso B */}
                            <div className="flex gap-2 rounded-xl bg-neutral-100 p-1 dark:bg-neutral-800">
                                <button
                                    type="button"
                                    onClick={() => setRegisterTab('new')}
                                    className={`flex-1 rounded-lg py-2 text-xs font-bold transition-colors ${
                                        registerTab === 'new'
                                            ? 'bg-card text-warning-strong shadow-sm dark:bg-neutral-700 dark:text-amber-400'
                                            : 'text-neutral-600 dark:text-neutral-400'
                                    }`}
                                >
                                    Caso B: Equipo Nuevo
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setRegisterTab('scan')}
                                    className={`flex-1 rounded-lg py-2 text-xs font-bold transition-colors ${
                                        registerTab === 'scan'
                                            ? 'bg-card text-warning-strong shadow-sm dark:bg-neutral-700 dark:text-amber-400'
                                            : 'text-neutral-600 dark:text-neutral-400'
                                    }`}
                                >
                                    Caso A: Ya tiene BF-EQ
                                </button>
                            </div>

                            {/* Tab Content */}
                            {registerTab === 'scan' ? (
                                <div className="space-y-3">
                                    <p className="text-xs text-neutral-600 dark:text-neutral-400">
                                        Escanee o ingrese el código de barras
                                        existente (ej. BF-EQ-000123):
                                    </p>
                                    <div className="flex gap-2">
                                        <input
                                            aria-label="Código de barras del equipo existente"
                                            type="text"
                                            value={barcodeScanInput}
                                            onChange={(e) =>
                                                setBarcodeScanInput(
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="BF-EQ-XXXXXX"
                                            className="flex-1 rounded-xl border border-neutral-300 bg-neutral-50 px-3 py-2 font-mono text-xs uppercase focus:ring-2 focus:ring-amber-500 dark:border-neutral-700 dark:bg-neutral-800"
                                        />
                                        <button
                                            type="button"
                                            onClick={handleSearchExisting}
                                            disabled={scanLoading}
                                            aria-label="Buscar equipo por código"
                                            className="rounded-xl bg-neutral-900 px-4 py-2 text-xs font-bold text-white dark:bg-neutral-700"
                                        >
                                            <Search className="h-4 w-4" />
                                        </button>
                                    </div>

                                    {scanError && (
                                        <p className="text-xs font-medium text-rose-600">
                                            {scanError}
                                        </p>
                                    )}

                                    {scanResult && (
                                        <div className="space-y-2 rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-800/60 dark:bg-amber-950/40">
                                            <div className="font-mono text-sm font-bold text-amber-900 dark:text-amber-300">
                                                {scanResult.numero_serie}
                                            </div>
                                            <div className="text-xs text-neutral-700 dark:text-neutral-300">
                                                <p>
                                                    <b>Cliente:</b>{' '}
                                                    {scanResult.cliente}
                                                </p>
                                                <p>
                                                    <b>Tipo/Capacidad:</b>{' '}
                                                    {scanResult.tipo_agente} -{' '}
                                                    {scanResult.capacidad}
                                                </p>
                                                <p>
                                                    <b>Marca:</b>{' '}
                                                    {scanResult.marca}
                                                </p>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={handleLinkExisting}
                                                className="w-full rounded-xl bg-amber-600 py-2 text-xs font-bold text-white hover:bg-amber-700"
                                            >
                                                Vincular a esta Orden
                                            </button>
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <form
                                    onSubmit={handleCreateNew}
                                    className="space-y-3"
                                >
                                    <div className="rounded-xl bg-neutral-50 p-2.5 text-[11px] text-neutral-600 dark:bg-neutral-800/60 dark:text-neutral-400">
                                        💡 Se asignará un correlativo oficial{' '}
                                        <b>BF-EQ-XXXXXX</b> de Bruce Fire. Si
                                        algún dato no se puede leer, use el
                                        botón de ayuda.
                                    </div>

                                    <div className="grid grid-cols-2 gap-2">
                                        <div>
                                            <label className="mb-1 block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300">
                                                Agente / Tipo *
                                            </label>
                                            <select
                                                aria-label="Agente / Tipo *"
                                                value={
                                                    equipmentForm.data
                                                        .tipo_agente
                                                }
                                                onChange={(e) =>
                                                    equipmentForm.setData(
                                                        'tipo_agente',
                                                        e.target.value,
                                                    )
                                                }
                                                className="w-full rounded-xl border border-neutral-300 bg-neutral-50 px-2.5 py-2 text-xs dark:border-neutral-700 dark:bg-neutral-800"
                                            >
                                                <OpcionesAgente vacia="Sin registrar" />
                                            </select>
                                        </div>

                                        <div>
                                            <label className="mb-1 block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300">
                                                Capacidad *
                                            </label>
                                            <input
                                                aria-label="Capacidad *"
                                                type="text"
                                                value={
                                                    equipmentForm.data.capacidad
                                                }
                                                onChange={(e) =>
                                                    equipmentForm.setData(
                                                        'capacidad',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Ej: 6 kg, 10 lbs..."
                                                className="w-full rounded-xl border border-neutral-300 bg-neutral-50 px-2.5 py-2 text-xs dark:border-neutral-700 dark:bg-neutral-800"
                                            />
                                        </div>
                                    </div>

                                    <div>
                                        <div className="mb-1 flex items-center justify-between">
                                            <label className="text-[11px] font-semibold text-neutral-700 dark:text-neutral-300">
                                                Marca del Cilindro
                                            </label>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setUnreadable('marca')
                                                }
                                                className="text-warning-strong text-[10px] hover:underline"
                                            >
                                                No legible
                                            </button>
                                        </div>
                                        <input
                                            aria-label="Marca"
                                            type="text"
                                            value={equipmentForm.data.marca}
                                            onChange={(e) =>
                                                equipmentForm.setData(
                                                    'marca',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Ej: Buckeye, Amerex, Badger..."
                                            className="w-full rounded-xl border border-neutral-300 bg-neutral-50 px-2.5 py-2 text-xs dark:border-neutral-700 dark:bg-neutral-800"
                                        />
                                    </div>

                                    <div className="grid grid-cols-2 gap-2">
                                        <div>
                                            <div className="mb-1 flex items-center justify-between">
                                                <label className="text-[11px] font-semibold text-neutral-700 dark:text-neutral-300">
                                                    Serie Fab.
                                                </label>
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setUnreadable(
                                                            'serie_fabricante',
                                                        )
                                                    }
                                                    className="text-warning-strong text-[10px] hover:underline"
                                                >
                                                    No legible
                                                </button>
                                            </div>
                                            <input
                                                aria-label="Serie fabricante"
                                                type="text"
                                                value={
                                                    equipmentForm.data
                                                        .serie_fabricante
                                                }
                                                onChange={(e) =>
                                                    equipmentForm.setData(
                                                        'serie_fabricante',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Serie original"
                                                className="w-full rounded-xl border border-neutral-300 bg-neutral-50 px-2.5 py-2 font-mono text-xs dark:border-neutral-700 dark:bg-neutral-800"
                                            />
                                        </div>

                                        <div>
                                            <div className="mb-1 flex items-center justify-between">
                                                <label className="text-[11px] font-semibold text-neutral-700 dark:text-neutral-300">
                                                    Año Fab.
                                                </label>
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setUnreadable(
                                                            'anio_fabricacion',
                                                        )
                                                    }
                                                    className="text-warning-strong text-[10px] hover:underline"
                                                >
                                                    No legible
                                                </button>
                                            </div>
                                            <input
                                                aria-label="Anio fabricacion"
                                                type="text"
                                                value={
                                                    equipmentForm.data
                                                        .anio_fabricacion
                                                }
                                                onChange={(e) =>
                                                    equipmentForm.setData(
                                                        'anio_fabricacion',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Ej: 2021"
                                                className="w-full rounded-xl border border-neutral-300 bg-neutral-50 px-2.5 py-2 text-xs dark:border-neutral-700 dark:bg-neutral-800"
                                            />
                                        </div>
                                    </div>

                                    <div>
                                        <label className="mb-1 block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300">
                                            Notas de Recepción / Ubicación
                                        </label>
                                        <input
                                            aria-label="Notas de Recepción / Ubicación"
                                            type="text"
                                            value={equipmentForm.data.notas}
                                            onChange={(e) =>
                                                equipmentForm.setData(
                                                    'notas',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Detalles visuales del equipo..."
                                            className="w-full rounded-xl border border-neutral-300 bg-neutral-50 px-2.5 py-2 text-xs dark:border-neutral-700 dark:bg-neutral-800"
                                        />
                                    </div>

                                    <button
                                        aria-label="Generar Código BF-EQ y Registrar"
                                        type="submit"
                                        disabled={equipmentForm.processing}
                                        className="flex w-full items-center justify-center gap-2 rounded-xl bg-amber-600 py-3 text-xs font-bold text-white shadow-sm hover:bg-amber-700 active:bg-amber-800"
                                    >
                                        <Sparkles className="h-4 w-4" />
                                        <span>
                                            Generar Código BF-EQ y Registrar
                                        </span>
                                    </button>
                                </form>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </TecnicoPlantaLayout>
    );
}
