import { Link, usePage } from '@inertiajs/react';
import {
    CalendarCheck,
    ChevronRight,
    ClipboardCheck,
    LayoutDashboard,
    MapPin,
    Truck,
    Wrench,
} from 'lucide-react';
import { useState, type ReactNode } from 'react';
import TecnicoLayout from '@/components/tecnico-layout';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import campo from '@/routes/tecnico-campo';
import recojos from '@/routes/tecnico-campo/recojos';
import inspecciones from '@/routes/tecnico-campo/inspecciones';
import instalaciones from '@/routes/tecnico-campo/instalaciones';
import mantenimientos from '@/routes/tecnico-campo/mantenimientos';
import entregas from '@/routes/tecnico-campo/entregas';
import type { Team } from '@/types';

export default function TecnicoCampoLayout({
    children,
}: {
    children: ReactNode;
    title?: string;
}) {
    const page = usePage<{ currentTeam?: Team | null }>();
    const equipo = page.props.currentTeam?.slug ?? '';
    const [serviciosAbiertos, setServiciosAbiertos] = useState(false);
    const servicios = [
        {
            title: 'Recojos',
            description: 'Recibir equipos y registrar la custodia',
            href: recojos.index.url(equipo),
            icon: Truck,
        },
        {
            title: 'Inspecciones',
            description: 'Revisar equipos y registrar hallazgos',
            href: inspecciones.index.url(equipo),
            icon: ClipboardCheck,
        },
        {
            title: 'Instalaciones',
            description: 'Instalar equipos y registrar la conformidad',
            href: instalaciones.index.url(equipo),
            icon: MapPin,
        },
        {
            title: 'Mantenimientos',
            description: 'Atender y revisar equipos en el sitio',
            href: mantenimientos.index.url(equipo),
            icon: Wrench,
        },
    ];
    return (
        <>
            <TecnicoLayout
                area="campo"
                navItems={[
                    {
                        title: 'Inicio',
                        href: campo.dashboard.url(equipo),
                        icon: LayoutDashboard,
                        activeMatch: '/tecnico-campo/dashboard',
                    },
                    {
                        title: 'Servicios',
                        icon: Wrench,
                        activeMatch: '',
                        activo:
                            serviciosAbiertos ||
                            servicios.some((item) =>
                                page.url.split('?')[0].startsWith(item.href),
                            ),
                        onClick: () => setServiciosAbiertos(true),
                    },
                    {
                        title: 'Entregas',
                        href: entregas.index.url(equipo),
                        icon: CalendarCheck,
                        activeMatch: '/tecnico-campo/entregas',
                    },
                ]}
            >
                {children}
            </TecnicoLayout>
            <Sheet open={serviciosAbiertos} onOpenChange={setServiciosAbiertos}>
                <SheetContent
                    side="bottom"
                    className="mx-auto max-h-[85dvh] max-w-2xl overflow-y-auto rounded-t-3xl px-5 pt-6 pb-[max(1.5rem,env(safe-area-inset-bottom))]"
                >
                    <SheetTitle className="text-xl font-bold">
                        Servicios de Campo
                    </SheetTitle>
                    <SheetDescription>
                        Elige el trabajo que vas a atender.
                    </SheetDescription>
                    <div className="border-border bg-card mt-4 divide-y overflow-hidden rounded-3xl border">
                        {servicios.map(
                            ({ title, description, href, icon: Icon }) => (
                                <Link
                                    key={title}
                                    href={href}
                                    onClick={() => setServiciosAbiertos(false)}
                                    className="hover:bg-muted focus-visible:ring-ring flex min-h-20 items-center gap-4 px-4 py-4 focus-visible:ring-2 focus-visible:outline-none"
                                >
                                    <span className="bg-primary/10 text-primary-strong flex size-11 shrink-0 items-center justify-center rounded-2xl">
                                        <Icon
                                            className="size-5"
                                            aria-hidden="true"
                                        />
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block text-sm font-semibold">
                                            {title}
                                        </span>
                                        <span className="text-muted-foreground block text-xs">
                                            {description}
                                        </span>
                                    </span>
                                    <ChevronRight
                                        className="text-muted-foreground size-4 shrink-0"
                                        aria-hidden="true"
                                    />
                                </Link>
                            ),
                        )}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}
