import { Head, router, useForm, usePage } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AlmacenLayout from '@/layouts/almacen-layout';
import { index, store } from '@/routes/almacen/traslados';
import type { Team } from '@/types';

type Option = { id: number; nombre: string; codigo?: string };

export default function Transfers({
    sourceSede,
    origenes,
    destinations,
    bulkProducts,
}: {
    sourceSede: Option;
    origenes: Option[];
    destinations: Option[];
    bulkProducts: Option[];
}) {
    const form = useForm({
        origen_sede_id: String(sourceSede.id),
        destination_sede_id: '',
        serials_text: '',
        product_id: '',
        quantity: 1,
        observation: '',
    });
    const teamSlug =
        usePage<{ currentTeam?: Team | null }>().props.currentTeam?.slug ?? '';

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
                        <Label>Origen</Label>
                        <select
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
                            onSuccess: () => form.reset(),
                        });
                    }}
                >
                    <div>
                        <Label>Destino</Label>
                        <select
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
                        <Label>Unidades BF-EQ</Label>
                        <textarea
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
                        <Label>Observación</Label>
                        <Input
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
                        >
                            {error}
                        </p>
                    ))}
                    <Button type="submit" disabled={form.processing}>
                        Confirmar traslado
                    </Button>
                </form>
            </Card>
        </AlmacenLayout>
    );
}
