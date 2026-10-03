import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type MobileDockItem = {
    title: string;
    href: string;
    icon: LucideIcon;
    isActive: boolean;
    badge?: number | string | boolean;
};

export type MobileNavDockProps = {
    items: MobileDockItem[];
    brandIcon?: string;
    roleBadge?: string;
};

/**
 * Barra de navegación móvil estilo "Floating Capsule Dock" (inspirado en la referencia Pinterest / iOS).
 * Cápsula flotante con emblema de marca circular, pestañas táctiles de 44px+,
 * micro-punto indicador activo, insignia de notificación y desenfoque de material.
 */
export function MobileNavDock({
    items,
    brandIcon = '/brand/logo-icon.svg',
    roleBadge,
}: MobileNavDockProps) {
    return (
        <div className="pointer-events-none fixed inset-x-0 bottom-[calc(0.75rem+env(safe-area-inset-bottom))] z-40 flex justify-center px-4 md:hidden">
            <nav
                aria-label="Navegación móvil"
                className="border-border/80 bg-card/92 dark:bg-card/85 pointer-events-auto flex h-[62px] w-full max-w-sm items-center justify-between rounded-full border p-1.5 shadow-[0_12px_36px_rgba(0,0,0,0.16)] backdrop-blur-xl transition-colors dark:shadow-[0_16px_40px_rgba(0,0,0,0.5)]"
            >
                {/* Emblema circular de marca Bruce Fire */}
                <div
                    className="bg-primary text-primary-foreground relative flex size-11 shrink-0 items-center justify-center rounded-full shadow-xs transition-transform active:scale-95"
                    title="Bruce Fire"
                >
                    <img
                        src={brandIcon}
                        alt="Bruce Fire"
                        className="size-6 object-contain brightness-0 invert"
                    />
                    {roleBadge && <span className="sr-only">{roleBadge}</span>}
                </div>

                {/* Contenedor de enlaces táctiles */}
                <div className="flex flex-1 items-center justify-around pl-1">
                    {items.map((item) => {
                        const Icon = item.icon;
                        const hasBadge = Boolean(item.badge);

                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                prefetch
                                aria-current={
                                    item.isActive ? 'page' : undefined
                                }
                                className={[
                                    'group relative flex h-11 min-w-[54px] flex-col items-center justify-center rounded-2xl px-2 transition-[color,transform] duration-150 active:scale-[0.96] select-none',
                                    item.isActive
                                        ? 'text-primary font-bold'
                                        : 'text-muted-foreground hover:text-foreground',
                                ].join(' ')}
                            >
                                <div className="relative">
                                    <Icon
                                        className={[
                                            'size-5 transition-transform group-hover:scale-105',
                                            item.isActive
                                                ? 'stroke-[2.5]'
                                                : 'stroke-2',
                                        ].join(' ')}
                                    />
                                    {hasBadge && (
                                        <span className="bg-primary ring-card absolute -top-1 -right-1.5 size-2 rounded-full ring-2" />
                                    )}
                                </div>
                                <span className="mt-0.5 text-[10px] leading-tight tracking-tight">
                                    {item.title}
                                </span>
                                {item.isActive && (
                                    <span className="bg-primary animate-in fade-in zoom-in-90 absolute -bottom-0.5 size-1 rounded-full duration-150 ease-out" />
                                )}
                            </Link>
                        );
                    })}
                </div>
            </nav>
        </div>
    );
}
