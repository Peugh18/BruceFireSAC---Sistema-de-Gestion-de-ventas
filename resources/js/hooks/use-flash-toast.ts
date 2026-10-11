import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import type { FlashToast } from '@/types/ui';

export function useFlashToast(): void {
    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;
            const data = flash?.toast as FlashToast | undefined;

            if (!data) {
                return;
            }

            // Un aviso nuevo reemplaza a los anteriores: con el mismo id no se
            // duplica y los errores se quedan más tiempo para poder leerlos.
            toast.dismiss();
            toast[data.type](data.message, {
                id: `${data.type}:${data.message}`,
                duration: data.type === 'error' ? 8000 : 4000,
            });
        });
    }, []);
}
