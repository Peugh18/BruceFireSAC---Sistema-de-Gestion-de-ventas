import { Head, useForm, usePage } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import guias from '@/routes/guias';
import type { Team } from '@/types';

type Item = {
    product_id: number | null;
    codigo: string | null;
    descripcion: string;
    unidad: string;
    cantidad: number | string;
    peso_kg: number | string;
};

type Datos = {
    sale_id?: number | null;
    service_order_id?: number | null;
    inventory_transfer_id?: number | null;
    motivo: string;
    motivo_descripcion: string | null;
    modalidad: string;
    fecha_traslado: string;
    destinatario_tipo_doc: string;
    destinatario_num_doc: string;
    destinatario_nombre: string;
    partida_ubigeo: string | null;
    partida_direccion: string | null;
    partida_cod_establecimiento: string | null;
    llegada_ubigeo: string | null;
    llegada_direccion: string | null;
    llegada_cod_establecimiento: string | null;
    peso_bruto: number | string;
    transport_vehicle_id: number | string;
    driver_id: number | string;
    transportista_ruc: string;
    transportista_razon: string;
    doc_relacionado_tipo: string | null;
    doc_relacionado_numero: string | null;
    items: Item[];
};

const SELECT =
    'border-border bg-background mt-1 h-10 w-full rounded-md border px-3';

