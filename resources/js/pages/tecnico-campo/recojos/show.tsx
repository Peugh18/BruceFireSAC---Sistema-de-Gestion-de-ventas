import React from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import TecnicoCampoLayout from '@/layouts/tecnico-campo-layout';
import type { Team } from '@/types';
import {
    ArrowLeft,
    Truck,
    MapPin,
    Phone,
    CheckCircle2,
    Clock,
    ShieldCheck,
    Calendar,
    User,
    FileText,
} from 'lucide-react';

interface CustodyEventItem {
    id: number;
    eslabon: string;
    etapa: string;
    responsable: string;
    fecha: string;
    payload: any;
}

interface Props {
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
        equipments: Array<{
            id: number;
            numero_serie: string;
            tipo_agente: string | null;
            capacidad: string | null;
            marca: string | null;
        }>;
    };
    custodyEvents: CustodyEventItem[];
}

export default function RecojoShow({ order, custodyEvents }: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug = currentTeam?.slug ?? '';
    const teamPrefix = `/${teamSlug}/tecnico-campo`;

    const form = useForm({
        cantidad: order.equipments.length > 0 ? order.equipments.length : 1,
        contacto_nombre: '',
        contacto_telefono: order.cliente.telefono || '',
        observaciones: '',
        conformidad_cliente: false,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(`${teamPrefix}/recojos/${order.id}`, {
            preserveScroll: true,
        });
    };

    const yaRecogido = custodyEvents.some((e) => e.eslabon === 'recojo_campo');

    return (
        <TecnicoCampoLayout>
            <Head title={`Recojo ${order.codigo} - Técnico de Campo`} />

            <div className="space-y-4 pb-16">
                {/* Back Link */}
                <Link
                    href={`${teamPrefix}/recojos`}
                    className="inline-flex items-center gap-1.5 text-xs font-semibold text-neutral-600 dark:text-neutral-400 hover:text-neutral-900"
                >
                    <ArrowLeft className="w-4 h-4" />
                    <span>Volver a Lista de Recojos</span>
                </Link>

                {/* Client & Service Info Card */}
                <div className="p-4 bg-white dark:bg-neutral-800 rounded-2xl border border-neutral-200 dark:border-neutral-700/80 shadow-sm space-y-3">
                    <div className="flex items-start justify-between gap-2">
                        <div>
                            <div className="flex items-center gap-2">
                                <span className="font-mono text-base font-extrabold text-neutral-900 dark:text-neutral-100">
                                    {order.codigo}
                                </span>
                                {yaRecogido ? (
                                    <span className="px-2 py-0.5 text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 rounded-full">
                                        Recojo Registrado
                                    </span>
                                ) : (
                                    <span className="px-2 py-0.5 text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 rounded-full">
                                        Pendiente de Recojo
                                    </span>
                                )}
                            </div>
                            <h2 className="font-bold text-base text-neutral-900 dark:text-neutral-100 mt-1">
                                {order.cliente.nombre}
                            </h2>
                            <p className="text-xs text-neutral-500 font-mono">
                                RUC/DNI: {order.cliente.documento}
                            </p>
                        </div>
                    </div>

                    <div className="space-y-2 pt-2 border-t border-neutral-100 dark:border-neutral-700/60 text-xs text-neutral-600 dark:text-neutral-400">
                        {order.cliente.direccion && (
                            <div className="flex items-start gap-1.5">
                                <MapPin className="w-4 h-4 text-blue-600 mt-0.5 flex-shrink-0" />
                                <a
                                    href={`https://maps.google.com/?q=${encodeURIComponent(order.cliente.direccion)}`}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="text-blue-600 dark:text-blue-400 hover:underline"
                                >
                                    {order.cliente.direccion}
                                </a>
                            </div>
                        )}
                        {order.cliente.telefono && (
                            <div className="flex items-center gap-1.5">
                                <Phone className="w-4 h-4 text-emerald-600 flex-shrink-0" />
                                <a href={`tel:${order.cliente.telefono}`} className="text-neutral-800 dark:text-neutral-200 font-medium">
                                    {order.cliente.telefono}
                                </a>
                            </div>
                        )}
                        {order.vehiculo && (
                            <div className="text-[11px] text-neutral-500">
                                🚗 Vehículo asignado: {order.vehiculo}
                            </div>
                        )}
                    </div>
                </div>

                {/* Cadena de Custodia Timeline (§22.4) */}
                <div className="p-4 bg-white dark:bg-neutral-800 rounded-2xl border border-neutral-200 dark:border-neutral-700/80 shadow-sm space-y-3">
                    <h3 className="text-xs font-bold uppercase text-neutral-700 dark:text-neutral-300 tracking-wider flex items-center gap-1.5">
                        <ShieldCheck className="w-4 h-4 text-blue-600" />
                        <span>Cadena de Custodia (§22.4)</span>
                    </h3>

                    {custodyEvents.length === 0 ? (
                        <p className="text-xs text-neutral-400 italic">
                            Aún no se han registrado eslabones de custodia para esta orden.
                        </p>
                    ) : (
                        <div className="space-y-3 relative before:absolute before:inset-0 before:left-2.5 before:w-0.5 before:bg-neutral-200 dark:before:bg-neutral-700">
                            {custodyEvents.map((ev) => (
                                <div key={ev.id} className="relative pl-7 space-y-1">
                                    <div className="absolute left-1 top-1 w-3.5 h-3.5 rounded-full bg-blue-600 ring-4 ring-white dark:ring-neutral-800" />
                                    <div className="text-xs font-bold text-neutral-900 dark:text-neutral-100">
                                        {ev.etapa || ev.eslabon}
                                    </div>
                                    <div className="text-[11px] text-neutral-500">
                                        Por: {ev.responsable} • {ev.fecha}
                                    </div>
                                    {ev.payload?.contacto_cliente && (
                                        <div className="text-[11px] text-neutral-600 dark:text-neutral-400">
                                            Entregado por cliente: {ev.payload.contacto_cliente}
                                        </div>
                                    )}
                                    {ev.payload?.observaciones && (
                                        <div className="text-[11px] text-neutral-600 dark:text-neutral-400 bg-neutral-50 dark:bg-neutral-900 p-1.5 rounded-lg">
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
                    <div className="p-4 bg-gradient-to-br from-blue-500/10 via-blue-500/5 to-transparent border border-blue-200 dark:border-blue-900/40 rounded-2xl space-y-3">
                        <h3 className="text-xs font-bold uppercase text-blue-900 dark:text-blue-300 tracking-wider flex items-center gap-1.5">
                            <Truck className="w-4 h-4" />
                            <span>Registro de Recojo Físico en Sitio</span>
                        </h3>

                        <form onSubmit={handleSubmit} className="space-y-3 pt-1">
                            <div className="grid grid-cols-2 gap-2">
                                <div>
                                    <label className="block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                                        Cantidad Recogida *
                                    </label>
                                    <input
                                        type="number"
                                        min={1}
                                        value={form.data.cantidad}
                                        onChange={(e) => form.setData('cantidad', Number(e.target.value))}
                                        className="w-full text-xs p-2.5 bg-white dark:bg-neutral-900 border border-neutral-300 dark:border-neutral-700 rounded-xl"
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                                        Teléfono Contacto
                                    </label>
                                    <input
                                        type="text"
                                        value={form.data.contacto_telefono}
                                        onChange={(e) => form.setData('contacto_telefono', e.target.value)}
                                        placeholder="Teléfono móvil"
                                        className="w-full text-xs p-2.5 bg-white dark:bg-neutral-900 border border-neutral-300 dark:border-neutral-700 rounded-xl"
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                                    Nombre del Responsable que Entrega (Cliente) *
                                </label>
                                <input
                                    type="text"
                                    value={form.data.contacto_nombre}
                                    onChange={(e) => form.setData('contacto_nombre', e.target.value)}
                                    placeholder="Nombre completo de quien entrega en el local"
                                    className="w-full text-xs p-2.5 bg-white dark:bg-neutral-900 border border-neutral-300 dark:border-neutral-700 rounded-xl"
                                    required
                                />
                            </div>

                            <div>
                                <label className="block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                                    Observaciones de Estado en Recojo
                                </label>
                                <textarea
                                    value={form.data.observaciones}
                                    onChange={(e) => form.setData('observaciones', e.target.value)}
                                    placeholder="Detalles sobre el estado físico de los extintores al retirarlos..."
                                    rows={2}
                                    className="w-full text-xs p-2.5 bg-white dark:bg-neutral-900 border border-neutral-300 dark:border-neutral-700 rounded-xl"
                                />
                            </div>

                            {/* Conformidad / Firma por defecto (§85.6.2) */}
                            <div className="p-3 bg-white dark:bg-neutral-900 rounded-xl border border-neutral-200 dark:border-neutral-700 space-y-1">
                                <label className="flex items-start gap-2.5 text-xs font-semibold text-neutral-800 dark:text-neutral-200 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={form.data.conformidad_cliente}
                                        onChange={(e) => form.setData('conformidad_cliente', e.target.checked)}
                                        className="mt-0.5 rounded border-neutral-300 text-blue-600 focus:ring-blue-500 w-4 h-4"
                                        required
                                    />
                                    <span>
                                        Conformidad del cliente: El responsable declara haber entregado los equipos detallados para su traslado a Planta Bruce Fire (§85.6.2).
                                    </span>
                                </label>
                            </div>

                            <button
                                type="submit"
                                disabled={form.processing || !form.data.conformidad_cliente}
                                className="w-full py-3.5 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 disabled:opacity-50 text-white rounded-xl text-xs font-bold flex items-center justify-center gap-2 shadow-sm"
                            >
                                <CheckCircle2 className="w-4 h-4" />
                                <span>Confirmar Recojo y Cadena de Custodia</span>
                            </button>
                        </form>
                    </div>
                ) : (
                    <div className="p-4 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/50 rounded-2xl flex items-center gap-2 text-xs text-emerald-800 dark:text-emerald-300">
                        <CheckCircle2 className="w-5 h-5 text-emerald-600 flex-shrink-0" />
                        <span>Recojo formalizado. Los equipos se encuentran en custodia rumbo a Planta.</span>
                    </div>
                )}
            </div>
        </TecnicoCampoLayout>
    );
}
