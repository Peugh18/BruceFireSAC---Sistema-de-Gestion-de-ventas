import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/**
 * Indica si la página actual se está recargando a sí misma (filtros,
 * paginación, elegir un registro), para mostrar skeletons en vez de datos
 * viejos. Solo se enciende si la respuesta tarda más que `retraso`, así una
 * carga instantánea no parpadea. Ignora prefetch, recargas parciales y
 * visitas sin barra de progreso (polling).
 */
export function useRecargando(retraso = 200): boolean {
    const [recargando, setRecargando] = useState(false);

    useEffect(() => {
        let temporizador: ReturnType<typeof setTimeout> | undefined;

        const quitarInicio = router.on('start', (event) => {
            const visita = event.detail.visit;
            const mismaPagina =
                visita.method === 'get' &&
                visita.url.pathname === window.location.pathname;

            if (
                !mismaPagina ||
                visita.prefetch ||
                visita.async ||
                visita.only.length > 0 ||
                !visita.showProgress
            ) {
                return;
            }

            clearTimeout(temporizador);
            temporizador = setTimeout(() => setRecargando(true), retraso);
        });

        const quitarFin = router.on('finish', () => {
            clearTimeout(temporizador);
            setRecargando(false);
        });

        return () => {
            quitarInicio();
            quitarFin();
            clearTimeout(temporizador);
        };
    }, [retraso]);

    return recargando;
}
