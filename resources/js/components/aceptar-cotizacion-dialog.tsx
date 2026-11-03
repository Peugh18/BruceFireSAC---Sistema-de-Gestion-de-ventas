import { useForm } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { FormEvent, useEffect } from 'react';

import QuoteController from '@/actions/App/Http/Controllers/Vendedor/QuoteController';
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
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';

const CANALES = [
    ['orden_compra', 'Orden de compra'],
    ['whatsapp', 'WhatsApp'],
    ['correo', 'Correo'],
    ['presencial', 'En tienda'],
    ['llamada', 'Llamada'],
] as const;

/**
 * D-10: la cotización pasa a venta u orden solo con la aceptación del cliente
 * registrada. La orden de compra es opcional (SUNAT, campo 54 del Anexo I):
 * si el cliente la mandó, sale en la factura.
 */
export default function AceptarCotizacionDialog({
    open,
    onOpenChange,
    teamSlug,
    quote,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    teamSlug: string;
    quote: { id: number; numero: string; cliente: string } | null;
}) {
    const form = useForm({
        aceptada_por: '',
        aceptada_canal: 'whatsapp' as (typeof CANALES)[number][0],
        orden_compra: '',
        orden_compra_archivo: null as File | null,
    });

    useEffect(() => {
        if (open) {
            form.reset();
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const guardar = (event: FormEvent) => {
        event.preventDefault();
        if (!quote) return;
        form.post(
            QuoteController.accept.url({
                current_team: teamSlug,
                quote: quote.id,
            }),
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            },
        );
    };

    const conOrden = form.data.aceptada_canal === 'orden_compra';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="border-border bg-card rounded-2xl sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="text-foreground font-['Oswald',sans-serif] text-[20px] font-semibold uppercase">
                        Aceptar {quote?.numero}
                    </DialogTitle>
                    <DialogDescription className="text-[12.5px]">
                        <b className="text-foreground">{quote?.cliente}</b>
                        <br />
                        Registra cómo aceptó: con esto la cotización pasa a
                        venta u orden de servicio y queda la prueba.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={guardar} className="space-y-3">
                    <div>
                        <Label htmlFor="aceptada_por">
                            ¿Quién aceptó? (nombre y cargo)
                        </Label>
                        <Input
                            id="aceptada_por"
                            required
                            autoFocus
                            value={form.data.aceptada_por}
                            onChange={(e) =>
                                form.setData('aceptada_por', e.target.value)
                            }
                            placeholder="Ej. Rosa Díaz, administradora"
                            className="mt-1 h-10 rounded-lg"
                        />
                        {form.errors.aceptada_por ? (
                            <p className="text-destructive-strong mt-1 text-xs font-semibold">
                                {form.errors.aceptada_por}
                            </p>
                        ) : null}
                    </div>
                    <div>
                        <Label htmlFor="aceptada_canal">¿Por dónde?</Label>
                        <NativeSelect
                            id="aceptada_canal"
                            value={form.data.aceptada_canal}
                            onChange={(e) =>
                                form.setData(
                                    'aceptada_canal',
                                    e.target
                                        .value as (typeof CANALES)[number][0],
                                )
                            }
                            className="border-border bg-card text-foreground mt-1 h-10 w-full rounded-lg border px-3 text-[14px]"
                        >
                            {CANALES.map(([valor, texto]) => (
                                <option key={valor} value={valor}>
                                    {texto}
                                </option>
                            ))}
                        </NativeSelect>
                    </div>
                    <div>
                        <Label htmlFor="orden_compra">
                            N.° de orden de compra
                            {conOrden ? '' : ' (si la mandó)'}
                        </Label>
                        <Input
                            id="orden_compra"
                            required={conOrden}
                            value={form.data.orden_compra}
                            onChange={(e) =>
                                form.setData('orden_compra', e.target.value)
                            }
                            placeholder="Ej. 4500123"
                            className="mt-1 h-10 rounded-lg font-mono"
                        />
                        <p className="text-muted-foreground mt-1 text-xs">
                            SUNAT no lo exige; si lo tienes, sale en la factura
                            y el cliente te paga más rápido.
                        </p>
                        {form.errors.orden_compra ? (
                            <p className="text-destructive-strong mt-1 text-xs font-semibold">
                                {form.errors.orden_compra}
                            </p>
                        ) : null}
                    </div>
                    <div>
                        <Label htmlFor="orden_compra_archivo">
                            PDF o foto de la orden (opcional)
                        </Label>
                        <Input
                            id="orden_compra_archivo"
                            type="file"
                            accept="application/pdf,image/jpeg,image/png"
                            onChange={(e) =>
                                form.setData(
                                    'orden_compra_archivo',
                                    e.target.files?.[0] ?? null,
                                )
                            }
                            className="mt-1 h-10 rounded-lg"
                        />
                        {form.errors.orden_compra_archivo ? (
                            <p className="text-destructive-strong mt-1 text-xs font-semibold">
                                {form.errors.orden_compra_archivo}
                            </p>
                        ) : null}
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            className="border-border bg-card text-foreground/80 rounded-lg shadow-none"
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            loading={form.processing}
                            className="rounded-lg font-bold"
                        >
                            <Check className="size-4" />
                            Registrar aceptación
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
