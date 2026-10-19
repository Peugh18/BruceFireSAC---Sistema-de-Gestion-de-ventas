import { Link, usePage } from '@inertiajs/react';

import guias from '@/routes/guias';
import facturacion from '@/routes/vendedor/facturacion';
import type { Team } from '@/types';

/**
 * Envío SUNAT del vendedor en un solo lugar: comprobantes (con sus notas de
 * crédito y débito) y guías de remisión. Las demás áreas no lo ven.
 */
export default function PestanasSunat({
    actual,
}: {
    actual: 'comprobantes' | 'guias';
}) {
    const { currentTeam, auth } = usePage<{
        currentTeam?: Team | null;
        auth: { roles?: string[] };
    }>().props;

    if (!(auth.roles ?? []).includes('Vendedor')) {
        return null;
    }

    const equipo = currentTeam?.slug ?? '';
    const pestanas = [
        {
            clave: 'comprobantes',
            texto: 'Facturas, boletas y notas',
            href: facturacion.index.url(equipo),
        },
        {
            clave: 'guias',
            texto: 'Guías de remisión',
            href: guias.index.url(equipo),
        },
    ] as const;

    return (
        <nav
            aria-label="Envío SUNAT"
            className="border-border flex flex-wrap gap-2 border-b pb-3"
        >
            {pestanas.map((p) => (
                <Link
                    key={p.clave}
                    href={p.href}
                    aria-current={actual === p.clave ? 'page' : undefined}
                    className={`inline-flex min-h-10 items-center rounded-full px-4 text-sm font-bold ${
                        actual === p.clave
                            ? 'bg-primary text-primary-foreground'
                            : 'border-border text-foreground hover:bg-muted border'
                    }`}
                >
                    {p.texto}
                </Link>
            ))}
        </nav>
    );
}
