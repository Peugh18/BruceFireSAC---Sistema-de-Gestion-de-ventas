import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    Calendar,
    Check,
    CheckCircle2,
    Clock,
    Download,
    FileCheck,
    FileText,
    Flame,
    MapPin,
    PackageCheck,
    Phone,
    Send,
    ShieldCheck,
    Truck,
    UserCheck,
} from 'lucide-react';
import React from 'react';

import TecnicoCampoLayout from '@/layouts/tecnico-campo-layout';
import type { Team } from '@/types';

type Client = {
    id: number;
    razon_social: string;
    numero_documento?: string;
    telefono?: string;
    direccion_fiscal?: string;
};

type Sede = {
    id: number;
    nombre: string;
    direccion?: string;
};

type Equipment = {
    id: number;
    numero_serie: string;
    serie_fabricante?: string | null;
    tipo_agente?: string | null;
    capacidad?: string | null;
    marca?: string | null;
    ubicacion_actual?: string | null;
};

type CustodyEvent = {
    id: number;
    eslabon: string;
    responsable: string;
    fecha?: string;
    payload?: Record<string, unknown>;
};

type ServiceOrder = {
    id: number;
    codigo: string;
    estado: string;
    tipo_servicio?: string;
    fecha?: string;
    client: Client;
    sede?: Sede;
    equipments: Equipment[];
    certificates: Array<{
        id: number;
        numero: string;
        certificate_type?: { nombre: string };
    }>;
};

type Props = {
    currentTeam?: Team | null;
    order: ServiceOrder;
    custodyEvents: CustodyEvent[];
};

const ESLABONES_MAP: Record<string, { label: string; icon: typeof Truck; color: string }> = {
    recojo_campo: { label: 'Recojo en Campo', icon: Truck, color: 'text-[#0284C7] bg-[#E0F2FE]' },
    recepcion_planta: { label: 'Recepción en Planta', icon: PackageCheck, color: 'text-[#D97706] bg-[#FEF3C7]' },
    procesado_planta: { label: 'Taller / Recarga', icon: Flame, color: 'text-[#EA580C] bg-[#FFEDD5]' },
    instalacion_campo: { label: 'Instalación en Sitio', icon: ShieldCheck, color: 'text-[#16A34A] bg-[#DCFCE7]' },
    inspeccion_campo: { label: 'Inspección en Sitio', icon: CheckCircle2, color: 'text-[#0369A1] bg-[#E0F2FE]' },
    entrega_campo: { label: 'Entrega al Cliente', icon: UserCheck, color: 'text-[#16A34A] bg-[#DCFCE7]' },
};

