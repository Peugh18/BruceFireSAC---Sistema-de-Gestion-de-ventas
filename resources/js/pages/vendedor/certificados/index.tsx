import { Link, router, usePage } from '@inertiajs/react';
import {
    Award,
    FileDown,
    FileText,
    Filter,
    Printer,
    RotateCcw,
    Search,
} from 'lucide-react';
import { useState } from 'react';

import { useBusquedaDinamica } from '@/hooks/use-busqueda-dinamica';
import { Badge } from '@/components/ui/badge';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import VendedorLayout from '@/layouts/vendedor-layout';
import certificados from '@/routes/vendedor/certificados';
import clientes from '@/routes/vendedor/clientes';
import type { Team } from '@/types';
import { fechaCorta } from '@/lib/utils';
import { NativeSelect } from '@/components/ui/native-select';

type CertificateRow = {
    id: number;
    numero: string;
    tipo: string | null;
    tipo_codigo: string | null;
    cliente: string | null;
    documento: string | null;
    client_id: number | null;
    orden: string | null;
    codigos: string[];
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
        return 'bg-emerald-500/10 text-success-strong border border-emerald-500/20';
    }
    if (e === 'anulado') {
        return 'bg-muted text-muted-foreground border border-border';
    }
    if (e === 'vencido') {
        return 'bg-destructive/10 text-destructive-strong border border-destructive/20';
    }
    return 'bg-amber-500/10 text-warning-strong border border-amber-500/20';
}

const accion =
    'border-border bg-card text-foreground/80 size-9 rounded-lg shadow-none';

type Grupo = {
    clave: string;
    dia: string | null;
    certificados: CertificateRow[];
};

/** Una atención: lo que recibió un cliente el mismo día por la misma orden o venta. */
function agrupar(filas: CertificateRow[]): Grupo[] {
    const grupos: Grupo[] = [];

    for (const fila of filas) {
        const clave = `${fila.fecha_emision}|${fila.client_id}|${fila.venta?.id ?? fila.orden ?? fila.id}`;
        const grupo = grupos.find((g) => g.clave === clave);

        if (grupo) {
            grupo.certificados.push(fila);
        } else {
            grupos.push({
                clave,
                dia: fila.fecha_emision,
                certificados: [fila],
            });
        }
    }

    return grupos;
}

