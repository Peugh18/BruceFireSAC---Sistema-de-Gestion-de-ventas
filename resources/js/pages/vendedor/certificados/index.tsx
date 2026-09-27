import { Link, router, usePage } from '@inertiajs/react';
import {
    Award,
    Eye,
    FileDown,
    FileText,
    Filter,
    Printer,
    Receipt,
    Search,
    Users,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import VendedorLayout from '@/layouts/vendedor-layout';
import certificados from '@/routes/vendedor/certificados';
import clientes from '@/routes/vendedor/clientes';
import ventas from '@/routes/vendedor/ventas';
import type { Team } from '@/types';

type CertificateRow = {
    id: number;
    numero: string;
    tipo: string | null;
    tipo_codigo: string | null;
    cliente: string | null;
    client_id: number | null;
    referencia: string | null;
    venta: {
        id: number;
        numero: string | null;
        comprobante: string | null;
    } | null;
    unidades: string[];
    participantes: number;
    fecha_emision: string | null;
    fecha_vigencia_hasta: string | null;
    estado: string;
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
    certificates: Paginated<CertificateRow>;
    tipos: { codigo: string; nombre: string }[];
    filters: { search?: string; tipo?: string; estado?: string };
};

function cleanLabel(label: string) {
    return label.replace('&laquo;', '<').replace('&raquo;', '>');
}

function badgeClass(estado: string) {
    const e = estado?.toLowerCase();
    if (e === 'vigente') {
        return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20';
    }
    if (e === 'anulado') {
        return 'bg-muted text-muted-foreground border border-border';
    }
    if (e === 'vencido') {
        return 'bg-destructive/10 text-destructive border border-destructive/20';
    }
    return 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20';
}

const accion =
    'border-border bg-card text-foreground/80 size-7 rounded-[7px] shadow-none';

export default function CertificadosIndex({
    certificates,
    tipos,
    filters,
}: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug =
        currentTeam?.slug ??
        (typeof window !== 'undefined'
            ? window.location.pathname.split('/')[1]
            : '');
    const [search, setSearch] = useState(filters.search ?? '');
    const tipo = filters.tipo ?? '';
    const estado = filters.estado ?? '';
    const filtrando = Boolean(filters.search || tipo || estado);

    function buscar(cambios: {
        search?: string;
        tipo?: string;
        estado?: string;
    }) {
        const query = { search, tipo, estado, ...cambios };

        router.get(
            certificados.index.url(teamSlug, {
                query: {
                    search: query.search || undefined,
                    tipo: query.tipo || undefined,
                    estado: query.estado || undefined,
                },
            }),
            {},
            { preserveScroll: true, preserveState: true },
        );
    }

    const submitSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        buscar({});
    };

    const verCertificado = (id: number) =>
        certificados.show.url({ current_team: teamSlug, certificate: id });

    return (
        <VendedorLayout title="Certificados">
            <div className="flex flex-col gap-4">
                <p className="text-muted-foreground text-[12.5px]">
                    Todos los certificados emitidos, con la venta de la que
                    salieron. Toca uno para verlo.
                </p>

                <Card className="border-border bg-card gap-0 rounded-[16px] p-5 shadow-none">
                    {/* Filtro por estado: Todos · Vigentes · Vencidos · Anulados */}
                    <div className="border-border mb-4 flex flex-wrap items-center gap-1.5 border-b pb-3">
                        {[
                            { valor: '', etiqueta: 'Todos' },
                            { valor: 'vigente', etiqueta: 'Vigentes' },
                            { valor: 'vencido', etiqueta: 'Vencidos' },
                            { valor: 'anulado', etiqueta: 'Anulados' },
                        ].map((pestana) => (
                            <button
                                key={pestana.valor}
                                type="button"
                                onClick={() =>
                                    buscar({ estado: pestana.valor })
                                }
                                className={`rounded-full px-3.5 py-1 text-[12px] font-bold transition-colors ${
                                    estado === pestana.valor
                                        ? 'bg-primary text-primary-foreground'
                                        : 'bg-muted text-muted-foreground hover:bg-accent hover:text-foreground'
                                }`}
                            >
                                {pestana.etiqueta}
                            </button>
                        ))}
                    </div>

                    <div className="mb-4 flex flex-col gap-2 lg:flex-row lg:items-center">
                        <form
                            onSubmit={submitSearch}
                            className="border-border bg-muted/40 flex min-w-0 flex-1 items-center gap-2 rounded-[9px] border px-3"
                        >
                            <Search className="text-muted-foreground size-3.5 shrink-0" />
                            <input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Buscar por cliente, RUC/DNI, N° de certificado, venta o serie del extintor…"
                                className="text-foreground placeholder:text-muted-foreground h-10 min-w-0 flex-1 bg-transparent text-[13px] outline-none"
                            />
                        </form>
                        {tipos.length > 1 ? (
                            <div className="border-border bg-card text-foreground/80 flex h-10 items-center gap-2 rounded-[9px] border px-3">
                                <Filter className="size-3.5" />
                                <select
                                    value={tipo}
                                    onChange={(event) =>
                                        buscar({ tipo: event.target.value })
                                    }
                                    className="bg-transparent text-[12.5px] font-semibold outline-none"
                                >
                                    <option value="">Todos los tipos</option>
                                    {tipos.map((opcion) => (
                                        <option
                                            key={opcion.codigo}
                                            value={opcion.codigo}
                                        >
                                            {opcion.nombre}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        ) : null}
                    </div>

                    {certificates.data.length === 0 ? (
                        <div className="border-border flex flex-col items-center gap-2 rounded-[12px] border border-dashed px-6 py-12 text-center">
                            <Award className="text-muted-foreground size-8" />
                            <div className="text-foreground text-[14px] font-bold">
                                {filtrando
                                    ? 'Ningún certificado coincide con la búsqueda'
                                    : 'Aún no hay certificados'}
                            </div>
                            {filtrando ? null : (
                                <p className="text-muted-foreground max-w-md text-[12.5px]">
                                    Salen solos al confirmar una venta con
                                    extintores. Los de servicios (luces, alarma,
                                    lámina) se llenan desde el detalle de la
                                    venta.
                                </p>
                            )}
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full border-collapse text-[12.5px]">
                                <thead>
                                    <tr>
                                        {[
                                            'Certificado',
                                            'Cliente',
                                            'Venta',
                                            'Qué certifica',
                                            'Vigencia',
                                            'Estado',
                                            '',
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
                                    {certificates.data.map((certificate) => (
                                        <tr
                                            key={certificate.id}
                                            onClick={() =>
                                                router.visit(
                                                    verCertificado(
                                                        certificate.id,
                                                    ),
                                                )
                                            }
                                            className="hover:bg-muted/40 cursor-pointer"
                                        >
                                            <td className="border-border border-b px-2.5 py-[11px]">
                                                <div className="flex items-center gap-2">
                                                    <div
                                                        className={`flex size-[30px] shrink-0 items-center justify-center rounded-[8px] ${certificate.estado === 'vigente' ? 'bg-destructive/10 text-primary' : 'bg-muted text-muted-foreground'}`}
                                                    >
                                                        <Award className="size-4" />
                                                    </div>
                                                    <div className="min-w-0">
                                                        <div className="font-['IBM_Plex_Mono',monospace] font-bold">
                                                            {certificate.numero}
                                                        </div>
                                                        <div className="text-muted-foreground max-w-[220px] truncate text-[11px]">
                                                            {certificate.tipo ??
                                                                certificate.tipo_codigo}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="border-border border-b px-2.5 py-[11px]">
                                                {certificate.client_id ? (
                                                    <Link
                                                        href={clientes.show.url(
                                                            {
                                                                current_team:
                                                                    teamSlug,
                                                                client: certificate.client_id,
                                                            },
                                                        )}
                                                        onClick={(event) =>
                                                            event.stopPropagation()
                                                        }
                                                        className="text-foreground hover:text-primary font-semibold hover:underline"
                                                    >
                                                        {certificate.cliente}
                                                    </Link>
                                                ) : (
                                                    '-'
                                                )}
                                                {certificate.referencia ? (
                                                    <div className="text-muted-foreground text-[11px]">
                                                        {certificate.referencia}
                                                    </div>
                                                ) : null}
                                            </td>
                                            <td className="border-border border-b px-2.5 py-[11px]">
                                                {certificate.venta ? (
                                                    <Link
                                                        href={ventas.show.url({
                                                            current_team:
                                                                teamSlug,
                                                            sale: certificate
                                                                .venta.id,
                                                        })}
                                                        onClick={(event) =>
                                                            event.stopPropagation()
                                                        }
                                                        className="group inline-flex flex-col"
                                                    >
                                                        <span className="group-hover:text-primary inline-flex items-center gap-1 font-['IBM_Plex_Mono',monospace] font-bold group-hover:underline">
                                                            <Receipt className="size-3" />
                                                            {certificate.venta
                                                                .numero ??
                                                                `Venta ${certificate.venta.id}`}
                                                        </span>
                                                        {certificate.venta
                                                            .comprobante ? (
                                                            <span className="text-muted-foreground text-[11px]">
                                                                {
                                                                    certificate
                                                                        .venta
                                                                        .comprobante
                                                                }
                                                            </span>
                                                        ) : null}
                                                    </Link>
                                                ) : (
                                                    <span className="text-muted-foreground text-[11.5px]">
                                                        Orden de servicio
                                                    </span>
                                                )}
                                            </td>
                                            <td className="border-border border-b px-2.5 py-[11px]">
                                                {certificate.participantes >
                                                0 ? (
                                                    <span className="text-foreground/80 inline-flex items-center gap-1 text-[11.5px]">
                                                        <Users className="size-3.5" />
                                                        {
                                                            certificate.participantes
                                                        }{' '}
                                                        trabajadores
                                                    </span>
                                                ) : certificate.unidades
                                                      .length > 0 ? (
                                                    <div className="flex max-w-[260px] flex-wrap gap-1">
                                                        {certificate.unidades
                                                            .slice(0, 4)
                                                            .map((unit) => (
                                                                <span
                                                                    key={unit}
                                                                    className="bg-muted text-foreground/80 rounded-[5px] px-1.5 py-0.5 font-['IBM_Plex_Mono',monospace] text-[9.5px] font-bold"
                                                                >
                                                                    {unit}
                                                                </span>
                                                            ))}
                                                        {certificate.unidades
                                                            .length > 4 ? (
                                                            <span className="text-muted-foreground px-1 text-[10.5px] font-bold">
                                                                +
                                                                {certificate
                                                                    .unidades
                                                                    .length -
                                                                    4}{' '}
                                                                más
                                                            </span>
                                                        ) : null}
                                                    </div>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        -
                                                    </span>
                                                )}
                                            </td>
                                            <td className="border-border text-foreground/80 border-b px-2.5 py-[11px] whitespace-nowrap">
                                                {certificate.fecha_vigencia_hasta ??
                                                    '-'}
                                            </td>
                                            <td className="border-border border-b px-2.5 py-[11px]">
                                                <Badge
                                                    className={`rounded-full border-transparent px-2.5 py-1 text-[10.5px] font-bold capitalize ${badgeClass(certificate.estado)}`}
                                                >
                                                    {certificate.estado}
                                                </Badge>
                                            </td>
                                            <td
                                                className="border-border border-b px-2.5 py-[11px]"
                                                onClick={(event) =>
                                                    event.stopPropagation()
                                                }
                                            >
                                                <div className="flex gap-1.5">
                                                    <Button
                                                        asChild
                                                        variant="outline"
                                                        size="icon"
                                                        className={accion}
                                                        title="Ver certificado"
                                                    >
                                                        <Link
                                                            href={verCertificado(
                                                                certificate.id,
                                                            )}
                                                        >
                                                            <Eye className="size-3.5" />
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        asChild
                                                        variant="outline"
                                                        size="icon"
                                                        className={accion}
                                                        title="Descargar PDF"
                                                    >
                                                        <a
                                                            href={certificados.pdf.url(
                                                                {
                                                                    current_team:
                                                                        teamSlug,
                                                                    certificate:
                                                                        certificate.id,
                                                                },
                                                            )}
                                                        >
                                                            <FileDown className="size-3.5" />
                                                        </a>
                                                    </Button>
                                                    <Button
                                                        asChild
                                                        variant="outline"
                                                        size="icon"
                                                        className={accion}
                                                        title="Imprimir"
                                                    >
                                                        <a
                                                            href={certificados.pdf.url(
                                                                {
                                                                    current_team:
                                                                        teamSlug,
                                                                    certificate:
                                                                        certificate.id,
                                                                },
                                                                {
                                                                    query: {
                                                                        inline: 1,
                                                                    },
                                                                },
                                                            )}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                        >
                                                            <Printer className="size-3.5" />
                                                        </a>
                                                    </Button>
                                                    <Button
                                                        asChild
                                                        variant="outline"
                                                        size="icon"
                                                        className={accion}
                                                        title="Descargar Word editable"
                                                    >
                                                        <a
                                                            href={certificados.word.url(
                                                                {
                                                                    current_team:
                                                                        teamSlug,
                                                                    certificate:
                                                                        certificate.id,
                                                                },
                                                            )}
                                                        >
                                                            <FileText className="size-3.5 text-[#2b579a]" />
                                                        </a>
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    <div className="text-muted-foreground mt-3.5 flex flex-col gap-3 text-[11.5px] sm:flex-row sm:items-center sm:justify-between">
                        <span>
                            Mostrando {certificates.from ?? 0}-
                            {certificates.to ?? 0} de{' '}
                            {certificates.total ?? certificates.data.length}{' '}
                            certificados
                        </span>
                        <div className="flex flex-wrap gap-1.5">
                            {certificates.links.map((link, index) =>
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
        </VendedorLayout>
    );
}
