import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
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
import { InputCorreo } from '@/components/ui/input-correo';
import { Label } from '@/components/ui/label';
import UsuariosRoutes from '@/routes/gerente/usuarios';

type Props = {
    usuario: { id: number; name: string; email: string };
    teamSlug: string;
    propiaCuenta: boolean;
    onCerrar: () => void;
};

export default function EditarUsuarioDialog({
    usuario,
    teamSlug,
    propiaCuenta,
    onCerrar,
}: Props) {
    const form = useForm({ name: usuario.name, email: usuario.email });
    const guardar = (event: FormEvent) => {
        event.preventDefault();
        form.patch(
            UsuariosRoutes.update.url({
                current_team: teamSlug,
                user: usuario.id,
            }),
            {
                preserveScroll: true,
                onSuccess: onCerrar,
            },
        );
    };

    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open && !form.processing) {
                    onCerrar();
                }
            }}
        >
            <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-md">
                <form onSubmit={guardar} className="min-w-0 space-y-4">
                    <DialogHeader>
                        <DialogTitle>Editar trabajador</DialogTitle>
                        <DialogDescription>
                            Actualiza el nombre y el correo de {usuario.name}.
                        </DialogDescription>
                    </DialogHeader>
                    {propiaCuenta && (
                        <p className="text-foreground bg-muted rounded-lg p-3 text-sm">
                            Esta es tu propia cuenta. Usarás el nuevo correo
                            para iniciar sesión.
                        </p>
                    )}
                    <div className="min-w-0 space-y-1.5">
                        <Label htmlFor="editar-usuario-nombre">
                            Nombre completo
                        </Label>
                        <Input
                            id="editar-usuario-nombre"
                            required
                            maxLength={255}
                            autoComplete="name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            aria-invalid={!!form.errors.name}
                            aria-describedby="editar-usuario-nombre-error"
                        />
                        <InputError
                            id="editar-usuario-nombre-error"
                            message={form.errors.name}
                        />
                    </div>
                    <div className="min-w-0 space-y-1.5">
                        <Label htmlFor="editar-usuario-email">
                            Correo electrónico
                        </Label>
                        <InputCorreo
                            id="editar-usuario-email"
                            required
                            maxLength={255}
                            autoComplete="email"
                            value={form.data.email}
                            onChange={(e) =>
                                form.setData('email', e.target.value)
                            }
                            aria-invalid={!!form.errors.email}
                            aria-describedby="editar-usuario-email-error"
                        />
                        <InputError
                            id="editar-usuario-email-error"
                            message={form.errors.email}
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            className="min-h-11"
                            disabled={form.processing}
                            onClick={onCerrar}
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            className="min-h-11"
                            disabled={form.processing}
                        >
                            {form.processing ? 'Guardando…' : 'Guardar cambios'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
