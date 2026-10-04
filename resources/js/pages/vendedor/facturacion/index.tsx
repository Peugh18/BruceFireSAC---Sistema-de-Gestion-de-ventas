import { Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    Download,
    FileDown,
    FileSpreadsheet,
    RefreshCcw,
    Search,
    Send,
    XCircle,
} from 'lucide-react';
import { FormEvent, useState } from 'react';
import { toast } from 'sonner';

import RangoFechas, { describirRango } from '@/components/rango-fechas';
import { CargaLarga, Cargando, FilasCargando } from '@/components/cargando';
import { Badge } from '@/components/ui/badge';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { useRecargando } from '@/hooks/use-recargando';
import VendedorLayout from '@/layouts/vendedor-layout';
import facturacion from '@/routes/vendedor/facturacion';
import ventas from '@/routes/vendedor/ventas';
import type { Team } from '@/types';

type DocumentRow = {
    id: number;
    sale_id?: number;
    tipo: string;
    serie: string;
    correlativo: number | string;
    cliente: string;
    total: number | string;
    sunat_estado: string;
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
        desde: string;
        hasta: string;
    };
    hoy: string;
    totalFiltrados: number;
    kpis: {
        emitidos_hoy: number;
        aceptados_hoy: number;
        observados: number;
        rechazados: number;
    };
};

const TYPE_FILTERS = [
    { label: 'Todos', value: '' },
    { label: 'Facturas', value: 'factura' },
    { label: 'Boletas', value: 'boleta' },
    { label: 'N. Crédito/Débito', value: 'nota' },
];

function money(value: number | string) {
    return new Intl.NumberFormat('es-PE', {
        style: 'currency',
        currency: 'PEN',
    }).format(Number(value) || 0);
}

function cleanLabel(label: string) {
    return label.replace('&laquo;', '<').replace('&raquo;', '>');
}

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
        const mensaje = (
            document.sunat_mensaje_simple ?? document.sunat_mensaje
        )?.trim();
        if (mensaje) {
            return `Rechazado: ${mensaje}`;
        }
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
    return document.sunat_estado || 'Pendiente';
}

