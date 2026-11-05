import { Progreso } from '@/components/progreso';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import VendedorLayout from '@/layouts/vendedor-layout';
import caja from '@/routes/vendedor/caja';
import cobranzas from '@/routes/vendedor/cobranzas';
import pagos from '@/routes/vendedor/cobranzas/pagos';
import type { Team } from '@/types';
import { fechaCorta, fechaLocal, soles } from '@/lib/utils';
import { NativeSelect } from '@/components/ui/native-select';

/** Días de hoy a la fecha "YYYY-MM-DD" (0 = hoy). */
function diasHasta(fecha: string): number {
    return Math.round(
        (new Date(`${fecha.slice(0, 10)}T00:00:00`).getTime() -
            new Date(`${fechaLocal()}T00:00:00`).getTime()) /
            86_400_000,
    );
}

/** "Hoy · 08:12" o "Jue 29/10 · 10:38": un turno de otro día se nota. */
function aperturaDelTurno(fecha?: string): string {
    if (!fecha) {
        return 'Hoy';
    }

    const apertura = new Date(fecha);
    const hora = apertura.toLocaleTimeString('es-PE', {
        hour: '2-digit',
        minute: '2-digit',
    });

    if (fechaLocal(apertura) === fechaLocal()) {
        return `Hoy · ${hora}`;
    }

    const dia = apertura.toLocaleDateString('es-PE', {
        weekday: 'short',
        day: '2-digit',
        month: '2-digit',
    });

    return `Desde ${dia} · ${hora}`;
}

/**
 * Billetes y monedas que se cuentan al cerrar, en céntimos (mismas claves que
 * el servidor: un punto en la clave rompe la validación de Laravel).
 */
const DENOMINACIONES = [
    '20000',
    '10000',
    '5000',
    '2000',
    '1000',
    '500',
    '200',
    '100',
    '50',
    '20',
    '10',
];

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
    abierto_mas_de_24_horas?: boolean;
    /** Ya contó el cajón y no cuadró: solo falta su explicación (arqueo ciego). */
    conteo_registrado?: boolean;
    ventas_por_forma_pago?: Record<string, number>;
    salidas?: {
        id: number;
        monto: number;
        motivo: string;
        hora: string | null;
    }[];
    movimientos?: Movimiento[];
} | null;

/** Un movimiento del turno; el efectivo llega sin monto (arqueo ciego). */
export type Movimiento = {
    id: string;
    hora: string | null;
    tipo: 'cobro' | 'venta' | 'salida' | 'apertura';
    titulo: string;
    detalle: string | null;
    comprobante: string | null;
    efectivo: boolean;
    monto: number | null;
};

type Tramo = { monto: number; cuotas: number };

export type ResumenCobranzas = {
    vencido: Tramo;
    semana: Tramo;
    total: Tramo;
    clientes: number;
    cobrado_mes: number;
};

export type CierreItem = {
    id: number;
    sede?: string;
    fecha_apertura?: string;
    fecha_cierre?: string;
    monto_apertura: number | string;
    monto_contado_cierre: number | string;
    observacion?: string;
    estado: string;
};

export type InstallmentItem = {
    id: number;
    sale_id: number;
    sale_numero: string;
    con_detraccion?: boolean;
    saldo_comercial_venta?: number;
    saldo_detraccion_venta?: number;
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
        es_detraccion?: boolean;
        fecha: string;
        puede_anular: boolean;
        numero_operacion?: string | null;
        evidencia_url?: string | null;
    }[];
    anulados: {
        id: number;
        monto: number | string;
        forma_pago: string;
        es_detraccion?: boolean;
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
    resumen?: ResumenCobranzas;
    filtro?: 'todas' | 'vencidas' | 'semana' | 'luego';
};

