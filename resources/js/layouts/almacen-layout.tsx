import { Head } from '@inertiajs/react';
import ChispaWidget from '@/components/chispa-widget';
import { Bell, Menu, PanelLeftClose, PanelLeftOpen } from 'lucide-react';
import { useState, type ReactNode } from 'react';

import { AlmacenSidebar } from '@/components/almacen-sidebar';
import { ThemeToggle } from '@/components/theme-toggle';

type AlmacenLayoutProps = {
    children: ReactNode;
    title: string;
};

export default function AlmacenLayout({ children, title }: AlmacenLayoutProps) {
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [sidebarCollapsed, setSidebarCollapsed] = useState(false);

    return (
        <>
            <Head title={title} />

            {/* Shell con altura completa y desbordamiento controlado (sin scroll en el body) */}
            <div className="bf-workspace bg-background text-foreground flex h-dvh w-full overflow-hidden">
                <AlmacenSidebar
                    open={sidebarOpen}
                    onClose={() => setSidebarOpen(false)}
                    collapsed={sidebarCollapsed}
                />

                <div className="flex h-full min-w-0 flex-1 flex-col overflow-hidden">
                    <header className="border-border bg-background relative z-30 flex h-[76px] shrink-0 items-center gap-2 px-4 transition-colors sm:gap-3.5 lg:px-[30px]">
                        <button
                            type="button"
                            onClick={() => setSidebarOpen(true)}
                            aria-label="Abrir menú"
                            aria-expanded={sidebarOpen}
                            aria-controls="app-navigation-sidebar"
                            className="border-border bg-card text-muted-foreground hover:bg-accent hover:text-foreground flex size-9 shrink-0 items-center justify-center rounded-xl border transition-[color,background-color,transform] duration-150 active:scale-95 lg:hidden"
                        >
                            <Menu className="size-4" strokeWidth={2} />
                        </button>

                        <button
                            type="button"
                            onClick={() =>
                                setSidebarCollapsed(!sidebarCollapsed)
                            }
                            aria-label={
                                sidebarCollapsed
                                    ? 'Expandir menú'
                                    : 'Contraer menú'
                            }
                            aria-expanded={!sidebarCollapsed}
                            aria-controls="app-navigation-sidebar"
                            className="border-border bg-card text-muted-foreground hidden size-9 shrink-0 items-center justify-center rounded-xl border transition-[color,background-color,transform] duration-150 active:scale-95 lg:flex"
                        >
                            {sidebarCollapsed ? (
                                <PanelLeftOpen className="size-4" />
                            ) : (
                                <PanelLeftClose className="size-4" />
                            )}
                        </button>

                        <div className="text-muted-foreground min-w-0 flex-1 truncate text-xs">
                            Almacén
                            <span className="mx-1.5 opacity-60">›</span>
                            <b className="text-foreground font-bold">{title}</b>
                        </div>

                        <ThemeToggle />

                        <button
                            type="button"
                            className="border-border bg-card text-muted-foreground hover:bg-accent hover:text-foreground relative flex size-[38px] shrink-0 items-center justify-center rounded-xl border transition-[color,background-color,transform] duration-150 active:scale-95"
                            aria-label="Notificaciones"
                        >
                            <Bell className="size-4" strokeWidth={2} />
                            <span className="border-card bg-primary absolute top-[7px] right-[7px] size-[7px] rounded-full border-[1.5px]" />
                        </button>
                    </header>

                    {/* Único contenedor de scroll vertical: sidebar y header quedan estáticos */}
                    <main className="flex-1 overflow-y-auto overscroll-contain px-4 py-6 pb-32 outline-none lg:px-8">
                        <div className="w-full">{children}</div>
                    </main>
                </div>
            </div>
            <ChispaWidget rol="almacen" />
        </>
    );
}
