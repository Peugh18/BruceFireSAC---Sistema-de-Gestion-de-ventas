import { Link, usePage } from '@inertiajs/react';
import {
    ArrowLeftRight,
    Barcode,
    Boxes,
    LayoutDashboard,
    Search,
    Truck,
    X,
    type LucideIcon,
} from 'lucide-react';

import { useInitials } from '@/hooks/use-initials';
import { dashboard } from '@/routes/almacen';
import recepciones from '@/routes/almacen/recepciones';
import stickers from '@/routes/almacen/stickers';
import stock from '@/routes/almacen/stock';
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

function formatCount(count?: number): string | undefined {
    if (!count) {
        return undefined;
    }

    return new Intl.NumberFormat('es-PE').format(count);
}

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
};

export function AlmacenSidebar({ open, onClose }: AlmacenSidebarProps) {
    const { auth, currentTeam, sidebarCounts } =
        usePage<AlmacenPageProps>().props;
    const currentPath = usePage().url;
    const getInitials = useInitials();
    const userRole = auth.roles?.[0] ?? 'Almacen';
    const almacenNavGroups = currentTeam?.slug
        ? buildNavGroups(currentTeam.slug, sidebarCounts)
        : [];

    return (
        <>
            {open && (
                <div
                    className="fixed inset-0 z-40 bg-black/50 lg:hidden"
                    onClick={onClose}
                />
            )}

            <aside
                className={[
                    'fixed inset-y-0 left-0 z-50 flex h-full w-[236px] shrink-0 flex-col border-r border-sidebar-border bg-sidebar text-sidebar-foreground transition-transform duration-200 lg:static lg:h-full lg:translate-x-0',
                    open ? 'translate-x-0' : '-translate-x-full',
                ].join(' ')}
            >
                <div className="flex h-[66px] shrink-0 items-center gap-2.5 px-5">
                    <img
                        src="/brand/logo-icon.png"
                        alt="Bruce Fire"
                        className="size-[30px] shrink-0"
                    />
                    <div className="min-w-0 flex-1">
                        <div className="font-['Oswald',sans-serif] text-[13px] leading-none font-bold tracking-[0.02em] text-sidebar-foreground uppercase">
                            BRUCE FIRE
                        </div>
                        <div className="mt-1 font-['IBM_Plex_Mono',monospace] text-[8px] tracking-[0.1em] text-sidebar-foreground/60 uppercase">
                            Panel almacén
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label="Cerrar menú"
                        className="flex size-8 shrink-0 items-center justify-center rounded-md text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-foreground lg:hidden transition-colors"
                    >
                        <X className="size-4" />
                    </button>
                </div>

                <nav className="flex flex-1 flex-col gap-0.5 overflow-y-auto px-3 py-3.5 overscroll-contain">
                    {almacenNavGroups.map((group, groupIndex) => (
                        <div key={group.label ?? `group-${groupIndex}`}>
                            {group.label && (
                                <div className="px-2.5 pt-4 pb-1.5 font-['IBM_Plex_Mono',monospace] text-[9.5px] tracking-[0.1em] text-sidebar-foreground/50 uppercase">
                                    {group.label}
                                </div>
                            )}

                            {group.items.map((item) => {
                                const Icon = item.icon;
                                const isActive = isActivePath(
                                    currentPath,
                                    item.href,
                                );

                                return (
                                    <Link
                                        key={item.title}
                                        href={item.href}
                                        prefetch
                                        onClick={onClose}
                                        className={[
                                            'flex h-[38px] items-center gap-[11px] rounded-[9px] px-3 text-[13px] font-medium no-underline transition-colors',
                                            isActive
                                                ? 'bg-primary font-bold text-primary-foreground shadow-xs'
                                                : 'text-sidebar-foreground/75 hover:bg-sidebar-accent hover:text-sidebar-foreground',
                                        ].join(' ')}
                                    >
                                        <Icon
                                            className={[
                                                'size-4 shrink-0',
                                                isActive
                                                    ? 'opacity-100'
                                                    : 'opacity-80',
                                            ].join(' ')}
                                            strokeWidth={2}
                                        />
                                        <span className="min-w-0 flex-1 truncate">
                                            {item.title}
                                        </span>
                                        {item.count !== undefined &&
                                            item.count > 0 && (
                                                <span
                                                    className={[
                                                        'rounded-full px-1.5 py-px font-mono text-[10px]',
                                                        isActive
                                                            ? 'bg-primary-foreground/20 text-primary-foreground'
                                                            : 'bg-sidebar-accent text-sidebar-foreground/90',
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

                <div className="flex shrink-0 items-center gap-2.5 border-t border-sidebar-border px-5 py-4">
                    <div className="flex size-9 shrink-0 items-center justify-center rounded-[10px] bg-primary text-xs font-bold text-primary-foreground shadow-xs">
                        {getInitials(auth.user.name)}
                    </div>
                    <div className="min-w-0">
                        <div className="truncate text-[12.5px] font-bold whitespace-nowrap text-sidebar-foreground">
                            {auth.user.name}
                        </div>
                        <div className="font-['IBM_Plex_Mono',monospace] text-[9.5px] text-sidebar-foreground/60">
                            {userRole}
                        </div>
                    </div>
                </div>
            </aside>
        </>
    );
}
