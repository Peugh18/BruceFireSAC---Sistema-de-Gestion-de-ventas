import { pageTitleClass } from '@/components/page-header';
import { Link, router, useForm, usePage } from '@inertiajs/react';
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
    Inbox,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { useConfirmarAccion } from '@/hooks/use-confirmar-accion';
import { toast } from 'sonner';

import { CargaLarga } from '@/components/cargando';
import CambiarUnidadDialog, {
    type LineaConSerie,
} from '@/components/cambiar-unidad-dialog';
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
import { NativeSelect } from '@/components/ui/native-select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    DocumentosRelacionados,
    type DocumentoRelacionado,
} from '@/components/documentos-relacionados';
import {
    HistorialActividad,
    type HistorialItem,
} from '@/components/historial-actividad';
import { SaleNotesPanel } from '@/components/sale-notes-panel';
import VendedorLayout from '@/layouts/vendedor-layout';
import guias from '@/routes/guias';
import cobranzas from '@/routes/vendedor/cobranzas';
import facturacion from '@/routes/vendedor/facturacion';
import ventas from '@/routes/vendedor/ventas';
import detracciones from '@/routes/vendedor/ventas/detraccion';
import type { Team } from '@/types';
import CertificadosRoutes from '@/routes/vendedor/certificados';
import { Empty } from '@/components/ui/empty';
import { fechaCorta, soles } from '@/lib/utils';

