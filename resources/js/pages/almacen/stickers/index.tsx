import { Head, Link, router, usePage } from '@inertiajs/react';
import { Barcode, Building2, FileText, Printer } from 'lucide-react';
import { useState } from 'react';

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

    const [inicio, setInicio] = useState('1');

    return (
        <AlmacenLayout title="Stickers de Barras">
            <Head title="Stickers de Barras - Almacén" />

            <div className="flex flex-col gap-6">
                <div>
                    <h1 className="text-foreground text-xl font-bold">
                        Stickers de Código de Barras
                    </h1>
                    <p className="text-muted-foreground text-xs">
                        Recepciones con unidades serializadas listas para
                        imprimir su hoja de stickers. El PDF se genera por
                        recepción, con las unidades conformes que ingresaron en
                        ese lote.
                    </p>
                </div>

                <Card className="border-border bg-card rounded-[16px] p-5 shadow-none">
                    <div className="grid gap-4 text-xs sm:grid-cols-2">
                        <div>
                            <label
                                htmlFor="sticker-inicio"
                                className="text-foreground/80 block font-semibold"
                            >
                                Empezar en la posición (1 a 20)
                            </label>
                            <input
                                id="sticker-inicio"
                                type="number"
                                min={1}
                                max={20}
                                value={inicio}
                                onChange={(e) => setInicio(e.target.value)}
                                className="border-border mt-1 w-24 rounded-lg border px-3 py-1.5"
                            />
                            <p className="text-muted-foreground mt-1">
                                La hoja A4 trae 20 stickers de 5 × 5 cm. Si ya
                                usaste los primeros, indica desde cuál seguir.
                            </p>
                        </div>
                        <form
                            method="get"
                            action={stickersRutas.unidad.url({
                                current_team: teamSlug,
                            })}
                            target="_blank"
                        >
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
                                <input
                                    type="hidden"
                                    name="inicio"
                                    value={inicio}
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
                            title="Aún no hay recepciones con unidades serializadas para imprimir stickers."
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <Table className="w-full text-left text-[12.5px]">
                                <TableHeader>
                                    <TableRow className="border-border text-muted-foreground border-b text-[11px] font-bold tracking-wider uppercase">
                                        <TableHead>N° Doc.</TableHead>
                                        <TableHead>Fecha</TableHead>
                                        <TableHead>Proveedor</TableHead>
                                        <TableHead>Sede Almacén</TableHead>
                                        <TableHead className="text-center">
                                            Unidades Serializadas
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
                                            <TableCell className="text-foreground/80 px-3 py-3 font-mono text-[11.5px] whitespace-nowrap">
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
                                                {rec.unidades_count}
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
                                                                    inicio,
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
