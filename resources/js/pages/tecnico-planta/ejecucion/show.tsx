import React, { useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import TecnicoPlantaLayout from '@/layouts/tecnico-planta-layout';
import type { Team } from '@/types';
import {
    ArrowLeft,
    CheckCircle2,
    Clock,
    Wrench,
    AlertTriangle,
    Package,
    Sparkles,
    ShieldCheck,
    FileCheck2,
    Calendar,
    ChevronRight,
    QrCode,
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
    order: OrderDetail;
    repuestos: ProductItem[];
    certificates: CertificateItem[];
}

export default function EjecucionShow({ order, repuestos, certificates }: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug = currentTeam?.slug ?? '';
    const teamPrefix = `/${teamSlug}/tecnico-planta`;

    const [selectedDeficiency, setSelectedDeficiency] = useState<DeficiencyItem | null>(null);
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

        spareForm.post(`${teamPrefix}/ordenes/${order.id}/deficiencias/${selectedDeficiency.id}/consumir-repuesto`, {
            onSuccess: () => {
                setSelectedDeficiency(null);
                spareForm.reset();
            },
        });
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
                    className="inline-flex items-center gap-1.5 text-xs font-semibold text-neutral-600 dark:text-neutral-400 hover:text-neutral-900"
                >
                    <ArrowLeft className="w-4 h-4" />
                    <span>Volver a Órdenes de Planta</span>
                </Link>

                {/* Header Card */}
                <div className="p-4 bg-white dark:bg-neutral-800 rounded-2xl border border-neutral-200 dark:border-neutral-700/80 shadow-sm space-y-3">
                    <div className="flex items-start justify-between gap-2">
                        <div>
                            <div className="flex items-center gap-2">
                                <span className="font-mono text-base font-extrabold text-neutral-900 dark:text-neutral-100">
                                    {order.codigo}
                                </span>
                                <span className="px-2 py-0.5 text-[10px] font-extrabold uppercase rounded-full bg-amber-100 text-amber-900 dark:bg-amber-950/60 dark:text-amber-300">
                                    {order.estado.replace('_', ' ')}
                                </span>
                            </div>
                            <h2 className="font-semibold text-base text-neutral-900 dark:text-neutral-100 mt-1">
                                {order.cliente}
                            </h2>
                            <p className="text-xs text-neutral-500 font-mono">
                                RUC/DNI: {order.cliente_doc} {order.telefono ? `• ${order.telefono}` : ''}
                            </p>
                        </div>
                    </div>

                    {/* Progress steps (§16.2) */}
                    <div className="pt-2 border-t border-neutral-100 dark:border-neutral-700/60">
                        <div className="flex items-center justify-between text-[11px] font-bold text-neutral-500">
                            <span className={order.estado === 'recibido_planta' || order.estado === 'en_revision' ? 'text-amber-600 font-extrabold' : ''}>
                                1. Taller
                            </span>
                            <ChevronRight className="w-3 h-3 text-neutral-300" />
                            <span className={order.estado === 'en_proceso' ? 'text-amber-600 font-extrabold' : ''}>
                                2. En Proceso
                            </span>
                            <ChevronRight className="w-3 h-3 text-neutral-300" />
                            <span className={order.estado === 'trabajo_terminado' ? 'text-amber-600 font-extrabold' : ''}>
                                3. Terminado
                            </span>
                            <ChevronRight className="w-3 h-3 text-neutral-300" />
                            <span className={order.estado === 'listo_certificado' ? 'text-emerald-600 font-extrabold' : ''}>
                                4. Certificado
                            </span>
                        </div>
                    </div>
                </div>

                {/* Primary Action Buttons Bar */}
                <div className="p-4 bg-gradient-to-br from-amber-500/10 via-amber-500/5 to-transparent border border-amber-200 dark:border-amber-900/40 rounded-2xl space-y-3">
                    <h3 className="text-xs font-bold uppercase text-amber-900 dark:text-amber-300 tracking-wider flex items-center gap-1.5">
                        <Wrench className="w-4 h-4" />
                        <span>Control de Ejecución en Planta (§85)</span>
                    </h3>

                    {order.estado === 'recibido_planta' || order.estado === 'autorizado' ? (
                        <button
                            type="button"
                            onClick={() => handleAdvance('en_proceso')}
                            className="w-full py-3 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold flex items-center justify-center gap-2 shadow-sm"
                        >
                            <Wrench className="w-4 h-4" />
                            <span>Iniciar Trabajo en Taller (En Proceso)</span>
                        </button>
                    ) : order.estado === 'en_proceso' ? (
                        <button
                            type="button"
                            onClick={() => handleAdvance('trabajo_terminado')}
                            className="w-full py-3 bg-neutral-900 dark:bg-neutral-100 hover:bg-neutral-800 text-white dark:text-neutral-900 rounded-xl text-xs font-bold flex items-center justify-center gap-2 shadow-sm"
                        >
                            <CheckCircle2 className="w-4 h-4" />
                            <span>Marcar Trabajo como Terminado en Taller</span>
                        </button>
                    ) : order.estado === 'trabajo_terminado' || order.estado === 'datos_completos' ? (
                        <div className="space-y-3">
                            <label className="flex items-center gap-2 text-xs font-semibold text-neutral-800 dark:text-neutral-200 cursor-pointer bg-white dark:bg-neutral-800 p-3 rounded-xl border border-neutral-200 dark:border-neutral-700">
                                <input
                                    type="checkbox"
                                    checked={phRealizada}
                                    onChange={(e) => setPhRealizada(e.target.checked)}
                                    className="w-4 h-4 rounded text-amber-600 border-neutral-300 focus:ring-amber-500"
                                />
                                <span>Se realizó Prueba Hidrostática (P.H.) en el cilindro</span>
                            </label>

                            <button
                                type="button"
                                onClick={() => handleAdvance('listo_certificado')}
                                className="w-full py-3.5 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white rounded-xl text-xs font-extrabold flex items-center justify-center gap-2 shadow-md"
                            >
                                <Sparkles className="w-4 h-4" />
                                <span>Cerrar Taller y Emitir Certificados Automáticos</span>
                            </button>
                        </div>
                    ) : (
                        <div className="p-3 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 rounded-xl text-xs text-emerald-800 dark:text-emerald-300 flex items-center gap-2">
                            <ShieldCheck className="w-5 h-5 text-emerald-600 flex-shrink-0" />
                            <span>Trabajo de taller finalizado y certificado emitido.</span>
                        </div>
                    )}
                </div>

                {/* Certificates Section */}
                {certificates.length > 0 && (
                    <div className="p-4 bg-white dark:bg-neutral-800 rounded-2xl border border-neutral-200 dark:border-neutral-700/80 shadow-sm space-y-3">
                        <div className="flex items-center gap-2 text-neutral-900 dark:text-neutral-100 font-bold text-sm">
                            <FileCheck2 className="w-4 h-4 text-emerald-600" />
                            <span>Certificados Emitidos ({certificates.length})</span>
                        </div>

                        <div className="space-y-2">
                            {certificates.map((cert) => (
                                <div
                                    key={cert.id}
                                    className="p-3 bg-emerald-50/60 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800/50 rounded-xl flex items-center justify-between gap-2"
                                >
                                    <div>
                                        <div className="font-mono text-xs font-bold text-emerald-900 dark:text-emerald-300">
                                            {cert.numero}
                                        </div>
                                        <div className="text-xs font-medium text-neutral-800 dark:text-neutral-200">
                                            {cert.tipo}
                                        </div>
                                        <div className="text-[11px] text-neutral-500">
                                            Vigente hasta: {cert.fecha_vigencia_hasta}
                                        </div>
                                    </div>
                                    <span className="px-2 py-0.5 text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300 rounded-full uppercase">
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
                        <h3 className="text-sm font-bold text-neutral-900 dark:text-neutral-100 flex items-center gap-1.5">
                            <Wrench className="w-4 h-4 text-amber-600" />
                            <span>Repuestos y Deficiencias ({order.deficiencies.length})</span>
                        </h3>
                    </div>

                    {order.deficiencies.length === 0 ? (
                        <div className="p-6 bg-white dark:bg-neutral-800/40 border border-dashed border-neutral-200 dark:border-neutral-700 rounded-2xl text-center">
                            <ShieldCheck className="w-8 h-8 text-emerald-500 mx-auto mb-1.5" />
                            <p className="text-xs font-medium text-neutral-700 dark:text-neutral-300">
                                No hay deficiencias registradas en esta orden
                            </p>
                        </div>
                    ) : (
                        <div className="space-y-2.5">
                            {order.deficiencies.map((d) => (
                                <div
                                    key={d.id}
                                    className="p-3.5 bg-white dark:bg-neutral-800 rounded-2xl border border-neutral-200 dark:border-neutral-700/80 shadow-sm space-y-2"
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
                                        <span className={`px-2 py-0.5 text-[10px] font-bold rounded-full uppercase ${
                                            d.estado === 'resuelta'
                                                ? 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300'
                                                : d.estado === 'autorizada'
                                                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                                                    : 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300'
                                        }`}>
                                            {d.estado.replace('_', ' ')}
                                        </span>
                                    </div>

                                    {d.repuesto_sugerido && (
                                        <div className="text-[11px] text-neutral-500">
                                            <b>Repuesto requerido: </b> {d.repuesto_sugerido}
                                        </div>
                                    )}

                                    {d.resolucion ? (
                                        <div className="p-2 bg-blue-50 dark:bg-blue-950/30 rounded-lg text-xs text-blue-900 dark:text-blue-300">
                                            <b>Solución: </b> {d.resolucion}
                                        </div>
                                    ) : (
                                        <button
                                            type="button"
                                            onClick={() => setSelectedDeficiency(d)}
                                            className="w-full py-2 bg-neutral-900 dark:bg-neutral-100 hover:bg-neutral-800 text-white dark:text-neutral-900 rounded-xl text-xs font-bold flex items-center justify-center gap-1.5 shadow-xs"
                                        >
                                            <Package className="w-3.5 h-3.5" />
                                            <span>Consumir Repuesto de Almacén (Kardex)</span>
                                        </button>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* MODAL: Consumir Repuesto de Almacén (§85) */}
                {selectedDeficiency && (
                    <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-end sm:items-center justify-center p-0 sm:p-4">
                        <div className="w-full sm:max-w-md bg-white dark:bg-neutral-900 rounded-t-3xl sm:rounded-2xl p-5 space-y-4 shadow-xl border border-neutral-200 dark:border-neutral-800 animate-in slide-in-from-bottom duration-200">
                            <div className="flex items-center justify-between border-b border-neutral-100 dark:border-neutral-800 pb-3">
                                <div>
                                    <h3 className="text-base font-bold text-neutral-900 dark:text-neutral-100">
                                        Consumo de Repuesto
                                    </h3>
                                    <p className="text-xs text-neutral-500">
                                        Kardex Almacén para {selectedDeficiency.componente}
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

                            <form onSubmit={handleConsumeSpare} className="space-y-3">
                                <div>
                                    <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                                        Producto / Repuesto de Almacén *
                                    </label>
                                    <select
                                        value={spareForm.data.product_id}
                                        onChange={(e) => spareForm.setData('product_id', Number(e.target.value))}
                                        className="w-full text-xs p-2.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-xl"
                                        required
                                    >
                                        {repuestos.map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.codigo ? `[${p.codigo}] ` : ''}{p.nombre}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                                        Cantidad a Descontar del Kardex *
                                    </label>
                                    <input
                                        type="number"
                                        min={1}
                                        value={spareForm.data.cantidad}
                                        onChange={(e) => spareForm.setData('cantidad', Number(e.target.value))}
                                        className="w-full text-xs p-2 bg-neutral-50 dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-xl"
                                        required
                                    />
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                                        Observación Técnica
                                    </label>
                                    <input
                                        type="text"
                                        value={spareForm.data.observacion}
                                        onChange={(e) => spareForm.setData('observacion', e.target.value)}
                                        placeholder="Ej: Reemplazo por fisura de manómetro..."
                                        className="w-full text-xs p-2 bg-neutral-50 dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-xl"
                                    />
                                </div>

                                <button
                                    type="submit"
                                    disabled={spareForm.processing}
                                    className="w-full py-3 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold flex items-center justify-center gap-2 shadow-sm"
                                >
                                    <Package className="w-4 h-4" />
                                    <span>Registrar Salida en Kardex y Resolver</span>
                                </button>
                            </form>
                        </div>
                    </div>
                )}
            </div>
        </TecnicoPlantaLayout>
    );
}
