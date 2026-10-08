import { useEffect, useRef, useState } from 'react';

import type { CatalogUnit } from '@/components/catalog-picker';
import catalogo from '@/routes/vendedor/catalogo';

/**
 * Piezas compartidas por los diálogos de unidades (elegir unidades de una
 * venta y cambiar el extintor de una línea): la carga de unidades desde la
 * API del catálogo y la fila de datos de cada unidad.
 */

/** Clases del marco de los diálogos de unidades (mismo aspecto en ambos). */
export const UNIDADES_DIALOG_CLASES =
    'border-border bg-card max-h-[85vh] overflow-y-auto rounded-[16px] sm:max-w-lg';

/** Clases del título de los diálogos de unidades. */
export const UNIDADES_TITULO_CLASES =
    "font-['Oswald',sans-serif] text-[19px] font-semibold uppercase";

/**
 * Carga las unidades disponibles de un producto en la sede del almacén.
 * Devuelve `null` mientras carga y `[]` si falla o no hay unidades.
 *
 * @param excluir series que no deben aparecer (ya están en la venta).
 */
export function useUnidadesDeProducto({
    teamSlug,
    productId,
    sedeId,
    excluir = [],
}: {
    teamSlug: string;
    productId: number | null;
    sedeId: number | '' | null;
    excluir?: readonly string[];
}): CatalogUnit[] | null {
    const [unidades, setUnidades] = useState<CatalogUnit[] | null>(null);
    const excluirRef = useRef(excluir);
    excluirRef.current = excluir;

    useEffect(() => {
        if (productId === null) {
            setUnidades(null);
            return;
        }

        setUnidades(null);
        void fetch(
            catalogo.unidades.url(teamSlug, {
                query: {
                    product_id: productId,
                    sede_id: sedeId || undefined,
                },
            }),
        )
            .then((r) => r.json())
            .then((data: CatalogUnit[]) =>
                setUnidades(
                    data.filter(
                        (unidad) =>
                            !excluirRef.current.includes(unidad.numero_serie),
                    ),
                ),
            )
            .catch(() => setUnidades([]));
    }, [productId, teamSlug, sedeId]);

    return unidades;
}

/** Serie y datos resumidos de una unidad (capacidad · marca · N° de serie). */
export function UnidadResumen({ unidad }: { unidad: CatalogUnit }) {
    return (
        <>
            <span className="font-['IBM_Plex_Mono',monospace] text-[12.5px] font-bold">
                {unidad.numero_serie}
            </span>
            <span className="text-muted-foreground text-[12px]">
                {[
                    unidad.capacidad,
                    unidad.marca,
                    unidad.serie_fabricante && `N° ${unidad.serie_fabricante}`,
                ]
                    .filter(Boolean)
                    .join(' · ')}
            </span>
        </>
    );
}
