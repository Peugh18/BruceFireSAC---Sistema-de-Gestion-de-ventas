import { usePage } from '@inertiajs/react';
import {
    ArrowLeftRight,
    Barcode,
    Boxes,
    LayoutDashboard,
    Search,
    Truck,
    type LucideIcon,
} from 'lucide-react';

import { AppNavSidebar } from '@/components/app-nav-sidebar';
import { dashboard } from '@/routes/almacen';
import recepciones from '@/routes/almacen/recepciones';
import stickers from '@/routes/almacen/stickers';
import stock from '@/routes/almacen/stock';
import guias from '@/routes/guias';
import type { Auth } from '@/types';

type SidebarCounts = {
    bajoMinimo?: number;
    recepcionesHoy?: number;
};

type AlmacenPageProps = {
    auth: Auth & {
        roles?: string[];
    };
    currentTeam?: { slug: string } | null;
    sidebarCounts?: SidebarCounts | null;
    [key: string]: unknown;
};

type AlmacenNavItem = {
    title: string;
    href: string;
    icon: LucideIcon;
    count?: number;
    disabled?: boolean;
};

type AlmacenNavGroup = {
    label?: string;
    items: AlmacenNavItem[];
};

function buildNavGroups(
    teamSlug: string,
    counts: SidebarCounts | null | undefined,
): AlmacenNavGroup[] {
    return [
        {
            items: [
                {
                    title: 'Dashboard',
                    href: dashboard.url(teamSlug),
                    icon: LayoutDashboard,
                },
            ],
        },
        {
            label: 'Inventario',
            items: [
                {
                    title: 'Stock y Kardex',
                    href: stock.index.url(teamSlug),
                    icon: Boxes,
                    count: counts?.bajoMinimo,
                },
                {
                    title: 'Recepciones',
                    href: recepciones.index.url(teamSlug),
                    icon: Truck,
                    count: counts?.recepcionesHoy,
                },
                {
                    title: 'Stickers de Barras',
                    href: stickers.index.url(teamSlug),
                    icon: Barcode,
                },
                {
                    title: 'Ajustes de Stock',
                    href: `/${teamSlug}/almacen/ajustes`,
                    icon: ArrowLeftRight,
                },
                {
                    title: 'Guías de remisión',
                    href: guias.index.url(teamSlug),
                    icon: Truck,
                },
                {
                    title: 'Traslados',
                    href: `/${teamSlug}/almacen/traslados`,
                    icon: ArrowLeftRight,
                },
            ],
        },
        {
            label: 'Operación',
            items: [
                {
                    title: 'Consulta Rápida',
                    href: `/${teamSlug}/almacen/consulta`,
                    icon: Search,
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
        normalizedHref.endsWith('/almacen/dashboard') &&
        normalizedCurrentPath.endsWith('/almacen')
    ) {
        return true;
    }

    return (
        normalizedCurrentPath === normalizedHref ||
        normalizedCurrentPath.startsWith(`${normalizedHref}/`)
    );
}

type AlmacenSidebarProps = {
    open: boolean;
    onClose: () => void;
    collapsed?: boolean;
};

export function AlmacenSidebar({
    open,
    onClose,
    collapsed = false,
}: AlmacenSidebarProps) {
    const { auth, currentTeam, sidebarCounts } =
        usePage<AlmacenPageProps>().props;
    const userRole = auth.roles?.[0] ?? 'Almacen';
    const almacenNavGroups = currentTeam?.slug
        ? buildNavGroups(currentTeam.slug, sidebarCounts)
        : [];

    return (
        <AppNavSidebar
            open={open}
            onClose={onClose}
            collapsed={collapsed}
            groups={almacenNavGroups}
            roleTitle={userRole}
            customIsActive={isActivePath}
        />
    );
}
