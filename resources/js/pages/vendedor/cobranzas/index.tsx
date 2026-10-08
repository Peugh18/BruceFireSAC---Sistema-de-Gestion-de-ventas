import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    CheckCircle2,
    CreditCard,
    Lock,
    Unlock,
    Wallet,
    XCircle,
} from 'lucide-react';
import { FormEvent, useState } from 'react';
import { toast } from 'sonner';

import AlertError from '@/components/alert-error';
import CashRegisterController from '@/actions/App/Http/Controllers/Vendedor/CashRegisterController';
import CollectionController from '@/actions/App/Http/Controllers/Vendedor/CollectionController';
import { Badge } from '@/components/ui/badge';
import { PageHeader } from '@/components/page-header';
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
import pagos from '@/routes/vendedor/cobranzas/pagos';
import type { Team } from '@/types';
import { fechaCorta, soles } from '@/lib/utils';

export type Sede = {
    id: number;
    nombre: string;
    tipo?: string;
};

export type TurnoActual = {
    id: number;
    sede?: string;
    sede_id?: number;
    fecha_apertura?: string;
    monto_apertura: number | string;
    estado: string;
    ventas_por_forma_pago?: Record<string, number>;
} | null;

export type CierreItem = {
    id: number;
    sede?: string;
    fecha_apertura?: string;
    fecha_cierre?: string;
    monto_apertura: number | string;
    monto_contado_cierre: number | string;
    monto_esperado_calculado: number | string;
    diferencia: number | string;
    observacion?: string;
    estado: string;
};

