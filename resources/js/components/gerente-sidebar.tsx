import { usePage } from '@inertiajs/react';
import {
    BarChart3,
    Boxes,
    Building2,
    CircleDollarSign,
    LayoutDashboard,
    MapPin,
    ShieldCheck,
    Truck,
    Users,
    Wallet,
    Wrench,
    type LucideIcon,
} from 'lucide-react';

import { AppNavSidebar } from '@/components/app-nav-sidebar';
import guias from '@/routes/guias';
import transporte from '@/routes/gerente/transporte';
import type { Auth } from '@/types';
import GerenteRoutes from '@/routes/gerente';
import ProductosRoutes from '@/routes/gerente/productos';
import ServiciosRoutes from '@/routes/gerente/servicios';
import CajasRoutes from '@/routes/gerente/cajas';
import CobranzasRoutes from '@/routes/gerente/cobranzas';
import ReportesRoutes from '@/routes/gerente/reportes';
import AuditoriaRoutes from '@/routes/gerente/auditoria';
import SedesRoutes from '@/routes/gerente/sedes';
import UsuariosRoutes from '@/routes/gerente/usuarios';
import EmpresaRoutes from '@/routes/gerente/configuracion/empresa';

type SidebarCounts = {
    productosBajoMinimo?: number;
    cobranzasVencidas?: number;
};

type GerentePageProps = {
    auth: Auth & {
        roles?: string[];
    };
    currentTeam?: { slug: string } | null;
    sidebarCounts?: SidebarCounts | null;
    [key: string]: unknown;
};

type GerenteNavItem = {
    title: string;
    href: string;
    icon: LucideIcon;
    count?: number;
    disabled?: boolean;
};

type GerenteNavGroup = {
    label?: string;
    items: GerenteNavItem[];
};

function buildNavGroups(
    teamSlug: string,
    counts: SidebarCounts | null | undefined,
): GerenteNavGroup[] {
    return [
        {
            items: [
                {
                    title: 'Dashboard',
                    href: GerenteRoutes.dashboard.url({
                        current_team: teamSlug,
                    }),
                    icon: LayoutDashboard,
                },
            ],
        },
        {
            label: 'Catálogo y Precios',
            items: [
                {
                    title: 'Productos',
                    href: ProductosRoutes.index.url({ current_team: teamSlug }),
                    icon: Boxes,
                    count: counts?.productosBajoMinimo,
                },
                {
                    title: 'Servicios',
                    href: ServiciosRoutes.index.url({ current_team: teamSlug }),
                    icon: Wrench,
                },
            ],
        },
        {
            label: 'Finanzas y Control',
            items: [
                {
                    title: 'Caja Consolidada',
                    href: CajasRoutes.index.url({ current_team: teamSlug }),
                    icon: Wallet,
                },
                {
                    title: 'Cobranzas',
                    href: CobranzasRoutes.index.url({ current_team: teamSlug }),
                    icon: CircleDollarSign,
                    count: counts?.cobranzasVencidas,
                },
            ],
        },
        {
            label: 'Información',
            items: [
                {
                    title: 'Reportes',
                    href: ReportesRoutes.index.url({ current_team: teamSlug }),
                    icon: BarChart3,
                },
            ],
        },
        {
            label: 'Administración',
            items: [
                {
                    title: 'Auditoría',
                    href: AuditoriaRoutes.index.url({ current_team: teamSlug }),
                    icon: ShieldCheck,
                },
                {
                    title: 'Sedes',
                    href: SedesRoutes.index.url({ current_team: teamSlug }),
                    icon: MapPin,
                },
                {
                    title: 'Guías de remisión',
                    href: guias.index.url(teamSlug),
                    icon: Truck,
                },
                {
                    title: 'Vehículos y conductores',
                    href: transporte.index.url(teamSlug),
                    icon: Truck,
                },
                {
                    title: 'Usuarios y Roles',
                    href: UsuariosRoutes.index.url({ current_team: teamSlug }),
                    icon: Users,
                },
                {
                    title: 'Configuración',
                    href: EmpresaRoutes.edit.url({ current_team: teamSlug }),
                    icon: Building2,
                },
            ],
        },
    ];
}

function normalizePath(path: string): string {
    const withoutQuery = path.split('?')[0].split('#')[0];
    const withoutTrailingSlash = withoutQuery.replace(/\/+$/u, '');

    return withoutTrailingSlash === '' ? '/' : withoutTrailingSlash;
}

function isActivePath(currentPath: string, href: string): boolean {
    const normalizedCurrentPath = normalizePath(currentPath);
    const normalizedHref = normalizePath(href);

    if (
        normalizedHref.endsWith('/gerente/dashboard') &&
        normalizedCurrentPath.endsWith('/gerente')
    ) {
        return true;
    }

    // Configuración agrupa sus pestañas (empresa, firmas y sellos).
    if (
        normalizedHref.endsWith('/gerente/configuracion/empresa') &&
        normalizedCurrentPath.includes('/gerente/configuracion/')
    ) {
        return true;
    }

    return (
        normalizedCurrentPath === normalizedHref ||
        normalizedCurrentPath.startsWith(`${normalizedHref}/`)
    );
}

type GerenteSidebarProps = {
    open: boolean;
    onClose: () => void;
    collapsed?: boolean;
};

export function GerenteSidebar({
    open,
    onClose,
    collapsed = false,
}: GerenteSidebarProps) {
    const { auth, currentTeam, sidebarCounts } =
        usePage<GerentePageProps>().props;
    const userRole = auth.roles?.[0] ?? 'Gerente';
    const gerenteNavGroups = currentTeam?.slug
        ? buildNavGroups(currentTeam.slug, sidebarCounts)
        : [];

    return (
        <AppNavSidebar
            open={open}
            onClose={onClose}
            collapsed={collapsed}
            groups={gerenteNavGroups}
            roleTitle={userRole}
            customIsActive={isActivePath}
        />
    );
}
