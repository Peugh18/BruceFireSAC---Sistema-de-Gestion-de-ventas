import { Link, router, usePage } from '@inertiajs/react';
import { Award, Eye, FileDown, Filter, Printer, Search } from 'lucide-react';
import { FormEvent, useMemo, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import VendedorLayout from '@/layouts/vendedor-layout';
import certificados from '@/routes/vendedor/certificados';
import type { Team } from '@/types';

type CertificateRow = {
    id: number;
    numero: string;
    tipo: string | null;
    tipo_codigo: string | null;
    cliente: string | null;
    unidades: string[];
    fecha_emision: string | null;
    fecha_vigencia_hasta: string | null;
    estado: string;
    qr_token: string;
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
    filters: { search?: string };
};

function cleanLabel(label: string) {
    return label.replace('&laquo;', '<').replace('&raquo;', '>');
}

function badgeClass(estado: string) {
    return estado?.toLowerCase() === 'vigente'
        ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20'
        : estado?.toLowerCase() === 'vencido'
          ? 'bg-destructive/10 text-destructive border border-destructive/20'
          : 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20';
}

export default function CertificadosIndex({ certificates, filters }: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug =
        currentTeam?.slug ??
        (typeof window !== 'undefined'
            ? window.location.pathname.split('/')[1]
            : '');
    const [search, setSearch] = useState(filters.search ?? '');
    const [tipo, setTipo] = useState('');
    const tipos = useMemo(
        () =>
            Array.from(
                new Set(
                    certificates.data
                        .map((certificate) => certificate.tipo_codigo)
                        .filter(Boolean),
                ),
            ),
        [certificates.data],
    );
    const rows = tipo
        ? certificates.data.filter(
              (certificate) => certificate.tipo_codigo === tipo,
          )
        : certificates.data;

    const submitSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(
            certificados.index.url(teamSlug, {
                query: { search: search || undefined },
            }),
            {},
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <VendedorLayout title="Certificados">
            <div className="flex flex-col gap-4">
                <p className="text-[12.5px] text-muted-foreground">
                    Consulta e imprime los certificados generados por ventas y
                    servicios completados.
                </p>

                <Card className="gap-0 rounded-[16px] border-border bg-card p-5 shadow-none">
                    <div className="mb-4 flex flex-col gap-2 lg:flex-row lg:items-center">
                        <form
                            onSubmit={submitSearch}
                            className="flex min-w-0 flex-1 items-center gap-2 rounded-[9px] border border-border bg-muted/40 px-3"
                        >
                            <Search className="size-3.5 shrink-0 text-muted-foreground" />
                            <input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Buscar por cliente o N° de certificado..."
                                className="h-10 min-w-0 flex-1 bg-transparent text-[13px] text-foreground outline-none placeholder:text-muted-foreground"
                            />
                        </form>
                        <div className="flex h-10 items-center gap-2 rounded-[9px] border border-border bg-card px-3 text-foreground/80">
                            <Filter className="size-3.5" />
                            <select
                                value={tipo}
                                onChange={(event) =>
                                    setTipo(event.target.value)
                                }
                                className="bg-transparent text-[12.5px] font-semibold outline-none"
                            >
                                <option value="">Tipo</option>
                                {tipos.map((value) => (
                                    <option key={value} value={value ?? ''}>
                                        {value}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-[12.5px]">
                            <thead>
                                <tr>
                                    {[
                                        'N° Certificado',
                                        'Tipo',
                                        'Cliente',
                                        'Unidades',
                                        'Vigencia',
                                        'Estado',
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
                                {rows.map((certificate) => (
                                    <tr
                                        key={certificate.id}
                                        className="hover:bg-muted/40"
                                    >
                                        <td className="border-b border-border px-2.5 py-[13px]">
                                            <div className="flex items-center gap-2">
                                                <div
                                                    className={`flex size-[30px] shrink-0 items-center justify-center rounded-[8px] ${certificate.estado === 'vencido' ? 'bg-muted text-muted-foreground' : 'bg-destructive/10 text-primary'}`}
                                                >
                                                    <Award className="size-4" />
                                                </div>
                                                <span className="font-['IBM_Plex_Mono',monospace] font-bold">
                                                    {certificate.numero}
                                                </span>
                                            </div>
                                        </td>
                                        <td className="border-b border-border px-2.5 py-[13px]">
                                            {certificate.tipo ??
                                                certificate.tipo_codigo ??
                                                '-'}
                                        </td>
                                        <td className="border-b border-border px-2.5 py-[13px] font-semibold text-foreground">
                                            {certificate.cliente ?? '-'}
                                        </td>
                                        <td className="border-b border-border px-2.5 py-[13px]">
                                            <div className="flex max-w-[320px] flex-wrap gap-1">
                                                {certificate.unidades.map(
                                                    (unit) => (
                                                        <span
                                                            key={unit}
                                                            className={`rounded-[5px] px-1.5 py-0.5 font-['IBM_Plex_Mono',monospace] text-[9.5px] font-bold ${certificate.estado === 'vencido' ? 'bg-destructive/10 text-destructive border border-destructive/20' : 'bg-muted text-foreground/80'}`}
                                                        >
                                                            {unit}
                                                        </span>
                                                    ),
                                                )}
                                            </div>
                                        </td>
                                        <td className="border-b border-border px-2.5 py-[13px] text-foreground/80">
                                            {certificate.fecha_vigencia_hasta ??
                                                '-'}
                                        </td>
                                        <td className="border-b border-border px-2.5 py-[13px]">
                                            <Badge
                                                className={`rounded-full border-transparent px-2.5 py-1 text-[10.5px] font-bold capitalize ${badgeClass(certificate.estado)}`}
                                            >
                                                {certificate.estado}
                                            </Badge>
                                        </td>
                                        <td className="border-b border-border px-2.5 py-[13px]">
                                            <div className="flex gap-1.5">
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    size="icon"
                                                    className="size-7 rounded-[7px] border-border bg-card text-foreground/80 shadow-none"
                                                    title="Ver"
                                                >
                                                    <Link
                                                        href={certificados.show(
                                                            {
                                                                current_team:
                                                                    teamSlug,
                                                                certificate:
                                                                    certificate.id,
                                                            },
                                                        )}
                                                    >
                                                        <Eye className="size-3.5" />
                                                    </Link>
                                                </Button>
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    size="icon"
                                                    className="size-7 rounded-[7px] border-border bg-card text-foreground/80 shadow-none"
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
                                                    className="size-7 rounded-[7px] border-border bg-card text-foreground/80 shadow-none"
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
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className="mt-3.5 flex flex-col gap-3 text-[11.5px] text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
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
