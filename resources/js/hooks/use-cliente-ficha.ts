import { useState, useEffect } from 'react';
import { toast } from 'sonner';

import type { ClientFicha } from '@/components/client-picker';
import clientes from '@/routes/vendedor/clientes';

type Opciones = {
    /** Ficha inicial mientras se carga (p. ej. la que ya trae la página). */
    inicial?: ClientFicha | null;
    /** Aviso si falla la carga; sin él, el fallo queda en silencio. */
    avisoError?: string;
};

/**
 * Carga la ficha del cliente (sedes, vehículos y datos fiscales) que
 * completan los selectores de cliente. Un único punto de acceso a la ruta
 * `clientes.ficha`, compartido por ventas, certificados y servicios.
 */
export function useClienteFicha(
    teamSlug: string,
    clienteId: number | null | undefined,
    { inicial = null, avisoError }: Opciones = {},
): [ClientFicha | null, (ficha: ClientFicha | null) => void] {
    const [ficha, setFicha] = useState<ClientFicha | null>(inicial);

    useEffect(() => {
        if (!clienteId) {
            return;
        }

        void fetch(
            clientes.ficha.url({ current_team: teamSlug, client: clienteId }),
        )
            .then((r) => (r.ok ? r.json() : null))
            .then((cargada: ClientFicha | null) => {
                if (cargada) {
                    setFicha(cargada);
                }
            })
            .catch(() => {
                if (avisoError) {
                    toast.error(avisoError);
                }
            });
        // La ficha se carga una sola vez, como en las pantallas originales.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [clienteId]);

    return [ficha, (nueva) => setFicha(nueva)];
}

export default useClienteFicha;
