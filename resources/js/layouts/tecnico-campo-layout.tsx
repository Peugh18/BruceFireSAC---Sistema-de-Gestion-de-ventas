import { Link, usePage } from '@inertiajs/react';
import {
    CalendarCheck,
    ClipboardCheck,
    Flame,
    LayoutDashboard,
    LogOut,
    MapPin,
    Truck,
    User,
    type LucideIcon,
} from 'lucide-react';
import { ReactNode } from 'react';

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

    const currentPath = typeof window !== 'undefined' ? window.location.pathname : '';

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
        <div className="min-h-screen bg-[#F7F6F3] text-[#201F1D] flex flex-col antialiased">
            {/* Mobile Top Header (Sticky) */}
            <header className="sticky top-0 z-30 flex h-14 w-full items-center justify-between border-b border-[#E4E1DC] bg-white px-4 shadow-sm">
                <div className="flex items-center gap-2.5">
                    <div className="flex size-8 items-center justify-center rounded-[8px] bg-[#0284C7] text-white shadow-sm">
                        <Flame className="size-4" />
                    </div>
                    <div>
                        <div className="flex items-center gap-1.5">
                            <span className="text-xs font-black tracking-tight text-[#201F1D]">BRUCE FIRE</span>
                            <span className="inline-flex items-center rounded-full border border-[#BAE6FD] bg-[#E0F2FE] px-1.5 py-0.2 text-[9.5px] font-black tracking-wide text-[#0369A1]">
                                CAMPO
                            </span>
                        </div>
                        <p className="text-[10px] text-[#6B6965] font-medium leading-none truncate max-w-[140px]">
                            {page.props.currentTeam?.name ?? 'Operaciones de Campo'}
                        </p>
                    </div>
                </div>

                {/* User badge */}
                <div className="flex items-center gap-2">
                    <div className="flex items-center gap-1.5 rounded-[8px] border border-[#E4E1DC] bg-[#FAF9F7] px-2.5 py-1 text-xs">
                        <div className="flex size-5 items-center justify-center rounded-full bg-[#0284C7] text-[9px] font-bold text-white">
                            {initials}
                        </div>
                        <span className="font-semibold text-[11px] text-[#201F1D] max-w-[90px] truncate">
                            {user?.name?.split(' ')[0] ?? 'Técnico'}
                        </span>
                    </div>

                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        className="flex size-8 items-center justify-center rounded-[8px] border border-[#E4E1DC] bg-white text-[#6B6965] hover:bg-[#FEF2F2] hover:text-[#DC2626]"
                        title="Cerrar sesión"
                    >
                        <LogOut className="size-3.5" />
                    </Link>
                </div>
            </header>

            {/* Desktop Navigation Pills (visible only on md and larger) */}
            <nav className="hidden md:flex border-b border-[#E4E1DC] bg-white px-6 py-2 gap-2 shadow-xs">
                {navItems.map((item) => {
                    const Icon = item.icon;
                    const isActive = currentPath.includes(item.activeMatch);
                    return (
                        <Link
                            key={item.href}
                            href={item.href}
                            className={`flex items-center gap-2 rounded-[8px] px-3.5 py-1.5 text-xs font-bold transition-all ${
                                isActive
                                    ? 'bg-[#0284C7] text-white shadow-xs'
                                    : 'text-[#6B6965] hover:bg-[#F3F1ED] hover:text-[#201F1D]'
                            }`}
                        >
                            <Icon className="size-4" />
                            <span>{item.title}</span>
                        </Link>
                    );
                })}
            </nav>

            {/* Main Content Area (padding-bottom prevents mobile navbar overlap) */}
            <main className="flex-1 w-full max-w-2xl mx-auto px-4 py-5 pb-24 md:pb-8">
                {children}
            </main>

            {/* Mobile Bottom Navigation Bar (Fixed bottom, touch-friendly min 48px) */}
            <nav className="md:hidden fixed bottom-0 left-0 right-0 z-40 flex h-16 items-center justify-around border-t border-[#E4E1DC] bg-white/95 px-2 backdrop-blur-md pb-[env(safe-area-inset-bottom)] shadow-[0_-4px_12px_rgba(0,0,0,0.04)]">
                {navItems.map((item) => {
                    const Icon = item.icon;
                    const isActive = currentPath.includes(item.activeMatch);
                    return (
                        <Link
                            key={item.href}
                            href={item.href}
                            className={`flex flex-col items-center justify-center min-w-[64px] min-h-[44px] rounded-[10px] px-2 py-1 transition-all ${
                                isActive
                                    ? 'text-[#0284C7]'
                                    : 'text-[#6B6965] active:scale-95 hover:text-[#201F1D]'
                            }`}
                        >
                            <div className={`p-1 rounded-[8px] ${isActive ? 'bg-[#E0F2FE]' : ''}`}>
                                <Icon className={`size-5 ${isActive ? 'stroke-[2.5]' : 'stroke-2'}`} />
                            </div>
                            <span className={`text-[10px] tracking-tight ${isActive ? 'font-black' : 'font-semibold'}`}>
                                {item.title}
                            </span>
                        </Link>
                    );
                })}
            </nav>
        </div>
    );
}
