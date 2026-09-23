import { Head, Link, usePage } from '@inertiajs/react';
import { Bell, LogOut, Menu, Search } from 'lucide-react';
import { useState, type ReactNode } from 'react';

import { ThemeToggle } from '@/components/theme-toggle';
import { VendedorSidebar } from '@/components/vendedor-sidebar';
import { useInitials } from '@/hooks/use-initials';
import { logout } from '@/routes';
import type { Auth } from '@/types';

type VendedorPageProps = {
    auth: Auth & {
        roles?: string[];
    };
    [key: string]: unknown;
};

type VendedorLayoutProps = {
    children: ReactNode;
    title: string;
};

export default function VendedorLayout({
    children,
    title,
}: VendedorLayoutProps) {
    const { auth } = usePage<VendedorPageProps>().props;
    const getInitials = useInitials();
    const [sidebarOpen, setSidebarOpen] = useState(false);

    return (
        <>
            <Head title={title} />

            {/* Shell con altura completa y desbordamiento controlado (sin scroll en el body) */}
            <div className="flex h-screen w-full overflow-hidden bg-background text-foreground">
                <VendedorSidebar
                    open={sidebarOpen}
                    onClose={() => setSidebarOpen(false)}
                />

                <div className="flex min-w-0 flex-1 flex-col h-full overflow-hidden">
                    <header className="flex h-[66px] shrink-0 items-center gap-2 border-b border-border bg-card px-4 sm:gap-3.5 lg:px-[30px] transition-colors">
                        <button
                            type="button"
                            onClick={() => setSidebarOpen(true)}
                            aria-label="Abrir menú"
                            className="flex size-9 shrink-0 items-center justify-center rounded-[9px] border border-border bg-card text-muted-foreground hover:bg-accent hover:text-foreground lg:hidden transition-colors"
                        >
                            <Menu className="size-4" strokeWidth={2} />
                        </button>

                        <div className="min-w-0 flex-1 truncate text-xs text-muted-foreground">
                            Panel
                            <span className="mx-1.5 opacity-60">›</span>
                            <b className="font-bold text-foreground">{title}</b>
                        </div>

                        <div className="hidden h-[38px] w-[230px] items-center gap-2 rounded-[9px] border border-border bg-muted/40 px-3 text-[13px] text-muted-foreground md:flex">
                            <Search
                                className="size-3.5 shrink-0"
                                strokeWidth={2}
                            />
                            <span>Buscar...</span>
                        </div>

                        <ThemeToggle />

                        <button
                            type="button"
                            className="relative flex size-[38px] shrink-0 items-center justify-center rounded-[9px] border border-border bg-card text-muted-foreground hover:bg-accent hover:text-foreground transition-colors"
                            aria-label="Notificaciones"
                        >
                            <Bell className="size-4" strokeWidth={2} />
                            <span className="absolute top-[7px] right-[7px] size-[7px] rounded-full border-[1.5px] border-card bg-primary" />
                        </button>

                        <div
                            className="flex size-9 shrink-0 items-center justify-center rounded-[10px] bg-primary text-xs font-bold text-primary-foreground shadow-xs"
                            title={auth.user.name}
                        >
                            {getInitials(auth.user.name)}
                        </div>

                        <Link
                            href={logout()}
                            as="button"
                            className="flex size-9 shrink-0 items-center justify-center rounded-[9px] border border-border bg-card text-muted-foreground hover:bg-destructive/10 hover:text-destructive hover:border-destructive/30 transition-colors"
                            title="Cerrar sesión"
                        >
                            <LogOut className="size-4" strokeWidth={2} />
                        </Link>
                    </header>

                    {/* Único contenedor de scroll vertical: sidebar y header quedan estáticos */}
                    <main className="flex-1 overflow-y-auto px-4 pt-[22px] pb-8 lg:px-7 overscroll-contain">
                        {children}
                    </main>
                </div>
            </div>
        </>
    );
}
