import { Head, Link, usePage } from '@inertiajs/react';
import ChispaWidget from '@/components/chispa-widget';
import {
    AlertTriangle,
    Bell,
    CheckCircle2,
    Clock,
    Menu,
    PanelLeftClose,
    PanelLeftOpen,
    MapPin,
    UserX,
    X,
} from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';

import { ThemeToggle } from '@/components/theme-toggle';
import { VendedorSidebar } from '@/components/vendedor-sidebar';
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
    currentSede?: { id: number; nombre: string } | null;
    caja_hoy?: { estado: string; fecha_apertura: string | null } | null;
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
        alertasTop = [],
        currentTeam,
        currentSede,
        caja_hoy,
    } = usePage<VendedorPageProps>().props;
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [notifsOpen, setNotifsOpen] = useState(false);
    const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
    const teamSlug = currentTeam?.slug ?? 'bruce-fire';

    useEffect(() => {
        const closeOnEscape = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setSidebarOpen(false);
                setNotifsOpen(false);
            }
        };
        window.addEventListener('keydown', closeOnEscape);
        return () => window.removeEventListener('keydown', closeOnEscape);
    }, []);

    return (
        <>
            <Head title={title} />
            <a href="#contenido-vendedor" className="bf-skip-link">
                Saltar al contenido
            </a>

            {/* Shell con altura completa y desbordamiento controlado (sin scroll en el body) */}
            <div className="bf-workspace bg-background text-foreground flex h-dvh w-full overflow-hidden">
                <VendedorSidebar
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
                            aria-controls="vendedor-navigation"
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
                            aria-controls="vendedor-navigation"
                            className="border-border bg-card text-muted-foreground hidden size-9 shrink-0 items-center justify-center rounded-xl border transition-[color,background-color,transform] duration-150 active:scale-95 lg:flex"
                        >
                            {sidebarCollapsed ? (
                                <PanelLeftOpen className="size-4" />
                            ) : (
                                <PanelLeftClose className="size-4" />
                            )}
                        </button>

                        <div className="text-muted-foreground min-w-0 flex-1 truncate text-xs">
                            {currentSede ? (
                                <span className="text-foreground inline-flex max-w-full items-center gap-2 font-medium">
                                    <MapPin className="text-primary-strong size-4 shrink-0" />
                                    <span className="truncate">
                                        {currentSede.nombre}
                                    </span>
                                </span>
                            ) : (
                                <span className="text-foreground font-medium">
                                    {title}
                                </span>
                            )}
                            {caja_hoy && (
                                <span className="text-success-strong ml-4 hidden items-center gap-1.5 text-[11px] sm:inline-flex">
                                    <span className="size-1.5 rounded-full bg-current" />
                                    Turno abierto
                                    {caja_hoy.fecha_apertura
                                        ? ` desde ${caja_hoy.fecha_apertura.slice(0, 10).split('-').reverse().join('/')}`
                                        : ''}
                                </span>
                            )}
                        </div>

                        <ThemeToggle />

                        {/* Campana de avisos con popover interactivo */}
                        <div className="relative">
                            <button
                                type="button"
                                onClick={() => setNotifsOpen(!notifsOpen)}
                                className="border-border bg-card text-muted-foreground hover:bg-accent hover:text-foreground relative flex size-[38px] shrink-0 items-center justify-center rounded-xl border transition-[color,background-color,transform] duration-150 active:scale-95"
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
                                    <div className="border-border bg-card animate-in fade-in-50 zoom-in-95 absolute top-full right-0 z-50 mt-2 w-80 origin-top-right rounded-2xl border p-4 shadow-xl duration-150 ease-out sm:w-96">
                                        <div className="border-border flex items-center justify-between border-b pb-3">
                                            <div className="flex items-center gap-2">
                                                <Bell className="text-primary-strong size-4" />
                                                <h4 className="text-foreground text-xs font-bold tracking-wider uppercase">
                                                    Avisos y Pendientes
                                                </h4>
                                                {alertasTop.length > 0 && (
                                                    <span className="bg-primary/10 text-primary-strong rounded-full px-2 py-0.5 text-[10px] font-bold">
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
                                                                    ? 'text-warning-strong bg-amber-500/10'
                                                                    : alerta.tipo ===
                                                                        'lista_entrega'
                                                                      ? 'text-success-strong bg-emerald-500/10'
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
                                                            <p className="text-foreground group-hover:text-primary-strong text-xs font-bold transition-colors">
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
                                                className="text-primary-strong text-[11px] font-semibold hover:underline"
                                            >
                                                Ver todas las órdenes de
                                                servicio →
                                            </Link>
                                        </div>
                                    </div>
                                </>
                            )}
                        </div>
                    </header>

                    {/* Único contenedor de scroll vertical: sidebar y header quedan estáticos */}
                    <main
                        id="contenido-vendedor"
                        tabIndex={-1}
                        className="flex-1 overflow-y-auto overscroll-contain px-4 py-6 pb-32 outline-none lg:px-8"
                    >
                        <div className="w-full">{children}</div>
                    </main>
                </div>
            </div>
            <ChispaWidget rol="vendedor" />
        </>
    );
}
