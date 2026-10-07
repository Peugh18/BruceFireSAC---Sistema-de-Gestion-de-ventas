import React, { useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { BotonFoto } from '@/components/captura-evidencia';
import TecnicoPlantaLayout from '@/layouts/tecnico-planta-layout';
import type { Team } from '@/types';
import {
    ArrowLeft,
    CheckCircle2,
    AlertTriangle,
    MinusCircle,
    ClipboardCheck,
    ShieldCheck,
} from 'lucide-react';

interface ElementConfig {
    clave: string;
    nombre: string;
    default_estado: 'conforme' | 'observado' | 'no_aplica';
    aplica: boolean;
}

interface ItemState {
    estado: 'conforme' | 'observado' | 'no_aplica';
    condicion: string;
    nota: string;
    accion_recomendada: string;
    repuesto_sugerido: string;
    requiere_autorizacion: boolean;
    foto: File | null;
}

interface Props {
    order: {
        id: number;
        codigo: string;
        cliente: string;
        tipo_servicio: string;
        estado: string;
    };
    equipment: {
        id: number;
        numero_serie: string;
        tipo_agente: string | null;
        capacidad: string | null;
        marca: string | null;
        serie_fabricante: string | null;
        anio_fabricacion: string | null;
        ubicacion_actual: string | null;
    };
    elementos: ElementConfig[];
}

export default function ChecklistCreate({
    order,
    equipment,
    elementos,
}: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug = currentTeam?.slug ?? '';
    const teamPrefix = `/${teamSlug}/tecnico-planta`;

    // Initialize checklist items state
    const initialItems: Record<string, ItemState> = {};
    elementos.forEach((el) => {
        initialItems[el.clave] = {
            estado: el.default_estado,
            condicion: '',
            nota: '',
            accion_recomendada: '',
            repuesto_sugerido: '',
            requiere_autorizacion: true,
            foto: null,
        };
    });

    const [items, setItems] = useState<Record<string, ItemState>>(initialItems);
    const [observacionesGenerales, setObservacionesGenerales] = useState('');

    const form = useForm({
        items: initialItems,
        observaciones: '',
    });

    const handleEstadoChange = (
        clave: string,
        nuevoEstado: 'conforme' | 'observado' | 'no_aplica',
    ) => {
        setItems((prev) => ({
            ...prev,
            [clave]: {
                ...prev[clave],
                estado: nuevoEstado,
            },
        }));
    };

    const handleFieldChange = (
        clave: string,
        field: keyof ItemState,
        value: any,
    ) => {
        setItems((prev) => ({
            ...prev,
            [clave]: {
                ...prev[clave],
                [field]: value,
            },
        }));
    };

    const handleMarkAllConforme = () => {
        const updated: Record<string, ItemState> = {};
        elementos.forEach((el) => {
            updated[el.clave] = {
                ...items[el.clave],
                estado: el.aplica ? 'conforme' : 'no_aplica',
            };
        });
        setItems(updated);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.setData({
            items,
            observaciones: observacionesGenerales,
        });
        form.post(
            `${teamPrefix}/ordenes/${order.id}/equipos/${equipment.id}/checklist`,
            { forceFormData: true },
        );
    };

    const countObservados = Object.values(items).filter(
        (i) => i.estado === 'observado',
    ).length;

    return (
        <TecnicoPlantaLayout>
            <Head title={`Checklist ${equipment.numero_serie} - Planta`} />

            <div className="space-y-4 pb-20">
                {/* Back Link */}
                <Link
                    href={`${teamPrefix}/recepciones/${order.id}`}
                    className="inline-flex items-center gap-1.5 text-xs font-semibold text-neutral-600 hover:text-neutral-900 dark:text-neutral-400"
                >
                    <ArrowLeft className="h-4 w-4" />
                    <span>Volver a Recepción #{order.codigo}</span>
                </Link>

                {/* Equipment Master Data Header (§19.2) */}
                <div className="bg-card space-y-2.5 rounded-2xl border border-neutral-200 p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
                    <div className="flex items-center justify-between">
                        <span className="text-warning-strong font-mono text-base font-extrabold">
                            {equipment.numero_serie}
                        </span>
                        <span className="text-xs font-semibold text-neutral-500">
                            Orden: {order.codigo}
                        </span>
                    </div>

                    <div className="grid grid-cols-2 gap-2 text-xs text-neutral-600 dark:text-neutral-400">
                        <div>
                            <span className="text-neutral-400">
                                Agente/Tipo:{' '}
                            </span>
                            <span className="font-semibold text-neutral-800 dark:text-neutral-200">
                                {equipment.tipo_agente || 'PQS ABC'}
                            </span>
                        </div>
                        <div>
                            <span className="text-neutral-400">
                                Capacidad:{' '}
                            </span>
                            <span className="font-semibold text-neutral-800 dark:text-neutral-200">
                                {equipment.capacidad || 'N/A'}
                            </span>
                        </div>
                        <div>
                            <span className="text-neutral-400">Marca: </span>
                            <span className="font-medium text-neutral-800 dark:text-neutral-200">
                                {equipment.marca || 'N/A'}
                            </span>
                        </div>
                        <div>
                            <span className="text-neutral-400">Año Fab: </span>
                            <span className="font-medium text-neutral-800 dark:text-neutral-200">
                                {equipment.anio_fabricacion || 'N/A'}
                            </span>
                        </div>
                    </div>
                </div>

                {/* Quick Action bar */}
                <div className="flex items-center justify-between gap-2 rounded-xl bg-neutral-100 p-2 dark:bg-neutral-800/80">
                    <div className="pl-2 text-xs font-medium text-neutral-600 dark:text-neutral-300">
                        {countObservados > 0 ? (
                            <span className="flex items-center gap-1 font-bold text-amber-700 dark:text-amber-400">
                                <AlertTriangle className="h-3.5 w-3.5" />
                                {countObservados} componente(s) observado(s)
                            </span>
                        ) : (
                            <span className="flex items-center gap-1 font-semibold text-emerald-700 dark:text-emerald-400">
                                <ShieldCheck className="h-3.5 w-3.5" />
                                Todos conformes
                            </span>
                        )}
                    </div>
                    <button
                        type="button"
                        onClick={handleMarkAllConforme}
                        className="bg-card rounded-lg px-3 py-1.5 text-xs font-semibold text-neutral-800 shadow-xs hover:bg-neutral-50 dark:bg-neutral-700 dark:text-neutral-200"
                    >
                        Marcar Todo Conforme
                    </button>
                </div>

                {/* Checklist Form Items */}
                <form onSubmit={handleSubmit} className="space-y-3">
                    {elementos.map((el) => {
                        const item = items[el.clave] || {
                            estado: 'conforme',
                            condicion: '',
                            nota: '',
                            accion_recomendada: '',
                            repuesto_sugerido: '',
                            requiere_autorizacion: true,
                            foto: null,
                        };
                        const isObservado = item.estado === 'observado';

                        return (
                            <div
                                key={el.clave}
                                className={`rounded-2xl border p-3.5 transition-all ${
                                    isObservado
                                        ? 'border-amber-300 bg-amber-50/50 dark:border-amber-800 dark:bg-amber-950/20'
                                        : 'bg-card border-neutral-200 dark:border-neutral-700 dark:bg-neutral-800'
                                }`}
                            >
                                <div className="space-y-2.5">
                                    <div className="flex items-center justify-between gap-2">
                                        <label className="text-xs font-bold text-neutral-900 dark:text-neutral-100">
                                            {el.nombre}
                                        </label>
                                    </div>

                                    {/* 3 State Touch Buttons (Mobile-first min 44px) */}
                                    <div className="grid grid-cols-3 gap-1.5 rounded-xl bg-neutral-100 p-1 dark:bg-neutral-900">
                                        <button
                                            type="button"
                                            onClick={() =>
                                                handleEstadoChange(
                                                    el.clave,
                                                    'conforme',
                                                )
                                            }
                                            className={`flex min-h-[44px] items-center justify-center gap-1 rounded-lg text-xs font-bold transition-all ${
                                                item.estado === 'conforme'
                                                    ? 'bg-emerald-600 text-white shadow-sm'
                                                    : 'text-neutral-600 hover:text-neutral-900 dark:text-neutral-400'
                                            }`}
                                        >
                                            <CheckCircle2 className="h-4 w-4" />
                                            <span>Conforme</span>
                                        </button>

                                        <button
                                            type="button"
                                            onClick={() =>
                                                handleEstadoChange(
                                                    el.clave,
                                                    'observado',
                                                )
                                            }
                                            className={`flex min-h-[44px] items-center justify-center gap-1 rounded-lg text-xs font-bold transition-all ${
                                                item.estado === 'observado'
                                                    ? 'bg-amber-600 text-white shadow-sm'
                                                    : 'text-neutral-600 hover:text-neutral-900 dark:text-neutral-400'
                                            }`}
                                        >
                                            <AlertTriangle className="h-4 w-4" />
                                            <span>Observado</span>
                                        </button>

                                        <button
                                            type="button"
                                            onClick={() =>
                                                handleEstadoChange(
                                                    el.clave,
                                                    'no_aplica',
                                                )
                                            }
                                            className={`flex min-h-[44px] items-center justify-center gap-1 rounded-lg text-xs font-bold transition-all ${
                                                item.estado === 'no_aplica'
                                                    ? 'bg-neutral-600 text-white shadow-sm'
                                                    : 'text-neutral-600 hover:text-neutral-900 dark:text-neutral-400'
                                            }`}
                                        >
                                            <MinusCircle className="h-4 w-4" />
                                            <span>N / A</span>
                                        </button>
                                    </div>

                                    {/* Expanded Observado Subform (§19.2) */}
                                    {isObservado && (
                                        <div className="animate-in fade-in space-y-2 border-t border-amber-200 pt-2.5 duration-150 dark:border-amber-900/60">
                                            <div className="grid grid-cols-2 gap-2">
                                                <div>
                                                    <label className="mb-1 block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300">
                                                        Condición / Falla *
                                                    </label>
                                                    <input
                                                        type="text"
                                                        value={item.condicion}
                                                        onChange={(e) =>
                                                            handleFieldChange(
                                                                el.clave,
                                                                'condicion',
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="Ej: Picado, Fisurado, Despresurizado..."
                                                        className="bg-card w-full rounded-xl border border-neutral-300 px-2.5 py-2 text-xs dark:border-neutral-700 dark:bg-neutral-900"
                                                        required
                                                    />
                                                </div>
                                                <div>
                                                    <label className="mb-1 block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300">
                                                        Acción Recomendada
                                                    </label>
                                                    <input
                                                        type="text"
                                                        value={
                                                            item.accion_recomendada
                                                        }
                                                        onChange={(e) =>
                                                            handleFieldChange(
                                                                el.clave,
                                                                'accion_recomendada',
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="Ej: Cambio de componente, P.H..."
                                                        className="bg-card w-full rounded-xl border border-neutral-300 px-2.5 py-2 text-xs dark:border-neutral-700 dark:bg-neutral-900"
                                                    />
                                                </div>
                                            </div>

                                            <div className="grid grid-cols-2 gap-2">
                                                <div>
                                                    <label className="mb-1 block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300">
                                                        Repuesto Sugerido
                                                    </label>
                                                    <input
                                                        type="text"
                                                        value={
                                                            item.repuesto_sugerido
                                                        }
                                                        onChange={(e) =>
                                                            handleFieldChange(
                                                                el.clave,
                                                                'repuesto_sugerido',
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="Ej: Manómetro 1/8, Manguera..."
                                                        className="bg-card w-full rounded-xl border border-neutral-300 px-2.5 py-2 text-xs dark:border-neutral-700 dark:bg-neutral-900"
                                                    />
                                                </div>
                                                <div className="flex items-center pt-5">
                                                    <label className="flex cursor-pointer items-center gap-2 text-xs font-semibold text-amber-900 dark:text-amber-300">
                                                        <input
                                                            type="checkbox"
                                                            checked={
                                                                item.requiere_autorizacion
                                                            }
                                                            onChange={(e) =>
                                                                handleFieldChange(
                                                                    el.clave,
                                                                    'requiere_autorizacion',
                                                                    e.target
                                                                        .checked,
                                                                )
                                                            }
                                                            className="text-warning-strong h-4 w-4 rounded border-amber-400 focus:ring-amber-500"
                                                        />
                                                        <span>
                                                            Requiere
                                                            Autorización
                                                        </span>
                                                    </label>
                                                </div>
                                            </div>

                                            <div className="flex flex-wrap items-center gap-2">
                                                <BotonFoto
                                                    archivo={item.foto}
                                                    etiqueta="Tomar foto *"
                                                    onFoto={(f) =>
                                                        handleFieldChange(
                                                            el.clave,
                                                            'foto',
                                                            f,
                                                        )
                                                    }
                                                />
                                                <span className="text-[11px] font-semibold text-neutral-700 dark:text-neutral-300">
                                                    {item.foto
                                                        ? item.foto.name
                                                        : 'La foto es obligatoria en un componente observado.'}
                                                </span>
                                                {form.errors[
                                                    `items.${el.clave}.foto` as keyof typeof form.errors
                                                ] && (
                                                    <span
                                                        className="text-[11px] font-semibold text-red-600"
                                                        role="alert"
                                                    >
                                                        {
                                                            form.errors[
                                                                `items.${el.clave}.foto` as keyof typeof form.errors
                                                            ]
                                                        }
                                                    </span>
                                                )}
                                            </div>

                                            <div>
                                                <label className="mb-1 block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300">
                                                    Nota Técnica
                                                </label>
                                                <input
                                                    type="text"
                                                    value={item.nota}
                                                    onChange={(e) =>
                                                        handleFieldChange(
                                                            el.clave,
                                                            'nota',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="Detalles adicionales para Vendedor..."
                                                    className="bg-card w-full rounded-xl border border-neutral-300 px-2.5 py-2 text-xs dark:border-neutral-700 dark:bg-neutral-900"
                                                />
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </div>
                        );
                    })}

                    {/* General Observations */}
                    <div className="bg-card space-y-1.5 rounded-2xl border border-neutral-200 p-3.5 dark:border-neutral-700 dark:bg-neutral-800">
                        <label className="block text-xs font-bold text-neutral-900 dark:text-neutral-100">
                            Observaciones Generales de la Inspección
                        </label>
                        <textarea
                            value={observacionesGenerales}
                            onChange={(e) =>
                                setObservacionesGenerales(e.target.value)
                            }
                            placeholder="Conclusiones técnicas adicionales..."
                            rows={2}
                            className="w-full rounded-xl border border-neutral-200 bg-neutral-50 p-2.5 text-xs focus:ring-2 focus:ring-amber-500 dark:border-neutral-700 dark:bg-neutral-900"
                        />
                    </div>

                    {/* Sticky Bottom Bar */}
                    <div className="bg-card/95 fixed right-0 bottom-14 left-0 z-40 mx-auto flex max-w-lg items-center justify-between gap-3 border-t border-neutral-200 p-3 backdrop-blur-md dark:border-neutral-800 dark:bg-neutral-900/95">
                        <div className="text-xs">
                            <span className="font-semibold text-neutral-800 dark:text-neutral-200">
                                {countObservados > 0
                                    ? `${countObservados} Falla(s)`
                                    : '100% Conforme'}
                            </span>
                        </div>
                        <button
                            type="submit"
                            disabled={
                                form.processing ||
                                Object.values(items).some(
                                    (i) => i.estado === 'observado' && !i.foto,
                                )
                            }
                            className="flex flex-1 items-center justify-center gap-2 rounded-xl bg-amber-600 py-3 text-xs font-bold text-white shadow-md hover:bg-amber-700 active:bg-amber-800"
                        >
                            <ClipboardCheck className="h-4 w-4" />
                            <span>Guardar Checklist Técnico</span>
                        </button>
                    </div>
                    {Object.values(form.errors)[0] ? (
                        <p
                            className="text-center text-[11px] font-semibold text-red-500"
                            role="alert"
                        >
                            {Object.values(form.errors)[0]}
                        </p>
                    ) : null}
                </form>
            </div>
        </TecnicoPlantaLayout>
    );
}
