import { Link, useForm } from '@inertiajs/react';
import { Check, Wrench, X } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { toast } from 'sonner';

import DeficiencyAuthorizationController from '@/actions/App/Http/Controllers/Vendedor/DeficiencyAuthorizationController';
import DeficiencyController from '@/actions/App/Http/Controllers/Vendedor/DeficiencyController';
import ServiceOrderController from '@/actions/App/Http/Controllers/Vendedor/ServiceOrderController';
import CatalogPicker, { type CatalogItem } from '@/components/catalog-picker';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { fechaLocal } from '@/lib/utils';
import { NativeSelect } from '@/components/ui/native-select';

export type DeficiencyItem = {
    id: number;
    service_order_id: number;
    orden: string;
    cliente: string;
    componente: string;
    condicion: string;
    nota?: string | null;
    accion_recomendada?: string | null;
    repuesto_sugerido?: string | null;
    equipo?: string | null;
    foto_url?: string | null;
    estado: string;
    pendiente_autorizacion?: boolean;
    requiere_autorizacion: boolean;
    authorization?: {
        autorizado_por: string;
        canal: string;
        fecha: string;
        observacion?: string | null;
        importe?: number | null;
    } | null;
    cotizacion_linea?: {
        id: number;
        numero: string;
        nombre: string;
        cantidad: number;
        importe: number;
    } | null;
    orden_cobrada?: boolean;
    cotizaciones?: { id: number; numero: string; total: number }[];
};

function getStatusStyle(estado: string): {
    borderClass: string;
    badgeClass: string;
    label: string;
} {
    switch (estado) {
        case 'detectada':
            return {
                borderClass: 'border-l-[5px] border-l-muted-foreground',
                badgeClass: 'bg-muted text-muted-foreground',
                label: 'Detectada',
            };
        case 'esperando_autorizacion':
            return {
                borderClass: 'border-l-[5px] border-l-warning-strong',
                badgeClass:
                    'bg-warning-strong/10 text-warning-strong border border-warning-strong/20',
                label: 'Esperando autorización',
            };
        case 'autorizada':
            return {
                borderClass: 'border-l-[5px] border-l-success-strong',
                badgeClass:
                    'bg-success-strong/10 text-success-strong border border-success-strong/20',
                label: 'Autorizada',
            };
        case 'rechazada':
            return {
                borderClass: 'border-l-[5px] border-l-destructive opacity-80',
                badgeClass:
                    'bg-destructive/10 text-destructive-strong border border-destructive/20',
                label: 'Rechazada',
            };
        case 'en_correccion':
            return {
                borderClass: 'border-l-[5px] border-l-primary',
                badgeClass:
                    'bg-primary/5 text-primary-strong border border-primary/20',
                label: 'En corrección',
            };
        case 'resuelta':
            return {
                borderClass:
                    'border-l-[5px] border-l-success-strong opacity-75',
                badgeClass:
                    'bg-success-strong/10 text-success-strong border border-success-strong/20',
                label: 'Resuelta',
            };
        default:
            return {
                borderClass: 'border-l-[5px] border-l-border',
                badgeClass: 'bg-muted text-muted-foreground',
                label: estado || 'Sin estado',
            };
    }
}

/**
 * Cotizar el adicional, aprobar o rechazar un hallazgo. Lo comparten la lista
 * de deficiencias y la ficha de la orden, para decidir en el mismo sitio.
 */
