import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AlmacenLayout from '@/layouts/almacen-layout';
import { confirmar, index, store } from '@/routes/almacen/traslados';
import guias from '@/routes/guias';
import type { Team } from '@/types';

type Option = { id: number; nombre: string; codigo?: string };

type EnTransito = {
    id: number;
    origen: string;
    destino: string;
    bienes: number;
    puede_confirmar: boolean;
    guia_aceptada: boolean;
    tiene_guia: boolean;
};

export default function Transfers({
    sourceSede,
    origenes,
    destinations,
    bulkProducts,
    enTransito,
}: {
    sourceSede: Option | null;
    origenes: Option[];
    destinations: Option[];
    bulkProducts: Option[];
    enTransito: EnTransito[];
}) {
    const form = useForm({
        origen_sede_id: sourceSede ? String(sourceSede.id) : '',
        destination_sede_id: '',
        serials_text: '',
        product_id: '',
        quantity: 1,
        observation: '',
    });
    const teamSlug =
        usePage<{ currentTeam?: Team | null }>().props.currentTeam?.slug ?? '';

    if (!sourceSede) {
        return (
            <AlmacenLayout title="Traslados">
                <Head title="Traslados entre sedes" />
                <Card className="mx-auto max-w-2xl space-y-2 p-6">
                    <h1 className="text-xl font-bold">Traslado entre sedes</h1>
                    <p className="text-muted-foreground text-sm">
                        No hay un almacén activo desde el cual trasladar. Activa
                        una sede de tipo almacén o mixta en Sedes para empezar.
                    </p>
                </Card>
            </AlmacenLayout>
        );
    }

    return (
        <AlmacenLayout title="Traslados">
            <Head title="Traslados entre sedes" />
            <Card className="mx-auto max-w-2xl space-y-5 p-6">
                <div>
                    <h1 className="text-xl font-bold">Traslado entre sedes</h1>
                    <p className="text-muted-foreground text-sm">
                        Origen: {sourceSede.nombre}. Escanea unidades BF-EQ o
                        indica un producto sin serie.
                    </p>
                </div>
                {origenes.length > 0 ? (
                    <div>
                        <Label htmlFor="almacen-traslados-origen">Origen</Label>
                        <select
                            id="almacen-traslados-origen"
                            value={sourceSede.id}
                            onChange={(event) =>
                                router.get(
                                    index.url(teamSlug, {
                                        query: {
                                            origen_sede_id: event.target.value,
                                        },
                                    }),
                                )
                            }
                            className="border-border bg-background mt-1 h-10 w-full rounded-md border px-3"
                        >
                            {origenes.map((sede) => (
                                <option key={sede.id} value={sede.id}>
                                    {sede.nombre}
                                </option>
                            ))}
                        </select>
                    </div>
                ) : null}
                <form
                    className="space-y-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.transform((data) => ({
                            ...data,
                            serials: data.serials_text
                                .split(/[,\n]/u)
                                .map((value) => value.trim())
                                .filter(Boolean),
                        }));
                        form.post(store.url(teamSlug), {
                            onError: (errors) =>
                                toast.error(
                                    Object.values(errors)[0] ??
                                        'No se pudo completar la acción.',
                                ),
                            onSuccess: () => form.reset(),
                        });
                    }}
                >
                    <div>
                        <Label htmlFor="almacen-traslados-destino">
                            Destino
                        </Label>
                        <select
                            id="almacen-traslados-destino"
                            value={form.data.destination_sede_id}
                            onChange={(event) =>
                                form.setData(
                                    'destination_sede_id',
                                    event.target.value,
                                )
                            }
                            className="border-border bg-background mt-1 h-10 w-full rounded-md border px-3"
                            required
                        >
                            <option value="">Seleccionar sede</option>
                            {destinations.map((sede) => (
                                <option key={sede.id} value={sede.id}>
                                    {sede.nombre}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <Label htmlFor="almacen-traslados-unidades-bf-eq">
                            Unidades BF-EQ
                        </Label>
                        <textarea
                            id="almacen-traslados-unidades-bf-eq"
                            value={form.data.serials_text}
                            onChange={(event) =>
                                form.setData('serials_text', event.target.value)
                            }
                            placeholder="Una serie por línea"
                            className="border-border bg-background mt-1 min-h-28 w-full rounded-md border p-3"
                        />
                    </div>
                    <p className="text-muted-foreground text-center text-xs">
                        o, para productos sin serie
                    </p>
                    <div className="grid gap-3 sm:grid-cols-[1fr_120px]">
                        <select
                            aria-label="Producto sin serie"
                            value={form.data.product_id}
                            onChange={(event) =>
                                form.setData('product_id', event.target.value)
                            }
                            className="border-border bg-background h-10 rounded-md border px-3"
                        >
                            <option value="">Seleccionar producto</option>
                            {bulkProducts.map((product) => (
                                <option key={product.id} value={product.id}>
                                    {product.codigo} · {product.nombre}
                                </option>
                            ))}
                        </select>
                        <Input
                            aria-label="Cantidad a trasladar"
                            type="number"
                            min={1}
                            value={form.data.quantity}
                            onChange={(event) =>
                                form.setData(
                                    'quantity',
                                    Number(event.target.value),
                                )
                            }
                        />
                    </div>
                    <div>
                        <Label htmlFor="almacen-traslados-observacion">
                            Observación
                        </Label>
                        <Input
                            id="almacen-traslados-observacion"
                            value={form.data.observation}
                            onChange={(event) =>
                                form.setData('observation', event.target.value)
                            }
                        />
                    </div>
                    {Object.values(form.errors).map((error) => (
                        <p
                            key={error}
                            className="text-destructive-strong text-xs"
                            role="alert"
                        >
                            {error}
                        </p>
                    ))}
                    <Button type="submit" disabled={form.processing}>
                        Confirmar traslado
                    </Button>
                </form>
            </Card>
            {enTransito.length > 0 ? (
                <Card className="mx-auto mt-4 max-w-2xl space-y-3 p-6">
                    <h2 className="text-lg font-bold">Traslados en tránsito</h2>
                    <p className="text-muted-foreground text-sm">
                        El stock entra al almacén destino cuando confirma la
                        llegada. La guía de remisión (motivo 04) debe tener el
                        CDR aceptado antes de que salga el vehículo.
                    </p>
                    <ul className="divide-border divide-y">
                        {enTransito.map((traslado) => (
                            <li
                                key={traslado.id}
                                className="flex flex-wrap items-center gap-2 py-3 text-sm"
                            >
                                <span className="flex-1">
                                    {traslado.origen} → {traslado.destino} ·{' '}
                                    {traslado.bienes} bienes
                                    {traslado.guia_aceptada
                                        ? ' · guía lista para trasladar'
                                        : traslado.tiene_guia
                                          ? ' · guía pendiente de SUNAT'
                                          : ' · sin guía'}
                                </span>
                                {!traslado.tiene_guia ? (
                                    <Button asChild variant="outline" size="sm">
                                        <Link
                                            href={guias.create.url(teamSlug, {
                                                query: {
                                                    origen: 'traslado',
                                                    id: traslado.id,
                                                },
                                            })}
                                        >
                                            Emitir guía
                                        </Link>
                                    </Button>
                                ) : null}
                                {traslado.puede_confirmar ? (
                                    <Button
                                        size="sm"
                                        onClick={() =>
                                            router.post(
                                                confirmar.url({
                                                    current_team: teamSlug,
                                                    traslado: traslado.id,
                                                }),
                                                {},
                                                {
                                                    onError: (errors) =>
                                                        toast.error(
                                                            Object.values(
                                                                errors,
                                                            )[0] ??
                                                                'No se pudo completar la acción.',
                                                        ),
                                                },
                                            )
                                        }
                                    >
                                        Confirmar llegada
                                    </Button>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                </Card>
            ) : null}
        </AlmacenLayout>
    );
}
