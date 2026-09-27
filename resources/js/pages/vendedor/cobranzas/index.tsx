import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    Building2,
    Calendar,
    CheckCircle2,
    Clock,
    CreditCard,
    DollarSign,
    Lock,
    Unlock,
    Wallet,
    XCircle,
} from 'lucide-react';
import { FormEvent, useState } from 'react';

import CashRegisterController from '@/actions/App/Http/Controllers/Vendedor/CashRegisterController';
import CollectionController from '@/actions/App/Http/Controllers/Vendedor/CollectionController';
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
import pagos from '@/routes/vendedor/cobranzas/pagos';
import type { Team } from '@/types';

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

function formatCurrency(amount: number | string | null | undefined): string {
    const num = typeof amount === 'string' ? parseFloat(amount) : (amount ?? 0);
    if (isNaN(num)) return 'S/ 0.00';
    return `S/ ${num.toLocaleString('es-PE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

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
    const ventasEfectivo = turno_actual?.ventas_por_forma_pago?.efectivo ?? 0;
    const ventasTransferencia =
        (turno_actual?.ventas_por_forma_pago?.transferencia ?? 0) +
        (turno_actual?.ventas_por_forma_pago?.deposito ?? 0);
    const ventasTarjetaYape =
        (turno_actual?.ventas_por_forma_pago?.tarjeta_yape ?? 0) +
        (turno_actual?.ventas_por_forma_pago?.yape ?? 0) +
        (turno_actual?.ventas_por_forma_pago?.plin ?? 0) +
        (turno_actual?.ventas_por_forma_pago?.pos ?? 0);
    const efectivoEsperado = montoApertura + ventasEfectivo;

    const installmentRows = installments?.data ?? [];

    return (
        <VendedorLayout title="Cobranzas y Caja">
            <Head title="Cobranzas y Caja" />

            <div className="flex flex-col gap-4">
                {/* 2-Column Main Layout */}
                <div className="grid gap-4 lg:grid-cols-12">
                    {/* Columna Izquierda: Panel de Arqueo de Caja (5 cols) */}
                    <div className="flex flex-col gap-4 lg:col-span-5">
                        <Card className="rounded-[16px] border-border bg-card p-5 shadow-none">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <Wallet className="size-4 text-muted-foreground" />
                                    <span className="text-[13.5px] font-bold text-foreground">
                                        Arqueo de caja
                                    </span>
                                </div>
                                {turno_actual?.sede && (
                                    <span className="text-[11px] font-semibold text-muted-foreground">
                                        {turno_actual.sede}
                                    </span>
                                )}
                            </div>

                            {turno_actual &&
                            turno_actual.estado === 'abierto' ? (
                                <div className="mt-4 flex flex-col">
                                    {/* Estado: Turno Abierto */}
                                    <div className="flex items-center gap-2.5 rounded-[12px] bg-emerald-500/10 p-3.5 text-emerald-600 dark:text-emerald-400">
                                        <span className="relative flex size-2.5">
                                            <span className="absolute inline-flex size-full animate-ping rounded-full bg-emerald-600 opacity-75" />
                                            <span className="relative inline-flex size-2.5 rounded-full bg-emerald-600" />
                                        </span>
                                        <span className="text-[12.5px] font-bold">
                                            Turno abierto
                                        </span>
                                        <span className="ml-auto font-mono text-[11px] text-muted-foreground">
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
                                    <div className="mt-4 divide-y divide-border text-[12.5px]">
                                        <div className="flex items-center justify-between py-2">
                                            <span className="text-muted-foreground">
                                                Fondo inicial (apertura)
                                            </span>
                                            <span className="font-semibold text-foreground">
                                                {formatCurrency(montoApertura)}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between py-2">
                                            <span className="text-muted-foreground">
                                                Ventas en efectivo
                                            </span>
                                            <span className="font-semibold text-foreground">
                                                {formatCurrency(ventasEfectivo)}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between py-2">
                                            <span className="text-muted-foreground">
                                                Transferencia / Depósito
                                            </span>
                                            <span className="font-semibold text-foreground">
                                                {formatCurrency(
                                                    ventasTransferencia,
                                                )}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between py-2">
                                            <span className="text-muted-foreground">
                                                Tarjeta / Yape / Plin
                                            </span>
                                            <span className="font-semibold text-foreground">
                                                {formatCurrency(
                                                    ventasTarjetaYape,
                                                )}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between pt-2.5 text-[13.5px]">
                                            <span className="font-bold text-foreground">
                                                Efectivo esperado
                                            </span>
                                            <span className="font-['Oswald',sans-serif] text-[18px] font-semibold text-emerald-600 dark:text-emerald-400">
                                                {formatCurrency(
                                                    efectivoEsperado,
                                                )}
                                            </span>
                                        </div>
                                    </div>

                                    {/* Botón Cerrar Turno */}
                                    <Button
                                        type="button"
                                        onClick={() =>
                                            setCloseTurnoDialogOpen(true)
                                        }
                                        className="mt-5 h-10 w-full rounded-[10px] bg-primary text-[13px] font-bold text-white shadow-none transition-colors hover:bg-primary/90"
                                    >
                                        <Lock className="mr-1.5 size-4" />
                                        <span>Cerrar turno (arqueo ciego)</span>
                                    </Button>
                                </div>
                            ) : (
                                <div className="mt-4 flex flex-col items-center justify-center gap-3 rounded-[12px] border border-dashed border-border bg-muted/40 p-6 text-center">
                                    <div className="flex size-11 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                        <Unlock className="size-5" />
                                    </div>
                                    <div>
                                        <p className="text-[13px] font-bold text-foreground">
                                            No hay un turno de caja abierto
                                        </p>
                                        <p className="mt-1 text-[11.5px] text-muted-foreground">
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
                            <Card className="rounded-[16px] border-border bg-card p-5 shadow-none">
                                <div className="flex items-center justify-between">
                                    <span className="text-[13px] font-bold text-foreground">
                                        Cierres recientes
                                    </span>
                                    <span className="text-[11px] text-muted-foreground">
                                        {cierres.total ?? cierres.data.length}{' '}
                                        cierres
                                    </span>
                                </div>
                                <div className="mt-3 divide-y divide-border text-[12px]">
                                    {cierres.data.slice(0, 4).map((cierre) => {
                                        const dif = Number(cierre.diferencia);
                                        return (
                                            <div
                                                key={cierre.id}
                                                className="py-2.5 first:pt-1"
                                            >
                                                <div className="flex items-center justify-between font-semibold text-foreground">
                                                    <span>
                                                        {cierre.sede ||
                                                            'Sede principal'}
                                                    </span>
                                                    <span>
                                                        {formatCurrency(
                                                            cierre.monto_contado_cierre,
                                                        )}
                                                    </span>
                                                </div>
                                                <div className="mt-0.5 flex items-center justify-between text-[11px] text-muted-foreground">
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
                                                                ? 'text-emerald-600 dark:text-emerald-400'
                                                                : dif > 0
                                                                  ? 'font-bold text-blue-600 dark:text-blue-400'
                                                                  : 'font-bold text-destructive'
                                                        }
                                                    >
                                                        {dif === 0
                                                            ? 'Exacto'
                                                            : dif > 0
                                                              ? `+${formatCurrency(dif)}`
                                                              : formatCurrency(
                                                                    dif,
                                                                )}
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
                        <Card className="rounded-[16px] border-border bg-card p-5 shadow-none">
                            <div className="flex items-center justify-between">
                                <div>
                                    <span className="text-[13.5px] font-bold text-foreground">
                                        Mis cuentas por cobrar
                                    </span>
                                    <p className="mt-0.5 text-[11.5px] text-muted-foreground">
                                        Cuotas pendientes o parciales de tus
                                        clientes
                                    </p>
                                </div>
                                <Badge className="border-none bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                    {installmentRows.length} cuotas
                                </Badge>
                            </div>

                            <div className="mt-4 overflow-x-auto">
                                <table className="w-full border-collapse text-[12.5px]">
                                    <thead>
                                        <tr className="border-b border-border">
                                            <th className="px-2.5 py-2.5 text-left font-mono text-[9.5px] font-semibold tracking-[0.05em] text-muted-foreground uppercase">
                                                Cliente
                                            </th>
                                            <th className="px-2.5 py-2.5 text-left font-mono text-[9.5px] font-semibold tracking-[0.05em] text-muted-foreground uppercase">
                                                Cuota
                                            </th>
                                            <th className="px-2.5 py-2.5 text-left font-mono text-[9.5px] font-semibold tracking-[0.05em] text-muted-foreground uppercase">
                                                Monto
                                            </th>
                                            <th className="px-2.5 py-2.5 text-left font-mono text-[9.5px] font-semibold tracking-[0.05em] text-muted-foreground uppercase">
                                                Vencimiento
                                            </th>
                                            <th className="px-2.5 py-2.5 text-right font-mono text-[9.5px] font-semibold tracking-[0.05em] text-muted-foreground uppercase">
                                                Acción
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {installmentRows.length === 0 ? (
                                            <tr>
                                                <td
                                                    colSpan={5}
                                                    className="px-4 py-12 text-center text-muted-foreground"
                                                >
                                                    <div className="flex flex-col items-center justify-center gap-2">
                                                        <CheckCircle2 className="size-8 text-emerald-600 dark:text-emerald-400" />
                                                        <p className="text-xs font-semibold text-foreground">
                                                            No tienes cuotas
                                                            pendientes de cobro
                                                        </p>
                                                        <p className="text-[11px] text-muted-foreground">
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
                                                        className="border-b border-border transition-colors hover:bg-muted/40"
                                                    >
                                                        <td className="px-2.5 py-3.5">
                                                            <div className="font-bold text-foreground">
                                                                {inst.cliente ||
                                                                    'Cliente'}
                                                            </div>
                                                            <div className="font-mono text-[10.5px] text-muted-foreground">
                                                                Venta:{' '}
                                                                {inst.sale_numero ||
                                                                    `#${inst.sale_id}`}
                                                            </div>
                                                        </td>

                                                        <td className="px-2.5 py-3.5 font-mono text-xs text-foreground/80">
                                                            {inst.numero_cuota}
                                                        </td>

                                                        <td className="px-2.5 py-3.5 font-bold text-foreground">
                                                            <div>
                                                                {formatCurrency(
                                                                    inst.saldo,
                                                                )}
                                                            </div>
                                                            <div className="text-[10px] font-normal text-muted-foreground">
                                                                Saldo de{' '}
                                                                {formatCurrency(
                                                                    inst.monto,
                                                                )}
                                                            </div>
                                                        </td>

                                                        <td className="px-2.5 py-3.5">
                                                            <Badge
                                                                className={`rounded-full border-none px-2 py-0.5 text-[10.5px] font-bold ${
                                                                    isVencido
                                                                        ? 'bg-destructive/10 text-destructive border border-destructive/20'
                                                                        : 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20'
                                                                }`}
                                                            >
                                                                {isVencido
                                                                    ? `Vencido hace ${inst.dias_vencido}d`
                                                                    : `Vence ${inst.fecha_vencimiento}`}
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
                                                                        className="mr-1 h-7 rounded-[7px] px-2 text-[11px] text-destructive"
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
                                                                className="h-7 rounded-[7px] bg-card px-3 text-[11px] font-bold text-white shadow-none transition-colors hover:bg-foreground/90"
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
                                    <div className="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-border pt-3 text-[11.5px] text-muted-foreground">
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
                                                                    ? 'bg-card font-bold text-white'
                                                                    : 'border border-border bg-card text-foreground/80 hover:bg-background'
                                                            }`}
                                                            dangerouslySetInnerHTML={{
                                                                __html: link.label,
                                                            }}
                                                        />
                                                    ) : (
                                                        <span
                                                            key={idx}
                                                            className="inline-flex h-7 min-w-[28px] items-center justify-center rounded-[6px] border border-transparent px-2 font-mono text-xs text-muted-foreground opacity-60"
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
                <DialogContent className="rounded-[16px] border-border bg-card sm:max-w-md">
                    <DialogHeader>
                        <div className="flex items-center gap-2.5">
                            <div className="flex size-9 items-center justify-center rounded-[10px] bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                <Unlock className="size-5" />
                            </div>
                            <div>
                                <DialogTitle className="font-['Oswald',sans-serif] text-[18px] font-semibold text-foreground">
                                    Abrir turno de caja
                                </DialogTitle>
                                <DialogDescription className="text-xs text-muted-foreground">
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
                        {sedes.length > 0 && (
                            <div>
                                <Label className="text-[11px] font-bold text-foreground/80 uppercase">
                                    Sede
                                </Label>
                                <select
                                    value={openForm.data.sede_id}
                                    onChange={(e) =>
                                        openForm.setData(
                                            'sede_id',
                                            e.target.value,
                                        )
                                    }
                                    className="mt-1 h-9 w-full rounded-[8px] border border-border bg-card px-3 text-[13px] text-foreground outline-none"
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
                            <Label className="text-[11px] font-bold text-foreground/80 uppercase">
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
                                className="mt-1 h-9 rounded-[8px] border-border bg-card font-mono text-[13px]"
                            />
                            {openForm.errors.monto_apertura && (
                                <p className="mt-1 text-[11px] text-destructive">
                                    {openForm.errors.monto_apertura}
                                </p>
                            )}
                        </div>

                        <DialogFooter className="pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setOpenTurnoDialogOpen(false)}
                                className="rounded-[8px] border-border text-xs font-semibold"
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
                <DialogContent className="rounded-[16px] border-border bg-card sm:max-w-md">
                    <DialogHeader>
                        <div className="flex items-center gap-2.5">
                            <div className="flex size-9 items-center justify-center rounded-[10px] bg-destructive/10 text-destructive border border-destructive/20">
                                <Lock className="size-5" />
                            </div>
                            <div>
                                <DialogTitle className="font-['Oswald',sans-serif] text-[18px] font-semibold text-foreground">
                                    Cerrar turno de caja
                                </DialogTitle>
                                <DialogDescription className="text-xs text-muted-foreground">
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
                        <div>
                            <Label className="text-[11px] font-bold text-foreground/80 uppercase">
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
                                className="mt-1 h-9 rounded-[8px] border-border bg-card font-mono text-[13px]"
                            />
                            {closeForm.errors.monto_contado_cierre && (
                                <p className="mt-1 text-[11px] text-destructive">
                                    {closeForm.errors.monto_contado_cierre}
                                </p>
                            )}
                        </div>

                        <div>
                            <Label className="text-[11px] font-bold text-foreground/80 uppercase">
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
                                className="mt-1 h-9 rounded-[8px] border-border bg-card text-[13px]"
                            />
                        </div>

                        <DialogFooter className="pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setCloseTurnoDialogOpen(false)}
                                className="rounded-[8px] border-border text-xs font-semibold"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={closeForm.processing}
                                className="rounded-[8px] bg-primary text-xs font-bold text-white hover:bg-primary/90"
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
                <DialogContent className="rounded-[16px] border-border bg-card sm:max-w-md">
                    <DialogHeader>
                        <div className="flex items-center gap-2.5">
                            <div className="flex size-9 items-center justify-center rounded-[10px] bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20">
                                <CreditCard className="size-5" />
                            </div>
                            <div>
                                <DialogTitle className="font-['Oswald',sans-serif] text-[18px] font-semibold text-foreground">
                                    Registrar pago de cuota
                                </DialogTitle>
                                <DialogDescription className="text-xs text-muted-foreground">
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
                            <Label className="text-[11px] font-bold text-foreground/80 uppercase">
                                Forma de pago
                            </Label>
                            <select
                                value={paymentForm.data.forma_pago}
                                onChange={(e) =>
                                    paymentForm.setData(
                                        'forma_pago',
                                        e.target.value,
                                    )
                                }
                                className="mt-1 h-9 w-full rounded-[8px] border border-border bg-card px-3 text-[13px] text-foreground outline-none"
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
                            <Label className="text-[11px] font-bold text-foreground/80 uppercase">
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
                                className="mt-1 h-9 rounded-[8px] border-border bg-card font-mono text-[13px]"
                            />
                            {paymentForm.errors.monto && (
                                <p className="mt-1 text-[11px] text-destructive">
                                    {paymentForm.errors.monto}
                                </p>
                            )}
                        </div>

                        {paymentForm.data.forma_pago !== 'efectivo' && (
                            <div>
                                <Label className="text-[11px] font-bold text-foreground/80 uppercase">
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
                                    className="mt-1 h-9 rounded-[8px] border-border bg-card font-mono text-[13px]"
                                />
                            </div>
                        )}

                        <DialogFooter className="pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setSelectedInstallment(null)}
                                className="rounded-[8px] border-border text-xs font-semibold"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={paymentForm.processing}
                                className="rounded-[8px] bg-card text-xs font-bold text-white hover:bg-foreground/90"
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
                <DialogContent className="rounded-[16px] border-border bg-card sm:max-w-md">
                    <DialogHeader>
                        <div className="flex items-center gap-2.5">
                            <XCircle className="size-6 text-destructive" />
                            <div>
                                <DialogTitle>Anular cobro</DialogTitle>
                                <DialogDescription>
                                    Indica por qué se anula el cobro de{' '}
                                    {formatCurrency(selectedPayment?.monto)}.
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
                                <p className="mt-1 text-xs text-destructive">
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
