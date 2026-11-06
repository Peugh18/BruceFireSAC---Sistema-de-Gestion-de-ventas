import { useHttp } from '@inertiajs/react';
import { MapPinPlus } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import clientes from '@/routes/tecnico-campo/clientes';

type LocalCreado = { id: number; nombre: string; direccion: string };

/**
 * Agrega un local al cliente desde el recojo: queda en su ficha con su
 * dirección, y de ahí salen la constancia, la entrega y el certificado.
 */
export default function NuevoLocalCampo({
    teamSlug,
    clienteId,
    onCreado,
}: {
    teamSlug: string;
    clienteId: number;
    onCreado: (local: LocalCreado) => void;
}) {
    const [abierto, setAbierto] = useState(false);
    const form = useHttp<
        { tipo: string; nombre: string; direccion: string; referencia: string },
        LocalCreado
    >({ tipo: 'local', nombre: '', direccion: '', referencia: '' });

    const guardar = (event: FormEvent) => {
        event.preventDefault();
        void form.post(
            clientes.locales.store.url({
                current_team: teamSlug,
                client: clienteId,
            }),
            {
                onError: (errores) =>
                    toast.error(
                        Object.values(errores)[0] ??
                            'No se pudo guardar el local.',
                    ),
                onSuccess: (local) => {
                    form.reset();
                    setAbierto(false);
                    onCreado(local);
                },
            },
        );
    };

    if (!abierto) {
        return (
            <Button
                type="button"
                variant="outline"
                onClick={() => setAbierto(true)}
                className="min-h-11 w-full rounded-xl"
            >
                <MapPinPlus />
                Nuevo local del cliente
            </Button>
        );
    }

    return (
        <div className="border-border bg-muted/40 space-y-3 rounded-2xl border p-3">
            <div>
                <Label htmlFor="local-nombre">Nombre del local *</Label>
                <Input
                    id="local-nombre"
                    value={form.data.nombre}
                    onChange={(e) => form.setData('nombre', e.target.value)}
                    placeholder="Tienda Centro"
                    className="mt-1 text-base"
                />
            </div>
            <div>
                <Label htmlFor="local-direccion">Dirección *</Label>
                <Input
                    id="local-direccion"
                    value={form.data.direccion}
                    onChange={(e) => form.setData('direccion', e.target.value)}
                    placeholder="Av. España 1234, Trujillo"
                    className="mt-1 text-base"
                />
            </div>
            <div>
                <Label htmlFor="local-referencia">Referencia</Label>
                <Input
                    id="local-referencia"
                    value={form.data.referencia}
                    onChange={(e) => form.setData('referencia', e.target.value)}
                    placeholder="Frente al mercado, 2.º piso"
                    className="mt-1 text-base"
                />
            </div>
            <div className="flex gap-2">
                <Button
                    type="button"
                    onClick={guardar}
                    loading={form.processing}
                    className="min-h-11 flex-1 rounded-xl"
                >
                    Guardar local
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    onClick={() => setAbierto(false)}
                    className="min-h-11 rounded-xl"
                >
                    Cancelar
                </Button>
            </div>
        </div>
    );
}