function nombreDelDia(fecha: string | null): string {
    if (!fecha) {
        return 'Sin fecha';
    }

    const d = new Date(`${fecha}T00:00:00`);
    const hoy = new Date();
    const ayer = new Date();
    ayer.setDate(hoy.getDate() - 1);
    const texto = d.toLocaleDateString('es-PE', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    });

    if (d.toDateString() === hoy.toDateString()) {
        return `Hoy · ${texto}`;
    }

    return d.toDateString() === ayer.toDateString() ? `Ayer · ${texto}` : texto;
}

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

    const aplicarBusqueda = () => {
        buscar({});
    };
    const submitSearch = useBusquedaDinamica(search, aplicarBusqueda);

    const verCertificado = (id: number) =>
        certificados.show.url({ current_team: teamSlug, certificate: id });

    return (
        <VendedorLayout title="Certificados">
            <div className="flex flex-col gap-4">
                <PageHeader
                    title="Certificados"
                    description="Agrupados por atención: qué recibió cada cliente y cuándo."
                />

                <Card className="border-border bg-card gap-0 rounded-2xl p-5 shadow-none">
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
                            className="border-border bg-muted/40 focus-within:border-ring focus-within:ring-ring/50 flex min-w-0 flex-1 items-center gap-2 rounded-lg border px-3 focus-within:ring-[3px]"
                        >
                            <Search className="text-muted-foreground size-3.5 shrink-0" />
                            <input
                                aria-label="Buscar certificados"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Buscar por cliente, RUC/DNI, N° de certificado, venta o serie del extintor…"
                                className="text-foreground placeholder:text-muted-foreground h-10 min-w-0 flex-1 bg-transparent text-[13px] outline-none"
                            />
                        </form>
                        {tipos.length > 1 ? (
                            <div className="border-border bg-card text-foreground/80 focus-within:border-ring focus-within:ring-ring/50 flex h-10 items-center gap-2 rounded-lg border px-3 focus-within:ring-[3px]">
                                <Filter className="size-3.5" />
                                <NativeSelect
                                    aria-label="Filtrar por tipo de certificado"
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
                                </NativeSelect>
                            </div>
                        ) : null}
                    </div>

                    {certificates.data.length === 0 ? (
                        <div className="border-border flex flex-col items-center gap-2 rounded-2xl border border-dashed px-6 py-12 text-center">
                            <Award className="text-muted-foreground size-8" />
                            <div className="text-foreground text-[14px] font-bold">
                                {filtrando
                                    ? 'Ningún certificado coincide con la búsqueda'
                                    : 'Aún no hay certificados'}
                            </div>
                            {filtrando ? (
                                <button
                                    type="button"
                                    onClick={() => {
                                        setSearch('');
                                        router.get(
                                            certificados.index.url(teamSlug),
                                        );
                                    }}
                                    className="border-border bg-card text-foreground hover:bg-muted mt-2 inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold shadow-xs transition-colors"
                                >
                                    <RotateCcw className="size-3" />
                                    <span>Limpiar filtros</span>
                                </button>
                            ) : (
                                <p className="text-muted-foreground max-w-md text-[12.5px]">
                                    Salen solos al confirmar una venta con
                                    extintores. Los de servicios (luces, alarma,
                                    lámina) se llenan desde el detalle de la
                                    venta.
                                </p>
                            )}
                        </div>
                    ) : (
                        <div className="flex flex-col gap-3">
                            {agrupar(certificates.data).map(
                                (grupo, indice, todos) => {
                                    const primero = grupo.certificados[0];
                                    const codigos = [
                                        ...new Set(
                                            grupo.certificados.flatMap(
                                                (c) => c.codigos,
                                            ),
                                        ),
                                    ];
                                    const nuevoDia =
                                        indice === 0 ||
                                        todos[indice - 1].dia !== grupo.dia;
                                    const pdf = (c: CertificateRow) =>
                                        certificados.pdf.url({
                                            current_team: teamSlug,
                                            certificate: c.id,
                                        });

                                    return (
                                        <div
                                            key={grupo.clave}
                                            className="space-y-3"
                                        >
                                            {nuevoDia ? (
                                                <h2 className="text-muted-foreground pt-2 text-xs font-bold tracking-wide uppercase">
                                                    {nombreDelDia(grupo.dia)}
                                                </h2>
                                            ) : null}
                                            <article className="border-border bg-card space-y-3 rounded-2xl border p-4">
                                                <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
                                                    <div className="min-w-0 flex-[1_1_280px]">
                                                        {primero.client_id ? (
                                                            <Link
                                                                href={clientes.show.url(
                                                                    {
                                                                        current_team:
                                                                            teamSlug,
                                                                        client: primero.client_id,
                                                                    },
                                                                )}
                                                                className="text-foreground hover:text-primary-strong text-base font-bold hover:underline"
                                                            >
                                                                {
                                                                    primero.cliente
                                                                }
                                                            </Link>
                                                        ) : (
                                                            <span className="text-foreground text-base font-bold">
                                                                {primero.cliente ??
                                                                    'Sin cliente'}
                                                            </span>
                                                        )}
                                                        <p className="text-muted-foreground text-xs">
                                                            {[
                                                                primero.documento,
                                                                primero.orden,
                                                                primero.venta
                                                                    ?.numero &&
                                                                    `venta ${primero.venta.numero}`,
                                                                primero.venta
                                                                    ?.comprobante,
                                                                codigos.length >
                                                                    0 &&
                                                                    `${codigos.length} ${codigos.length === 1 ? 'extintor' : 'extintores'}`,
                                                                primero.participantes >
                                                                    0 &&
                                                                    `${primero.participantes} trabajadores`,
                                                            ]
                                                                .filter(Boolean)
                                                                .join(' · ')}
                                                        </p>
                                                    </div>
                                                    {grupo.certificados.length >
                                                    1 ? (
                                                        <Button
                                                            type="button"
                                                            onClick={() =>
                                                                grupo.certificados.forEach(
                                                                    (c) => {
                                                                        const enlace =
                                                                            document.createElement(
                                                                                'a',
                                                                            );
                                                                        enlace.href =
                                                                            pdf(
                                                                                c,
                                                                            );
                                                                        enlace.download =
                                                                            '';
                                                                        enlace.click();
                                                                    },
                                                                )
                                                            }
                                                            className="h-11"
                                                        >
                                                            <FileDown className="size-4" />
                                                            Descargar los{' '}
                                                            {
                                                                grupo
                                                                    .certificados
                                                                    .length
                                                            }{' '}
                                                            PDF
                                                        </Button>
                                                    ) : null}
                                                </div>
                                                <div className="grid gap-2 md:grid-cols-2">
                                                    {grupo.certificados.map(
                                                        (certificate) => (
                                                            <div
                                                                key={
                                                                    certificate.id
                                                                }
                                                                className="border-border flex flex-wrap items-center gap-2 rounded-xl border p-3"
                                                            >
                                                                <Award
                                                                    className={`size-5 shrink-0 ${certificate.estado === 'vigente' ? 'text-primary-strong' : 'text-muted-foreground'}`}
                                                                />
                                                                <Link
                                                                    href={verCertificado(
                                                                        certificate.id,
                                                                    )}
                                                                    className="min-w-0 flex-1"
                                                                >
                                                                    <span className="text-foreground block font-['IBM_Plex_Mono',monospace] text-sm font-bold">
                                                                        {
                                                                            certificate.numero
                                                                        }
                                                                    </span>
                                                                    <span className="text-muted-foreground block truncate text-xs">
                                                                        {certificate.tipo ??
                                                                            certificate.tipo_codigo}
                                                                        {certificate.fecha_vigencia_hasta
                                                                            ? ` · hasta ${fechaCorta(certificate.fecha_vigencia_hasta)}`
                                                                            : ''}
                                                                    </span>
                                                                </Link>
                                                                <Badge
                                                                    className={`rounded-full border-transparent px-2.5 py-1 text-xs font-bold capitalize ${badgeClass(certificate.estado)}`}
                                                                >
                                                                    {
                                                                        certificate.estado
                                                                    }
                                                                </Badge>
                                                                <div className="flex gap-1">
                                                                    <Button
                                                                        aria-label={`Descargar PDF de ${certificate.numero}`}
                                                                        asChild
                                                                        variant="outline"
                                                                        size="icon"
                                                                        className={
                                                                            accion
                                                                        }
                                                                        title="Descargar PDF"
                                                                    >
                                                                        <a
                                                                            href={pdf(
                                                                                certificate,
                                                                            )}
                                                                        >
                                                                            <FileDown className="size-4" />
                                                                        </a>
                                                                    </Button>
                                                                    <Button
                                                                        aria-label={`Imprimir ${certificate.numero}`}
                                                                        asChild
                                                                        variant="outline"
                                                                        size="icon"
                                                                        className={
                                                                            accion
                                                                        }
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
                                                                            <Printer className="size-4" />
                                                                        </a>
                                                                    </Button>
                                                                    <Button
                                                                        aria-label={`Descargar Word de ${certificate.numero}`}
                                                                        asChild
                                                                        variant="outline"
                                                                        size="icon"
                                                                        className={
                                                                            accion
                                                                        }
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
                                                                            <FileText className="size-4 text-[#2b579a]" />
                                                                        </a>
                                                                    </Button>
                                                                </div>
                                                            </div>
                                                        ),
                                                    )}
                                                </div>
                                                {codigos.length > 0 ? (
                                                    <p className="text-muted-foreground text-xs">
                                                        Extintores:{' '}
                                                        <span className="text-foreground font-['IBM_Plex_Mono',monospace]">
                                                            {codigos.join(
                                                                ' · ',
                                                            )}
                                                        </span>
                                                    </p>
                                                ) : null}
                                            </article>
                                        </div>
                                    );
                                },
                            )}
                        </div>
                    )}

                    <div className="text-muted-foreground mt-3.5 flex flex-col gap-3 text-xs sm:flex-row sm:items-center sm:justify-between">
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
                                        className={`flex h-[26px] min-w-[26px] items-center justify-center rounded-md px-2 font-['IBM_Plex_Mono',monospace] text-xs no-underline ${link.active ? 'bg-primary font-bold text-white' : 'text-muted-foreground hover:bg-muted hover:text-foreground'}`}
                                    >
                                        {cleanLabel(link.label)}
                                    </Link>
                                ) : (
                                    <span
                                        key={`${link.label}-${index}`}
                                        className="text-muted-foreground flex h-[26px] min-w-[26px] items-center justify-center rounded-md px-2 font-['IBM_Plex_Mono',monospace] text-xs"
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