export default function EntregaShow({
    currentTeam,
    order,
    custodyEvents,
}: Props) {
    const teamSlug = currentTeam?.slug ?? '';
    const { auth, flash } = usePage<{ auth?: { user?: { name?: string } }; flash?: { success?: string; error?: string } }>().props;

    const isCerrada = order.estado === 'cerrado';

    const form = useForm({
        receptor_nombre: '',
        receptor_dni: '',
        observaciones_entrega: '',
        conformidad_aceptada: false,
        cerrar_orden: true,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(`/${teamSlug}/tecnico-campo/entregas/${order.id}/confirmar`);
    };

    return (
        <TecnicoCampoLayout title={`Entrega ${order.codigo}`}>
            <Head title={`Entrega ${order.codigo} - Técnico de Campo`} />

            {/* Back Bar */}
            <div className="mb-4 flex items-center justify-between">
                <Link
                    href={`/${teamSlug}/tecnico-campo/entregas`}
                    className="inline-flex items-center gap-1.5 text-xs font-bold text-[#6B6965] hover:text-[#201F1D]"
                >
                    <ArrowLeft className="size-4" />
                    <span>Volver a entregas</span>
                </Link>
                <span className="font-mono text-xs font-black text-[#0284C7]">
                    {order.codigo}
                </span>
            </div>

            {flash?.success && (
                <div className="mb-4 rounded-[10px] border border-[#BBF7D0] bg-[#DCFCE7] p-3 text-xs font-medium text-[#166534]">
                    {flash.success}
                </div>
            )}

            {/* Header Data Card (§22.3) */}
            <div className="mb-5 rounded-[14px] border border-[#E4E1DC] bg-white p-4 shadow-xs">
                <div className="flex items-start justify-between gap-2 border-b border-[#F3F1ED] pb-3 mb-3">
                    <div>
                        <span className="text-[10px] font-bold uppercase tracking-wider text-[#6B6965]">
                            Cliente Receptor
                        </span>
                        <h1 className="text-base font-black text-[#201F1D] leading-snug">
                            {order.client.razon_social}
                        </h1>
                        {order.client.numero_documento && (
                            <span className="text-xs font-mono text-[#6B6965]">
                                RUC / DNI: {order.client.numero_documento}
                            </span>
                        )}
                    </div>
                    <span
                        className={`rounded-full px-2.5 py-1 text-[10px] font-black border ${
                            isCerrada
                                ? 'bg-[#F3F4F6] text-[#374151] border-[#E5E7EB]'
                                : 'bg-[#DCFCE7] text-[#166534] border-[#BBF7D0]'
                        }`}
                    >
                        {order.estado.replace('_', ' ').toUpperCase()}
                    </span>
                </div>

                <div className="space-y-2 text-xs">
                    <div className="flex items-start gap-2">
                        <MapPin className="size-4 text-[#0284C7] shrink-0 mt-0.5" />
                        <div>
                            <span className="font-semibold text-[#201F1D]">Lugar de Entrega: </span>
                            <span className="text-[#6B6965]">
                                {order.sede?.direccion || order.client.direccion_fiscal || 'Sede principal'}
                            </span>
                        </div>
                    </div>

                    {order.client.telefono && (
                        <div className="flex items-center justify-between pt-1">
                            <div className="flex items-center gap-2">
                                <Phone className="size-4 text-[#16A34A] shrink-0" />
                                <span className="font-mono text-[#201F1D]">{order.client.telefono}</span>
                            </div>
                            <a
                                href={`tel:${order.client.telefono}`}
                                className="inline-flex items-center gap-1 rounded-[8px] border border-[#BBF7D0] bg-[#DCFCE7] px-2.5 py-1 text-[11px] font-bold text-[#166534] active:scale-95"
                            >
                                <Phone className="size-3" />
                                Llamar
                            </a>
                        </div>
                    )}
                </div>

                {/* Acta de Conformidad PDF Action */}
                <div className="mt-4 border-t border-[#F3F1ED] pt-3">
                    <a
                        href={`/${teamSlug}/tecnico-campo/entregas/${order.id}/acta-pdf`}
                        target="_blank"
                        rel="noreferrer"
                        className="flex w-full items-center justify-center gap-2 min-h-[44px] rounded-[10px] border border-[#0284C7] bg-[#F0F9FF] text-xs font-black text-[#0284C7] hover:bg-[#E0F2FE] transition-all shadow-2xs"
                    >
                        <FileText className="size-4" />
                        <span>Descargar / Imprimir Acta de Conformidad (PDF)</span>
                    </a>
                </div>
            </div>

            {/* Custody Chain Trail (§22.4, §85.6.3) */}
            <div className="mb-5 rounded-[14px] border border-[#E4E1DC] bg-white p-4 shadow-xs">
                <h2 className="text-xs font-black text-[#201F1D] uppercase tracking-wider flex items-center gap-2 mb-3">
                    <Truck className="size-4 text-[#0284C7]" />
                    Cadena de Custodia del Servicio (§22.4)
                </h2>

                {custodyEvents.length === 0 ? (
                    <p className="text-xs text-[#6B6965]">No hay eventos de custodia registrados aún.</p>
                ) : (
                    <div className="relative pl-6 space-y-4 before:absolute before:left-2.5 before:top-2 before:bottom-2 before:w-0.5 before:bg-[#E4E1DC]">
                        {custodyEvents.map((evt) => {
                            const meta = ESLABONES_MAP[evt.eslabon] || {
                                label: evt.eslabon,
                                icon: CheckCircle2,
                                color: 'text-[#6B6965] bg-[#F3F4F6]',
                            };
                            const Icon = meta.icon;

                            return (
                                <div key={evt.id} className="relative text-xs">
                                    <div
                                        className={`absolute -left-6 top-0 flex size-5 items-center justify-center rounded-full border border-white shadow-2xs ${meta.color}`}
                                    >
                                        <Icon className="size-3" />
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="font-bold text-[#201F1D]">{meta.label}</span>
                                        {evt.fecha && (
                                            <span className="text-[10px] font-mono text-[#6B6965]">
                                                {new Date(evt.fecha).toLocaleDateString('es-PE', {
                                                    day: '2-digit',
                                                    month: '2-digit',
                                                    hour: '2-digit',
                                                    minute: '2-digit',
                                                })}
                                            </span>
                                        )}
                                    </div>
                                    <p className="text-[11px] text-[#6B6965] mt-0.5">
                                        Responsable: <span className="font-semibold text-[#201F1D]">{evt.responsable}</span>
                                    </p>
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>

            {/* Equipments Table/Cards (§23) */}
            <div className="mb-5 rounded-[14px] border border-[#E4E1DC] bg-white p-4 shadow-xs">
                <h2 className="text-xs font-black text-[#201F1D] uppercase tracking-wider flex items-center gap-2 mb-3">
                    <Flame className="size-4 text-[#EA580C]" />
                    Equipos Entregados ({order.equipments.length})
                </h2>

                <div className="space-y-2">
                    {order.equipments.map((eq, idx) => (
                        <div
                            key={eq.id}
                            className="flex items-center justify-between rounded-[8px] bg-[#FAF9F7] p-2.5 border border-[#E4E1DC] text-xs"
                        >
                            <div>
                                <div className="flex items-center gap-2">
                                    <span className="font-mono font-black text-[#0284C7]">{eq.numero_serie}</span>
                                    <span className="rounded bg-white px-1.5 py-0.2 text-[9.5px] font-bold border border-[#E4E1DC]">
                                        {eq.tipo_agente} {eq.capacidad}
                                    </span>
                                </div>
                                <p className="text-[10.5px] text-[#6B6965] mt-0.5">
                                    {eq.marca || 'Bruce Fire'} • {eq.ubicacion_actual || 'Sede cliente'}
                                </p>
                            </div>
                            <span className="rounded-full bg-[#DCFCE7] text-[#166534] px-2 py-0.5 text-[9.5px] font-bold">
                                Conforme
                            </span>
                        </div>
                    ))}
                </div>
            </div>

            {/* Delivery Confirmation Form (§22.3, §85.6.2) */}
            {!isCerrada && (
                <div className="rounded-[14px] border border-[#BAE6FD] bg-white p-4 shadow-sm">
                    <h2 className="text-sm font-black text-[#0369A1] flex items-center gap-2 mb-1">
                        <UserCheck className="size-4 text-[#0284C7]" />
                        Confirmar Entrega y Acta de Conformidad
                    </h2>
                    <p className="text-[11px] text-[#6B6965] mb-4">
                        Cierre final del servicio en sitio con firma/conformidad del receptor (§22.3, §23, §85.6.2).
                    </p>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label className="block text-[11px] font-bold text-[#201F1D] mb-1">
                                    Nombre del Receptor / Encargado en Sede <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    required
                                    value={form.data.receptor_nombre}
                                    onChange={(e) => form.setData('receptor_nombre', e.target.value)}
                                    placeholder="Nombre de quien recibe los extintores"
                                    className="w-full text-xs rounded-[8px] border border-[#E4E1DC] p-2 bg-white"
                                />
                                {form.errors.receptor_nombre && (
                                    <p className="text-[10px] text-red-500 mt-0.5">{form.errors.receptor_nombre}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-[11px] font-bold text-[#201F1D] mb-1">
                                    DNI / Cargo del Receptor
                                </label>
                                <input
                                    type="text"
                                    value={form.data.receptor_dni}
                                    onChange={(e) => form.setData('receptor_dni', e.target.value)}
                                    placeholder="ej. DNI 45892147 / Jefe de Almacén"
                                    className="w-full text-xs rounded-[8px] border border-[#E4E1DC] p-2 bg-white"
                                />
                            </div>
                        </div>

                        <div>
                            <label className="block text-[11px] font-bold text-[#201F1D] mb-1">
                                Observaciones de Entrega
                            </label>
                            <textarea
                                rows={2}
                                value={form.data.observaciones_entrega}
                                onChange={(e) => form.setData('observaciones_entrega', e.target.value)}
                                placeholder="Notas de colocación, tarjetas selladas entregadas..."
                                className="w-full text-xs rounded-[8px] border border-[#E4E1DC] p-2 bg-white"
                            />
                        </div>

                        {/* Checkbox de Conformidad en Sitio (§85.6.2) */}
                        <div className="rounded-[10px] border border-[#BAE6FD] bg-[#F0F9FF] p-3">
                            <label className="flex items-start gap-2.5 cursor-pointer">
                                <input
                                    type="checkbox"
                                    required
                                    checked={form.data.conformidad_aceptada}
                                    onChange={(e) => form.setData('conformidad_aceptada', e.target.checked)}
                                    className="size-4 mt-0.5 rounded border-[#0284C7] text-[#0284C7]"
                                />
                                <span className="text-xs font-semibold text-[#0369A1] leading-tight">
                                    Conformidad en sitio: El receptor declara recibir los extintores detallados debidamente inspeccionados, cargados y operativos según NTP 350.043.
                                </span>
                            </label>
                            {form.errors.conformidad_aceptada && (
                                <p className="text-[10px] text-red-500 mt-1">{form.errors.conformidad_aceptada}</p>
                            )}
                        </div>

                        <label className="flex items-center gap-2 cursor-pointer text-xs font-bold text-[#201F1D]">
                            <input
                                type="checkbox"
                                checked={form.data.cerrar_orden}
                                onChange={(e) => form.setData('cerrar_orden', e.target.checked)}
                                className="size-4 rounded border-[#0284C7] text-[#0284C7]"
                            />
                            <span>Cerrar la Orden de Servicio definitivamente (estado: cerrado)</span>
                        </label>

                        <button
                            type="submit"
                            disabled={form.processing || !form.data.conformidad_aceptada}
                            className="w-full min-h-[48px] rounded-[10px] bg-[#0284C7] text-white font-bold text-xs shadow-sm hover:bg-[#0369A1] transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                        >
                            <Send className="size-4" />
                            <span>Confirmar Entrega y Cerrar Orden de Servicio</span>
                        </button>
                    </form>
                </div>
            )}
        </TecnicoCampoLayout>
    );
}
