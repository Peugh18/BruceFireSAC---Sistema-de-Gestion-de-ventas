import { Head, Link, useForm, usePage } from '@inertiajs/react';
import TomarOrden, { type AsignacionOrden } from '@/components/tomar-orden';
import ConversacionOrden, {
    type ConversacionProps,
} from '@/components/conversacion-orden';
import FirmaCanvas from '@/components/firma-canvas';
import { etiquetaEstadoOrden } from '@/lib/estados-orden';
import SubirEvidencia, {
    type EvidenciaListada,
} from '@/components/subir-evidencia';
import {
    ArrowLeft,
    Check,
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
import { toast } from 'sonner';

import TecnicoCampoLayout from '@/layouts/tecnico-campo-layout';
import instalaciones from '@/routes/tecnico-campo/instalaciones';
import type { Team } from '@/types';
import OpcionesAgente, {
    useAgentesExtintor,
} from '@/components/opciones-agente';
import { NativeSelect } from '@/components/ui/native-select';

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
    asignacion: AsignacionOrden;
    currentTeam?: Team | null;
    order: ServiceOrder;
    customerEquipments: Equipment[];
    unidadesVendidas: Equipment[];
    evidencias: EvidenciaListada[];
    conversacion: ConversacionProps;
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
    asignacion,
    currentTeam,
    order,
    unidadesVendidas,
    evidencias,
    conversacion,
    certificateTypes,
}: Props) {
    const teamSlug = currentTeam?.slug ?? '';
    const { flash } = usePage<{
        auth?: { user?: { name?: string } };
        flash?: { success?: string; error?: string };
    }>().props;

    const isFinalizada = ['listo_entrega', 'entregado', 'cerrado'].includes(
        order.estado,
    );
    const agentes = useAgentesExtintor();

    // Lista de equipos a instalar en el formulario
    const [equiposList, setEquiposList] = useState<InstalledItem[]>(
        unidadesVendidas.length > 0
            ? []
            : order.equipments.length > 0
              ? order.equipments.map((eq) => ({
                    equipment_id: eq.id,
                    numero_serie: eq.numero_serie,
                    tipo_agente: agentes.includes(eq.tipo_agente ?? '')
                        ? (eq.tipo_agente as string)
                        : '',
                    capacidad: eq.capacidad || '6 kg',
                    marca: eq.marca || 'Bruce Fire',
                    ubicacion_actual: eq.ubicacion_actual || '',
                }))
              : [
                    {
                        equipment_id: null,
                        numero_serie: '',
                        tipo_agente: '',
                        capacidad: '6 kg',
                        marca: 'Bruce Fire',
                        ubicacion_actual: '',
                    },
                ],
    );

    const form = useForm({
        area: 'Área Principal / Operaciones',
        ubicacion_instalada: '',
        pruebas:
            'Soporte fijado a 1.50m sobre nivel de piso. Verificación de manómetro y precinto de seguridad conforme NTP 350.043.',
        observaciones: '',
        conformidad_nombre: '',
        conformidad_aceptada: false,
        emitir_certificado: true,
        tipo_certificado_codigo: 'operatividad_garantia',
        firma: null as string | null,
        equipos: equiposList,
    });

    // T4: cada unidad vendida se escanea (o se escribe su código BF-EQ).
    const [codigo, setCodigo] = useState('');
    const [errorEscaneo, setErrorEscaneo] = useState<string | null>(null);
    const escaneadas = new Set(equiposList.map((i) => i.equipment_id));
    const faltantes = unidadesVendidas.filter((u) => !escaneadas.has(u.id));

    const escanear = () => {
        const unidad = unidadesVendidas.find(
            (u) => u.numero_serie.toLowerCase() === codigo.trim().toLowerCase(),
        );

        if (!unidad) {
            setErrorEscaneo(
                'Ese código no es de una unidad vendida a este cliente.',
            );

            return;
        }

        setErrorEscaneo(null);
        setCodigo('');

        if (escaneadas.has(unidad.id)) {
            return;
        }

        const updated = [
            ...equiposList,
            {
                equipment_id: unidad.id,
                numero_serie: unidad.numero_serie,
                tipo_agente: unidad.tipo_agente ?? '',
                capacidad: unidad.capacidad ?? '',
                marca: 'Bruce Fire',
                ubicacion_actual: form.data.ubicacion_instalada || '',
            },
        ];
        setEquiposList(updated);
        form.setData('equipos', updated);
    };

    const addEquipmentRow = () => {
        const updated = [
            ...equiposList,
            {
                equipment_id: null,
                numero_serie: '',
                tipo_agente: '',
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

    const updateEquipmentRow = (
        index: number,
        field: keyof InstalledItem,
        value: unknown,
    ) => {
        const updated = equiposList.map((item, i) =>
            i === index ? { ...item, [field]: value } : item,
        );
        setEquiposList(updated);
        form.setData('equipos', updated);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.setData('equipos', equiposList);
        form.post(
            instalaciones.store.url({
                current_team: teamSlug,
                service_order: order.id,
            }),
            {
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ??
                            'No se pudo completar la acción.',
                    ),
            },
        );
    };

    return (
        <TecnicoCampoLayout title={`Instalación ${order.codigo}`}>
            <Head title={`Instalación ${order.codigo} - Técnico de Campo`} />

            {/* Back link */}
            <div className="mb-4 flex items-center justify-between">
                <Link
                    href={instalaciones.index.url({ current_team: teamSlug })}
                    className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-xs font-bold"
                >
                    <ArrowLeft className="size-4" />
                    <span>Volver a instalaciones</span>
                </Link>
                <span className="text-foreground font-mono text-xs font-black">
                    {order.codigo}
                </span>
            </div>

            {flash?.success && (
                <div className="text-success-strong mb-4 rounded-lg border border-emerald-500/20 bg-emerald-500/10 p-3 text-xs font-medium">
                    {flash.success}
                </div>
            )}

            <TomarOrden asignacion={asignacion} />

            {/* Header Data Card (§25) */}
            <div className="border-border bg-card mb-5 rounded-2xl border p-4 shadow-xs">
                <div className="border-border mb-3 flex items-start justify-between gap-2 border-b pb-3">
                    <div>
                        <span className="text-muted-foreground text-xs font-bold tracking-wider uppercase">
                            Cliente & Sede de Montaje
                        </span>
                        <h1 className="text-foreground text-base leading-snug font-black">
                            {order.client.razon_social}
                        </h1>
                        {order.client.numero_documento && (
                            <span className="text-muted-foreground font-mono text-xs">
                                RUC / DNI: {order.client.numero_documento}
                            </span>
                        )}
                    </div>
                    <span className="border-border bg-muted text-foreground rounded-full border px-2.5 py-1 text-xs font-black">
                        {etiquetaEstadoOrden(order.estado).toUpperCase()}
                    </span>
                </div>

                <div className="space-y-2 text-xs">
                    <div className="flex items-start gap-2">
                        <MapPin className="text-foreground mt-0.5 size-4 shrink-0" />
                        <div>
                            <span className="text-foreground font-semibold">
                                Lugar de Instalación:{' '}
                            </span>
                            <span className="text-muted-foreground">
                                {order.sede?.direccion ||
                                    order.client.direccion_fiscal ||
                                    'Sede no asignada'}
                            </span>
                        </div>
                    </div>

                    {order.client.telefono && (
                        <div className="flex items-center justify-between pt-1">
                            <div className="flex items-center gap-2">
                                <Phone className="text-success-strong size-4 shrink-0" />
                                <span className="text-foreground font-mono">
                                    {order.client.telefono}
                                </span>
                            </div>
                            <a
                                href={`tel:${order.client.telefono}`}
                                className="text-success-strong inline-flex items-center gap-1 rounded-lg border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-1 text-xs font-bold transition hover:brightness-95 active:scale-95 dark:text-emerald-400"
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
                <div className="mb-5 rounded-2xl border border-emerald-500/20 bg-emerald-500/10 p-4">
                    <h3 className="text-success-strong mb-2 flex items-center gap-1.5 text-xs font-black">
                        <FileCheck className="text-success-strong size-4" />
                        Certificados Emitidos
                    </h3>
                    <div className="space-y-1.5">
                        {order.certificates.map((cert) => (
                            <div
                                key={cert.id}
                                className="bg-card flex items-center justify-between rounded-lg border border-emerald-500/20 p-2.5 text-xs"
                            >
                                <span className="text-foreground font-bold">
                                    {cert.certificate_type?.nombre ||
                                        'Certificado'}
                                </span>
                                <span className="text-success-strong font-mono font-bold">
                                    {cert.numero}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {/* Installation Form (§25) */}
            <form onSubmit={handleSubmit} className="space-y-5">
                {/* 1. Datos del área y ubicación */}
                <div className="border-border bg-card space-y-3 rounded-2xl border p-4 shadow-xs">
                    <h2 className="text-foreground flex items-center gap-2 text-xs font-black tracking-wider uppercase">
                        <Wrench className="text-foreground size-4" />
                        1. Ubicación y Área de Instalación
                    </h2>

                    <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div>
                            <label className="text-foreground mb-1 block text-xs font-bold">
                                Área / Sección en Instalación{' '}
                                <span className="text-red-500">*</span>
                            </label>
                            <input
                                aria-label="Area"
                                type="text"
                                required
                                disabled={isFinalizada}
                                value={form.data.area}
                                onChange={(e) =>
                                    form.setData('area', e.target.value)
                                }
                                placeholder="ej. Almacén Central / Pasillo Oficinas"
                                className="border-border bg-card disabled:bg-muted w-full rounded-lg border p-2 text-xs"
                            />
                        </div>

                        <div>
                            <label className="text-foreground mb-1 block text-xs font-bold">
                                Punto Específico de Montaje{' '}
                                <span className="text-red-500">*</span>
                            </label>
                            <input
                                aria-label="Ubicacion instalada"
                                type="text"
                                required
                                disabled={isFinalizada}
                                value={form.data.ubicacion_instalada}
                                onChange={(e) =>
                                    form.setData(
                                        'ubicacion_instalada',
                                        e.target.value,
                                    )
                                }
                                placeholder="ej. Columna 3 junto al extintor / Pared este a 1.50m"
                                className="border-border bg-card disabled:bg-muted w-full rounded-lg border p-2 text-xs"
                            />
                        </div>
                    </div>

                    <div>
                        <label className="text-foreground mb-1 block text-xs font-bold">
                            Pruebas y Verificación de Montaje
                        </label>
                        <textarea
                            aria-label="Pruebas y Verificación de Montaje"
                            rows={2}
                            disabled={isFinalizada}
                            value={form.data.pruebas}
                            onChange={(e) =>
                                form.setData('pruebas', e.target.value)
                            }
                            className="border-border bg-card disabled:bg-muted w-full rounded-lg border p-2 text-xs"
                        />
                    </div>
                </div>

                {unidadesVendidas.length > 0 && (
                    <div className="border-border bg-card space-y-3 rounded-2xl border p-4 shadow-xs">
                        <h2 className="text-foreground text-xs font-black tracking-wider uppercase">
                            Escanear unidades vendidas (
                            {unidadesVendidas.length - faltantes.length}/
                            {unidadesVendidas.length})
                        </h2>
                        {!isFinalizada && (
                            <div className="flex gap-2">
                                <label
                                    htmlFor="codigo-unidad"
                                    className="sr-only"
                                >
                                    Código del extintor
                                </label>
                                <input
                                    id="codigo-unidad"
                                    value={codigo}
                                    onChange={(e) => setCodigo(e.target.value)}
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter') {
                                            e.preventDefault();
                                            escanear();
                                        }
                                    }}
                                    placeholder="Escanea o escribe BF-EQ-..."
                                    className="border-border bg-card min-h-[44px] flex-1 rounded-lg border p-2 font-mono text-xs"
                                />
                                <button
                                    type="button"
                                    onClick={escanear}
                                    className="bg-primary min-h-[44px] rounded-lg px-4 text-xs font-bold text-white transition hover:brightness-95"
                                >
                                    Agregar
                                </button>
                            </div>
                        )}
                        {errorEscaneo && (
                            <p
                                className="text-destructive-strong text-xs font-semibold"
                                role="alert"
                            >
                                {errorEscaneo}
                            </p>
                        )}
                        <ul className="space-y-1">
                            {unidadesVendidas.map((u) => (
                                <li
                                    key={u.id}
                                    className="flex items-center gap-2 text-xs"
                                >
                                    {escaneadas.has(u.id) ? (
                                        <Check className="text-success-strong size-4" />
                                    ) : (
                                        <span className="size-4 rounded-full border border-neutral-400" />
                                    )}
                                    <span className="font-mono font-semibold">
                                        {u.numero_serie}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {u.tipo_agente} {u.capacidad}
                                    </span>
                                </li>
                            ))}
                        </ul>
                        {faltantes.length > 0 && (
                            <p className="text-warning-strong text-xs font-semibold dark:text-amber-400">
                                Faltan {faltantes.length} unidad(es) por
                                escanear.
                            </p>
                        )}
                    </div>
                )}

                {/* 2. Equipos Instalados -> Pasan a Equipos del Cliente (§25) */}
                <div className="border-border bg-card space-y-3 rounded-2xl border p-4 shadow-xs">
                    <div className="flex items-center justify-between">
                        <div>
                            <h2 className="text-foreground flex items-center gap-2 text-xs font-black tracking-wider uppercase">
                                <Flame className="text-warning-strong size-4" />
                                2. Extintores / Unidades a Instalar (
                                {equiposList.length})
                            </h2>
                            <p className="text-muted-foreground text-xs">
                                Se registran en el historial de Equipos del
                                Cliente (Equipment) sin duplicar tablas.
                            </p>
                        </div>
                        {!isFinalizada && (
                            <button
                                type="button"
                                onClick={addEquipmentRow}
                                className="border-border bg-muted text-foreground inline-flex items-center gap-1 rounded-lg border px-2.5 py-1 text-xs font-bold transition hover:brightness-95 active:scale-95"
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
                                className="border-border bg-muted/40 relative space-y-2 rounded-lg border p-3 text-xs"
                            >
                                <div className="border-border flex items-center justify-between border-b pb-1.5">
                                    <span className="text-foreground font-bold">
                                        Extintor #{idx + 1}
                                    </span>
                                    {!isFinalizada &&
                                        equiposList.length > 1 && (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    removeEquipmentRow(idx)
                                                }
                                                className="rounded p-1 text-red-500 hover:bg-red-50"
                                            >
                                                <Trash2 className="size-3.5" />
                                                <span className="sr-only">
                                                    Quitar extintor
                                                </span>
                                            </button>
                                        )}
                                </div>

                                <div className="grid grid-cols-2 gap-2">
                                    <div>
                                        <label className="text-muted-foreground mb-0.5 block text-xs font-bold">
                                            Agente Extintor
                                        </label>
                                        <NativeSelect
                                            aria-label="Agente Extintor"
                                            disabled={isFinalizada}
                                            value={item.tipo_agente}
                                            onChange={(e) =>
                                                updateEquipmentRow(
                                                    idx,
                                                    'tipo_agente',
                                                    e.target.value,
                                                )
                                            }
                                            className="border-border bg-card w-full rounded-md border p-1.5 text-xs"
                                        >
                                            <OpcionesAgente vacia="Sin registrar" />
                                        </NativeSelect>
                                    </div>
                                    <div>
                                        <label className="text-muted-foreground mb-0.5 block text-xs font-bold">
                                            Capacidad
                                        </label>
                                        <input
                                            aria-label="Capacidad"
                                            type="text"
                                            disabled={isFinalizada}
                                            value={item.capacidad}
                                            onChange={(e) =>
                                                updateEquipmentRow(
                                                    idx,
                                                    'capacidad',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="ej. 6 kg"
                                            className="border-border bg-card w-full rounded-md border p-1.5 text-xs"
                                        />
                                    </div>
                                    <div>
                                        <label className="text-muted-foreground mb-0.5 block text-xs font-bold">
                                            Serie Interna / Código
                                        </label>
                                        <input
                                            aria-label="Serie Interna / Código"
                                            type="text"
                                            disabled={isFinalizada}
                                            value={item.numero_serie}
                                            onChange={(e) =>
                                                updateEquipmentRow(
                                                    idx,
                                                    'numero_serie',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Auto-correlativo si vacío"
                                            className="border-border bg-card w-full rounded-md border p-1.5 font-mono text-xs"
                                        />
                                    </div>
                                    <div>
                                        <label className="text-muted-foreground mb-0.5 block text-xs font-bold">
                                            Marca
                                        </label>
                                        <input
                                            aria-label="Marca"
                                            type="text"
                                            disabled={isFinalizada}
                                            value={item.marca}
                                            onChange={(e) =>
                                                updateEquipmentRow(
                                                    idx,
                                                    'marca',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Marca"
                                            className="border-border bg-card w-full rounded-md border p-1.5 text-xs"
                                        />
                                    </div>
                                    <div className="col-span-2">
                                        <label className="text-muted-foreground mb-0.5 block text-xs font-bold">
                                            Ubicación Exacta
                                        </label>
                                        <input
                                            aria-label="Ubicación Exacta"
                                            type="text"
                                            disabled={isFinalizada}
                                            value={item.ubicacion_actual}
                                            onChange={(e) =>
                                                updateEquipmentRow(
                                                    idx,
                                                    'ubicacion_actual',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="ej. Pared norte frente a montacargas"
                                            className="border-border bg-card w-full rounded-md border p-1.5 text-xs"
                                        />
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                {/* 3. Certificado Aplicable (§25) */}
                <div className="border-border bg-card space-y-3 rounded-2xl border p-4 shadow-xs">
                    <h2 className="text-foreground flex items-center gap-2 text-xs font-black tracking-wider uppercase">
                        <FileCheck className="text-success-strong size-4" />
                        3. Certificado Aplicable
                    </h2>

                    <label className="text-foreground flex cursor-pointer items-center gap-2 text-xs font-bold">
                        <input
                            type="checkbox"
                            disabled={isFinalizada}
                            checked={form.data.emitir_certificado}
                            onChange={(e) =>
                                form.setData(
                                    'emitir_certificado',
                                    e.target.checked,
                                )
                            }
                            className="border-border text-foreground size-4 rounded"
                        />
                        <span>
                            Emitir certificado automático al registrar la
                            instalación
                        </span>
                    </label>

                    {form.data.emitir_certificado && (
                        <div>
                            <label className="text-muted-foreground mb-1 block text-xs font-bold">
                                Tipo de Certificado
                            </label>
                            <NativeSelect
                                aria-label="Tipo de Certificado"
                                disabled={isFinalizada}
                                value={form.data.tipo_certificado_codigo}
                                onChange={(e) =>
                                    form.setData(
                                        'tipo_certificado_codigo',
                                        e.target.value,
                                    )
                                }
                                className="border-border bg-card w-full rounded-lg border p-2 text-xs"
                            >
                                {certificateTypes.map((ct) => (
                                    <option key={ct.id} value={ct.codigo}>
                                        {ct.nombre} ({ct.codigo})
                                    </option>
                                ))}
                            </NativeSelect>
                        </div>
                    )}
                </div>

                {/* 4. Conformidad del Cliente en Sitio (§25, §85.6.2) */}
                {!isFinalizada && (
                    <div className="bg-card border-border space-y-3 rounded-2xl border p-4 shadow-sm">
                        <h2 className="text-foreground flex items-center gap-2 text-xs font-black tracking-wider uppercase">
                            <UserCheck className="text-foreground size-4" />
                            4. Conformidad de Instalación en Sitio
                        </h2>

                        <div>
                            <label className="text-foreground mb-1 block text-xs font-bold">
                                Nombre del Receptor / Encargado en Sitio{' '}
                                <span className="text-red-500">*</span>
                            </label>
                            <input
                                aria-label="Conformidad nombre"
                                type="text"
                                required
                                value={form.data.conformidad_nombre}
                                onChange={(e) =>
                                    form.setData(
                                        'conformidad_nombre',
                                        e.target.value,
                                    )
                                }
                                placeholder="Nombre completo de quien recibe la instalación"
                                className="border-border bg-card w-full rounded-lg border p-2 text-xs"
                            />
                            {form.errors.conformidad_nombre && (
                                <p className="mt-0.5 text-xs text-red-500">
                                    {form.errors.conformidad_nombre}
                                </p>
                            )}
                        </div>

                        <SubirEvidencia
                            ordenId={order.id}
                            etapa="antes"
                            titulo="Fotos antes"
                            evidencias={evidencias}
                            equipos={conversacion.equipos}
                        />
                        <SubirEvidencia
                            ordenId={order.id}
                            etapa="despues"
                            titulo="Fotos después"
                            evidencias={evidencias}
                            equipos={conversacion.equipos}
                        />

                        <FirmaCanvas
                            onChange={(f) => form.setData('firma', f)}
                        />
                        {form.errors.firma && (
                            <p className="text-xs text-red-500" role="alert">
                                {form.errors.firma}
                            </p>
                        )}

                        <div>
                            <label className="text-foreground mb-1 block text-xs font-bold">
                                Observaciones Adicionales
                            </label>
                            <textarea
                                aria-label="Observaciones Adicionales"
                                rows={2}
                                value={form.data.observaciones}
                                onChange={(e) =>
                                    form.setData(
                                        'observaciones',
                                        e.target.value,
                                    )
                                }
                                placeholder="Detalles de señalética instalada, tarjetas de control entregadas..."
                                className="border-border bg-card w-full rounded-lg border p-2 text-xs"
                            />
                        </div>

                        <div className="border-border bg-muted rounded-lg border p-3">
                            <label className="flex cursor-pointer items-start gap-2.5">
                                <input
                                    type="checkbox"
                                    required
                                    checked={form.data.conformidad_aceptada}
                                    onChange={(e) =>
                                        form.setData(
                                            'conformidad_aceptada',
                                            e.target.checked,
                                        )
                                    }
                                    className="border-border text-foreground mt-0.5 size-4 rounded"
                                />
                                <span className="text-foreground text-xs leading-tight font-semibold">
                                    Conformidad en sitio: El cliente valida la
                                    instalación física de los extintores y los
                                    soportes de montaje en las ubicaciones
                                    acordadas.
                                </span>
                            </label>
                            {form.errors.conformidad_aceptada && (
                                <p className="mt-1 text-xs text-red-500">
                                    {form.errors.conformidad_aceptada}
                                </p>
                            )}
                        </div>

                        <button
                            type="submit"
                            disabled={
                                form.processing ||
                                !form.data.firma ||
                                faltantes.length > 0
                            }
                            className="bg-primary hover:bg-primary/90 flex min-h-[48px] w-full items-center justify-center gap-2 rounded-lg text-xs font-bold text-white shadow-sm transition-all disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <Send className="size-4" />
                            <span>Registrar y Guardar Instalación Técnica</span>
                        </button>
                        {!form.data.conformidad_aceptada ? (
                            <p
                                className="text-center text-xs font-semibold text-red-500"
                                role="alert"
                            >
                                Marca la conformidad del cliente antes de
                                finalizar la instalación.
                            </p>
                        ) : null}
                    </div>
                )}
            </form>

            <div className="mt-5">
                <ConversacionOrden
                    ordenId={order.id}
                    conversacion={conversacion}
                />
            </div>
        </TecnicoCampoLayout>
    );
}
