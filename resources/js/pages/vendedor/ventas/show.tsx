import { Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Award,
    Banknote,
    CalendarDays,
    Clock3,
    FileText,
    PencilLine,
    ReceiptText,
    Send,
    TriangleAlert,
    ShieldCheck,
    XCircle,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

import { CargaLarga } from '@/components/cargando';
import CambiarUnidadDialog, {
    type LineaConSerie,
} from '@/components/cambiar-unidad-dialog';
import ComprobanteEditDialog, {
    type ComprobanteClient,
} from '@/components/comprobante-edit-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { SaleNotesPanel } from '@/components/sale-notes-panel';
import VendedorLayout from '@/layouts/vendedor-layout';
import facturacion from '@/routes/vendedor/facturacion';
import ventas from '@/routes/vendedor/ventas';
import type { Team } from '@/types';

type SaleItem = {
    id: number;
    product_id?: number | null;
    tipo_linea?: string;
    numero_serie?: string;
    cantidad?: number;
    precio_unitario?: number | string;
    descuento?: number | string;
    subtotal?: number | string;
    product?: { nombre?: string };
    service?: { nombre?: string };
};

type SalePayment = {
    id: number;
    forma_pago?: string;
    monto?: number | string;
    fecha_pago?: string;
    estado?: string;
};

type ElectronicDocument = {
    id: number;
    tipo?: string;
    serie?: string;
    correlativo?: number;
    motivo_catalogo?: string | null;
    importe?: number | string | null;
    sunat_estado?: string;
    sunat_mensaje?: string;
    enviar_desde?: string | null;
    pdf_path?: string | null;
};

type Sale = {
    id: number;
    numero_interno?: string;
    numero_nota_venta?: string | null;
    fecha?: string;
    comprobante_tipo?: string;
    condicion_pago?: string;
    referencia?: string | null;
    sede_id?: number | null;
    destino?: string;
    subtotal?: number | string;
    igv?: number | string;
    total?: number | string;
    estado?: string;
    client: ComprobanteClient;
    items?: SaleItem[];
    payments?: SalePayment[];
    electronic_documents?: ElectronicDocument[];
};

type CertificadoVenta = {
    id: number;
    numero: string;
    tipo: string;
    tipo_codigo: string;
    revision: number;
    estado: string;
    referencia: string | null;
    unidades: number;
};

type Props = {
    sale: Sale;
    clientesVarios: ComprobanteClient;
    limiteBoletaSinIdentificar: number;
    certificados: CertificadoVenta[];
    tieneEquipos: boolean;
    tiposServicio: { codigo: string; nombre: string }[];
};

function money(value?: number | string) {
    return new Intl.NumberFormat('es-PE', {
        style: 'currency',
        currency: 'PEN',
    }).format(Number(value ?? 0) || 0);
}

const MEDIOS_PAGO: Record<string, string> = {
    efectivo: 'Efectivo',
    yape: 'Yape',
    plin: 'Plin',
    transferencia: 'Transferencia',
    pos: 'Tarjeta',
    deposito: 'Depósito',
};

function medioPago(value?: string) {
    return value ? (MEDIOS_PAGO[value] ?? nice(value)) : '-';
}

function nice(value?: string) {
    return value ? value.replaceAll('_', ' ') : '-';
}

function horaDeEnvio(value?: string | null) {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('es-PE', {
        weekday: 'short',
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

export default function VentasShow({
    sale,
    clientesVarios,
    limiteBoletaSinIdentificar,
    certificados,
    tieneEquipos,
    tiposServicio,
}: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug =
        currentTeam?.slug ??
        (typeof window !== 'undefined'
            ? window.location.pathname.split('/')[1]
            : '');
    const [confirmando, setConfirmando] = useState(false);
    const [editando, setEditando] = useState(false);
    const [lineaACambiar, setLineaACambiar] = useState<LineaConSerie | null>(
        null,
    );
    const puedeCambiarExtintor =
        sale.estado === 'borrador' || sale.estado === 'confirmada';
    const [procesando, setProcesando] = useState<
        'enviar' | 'anular' | 'corregir' | null
    >(null);
    const documento = (sale.electronic_documents ?? [])
        .filter((d) => d.tipo === 'factura' || d.tipo === 'boleta')
        .at(-1);
    const porEnviar = documento?.sunat_estado === 'por_enviar';
    const rechazado =
        documento?.sunat_estado === 'rechazado' ||
        documento?.sunat_estado === 'excepcion';
    const ventaActiva = sale.estado === 'confirmada';
    const aceptado =
        documento?.sunat_estado === 'aceptado' ||
        documento?.sunat_estado === 'observado';
    const [abrirNotaCredito, setAbrirNotaCredito] = useState(0);

    // Desde la lista, el lápiz llega con ?corregir=1: se abre directo lo que
    // corresponde (editar si aún no se envió, nota de crédito si ya se aceptó).
    useEffect(() => {
        if (
            typeof window === 'undefined' ||
            !new URLSearchParams(window.location.search).has('corregir')
        ) {
            return;
        }

        if (porEnviar || rechazado) {
            setEditando(true);
        } else if (aceptado) {
            setAbrirNotaCredito((n) => n + 1);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    function accion(tipo: 'enviar' | 'anular' | 'corregir') {
        const avisos = {
            anular: 'Se anula la venta, las unidades vuelven al stock y el número del comprobante se libera. ¿Continuar?',
            corregir:
                'Se anula este comprobante (aún no llegó a SUNAT, el número se libera) y se abre una copia con todo lleno para que corrijas productos o precios y vuelvas a emitir. ¿Continuar?',
            enviar: null,
        };
        const aviso = avisos[tipo];

        if (aviso && !window.confirm(aviso)) {
            return;
        }

        setProcesando(tipo);
        router.post(
            {
                enviar: ventas.enviarSunat,
                anular: ventas.anular,
                corregir: ventas.corregirProductos,
            }[tipo].url({
                current_team: teamSlug,
                sale: sale.id,
            }),
            {},
            {
                preserveScroll: true,
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ??
                            'No se pudo completar la acción.',
                    ),
                onFinish: () => setProcesando(null),
            },
        );
    }

    const [descartando, setDescartando] = useState(false);

    function descartarVenta() {
        const texto =
            sale.estado === 'borrador'
                ? 'Se descarta el borrador y sus unidades vuelven al stock. ¿Continuar?'
                : 'Se anula la nota de venta, sus unidades vuelven al stock y sus certificados se anulan. ¿Continuar?';

        if (!window.confirm(texto)) {
            return;
        }

        setDescartando(true);
        router.post(
            ventas.descartar.url({ current_team: teamSlug, sale: sale.id }),
            {},
            {
                preserveScroll: true,
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ??
                            'No se pudo descartar la venta.',
                    ),
                onFinish: () => setDescartando(false),
            },
        );
    }

    function confirmarVenta() {
        setConfirmando(true);
        router.post(
            ventas.confirmar.url({ current_team: teamSlug, sale: sale.id }),
            {},
            {
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ??
                            'No se pudo confirmar la venta.',
                    ),
                onFinish: () => setConfirmando(false),
            },
        );
    }

    return (
        <VendedorLayout title="Detalle venta">
            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap items-center gap-3">
                    <Button
                        asChild
                        variant="outline"
                        className="border-border bg-card text-foreground/80 h-9 rounded-[9px] shadow-none"
                    >
                        <Link href={ventas.index(teamSlug)}>
                            <ArrowLeft className="size-4" />
                            Ventas
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-foreground font-['Oswald',sans-serif] text-[24px] font-semibold tracking-[0.02em] uppercase">
                            {sale.numero_interno ?? `Venta ${sale.id}`}
                        </h1>
                        <p className="text-muted-foreground text-[12.5px]">
                            {sale.client?.razon_social ?? 'Cliente sin datos'}
                            {sale.referencia ? ` · ${sale.referencia}` : ''}
                        </p>
                    </div>
                    <div className="flex-1" />
                    {sale.estado === 'borrador' ||
                    (sale.estado === 'confirmada' &&
                        sale.comprobante_tipo === 'nota_venta') ? (
                        <Button
                            type="button"
                            variant="outline"
                            disabled={descartando}
                            onClick={descartarVenta}
                            className="border-border bg-card text-destructive-strong h-9 rounded-[9px] shadow-none"
                        >
                            <XCircle className="size-4" />
                            {sale.estado === 'borrador'
                                ? 'Descartar borrador'
                                : 'Anular nota de venta'}
                        </Button>
                    ) : null}
                    {sale.estado === 'borrador' && (
                        <Button
                            asChild
                            variant="outline"
                            className="border-border bg-card text-foreground h-9 rounded-[9px] shadow-none"
                        >
                            <Link
                                href={ventas.edit.url({
                                    current_team: teamSlug,
                                    sale: sale.id,
                                })}
                            >
                                <PencilLine className="size-4" />
                                Editar
                            </Link>
                        </Button>
                    )}
                    {sale.estado === 'borrador' && (
                        <Button
                            onClick={confirmarVenta}
                            disabled={confirmando}
                            className="h-9 rounded-[9px] bg-emerald-600 text-white shadow-none hover:bg-emerald-700"
                        >
                            {confirmando
                                ? 'Emitiendo…'
                                : sale.comprobante_tipo === 'nota_venta'
                                  ? 'Emitir nota de venta'
                                  : `Emitir ${sale.comprobante_tipo === 'boleta' ? 'boleta' : 'factura'}`}
                        </Button>
                    )}
                    {sale.estado === 'anulada' && (
                        <Button
                            asChild
                            className="bg-primary hover:bg-primary/90 h-9 rounded-[9px] text-white shadow-none"
                        >
                            <Link
                                href={ventas.create.url(teamSlug, {
                                    query: { rehacer: sale.id },
                                })}
                            >
                                <PencilLine className="size-4" />
                                Rehacer venta
                            </Link>
                        </Button>
                    )}
                    <Badge className="text-success-strong rounded-full border-transparent bg-emerald-500/10 px-3 py-1 text-[10.5px] font-bold capitalize dark:text-emerald-400">
                        {sale.estado ?? 'registrada'}
                    </Badge>
                    {sale.numero_nota_venta && (
                        <Button
                            asChild
                            variant="outline"
                            className="border-border bg-card text-foreground/80 h-9 rounded-[9px] shadow-none"
                        >
                            <a
                                href={`/${teamSlug}/vendedor/ventas/${sale.id}/nota-venta-pdf`}
                                target="_blank"
                                rel="noreferrer"
                            >
                                {sale.numero_nota_venta} · PDF
                            </a>
                        </Button>
                    )}
                    {documento && (
                        <Badge className="rounded-full border-transparent bg-blue-500/10 px-3 py-1 text-[10.5px] font-bold text-blue-600 capitalize dark:text-blue-400">
                            SUNAT: {documento.serie}-{documento.correlativo} ·{' '}
                            {nice(documento.sunat_estado)}
                        </Badge>
                    )}
                    {documento?.pdf_path && (
                        <Button
                            asChild
                            variant="outline"
                            className="border-border bg-card text-foreground/80 h-9 rounded-[9px] shadow-none"
                        >
                            <a
                                href={facturacion.pdf.url({
                                    current_team: teamSlug,
                                    electronic_document: documento.id,
                                })}
                            >
                                <FileText className="size-4" />
                                PDF
                            </a>
                        </Button>
                    )}
                </div>

                {documento && ventaActiva && (porEnviar || rechazado) ? (
                    <Card
                        className={`flex flex-col gap-3 rounded-[14px] p-4 shadow-none sm:flex-row sm:items-center ${rechazado ? 'border-destructive/40 bg-destructive/5' : 'border-amber-500/40 bg-amber-500/5'}`}
                    >
                        <div className="flex flex-1 items-start gap-3">
                            {rechazado ? (
                                <TriangleAlert className="text-destructive-strong mt-0.5 size-5 shrink-0" />
                            ) : (
                                <Clock3 className="text-warning-strong mt-0.5 size-5 shrink-0" />
                            )}
                            <div>
                                <div className="text-foreground text-[13.5px] font-bold">
                                    {rechazado
                                        ? `SUNAT rechazó ${documento.serie}-${documento.correlativo}`
                                        : `${documento.serie}-${documento.correlativo} por enviar a SUNAT`}
                                </div>
                                <p className="text-muted-foreground text-[12px]">
                                    {rechazado
                                        ? documento.sunat_mensaje
                                        : `Se envía automáticamente el ${horaDeEnvio(documento.enviar_desde)}. Hasta entonces puedes corregirlo sin nota de crédito.`}
                                </p>
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setEditando(true)}
                                className="border-border bg-card text-foreground h-9 rounded-[9px] shadow-none"
                            >
                                <PencilLine className="size-4" />
                                {rechazado
                                    ? 'Corregir y reemitir'
                                    : 'Editar comprobante'}
                            </Button>
                            {porEnviar ? (
                                <>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={procesando !== null}
                                        onClick={() => accion('corregir')}
                                        className="border-border bg-card text-foreground h-9 rounded-[9px] shadow-none"
                                    >
                                        <PencilLine className="size-4" />
                                        Corregir productos o precios
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={procesando !== null}
                                        onClick={() => accion('anular')}
                                        className="border-border bg-card text-destructive-strong h-9 rounded-[9px] shadow-none"
                                    >
                                        <XCircle className="size-4" />
                                        Anular venta
                                    </Button>
                                    <Button
                                        type="button"
                                        disabled={procesando !== null}
                                        onClick={() => accion('enviar')}
                                        className="bg-primary hover:bg-primary/90 h-9 rounded-[9px] text-white shadow-none"
                                    >
                                        <Send className="size-4" />
                                        {procesando === 'enviar'
                                            ? 'Enviando…'
                                            : 'Enviar ya'}
                                    </Button>
                                </>
                            ) : null}
                        </div>
                    </Card>
                ) : null}

                {documento && ventaActiva && aceptado ? (
                    <Card className="flex-row flex-wrap items-center justify-between gap-3 rounded-[14px] border-emerald-500/30 bg-emerald-500/5 p-4 shadow-none">
                        <div className="flex items-start gap-3">
                            <ShieldCheck className="text-success-strong mt-0.5 size-5 shrink-0" />
                            <div>
                                <div className="text-foreground text-[13.5px] font-bold">
                                    {documento.serie}-{documento.correlativo}{' '}
                                    aceptada por SUNAT
                                </div>
                                <p className="text-muted-foreground text-[12px]">
                                    Ya no se puede editar. Para corregir el
                                    monto, el cliente o los productos se emite
                                    una nota de crédito. Si solo cambias un
                                    extintor por otro igual, usa «Cambiar» junto
                                    a su serie: no necesita nota.
                                </p>
                            </div>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setAbrirNotaCredito((n) => n + 1)}
                            className="border-border bg-card text-foreground h-9 rounded-[9px] shadow-none"
                        >
                            <PencilLine className="size-4" />
                            Corregir con nota de crédito
                        </Button>
                    </Card>
                ) : null}

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card className="border-border bg-card gap-1 rounded-[14px] p-4 shadow-none">
                        <CalendarDays className="text-warning-strong size-5" />
                        <div className="text-muted-foreground text-[11px] font-bold uppercase">
                            Fecha
                        </div>
                        <div className="font-['IBM_Plex_Mono',monospace] text-[13px] font-bold">
                            {sale.fecha ?? '-'}
                        </div>
                    </Card>
                    <Card className="border-border bg-card gap-1 rounded-[14px] p-4 shadow-none">
                        <ReceiptText className="size-5 text-blue-600 dark:text-blue-400" />
                        <div className="text-muted-foreground text-[11px] font-bold uppercase">
                            Comprobante
                        </div>
                        <div className="font-['IBM_Plex_Mono',monospace] text-[13px] font-bold uppercase">
                            {nice(sale.comprobante_tipo)}
                        </div>
                    </Card>
                    <Card className="border-border bg-card gap-1 rounded-[14px] p-4 shadow-none">
                        <Banknote className="text-success-strong size-5" />
                        <div className="text-muted-foreground text-[11px] font-bold uppercase">
                            Condición
                        </div>
                        <div className="text-[13px] font-bold capitalize">
                            {nice(sale.condicion_pago)}
                        </div>
                    </Card>
                    <Card className="border-border bg-card gap-1 rounded-[14px] p-4 shadow-none">
                        <div className="text-muted-foreground text-[11px] font-bold uppercase">
                            Total
                        </div>
                        <div className="font-['Oswald',sans-serif] text-[25px] font-semibold">
                            {money(sale.total)}
                        </div>
                    </Card>
                </div>

                <Card className="border-border bg-card gap-0 rounded-[16px] p-5 shadow-none">
                    <h2 className="mb-3 font-['Oswald',sans-serif] text-[18px] font-semibold uppercase">
                        Items
                    </h2>
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-[12.5px]">
                            <thead>
                                <tr>
                                    {[
                                        'Producto',
                                        'Serie',
                                        'Tipo',
                                        'Cant.',
                                        'P. Unit.',
                                        'Desc.',
                                        'Subtotal',
                                    ].map((h) => (
                                        <th
                                            key={h}
                                            className="border-border text-muted-foreground border-b px-2.5 py-2.5 text-left font-['IBM_Plex_Mono',monospace] text-[9.5px] font-bold tracking-[0.05em] uppercase"
                                        >
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {(sale.items ?? []).map((item) => (
                                    <tr key={item.id}>
                                        <td className="border-border border-b px-2.5 py-[13px] font-semibold">
                                            {item.product?.nombre ??
                                                item.service?.nombre ??
                                                '-'}
                                        </td>
                                        <td className="border-border border-b px-2.5 py-[13px] font-['IBM_Plex_Mono',monospace] whitespace-nowrap tabular-nums">
                                            {item.numero_serie ?? '-'}
                                            {puedeCambiarExtintor &&
                                            item.tipo_linea ===
                                                'unidad_nueva' &&
                                            item.numero_serie &&
                                            item.product_id ? (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setLineaACambiar({
                                                            id: item.id,
                                                            product_id:
                                                                item.product_id!,
                                                            nombre:
                                                                item.product
                                                                    ?.nombre ??
                                                                '',
                                                            numero_serie:
                                                                item.numero_serie!,
                                                        })
                                                    }
                                                    className="text-primary-strong ml-2 font-sans text-[11.5px] font-semibold hover:underline"
                                                >
                                                    Cambiar
                                                </button>
                                            ) : null}
                                        </td>
                                        <td className="border-border border-b px-2.5 py-[13px] capitalize">
                                            {nice(item.tipo_linea)}
                                        </td>
                                        <td className="border-border border-b px-2.5 py-[13px]">
                                            {item.cantidad ?? 1}
                                        </td>
                                        <td className="border-border border-b px-2.5 py-[13px]">
                                            {money(item.precio_unitario)}
                                        </td>
                                        <td className="border-border border-b px-2.5 py-[13px]">
                                            {money(item.descuento)}
                                        </td>
                                        <td className="border-border border-b px-2.5 py-[13px] font-bold">
                                            {money(item.subtotal)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>

                <Card className="border-border bg-card gap-0 rounded-[16px] p-5 shadow-none">
                    <h2 className="mb-3 font-['Oswald',sans-serif] text-[18px] font-semibold uppercase">
                        Pagos
                    </h2>
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-[12.5px]">
                            <thead>
                                <tr>
                                    {['Forma', 'Fecha', 'Monto', 'Estado'].map(
                                        (h) => (
                                            <th
                                                key={h}
                                                className="border-border text-muted-foreground border-b px-2.5 py-2.5 text-left font-['IBM_Plex_Mono',monospace] text-[9.5px] font-bold tracking-[0.05em] uppercase"
                                            >
                                                {h}
                                            </th>
                                        ),
                                    )}
                                </tr>
                            </thead>
                            <tbody>
                                {(sale.payments ?? []).length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={4}
                                            className="text-muted-foreground px-2.5 py-8 text-center"
                                        >
                                            Sin pagos registrados
                                        </td>
                                    </tr>
                                ) : (
                                    (sale.payments ?? []).map((payment) => (
                                        <tr key={payment.id}>
                                            <td className="border-border border-b px-2.5 py-[13px] capitalize">
                                                {medioPago(payment.forma_pago)}
                                            </td>
                                            <td className="border-border border-b px-2.5 py-[13px]">
                                                {payment.fecha_pago ?? '-'}
                                            </td>
                                            <td className="border-border border-b px-2.5 py-[13px] font-bold">
                                                {money(payment.monto)}
                                            </td>
                                            <td className="border-border border-b px-2.5 py-[13px] capitalize">
                                                {payment.estado ?? '-'}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </Card>

                {sale.estado === 'confirmada' || certificados.length > 0 ? (
                    <Card className="border-border bg-card gap-3 rounded-[16px] p-5 shadow-none">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div className="flex items-center gap-2">
                                <Award className="text-primary-strong size-5" />
                                <h2 className="text-foreground font-['Oswald',sans-serif] text-[18px] font-semibold uppercase">
                                    Certificados
                                </h2>
                            </div>
                            {sale.estado === 'confirmada' ? (
                                <Button
                                    asChild
                                    className="bg-primary hover:bg-primary/90 h-9 rounded-[9px] text-white shadow-none"
                                >
                                    <Link
                                        href={ventas.certificados.create.url({
                                            current_team: teamSlug,
                                            sale: sale.id,
                                        })}
                                    >
                                        <Award className="size-4" />
                                        {certificados.some(
                                            (c) => c.estado === 'vigente',
                                        )
                                            ? 'Ajustar orden o grupos'
                                            : 'Armar certificados'}
                                    </Link>
                                </Button>
                            ) : null}
                        </div>
                        {sale.estado === 'confirmada' &&
                        tiposServicio.length > 0 ? (
                            <div className="flex flex-wrap items-center gap-1.5">
                                <span className="text-muted-foreground text-[12px]">
                                    Certificado de servicio:
                                </span>
                                {tiposServicio.map((tipo) => (
                                    <Link
                                        key={tipo.codigo}
                                        href={ventas.certificadoServicio.create.url(
                                            {
                                                current_team: teamSlug,
                                                sale: sale.id,
                                                tipo: tipo.codigo,
                                            },
                                        )}
                                        className="border-border hover:border-primary/60 hover:text-primary-strong rounded-full border px-2.5 py-1 text-[11.5px] font-bold transition-colors"
                                    >
                                        {tipo.nombre}
                                    </Link>
                                ))}
                            </div>
                        ) : null}
                        {certificados.length === 0 ? (
                            <p className="text-muted-foreground text-[12.5px]">
                                {tieneEquipos
                                    ? 'Salen solos al confirmar la venta: Operatividad y Garantía más Capacitación (local) o Prueba Hidrostática (vehículo).'
                                    : 'Esta venta no tiene extintores con serie; puedes emitir el certificado de capacitación.'}
                            </p>
                        ) : (
                            <div className="border-border overflow-hidden rounded-[10px] border">
                                {certificados.map((c) => (
                                    <div
                                        key={c.id}
                                        className={`border-border flex flex-wrap items-center justify-between gap-2 border-b px-3 py-2.5 last:border-b-0 ${c.estado === 'vigente' ? '' : 'opacity-50'}`}
                                    >
                                        <div>
                                            <span className="text-foreground font-['IBM_Plex_Mono',monospace] text-[12.5px] font-bold">
                                                {c.numero}
                                            </span>
                                            <span className="text-muted-foreground ml-2 text-[12px]">
                                                {c.tipo}
                                                {c.referencia
                                                    ? ` · ${c.referencia}`
                                                    : ''}
                                                {c.unidades > 0
                                                    ? ` · ${c.unidades} extintor(es)`
                                                    : ''}
                                                {c.revision > 0
                                                    ? ` · Rev. ${c.revision}`
                                                    : ''}
                                                {c.estado !== 'vigente'
                                                    ? ` · ${c.estado}`
                                                    : ''}
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-1.5">
                                            <Button
                                                asChild
                                                variant="outline"
                                                size="sm"
                                                className="border-border bg-card h-8 rounded-[8px] shadow-none"
                                            >
                                                <a
                                                    href={`/${teamSlug}/vendedor/certificados/${c.id}/pdf?inline=1`}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    <FileText className="size-3.5" />
                                                    Ver e imprimir
                                                </a>
                                            </Button>
                                            <Button
                                                asChild
                                                variant="outline"
                                                size="sm"
                                                className="border-border bg-card h-8 rounded-[8px] shadow-none"
                                                title="Descargar Word editable"
                                            >
                                                <a
                                                    href={`/${teamSlug}/vendedor/certificados/${c.id}/word`}
                                                >
                                                    <FileText className="size-3.5 text-[#2b579a]" />
                                                    Word
                                                </a>
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </Card>
                ) : null}

                <SaleNotesPanel
                    teamSlug={teamSlug}
                    documents={sale.electronic_documents ?? []}
                    saleEstado={sale.estado}
                    abrirNotaCredito={abrirNotaCredito}
                />
            </div>

            {documento && (porEnviar || rechazado) ? (
                <ComprobanteEditDialog
                    open={editando}
                    onOpenChange={setEditando}
                    teamSlug={teamSlug}
                    saleId={sale.id}
                    total={Number(sale.total ?? 0)}
                    tipoActual={
                        documento.tipo === 'factura' ? 'factura' : 'boleta'
                    }
                    clienteActual={sale.client}
                    clientesVarios={clientesVarios}
                    limiteBoletaSinIdentificar={limiteBoletaSinIdentificar}
                    rechazado={rechazado}
                />
            ) : null}

            <CargaLarga
                activo={confirmando}
                titulo={
                    sale.comprobante_tipo === 'nota_venta'
                        ? 'Confirmando la nota de venta'
                        : 'Confirmando la venta y generando el comprobante'
                }
            />
            <CargaLarga
                activo={procesando === 'enviar'}
                titulo="Enviando el comprobante a SUNAT"
                detalle="Suele tardar menos de 10 segundos"
            />
            <CargaLarga
                activo={procesando === 'anular'}
                titulo="Anulando la venta"
                detalle="Las unidades vuelven al stock"
            />

            <CambiarUnidadDialog
                teamSlug={teamSlug}
                saleId={sale.id}
                sedeId={sale.sede_id ?? null}
                linea={lineaACambiar}
                onClose={() => setLineaACambiar(null)}
            />
        </VendedorLayout>
    );
}
