import { usePage } from '@inertiajs/react';
import {
    Award,
    BellRing,
    ClipboardList,
    LayoutDashboard,
    ReceiptText,
    ShoppingCart,
    UsersRound,
    Wallet,
    Wrench,
    type LucideIcon,
} from 'lucide-react';

import { AppNavSidebar } from '@/components/app-nav-sidebar';
import { dashboard } from '@/routes/vendedor';
import alertas from '@/routes/vendedor/alertas';
import caja from '@/routes/vendedor/caja';
import certificados from '@/routes/vendedor/certificados';
import clientes from '@/routes/vendedor/clientes';
import cotizaciones from '@/routes/vendedor/cotizaciones';
import facturacion from '@/routes/vendedor/facturacion';
import ordenesServicio from '@/routes/vendedor/ordenes-servicio';
import ventas from '@/routes/vendedor/ventas';
import type { Auth } from '@/types';

type SidebarCounts = {
    clientes: number;
    cotizaciones: number;
    alertas: number;
    deficiencias: number;
    servicios_alertas?: number;
};

type VendedorPageProps = {
    auth: Auth & {
        roles?: string[];
    };
    currentTeam?: { slug: string } | null;
    sidebarCounts?: SidebarCounts | null;
    [key: string]: unknown;
};

type VendedorNavItem = {
    title: string;
    href: string;
    icon: LucideIcon;
    count?: number;
};

type VendedorNavGroup = {
    label?: string;
    items: VendedorNavItem[];
};

function buildNavGroups(
    teamSlug: string,
    counts: SidebarCounts | null | undefined,
): VendedorNavGroup[] {
    return [
        {
            items: [
                {
                    title: 'Inicio',
                    href: dashboard.url(teamSlug),
                    icon: LayoutDashboard,
                },
            ],
        },
        {
            label: 'Comercial',
            items: [
                {
                    title: 'Clientes',
                    href: clientes.index.url(teamSlug),
                    icon: UsersRound,
                },
                {
                    title: 'Por vencer',
                    href: alertas.index.url(teamSlug),
                    icon: BellRing,
                },
                {
                    title: 'Cotizaciones',
                    href: cotizaciones.index.url(teamSlug),
                    icon: ClipboardList,
                    count: counts?.cotizaciones,
                },
                {
                    title: 'Ventas',
                    href: ventas.index.url(teamSlug),
                    icon: ShoppingCart,
                },
                {
                    title: 'Caja y cobranzas',
                    href: caja.index.url(teamSlug),
                    icon: Wallet,
                },
            ],
        },
        {
            label: 'Operación',
            items: [
                {
                    title: 'Servicios',
                    href: ordenesServicio.index.url(teamSlug),
                    icon: Wrench,
                    count: counts?.servicios_alertas ?? counts?.deficiencias,
                },
                {
                    title: 'Certificados',
                    href: certificados.index.url(teamSlug),
                    icon: Award,
                },
                {
                    title: 'Comprobantes SUNAT',
                    href: facturacion.index.url(teamSlug),
                    icon: ReceiptText,
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
        (normalizedHref.endsWith('/vendedor/dashboard') ||
            normalizedHref.endsWith('/vendedor')) &&
        (normalizedCurrentPath.endsWith('/vendedor') ||
            normalizedCurrentPath.endsWith('/vendedor/dashboard'))
    ) {
        return true;
    }

    if (normalizedHref.includes('/vendedor/ordenes-servicio')) {
        if (
            normalizedCurrentPath.includes('/vendedor/ordenes-servicio') ||
            normalizedCurrentPath.includes('/vendedor/comunicacion') ||
            normalizedCurrentPath.includes('/vendedor/deficiencias')
        ) {
            return true;
        }
    }

    return (
        normalizedCurrentPath === normalizedHref ||
        normalizedCurrentPath.startsWith(`${normalizedHref}/`)
    );
}

type VendedorSidebarProps = {
    open: boolean;
    onClose: () => void;
    collapsed?: boolean;
};

export function VendedorSidebar({
    open,
    onClose,
    collapsed = false,
}: VendedorSidebarProps) {
    const { auth, currentTeam, sidebarCounts } =
        usePage<VendedorPageProps>().props;
    const userRole = auth.roles?.[0] ?? 'Vendedor';
    const vendedorNavGroups = currentTeam?.slug
        ? buildNavGroups(currentTeam.slug, sidebarCounts)
        : [];

    return (
        <AppNavSidebar
            open={open}
            onClose={onClose}
            collapsed={collapsed}
            groups={vendedorNavGroups}
            roleTitle={userRole}
            customIsActive={isActivePath}
        />
    );
}
