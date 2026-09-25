import { Link, router } from '@inertiajs/react';
import {
    AlertCircle,
    Clock3,
    Download,
    Eye,
    Filter,
    Plus,
    Search,
    UsersRound,
} from 'lucide-react';
import { FormEvent, useState } from 'react';

import ClientCreateDialog from '@/components/client-create-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import VendedorLayout from '@/layouts/vendedor-layout';
import clientes from '@/routes/vendedor/clientes';

type CurrentTeam = {
    slug: string;
};

type ClientRow = {
    id: number;
    codigo_interno: string;
    cliente: {
        razon_social: string;
        tipo_documento: 'dni' | 'ruc' | string;
        avatar: string;
    };
    documento: string;
    direccion: string | null;
    estado_sunat: {
        estado_contribuyente: string | null;
        condicion_domicilio: string | null;
    };
    ultima_compra: string | null;
    activo: boolean;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedClients = {
    data: ClientRow[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
};

type Props = {
    currentTeam: CurrentTeam;
    clients: PaginatedClients;
    filters: {
        search?: string;
    };
    columns: string[];
    kpis: {
        total_clientes: number;
        nuevos_este_mes: number;
        porcentaje_activo_habido: number;
        inactivos: number;
    };
};

function formatNumber(value: number) {
    return new Intl.NumberFormat('es-PE').format(value);
}

function initials(value: string) {
    return value
        .split(/\s+/u)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

function sunatIsHealthy(client: ClientRow) {
    return (
        client.estado_sunat.estado_contribuyente === 'ACTIVO' &&
        client.estado_sunat.condicion_domicilio === 'HABIDO'
    );
}

function cleanPaginationLabel(label: string) {
    return label.replace('&laquo;', '<').replace('&raquo;', '>');
}

export default function ClientesIndex({
    currentTeam,
    clients,
    filters,
    columns,
    kpis,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [dialogOpen, setDialogOpen] = useState(false);
    const submitSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        router.get(
            clientes.index.url(currentTeam.slug, {
                query: {
                    search: search || undefined,
                },
            }),
            {},
            {
                preserveScroll: true,
                preserveState: true,
            },
        );
    };

    const kpiItems = [
        {
            label: 'Total clientes',
            value: formatNumber(kpis.total_clientes),
            icon: UsersRound,
            bg: 'bg-blue-500/10',
            text: 'text-blue-600 dark:text-blue-400',
        },
        {
            label: 'Nuevos este mes',
            value: formatNumber(kpis.nuevos_este_mes),
            icon: Plus,
            bg: 'bg-emerald-500/10',
            text: 'text-emerald-600 dark:text-emerald-400',
        },
        {
            label: 'RUC activo y habido',
            value: `${kpis.porcentaje_activo_habido}%`,
            icon: Clock3,
            bg: 'bg-amber-500/10',
            text: 'text-amber-600 dark:text-amber-400',
        },
        {
            label: 'Inactivos',
            value: formatNumber(kpis.inactivos),
            icon: AlertCircle,
            bg: 'bg-destructive/10',
            text: 'text-destructive',
        },
    ];

    return (
        <VendedorLayout title="Clientes">
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
                                <div className="min-w-0">
                                    <div className="text-[11px] font-bold tracking-[0.03em] text-muted-foreground uppercase">
                                        {item.label}
                                    </div>
                                    <div className="font-['Oswald',sans-serif] text-[21px] leading-tight font-semibold text-foreground">
                                        {item.value}
                                    </div>
                                </div>
                            </Card>
                        );
                    })}
                </div>

                <Card className="gap-0 rounded-[16px] border-border bg-card p-5 shadow-none">
                    <div className="mb-4 flex flex-col gap-2 lg:flex-row lg:items-center">
                        <form
                            onSubmit={submitSearch}
                            className="flex min-w-0 flex-1 items-center gap-2 rounded-[9px] border border-border bg-muted/40 px-3"
                        >
                            <Search
                                className="size-3.5 shrink-0 text-muted-foreground"
                                strokeWidth={2}
                            />
                            <input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Buscar por razon social, RUC o DNI..."
                                className="h-10 min-w-0 flex-1 bg-transparent text-[13px] text-foreground outline-none placeholder:text-muted-foreground"
                            />
                        </form>

                        <Button
                            type="button"
                            variant="outline"
                            className="h-10 rounded-[9px] border-border bg-card px-3.5 text-[12.5px] font-semibold text-foreground/80 shadow-none"
                        >
                            <Filter className="size-3.5" />
                            Filtros
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            className="h-10 rounded-[9px] border-border bg-card px-3.5 text-[12.5px] font-semibold text-foreground/80 shadow-none"
                        >
                            <Download className="size-3.5" />
                            Exportar
                        </Button>
                        <Button
                            type="button"
                            onClick={() => {
                                setDialogOpen(true);
                            }}
                            className="h-10 rounded-[9px] bg-primary px-4 text-[13px] font-bold text-white shadow-none hover:bg-primary/90"
                        >
                            <Plus className="size-3.5" />
                            Agregar cliente
                        </Button>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-[12.5px]">
                            <thead>
                                <tr>
                                    {columns.map((column) => (
                                        <th
                                            key={column}
                                            className="border-b border-border px-2.5 py-2.5 text-left font-['IBM_Plex_Mono',monospace] text-[9.5px] font-bold tracking-[0.05em] whitespace-nowrap text-muted-foreground uppercase"
                                        >
                                            {column}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {clients.data.map((client) => {
                                    const healthy = sunatIsHealthy(client);

                                    return (
                                        <tr key={client.id}>
                                            <td className="border-b border-border px-2.5 py-[13px]">
                                                <div className="flex items-center gap-2.5">
                                                    <div className="flex size-[34px] shrink-0 items-center justify-center rounded-full bg-muted text-[11px] font-bold text-foreground/80">
                                                        {initials(
                                                            client.cliente
                                                                .razon_social,
                                                        ) ||
                                                            client.cliente
                                                                .avatar}
                                                    </div>
                                                    <div className="min-w-[180px]">
                                                        <div className="flex items-center gap-1.5 font-bold text-foreground">
                                                            {
                                                                client.cliente
                                                                    .razon_social
                                                            }
                                                            {!client.activo && (
                                                                <Badge className="rounded-full border-transparent bg-destructive/10 px-1.5 py-0 text-[9.5px] font-bold text-destructive shadow-none">
                                                                    Inactivo
                                                                </Badge>
                                                            )}
                                                        </div>
                                                        <div className="text-[11px] text-muted-foreground">
                                                            {
                                                                client.codigo_interno
                                                            }{' '}
                                                            ·{' '}
                                                            {client.cliente.tipo_documento.toUpperCase()}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="border-b border-border px-2.5 py-[13px] font-['IBM_Plex_Mono',monospace] text-muted-foreground">
                                                {client.documento}
                                            </td>
                                            <td className="max-w-[280px] border-b border-border px-2.5 py-[13px] text-muted-foreground">
                                                <span className="line-clamp-2">
                                                    {client.direccion ?? '-'}
                                                </span>
                                            </td>
                                            <td className="border-b border-border px-2.5 py-[13px]">
                                                <Badge
                                                    className={[
                                                        'rounded-full border-transparent px-2.5 py-1 text-[10.5px] font-bold shadow-none',
                                                        healthy
                                                            ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20'
                                                            : 'bg-destructive/10 text-destructive border border-destructive/20',
                                                    ].join(' ')}
                                                >
                                                    {client.estado_sunat
                                                        .estado_contribuyente ??
                                                        '-'}{' '}
                                                    ·{' '}
                                                    {client.estado_sunat
                                                        .condicion_domicilio ??
                                                        '-'}
                                                </Badge>
                                            </td>
                                            <td className="border-b border-border px-2.5 py-[13px] text-muted-foreground">
                                                {client.ultima_compra ?? '-'}
                                            </td>
                                            <td className="border-b border-border px-2.5 py-[13px]">
                                                <div className="flex gap-1.5">
                                                    <Button
                                                        asChild
                                                        variant="outline"
                                                        size="icon"
                                                        className="size-7 rounded-[7px] border-border bg-card text-foreground/80 shadow-none"
                                                    >
                                                        <Link
                                                            href={clientes.show(
                                                                {
                                                                    current_team:
                                                                        currentTeam.slug,
                                                                    client: client.id,
                                                                },
                                                            )}
                                                        >
                                                            <Eye className="size-3.5" />
                                                        </Link>
                                                    </Button>
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
                            Mostrando {clients.from ?? 0}-{clients.to ?? 0} de{' '}
                            {formatNumber(clients.total)} clientes
                        </span>
                        <div className="flex flex-wrap gap-1.5">
                            {clients.links.map((link, index) =>
                                link.url ? (
                                    <Link
                                        key={`${link.label}-${index}`}
                                        href={link.url}
                                        preserveScroll
                                        preserveState
                                        className={[
                                            'flex h-[26px] min-w-[26px] items-center justify-center rounded-[7px] px-2 font-["IBM_Plex_Mono",monospace] text-[11.5px] no-underline',
                                            link.active
                                                ? 'bg-primary font-bold text-white'
                                                : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                                        ].join(' ')}
                                    >
                                        {cleanPaginationLabel(link.label)}
                                    </Link>
                                ) : (
                                    <span
                                        key={`${link.label}-${index}`}
                                        className="flex h-[26px] min-w-[26px] items-center justify-center rounded-[7px] px-2 font-['IBM_Plex_Mono',monospace] text-[11.5px] text-muted-foreground"
                                    >
                                        {cleanPaginationLabel(link.label)}
                                    </span>
                                ),
                            )}
                        </div>
                    </div>
                </Card>
            </div>

            <ClientCreateDialog
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                teamSlug={currentTeam.slug}
                onCreated={(client) =>
                    router.visit(
                        clientes.show.url({
                            current_team: currentTeam.slug,
                            client: client.id,
                        }),
                    )
                }
            />
        </VendedorLayout>
    );
}
