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
