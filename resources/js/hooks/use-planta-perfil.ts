import { createContext, useContext } from 'react';

export const PlantaPerfilContext = createContext<{
    abrirPerfil: () => void;
    contenedorPie: HTMLDivElement | null;
} | null>(null);

export function usePlantaPerfil() {
    const contexto = useContext(PlantaPerfilContext);

    if (!contexto) {
        throw new Error('El perfil de planta necesita TecnicoPlantaLayout.');
    }

    return contexto;
}
