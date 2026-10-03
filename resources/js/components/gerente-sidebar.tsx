import { usePage } from '@inertiajs/react';
import {
    BarChart3,
    Boxes,
    Building2,
    CircleDollarSign,
    LayoutDashboard,
    MapPin,
    ShieldCheck,
    Users,
    Wallet,
    Wrench,
    type LucideIcon,
} from 'lucide-react';

import { AppNavSidebar } from '@/components/app-nav-sidebar';
import type { Auth } from '@/types';

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
                    href: `/${teamSlug}/gerente/dashboard`,
                    icon: LayoutDashboard,
                },
            ],
        },
        {
            label: 'Catálogo y Precios',
            items: [
                {
                    title: 'Productos',
                    href: `/${teamSlug}/gerente/productos`,
                    icon: Boxes,
                    count: counts?.productosBajoMinimo,
                },
                {
                    title: 'Servicios',
                    href: `/${teamSlug}/gerente/servicios`,
                    icon: Wrench,
                },
            ],
        },
        {
            label: 'Finanzas y Control',
            items: [
                {
                    title: 'Caja Consolidada',
                    href: `/${teamSlug}/gerente/cajas`,
                    icon: Wallet,
                },
                {
                    title: 'Cobranzas',
                    href: `/${teamSlug}/gerente/cobranzas`,
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
                    href: `/${teamSlug}/gerente/reportes`,
                    icon: BarChart3,
                },
            ],
        },
        {
            label: 'Administración',
            items: [
                {
                    title: 'Auditoría',
                    href: `/${teamSlug}/gerente/auditoria`,
                    icon: ShieldCheck,
                },
                {
                    title: 'Sedes',
                    href: `/${teamSlug}/gerente/sedes`,
                    icon: MapPin,
                },
                {
                    title: 'Usuarios y Roles',
                    href: `/${teamSlug}/gerente/usuarios`,
                    icon: Users,
                },
                {
                    title: 'Configuración',
                    href: `/${teamSlug}/gerente/configuracion/empresa`,
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
