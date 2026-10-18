import { Pasos } from '@/components/progreso';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AlmacenLayout from '@/layouts/almacen-layout';
import { confirmar, despachar, index, store } from '@/routes/almacen/traslados';
import type { Team } from '@/types';
import { NativeSelect } from '@/components/ui/native-select';

type Option = { id: number; nombre: string; codigo?: string };

type EnTransito = {
    id: number;
    origen: string;
    destino: string;
    bienes: number;
    estado: string;
    puede_despachar: boolean;
    puede_confirmar: boolean;
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
    const [serials, setSerials] = useState<string[]>([]);
    const [scanValue, setScanValue] = useState('');
    const scanInputRef = useRef<HTMLInputElement | null>(null);
    const teamSlug =
        usePage<{ currentTeam?: Team | null }>().props.currentTeam?.slug ?? '';

    const addSerial = () => {
        const value = scanValue.trim();

        if (value !== '') {
            setSerials((current) =>
                current.includes(value) ? current : [...current, value],
            );
        }

        setScanValue('');
        scanInputRef.current?.focus();
    };

    const removeSerial = (serialToRemove: string) => {
        setSerials((current) =>
            current.filter((serial) => serial !== serialToRemove),
        );
        scanInputRef.current?.focus();
    };

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
                        <NativeSelect
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
                        </NativeSelect>
                    </div>
                ) : null}
                {destinations.length === 0 && (
                    <p role="status" className="text-muted-foreground text-sm">
                        No hay otro almacén activo de destino. Solicita al
                        Gerente activar una sede de tipo almacén o mixta para
                        trasladar.
                    </p>
                )}
                <form
                    className="space-y-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.transform((data) => ({
                            ...data,
                            serials_text: serials.join('\n'),
                            serials,
                        }));
                        form.post(store.url(teamSlug), {
                            onError: (errors) =>
                                toast.error(
                                    Object.values(errors)[0] ??
                                        'No se pudo completar la acción.',
                                ),
                            onSuccess: () => {
                                form.reset();
                                setSerials([]);
                                setScanValue('');
                                scanInputRef.current?.focus();
                            },
                        });
                    }}
                >
                    <div>
                        <Label htmlFor="almacen-traslados-destino">
                            Destino
                        </Label>
                        <NativeSelect
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
                        </NativeSelect>
                    </div>
                    <div>
                        <Label htmlFor="almacen-traslados-unidades-bf-eq">
                            Escanea las unidades BF-EQ
                        </Label>
                        <Input
                            id="almacen-traslados-unidades-bf-eq"
                            ref={scanInputRef}
                            value={scanValue}
                            onChange={(event) =>
                                setScanValue(event.target.value)
                            }
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    event.preventDefault();
                                    addSerial();
                                }
                            }}
                            placeholder="BF-EQ-000001"
                            autoComplete="off"
                            autoFocus
                            className="border-border bg-background mt-1 font-mono"
                        />
                        <div className="mt-2 flex flex-wrap gap-2">
                            {serials.map((serial) => (
                                <span
                                    key={serial}
                                    className="border-border bg-muted/40 inline-flex items-center gap-1 rounded-full border px-2.5 py-1 font-mono text-xs"
                                >
                                    {serial}
                                    <button
                                        type="button"
                                        onClick={() => removeSerial(serial)}
                                        aria-label={'Quitar ' + serial}
                                        className="text-muted-foreground hover:text-foreground rounded-full p-0.5 transition-colors"
                                    >
                                        <X className="size-3.5" />
                                    </button>
                                </span>
                            ))}
                        </div>
                    </div>
                    <p className="text-muted-foreground text-center text-xs">
                        o, para productos sin serie
                    </p>
                    <div className="grid gap-3 sm:grid-cols-[1fr_120px]">
                        <NativeSelect
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
                        </NativeSelect>
                        <div>
                            <Label htmlFor="almacen-traslados-cantidad">
                                Cantidad
                            </Label>
                            <Input
                                id="almacen-traslados-cantidad"
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
                    <Button
                        type="submit"
                        disabled={destinations.length === 0}
                        loading={form.processing}
                    >
                        Preparar traslado
                    </Button>
                </form>
            </Card>
            {enTransito.length > 0 ? (
                <Card className="mx-auto mt-4 max-w-2xl space-y-3 p-6">
                    <h2 className="text-lg font-bold">
                        Traslados pendientes y en tránsito
                    </h2>
                    <p className="text-muted-foreground text-sm">
                        Prepara el traslado y despáchalo cuando salga del
                        almacén. El stock entra al destino cuando se confirma la
                        llegada.
                    </p>
                    <ul className="divide-border divide-y">
                        {enTransito.map((traslado) => (
                            <li
                                key={traslado.id}
                                className="flex flex-wrap items-center gap-2 py-3 text-sm"
                            >
                                <span className="flex-1">
                                    {traslado.origen} → {traslado.destino} ·{' '}
                                    {traslado.bienes} bienes ·{' '}
                                    {traslado.estado === 'pendiente_despacho'
                                        ? 'por despachar'
                                        : 'en tránsito'}
                                </span>
                                <Pasos
                                    label={`Traslado de ${traslado.origen} a ${traslado.destino}`}
                                    variante="puntos"
                                    pasos={[
                                        {
                                            id: 'preparado',
                                            label: 'Preparado',
                                            estado: 'completado',
                                        },
                                        {
                                            id: 'transito',
                                            label: 'En tránsito',
                                            estado:
                                                traslado.estado ===
                                                'en_transito'
                                                    ? 'actual'
                                                    : 'pendiente',
                                        },
                                        {
                                            id: 'recibido',
                                            label: 'Recibido',
                                            estado: 'pendiente',
                                        },
                                    ]}
                                />
                                {traslado.puede_despachar ? (
                                    <Button
                                        size="sm"
                                        onClick={() =>
                                            router.post(
                                                despachar.url({
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
                                                                'No se pudo despachar.',
                                                        ),
                                                },
                                            )
                                        }
                                    >
                                        Despachar
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
