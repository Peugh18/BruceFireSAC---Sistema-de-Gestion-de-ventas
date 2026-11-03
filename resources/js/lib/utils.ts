import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

/**
 * "2026-09-27" (o con hora) → "27/09/2026". Deja igual lo que no sea fecha.
 */
export function fechaCorta(valor: string | null | undefined): string {
    const coincide = valor?.match(/^(\d{4})-(\d{2})-(\d{2})/);

    return coincide
        ? `${coincide[3]}/${coincide[2]}/${coincide[1]}`
        : (valor ?? '');
}

/**
 * 1234.5 → "S/ 1,234.50". El espacio no se corta, así el monto no se parte
 * en dos líneas dentro de una tabla angosta.
 */
export function soles(monto: number | string | null | undefined): string {
    const leido = Number(monto ?? 0);
    // Un -0.001 se vería "-S/ 0.00" en el servidor y "S/ 0.00" en el navegador:
    // React rompería la hidratación. El cero no tiene signo.
    const numero = Math.round(Math.abs(leido) * 100) === 0 ? 0 : leido;

    return new Intl.NumberFormat('es-PE', {
        style: 'currency',
        currency: 'PEN',
    })
        .format(Number.isFinite(numero) ? numero : 0)
        .replace(/\s/u, '\u00a0');
}

/**
 * Fecha "YYYY-MM-DD" en la hora del equipo (Perú), no en UTC: con
 * toISOString() una venta hecha después de las 19:00 quedaba con la fecha
 * de mañana.
 */
export function fechaLocal(fecha: Date = new Date()): string {
    return [
        fecha.getFullYear(),
        String(fecha.getMonth() + 1).padStart(2, '0'),
        String(fecha.getDate()).padStart(2, '0'),
    ].join('-');
}

export type NoGravados = { productos: number[]; servicios: number[] };

/**
 * Separa base e IGV de líneas con IGV incluido, como el comprobante: suma por
 * producto y precio, y lo exonerado o inafecto no lleva IGV.
 */
export function desglosarIgv(
    lineas: {
        product_id?: number | null;
        service_id?: number | null;
        cantidad: number;
        precio_unitario: number;
        descuento: number;
    }[],
    noGravados: NoGravados,
) {
    const grupos = new Map<string, { monto: number; gravado: boolean }>();

    for (const linea of lineas) {
        const clave = `${linea.product_id ?? ''}|${linea.service_id ?? ''}|${linea.precio_unitario}`;
        const gravado = !(
            (linea.product_id != null &&
                noGravados.productos.includes(linea.product_id)) ||
            (linea.service_id != null &&
                noGravados.servicios.includes(linea.service_id))
        );
        const monto =
            Math.round(
                (linea.cantidad * linea.precio_unitario - linea.descuento) *
                    100,
            ) / 100;
        grupos.set(clave, {
            monto: (grupos.get(clave)?.monto ?? 0) + monto,
            gravado,
        });
    }

    let base = 0;
    let total = 0;
    for (const { monto, gravado } of grupos.values()) {
        base += gravado ? Math.round((monto / 1.18) * 100) / 100 : monto;
        total += monto;
    }

    return {
        base: Math.round(base * 100) / 100,
        igv: Math.round((total - base) * 100) / 100,
        total: Math.round(total * 100) / 100,
    };
}

/** Suma días a una fecha "YYYY-MM-DD" sin salir de la hora local. */
export function sumarDias(fecha: string, dias: number): string {
    const d = new Date(`${fecha}T00:00:00`);
    d.setDate(d.getDate() + dias);

    return fechaLocal(d);
}

/** "2026-09-27T10:30:00" con hora, en formato de Perú; sin fecha, una raya. */
export function fechaHora(valor: string | null | undefined): string {
    if (!valor) {
        return '—';
    }

    return new Date(valor).toLocaleString('es-PE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

/** "Clínica San Pablo" → "CS"; sin nombre, "CL". */
export function iniciales(valor: string | null | undefined): string {
    return (
        valor
            ?.split(/\s+/u)
            .filter(Boolean)
            .slice(0, 2)
            .map((parte) => parte[0]?.toUpperCase() ?? '')
            .join('') || 'CL'
    );
}

/** Etiqueta de paginación de Laravel: "&laquo; Anterior" → "< Anterior". */
export function etiquetaPaginacion(etiqueta: string): string {
    return etiqueta.replace('&laquo;', '<').replace('&raquo;', '>');
}

/** Ruta sin consulta, ancla ni barra final. */
export function normalizePath(ruta: string): string {
    const sinConsulta = ruta.split('?')[0].split('#')[0];
    const sinBarraFinal = sinConsulta.replace(/\/+$/u, '');

    return sinBarraFinal === '' ? '/' : sinBarraFinal;
}

const NOMBRES_DOCUMENTO: Record<string, string> = {
    factura: 'Factura',
    boleta: 'Boleta',
    nota_credito: 'Nota de crédito',
    nota_debito: 'Nota de débito',
};

/** "nota_credito" → "Nota de crédito". */
export function nombreDocumento(tipo: string | null | undefined): string {
    return NOMBRES_DOCUMENTO[tipo ?? ''] ?? tipo ?? '';
}

export function hace(fecha: string | null): string {
    if (!fecha) {
        return '';
    }

    const minutos = Math.max(
        0,
        Math.round((Date.now() - new Date(fecha).getTime()) / 60000),
    );

    if (minutos < 60) {
        return `hace ${minutos} min`;
    }

    const horas = Math.round(minutos / 60);

    return horas < 24 ? `hace ${horas} h` : `hace ${Math.round(horas / 24)} d`;
}
