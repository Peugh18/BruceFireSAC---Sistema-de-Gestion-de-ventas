import { useEffect, useState } from 'react';

import { Cargando } from '@/components/cargando';
import type { CatalogItem, CatalogUnit } from '@/components/catalog-picker';
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

/**
 * Al elegir por nombre un producto con serie, muestra sus unidades
 * disponibles en el almacén de la sede para marcar cuáles se venden.
 */
export default function UnidadesDialog({
    teamSlug,
    sedeId,
    producto,
    excluir,
    onClose,
    onAgregar,
}: {
    teamSlug: string;
    sedeId: number | '';
    producto: CatalogItem | null;
    excluir: string[];
    onClose: () => void;
    onAgregar: (unidades: CatalogUnit[]) => void;
}) {
    const [unidades, setUnidades] = useState<CatalogUnit[] | null>(null);
    const [marcadas, setMarcadas] = useState<number[]>([]);

    useEffect(() => {
        if (!producto) {
            return;
        }

        setUnidades(null);
        setMarcadas([]);
        void fetch(
            catalogo.unidades.url(teamSlug, {
                query: {
                    product_id: producto.id,
                    sede_id: sedeId || undefined,
                },
            }),
        )
            .then((r) => r.json())
            .then((data: CatalogUnit[]) =>
                setUnidades(
                    data.filter((u) => !excluir.includes(u.numero_serie)),
                ),
            )
            .catch(() => setUnidades([]));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [producto]);

    const alternar = (id: number) =>
        setMarcadas((actual) =>
            actual.includes(id)
                ? actual.filter((x) => x !== id)
                : [...actual, id],
        );

    return (
        <Dialog
            open={producto !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="max-h-[85vh] overflow-y-auto rounded-[16px] border-border bg-card sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle className="font-['Oswald',sans-serif] text-[19px] font-semibold uppercase">
                        {producto?.nombre}
                    </DialogTitle>
                    <DialogDescription className="text-[12.5px]">
                        Marca las unidades que se llevan. La serie queda en el
                        control interno; en el comprobante sale solo el
                        producto.
                    </DialogDescription>
                </DialogHeader>

                {unidades === null ? (
                    <div className="flex justify-center py-8">
                        <Cargando className="size-5 text-muted-foreground" />
                    </div>
                ) : unidades.length === 0 ? (
                    <p className="py-6 text-center text-[13px] text-muted-foreground">
                        No hay unidades disponibles de este producto en tu
                        almacén.
                    </p>
                ) : (
                    <div className="overflow-hidden rounded-[10px] border border-border">
                        <button
                            type="button"
                            onClick={() =>
                                setMarcadas(
                                    marcadas.length === unidades.length
                                        ? []
                                        : unidades.map(
                                              (u) => u.inventory_unit_id,
                                          ),
                                )
                            }
                            className="w-full border-b border-border bg-muted/50 px-3 py-2 text-left text-[11.5px] font-bold text-foreground/80"
                        >
                            {marcadas.length === unidades.length
                                ? 'Desmarcar todas'
                                : `Marcar todas (${unidades.length})`}
                        </button>
                        {unidades.map((unidad) => (
                            <label
                                key={unidad.inventory_unit_id}
                                className="flex cursor-pointer items-center gap-3 border-b border-border px-3 py-2 last:border-b-0 hover:bg-muted/40"
                            >
                                <input
                                    type="checkbox"
                                    checked={marcadas.includes(
                                        unidad.inventory_unit_id,
                                    )}
                                    onChange={() =>
                                        alternar(unidad.inventory_unit_id)
                                    }
                                    className="size-4 accent-primary"
                                />
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
                            </label>
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
                        Cancelar
                    </Button>
                    <Button
                        type="button"
                        disabled={marcadas.length === 0}
                        onClick={() => {
                            onAgregar(
                                (unidades ?? []).filter((u) =>
                                    marcadas.includes(u.inventory_unit_id),
                                ),
                            );
                            onClose();
                        }}
                        className="rounded-[9px] bg-primary font-bold text-white shadow-none hover:bg-primary/90"
                    >
                        Agregar {marcadas.length || ''}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