export default function FacturacionIndex({
    documents,
    filters,
    kpis,
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

    const submitSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        filtrar({ buscar: search.trim() });
    };

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
            { preserveScroll: true, onFinish: () => setProcessingId(null) },
        );
    };

    const kpiItems = [
        {
            label: 'Emitidos hoy',
            value: kpis.emitidos_hoy,
            icon: Send,
            bg: 'bg-blue-500/10',
            text: 'text-blue-600 dark:text-blue-400',
        },
        {
            label: 'Aceptados hoy',
            value: kpis.aceptados_hoy,
            icon: CheckCircle2,
            bg: 'bg-emerald-500/10',
            text: 'text-success-strong',
        },
        {
            label: 'Observados',
            value: kpis.observados,
            icon: AlertTriangle,
            bg: 'bg-amber-500/10',
            text: 'text-warning-strong',
        },
        {
            label: 'Rechazados',
            value: kpis.rechazados,
            icon: XCircle,
            bg: 'bg-destructive/10',
            text: 'text-destructive-strong',
        },
    ];

    return (
        <VendedorLayout title="Comprobantes SUNAT">
            <div className="flex flex-col gap-4">
                <PageHeader
                    title="Comprobantes SUNAT"
                    description="Facturas, boletas y notas enviadas a SUNAT con su respuesta. Marca varios para descargarlos juntos."
                />
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {kpiItems.map((item) => {
                        const Icon = item.icon;
                        return (
                            <Card
                                key={item.label}
                                className="border-border bg-card flex-row items-center gap-3.5 rounded-[14px] px-[18px] py-4 shadow-none"
                            >
                                <div
                                    className={`flex size-[42px] shrink-0 items-center justify-center rounded-[11px] ${item.bg}`}
                                >
                                    <Icon
                                        className={`size-5 ${item.text}`}
                                        strokeWidth={2}
                                    />
                                </div>
                                <div>
                                    <div className="text-muted-foreground text-[11px] font-bold tracking-[0.03em] uppercase">
                                        {item.label}
                                    </div>
                                    <div className="text-foreground font-['Oswald',sans-serif] text-[22px] font-semibold">
                                        {item.value}
                                    </div>
                                </div>
                            </Card>
                        );
                    })}
                </div>

                <Card className="border-border bg-card gap-0 rounded-[16px] p-5 shadow-none">
                    <div className="mb-2 flex flex-wrap items-end gap-2.5">
                        <div className="bg-muted flex max-w-full overflow-x-auto rounded-[9px] p-[3px]">
                            {TYPE_FILTERS.map((filter) => {
                                const active =
                                    filter.value === ''
                                        ? !filters.tipo ||
                                          filters.tipo === 'todos'
                                        : filters.tipo === filter.value;
                                return (
                                    <button
                                        key={filter.label}
                                        type="button"
                                        onClick={() => changeType(filter.value)}
                                        className={`rounded-[7px] px-3.5 py-1.5 text-xs font-bold whitespace-nowrap transition-all ${active ? 'bg-card text-foreground shadow-[0_1px_2px_rgba(0,0,0,0.06)]' : 'text-muted-foreground hover:text-foreground'}`}
                                    >
                                        {filter.label}
                                    </button>
                                );
                            })}
                        </div>
                        <RangoFechas
                            rango={filters}
                            hoy={hoy}
                            onCambiar={filtrar}
                        />
                        <div className="flex-1" />
                        <form
                            onSubmit={submitSearch}
                            className="border-border bg-muted/40 focus-within:border-ring focus-within:ring-ring/50 flex h-10 min-w-[230px] items-center gap-2 rounded-[9px] border px-3 focus-within:ring-[3px]"
                        >
                            <Search className="text-muted-foreground size-3.5 shrink-0" />
                            <input
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
                        {totalFiltrados} comprobante(s) de{' '}
                        {describirRango(filters, hoy)}
                        {filters.buscar
                            ? ` que coinciden con «${filters.buscar}»`
                            : ''}
                        .
                    </p>

                    <div className="border-border bg-muted/30 mb-3 flex flex-wrap items-center gap-2 rounded-[10px] border px-3 py-2">
                        <span className="text-foreground text-[12px] font-semibold">
                            {cantidadSeleccionada > 0
                                ? `${cantidadSeleccionada} comprobante(s) seleccionado(s)`
                                : 'Marca comprobantes para descargarlos'}
                        </span>
                        {paginaMarcada &&
                        !todoLoFiltrado &&
                        totalFiltrados > idsPagina.length ? (
                            <button
                                type="button"
                                onClick={() => setTodoLoFiltrado(true)}
                                className="text-primary-strong text-[12px] font-semibold hover:underline"
                            >
                                Seleccionar los {totalFiltrados} del filtro
                            </button>
                        ) : null}
                        <div className="flex-1" />
                        {(['xml', 'cdr', 'pdf'] as const).map((tipo) => (
                            <label
                                key={tipo}
                                className="flex items-center gap-1 text-[12px] font-semibold uppercase"
                            >
                                <input
                                    type="checkbox"
                                    className="accent-primary size-3.5"
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
                        <Button
                            type="button"
                            disabled={
                                cantidadSeleccionada === 0 ||
                                incluir.length === 0 ||
                                descargando !== null
                            }
                            onClick={() =>
                                descargar(
                                    'zip',
                                    urlDescarga(facturacion.descargaMasiva),
                                )
                            }
                            size="sm"
                            className="bg-primary hover:bg-primary/90 h-8 rounded-[8px] text-white shadow-none"
                        >
                            {descargando === 'zip' ? (
                                <Spinner />
                            ) : (
                                <Download className="size-3.5" />
                            )}
                            {descargando === 'zip'
                                ? 'Preparando ZIP…'
                                : 'Descargar ZIP'}
                        </Button>
                        <Button
                            type="button"
                            disabled={
                                cantidadSeleccionada === 0 ||
                                descargando !== null
                            }
                            onClick={() =>
                                descargar(
                                    'excel',
                                    urlDescarga(facturacion.excel),
                                )
                            }
                            variant="outline"
                            size="sm"
                            className="h-8 rounded-[8px] shadow-none"
                        >
                            {descargando === 'excel' ? (
                                <Spinner />
                            ) : (
                                <FileSpreadsheet className="size-3.5" />
                            )}
                            {descargando === 'excel' ? 'Preparando…' : 'Excel'}
                        </Button>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-[12.5px]">
                            <thead>
                                <tr>
                                    <th className="border-border w-8 border-b px-2.5 py-2.5">
                                        <input
                                            type="checkbox"
                                            aria-label="Marcar la página"
                                            className="accent-primary size-3.5"
                                            checked={paginaMarcada}
                                            onChange={alternarPagina}
                                        />
                                    </th>
                                    {[
                                        'Comprobante',
                                        'Cliente',
                                        'Fecha',
                                        'Total',
                                        'Estado SUNAT',
                                        'Código',
                                        'Acciones',
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
                                            <tr
                                                key={document.id}
                                                className="hover:bg-muted/40"
                                            >
                                                <td className="border-border border-b px-2.5 py-[13px]">
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
                                                </td>
                                                <td className="border-border border-b px-2.5 py-[13px]">
                                                    <span className="bg-muted text-foreground/80 mr-1.5 rounded-[5px] px-2 py-1 font-['IBM_Plex_Mono',monospace] text-[10px] font-bold uppercase">
                                                        {document.tipo}
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
                                                </td>
                                                <td className="border-border text-foreground border-b px-2.5 py-[13px] font-semibold">
                                                    {document.cliente}
                                                </td>
                                                <td className="border-border text-foreground/80 border-b px-2.5 py-[13px]">
                                                    {document.created_at ?? '-'}
                                                </td>
                                                <td className="border-border border-b px-2.5 py-[13px] font-['IBM_Plex_Mono',monospace] font-bold whitespace-nowrap tabular-nums">
                                                    {money(document.total)}
                                                </td>
                                                <td className="border-border border-b px-2.5 py-[13px]">
                                                    <Badge
                                                        title={
                                                            document.sunat_mensaje ??
                                                            undefined
                                                        }
                                                        className={`max-w-[240px] justify-start rounded-full border-transparent px-2.5 py-1 text-[10.5px] font-bold ${sunatBadge(document.sunat_estado)}`}
                                                    >
                                                        <span className="truncate">
                                                            {sunatEstadoLabel(
                                                                document,
                                                            )}
                                                        </span>
                                                    </Badge>
                                                </td>
                                                <td className="border-border text-muted-foreground border-b px-2.5 py-[13px] font-['IBM_Plex_Mono',monospace] text-[11px] whitespace-nowrap tabular-nums">
                                                    {document.sunat_codigo_respuesta ??
                                                        '-'}
                                                </td>
                                                <td className="border-border border-b px-2.5 py-[13px]">
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
                                                                className="border-border bg-card text-warning-strong size-7 rounded-[7px] shadow-none"
                                                            >
                                                                {processingId ===
                                                                document.id ? (
                                                                    <Cargando className="size-3.5" />
                                                                ) : (
                                                                    <RefreshCcw className="size-3.5" />
                                                                )}
                                                            </Button>
                                                        )}
                                                        <a
                                                            href={facturacion.xml.url(
                                                                {
                                                                    current_team:
                                                                        teamSlug,
                                                                    electronic_document:
                                                                        document.id,
                                                                },
                                                            )}
                                                            className="border-border bg-card text-foreground/80 hover:border-border inline-flex h-[26px] items-center rounded-[6px] border px-2 font-['IBM_Plex_Mono',monospace] text-[9.5px] font-bold no-underline"
                                                        >
                                                            XML
                                                        </a>
                                                        <a
                                                            href={facturacion.cdr.url(
                                                                {
                                                                    current_team:
                                                                        teamSlug,
                                                                    electronic_document:
                                                                        document.id,
                                                                },
                                                            )}
                                                            className="border-border bg-card text-foreground/80 hover:border-border inline-flex h-[26px] items-center rounded-[6px] border px-2 font-['IBM_Plex_Mono',monospace] text-[9.5px] font-bold no-underline"
                                                        >
                                                            CDR
                                                        </a>
                                                        <a
                                                            href={facturacion.pdf.url(
                                                                {
                                                                    current_team:
                                                                        teamSlug,
                                                                    electronic_document:
                                                                        document.id,
                                                                },
                                                            )}
                                                            className="border-border bg-card text-foreground/80 hover:border-border inline-flex h-[26px] items-center gap-1 rounded-[6px] border px-2 font-['IBM_Plex_Mono',monospace] text-[9.5px] font-bold no-underline"
                                                        >
                                                            <FileDown className="size-3" />
                                                            PDF
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="text-muted-foreground mt-3.5 flex flex-col gap-3 text-[11.5px] sm:flex-row sm:items-center sm:justify-between">
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
                                        className={`flex h-[26px] min-w-[26px] items-center justify-center rounded-[7px] px-2 font-['IBM_Plex_Mono',monospace] text-[11.5px] no-underline ${link.active ? 'bg-primary font-bold text-white' : 'text-muted-foreground hover:bg-muted hover:text-foreground'}`}
                                    >
                                        {cleanLabel(link.label)}
                                    </Link>
                                ) : (
                                    <span
                                        key={`${link.label}-${index}`}
                                        className="text-muted-foreground flex h-[26px] min-w-[26px] items-center justify-center rounded-[7px] px-2 font-['IBM_Plex_Mono',monospace] text-[11.5px]"
                                    >
                                        {cleanLabel(link.label)}
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
                detalle="Suele tardar menos de 10 segundos"
            />
        </VendedorLayout>
    );
}
