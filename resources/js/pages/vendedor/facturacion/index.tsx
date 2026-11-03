import { Link, router, usePage } from '@inertiajs/react';
import PestanasSunat from '@/components/pestanas-sunat';
import {
    AlertTriangle,
    Download,
    FileDown,
    FileSpreadsheet,
    RefreshCcw,
    Search,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

import { useBusquedaDinamica } from '@/hooks/use-busqueda-dinamica';
import FiltroSelect from '@/components/filtro-select';
import RangoFechas, { describirRango } from '@/components/rango-fechas';
import { CargaLarga, Cargando, FilasCargando } from '@/components/cargando';
import { Badge } from '@/components/ui/badge';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useRecargando } from '@/hooks/use-recargando';
import VendedorLayout from '@/layouts/vendedor-layout';
import facturacion from '@/routes/vendedor/facturacion';
import ventas from '@/routes/vendedor/ventas';
import type { Team } from '@/types';
import { etiquetaPaginacion, nombreDocumento, soles } from '@/lib/utils';

type DocumentRow = {
    id: number;
    sale_id?: number;
    tipo: string;
    serie: string;
    correlativo: number | string;
    modifica?: string | null;
    cliente: string;
    total: number | string;
    sunat_estado: string;
    tiene_xml?: boolean;
    tiene_cdr?: boolean;
    sunat_codigo_respuesta: string | null;
    sunat_mensaje?: string | null;
    sunat_mensaje_simple?: string | null;
    created_at: string | null;
};

type PaginationLink = { url: string | null; label: string; active: boolean };
type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
    from?: number | null;
    to?: number | null;
    total?: number;
};

type Props = {
    documents: Paginated<DocumentRow>;
    filters: {
        tipo?: string;
        estado?: string;
        buscar?: string;
        plazo?: string;
        desde: string;
        hasta: string;
    };
    hoy: string;
    totalFiltrados: number;
    kpis: {
        emitidos: number;
        aceptados: number;
        por_enviar: number;
        observados: number;
        rechazados: number;
    };
    /** S8: sin enviar a SUNAT que vencen hoy o mañana, y ya vencidos. */
    plazoSunat: { por_vencer: number; vencidos: number };
};

const TYPE_FILTERS = [
    { label: 'Todos', value: '' },
    { label: 'Facturas', value: 'factura' },
    { label: 'Boletas', value: 'boleta' },
    { label: 'N. Crédito/Débito', value: 'nota' },
];

function sunatBadge(estado: string) {
    const value = estado?.toLowerCase();
    if (value === 'aceptado')
        return 'bg-emerald-500/10 text-success-strong border border-emerald-500/20';
    if (value === 'observado')
        return 'bg-amber-500/10 text-warning-strong border border-amber-500/20';
    if (value === 'rechazado' || value === 'excepcion')
        return 'bg-destructive/10 text-destructive-strong border border-destructive/20';
    return 'bg-muted text-muted-foreground';
}

function sunatEstadoLabel(document: DocumentRow) {
    const estado = document.sunat_estado?.toLowerCase();
    if (estado === 'excepcion' || estado === 'rechazado') {
        // El motivo va debajo de la etiqueta, entero; aquí solo el estado.
        if (document.sunat_codigo_respuesta === '500') {
            return 'Rechazado: Error de comunicación SUNAT';
        }
        if (document.sunat_codigo_respuesta) {
            return `Rechazado (${document.sunat_codigo_respuesta})`;
        }
        return 'Rechazado';
    }
    if (estado === 'aceptado') return 'Aceptado';
    if (estado === 'observado') return 'Observado';
    if (estado === 'por_enviar') return 'Por enviar';
    if (estado === 'baja_pendiente') return 'Baja en proceso';
    if (estado === 'anulado') return 'Anulado (baja)';
    return document.sunat_estado || 'Pendiente';
}

