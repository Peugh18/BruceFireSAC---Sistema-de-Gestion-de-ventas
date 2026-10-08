import { ClipboardCheck, MessageCircle, Wrench } from 'lucide-react';

import { TabsBf, type PestanaBf } from '@/components/ui/tabs-bf';
import comunicacion from '@/routes/vendedor/comunicacion';
import deficiencias from '@/routes/vendedor/deficiencias';
import ordenesServicio from '@/routes/vendedor/ordenes-servicio';

type Props = {
    teamSlug: string;
    activa: 'ordenes' | 'taller' | 'deficiencias';
    deficienciasPendientes?: number;
};

/**
 * Pestañas compartidas de Servicios: Órdenes de Servicio, Seguimiento de
 * taller y Deficiencias. Usa la barra de pestañas unificada de la aplicación.
 */
export function ServiciosTabs({
    teamSlug,
    activa,
    deficienciasPendientes = 0,
}: Props) {
    const pestanas: PestanaBf<'ordenes' | 'taller' | 'deficiencias'>[] = [
        {
            id: 'ordenes',
            titulo: 'Órdenes de servicio',
            icono: ClipboardCheck,
            href: ordenesServicio.index.url(teamSlug),
        },
        {
            id: 'taller',
            titulo: 'Seguimiento de taller',
            icono: MessageCircle,
            href: comunicacion.index.url(teamSlug),
        },
        {
            id: 'deficiencias',
            titulo: 'Deficiencias y adicionales',
            icono: Wrench,
            href: deficiencias.index.url(teamSlug),
            contador: deficienciasPendientes,
        },
    ];

    return (
        <TabsBf
            pestanas={pestanas}
            activa={activa}
            etiqueta="Secciones de servicios"
            idBase="servicios"
        />
    );
}

export default ServiciosTabs;
