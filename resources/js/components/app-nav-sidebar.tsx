import { Link, usePage } from '@inertiajs/react';
import { LogOut, X, type LucideIcon } from 'lucide-react';

import { useInitials } from '@/hooks/use-initials';
import { logout } from '@/routes';
import type { Auth } from '@/types';

export type AppNavItem = {
    title: string;
    href: string;
    icon: LucideIcon;
    count?: number | string;
    activeMatch?: string;
};

export type AppNavGroup = {
    label?: string;
    items: AppNavItem[];
};

export type AppNavSidebarProps = {
    open: boolean;
    onClose: () => void;
    collapsed?: boolean;
    groups: AppNavGroup[];
    roleTitle?: string;
    customIsActive?: (
        currentPath: string,
        href: string,
        activeMatch?: string,
    ) => boolean;
};

type AuthPageProps = {
    auth?: Auth & {
        roles?: string[];
    };
    [key: string]: unknown;
};

function normalizePath(path: string): string {
    const withoutQuery = path.split('?')[0].split('#')[0];
    const withoutTrailingSlash = withoutQuery.replace(/\/+$/u, '');

    return withoutTrailingSlash === '' ? '/' : withoutTrailingSlash;
}

export function defaultIsItemActive(
    currentPath: string,
    href: string,
    activeMatch?: string,
): boolean {
    const normalizedCurrent = normalizePath(currentPath);
    const normalizedHref = normalizePath(href);

    if (activeMatch) {
        return normalizedCurrent.includes(activeMatch);
    }

    if (normalizedCurrent === normalizedHref) {
        return true;
    }

    // Si es dashboard o inicio (evita coincidir con rutas hermanas)
    if (
        normalizedHref.endsWith('/dashboard') ||
        normalizedHref.endsWith('/inicio')
    ) {
        return normalizedCurrent === normalizedHref;
    }

    return normalizedCurrent.startsWith(`${normalizedHref}/`);
}

function formatCount(count?: number | string): string | undefined {
    if (count === undefined || count === null || count === 0 || count === '0') {
        return undefined;
    }

    if (typeof count === 'number') {
        return new Intl.NumberFormat('es-PE').format(count);
    }

    return String(count);
}

/**
 * Sidebar base unificado para todos los roles (Vendedor, Gerente, Almacén, Técnicos).
 * Proporciona el efecto cóncavo de diseño Apple, colapso fluido a píldora circular,
 * pie con avatar de usuario y cierre de sesión centralizado.
 */