export function useAccionesDeficiencia(teamSlug: string) {
    // Estado del diálogo de autorización/rechazo
    const [actionModal, setActionModal] = useState<{
        item: DeficiencyItem;
        isApproving: boolean;
    } | null>(null);

    const [cotizarItem, setCotizarItem] = useState<DeficiencyItem | null>(null);
    const [seleccion, setSeleccion] = useState<CatalogItem | null>(null);
    const cotizarForm = useForm({
        product_id: '' as number | '',
        service_id: '' as number | '',
        cantidad: 1,
        precio_unitario: '' as number | '',
    });
    const abrirCotizacion = (item: DeficiencyItem) => {
        setCotizarItem(item);
        setSeleccion(null);
        cotizarForm.reset();
    };

    const authForm = useForm({
        autorizado: true,
        autorizado_por: '',
        canal: 'whatsapp',
        fecha: fechaLocal(),
        observacion: '',
        cotizacion_adicional_id: '' as number | '',
        importe: '',
    });

    const openActionDialog = (item: DeficiencyItem, isApproving: boolean) => {
        setActionModal({ item, isApproving });
        authForm.setData({
            autorizado: isApproving,
            autorizado_por: '',
            canal: 'whatsapp',
            fecha: fechaLocal(),
            observacion: '',
            cotizacion_adicional_id: item.cotizacion_linea?.id ?? '',
            importe: item.cotizacion_linea
                ? String(item.cotizacion_linea.importe)
                : '',
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
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ??
                            'No se pudo completar la acción.',
                    ),
                preserveScroll: true,
                onSuccess: () => {
                    setActionModal(null);
                    authForm.reset();
                },
            },
        );
    };

    const dialogos = (
        <>
            {/* Dialog: Aprobar / Rechazar Autorización */}
            <Dialog
                open={actionModal !== null}
                onOpenChange={(open) => {
                    if (!open) setActionModal(null);
                }}
            >
                <DialogContent className="border-border bg-card rounded-[16px] sm:max-w-md">
                    <DialogHeader>
                        <div className="flex items-center gap-2.5">
                            <div
                                className={`flex size-9 items-center justify-center rounded-[10px] ${
                                    actionModal?.isApproving
                                        ? 'text-success-strong border-success-strong/20 bg-success-strong/10 border'
                                        : 'bg-destructive/10 text-destructive-strong border-destructive/20 border'
                                }`}
                            >
                                {actionModal?.isApproving ? (
                                    <Check className="size-5" />
                                ) : (
                                    <X className="size-5" />
                                )}
                            </div>
                            <div>
                                <DialogTitle className="text-foreground font-['Oswald',sans-serif] text-[18px] font-semibold">
                                    {actionModal?.isApproving
                                        ? 'Aprobar deficiencia adicional'
                                        : 'Rechazar deficiencia adicional'}
                                </DialogTitle>
                                <DialogDescription className="text-muted-foreground text-xs">
                                    {actionModal?.item.cliente} · Orden{' '}
                                    {actionModal?.item.orden}
                                </DialogDescription>
                            </div>
                        </div>
                    </DialogHeader>

                    <form
                        onSubmit={submitAuthorization}
                        className="mt-3 space-y-3.5"
                    >
                        <div>
                            <Label className="text-foreground text-sm font-medium">
                                {actionModal?.isApproving
                                    ? 'Persona que autoriza en cliente *'
                                    : 'Persona que comunica el rechazo *'}
                            </Label>
                            <Input
                                aria-label="Autorizado por"
                                required
                                value={authForm.data.autorizado_por}
                                onChange={(e) =>
                                    authForm.setData(
                                        'autorizado_por',
                                        e.target.value,
                                    )
                                }
                                placeholder="Nombre y cargo de contacto del cliente"
                                className="border-border bg-card mt-1 h-9 rounded-[8px] text-[13px]"
                            />
                            {authForm.errors.autorizado_por && (
                                <p className="text-destructive-strong mt-1 text-[11px]">
                                    {authForm.errors.autorizado_por}
                                </p>
                            )}
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <Label className="text-foreground text-sm font-medium">
                                    Canal *
                                </Label>
                                <NativeSelect
                                    aria-label="Canal *"
                                    value={authForm.data.canal}
                                    onChange={(e) =>
                                        authForm.setData(
                                            'canal',
                                            e.target.value as
                                                | 'whatsapp'
                                                | 'presencial',
                                        )
                                    }
                                    className="border-border bg-card text-foreground focus-visible:border-ring focus-visible:ring-ring/50 mt-1 h-9 w-full rounded-[8px] border px-3 text-[13px] outline-none focus-visible:ring-[3px]"
                                >
                                    <option value="whatsapp">
                                        WhatsApp / Mensaje
                                    </option>
                                    <option value="presencial">
                                        Presencial / Llamada
                                    </option>
                                </NativeSelect>
                            </div>

                            <div>
                                <Label className="text-foreground text-sm font-medium">
                                    Fecha *
                                </Label>
                                <Input
                                    aria-label="Fecha"
                                    type="date"
                                    required
                                    value={authForm.data.fecha}
                                    onChange={(e) =>
                                        authForm.setData(
                                            'fecha',
                                            e.target.value,
                                        )
                                    }
                                    className="border-border bg-card mt-1 h-9 rounded-[8px] text-[13px]"
                                />
                            </div>
                        </div>

                        {actionModal?.isApproving && (
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <Label
                                        htmlFor="cotizacion_adicional_id"
                                        className="text-foreground text-sm font-medium"
                                    >
                                        Cotización del adicional
                                    </Label>
                                    <NativeSelect
                                        id="cotizacion_adicional_id"
                                        value={
                                            authForm.data
                                                .cotizacion_adicional_id
                                        }
                                        onChange={(e) =>
                                            authForm.setData(
                                                'cotizacion_adicional_id',
                                                e.target.value
                                                    ? Number(e.target.value)
                                                    : '',
                                            )
                                        }
                                        className="border-border bg-card text-foreground focus-visible:border-ring focus-visible:ring-ring/50 mt-1 h-9 w-full rounded-[8px] border px-3 text-[13px] outline-none focus-visible:ring-[3px]"
                                    >
                                        <option value="">Sin cotización</option>
                                        {(
                                            actionModal.item.cotizaciones ?? []
                                        ).map((cotizacion) => (
                                            <option
                                                key={cotizacion.id}
                                                value={cotizacion.id}
                                            >
                                                {cotizacion.numero} · S/{' '}
                                                {cotizacion.total.toFixed(2)}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </div>
                                <div>
                                    <Label
                                        htmlFor="importe"
                                        className="text-foreground text-sm font-medium"
                                    >
                                        Importe aceptado (S/)
                                        {actionModal.item.orden_cobrada && ' *'}
                                    </Label>
                                    <Input
                                        id="importe"
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        value={authForm.data.importe}
                                        onChange={(e) =>
                                            authForm.setData(
                                                'importe',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Total de la cotización"
                                        className="border-border bg-card mt-1 h-9 rounded-[8px] text-[13px]"
                                    />
                                    {authForm.errors.importe && (
                                        <p className="text-destructive-strong mt-1 text-[11px] font-semibold">
                                            {authForm.errors.importe}
                                        </p>
                                    )}
                                </div>
                                {actionModal.item.orden_cobrada && (
                                    <p className="text-muted-foreground text-[11px] sm:col-span-2">
                                        El adicional se cotiza y se convierte a
                                        una venta aparte, vinculada a la orden.
                                    </p>
                                )}
                            </div>
                        )}

                        <div>
                            <Label className="text-foreground text-sm font-medium">
                                Observaciones / Sustento
                            </Label>
                            <Input
                                aria-label="Observación"
                                value={authForm.data.observacion}
                                onChange={(e) =>
                                    authForm.setData(
                                        'observacion',
                                        e.target.value,
                                    )
                                }
                                placeholder="Ej. Aprobó cotización adicional enviada por correo..."
                                className="border-border bg-card mt-1 h-9 rounded-[8px] text-[13px]"
                            />
                        </div>

                        <DialogFooter className="pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setActionModal(null)}
                                className="border-border rounded-[8px] text-xs font-semibold"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                loading={authForm.processing}
                                className={`rounded-[8px] text-xs font-bold text-white ${
                                    actionModal?.isApproving
                                        ? 'bg-emerald-600 hover:bg-emerald-700'
                                        : 'bg-destructive hover:bg-destructive/90'
                                }`}
                            >
                                {actionModal?.isApproving
                                    ? 'Confirmar aprobación'
                                    : 'Confirmar rechazo'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
            <Dialog
                open={cotizarItem !== null}
                onOpenChange={(open) => {
                    if (!open) setCotizarItem(null);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Cotizar adicional</DialogTitle>
                        <DialogDescription>
                            {cotizarItem?.componente}: el precio incluye IGV y
                            puede corregirse antes de confirmar con el cliente.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            if (!cotizarItem || !seleccion) return;
                            cotizarForm.post(
                                DeficiencyController.cotizar.url({
                                    current_team: teamSlug,
                                    deficiency: cotizarItem.id,
                                }),
                                {
                                    preserveScroll: true,
                                    onSuccess: () => setCotizarItem(null),
                                    onError: (errors) =>
                                        toast.error(
                                            Object.values(errors)[0] ??
                                                'Revisa los datos.',
                                        ),
                                },
                            );
                        }}
                        className="space-y-4"
                    >
                        <CatalogPicker
                            teamSlug={teamSlug}
                            onPick={(item) => {
                                setSeleccion(item);
                                cotizarForm.setData({
                                    product_id:
                                        item.tipo === 'product' ? item.id : '',
                                    service_id:
                                        item.tipo === 'service' ? item.id : '',
                                    cantidad: 1,
                                    precio_unitario: item.precio_venta,
                                });
                            }}
                        />
                        {seleccion && (
                            <p className="text-sm">{seleccion.nombre}</p>
                        )}
                        <div>
                            <Label htmlFor="adicional-cantidad">Cantidad</Label>
                            <Input
                                id="adicional-cantidad"
                                type="number"
                                min="1"
                                required
                                value={cotizarForm.data.cantidad}
                                onChange={(event) =>
                                    cotizarForm.setData(
                                        'cantidad',
                                        Number(event.target.value),
                                    )
                                }
                            />
                        </div>
                        <div>
                            <Label htmlFor="adicional-precio">
                                Precio unitario con IGV (S/)
                            </Label>
                            <Input
                                id="adicional-precio"
                                type="number"
                                min="0.01"
                                step="0.01"
                                required
                                value={cotizarForm.data.precio_unitario}
                                onChange={(event) =>
                                    cotizarForm.setData(
                                        'precio_unitario',
                                        Number(event.target.value),
                                    )
                                }
                            />
                        </div>
                        <DialogFooter>
                            <Button
                                type="submit"
                                disabled={!seleccion}
                                loading={cotizarForm.processing}
                            >
                                Guardar en cotización
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );

    return {
        abrirCotizacion,
        abrirDecision: openActionDialog,
        dialogos,
    };
}

export type AccionesDeficiencia = ReturnType<typeof useAccionesDeficiencia>;

export function DeficienciaCard({
    item,
    teamSlug,
    acciones,
    enOrden = false,
}: {
    item: DeficiencyItem;
    teamSlug: string;
    acciones: AccionesDeficiencia;
    /** En la ficha de la orden no hace falta enlazar a la misma orden. */
    enOrden?: boolean;
}) {
    const statusStyle = getStatusStyle(item.estado);
    const isPending =
        item.estado === 'esperando_autorizacion' ||
        item.pendiente_autorizacion === true;

    return (
        <div
            key={item.id}
            className={`border-border bg-card flex flex-col gap-3 rounded-[14px] border p-4.5 shadow-none transition-all sm:flex-row sm:items-start sm:gap-4 ${statusStyle.borderClass}`}
        >
            {/* Icon Box */}
            <div className="bg-muted text-muted-foreground flex size-11 shrink-0 items-center justify-center rounded-[10px]">
                <Wrench className="size-5" />
            </div>

            {/* Content */}
            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                    <span className="text-foreground text-[13.5px] font-bold">
                        {item.cliente}
                    </span>
                    <Badge
                        className={`rounded-full border-none px-2.5 py-0.5 text-[10.5px] font-bold ${statusStyle.badgeClass}`}
                    >
                        {statusStyle.label}
                    </Badge>
                    <Badge className="bg-muted text-foreground border-none text-[10.5px]">
                        {item.componente}
                    </Badge>
                </div>

                <div className="text-foreground/80 mt-1 text-[12.5px]">
                    {item.nota ?? item.condicion}
                    {item.equipo ? ` · ${item.equipo}` : ''}
                </div>
                {(item.accion_recomendada || item.repuesto_sugerido) && (
                    <div className="text-muted-foreground mt-1 text-[12px]">
                        {[
                            item.accion_recomendada &&
                                `Acción: ${item.accion_recomendada}`,
                            item.repuesto_sugerido &&
                                `Repuesto: ${item.repuesto_sugerido}`,
                        ]
                            .filter(Boolean)
                            .join(' · ')}
                    </div>
                )}
                {item.foto_url && (
                    <a
                        href={item.foto_url}
                        target="_blank"
                        rel="noopener"
                        className="mt-2 inline-block"
                    >
                        <img
                            src={item.foto_url}
                            alt={`Foto de la falla en ${item.componente}`}
                            className="border-border size-20 rounded-md border object-cover"
                        />
                    </a>
                )}

                <div className="text-muted-foreground mt-2 flex flex-wrap items-center gap-3 text-[11.5px]">
                    {!enOrden && (
                        <span>
                            Orden de Servicio:{' '}
                            <Link
                                href={ServiceOrderController.show.url({
                                    current_team: teamSlug,
                                    service_order: item.service_order_id,
                                })}
                                className="text-foreground hover:text-primary-strong font-mono font-bold underline"
                            >
                                {item.orden}
                            </Link>
                        </span>
                    )}

                    {item.authorization && (
                        <span className="text-success-strong">
                            &bull; Autorizado por{' '}
                            <b>{item.authorization.autorizado_por}</b> (
                            {item.authorization.canal}) el{' '}
                            {item.authorization.fecha}
                            {item.authorization.importe != null &&
                                ` · S/ ${item.authorization.importe.toFixed(2)}`}
                        </span>
                    )}
                </div>
            </div>

            {/* Actions */}
            <div className="flex flex-wrap items-center gap-2 sm:self-center">
                {['detectada', 'esperando_autorizacion', 'autorizada'].includes(
                    item.estado,
                ) && (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => acciones.abrirCotizacion(item)}
                    >
                        {item.cotizacion_linea
                            ? 'Corregir adicional'
                            : 'Cotizar adicional'}
                    </Button>
                )}
                {item.cotizacion_linea && (
                    <Link
                        href={`/${teamSlug}/vendedor/cotizaciones/${item.cotizacion_linea.id}/editar`}
                        className="text-primary-strong focus-visible:ring-ring text-xs underline focus-visible:ring-2"
                    >
                        {item.cotizacion_linea.numero} · S/{' '}
                        {item.cotizacion_linea.importe.toFixed(2)}
                    </Link>
                )}
                {isPending ? (
                    <>
                        <Button
                            aria-label="Rechazar deficiencia"
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => acciones.abrirDecision(item, false)}
                            className="border-destructive/20 bg-card text-destructive-strong hover:bg-destructive/10 h-8 rounded-[8px] text-xs font-bold"
                        >
                            <X className="mr-1 size-3.5 stroke-[2.5]" />
                            <span>Rechazar</span>
                        </Button>

                        <Button
                            aria-label="Aprobar deficiencia"
                            type="button"
                            size="sm"
                            onClick={() => acciones.abrirDecision(item, true)}
                            className="h-8 rounded-[8px] bg-emerald-600 text-xs font-bold text-white shadow-none hover:bg-emerald-700"
                        >
                            <Check className="mr-1 size-3.5 stroke-[2.5]" />
                            <span>Aprobar</span>
                        </Button>
                    </>
                ) : enOrden ? null : (
                    <Button
                        asChild
                        variant="outline"
                        size="sm"
                        className="border-border bg-card text-foreground/80 h-8 rounded-[8px] text-xs font-semibold"
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
}
