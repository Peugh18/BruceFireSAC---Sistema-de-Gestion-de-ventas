import { Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Banknote,
    CalendarDays,
    Clock3,
    FileText,
    PencilLine,
    ReceiptText,
    Send,
    TriangleAlert,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

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

type Props = {
    sale: Sale;
    clientesVarios: ComprobanteClient;
    limiteBoletaSinIdentificar: number;
};

function money(value?: number | string) {
    return new Intl.NumberFormat('es-PE', {
        style: 'currency',
        currency: 'PEN',
    }).format(Number(value ?? 0) || 0);
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
}: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug =
        currentTeam?.slug ??
        (typeof window !== 'undefined'
            ? window.location.pathname.split('/')[1]
            : '');
    const [confirmando, setConfirmando] = useState(false);
    const [editando, setEditando] = useState(false);
    const [procesando, setProcesando] = useState<'enviar' | 'anular' | null>(
        null,
    );
    const documento = (sale.electronic_documents ?? [])
        .filter((d) => d.tipo === 'factura' || d.tipo === 'boleta')
        .at(-1);
    const porEnviar = documento?.sunat_estado === 'por_enviar';
    const rechazado =
        documento?.sunat_estado === 'rechazado' ||
        documento?.sunat_estado === 'excepcion';
    const ventaActiva = sale.estado === 'confirmada';

    function accion(tipo: 'enviar' | 'anular') {
        if (
            tipo === 'anular' &&
            !window.confirm(
                'Se anula la venta, las unidades vuelven al stock y el número del comprobante se libera. ¿Continuar?',
            )
        ) {
            return;
        }

        setProcesando(tipo);
        router.post(
            (tipo === 'enviar' ? ventas.enviarSunat : ventas.anular).url({
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
                        </p>
                    </div>
                    <div className="flex-1" />
                    {sale.estado === 'borrador' && (
                        <Button
                            onClick={confirmarVenta}
                            disabled={confirmando}
                            className="h-9 rounded-[9px] bg-emerald-600 text-white shadow-none hover:bg-emerald-700"
                        >
                            {confirmando
                                ? 'Confirmando…'
                                : sale.comprobante_tipo === 'nota_venta'
                                  ? 'Confirmar nota de venta'
                                  : 'Confirmar y emitir'}
                        </Button>
                    )}
                    <Badge className="rounded-full border-transparent bg-emerald-500/10 px-3 py-1 text-[10.5px] font-bold text-emerald-600 capitalize dark:text-emerald-400">
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
                                <TriangleAlert className="text-destructive mt-0.5 size-5 shrink-0" />
                            ) : (
                                <Clock3 className="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-400" />
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
                                        onClick={() => accion('anular')}
                                        className="border-border bg-card text-destructive h-9 rounded-[9px] shadow-none"
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

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card className="border-border bg-card gap-1 rounded-[14px] p-4 shadow-none">
                        <CalendarDays className="size-5 text-amber-600 dark:text-amber-400" />
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
                        <Banknote className="size-5 text-emerald-600 dark:text-emerald-400" />
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
                                        <td className="border-border border-b px-2.5 py-[13px] font-['IBM_Plex_Mono',monospace]">
                                            {item.numero_serie ?? '-'}
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
                                                {nice(payment.forma_pago)}
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

                <SaleNotesPanel
                    teamSlug={teamSlug}
                    documents={sale.electronic_documents ?? []}
                    saleEstado={sale.estado}
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
        </VendedorLayout>
    );
}
