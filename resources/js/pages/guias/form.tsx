import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { InputPeru } from '@/components/ui/input-peru';
import { Label } from '@/components/ui/label';
import RoleLayout from '@/layouts/role-layout';
import guias from '@/routes/guias';
import type { Team } from '@/types';
import { NativeSelect } from '@/components/ui/native-select';

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
    nuevo_vehiculo_placa: string;
    nuevo_vehiculo_categoria: string;
    nuevo_conductor_dni: string;
    nuevo_conductor_nombres: string;
    nuevo_conductor_apellidos: string;
    nuevo_conductor_licencia: string;
    items: Item[];
};

const SELECT =
    'border-border bg-background mt-1 h-10 w-full rounded-md border px-3';

export default function GuiaForm({
    datos,
    origen,
    vehiculos,
    conductores,
    motivos,
}: {
    datos: Datos;
    origen: { descripcion: string; url: string };
    vehiculos: { id: number; placa: string; categoria: string }[];
    conductores: {
        id: number;
        nombres: string;
        apellidos: string;
        dni: string;
        licencia: string;
    }[];
    motivos: Record<string, string>;
}) {
    const teamSlug =
        usePage<{ currentTeam?: Team | null }>().props.currentTeam?.slug ?? '';
    const [nuevoVehiculo, setNuevoVehiculo] = useState(false);
    const [nuevoConductor, setNuevoConductor] = useState(false);
    // Con un solo vehículo o conductor activo no hay nada que elegir.
    const form = useForm<Datos>({
        ...datos,
        nuevo_vehiculo_placa: '',
        nuevo_vehiculo_categoria: 'N',
        nuevo_conductor_dni: '',
        nuevo_conductor_nombres: '',
        nuevo_conductor_apellidos: '',
        nuevo_conductor_licencia: '',
        transport_vehicle_id:
            datos.transport_vehicle_id ||
            (vehiculos.length === 1 ? vehiculos[0].id : ''),
        driver_id:
            datos.driver_id ||
            (conductores.length === 1 ? conductores[0].id : ''),
    });

    const campo = (
        id: keyof Datos,
        etiqueta: string,
        tipo: 'text' | 'date' | 'number' = 'text',
        soloLectura = false,
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

        // Documentos con su largo exacto: DNI de 8 o RUC de 11, sin letras.
        const documento =
            id === 'transportista_ruc'
                ? ('ruc' as const)
                : id === 'nuevo_conductor_dni'
                  ? ('dni' as const)
                  : id === 'destinatario_num_doc'
                    ? form.data.destinatario_tipo_doc === '1'
                        ? ('dni' as const)
                        : ('ruc' as const)
                    : null;

        return (
            <div>
                <Label htmlFor={`guia-${id}`}>{etiqueta}</Label>
                {documento ? (
                    <InputPeru
                        tipo={documento}
                        id={`guia-${id}`}
                        readOnly={soloLectura}
                        value={texto}
                        onChange={(event) =>
                            form.setData(id, event.target.value)
                        }
                    />
                ) : (
                    <Input
                        id={`guia-${id}`}
                        type={tipo}
                        step={tipo === 'number' ? '0.001' : undefined}
                        readOnly={soloLectura}
                        value={texto}
                        onChange={(event) =>
                            form.setData(id, event.target.value)
                        }
                    />
                )}
                {form.errors[id] ? (
                    <p className="text-destructive-strong mt-1 text-xs">
                        {form.errors[id]}
                    </p>
                ) : null}
            </div>
        );
    };

    return (
        <RoleLayout
            title="Guías de remisión"
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
                    form.post(guias.store.url(teamSlug), {
                        onError: (errors) =>
                            toast.error(
                                Object.values(errors)[0] ??
                                    'No se pudo completar la acción.',
                            ),
                    });
                }}
            >
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-bold">
                            Nueva guía de remisión
                        </h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {origen.descripcion}
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={origen.url}>
                            {origen.url.endsWith('/guias')
                                ? 'Volver a guías'
                                : 'Volver al origen'}
                        </Link>
                    </Button>
                </div>
                <p className="text-muted-foreground text-sm">
                    Revisa los bienes, las direcciones y el transporte. Crear la
                    guía la deja sin enviar; el envío a SUNAT se realiza
                    después.
                </p>
                <Card className="grid gap-3 p-4 sm:grid-cols-2">
                    <div>
                        <Label htmlFor="guia-motivo">Motivo del traslado</Label>
                        <NativeSelect
                            disabled
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
                        </NativeSelect>
                    </div>

                    <div>
                        <Label htmlFor="guia-modalidad">Modalidad</Label>
                        <NativeSelect
                            id="guia-modalidad"
                            className={SELECT}
                            value={form.data.modalidad}
                            onChange={(event) =>
                                form.setData('modalidad', event.target.value)
                            }
                        >
                            <option value="02">Transporte privado</option>
                            <option value="01">Transporte público</option>
                        </NativeSelect>
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
                            <NativeSelect
                                disabled={nuevoVehiculo}
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
                            </NativeSelect>
                            <label className="mt-3 flex min-h-11 items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={nuevoVehiculo}
                                    onChange={(event) => {
                                        setNuevoVehiculo(event.target.checked);
                                        form.setData(
                                            'transport_vehicle_id',
                                            '',
                                        );
                                        form.setData(
                                            'nuevo_vehiculo_placa',
                                            '',
                                        );
                                    }}
                                />{' '}
                                Agregar vehículo
                            </label>
                            {nuevoVehiculo && (
                                <div className="space-y-3">
                                    {campo('nuevo_vehiculo_placa', 'Placa')}
                                    <Label htmlFor="guia-categoria">
                                        Categoría del vehículo
                                    </Label>
                                    <NativeSelect
                                        id="guia-categoria"
                                        value={
                                            form.data.nuevo_vehiculo_categoria
                                        }
                                        onChange={(event) =>
                                            form.setData(
                                                'nuevo_vehiculo_categoria',
                                                event.target.value,
                                            )
                                        }
                                    >
                                        <option value="N">N (camión)</option>
                                        <option value="M1">M1 (auto)</option>
                                        <option value="L">L (moto)</option>
                                    </NativeSelect>
                                </div>
                            )}
                            {form.errors.transport_vehicle_id ? (
                                <p className="text-destructive-strong mt-1 text-xs">
                                    {form.errors.transport_vehicle_id}
                                </p>
                            ) : null}
                        </div>
                        <div>
                            <Label htmlFor="guia-conductor">Conductor</Label>
                            <NativeSelect
                                disabled={nuevoConductor}
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
                                        {conductor.dni} · Licencia{' '}
                                        {conductor.licencia}
                                    </option>
                                ))}
                            </NativeSelect>
                            <label className="mt-3 flex min-h-11 items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={nuevoConductor}
                                    onChange={(event) => {
                                        setNuevoConductor(event.target.checked);
                                        form.setData('driver_id', '');
                                        form.setData('nuevo_conductor_dni', '');
                                    }}
                                />{' '}
                                Agregar conductor
                            </label>
                            {nuevoConductor && (
                                <div className="space-y-3">
                                    {campo(
                                        'nuevo_conductor_dni',
                                        'DNI del conductor',
                                    )}
                                    {campo(
                                        'nuevo_conductor_nombres',
                                        'Nombres del conductor',
                                    )}
                                    {campo(
                                        'nuevo_conductor_apellidos',
                                        'Apellidos del conductor',
                                    )}
                                    {campo(
                                        'nuevo_conductor_licencia',
                                        'Licencia de conducir',
                                    )}
                                </div>
                            )}
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
                        <NativeSelect
                            disabled
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
                        </NativeSelect>
                    </div>
                    {campo(
                        'destinatario_num_doc',
                        'Número de documento',
                        'text',
                        true,
                    )}
                    {campo(
                        'destinatario_nombre',
                        'Nombre o razón social',
                        'text',
                        true,
                    )}
                </Card>

                <Card className="grid gap-3 p-4 sm:grid-cols-2">
                    {campo('partida_direccion', 'Dirección de partida')}
                    {campo('partida_ubigeo', 'Ubigeo de partida (6 dígitos)')}
                    {campo('llegada_direccion', 'Dirección de llegada')}
                    {campo('llegada_ubigeo', 'Ubigeo de llegada (6 dígitos)')}
                </Card>

                <Card className="space-y-3 p-4">
                    <h2 className="font-semibold">Bienes de la venta</h2>
                    <p className="text-muted-foreground text-sm">
                        Se copian del comprobante. Peso total en kilogramos;
                        cantidades según la unidad de cada bien.
                    </p>
                    {form.data.items.map((item, indice) => (
                        <div
                            key={`${item.codigo}-${indice}`}
                            className="grid gap-2 sm:grid-cols-[1fr_90px_110px]"
                        >
                            <Input
                                aria-label="Descripción del bien"
                                value={item.descripcion}
                                readOnly
                            />
                            <Input
                                aria-label={`Cantidad (${item.unidad})`}
                                title={`Unidad: ${item.unidad}`}
                                type="number"
                                min={0}
                                step="0.001"
                                value={item.cantidad}
                                readOnly
                            />
                            <Input
                                aria-label="Peso en kg (viene del producto)"
                                title="El peso viene del producto; el peso bruto total sí se puede ajustar abajo."
                                type="number"
                                readOnly
                                className="bg-muted/50"
                                value={item.peso_kg}
                            />
                        </div>
                    ))}
                    {form.errors.items ? (
                        <p
                            className="text-destructive-strong text-xs"
                            role="alert"
                        >
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
                            role="alert"
                        >
                            {error}
                        </p>
                    ))}
                <div className="flex flex-wrap justify-end gap-3">
                    <Button asChild variant="outline">
                        <Link href={origen.url}>Cancelar</Link>
                    </Button>
                    <Button type="submit" loading={form.processing}>
                        Crear guía sin enviar
                    </Button>
                </div>
            </form>
        </RoleLayout>
    );
}
