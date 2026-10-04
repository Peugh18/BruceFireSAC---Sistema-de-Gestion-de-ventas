import { MessageCircle } from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { leerCookie } from '@/lib/cookies';
import clientes from '@/routes/vendedor/clientes';

/**
 * Pide el celular de un cliente que no tiene WhatsApp guardado, lo guarda en
 * su ficha y abre WhatsApp con el mensaje listo. La pestaña se abre en el
 * mismo clic (si se abriera después de guardar, el navegador la bloquearía).
 */
export default function WhatsappNumeroDialog({
    open,
    onOpenChange,
    teamSlug,
    clientId,
    cliente,
    enlace,
    onGuardado,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    teamSlug: string;
    clientId: number;
    cliente: string;
    /** Enlace de wa.me para el número ya guardado (con el 51 de Perú). */
    enlace: (numero: string) => string;
    onGuardado?: (numero: string) => void;
}) {
    const [numero, setNumero] = useState('');
    const [guardando, setGuardando] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (open) {
            setNumero('');
            setError(null);
        }
    }, [open]);

    const guardar = async (event: FormEvent) => {
        event.preventDefault();
        setGuardando(true);
        setError(null);
        const ventana = window.open('', '_blank');

        try {
            const response = await fetch(
                clientes.whatsapp.url({
                    current_team: teamSlug,
                    client: clientId,
                }),
                {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': leerCookie('XSRF-TOKEN'),
                    },
                    body: JSON.stringify({ whatsapp: numero }),
                },
            );
            const payload = (await response.json()) as {
                whatsapp?: string;
                message?: string;
                errors?: Record<string, string[]>;
            };

            if (!response.ok || !payload.whatsapp) {
                ventana?.close();
                setError(
                    payload.errors?.whatsapp?.[0] ??
                        payload.message ??
                        'No se pudo guardar el número.',
                );
                return;
            }

            if (ventana) {
                ventana.location.href = enlace(payload.whatsapp);
            } else {
                window.location.href = enlace(payload.whatsapp);
            }

            onOpenChange(false);
            onGuardado?.(payload.whatsapp);
        } catch {
            ventana?.close();
            setError('No se pudo guardar el número.');
        } finally {
            setGuardando(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="border-border bg-card rounded-[16px] sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="text-foreground font-['Oswald',sans-serif] text-[20px] font-semibold uppercase">
                        Agregar número
                    </DialogTitle>
                    <DialogDescription className="text-[12.5px]">
                        {cliente} no tiene celular guardado. Se guarda en su
                        ficha y se abre WhatsApp con el mensaje listo.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={guardar} className="space-y-3">
                    <div>
                        <label className="text-foreground/80 text-[11px] font-bold uppercase">
                            Celular (WhatsApp)
                        </label>
                        <Input
                            autoFocus
                            inputMode="tel"
                            value={numero}
                            onChange={(event) => setNumero(event.target.value)}
                            placeholder="Ej. 987 654 321"
                            className="mt-1 h-10 rounded-[9px] text-[14px]"
                        />
                        {error ? (
                            <p className="text-destructive-strong mt-1 text-[11.5px] font-semibold">
                                {error}
                            </p>
                        ) : null}
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            className="border-border bg-card text-foreground/80 rounded-[9px] shadow-none"
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            disabled={guardando || numero.trim().length < 9}
                            className="rounded-[9px] bg-green-700 font-bold text-white shadow-none hover:bg-green-800"
                        >
                            <MessageCircle className="size-4" />
                            {guardando ? 'Guardando…' : 'Guardar y enviar'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
