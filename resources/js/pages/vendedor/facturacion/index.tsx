import { Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    FileDown,
    RefreshCcw,
    Search,
    Send,
    XCircle,
} from 'lucide-react';
import { FormEvent, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import VendedorLayout from '@/layouts/vendedor-layout';
import facturacion from '@/routes/vendedor/facturacion';
import type { Team } from '@/types';

type DocumentRow = {
    id: number;
    tipo: string;
    serie: string;
    correlativo: number | string;
    cliente: string;
    total: number | string;
    sunat_estado: string;
    sunat_codigo_respuesta: string | null;
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
    filters: { tipo?: string; estado?: string; mes?: string };
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
    if (value === 'aceptado') return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20';
    if (value === 'observado' || value === 'excepcion')
        return 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20';
    if (value === 'rechazado') return 'bg-destructive/10 text-destructive border border-destructive/20';
    return 'bg-muted text-muted-foreground';
}

export default function FacturacionIndex({ documents, filters, kpis }: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug =
        currentTeam?.slug ??
        (typeof window !== 'undefined'
            ? window.location.pathname.split('/')[1]
            : '');
    const [search, setSearch] = useState('');
    const [processingId, setProcessingId] = useState<number | null>(null);

    const changeType = (tipo: string) => {
        router.get(
            facturacion.index.url(teamSlug, {
                query: {
                    tipo: tipo || undefined,
                    estado: filters.estado || undefined,
                    mes: filters.mes || undefined,
                },
            }),
            {},
            { preserveScroll: true, preserveState: true },
        );
    };

    const submitSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
    };

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
            label: 'Aceptados',
            value: kpis.aceptados_hoy,
            icon: CheckCircle2,
            bg: 'bg-emerald-500/10',
            text: 'text-emerald-600 dark:text-emerald-400',
        },
        {
            label: 'Observados',
            value: kpis.observados,
            icon: AlertTriangle,
            bg: 'bg-amber-500/10',
            text: 'text-amber-600 dark:text-amber-400',
        },
        {
            label: 'Rechazados',
            value: kpis.rechazados,
            icon: XCircle,
            bg: 'bg-destructive/10',
            text: 'text-destructive',
        },
    ];

    return (
        <VendedorLayout title="Facturación Electrónica">
            <div className="flex flex-col gap-4">
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {kpiItems.map((item) => {
                        const Icon = item.icon;
                        return (
                            <Card
                                key={item.label}
                                className="flex-row items-center gap-3.5 rounded-[14px] border-border bg-card px-[18px] py-4 shadow-none"
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
                                    <div className="text-[11px] font-bold tracking-[0.03em] text-muted-foreground uppercase">
                                        {item.label}
                                    </div>
                                    <div className="font-['Oswald',sans-serif] text-[22px] font-semibold text-foreground">
                                        {item.value}
                                    </div>
                                </div>
                            </Card>
                        );
                    })}
                </div>

                <Card className="gap-0 rounded-[16px] border-border bg-card p-5 shadow-none">
                    <div className="mb-4 flex flex-wrap items-center gap-2.5">
                        <div className="flex rounded-[9px] bg-muted p-[3px]">
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
                        <div className="flex-1" />
                        <form
                            onSubmit={submitSearch}
                            className="flex h-10 min-w-[230px] items-center gap-2 rounded-[9px] border border-border bg-muted/40 px-3"
                        >
                            <Search className="size-3.5 shrink-0 text-muted-foreground" />
                            <input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Buscar comprobante..."
                                className="min-w-0 flex-1 bg-transparent text-[13px] outline-none placeholder:text-muted-foreground"
                            />
                        </form>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-[12.5px]">
                            <thead>
                                <tr>
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
                                            className="border-b border-border px-2.5 py-2.5 text-left font-['IBM_Plex_Mono',monospace] text-[9.5px] font-bold tracking-[0.05em] text-muted-foreground uppercase"
                                        >
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {documents.data.map((document) => {
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
                                            <td className="border-b border-border px-2.5 py-[13px]">
                                                <span className="mr-1.5 rounded-[5px] bg-muted px-2 py-1 font-['IBM_Plex_Mono',monospace] text-[10px] font-bold text-foreground/80 uppercase">
                                                    {document.tipo}
                                                </span>
                                                <span className="font-['IBM_Plex_Mono',monospace] font-bold">
                                                    {number}
                                                </span>
                                            </td>
                                            <td className="border-b border-border px-2.5 py-[13px] font-semibold text-foreground">
                                                {document.cliente}
                                            </td>
                                            <td className="border-b border-border px-2.5 py-[13px] text-foreground/80">
                                                {document.created_at ?? '-'}
                                            </td>
                                            <td className="border-b border-border px-2.5 py-[13px] font-['IBM_Plex_Mono',monospace] font-bold">
                                                {money(document.total)}
                                            </td>
                                            <td className="border-b border-border px-2.5 py-[13px]">
                                                <Badge
                                                    className={`rounded-full border-transparent px-2.5 py-1 text-[10.5px] font-bold capitalize ${sunatBadge(document.sunat_estado)}`}
                                                >
                                                    {document.sunat_estado}
                                                </Badge>
                                            </td>
                                            <td className="border-b border-border px-2.5 py-[13px] font-['IBM_Plex_Mono',monospace] text-[11px]">
                                                {document.sunat_codigo_respuesta ??
                                                    '-'}
                                            </td>
                                            <td className="border-b border-border px-2.5 py-[13px]">
                                                <div className="flex items-center gap-1.5">
                                                    {canResend && (
                                                        <Button
                                                            type="button"
                                                            onClick={() =>
                                                                resend(document)
                                                            }
                                                            disabled={
                                                                processingId ===
                                                                document.id
                                                            }
                                                            variant="outline"
                                                            size="icon"
                                                            className="size-7 rounded-[7px] border-border bg-card text-amber-600 dark:text-amber-400 shadow-none"
                                                        >
                                                            <RefreshCcw
                                                                className={`size-3.5 ${processingId === document.id ? 'animate-spin' : ''}`}
                                                            />
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
                                                        className="inline-flex h-[26px] items-center rounded-[6px] border border-border bg-card px-2 font-['IBM_Plex_Mono',monospace] text-[9.5px] font-bold text-foreground/80 no-underline hover:border-border"
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
                                                        className="inline-flex h-[26px] items-center rounded-[6px] border border-border bg-card px-2 font-['IBM_Plex_Mono',monospace] text-[9.5px] font-bold text-foreground/80 no-underline hover:border-border"
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
                                                        className="inline-flex h-[26px] items-center gap-1 rounded-[6px] border border-border bg-card px-2 font-['IBM_Plex_Mono',monospace] text-[9.5px] font-bold text-foreground/80 no-underline hover:border-border"
                                                    >
                                                        <FileDown className="size-3" />
                                                        PDF
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    <div className="mt-3.5 flex flex-col gap-3 text-[11.5px] text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
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
                                        className="flex h-[26px] min-w-[26px] items-center justify-center rounded-[7px] px-2 font-['IBM_Plex_Mono',monospace] text-[11.5px] text-muted-foreground"
                                    >
                                        {cleanLabel(link.label)}
                                    </span>
                                ),
                            )}
                        </div>
                    </div>
                </Card>
            </div>
        </VendedorLayout>
    );
}
