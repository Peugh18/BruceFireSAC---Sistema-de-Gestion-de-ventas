const ETIQUETAS: Record<string, string> = {
    pendiente_recepcion: 'Por recibir',
    recibido_planta: 'En taller',
    en_revision: 'En revisión',
    esperando_autorizacion: 'Esperando autorización',
    autorizado: 'Autorizado',
    en_proceso: 'En proceso',
    trabajo_terminado: 'Trabajo terminado',
    pendiente_datos: 'Faltan datos',
    datos_completos: 'Datos completos',
    listo_certificado: 'Certificado listo',
    listo_entrega: 'Lista para entregar',
    entregado: 'Entregada',
    cerrado: 'Cerrada',
    anulada: 'Anulada',
};

/** El estado de una orden dicho en español claro, nunca el código interno. */
export function etiquetaEstadoOrden(estado: string): string {
    return ETIQUETAS[estado] ?? estado.replace(/_/g, ' ');
}
