import { useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, Ban, CheckCircle2, FilePlus2, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import facturacion from '@/routes/vendedor/facturacion';
import notasCredito from '@/routes/vendedor/notas-credito';
import notasDebito from '@/routes/vendedor/notas-debito';

export type SaleDocument = {
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

/** Nota pedida al Gerente que todavía no se emite. */
export type SolicitudNota = {
    id: number;
    tipo: NoteKind;
    motivo_catalogo: string;
    importe: number | string;
    estado: 'por_aprobar' | 'rechazada';
    motivo_rechazo: string | null;
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

// Prohibidos en notas de crédito sobre boletas (guía NC de SUNAT).
const PROHIBIDOS_EN_BOLETA = ['04', '05', '08'];

const KIND_LABEL: Record<string, string> = {
    nota_credito: 'Nota de crédito',
    nota_debito: 'Nota de débito',
};

const ESTADO_LABEL: Record<string, string> = {
    baja_pendiente: 'baja en proceso',
    por_aprobar: 'por aprobar',
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

    if (['pendiente', 'por_aprobar', 'baja_pendiente'].includes(estado ?? '')) {
        return 'bg-amber-500/10 text-warning-strong';
    }

    return 'bg-destructive/10 text-destructive-strong';
}

type Props = {
    teamSlug: string;
    documents: SaleDocument[];
    saleEstado?: string;
    /** Cada vez que cambia, abre el formulario de nota de crédito. */
    abrirNotaCredito?: number;
    solicitudes?: SolicitudNota[];
};

export function SaleNotesPanel({
    teamSlug,
    documents,
    saleEstado,
    abrirNotaCredito = 0,
    solicitudes = [],
}: Props) {
    const { flash } = usePage<{
        flash?: { success?: string; error?: string };
    }>().props;
    const [open, setOpen] = useState(false);
    const [bajaOpen, setBajaOpen] = useState(false);
    const [kind, setKind] = useState<NoteKind>('nota_credito');

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

    const form = useForm({
        electronic_document_id: original?.id ?? 0,
        motivo_catalogo: '01',
        detalle: '',
        importe: '',
    });
    const baja = useForm({ motivo: '', no_entregado: false });

    useEffect(() => {
        if (abrirNotaCredito > 0 && original) {
            setKind('nota_credito');
            form.clearErrors();
            form.setData({
                electronic_document_id: original.id,
                motivo_catalogo: '01',
                detalle: '',
                importe: '',
            });
            setOpen(true);
            document
                .getElementById('notas-de-la-venta')
                ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [abrirNotaCredito]);

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
    const motivos = MOTIVOS[kind].filter(
        (m) =>
            !(
                kind === 'nota_credito' &&
                original.tipo === 'boleta' &&
                PROHIBIDOS_EN_BOLETA.includes(m.value)
            ),
    );

    const openModal = (nextKind: NoteKind) => {
        setKind(nextKind);
        form.clearErrors();
        form.setData({
            electronic_document_id: original.id,
            motivo_catalogo: '01',
            detalle: '',
            importe: '',
        });
        setOpen(true);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const ruta =
            kind === 'nota_credito'
                ? notasCredito.store.url(teamSlug)
                : notasDebito.store.url(teamSlug);

        form.post(ruta, {
            onError: (errors) =>
                toast.error(
                    Object.values(errors)[0] ??
                        'No se pudo completar la acción.',
                ),
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

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
            className="border-border bg-card gap-0 rounded-[16px] p-5 shadow-none"
        >
            <div className="mb-3 flex flex-wrap items-center gap-3">
                <h2 className="font-['Oswald',sans-serif] text-[18px] font-semibold uppercase">
                    Notas de crédito y débito
                </h2>
                <div className="flex-1" />
                <Button
                    type="button"
                    variant="outline"
                    disabled={anulada || !vigente}
                    onClick={() => openModal('nota_credito')}
                    className="border-border h-9 rounded-[9px] shadow-none"
                >
                    <FilePlus2 className="size-4" />
                    Nota de crédito
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    disabled={anulada || !vigente}
                    onClick={() => openModal('nota_debito')}
                    className="border-border h-9 rounded-[9px] shadow-none"
                >
                    <FilePlus2 className="size-4" />
                    Nota de débito
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    disabled={anulada || !vigente}
                    onClick={() => {
                        baja.reset();
                        baja.clearErrors();
                        setBajaOpen(true);
                    }}
                    className="border-border h-9 rounded-[9px] shadow-none"
                >
                    <Ban className="size-4" />
                    Comunicar baja
                </Button>
            </div>

            <p className="text-muted-foreground mb-3 text-[12px]">
                Se emiten sobre {original.tipo} {original.serie}-
                {original.correlativo}. El comprobante original no se borra: la
                nota queda enlazada a él. Si la pide un vendedor, el Gerente
                debe aprobarla antes de emitirla.
                {anulada && ' La venta está anulada y no admite más notas.'}
            </p>

            {original.sunat_estado === 'baja_pendiente' && (
                <div className="mb-3 flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    <AlertTriangle className="size-4 shrink-0" />
                    Baja enviada a SUNAT: esperando su respuesta. La venta se
                    anula cuando SUNAT la acepte.
                </div>
            )}
            {vigente && original.baja_mensaje && (
                <div className="mb-3 flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    <AlertTriangle className="size-4 shrink-0" />
                    {original.baja_mensaje}
                </div>
            )}

            {flash?.success && (
                <div
                    role="status"
                    className="mb-3 flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800"
                >
                    <CheckCircle2 className="size-4 shrink-0" />
                    {flash.success}
                </div>
            )}
            {flash?.error && (
                <div
                    role="alert"
                    className="mb-3 flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800"
                >
                    <AlertTriangle className="size-4 shrink-0" />
                    {flash.error}
                </div>
            )}

            <div className="overflow-x-auto">
                <table className="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            {['Documento', 'Motivo', 'Importe', 'SUNAT'].map(
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
                        {notas.length === 0 && solicitudes.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={4}
                                    className="text-muted-foreground px-2.5 py-6 text-center"
                                >
                                    Sin notas emitidas
                                </td>
                            </tr>
                        ) : (
                            <>
                                {solicitudes.map((solicitud) => (
                                    <tr key={`solicitud-${solicitud.id}`}>
                                        <td className="border-border border-b px-2.5 py-[13px] font-bold">
                                            {KIND_LABEL[solicitud.tipo]} (sin
                                            número)
                                        </td>
                                        <td className="border-border border-b px-2.5 py-[13px]">
                                            {MOTIVOS[solicitud.tipo]?.find(
                                                (m) =>
                                                    m.value ===
                                                    solicitud.motivo_catalogo,
                                            )?.label ??
                                                solicitud.motivo_catalogo}
                                            {solicitud.motivo_rechazo && (
                                                <span className="text-destructive-strong block text-[11px]">
                                                    Rechazada por el Gerente:{' '}
                                                    {solicitud.motivo_rechazo}
                                                </span>
                                            )}
                                        </td>
                                        <td className="border-border border-b px-2.5 py-[13px] font-bold">
                                            {money(solicitud.importe)}
                                        </td>
                                        <td className="border-border border-b px-2.5 py-[13px]">
                                            <Badge
                                                className={`rounded-full border-transparent px-2.5 py-1 text-[10.5px] font-bold shadow-none ${estadoClass(solicitud.estado)}`}
                                            >
                                                {ESTADO_LABEL[
                                                    solicitud.estado
                                                ] ?? solicitud.estado}
                                            </Badge>
                                        </td>
                                    </tr>
                                ))}
                                {notas.map((nota) => (
                                    <tr key={nota.id}>
                                        <td className="border-border border-b px-2.5 py-[13px] font-['IBM_Plex_Mono',monospace] font-bold">
                                            {KIND_LABEL[nota.tipo ?? ''] ??
                                                nota.tipo}{' '}
                                            {nota.serie}-{nota.correlativo}
                                        </td>
                                        <td className="border-border border-b px-2.5 py-[13px]">
                                            {MOTIVOS[
                                                nota.tipo as NoteKind
                                            ]?.find(
                                                (m) =>
                                                    m.value ===
                                                    nota.motivo_catalogo,
                                            )?.label ?? nota.motivo_catalogo}
                                        </td>
                                        <td className="border-border border-b px-2.5 py-[13px] font-bold">
                                            {money(nota.importe)}
                                        </td>
                                        <td className="border-border border-b px-2.5 py-[13px]">
                                            <Badge
                                                className={`rounded-full border-transparent px-2.5 py-1 text-[10.5px] font-bold capitalize shadow-none ${estadoClass(nota.sunat_estado)}`}
                                            >
                                                {ESTADO_LABEL[
                                                    nota.sunat_estado ?? ''
                                                ] ?? nota.sunat_estado}
                                            </Badge>
                                        </td>
                                    </tr>
                                ))}
                            </>
                        )}
                    </tbody>
                </table>
            </div>

            {open && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="nota-titulo"
                        className="border-border bg-card w-full max-w-md rounded-xl border p-6 shadow-xl"
                    >
                        <div className="border-border flex items-center justify-between border-b pb-3">
                            <h3
                                id="nota-titulo"
                                className="font-['Oswald',sans-serif] text-lg font-bold uppercase"
                            >
                                {KIND_LABEL[kind]}
                            </h3>
                            <button
                                type="button"
                                onClick={() => setOpen(false)}
                                aria-label="Cerrar"
                                className="text-muted-foreground rounded-md p-1"
                            >
                                <X className="size-4" />
                            </button>
                        </div>

                        <form
                            onSubmit={submit}
                            className="mt-4 space-y-4 text-xs"
                        >
                            <div>
                                <label
                                    htmlFor="nota-motivo"
                                    className="text-foreground/80 block font-semibold"
                                >
                                    Motivo *
                                </label>
                                <select
                                    id="nota-motivo"
                                    value={form.data.motivo_catalogo}
                                    onChange={(e) =>
                                        form.setData(
                                            'motivo_catalogo',
                                            e.target.value,
                                        )
                                    }
                                    className="border-border mt-1 w-full rounded-lg border px-3 py-2"
                                >
                                    {motivos.map((m) => (
                                        <option key={m.value} value={m.value}>
                                            {m.label}
                                        </option>
                                    ))}
                                </select>
                                {form.errors.motivo_catalogo && (
                                    <p className="mt-1 text-red-600">
                                        {form.errors.motivo_catalogo}
                                    </p>
                                )}
                            </div>

                            <div>
                                <label
                                    htmlFor="nota-detalle"
                                    className="text-foreground/80 block font-semibold"
                                >
                                    Detalle *
                                </label>
                                <textarea
                                    id="nota-detalle"
                                    required
                                    rows={3}
                                    value={form.data.detalle}
                                    onChange={(e) =>
                                        form.setData('detalle', e.target.value)
                                    }
                                    className="border-border mt-1 w-full rounded-lg border px-3 py-2"
                                />
                                {form.errors.detalle && (
                                    <p className="mt-1 text-red-600">
                                        {form.errors.detalle}
                                    </p>
                                )}
                            </div>

                            <div>
                                <label
                                    htmlFor="nota-importe"
                                    className="text-foreground/80 block font-semibold"
                                >
                                    Importe con IGV (S/) *
                                </label>
                                <input
                                    id="nota-importe"
                                    type="number"
                                    required
                                    min="0.01"
                                    step="0.01"
                                    value={form.data.importe}
                                    onChange={(e) =>
                                        form.setData('importe', e.target.value)
                                    }
                                    className="border-border mt-1 w-full rounded-lg border px-3 py-2 font-mono"
                                />
                                {form.errors.importe && (
                                    <p className="mt-1 text-red-600">
                                        {form.errors.importe}
                                    </p>
                                )}
                                {kind === 'nota_credito' &&
                                    ['01', '02', '06'].includes(
                                        form.data.motivo_catalogo,
                                    ) && (
                                        <p className="text-muted-foreground mt-1">
                                            Si el importe cubre todo el
                                            comprobante la venta se anula: las
                                            unidades vuelven al stock y las
                                            cuotas sin cobrar se eliminan.
                                        </p>
                                    )}
                            </div>

                            {form.errors.electronic_document_id && (
                                <p className="text-red-600" role="alert">
                                    {form.errors.electronic_document_id}
                                </p>
                            )}

                            <div className="border-border flex justify-end gap-2 border-t pt-4">
                                <button
                                    type="button"
                                    onClick={() => setOpen(false)}
                                    className="border-border rounded-lg border px-4 py-2 font-semibold"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    disabled={form.processing}
                                    className="bg-primary text-primary-foreground rounded-lg px-4 py-2 font-bold disabled:opacity-50"
                                >
                                    Enviar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {bajaOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="baja-titulo"
                        className="border-border bg-card w-full max-w-md rounded-xl border p-6 shadow-xl"
                    >
                        <div className="border-border flex items-center justify-between border-b pb-3">
                            <h3
                                id="baja-titulo"
                                className="font-['Oswald',sans-serif] text-lg font-bold uppercase"
                            >
                                Comunicar baja a SUNAT
                            </h3>
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
                                    <p className="mt-1 text-red-600">
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
                                <p className="text-red-600" role="alert">
                                    {baja.errors.no_entregado}
                                </p>
                            )}
                            {errorBaja && (
                                <p className="text-red-600" role="alert">
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
                                    className="bg-destructive rounded-lg px-4 py-2 font-bold text-white disabled:opacity-50"
                                >
                                    Enviar baja
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </Card>
    );
}
