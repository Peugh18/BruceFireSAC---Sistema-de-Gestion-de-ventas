import { useEffect, useRef } from 'react';

/**
 * Búsqueda que se aplica sola mientras la persona escribe: espera `retraso`
 * ms desde la última tecla y entonces llama a `aplicar`. Devuelve el
 * manejador del formulario para que Enter busque al instante.
 *
 * No hace nada en la primera carga ni cuando el texto no cambió, así no se
 * repite una consulta que ya está en pantalla.
 */
export function useBusquedaDinamica(
    valor: string,
    aplicar: () => void,
    retraso = 350,
): (evento?: { preventDefault: () => void }) => void {
    const ultima = useRef(valor.trim());
    const temporizador = useRef<ReturnType<typeof setTimeout> | undefined>(
        undefined,
    );
    const aplicarActual = useRef(aplicar);

    useEffect(() => {
        aplicarActual.current = aplicar;
    });

    useEffect(() => {
        if (valor.trim() === ultima.current) {
            return;
        }

        temporizador.current = setTimeout(() => {
            ultima.current = valor.trim();
            aplicarActual.current();
        }, retraso);

        return () => clearTimeout(temporizador.current);
    }, [valor, retraso]);

    return (evento) => {
        evento?.preventDefault();
        clearTimeout(temporizador.current);
        ultima.current = valor.trim();
        aplicarActual.current();
    };
}
