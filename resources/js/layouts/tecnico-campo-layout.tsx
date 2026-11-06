import { usePage } from '@inertiajs/react';
import { CalendarCheck, LayoutDashboard, MapPin, Truck } from 'lucide-react';
import type { ReactNode } from 'react';
import TecnicoLayout from '@/components/tecnico-layout';
import campo from '@/routes/tecnico-campo';
import entregas from '@/routes/tecnico-campo/entregas';
import instalaciones from '@/routes/tecnico-campo/instalaciones';
import recojos from '@/routes/tecnico-campo/recojos';
import type { Team } from '@/types';

/**
 * Campo hace tres trabajos: recoger extintores, entregarlos e instalarlos.
 * Van directo en la barra; la inspección y el mantenimiento son de planta.
 */
export default function TecnicoCampoLayout({
    children,
    ocultarMenu = false,
}: {
    children: ReactNode;
    title?: string;
    /** En un trabajo en curso la barra se oculta y abajo queda su botón. */
    ocultarMenu?: boolean;
}) {
    const page = usePage<{ currentTeam?: Team | null }>();
    const equipo = page.props.currentTeam?.slug ?? '';

    return (
        <TecnicoLayout
            area="campo"
            ocultarMenu={ocultarMenu}
            navItems={[
                {
                    title: 'Inicio',
                    href: campo.dashboard.url(equipo),
                    icon: LayoutDashboard,
                    activeMatch: '/tecnico-campo/dashboard',
                },
                {
                    title: 'Recojos',
                    href: recojos.index.url(equipo),
                    icon: Truck,
                    activeMatch: '/tecnico-campo/recojos',
                },
                {
                    title: 'Entregas',
                    href: entregas.index.url(equipo),
                    icon: CalendarCheck,
                    activeMatch: '/tecnico-campo/entregas',
                },
                {
                    title: 'Instalaciones',
                    href: instalaciones.index.url(equipo),
                    icon: MapPin,
                    activeMatch: '/tecnico-campo/instalaciones',
                },
            ]}
        >
            {children}
        </TecnicoLayout>
    );
}
