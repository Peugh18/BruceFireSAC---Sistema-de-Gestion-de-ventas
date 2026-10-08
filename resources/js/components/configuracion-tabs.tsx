import { Building2, PenLine } from 'lucide-react';

import { TabsBf, type PestanaBf } from '@/components/ui/tabs-bf';
import empresa from '@/routes/gerente/configuracion/empresa';
import firmas from '@/routes/gerente/configuracion/firmas';

type Props = {
    teamSlug: string;
    activa: 'empresa' | 'firmas';
    firmasPendientes?: number;
};

/**
 * Pestañas de Configuración: los datos de la empresa y las firmas y sellos
 * de los certificados viven en el mismo lugar. Usa la barra de pestañas
 * unificada de la aplicación.
 */
export function ConfiguracionTabs({
    teamSlug,
    activa,
    firmasPendientes = 0,
}: Props) {
    const pestanas: PestanaBf<'empresa' | 'firmas'>[] = [
        {
            id: 'empresa',
            titulo: 'Datos de la empresa',
            icono: Building2,
            href: empresa.edit.url({ current_team: teamSlug }),
        },
        {
            id: 'firmas',
            titulo: 'Firmas y sellos',
            icono: PenLine,
            href: firmas.index.url({ current_team: teamSlug }),
            contador: firmasPendientes,
        },
    ];

    return (
        <TabsBf
            pestanas={pestanas}
            activa={activa}
            etiqueta="Secciones de configuración"
            idBase="configuracion"
        />
    );
}
