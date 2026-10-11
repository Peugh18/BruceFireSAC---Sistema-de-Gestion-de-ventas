import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

import AlmacenLayout from '@/layouts/almacen-layout';
import AppLayout from '@/layouts/app-layout';
import GerenteLayout from '@/layouts/gerente-layout';
import TecnicoCampoLayout from '@/layouts/tecnico-campo-layout';
import TecnicoPlantaLayout from '@/layouts/tecnico-planta-layout';
import VendedorLayout from '@/layouts/vendedor-layout';
import type { BreadcrumbItem } from '@/types';

export default function RoleLayout({
    children,
    breadcrumbs,
    title = 'Mi cuenta',
}: {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
    title?: string;
}) {
    const { auth } = usePage<{ auth: { roles?: string[] } }>().props;
    const roles = auth.roles ?? [];

    if (roles.includes('Gerente'))
        return <GerenteLayout title={title}>{children}</GerenteLayout>;
    if (roles.includes('Vendedor'))
        return <VendedorLayout title={title}>{children}</VendedorLayout>;
    if (roles.includes('Almacen'))
        return <AlmacenLayout title={title}>{children}</AlmacenLayout>;
    if (roles.includes('TecnicoCampo'))
        return (
            <TecnicoCampoLayout title={title}>{children}</TecnicoCampoLayout>
        );

    if (roles.includes('TecnicoPlanta'))
        return (
            <TecnicoPlantaLayout title={title}>{children}</TecnicoPlantaLayout>
        );

    return <AppLayout breadcrumbs={breadcrumbs}>{children}</AppLayout>;
}
