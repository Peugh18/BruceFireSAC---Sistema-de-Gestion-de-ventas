import React, { useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import TecnicoPlantaLayout from '@/layouts/tecnico-planta-layout';
import type { Team } from '@/types';
import {
    ArrowLeft,
    CheckCircle2,
    AlertTriangle,
    MinusCircle,
    ClipboardCheck,
    Wrench,
    HelpCircle,
    Sparkles,
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

export default function ChecklistCreate({ order, equipment, elementos }: Props) {
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
        };
    });

    const [items, setItems] = useState<Record<string, ItemState>>(initialItems);
    const [observacionesGenerales, setObservacionesGenerales] = useState('');

    const form = useForm({
        items: initialItems,
        observaciones: '',
    });

    const handleEstadoChange = (clave: string, nuevoEstado: 'conforme' | 'observado' | 'no_aplica') => {
        setItems((prev) => ({
            ...prev,
            [clave]: {
                ...prev[clave],
                estado: nuevoEstado,
            },
        }));
    };

    const handleFieldChange = (clave: string, field: keyof ItemState, value: any) => {
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
        form.post(`${teamPrefix}/ordenes/${order.id}/equipos/${equipment.id}/checklist`);
    };

    const countObservados = Object.values(items).filter((i) => i.estado === 'observado').length;

    return (
        <TecnicoPlantaLayout>
            <Head title={`Checklist ${equipment.numero_serie} - Planta`} />

            <div className="space-y-4 pb-20">
                {/* Back Link */}
                <Link
                    href={`${teamPrefix}/recepciones/${order.id}`}
                    className="inline-flex items-center gap-1.5 text-xs font-semibold text-neutral-600 dark:text-neutral-400 hover:text-neutral-900"
                >
                    <ArrowLeft className="w-4 h-4" />
                    <span>Volver a Recepción #{order.codigo}</span>
                </Link>

                {/* Equipment Master Data Header (§19.2) */}
                <div className="p-4 bg-white dark:bg-neutral-800 rounded-2xl border border-neutral-200 dark:border-neutral-700 shadow-sm space-y-2.5">
                    <div className="flex items-center justify-between">
                        <span className="font-mono text-base font-extrabold text-amber-600 dark:text-amber-400">
                            {equipment.numero_serie}
                        </span>
                        <span className="text-xs font-semibold text-neutral-500">
                            Orden: {order.codigo}
                        </span>
                    </div>

                    <div className="grid grid-cols-2 gap-2 text-xs text-neutral-600 dark:text-neutral-400">
                        <div>
                            <span className="text-neutral-400">Agente/Tipo: </span>
                            <span className="font-semibold text-neutral-800 dark:text-neutral-200">
                                {equipment.tipo_agente || 'PQS ABC'}
                            </span>
                        </div>
                        <div>
                            <span className="text-neutral-400">Capacidad: </span>
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
                <div className="flex items-center justify-between gap-2 p-2 bg-neutral-100 dark:bg-neutral-800/80 rounded-xl">
                    <div className="text-xs font-medium text-neutral-600 dark:text-neutral-300 pl-2">
                        {countObservados > 0 ? (
                            <span className="text-amber-700 dark:text-amber-400 font-bold flex items-center gap-1">
                                <AlertTriangle className="w-3.5 h-3.5" />
                                {countObservados} componente(s) observado(s)
                            </span>
                        ) : (
                            <span className="text-emerald-700 dark:text-emerald-400 font-semibold flex items-center gap-1">
                                <ShieldCheck className="w-3.5 h-3.5" />
                                Todos conformes
                            </span>
                        )}
                    </div>
                    <button
                        type="button"
                        onClick={handleMarkAllConforme}
                        className="px-3 py-1.5 bg-white dark:bg-neutral-700 hover:bg-neutral-50 text-neutral-800 dark:text-neutral-200 rounded-lg text-xs font-semibold shadow-xs"
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
                        };
                        const isObservado = item.estado === 'observado';

                        return (
                            <div
                                key={el.clave}
                                className={`p-3.5 rounded-2xl border transition-all ${
                                    isObservado
                                        ? 'bg-amber-50/50 dark:bg-amber-950/20 border-amber-300 dark:border-amber-800'
                                        : 'bg-white dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700'
                                }`}
                            >
                                <div className="space-y-2.5">
                                    <div className="flex items-center justify-between gap-2">
                                        <label className="text-xs font-bold text-neutral-900 dark:text-neutral-100">
                                            {el.nombre}
                                        </label>
                                    </div>

                                    {/* 3 State Touch Buttons (Mobile-first min 44px) */}
                                    <div className="grid grid-cols-3 gap-1.5 p-1 bg-neutral-100 dark:bg-neutral-900 rounded-xl">
                                        <button
                                            type="button"
                                            onClick={() => handleEstadoChange(el.clave, 'conforme')}
                                            className={`min-h-[44px] rounded-lg text-xs font-bold flex items-center justify-center gap-1 transition-all ${
                                                item.estado === 'conforme'
                                                    ? 'bg-emerald-600 text-white shadow-sm'
                                                    : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900'
                                            }`}
                                        >
                                            <CheckCircle2 className="w-4 h-4" />
                                            <span>Conforme</span>
                                        </button>

                                        <button
                                            type="button"
                                            onClick={() => handleEstadoChange(el.clave, 'observado')}
                                            className={`min-h-[44px] rounded-lg text-xs font-bold flex items-center justify-center gap-1 transition-all ${
                                                item.estado === 'observado'
                                                    ? 'bg-amber-600 text-white shadow-sm'
                                                    : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900'
                                            }`}
                                        >
                                            <AlertTriangle className="w-4 h-4" />
                                            <span>Observado</span>
                                        </button>

                                        <button
                                            type="button"
                                            onClick={() => handleEstadoChange(el.clave, 'no_aplica')}
                                            className={`min-h-[44px] rounded-lg text-xs font-bold flex items-center justify-center gap-1 transition-all ${
                                                item.estado === 'no_aplica'
                                                    ? 'bg-neutral-600 text-white shadow-sm'
                                                    : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900'
                                            }`}
                                        >
                                            <MinusCircle className="w-4 h-4" />
                                            <span>N / A</span>
                                        </button>
                                    </div>

                                    {/* Expanded Observado Subform (§19.2) */}
                                    {isObservado && (
                                        <div className="pt-2.5 space-y-2 border-t border-amber-200 dark:border-amber-900/60 animate-in fade-in duration-150">
                                            <div className="grid grid-cols-2 gap-2">
                                                <div>
                                                    <label className="block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                                                        Condición / Falla *
                                                    </label>
                                                    <input
                                                        type="text"
                                                        value={item.condicion}
                                                        onChange={(e) => handleFieldChange(el.clave, 'condicion', e.target.value)}
                                                        placeholder="Ej: Picado, Fisurado, Despresurizado..."
                                                        className="w-full text-xs px-2.5 py-2 bg-white dark:bg-neutral-900 border border-neutral-300 dark:border-neutral-700 rounded-xl"
                                                        required
                                                    />
                                                </div>
                                                <div>
                                                    <label className="block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                                                        Acción Recomendada
                                                    </label>
                                                    <input
                                                        type="text"
                                                        value={item.accion_recomendada}
                                                        onChange={(e) => handleFieldChange(el.clave, 'accion_recomendada', e.target.value)}
                                                        placeholder="Ej: Cambio de componente, P.H..."
                                                        className="w-full text-xs px-2.5 py-2 bg-white dark:bg-neutral-900 border border-neutral-300 dark:border-neutral-700 rounded-xl"
                                                    />
                                                </div>
                                            </div>

                                            <div className="grid grid-cols-2 gap-2">
                                                <div>
                                                    <label className="block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                                                        Repuesto Sugerido
                                                    </label>
                                                    <input
                                                        type="text"
                                                        value={item.repuesto_sugerido}
                                                        onChange={(e) => handleFieldChange(el.clave, 'repuesto_sugerido', e.target.value)}
                                                        placeholder="Ej: Manómetro 1/8, Manguera..."
                                                        className="w-full text-xs px-2.5 py-2 bg-white dark:bg-neutral-900 border border-neutral-300 dark:border-neutral-700 rounded-xl"
                                                    />
                                                </div>
                                                <div className="flex items-center pt-5">
                                                    <label className="flex items-center gap-2 text-xs font-semibold text-amber-900 dark:text-amber-300 cursor-pointer">
                                                        <input
                                                            type="checkbox"
                                                            checked={item.requiere_autorizacion}
                                                            onChange={(e) => handleFieldChange(el.clave, 'requiere_autorizacion', e.target.checked)}
                                                            className="rounded border-amber-400 text-amber-600 focus:ring-amber-500 w-4 h-4"
                                                        />
                                                        <span>Requiere Autorización</span>
                                                    </label>
                                                </div>
                                            </div>

                                            <div>
                                                <label className="block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                                                    Nota Técnica
                                                </label>
                                                <input
                                                    type="text"
                                                    value={item.nota}
                                                    onChange={(e) => handleFieldChange(el.clave, 'nota', e.target.value)}
                                                    placeholder="Detalles adicionales para Vendedor..."
                                                    className="w-full text-xs px-2.5 py-2 bg-white dark:bg-neutral-900 border border-neutral-300 dark:border-neutral-700 rounded-xl"
                                                />
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </div>
                        );
                    })}

                    {/* General Observations */}
                    <div className="p-3.5 bg-white dark:bg-neutral-800 rounded-2xl border border-neutral-200 dark:border-neutral-700 space-y-1.5">
                        <label className="block text-xs font-bold text-neutral-900 dark:text-neutral-100">
                            Observaciones Generales de la Inspección
                        </label>
                        <textarea
                            value={observacionesGenerales}
                            onChange={(e) => setObservacionesGenerales(e.target.value)}
                            placeholder="Conclusiones técnicas adicionales..."
                            rows={2}
                            className="w-full text-xs p-2.5 bg-neutral-50 dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded-xl focus:ring-2 focus:ring-amber-500"
                        />
                    </div>

                    {/* Sticky Bottom Bar */}
                    <div className="fixed bottom-14 left-0 right-0 p-3 bg-white/95 dark:bg-neutral-900/95 backdrop-blur-md border-t border-neutral-200 dark:border-neutral-800 flex items-center justify-between gap-3 max-w-lg mx-auto z-40">
                        <div className="text-xs">
                            <span className="font-semibold text-neutral-800 dark:text-neutral-200">
                                {countObservados > 0 ? `${countObservados} Falla(s)` : '100% Conforme'}
                            </span>
                        </div>
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="flex-1 py-3 bg-amber-600 hover:bg-amber-700 active:bg-amber-800 text-white rounded-xl text-xs font-bold flex items-center justify-center gap-2 shadow-md"
                        >
                            <ClipboardCheck className="w-4 h-4" />
                            <span>Guardar Checklist Técnico</span>
                        </button>
                    </div>
                </form>
            </div>
        </TecnicoPlantaLayout>
    );
}
