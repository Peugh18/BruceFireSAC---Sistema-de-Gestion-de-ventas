import { Link } from '@inertiajs/react';
import { ClipboardCheck, MessageCircle, Wrench } from 'lucide-react';

import comunicacion from '@/routes/vendedor/comunicacion';
import deficiencias from '@/routes/vendedor/deficiencias';
import ordenesServicio from '@/routes/vendedor/ordenes-servicio';

type Props = {
    teamSlug: string;
    activa: 'ordenes' | 'taller' | 'deficiencias';
    deficienciasPendientes?: number;
};

/**
 * Pestañas compartidas de Servicios: Órdenes de Servicio, Seguimiento de taller y Deficiencias.
 */
export function ServiciosTabs({
    teamSlug,
    activa,
    deficienciasPendientes = 0,
}: Props) {
    const pestanas = [
        {
            clave: 'ordenes' as const,
            titulo: 'Órdenes de servicio',
            icono: ClipboardCheck,
            href: ordenesServicio.index.url(teamSlug),
        },
        {
            clave: 'taller' as const,
            titulo: 'Seguimiento de taller',
            icono: MessageCircle,
            href: comunicacion.index.url(teamSlug),
        },
        {
            clave: 'deficiencias' as const,
            titulo: 'Deficiencias y adicionales',
            icono: Wrench,
            href: deficiencias.index.url(teamSlug),
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
                        {pestana.clave === 'deficiencias' &&
                        deficienciasPendientes > 0 ? (
                            <span className="rounded-full bg-amber-500/15 px-1.5 text-[10.5px] font-bold text-amber-700 dark:text-amber-400">
                                {deficienciasPendientes}
                            </span>
                        ) : null}
                    </Link>
                );
            })}
        </div>
    );
}

export default ServiciosTabs;
