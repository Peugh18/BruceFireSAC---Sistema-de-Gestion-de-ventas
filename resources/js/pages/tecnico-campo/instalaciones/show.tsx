import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    Check,
    CheckCircle2,
    Clock,
    FileCheck,
    Flame,
    MapPin,
    Phone,
    Plus,
    Send,
    Trash2,
    UserCheck,
    Wrench,
} from 'lucide-react';
import React, { useState } from 'react';

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
    tipo_agente?: string | null;
    capacidad?: string | null;
    marca?: string | null;
    ubicacion_actual?: string | null;
};

type CertificateType = {
    id: number;
    codigo: string;
    nombre: string;
};

type ServiceOrder = {
    id: number;
    codigo: string;
    estado: string;
    tipo_servicio?: string;
    client_id: number;
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
    customerEquipments: Equipment[];
    certificateTypes: CertificateType[];
};

type InstalledItem = {
    equipment_id?: number | null;
    numero_serie: string;
    tipo_agente: string;
    capacidad: string;
    marca: string;
    ubicacion_actual: string;
};

export default function InstalacionShow({
    currentTeam,
    order,
    customerEquipments,
    certificateTypes,
}: Props) {
    const teamSlug = currentTeam?.slug ?? '';
    const { auth, flash } = usePage<{ auth?: { user?: { name?: string } }; flash?: { success?: string; error?: string } }>().props;

    const isFinalizada = ['listo_entrega', 'entregado', 'cerrado'].includes(order.estado);

    // Lista de equipos a instalar en el formulario
    const [equiposList, setEquiposList] = useState<InstalledItem[]>(
        order.equipments.length > 0
            ? order.equipments.map((eq) => ({
                  equipment_id: eq.id,
                  numero_serie: eq.numero_serie,
                  tipo_agente: eq.tipo_agente || 'PQS',
                  capacidad: eq.capacidad || '6 kg',
                  marca: eq.marca || 'Bruce Fire',
                  ubicacion_actual: eq.ubicacion_actual || '',
              }))
            : [
                  {
                      equipment_id: null,
                      numero_serie: '',
                      tipo_agente: 'PQS',
                      capacidad: '6 kg',
                      marca: 'Bruce Fire',
                      ubicacion_actual: '',
                  },
              ]
    );

    const form = useForm({
        area: 'Área Principal / Operaciones',
        ubicacion_instalada: '',
        pruebas: 'Soporte fijado a 1.50m sobre nivel de piso. Verificación de manómetro y precinto de seguridad conforme NTP 350.043.',
        foto_antes_path: '',
        foto_despues_path: '',
        observaciones: '',
        conformidad_nombre: '',
        conformidad_aceptada: false,
        emitir_certificado: true,
        tipo_certificado_codigo: 'operatividad_garantia',
        equipos: equiposList,
    });

    const addEquipmentRow = () => {
        const updated = [
            ...equiposList,
            {
                equipment_id: null,
                numero_serie: '',
                tipo_agente: 'PQS',
                capacidad: '6 kg',
                marca: 'Bruce Fire',
                ubicacion_actual: form.data.ubicacion_instalada || '',
            },
        ];
        setEquiposList(updated);
        form.setData('equipos', updated);
    };

    const removeEquipmentRow = (index: number) => {
        const updated = equiposList.filter((_, i) => i !== index);
        setEquiposList(updated);
        form.setData('equipos', updated);
    };

    const updateEquipmentRow = (index: number, field: keyof InstalledItem, value: unknown) => {
        const updated = equiposList.map((item, i) => (i === index ? { ...item, [field]: value } : item));
        setEquiposList(updated);
        form.setData('equipos', updated);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.setData('equipos', equiposList);
        form.post(`/${teamSlug}/tecnico-campo/instalaciones/${order.id}`);
    };

    return (
        <TecnicoCampoLayout title={`Instalación ${order.codigo}`}>
            <Head title={`Instalación ${order.codigo} - Técnico de Campo`} />

            {/* Back link */}
            <div className="mb-4 flex items-center justify-between">
                <Link
                    href={`/${teamSlug}/tecnico-campo/instalaciones`}
                    className="inline-flex items-center gap-1.5 text-xs font-bold text-[#6B6965] hover:text-[#201F1D]"
                >
                    <ArrowLeft className="size-4" />
                    <span>Volver a instalaciones</span>
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

            {/* Header Data Card (§25) */}
            <div className="mb-5 rounded-[14px] border border-[#E4E1DC] bg-white p-4 shadow-xs">
                <div className="flex items-start justify-between gap-2 border-b border-[#F3F1ED] pb-3 mb-3">
                    <div>
                        <span className="text-[10px] font-bold uppercase tracking-wider text-[#6B6965]">
                            Cliente & Sede de Montaje
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
                    <span className="rounded-full border border-[#BAE6FD] bg-[#E0F2FE] px-2.5 py-1 text-[10px] font-black text-[#0369A1]">
                        {order.estado.replace('_', ' ').toUpperCase()}
                    </span>
                </div>

                <div className="space-y-2 text-xs">
                    <div className="flex items-start gap-2">
                        <MapPin className="size-4 text-[#0284C7] shrink-0 mt-0.5" />
                        <div>
                            <span className="font-semibold text-[#201F1D]">Lugar de Instalación: </span>
                            <span className="text-[#6B6965]">
                                {order.sede?.direccion || order.client.direccion_fiscal || 'Sede no asignada'}
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
                                Contactar
                            </a>
                        </div>
                    )}
                </div>
            </div>

            {/* Emitted Certificates if any */}
            {order.certificates && order.certificates.length > 0 && (
                <div className="mb-5 rounded-[14px] border border-[#BBF7D0] bg-[#F0FDF4] p-4">
                    <h3 className="text-xs font-black text-[#166534] flex items-center gap-1.5 mb-2">
                        <FileCheck className="size-4 text-[#16A34A]" />
                        Certificados Emitidos
                    </h3>
                    <div className="space-y-1.5">
                        {order.certificates.map((cert) => (
                            <div
                                key={cert.id}
                                className="flex items-center justify-between rounded-[8px] bg-white p-2.5 border border-[#BBF7D0] text-xs"
                            >
                                <span className="font-bold text-[#201F1D]">{cert.certificate_type?.nombre || 'Certificado'}</span>
                                <span className="font-mono text-[#166534] font-bold">{cert.numero}</span>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {/* Installation Form (§25) */}
            <form onSubmit={handleSubmit} className="space-y-5">
                {/* 1. Datos del área y ubicación */}
                <div className="rounded-[14px] border border-[#E4E1DC] bg-white p-4 shadow-xs space-y-3">
                    <h2 className="text-xs font-black text-[#201F1D] uppercase tracking-wider flex items-center gap-2">
                        <Wrench className="size-4 text-[#0284C7]" />
                        1. Ubicación y Área de Instalación (§25)
                    </h2>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label className="block text-[11px] font-bold text-[#201F1D] mb-1">
                                Área / Sección en Instalación <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                required
                                disabled={isFinalizada}
                                value={form.data.area}
                                onChange={(e) => form.setData('area', e.target.value)}
                                placeholder="ej. Almacén Central / Pasillo Oficinas"
                                className="w-full text-xs rounded-[8px] border border-[#E4E1DC] p-2 bg-white disabled:bg-[#F3F4F6]"
                            />
                        </div>

                        <div>
                            <label className="block text-[11px] font-bold text-[#201F1D] mb-1">
                                Punto Específico de Montaje <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                required
                                disabled={isFinalizada}
                                value={form.data.ubicacion_instalada}
                                onChange={(e) => form.setData('ubicacion_instalada', e.target.value)}
                                placeholder="ej. Columna 3 junto al extintor / Pared este a 1.50m"
                                className="w-full text-xs rounded-[8px] border border-[#E4E1DC] p-2 bg-white disabled:bg-[#F3F4F6]"
                            />
                        </div>
                    </div>

                    <div>
                        <label className="block text-[11px] font-bold text-[#201F1D] mb-1">
                            Pruebas y Verificación de Montaje (§25)
                        </label>
                        <textarea
                            rows={2}
                            disabled={isFinalizada}
                            value={form.data.pruebas}
                            onChange={(e) => form.setData('pruebas', e.target.value)}
                            className="w-full text-xs rounded-[8px] border border-[#E4E1DC] p-2 bg-white disabled:bg-[#F3F4F6]"
                        />
                    </div>
                </div>

                {/* 2. Equipos Instalados -> Pasan a Equipos del Cliente (§25) */}
                <div className="rounded-[14px] border border-[#E4E1DC] bg-white p-4 shadow-xs space-y-3">
                    <div className="flex items-center justify-between">
                        <div>
                            <h2 className="text-xs font-black text-[#201F1D] uppercase tracking-wider flex items-center gap-2">
                                <Flame className="size-4 text-[#EA580C]" />
                                2. Extintores / Unidades a Instalar ({equiposList.length})
                            </h2>
                            <p className="text-[10.5px] text-[#6B6965]">
                                Se registran en el historial de Equipos del Cliente (Equipment) sin duplicar tablas (§25).
                            </p>
                        </div>
                        {!isFinalizada && (
                            <button
                                type="button"
                                onClick={addEquipmentRow}
                                className="inline-flex items-center gap-1 rounded-[8px] border border-[#0284C7] bg-[#E0F2FE] px-2.5 py-1 text-xs font-bold text-[#0284C7] active:scale-95"
                            >
                                <Plus className="size-3.5" />
                                Agregar
                            </button>
                        )}
                    </div>

                    <div className="space-y-3">
                        {equiposList.map((item, idx) => (
                            <div
                                key={idx}
                                className="rounded-[10px] border border-[#E4E1DC] bg-[#FAF9F7] p-3 text-xs space-y-2 relative"
                            >
                                <div className="flex items-center justify-between border-b border-[#E4E1DC] pb-1.5">
                                    <span className="font-bold text-[#201F1D]">
                                        Extintor #{idx + 1}
                                    </span>
                                    {!isFinalizada && equiposList.length > 1 && (
                                        <button
                                            type="button"
                                            onClick={() => removeEquipmentRow(idx)}
                                            className="text-red-500 p-1 hover:bg-red-50 rounded"
                                        >
                                            <Trash2 className="size-3.5" />
                                        </button>
                                    )}
                                </div>

                                <div className="grid grid-cols-2 gap-2">
                                    <div>
                                        <label className="block text-[10px] font-bold text-[#6B6965] mb-0.5">
                                            Agente Extintor
                                        </label>
                                        <select
                                            disabled={isFinalizada}
                                            value={item.tipo_agente}
                                            onChange={(e) => updateEquipmentRow(idx, 'tipo_agente', e.target.value)}
                                            className="w-full text-xs rounded-[6px] border border-[#E4E1DC] p-1.5 bg-white"
                                        >
                                            <option value="PQS">PQS</option>
                                            <option value="CO2">CO2</option>
                                            <option value="Agua">Agua</option>
                                            <option value="Acetato de Potasio">Acetato K</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label className="block text-[10px] font-bold text-[#6B6965] mb-0.5">
                                            Capacidad
                                        </label>
                                        <input
                                            type="text"
                                            disabled={isFinalizada}
                                            value={item.capacidad}
                                            onChange={(e) => updateEquipmentRow(idx, 'capacidad', e.target.value)}
                                            placeholder="ej. 6 kg"
                                            className="w-full text-xs rounded-[6px] border border-[#E4E1DC] p-1.5 bg-white"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-[10px] font-bold text-[#6B6965] mb-0.5">
                                            Serie Interna / Código
                                        </label>
                                        <input
                                            type="text"
                                            disabled={isFinalizada}
                                            value={item.numero_serie}
                                            onChange={(e) => updateEquipmentRow(idx, 'numero_serie', e.target.value)}
                                            placeholder="Auto-correlativo si vacío"
                                            className="w-full text-xs rounded-[6px] border border-[#E4E1DC] p-1.5 bg-white font-mono"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-[10px] font-bold text-[#6B6965] mb-0.5">
                                            Marca
                                        </label>
                                        <input
                                            type="text"
                                            disabled={isFinalizada}
                                            value={item.marca}
                                            onChange={(e) => updateEquipmentRow(idx, 'marca', e.target.value)}
                                            placeholder="Marca"
                                            className="w-full text-xs rounded-[6px] border border-[#E4E1DC] p-1.5 bg-white"
                                        />
                                    </div>
                                    <div className="col-span-2">
                                        <label className="block text-[10px] font-bold text-[#6B6965] mb-0.5">
                                            Ubicación Exacta
                                        </label>
                                        <input
                                            type="text"
                                            disabled={isFinalizada}
                                            value={item.ubicacion_actual}
                                            onChange={(e) => updateEquipmentRow(idx, 'ubicacion_actual', e.target.value)}
                                            placeholder="ej. Pared norte frente a montacargas"
                                            className="w-full text-xs rounded-[6px] border border-[#E4E1DC] p-1.5 bg-white"
                                        />
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                {/* 3. Certificado Aplicable (§25) */}
                <div className="rounded-[14px] border border-[#E4E1DC] bg-white p-4 shadow-xs space-y-3">
                    <h2 className="text-xs font-black text-[#201F1D] uppercase tracking-wider flex items-center gap-2">
                        <FileCheck className="size-4 text-[#16A34A]" />
                        3. Certificado Aplicable (§25)
                    </h2>

                    <label className="flex items-center gap-2 cursor-pointer text-xs font-bold text-[#201F1D]">
                        <input
                            type="checkbox"
                            disabled={isFinalizada}
                            checked={form.data.emitir_certificado}
                            onChange={(e) => form.setData('emitir_certificado', e.target.checked)}
                            className="size-4 rounded border-[#0284C7] text-[#0284C7]"
                        />
                        <span>Emitir certificado automático al registrar la instalación</span>
                    </label>

                    {form.data.emitir_certificado && (
                        <div>
                            <label className="block text-[10px] font-bold text-[#6B6965] mb-1">
                                Tipo de Certificado
                            </label>
                            <select
                                disabled={isFinalizada}
                                value={form.data.tipo_certificado_codigo}
                                onChange={(e) => form.setData('tipo_certificado_codigo', e.target.value)}
                                className="w-full text-xs rounded-[8px] border border-[#E4E1DC] p-2 bg-white"
                            >
                                {certificateTypes.map((ct) => (
                                    <option key={ct.id} value={ct.codigo}>
                                        {ct.nombre} ({ct.codigo})
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}
                </div>

                {/* 4. Conformidad del Cliente en Sitio (§25, §85.6.2) */}
                {!isFinalizada && (
                    <div className="rounded-[14px] border border-[#BAE6FD] bg-white p-4 shadow-sm space-y-3">
                        <h2 className="text-xs font-black text-[#0369A1] uppercase tracking-wider flex items-center gap-2">
                            <UserCheck className="size-4 text-[#0284C7]" />
                            4. Conformidad de Instalación en Sitio (§25, §85.6.2)
                        </h2>

                        <div>
                            <label className="block text-[11px] font-bold text-[#201F1D] mb-1">
                                Nombre del Receptor / Encargado en Sitio <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                required
                                value={form.data.conformidad_nombre}
                                onChange={(e) => form.setData('conformidad_nombre', e.target.value)}
                                placeholder="Nombre completo de quien recibe la instalación"
                                className="w-full text-xs rounded-[8px] border border-[#E4E1DC] p-2 bg-white"
                            />
                            {form.errors.conformidad_nombre && (
                                <p className="text-[10px] text-red-500 mt-0.5">{form.errors.conformidad_nombre}</p>
                            )}
                        </div>

                        <div>
                            <label className="block text-[11px] font-bold text-[#201F1D] mb-1">
                                Observaciones Adicionales
                            </label>
                            <textarea
                                rows={2}
                                value={form.data.observaciones}
                                onChange={(e) => form.setData('observaciones', e.target.value)}
                                placeholder="Detalles de señalética instalada, tarjetas de control entregadas..."
                                className="w-full text-xs rounded-[8px] border border-[#E4E1DC] p-2 bg-white"
                            />
                        </div>

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
                                    Conformidad en sitio: El cliente valida la instalación física de los extintores y los soportes de montaje en las ubicaciones acordadas.
                                </span>
                            </label>
                            {form.errors.conformidad_aceptada && (
                                <p className="text-[10px] text-red-500 mt-1">{form.errors.conformidad_aceptada}</p>
                            )}
                        </div>

                        <button
                            type="submit"
                            disabled={form.processing || !form.data.conformidad_aceptada}
                            className="w-full min-h-[48px] rounded-[10px] bg-[#0284C7] text-white font-bold text-xs shadow-sm hover:bg-[#0369A1] transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                        >
                            <Send className="size-4" />
                            <span>Registrar y Guardar Instalación Técnica</span>
                        </button>
                    </div>
                )}
            </form>
        </TecnicoCampoLayout>
    );
}
