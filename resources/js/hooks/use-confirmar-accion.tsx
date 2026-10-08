import { useCallback, useState } from 'react';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type ConfirmacionPendiente = {
    mensaje: string;
    resolver: (aceptado: boolean) => void;
};

/**
 * Confirmación destructiva con el diálogo propio del proyecto (en vez de
 * window.confirm). Uso:
 *
 *   const { pedirConfirmacion, dialogoConfirmacion } = useConfirmarAccion();
 *   ...
 *   if (!(await pedirConfirmacion('¿Eliminar?'))) return;
 *   ...
 *   return <div>{dialogoConfirmacion}</div>;
 */
export function useConfirmarAccion() {
    const [pendiente, setPendiente] = useState<ConfirmacionPendiente | null>(
        null,
    );

    const pedirConfirmacion = useCallback(
        (mensaje: string) =>
            new Promise<boolean>((resolver) => {
                setPendiente({ mensaje, resolver });
            }),
        [],
    );

    const resolver = (aceptado: boolean) => {
        pendiente?.resolver(aceptado);
        setPendiente(null);
    };

    const dialogoConfirmacion = pendiente ? (
        <Dialog
            open
            onOpenChange={(abierto) => {
                if (!abierto) {
                    resolver(false);
                }
            }}
        >
            <DialogContent className="border-border bg-card rounded-[16px] sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Confirmar acción</DialogTitle>
                    <DialogDescription>{pendiente.mensaje}</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => resolver(false)}
                        className="rounded-[8px] text-xs font-semibold"
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        onClick={() => resolver(true)}
                        className="rounded-[8px] text-xs font-bold"
                    >
                        Confirmar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    ) : null;

    return { pedirConfirmacion, dialogoConfirmacion };
}