type ComprobanteClient = {
    id: number;
    tipo_documento?: string;
    razon_social: string;
    numero_documento: string;
};

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
    es_detraccion?: boolean;
    numero_operacion?: string | null;
    tiene_constancia?: boolean;
    fecha?: string;
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
    correccion_desde?: string | null;
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
    errorDetraccion?: string | null;
    sale: Sale;
    editable: boolean;
    detraccion?: {
        aplica: boolean;
        monto: number;
        codigo_bien: string;
        acreditado: number;
        pendiente: number;
    };
    certificados: CertificadoVenta[];
    tieneEquipos: boolean;
    relacionados: DocumentoRelacionado[];
    resumen: {
        total: number;
        notas_credito: number;
        notas_debito: number;
        cobrado: number;
        devuelto: number;
        pendiente: number;
    };
    historial: HistorialItem[];
    tiposServicio: { codigo: string; nombre: string }[];
};

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
    editable,
    detraccion,
    errorDetraccion,
    certificados,
    tieneEquipos,
    relacionados,
    resumen,
    historial,
    tiposServicio,
}: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const { pedirConfirmacion, dialogoConfirmacion } = useConfirmarAccion();
    const teamSlug =
        currentTeam?.slug ??
        (typeof window !== 'undefined'
            ? window.location.pathname.split('/')[1]
            : '');
    const constanciaForm = useForm({
        monto: String(detraccion?.pendiente ?? 0),
        numero_operacion: '',
        fecha_deposito: '',
        evidencia: null as File | null,
    });
    const [confirmando, setConfirmando] = useState(false);
    const [formalizando, setFormalizando] = useState(false);
    const formalizacionForm = useForm({
        comprobante_tipo:
            sale.client.tipo_documento === 'ruc' ? 'factura' : 'boleta',
    });
    const [lineaACambiar, setLineaACambiar] = useState<LineaConSerie | null>(
        null,
    );
    const puedeCambiarExtintor =
        sale.estado === 'borrador' || sale.estado === 'confirmada';
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
    const aceptado =
        documento?.sunat_estado === 'aceptado' ||
        documento?.sunat_estado === 'observado';
    const urlNotaCredito = ventas.nota.url({
        current_team: teamSlug,
        sale: sale.id,
        tipo: 'credito',
    });

    const notas = (sale.electronic_documents ?? []).filter(
        (d) => d.tipo === 'nota_credito' || d.tipo === 'nota_debito',
    );
    const accesos = [
        {
            texto: 'Pagos',
            cantidad: (sale.payments ?? []).length,
            href: '#pagos-de-la-venta',
        },
        {
            texto: 'Notas',
            cantidad: notas.length,
            href: '#notas-de-la-venta',
        },
        {
            texto: 'Certificados',
            cantidad: certificados.length,
            href: '#certificados-de-la-venta',
        },
        {
            texto: 'Documentos',
            cantidad: relacionados.length,
            href: '#documentos-de-la-venta',
        },
    ].filter((acceso) => acceso.cantidad > 0);
    const pasosSunat = ['Por enviar', 'Enviado', 'Aceptado'];
    const pasoSunat = aceptado ? 2 : porEnviar ? 0 : 1;
    const lineasSaldo = [
        ['Total', resumen.total],
        ['Notas de crédito', -resumen.notas_credito],
        ['Notas de débito', resumen.notas_debito],
        ['Cobrado', -resumen.cobrado],
        ['Devuelto', resumen.devuelto],
    ].filter(([, monto], indice) => indice === 0 || monto !== 0) as [
        string,
        number,
    ][];

    const urlEditar = ventas.edit.url({
        current_team: teamSlug,
        sale: sale.id,
    });

    // Desde la lista, el lápiz de una venta ya aceptada por SUNAT llega con
    // ?corregir=1: se abre directo la nota de crédito.
    useEffect(() => {
        if (
            typeof window === 'undefined' ||
            !new URLSearchParams(window.location.search).has('corregir')
        ) {
            return;
        }

        if (aceptado) {
            router.visit(urlNotaCredito, { replace: true });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    async function accion(tipo: 'enviar' | 'anular') {
        const avisos = {
            anular: 'Se anula la venta, las unidades vuelven al stock y el número del comprobante se libera. ¿Continuar?',
            enviar: null,
        };
        const aviso = avisos[tipo];

        if (aviso && !(await pedirConfirmacion(aviso))) {
            return;
        }

        setProcesando(tipo);
        router.post(
            {
                enviar: ventas.enviarSunat,
                anular: ventas.anular,
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

    async function descartarVenta() {
        const texto =
            sale.estado === 'borrador'
                ? 'Se descarta el borrador y sus unidades vuelven al stock. ¿Continuar?'
                : 'Se anula la nota de venta, sus unidades vuelven al stock y sus certificados se anulan. ¿Continuar?';

        if (!(await pedirConfirmacion(texto))) {
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
            {errorDetraccion && (
                <p
                    role="alert"
                    className="text-warning-strong rounded-xl border border-amber-500/30 p-3 text-sm"
                >
                    {errorDetraccion}
                </p>
            )}

            <div className="flex flex-col gap-4">
                {dialogoConfirmacion}
                {sale.comprobante_tipo === 'nota_venta' &&
                    sale.estado === 'confirmada' && (
                        <Card className="border-border gap-3 p-4">
                            <div>
                                <p className="font-medium">
                                    Pendiente de comprobante SUNAT
                                </p>
                                <p className="text-muted-foreground text-sm">
                                    La nota de venta es interna. Emite el
                                    comprobante que corresponde a esta
                                    operación; se conservarán sus cobros,
                                    cuotas, equipos y certificados.
                                </p>
                            </div>
                            <Button
                                variant="outline"
                                className="w-fit"
                                onClick={() => setFormalizando(true)}
                            >
                                <ReceiptText className="size-4" /> Emitir
                                factura o boleta
                            </Button>
                            <Dialog
                                open={formalizando}
                                onOpenChange={setFormalizando}
                            >
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>
                                            Formalizar nota de venta
                                        </DialogTitle>
                                        <DialogDescription>
                                            Se emitirá un comprobante para esta
                                            misma venta. La fecha de emisión
                                            será la actual; las fechas
                                            originales de atención y de cuotas
                                            se conservan. Revisa los datos
                                            fiscales del cliente antes de
                                            continuar.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <form
                                        onSubmit={(event) => {
                                            event.preventDefault();
                                            formalizacionForm.post(
                                                ventas.formalizarNv.url({
                                                    current_team: teamSlug,
                                                    sale: sale.id,
                                                }),
                                                {
                                                    preserveScroll: true,
                                                    onSuccess: () =>
                                                        setFormalizando(false),
                                                },
                                            );
                                        }}
                                        className="space-y-4"
                                    >
                                        <label className="grid gap-2 text-sm font-medium">
                                            Comprobante
                                            <NativeSelect
                                                value={
                                                    formalizacionForm.data
                                                        .comprobante_tipo
                                                }
                                                onChange={(event) =>
                                                    formalizacionForm.setData(
                                                        'comprobante_tipo',
                                                        event.target.value,
                                                    )
                                                }
                                                disabled={
                                                    formalizacionForm.processing
                                                }
                                            >
                                                <option
                                                    value="factura"
                                                    disabled={
                                                        sale.client
                                                            .tipo_documento !==
                                                        'ruc'
                                                    }
                                                >
                                                    Factura · requiere RUC
                                                </option>
                                                <option value="boleta">
                                                    Boleta
                                                </option>
                                            </NativeSelect>
                                        </label>
                                        {Object.entries(
                                            formalizacionForm.errors,
                                        ).map(([campo, mensaje]) => (
                                            <p
                                                role="alert"
                                                key={campo}
                                                className="text-destructive text-sm"
                                            >
                                                {mensaje}
                                            </p>
                                        ))}
                                        <p className="text-muted-foreground text-sm">
                                            Si corresponde detracción, se
                                            validará la clasificación, la cuenta
                                            BN y los cobros ya registrados. Este
                                            proceso no acredita depósitos
                                            bancarios.
                                        </p>
                                        <DialogFooter>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() =>
                                                    setFormalizando(false)
                                                }
                                                disabled={
                                                    formalizacionForm.processing
                                                }
                                            >
                                                Cancelar
                                            </Button>
                                            <Button
                                                type="submit"
                                                disabled={
                                                    formalizacionForm.processing
                                                }
                                            >
                                                {formalizacionForm.processing
                                                    ? 'Emitiendo…'
                                                    : 'Emitir comprobante'}
                                            </Button>
                                        </DialogFooter>
                                    </form>
                                </DialogContent>
                            </Dialog>
                        </Card>
                    )}
                <div className="flex flex-wrap items-center gap-3">
                    <Button
                        asChild
                        variant="outline"
                        className="border-border bg-card text-foreground/80 h-9 rounded-lg shadow-none"
                    >
                        <Link href={ventas.index(teamSlug)}>
                            <ArrowLeft className="size-4" />
                            Ventas
                        </Link>
                    </Button>
                    <div>
                        <h1 className={pageTitleClass}>
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
                            className="border-border bg-card text-destructive-strong h-9 rounded-lg shadow-none"
                        >
                            <XCircle className="size-4" />
                            {sale.estado === 'borrador'
                                ? 'Descartar borrador'
                                : 'Anular nota de venta'}
                        </Button>
                    ) : null}
                    {sale.estado === 'confirmada' &&
                        sale.items?.some((item) => item.product_id) &&
                        sale.electronic_documents?.some(
                            (doc) =>
                                ['factura', 'boleta'].includes(
                                    doc.tipo ?? '',
                                ) &&
                                [
                                    'por_enviar',
                                    'pendiente',
                                    'aceptado',
                                    'observado',
                                ].includes(doc.sunat_estado ?? ''),
                        ) && (
                            <Button
                                asChild
                                variant="outline"
                                className="border-border bg-card text-foreground h-9 rounded-lg shadow-none"
                            >
                                <Link
                                    href={guias.create.url(teamSlug, {
                                        query: { origen: 'venta', id: sale.id },
                                    })}
                                >
                                    <FileText className="size-4" />
                                    Emitir guía
                                </Link>
                            </Button>
                        )}
                    {editable && (
                        <Button
                            asChild
                            variant="outline"
                            className="border-border bg-card text-foreground h-9 rounded-lg shadow-none"
                        >
                            <Link href={urlEditar}>
                                <PencilLine className="size-4" />
                                Editar
                            </Link>
                        </Button>
                    )}
                    {sale.estado === 'borrador' && (
                        <Button
                            onClick={confirmarVenta}
                            disabled={confirmando}
                            className="h-9 rounded-lg bg-emerald-600 text-white shadow-none hover:bg-emerald-700"
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
                            className="bg-primary hover:bg-primary/90 h-9 rounded-lg text-white shadow-none"
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
                    <Badge className="text-success-strong rounded-full border-transparent bg-emerald-500/10 px-3 py-1 text-xs font-bold capitalize dark:text-emerald-400">
                        {sale.estado ?? 'registrada'}
                    </Badge>
                    {sale.numero_nota_venta && (
                        <Button
                            asChild
                            variant="outline"
                            className="border-border bg-card text-foreground/80 h-9 rounded-lg shadow-none"
                        >
                            <a
                                href={ventas.notaVentaPdf.url({
                                    current_team: teamSlug,
                                    sale: sale.id,
                                })}
                                target="_blank"
                                rel="noreferrer"
                            >
                                {sale.numero_nota_venta} · PDF
                            </a>
                        </Button>
                    )}
                    {documento && (
                        <Badge className="bg-muted text-foreground rounded-full border-transparent px-3 py-1 text-xs font-bold capitalize">
                            SUNAT: {documento.serie}-{documento.correlativo} ·{' '}
                            {nice(documento.sunat_estado)}
                        </Badge>
                    )}
                    {documento?.pdf_path && (
                        <Button
                            asChild
                            variant="outline"
                            className="border-border bg-card text-foreground/80 h-9 rounded-lg shadow-none"
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
                        className={`flex flex-col gap-3 rounded-2xl p-4 shadow-none sm:flex-row sm:items-center ${rechazado ? 'border-destructive/40 bg-destructive/5' : 'border-amber-500/40 bg-amber-500/5'}`}
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
                                        ? `${documento.sunat_mensaje ?? ''} Corrígelo con «Editar»: se emite uno nuevo con otro número y la fecha de hoy.`
                                        : `Se envía automáticamente el ${horaDeEnvio(documento.enviar_desde)}; hasta entonces puedes cambiar lo que sea con «Editar», sin nota de crédito.`}
                                </p>
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {porEnviar ? (
                                <>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={procesando !== null}
                                        onClick={() => accion('anular')}
                                        className="border-border bg-card text-destructive-strong h-9 rounded-lg shadow-none"
                                    >
                                        <XCircle className="size-4" />
                                        Anular venta
                                    </Button>
                                    <Button
                                        type="button"
                                        disabled={procesando !== null}
                                        onClick={() => accion('enviar')}
                                        className="bg-primary hover:bg-primary/90 h-9 rounded-lg text-white shadow-none"
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
                    <Card className="flex-row flex-wrap items-center justify-between gap-3 rounded-2xl border-emerald-500/30 bg-emerald-500/5 p-4 shadow-none">
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
                            asChild
                            variant="outline"
                            className="border-border bg-card text-foreground h-9 rounded-lg shadow-none"
                        >
                            <Link href={urlNotaCredito}>
                                <PencilLine className="size-4" />
                                Corregir con nota de crédito
                            </Link>
                        </Button>
                    </Card>
                ) : null}

                {sale.correccion_desde ? (
                    <Card className="flex flex-col gap-3 rounded-2xl border-amber-500/40 bg-amber-500/5 p-4 shadow-none sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-start gap-3">
                            <Clock3 className="text-warning-strong mt-0.5 size-5 shrink-0" />
                            <div>
                                <div className="text-foreground text-[13.5px] font-bold">
                                    Corrección pendiente
                                </div>
                                <p className="text-muted-foreground text-[12px]">
                                    Emite la venta corregida en 7 días. Hasta
                                    entonces los certificados siguen vigentes;
                                    después se anulan.
                                </p>
                            </div>
                        </div>
                    </Card>
                ) : null}

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <nav
                        aria-label="Relacionado con esta venta"
                        className="flex flex-wrap gap-2"
                    >
                        {accesos.map((acceso) => (
                            <a
                                key={acceso.texto}
                                href={acceso.href}
                                className="border-border bg-card hover:border-primary/60 focus-visible:ring-ring inline-flex min-h-10 items-center gap-2 rounded-lg border px-3 text-[13px] font-semibold focus-visible:ring-2 focus-visible:outline-none"
                            >
                                <span className="font-['Oswald',sans-serif] text-base font-semibold">
                                    {acceso.cantidad}
                                </span>
                                {acceso.texto}
                            </a>
                        ))}
                    </nav>
                    {documento && ventaActiva && !rechazado ? (
                        <ol
                            aria-label="Estado en SUNAT"
                            className="border-border bg-card flex overflow-hidden rounded-lg border text-xs font-bold"
                        >
                            {pasosSunat.map((paso, indice) => (
                                <li
                                    key={paso}
                                    aria-current={
                                        indice === pasoSunat
                                            ? 'step'
                                            : undefined
                                    }
                                    className={
                                        indice === pasoSunat
                                            ? 'text-success-strong bg-emerald-500/10 px-3 py-2'
                                            : 'text-muted-foreground px-3 py-2'
                                    }
                                >
                                    {paso}
                                </li>
                            ))}
                        </ol>
                    ) : null}
                </div>

                <div className="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
                    <aside className="flex min-w-0 flex-col gap-4 lg:sticky lg:top-4 lg:order-last">
                        <Card className="border-border bg-card gap-3 rounded-2xl p-5 shadow-none">
                            <h2 className="font-['Oswald',sans-serif] text-[18px] font-semibold uppercase">
                                Saldo
                            </h2>
                            <dl className="flex flex-col gap-2 text-[13px]">
                                {lineasSaldo.map(([etiqueta, monto]) => (
                                    <div
                                        key={etiqueta}
                                        className="flex justify-between gap-3"
                                    >
                                        <dt className="text-muted-foreground">
                                            {etiqueta}
                                        </dt>
                                        <dd className="font-semibold tabular-nums">
                                            {soles(monto)}
                                        </dd>
                                    </div>
                                ))}
                                <div className="border-border flex items-baseline justify-between gap-3 border-t pt-3">
                                    <dt className="font-bold">Pendiente</dt>
                                    <dd className="font-['Oswald',sans-serif] text-[25px] font-semibold tabular-nums">
                                        {soles(resumen.pendiente)}
                                    </dd>
                                </div>
                            </dl>
                            {resumen.pendiente > 0 ? (
                                <Button
                                    asChild
                                    className="bg-primary hover:bg-primary/90 h-10 rounded-lg text-white shadow-none"
                                >
                                    <Link href={cobranzas.index.url(teamSlug)}>
                                        <Banknote className="size-4" />
                                        Registrar pago
                                    </Link>
                                </Button>
                            ) : null}
                        </Card>
                        <HistorialActividad historial={historial} />
                    </aside>

                    <div className="flex min-w-0 flex-col gap-4">
                        <div className="grid gap-4 sm:grid-cols-3">
                            <Card className="border-border bg-card gap-1 rounded-2xl p-4 shadow-none">
                                <CalendarDays className="text-warning-strong size-5" />
                                <div className="text-muted-foreground text-xs font-bold uppercase">
                                    Fecha
                                </div>
                                <div className="font-['IBM_Plex_Mono',monospace] text-[13px] font-bold">
                                    {fechaCorta(sale.fecha) || '-'}
                                </div>
                            </Card>
                            <Card className="border-border bg-card gap-1 rounded-2xl p-4 shadow-none">
                                <ReceiptText className="text-foreground size-5" />
                                <div className="text-muted-foreground text-xs font-bold uppercase">
                                    Comprobante
                                </div>
                                <div className="font-['IBM_Plex_Mono',monospace] text-[13px] font-bold uppercase">
                                    {nice(sale.comprobante_tipo)}
                                </div>
                            </Card>
                            <Card className="border-border bg-card gap-1 rounded-2xl p-4 shadow-none">
                                <Banknote className="text-success-strong size-5" />
                                <div className="text-muted-foreground text-xs font-bold uppercase">
                                    Condición
                                </div>
                                <div className="text-[13px] font-bold capitalize">
                                    {sale.condicion_pago === 'credito'
                                        ? 'Crédito'
                                        : nice(sale.condicion_pago)}
                                </div>
                            </Card>
                        </div>

                        <Card className="border-border bg-card gap-0 rounded-2xl p-5 shadow-none">
                            <h2 className="mb-3 font-['Oswald',sans-serif] text-[18px] font-semibold uppercase">
                                Items
                            </h2>
                            <div className="overflow-x-auto">
                                {detraccion?.aplica && (
                                    <div className="border-border mb-4 rounded-lg border p-3 text-sm">
                                        <p>
                                            Detracción {detraccion.codigo_bien}:{' '}
                                            {soles(detraccion.monto)} ·
                                            Acreditada:{' '}
                                            {soles(detraccion.acreditado)} ·
                                            Pendiente:{' '}
                                            {soles(detraccion.pendiente)}
                                        </p>
                                        {sale.estado === 'confirmada' &&
                                            sale.condicion_pago === 'contado' &&
                                            detraccion.pendiente > 0 && (
                                                <form
                                                    className="mt-3 flex flex-wrap items-end gap-3"
                                                    onSubmit={(event) => {
                                                        event.preventDefault();
                                                        constanciaForm.post(
                                                            detracciones.store.url(
                                                                {
                                                                    current_team:
                                                                        teamSlug,
                                                                    sale: sale.id,
                                                                },
                                                            ),
                                                            {
                                                                preserveScroll: true,
                                                                onSuccess: () =>
                                                                    constanciaForm.reset(
                                                                        'numero_operacion',
                                                                        'fecha_deposito',
                                                                        'evidencia',
                                                                    ),
                                                            },
                                                        );
                                                    }}
                                                >
                                                    <label className="flex flex-col gap-1">
                                                        Monto depositado (S/)
                                                        <input
                                                            className="border-border rounded border p-2"
                                                            type="number"
                                                            min="0.01"
                                                            max={
                                                                detraccion.pendiente
                                                            }
                                                            step="0.01"
                                                            required
                                                            value={
                                                                constanciaForm
                                                                    .data.monto
                                                            }
                                                            onChange={(event) =>
                                                                constanciaForm.setData(
                                                                    'monto',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                        />
                                                    </label>
                                                    <label className="flex flex-col gap-1">
                                                        Número de constancia BN
                                                        <input
                                                            className="border-border rounded border p-2"
                                                            required
                                                            maxLength={255}
                                                            value={
                                                                constanciaForm
                                                                    .data
                                                                    .numero_operacion
                                                            }
                                                            onChange={(event) =>
                                                                constanciaForm.setData(
                                                                    'numero_operacion',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                        />
                                                    </label>
                                                    <label className="flex flex-col gap-1">
                                                        Fecha real del depósito
                                                        <input
                                                            className="border-border rounded border p-2"
                                                            type="date"
                                                            required
                                                            value={
                                                                constanciaForm
                                                                    .data
                                                                    .fecha_deposito
                                                            }
                                                            onChange={(event) =>
                                                                constanciaForm.setData(
                                                                    'fecha_deposito',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                        />
                                                    </label>
                                                    <label className="flex flex-col gap-1">
                                                        Constancia PDF o foto
                                                        (opcional, hasta 5 MB)
                                                        <input
                                                            className="border-border rounded border p-2"
                                                            type="file"
                                                            accept="application/pdf,image/jpeg,image/png"
                                                            onChange={(event) =>
                                                                constanciaForm.setData(
                                                                    'evidencia',
                                                                    event.target
                                                                        .files?.[0] ??
                                                                        null,
                                                                )
                                                            }
                                                        />
                                                    </label>
                                                    <Button
                                                        type="submit"
                                                        loading={
                                                            constanciaForm.processing
                                                        }
                                                    >
                                                        Acreditar depósito
                                                        verificado
                                                    </Button>
                                                    <p className="text-muted-foreground w-full text-xs">
                                                        El depósito reduce la
                                                        deuda y queda fuera de
                                                        caja. La fecha debe
                                                        coincidir con la
                                                        constancia. El sistema
                                                        registra tu
                                                        verificación; no
                                                        consulta automáticamente
                                                        al banco.
                                                    </p>
                                                    {Object.entries(
                                                        constanciaForm.errors,
                                                    ).map(([field, error]) => (
                                                        <p
                                                            key={field}
                                                            role="alert"
                                                            className="text-destructive-strong w-full text-xs"
                                                        >
                                                            {error}
                                                        </p>
                                                    ))}
                                                </form>
                                            )}
                                        {(sale.payments ?? [])
                                            .filter(
                                                (payment) =>
                                                    payment.es_detraccion &&
                                                    payment.tiene_constancia,
                                            )
                                            .map((payment) => (
                                                <a
                                                    key={payment.id}
                                                    className="text-primary mt-2 block text-xs underline"
                                                    href={detracciones.evidencia.url(
                                                        {
                                                            current_team:
                                                                teamSlug,
                                                            sale: sale.id,
                                                            payment: payment.id,
                                                        },
                                                    )}
                                                >
                                                    Descargar constancia{' '}
                                                    {payment.numero_operacion}
                                                </a>
                                            ))}
                                    </div>
                                )}
                                <Table className="w-full border-collapse text-[12.5px]">
                                    <TableHeader>
                                        <TableRow>
                                            {[
                                                'Producto',
                                                'Serie',
                                                'Tipo',
                                                'Cant.',
                                                'P. Unit.',
                                                'Desc.',
                                                'Subtotal',
                                            ].map((h) => (
                                                <TableHead
                                                    key={h}
                                                    className="text-left"
                                                >
                                                    {h}
                                                </TableHead>
                                            ))}
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {(sale.items ?? []).map((item) => (
                                            <TableRow key={item.id}>
                                                <TableCell className="border-border border-b px-2.5 py-[13px] font-semibold">
                                                    {item.product?.nombre ??
                                                        item.service?.nombre ??
                                                        '-'}
                                                </TableCell>
                                                <TableCell className="border-border border-b px-2.5 py-[13px] font-['IBM_Plex_Mono',monospace] whitespace-nowrap tabular-nums">
                                                    {item.numero_serie ?? '-'}
                                                    {puedeCambiarExtintor &&
                                                    item.tipo_linea ===
                                                        'unidad_nueva' &&
                                                    item.numero_serie &&
                                                    item.product_id ? (
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                setLineaACambiar(
                                                                    {
                                                                        id: item.id,
                                                                        product_id:
                                                                            item.product_id!,
                                                                        nombre:
                                                                            item
                                                                                .product
                                                                                ?.nombre ??
                                                                            '',
                                                                        numero_serie:
                                                                            item.numero_serie!,
                                                                    },
                                                                )
                                                            }
                                                            className="text-primary-strong ml-2 font-sans text-xs font-semibold hover:underline"
                                                        >
                                                            Cambiar
                                                        </button>
                                                    ) : null}
                                                </TableCell>
                                                <TableCell className="border-border border-b px-2.5 py-[13px] capitalize">
                                                    {nice(item.tipo_linea)}
                                                </TableCell>
                                                <TableCell className="border-border border-b px-2.5 py-[13px]">
                                                    {item.cantidad ?? 1}
                                                </TableCell>
                                                <TableCell className="border-border border-b px-2.5 py-[13px]">
                                                    {soles(
                                                        item.precio_unitario,
                                                    )}
                                                </TableCell>
                                                <TableCell className="border-border border-b px-2.5 py-[13px]">
                                                    {soles(item.descuento)}
                                                </TableCell>
                                                <TableCell className="border-border border-b px-2.5 py-[13px] font-bold">
                                                    {soles(item.subtotal)}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        </Card>

                        <Card
                            id="pagos-de-la-venta"
                            className="border-border bg-card scroll-mt-4 gap-0 rounded-2xl p-5 shadow-none"
                        >
                            <h2 className="mb-3 font-['Oswald',sans-serif] text-[18px] font-semibold uppercase">
                                Pagos
                            </h2>
                            <div className="overflow-x-auto">
                                <Table className="w-full border-collapse text-[12.5px]">
                                    <TableHeader>
                                        <TableRow>
                                            {[
                                                'Forma',
                                                'Fecha',
                                                'Monto',
                                                'Estado',
                                            ].map((h) => (
                                                <TableHead
                                                    key={h}
                                                    className="text-left"
                                                >
                                                    {h}
                                                </TableHead>
                                            ))}
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {(sale.payments ?? []).length === 0 ? (
                                            <TableRow>
                                                <TableCell colSpan={4}>
                                                    <Empty
                                                        icon={<Inbox />}
                                                        title="Sin pagos registrados"
                                                    />
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            (sale.payments ?? []).map(
                                                (payment) => (
                                                    <TableRow key={payment.id}>
                                                        <TableCell className="border-border border-b px-2.5 py-[13px] capitalize">
                                                            {payment.es_detraccion
                                                                ? 'Detracción BN'
                                                                : medioPago(
                                                                      payment.forma_pago,
                                                                  )}
                                                            {payment.numero_operacion && (
                                                                <span className="text-muted-foreground ml-2 text-xs">
                                                                    {
                                                                        payment.numero_operacion
                                                                    }
                                                                </span>
                                                            )}
                                                        </TableCell>
                                                        <TableCell className="border-border border-b px-2.5 py-[13px]">
                                                            {(
                                                                payment.fecha_pago ??
                                                                payment.fecha
                                                            )
                                                                ?.split('T')[0]
                                                                .split('-')
                                                                .reverse()
                                                                .join('/') ??
                                                                '-'}
                                                        </TableCell>
                                                        <TableCell className="border-border border-b px-2.5 py-[13px] font-bold">
                                                            {soles(
                                                                payment.monto,
                                                            )}
                                                        </TableCell>
                                                        <TableCell className="border-border border-b px-2.5 py-[13px] capitalize">
                                                            {payment.estado ??
                                                                'Registrado'}
                                                        </TableCell>
                                                    </TableRow>
                                                ),
                                            )
                                        )}
                                    </TableBody>
                                </Table>
                            </div>
                        </Card>

                        {(sale.estado === 'confirmada' &&
                            (tieneEquipos || tiposServicio.length > 0)) ||
                        certificados.length > 0 ? (
                            <Card
                                id="certificados-de-la-venta"
                                className="border-border bg-card scroll-mt-4 gap-3 rounded-2xl p-5 shadow-none"
                            >
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
                                            className="bg-primary hover:bg-primary/90 h-9 rounded-lg text-white shadow-none"
                                        >
                                            <Link
                                                href={ventas.certificados.create.url(
                                                    {
                                                        current_team: teamSlug,
                                                        sale: sale.id,
                                                    },
                                                )}
                                            >
                                                <Award className="size-4" />
                                                Gestionar certificados de venta
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
                                                className="border-border hover:border-primary/60 hover:text-primary-strong rounded-full border px-2.5 py-1 text-xs font-bold transition-colors"
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
                                            : 'Aún no hay certificados emitidos. Los del servicio de taller se generan al cerrar el trabajo; la capacitación se registra con sus datos.'}
                                    </p>
                                ) : (
                                    <div className="border-border overflow-hidden rounded-lg border">
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
                                                        className="border-border bg-card h-8 rounded-lg shadow-none"
                                                    >
                                                        <a
                                                            href={CertificadosRoutes.pdf.url(
                                                                {
                                                                    current_team:
                                                                        teamSlug,
                                                                    certificate:
                                                                        c.id,
                                                                },
                                                                {
                                                                    query: {
                                                                        inline: 1,
                                                                    },
                                                                },
                                                            )}
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
                                                        className="border-border bg-card h-8 rounded-lg shadow-none"
                                                        title="Descargar Word editable"
                                                    >
                                                        <a
                                                            href={CertificadosRoutes.word.url(
                                                                {
                                                                    current_team:
                                                                        teamSlug,
                                                                    certificate:
                                                                        c.id,
                                                                },
                                                            )}
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

                        <DocumentosRelacionados documentos={relacionados} />

                        <SaleNotesPanel
                            teamSlug={teamSlug}
                            documents={sale.electronic_documents ?? []}
                            saleId={sale.id}
                            saleEstado={sale.estado}
                        />
                    </div>
                </div>
            </div>

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
                detalle="La operación está en curso. El resultado se mostrará al terminar."
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
