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
        (typeof window !== 'undefined'
            ? window.location.pathname.split('/')[1]
            : '');

    return (
        <AlmacenLayout title="Stickers de Barras">
            <Head title="Stickers de Barras - Almacén" />

            <div className="flex flex-col gap-6">
                <div>
                    <h1 className="text-xl font-bold text-foreground">
                        Stickers de Código de Barras
                    </h1>
                    <p className="text-xs text-muted-foreground">
                        Recepciones con unidades serializadas listas para
                        imprimir su hoja de stickers. El PDF se genera por
                        recepción, con las unidades conformes que ingresaron en
                        ese lote.
                    </p>
                </div>

                <Card className="rounded-[16px] border-border bg-card p-5 shadow-none">
                    {receptions.data.length === 0 ? (
                        <div className="flex min-h-[260px] flex-col items-center justify-center text-center">
                            <Barcode className="size-10 text-muted-foreground" />
                            <p className="mt-2 text-sm font-medium text-muted-foreground">
                                Aún no hay recepciones con unidades serializadas
                                para imprimir stickers.
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-[12.5px]">
                                <thead>
                                    <tr className="border-b border-border text-[11px] font-bold tracking-wider text-muted-foreground uppercase">
                                        <th className="py-2.5 pr-3">N° Doc.</th>
                                        <th className="px-3 py-2.5">Fecha</th>
                                        <th className="px-4 py-2.5">
                                            Proveedor
                                        </th>
                                        <th className="px-3 py-2.5">
                                            Sede Almacén
                                        </th>
                                        <th className="px-3 py-2.5 text-center">
                                            Unidades Serializadas
                                        </th>
                                        <th className="py-2.5 pl-4 text-right">
                                            Acción
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {receptions.data.map((rec) => (
                                        <tr
                                            key={rec.id}
                                            className="transition-colors hover:bg-muted/40"
                                        >
                                            <td className="py-3 pr-3 font-mono font-bold text-foreground">
                                                #{rec.id}
                                            </td>
                                            <td className="px-3 py-3 font-mono text-[11.5px] whitespace-nowrap text-foreground/80">
                                                {formatDate(rec.fecha)}
                                            </td>
                                            <td className="px-4 py-3 font-bold text-foreground">
                                                {rec.proveedor}
                                            </td>
                                            <td className="px-3 py-3 text-foreground/80">
                                                <div className="flex items-center gap-1.5">
                                                    <Building2 className="size-3.5 text-muted-foreground" />
                                                    <span>
                                                        {rec.sede_almacen
                                                            ?.nombre ?? '—'}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="px-3 py-3 text-center font-mono font-bold text-foreground">
                                                {rec.unidades_count}
                                            </td>
                                            <td className="py-3 pl-4 text-right">
                                                <div className="flex items-center justify-end gap-2">
                                                    <Link
                                                        href={recepciones.show.url(
                                                            {
                                                                current_team:
                                                                    teamSlug,
                                                                reception:
                                                                    rec.id,
                                                            },
                                                        )}
                                                        className="inline-flex items-center gap-1 rounded-md border border-border bg-card px-2.5 py-1 text-xs font-bold text-foreground hover:bg-background"
                                                    >
                                                        <FileText className="size-3.5" />
                                                        <span>Detalle</span>
                                                    </Link>
                                                    <a
                                                        href={recepciones.stickers.url(
                                                            {
                                                                current_team:
                                                                    teamSlug,
                                                                reception:
                                                                    rec.id,
                                                            },
                                                        )}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="inline-flex items-center gap-1 rounded-md bg-card px-2.5 py-1 text-xs font-bold text-white hover:bg-foreground/90"
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
                                <div className="mt-4 flex items-center justify-between border-t border-border pt-4 text-xs text-muted-foreground">
                                    <span>
                                        Mostrando{' '}
                                        <b>{receptions.data.length}</b> de{' '}
                                        <b>{receptions.total}</b> recepciones
                                    </span>
                                    <div className="flex items-center gap-1">
                                        {receptions.links.map((link, idx) => (
                                            <button
                                                key={idx}
                                                type="button"
                                                disabled={!link.url}
                                                onClick={() => {
                                                    if (link.url) {
                                                        router.get(
                                                            link.url,
                                                            {},
                                                            {
                                                                preserveState: true,
                                                                preserveScroll: true,
                                                            },
                                                        );
                                                    }
                                                }}
                                                dangerouslySetInnerHTML={{
                                                    __html: link.label,
                                                }}
                                                className={[
                                                    'h-8 min-w-[32px] rounded-md px-2 font-medium transition-colors',
                                                    link.active
                                                        ? 'bg-card font-bold text-white'
                                                        : link.url
                                                          ? 'text-foreground hover:bg-muted'
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
