import { Head, Link, usePage } from '@inertiajs/react';
import ChispaWidget from '@/components/chispa-widget';
import { Bell, Menu, PanelLeftClose, PanelLeftOpen } from 'lucide-react';
import { useState, type ReactNode } from 'react';

import { GerenteSidebar } from '@/components/gerente-sidebar';
import { ThemeToggle } from '@/components/theme-toggle';
import cajasRoutes from '@/routes/gerente/cajas';
import type { Team } from '@/types';

type GerenteLayoutProps = {
    children: ReactNode;
    title: string;
};

export default function GerenteLayout({ children, title }: GerenteLayoutProps) {
    const { currentTeam, cierresConDiferencia = 0 } = usePage<{
        currentTeam?: Team | null;
        cierresConDiferencia?: number;
    }>().props;
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [sidebarCollapsed, setSidebarCollapsed] = useState(false);

    return (
        <>
            <Head title={title} />

            {/* Shell con altura completa y desbordamiento controlado (sin scroll en el body) */}
            <div className="bf-workspace bg-background text-foreground flex h-dvh w-full overflow-hidden">
                <GerenteSidebar
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
                            className="border-border bg-card text-muted-foreground hover:bg-accent hover:text-foreground flex size-11 shrink-0 items-center justify-center rounded-xl border transition-[color,background-color,transform] duration-150 active:scale-95 lg:hidden"
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
                            Gerencia
                            <span className="mx-1.5 opacity-60">›</span>
                            <b className="text-foreground font-bold">{title}</b>
                        </div>

                        <ThemeToggle />

                        <Link
                            href={`${cajasRoutes.index.url(currentTeam?.slug ?? '')}?con_diferencia=1`}
                            className="border-border bg-card text-muted-foreground hover:bg-accent hover:text-foreground relative flex size-11 shrink-0 items-center justify-center rounded-xl border transition-[color,background-color,transform] duration-150 active:scale-95"
                            aria-label={
                                cierresConDiferencia > 0
                                    ? `${cierresConDiferencia} cierres de caja con dinero de más o de menos esta semana`
                                    : 'Sin avisos: las cajas de esta semana cuadran'
                            }
                            title={
                                cierresConDiferencia > 0
                                    ? 'Cierres de caja con diferencia'
                                    : 'Sin avisos'
                            }
                        >
                            <Bell className="size-4" strokeWidth={2} />
                            {cierresConDiferencia > 0 && (
                                <span className="bg-destructive text-destructive-foreground absolute -top-1 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-xs font-bold">
                                    {cierresConDiferencia}
                                </span>
                            )}
                        </Link>
                    </header>

                    {/* Único contenedor de scroll vertical: sidebar y header quedan estáticos */}
                    <main
                        tabIndex={0}
                        aria-label="Contenido principal"
                        className="focus-visible:outline-ring flex-1 overflow-y-auto overscroll-contain px-4 py-6 pb-32 outline-none focus-visible:outline-2 focus-visible:outline-offset-[-2px] lg:px-8"
                    >
                        <div className="w-full">{children}</div>
                    </main>
                </div>
            </div>
            <ChispaWidget rol="gerente" />
        </>
    );
}
