import { usePage } from '@inertiajs/react';
import { AlertTriangle, LayoutDashboard, PackageCheck } from 'lucide-react';
import type { ReactNode } from 'react';
import TecnicoLayout from '@/components/tecnico-layout';
import { dashboard } from '@/routes/tecnico-planta';
import deficiencias from '@/routes/tecnico-planta/deficiencias';
import recepciones from '@/routes/tecnico-planta/recepciones';
import type { Team } from '@/types';

export default function TecnicoPlantaLayout({
    children,
    ocultarMenu = false,
}: {
    children: ReactNode;
    title?: string;
    ocultarMenu?: boolean;
}) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const equipo = currentTeam?.slug ?? '';
    return (
        <TecnicoLayout
            area="planta"
            ocultarMenu={ocultarMenu}
            navItems={[
                {
                    title: 'Inicio',
                    href: dashboard.url(equipo),
                    icon: LayoutDashboard,
                    activeMatch: '/tecnico-planta/dashboard',
                },
                {
                    title: 'Recepción',
                    href: recepciones.index.url(equipo),
                    icon: PackageCheck,
                    activeMatch: '/tecnico-planta/recepciones',
                },
                {
                    title: 'Hallazgos',
                    href: deficiencias.index.url(equipo),
                    icon: AlertTriangle,
                    activeMatch: '/tecnico-planta/deficiencias',
                },
            ]}
        >
            {children}
        </TecnicoLayout>
    );
}
