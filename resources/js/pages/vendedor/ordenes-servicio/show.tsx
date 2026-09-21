import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Building2,
    Calendar,
    CheckCircle2,
    Clock,
    FileText,
    MessageCircle,
    User,
    Wrench,
} from 'lucide-react';

import ServiceOrderController from '@/actions/App/Http/Controllers/Vendedor/ServiceOrderController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import VendedorLayout from '@/layouts/vendedor-layout';
import type { Team } from '@/types';

export type ServiceOrderEvent = {
    id: number;
    tipo: string;
    payload?: {
        mensaje?: string;
        resultado?: string;
        [key: string]: unknown;
    };
    created_at: string;
    user?: {
        name: string;
    };
};

export type ServiceOrderDetails = {
    id: number;
    codigo: string;
    tipo_servicio: string;
    fecha: string;
    prioridad: string;
    departamento_tecnico?: string | null;
    estado: string;
    observaciones?: string | null;
    created_at: string;
    client?: {
        id: number;
        razon_social: string;
        numero_documento?: string;
        telefono?: string;
        direccion_fiscal?: string;
    };
    tecnico?: {
        id: number;
        name: string;
        email?: string;
    } | null;
    events: ServiceOrderEvent[];
};

export type Props = {
    serviceOrder: ServiceOrderDetails;
};

const STEPS = [
    { key: 'asignada', label: '1. Asignada' },
    { key: 'en_proceso', label: '2. En proceso' },
    { key: 'completada', label: '3. Completada' },
    { key: 'cerrada', label: '4. Cerrada' },
];

function getCoarseStep(estado: string): number {
    const map: Record<string, number> = {
        pendiente_recepcion: 1,
        recibido_planta: 1,
        en_revision: 1,
        esperando_autorizacion: 1,
        autorizado: 2,
        en_proceso: 2,
        trabajo_terminado: 2,
        pendiente_datos: 2,
        datos_completos: 2,
        listo_certificado: 3,
        listo_entrega: 3,
        entregado: 3,
        cerrado: 4,
    };
    return map[estado] ?? 1;
}

