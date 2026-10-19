import { Head, Link, router, usePage } from '@inertiajs/react';
import { Barcode, Building2, FileText, Printer } from 'lucide-react';
import { useState } from 'react';

import SelectorFormatoStickers, {
    type FormatoStickers,
} from '@/components/formato-stickers';
import { Card } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AlmacenLayout from '@/layouts/almacen-layout';
import recepciones from '@/routes/almacen/recepciones';
import stickersRutas from '@/routes/almacen/stickers';
import type { Team } from '@/types';

export type StickerReceptionRow = {
    id: number;
    proveedor: string;
    fecha: string;
    sede_almacen: { id: number; nombre: string } | null;
    unidades_count: number;
    productos_count: number;
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
    const [formato, setFormato] = useState<FormatoStickers>('rollo');
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
                    <h1 className="text-foreground text-xl font-bold">
                        Stickers de Código de Barras
                    </h1>
                    <p className="text-muted-foreground text-xs">
                        Imprime una etiqueta por extintor y una por producto
                        distinto recibido por cantidad. El código PRD identifica
                        el producto, sin reemplazar su código de fábrica.
                    </p>
                    <p className="text-muted-foreground mt-2 text-xs">
                        Etiquetas de 80 × 50 mm: una por corte en rollo o diez
                        por hoja A4. Imprime el PDF a tamaño real (100 %).
                    </p>
                </div>

                <SelectorFormatoStickers
                    value={formato}
                    onChange={setFormato}
                />

                <Card className="border-border bg-card rounded-2xl p-5 shadow-none">
                    <form
                        method="get"
                        action={stickersRutas.producto.url({
                            current_team: teamSlug,
                        })}
                        target="_blank"
                        className="mb-5 space-y-2"
                    >
                        <input type="hidden" name="formato" value={formato} />
                        <label
                            htmlFor="sticker-producto"
                            className="text-sm font-semibold"
                        >
                            Reimprimir etiqueta de producto sin serie
                        </label>
                        <div className="flex flex-wrap gap-2">
                            <input
                                id="sticker-producto"
                                name="codigo"
                                required
                                placeholder="PRD-000001"
                                className="border-border bg-background min-h-11 rounded-lg border px-3 font-mono text-sm"
                            />
                            <button
                                type="submit"
                                className="bg-foreground text-background min-h-11 rounded-lg px-3 text-sm"
                            >
                                Imprimir producto
                            </button>
                        </div>
                    </form>
                    <div className="max-w-xl text-xs">
                        <form
                            method="get"
                            action={stickersRutas.unidad.url({
                                current_team: teamSlug,
                            })}
                            target="_blank"
                        >
                            <input
                                type="hidden"
                                name="formato"
                                value={formato}
                            />
                            <label
                                htmlFor="sticker-serie"
                                className="text-foreground/80 block font-semibold"
                            >
                                Reimprimir un sticker
                            </label>
                            <div className="mt-1 flex items-center gap-2">
                                <input
                                    id="sticker-serie"
                                    name="serie"
                                    required
                                    placeholder="BF-EQ-000001"
                                    className="border-border rounded-lg border px-3 py-1.5 font-mono"
                                />
                                <button
                                    type="submit"
                                    className="bg-foreground text-background rounded-md px-3 py-1.5 font-bold transition hover:brightness-95"
                                >
                                    Imprimir
                                </button>
                            </div>
                        </form>
                    </div>

                    {receptions.data.length === 0 ? (
                        <Empty
                            icon={<Barcode />}
                            title="Aún no hay recepciones para imprimir stickers."
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <Table className="w-full text-left text-[12.5px]">
                                <TableHeader>
                                    <TableRow className="border-border text-muted-foreground border-b text-xs font-bold tracking-wider uppercase">
                                        <TableHead>N° Doc.</TableHead>
                                        <TableHead>Fecha</TableHead>
                                        <TableHead>Proveedor</TableHead>
                                        <TableHead>Sede Almacén</TableHead>
                                        <TableHead className="text-center">
                                            Etiquetas
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Acción
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody className="divide-border divide-y">
                                    {receptions.data.map((rec) => (
                                        <TableRow
                                            key={rec.id}
                                            className="hover:bg-muted/40 transition-colors"
                                        >
                                            <TableCell className="text-foreground py-3 pr-3 font-mono font-bold">
                                                #{rec.id}
                                            </TableCell>
                                            <TableCell className="text-foreground/80 px-3 py-3 font-mono text-xs whitespace-nowrap">
                                                {formatDate(rec.fecha)}
                                            </TableCell>
                                            <TableCell className="text-foreground px-4 py-3 font-bold">
                                                {rec.proveedor}
                                            </TableCell>
                                            <TableCell className="text-foreground/80 px-3 py-3">
                                                <div className="flex items-center gap-1.5">
                                                    <Building2 className="text-muted-foreground size-3.5" />
                                                    <span>
                                                        {rec.sede_almacen
                                                            ?.nombre ?? '—'}
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-foreground px-3 py-3 text-center font-mono font-bold">
                                                {rec.unidades_count +
                                                    rec.productos_count}
                                            </TableCell>
                                            <TableCell className="py-3 pl-4 text-right">
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
                                                        className="border-border bg-card text-foreground hover:bg-background inline-flex items-center gap-1 rounded-md border px-2.5 py-1 text-xs font-bold"
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
                                                            {
                                                                query: {
                                                                    formato,
                                                                },
                                                            },
                                                        )}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="bg-foreground text-background hover:bg-foreground/90 inline-flex items-center gap-1 rounded-md px-2.5 py-1 text-xs font-bold shadow-xs transition-colors"
                                                    >
                                                        <Printer className="size-3.5" />
                                                        <span>Imprimir</span>
                                                    </a>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>

                            {receptions.links.length > 3 && (
                                <div className="border-border text-muted-foreground mt-4 flex items-center justify-between border-t pt-4 text-xs">
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
                                                        ? 'bg-foreground text-background font-bold shadow-xs'
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
