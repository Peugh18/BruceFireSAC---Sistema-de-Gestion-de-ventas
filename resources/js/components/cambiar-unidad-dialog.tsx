import { router } from '@inertiajs/react';
import { Loader2, ScanLine } from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';

import type { CatalogUnit } from '@/components/catalog-picker';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import catalogo from '@/routes/vendedor/catalogo';
import ventas from '@/routes/vendedor/ventas';

export type LineaConSerie = {
    id: number;
    product_id: number;
    nombre: string;
    numero_serie: string;
};

/**
 * Cambia el extintor de una línea por otro del mismo producto (se escanea o
 * se elige de los disponibles). No necesita nota de crédito: el comprobante
 * no muestra la serie; el stock y los certificados se corrigen solos.
 */
export default function CambiarUnidadDialog({
    teamSlug,
    saleId,
    sedeId,
    linea,
    onClose,
}: {
    teamSlug: string;
    saleId: number;
    sedeId: number | null;
    linea: LineaConSerie | null;
    onClose: () => void;
}) {
    const [serie, setSerie] = useState('');
    const [disponibles, setDisponibles] = useState<CatalogUnit[] | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [guardando, setGuardando] = useState(false);

    useEffect(() => {
        if (!linea) {
            return;
        }

        setSerie('');
        setError(null);
        setDisponibles(null);
        void fetch(
            catalogo.unidades.url(teamSlug, {
                query: {
                    product_id: linea.product_id,
                    sede_id: sedeId ?? undefined,
                },
            }),
        )
            .then((r) => r.json())
            .then((data: CatalogUnit[]) => setDisponibles(data))
            .catch(() => setDisponibles([]));
    }, [linea, teamSlug, sedeId]);

    const cambiar = (numeroSerie: string, event?: FormEvent) => {
        event?.preventDefault();
        if (!linea || !numeroSerie.trim()) return;

        setGuardando(true);
        setError(null);
        router.post(
            ventas.cambiarUnidad.url({
                current_team: teamSlug,
                sale: saleId,
                item: linea.id,
            }),
            { numero_serie: numeroSerie.trim() },
            {
                preserveScroll: true,
                onSuccess: onClose,
                onError: (errors) =>
                    setError(
                        Object.values(errors)[0] ??
                            'No se pudo cambiar el extintor.',
                    ),
                onFinish: () => setGuardando(false),
            },
        );
    };

    return (
        <Dialog
            open={linea !== null}
            onOpenChange={(abierto) => !abierto && onClose()}
        >
            <DialogContent className="max-h-[85vh] overflow-y-auto rounded-[16px] border-border bg-card sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle className="font-['Oswald',sans-serif] text-[19px] font-semibold uppercase">
                        Cambiar extintor
                    </DialogTitle>
                    <DialogDescription className="text-[12.5px]">
                        {linea?.nombre}: sale la serie{' '}
                        <b className="font-['IBM_Plex_Mono',monospace]">
                            {linea?.numero_serie}
                        </b>
                        , que vuelve al stock. El comprobante no cambia y los
                        certificados se corrigen con el mismo número.
                    </DialogDescription>
                </DialogHeader>

                <form
                    onSubmit={(event) => cambiar(serie, event)}
                    className="flex gap-2"
                >
                    <div className="flex min-w-0 flex-1 items-center gap-2 rounded-[9px] border border-border bg-muted/40 px-3">
                        <ScanLine className="size-4 shrink-0 text-muted-foreground" />
                        <input
                            autoFocus
                            value={serie}
                            onChange={(e) => setSerie(e.target.value)}
                            placeholder="Escanea la serie correcta (BF-EQ-…)"
                            className="h-10 min-w-0 flex-1 bg-transparent text-[13px] outline-none"
                        />
                    </div>
                    <Button
                        type="submit"
                        disabled={guardando || !serie.trim()}
                        className="h-10 rounded-[9px] bg-primary text-white shadow-none hover:bg-primary/90"
                    >
                        {guardando ? (
                            <Loader2 className="size-4 animate-spin" />
                        ) : null}
                        Cambiar
                    </Button>
                </form>

                {error ? (
                    <p className="rounded-[9px] bg-destructive/10 px-3 py-2 text-[12px] font-semibold text-destructive">
                        {error}
                    </p>
                ) : null}

                <div className="text-[11px] font-bold text-foreground/80 uppercase">
                    O elige una disponible
                </div>
                {disponibles === null ? (
                    <div className="flex justify-center py-4">
                        <Loader2 className="size-5 animate-spin text-muted-foreground" />
                    </div>
                ) : disponibles.length === 0 ? (
                    <p className="text-[12.5px] text-muted-foreground">
                        No hay otras unidades de este producto en el almacén de
                        la sede.
                    </p>
                ) : (
                    <div className="max-h-[260px] overflow-y-auto rounded-[10px] border border-border">
                        {disponibles.map((unidad) => (
                            <button
                                key={unidad.inventory_unit_id}
                                type="button"
                                disabled={guardando}
                                onClick={() => cambiar(unidad.numero_serie)}
                                className="flex w-full items-center gap-3 border-b border-border px-3 py-2 text-left last:border-b-0 hover:bg-muted/40"
                            >
                                <span className="font-['IBM_Plex_Mono',monospace] text-[12.5px] font-bold">
                                    {unidad.numero_serie}
                                </span>
                                <span className="text-[12px] text-muted-foreground">
                                    {[
                                        unidad.capacidad,
                                        unidad.marca,
                                        unidad.serie_fabricante &&
                                            `N° ${unidad.serie_fabricante}`,
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </span>
                            </button>
                        ))}
                    </div>
                )}

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onClose}
                        className="rounded-[9px] shadow-none"
                    >
                        Cerrar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
