import { Link, useForm, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    Clock,
    FileMinus2,
    FilePlus2,
    RotateCcw,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

import ArchivosNota from '@/components/archivos-nota';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import VendedorLayout from '@/layouts/vendedor-layout';
import { fechaCorta, soles } from '@/lib/utils';
import notasCredito from '@/routes/vendedor/notas-credito';
import notasDebito from '@/routes/vendedor/notas-debito';
import ventas from '@/routes/vendedor/ventas';
import type { Team } from '@/types';

type Linea = {
    indice: number;
    nombre: string;
    cantidad: number;
    total: number;
    devuelta: number;
    series: string[];
};

type NotaEmitida = {
    id: number;
    tipo: 'nota_credito' | 'nota_debito';
    numero: string;
    importe: number;
    estado: string;
    mensaje: string | null;
};

type Props = {
    tipo: 'credito' | 'debito';
    anulada: boolean;
    venta: { id: number; numero: string; cliente: string; documento: string };
    original: {
        id: number;
        tipo: string;
        numero: string;
        fecha: string | null;
        total: number;
    };
    porAcreditar: number;
    lineas: Linea[];
    hay_productos?: boolean;
    notas: NotaEmitida[];
    resumen: { pendiente: number; devolvible: number };
};

type Modo = 'corregir' | 'devolver_todo' | 'devolver_parte';

const MODOS: { valor: Modo; titulo: string; detalle: string }[] = [
    {
        valor: 'corregir',
        titulo: 'Corregir un error del comprobante',
        detalle:
            'Boleta en vez de factura, o cliente o datos equivocados. Se anula este comprobante y emites uno nuevo; los certificados se conservan.',
    },
    {
        valor: 'devolver_todo',
        titulo: 'El cliente devuelve todo',
        detalle: 'Se anula la venta y sus certificados. No se vuelve a emitir.',
    },
    {
        valor: 'devolver_parte',
        titulo: 'El cliente devuelve una parte',
        detalle: 'Eliges qué devuelve. El comprobante sigue vigente.',
    },
];

const redondear = (valor: number) =>
    Math.round((valor + Number.EPSILON) * 100) / 100;

/**
 * La nota de crédito o débito sobre la misma boleta o factura: sus ítems ya
 * están puestos y el importe sale solo. El vendedor acepta y sale a SUNAT.
 */
export default function NotaDeVenta({
    tipo,
    anulada,
    venta,
    original,
    porAcreditar,
    lineas,
    notas,
    hay_productos = true,
    resumen,
}: Props) {
    const { currentTeam, flash } = usePage<{
        currentTeam?: Team | null;
        flash?: { success?: string; error?: string };
    }>().props;
    const teamSlug = currentTeam?.slug ?? '';
    const esCredito = tipo === 'credito';
    const titulo = esCredito ? 'Nota de crédito' : 'Nota de débito';

    // Crédito: lo normal es corregir un error; las devoluciones son la excepción.
    const [modo, setModo] = useState<Modo>('corregir');
    const parcial = modo === 'devolver_parte';
    const [devueltas, setDevueltas] = useState<Record<number, number>>({});
    const [seriesElegidas, setSeriesElegidas] = useState<
        Record<number, string[]>
    >({});
    const alternarSerie = (indice: number, serie: string) => {
        const actuales = seriesElegidas[indice] ?? [];
        const series = actuales.includes(serie)
            ? actuales.filter((s) => s !== serie)
            : [...actuales, serie];
        setSeriesElegidas({ ...seriesElegidas, [indice]: series });
        setDevueltas({ ...devueltas, [indice]: series.length });
    };
    // Débito: cobro por pago tardío o ajuste de precio de un ítem.
    const [cobro, setCobro] = useState<'mora' | 'precio'>('mora');
    const [montoMora, setMontoMora] = useState('');
    const [lineaAjuste, setLineaAjuste] = useState(lineas[0]?.indice ?? 0);
    const [precioNuevo, setPrecioNuevo] = useState('');

    const linea = lineas.find((l) => l.indice === lineaAjuste);
    const precioActual = linea ? redondear(linea.total / linea.cantidad) : 0;
    const importeAjuste = linea
        ? redondear(
              Math.max((Number(precioNuevo) || 0) - precioActual, 0) *
                  linea.cantidad,
          )
        : 0;
    const importeParcial = redondear(
        lineas.reduce(
            (suma, l) =>
                suma +
                redondear(
                    (l.total * Math.min(devueltas[l.indice] ?? 0, l.cantidad)) /
                        l.cantidad,
                ),
            0,
        ),
    );

    const importe = esCredito
        ? parcial
            ? importeParcial
            : porAcreditar
        : cobro === 'mora'
          ? redondear(Number(montoMora) || 0)
          : importeAjuste;

    const form = useForm({});

    const aceptar = () => {
        const datos = esCredito
            ? parcial
                ? {
                      motivo_catalogo: '07',
                      detalle: 'Devolución de productos',
                      nota_datos: {
                          lineas: lineas
                              .filter((l) => (devueltas[l.indice] ?? 0) > 0)
                              .map((l) => ({
                                  indice: l.indice,
                                  cantidad: devueltas[l.indice],
                                  ...(l.series.length > 0
                                      ? { series: seriesElegidas[l.indice] }
                                      : {}),
                              })),
                      },
                  }
                : {
                      motivo_catalogo: '01',
                      detalle: 'Anulación de la operación',
                      ...(modo === 'corregir'
                          ? { nota_datos: { correccion: true } }
                          : {}),
                  }
            : cobro === 'mora'
              ? { motivo_catalogo: '01', detalle: 'Intereses por mora' }
              : {
                    motivo_catalogo: '02',
                    detalle: `Aumento en el valor: ${linea?.nombre ?? ''}`,
                };

        form.transform(() => ({
            electronic_document_id: original.id,
            importe: importe.toFixed(2),
            ...datos,
        }));
        form.post(
            esCredito
                ? notasCredito.store.url(teamSlug)
                : notasDebito.store.url(teamSlug),
            {
                preserveScroll: true,
                // Lo escrito se limpia: así no se emite otra nota por error.
                onSuccess: (page) => {
                    if (
                        (page.props.flash as { error?: string } | undefined)
                            ?.error
                    ) {
                        return;
                    }
                    setMontoMora('');
                    setPrecioNuevo('');
                    setDevueltas({});
                    setSeriesElegidas({});
                    setModo('corregir');
                },
                onError: (errores) =>
                    toast.error(
                        Object.values(errores)[0] ??
                            'No se pudo emitir la nota.',
                    ),
            },
        );
    };

    // Lo mismo que hará el sistema cuando SUNAT acepte la nota: anular todo
    // cierra las cuotas y devuelve lo cobrado; una nota parcial rebaja el saldo
    // y solo el excedente sale de caja; un débito agrega una cuota.
    const anulaTodo = esCredito && !parcial;
    const rebaja = Math.min(importe, resumen.pendiente);
    const excedente = redondear(importe - rebaja);
    const devuelveCaja = anulaTodo
        ? resumen.devolvible
        : esCredito
          ? Math.min(excedente, resumen.devolvible)
          : 0;
    const saldoFinal = anulaTodo
        ? 0
        : esCredito
          ? redondear(resumen.pendiente - rebaja)
          : redondear(resumen.pendiente + importe);
    const efecto: [string, string][] = [
        [
            'Comprobante',
            anulaTodo ? 'Queda anulado con su venta' : 'Sigue vigente',
        ],
        ['Saldo pendiente', soles(saldoFinal)],
        [
            'Caja',
            devuelveCaja > 0
                ? `Devuelve ${soles(devuelveCaja)}`
                : 'Sin movimiento',
        ],
        [
            'Stock',
            hay_productos && (anulaTodo || (parcial && importe > 0))
                ? parcial
                    ? 'Vuelven los productos elegidos'
                    : 'Vuelven los productos'
                : 'Sin cambios',
        ],
    ];

    const ultima = notas.find((n) =>
        esCredito ? n.tipo === 'nota_credito' : n.tipo === 'nota_debito',
    );
    const sinSaldo = anulada || (esCredito && porAcreditar <= 0);

    return (
        <VendedorLayout title={titulo}>
            <div className="mx-auto flex max-w-4xl flex-col gap-4">
                <div className="flex items-center gap-3">
                    <Link
                        href={ventas.show.url({
                            current_team: teamSlug,
                            sale: venta.id,
                        })}
                        className="text-muted-foreground hover:text-foreground inline-flex min-h-11 items-center gap-1 text-sm font-semibold"
                    >
                        <ArrowLeft className="size-4" />
                        {venta.numero}
                    </Link>
                </div>

                <PageHeader
                    title={titulo}
                    description={
                        anulada
                            ? 'Esta venta ya está anulada.'
                            : esCredito
                              ? 'Sobre esta misma boleta o factura. Elige qué pasó y revisa el efecto antes de aceptar.'
                              : 'Cobra lo que se agregó después de la boleta o factura: intereses por pagar tarde o un precio que subió.'
                    }
                />

                {flash?.error ? (
                    <div
                        role="alert"
                        className="border-border text-destructive-strong rounded-xl border p-4 text-sm break-words"
                    >
                        {flash.error}
                    </div>
                ) : null}
                {flash?.success ? (
                    <div
                        role="status"
                        className="border-border text-success-strong rounded-xl border p-4 text-sm break-words"
                    >
                        {flash.success}
                    </div>
                ) : null}

                {anulada && esCredito ? (
                    <Card className="border-border bg-card flex flex-col gap-3 rounded-2xl p-4 shadow-none sm:flex-row sm:items-center sm:justify-between">
                        <p className="text-foreground text-sm font-semibold">
                            ¿Era un error? Rehaz la venta: ya trae el cliente y
                            los productos, y puedes cambiar a boleta o factura.
                        </p>
                        <Button asChild className="h-11 shrink-0 rounded-lg">
                            <Link
                                href={ventas.create.url(teamSlug, {
                                    query: { rehacer: venta.id },
                                })}
                            >
                                <RotateCcw className="size-4" />
                                Rehacer venta
                            </Link>
                        </Button>
                    </Card>
                ) : null}

                {ultima ? (
                    <Card className="border-border bg-card flex flex-col gap-3 rounded-2xl p-4 shadow-none sm:flex-row sm:items-center">
                        {ultima.estado === 'aceptado' ? (
                            <CheckCircle2
                                className="text-success-strong size-6 shrink-0"
                                aria-hidden="true"
                            />
                        ) : ultima.estado === 'rechazado' ? (
                            <XCircle
                                className="text-destructive-strong size-6 shrink-0"
                                aria-hidden="true"
                            />
                        ) : ultima.estado === 'observado' ||
                          ultima.estado === 'excepcion' ? (
                            <AlertTriangle
                                className="text-warning-strong size-6 shrink-0"
                                aria-hidden="true"
                            />
                        ) : (
                            <Clock
                                className="text-muted-foreground size-6 shrink-0"
                                aria-hidden="true"
                            />
                        )}
                        <div className="min-w-0 flex-1 text-sm">
                            <p className="text-foreground font-semibold">
                                {titulo} {ultima.numero} ·{' '}
                                {soles(ultima.importe)}
                            </p>
                            <p className="text-muted-foreground text-xs break-words">
                                SUNAT: {ultima.estado}
                                {ultima.mensaje ? ` · ${ultima.mensaje}` : ''}
                            </p>
                        </div>
                        <ArchivosNota
                            teamSlug={teamSlug}
                            id={ultima.id}
                            aceptada={['aceptado', 'observado'].includes(
                                ultima.estado,
                            )}
                        />
                    </Card>
                ) : null}

                <Card className="border-border bg-card gap-4 rounded-2xl p-5 shadow-none">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p className="text-muted-foreground text-xs font-semibold uppercase">
                                {titulo} sobre {original.tipo}
                            </p>
                            <p className="text-foreground font-['IBM_Plex_Mono',monospace] text-lg font-bold">
                                {original.numero}
                            </p>
                        </div>
                        <div className="text-right text-sm">
                            <p className="text-foreground font-semibold">
                                {venta.cliente}
                            </p>
                            <p className="text-muted-foreground text-xs">
                                {venta.documento}
                                {original.fecha
                                    ? ` · ${fechaCorta(original.fecha)}`
                                    : ''}
                            </p>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <Table className="w-full text-sm">
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Ítem</TableHead>
                                    <TableHead className="text-right">
                                        Cant.
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Total
                                    </TableHead>
                                    {esCredito && parcial ? (
                                        <TableHead className="text-right">
                                            Devuelve
                                        </TableHead>
                                    ) : null}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {lineas.map((l) => (
                                    <TableRow key={l.indice}>
                                        <TableCell className="text-foreground font-medium">
                                            {l.nombre}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {l.cantidad}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {soles(l.total)}
                                        </TableCell>
                                        {esCredito && parcial ? (
                                            <TableCell className="text-right">
                                                {l.series.length > 0 ? (
                                                    <div className="flex flex-col items-end gap-1">
                                                        {l.series.map(
                                                            (serie) => (
                                                                <label
                                                                    key={serie}
                                                                    className="flex items-center gap-2 font-['IBM_Plex_Mono',monospace] text-xs"
                                                                >
                                                                    <input
                                                                        type="checkbox"
                                                                        checked={(
                                                                            seriesElegidas[
                                                                                l
                                                                                    .indice
                                                                            ] ??
                                                                            []
                                                                        ).includes(
                                                                            serie,
                                                                        )}
                                                                        onChange={() =>
                                                                            alternarSerie(
                                                                                l.indice,
                                                                                serie,
                                                                            )
                                                                        }
                                                                    />
                                                                    {serie}
                                                                </label>
                                                            ),
                                                        )}
                                                    </div>
                                                ) : (
                                                    <Input
                                                        type="number"
                                                        min={0}
                                                        max={
                                                            l.cantidad -
                                                            l.devuelta
                                                        }
                                                        step={1}
                                                        aria-label={`Cantidad que devuelve de ${l.nombre}`}
                                                        value={
                                                            devueltas[
                                                                l.indice
                                                            ] ?? 0
                                                        }
                                                        onChange={(e) =>
                                                            setDevueltas({
                                                                ...devueltas,
                                                                [l.indice]:
                                                                    Math.min(
                                                                        Math.max(
                                                                            Number(
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                            ) ||
                                                                                0,
                                                                            0,
                                                                        ),
                                                                        l.cantidad -
                                                                            l.devuelta,
                                                                    ),
                                                            })
                                                        }
                                                        className="ml-auto h-9 w-20 text-right"
                                                    />
                                                )}
                                                {l.devuelta > 0 ? (
                                                    <span className="text-muted-foreground mt-1 block text-xs">
                                                        Ya devuelto:{' '}
                                                        {l.devuelta}
                                                    </span>
                                                ) : null}
                                            </TableCell>
                                        ) : null}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>

                    {anulada ? null : esCredito ? (
                        <fieldset className="flex flex-col gap-2">
                            <legend className="text-foreground mb-2 text-sm font-bold">
                                ¿Qué pasó?
                            </legend>
                            {MODOS.map((opcion) => (
                                <label
                                    key={opcion.valor}
                                    className={`flex min-h-11 cursor-pointer items-start gap-3 rounded-xl border p-3 text-sm ${modo === opcion.valor ? 'border-primary bg-destructive/10' : 'border-border'}`}
                                >
                                    <input
                                        type="radio"
                                        name="que-paso"
                                        checked={modo === opcion.valor}
                                        onChange={() => {
                                            setModo(opcion.valor);
                                            setDevueltas({});
                                            setSeriesElegidas({});
                                        }}
                                        className="mt-1"
                                    />
                                    <span>
                                        <span className="text-foreground block font-semibold">
                                            {opcion.titulo}
                                        </span>
                                        <span className="text-muted-foreground block text-xs">
                                            {opcion.detalle}
                                        </span>
                                    </span>
                                </label>
                            ))}
                            {modo === 'corregir' ? (
                                <p className="text-muted-foreground text-xs">
                                    Lo cobrado figura como devuelto y al rehacer
                                    la venta registras el cobro otra vez.
                                </p>
                            ) : null}
                        </fieldset>
                    ) : (
                        <div className="grid gap-3 sm:grid-cols-2">
                            {(
                                [
                                    ['mora', 'Cobro por pago tardío'],
                                    ['precio', 'Ajuste de precio'],
                                ] as const
                            ).map(([valor, texto]) => (
                                <button
                                    key={valor}
                                    type="button"
                                    onClick={() => setCobro(valor)}
                                    aria-pressed={cobro === valor}
                                    className={`min-h-11 rounded-xl border px-4 py-3 text-left text-sm font-semibold transition-colors ${cobro === valor ? 'border-primary bg-destructive/10 text-foreground' : 'border-border text-muted-foreground hover:text-foreground'}`}
                                >
                                    {texto}
                                </button>
                            ))}
                            {cobro === 'mora' ? (
                                <label className="text-muted-foreground flex flex-col gap-1 text-xs sm:col-span-2">
                                    Monto a cobrar (S/)
                                    <Input
                                        type="number"
                                        min={0.01}
                                        step={0.01}
                                        value={montoMora}
                                        onChange={(e) =>
                                            setMontoMora(e.target.value)
                                        }
                                        className="h-11 max-w-xs text-sm"
                                    />
                                </label>
                            ) : (
                                <div className="grid gap-3 sm:col-span-2 sm:grid-cols-2">
                                    <label className="text-muted-foreground flex flex-col gap-1 text-xs">
                                        Ítem
                                        <NativeSelect
                                            value={lineaAjuste}
                                            onChange={(e) =>
                                                setLineaAjuste(
                                                    Number(e.target.value),
                                                )
                                            }
                                            className="text-foreground h-11 text-sm"
                                        >
                                            {lineas.map((l) => (
                                                <option
                                                    key={l.indice}
                                                    value={l.indice}
                                                >
                                                    {l.nombre}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    </label>
                                    <label className="text-muted-foreground flex flex-col gap-1 text-xs">
                                        Nuevo precio por unidad (antes{' '}
                                        {soles(precioActual)})
                                        <Input
                                            type="number"
                                            min={0.01}
                                            step={0.01}
                                            value={precioNuevo}
                                            onChange={(e) =>
                                                setPrecioNuevo(e.target.value)
                                            }
                                            className="h-11 text-sm"
                                        />
                                    </label>
                                </div>
                            )}
                        </div>
                    )}

                    {sinSaldo ? null : (
                        <section
                            aria-labelledby="efecto-nota"
                            className="bg-muted/50 rounded-xl p-4"
                        >
                            <h2
                                id="efecto-nota"
                                className="text-foreground text-sm font-bold"
                            >
                                Qué pasará cuando SUNAT la acepte
                            </h2>
                            <dl className="mt-2 grid gap-2 text-sm sm:grid-cols-2">
                                {efecto.map(([etiqueta, valor]) => (
                                    <div
                                        key={etiqueta}
                                        className="flex justify-between gap-3"
                                    >
                                        <dt className="text-muted-foreground">
                                            {etiqueta}
                                        </dt>
                                        <dd className="text-foreground text-right font-semibold tabular-nums">
                                            {valor}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </section>
                    )}

                    <div className="border-border flex flex-col gap-3 border-t pt-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p className="text-muted-foreground text-xs font-semibold uppercase">
                                Importe de la nota
                            </p>
                            <p className="text-foreground font-['Oswald',sans-serif] text-3xl font-bold">
                                {soles(importe)}
                            </p>
                            {sinSaldo ? (
                                <p className="text-warning-strong text-xs">
                                    {anulada
                                        ? 'La venta quedó anulada: no admite más notas.'
                                        : 'Este comprobante ya no tiene saldo por devolver.'}
                                </p>
                            ) : null}
                        </div>
                        <Button
                            type="button"
                            onClick={aceptar}
                            loading={form.processing}
                            disabled={importe <= 0 || sinSaldo}
                            className="h-11 rounded-lg px-6 text-sm font-bold"
                        >
                            {esCredito ? (
                                <FileMinus2 className="size-4" />
                            ) : (
                                <FilePlus2 className="size-4" />
                            )}
                            Aceptar y enviar a SUNAT
                        </Button>
                    </div>
                </Card>
            </div>
        </VendedorLayout>
    );
}
