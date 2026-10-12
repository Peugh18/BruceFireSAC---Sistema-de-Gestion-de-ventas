import { useForm, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Ban,
    CheckCircle2,
    FilePlus2,
    X,
    Inbox,
} from 'lucide-react';
import { useEffect, useState } from 'react';
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
import notasCredito from '@/routes/vendedor/notas-credito';
import notasDebito from '@/routes/vendedor/notas-debito';
import type { Auth } from '@/types';
import { NativeSelect } from '@/components/ui/native-select';
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

/** Línea del comprobante afectado, en el orden de su XML. */
export type LineaOriginal = {
    indice: number;
    nombre: string;
    cantidad: number;
    total: number;
};

type Seleccion = {
    descripcion?: string;
    importe?: string;
    cantidad?: string;
};

/** Motivos de nota de crédito que se aplican a líneas concretas. */
const MOTIVOS_POR_LINEA = ['03', '05', '07'];

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
    lineasOriginal?: LineaOriginal[];
};

export function SaleNotesPanel({
    teamSlug,
    documents,
    saleEstado,
    abrirNotaCredito = 0,
    solicitudes = [],
    lineasOriginal = [],
}: Props) {
    const { flash, auth } = usePage<{
        auth: Auth;
        flash?: { success?: string; error?: string };
    }>().props;
    const [open, setOpen] = useState(false);
    const [bajaOpen, setBajaOpen] = useState(false);
    const [kind, setKind] = useState<NoteKind>('nota_credito');
    const [seleccion, setSeleccion] = useState<Record<number, Seleccion>>({});
    const [devolucionFisica, setDevolucionFisica] = useState(false);

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
            setSeleccion({});
            setDevolucionFisica(false);
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

    const reiniciarLineas = () => {
        setSeleccion({});
        setDevolucionFisica(false);
    };

    const porLinea =
        kind === 'nota_credito' &&
        MOTIVOS_POR_LINEA.includes(form.data.motivo_catalogo);
    const lineasElegidas = lineasOriginal.filter(
        (l) => seleccion[l.indice] !== undefined,
    );
    // El importe sale de las líneas elegidas: nunca se reparte uno suelto.
    const importeCalculado = lineasElegidas.reduce((suma, l) => {
        const dato = seleccion[l.indice];

        if (form.data.motivo_catalogo === '05') {
            return suma + (Number(dato.importe) || 0);
        }

        if (form.data.motivo_catalogo === '07') {
            return (
                suma +
                (l.total * Math.min(Number(dato.cantidad) || 0, l.cantidad)) /
                    l.cantidad
            );
        }

        return suma;
    }, 0);

    const alternarLinea = (linea: LineaOriginal, activa: boolean) =>
        setSeleccion((previa) => {
            const copia = { ...previa };

            if (activa) {
                copia[linea.indice] = { descripcion: linea.nombre };
            } else {
                delete copia[linea.indice];
            }

            return copia;
        });

    const openModal = (nextKind: NoteKind) => {
        reiniciarLineas();
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

        form.transform((datos) => {
            if (!porLinea) {
                return {
                    ...datos,
                    ...(kind === 'nota_credito' &&
                    ['01', '02', '06'].includes(datos.motivo_catalogo) &&
                    devolucionFisica
                        ? { nota_datos: { devolucion_fisica: true } }
                        : {}),
                };
            }

            return {
                ...datos,
                importe:
                    datos.motivo_catalogo === '03'
                        ? '0'
                        : importeCalculado.toFixed(2),
                nota_datos: {
                    lineas: lineasElegidas.map((l) => {
                        const dato = seleccion[l.indice];

                        return {
                            indice: l.indice,
                            ...(datos.motivo_catalogo === '03'
                                ? { descripcion: dato.descripcion ?? '' }
                                : {}),
                            ...(datos.motivo_catalogo === '05'
                                ? { importe: dato.importe ?? '' }
                                : {}),
                            ...(datos.motivo_catalogo === '07'
                                ? { cantidad: dato.cantidad ?? '' }
                                : {}),
                        };
                    }),
                },
            };
        });
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
                <Table className="w-full border-collapse text-[12.5px]">
                    <TableHeader>
                        <TableRow>
                            {['Documento', 'Motivo', 'Importe', 'SUNAT'].map(
                                (h) => (
                                    <TableHead key={h} className="text-left">
                                        {h}
                                    </TableHead>
                                ),
                            )}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {notas.length === 0 && solicitudes.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={4}>
                                    <Empty
                                        icon={<Inbox />}
                                        title="Sin notas emitidas"
                                    />
                                </TableCell>
                            </TableRow>
                        ) : (
                            <>
                                {solicitudes.map((solicitud) => (
                                    <TableRow key={`solicitud-${solicitud.id}`}>
                                        <TableCell className="border-border border-b px-2.5 py-[13px] font-bold">
                                            {KIND_LABEL[solicitud.tipo]} (sin
                                            número)
                                        </TableCell>
                                        <TableCell className="border-border border-b px-2.5 py-[13px]">
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
                                        </TableCell>
                                        <TableCell className="border-border border-b px-2.5 py-[13px] font-bold">
                                            {money(solicitud.importe)}
                                        </TableCell>
                                        <TableCell className="border-border border-b px-2.5 py-[13px]">
                                            <Badge
                                                className={`rounded-full border-transparent px-2.5 py-1 text-[10.5px] font-bold shadow-none ${estadoClass(solicitud.estado)}`}
                                            >
                                                {ESTADO_LABEL[
                                                    solicitud.estado
                                                ] ?? solicitud.estado}
                                            </Badge>
                                        </TableCell>
                                    </TableRow>
                                ))}
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
                                                className={`rounded-full border-transparent px-2.5 py-1 text-[10.5px] font-bold capitalize shadow-none ${estadoClass(nota.sunat_estado)}`}
                                            >
                                                {ESTADO_LABEL[
                                                    nota.sunat_estado ?? ''
                                                ] ?? nota.sunat_estado}
                                            </Badge>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </>
                        )}
                    </TableBody>
                </Table>
            </div>

            {open && (
                <Dialog open={open} onOpenChange={setOpen}>
                    <DialogContent
                        aria-labelledby="nota-titulo"
                        aria-describedby={undefined}
                        className="border-border bg-card w-full max-w-md rounded-xl border p-6 shadow-xl [&>button]:hidden"
                    >
                        <div className="border-border flex items-center justify-between border-b pb-3">
                            <DialogTitle
                                id="nota-titulo"
                                className="font-['Oswald',sans-serif] text-lg font-bold uppercase"
                            >
                                {KIND_LABEL[kind]}
                            </DialogTitle>
                            <button
                                type="button"
                                onClick={() => setOpen(false)}
                                aria-label="Cerrar"
                                className="text-muted-foreground rounded-md p-1"
                            >
                                <X className="size-4" />
                            </button>
                        </div>

                        <p className="text-muted-foreground mt-3 text-sm">
                            Comprobante afectado: {original.tipo}{' '}
                            {original.serie}-{original.correlativo}.
                            {(auth.roles ?? []).includes('Gerente')
                                ? ' La confirmación emite la nota y la envía a SUNAT.'
                                : ' La solicitud requiere aprobación del gerente antes de emitir la nota.'}
                        </p>
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
                                <NativeSelect
                                    id="nota-motivo"
                                    value={form.data.motivo_catalogo}
                                    onChange={(e) => {
                                        reiniciarLineas();
                                        form.setData(
                                            'motivo_catalogo',
                                            e.target.value,
                                        );
                                    }}
                                    className="border-border mt-1 w-full rounded-lg border px-3 py-2"
                                >
                                    {motivos.map((m) => (
                                        <option
                                            key={m.value}
                                            value={m.value}
                                            disabled={
                                                kind === 'nota_credito' &&
                                                m.value === '13'
                                            }
                                        >
                                            {m.label}
                                            {kind === 'nota_credito' &&
                                            m.value === '13'
                                                ? ' (no disponible)'
                                                : ''}
                                        </option>
                                    ))}
                                </NativeSelect>
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

                            {porLinea && (
                                <fieldset className="border-border rounded-lg border p-3">
                                    <legend className="text-foreground/80 px-1 font-semibold">
                                        {form.data.motivo_catalogo === '03'
                                            ? 'Líneas con la descripción a corregir *'
                                            : form.data.motivo_catalogo === '05'
                                              ? 'Líneas y descuento de cada una *'
                                              : 'Líneas y cantidad devuelta *'}
                                    </legend>
                                    {lineasOriginal.length === 0 && (
                                        <p className="text-destructive-strong">
                                            No se pudo leer el detalle del
                                            comprobante original; esta nota no
                                            se puede preparar.
                                        </p>
                                    )}
                                    <ul className="space-y-3">
                                        {lineasOriginal.map((l) => {
                                            const dato = seleccion[l.indice];
                                            const activa = dato !== undefined;

                                            return (
                                                <li key={l.indice}>
                                                    <label className="flex items-start gap-2">
                                                        <input
                                                            type="checkbox"
                                                            checked={activa}
                                                            onChange={(e) =>
                                                                alternarLinea(
                                                                    l,
                                                                    e.target
                                                                        .checked,
                                                                )
                                                            }
                                                            className="mt-0.5"
                                                        />
                                                        <span>
                                                            {l.nombre}{' '}
                                                            <span className="text-muted-foreground">
                                                                ({l.cantidad} u.
                                                                ·{' '}
                                                                {money(l.total)}
                                                                )
                                                            </span>
                                                        </span>
                                                    </label>
                                                    {activa &&
                                                        form.data
                                                            .motivo_catalogo ===
                                                            '03' && (
                                                            <input
                                                                aria-label={`Descripción corregida de ${l.nombre}`}

                                                                value={
                                                                    dato.descripcion ??
                                                                    ''
                                                                }
                                                                onChange={(e) =>
                                                                    setSeleccion(
                                                                        (
                                                                            previa,
                                                                        ) => ({
                                                                            ...previa,
                                                                            [l.indice]:
                                                                                {
                                                                                    descripcion:
                                                                                        e
                                                                                            .target
                                                                                            .value,
                                                                                },
                                                                        }),
                                                                    )
                                                                }
                                                                className="border-border mt-1 w-full rounded-lg border px-3 py-2 font-mono"
                                                            />
                                                        )}
                                                    {activa &&
                                                        form.data
                                                            .motivo_catalogo ===
                                                            '05' && (
                                                            <input
                                                                aria-label={`Descuento con IGV de ${l.nombre}`}
                                                                type="number"
                                                                min="0.01"
                                                                max={l.total}
                                                                step="0.01"
                                                                placeholder="Descuento con IGV (S/)"
                                                                value={
                                                                    dato.importe ??
                                                                    ''
                                                                }
                                                                onChange={(e) =>
                                                                    setSeleccion(
                                                                        (
                                                                            previa,
                                                                        ) => ({
                                                                            ...previa,
                                                                            [l.indice]:
                                                                                {
                                                                                    importe:
                                                                                        e
                                                                                            .target
                                                                                            .value,
                                                                                },
                                                                        }),
                                                                    )
                                                                }
                                                                className="border-border mt-1 w-full rounded-lg border px-3 py-2 font-mono"
                                                            />
                                                        )}
                                                    {activa &&
                                                        form.data
                                                            .motivo_catalogo ===
                                                            '07' && (
                                                            <input
                                                                aria-label={`Cantidad devuelta de ${l.nombre}`}
                                                                type="number"
                                                                min="0.001"
                                                                max={l.cantidad}
                                                                step="any"
                                                                placeholder="Cantidad devuelta"
                                                                value={
                                                                    dato.cantidad ??
                                                                    ''
                                                                }
                                                                onChange={(e) =>
                                                                    setSeleccion(
                                                                        (
                                                                            previa,
                                                                        ) => ({
                                                                            ...previa,
                                                                            [l.indice]:
                                                                                {
                                                                                    cantidad:
                                                                                        e
                                                                                            .target
                                                                                            .value,
                                                                                },
                                                                        }),
                                                                    )
                                                                }
                                                                className="border-border mt-1 w-full rounded-lg border px-3 py-2 font-mono"
                                                            />
                                                        )}
                                                </li>
                                            );
                                        })}
                                    </ul>
                                    {form.data.motivo_catalogo !== '03' && (
                                        <p className="mt-2 font-semibold">
                                            Importe de la nota:{' '}
                                            {money(importeCalculado)}
                                        </p>
                                    )}
                                    {form.data.motivo_catalogo === '07' && (
                                        <p className="text-muted-foreground mt-1">
                                            Registra solo el documento fiscal:
                                            el regreso de las unidades al stock
                                            se hace por el flujo de inventario.
                                        </p>
                                    )}
                                    {form.errors.importe && (
                                        <p className="mt-1 text-red-600">
                                            {form.errors.importe}
                                        </p>
                                    )}
                                </fieldset>
                            )}

                            {!porLinea && (
                                <div>
                                    <label
                                        htmlFor="nota-importe"
                                        className="text-foreground/80 block font-semibold"
                                    >
                                        Importe del ajuste (S/) *
                                    </label>
                                    <input
                                        id="nota-importe"
                                        type="number"
                                        required
                                        min="0.01"
                                        step="0.01"
                                        value={form.data.importe}
                                        onChange={(e) =>
                                            form.setData(
                                                'importe',
                                                e.target.value,
                                            )
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
                                                comprobante la venta se anula:
                                                las unidades vuelven al stock y
                                                las cuotas sin cobrar se
                                                eliminan.
                                            </p>
                                        )}
                                </div>
                            )}

                            {kind === 'nota_credito' &&
                                ['01', '02', '06'].includes(
                                    form.data.motivo_catalogo,
                                ) && (
                                    <label className="flex items-start gap-2">
                                        <input
                                            type="checkbox"
                                            checked={devolucionFisica}
                                            onChange={(e) =>
                                                setDevolucionFisica(
                                                    e.target.checked,
                                                )
                                            }
                                            className="mt-0.5"
                                        />
                                        <span>
                                            Ya recibí físicamente los equipos:
                                            al anular la venta vuelven al stock.
                                            Si no la marcas, solo se anula el
                                            documento.
                                        </span>
                                    </label>
                                )}

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
                                    className="bg-primary text-primary-foreground rounded-lg px-4 py-2 font-bold transition hover:brightness-95 disabled:opacity-50"
                                >
                                    {(auth.roles ?? []).includes('Gerente')
                                        ? 'Emitir y enviar a SUNAT'
                                        : 'Solicitar al gerente'}
                                </button>
                            </div>
                        </form>
                    </DialogContent>
                </Dialog>
            )}

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
