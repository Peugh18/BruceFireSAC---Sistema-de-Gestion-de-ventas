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
type Paginated<T> = { data: T[]; links: PaginationLink[]; from?: number | null; to?: number | null; total?: number };
type Props = { certificates: Paginated<CertificateRow>; filters: { search?: string } };

function cleanLabel(label: string) {
    return label.replace('&laquo;', '<').replace('&raquo;', '>');
}

function badgeClass(estado: string) {
    return estado?.toLowerCase() === 'vigente'
        ? 'bg-[#E5F5EC] text-[#1E8E5A]'
        : estado?.toLowerCase() === 'vencido'
          ? 'bg-[#FBE7E7] text-[#B91C1C]'
          : 'bg-[#FDF1E0] text-[#B45309]';
}

export default function CertificadosIndex({ certificates, filters }: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug = currentTeam?.slug ?? (typeof window !== 'undefined' ? window.location.pathname.split('/')[1] : '');
    const [search, setSearch] = useState(filters.search ?? '');
    const [tipo, setTipo] = useState('');
    const tipos = useMemo(() => Array.from(new Set(certificates.data.map((certificate) => certificate.tipo_codigo).filter(Boolean))), [certificates.data]);
    const rows = tipo ? certificates.data.filter((certificate) => certificate.tipo_codigo === tipo) : certificates.data;

    const submitSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(
            certificados.index.url(teamSlug, { query: { search: search || undefined } }),
            {},
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <VendedorLayout title="Certificados">
            <div className="flex flex-col gap-4">
                <p className="text-[12.5px] text-[#8A8680]">
                    Consulta e imprime los certificados generados por ventas y servicios completados.
                </p>

                <Card className="gap-0 rounded-[16px] border-[#E7E4DE] bg-white p-5 shadow-none">
                    <div className="mb-4 flex flex-col gap-2 lg:flex-row lg:items-center">
                        <form onSubmit={submitSearch} className="flex min-w-0 flex-1 items-center gap-2 rounded-[9px] border border-[#E4E1DC] bg-[#FAFAF8] px-3">
                            <Search className="size-3.5 shrink-0 text-[#8A8680]" />
                            <input
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Buscar por cliente o N° de certificado..."
                                className="h-10 min-w-0 flex-1 bg-transparent text-[13px] text-[#201F1D] outline-none placeholder:text-[#8A8680]"
                            />
                        </form>
                        <div className="flex h-10 items-center gap-2 rounded-[9px] border border-[#E4E1DC] bg-white px-3 text-[#4A4742]">
                            <Filter className="size-3.5" />
                            <select value={tipo} onChange={(event) => setTipo(event.target.value)} className="bg-transparent text-[12.5px] font-semibold outline-none">
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
                                <tr>{['N° Certificado', 'Tipo', 'Cliente', 'Unidades', 'Vigencia', 'Estado', 'Acciones'].map((h) => <th key={h} className="border-b border-[#E7E4DE] px-2.5 py-2.5 text-left font-['IBM_Plex_Mono',monospace] text-[9.5px] font-bold tracking-[0.05em] text-[#8A8680] uppercase">{h}</th>)}</tr>
                            </thead>
                            <tbody>
                                {rows.map((certificate) => (
                                    <tr key={certificate.id} className="hover:bg-[#FAFAF8]">
                                        <td className="border-b border-[#F1EFEC] px-2.5 py-[13px]">
                                            <div className="flex items-center gap-2">
                                                <div className={`flex size-[30px] shrink-0 items-center justify-center rounded-[8px] ${certificate.estado === 'vencido' ? 'bg-[#F1EFEC] text-[#8A8680]' : 'bg-[#FBE7E7] text-[#E31E24]'}`}>
                                                    <Award className="size-4" />
                                                </div>
                                                <span className="font-['IBM_Plex_Mono',monospace] font-bold">{certificate.numero}</span>
                                            </div>
                                        </td>
                                        <td className="border-b border-[#F1EFEC] px-2.5 py-[13px]">{certificate.tipo ?? certificate.tipo_codigo ?? '-'}</td>
                                        <td className="border-b border-[#F1EFEC] px-2.5 py-[13px] font-semibold text-[#201F1D]">{certificate.cliente ?? '-'}</td>
                                        <td className="border-b border-[#F1EFEC] px-2.5 py-[13px]">
                                            <div className="flex max-w-[320px] flex-wrap gap-1">
                                                {certificate.unidades.map((unit) => (
                                                    <span key={unit} className={`rounded-[5px] px-1.5 py-0.5 font-['IBM_Plex_Mono',monospace] text-[9.5px] font-bold ${certificate.estado === 'vencido' ? 'bg-[#FBE7E7] text-[#B91C1C]' : 'bg-[#F1EFEC] text-[#4A4742]'}`}>
                                                        {unit}
                                                    </span>
                                                ))}
                                            </div>
                                        </td>
                                        <td className="border-b border-[#F1EFEC] px-2.5 py-[13px] text-[#4A4742]">{certificate.fecha_vigencia_hasta ?? '-'}</td>
                                        <td className="border-b border-[#F1EFEC] px-2.5 py-[13px]">
                                            <Badge className={`rounded-full border-transparent px-2.5 py-1 text-[10.5px] font-bold capitalize ${badgeClass(certificate.estado)}`}>{certificate.estado}</Badge>
                                        </td>
                                        <td className="border-b border-[#F1EFEC] px-2.5 py-[13px]">
                                            <div className="flex gap-1.5">
                                                <Button asChild variant="outline" size="icon" className="size-7 rounded-[7px] border-[#E7E4DE] bg-white text-[#4A4742] shadow-none">
                                                    <Link href={certificados.show({ current_team: teamSlug, certificate: certificate.id })}><Eye className="size-3.5" /></Link>
                                                </Button>
                                                <Button variant="outline" size="icon" className="size-7 rounded-[7px] border-[#E7E4DE] bg-white text-[#4A4742] shadow-none"><FileDown className="size-3.5" /></Button>
                                                <Button variant="outline" size="icon" className="size-7 rounded-[7px] border-[#E7E4DE] bg-white text-[#4A4742] shadow-none"><Printer className="size-3.5" /></Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className="mt-3.5 flex flex-col gap-3 text-[11.5px] text-[#8A8680] sm:flex-row sm:items-center sm:justify-between">
                        <span>Mostrando {certificates.from ?? 0}-{certificates.to ?? 0} de {certificates.total ?? certificates.data.length} certificados</span>
                        <div className="flex flex-wrap gap-1.5">
                            {certificates.links.map((link, index) =>
                                link.url ? (
                                    <Link key={`${link.label}-${index}`} href={link.url} preserveScroll preserveState className={`flex h-[26px] min-w-[26px] items-center justify-center rounded-[7px] px-2 font-['IBM_Plex_Mono',monospace] text-[11.5px] no-underline ${link.active ? 'bg-[#E31E24] font-bold text-white' : 'text-[#8A8680] hover:bg-[#F1EFEC] hover:text-[#201F1D]'}`}>
                                        {cleanLabel(link.label)}
                                    </Link>
                                ) : (
                                    <span key={`${link.label}-${index}`} className="flex h-[26px] min-w-[26px] items-center justify-center rounded-[7px] px-2 font-['IBM_Plex_Mono',monospace] text-[11.5px] text-[#C9C5BE]">
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