export type InstallmentItem = {
    id: number;
    sale_id: number;
    sale_numero: string;
    numero_cuota: number;
    monto: number | string;
    saldo: number | string;
    fecha_vencimiento: string;
    estado: string;
    dias_vencido: number;
    cliente: string;
    pagos: {
        id: number;
        monto: number | string;
        forma_pago: string;
        fecha: string;
        puede_anular: boolean;
    }[];
    anulados: {
        id: number;
        monto: number | string;
        forma_pago: string;
        motivo: string | null;
        por: string | null;
    }[];
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type PaginatedList<T> = {
    data: T[];
    links?: PaginationLink[];
    from?: number | null;
    to?: number | null;
    total?: number;
};

export type Props = {
    turno_actual?: TurnoActual;
    cierres?: PaginatedList<CierreItem>;
    sedes?: Sede[];
    installments?: PaginatedList<InstallmentItem>;
};

export default function CobranzasIndex({
    turno_actual = null,
    cierres,
    sedes = [],
    installments,
}: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug =
        currentTeam?.slug ||
        (typeof window !== 'undefined'
            ? window.location.pathname.split('/')[1]
            : '');

    // Dialog state
    const [openTurnoDialogOpen, setOpenTurnoDialogOpen] = useState(false);
    const [closeTurnoDialogOpen, setCloseTurnoDialogOpen] = useState(false);
    const [selectedInstallment, setSelectedInstallment] =
        useState<InstallmentItem | null>(null);
    const [selectedPayment, setSelectedPayment] = useState<{
        id: number;
        monto: number | string;
    } | null>(null);

    // Form: Abrir turno de caja
    const openForm = useForm({
        sede_id: sedes[0]?.id ? String(sedes[0].id) : '',
        monto_apertura: '0.00',
    });

    const submitOpenTurno = (e: FormEvent) => {
        e.preventDefault();
        openForm.post(CashRegisterController.open.url(teamSlug), {
            onError: (errors) =>
                toast.error(
                    Object.values(errors)[0] ??
                        'No se pudo completar la acción.',
                ),
            preserveScroll: true,
            onSuccess: () => {
                setOpenTurnoDialogOpen(false);
                openForm.reset();
            },
        });
    };

    // Form: Cerrar turno de caja (arqueo ciego)
    const closeForm = useForm({
        monto_contado_cierre: '',
        observacion: '',
    });

    const submitCloseTurno = (e: FormEvent) => {
        e.preventDefault();
        if (!turno_actual) return;
        closeForm.post(
            CashRegisterController.close.url({
                current_team: teamSlug,
                cash_register: turno_actual.id,
            }),
            {
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ??
                            'No se pudo completar la acción.',
                    ),
                preserveScroll: true,
                onSuccess: () => {
                    setCloseTurnoDialogOpen(false);
                    closeForm.reset();
                },
            },
        );
    };

    // Form: Registrar pago de cuota
    const paymentForm = useForm({
        forma_pago: 'efectivo',
        monto: '',
        numero_operacion: '',
    });

    const handleOpenPaymentDialog = (inst: InstallmentItem) => {
        setSelectedInstallment(inst);
        paymentForm.setData({
            forma_pago: 'efectivo',
            monto: String(inst.saldo),
            numero_operacion: '',
        });
    };

    const cancelForm = useForm({ motivo: '' });

    const submitCancelPayment = (event: FormEvent) => {
        event.preventDefault();
        if (!selectedPayment) return;

        cancelForm.delete(
            pagos.anular.url({
                current_team: teamSlug,
                payment: selectedPayment.id,
            }),
            {
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ??
                            'No se pudo completar la acción.',
                    ),
                preserveScroll: true,
                onSuccess: () => {
                    setSelectedPayment(null);
                    cancelForm.reset();
                },
            },
        );
    };

    const submitRegisterPayment = (e: FormEvent) => {
        e.preventDefault();
        if (!selectedInstallment) return;
        paymentForm.post(
            CollectionController.registerPayment.url({
                current_team: teamSlug,
                installment: selectedInstallment.id,
            }),
            {
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ??
                            'No se pudo completar la acción.',
                    ),
                preserveScroll: true,
                onSuccess: () => {
                    setSelectedInstallment(null);
                    paymentForm.reset();
                },
            },
        );
    };

    // Totales calculados de turno actual
    const montoApertura = turno_actual
        ? Number(turno_actual.monto_apertura)
        : 0;
    const ventasTransferencia =
        (turno_actual?.ventas_por_forma_pago?.transferencia ?? 0) +
        (turno_actual?.ventas_por_forma_pago?.deposito ?? 0);
    const ventasTarjetaYape =
        (turno_actual?.ventas_por_forma_pago?.tarjeta_yape ?? 0) +
        (turno_actual?.ventas_por_forma_pago?.yape ?? 0) +
        (turno_actual?.ventas_por_forma_pago?.plin ?? 0) +
        (turno_actual?.ventas_por_forma_pago?.pos ?? 0);

    const installmentRows = installments?.data ?? [];

    return (
        <VendedorLayout title="Caja y cobranzas">
            <Head title="Caja y cobranzas" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    title="Caja y cobranzas"
                    description="El arqueo de tu turno y las cuotas que tus clientes tienen por pagar."
                />
                {/* 2-Column Main Layout */}
                <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
                    {/* Columna Izquierda: Panel de Arqueo de Caja (5 cols) */}
                    <div className="flex flex-col gap-4 lg:col-span-5">
                        <Card className="border-border bg-card rounded-[16px] p-5 shadow-none">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <Wallet className="text-muted-foreground size-4" />
                                    <span className="text-foreground text-[13.5px] font-bold">
                                        Arqueo de caja
                                    </span>
                                </div>
                                {turno_actual?.sede && (
                                    <span className="text-muted-foreground text-[11px] font-semibold">
                                        {turno_actual.sede}
                                    </span>
                                )}
                            </div>

                            {turno_actual &&
                            turno_actual.estado === 'abierto' ? (
                                <div className="mt-4 flex flex-col">
                                    {/* Estado: Turno Abierto */}
                                    <div className="text-success-strong flex items-center gap-2.5 rounded-[12px] bg-emerald-500/10 p-3.5">
                                        <span className="relative flex size-2.5">
                                            <span className="absolute inline-flex size-full animate-ping rounded-full bg-emerald-600 opacity-75" />
                                            <span className="relative inline-flex size-2.5 rounded-full bg-emerald-600" />
                                        </span>
                                        <span className="text-[12.5px] font-bold">
                                            Turno abierto
                                        </span>
                                        <span className="text-muted-foreground ml-auto font-mono text-[11px]">
                                            {turno_actual.fecha_apertura
                                                ? new Date(
                                                      turno_actual.fecha_apertura,
                                                  ).toLocaleTimeString(
                                                      'es-PE',
                                                      {
                                                          hour: '2-digit',
                                                          minute: '2-digit',
                                                      },
                                                  )
                                                : 'Hoy'}
                                        </span>
                                    </div>

                                    {/* Desglose Monetario */}
                                    <div className="divide-border mt-4 divide-y text-[12.5px]">
                                        <div className="flex items-center justify-between py-2">
                                            <span className="text-muted-foreground">
                                                Fondo inicial (apertura)
                                            </span>
                                            <span className="text-foreground font-semibold">
                                                {soles(montoApertura)}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between py-2">
                                            <span className="text-muted-foreground">
                                                Transferencia / Depósito
                                            </span>
                                            <span className="text-foreground font-semibold">
                                                {soles(ventasTransferencia)}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between py-2">
                                            <span className="text-muted-foreground">
                                                Tarjeta / Yape / Plin
                                            </span>
                                            <span className="text-foreground font-semibold">
                                                {soles(ventasTarjetaYape)}
                                            </span>
                                        </div>
                                        {/* Arqueo ciego: el efectivo esperado
                                        se ve recién al cerrar, después de
                                        contar lo que hay en el cajón. */}
                                        <p className="text-muted-foreground pt-2.5 text-[11.5px]">
                                            El efectivo esperado se muestra al
                                            cerrar el turno, después de que
                                            cuentes el cajón.
                                        </p>
                                    </div>

                                    {/* Botón Cerrar Turno */}
                                    <Button
                                        type="button"
                                        onClick={() =>
                                            setCloseTurnoDialogOpen(true)
                                        }
                                        className="bg-primary hover:bg-primary/90 mt-5 h-10 w-full rounded-[10px] text-[13px] font-bold text-white shadow-none transition-colors"
                                    >
                                        <Lock className="mr-1.5 size-4" />
                                        <span>Cerrar turno (arqueo ciego)</span>
                                    </Button>
                                </div>
                            ) : (
                                <div className="border-border bg-muted/40 mt-4 flex flex-col items-center justify-center gap-3 rounded-[12px] border border-dashed p-6 text-center">
                                    <div className="bg-muted text-muted-foreground flex size-11 items-center justify-center rounded-full">
                                        <Unlock className="size-5" />
                                    </div>
                                    <div>
                                        <p className="text-foreground text-[13px] font-bold">
                                            No hay un turno de caja abierto
                                        </p>
                                        <p className="text-muted-foreground mt-1 text-[11.5px]">
                                            Debes abrir un turno para registrar
                                            cobros en efectivo y ventas del día.
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        onClick={() =>
                                            setOpenTurnoDialogOpen(true)
                                        }
                                        className="mt-1 rounded-[9px] bg-emerald-600 px-4 text-[13px] font-bold text-white shadow-none hover:bg-emerald-700"
                                    >
                                        <Unlock className="mr-1.5 size-3.5" />
                                        <span>Abrir turno de caja</span>
                                    </Button>
                                </div>
                            )}
                        </Card>

                        {/* Historial de Cierres Recientes (si hay data) */}
                        {cierres && cierres.data.length > 0 && (
                            <Card className="border-border bg-card rounded-[16px] p-5 shadow-none">
                                <div className="flex items-center justify-between">
                                    <span className="text-foreground text-[13px] font-bold">
                                        Cierres recientes
                                    </span>
                                    <span className="text-muted-foreground text-[11px]">
                                        {cierres.total ?? cierres.data.length}{' '}
                                        cierres
                                    </span>
                                </div>
                                <div className="divide-border mt-3 divide-y text-[12px]">
                                    {cierres.data.slice(0, 4).map((cierre) => {
                                        const dif = Number(cierre.diferencia);
                                        return (
                                            <div
                                                key={cierre.id}
                                                className="py-2.5 first:pt-1"
                                            >
                                                <div className="text-foreground flex items-center justify-between font-semibold">
                                                    <span>
                                                        {cierre.sede ||
                                                            'Sede principal'}
                                                    </span>
                                                    <span>
                                                        {soles(
                                                            cierre.monto_contado_cierre,
                                                        )}
                                                    </span>
                                                </div>
                                                <div className="text-muted-foreground mt-0.5 flex items-center justify-between text-[11px]">
                                                    <span>
                                                        {cierre.fecha_cierre
                                                            ? new Date(
                                                                  cierre.fecha_cierre,
                                                              ).toLocaleDateString(
                                                                  'es-PE',
                                                              )
                                                            : '-'}
                                                    </span>
                                                    <span
                                                        className={
                                                            dif === 0
                                                                ? 'text-success-strong'
                                                                : dif > 0
                                                                  ? 'font-bold text-blue-600 dark:text-blue-400'
                                                                  : 'text-destructive-strong font-bold'
                                                        }
                                                    >
                                                        {dif === 0
                                                            ? 'Exacto'
                                                            : dif > 0
                                                              ? `+${soles(dif)}`
                                                              : soles(dif)}
                                                    </span>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </Card>
                        )}
                    </div>

                    {/* Columna Derecha: Mis Cuentas por Cobrar (7 cols) */}
                    <div className="flex flex-col lg:col-span-7">
                        <Card className="border-border bg-card rounded-[16px] p-5 shadow-none">
                            <div className="flex items-center justify-between">
                                <div>
                                    <span className="text-foreground text-[13.5px] font-bold">
                                        Mis cuentas por cobrar
                                    </span>
                                    <p className="text-muted-foreground mt-0.5 text-[11.5px]">
                                        Cuotas pendientes o parciales de tus
                                        clientes
                                    </p>
                                </div>
                                <Badge className="text-warning-strong border border-none border-amber-500/20 bg-amber-500/10">
                                    {installmentRows.length} cuotas
                                </Badge>
                            </div>

                            <div className="mt-4 overflow-x-auto">
                                <table className="w-full border-collapse text-[12.5px]">
                                    <thead>
                                        <tr className="border-border border-b">
                                            <th className="text-muted-foreground px-2.5 py-2.5 text-left font-mono text-[9.5px] font-semibold tracking-[0.05em] uppercase">
                                                Cliente
                                            </th>
                                            <th className="text-muted-foreground px-2.5 py-2.5 text-left font-mono text-[9.5px] font-semibold tracking-[0.05em] uppercase">
                                                Cuota
                                            </th>
                                            <th className="text-muted-foreground px-2.5 py-2.5 text-left font-mono text-[9.5px] font-semibold tracking-[0.05em] uppercase">
                                                Monto
                                            </th>
                                            <th className="text-muted-foreground px-2.5 py-2.5 text-left font-mono text-[9.5px] font-semibold tracking-[0.05em] uppercase">
                                                Vencimiento
                                            </th>
                                            <th className="text-muted-foreground px-2.5 py-2.5 text-right font-mono text-[9.5px] font-semibold tracking-[0.05em] uppercase">
                                                Acción
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {installmentRows.length === 0 ? (
                                            <tr>
                                                <td
                                                    colSpan={5}
                                                    className="text-muted-foreground px-4 py-12 text-center"
                                                >
                                                    <div className="flex flex-col items-center justify-center gap-2">
                                                        <CheckCircle2 className="text-success-strong size-8" />
                                                        <p className="text-foreground text-xs font-semibold">
                                                            No tienes cuotas
                                                            pendientes de cobro
                                                        </p>
                                                        <p className="text-muted-foreground text-[11px]">
                                                            Todos los clientes
                                                            de tus ventas están
                                                            al día con sus
                                                            pagos.
                                                        </p>
                                                    </div>
                                                </td>
                                            </tr>
                                        ) : (
                                            installmentRows.map((inst) => {
                                                const isVencido =
                                                    inst.dias_vencido > 0;

                                                return (
                                                    <tr
                                                        key={inst.id}
                                                        className="border-border hover:bg-muted/40 border-b transition-colors"
                                                    >
                                                        <td className="px-2.5 py-3.5">
                                                            <div className="text-foreground font-bold">
                                                                {inst.cliente ||
                                                                    'Cliente'}
                                                            </div>
                                                            <div className="text-muted-foreground font-mono text-[10.5px]">
                                                                Venta:{' '}
                                                                {inst.sale_numero ||
                                                                    `#${inst.sale_id}`}
                                                            </div>
                                                        </td>

                                                        <td className="text-foreground/80 px-2.5 py-3.5 font-mono text-xs">
                                                            {inst.numero_cuota}
                                                        </td>

                                                        <td className="text-foreground px-2.5 py-3.5 font-bold">
                                                            <div>
                                                                {soles(
                                                                    inst.saldo,
                                                                )}
                                                            </div>
                                                            <div className="text-muted-foreground text-[10px] font-normal">
                                                                Saldo de{' '}
                                                                {soles(
                                                                    inst.monto,
                                                                )}
                                                            </div>
                                                            {inst.anulados.map(
                                                                (anulado) => (
                                                                    <div
                                                                        key={
                                                                            anulado.id
                                                                        }
                                                                        className="text-destructive-strong text-[10px] font-normal"
                                                                        title={
                                                                            anulado.motivo ??
                                                                            undefined
                                                                        }
                                                                    >
                                                                        <span className="line-through">
                                                                            {soles(
                                                                                anulado.monto,
                                                                            )}
                                                                        </span>{' '}
                                                                        cobro
                                                                        anulado
                                                                        {anulado.por
                                                                            ? ` por ${anulado.por}`
                                                                            : ''}
                                                                        {anulado.motivo
                                                                            ? `: ${anulado.motivo}`
                                                                            : ''}
                                                                    </div>
                                                                ),
                                                            )}
                                                        </td>

                                                        <td className="px-2.5 py-3.5">
                                                            <Badge
                                                                className={`rounded-full border-none px-2 py-0.5 text-[10.5px] font-bold ${
                                                                    isVencido
                                                                        ? 'bg-destructive/10 text-destructive-strong border-destructive/20 border'
                                                                        : 'text-warning-strong border border-amber-500/20 bg-amber-500/10'
                                                                }`}
                                                            >
                                                                {isVencido
                                                                    ? `Vencido hace ${inst.dias_vencido}d`
                                                                    : `Vence ${fechaCorta(inst.fecha_vencimiento)}`}
                                                            </Badge>
                                                        </td>

                                                        <td className="px-2.5 py-3.5 text-right">
                                                            {inst.pagos
                                                                .filter(
                                                                    (pago) =>
                                                                        pago.puede_anular,
                                                                )
                                                                .map((pago) => (
                                                                    <Button
                                                                        key={
                                                                            pago.id
                                                                        }
                                                                        type="button"
                                                                        size="sm"
                                                                        variant="outline"
                                                                        onClick={() =>
                                                                            setSelectedPayment(
                                                                                pago,
                                                                            )
                                                                        }
                                                                        className="text-destructive-strong mr-1 h-7 rounded-[7px] px-2 text-[11px]"
                                                                    >
                                                                        Anular
                                                                        cobro
                                                                    </Button>
                                                                ))}
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                onClick={() =>
                                                                    handleOpenPaymentDialog(
                                                                        inst,
                                                                    )
                                                                }
                                                                disabled={
                                                                    Number(
                                                                        inst.saldo,
                                                                    ) <= 0
                                                                }
                                                                className="bg-foreground text-background hover:bg-foreground/90 h-7 rounded-[7px] px-3 text-[11px] font-bold shadow-xs transition-colors"
                                                            >
                                                                Registrar pago
                                                            </Button>
                                                        </td>
                                                    </tr>
                                                );
                                            })
                                        )}
                                    </tbody>
                                </table>
                            </div>

                            {/* Paginación si existe */}
                            {installments?.links &&
                                installments.links.length > 3 && (
                                    <div className="border-border text-muted-foreground mt-4 flex flex-wrap items-center justify-between gap-2 border-t pt-3 text-[11.5px]">
                                        <span>
                                            Mostrando {installments.from ?? 0}-
                                            {installments.to ?? 0} de{' '}
                                            {installments.total ?? 0} cuotas
                                        </span>
                                        <div className="flex gap-1">
                                            {installments.links.map(
                                                (link, idx) =>
                                                    link.url ? (
                                                        <Link
                                                            key={idx}
                                                            href={link.url}
                                                            preserveScroll
                                                            className={`inline-flex h-7 min-w-[28px] items-center justify-center rounded-[6px] px-2 font-mono text-xs ${
                                                                link.active
                                                                    ? 'bg-foreground text-background font-bold shadow-xs'
                                                                    : 'border-border bg-card text-foreground/80 hover:bg-background border'
                                                            }`}
                                                            dangerouslySetInnerHTML={{
                                                                __html: link.label,
                                                            }}
                                                        />
                                                    ) : (
                                                        <span
                                                            key={idx}
                                                            className="text-muted-foreground inline-flex h-7 min-w-[28px] items-center justify-center rounded-[6px] border border-transparent px-2 font-mono text-xs opacity-60"
                                                            dangerouslySetInnerHTML={{
                                                                __html: link.label,
                                                            }}
                                                        />
                                                    ),
                                            )}
                                        </div>
                                    </div>
                                )}
                        </Card>
                    </div>
                </div>
            </div>

            {/* Dialog: Abrir Turno de Caja */}
            <Dialog
                open={openTurnoDialogOpen}
                onOpenChange={setOpenTurnoDialogOpen}
            >
                <DialogContent className="border-border bg-card rounded-[16px] sm:max-w-md">
                    <DialogHeader>
                        <div className="flex items-center gap-2.5">
                            <div className="text-success-strong flex size-9 items-center justify-center rounded-[10px] border border-emerald-500/20 bg-emerald-500/10">
                                <Unlock className="size-5" />
                            </div>
                            <div>
                                <DialogTitle className="text-foreground font-['Oswald',sans-serif] text-[18px] font-semibold">
                                    Abrir turno de caja
                                </DialogTitle>
                                <DialogDescription className="text-muted-foreground text-xs">
                                    Indica el fondo inicial de efectivo con el
                                    que inicias el turno.
                                </DialogDescription>
                            </div>
                        </div>
                    </DialogHeader>

                    <form
                        onSubmit={submitOpenTurno}
                        className="mt-3 space-y-3.5"
                    >
                        {/* Clave global de caja (p.ej. "cash_register") que no
                            corresponde a un campo del formulario. */}
                        <AlertError
                            errors={Object.entries(openForm.errors)
                                .filter(([clave]) => clave !== 'monto_apertura')
                                .map(([, mensaje]) => mensaje)}
                        />
                        {sedes.length > 0 && (
                            <div>
                                <Label className="text-foreground/80 text-[11px] font-bold uppercase">
                                    Sede
                                </Label>
                                <select
                                    aria-label="Sede"
                                    value={openForm.data.sede_id}
                                    onChange={(e) =>
                                        openForm.setData(
                                            'sede_id',
                                            e.target.value,
                                        )
                                    }
                                    className="border-border bg-card text-foreground focus-visible:border-ring focus-visible:ring-ring/50 mt-1 h-9 w-full rounded-[8px] border px-3 text-[13px] outline-none focus-visible:ring-[3px]"
                                >
                                    {sedes.map((s) => (
                                        <option key={s.id} value={s.id}>
                                            {s.nombre}{' '}
                                            {s.tipo ? `(${s.tipo})` : ''}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        )}

                        <div>
                            <Label className="text-foreground/80 text-[11px] font-bold uppercase">
                                Fondo inicial (S/)
                            </Label>
                            <Input
                                type="number"
                                step="0.01"
                                min="0"
                                required
                                value={openForm.data.monto_apertura}
                                onChange={(e) =>
                                    openForm.setData(
                                        'monto_apertura',
                                        e.target.value,
                                    )
                                }
                                placeholder="0.00"
                                className="border-border bg-card mt-1 h-9 rounded-[8px] font-mono text-[13px]"
                            />
                            {openForm.errors.monto_apertura && (
                                <p className="text-destructive-strong mt-1 text-[11px]">
                                    {openForm.errors.monto_apertura}
                                </p>
                            )}
                        </div>

                        <DialogFooter className="pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setOpenTurnoDialogOpen(false)}
                                className="border-border rounded-[8px] text-xs font-semibold"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={openForm.processing}
                                className="rounded-[8px] bg-emerald-600 text-xs font-bold text-white hover:bg-emerald-700"
                            >
                                Confirmar apertura
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Dialog: Cerrar Turno de Caja (Arqueo ciego) */}
            <Dialog
                open={closeTurnoDialogOpen}
                onOpenChange={setCloseTurnoDialogOpen}
            >
                <DialogContent className="border-border bg-card rounded-[16px] sm:max-w-md">
                    <DialogHeader>
                        <div className="flex items-center gap-2.5">
                            <div className="bg-destructive/10 text-destructive-strong border-destructive/20 flex size-9 items-center justify-center rounded-[10px] border">
                                <Lock className="size-5" />
                            </div>
                            <div>
                                <DialogTitle className="text-foreground font-['Oswald',sans-serif] text-[18px] font-semibold">
                                    Cerrar turno de caja
                                </DialogTitle>
                                <DialogDescription className="text-muted-foreground text-xs">
                                    Arqueo ciego: ingresa el monto total en
                                    efectivo físico que has contado.
                                </DialogDescription>
                            </div>
                        </div>
                    </DialogHeader>

                    <form
                        onSubmit={submitCloseTurno}
                        className="mt-3 space-y-3.5"
                    >
                        {/* Clave global de caja (p.ej. "cash_register") que no
                            corresponde a un campo del formulario. */}
                        <AlertError
                            errors={Object.entries(closeForm.errors)
                                .filter(
                                    ([clave]) =>
                                        clave !== 'monto_contado_cierre',
                                )
                                .map(([, mensaje]) => mensaje)}
                        />
                        <div>
                            <Label className="text-foreground/80 text-[11px] font-bold uppercase">
                                Monto contado en efectivo (S/) *
                            </Label>
                            <Input
                                type="number"
                                step="0.01"
                                min="0"
                                required
                                value={closeForm.data.monto_contado_cierre}
                                onChange={(e) =>
                                    closeForm.setData(
                                        'monto_contado_cierre',
                                        e.target.value,
                                    )
                                }
                                placeholder="0.00"
                                className="border-border bg-card mt-1 h-9 rounded-[8px] font-mono text-[13px]"
                            />
                            {closeForm.errors.monto_contado_cierre && (
                                <p className="text-destructive-strong mt-1 text-[11px]">
                                    {closeForm.errors.monto_contado_cierre}
                                </p>
                            )}
                        </div>

                        <div>
                            <Label className="text-foreground/80 text-[11px] font-bold uppercase">
                                Observaciones (opcional)
                            </Label>
                            <Input
                                type="text"
                                value={closeForm.data.observacion}
                                onChange={(e) =>
                                    closeForm.setData(
                                        'observacion',
                                        e.target.value,
                                    )
                                }
                                placeholder="Ej. Billetes deteriorados, diferencias..."
                                className="border-border bg-card mt-1 h-9 rounded-[8px] text-[13px]"
                            />
                        </div>

                        <DialogFooter className="pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setCloseTurnoDialogOpen(false)}
                                className="border-border rounded-[8px] text-xs font-semibold"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={closeForm.processing}
                                className="bg-primary hover:bg-primary/90 rounded-[8px] text-xs font-bold text-white"
                            >
                                Confirmar y cerrar turno
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Dialog: Registrar Pago de Cuota */}
            <Dialog
                open={selectedInstallment !== null}
                onOpenChange={(open) => {
                    if (!open) setSelectedInstallment(null);
                }}
            >
                <DialogContent className="border-border bg-card rounded-[16px] sm:max-w-md">
                    <DialogHeader>
                        <div className="flex items-center gap-2.5">
                            <div className="flex size-9 items-center justify-center rounded-[10px] border border-blue-500/20 bg-blue-500/10 text-blue-600 dark:text-blue-400">
                                <CreditCard className="size-5" />
                            </div>
                            <div>
                                <DialogTitle className="text-foreground font-['Oswald',sans-serif] text-[18px] font-semibold">
                                    Registrar pago de cuota
                                </DialogTitle>
                                <DialogDescription className="text-muted-foreground text-xs">
                                    {selectedInstallment?.cliente} · Cuota{' '}
                                    {selectedInstallment?.numero_cuota}
                                </DialogDescription>
                            </div>
                        </div>
                    </DialogHeader>

                    <form
                        onSubmit={submitRegisterPayment}
                        className="mt-3 space-y-3.5"
                    >
                        <div>
                            <Label className="text-foreground/80 text-[11px] font-bold uppercase">
                                Forma de pago
                            </Label>
                            <select
                                aria-label="Forma de pago"
                                value={paymentForm.data.forma_pago}
                                onChange={(e) =>
                                    paymentForm.setData(
                                        'forma_pago',
                                        e.target.value,
                                    )
                                }
                                className="border-border bg-card text-foreground focus-visible:border-ring focus-visible:ring-ring/50 mt-1 h-9 w-full rounded-[8px] border px-3 text-[13px] outline-none focus-visible:ring-[3px]"
                            >
                                <option value="efectivo">Efectivo</option>
                                <option value="transferencia">
                                    Transferencia bancaria
                                </option>
                                <option value="yape">Yape</option>
                                <option value="plin">Plin</option>
                                <option value="pos">Tarjeta (POS)</option>
                                <option value="deposito">
                                    Depósito bancario
                                </option>
                                <option value="otro">Otro</option>
                            </select>
                        </div>

                        <div>
                            <Label className="text-foreground/80 text-[11px] font-bold uppercase">
                                Monto a pagar (S/) *
                            </Label>
                            <Input
                                type="number"
                                step="0.01"
                                min="0.01"
                                max={selectedInstallment?.saldo}
                                required
                                value={paymentForm.data.monto}
                                onChange={(e) =>
                                    paymentForm.setData('monto', e.target.value)
                                }
                                className="border-border bg-card mt-1 h-9 rounded-[8px] font-mono text-[13px]"
                            />
                            {paymentForm.errors.monto && (
                                <p className="text-destructive-strong mt-1 text-[11px]">
                                    {paymentForm.errors.monto}
                                </p>
                            )}
                        </div>

                        {paymentForm.data.forma_pago !== 'efectivo' && (
                            <div>
                                <Label className="text-foreground/80 text-[11px] font-bold uppercase">
                                    N° Operación / Referencia
                                </Label>
                                <Input
                                    type="text"
                                    value={paymentForm.data.numero_operacion}
                                    onChange={(e) =>
                                        paymentForm.setData(
                                            'numero_operacion',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Ej. OP-983412"
                                    className="border-border bg-card mt-1 h-9 rounded-[8px] font-mono text-[13px]"
                                />
                            </div>
                        )}

                        <DialogFooter className="pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setSelectedInstallment(null)}
                                className="border-border rounded-[8px] text-xs font-semibold"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={paymentForm.processing}
                                className="bg-foreground text-background hover:bg-foreground/90 rounded-[8px] text-xs font-bold shadow-xs transition-colors"
                            >
                                Registrar pago
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={selectedPayment !== null}
                onOpenChange={(open) => {
                    if (!open) setSelectedPayment(null);
                }}
            >
                <DialogContent className="border-border bg-card rounded-[16px] sm:max-w-md">
                    <DialogHeader>
                        <div className="flex items-center gap-2.5">
                            <XCircle className="text-destructive-strong size-6" />
                            <div>
                                <DialogTitle>Anular cobro</DialogTitle>
                                <DialogDescription>
                                    Indica por qué se anula el cobro de{' '}
                                    {soles(selectedPayment?.monto)}.
                                </DialogDescription>
                            </div>
                        </div>
                    </DialogHeader>
                    <form onSubmit={submitCancelPayment} className="space-y-3">
                        <div>
                            <Label htmlFor="motivo-anulacion">Motivo *</Label>
                            <Input
                                id="motivo-anulacion"
                                value={cancelForm.data.motivo}
                                onChange={(event) =>
                                    cancelForm.setData(
                                        'motivo',
                                        event.target.value,
                                    )
                                }
                                placeholder="Ej. Se registró dos veces"
                                required
                            />
                            {cancelForm.errors.motivo && (
                                <p className="text-destructive-strong mt-1 text-xs">
                                    {cancelForm.errors.motivo}
                                </p>
                            )}
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setSelectedPayment(null)}
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={cancelForm.processing}
                            >
                                Anular cobro
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </VendedorLayout>
    );
}
