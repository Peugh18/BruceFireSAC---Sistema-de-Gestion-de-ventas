import { Link } from '@inertiajs/react';
import { Building2, PenLine } from 'lucide-react';

import empresa from '@/routes/gerente/configuracion/empresa';
import firmas from '@/routes/gerente/configuracion/firmas';

type Props = {
    teamSlug: string;
    activa: 'empresa' | 'firmas';
    firmasPendientes?: number;
};

/**
 * Pestañas de Configuración: los datos de la empresa y las firmas y sellos
 * de los certificados viven en el mismo lugar.
 */
export function ConfiguracionTabs({
    teamSlug,
    activa,
    firmasPendientes = 0,
}: Props) {
    const pestanas = [
        {
            clave: 'empresa' as const,
            titulo: 'Datos de la empresa',
            icono: Building2,
            href: empresa.edit.url({ current_team: teamSlug }),
        },
        {
            clave: 'firmas' as const,
            titulo: 'Firmas y sellos',
            icono: PenLine,
            href: firmas.index.url({ current_team: teamSlug }),
        },
    ];

    return (
        <div className="border-border flex gap-1 border-b">
            {pestanas.map((pestana) => {
                const Icono = pestana.icono;
                const activo = pestana.clave === activa;

                return (
                    <Link
                        key={pestana.clave}
                        href={pestana.href}
                        preserveScroll
                        className={`-mb-px inline-flex items-center gap-2 border-b-2 px-3 py-2.5 text-[13px] font-bold transition-colors ${
                            activo
                                ? 'border-primary text-foreground'
                                : 'text-muted-foreground hover:text-foreground border-transparent'
                        }`}
                    >
                        <Icono className="size-4" />
                        {pestana.titulo}
                        {pestana.clave === 'firmas' && firmasPendientes > 0 ? (
                            <span className="rounded-full bg-amber-500/15 px-1.5 text-[10.5px] text-amber-700 dark:text-amber-400">
                                {firmasPendientes}
                            </span>
                        ) : null}
                    </Link>
                );
            })}
        </div>
    );
}
