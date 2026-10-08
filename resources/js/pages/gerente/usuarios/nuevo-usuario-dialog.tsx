import { useForm } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import UsuariosRoutes from '@/routes/gerente/usuarios';

type SedeOption = { id: number; nombre: string; tipo: string };

type Props = {
    teamSlug: string;
    roles: string[];
    sedesPara: (rol: string | null) => SedeOption[];
    roleLabel: (role: string | null) => string;
};

export default function NuevoUsuarioDialog({
    teamSlug,
    roles,
    sedesPara,
    roleLabel,
}: Props) {
    const [abierto, setAbierto] = useState(false);
    const form = useForm({
        name: '',
        email: '',
        role: 'Vendedor',
        sede_id: '',
        password: '',
    });
    const sedes = sedesPara(form.data.role);

    const guardar = (evento: FormEvent) => {
        evento.preventDefault();
        form.post(UsuariosRoutes.store.url({ current_team: teamSlug }), {
            onError: (errors) =>
                toast.error(
                    Object.values(errors)[0] ??
                        'No se pudo completar la acción.',
                ),
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setAbierto(false);
            },
        });
    };

    return (
        <Dialog open={abierto} onOpenChange={setAbierto}>
            <DialogTrigger asChild>
                <Button className="gap-1.5">
                    <UserPlus className="size-4" />
                    Nuevo usuario
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <form onSubmit={guardar} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>Nuevo trabajador</DialogTitle>
                        <DialogDescription>
                            Entra con este correo y la contraseña inicial que le
                            entregues; luego la cambia en su perfil.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-1.5">
                        <Label htmlFor="nuevo-nombre">Nombre completo</Label>
                        <Input
                            id="nuevo-nombre"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            autoFocus
                        />
                        <InputError message={form.errors.name} />
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="nuevo-correo">Correo</Label>
                        <Input
                            id="nuevo-correo"
                            type="email"
                            value={form.data.email}
                            onChange={(e) =>
                                form.setData('email', e.target.value)
                            }
                        />
                        <InputError message={form.errors.email} />
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label htmlFor="nuevo-rol">Rol</Label>
                            <select
                                id="nuevo-rol"
                                value={form.data.role}
                                onChange={(e) =>
                                    form.setData((datos) => ({
                                        ...datos,
                                        role: e.target.value,
                                        sede_id: '',
                                    }))
                                }
                                className="border-input bg-background h-9 w-full rounded-md border px-2 text-sm"
                            >
                                {roles.map((rol) => (
                                    <option key={rol} value={rol}>
                                        {roleLabel(rol)}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.role} />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="nuevo-sede">Sede</Label>
                            <select
                                id="nuevo-sede"
                                value={form.data.sede_id}
                                onChange={(e) =>
                                    form.setData('sede_id', e.target.value)
                                }
                                className="border-input bg-background h-9 w-full rounded-md border px-2 text-sm"
                            >
                                <option value="">
                                    {form.data.role === 'Gerente'
                                        ? 'Todas las sedes'
                                        : 'Elige una sede'}
                                </option>
                                {sedes.map((sede) => (
                                    <option key={sede.id} value={sede.id}>
                                        {sede.nombre}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.sede_id} />
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="nuevo-clave">Contraseña inicial</Label>
                        <Input
                            id="nuevo-clave"
                            type="text"
                            autoComplete="off"
                            value={form.data.password}
                            onChange={(e) =>
                                form.setData('password', e.target.value)
                            }
                            placeholder="Mínimo 8 caracteres"
                        />
                        <InputError message={form.errors.password} />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setAbierto(false)}
                        >
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Creando…' : 'Crear usuario'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
