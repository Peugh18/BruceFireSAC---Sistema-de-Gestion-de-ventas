import { Link, usePage } from '@inertiajs/react';
import ChispaWidget from '@/components/chispa-widget';
import {
    AlertTriangle,
    LayoutDashboard,
    LogOut,
    PackageCheck,
    type LucideIcon,
} from 'lucide-react';
import { ReactNode } from 'react';

import { MobileNavDock } from '@/components/mobile-nav-dock';
import { ThemeToggle } from '@/components/theme-toggle';
import { useInitials } from '@/hooks/use-initials';
import { dashboard } from '@/routes/tecnico-planta';
import deficiencias from '@/routes/tecnico-planta/deficiencias';
import recepciones from '@/routes/tecnico-planta/recepciones';
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

export default function TecnicoPlantaLayout({
    children,
}: {
    children: ReactNode;
    title?: string;
}) {
    const page = usePage<PageProps>();
    const teamSlug = page.props.currentTeam?.slug ?? '';
    const user = page.props.auth?.user;
    const getInitials = useInitials();
    const initials = user?.name ? getInitials(user.name) : 'TP';

    const currentPath = page.url;

    const navItems: NavItem[] = [
        {
            title: 'Inicio',
            href: dashboard.url(teamSlug),
            icon: LayoutDashboard,
            activeMatch: '/tecnico-planta/dashboard',
        },
        {
            title: 'Recepción',
            href: recepciones.index.url(teamSlug),
            icon: PackageCheck,
            activeMatch: '/tecnico-planta/recepciones',
        },
        {
            title: 'Deficiencias',
            href: deficiencias.index.url(teamSlug),
            icon: AlertTriangle,
            activeMatch: '/tecnico-planta/deficiencias',
        },
    ];

    return (
        <div className="bg-background text-foreground flex h-screen w-full flex-col overflow-hidden antialiased">
            {/* Mobile Top Header (Fixed top, isolated from scroll) */}
            <header className="border-border bg-card flex h-14 w-full shrink-0 items-center justify-between border-b px-4 shadow-xs transition-colors">
                <div className="flex items-center gap-2.5">
                    <img
                        src="/brand/logo-icon.png"
                        alt="Bruce Fire"
                        className="size-8 shrink-0"
                    />
                    <div>
                        <div className="flex items-center gap-1.5">
                            <span className="text-foreground text-xs font-black tracking-tight">
                                BRUCE FIRE
                            </span>
                            <span className="py-0.2 text-warning-strong inline-flex items-center rounded-full border border-amber-500/30 bg-amber-500/10 px-1.5 text-[9.5px] font-black tracking-wide">
                                PLANTA
                            </span>
                        </div>
                        <p className="text-muted-foreground max-w-[140px] truncate text-[10px] leading-none font-medium">
                            {page.props.currentTeam?.name ?? 'Taller Principal'}
                        </p>
                    </div>
                </div>

                {/* User badge + theme toggle + logout */}
                <div className="flex items-center gap-2">
                    <ThemeToggle className="size-8 rounded-[8px]" />

                    <div className="border-border bg-muted/40 flex items-center gap-1.5 rounded-[8px] border px-2.5 py-1 text-xs">
                        <div className="bg-primary text-primary-foreground flex size-5 items-center justify-center rounded-full text-[9px] font-bold">
                            {initials}
                        </div>
                        <span className="text-foreground max-w-[90px] truncate text-[11px] font-semibold">
                            {user?.name?.split(' ')[0] ?? 'Técnico'}
                        </span>
                    </div>

                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        className="border-border bg-card text-muted-foreground hover:bg-destructive/10 hover:text-destructive-strong flex size-8 items-center justify-center rounded-[8px] border transition-colors"
                        title="Cerrar sesión"
                    >
                        <LogOut className="size-3.5" />
                    </Link>
                </div>
            </header>

            {/* Desktop Navigation Pills (visible only on md and larger) */}
            <nav className="border-border bg-card hidden shrink-0 gap-2 border-b px-6 py-2 shadow-xs transition-colors md:flex">
                {navItems.map((item) => {
                    const Icon = item.icon;
                    const isActive = currentPath.includes(item.activeMatch);
                    return (
                        <Link
                            key={item.href}
                            href={item.href}
                            className={`flex items-center gap-2 rounded-[8px] px-3.5 py-1.5 text-xs font-bold transition-all ${
                                isActive
                                    ? 'bg-primary text-primary-foreground shadow-xs'
                                    : 'text-muted-foreground hover:bg-accent hover:text-foreground'
                            }`}
                        >
                            <Icon className="size-4" />
                            <span>{item.title}</span>
                        </Link>
                    );
                })}
            </nav>

            {/* Main Content Area: único scroll, pb-28 para que la barra inferior móvil nunca tape contenido */}
            <main className="mx-auto w-full max-w-2xl flex-1 overflow-y-auto overscroll-contain px-4 py-5 pb-48 md:pb-32">
                {children}
            </main>

            {/* Mobile Bottom Navigation Bar (Floating Capsule Dock) */}
            <MobileNavDock
                items={navItems.map((item) => ({
                    title: item.title,
                    href: item.href,
                    icon: item.icon,
                    isActive: currentPath.includes(item.activeMatch),
                    badge: item.badge,
                }))}
                roleBadge="PLANTA"
            />
            <ChispaWidget rol="tecnico" />
        </div>
    );
}
