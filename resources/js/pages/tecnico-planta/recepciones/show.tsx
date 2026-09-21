import React, { useState } from 'react';
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
    ShieldAlert,
    Camera,
    Sparkles,
    Wrench,
} from 'lucide-react';

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
        payload: any;
        created_at: string;
    }>;
}

interface Props {
    order: OrderDetail;
}

export default function RecepcionShow({ order }: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug = currentTeam?.slug ?? '';
    const teamPrefix = `/${teamSlug}/tecnico-planta`;

    const [showRegisterModal, setShowRegisterModal] = useState(false);
    const [registerTab, setRegisterTab] = useState<'scan' | 'new'>('new');
    const [barcodeScanInput, setBarcodeScanInput] = useState('');
    const [scanResult, setScanResult] = useState<any>(null);
    const [scanLoading, setScanLoading] = useState(false);
    const [scanError, setScanError] = useState<string | null>(null);

    // Form for Confirming Reception
    const confirmForm = useForm({
        observaciones: '',
        equipos_recibidos_count: order.equipments.length,
        diferencias: '',
    });

    // Form for Alta Técnica Rápida (Caso A and B)
    const equipmentForm = useForm({
        numero_serie: '',
        tipo_agente: 'PQS ABC',
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
        confirmForm.post(`${teamPrefix}/recepciones/${order.id}/confirmar`, {
            preserveScroll: true,
        });
    };

    const handleSearchExisting = async () => {
        if (!barcodeScanInput.trim()) return;
        setScanLoading(true);
        setScanError(null);
        try {
            const res = await fetch(`${teamPrefix}/equipos/buscar?code=${encodeURIComponent(barcodeScanInput.trim())}`);
            const data = await res.json();
            if (data.found) {
                setScanResult(data.equipment);
            } else {
                setScanResult(null);
                setScanError('No se encontró ningún equipo con este código Bruce Fire.');
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
        equipmentForm.post(`${teamPrefix}/recepciones/${order.id}/equipos`, {
            onSuccess: () => {
                setShowRegisterModal(false);
                setScanResult(null);
                setBarcodeScanInput('');
            },
        });
    };

    const handleCreateNew = (e: React.FormEvent) => {
        e.preventDefault();
        equipmentForm.post(`${teamPrefix}/recepciones/${order.id}/equipos`, {
            onSuccess: () => {
                setShowRegisterModal(false);
                equipmentForm.reset();
            },
        });
    };

    const setUnreadable = (field: 'marca' | 'serie_fabricante' | 'anio_fabricacion') => {
        equipmentForm.setData(field, 'No legible / Pendiente de verificar');
    };

    return (
        <TecnicoPlantaLayout>
            <Head title={`Recepción ${order.codigo} - Planta`} />

            <div className="space-y-4 pb-12">
                {/* Back Button */}
                <Link
                    href={`${teamPrefix}/recepciones`}
                    className="inline-flex items-center gap-1.5 text-xs font-semibold text-neutral-600 dark:text-neutral-400 hover:text-neutral-900"
                >
                    <ArrowLeft className="w-4 h-4" />
                    <span>Volver a Recepciones</span>
                </Link>

                {/* Header Card */}
                <div className="p-4 bg-white dark:bg-neutral-800 rounded-2xl border border-neutral-200 dark:border-neutral-700/80 shadow-sm space-y-3">
                    <div className="flex items-start justify-between gap-2">
                        <div>
                            <div className="flex items-center gap-2">
                                <span className="font-mono text-base font-extrabold text-neutral-900 dark:text-neutral-100">
                                    {order.codigo}
                                </span>
                                {isPendiente ? (
                                    <span className="px-2 py-0.5 text-[10px] font-bold bg-amber-100 text-amber-900 dark:bg-amber-950/60 dark:text-amber-300 rounded-full">
                                        Pendiente de Recepción
                                    </span>
                                ) : (
                                    <span className="px-2 py-0.5 text-[10px] font-bold bg-emerald-100 text-emerald-900 dark:bg-emerald-950/60 dark:text-emerald-300 rounded-full">
                                        Recibido en Planta
                                    </span>
                                )}
                            </div>
                            <h2 className="font-semibold text-base text-neutral-900 dark:text-neutral-100 mt-1">
                                {order.cliente.nombre}
                            </h2>
                            <p className="text-xs text-neutral-500 font-mono">
                                RUC/DNI: {order.cliente.documento} {order.cliente.telefono ? `• Tel: ${order.cliente.telefono}` : ''}
                            </p>
                        </div>

                        {order.equipments.length > 0 && (
                            <a
                                href={`${teamPrefix}/recepciones/${order.id}/stickers`}
                                target="_blank"
                                rel="noreferrer"
                                className="p-2.5 bg-neutral-100 dark:bg-neutral-700 hover:bg-neutral-200 text-neutral-800 dark:text-neutral-200 rounded-xl flex items-center justify-center flex-shrink-0"
                                title="Imprimir stickers 2x2"
                            >
                                <Printer className="w-4 h-4" />
                            </a>
                        )}
                    </div>

                    <div className="grid grid-cols-2 gap-2 pt-2 border-t border-neutral-100 dark:border-neutral-700/60 text-xs">
                        <div className="flex items-center gap-1.5 text-neutral-600 dark:text-neutral-400">
                            <Building2 className="w-3.5 h-3.5 text-neutral-400" />
                            <span>{order.sede || 'Sede Principal'}</span>
                        </div>
                        <div className="flex items-center gap-1.5 text-neutral-600 dark:text-neutral-400">
                            <Calendar className="w-3.5 h-3.5 text-neutral-400" />
                            <span>{order.fecha}</span>
                        </div>
                    </div>

                    {order.observaciones && (
                        <div className="p-2.5 bg-neutral-50 dark:bg-neutral-900/50 rounded-xl text-xs text-neutral-600 dark:text-neutral-400 border border-neutral-100 dark:border-neutral-800">
                            <span className="font-semibold">Observaciones: </span>
                            {order.observaciones}
                        </div>
                    )}
                </div>

                {/* Status & Confirmation Section */}
                {isPendiente ? (
                    <div className="p-4 bg-gradient-to-br from-amber-500/10 via-amber-500/5 to-transparent border border-amber-200 dark:border-amber-900/40 rounded-2xl space-y-3">
                        <div className="flex items-center gap-2 text-amber-900 dark:text-amber-300">
                            <AlertTriangle className="w-4 h-4" />
                            <h3 className="text-xs font-bold uppercase tracking-wider">
                                Confirmación de Ingreso a Taller
                            </h3>
                        </div>
                        <p className="text-xs text-amber-800 dark:text-amber-300/90 leading-relaxed">
                            Verifique que los extintores físicos coincidan con la orden. Puede agregar observaciones o registrar faltantes/sobrantes.
                        </p>

                        <form onSubmit={handleConfirmReception} className="space-y-3 pt-1">
                            <div>
                                <label className="block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                                    Diferencias o novedades en la recepción física
                                </label>
                                <input
                                    type="text"
                                    value={confirmForm.data.diferencias}
                                    onChange={(e) => confirmForm.setData('diferencias', e.target.value)}
                                    placeholder="Ej: Se reciben 2 cilindros en vez de 3, o con manguera rota..."
                                    className="w-full text-xs px-3 py-2 bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded-xl focus:ring-2 focus:ring-amber-500"
                                />
                            </div>

                            <button
                                type="submit"
                                disabled={confirmForm.processing}
                                className="w-full py-3 bg-amber-600 hover:bg-amber-700 active:bg-amber-800 text-white rounded-xl text-xs font-bold flex items-center justify-center gap-2 shadow-sm transition-colors"
                            >
                                <CheckCircle2 className="w-4 h-4" />
                                <span>Confirmar Recepción Física en Planta</span>
                            </button>
                        </form>
                    </div>
                ) : (
                    <div className="p-3 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/40 rounded-xl flex items-center gap-2 text-emerald-800 dark:text-emerald-300 text-xs">
                        <CheckCircle2 className="w-4 h-4 text-emerald-600 flex-shrink-0" />
                        <span>Esta orden ya ingresó formalmente al taller de Planta.</span>
                    </div>
                )}

                {/* Equipments Section */}
                <div className="space-y-3">
                    <div className="flex items-center justify-between">
                        <div>
                            <h3 className="text-sm font-bold text-neutral-900 dark:text-neutral-100 flex items-center gap-1.5">
                                <QrCode className="w-4 h-4 text-amber-600" />
                                <span>Equipos en esta Orden ({order.equipments.length})</span>
                            </h3>
                            <p className="text-[11px] text-neutral-500">
                                Identificación con código de barras Bruce Fire (§18)
                            </p>
                        </div>

                        <button
                            type="button"
                            onClick={() => setShowRegisterModal(true)}
                            className="px-3 py-2 bg-neutral-900 dark:bg-neutral-100 text-white dark:text-neutral-900 rounded-xl text-xs font-semibold flex items-center gap-1.5 shadow-sm active:scale-95 transition-transform"
                        >
                            <Plus className="w-3.5 h-3.5" />
                            <span>Alta Rápida</span>
                        </button>
                    </div>

                    {order.equipments.length === 0 ? (
                        <div className="p-6 bg-white dark:bg-neutral-800/40 border border-dashed border-neutral-300 dark:border-neutral-700 rounded-2xl text-center space-y-2">
                            <QrCode className="w-8 h-8 text-neutral-400 mx-auto" />
                            <p className="text-xs font-medium text-neutral-700 dark:text-neutral-300">
                                Aún no hay equipos vinculados a esta orden
                            </p>
                            <p className="text-[11px] text-neutral-500">
                                Presione "Alta Rápida" para escanear un equipo existente o generar un sticker nuevo.
                            </p>
                        </div>
                    ) : (
                        <div className="space-y-2.5">
                            {order.equipments.map((eq) => (
                                <div
                                    key={eq.id}
                                    className="p-3.5 bg-white dark:bg-neutral-800 rounded-2xl border border-neutral-200 dark:border-neutral-700/80 shadow-sm space-y-2"
                                >
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <span className="font-mono text-sm font-extrabold text-amber-600 dark:text-amber-400">
                                                {eq.numero_serie}
                                            </span>
                                            <span className="px-1.5 py-0.5 text-[10px] font-semibold bg-neutral-100 dark:bg-neutral-700 text-neutral-700 dark:text-neutral-300 rounded">
                                                {eq.tipo_agente || 'Extintor'}
                                            </span>
                                            {eq.capacidad && (
                                                <span className="px-1.5 py-0.5 text-[10px] font-semibold bg-neutral-100 dark:bg-neutral-700 text-neutral-700 dark:text-neutral-300 rounded">
                                                    {eq.capacidad}
                                                </span>
                                            )}
                                        </div>

                                        <Link
                                            href={`${teamPrefix}/ordenes/${order.id}/equipos/${eq.id}/checklist`}
                                            className="px-2.5 py-1.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-[11px] font-bold flex items-center gap-1 shadow-xs"
                                        >
                                            <Wrench className="w-3.5 h-3.5" />
                                            <span>Checklist</span>
                                        </Link>
                                    </div>

                                    <div className="grid grid-cols-2 gap-2 text-[11px] text-neutral-600 dark:text-neutral-400">
                                        <div>
                                            <span className="text-neutral-400">Marca: </span>
                                            <span className="font-medium text-neutral-800 dark:text-neutral-200">
                                                {eq.marca || 'N/A'}
                                            </span>
                                        </div>
                                        <div>
                                            <span className="text-neutral-400">Serie Fab: </span>
                                            <span className="font-mono font-medium text-neutral-800 dark:text-neutral-200">
                                                {eq.serie_fabricante || 'N/A'}
                                            </span>
                                        </div>
                                        <div>
                                            <span className="text-neutral-400">Año Fab: </span>
                                            <span className="font-medium text-neutral-800 dark:text-neutral-200">
                                                {eq.anio_fabricacion || 'N/A'}
                                            </span>
                                        </div>
                                    </div>

                                    {eq.notas && (
                                        <p className="text-[11px] text-neutral-500 bg-neutral-50 dark:bg-neutral-900/40 p-2 rounded-lg">
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
                    <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-end sm:items-center justify-center p-0 sm:p-4">
                        <div className="w-full sm:max-w-md bg-white dark:bg-neutral-900 rounded-t-3xl sm:rounded-2xl max-h-[90vh] overflow-y-auto p-5 space-y-4 shadow-xl border border-neutral-200 dark:border-neutral-800 animate-in slide-in-from-bottom duration-200">
                            <div className="flex items-center justify-between border-b border-neutral-100 dark:border-neutral-800 pb-3">
                                <div>
                                    <h3 className="text-base font-bold text-neutral-900 dark:text-neutral-100">
                                        Alta Técnica Rápida
                                    </h3>
                                    <p className="text-xs text-neutral-500">
                                        Recepción de extintores en Planta (§18)
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setShowRegisterModal(false)}
                                    className="p-1 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200"
                                >
                                    ✕
                                </button>
                            </div>

                            {/* Tabs: Caso A vs Caso B */}
                            <div className="flex gap-2 p-1 bg-neutral-100 dark:bg-neutral-800 rounded-xl">
                                <button
                                    type="button"
                                    onClick={() => setRegisterTab('new')}
                                    className={`flex-1 py-2 text-xs font-bold rounded-lg transition-colors ${
                                        registerTab === 'new'
                                            ? 'bg-white dark:bg-neutral-700 text-amber-600 dark:text-amber-400 shadow-sm'
                                            : 'text-neutral-600 dark:text-neutral-400'
                                    }`}
                                >
                                    Caso B: Equipo Nuevo
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setRegisterTab('scan')}
                                    className={`flex-1 py-2 text-xs font-bold rounded-lg transition-colors ${
                                        registerTab === 'scan'
                                            ? 'bg-white dark:bg-neutral-700 text-amber-600 dark:text-amber-400 shadow-sm'
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
                                        Escanee o ingrese el código de barras existente (ej. BF-EQ-000123):
                                    </p>
                                    <div className="flex gap-2">
                                        <input
                                            type="text"
                                            value={barcodeScanInput}
                                            onChange={(e) => setBarcodeScanInput(e.target.value)}
                                            placeholder="BF-EQ-XXXXXX"
                                            className="flex-1 font-mono text-xs px-3 py-2 bg-neutral-50 dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-xl focus:ring-2 focus:ring-amber-500 uppercase"
                                        />
                                        <button
                                            type="button"
                                            onClick={handleSearchExisting}
                                            disabled={scanLoading}
                                            className="px-4 py-2 bg-neutral-900 dark:bg-neutral-700 text-white rounded-xl text-xs font-bold"
                                        >
                                            <Search className="w-4 h-4" />
                                        </button>
                                    </div>

                                    {scanError && (
                                        <p className="text-xs text-rose-600 font-medium">{scanError}</p>
                                    )}

                                    {scanResult && (
                                        <div className="p-3 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 rounded-xl space-y-2">
                                            <div className="font-mono font-bold text-amber-900 dark:text-amber-300 text-sm">
                                                {scanResult.numero_serie}
                                            </div>
                                            <div className="text-xs text-neutral-700 dark:text-neutral-300">
                                                <p><b>Cliente:</b> {scanResult.cliente}</p>
                                                <p><b>Tipo/Capacidad:</b> {scanResult.tipo_agente} - {scanResult.capacidad}</p>
                                                <p><b>Marca:</b> {scanResult.marca}</p>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={handleLinkExisting}
                                                className="w-full py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold"
                                            >
                                                Vincular a esta Orden
                                            </button>
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <form onSubmit={handleCreateNew} className="space-y-3">
                                    <div className="p-2.5 bg-neutral-50 dark:bg-neutral-800/60 rounded-xl text-[11px] text-neutral-600 dark:text-neutral-400">
                                        💡 Se asignará un correlativo oficial <b>BF-EQ-XXXXXX</b> de Bruce Fire. Si algún dato no se puede leer, use el botón de ayuda.
                                    </div>

                                    <div className="grid grid-cols-2 gap-2">
                                        <div>
                                            <label className="block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                                                Agente / Tipo *
                                            </label>
                                            <select
                                                value={equipmentForm.data.tipo_agente}
                                                onChange={(e) => equipmentForm.setData('tipo_agente', e.target.value)}
                                                className="w-full text-xs px-2.5 py-2 bg-neutral-50 dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-xl"
                                            >
                                                <option value="PQS ABC">PQS ABC</option>
                                                <option value="CO2">CO2</option>
                                                <option value="Agua Presurizada">Agua Presurizada</option>
                                                <option value="Acetato de Potasio (K)">Acetato de Potasio (K)</option>
                                                <option value="Espuma AFFF">Espuma AFFF</option>
                                                <option value="Otro">Otro</option>
                                            </select>
                                        </div>

                                        <div>
                                            <label className="block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                                                Capacidad *
                                            </label>
                                            <input
                                                type="text"
                                                value={equipmentForm.data.capacidad}
                                                onChange={(e) => equipmentForm.setData('capacidad', e.target.value)}
                                                placeholder="Ej: 6 kg, 10 lbs..."
                                                className="w-full text-xs px-2.5 py-2 bg-neutral-50 dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-xl"
                                            />
                                        </div>
                                    </div>

                                    <div>
                                        <div className="flex items-center justify-between mb-1">
                                            <label className="text-[11px] font-semibold text-neutral-700 dark:text-neutral-300">
                                                Marca del Cilindro
                                            </label>
                                            <button
                                                type="button"
                                                onClick={() => setUnreadable('marca')}
                                                className="text-[10px] text-amber-600 hover:underline"
                                            >
                                                No legible
                                            </button>
                                        </div>
                                        <input
                                            type="text"
                                            value={equipmentForm.data.marca}
                                            onChange={(e) => equipmentForm.setData('marca', e.target.value)}
                                            placeholder="Ej: Buckeye, Amerex, Badger..."
                                            className="w-full text-xs px-2.5 py-2 bg-neutral-50 dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-xl"
                                        />
                                    </div>

                                    <div className="grid grid-cols-2 gap-2">
                                        <div>
                                            <div className="flex items-center justify-between mb-1">
                                                <label className="text-[11px] font-semibold text-neutral-700 dark:text-neutral-300">
                                                    Serie Fab.
                                                </label>
                                                <button
                                                    type="button"
                                                    onClick={() => setUnreadable('serie_fabricante')}
                                                    className="text-[10px] text-amber-600 hover:underline"
                                                >
                                                    No legible
                                                </button>
                                            </div>
                                            <input
                                                type="text"
                                                value={equipmentForm.data.serie_fabricante}
                                                onChange={(e) => equipmentForm.setData('serie_fabricante', e.target.value)}
                                                placeholder="Serie original"
                                                className="w-full text-xs px-2.5 py-2 bg-neutral-50 dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-xl font-mono"
                                            />
                                        </div>

                                        <div>
                                            <div className="flex items-center justify-between mb-1">
                                                <label className="text-[11px] font-semibold text-neutral-700 dark:text-neutral-300">
                                                    Año Fab.
                                                </label>
                                                <button
                                                    type="button"
                                                    onClick={() => setUnreadable('anio_fabricacion')}
                                                    className="text-[10px] text-amber-600 hover:underline"
                                                >
                                                    No legible
                                                </button>
                                            </div>
                                            <input
                                                type="text"
                                                value={equipmentForm.data.anio_fabricacion}
                                                onChange={(e) => equipmentForm.setData('anio_fabricacion', e.target.value)}
                                                placeholder="Ej: 2021"
                                                className="w-full text-xs px-2.5 py-2 bg-neutral-50 dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-xl"
                                            />
                                        </div>
                                    </div>

                                    <div>
                                        <label className="block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                                            Notas de Recepción / Ubicación
                                        </label>
                                        <input
                                            type="text"
                                            value={equipmentForm.data.notas}
                                            onChange={(e) => equipmentForm.setData('notas', e.target.value)}
                                            placeholder="Detalles visuales del equipo..."
                                            className="w-full text-xs px-2.5 py-2 bg-neutral-50 dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-xl"
                                        />
                                    </div>

                                    <button
                                        type="submit"
                                        disabled={equipmentForm.processing}
                                        className="w-full py-3 bg-amber-600 hover:bg-amber-700 active:bg-amber-800 text-white rounded-xl text-xs font-bold flex items-center justify-center gap-2 shadow-sm"
                                    >
                                        <Sparkles className="w-4 h-4" />
                                        <span>Generar Código BF-EQ y Registrar</span>
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
