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
    const numero = Number(monto ?? 0);

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
