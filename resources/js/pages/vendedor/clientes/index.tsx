import { Link, router } from '@inertiajs/react';
import {
    AlertCircle,
    Clock3,
    Download,
    Eye,
    Plus,
    Search,
    UsersRound,
} from 'lucide-react';
import { FormEvent, useState } from 'react';

import { FilasCargando } from '@/components/cargando';
import { PageHeader } from '@/components/page-header';
import ClientCreateDialog from '@/components/client-create-dialog';
import ClientesPorRegistrar, {
    type PorRegistrarData,
} from '@/components/clientes-por-registrar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { useRecargando } from '@/hooks/use-recargando';
import VendedorLayout from '@/layouts/vendedor-layout';
import clientes from '@/routes/vendedor/clientes';
import { fechaCorta } from '@/lib/utils';

type CurrentTeam = {
    slug: string;
};

type ClientRow = {
    id: number;
    codigo_interno: string;
    cliente: {
        razon_social: string;
        tipo_documento: string;
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
    vista: 'clientes' | 'por-registrar';
    porRegistrar: PorRegistrarData;
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

function cleanPaginationLabel(label: string) {
    return label.replace('&laquo;', '<').replace('&raquo;', '>');
}

function renderSunatBadge(client: ClientRow) {
    const tipo = client.cliente.tipo_documento?.toLowerCase();
    const esDni = tipo === 'dni';
    const esVarios =
        client.documento === '00000000' ||
        client.cliente.razon_social?.toUpperCase().includes('VARIOS');

    if (esDni) {
        return (
            <span className="text-muted-foreground text-[11.5px] font-medium">
                No aplica (DNI)
            </span>
        );
    }

    if (esVarios) {
        return (
            <span className="text-muted-foreground text-[11.5px] font-medium">
                No aplica
            </span>
        );
    }

    const { estado_contribuyente, condicion_domicilio } = client.estado_sunat;

    // Sin verificar
    if (!estado_contribuyente && !condicion_domicilio) {
        return (
            <Badge className="rounded-full border border-neutral-300 bg-neutral-100 px-2.5 py-1 text-[10.5px] font-bold text-neutral-600 shadow-none dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-400">
                Sin verificar
            </Badge>
        );
    }

    // Activo y Habido
    const isActivo = estado_contribuyente?.toUpperCase() === 'ACTIVO';
    const isHabido = condicion_domicilio?.toUpperCase() === 'HABIDO';

    if (isActivo && isHabido) {
        return (
            <Badge className="text-success-strong rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-1 text-[10.5px] font-bold shadow-none dark:text-emerald-400">
                Activo · Habido
            </Badge>
        );
    }

    // Otro estado (rojo con el estado real)
    return (
        <Badge className="border-destructive/20 bg-destructive/10 text-destructive-strong rounded-full border px-2.5 py-1 text-[10.5px] font-bold shadow-none">
            {estado_contribuyente || 'No activo'} ·{' '}
            {condicion_domicilio || 'No habido'}
        </Badge>
    );
}

export default function ClientesIndex({
    currentTeam,
    clients,
    filters,
    columns,
    kpis,
    vista,
    porRegistrar,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [dialogOpen, setDialogOpen] = useState(false);
    const [documentoARegistrar, setDocumentoARegistrar] = useState('');

    const cambiarVista = (nueva: Props['vista']) =>
        router.get(
            clientes.index.url(currentTeam.slug, {
                query: {
                    vista: nueva === 'por-registrar' ? nueva : undefined,
                },
            }),
            {},
            { preserveScroll: true },
        );
    const recargando = useRecargando();
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

    const rucClients = clients.data.filter(
        (c) => c.cliente.tipo_documento?.toLowerCase() === 'ruc',
    );
    const rucTotal = rucClients.length;
    const rucActivosHabidos = rucClients.filter(
        (c) =>
            c.estado_sunat.estado_contribuyente === 'ACTIVO' &&
            c.estado_sunat.condicion_domicilio === 'HABIDO',
    ).length;
    const rucSinVerificar = rucClients.filter(
        (c) =>
            !c.estado_sunat.estado_contribuyente &&
            !c.estado_sunat.condicion_domicilio,
    ).length;

    const rucKpiValue =
        rucTotal > 0
            ? `${rucActivosHabidos} de ${rucTotal} (${rucSinVerificar} sin verificar)`
            : `${kpis.porcentaje_activo_habido}%`;

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
            text: 'text-success-strong',
        },
        {
            label: 'RUC activos y habidos',
            value: rucKpiValue,
            icon: Clock3,
            bg: 'bg-amber-500/10',
            text: 'text-warning-strong',
        },
        {
            label: 'Inactivos',
            value: formatNumber(kpis.inactivos),
            icon: AlertCircle,
            bg: 'bg-destructive/10',
            text: 'text-destructive-strong',
        },
    ];

    return (
        <VendedorLayout title="Clientes">
            <div className="flex flex-col gap-4">
                <PageHeader
                    title="Clientes"
                    description="Tu cartera de clientes y los compradores del histórico que aún falta registrar."
                    actions={
                        <Button
                            type="button"
                            onClick={() => {
                                setDocumentoARegistrar('');
                                setDialogOpen(true);
                            }}
                            className="bg-primary hover:bg-primary/90 h-10 rounded-[9px] px-4 text-[13px] font-bold text-white shadow-none"
                        >
                            <Plus className="size-3.5" />
                            Agregar cliente
                        </Button>
                    }
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
                                <div className="min-w-0">
                                    <div className="text-muted-foreground text-[11px] font-bold tracking-[0.03em] uppercase">
                                        {item.label}
                                    </div>
                                    <div className="text-foreground font-['Oswald',sans-serif] text-[21px] leading-tight font-semibold">
                                        {item.value}
                                    </div>
                                </div>
                            </Card>
                        );
                    })}
                </div>

                <Card className="border-border bg-card gap-0 rounded-[16px] p-5 shadow-none">
                    <div className="border-border mb-4 flex gap-5 border-b text-[13px]">
                        {(
                            [
                                [
                                    'clientes',
                                    'Mis clientes',
                                    kpis.total_clientes,
                                ],
                                [
                                    'por-registrar',
                                    'Por registrar',
                                    porRegistrar.conteo.todas,
                                ],
                            ] as const
                        ).map(([clave, etiqueta, cantidad]) => (
                            <button
                                key={clave}
                                type="button"
                                onClick={() => cambiarVista(clave)}
                                className={`-mb-px border-b-2 pb-2.5 font-semibold transition-colors ${
                                    vista === clave
                                        ? 'border-primary text-foreground'
                                        : 'text-muted-foreground hover:text-foreground border-transparent'
                                }`}
                            >
                                {etiqueta}{' '}
                                <span className="text-muted-foreground font-normal">
                                    {formatNumber(cantidad)}
                                </span>
                            </button>
                        ))}
                    </div>

                    {vista === 'por-registrar' ? (
                        <ClientesPorRegistrar
                            teamSlug={currentTeam.slug}
                            datos={porRegistrar}
                            onRegistrar={(documento) => {
                                setDocumentoARegistrar(documento);
                                setDialogOpen(true);
                            }}
                        />
                    ) : (
                        <>
                            <div className="mb-4 flex flex-col gap-2 lg:flex-row lg:items-center">
                                <form
                                    onSubmit={submitSearch}
                                    className="border-border bg-muted/40 focus-within:border-ring focus-within:ring-ring/50 flex min-w-0 flex-1 items-center gap-2 rounded-[9px] border px-3 focus-within:ring-[3px]"
                                >
                                    <Search
                                        className="text-muted-foreground size-3.5 shrink-0"
                                        strokeWidth={2}
                                    />
                                    <input
                                        value={search}
                                        onChange={(event) =>
                                            setSearch(event.target.value)
                                        }
                                        placeholder="Buscar por nombre, razón social, RUC o DNI..."
                                        className="text-foreground placeholder:text-muted-foreground h-10 min-w-0 flex-1 bg-transparent text-[13px] outline-none"
                                    />
                                </form>

                                <Button
                                    asChild
                                    variant="outline"
                                    className="border-border bg-card text-foreground/80 h-10 rounded-[9px] px-3.5 text-[12.5px] font-semibold shadow-none"
                                >
                                    <a
                                        href={clientes.export.url(
                                            currentTeam.slug,
                                            {
                                                query: {
                                                    search:
                                                        filters.search ||
                                                        undefined,
                                                },
                                            },
                                        )}
                                    >
                                        <Download className="size-3.5" />
                                        Exportar
                                    </a>
                                </Button>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="w-full border-collapse text-[12.5px]">
                                    <thead>
                                        <tr>
                                            {columns.map((column) => (
                                                <th
                                                    key={column}
                                                    className="border-border text-muted-foreground border-b px-2.5 py-2.5 text-left font-['IBM_Plex_Mono',monospace] text-[9.5px] font-bold tracking-[0.05em] whitespace-nowrap uppercase"
                                                >
                                                    {column}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {recargando ? (
                                            <FilasCargando
                                                columnas={columns.length}
                                                filas={clients.data.length}
                                            />
                                        ) : (
                                            clients.data.map((client) => {
                                                return (
                                                    <tr key={client.id}>
                                                        <td className="border-border border-b px-2.5 py-[13px]">
                                                            <div className="flex items-center gap-2.5">
                                                                <div className="bg-muted text-foreground/80 flex size-[34px] shrink-0 items-center justify-center rounded-full text-[11px] font-bold">
                                                                    {initials(
                                                                        client
                                                                            .cliente
                                                                            .razon_social,
                                                                    ) ||
                                                                        client
                                                                            .cliente
                                                                            .avatar}
                                                                </div>
                                                                <div className="min-w-[180px]">
                                                                    <div className="text-foreground flex items-center gap-1.5 font-bold">
                                                                        <Link
                                                                            href={clientes.show(
                                                                                {
                                                                                    current_team:
                                                                                        currentTeam.slug,
                                                                                    client: client.id,
                                                                                },
                                                                            )}
                                                                            className="hover:text-primary-strong hover:underline"
                                                                        >
                                                                            {
                                                                                client
                                                                                    .cliente
                                                                                    .razon_social
                                                                            }
                                                                        </Link>
                                                                        {!client.activo && (
                                                                            <Badge className="bg-destructive/10 text-destructive-strong rounded-full border-transparent px-1.5 py-0 text-[9.5px] font-bold shadow-none">
                                                                                Inactivo
                                                                            </Badge>
                                                                        )}
                                                                    </div>
                                                                    <div className="text-muted-foreground text-[11px]">
                                                                        {
                                                                            client.codigo_interno
                                                                        }{' '}
                                                                        ·{' '}
                                                                        {client.cliente.tipo_documento.toUpperCase()}
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td className="border-border text-muted-foreground border-b px-2.5 py-[13px] font-['IBM_Plex_Mono',monospace] whitespace-nowrap tabular-nums">
                                                            {client.documento}
                                                        </td>
                                                        <td className="border-border text-muted-foreground max-w-[280px] border-b px-2.5 py-[13px]">
                                                            <span className="line-clamp-2">
                                                                {client.direccion ??
                                                                    '-'}
                                                            </span>
                                                        </td>
                                                        <td className="border-border border-b px-2.5 py-[13px]">
                                                            {renderSunatBadge(
                                                                client,
                                                            )}
                                                        </td>
                                                        <td className="border-border text-muted-foreground border-b px-2.5 py-[13px]">
                                                            {fechaCorta(
                                                                client.ultima_compra,
                                                            ) || '-'}
                                                        </td>
                                                        <td className="border-border border-b px-2.5 py-[13px]">
                                                            <div className="flex gap-1.5">
                                                                <Button
                                                                    asChild
                                                                    variant="outline"
                                                                    size="icon"
                                                                    className="border-border bg-card text-foreground/80 size-7 rounded-[7px] shadow-none"
                                                                >
                                                                    <Link
                                                                        href={clientes.show(
                                                                            {
                                                                                current_team:
                                                                                    currentTeam.slug,
                                                                                client: client.id,
                                                                            },
                                                                        )}
                                                                        aria-label={`Ver ficha de ${client.cliente.razon_social}`}
                                                                    >
                                                                        <Eye className="size-3.5" />
                                                                    </Link>
                                                                </Button>
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
                                    Mostrando {clients.from ?? 0}-
                                    {clients.to ?? 0} de{' '}
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
                                                {cleanPaginationLabel(
                                                    link.label,
                                                )}
                                            </Link>
                                        ) : (
                                            <span
                                                key={`${link.label}-${index}`}
                                                className="text-muted-foreground flex h-[26px] min-w-[26px] items-center justify-center rounded-[7px] px-2 font-['IBM_Plex_Mono',monospace] text-[11.5px]"
                                            >
                                                {cleanPaginationLabel(
                                                    link.label,
                                                )}
                                            </span>
                                        ),
                                    )}
                                </div>
                            </div>
                        </>
                    )}
                </Card>
            </div>

            <ClientCreateDialog
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                teamSlug={currentTeam.slug}
                initialDocumento={documentoARegistrar}
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
