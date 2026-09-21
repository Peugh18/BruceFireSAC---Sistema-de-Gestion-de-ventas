import { Link, usePage } from '@inertiajs/react';
import {
    Award,
    ClipboardCheck,
    ClipboardList,
    CreditCard,
    LayoutDashboard,
    MessageCircle,
    ReceiptText,
    ShoppingCart,
    TriangleAlert,
    UsersRound,
    Wrench,
    type LucideIcon,
} from 'lucide-react';

import { dashboard } from '@/routes/vendedor';
import alertas from '@/routes/vendedor/alertas';
import certificados from '@/routes/vendedor/certificados';
import clientes from '@/routes/vendedor/clientes';
import cobranzas from '@/routes/vendedor/cobranzas';
import comunicacion from '@/routes/vendedor/comunicacion';
import cotizaciones from '@/routes/vendedor/cotizaciones';
import deficiencias from '@/routes/vendedor/deficiencias';
import facturacion from '@/routes/vendedor/facturacion';
import ordenesServicio from '@/routes/vendedor/ordenes-servicio';
import ventas from '@/routes/vendedor/ventas';
import { useInitials } from '@/hooks/use-initials';
import type { Auth } from '@/types';

type SidebarCounts = {
    clientes: number;
    cotizaciones: number;
    alertas: number;
    deficiencias: number;
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

function formatCount(count?: number): string | undefined {
    if (!count) {
        return undefined;
    }

    return new Intl.NumberFormat('es-PE').format(count);
}

function buildNavGroups(teamSlug: string, counts: SidebarCounts | null | undefined): VendedorNavGroup[] {
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
            label: 'Comercial',
            items: [
                {
                    title: 'Clientes',
                    href: clientes.index.url(teamSlug),
                    icon: UsersRound,
                    count: counts?.clientes,
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
                    title: 'Cobranzas',
                    href: cobranzas.index.url(teamSlug),
                    icon: CreditCard,
                },
            ],
        },
        {
            label: 'Operación',
            items: [
                {
                    title: 'Alertas de Vencimiento',
                    href: alertas.index.url(teamSlug),
                    icon: TriangleAlert,
                    count: counts?.alertas,
                },
                {
                    title: 'Órdenes de Servicio',
                    href: ordenesServicio.index.url(teamSlug),
                    icon: ClipboardCheck,
                },
                {
                    title: 'Deficiencias y Adicionales',
                    href: deficiencias.index.url(teamSlug),
                    icon: Wrench,
                    count: counts?.deficiencias,
                },
                {
                    title: 'Comunicación con Taller',
                    href: comunicacion.index.url(teamSlug),
                    icon: MessageCircle,
                },
            ],
        },
        {
            label: 'Certificados y fiscal',
            items: [
                {
                    title: 'Certificados',
                    href: certificados.index.url(teamSlug),
                    icon: Award,
                },
                {
                    title: 'Facturación Electrónica',
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
        normalizedHref.endsWith('/vendedor/dashboard') &&
        normalizedCurrentPath.endsWith('/vendedor')
    ) {
        return true;
    }

    return (
        normalizedCurrentPath === normalizedHref ||
        normalizedCurrentPath.startsWith(`${normalizedHref}/`)
    );
}

export function VendedorSidebar() {
    const { auth, currentTeam, sidebarCounts } = usePage<VendedorPageProps>().props;
    const currentPath = usePage().url;
    const getInitials = useInitials();
    const userRole = auth.roles?.[0] ?? 'Vendedor';
    const vendedorNavGroups = currentTeam?.slug
        ? buildNavGroups(currentTeam.slug, sidebarCounts)
        : [];

    return (
        <aside className="flex h-screen w-[236px] shrink-0 flex-col bg-[#18181B]">
            <div className="flex h-[66px] shrink-0 items-center gap-2.5 px-5">
                <div className="flex size-[26px] items-center justify-center rounded-[7px] bg-[#E31E24] font-['Oswald',sans-serif] text-[11px] font-bold text-white">
                    BF
                </div>
                <div className="min-w-0">
                    <div className="font-['Oswald',sans-serif] text-[13px] leading-none font-bold tracking-[0.02em] text-white uppercase">
                        BRUCE FIRE
                    </div>
                    <div className="mt-1 font-['IBM_Plex_Mono',monospace] text-[8px] tracking-[0.1em] text-[#6B6965] uppercase">
                        Panel vendedor
                    </div>
                </div>
            </div>

            <nav className="flex flex-1 flex-col gap-0.5 overflow-y-auto px-3 py-3.5">
                {vendedorNavGroups.map((group, groupIndex) => (
                    <div key={group.label ?? `group-${groupIndex}`}>
                        {group.label && (
                            <div className="px-2.5 pt-4 pb-1.5 font-['IBM_Plex_Mono',monospace] text-[9.5px] tracking-[0.1em] text-[#6B6965] uppercase">
                                {group.label}
                            </div>
                        )}

                        {group.items.map((item) => {
                            const Icon = item.icon;
                            const isActive = isActivePath(currentPath, item.href);

                            return (
                                <Link
                                    key={item.title}
                                    href={item.href}
                                    prefetch
                                    className={[
                                        'flex h-[38px] items-center gap-[11px] rounded-[9px] px-3 text-[13px] font-medium no-underline transition-colors',
                                        isActive
                                            ? 'bg-[#E31E24] font-bold text-white'
                                            : 'text-[#B9B7B2] hover:bg-[#232327] hover:text-white',
                                    ].join(' ')}
                                >
                                    <Icon
                                        className={[
                                            'size-4 shrink-0',
                                            isActive ? 'opacity-100' : 'opacity-80',
                                        ].join(' ')}
                                        strokeWidth={2}
                                    />
                                    <span className="min-w-0 flex-1 truncate">
                                        {item.title}
                                    </span>
                                    {item.count !== undefined && item.count > 0 && (
                                        <span
                                            className={[
                                                'rounded-full px-1.5 py-px font-mono text-[10px]',
                                                isActive
                                                    ? 'bg-white/25'
                                                    : 'bg-white/[0.12]',
                                            ].join(' ')}
                                        >
                                            {formatCount(item.count)}
                                        </span>
                                    )}
                                </Link>
                            );
                        })}
                    </div>
                ))}
            </nav>

            <div className="flex shrink-0 items-center gap-2.5 border-t border-[#26262A] px-5 py-4">
                <div className="flex size-9 shrink-0 items-center justify-center rounded-[10px] bg-[#E31E24] text-xs font-bold text-white">
                    {getInitials(auth.user.name)}
                </div>
                <div className="min-w-0">
                    <div className="truncate text-[12.5px] font-bold whitespace-nowrap text-white">
                        {auth.user.name}
                    </div>
                    <div className="font-['IBM_Plex_Mono',monospace] text-[9.5px] text-[#6B6965]">
                        {userRole}
                    </div>
                </div>
            </div>
        </aside>
    );
}
