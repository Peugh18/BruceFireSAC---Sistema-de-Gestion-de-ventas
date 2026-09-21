import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Check,
    CheckCircle2,
    Clock,
    FileText,
    ShieldAlert,
    Wrench,
    X,
} from 'lucide-react';
import { FormEvent, useState } from 'react';

import DeficiencyAuthorizationController from '@/actions/App/Http/Controllers/Vendedor/DeficiencyAuthorizationController';
import DeficiencyController from '@/actions/App/Http/Controllers/Vendedor/DeficiencyController';
import ServiceOrderController from '@/actions/App/Http/Controllers/Vendedor/ServiceOrderController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import VendedorLayout from '@/layouts/vendedor-layout';
import type { Team } from '@/types';

export type DeficiencyItem = {
    id: number;
    service_order_id: number;
    orden: string;
    cliente: string;
    componente: string;
    condicion: string;
    estado:
        | 'detectada'
        | 'esperando_autorizacion'
        | 'autorizada'
        | 'rechazada'
        | 'en_correccion'
        | 'resuelta'
        | string;
    requiere_autorizacion: boolean;
    authorization?: {
        id: number;
        autorizado_por: string;
        canal: string;
        fecha: string;
        observacion?: string;
    } | null;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Props = {
    deficiencies: {
        data: DeficiencyItem[];
        links: PaginationLink[];
        from?: number | null;
        to?: number | null;
        total: number;
    };
    filters: {
        estado?: string;
    };
};

const TABS = [
    { id: '', label: 'Todas' },
    { id: 'detectada', label: 'Detectadas' },
    { id: 'esperando_autorizacion', label: 'Esperando autorización' },
    { id: 'autorizada', label: 'Autorizadas' },
    { id: 'rechazada', label: 'Rechazadas' },
    { id: 'resuelta', label: 'Resueltas' },
] as const;

function getStatusStyle(estado: string): { borderClass: string; badgeClass: string; label: string } {
    switch (estado) {
        case 'detectada':
            return {
                borderClass: 'border-l-[5px] border-l-[#8A8680]',
                badgeClass: 'bg-[#F1EFEC] text-[#6B6862]',
                label: 'Detectada',
            };
        case 'esperando_autorizacion':
            return {
                borderClass: 'border-l-[5px] border-l-[#B45309]',
                badgeClass: 'bg-[#FDF1E0] text-[#B45309]',
                label: 'Esperando autorización',
            };
        case 'autorizada':
            return {
                borderClass: 'border-l-[5px] border-l-[#1E8E5A]',
                badgeClass: 'bg-[#E5F5EC] text-[#1E8E5A]',
                label: 'Autorizada',
            };
        case 'rechazada':
            return {
                borderClass: 'border-l-[5px] border-l-[#B91C1C] opacity-80',
                badgeClass: 'bg-[#FBE7E7] text-[#B91C1C]',
                label: 'Rechazada',
            };
        case 'en_correccion':
            return {
                borderClass: 'border-l-[5px] border-l-[#2563EB]',
                badgeClass: 'bg-[#E9EFFD] text-[#2563EB]',
                label: 'En corrección',
            };
        case 'resuelta':
            return {
                borderClass: 'border-l-[5px] border-l-[#1E8E5A] opacity-75',
                badgeClass: 'bg-[#E5F5EC] text-[#1E8E5A]',
                label: 'Resuelta',
            };
        default:
            return {
                borderClass: 'border-l-[5px] border-l-[#E4E1DC]',
                badgeClass: 'bg-[#F1EFEC] text-[#6B6862]',
                label: estado || 'Sin estado',
            };
    }
}

export default function DeficienciasIndex({ deficiencies, filters }: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug =
        currentTeam?.slug ||
        (typeof window !== 'undefined'
            ? window.location.pathname.split('/')[1]
            : '');

    const currentEstado = filters?.estado || '';

    // Estado del diálogo de autorización/rechazo
    const [actionModal, setActionModal] = useState<{
        item: DeficiencyItem;
        isApproving: boolean;
    } | null>(null);

    const authForm = useForm({
        autorizado: true,
        autorizado_por: '',
        canal: 'whatsapp',
        fecha: new Date().toISOString().split('T')[0],
        observacion: '',
    });

    const openActionDialog = (item: DeficiencyItem, isApproving: boolean) => {
        setActionModal({ item, isApproving });
        authForm.setData({
            autorizado: isApproving,
            autorizado_por: '',
            canal: 'whatsapp',
            fecha: new Date().toISOString().split('T')[0],
            observacion: '',
        });
    };

    const submitAuthorization = (e: FormEvent) => {
        e.preventDefault();
        if (!actionModal) return;

        authForm.post(
            DeficiencyAuthorizationController.store.url({
                current_team: teamSlug,
                deficiency: actionModal.item.id,
            }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    setActionModal(null);
                    authForm.reset();
                },
            },
        );
    };

    const handleFilterChange = (tabId: string) => {
        router.get(
            DeficiencyController.index.url(teamSlug, {
                query: {
                    estado: tabId || undefined,
                },
            }),
            {},
            {
                preserveScroll: true,
                preserveState: true,
            },
        );
    };

    return (
        <VendedorLayout title="Deficiencias y Adicionales">
            <Head title="Deficiencias y Adicionales" />

            <div className="flex flex-col gap-4">
                {/* Header title */}
                <div>
                    <h2 className="font-['Oswald',sans-serif] text-[22px] font-semibold text-[#201F1D]">
                        Deficiencias y Adicionales
                    </h2>
                    <p className="mt-0.5 text-[12.5px] text-[#8A8680]">
                        Hallazgos que los técnicos reportaron en equipos de tus clientes. Si implica costo adicional, requiere autorización del cliente antes de proceder.
                    </p>
                </div>

                {/* Filter Tabs */}
                <div className="flex flex-wrap rounded-[9px] bg-[#F1EFEC] p-[3px] w-fit">
                    {TABS.map((tab) => {
                        const active = currentEstado === tab.id;
                        return (
                            <button
                                key={tab.id}
                                type="button"
                                onClick={() => handleFilterChange(tab.id)}
                                className={`cursor-pointer rounded-[7px] px-3.5 py-1.5 text-xs font-bold transition-all ${
                                    active
                                        ? 'bg-white text-[#201F1D] shadow-[0_1px_2px_rgba(0,0,0,0.06)]'
                                        : 'text-[#6B6862] hover:text-[#201F1D]'
                                }`}
                            >
                                {tab.label}
                            </button>
                        );
                    })}
                </div>

                {/* List of Deficiency Cards */}
                <div className="flex flex-col gap-3">
                    {deficiencies.data.length === 0 ? (
                        <Card className="flex flex-col items-center justify-center gap-2 rounded-[16px] border-[#E7E4DE] bg-white p-12 text-center shadow-none">
                            <CheckCircle2 className="size-10 text-[#1E8E5A]" />
                            <p className="text-sm font-bold text-[#201F1D]">
                                No se encontraron deficiencias
                            </p>
                            <p className="text-xs text-[#8A8680]">
                                {currentEstado
                                    ? 'No hay registros bajo el estado seleccionado.'
                                    : 'No hay deficiencias ni adicionales reportados por los técnicos.'}
                            </p>
                        </Card>
                    ) : (
                        deficiencies.data.map((item) => {
                            const statusStyle = getStatusStyle(item.estado);
                            const isPending = item.estado === 'esperando_autorizacion';

                            return (
                                <div
                                    key={item.id}
                                    className={`flex flex-col gap-3 rounded-[14px] border border-[#E7E4DE] bg-white p-4.5 shadow-none transition-all sm:flex-row sm:items-start sm:gap-4 ${statusStyle.borderClass}`}
                                >
                                    {/* Icon Box */}
                                    <div className="flex size-11 shrink-0 items-center justify-center rounded-[10px] bg-[#F1EFEC] text-[#8A8680]">
                                        <Wrench className="size-5" />
                                    </div>

                                    {/* Content */}
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="text-[13.5px] font-bold text-[#201F1D]">
                                                {item.cliente}
                                            </span>
                                            <Badge
                                                className={`rounded-full border-none px-2.5 py-0.5 text-[10.5px] font-bold ${statusStyle.badgeClass}`}
                                            >
                                                {statusStyle.label}
                                            </Badge>
                                            <Badge className="border-none bg-[#F1EFEC] text-[#4A4742] text-[10.5px]">
                                                {item.componente}
                                            </Badge>
                                        </div>

                                        <div className="mt-1 text-[12.5px] text-[#3A3833]">
                                            {item.condicion}
                                        </div>

                                        <div className="mt-2 flex flex-wrap items-center gap-3 text-[11.5px] text-[#8A8680]">
                                            <span>
                                                Orden de Servicio:{' '}
                                                <Link
                                                    href={ServiceOrderController.show.url({
                                                        current_team: teamSlug,
                                                        service_order: item.service_order_id,
                                                    })}
                                                    className="font-mono font-bold text-[#201F1D] underline hover:text-[#E31E24]"
                                                >
                                                    {item.orden}
                                                </Link>
                                            </span>

                                            {item.authorization && (
                                                <span className="text-[#1E8E5A]">
                                                    &bull; Autorizado por <b>{item.authorization.autorizado_por}</b> ({item.authorization.canal}) el {item.authorization.fecha}
                                                </span>
                                            )}
                                        </div>
                                    </div>

                                    {/* Actions */}
                                    <div className="flex flex-wrap items-center gap-2 sm:self-center">
                                        {isPending ? (
                                            <>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => openActionDialog(item, false)}
                                                    className="h-8 rounded-[8px] border-[#FBE7E7] bg-white text-xs font-bold text-[#B91C1C] hover:bg-[#FBE7E7]"
                                                >
                                                    <X className="mr-1 size-3.5 stroke-[2.5]" />
                                                    <span>Rechazar</span>
                                                </Button>

                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    onClick={() => openActionDialog(item, true)}
                                                    className="h-8 rounded-[8px] bg-[#1E8E5A] text-xs font-bold text-white shadow-none hover:bg-[#18754A]"
                                                >
                                                    <Check className="mr-1 size-3.5 stroke-[2.5]" />
                                                    <span>Aprobar</span>
                                                </Button>
                                            </>
                                        ) : (
                                            <Button
                                                asChild
                                                variant="outline"
                                                size="sm"
                                                className="h-8 rounded-[8px] border-[#E4E1DC] bg-white text-xs font-semibold text-[#4A4742]"
                                            >
                                                <Link
                                                    href={ServiceOrderController.show.url({
                                                        current_team: teamSlug,
                                                        service_order: item.service_order_id,
                                                    })}
                                                >
                                                    Ver orden
                                                </Link>
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            );
                        })
                    )}
                </div>

                {/* Pagination */}
                {deficiencies.links && deficiencies.links.length > 3 && (
                    <div className="flex flex-wrap items-center justify-between gap-2 pt-2 text-[11.5px] text-[#8A8680]">
                        <span>
                            Mostrando {deficiencies.from ?? 0}-{deficiencies.to ?? 0} de {deficiencies.total} registros
                        </span>
                        <div className="flex gap-1">
                            {deficiencies.links.map((link, idx) =>
                                link.url ? (
                                    <Link
                                        key={idx}
                                        href={link.url}
                                        preserveScroll
                                        className={`inline-flex h-7 min-w-[28px] items-center justify-center rounded-[6px] px-2 font-mono text-xs ${
                                            link.active
                                                ? 'bg-[#18181B] font-bold text-white'
                                                : 'border border-[#E4E1DC] bg-white text-[#4A4742] hover:bg-[#F3F1ED]'
                                        }`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ) : (
                                    <span
                                        key={idx}
                                        className="inline-flex h-7 min-w-[28px] items-center justify-center rounded-[6px] border border-transparent px-2 font-mono text-xs text-[#A8A49D] opacity-60"
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ),
                            )}
                        </div>
                    </div>
                )}

                {/* Flow note banner */}
                <div className="rounded-[12px] border border-dashed border-[#D9D5CE] bg-[#F8F7F4] p-3.5 text-xs text-[#6B6862]">
                    <b>Flujo de deficiencias:</b> Detectada (técnico reporta en Planta o Campo) &rarr; Si genera costo, pasa a <b>Esperando autorización</b> (tú avisas al cliente y obtienes su conformidad) &rarr; <b>Autorizada</b> (se procede con la reparación) o <b>Rechazada</b> &rarr; <b>Resuelta</b> al completarse el trabajo.
                </div>
            </div>

            {/* Dialog: Aprobar / Rechazar Autorización */}
            <Dialog
                open={actionModal !== null}
                onOpenChange={(open) => {
                    if (!open) setActionModal(null);
                }}
            >
                <DialogContent className="rounded-[16px] border-[#E7E4DE] bg-white sm:max-w-md">
                    <DialogHeader>
                        <div className="flex items-center gap-2.5">
                            <div
                                className={`flex size-9 items-center justify-center rounded-[10px] ${
                                    actionModal?.isApproving
                                        ? 'bg-[#E5F5EC] text-[#1E8E5A]'
                                        : 'bg-[#FBE7E7] text-[#B91C1C]'
                                }`}
                            >
                                {actionModal?.isApproving ? (
                                    <Check className="size-5" />
                                ) : (
                                    <X className="size-5" />
                                )}
                            </div>
                            <div>
                                <DialogTitle className="font-['Oswald',sans-serif] text-[18px] font-semibold text-[#201F1D]">
                                    {actionModal?.isApproving
                                        ? 'Aprobar deficiencia adicional'
                                        : 'Rechazar deficiencia adicional'}
                                </DialogTitle>
                                <DialogDescription className="text-xs text-[#8A8680]">
                                    {actionModal?.item.cliente} · Orden {actionModal?.item.orden}
                                </DialogDescription>
                            </div>
                        </div>
                    </DialogHeader>

                    <form onSubmit={submitAuthorization} className="mt-3 space-y-3.5">
                        <div>
                            <Label className="text-[11px] font-bold text-[#4A4742] uppercase">
                                {actionModal?.isApproving
                                    ? 'Persona que autoriza en cliente *'
                                    : 'Persona que comunica el rechazo *'}
                            </Label>
                            <Input
                                required
                                value={authForm.data.autorizado_por}
                                onChange={(e) => authForm.setData('autorizado_por', e.target.value)}
                                placeholder="Nombre y cargo de contacto del cliente"
                                className="mt-1 h-9 rounded-[8px] border-[#E4E1DC] bg-white text-[13px]"
                            />
                            {authForm.errors.autorizado_por && (
                                <p className="mt-1 text-[11px] text-[#B91C1C]">
                                    {authForm.errors.autorizado_por}
                                </p>
                            )}
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <Label className="text-[11px] font-bold text-[#4A4742] uppercase">Canal *</Label>
                                <select
                                    value={authForm.data.canal}
                                    onChange={(e) => authForm.setData('canal', e.target.value as 'whatsapp' | 'presencial')}
                                    className="mt-1 h-9 w-full rounded-[8px] border border-[#E4E1DC] bg-white px-3 text-[13px] text-[#201F1D] outline-none"
                                >
                                    <option value="whatsapp">WhatsApp / Mensaje</option>
                                    <option value="presencial">Presencial / Llamada</option>
                                </select>
                            </div>

                            <div>
                                <Label className="text-[11px] font-bold text-[#4A4742] uppercase">Fecha *</Label>
                                <Input
                                    type="date"
                                    required
                                    value={authForm.data.fecha}
                                    onChange={(e) => authForm.setData('fecha', e.target.value)}
                                    className="mt-1 h-9 rounded-[8px] border-[#E4E1DC] bg-white text-[13px]"
                                />
                            </div>
                        </div>

                        <div>
                            <Label className="text-[11px] font-bold text-[#4A4742] uppercase">
                                Observaciones / Sustento
                            </Label>
                            <Input
                                value={authForm.data.observacion}
                                onChange={(e) => authForm.setData('observacion', e.target.value)}
                                placeholder="Ej. Aprobó cotización adicional enviada por correo..."
                                className="mt-1 h-9 rounded-[8px] border-[#E4E1DC] bg-white text-[13px]"
                            />
                        </div>

                        <DialogFooter className="pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setActionModal(null)}
                                className="rounded-[8px] border-[#E4E1DC] text-xs font-semibold"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={authForm.processing}
                                className={`rounded-[8px] text-xs font-bold text-white ${
                                    actionModal?.isApproving
                                        ? 'bg-[#1E8E5A] hover:bg-[#18754A]'
                                        : 'bg-[#B91C1C] hover:bg-[#991B1B]'
                                }`}
                            >
                                {actionModal?.isApproving ? 'Confirmar aprobación' : 'Confirmar rechazo'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </VendedorLayout>
    );
}
