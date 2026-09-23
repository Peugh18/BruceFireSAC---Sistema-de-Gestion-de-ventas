import { useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, FilePlus2, X } from 'lucide-react';
import { useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';

export type SaleDocument = {
    id: number;
    tipo?: string;
    serie?: string;
    correlativo?: number;
    motivo_catalogo?: string | null;
    importe?: number | string | null;
    sunat_estado?: string;
    sunat_mensaje?: string | null;
};

type NoteKind = 'nota_credito' | 'nota_debito';

const MOTIVOS: Record<NoteKind, { value: string; label: string }[]> = {
    nota_credito: [
        { value: '01', label: 'Anulación de la operación' },
        { value: '02', label: 'Anulación por error en el RUC' },
        { value: '03', label: 'Corrección por error en la descripción' },
        { value: '04', label: 'Descuento global' },
        { value: '05', label: 'Descuento por ítem' },
        { value: '06', label: 'Devolución total' },
        { value: '07', label: 'Devolución por ítem' },
    ],
    nota_debito: [
        { value: '01', label: 'Intereses por mora' },
        { value: '02', label: 'Aumento en el valor' },
        { value: '03', label: 'Penalidades u otros conceptos' },
    ],
};

const KIND_LABEL: Record<string, string> = {
    nota_credito: 'Nota de crédito',
    nota_debito: 'Nota de débito',
};

function money(value?: number | string | null) {
    return new Intl.NumberFormat('es-PE', {
        style: 'currency',
        currency: 'PEN',
    }).format(Number(value ?? 0) || 0);
}

function estadoClass(estado?: string) {
    if (estado === 'aceptado') {
        return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400';
    }

    if (estado === 'pendiente') {
        return 'bg-amber-500/10 text-amber-600 dark:text-amber-400';
    }

    return 'bg-destructive/10 text-destructive';
}

type Props = {
    teamSlug: string;
    documents: SaleDocument[];
    saleEstado?: string;
};

export function SaleNotesPanel({ teamSlug, documents, saleEstado }: Props) {
    const { flash } = usePage<{
        flash?: { success?: string; error?: string };
    }>().props;
    const [open, setOpen] = useState(false);
    const [kind, setKind] = useState<NoteKind>('nota_credito');

    const original = documents.find(
        (d) => d.tipo === 'factura' || d.tipo === 'boleta',
    );
    const notas = documents.filter(
        (d) => d.tipo === 'nota_credito' || d.tipo === 'nota_debito',
    );

    const form = useForm({
        electronic_document_id: original?.id ?? 0,
        motivo_catalogo: '01',
        detalle: '',
        importe: '',
    });

    if (!original) {
        return null;
    }

    const anulada = saleEstado === 'anulada';

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
        const path = kind === 'nota_credito' ? 'notas-credito' : 'notas-debito';

        form.post(`/${teamSlug}/vendedor/${path}`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Card className="border-border bg-card gap-0 rounded-[16px] p-5 shadow-none">
            <div className="mb-3 flex flex-wrap items-center gap-3">
                <h2 className="font-['Oswald',sans-serif] text-[18px] font-semibold uppercase">
                    Notas de crédito y débito
                </h2>
                <div className="flex-1" />
                <Button
                    type="button"
                    variant="outline"
                    disabled={anulada}
                    onClick={() => openModal('nota_credito')}
                    className="border-border h-9 rounded-[9px] shadow-none"
                >
                    <FilePlus2 className="size-4" />
                    Nota de crédito
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    disabled={anulada}
                    onClick={() => openModal('nota_debito')}
                    className="border-border h-9 rounded-[9px] shadow-none"
                >
                    <FilePlus2 className="size-4" />
                    Nota de débito
                </Button>
            </div>

            <p className="text-muted-foreground mb-3 text-[12px]">
                Se emiten sobre {original.tipo} {original.serie}-
                {original.correlativo}. El comprobante original no se borra: la
                nota queda enlazada a él.
                {anulada && ' La venta está anulada y no admite más notas.'}
            </p>

            {flash?.success && (
                <div className="mb-3 flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800">
                    <CheckCircle2 className="size-4 shrink-0" />
                    {flash.success}
                </div>
            )}
            {flash?.error && (
                <div className="mb-3 flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
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
                        {notas.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={4}
                                    className="text-muted-foreground px-2.5 py-6 text-center"
                                >
                                    Sin notas emitidas
                                </td>
                            </tr>
                        ) : (
                            notas.map((nota) => (
                                <tr key={nota.id}>
                                    <td className="border-border border-b px-2.5 py-[13px] font-['IBM_Plex_Mono',monospace] font-bold">
                                        {KIND_LABEL[nota.tipo ?? ''] ??
                                            nota.tipo}{' '}
                                        {nota.serie}-{nota.correlativo}
                                    </td>
                                    <td className="border-border border-b px-2.5 py-[13px]">
                                        {MOTIVOS[nota.tipo as NoteKind]?.find(
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
                                            {nota.sunat_estado}
                                        </Badge>
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>

            {open && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="border-border bg-card w-full max-w-md rounded-xl border p-6 shadow-xl">
                        <div className="border-border flex items-center justify-between border-b pb-3">
                            <h3 className="font-['Oswald',sans-serif] text-lg font-bold uppercase">
                                {KIND_LABEL[kind]}
                            </h3>
                            <button
                                type="button"
                                onClick={() => setOpen(false)}
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
                                <label className="text-foreground/80 block font-semibold">
                                    Motivo *
                                </label>
                                <select
                                    value={form.data.motivo_catalogo}
                                    onChange={(e) =>
                                        form.setData(
                                            'motivo_catalogo',
                                            e.target.value,
                                        )
                                    }
                                    className="border-border mt-1 w-full rounded-lg border px-3 py-2"
                                >
                                    {MOTIVOS[kind].map((m) => (
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
                                <label className="text-foreground/80 block font-semibold">
                                    Detalle *
                                </label>
                                <textarea
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
                                <label className="text-foreground/80 block font-semibold">
                                    Importe con IGV (S/) *
                                </label>
                                <input
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
                                    form.data.motivo_catalogo === '01' && (
                                        <p className="text-muted-foreground mt-1">
                                            Si el importe cubre todo el
                                            comprobante la venta se anula: las
                                            unidades vuelven al stock y las
                                            cuotas sin cobrar se eliminan.
                                        </p>
                                    )}
                            </div>

                            {form.errors.electronic_document_id && (
                                <p className="text-red-600">
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
                                    Emitir
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </Card>
    );
}