export default function CobranzasIndex({
    turno_actual = null,
    cierres,
    sedes = [],
    installments,
    resumen,
    filtro = 'todas',
}: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    // Dos vistas de la misma pantalla: Caja (/caja) y Cobranzas (/cobranzas).
    const vista = usePage().url.includes('/cobranzas') ? 'cobranzas' : 'caja';
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
        // Se propone lo contado en el último cierre; se puede cambiar.
        monto_apertura: cierres?.data?.[0]
            ? Number(cierres.data[0].monto_contado_cierre).toFixed(2)
            : '0.00',
    });

    // Se propone lo contado en el último cierre (el más reciente al abrir).
    const abrirDialogoApertura = () => {
        const ultimo = cierres?.data?.[0];
        openForm.setData(
            'monto_apertura',
            ultimo ? Number(ultimo.monto_contado_cierre).toFixed(2) : '0.00',
        );
        setOpenTurnoDialogOpen(true);
    };

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

    // Form: Salida de dinero del turno (gasto con su motivo)
    const [salidaDialogOpen, setSalidaDialogOpen] = useState(false);
    const salidaForm = useForm({ monto: '', motivo: '' });
    const submitSalida = (e: FormEvent) => {
        e.preventDefault();
        if (!turno_actual) return;
        salidaForm.post(
            CashRegisterController.salida.url({
                current_team: teamSlug,
                cash_register: turno_actual.id,
            }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    salidaForm.reset();
                    setSalidaDialogOpen(false);
                    toast.success('Salida de dinero registrada.');
                },
            },
        );
    };
    const totalSalidas = (turno_actual?.salidas ?? []).reduce(
        (total, salida) => total + Number(salida.monto),
        0,
    );

    // Form: Cerrar turno de caja (arqueo ciego por billetes y monedas)
    const closeForm = useForm({
        denominaciones: {} as Record<string, string>,
        observacion: '',
    });
    const totalContado = DENOMINACIONES.reduce(
        (total, d) =>
            total +
            (Number(d) / 100) * Number(closeForm.data.denominaciones[d] || 0),
        0,
    );
    const conteoRegistrado = !!turno_actual?.conteo_registrado;

    const submitCloseTurno = (e: FormEvent) => {
        e.preventDefault();
        if (!turno_actual) return;
        closeForm.post(
            CashRegisterController.close.url({
                current_team: teamSlug,
                cash_register: turno_actual.id,
            }),
            {
                // Los errores quedan junto a cada campo del diálogo.
                preserveScroll: true,
                onSuccess: () => {
                    setCloseTurnoDialogOpen(false);
                    closeForm.reset();
                    toast.success('Turno de caja cerrado.');
                },
            },
        );
    };

    // Form: Registrar pago de cuota
    const paymentForm = useForm({
        forma_pago: 'efectivo',
        monto: '',
        numero_operacion: '',
        es_detraccion: false,
        fecha_deposito: '',
        evidencia: null as File | null,
    });

    const handleOpenPaymentDialog = (inst: InstallmentItem) => {
        setSelectedInstallment(inst);
        paymentForm.setData({
            forma_pago: 'efectivo',
            monto: String(
                Math.min(
                    Number(inst.saldo),
                    inst.saldo_comercial_venta ?? Number(inst.saldo),
                ),
            ),
            numero_operacion: '',
            es_detraccion: false,
            fecha_deposito: '',
            evidencia: null,
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
    const ventasOtros = turno_actual?.ventas_por_forma_pago?.otro ?? 0;

    const installmentRows = installments?.data ?? [];

    return (
        <VendedorLayout title="Caja y cobranzas">
            <Head title="Caja y cobranzas" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    title="Caja y cobranzas"
                    description={
                        vista === 'caja'
                            ? 'El dinero de tu turno de hoy y cómo cerraron los anteriores.'
                            : 'Las cuotas que tus clientes tienen por pagar, empezando por las vencidas.'
                    }
                />
                <nav
                    aria-label="Secciones de Caja y cobranzas"
                    className="border-border flex gap-1 border-b"
                >
                    {[
                        {
                            clave: 'caja',
                            nombre: 'Mi caja',
                            href: caja.index.url(teamSlug),
                        },
                        {
                            clave: 'cobranzas',
                            nombre: 'Cobranzas',
                            href: cobranzas.index.url(teamSlug),
                        },
                    ].map((pestana) => (
                        <Link
                            key={pestana.clave}
                            href={pestana.href}
                            aria-current={
                                vista === pestana.clave ? 'page' : undefined
                            }
                            className={`-mb-px flex min-h-11 items-center gap-2 border-b-2 px-4 text-sm font-semibold ${vista === pestana.clave ? 'border-primary text-foreground' : 'text-muted-foreground hover:text-foreground border-transparent'}`}
                        >
                            {pestana.nombre}
                            {pestana.clave === 'cobranzas' &&
                            (resumen?.vencido.cuotas ?? 0) > 0 ? (
                                <span className="bg-destructive/10 text-destructive-strong rounded-full px-2 py-0.5 text-xs font-bold">
                                    {resumen?.vencido.cuotas}{' '}
                                    {resumen?.vencido.cuotas === 1
                                        ? 'vencida'
                                        : 'vencidas'}
                                </span>
                            ) : null}
                        </Link>
                    ))}
                </nav>
                <div className="flex flex-col gap-4">
                    {vista === 'caja' ? (
                        <>
                            <div className="grid items-start gap-4 lg:grid-cols-2">
                                <Card className="border-border bg-card rounded-2xl p-5 shadow-none">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <Wallet className="text-muted-foreground size-4" />
                                            <span className="text-foreground text-[13.5px] font-bold">
                                                Arqueo de caja
                                            </span>
                                        </div>
                                        {turno_actual?.sede && (
                                            <span className="text-muted-foreground text-xs font-semibold">
                                                {turno_actual.sede}
                                            </span>
                                        )}
                                    </div>

                                    {turno_actual &&
                                    turno_actual.estado === 'abierto' ? (
                                        <div className="mt-4 flex flex-col">
                                            {/* Estado: Turno Abierto */}
                                            <div className="text-success-strong flex items-center gap-2.5 rounded-2xl bg-emerald-500/10 p-3.5">
                                                <span className="relative flex size-2.5">
                                                    <span className="absolute inline-flex size-full animate-ping rounded-full bg-emerald-600 opacity-75" />
                                                    <span className="relative inline-flex size-2.5 rounded-full bg-emerald-600" />
                                                </span>
                                                <span className="text-[12.5px] font-bold">
                                                    Turno abierto
                                                </span>
                                                <span className="text-muted-foreground ml-auto text-xs tabular-nums">
                                                    {aperturaDelTurno(
                                                        turno_actual.fecha_apertura,
                                                    )}
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
                                                        {soles(
                                                            ventasTransferencia,
                                                        )}
                                                    </span>
                                                </div>
                                                <div className="flex items-center justify-between py-2">
                                                    <span className="text-muted-foreground">
                                                        Tarjeta / Yape / Plin
                                                    </span>
                                                    <span className="text-foreground font-semibold">
                                                        {soles(
                                                            ventasTarjetaYape,
                                                        )}
                                                    </span>
                                                </div>
                                                {ventasOtros !== 0 && (
                                                    <div className="flex items-center justify-between py-2">
                                                        <span className="text-muted-foreground">
                                                            Otros medios
                                                        </span>
                                                        <span className="text-foreground font-semibold">
                                                            {soles(ventasOtros)}
                                                        </span>
                                                    </div>
                                                )}
                                                <div className="flex items-center justify-between py-2">
                                                    <span className="text-muted-foreground">
                                                        Salidas de dinero
                                                    </span>
                                                    <span className="text-foreground font-semibold">
                                                        − {soles(totalSalidas)}
                                                    </span>
                                                </div>
                                                {(turno_actual.salidas ?? [])
                                                    .length > 0 && (
                                                    <ul className="text-muted-foreground space-y-1 py-2 text-xs">
                                                        {(
                                                            turno_actual.salidas ??
                                                            []
                                                        ).map((salida) => (
                                                            <li
                                                                key={salida.id}
                                                                className="flex justify-between gap-3"
                                                            >
                                                                <span className="min-w-0 truncate">
                                                                    {
                                                                        salida.hora
                                                                    }{' '}
                                                                    ·{' '}
                                                                    {
                                                                        salida.motivo
                                                                    }
                                                                </span>
                                                                <span className="shrink-0">
                                                                    {soles(
                                                                        salida.monto,
                                                                    )}
                                                                </span>
                                                            </li>
                                                        ))}
                                                    </ul>
                                                )}
                                                {/* Arqueo ciego (E-011): el vendedor
                                        no ve el efectivo esperado; la
                                        diferencia la revisa el Gerente. */}
                                                <p className="text-muted-foreground pt-2.5 text-xs">
                                                    Al cerrar cuentas el cajón
                                                    por billetes y monedas. La
                                                    diferencia, si la hay, la
                                                    revisa el Gerente.
                                                </p>
                                            </div>

                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() =>
                                                    setSalidaDialogOpen(true)
                                                }
                                                className="border-border mt-5 h-10 w-full rounded-lg text-[13px] font-semibold"
                                            >
                                                Registrar salida de dinero
                                            </Button>

                                            {/* Botón Cerrar Turno */}
                                            <Button
                                                type="button"
                                                onClick={() =>
                                                    setCloseTurnoDialogOpen(
                                                        true,
                                                    )
                                                }
                                                className="bg-primary hover:bg-primary/90 mt-2 h-10 w-full rounded-lg text-[13px] font-bold text-white shadow-none transition-colors"
                                            >
                                                <Lock className="mr-1.5 size-4" />
                                                <span>
                                                    Cerrar turno y contar
                                                    efectivo
                                                </span>
                                            </Button>
                                        </div>
                                    ) : (
                                        <div className="border-border bg-muted/40 mt-4 flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed p-6 text-center">
                                            <div className="bg-muted text-muted-foreground flex size-11 items-center justify-center rounded-full">
                                                <Unlock className="size-5" />
                                            </div>
                                            <div>
                                                <p className="text-foreground text-[13px] font-bold">
                                                    No hay un turno de caja
                                                    abierto
                                                </p>
                                                <p className="text-muted-foreground mt-1 text-xs">
                                                    Debes abrir un turno para
                                                    registrar cobros en efectivo
                                                    y ventas del día.
                                                </p>
                                            </div>
                                            <Button
                                                aria-label="Abrir turno de caja"
                                                type="button"
                                                onClick={() =>
                                                    abrirDialogoApertura()
                                                }
                                                className="mt-1 rounded-lg bg-emerald-600 px-4 text-[13px] font-bold text-white shadow-none hover:bg-emerald-700"
                                            >
                                                <Unlock className="mr-1.5 size-3.5" />
                                                <span>Abrir turno de caja</span>
                                            </Button>
                                        </div>
                                    )}
                                </Card>

                                <Card className="border-border bg-card rounded-2xl p-5 shadow-none">
                                    <div className="flex items-baseline justify-between">
                                        <h2 className="text-foreground text-[13.5px] font-bold">
                                            Movimientos de hoy
                                        </h2>
                                        <span className="text-muted-foreground text-xs">
                                            {
                                                (
                                                    turno_actual?.movimientos ??
                                                    []
                                                ).length
                                            }{' '}
                                            {(turno_actual?.movimientos ?? [])
                                                .length === 1
                                                ? 'movimiento'
                                                : 'movimientos'}
                                        </span>
                                    </div>
                                    {(turno_actual?.movimientos ?? [])
                                        .length === 0 ? (
                                        <p className="text-muted-foreground mt-4 text-[12.5px]">
                                            Cuando abras tu turno, aquí verás
                                            cada cobro, venta y salida con su
                                            hora.
                                        </p>
                                    ) : (
                                        <ol className="divide-border mt-3 divide-y">
                                            {(
                                                turno_actual?.movimientos ?? []
                                            ).map((mov) => (
                                                <li
                                                    key={mov.id}
                                                    className="grid grid-cols-[3rem_minmax(0,1fr)_auto] items-center gap-3 py-2.5"
                                                >
                                                    <span className="text-muted-foreground text-xs tabular-nums">
                                                        {mov.hora}
                                                    </span>
                                                    <span className="min-w-0">
                                                        <span className="text-foreground block truncate text-[12.5px] font-semibold">
                                                            {mov.titulo}
                                                        </span>
                                                        <span className="text-muted-foreground block truncate text-xs">
                                                            {[
                                                                mov.detalle,
                                                                mov.comprobante,
                                                            ]
                                                                .filter(Boolean)
                                                                .join(' · ')}
                                                        </span>
                                                    </span>
                                                    <span
                                                        className={`text-[12.5px] font-semibold tabular-nums ${mov.tipo === 'salida' ? 'text-destructive-strong' : mov.efectivo && mov.tipo !== 'apertura' ? 'text-success-strong' : 'text-muted-foreground'}`}
                                                    >
                                                        {mov.monto === null
                                                            ? 'Efectivo'
                                                            : mov.tipo ===
                                                                'salida'
                                                              ? `− ${soles(mov.monto)}`
                                                              : soles(
                                                                    mov.monto,
                                                                )}
                                                    </span>
                                                </li>
                                            ))}
                                        </ol>
                                    )}
                                    <p className="text-muted-foreground border-border mt-3 border-t pt-3 text-xs">
                                        El efectivo sale sin monto: lo cuentas
                                        al cerrar y la diferencia la revisa el
                                        gerente.
                                    </p>
                                </Card>
                            </div>

                            {cierres && cierres.data.length > 0 && (
                                <Card className="border-border bg-card rounded-2xl p-5 shadow-none">
                                    <div className="flex items-baseline justify-between">
                                        <h2 className="text-foreground text-[13.5px] font-bold">
                                            Turnos anteriores
                                        </h2>
                                        <span className="text-muted-foreground text-xs">
                                            {cierres.total ??
                                                cierres.data.length}{' '}
                                            {(cierres.total ??
                                                cierres.data.length) === 1
                                                ? 'turno'
                                                : 'turnos'}
                                        </span>
                                    </div>
                                    <div className="mt-3 overflow-x-auto">
                                        <Table className="w-full text-[12.5px]">
                                            <TableHeader>
                                                <TableRow className="border-border border-b">
                                                    <TableHead className="text-left">
                                                        Día
                                                    </TableHead>
                                                    <TableHead className="text-left">
                                                        Sede
                                                    </TableHead>
                                                    <TableHead className="text-right">
                                                        Fondo inicial
                                                    </TableHead>
                                                    <TableHead className="text-right">
                                                        Contaste al cerrar
                                                    </TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {cierres.data
                                                    .slice(0, 6)
                                                    .map((cierre) => (
                                                        <TableRow
                                                            key={cierre.id}
                                                            className="border-border border-b last:border-0"
                                                        >
                                                            <TableCell className="py-2.5">
                                                                <span className="text-foreground block font-semibold capitalize">
                                                                    {cierre.fecha_cierre
                                                                        ? new Date(
                                                                              cierre.fecha_cierre,
                                                                          ).toLocaleDateString(
                                                                              'es-PE',
                                                                              {
                                                                                  weekday:
                                                                                      'long',
                                                                                  day: 'numeric',
                                                                                  month: 'short',
                                                                              },
                                                                          )
                                                                        : '—'}
                                                                </span>
                                                                <span className="text-muted-foreground block text-xs tabular-nums">
                                                                    {cierre.fecha_apertura
                                                                        ? new Date(
                                                                              cierre.fecha_apertura,
                                                                          ).toLocaleTimeString(
                                                                              'es-PE',
                                                                              {
                                                                                  hour: '2-digit',
                                                                                  minute: '2-digit',
                                                                              },
                                                                          )
                                                                        : ''}
                                                                    {cierre.fecha_cierre
                                                                        ? ` a ${new Date(cierre.fecha_cierre).toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' })}`
                                                                        : ''}
                                                                </span>
                                                            </TableCell>
                                                            <TableCell className="text-muted-foreground py-2.5">
                                                                {cierre.sede ||
                                                                    'Sede principal'}
                                                            </TableCell>
                                                            <TableCell className="py-2.5 text-right tabular-nums">
                                                                {soles(
                                                                    cierre.monto_apertura,
                                                                )}
                                                            </TableCell>
                                                            <TableCell className="text-foreground py-2.5 text-right font-semibold tabular-nums">
                                                                {soles(
                                                                    cierre.monto_contado_cierre,
                                                                )}
                                                            </TableCell>
                                                        </TableRow>
                                                    ))}
                                            </TableBody>
                                        </Table>
                                    </div>
                                    <p className="text-muted-foreground mt-3 text-xs">
                                        Si hubo diferencia en algún cierre, la
                                        revisa el gerente.
                                    </p>
                                </Card>
                            )}
                        </>
                    ) : null}

                    {/* Columna Derecha: Mis Cuentas por Cobrar (7 cols) */}
                    {vista === 'cobranzas' ? (
                        <div className="flex flex-col gap-4">
                            {resumen ? (
                                <section
                                    aria-label="Resumen de cobranzas"
                                    className="grid grid-cols-2 gap-3 xl:grid-cols-4"
                                >
                                    {[
                                        {
                                            nombre: 'Vencido',
                                            monto: resumen.vencido.monto,
                                            nota: `${resumen.vencido.cuotas} ${resumen.vencido.cuotas === 1 ? 'cuota' : 'cuotas'}`,
                                            tono: 'peligro',
                                        },
                                        {
                                            nombre: 'Vence en 7 días',
                                            monto: resumen.semana.monto,
                                            nota: `${resumen.semana.cuotas} ${resumen.semana.cuotas === 1 ? 'cuota' : 'cuotas'}`,
                                            tono: 'aviso',
                                        },
                                        {
                                            nombre: 'Por cobrar en total',
                                            monto: resumen.total.monto,
                                            nota: `${resumen.total.cuotas} ${resumen.total.cuotas === 1 ? 'cuota' : 'cuotas'} de ${resumen.clientes} ${resumen.clientes === 1 ? 'cliente' : 'clientes'}`,
                                            tono: 'neutro',
                                        },
                                        {
                                            nombre: 'Cobrado este mes',
                                            monto: resumen.cobrado_mes,
                                            nota: 'Descontando devoluciones',
                                            tono: 'neutro',
                                        },
                                    ].map((dato) => (
                                        <div
                                            key={dato.nombre}
                                            className={`rounded-2xl border p-4 ${dato.tono === 'peligro' && dato.monto > 0 ? 'border-destructive/30 bg-destructive/10' : dato.tono === 'aviso' && dato.monto > 0 ? 'border-amber-500/30 bg-amber-500/10' : 'border-border bg-card'}`}
                                        >
                                            <p
                                                className={`text-[12.5px] font-semibold ${dato.tono === 'peligro' && dato.monto > 0 ? 'text-destructive-strong' : dato.tono === 'aviso' && dato.monto > 0 ? 'text-warning-strong' : 'text-muted-foreground'}`}
                                            >
                                                {dato.nombre}
                                            </p>
                                            <p className="text-foreground mt-1 text-lg font-bold tabular-nums sm:text-2xl">
                                                {soles(dato.monto)}
                                            </p>
                                            <p className="text-muted-foreground text-xs">
                                                {dato.nota}
                                            </p>
                                        </div>
                                    ))}
                                </section>
                            ) : null}
                            <Card className="border-border bg-card rounded-2xl p-5 shadow-none">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <span className="text-foreground text-[13.5px] font-bold">
                                            Mis cuentas por cobrar
                                        </span>
                                        <p className="text-muted-foreground mt-0.5 text-xs">
                                            Cuotas por cobrar y pagos
                                            registrados hoy
                                        </p>
                                    </div>
                                    <Badge className="text-warning-strong border border-none border-amber-500/20 bg-amber-500/10">
                                        {installmentRows.length}{' '}
                                        {installmentRows.length === 1
                                            ? 'cuota'
                                            : 'cuotas'}
                                    </Badge>
                                </div>

                                <nav
                                    aria-label="Filtrar cuotas por vencimiento"
                                    className="mt-4 flex flex-wrap gap-2"
                                >
                                    {(
                                        [
                                            [
                                                'todas',
                                                'Todas',
                                                resumen?.total.cuotas,
                                            ],
                                            [
                                                'vencidas',
                                                'Vencidas',
                                                resumen?.vencido.cuotas,
                                            ],
                                            [
                                                'semana',
                                                'Vencen en 7 días',
                                                resumen?.semana.cuotas,
                                            ],
                                            [
                                                'luego',
                                                'Más adelante',
                                                undefined,
                                            ],
                                        ] as const
                                    ).map(([clave, nombre, cuenta]) => (
                                        <Link
                                            key={clave}
                                            href={cobranzas.index.url(
                                                teamSlug,
                                                {
                                                    query:
                                                        clave === 'todas'
                                                            ? {}
                                                            : { filtro: clave },
                                                },
                                            )}
                                            preserveScroll
                                            aria-current={
                                                filtro === clave
                                                    ? 'page'
                                                    : undefined
                                            }
                                            className={`flex min-h-10 items-center gap-1.5 rounded-full border px-3.5 text-[13px] font-semibold ${filtro === clave ? 'bg-foreground text-background border-foreground' : 'border-border text-muted-foreground hover:text-foreground'}`}
                                        >
                                            {nombre}
                                            {cuenta !== undefined ? (
                                                <span className="tabular-nums">
                                                    {cuenta}
                                                </span>
                                            ) : null}
                                        </Link>
                                    ))}
                                </nav>

                                <div className="mt-4 overflow-x-auto">
                                    <Table className="w-full border-collapse text-[12.5px]">
                                        <TableHeader>
                                            <TableRow className="border-border border-b">
                                                <TableHead className="text-left">
                                                    Cliente
                                                </TableHead>
                                                <TableHead className="text-left">
                                                    Cuota
                                                </TableHead>
                                                <TableHead className="text-left">
                                                    Saldo por cobrar
                                                </TableHead>
                                                <TableHead className="text-left">
                                                    Vencimiento
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    Acción
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {installmentRows.length === 0 ? (
                                                <TableRow>
                                                    <TableCell
                                                        colSpan={5}
                                                        className="text-muted-foreground px-4 py-12 text-center"
                                                    >
                                                        <div className="flex flex-col items-center justify-center gap-2">
                                                            <CheckCircle2 className="text-success-strong size-8" />
                                                            <p className="text-foreground text-xs font-semibold">
                                                                No tienes cuotas
                                                                pendientes de
                                                                cobro
                                                            </p>
                                                            <p className="text-muted-foreground text-xs">
                                                                Todos los
                                                                clientes de tus
                                                                ventas están al
                                                                día con sus
                                                                pagos.
                                                            </p>
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            ) : (
                                                installmentRows.map((inst) => {
                                                    const pagada =
                                                        Number(inst.saldo) <= 0;
                                                    // Una cuota ya pagada no es deuda vencida.
                                                    const isVencido =
                                                        !pagada &&
                                                        inst.dias_vencido > 0;
                                                    const faltan = diasHasta(
                                                        inst.fecha_vencimiento,
                                                    );
                                                    const pronto =
                                                        !pagada &&
                                                        !isVencido &&
                                                        faltan <= 7;

                                                    return (
                                                        <TableRow
                                                            key={inst.id}
                                                            className="border-border hover:bg-muted/40 border-b transition-colors"
                                                        >
                                                            <TableCell className="px-2.5 py-3.5">
                                                                <div className="text-foreground font-bold">
                                                                    {inst.cliente ||
                                                                        'Cliente'}
                                                                </div>
                                                                <div className="text-muted-foreground font-mono text-xs">
                                                                    Venta:{' '}
                                                                    {inst.sale_numero ||
                                                                        `#${inst.sale_id}`}
                                                                </div>
                                                            </TableCell>

                                                            <TableCell className="text-foreground/80 px-2.5 py-3.5 font-mono text-xs">
                                                                {
                                                                    inst.numero_cuota
                                                                }
                                                            </TableCell>

                                                            <TableCell className="text-foreground px-2.5 py-3.5 font-bold">
                                                                <div>
                                                                    {soles(
                                                                        inst.saldo,
                                                                    )}
                                                                </div>
                                                                <div className="text-muted-foreground text-xs font-normal">
                                                                    Importe
                                                                    original:{' '}
                                                                    {soles(
                                                                        inst.monto,
                                                                    )}
                                                                </div>
                                                                {Number(
                                                                    inst.monto,
                                                                ) >
                                                                Number(
                                                                    inst.saldo,
                                                                ) ? (
                                                                    <div className="mt-2 min-w-36 space-y-1">
                                                                        <p className="text-muted-foreground text-xs">
                                                                            Abonado:{' '}
                                                                            {soles(
                                                                                Math.max(
                                                                                    0,
                                                                                    Number(
                                                                                        inst.monto,
                                                                                    ) -
                                                                                        Number(
                                                                                            inst.saldo,
                                                                                        ),
                                                                                ),
                                                                            )}
                                                                        </p>
                                                                        <Progreso
                                                                            label={`Pago de cuota ${inst.numero_cuota} de ${inst.sale_numero}`}
                                                                            value={Math.max(
                                                                                0,
                                                                                Number(
                                                                                    inst.monto,
                                                                                ) -
                                                                                    Number(
                                                                                        inst.saldo,
                                                                                    ),
                                                                            )}
                                                                            max={Number(
                                                                                inst.monto,
                                                                            )}
                                                                            aria-valuetext={`${soles(Math.max(0, Number(inst.monto) - Number(inst.saldo)))} de ${soles(inst.monto)}`}
                                                                            tone="success"
                                                                        />
                                                                    </div>
                                                                ) : null}
                                                                {inst.anulados.map(
                                                                    (
                                                                        anulado,
                                                                    ) => (
                                                                        <div
                                                                            key={
                                                                                anulado.id
                                                                            }
                                                                            className="text-destructive-strong text-xs font-normal"
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
                                                            </TableCell>

                                                            <TableCell className="px-2.5 py-3.5">
                                                                <Badge
                                                                    className={`rounded-full border-none px-2 py-0.5 text-xs font-bold ${
                                                                        pagada
                                                                            ? 'text-success-strong border border-emerald-500/20 bg-emerald-500/10'
                                                                            : isVencido
                                                                              ? 'bg-destructive/10 text-destructive-strong border-destructive/20 border'
                                                                              : pronto
                                                                                ? 'text-warning-strong border border-amber-500/20 bg-amber-500/10'
                                                                                : 'bg-muted text-muted-foreground border-border border'
                                                                    }`}
                                                                >
                                                                    {pagada
                                                                        ? `Pagada · vencía ${fechaCorta(inst.fecha_vencimiento)}`
                                                                        : isVencido
                                                                          ? `Venció hace ${inst.dias_vencido} ${inst.dias_vencido === 1 ? 'día' : 'días'}`
                                                                          : faltan ===
                                                                              0
                                                                            ? 'Vence hoy'
                                                                            : pronto
                                                                              ? `Vence en ${faltan} ${faltan === 1 ? 'día' : 'días'}`
                                                                              : `Vence ${fechaCorta(inst.fecha_vencimiento)}`}
                                                                </Badge>
                                                            </TableCell>

                                                            <TableCell className="px-2.5 py-3.5 text-right">
                                                                {inst.pagos
                                                                    .filter(
                                                                        (
                                                                            pago,
                                                                        ) =>
                                                                            pago.evidencia_url,
                                                                    )
                                                                    .map(
                                                                        (
                                                                            pago,
                                                                        ) => (
                                                                            <a
                                                                                key={`evidencia-${pago.id}`}
                                                                                href={
                                                                                    pago.evidencia_url ??
                                                                                    undefined
                                                                                }
                                                                                className="text-primary mb-1 block text-xs underline"
                                                                            >
                                                                                Constancia{' '}
                                                                                {
                                                                                    pago.numero_operacion
                                                                                }
                                                                            </a>
                                                                        ),
                                                                    )}
                                                                {inst.pagos
                                                                    .filter(
                                                                        (
                                                                            pago,
                                                                        ) =>
                                                                            pago.puede_anular,
                                                                    )
                                                                    .map(
                                                                        (
                                                                            pago,
                                                                        ) => (
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
                                                                                className="text-destructive-strong mr-1 h-7 rounded-md px-2 text-xs"
                                                                            >
                                                                                Anular
                                                                                cobro
                                                                            </Button>
                                                                        ),
                                                                    )}
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
                                                                    className="bg-foreground text-background hover:bg-foreground/90 h-7 rounded-md px-3 text-xs font-bold shadow-xs transition-colors"
                                                                >
                                                                    {Number(
                                                                        inst.saldo,
                                                                    ) <= 0
                                                                        ? 'Pagada'
                                                                        : 'Registrar pago'}
                                                                </Button>
                                                            </TableCell>
                                                        </TableRow>
                                                    );
                                                })
                                            )}
                                        </TableBody>
                                    </Table>
                                </div>

                                {/* Paginación si existe */}
                                {installments?.links &&
                                    installments.links.length > 3 && (
                                        <div className="border-border text-muted-foreground mt-4 flex flex-wrap items-center justify-between gap-2 border-t pt-3 text-xs">
                                            <span>
                                                Mostrando{' '}
                                                {installments.from ?? 0}-
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
                                                                className={`inline-flex h-7 min-w-[28px] items-center justify-center rounded-md px-2 font-mono text-xs ${
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
                                                                className="text-muted-foreground inline-flex h-7 min-w-[28px] items-center justify-center rounded-md border border-transparent px-2 font-mono text-xs opacity-60"
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
                    ) : null}
                </div>
            </div>

            {/* Dialog: Abrir Turno de Caja */}
            <Dialog
                open={openTurnoDialogOpen}
                onOpenChange={setOpenTurnoDialogOpen}
            >
                <DialogContent className="border-border bg-card rounded-2xl sm:max-w-md">
                    <DialogHeader>
                        <div className="flex items-center gap-2.5">
                            <div className="text-success-strong flex size-9 items-center justify-center rounded-lg border border-emerald-500/20 bg-emerald-500/10">
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
                                <Label className="text-foreground text-sm font-medium">
                                    Sede
                                </Label>
                                <NativeSelect
                                    aria-label="Sede"
                                    value={openForm.data.sede_id}
                                    onChange={(e) =>
                                        openForm.setData(
                                            'sede_id',
                                            e.target.value,
                                        )
                                    }
                                    className="border-border bg-card text-foreground focus-visible:border-ring focus-visible:ring-ring/50 mt-1 h-9 w-full rounded-lg border px-3 text-[13px] outline-none focus-visible:ring-[3px]"
                                >
                                    {sedes.map((s) => (
                                        <option key={s.id} value={s.id}>
                                            {s.nombre}{' '}
                                            {s.tipo ? `(${s.tipo})` : ''}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </div>
                        )}

                        <div>
                            <Label className="text-foreground text-sm font-medium">
                                Fondo inicial (S/)
                            </Label>
                            <Input
                                aria-label="Fondo inicial (S/)"
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
                                className="border-border bg-card mt-1 h-9 rounded-lg font-mono text-[13px]"
                            />
                            {openForm.errors.monto_apertura && (
                                <p className="text-destructive-strong mt-1 text-xs">
                                    {openForm.errors.monto_apertura}
                                </p>
                            )}
                        </div>

                        <DialogFooter className="pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setOpenTurnoDialogOpen(false)}
                                className="border-border rounded-lg text-xs font-semibold"
                            >
                                Cancelar
                            </Button>
                            <Button
                                aria-label="Confirmar apertura"
                                type="submit"
                                loading={openForm.processing}
                                className="rounded-lg bg-emerald-600 text-xs font-bold text-white hover:bg-emerald-700"
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
                <DialogContent className="border-border bg-card rounded-2xl sm:max-w-md">
                    <DialogHeader>
                        <div className="flex items-center gap-2.5">
                            <div className="bg-destructive/10 text-destructive-strong border-destructive/20 flex size-9 items-center justify-center rounded-lg border">
                                <Lock className="size-5" />
                            </div>
                            <div>
                                <DialogTitle className="text-foreground font-['Oswald',sans-serif] text-[18px] font-semibold">
                                    Cerrar turno de caja
                                </DialogTitle>
                                <DialogDescription className="text-muted-foreground text-xs">
                                    {conteoRegistrado
                                        ? 'Tu conteo ya quedó registrado.'
                                        : 'Arqueo ciego: cuenta el efectivo del cajón por billetes y monedas.'}
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
                                        clave !== 'observacion' &&
                                        !clave.startsWith('denominaciones.'),
                                )
                                .map(([, mensaje]) => mensaje)}
                        />
                        {conteoRegistrado ? (
                            <p className="border-border bg-muted/40 text-foreground rounded-lg border p-3 text-[13px]">
                                Ya no se puede cambiar el conteo. Si necesitas
                                volver a contar, pídeselo al Gerente.
                            </p>
                        ) : (
                            <fieldset>
                                <legend className="text-foreground text-sm font-medium">
                                    ¿Cuántos hay de cada uno?
                                </legend>
                                <div className="mt-2 grid grid-cols-2 gap-x-3 gap-y-2 sm:grid-cols-3">
                                    {DENOMINACIONES.map((d) => {
                                        const id = `denominacion-${d}`;
                                        const error = (
                                            closeForm.errors as Record<
                                                string,
                                                string | undefined
                                            >
                                        )[`denominaciones.${d}`];
                                        return (
                                            <div key={d}>
                                                <Label
                                                    htmlFor={id}
                                                    className="text-muted-foreground text-xs"
                                                >
                                                    {Number(d) >= 1000
                                                        ? 'Billete'
                                                        : 'Moneda'}{' '}
                                                    S/{' '}
                                                    {Number(d) >= 100
                                                        ? Number(d) / 100
                                                        : (
                                                              Number(d) / 100
                                                          ).toFixed(2)}
                                                </Label>
                                                <Input
                                                    id={id}
                                                    type="number"
                                                    inputMode="numeric"
                                                    step="1"
                                                    min="0"
                                                    value={
                                                        closeForm.data
                                                            .denominaciones[
                                                            d
                                                        ] ?? ''
                                                    }
                                                    onChange={(e) =>
                                                        closeForm.setData(
                                                            'denominaciones',
                                                            {
                                                                ...closeForm
                                                                    .data
                                                                    .denominaciones,
                                                                [d]: e.target
                                                                    .value,
                                                            },
                                                        )
                                                    }
                                                    placeholder="0"
                                                    aria-invalid={!!error}
                                                    aria-describedby={
                                                        error
                                                            ? `${id}-error`
                                                            : undefined
                                                    }
                                                    className="border-border bg-card mt-0.5 h-9 rounded-lg font-mono text-[13px]"
                                                />
                                                {error && (
                                                    <p
                                                        id={`${id}-error`}
                                                        className="text-destructive-strong mt-1 text-xs"
                                                    >
                                                        {error}
                                                    </p>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                                <p
                                    className="text-foreground mt-3 flex justify-between text-[13px] font-semibold"
                                    aria-live="polite"
                                >
                                    <span>Total contado</span>
                                    <span className="font-mono">
                                        {soles(totalContado)}
                                    </span>
                                </p>
                            </fieldset>
                        )}

                        <div>
                            <Label
                                htmlFor="cierre-observacion"
                                className="text-foreground text-sm font-medium"
                            >
                                {conteoRegistrado
                                    ? 'Explica qué pasó *'
                                    : 'Observación (opcional)'}
                            </Label>
                            <Input
                                id="cierre-observacion"
                                type="text"
                                required={conteoRegistrado}
                                value={closeForm.data.observacion}
                                onChange={(e) =>
                                    closeForm.setData(
                                        'observacion',
                                        e.target.value,
                                    )
                                }
                                placeholder="Ej. Di vuelto de más a un cliente"
                                className="border-border bg-card mt-1 h-9 rounded-lg text-[13px]"
                                aria-invalid={!!closeForm.errors.observacion}
                                aria-describedby={
                                    closeForm.errors.observacion
                                        ? 'cierre-observacion-error'
                                        : undefined
                                }
                            />
                            {closeForm.errors.observacion && (
                                <p
                                    id="cierre-observacion-error"
                                    className="text-destructive-strong mt-1 text-xs font-medium"
                                >
                                    {closeForm.errors.observacion}
                                </p>
                            )}
                        </div>

                        <DialogFooter className="pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setCloseTurnoDialogOpen(false)}
                                className="border-border rounded-lg text-xs font-semibold"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                loading={closeForm.processing}
                                className="bg-primary hover:bg-primary/90 rounded-lg text-xs font-bold text-white"
                            >
                                {conteoRegistrado
                                    ? 'Guardar explicación y cerrar'
                                    : 'Confirmar conteo y cerrar'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Dialog: Salida de dinero */}
            <Dialog open={salidaDialogOpen} onOpenChange={setSalidaDialogOpen}>
                <DialogContent className="sm:max-w-sm">
                    <DialogHeader>
                        <DialogTitle>Registrar salida de dinero</DialogTitle>
                        <DialogDescription>
                            Efectivo que sacas del cajón para un gasto. Se
                            descuenta al cerrar la caja.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitSalida} className="space-y-3">
                        <div>
                            <Label htmlFor="salida-monto">Monto (S/)</Label>
                            <Input
                                id="salida-monto"
                                type="number"
                                inputMode="decimal"
                                step="0.01"
                                min="0.01"
                                required
                                value={salidaForm.data.monto}
                                onChange={(e) =>
                                    salidaForm.setData('monto', e.target.value)
                                }
                                aria-invalid={!!salidaForm.errors.monto}
                                className="mt-1"
                            />
                            {salidaForm.errors.monto && (
                                <p className="text-destructive-strong mt-1 text-xs font-medium">
                                    {salidaForm.errors.monto}
                                </p>
                            )}
                        </div>
                        <div>
                            <Label htmlFor="salida-motivo">
                                ¿Para qué sale?
                            </Label>
                            <Input
                                id="salida-motivo"
                                required
                                maxLength={255}
                                value={salidaForm.data.motivo}
                                onChange={(e) =>
                                    salidaForm.setData('motivo', e.target.value)
                                }
                                placeholder="Ej. Compra de útiles de limpieza"
                                aria-invalid={!!salidaForm.errors.motivo}
                                className="mt-1"
                            />
                            {salidaForm.errors.motivo && (
                                <p className="text-destructive-strong mt-1 text-xs font-medium">
                                    {salidaForm.errors.motivo}
                                </p>
                            )}
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setSalidaDialogOpen(false)}
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                loading={salidaForm.processing}
                            >
                                Guardar salida
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
                <DialogContent className="border-border bg-card rounded-2xl sm:max-w-md">
                    <DialogHeader>
                        <div className="flex items-center gap-2.5">
                            <div className="border-border bg-muted text-foreground flex size-9 items-center justify-center rounded-lg border">
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
                        {Number(
                            selectedInstallment?.saldo_detraccion_venta ?? 0,
                        ) > 0 && (
                            <p className="border-border text-muted-foreground rounded-lg border p-3 text-xs">
                                Pendiente de la venta: pago comercial{' '}
                                {soles(
                                    selectedInstallment?.saldo_comercial_venta ??
                                        0,
                                )}{' '}
                                · detracción BN{' '}
                                {soles(
                                    selectedInstallment?.saldo_detraccion_venta ??
                                        0,
                                )}
                                . Estos importes corresponden a toda la venta;
                                el abono se aplica a esta cuota.
                            </p>
                        )}
                        {selectedInstallment?.con_detraccion && (
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={paymentForm.data.es_detraccion}
                                    onChange={(e) =>
                                        paymentForm.setData({
                                            ...paymentForm.data,
                                            es_detraccion: e.target.checked,
                                            forma_pago: e.target.checked
                                                ? 'deposito'
                                                : 'efectivo',
                                            monto: String(
                                                Math.min(
                                                    Number(
                                                        selectedInstallment?.saldo ??
                                                            0,
                                                    ),
                                                    e.target.checked
                                                        ? (selectedInstallment?.saldo_detraccion_venta ??
                                                              0)
                                                        : (selectedInstallment?.saldo_comercial_venta ??
                                                              Number(
                                                                  selectedInstallment?.saldo ??
                                                                      0,
                                                              )),
                                                ),
                                            ),
                                            fecha_deposito: '',
                                            evidencia: null,
                                        })
                                    }
                                />
                                Acreditar constancia de detracción (Banco de la
                                Nación)
                            </label>
                        )}
                        {paymentForm.data.es_detraccion && (
                            <p className="text-muted-foreground text-xs">
                                Registra únicamente el depósito verificado.
                                Reduce la deuda y se conserva fuera de los
                                ingresos de caja. Ingresa la fecha de la
                                constancia; no se verifica automáticamente con
                                el banco.
                            </p>
                        )}
                        {paymentForm.data.es_detraccion && (
                            <>
                                <div>
                                    <Label htmlFor="fecha-deposito-cuota">
                                        Fecha real del depósito *
                                    </Label>
                                    <Input
                                        id="fecha-deposito-cuota"
                                        type="date"
                                        required
                                        value={paymentForm.data.fecha_deposito}
                                        onChange={(event) =>
                                            paymentForm.setData(
                                                'fecha_deposito',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    {paymentForm.errors.fecha_deposito && (
                                        <p
                                            role="alert"
                                            className="text-destructive-strong text-xs"
                                        >
                                            {paymentForm.errors.fecha_deposito}
                                        </p>
                                    )}
                                </div>
                                <div>
                                    <Label htmlFor="evidencia-cuota">
                                        Constancia PDF o foto (opcional, hasta 5
                                        MB)
                                    </Label>
                                    <Input
                                        id="evidencia-cuota"
                                        type="file"
                                        accept="application/pdf,image/jpeg,image/png"
                                        onChange={(event) =>
                                            paymentForm.setData(
                                                'evidencia',
                                                event.target.files?.[0] ?? null,
                                            )
                                        }
                                    />
                                    {paymentForm.errors.evidencia && (
                                        <p
                                            role="alert"
                                            className="text-destructive-strong text-xs"
                                        >
                                            {paymentForm.errors.evidencia}
                                        </p>
                                    )}
                                </div>
                            </>
                        )}
                        <div>
                            <Label className="text-foreground text-sm font-medium">
                                Forma de pago
                            </Label>
                            <NativeSelect
                                aria-label="Forma de pago"
                                disabled={paymentForm.data.es_detraccion}
                                value={paymentForm.data.forma_pago}
                                onChange={(e) =>
                                    paymentForm.setData(
                                        'forma_pago',
                                        e.target.value,
                                    )
                                }
                                className="border-border bg-card text-foreground focus-visible:border-ring focus-visible:ring-ring/50 mt-1 h-9 w-full rounded-lg border px-3 text-[13px] outline-none focus-visible:ring-[3px]"
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
                            </NativeSelect>
                        </div>

                        <div>
                            <Label className="text-foreground text-sm font-medium">
                                Monto a pagar (S/) *
                            </Label>
                            <Input
                                aria-label="Monto"
                                type="number"
                                step="0.01"
                                min="0.01"
                                max={Math.min(
                                    Number(selectedInstallment?.saldo ?? 0),
                                    paymentForm.data.es_detraccion
                                        ? (selectedInstallment?.saldo_detraccion_venta ??
                                              0)
                                        : (selectedInstallment?.saldo_comercial_venta ??
                                              Number(
                                                  selectedInstallment?.saldo ??
                                                      0,
                                              )),
                                )}
                                required
                                value={paymentForm.data.monto}
                                onChange={(e) =>
                                    paymentForm.setData('monto', e.target.value)
                                }
                                className="border-border bg-card mt-1 h-9 rounded-lg font-mono text-[13px]"
                            />
                            {paymentForm.errors.monto && (
                                <p className="text-destructive-strong mt-1 text-xs">
                                    {paymentForm.errors.monto}
                                </p>
                            )}
                        </div>

                        {paymentForm.data.forma_pago !== 'efectivo' && (
                            <div>
                                <Label className="text-foreground text-sm font-medium">
                                    N° Operación / Referencia
                                </Label>
                                <Input
                                    aria-label="Número de operación"
                                    type="text"
                                    value={paymentForm.data.numero_operacion}
                                    onChange={(e) =>
                                        paymentForm.setData(
                                            'numero_operacion',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Ej. OP-983412"
                                    className="border-border bg-card mt-1 h-9 rounded-lg font-mono text-[13px]"
                                />
                                {paymentForm.errors.numero_operacion && (
                                    <p
                                        role="alert"
                                        className="text-destructive-strong text-xs"
                                    >
                                        {paymentForm.errors.numero_operacion}
                                    </p>
                                )}
                            </div>
                        )}

                        <DialogFooter className="pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setSelectedInstallment(null)}
                                className="border-border rounded-lg text-xs font-semibold"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                loading={paymentForm.processing}
                                className="bg-foreground text-background hover:bg-foreground/90 rounded-lg text-xs font-bold shadow-xs transition-colors"
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
                <DialogContent className="border-border bg-card rounded-2xl sm:max-w-md">
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
                                loading={cancelForm.processing}
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
