import { Head, usePage } from '@inertiajs/react';
import { Bell, Search } from 'lucide-react';
import type { ReactNode } from 'react';

import { AlmacenSidebar } from '@/components/almacen-sidebar';
import { useInitials } from '@/hooks/use-initials';
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

export default function AlmacenLayout({
    children,
    title,
}: AlmacenLayoutProps) {
    const { auth } = usePage<AlmacenPageProps>().props;
    const getInitials = useInitials();

    return (
        <>
            <Head title={title} />

            <div className="flex min-h-screen bg-[#F3F1ED] text-[#201F1D]">
                <AlmacenSidebar />

                <div className="flex min-w-0 flex-1 flex-col bg-[#F3F1ED]">
                    <header className="flex h-[66px] shrink-0 items-center gap-3.5 border-b border-[#E7E4DE] bg-white px-[30px]">
                        <div className="text-xs text-[#8A8680]">
                            Almacén
                            <span className="mx-1.5">›</span>
                            <b className="font-bold text-[#201F1D]">{title}</b>
                        </div>

                        <div className="flex-1" />

                        <div className="flex h-[38px] w-[230px] items-center gap-2 rounded-[9px] border border-[#E4E1DC] bg-[#FAFAF8] px-3 text-[13px] text-[#8A8680]">
                            <Search className="size-3.5 shrink-0" strokeWidth={2} />
                            <span>Buscar en inventario...</span>
                        </div>

                        <button
                            type="button"
                            className="relative flex size-[38px] shrink-0 items-center justify-center rounded-[9px] border border-[#E4E1DC] bg-white text-[#4A4742]"
                            aria-label="Notificaciones"
                        >
                            <Bell className="size-4" strokeWidth={2} />
                            <span className="absolute top-[7px] right-[7px] size-[7px] rounded-full border-[1.5px] border-white bg-[#E31E24]" />
                        </button>

                        <div
                            className="flex size-9 shrink-0 items-center justify-center rounded-[10px] bg-[#1A1A1D] text-xs font-bold text-white"
                            title={auth.user.name}
                        >
                            {getInitials(auth.user.name)}
                        </div>
                    </header>

                    <main className="flex-1 p-[30px]">{children}</main>
                </div>
            </div>
        </>
    );
}
