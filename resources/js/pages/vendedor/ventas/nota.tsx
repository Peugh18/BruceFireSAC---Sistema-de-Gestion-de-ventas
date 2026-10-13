import { Link, useForm, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    Clock,
    FileMinus2,
    FilePlus2,
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
import { soles } from '@/lib/utils';
import notasCredito from '@/routes/vendedor/notas-credito';
import notasDebito from '@/routes/vendedor/notas-debito';
import ventas from '@/routes/vendedor/ventas';
import type { Team } from '@/types';

type Linea = {
    indice: number;
    nombre: string;
    cantidad: number;
    total: number;
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
    notas: NotaEmitida[];
};

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
}: Props) {
    const { currentTeam, flash } = usePage<{
        currentTeam?: Team | null;
        flash?: { success?: string; error?: string };
    }>().props;
    const teamSlug = currentTeam?.slug ?? '';
    const esCredito = tipo === 'credito';
    const titulo = esCredito ? 'Nota de crédito' : 'Nota de débito';

    // Crédito: anular todo (por defecto) o devolver solo una parte.
    const [parcial, setParcial] = useState(false);
    const [devueltas, setDevueltas] = useState<Record<number, number>>({});
    const [devolverStock, setDevolverStock] = useState(false);
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
                              })),
                      },
                  }
                : {
                      motivo_catalogo: '01',
                      detalle: 'Anulación de la operación',
                      ...(devolverStock
                          ? { nota_datos: { devolucion_fisica: true } }
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
                    setParcial(false);
                    setDevolverStock(false);
                },
                onError: (errores) =>
                    toast.error(
                        Object.values(errores)[0] ??
                            'No se pudo emitir la nota.',
                    ),
            },
        );
    };

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
                        esCredito
                            ? 'Sobre la misma boleta o factura. Por defecto anula todo: revisa y acepta.'
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
                                {original.fecha ? ` · ${original.fecha}` : ''}
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
                                                <Input
                                                    type="number"
                                                    min={0}
                                                    max={l.cantidad}
                                                    step={1}
                                                    aria-label={`Cantidad que devuelve de ${l.nombre}`}
                                                    value={
                                                        devueltas[l.indice] ?? 0
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
                                                                        ) || 0,
                                                                        0,
                                                                    ),
                                                                    l.cantidad,
                                                                ),
                                                        })
                                                    }
                                                    className="ml-auto h-9 w-20 text-right"
                                                />
                                            </TableCell>
                                        ) : null}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>

                    {anulada ? null : esCredito ? (
                        <div className="flex flex-col gap-3">
                            {!parcial ? (
                                <label className="text-foreground flex items-start gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={devolverStock}
                                        onChange={(e) =>
                                            setDevolverStock(e.target.checked)
                                        }
                                        className="mt-1"
                                    />
                                    <span>
                                        Ya recibí los productos: vuelven al
                                        stock. Si no lo marcas, solo se anula el
                                        comprobante.
                                    </span>
                                </label>
                            ) : null}
                            <button
                                type="button"
                                onClick={() => {
                                    setParcial(!parcial);
                                    setDevueltas({});
                                }}
                                className="text-primary-strong min-h-9 self-start text-sm font-semibold underline-offset-2 hover:underline"
                            >
                                {parcial
                                    ? 'Mejor anular todo'
                                    : '¿Solo devuelve una parte? Elegir qué devuelve'}
                            </button>
                        </div>
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

                    <div className="border-border flex flex-col gap-3 border-t pt-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p className="text-muted-foreground text-xs font-semibold uppercase">
                                {esCredito ? 'Se devuelve' : 'Se cobra'}
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
