import { useEffect, useState } from 'react';

import { Cargando } from '@/components/cargando';
import type { CatalogItem, CatalogUnit } from '@/components/catalog-picker';
import {
    UNIDADES_DIALOG_CLASES,
    UNIDADES_TITULO_CLASES,
    UnidadResumen,
    useUnidadesDeProducto,
} from '@/components/unidades-compartidas';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

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
    const unidades = useUnidadesDeProducto({
        teamSlug,
        productId: producto?.id ?? null,
        sedeId,
        excluir,
    });
    const [marcadas, setMarcadas] = useState<number[]>([]);

    // Al cambiar de producto, la selección empieza de cero.
    useEffect(() => {
        setMarcadas([]);
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
            <DialogContent className={UNIDADES_DIALOG_CLASES}>
                <DialogHeader>
                    <DialogTitle className={UNIDADES_TITULO_CLASES}>
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
                        <Cargando className="text-muted-foreground size-5" />
                    </div>
                ) : unidades.length === 0 ? (
                    <p className="text-muted-foreground py-6 text-center text-[13px]">
                        No hay unidades disponibles de este producto en tu
                        almacén.
                    </p>
                ) : (
                    <div className="border-border overflow-hidden rounded-[10px] border">
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
                            className="border-border bg-muted/50 text-foreground/80 w-full border-b px-3 py-2 text-left text-[11.5px] font-bold"
                        >
                            {marcadas.length === unidades.length
                                ? 'Desmarcar todas'
                                : `Marcar todas (${unidades.length})`}
                        </button>
                        {unidades.map((unidad) => (
                            <label
                                key={unidad.inventory_unit_id}
                                className="border-border hover:bg-muted/40 flex cursor-pointer items-center gap-3 border-b px-3 py-2 last:border-b-0"
                            >
                                <input
                                    type="checkbox"
                                    checked={marcadas.includes(
                                        unidad.inventory_unit_id,
                                    )}
                                    onChange={() =>
                                        alternar(unidad.inventory_unit_id)
                                    }
                                    className="accent-primary size-4"
                                />
                                <UnidadResumen unidad={unidad} />
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
                        className="bg-primary hover:bg-primary/90 rounded-[9px] font-bold text-white shadow-none"
                    >
                        Agregar {marcadas.length || ''}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