export default function ServiceOrderShow({ serviceOrder }: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug =
        currentTeam?.slug ||
        (typeof window !== 'undefined'
            ? window.location.pathname.split('/')[1]
            : '');

    const currentStep = getCoarseStep(serviceOrder.estado);

    return (
        <VendedorLayout title={`Orden ${serviceOrder.codigo}`}>
            <Head title={`Orden ${serviceOrder.codigo}`} />

            <div className="flex flex-col gap-4">
                {/* Header Back & Info */}
                <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <div className="flex items-center gap-3">
                        <Button
                            asChild
                            variant="outline"
                            size="icon"
                            className="size-8 rounded-[8px] border-[#E7E4DE] bg-white text-[#4A4742] shadow-none"
                        >
                            <Link href={ServiceOrderController.index.url(teamSlug)}>
                                <ArrowLeft className="size-4" />
                            </Link>
                        </Button>
                        <div>
                            <div className="flex items-center gap-2">
                                <h2 className="font-['Oswald',sans-serif] text-[22px] font-semibold text-[#201F1D]">
                                    {serviceOrder.codigo}
                                </h2>
                                <Badge className="border-none bg-[#E9EFFD] text-[#2563EB]">
                                    {serviceOrder.estado.replace(/_/g, ' ')}
                                </Badge>
                            </div>
                            <p className="text-[12px] text-[#8A8680]">
                                Creada el {new Date(serviceOrder.created_at).toLocaleDateString('es-PE')}
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button
                            asChild
                            variant="outline"
                            className="rounded-[9px] border-[#E4E1DC] bg-white text-xs font-semibold text-[#4A4742]"
                        >
                            <Link href={`/${teamSlug}/vendedor/comunicacion`}>
                                <MessageCircle className="mr-1.5 size-3.5" />
                                <span>Ver en Comunicación</span>
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* 4-Step Progress Tracker Banner */}
                <Card className="rounded-[16px] border-[#E7E4DE] bg-white p-5 shadow-none">
                    <div className="mb-3 text-xs font-bold text-[#8A8680] uppercase">
                        Progreso de atención
                    </div>
                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        {STEPS.map((stepItem, idx) => {
                            const stepNum = idx + 1;
                            const isDone = stepNum < currentStep;
                            const isCurrent = stepNum === currentStep;

                            return (
                                <div
                                    key={stepItem.key}
                                    className={`flex items-center gap-2 rounded-[10px] p-3 text-xs font-bold transition-all ${
                                        isCurrent
                                            ? 'bg-[#E9EFFD] text-[#2563EB] ring-1 ring-blue-300'
                                            : isDone
                                              ? 'bg-[#E5F5EC] text-[#1E8E5A]'
                                              : 'bg-[#FAFAF8] text-[#A8A49D]'
                                    }`}
                                >
                                    <div
                                        className={`flex size-6 shrink-0 items-center justify-center rounded-full text-[11px] ${
                                            isCurrent
                                                ? 'bg-[#2563EB] text-white'
                                                : isDone
                                                  ? 'bg-[#1E8E5A] text-white'
                                                  : 'bg-[#E4E1DC] text-[#6B6862]'
                                        }`}
                                    >
                                        {isDone ? <CheckCircle2 className="size-3.5" /> : stepNum}
                                    </div>
                                    <span className="truncate">{stepItem.label}</span>
                                </div>
                            );
                        })}
                    </div>
                </Card>

                {/* 2-Column Info & Bitácora */}
                <div className="grid gap-4 lg:grid-cols-12">
                    {/* Left Column: Detalle del Servicio & Cliente (5 cols) */}
                    <div className="flex flex-col gap-4 lg:col-span-5">
                        <Card className="rounded-[16px] border-[#E7E4DE] bg-white p-5 shadow-none">
                            <div className="text-[13.5px] font-bold text-[#201F1D]">Detalles del servicio</div>
                            <div className="mt-3 divide-y divide-[#F1EFEC] text-[12.5px]">
                                <div className="py-2">
                                    <div className="text-[11px] text-[#8A8680]">Tipo de servicio</div>
                                    <div className="font-semibold text-[#201F1D]">{serviceOrder.tipo_servicio}</div>
                                </div>
                                <div className="py-2">
                                    <div className="text-[11px] text-[#8A8680]">Fecha programada</div>
                                    <div className="font-mono text-[#201F1D]">{serviceOrder.fecha}</div>
                                </div>
                                <div className="py-2">
                                    <div className="text-[11px] text-[#8A8680]">Departamento / Prioridad</div>
                                    <div className="capitalize text-[#201F1D]">
                                        {serviceOrder.departamento_tecnico || 'General'} · Prioridad {serviceOrder.prioridad}
                                    </div>
                                </div>
                                <div className="py-2">
                                    <div className="text-[11px] text-[#8A8680]">Técnico asignado</div>
                                    <div className="font-semibold text-[#201F1D]">
                                        {serviceOrder.tecnico?.name || 'Pendiente de asignación'}
                                    </div>
                                </div>
                                {serviceOrder.observaciones && (
                                    <div className="py-2">
                                        <div className="text-[11px] text-[#8A8680]">Observaciones</div>
                                        <div className="text-[#4A4742]">{serviceOrder.observaciones}</div>
                                    </div>
                                )}
                            </div>
                        </Card>

                        {serviceOrder.client && (
                            <Card className="rounded-[16px] border-[#E7E4DE] bg-white p-5 shadow-none">
                                <div className="text-[13.5px] font-bold text-[#201F1D]">Datos del cliente</div>
                                <div className="mt-3 space-y-1.5 text-[12.5px]">
                                    <div className="font-bold text-[#201F1D]">
                                        {serviceOrder.client.razon_social}
                                    </div>
                                    {serviceOrder.client.numero_documento && (
                                        <div className="font-mono text-xs text-[#8A8680]">
                                            RUC/DNI: {serviceOrder.client.numero_documento}
                                        </div>
                                    )}
                                    {serviceOrder.client.direccion_fiscal && (
                                        <div className="text-xs text-[#4A4742]">
                                            {serviceOrder.client.direccion_fiscal}
                                        </div>
                                    )}
                                    {serviceOrder.client.telefono && (
                                        <div className="text-xs text-[#8A8680]">
                                            Teléfono: {serviceOrder.client.telefono}
                                        </div>
                                    )}
                                </div>
                            </Card>
                        )}
                    </div>

                    {/* Right Column: Bitácora de Eventos (7 cols) */}
                    <div className="flex flex-col lg:col-span-7">
                        <Card className="rounded-[16px] border-[#E7E4DE] bg-white p-5 shadow-none">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <Clock className="size-4 text-[#8A8680]" />
                                    <span className="text-[13.5px] font-bold text-[#201F1D]">
                                        Bitácora y eventos de orden
                                    </span>
                                </div>
                                <span className="text-[11px] text-[#8A8680]">
                                    {serviceOrder.events?.length ?? 0} eventos
                                </span>
                            </div>

                            <div className="mt-4 space-y-4">
                                {serviceOrder.events && serviceOrder.events.length > 0 ? (
                                    serviceOrder.events.map((evt) => (
                                        <div key={evt.id} className="flex gap-3 text-[12.5px]">
                                            <div className="mt-1 flex size-6 shrink-0 items-center justify-center rounded-full bg-[#F1EFEC] text-[#8A8680]">
                                                <CheckCircle2 className="size-3.5" />
                                            </div>
                                            <div className="flex-1 rounded-[10px] border border-[#F1EFEC] bg-[#FAFAF8] p-3">
                                                <div className="flex items-center justify-between">
                                                    <span className="font-bold text-[#201F1D] capitalize">
                                                        {evt.tipo.replace(/_/g, ' ')}
                                                    </span>
                                                    <span className="font-mono text-[10.5px] text-[#8A8680]">
                                                        {new Date(evt.created_at).toLocaleString('es-PE', {
                                                            month: 'short',
                                                            day: 'numeric',
                                                            hour: '2-digit',
                                                            minute: '2-digit',
                                                        })}
                                                    </span>
                                                </div>
                                                <div className="mt-1 text-[#4A4742]">
                                                    {evt.payload?.mensaje ||
                                                        (evt.payload?.resultado
                                                            ? `Resultado: ${evt.payload.resultado}`
                                                            : 'Evento registrado en orden de servicio')}
                                                </div>
                                                {evt.user?.name && (
                                                    <div className="mt-1 text-[11px] text-[#8A8680]">
                                                        Registrado por: <b>{evt.user.name}</b>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    ))
                                ) : (
                                    <div className="py-8 text-center text-xs text-[#8A8680]">
                                        No se han registrado eventos adicionales en esta orden.
                                    </div>
                                )}
                            </div>
                        </Card>
                    </div>
                </div>
            </div>
        </VendedorLayout>
    );
}
