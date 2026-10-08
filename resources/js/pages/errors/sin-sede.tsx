import { Head, router } from '@inertiajs/react';
import { MapPin } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { logout } from '@/routes';

type Props = { nombre: string };

/**
 * Pantalla para el trabajador que todavía no tiene sede: sin sede vería los
 * datos de todas las sedes, así que primero el Gerente debe asignarle una.
 */
export default function SinSede({ nombre }: Props) {
    return (
        <div className="bg-background flex min-h-screen items-center justify-center px-4">
            <Head title="Falta tu sede" />
            <div className="border-border bg-card w-full max-w-md rounded-[16px] border p-8 text-center">
                <div className="bg-destructive/10 text-primary-strong mx-auto mb-4 flex size-12 items-center justify-center rounded-full">
                    <MapPin className="size-6" />
                </div>
                <h1 className="font-['Oswald',sans-serif] text-[22px] font-semibold uppercase">
                    Todavía no tienes sede
                </h1>
                <p className="text-muted-foreground mt-2 text-[13.5px]">
                    Hola {nombre}. Para trabajar necesitas estar asignado a una
                    tienda o almacén. Pídele al Gerente que te asigne tu sede en{' '}
                    <b>Usuarios y Roles</b> y vuelve a entrar.
                </p>
                <Button
                    type="button"
                    variant="outline"
                    className="mt-6"
                    onClick={() =>
                        router.post(
                            logout.url(),
                            {},
                            {
                                onError: (errors) =>
                                    toast.error(
                                        Object.values(errors)[0] ??
                                            'No se pudo completar la acción.',
                                    ),
                            },
                        )
                    }
                >
                    Cerrar sesión
                </Button>
            </div>
        </div>
    );
}
