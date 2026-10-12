import type { Paso } from './pasos';

const etapas: Record<string, number> = {
    pendiente_recepcion: 0,
    recibido_planta: 0,
    en_revision: 0,
    esperando_autorizacion: 0,
    autorizado: 1,
    en_proceso: 1,
    trabajo_terminado: 1,
    pendiente_datos: 1,
    datos_completos: 1,
    listo_certificado: 2,
    listo_entrega: 2,
    entregado: 2,
    cerrado: 3,
};

/** Representación de los hitos existentes; la asignación depende del responsable real. */
export function pasosOrdenAtencion(
    estado: string,
    tieneTecnico: boolean,
): Paso[] {
    const etapa = etapas[estado];
    if (etapa === undefined) return [];
    const pasos: Paso[] = [
        {
            id: 'asignada',
            label: tieneTecnico ? 'Asignada' : 'Por asignar',
            estado: tieneTecnico ? 'completado' : 'pendiente',
        },
        { id: 'en_proceso', label: 'En proceso', estado: 'pendiente' },
        { id: 'completada', label: 'Completada', estado: 'pendiente' },
        { id: 'cerrada', label: 'Cerrada', estado: 'pendiente' },
    ];
    if (etapa === 0 && tieneTecnico) pasos[0].estado = 'actual';
    for (let indice = 1; indice < pasos.length; indice++) {
        pasos[indice].estado =
            indice < etapa || estado === 'cerrado'
                ? 'completado'
                : indice === etapa
                  ? 'actual'
                  : 'pendiente';
    }
    return pasos;
}

export function pasosTaller(estado: string, tieneCertificado: boolean): Paso[] {
    const etapa =
        estado === 'anulada'
            ? undefined
            : {
                  pendiente_recepcion: 0,
                  recibido_planta: 0,
                  en_revision: 0,
                  esperando_autorizacion: 0,
                  autorizado: 1,
                  en_proceso: 1,
                  trabajo_terminado: 2,
                  pendiente_datos: 2,
                  datos_completos: 2,
                  listo_certificado: 3,
                  listo_entrega: 3,
                  entregado: 3,
                  cerrado: 3,
              }[estado];
    if (etapa === undefined) return [];
    return ['Taller', 'En proceso', 'Terminado', 'Certificado'].map(
        (label, indice) => ({
            id: label,
            label,
            estado:
                indice === 3
                    ? tieneCertificado
                        ? 'completado'
                        : 'pendiente'
                    : indice < etapa
                      ? 'completado'
                      : indice === etapa
                        ? 'actual'
                        : 'pendiente',
        }),
    );
}

/** Entrega en sitio: sale de taller, se entrega con acta y se cierra. */
export function pasosEntrega(estado: string): Paso[] {
    const etapa = { listo_entrega: 0, entregado: 1, cerrado: 2 }[estado];
    if (etapa === undefined) return [];
    return ['Por entregar', 'Entregada', 'Cerrada'].map((label, indice) => ({
        id: label,
        label,
        estado:
            indice < etapa || estado === 'cerrado'
                ? 'completado'
                : indice === etapa
                  ? 'actual'
                  : 'pendiente',
    }));
}
