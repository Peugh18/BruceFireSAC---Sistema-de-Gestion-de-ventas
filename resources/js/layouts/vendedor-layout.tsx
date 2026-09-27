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
            <div className="flex h-screen w-full overflow-hidden bg-background text-foreground">
                <VendedorSidebar
                    open={sidebarOpen}
                    onClose={() => setSidebarOpen(false)}
                />

                <div className="flex min-w-0 flex-1 flex-col h-full overflow-hidden">
                    <header className="flex h-[66px] shrink-0 items-center gap-2 border-b border-border bg-card px-4 sm:gap-3.5 lg:px-[30px] transition-colors relative z-30">
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

                        <ThemeToggle />

                        {/* Campana de avisos con popover interactivo */}
                        <div className="relative">
                            <button
                                type="button"
                                onClick={() => setNotifsOpen(!notifsOpen)}
                                className="relative flex size-[38px] shrink-0 items-center justify-center rounded-[9px] border border-border bg-card text-muted-foreground hover:bg-accent hover:text-foreground transition-colors"
                                aria-label="Notificaciones"
                                aria-expanded={notifsOpen}
                            >
                                <Bell className="size-4" strokeWidth={2} />
                                {alertasTop.length > 0 && (
                                    <span className="absolute -top-1 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] font-bold text-destructive-foreground shadow-xs animate-in zoom-in-75">
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
                                    <div className="absolute right-0 top-full mt-2 w-80 sm:w-96 rounded-2xl border border-border bg-card p-4 shadow-xl z-50 animate-in fade-in-50 zoom-in-95">
                                        <div className="flex items-center justify-between pb-3 border-b border-border">
                                            <div className="flex items-center gap-2">
                                                <Bell className="size-4 text-primary" />
                                                <h4 className="text-xs font-bold uppercase tracking-wider text-foreground">
                                                    Avisos y Pendientes
                                                </h4>
                                                {alertasTop.length > 0 && (
                                                    <span className="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-bold text-primary">
                                                        {alertasTop.length}
                                                    </span>
                                                )}
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setNotifsOpen(false)
                                                }
                                                className="rounded-lg p-1 text-muted-foreground hover:bg-accent hover:text-foreground"
                                                aria-label="Cerrar avisos"
                                            >
                                                <X className="size-3.5" />
                                            </button>
                                        </div>

                                        <div className="mt-2 max-h-[360px] overflow-y-auto divide-y divide-border/60">
                                            {alertasTop.length === 0 ? (
                                                <div className="flex flex-col items-center justify-center py-8 text-center text-muted-foreground">
                                                    <CheckCircle2 className="size-7 text-emerald-500 mb-2 opacity-80" />
                                                    <p className="text-xs font-semibold text-foreground">
                                                        Todo al día
                                                    </p>
                                                    <p className="text-[11px] mt-0.5">
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
                                                        className="flex items-start gap-3 p-2.5 rounded-xl hover:bg-accent/60 transition-colors text-left group"
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
                                                            <p className="text-xs font-bold text-foreground group-hover:text-primary transition-colors">
                                                                {alerta.titulo}
                                                            </p>
                                                            <p className="text-[11px] text-muted-foreground line-clamp-2 mt-0.5">
                                                                {alerta.mensaje}
                                                            </p>
                                                            <div className="flex items-center gap-1 mt-1 text-[10px] text-muted-foreground/80">
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

                                        <div className="mt-3 pt-2.5 border-t border-border flex items-center justify-between">
                                            <Link
                                                href={ordenesServicio.index.url(
                                                    teamSlug,
                                                )}
                                                onClick={() =>
                                                    setNotifsOpen(false)
                                                }
                                                className="text-[11px] font-semibold text-primary hover:underline"
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
                    <main className="flex-1 overflow-y-auto px-4 pt-[22px] pb-32 lg:px-7 overscroll-contain">
                        {children}
                    </main>
                </div>
            </div>
            <ChispaWidget rol="vendedor" />
        </>
    );
}
