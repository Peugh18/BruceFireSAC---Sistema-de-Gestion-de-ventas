import { Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    ChevronRight,
    LogOut,
    Moon,
    ShieldCheck,
    UserRound,
    type LucideIcon,
} from 'lucide-react';
import { useRef, useState, type ReactNode } from 'react';

import AppearanceToggleTab from '@/components/appearance-tabs';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import { useInitials } from '@/hooks/use-initials';
import { PlantaPerfilContext } from '@/hooks/use-planta-perfil';
import { logout } from '@/routes';
import { edit as editarPerfil } from '@/routes/profile';
import { edit as editarSeguridad } from '@/routes/security';
import type { Auth, Team } from '@/types';

type PageProps = {
    auth: Auth;
    currentTeam?: Team | null;
    [key: string]: unknown;
};

export type DestinoTecnico = {
    title: string;
    href?: string;
    icon: LucideIcon;
    activeMatch: string;
    activo?: boolean;
    onClick?: () => void;
};

export default function TecnicoLayout({
    children,
    ocultarMenu = false,
    area,
    navItems,
}: {
    children: ReactNode;
    title?: string;
    ocultarMenu?: boolean;
    area: 'planta' | 'campo';
    navItems: DestinoTecnico[];
}) {
    const page = usePage<PageProps>();
    const user = page.props.auth.user;
    const getInitials = useInitials();
    const [perfilAbierto, setPerfilAbierto] = useState(false);
    const [contenedorPie, setContenedorPie] = useState<HTMLDivElement | null>(
        null,
    );
    const botonPerfil = useRef<HTMLElement | null>(null);
    const abrirPerfil = () => {
        if (perfilAbierto) {
            return;
        }
        if (document.activeElement instanceof HTMLElement) {
            botonPerfil.current = document.activeElement;
        }
        setPerfilAbierto(true);
    };
    const estiloDestino = (activo: boolean) =>
        `focus-visible:ring-ring relative flex min-h-16 min-w-0 touch-manipulation flex-col items-center justify-end rounded-2xl px-1 pb-2 text-xs focus-visible:ring-2 focus-visible:outline-none ${activo ? 'text-primary-strong font-bold' : 'text-muted-foreground hover:text-foreground'}`;
    const estiloIcono = (activo: boolean) =>
        activo
            ? 'bg-card text-primary-strong ring-background absolute -top-6 flex size-14 items-center justify-center rounded-full border-2 border-primary/40 shadow-sm ring-[7px]'
            : 'absolute top-3 flex size-6 items-center justify-center';
    const navegacion = (enPerfil = false) => (
        <div className="mx-auto w-full max-w-2xl shrink-0 px-3 pt-6 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
            <nav
                aria-label={`Navegación de ${area}`}
                className="border-border/70 bg-card grid grid-cols-4 gap-1 rounded-3xl border px-2 pt-2 shadow-[0_4px_24px_rgb(0_0_0/0.05)]"
            >
                {navItems.map((item) => {
                    const Icon = item.icon;
                    const activo =
                        !enPerfil &&
                        (item.activo ?? page.url.includes(item.activeMatch));
                    const contenido = (
                        <>
                            <span className={estiloIcono(activo)}>
                                <Icon
                                    className={activo ? 'size-7' : 'size-5'}
                                    aria-hidden="true"
                                />
                            </span>
                            {item.title}
                        </>
                    );
                    return item.href ? (
                        <Link
                            key={item.title}
                            href={item.href}
                            onClick={() => setPerfilAbierto(false)}
                            aria-current={activo ? 'page' : undefined}
                            className={estiloDestino(activo)}
                        >
                            {contenido}
                        </Link>
                    ) : (
                        <button
                            key={item.title}
                            type="button"
                            aria-haspopup="dialog"
                            onClick={() => {
                                setPerfilAbierto(false);
                                item.onClick?.();
                            }}
                            className={estiloDestino(activo)}
                        >
                            {contenido}
                        </button>
                    );
                })}
                <button
                    type="button"
                    onClick={abrirPerfil}
                    aria-haspopup="dialog"
                    aria-expanded={perfilAbierto}
                    className={estiloDestino(
                        enPerfil || page.url.startsWith('/settings/'),
                    )}
                >
                    <span
                        className={estiloIcono(
                            enPerfil || page.url.startsWith('/settings/'),
                        )}
                    >
                        <UserRound
                            className={
                                enPerfil || page.url.startsWith('/settings/')
                                    ? 'size-7'
                                    : 'size-5'
                            }
                            aria-hidden="true"
                        />
                    </span>
                    Perfil
                </button>
            </nav>
        </div>
    );
    return (
        <PlantaPerfilContext.Provider value={{ abrirPerfil, contenedorPie }}>
            <div className="bg-background text-foreground flex h-dvh w-full flex-col overflow-hidden pt-[env(safe-area-inset-top)] antialiased">
                <main
                    className={`mx-auto min-h-0 w-full max-w-2xl flex-1 overflow-y-auto overscroll-contain px-4 pt-6 pb-6 sm:px-6 ${area === 'campo' ? '[&_input:not([type=checkbox]):not([type=radio])]:text-base [&_input:not([type=checkbox]):not([type=radio]):not([type=file])]:min-h-11 [&_select]:min-h-11 [&_select]:text-base [&_textarea]:text-base' : ''}`}
                >
                    {children}
                </main>
                {!ocultarMenu && navegacion()}
                {ocultarMenu && (
                    <div
                        ref={setContenedorPie}
                        data-pie-orden
                        className="mx-auto w-full max-w-2xl shrink-0"
                    />
                )}
            </div>
            <Sheet open={perfilAbierto} onOpenChange={setPerfilAbierto}>
                <SheetContent
                    side="bottom"
                    className="mx-auto h-dvh max-w-2xl gap-0 border-0 pt-[env(safe-area-inset-top)] motion-reduce:animate-none [&>button]:hidden"
                    onCloseAutoFocus={(event) => {
                        if (botonPerfil.current?.isConnected) {
                            event.preventDefault();
                            botonPerfil.current.focus();
                        }
                    }}
                >
                    <div className="min-h-0 flex-1 overflow-y-auto px-5 pt-5 pb-8 sm:px-8">
                        <div className="mb-8 flex items-center gap-3">
                            <button
                                type="button"
                                onClick={() => setPerfilAbierto(false)}
                                aria-label={
                                    area === 'planta'
                                        ? 'Volver al taller'
                                        : 'Volver a Campo'
                                }
                                className="border-border bg-card hover:bg-muted focus-visible:ring-ring flex size-11 items-center justify-center rounded-2xl border focus-visible:ring-2 focus-visible:outline-none"
                            >
                                <ArrowLeft
                                    className="size-5"
                                    aria-hidden="true"
                                />
                            </button>
                            <SheetTitle className="text-xl font-bold tracking-tight">
                                Mi perfil
                            </SheetTitle>
                        </div>
                        <SheetDescription className="sr-only">
                            Tu cuenta, el tema de la aplicación y cerrar sesión.
                        </SheetDescription>
                        <div className="mb-8 space-y-2 text-center">
                            <div className="bg-primary/10 text-primary-strong mx-auto mb-4 flex size-20 items-center justify-center rounded-full text-2xl font-bold">
                                {getInitials(user.name)}
                            </div>
                            <h2 className="text-2xl font-bold tracking-tight">
                                {user.name}
                            </h2>
                            <p className="text-muted-foreground text-sm">
                                {area === 'planta'
                                    ? 'Técnico de planta'
                                    : 'Técnico de campo'}
                            </p>
                        </div>
                        <div className="border-border/70 bg-card mb-8 flex items-center gap-4 rounded-3xl border p-5">
                            <img
                                src="/brand/logo-icon.png"
                                alt="Bruce Fire"
                                width={44}
                                height={44}
                                className="size-11 shrink-0 object-contain"
                            />
                            <div className="min-w-0">
                                <p className="font-semibold">
                                    {page.props.currentTeam?.name ??
                                        'Bruce Fire'}
                                </p>
                                <p className="text-muted-foreground text-sm">
                                    {area === 'planta' ? 'Planta' : 'Campo'}
                                </p>
                            </div>
                        </div>
                        <h2 className="mb-3 text-base font-bold">Mi cuenta</h2>
                        <div className="border-border/70 bg-card divide-border/70 mb-6 divide-y overflow-hidden rounded-3xl border">
                            {[
                                {
                                    titulo: 'Datos personales',
                                    descripcion: 'Nombre y correo electrónico',
                                    href: editarPerfil(),
                                    icono: UserRound,
                                },
                                {
                                    titulo: 'Contraseña y seguridad',
                                    descripcion:
                                        'Contraseña, doble factor y llaves de acceso',
                                    href: editarSeguridad(),
                                    icono: ShieldCheck,
                                },
                            ].map(
                                ({
                                    titulo,
                                    descripcion,
                                    href,
                                    icono: Icono,
                                }) => (
                                    <Link
                                        key={titulo}
                                        href={href}
                                        onClick={() => setPerfilAbierto(false)}
                                        className="hover:bg-muted focus-visible:ring-ring flex min-h-16 items-center gap-3 px-4 py-4 focus-visible:ring-2 focus-visible:outline-none focus-visible:ring-inset"
                                    >
                                        <Icono
                                            className="text-muted-foreground size-5 shrink-0"
                                            aria-hidden="true"
                                        />
                                        <span className="min-w-0 flex-1">
                                            <span className="block text-sm font-semibold">
                                                {titulo}
                                            </span>
                                            <span className="text-muted-foreground mt-0.5 block text-xs">
                                                {descripcion}
                                            </span>
                                        </span>
                                        <ChevronRight
                                            className="text-muted-foreground size-4 shrink-0"
                                            aria-hidden="true"
                                        />
                                    </Link>
                                ),
                            )}
                        </div>
                        <h2 className="mb-3 text-base font-bold">
                            Preferencias
                        </h2>
                        <section className="border-border/70 bg-card space-y-4 rounded-3xl border p-5">
                            <h3 className="flex items-center gap-2 text-sm font-semibold">
                                <Moon className="size-4" aria-hidden="true" />
                                Apariencia
                            </h3>
                            <AppearanceToggleTab />
                            <p className="text-muted-foreground text-xs">
                                Se aplica a toda la aplicación.
                            </p>
                        </section>
                        <Link
                            href={logout()}
                            method="post"
                            as="button"
                            onClick={() => router.flushAll()}
                            className="border-border/70 bg-card text-destructive-strong hover:bg-destructive/5 focus-visible:ring-ring mt-5 flex min-h-14 w-full items-center gap-3 rounded-3xl border px-5 text-sm font-semibold transition-colors focus-visible:ring-2 focus-visible:outline-none"
                        >
                            <LogOut className="size-5" aria-hidden="true" />
                            Cerrar sesión
                        </Link>
                    </div>
                    {navegacion(true)}
                </SheetContent>
            </Sheet>
        </PlantaPerfilContext.Provider>
    );
}
