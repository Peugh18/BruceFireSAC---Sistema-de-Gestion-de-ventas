import type { Paso } from './pasos';

const etapas: Record<string, number> = {
    borrador: 0,
    emitida: 1,
    pendiente: 1,
    enviada: 1,
    aceptada: 2,
    convertida: 3,
};

/**
 * Hitos de una cotización que sigue su camino normal. Rechazada, vencida y
 * anulada son salidas, no etapas: se muestran solo con su etiqueta.
 */
export function pasosCotizacion(estado: string): Paso[] {
    const etapa = etapas[estado?.toLowerCase()];
    if (etapa === undefined) return [];

    return [
        { id: 'borrador', label: 'Borrador' },
        { id: 'enviada', label: 'Enviada' },
        { id: 'aceptada', label: 'Aceptada' },
        { id: 'vendida', label: 'Vendida' },
    ].map((paso, indice) => ({
        ...paso,
        estado:
            indice < etapa || etapa === 3
                ? 'completado'
                : indice === etapa
                  ? 'actual'
                  : 'pendiente',
    }));
}
