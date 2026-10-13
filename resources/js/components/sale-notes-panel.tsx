import { Link, useForm, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Ban,
    CheckCircle2,
    FilePlus2,
    X,
    Inbox,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import facturacion from '@/routes/vendedor/facturacion';
import ventas from '@/routes/vendedor/ventas';
import ArchivosNota from '@/components/archivos-nota';
import { Empty } from '@/components/ui/empty';

type SaleDocument = {
    id: number;
    tipo?: string;
    serie?: string;
    correlativo?: number;
    motivo_catalogo?: string | null;
    importe?: number | string | null;
    sunat_estado?: string;
    sunat_mensaje?: string | null;
    baja_mensaje?: string | null;
};

type NoteKind = 'nota_credito' | 'nota_debito';

// Catálogo 09 (NC) y catálogo 10 (ND, R.S. 000048-2026) de SUNAT.
const MOTIVOS: Record<NoteKind, { value: string; label: string }[]> = {
    nota_credito: [
        { value: '01', label: 'Anulación de la operación' },
        { value: '02', label: 'Anulación por error en el RUC' },
        { value: '03', label: 'Corrección por error en la descripción' },
        { value: '04', label: 'Descuento global' },
        { value: '05', label: 'Descuento por ítem' },
        { value: '06', label: 'Devolución total' },
        { value: '07', label: 'Devolución parcial' },
        { value: '08', label: 'Bonificación' },
        { value: '09', label: 'Disminución en el valor' },
        { value: '10', label: 'Otros conceptos' },
        {
            value: '13',
            label: 'Corrección del monto neto pendiente o de las cuotas',
        },
    ],
    nota_debito: [
        { value: '01', label: 'Intereses por mora' },
        { value: '02', label: 'Aumento en el valor' },
        { value: '03', label: 'Otros conceptos' },
        { value: '13', label: 'Penalidades (sin IGV)' },
    ],
};

const KIND_LABEL: Record<string, string> = {
    nota_credito: 'Nota de crédito',
    nota_debito: 'Nota de débito',
};

const ESTADO_LABEL: Record<string, string> = {
    baja_pendiente: 'baja en proceso',
};

function money(value?: number | string | null) {
    return new Intl.NumberFormat('es-PE', {
        style: 'currency',
        currency: 'PEN',
    }).format(Number(value ?? 0) || 0);
}

function estadoClass(estado?: string) {
    if (estado === 'aceptado') {
        return 'bg-emerald-500/10 text-success-strong';
    }

    if (['pendiente', 'baja_pendiente'].includes(estado ?? '')) {
        return 'bg-amber-500/10 text-warning-strong';
    }

    return 'bg-destructive/10 text-destructive-strong';
}

type Props = {
    teamSlug: string;
    saleId: number;
    documents: SaleDocument[];
    saleEstado?: string;
};

export function SaleNotesPanel({
    teamSlug,
    saleId,
    documents,
    saleEstado,
}: Props) {
    const { flash } = usePage<{
        flash?: { success?: string; error?: string };
    }>().props;
    const [bajaOpen, setBajaOpen] = useState(false);

    // Solo un comprobante que SUNAT ya aceptó admite notas o baja; antes de
    // eso se corrige con el botón "Editar" de la venta.
    const original = documents
        .filter(
            (d) =>
                (d.tipo === 'factura' || d.tipo === 'boleta') &&
                ['aceptado', 'observado', 'baja_pendiente', 'anulado'].includes(
                    d.sunat_estado ?? '',
                ),
        )
        .at(-1);
    const notas = documents.filter(
        (d) => d.tipo === 'nota_credito' || d.tipo === 'nota_debito',
    );

    const baja = useForm({ motivo: '', no_entregado: false });

    if (!original) {
        return null;
    }

    const anulada = saleEstado === 'anulada';
    // Las reglas de SUNAT (plazo, CDR, caja) vuelven en este campo.
    const errorBaja = (baja.errors as Record<string, string | undefined>)
        .electronic_document;
    const vigente = ['aceptado', 'observado'].includes(
        original.sunat_estado ?? '',
    );
    const rutaNota = (tipo: 'credito' | 'debito') =>
        ventas.nota.url({ current_team: teamSlug, sale: saleId, tipo });

    const submitBaja = (e: React.FormEvent) => {
        e.preventDefault();
        baja.post(
            facturacion.baja.url({
                current_team: teamSlug,
                electronic_document: original.id,
            }),
            {
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ??
                            'No se pudo completar la acción.',
                    ),
                preserveScroll: true,
                onSuccess: () => setBajaOpen(false),
            },
        );
    };

    return (
        <Card
            id="notas-de-la-venta"
            className="border-border bg-card gap-0 rounded-2xl p-5 shadow-none"
        >
            <div className="mb-3 flex flex-wrap items-center gap-3">
                <h2 className="font-['Oswald',sans-serif] text-[18px] font-semibold uppercase">
                    Notas de crédito y débito
                </h2>
                <div className="flex-1" />
                {anulada || !vigente ? null : (
                    <>
                        <Button
                            asChild
                            variant="outline"
                            className="border-border h-9 rounded-lg shadow-none"
                        >
                            <Link href={rutaNota('credito')}>
                                <FilePlus2 className="size-4" />
                                Nota de crédito
                            </Link>
                        </Button>
                        <Button
                            asChild
                            variant="outline"
                            className="border-border h-9 rounded-lg shadow-none"
                        >
                            <Link href={rutaNota('debito')}>
                                <FilePlus2 className="size-4" />
                                Nota de débito
                            </Link>
                        </Button>
                    </>
                )}
                <Button
                    type="button"
                    variant="outline"
                    disabled={anulada || !vigente}
                    onClick={() => {
                        baja.reset();
                        baja.clearErrors();
                        setBajaOpen(true);
                    }}
                    className="border-border h-9 rounded-lg shadow-none"
                >
                    <Ban className="size-4" />
                    Comunicar baja
                </Button>
            </div>

            <p className="text-muted-foreground mb-3 text-[12px]">
                Se emiten sobre {original.tipo} {original.serie}-
                {original.correlativo}. El comprobante original no se borra: la
                nota queda enlazada a él y sale directo a SUNAT.
                {anulada && ' La venta está anulada y no admite más notas.'}
            </p>

            {original.sunat_estado === 'baja_pendiente' && (
                <div className="text-warning-strong mb-3 flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs">
                    <AlertTriangle className="size-4 shrink-0" />
                    Baja enviada a SUNAT: esperando su respuesta. La venta se
                    anula cuando SUNAT la acepte.
                </div>
            )}
            {vigente && original.baja_mensaje && (
                <div className="text-warning-strong mb-3 flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs">
                    <AlertTriangle className="size-4 shrink-0" />
                    {original.baja_mensaje}
                </div>
            )}

            {flash?.success && (
                <div
                    role="status"
                    className="text-success-strong mb-3 flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs"
                >
                    <CheckCircle2 className="size-4 shrink-0" />
                    {flash.success}
                </div>
            )}
            {flash?.error && (
                <div
                    role="alert"
                    className="text-warning-strong mb-3 flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs"
                >
                    <AlertTriangle className="size-4 shrink-0" />
                    {flash.error}
                </div>
            )}

            <div className="overflow-x-auto">
                <Table className="w-full border-collapse text-[12.5px]">
                    <TableHeader>
                        <TableRow>
                            {[
                                'Documento',
                                'Motivo',
                                'Importe',
                                'SUNAT',
                                'Archivos',
                            ].map((h) => (
                                <TableHead key={h} className="text-left">
                                    {h}
                                </TableHead>
                            ))}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {notas.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={5}>
                                    <Empty
                                        icon={<Inbox />}
                                        title="Sin notas emitidas"
                                    />
                                </TableCell>
                            </TableRow>
                        ) : (
                            <>
                                {notas.map((nota) => (
                                    <TableRow key={nota.id}>
                                        <TableCell className="border-border border-b px-2.5 py-[13px] font-['IBM_Plex_Mono',monospace] font-bold">
                                            {KIND_LABEL[nota.tipo ?? ''] ??
                                                nota.tipo}{' '}
                                            {nota.serie}-{nota.correlativo}
                                        </TableCell>
                                        <TableCell className="border-border border-b px-2.5 py-[13px]">
                                            {MOTIVOS[
                                                nota.tipo as NoteKind
                                            ]?.find(
                                                (m) =>
                                                    m.value ===
                                                    nota.motivo_catalogo,
                                            )?.label ?? nota.motivo_catalogo}
                                        </TableCell>
                                        <TableCell className="border-border border-b px-2.5 py-[13px] font-bold">
                                            {money(nota.importe)}
                                        </TableCell>
                                        <TableCell className="border-border border-b px-2.5 py-[13px]">
                                            <Badge
                                                className={`rounded-full border-transparent px-2.5 py-1 text-xs font-bold capitalize shadow-none ${estadoClass(nota.sunat_estado)}`}
                                            >
                                                {ESTADO_LABEL[
                                                    nota.sunat_estado ?? ''
                                                ] ?? nota.sunat_estado}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="border-border border-b px-2.5 py-[13px]">
                                            <ArchivosNota
                                                teamSlug={teamSlug}
                                                id={nota.id}
                                                aceptada={[
                                                    'aceptado',
                                                    'observado',
                                                ].includes(
                                                    nota.sunat_estado ?? '',
                                                )}
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </>
                        )}
                    </TableBody>
                </Table>
            </div>

            {bajaOpen && (
                <Dialog open={bajaOpen} onOpenChange={setBajaOpen}>
                    <DialogContent
                        aria-labelledby="baja-titulo"
                        aria-describedby={undefined}
                        className="border-border bg-card w-full max-w-md rounded-xl border p-6 shadow-xl [&>button]:hidden"
                    >
                        <div className="border-border flex items-center justify-between border-b pb-3">
                            <DialogTitle
                                id="baja-titulo"
                                className="font-['Oswald',sans-serif] text-lg font-bold uppercase"
                            >
                                Comunicar baja a SUNAT
                            </DialogTitle>
                            <button
                                type="button"
                                onClick={() => setBajaOpen(false)}
                                aria-label="Cerrar"
                                className="text-muted-foreground rounded-md p-1"
                            >
                                <X className="size-4" />
                            </button>
                        </div>

                        <form
                            onSubmit={submitBaja}
                            className="mt-4 space-y-4 text-xs"
                        >
                            <p className="text-muted-foreground">
                                Anula {original.serie}-{original.correlativo}{' '}
                                ante SUNAT sin nota de crédito. Solo procede si
                                el comprobante no llegó al cliente y dentro de
                                los 7 días siguientes a su aceptación. Si SUNAT
                                la acepta, la venta se anula.
                            </p>

                            <div>
                                <label
                                    htmlFor="baja-motivo"
                                    className="text-foreground/80 block font-semibold"
                                >
                                    Motivo *
                                </label>
                                <input
                                    id="baja-motivo"
                                    required
                                    maxLength={100}
                                    value={baja.data.motivo}
                                    onChange={(e) =>
                                        baja.setData('motivo', e.target.value)
                                    }
                                    className="border-border mt-1 w-full rounded-lg border px-3 py-2"
                                />
                                {baja.errors.motivo && (
                                    <p className="text-destructive-strong mt-1">
                                        {baja.errors.motivo}
                                    </p>
                                )}
                            </div>

                            <label className="flex items-start gap-2">
                                <input
                                    type="checkbox"
                                    required
                                    checked={baja.data.no_entregado}
                                    onChange={(e) =>
                                        baja.setData(
                                            'no_entregado',
                                            e.target.checked,
                                        )
                                    }
                                    className="mt-0.5 size-4"
                                />
                                <span>
                                    Confirmo que el comprobante{' '}
                                    <strong>no fue entregado</strong> al
                                    cliente.
                                </span>
                            </label>
                            {baja.errors.no_entregado && (
                                <p
                                    className="text-destructive-strong"
                                    role="alert"
                                >
                                    {baja.errors.no_entregado}
                                </p>
                            )}
                            {errorBaja && (
                                <p
                                    className="text-destructive-strong"
                                    role="alert"
                                >
                                    {errorBaja}
                                </p>
                            )}

                            <div className="border-border flex justify-end gap-2 border-t pt-4">
                                <button
                                    type="button"
                                    onClick={() => setBajaOpen(false)}
                                    className="border-border rounded-lg border px-4 py-2 font-semibold"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    disabled={baja.processing}
                                    className="bg-destructive rounded-lg px-4 py-2 font-bold text-white transition hover:brightness-95 disabled:opacity-50"
                                >
                                    Enviar baja
                                </button>
                            </div>
                        </form>
                    </DialogContent>
                </Dialog>
            )}
        </Card>
    );
}
