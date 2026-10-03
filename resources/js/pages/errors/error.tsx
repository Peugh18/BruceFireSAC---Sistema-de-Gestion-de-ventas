import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Home, LogIn, RotateCw } from 'lucide-react';

import ChispaAvatar, { type ChispaPose } from '@/components/chispa-avatar';
import { home, login } from '@/routes';

type Props = {
    status: number;
};

type Mensaje = {
    pose: ChispaPose;
    titulo: string;
    descripcion: string;
    /** Acción secundaria: volver a la página anterior o reintentar. */
    secundaria: 'atras' | 'reintentar' | null;
};

const MENSAJES: Record<number, Mensaje> = {
    403: {
        pose: 'piensa',
        titulo: 'Esta sección no es de tu rol',
        descripcion:
            'Tu cuenta no tiene acceso a esta página o a los datos de esta sede. Si la necesitas para tu trabajo, pídesela a tu gerente.',
        secundaria: 'atras',
    },
    404: {
        pose: 'busca',
        titulo: 'Chispa buscó esta página y no la encontró',
        descripcion:
            'Puede que el enlace esté mal escrito o que la página ya no exista. Revisa la dirección o vuelve a tu panel.',
        secundaria: 'atras',
    },
    419: {
        pose: 'sentado',
        titulo: 'Tu sesión se venció',
        descripcion:
            'Por seguridad cerramos la sesión después de un rato sin uso. Vuelve a entrar y sigue donde te quedaste.',
        secundaria: null,
    },
    500: {
        pose: 'corre',
        titulo: 'Algo falló de nuestro lado',
        descripcion:
            'El error ya quedó registrado. Intenta de nuevo en unos minutos; si se repite, avísale a tu gerente.',
        secundaria: 'reintentar',
    },
    503: {
        pose: 'sentado',
        titulo: 'Estamos en mantenimiento',
        descripcion:
            'El sistema vuelve en unos minutos. Lo que ya guardaste está a salvo.',
        secundaria: 'reintentar',
    },
};

/** Nombre visible de cada rol de Spatie para el botón "Volver a mi panel". */
const ROLES: Record<string, string> = {
    Gerente: 'Gerente',
    Vendedor: 'Vendedor',
    Almacen: 'Almacén',
    TecnicoPlanta: 'Técnico de planta',
    TecnicoCampo: 'Técnico de campo',
};

/**
 * Página de error de marca para 403, 404, 419, 500 y 503. Solo recibe el
 * código: nunca muestra el detalle técnico de la excepción. "/" lleva al panel
 * del rol si hay sesión o al login si no la hay.
 */
export default function ErrorPage({ status }: Props) {
    const { auth } = usePage().props;
    const mensaje = MENSAJES[status] ?? MENSAJES[500];
    const conSesion = Boolean(auth?.user);
    const rol = auth?.roles?.map((nombre) => ROLES[nombre]).find(Boolean);
    const botonPrincipal =
        'inline-flex h-10 items-center justify-center gap-2 rounded-[10px] px-5 text-[13px] font-bold transition-transform duration-150 ease-out active:scale-[0.97]';

    return (
        <>
            <Head title={`Error ${status}`} />
            <main className="bg-background text-foreground flex min-h-dvh flex-col items-center px-4 py-8 text-center">
                <div className="flex items-center gap-2">
                    <img src="/brand/logo-icon.png" alt="" className="size-7" />
                    <span className="text-muted-foreground font-['Oswald',sans-serif] text-[13px] font-bold tracking-[0.08em] uppercase">
                        Bruce Fire
                    </span>
                </div>

                <div className="flex w-full max-w-md flex-1 flex-col items-center justify-center py-10">
                    <ChispaAvatar pose={mensaje.pose} size={168} animado />

                    <div className="text-primary-strong mt-6 font-['Oswald',sans-serif] text-[56px] leading-none font-semibold">
                        {status}
                    </div>
                    <h1 className="mt-3 font-['Oswald',sans-serif] text-[22px] font-semibold text-balance uppercase sm:text-[24px]">
                        {mensaje.titulo}
                    </h1>
                    <p className="text-muted-foreground mt-3 max-w-sm text-[13.5px] text-pretty">
                        {mensaje.descripcion}
                    </p>

                    <div className="mt-8 flex w-full flex-col-reverse gap-2 sm:w-auto sm:flex-row sm:justify-center">
                        {mensaje.secundaria === 'atras' ? (
                            <button
                                type="button"
                                onClick={() => window.history.back()}
                                className={`${botonPrincipal} border-border bg-card text-foreground hover:bg-muted border`}
                            >
                                <ArrowLeft className="size-4" />
                                Volver atrás
                            </button>
                        ) : null}
                        {mensaje.secundaria === 'reintentar' ? (
                            <button
                                type="button"
                                onClick={() => window.location.reload()}
                                className={`${botonPrincipal} border-border bg-card text-foreground hover:bg-muted border`}
                            >
                                <RotateCw className="size-4" />
                                Intentar de nuevo
                            </button>
                        ) : null}

                        {status === 419 || !conSesion ? (
                            <Link
                                href={status === 419 ? login() : home()}
                                className={`${botonPrincipal} bg-primary text-primary-foreground hover:bg-primary/90`}
                            >
                                {status === 419 ? (
                                    <LogIn className="size-4" />
                                ) : (
                                    <Home className="size-4" />
                                )}
                                {status === 419
                                    ? 'Volver a entrar'
                                    : 'Ir al inicio'}
                            </Link>
                        ) : (
                            <Link
                                href={home()}
                                className={`${botonPrincipal} bg-primary text-primary-foreground hover:bg-primary/90`}
                            >
                                <Home className="size-4" />
                                {rol
                                    ? `Volver a mi panel de ${rol}`
                                    : 'Volver a mi panel'}
                            </Link>
                        )}
                    </div>
                </div>

                <p className="text-muted-foreground font-['IBM_Plex_Mono',monospace] text-[11px] tracking-[0.06em] uppercase">
                    Error {status}
                </p>
            </main>
        </>
    );
}
