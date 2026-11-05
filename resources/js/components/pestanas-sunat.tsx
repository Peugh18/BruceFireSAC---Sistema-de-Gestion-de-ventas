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
            className="border-border flex flex-wrap gap-1 border-b"
        >
            {pestanas.map((p) => (
                <Link
                    key={p.clave}
                    href={p.href}
                    aria-current={actual === p.clave ? 'page' : undefined}
                    className={`-mb-px inline-flex min-h-11 items-center border-b-2 px-4 text-sm font-semibold ${
                        actual === p.clave
                            ? 'border-primary text-foreground'
                            : 'text-muted-foreground hover:text-foreground border-transparent'
                    }`}
                >
                    {p.texto}
                </Link>
            ))}
        </nav>
    );
}
