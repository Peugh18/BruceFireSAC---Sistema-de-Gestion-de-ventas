export type ReglasDetraccion = {
    monto_minimo: number;
    porcentaje: number;
    cuenta_bn: string;
    servicios: {
        id: number;
        codigo: string | null;
        nombre: string;
        afectacion: string | null;
        /** Porcentaje vigente de su código (catálogo 54); null si no tiene. */
        porcentaje?: number | null;
        /** no_sujeto | pendiente | sujeto; null en servicios anteriores. */
        estado?: string | null;
    }[];
};

type Linea = { service_id?: number | null; product_id?: number | null };
type Estado = 'no_aplica' | 'bajo_minimo' | 'revisar' | 'sin_cuenta' | 'aplica';

/** Estimación para avisar antes de guardar. El servidor valida la operación al emitir. */
export function resumenDetraccion(
    comprobante: string,
    total: number,
    items: Linea[],
    reglas: ReglasDetraccion,
): { estado: Estado; monto: number; neto: number; nombre: string } {
    const sinDetraccion = { monto: 0, neto: total, nombre: '' };
    if (comprobante !== 'factura')
        return { ...sinDetraccion, estado: 'no_aplica' };

    const lineas = items.filter((item) => item.service_id != null);
    if (lineas.length === 0) return { ...sinDetraccion, estado: 'no_aplica' };
    if (total <= reglas.monto_minimo)
        return { ...sinDetraccion, estado: 'bajo_minimo' };

    const servicios = lineas.map((item) =>
        reglas.servicios.find((s) => s.id === item.service_id),
    );
    if (
        servicios.some(
            (s) =>
                !s ||
                !['10', '20', '30'].includes(s.afectacion ?? '') ||
                s.estado === 'pendiente',
        )
    ) {
        return { ...sinDetraccion, estado: 'revisar' };
    }
    // Un servicio revisado como "no sujeto" no entra en la detracción.
    const sujetos = servicios.filter((s) => s?.estado !== 'no_sujeto');
    const gravados = sujetos.filter((s) => s?.afectacion === '10');
    if (gravados.length === 0) return { ...sinDetraccion, estado: 'no_aplica' };
    if (
        gravados.length !== sujetos.length ||
        gravados.some((s) => !s?.codigo)
    ) {
        return { ...sinDetraccion, estado: 'revisar' };
    }
    const codigos = new Set(gravados.map((s) => s?.codigo));
    if (codigos.size !== 1) return { ...sinDetraccion, estado: 'revisar' };

    const porcentaje = gravados[0]?.porcentaje ?? reglas.porcentaje;
    const monto = Math.round(total * porcentaje + Number.EPSILON) / 100;
    return {
        estado: reglas.cuenta_bn.trim() ? 'aplica' : 'sin_cuenta',
        monto,
        neto: Math.round((total - monto) * 100) / 100,
        nombre: gravados[0]?.nombre ?? '',
    };
}
