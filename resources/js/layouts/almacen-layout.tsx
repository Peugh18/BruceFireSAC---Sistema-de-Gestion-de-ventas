import { Head, Link, usePage } from '@inertiajs/react';
import ChispaWidget from '@/components/chispa-widget';
import { Bell, LogOut, Menu } from 'lucide-react';
import { useState, type ReactNode } from 'react';

import { AlmacenSidebar } from '@/components/almacen-sidebar';
import { ThemeToggle } from '@/components/theme-toggle';
import { useInitials } from '@/hooks/use-initials';
import { logout } from '@/routes';
import type { Auth } from '@/types';

type AlmacenPageProps = {
    auth: Auth & {
        roles?: string[];
    };
    [key: string]: unknown;
};

type AlmacenLayoutProps = {
    children: ReactNode;
    title: string;
};

export default function AlmacenLayout({ children, title }: AlmacenLayoutProps) {
    const { auth } = usePage<AlmacenPageProps>().props;
    const getInitials = useInitials();
    const [sidebarOpen, setSidebarOpen] = useState(false);

    return (
        <>
            <Head title={title} />

            {/* Shell con altura completa y desbordamiento controlado (sin scroll en el body) */}
            <div className="bg-background text-foreground flex h-screen w-full overflow-hidden">
                <AlmacenSidebar
                    open={sidebarOpen}
                    onClose={() => setSidebarOpen(false)}
                />

                <div className="flex h-full min-w-0 flex-1 flex-col overflow-hidden">
                    <header className="border-border bg-card flex h-[66px] shrink-0 items-center gap-2 border-b px-4 transition-colors sm:gap-3.5 lg:px-[30px]">
                        <button
                            type="button"
                            onClick={() => setSidebarOpen(true)}
                            aria-label="Abrir menú"
                            className="border-border bg-card text-muted-foreground hover:bg-accent hover:text-foreground flex size-9 shrink-0 items-center justify-center rounded-[9px] border transition-colors lg:hidden"
                        >
                            <Menu className="size-4" strokeWidth={2} />
                        </button>

                        <div className="text-muted-foreground min-w-0 flex-1 truncate text-xs">
                            Almacén
                            <span className="mx-1.5 opacity-60">›</span>
                            <b className="text-foreground font-bold">{title}</b>
                        </div>

                        <ThemeToggle />

                        <button
                            type="button"
                            className="border-border bg-card text-muted-foreground hover:bg-accent hover:text-foreground relative flex size-[38px] shrink-0 items-center justify-center rounded-[9px] border transition-colors"
                            aria-label="Notificaciones"
                        >
                            <Bell className="size-4" strokeWidth={2} />
                            <span className="border-card bg-primary absolute top-[7px] right-[7px] size-[7px] rounded-full border-[1.5px]" />
                        </button>

                        <div
                            className="bg-primary text-primary-foreground flex size-9 shrink-0 items-center justify-center rounded-[10px] text-xs font-bold shadow-xs"
                            title={auth.user.name}
                        >
                            {getInitials(auth.user.name)}
                        </div>

                        <Link
                            href={logout()}
                            as="button"
                            className="border-border bg-card text-muted-foreground hover:bg-destructive/10 hover:text-destructive hover:border-destructive/30 flex size-9 shrink-0 items-center justify-center rounded-[9px] border transition-colors"
                            title="Cerrar sesión"
                        >
                            <LogOut className="size-4" strokeWidth={2} />
                        </Link>
                    </header>

                    {/* Único contenedor de scroll vertical: sidebar y header quedan estáticos */}
                    <main className="flex-1 overflow-y-auto overscroll-contain p-4 pb-32 lg:p-[30px] lg:pb-32">
                        {children}
                    </main>
                </div>
            </div>
            <ChispaWidget rol="almacen" />
        </>
    );
}
