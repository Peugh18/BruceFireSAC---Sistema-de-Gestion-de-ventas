import { Head, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { useInitials } from '@/hooks/use-initials';
import type { Auth } from '@/types';

type GerentePageProps = {
    auth: Auth & { roles?: string[] };
    [key: string]: unknown;
};

type GerenteLayoutProps = {
    children: ReactNode;
    title: string;
};

/**
 * Layout mínimo para el rol Gerente: todavía no existe un dashboard ni menú
 * lateral propio para este rol (ver Documento Maestro §77.3), así que por
 * ahora esta pantalla se abre directa sin sidebar. Cuando se construya el
 * dashboard de Gerente, este layout debe reemplazarse por uno con menú,
 * igual que VendedorLayout.
 */
export default function GerenteLayout({ children, title }: GerenteLayoutProps) {
    const { auth } = usePage<GerentePageProps>().props;
    const getInitials = useInitials();

    return (
        <>
            <Head title={title} />

            <div className="min-h-screen bg-[#F3F1ED] text-[#201F1D]">
                <header className="flex h-[66px] shrink-0 items-center gap-3.5 border-b border-[#E7E4DE] bg-white px-[30px]">
                    <div className="font-['Oswald',sans-serif] text-[15px] font-semibold tracking-[0.02em] uppercase">BRUCE FIRE</div>
                    <div className="text-xs text-[#8A8680]">
                        Gerencia
                        <span className="mx-1.5">›</span>
                        <b className="font-bold text-[#201F1D]">{title}</b>
                    </div>
                    <div className="flex-1" />
                    <div
                        className="flex size-9 shrink-0 items-center justify-center rounded-[10px] bg-[#1A1A1D] text-xs font-bold text-white"
                        title={auth.user.name}
                    >
                        {getInitials(auth.user.name)}
                    </div>
                </header>

                <main className="mx-auto max-w-4xl px-7 pt-[22px] pb-8">{children}</main>
            </div>
        </>
    );
}
