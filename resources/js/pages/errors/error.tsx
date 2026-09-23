import { Head, Link, usePage } from '@inertiajs/react';
import { AlertTriangle, Home, Lock, SearchX, ServerCrash } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

type Props = {
    status: number;
};

type ErrorCopy = {
    icon: LucideIcon;
    title: string;
    description: string;
};

const COPY: Record<number, ErrorCopy> = {
    403: {
        icon: Lock,
        title: 'No tienes acceso a esta página',
        description:
            'Tu cuenta no tiene el rol necesario para ver este contenido. Si crees que es un error, contacta a tu gerente.',
    },
    404: {
        icon: SearchX,
        title: 'Página no encontrada',
        description:
            'El enlace que abriste no existe o fue movido. Verifica la dirección o vuelve al inicio.',
    },
    419: {
        icon: AlertTriangle,
        title: 'Tu sesión expiró',
        description:
            'Por seguridad, cerramos la página tras un tiempo de inactividad. Vuelve a iniciar sesión para continuar.',
    },
    500: {
        icon: ServerCrash,
        title: 'Algo salió mal',
        description:
            'Ocurrió un error inesperado en el servidor. Ya quedó registrado — intenta de nuevo en unos minutos.',
    },
    503: {
        icon: ServerCrash,
        title: 'Servicio en mantenimiento',
        description:
            'El sistema está en mantenimiento programado. Vuelve a intentarlo en unos minutos.',
    },
};

export default function ErrorPage({ status }: Props) {
    const { auth } = usePage().props;
    const copy = COPY[status] ?? COPY[500];
    const Icon = copy.icon;

    return (
        <>
            <Head title={`Error ${status}`} />
            <div className="flex min-h-screen flex-col items-center justify-center bg-background px-6 text-center text-foreground">
                <div className="flex size-[52px] items-center justify-center rounded-[12px] bg-primary font-['Oswald',sans-serif] text-[16px] font-bold text-white">
                    BF
                </div>
                <div className="mt-3 font-['Oswald',sans-serif] text-[13px] font-bold tracking-[0.08em] text-muted-foreground uppercase">
                    Bruce Fire
                </div>

                <div className="mt-10 flex size-16 items-center justify-center rounded-full bg-destructive/10 text-primary">
                    <Icon className="size-8" strokeWidth={1.75} />
                </div>

                <div className="mt-6 font-['IBM_Plex_Mono',monospace] text-[13px] font-bold tracking-[0.1em] text-primary uppercase">
                    Error {status}
                </div>
                <h1 className="mt-2 max-w-md font-['Oswald',sans-serif] text-[24px] font-semibold text-foreground">
                    {copy.title}
                </h1>
                <p className="mt-3 max-w-sm text-[13.5px] text-muted-foreground">
                    {copy.description}
                </p>

                <Link
                    href={auth?.user ? '/' : '/login'}
                    className="mt-8 inline-flex items-center gap-2 rounded-[9px] bg-foreground px-5 py-2.5 text-[13px] font-bold text-background hover:bg-foreground/90"
                >
                    <Home className="size-4" />
                    {auth?.user ? 'Volver al inicio' : 'Ir al login'}
                </Link>
            </div>
        </>
    );
}