export function AppNavSidebar({
    open,
    onClose,
    collapsed = false,
    groups,
    roleTitle,
    customIsActive,
}: AppNavSidebarProps) {
    const { auth } = usePage<AuthPageProps>().props;
    const currentPath = usePage().url;
    const getInitials = useInitials();

    const roles = auth?.roles ?? [];
    const userRole = roleTitle ?? (roles.length > 0 ? roles[0] : 'Bruce Fire');
    const userName = auth?.user?.name ?? 'Usuario';

    return (
        <>
            {open && (
                <div
                    onClick={onClose}
                    className="fixed inset-0 z-40 bg-black/60 backdrop-blur-xs transition-opacity duration-200 ease-out lg:hidden"
                    aria-hidden="true"
                />
            )}

            <aside
                id="app-navigation-sidebar"
                aria-label="Menú principal"
                data-collapsed={collapsed}
                className={[
                    'bf-sidebar fixed inset-y-0 left-0 z-50 flex h-full w-[270px] shrink-0 flex-col bg-sidebar text-sidebar-foreground transition-[width,transform] duration-200 ease-out lg:static lg:h-full lg:translate-x-0',
                    open
                        ? 'visible translate-x-0'
                        : 'invisible -translate-x-full lg:visible',
                ].join(' ')}
            >
                <div className="bf-sidebar-brand flex h-[96px] shrink-0 items-center gap-2.5 px-6">
                    <img
                        src="/brand/logo-blanco.svg"
                        alt="Bruce Fire"
                        className="bf-full-logo h-auto w-[222px] min-w-0 object-contain"
                    />
                    <img
                        src="/brand/logo-icon.svg"
                        alt="Bruce Fire"
                        className="bf-compact-logo hidden size-9"
                    />
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label="Cerrar menú"
                        className="text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-foreground flex size-8 shrink-0 items-center justify-center rounded-lg transition-colors active:scale-95 lg:hidden"
                    >
                        <X className="size-4" />
                    </button>
                </div>

                <nav className="flex flex-1 flex-col gap-1 overflow-y-auto overscroll-contain py-3.5 pr-0 pl-3.5">
                    {groups.map((group, groupIndex) => (
                        <div key={group.label ?? `group-${groupIndex}`}>
                            {group.label && (
                                <div className="bf-nav-label text-sidebar-foreground/50 px-3 pt-5 pb-1.5 font-['IBM_Plex_Mono',monospace] text-[9.5px] tracking-[0.08em] uppercase">
                                    {group.label}
                                </div>
                            )}

                            {group.items.map((item) => {
                                const Icon = item.icon;
                                const isActive = customIsActive
                                    ? customIsActive(
                                          currentPath,
                                          item.href,
                                          item.activeMatch,
                                      )
                                    : defaultIsItemActive(
                                          currentPath,
                                          item.href,
                                          item.activeMatch,
                                      );

                                const countText = formatCount(item.count);

                                return (
                                    <Link
                                        key={item.title}
                                        href={item.href}
                                        prefetch
                                        onClick={onClose}
                                        aria-current={
                                            isActive ? 'page' : undefined
                                        }
                                        title={
                                            collapsed ? item.title : undefined
                                        }
                                        className={[
                                            'group relative flex h-11 items-center gap-3 text-[13px] no-underline transition-[background-color,color,transform] duration-150 active:scale-[0.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sidebar-ring',
                                            isActive
                                                ? 'bf-nav-active rounded-l-2xl pl-3.5 pr-3.5 font-semibold'
                                                : 'mr-3.5 rounded-xl px-3.5 font-medium text-sidebar-foreground/75 hover:bg-sidebar-accent/60 hover:text-sidebar-foreground',
                                        ].join(' ')}
                                    >
                                        <Icon
                                            className={[
                                                'size-4 shrink-0 transition-colors',
                                                isActive
                                                    ? 'text-primary'
                                                    : 'text-sidebar-foreground/70 group-hover:text-sidebar-foreground',
                                            ].join(' ')}
                                            strokeWidth={2}
                                        />
                                        <span className="bf-nav-text min-w-0 flex-1 truncate">
                                            {item.title}
                                        </span>
                                        {countText && (
                                            <span
                                                className={[
                                                    'bf-nav-count rounded-full px-1.5 py-px text-[10px] font-mono',
                                                    isActive
                                                        ? 'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-200'
                                                        : 'bg-sidebar-accent text-sidebar-foreground/90',
                                                ].join(' ')}
                                            >
                                                {countText}
                                            </span>
                                        )}
                                    </Link>
                                );
                            })}
                        </div>
                    ))}
                </nav>

                <div className="border-sidebar-border bf-sidebar-footer flex shrink-0 items-center justify-between gap-2.5 border-t px-4 py-3.5">
                    <div className="flex min-w-0 items-center gap-2.5">
                        <div className="bg-primary text-primary-foreground flex size-9 shrink-0 items-center justify-center rounded-[10px] text-xs font-bold shadow-xs">
                            {getInitials(userName)}
                        </div>
                        <div className="bf-nav-text min-w-0">
                            <div className="text-sidebar-foreground truncate text-[12.5px] font-semibold whitespace-nowrap">
                                {userName}
                            </div>
                            <div className="text-sidebar-foreground/60 font-['IBM_Plex_Mono',monospace] text-[9.5px]">
                                {userRole}
                            </div>
                        </div>
                    </div>
                    <Link
                        href={logout()}
                        as="button"
                        className="bf-nav-text text-sidebar-foreground/60 hover:text-destructive hover:bg-sidebar-accent focus-visible:ring-sidebar-ring flex size-8 shrink-0 items-center justify-center rounded-lg transition-colors focus-visible:ring-2 focus-visible:outline-none"
                        title="Cerrar sesión"
                        aria-label="Cerrar sesión"
                    >
                        <LogOut className="size-4" strokeWidth={2} />
                    </Link>
                </div>
            </aside>
        </>
    );
}