export default function GuiaForm({
    datos,
    vehiculos,
    conductores,
    motivos,
}: {
    datos: Datos;
    vehiculos: { id: number; placa: string; categoria: string }[];
    conductores: {
        id: number;
        nombres: string;
        apellidos: string;
        dni: string;
    }[];
    motivos: Record<string, string>;
}) {
    const teamSlug =
        usePage<{ currentTeam?: Team | null }>().props.currentTeam?.slug ?? '';
    const form = useForm<Datos>(datos);

    const campo = (
        id: keyof Datos,
        etiqueta: string,
        tipo: 'text' | 'date' | 'number' = 'text',
    ) => {
        // Solo los campos de texto: un campo de más (como `items`) daría
        // "[object Object]" al convertirlo.
        const valor = form.data[id];
        const texto =
            typeof valor === 'number'
                ? valor.toString()
                : typeof valor === 'string'
                  ? valor
                  : '';

        return (
            <div>
                <Label htmlFor={`guia-${id}`}>{etiqueta}</Label>
                <Input
                    id={`guia-${id}`}
                    type={tipo}
                    step={tipo === 'number' ? '0.001' : undefined}
                    value={texto}
                    onChange={(event) => form.setData(id, event.target.value)}
                />
                {form.errors[id] ? (
                    <p className="text-destructive-strong mt-1 text-xs">
                        {form.errors[id]}
                    </p>
                ) : null}
            </div>
        );
    };

    const cambiarItem = (indice: number, cambios: Partial<Item>) =>
        form.setData(
            'items',
            form.data.items.map((item, i) =>
                i === indice ? { ...item, ...cambios } : item,
            ),
        );

    return (
        <AppLayout
            breadcrumbs={[
                {
                    title: 'Guías de remisión',
                    href: guias.index.url(teamSlug),
                },
            ]}
        >
            <Head title="Nueva guía de remisión" />
            <form
                className="mx-auto max-w-3xl space-y-4 p-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(guias.store.url(teamSlug));
                }}
            >
                <h1 className="text-xl font-bold">Nueva guía de remisión</h1>
                <Card className="grid gap-3 p-4 sm:grid-cols-2">
                    <div>
                        <Label htmlFor="guia-motivo">Motivo del traslado</Label>
                        <select
                            id="guia-motivo"
                            className={SELECT}
                            value={form.data.motivo}
                            onChange={(event) =>
                                form.setData('motivo', event.target.value)
                            }
                        >
                            {Object.entries(motivos).map(([codigo, texto]) => (
                                <option key={codigo} value={codigo}>
                                    {codigo} · {texto}
                                </option>
                            ))}
                        </select>
                    </div>
                    {campo('motivo_descripcion', 'Descripción del motivo')}
                    <div>
                        <Label htmlFor="guia-modalidad">Modalidad</Label>
                        <select
                            id="guia-modalidad"
                            className={SELECT}
                            value={form.data.modalidad}
                            onChange={(event) =>
                                form.setData('modalidad', event.target.value)
                            }
                        >
                            <option value="02">Transporte privado</option>
                            <option value="01">Transporte público</option>
                        </select>
                    </div>
                    {campo('fecha_traslado', 'Inicio del traslado', 'date')}
                    {campo('peso_bruto', 'Peso bruto total (kg)', 'number')}
                    {form.data.doc_relacionado_numero ? (
                        <p className="self-end text-sm">
                            Documento relacionado:{' '}
                            <strong>{form.data.doc_relacionado_numero}</strong>
                        </p>
                    ) : null}
                </Card>

                {form.data.modalidad === '02' ? (
                    <Card className="grid gap-3 p-4 sm:grid-cols-2">
                        <div>
                            <Label htmlFor="guia-vehiculo">Vehículo</Label>
                            <select
                                id="guia-vehiculo"
                                className={SELECT}
                                value={form.data.transport_vehicle_id}
                                onChange={(event) =>
                                    form.setData(
                                        'transport_vehicle_id',
                                        event.target.value,
                                    )
                                }
                            >
                                <option value="">Seleccionar</option>
                                {vehiculos.map((vehiculo) => (
                                    <option
                                        key={vehiculo.id}
                                        value={vehiculo.id}
                                    >
                                        {vehiculo.placa} ({vehiculo.categoria})
                                    </option>
                                ))}
                            </select>
                            {form.errors.transport_vehicle_id ? (
                                <p className="text-destructive-strong mt-1 text-xs">
                                    {form.errors.transport_vehicle_id}
                                </p>
                            ) : null}
                        </div>
                        <div>
                            <Label htmlFor="guia-conductor">Conductor</Label>
                            <select
                                id="guia-conductor"
                                className={SELECT}
                                value={form.data.driver_id}
                                onChange={(event) =>
                                    form.setData(
                                        'driver_id',
                                        event.target.value,
                                    )
                                }
                            >
                                <option value="">Seleccionar</option>
                                {conductores.map((conductor) => (
                                    <option
                                        key={conductor.id}
                                        value={conductor.id}
                                    >
                                        {conductor.apellidos},{' '}
                                        {conductor.nombres} · DNI{' '}
                                        {conductor.dni}
                                    </option>
                                ))}
                            </select>
                            {form.errors.driver_id ? (
                                <p className="text-destructive-strong mt-1 text-xs">
                                    {form.errors.driver_id}
                                </p>
                            ) : null}
                        </div>
                    </Card>
                ) : (
                    <Card className="grid gap-3 p-4 sm:grid-cols-2">
                        {campo('transportista_ruc', 'RUC del transportista')}
                        {campo(
                            'transportista_razon',
                            'Razón social del transportista',
                        )}
                    </Card>
                )}

                <Card className="grid gap-3 p-4 sm:grid-cols-2">
                    <div>
                        <Label htmlFor="guia-destinatario_tipo_doc">
                            Tipo de documento del destinatario
                        </Label>
                        <select
                            id="guia-destinatario_tipo_doc"
                            className={SELECT}
                            value={form.data.destinatario_tipo_doc}
                            onChange={(event) =>
                                form.setData(
                                    'destinatario_tipo_doc',
                                    event.target.value,
                                )
                            }
                        >
                            <option value="6">RUC</option>
                            <option value="1">DNI</option>
                        </select>
                    </div>
                    {campo('destinatario_num_doc', 'Número de documento')}
                    {campo('destinatario_nombre', 'Nombre o razón social')}
                </Card>

                <Card className="grid gap-3 p-4 sm:grid-cols-2">
                    {campo('partida_direccion', 'Dirección de partida')}
                    {campo('partida_ubigeo', 'Ubigeo de partida (6 dígitos)')}
                    {campo('llegada_direccion', 'Dirección de llegada')}
                    {campo('llegada_ubigeo', 'Ubigeo de llegada (6 dígitos)')}
                </Card>

                <Card className="space-y-3 p-4">
                    <h2 className="font-semibold">Bienes a trasladar</h2>
                    {form.data.items.map((item, indice) => (
                        <div
                            key={`${item.codigo}-${indice}`}
                            className="grid gap-2 sm:grid-cols-[1fr_90px_110px]"
                        >
                            <Input
                                aria-label="Descripción del bien"
                                value={item.descripcion}
                                onChange={(event) =>
                                    cambiarItem(indice, {
                                        descripcion: event.target.value,
                                    })
                                }
                            />
                            <Input
                                aria-label="Cantidad"
                                type="number"
                                min={0}
                                step="0.001"
                                value={item.cantidad}
                                onChange={(event) =>
                                    cambiarItem(indice, {
                                        cantidad: event.target.value,
                                    })
                                }
                            />
                            <Input
                                aria-label="Peso en kg"
                                type="number"
                                min={0}
                                step="0.001"
                                value={item.peso_kg}
                                onChange={(event) =>
                                    cambiarItem(indice, {
                                        peso_kg: event.target.value,
                                    })
                                }
                            />
                        </div>
                    ))}
                    {form.errors.items ? (
                        <p className="text-destructive-strong text-xs">
                            {form.errors.items}
                        </p>
                    ) : null}
                </Card>

                {Object.entries(form.errors)
                    .filter(([clave]) => clave.startsWith('items.'))
                    .map(([clave, error]) => (
                        <p
                            key={clave}
                            className="text-destructive-strong text-xs"
                        >
                            {error}
                        </p>
                    ))}
                <Button type="submit" disabled={form.processing}>
                    Crear guía
                </Button>
            </form>
        </AppLayout>
    );
}
