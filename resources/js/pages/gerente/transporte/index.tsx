import { Head, router, useForm, usePage } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import GerenteLayout from '@/layouts/gerente-layout';
import transporte from '@/routes/gerente/transporte';
import type { Team } from '@/types';

type Vehiculo = {
    id: number;
    placa: string;
    categoria: string;
    descripcion: string | null;
    activo: boolean;
};
type Conductor = {
    id: number;
    dni: string;
    nombres: string;
    apellidos: string;
    licencia: string;
    activo: boolean;
};

export default function Transporte({
    vehiculos,
    conductores,
    categorias,
}: {
    vehiculos: Vehiculo[];
    conductores: Conductor[];
    categorias: Record<string, string>;
}) {
    const teamSlug =
        usePage<{ currentTeam?: Team | null }>().props.currentTeam?.slug ?? '';
    const vehiculo = useForm({ placa: '', categoria: 'N', descripcion: '' });
    const conductor = useForm({
        dni: '',
        nombres: '',
        apellidos: '',
        licencia: '',
    });

    return (
        <GerenteLayout title="Transporte">
            <Head title="Vehículos y conductores" />
            <div className="mx-auto max-w-4xl space-y-4 p-4">
                <h1 className="text-xl font-bold">
                    Vehículos y conductores de las guías de remisión
                </h1>

                <Card className="space-y-3 p-4">
                    <h2 className="font-semibold">Vehículos de la empresa</h2>
                    <ul className="divide-border divide-y text-sm">
                        {vehiculos.map((v) => (
                            <li
                                key={v.id}
                                className="flex items-center gap-2 py-2"
                            >
                                <span className="flex-1">
                                    <strong className="font-mono">
                                        {v.placa}
                                    </strong>{' '}
                                    · {categorias[v.categoria] ?? v.categoria}
                                    {v.activo ? '' : ' · inactivo'}
                                </span>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        router.patch(
                                            transporte.vehiculos.toggle.url({
                                                current_team: teamSlug,
                                                vehiculo: v.id,
                                            }),
                                        )
                                    }
                                >
                                    {v.activo ? 'Desactivar' : 'Activar'}
                                </Button>
                            </li>
                        ))}
                    </ul>
                    <form
                        className="grid gap-2 sm:grid-cols-[1fr_1fr_auto]"
                        onSubmit={(event) => {
                            event.preventDefault();
                            vehiculo.post(
                                transporte.vehiculos.store.url(teamSlug),
                                { onSuccess: () => vehiculo.reset() },
                            );
                        }}
                    >
                        <div>
                            <Label htmlFor="veh-placa">Placa</Label>
                            <Input
                                id="veh-placa"
                                value={vehiculo.data.placa}
                                onChange={(e) =>
                                    vehiculo.setData('placa', e.target.value)
                                }
                            />
                            {vehiculo.errors.placa ? (
                                <p className="text-destructive-strong text-xs">
                                    {vehiculo.errors.placa}
                                </p>
                            ) : null}
                        </div>
                        <div>
                            <Label htmlFor="veh-categoria">Categoría</Label>
                            <select
                                id="veh-categoria"
                                className="border-border bg-background h-10 w-full rounded-md border px-3"
                                value={vehiculo.data.categoria}
                                onChange={(e) =>
                                    vehiculo.setData('categoria', e.target.value)
                                }
                            >
                                {Object.entries(categorias).map(([k, texto]) => (
                                    <option key={k} value={k}>
                                        {texto}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <Button
                            type="submit"
                            className="self-end"
                            disabled={vehiculo.processing}
                        >
                            Agregar vehículo
                        </Button>
                    </form>
                </Card>

                <Card className="space-y-3 p-4">
                    <h2 className="font-semibold">Conductores</h2>
                    <ul className="divide-border divide-y text-sm">
                        {conductores.map((c) => (
                            <li
                                key={c.id}
                                className="flex items-center gap-2 py-2"
                            >
                                <span className="flex-1">
                                    {c.apellidos}, {c.nombres} · DNI {c.dni} ·
                                    licencia {c.licencia}
                                    {c.activo ? '' : ' · inactivo'}
                                </span>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        router.patch(
                                            transporte.conductores.toggle.url({
                                                current_team: teamSlug,
                                                conductor: c.id,
                                            }),
                                        )
                                    }
                                >
                                    {c.activo ? 'Desactivar' : 'Activar'}
                                </Button>
                            </li>
                        ))}
                    </ul>
                    <form
                        className="grid gap-2 sm:grid-cols-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            conductor.post(
                                transporte.conductores.store.url(teamSlug),
                                { onSuccess: () => conductor.reset() },
                            );
                        }}
                    >
                        {(
                            [
                                ['dni', 'DNI'],
                                ['licencia', 'Licencia de conducir'],
                                ['nombres', 'Nombres'],
                                ['apellidos', 'Apellidos'],
                            ] as const
                        ).map(([campo, etiqueta]) => (
                            <div key={campo}>
                                <Label htmlFor={`cond-${campo}`}>
                                    {etiqueta}
                                </Label>
                                <Input
                                    id={`cond-${campo}`}
                                    value={conductor.data[campo]}
                                    onChange={(e) =>
                                        conductor.setData(campo, e.target.value)
                                    }
                                />
                                {conductor.errors[campo] ? (
                                    <p className="text-destructive-strong text-xs">
                                        {conductor.errors[campo]}
                                    </p>
                                ) : null}
                            </div>
                        ))}
                        <Button
                            type="submit"
                            className="sm:col-span-2"
                            disabled={conductor.processing}
                        >
                            Agregar conductor
                        </Button>
                    </form>
                </Card>
            </div>
        </GerenteLayout>
    );
}
