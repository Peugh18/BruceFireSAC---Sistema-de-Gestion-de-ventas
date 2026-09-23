import { Link, usePage } from '@inertiajs/react';
import {
    CalendarCheck,
    ClipboardCheck,
    Flame,
    LayoutDashboard,
    LogOut,
    MapPin,
    Truck,
    type LucideIcon,
} from 'lucide-react';
import { ReactNode } from 'react';

import { ThemeToggle } from '@/components/theme-toggle';
import { useInitials } from '@/hooks/use-initials';
import type { Auth, Team } from '@/types';

type PageProps = {
    auth: Auth & {
        roles?: string[];
    };
    currentTeam?: Team | null;
    [key: string]: unknown;
};

type NavItem = {
    title: string;
    href: string;
    icon: LucideIcon;
    activeMatch: string;
    badge?: number | string;
};

export default function TecnicoCampoLayout({
    children,
    title,
}: {
    children: ReactNode;
    title?: string;
}) {
    const page = usePage<PageProps>();
    const teamSlug = page.props.currentTeam?.slug ?? '';
    const user = page.props.auth?.user;
    const getInitials = useInitials();
    const initials = user?.name ? getInitials(user.name) : 'TC';

    const currentPath = page.url;

    const navItems: NavItem[] = [
        {
            title: 'Inicio',
            href: `/${teamSlug}/tecnico-campo/dashboard`,
            icon: LayoutDashboard,
            activeMatch: '/tecnico-campo/dashboard',
        },
        {
            title: 'Recojos',
            href: `/${teamSlug}/tecnico-campo/recojos`,
            icon: Truck,
            activeMatch: '/tecnico-campo/recojos',
        },
        {
            title: 'Inspección',
            href: `/${teamSlug}/tecnico-campo/inspecciones`,
            icon: ClipboardCheck,
            activeMatch: '/tecnico-campo/inspecciones',
        },
        {
            title: 'Instalación',
            href: `/${teamSlug}/tecnico-campo/instalaciones`,
            icon: MapPin,
            activeMatch: '/tecnico-campo/instalaciones',
        },
        {
            title: 'Entregas',
            href: `/${teamSlug}/tecnico-campo/entregas`,
            icon: CalendarCheck,
            activeMatch: '/tecnico-campo/entregas',
        },
    ];

    return (
        <div className="flex h-screen w-full flex-col overflow-hidden bg-background text-foreground antialiased">
            {/* Mobile Top Header (Fixed top, isolated from scroll) */}
            <header className="shrink-0 flex h-14 w-full items-center justify-between border-b border-border bg-card px-4 shadow-xs transition-colors">
                <div className="flex items-center gap-2.5">
                    <div className="flex size-8 items-center justify-center rounded-[8px] bg-info text-info-foreground shadow-xs">
                        <Flame className="size-4" />
                    </div>
                    <div>
                        <div className="flex items-center gap-1.5">
                            <span className="text-xs font-black tracking-tight text-foreground">
                                BRUCE FIRE
                            </span>
                            <span className="py-0.2 inline-flex items-center rounded-full border border-sky-500/30 bg-sky-500/10 px-1.5 text-[9.5px] font-black tracking-wide text-sky-600 dark:text-sky-400">
                                CAMPO
                            </span>
                        </div>
                        <p className="max-w-[140px] truncate text-[10px] leading-none font-medium text-muted-foreground">
                            {page.props.currentTeam?.name ?? 'Operaciones de Campo'}
                        </p>
                    </div>
                </div>

                {/* User badge + theme toggle + logout */}
                <div className="flex items-center gap-2">
                    <ThemeToggle className="size-8 rounded-[8px]" />

                    <div className="flex items-center gap-1.5 rounded-[8px] border border-border bg-muted/40 px-2.5 py-1 text-xs">
                        <div className="flex size-5 items-center justify-center rounded-full bg-info text-[9px] font-bold text-info-foreground">
                            {initials}
                        </div>
                        <span className="max-w-[90px] truncate text-[11px] font-semibold text-foreground">
                            {user?.name?.split(' ')[0] ?? 'Técnico'}
                        </span>
                    </div>

                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        className="flex size-8 items-center justify-center rounded-[8px] border border-border bg-card text-muted-foreground hover:bg-destructive/10 hover:text-destructive transition-colors"
                        title="Cerrar sesión"
                    >
                        <LogOut className="size-3.5" />
                    </Link>
                </div>
            </header>

            {/* Desktop Navigation Pills (visible only on md and larger) */}
            <nav className="hidden shrink-0 gap-2 border-b border-border bg-card px-6 py-2 shadow-xs md:flex transition-colors">
                {navItems.map((item) => {
                    const Icon = item.icon;
                    const isActive = currentPath.includes(item.activeMatch);
                    return (
                        <Link
                            key={item.href}
                            href={item.href}
                            className={`flex items-center gap-2 rounded-[8px] px-3.5 py-1.5 text-xs font-bold transition-all ${
                                isActive
                                    ? 'bg-info text-info-foreground shadow-xs'
                                    : 'text-muted-foreground hover:bg-accent hover:text-foreground'
                            }`}
                        >
                            <Icon className="size-4" />
                            <span>{item.title}</span>
                        </Link>
                    );
                })}
            </nav>

            {/* Main Content Area: único scroll, pb-28 para que la barra inferior táctil nunca tape contenido */}
            <main className="mx-auto w-full max-w-2xl flex-1 overflow-y-auto px-4 py-5 pb-28 md:pb-8 overscroll-contain">
                {children}
            </main>

            {/* Mobile Bottom Navigation Bar (Fixed bottom, touch-friendly min 48px) */}
            <nav className="fixed right-0 bottom-0 left-0 z-40 flex h-16 items-center justify-around border-t border-border bg-card/95 px-2 pb-[env(safe-area-inset-bottom)] shadow-[0_-4px_12px_rgba(0,0,0,0.06)] backdrop-blur-md md:hidden transition-colors">
                {navItems.map((item) => {
                    const Icon = item.icon;
                    const isActive = currentPath.includes(item.activeMatch);
                    return (
                        <Link
                            key={item.href}
                            href={item.href}
                            className={`flex min-h-[44px] min-w-[64px] flex-col items-center justify-center rounded-[10px] px-2 py-1 transition-all ${
                                isActive
                                    ? 'text-info font-bold'
                                    : 'text-muted-foreground hover:text-foreground active:scale-95'
                            }`}
                        >
                            <div
                                className={`rounded-[8px] p-1 ${isActive ? 'bg-info/10' : ''}`}
                            >
                                <Icon
                                    className={`size-5 ${isActive ? 'stroke-[2.5]' : 'stroke-2'}`}
                                />
                            </div>
                            <span
                                className={`text-[10px] tracking-tight ${isActive ? 'font-black' : 'font-semibold'}`}
                            >
                                {item.title}
                            </span>
                        </Link>
                    );
                })}
            </nav>
        </div>
    );
}
