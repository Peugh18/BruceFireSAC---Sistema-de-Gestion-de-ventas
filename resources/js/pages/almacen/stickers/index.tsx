import { Head, Link, router, usePage } from '@inertiajs/react';
import { Barcode, Building2, FileText, Printer } from 'lucide-react';

import { Card } from '@/components/ui/card';
import AlmacenLayout from '@/layouts/almacen-layout';
import recepciones from '@/routes/almacen/recepciones';
import type { Team } from '@/types';

export type StickerReceptionRow = {
    id: number;
    proveedor: string;
    fecha: string;
    sede_almacen: { id: number; nombre: string } | null;
    unidades_count: number;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type PaginatedReceptions = {
    data: StickerReceptionRow[];
    links: PaginationLink[];
    total: number;
};

export type Props = {
    recepciones: PaginatedReceptions;
};

function formatDate(dateStr: string): string {
    const [year, month, day] = dateStr.split('-');
    return `${day}/${month}/${year}`;
}

export default function StickersIndex({ recepciones: receptions }: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug =
        currentTeam?.slug ||
        (typeof window !== 'undefined' ? window.location.pathname.split('/')[1] : '');

    return (
        <AlmacenLayout title="Stickers de Barras">
            <Head title="Stickers de Barras - Almacén" />

            <div className="flex flex-col gap-6">
                <div>
                    <h1 className="text-xl font-bold text-[#201F1D]">Stickers de Código de Barras</h1>
                    <p className="text-xs text-[#8A8680]">
                        Recepciones con unidades serializadas listas para imprimir su hoja de stickers (§84.9). El PDF se
                        genera por recepción, con las unidades conformes que ingresaron en ese lote.
                    </p>
                </div>

                <Card className="rounded-[16px] border-[#E7E4DE] bg-white p-5 shadow-none">
                    {receptions.data.length === 0 ? (
                        <div className="flex min-h-[260px] flex-col items-center justify-center text-center">
                            <Barcode className="size-10 text-[#D5D2CB]" />
                            <p className="mt-2 text-sm font-medium text-[#8A8680]">
                                Aún no hay recepciones con unidades serializadas para imprimir stickers.
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-[12.5px]">
                                <thead>
                                    <tr className="border-b border-[#E7E4DE] text-[11px] font-bold text-[#8A8680] uppercase tracking-wider">
                                        <th className="py-2.5 pr-3">N° Doc.</th>
                                        <th className="py-2.5 px-3">Fecha</th>
                                        <th className="py-2.5 px-4">Proveedor</th>
                                        <th className="py-2.5 px-3">Sede Almacén</th>
                                        <th className="py-2.5 px-3 text-center">Unidades Serializadas</th>
                                        <th className="py-2.5 pl-4 text-right">Acción</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-[#F1EFEC]">
                                    {receptions.data.map((rec) => (
                                        <tr key={rec.id} className="transition-colors hover:bg-[#FAFAF8]">
                                            <td className="py-3 pr-3 font-mono font-bold text-[#201F1D]">#{rec.id}</td>
                                            <td className="py-3 px-3 font-mono text-[11.5px] whitespace-nowrap text-[#4A4742]">
                                                {formatDate(rec.fecha)}
                                            </td>
                                            <td className="py-3 px-4 font-bold text-[#201F1D]">{rec.proveedor}</td>
                                            <td className="py-3 px-3 text-[#4A4742]">
                                                <div className="flex items-center gap-1.5">
                                                    <Building2 className="size-3.5 text-[#8A8680]" />
                                                    <span>{rec.sede_almacen?.nombre ?? '—'}</span>
                                                </div>
                                            </td>
                                            <td className="py-3 px-3 text-center font-mono font-bold text-[#201F1D]">
                                                {rec.unidades_count}
                                            </td>
                                            <td className="py-3 pl-4 text-right">
                                                <div className="flex items-center justify-end gap-2">
                                                    <Link
                                                        href={recepciones.show.url({ current_team: teamSlug, reception: rec.id })}
                                                        className="inline-flex items-center gap-1 rounded-md border border-[#E4E1DC] bg-white px-2.5 py-1 text-xs font-bold text-[#201F1D] hover:bg-[#F3F1ED]"
                                                    >
                                                        <FileText className="size-3.5" />
                                                        <span>Detalle</span>
                                                    </Link>
                                                    <a
                                                        href={recepciones.stickers.url({ current_team: teamSlug, reception: rec.id })}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="inline-flex items-center gap-1 rounded-md bg-[#18181B] px-2.5 py-1 text-xs font-bold text-white hover:bg-[#27272A]"
                                                    >
                                                        <Printer className="size-3.5" />
                                                        <span>Imprimir</span>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>

                            {receptions.links.length > 3 && (
                                <div className="mt-4 flex items-center justify-between border-t border-[#F1EFEC] pt-4 text-xs text-[#8A8680]">
                                    <span>
                                        Mostrando <b>{receptions.data.length}</b> de <b>{receptions.total}</b> recepciones
                                    </span>
                                    <div className="flex items-center gap-1">
                                        {receptions.links.map((link, idx) => (
                                            <button
                                                key={idx}
                                                type="button"
                                                disabled={!link.url}
                                                onClick={() => {
                                                    if (link.url) {
                                                        router.get(link.url, {}, { preserveState: true, preserveScroll: true });
                                                    }
                                                }}
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                                className={[
                                                    'h-8 min-w-[32px] rounded-md px-2 font-medium transition-colors',
                                                    link.active
                                                        ? 'bg-[#18181B] font-bold text-white'
                                                        : link.url
                                                          ? 'text-[#201F1D] hover:bg-[#F1EFEC]'
                                                          : 'cursor-not-allowed opacity-40',
                                                ].join(' ')}
                                            />
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    )}
                </Card>
            </div>
        </AlmacenLayout>
    );
}
