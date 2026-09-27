import { Head, Link, usePage } from '@inertiajs/react';
import ChispaWidget from '@/components/chispa-widget';
import {
    AlertTriangle,
    Bell,
    CheckCircle2,
    Clock,
    LogOut,
    Menu,
    UserX,
    X,
} from 'lucide-react';
import { useState, type ReactNode } from 'react';

import { ThemeToggle } from '@/components/theme-toggle';
import { VendedorSidebar } from '@/components/vendedor-sidebar';
import { useInitials } from '@/hooks/use-initials';
import { logout } from '@/routes';
import ordenesServicio from '@/routes/vendedor/ordenes-servicio';
import type { Auth } from '@/types';

type AlertaTopItem = {
    id: string;
    tipo: string;
    titulo: string;
    mensaje: string;
    url: string;
    fecha: string;
    urgencia: 'alta' | 'media' | 'baja';
};

type VendedorPageProps = {
    auth: Auth & {
        roles?: string[];
    };
    alertasTop?: AlertaTopItem[];
    currentTeam?: { slug: string } | null;
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
    const {
        auth,
        alertasTop = [],
        currentTeam,
    } = usePage<VendedorPageProps>().props;
    const getInitials = useInitials();
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [notifsOpen, setNotifsOpen] = useState(false);
    const teamSlug = currentTeam?.slug ?? 'bruce-fire';

    return (
        <>
            <Head title={title} />

            {/* Shell con altura completa y desbordamiento controlado (sin scroll en el body) */}
            <div className="bg-background text-foreground flex h-screen w-full overflow-hidden">
                <VendedorSidebar
                    open={sidebarOpen}
                    onClose={() => setSidebarOpen(false)}
                />

                <div className="flex h-full min-w-0 flex-1 flex-col overflow-hidden">
                    <header className="border-border bg-card relative z-30 flex h-[66px] shrink-0 items-center gap-2 border-b px-4 transition-colors sm:gap-3.5 lg:px-[30px]">
                        <button
                            type="button"
                            onClick={() => setSidebarOpen(true)}
                            aria-label="Abrir menú"
                            className="border-border bg-card text-muted-foreground hover:bg-accent hover:text-foreground flex size-9 shrink-0 items-center justify-center rounded-[9px] border transition-colors lg:hidden"
                        >
                            <Menu className="size-4" strokeWidth={2} />
                        </button>

                        <div className="text-muted-foreground min-w-0 flex-1 truncate text-xs">
                            Panel
                            <span className="mx-1.5 opacity-60">›</span>
                            <b className="text-foreground font-bold">{title}</b>
                        </div>

                        <ThemeToggle />

                        {/* Campana de avisos con popover interactivo */}
                        <div className="relative">
                            <button
                                type="button"
                                onClick={() => setNotifsOpen(!notifsOpen)}
                                className="border-border bg-card text-muted-foreground hover:bg-accent hover:text-foreground relative flex size-[38px] shrink-0 items-center justify-center rounded-[9px] border transition-colors"
                                aria-label="Notificaciones"
                                aria-expanded={notifsOpen}
                            >
                                <Bell className="size-4" strokeWidth={2} />
                                {alertasTop.length > 0 && (
                                    <span className="bg-destructive text-destructive-foreground animate-in zoom-in-75 absolute -top-1 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] font-bold shadow-xs">
                                        {alertasTop.length}
                                    </span>
                                )}
                            </button>

                            {notifsOpen && (
                                <>
                                    <div
                                        className="fixed inset-0 z-40 bg-black/10 backdrop-blur-[1px]"
                                        onClick={() => setNotifsOpen(false)}
                                    />
                                    <div className="border-border bg-card animate-in fade-in-50 zoom-in-95 absolute top-full right-0 z-50 mt-2 w-80 rounded-2xl border p-4 shadow-xl sm:w-96">
                                        <div className="border-border flex items-center justify-between border-b pb-3">
                                            <div className="flex items-center gap-2">
                                                <Bell className="text-primary size-4" />
                                                <h4 className="text-foreground text-xs font-bold tracking-wider uppercase">
                                                    Avisos y Pendientes
                                                </h4>
                                                {alertasTop.length > 0 && (
                                                    <span className="bg-primary/10 text-primary rounded-full px-2 py-0.5 text-[10px] font-bold">
                                                        {alertasTop.length}
                                                    </span>
                                                )}
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setNotifsOpen(false)
                                                }
                                                className="text-muted-foreground hover:bg-accent hover:text-foreground rounded-lg p-1"
                                                aria-label="Cerrar avisos"
                                            >
                                                <X className="size-3.5" />
                                            </button>
                                        </div>

                                        <div className="divide-border/60 mt-2 max-h-[360px] divide-y overflow-y-auto">
                                            {alertasTop.length === 0 ? (
                                                <div className="text-muted-foreground flex flex-col items-center justify-center py-8 text-center">
                                                    <CheckCircle2 className="mb-2 size-7 text-emerald-500 opacity-80" />
                                                    <p className="text-foreground text-xs font-semibold">
                                                        Todo al día
                                                    </p>
                                                    <p className="mt-0.5 text-[11px]">
                                                        No tienes avisos
                                                        urgentes ni pendientes
                                                        de atención.
                                                    </p>
                                                </div>
                                            ) : (
                                                alertasTop.map((alerta) => (
                                                    <Link
                                                        key={alerta.id}
                                                        href={alerta.url}
                                                        onClick={() =>
                                                            setNotifsOpen(false)
                                                        }
                                                        className="hover:bg-accent/60 group flex items-start gap-3 rounded-xl p-2.5 text-left transition-colors"
                                                    >
                                                        <div
                                                            className={`mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-lg ${
                                                                alerta.urgencia ===
                                                                'alta'
                                                                    ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400'
                                                                    : alerta.tipo ===
                                                                        'lista_entrega'
                                                                      ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                                                      : 'bg-blue-500/10 text-blue-600 dark:text-blue-400'
                                                            }`}
                                                        >
                                                            {alerta.urgencia ===
                                                            'alta' ? (
                                                                <AlertTriangle className="size-3.5" />
                                                            ) : alerta.tipo ===
                                                              'lista_entrega' ? (
                                                                <CheckCircle2 className="size-3.5" />
                                                            ) : (
                                                                <UserX className="size-3.5" />
                                                            )}
                                                        </div>
                                                        <div className="min-w-0 flex-1">
                                                            <p className="text-foreground group-hover:text-primary text-xs font-bold transition-colors">
                                                                {alerta.titulo}
                                                            </p>
                                                            <p className="text-muted-foreground mt-0.5 line-clamp-2 text-[11px]">
                                                                {alerta.mensaje}
                                                            </p>
                                                            <div className="text-muted-foreground/80 mt-1 flex items-center gap-1 text-[10px]">
                                                                <Clock className="size-3" />
                                                                <span>
                                                                    {
                                                                        alerta.fecha
                                                                    }
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </Link>
                                                ))
                                            )}
                                        </div>

                                        <div className="border-border mt-3 flex items-center justify-between border-t pt-2.5">
                                            <Link
                                                href={ordenesServicio.index.url(
                                                    teamSlug,
                                                )}
                                                onClick={() =>
                                                    setNotifsOpen(false)
                                                }
                                                className="text-primary text-[11px] font-semibold hover:underline"
                                            >
                                                Ver todas las órdenes de
                                                servicio →
                                            </Link>
                                        </div>
                                    </div>
                                </>
                            )}
                        </div>

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
                    <main className="flex-1 overflow-y-auto overscroll-contain px-4 pt-[22px] pb-32 lg:px-7">
                        {children}
                    </main>
                </div>
            </div>
            <ChispaWidget rol="vendedor" />
        </>
    );
}