export default function FacturacionIndex({
    documents,
    filters,
    kpis,
    plazoSunat,
    totalFiltrados,
    hoy,
}: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug =
        currentTeam?.slug ??
        (typeof window !== 'undefined'
            ? window.location.pathname.split('/')[1]
            : '');
    const [search, setSearch] = useState(filters.buscar ?? '');
    const [processingId, setProcessingId] = useState<number | null>(null);
    const recargando = useRecargando();
    const [marcados, setMarcados] = useState<number[]>([]);
    const [todoLoFiltrado, setTodoLoFiltrado] = useState(false);
    const [incluir, setIncluir] = useState<string[]>(['xml', 'cdr', 'pdf']);
    const [avisoDescarga, setAvisoDescarga] = useState(false);

    const filtrar = (cambios: Partial<Props['filters']>) => {
        const siguiente = { ...filters, ...cambios };
        setMarcados([]);
        setTodoLoFiltrado(false);
        router.get(
            facturacion.index.url(teamSlug, {
                query: {
                    tipo: siguiente.tipo || undefined,
                    estado: siguiente.estado || undefined,
                    desde: siguiente.desde,
                    hasta: siguiente.hasta,
                    buscar: siguiente.buscar || undefined,
                },
            }),
            {},
            { preserveScroll: true, preserveState: true },
        );
    };

    const changeType = (tipo: string) => filtrar({ tipo });

    const aplicarBusqueda = () => {
        filtrar({ buscar: search.trim() });
    };
    const submitSearch = useBusquedaDinamica(search, aplicarBusqueda);

    const idsPagina = documents.data.map((d) => d.id);
    const paginaMarcada =
        idsPagina.length > 0 && idsPagina.every((id) => marcados.includes(id));
    const cantidadSeleccionada = todoLoFiltrado
        ? totalFiltrados
        : marcados.length;

    const alternar = (id: number) => {
        setTodoLoFiltrado(false);
        setMarcados((actual) =>
            actual.includes(id)
                ? actual.filter((x) => x !== id)
                : [...actual, id],
        );
    };

    const alternarPagina = () => {
        setTodoLoFiltrado(false);
        setMarcados(
            paginaMarcada
                ? marcados.filter((id) => !idsPagina.includes(id))
                : Array.from(new Set([...marcados, ...idsPagina])),
        );
    };

    const [descargando, setDescargando] = useState<'zip' | 'excel' | null>(
        null,
    );

    /**
     * Descarga en segundo plano para mostrar "Preparando…" mientras el
     * servidor arma el archivo (un ZIP grande tarda varios segundos).
     */
    async function descargar(tipo: 'zip' | 'excel', url: string) {
        setDescargando(tipo);

        try {
            const respuesta = await fetch(url, {
                credentials: 'same-origin',
            });

            if (!respuesta.ok) {
                throw new Error(String(respuesta.status));
            }

            const nombre =
                /filename="?([^";]+)"?/.exec(
                    respuesta.headers.get('Content-Disposition') ?? '',
                )?.[1] ??
                (tipo === 'zip' ? 'comprobantes.zip' : 'comprobantes.xlsx');
            const enlace = document.createElement('a');
            enlace.href = URL.createObjectURL(await respuesta.blob());
            enlace.download = nombre;
            enlace.click();
            URL.revokeObjectURL(enlace.href);
        } catch {
            toast.error('No se pudo preparar la descarga. Inténtalo de nuevo.');
        } finally {
            setDescargando(null);
        }
    }

    const urlDescarga = (ruta: typeof facturacion.descargaMasiva) =>
        ruta.url(teamSlug, {
            query: {
                tipo: filters.tipo || undefined,
                estado: filters.estado || undefined,
                desde: filters.desde,
                hasta: filters.hasta,
                buscar: filters.buscar || undefined,
                ...(todoLoFiltrado ? {} : { ids: marcados }),
                incluir,
            },
        });

    const resend = (document: DocumentRow) => {
        setProcessingId(document.id);
        router.post(
            facturacion.resend.url({
                current_team: teamSlug,
                electronic_document: document.id,
            }),
            {},
            {
                preserveScroll: true,
                // El backend puede negarse (ya está en SUNAT, en curso, sin XML)
                // y sin esto el usuario no se entera de nada.
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ??
                            'No se pudo reenviar el comprobante.',
                    ),
                onFinish: () => setProcessingId(null),
            },
        );
    };

    return (
        <VendedorLayout title="Envío SUNAT">
            <div className="flex flex-col gap-4">
                <PageHeader
                    title="Envío SUNAT"
                    description="Lo que enviaste a SUNAT y su respuesta. Se envía solo; aquí ves lo que necesita tu atención."
                />
                <PestanasSunat actual="comprobantes" />
                {(plazoSunat.por_vencer > 0 ||
                    plazoSunat.vencidos > 0 ||
                    filters.plazo) && (
                    <div
                        role="alert"
                        className="text-warning-strong flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200"
                    >
                        <AlertTriangle className="size-5 shrink-0" />
                        <p className="min-w-[14rem] flex-1">
                            <strong>Plazo de SUNAT:</strong>{' '}
                            {plazoSunat.por_vencer} sin enviar vencen hoy o
                            mañana y {plazoSunat.vencidos} ya vencieron.
                            Envíalos cuanto antes.
                        </p>
                        <div className="flex flex-wrap gap-3 font-semibold">
                            <Link
                                href={facturacion.index.url(teamSlug, {
                                    query: { plazo: 'por_vencer' },
                                })}
                                className="underline underline-offset-2"
                            >
                                Ver los que vencen
                            </Link>
                            <Link
                                href={facturacion.index.url(teamSlug, {
                                    query: { plazo: 'vencidos' },
                                })}
                                className="underline underline-offset-2"
                            >
                                Ver los vencidos
                            </Link>
                            {filters.plazo && (
                                <Link
                                    href={facturacion.index.url(teamSlug)}
                                    className="underline underline-offset-2"
                                >
                                    Ver todos
                                </Link>
                            )}
                        </div>
                    </div>
                )}
                {kpis.rechazados > 0 && filters.estado !== 'rechazados' ? (
                    <div
                        role="alert"
                        className="border-destructive/30 bg-destructive/10 flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border px-4 py-3 text-sm"
                    >
                        <XCircle className="text-destructive-strong size-5 shrink-0" />
                        <p className="text-foreground min-w-[14rem] flex-1">
                            <strong>
                                {kpis.rechazados}{' '}
                                {kpis.rechazados === 1
                                    ? 'comprobante rechazado'
                                    : 'comprobantes rechazados'}
                            </strong>{' '}
                            por SUNAT: hay que corregirlos y volver a enviarlos.
                        </p>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => filtrar({ estado: 'rechazados' })}
                            className="h-9 rounded-lg font-semibold"
                        >
                            Ver rechazados
                        </Button>
                    </div>
                ) : null}

                <Card className="border-border bg-card gap-0 rounded-2xl p-5 shadow-none">
                    <nav
                        aria-label="Filtrar por estado en SUNAT"
                        className="mb-4 flex flex-wrap gap-2"
                    >
                        {(
                            [
                                ['', 'Todos', kpis.emitidos],
                                ['rechazados', 'Rechazados', kpis.rechazados],
                                ['por_enviar', 'Por enviar', kpis.por_enviar],
                                [
                                    'observado',
                                    'Con observación',
                                    kpis.observados,
                                ],
                                ['aceptado', 'Aceptados', kpis.aceptados],
                            ] as const
                        ).map(([valor, nombre, cuenta]) => {
                            const activo = (filters.estado ?? '') === valor;

                            return (
                                <button
                                    key={nombre}
                                    type="button"
                                    aria-pressed={activo}
                                    onClick={() => filtrar({ estado: valor })}
                                    className={`flex min-h-10 items-center gap-1.5 rounded-full border px-3.5 text-[13px] font-semibold ${activo ? 'bg-foreground text-background border-foreground' : 'border-border text-muted-foreground hover:text-foreground'}`}
                                >
                                    {nombre}
                                    <span className="tabular-nums">
                                        {cuenta}
                                    </span>
                                </button>
                            );
                        })}
                    </nav>
                    <div className="mb-2 flex flex-wrap items-end gap-2.5">
                        <FiltroSelect
                            etiqueta="Tipo"
                            valor={
                                !filters.tipo || filters.tipo === 'todos'
                                    ? ''
                                    : filters.tipo
                            }
                            opciones={TYPE_FILTERS.map((filter) => ({
                                value: filter.value,
                                label: filter.label,
                            }))}
                            onCambiar={changeType}
                        />
                        <RangoFechas
                            rango={filters}
                            hoy={hoy}
                            onCambiar={filtrar}
                        />
                        <div className="flex-1" />
                        <form
                            onSubmit={submitSearch}
                            className="border-border bg-muted/40 focus-within:border-ring focus-within:ring-ring/50 flex h-10 min-w-[230px] items-center gap-2 rounded-lg border px-3 focus-within:ring-[3px]"
                        >
                            <Search className="text-muted-foreground size-3.5 shrink-0" />
                            <input
                                aria-label="Buscar comprobantes"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="F001-65, cliente o RUC..."
                                className="placeholder:text-muted-foreground min-w-0 flex-1 bg-transparent text-[13px] outline-none"
                            />
                        </form>
                    </div>

                    <p className="text-muted-foreground mb-3 text-[12px]">
                        {totalFiltrados} comprobante(s) ·{' '}
                        {describirRango(filters, hoy)}
                        {filters.buscar
                            ? ` que coinciden con «${filters.buscar}»`
                            : ''}
                        .
                    </p>

                    {/* La barra está siempre y sus botones nunca se apagan: sin
                    nada marcado, al tocarlos dice qué falta. */}
                    <div
                        role="region"
                        aria-label="Descargar comprobantes"
                        className="bg-muted border-border mb-3 flex flex-wrap items-center gap-x-4 gap-y-2 rounded-lg border px-3 py-2"
                    >
                        <span className="text-foreground text-[13px] font-semibold">
                            {cantidadSeleccionada === 0
                                ? 'Marca comprobantes para descargarlos juntos'
                                : cantidadSeleccionada === 1
                                  ? '1 comprobante marcado'
                                  : `${cantidadSeleccionada} comprobantes marcados`}
                        </span>
                        {paginaMarcada &&
                        !todoLoFiltrado &&
                        totalFiltrados > idsPagina.length ? (
                            <button
                                type="button"
                                onClick={() => setTodoLoFiltrado(true)}
                                className="text-primary-strong text-[12.5px] font-semibold hover:underline"
                            >
                                Marcar los {totalFiltrados} del filtro
                            </button>
                        ) : null}
                        <fieldset className="flex items-center gap-3">
                            <legend className="text-muted-foreground float-left mr-1 text-[12.5px]">
                                Incluir:
                            </legend>
                            {(['xml', 'cdr', 'pdf'] as const).map((tipo) => (
                                <label
                                    key={tipo}
                                    className="text-foreground flex min-h-8 items-center gap-1.5 text-[12.5px] font-semibold uppercase"
                                >
                                    <input
                                        type="checkbox"
                                        className="accent-foreground size-4"
                                        checked={incluir.includes(tipo)}
                                        onChange={() =>
                                            setIncluir(
                                                incluir.includes(tipo)
                                                    ? incluir.filter(
                                                          (t) => t !== tipo,
                                                      )
                                                    : [...incluir, tipo],
                                            )
                                        }
                                    />
                                    {tipo}
                                </label>
                            ))}
                        </fieldset>
                        <div className="flex-1" />
                        <Button
                            type="button"
                            disabled={descargando !== null}
                            onClick={() =>
                                cantidadSeleccionada === 0 ||
                                incluir.length === 0
                                    ? setAvisoDescarga(true)
                                    : descargar(
                                          'zip',
                                          urlDescarga(
                                              facturacion.descargaMasiva,
                                          ),
                                      )
                            }
                            size="sm"
                            className="bg-foreground text-background hover:bg-foreground/90 h-9 rounded-lg font-semibold shadow-none"
                        >
                            {descargando === 'zip' ? (
                                <Spinner />
                            ) : (
                                <Download className="size-4" />
                            )}
                            {descargando === 'zip'
                                ? 'Preparando ZIP…'
                                : 'Descargar ZIP'}
                        </Button>
                        <Button
                            type="button"
                            disabled={descargando !== null}
                            onClick={() =>
                                cantidadSeleccionada === 0
                                    ? setAvisoDescarga(true)
                                    : descargar(
                                          'excel',
                                          urlDescarga(facturacion.excel),
                                      )
                            }
                            variant="outline"
                            size="sm"
                            className="border-border bg-card h-9 rounded-lg font-semibold shadow-none"
                        >
                            {descargando === 'excel' ? (
                                <Spinner />
                            ) : (
                                <FileSpreadsheet className="size-4" />
                            )}
                            {descargando === 'excel'
                                ? 'Preparando…'
                                : 'CSV para Excel'}
                        </Button>
                        {cantidadSeleccionada > 0 ? (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    setMarcados([]);
                                    setTodoLoFiltrado(false);
                                }}
                                className="h-9 rounded-lg font-semibold"
                            >
                                Quitar selección
                            </Button>
                        ) : null}
                        {avisoDescarga &&
                        (cantidadSeleccionada === 0 || incluir.length === 0) ? (
                            <p
                                role="status"
                                className="text-warning-strong basis-full text-[12.5px]"
                            >
                                {cantidadSeleccionada === 0
                                    ? 'Marca al menos un comprobante en la lista para descargarlo.'
                                    : 'Elige qué incluir: XML, CDR o PDF.'}
                            </p>
                        ) : null}
                    </div>

                    <div className="overflow-x-auto">
                        <Table className="w-full border-collapse text-[12.5px]">
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-8">
                                        <input
                                            type="checkbox"
                                            aria-label="Marcar la página"
                                            className="accent-primary size-3.5"
                                            checked={paginaMarcada}
                                            onChange={alternarPagina}
                                        />
                                    </TableHead>
                                    {[
                                        'Comprobante',
                                        'Cliente',
                                        'Fecha',
                                        'Total',
                                        'Estado SUNAT',
                                        'Acciones',
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
                                {recargando ? (
                                    <FilasCargando
                                        columnas={8}
                                        filas={documents.data.length}
                                    />
                                ) : (
                                    documents.data.map((document) => {
                                        const canResend = [
                                            'observado',
                                            'excepcion',
                                        ].includes(document.sunat_estado);
                                        const number = `${document.serie}-${String(document.correlativo).padStart(8, '0')}`;

                                        return (
                                            <TableRow
                                                key={document.id}
                                                className="hover:bg-muted/40"
                                            >
                                                <TableCell className="border-border border-b px-2.5 py-[13px]">
                                                    <input
                                                        type="checkbox"
                                                        aria-label={`Marcar ${number}`}
                                                        className="accent-primary size-3.5"
                                                        checked={
                                                            todoLoFiltrado ||
                                                            marcados.includes(
                                                                document.id,
                                                            )
                                                        }
                                                        onChange={() =>
                                                            alternar(
                                                                document.id,
                                                            )
                                                        }
                                                    />
                                                </TableCell>
                                                <TableCell className="border-border border-b px-2.5 py-[13px]">
                                                    <span className="bg-muted text-foreground/80 mr-1.5 rounded-md px-2 py-1 font-['IBM_Plex_Mono',monospace] text-xs font-bold">
                                                        {nombreDocumento(
                                                            document.tipo,
                                                        )}
                                                    </span>
                                                    {document.sale_id ? (
                                                        <Link
                                                            href={ventas.show.url(
                                                                {
                                                                    current_team:
                                                                        teamSlug,
                                                                    sale: document.sale_id,
                                                                },
                                                            )}
                                                            className="hover:text-primary-strong font-['IBM_Plex_Mono',monospace] font-bold hover:underline"
                                                            title="Ver venta"
                                                        >
                                                            {number}
                                                        </Link>
                                                    ) : (
                                                        <span className="font-['IBM_Plex_Mono',monospace] font-bold">
                                                            {number}
                                                        </span>
                                                    )}
                                                    {document.modifica ? (
                                                        <span className="text-muted-foreground mt-1 block text-xs">
                                                            Modifica a{' '}
                                                            {document.modifica}
                                                        </span>
                                                    ) : null}
                                                </TableCell>
                                                <TableCell className="border-border text-foreground border-b px-2.5 py-[13px] font-semibold">
                                                    {document.cliente}
                                                </TableCell>
                                                <TableCell className="border-border text-foreground/80 border-b px-2.5 py-[13px]">
                                                    {document.created_at ?? '-'}
                                                </TableCell>
                                                <TableCell className="border-border border-b px-2.5 py-[13px] font-['IBM_Plex_Mono',monospace] font-bold whitespace-nowrap tabular-nums">
                                                    {soles(document.total)}
                                                </TableCell>
                                                <TableCell className="border-border border-b px-2.5 py-[13px]">
                                                    <Badge
                                                        title={
                                                            document.sunat_mensaje ??
                                                            undefined
                                                        }
                                                        className={`max-w-[240px] justify-start rounded-full border-transparent px-2.5 py-1 text-xs font-bold ${sunatBadge(document.sunat_estado)}`}
                                                    >
                                                        <span className="truncate">
                                                            {sunatEstadoLabel(
                                                                document,
                                                            )}
                                                        </span>
                                                    </Badge>
                                                    {document.sunat_mensaje_simple &&
                                                    document.sunat_estado !==
                                                        'aceptado' ? (
                                                        <span className="text-muted-foreground mt-1 block max-w-[260px] text-xs">
                                                            {
                                                                document.sunat_mensaje_simple
                                                            }
                                                        </span>
                                                    ) : null}
                                                </TableCell>
                                                <TableCell className="border-border border-b px-2.5 py-[13px]">
                                                    <div className="flex items-center gap-1.5">
                                                        {canResend && (
                                                            <Button
                                                                type="button"
                                                                onClick={() =>
                                                                    resend(
                                                                        document,
                                                                    )
                                                                }
                                                                disabled={
                                                                    processingId ===
                                                                    document.id
                                                                }
                                                                variant="outline"
                                                                size="icon"
                                                                aria-label="Reenviar a SUNAT"
                                                                title="Reenviar a SUNAT"
                                                                className="border-border bg-card text-warning-strong size-7 rounded-md shadow-none"
                                                            >
                                                                {processingId ===
                                                                document.id ? (
                                                                    <Cargando className="size-3.5" />
                                                                ) : (
                                                                    <RefreshCcw className="size-3.5" />
                                                                )}
                                                            </Button>
                                                        )}
                                                        {document.tiene_xml ? (
                                                            <a
                                                                href={facturacion.xml.url(
                                                                    {
                                                                        current_team:
                                                                            teamSlug,
                                                                        electronic_document:
                                                                            document.id,
                                                                    },
                                                                )}
                                                                className="border-border bg-card text-foreground/80 hover:border-border inline-flex h-[26px] items-center rounded-md border px-2 font-['IBM_Plex_Mono',monospace] text-xs font-bold no-underline"
                                                            >
                                                                XML
                                                            </a>
                                                        ) : null}
                                                        {document.tiene_cdr ? (
                                                            <a
                                                                href={facturacion.cdr.url(
                                                                    {
                                                                        current_team:
                                                                            teamSlug,
                                                                        electronic_document:
                                                                            document.id,
                                                                    },
                                                                )}
                                                                className="border-border bg-card text-foreground/80 hover:border-border inline-flex h-[26px] items-center rounded-md border px-2 font-['IBM_Plex_Mono',monospace] text-xs font-bold no-underline"
                                                            >
                                                                CDR
                                                            </a>
                                                        ) : null}
                                                        <a
                                                            href={facturacion.pdf.url(
                                                                {
                                                                    current_team:
                                                                        teamSlug,
                                                                    electronic_document:
                                                                        document.id,
                                                                },
                                                            )}
                                                            className="border-border bg-card text-foreground/80 hover:border-border inline-flex h-[26px] items-center gap-1 rounded-md border px-2 font-['IBM_Plex_Mono',monospace] text-xs font-bold no-underline"
                                                        >
                                                            <FileDown className="size-3" />
                                                            PDF
                                                        </a>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })
                                )}
                            </TableBody>
                        </Table>
                    </div>

                    <div className="text-muted-foreground mt-3.5 flex flex-col gap-3 text-xs sm:flex-row sm:items-center sm:justify-between">
                        <span>
                            Mostrando {documents.from ?? 0}-{documents.to ?? 0}{' '}
                            de {documents.total ?? documents.data.length}{' '}
                            comprobantes
                        </span>
                        <div className="flex flex-wrap gap-1.5">
                            {documents.links.map((link, index) =>
                                link.url ? (
                                    <Link
                                        key={`${link.label}-${index}`}
                                        href={link.url}
                                        preserveScroll
                                        preserveState
                                        className={`flex h-[26px] min-w-[26px] items-center justify-center rounded-md px-2 font-['IBM_Plex_Mono',monospace] text-xs no-underline ${link.active ? 'bg-primary font-bold text-white' : 'text-muted-foreground hover:bg-muted hover:text-foreground'}`}
                                    >
                                        {etiquetaPaginacion(link.label)}
                                    </Link>
                                ) : (
                                    <span
                                        key={`${link.label}-${index}`}
                                        className="text-muted-foreground flex h-[26px] min-w-[26px] items-center justify-center rounded-md px-2 font-['IBM_Plex_Mono',monospace] text-xs"
                                    >
                                        {etiquetaPaginacion(link.label)}
                                    </span>
                                ),
                            )}
                        </div>
                    </div>
                </Card>
            </div>

            <CargaLarga
                activo={processingId !== null}
                titulo="Reenviando el comprobante a SUNAT"
                detalle="La operación está en curso. El resultado se mostrará al terminar."
            />
        </VendedorLayout>
    );
}
