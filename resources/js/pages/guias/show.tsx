import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { toast } from 'sonner';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import RoleLayout from '@/layouts/role-layout';
import guias from '@/routes/guias';
import type { Team } from '@/types';

type Item = {
    id: number;
    codigo: string | null;
    descripcion: string;
    unidad: string;
    cantidad: number | string;
    peso_kg: number | string;
};

type Guia = {
    id: number;
    numero: string;
    sede: string;
    estado_sunat: 'borrador' | 'enviada' | 'aceptada' | 'rechazada';
    motivo: string;
    motivo_descripcion: string | null;
    modalidad: string;
    fecha_emision: string;
    fecha_traslado: string;
    destinatario_tipo_doc: string;
    destinatario_num_doc: string;
    destinatario_nombre: string;
    partida_direccion: string | null;
    llegada_direccion: string | null;
    peso_bruto: number | string;
    transport_vehicle_id: number | null;
    driver_id: number | null;
    vehiculo: string | null;
    conductor: string | null;
    transportista_ruc: string | null;
    transportista_razon: string | null;
    doc_relacionado_numero: string | null;
    sunat_codigo: string | null;
    sunat_mensaje: string | null;
    items: Item[];
    origen: {
        descripcion: string;
        url: string | null;
        crear_url: string | null;
    };
    documentos: { xml?: string; cdr?: string };
    lista_para_trasladar: boolean;
    puede_editar: boolean;
    puede_enviar: boolean;
};

const TIPOS_DOC: Record<string, string> = {
    '1': 'DNI',
    '4': 'Carné de extranjería',
    '6': 'RUC',
    '7': 'Pasaporte',
};

const ESTADOS: Record<Guia['estado_sunat'], string> = {
    borrador: 'Sin enviar',
    enviada: 'En proceso en SUNAT',
    aceptada: 'Aceptada por SUNAT',
    rechazada: 'Rechazada',
};

const SELECT =
    'border-border bg-background mt-1 h-10 w-full rounded-md border px-3';

function Dato({ titulo, valor }: { titulo: string; valor?: string | null }) {
    return (
        <div>
            <dt className="text-muted-foreground text-xs">{titulo}</dt>
            <dd className="font-medium">{valor || '—'}</dd>
        </div>
    );
}

export default function GuiaShow({
    guia,
    vehiculos,
    conductores,
}: {
    guia: Guia;
    vehiculos: { id: number; placa: string }[];
    conductores: { id: number; nombres: string; apellidos: string }[];
}) {
    const { currentTeam, flash } = usePage<{
        currentTeam?: Team | null;
        flash?: { success?: string; error?: string };
    }>().props;
    const teamSlug = currentTeam?.slug ?? '';
    const form = useForm({
        fecha_traslado: guia.fecha_traslado,
        modalidad: guia.modalidad,
        peso_bruto: String(guia.peso_bruto),
        transport_vehicle_id: guia.transport_vehicle_id ?? '',
        driver_id: guia.driver_id ?? '',
        transportista_ruc: guia.transportista_ruc ?? '',
        transportista_razon: guia.transportista_razon ?? '',
    });
    const privado = form.data.modalidad === '02';

    const accion = (tipo: 'enviar' | 'consultar') =>
        router.post(
            guias[tipo].url({ current_team: teamSlug, guide: guia.id }),
            {},
            {
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ??
                            'No se pudo completar la acción.',
                    ),
                preserveScroll: true,
            },
        );

    const guardar = (e: React.FormEvent) => {
        e.preventDefault();
        form.patch(
            guias.update.url({ current_team: teamSlug, guide: guia.id }),
            {
                preserveScroll: true,
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ??
                            'No se pudo guardar el cambio.',
                    ),
            },
        );
    };

    return (
        <RoleLayout
            title={`Guía ${guia.numero}`}
            breadcrumbs={[
                {
                    title: 'Guías de remisión',
                    href: guias.index.url(teamSlug),
                },
                { title: guia.numero, href: '#' },
            ]}
        >
            <Head title={`Guía ${guia.numero}`} />
            <div className="mx-auto max-w-4xl space-y-4 p-4">
                <div className="flex flex-wrap items-center gap-3">
                    <h1 className="font-mono text-xl font-bold">
                        {guia.numero}
                    </h1>
                    <Badge
                        variant={
                            guia.lista_para_trasladar ? 'default' : 'outline'
                        }
                    >
                        {guia.lista_para_trasladar
                            ? 'Lista para trasladar'
                            : ESTADOS[guia.estado_sunat]}
                    </Badge>
                    <div className="flex-1" />
                    <Button asChild variant="outline" size="sm">
                        <Link href={guias.index.url(teamSlug)}>
                            Volver al historial
                        </Link>
                    </Button>
                </div>

                {flash?.success ? (
                    <p role="status" className="text-sm text-emerald-700">
                        {flash.success}
                    </p>
                ) : null}
                {flash?.error ? (
                    <p role="alert" className="text-destructive-strong text-sm">
                        {flash.error}
                    </p>
                ) : null}

                <Card className="space-y-3 p-4 text-sm">
                    <h2 className="font-semibold">Origen y estado</h2>
                    <dl className="grid gap-3 sm:grid-cols-2">
                        <div>
                            <dt className="text-muted-foreground text-xs">
                                Operación de origen
                            </dt>
                            <dd className="font-medium">
                                {guia.origen.url ? (
                                    <Link
                                        href={guia.origen.url}
                                        className="underline"
                                    >
                                        {guia.origen.descripcion}
                                    </Link>
                                ) : (
                                    guia.origen.descripcion
                                )}
                            </dd>
                        </div>
                        <Dato
                            titulo="Comprobante relacionado"
                            valor={guia.doc_relacionado_numero}
                        />
                        <Dato
                            titulo="Motivo"
                            valor={guia.motivo_descripcion ?? guia.motivo}
                        />
                        <Dato titulo="Sede" valor={guia.sede} />
                        <Dato titulo="Emitida" valor={guia.fecha_emision} />
                        <Dato
                            titulo="Respuesta SUNAT"
                            valor={guia.sunat_mensaje}
                        />
                    </dl>
                    {guia.estado_sunat === 'rechazada' ? (
                        <p className="text-destructive-strong">
                            SUNAT rechazó la guía. Revisa el mensaje; la guía
                            rechazada se reenvía tal como está y no se edita.
                        </p>
                    ) : null}
                    <div className="flex flex-wrap gap-2">
                        {guia.puede_enviar ? (
                            <Button size="sm" onClick={() => accion('enviar')}>
                                Enviar a SUNAT
                            </Button>
                        ) : null}
                        {guia.estado_sunat === 'enviada' ? (
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => accion('consultar')}
                            >
                                Consultar CDR
                            </Button>
                        ) : null}
                        {guia.documentos.xml ? (
                            <Button asChild size="sm" variant="outline">
                                <a href={guia.documentos.xml}>Descargar XML</a>
                            </Button>
                        ) : null}
                        {guia.documentos.cdr ? (
                            <Button asChild size="sm" variant="outline">
                                <a href={guia.documentos.cdr}>Descargar CDR</a>
                            </Button>
                        ) : null}
                    </div>
                </Card>

                <Card className="space-y-3 p-4 text-sm">
                    <h2 className="font-semibold">Destinatario y recorrido</h2>
                    <dl className="grid gap-3 sm:grid-cols-2">
                        <Dato
                            titulo="Destinatario"
                            valor={guia.destinatario_nombre}
                        />
                        <Dato
                            titulo="Documento"
                            valor={`${TIPOS_DOC[guia.destinatario_tipo_doc] ?? guia.destinatario_tipo_doc} ${guia.destinatario_num_doc}`}
                        />
                        <Dato
                            titulo="Punto de partida"
                            valor={guia.partida_direccion}
                        />
                        <Dato
                            titulo="Punto de llegada"
                            valor={guia.llegada_direccion}
                        />
                    </dl>
                </Card>

                <Card className="space-y-3 p-4 text-sm">
                    <h2 className="font-semibold">Bienes trasladados</h2>
                    <ul className="divide-border divide-y">
                        {guia.items.map((item) => (
                            <li
                                key={item.id}
                                className="flex flex-wrap gap-2 py-2"
                            >
                                <span className="min-w-48 flex-1">
                                    {item.descripcion}
                                    {item.codigo ? (
                                        <span className="text-muted-foreground">
                                            {' '}
                                            · {item.codigo}
                                        </span>
                                    ) : null}
                                </span>
                                <span className="font-mono">
                                    {item.cantidad} {item.unidad} ·{' '}
                                    {item.peso_kg} kg
                                </span>
                            </li>
                        ))}
                    </ul>
                    <p className="text-muted-foreground">
                        Peso bruto total: {guia.peso_bruto} kg. Los bienes y el
                        origen no se modifican desde esta ficha.
                    </p>
                </Card>

                <Card className="space-y-3 p-4 text-sm">
                    <h2 className="font-semibold">Transporte</h2>
                    {guia.puede_editar ? (
                        <form
                            onSubmit={guardar}
                            className="grid gap-3 sm:grid-cols-2"
                        >
                            <div>
                                <Label htmlFor="fecha_traslado">
                                    Fecha de traslado
                                </Label>
                                <Input
                                    id="fecha_traslado"
                                    type="date"
                                    value={form.data.fecha_traslado}
                                    onChange={(e) =>
                                        form.setData(
                                            'fecha_traslado',
                                            e.target.value,
                                        )
                                    }
                                />
                                {form.errors.fecha_traslado ? (
                                    <p className="text-destructive-strong">
                                        {form.errors.fecha_traslado}
                                    </p>
                                ) : null}
                            </div>
                            <div>
                                <Label htmlFor="peso_bruto">
                                    Peso bruto (kg)
                                </Label>
                                <Input
                                    id="peso_bruto"
                                    type="number"
                                    step="0.001"
                                    min="0.001"
                                    value={form.data.peso_bruto}
                                    onChange={(e) =>
                                        form.setData(
                                            'peso_bruto',
                                            e.target.value,
                                        )
                                    }
                                />
                                {form.errors.peso_bruto ? (
                                    <p className="text-destructive-strong">
                                        {form.errors.peso_bruto}
                                    </p>
                                ) : null}
                            </div>
                            <div>
                                <Label htmlFor="modalidad">Modalidad</Label>
                                <select
                                    id="modalidad"
                                    className={SELECT}
                                    value={form.data.modalidad}
                                    onChange={(e) =>
                                        form.setData(
                                            'modalidad',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="01">
                                        Transporte público (tercero)
                                    </option>
                                    <option value="02">
                                        Transporte privado (propio)
                                    </option>
                                </select>
                            </div>
                            {privado ? (
                                <>
                                    <div>
                                        <Label htmlFor="transport_vehicle_id">
                                            Vehículo
                                        </Label>
                                        <select
                                            id="transport_vehicle_id"
                                            className={SELECT}
                                            value={
                                                form.data.transport_vehicle_id
                                            }
                                            onChange={(e) =>
                                                form.setData(
                                                    'transport_vehicle_id',
                                                    e.target.value,
                                                )
                                            }
                                        >
                                            <option value="">
                                                Elige un vehículo
                                            </option>
                                            {vehiculos.map((v) => (
                                                <option key={v.id} value={v.id}>
                                                    {v.placa}
                                                </option>
                                            ))}
                                        </select>
                                        {form.errors.transport_vehicle_id ? (
                                            <p className="text-destructive-strong">
                                                {
                                                    form.errors
                                                        .transport_vehicle_id
                                                }
                                            </p>
                                        ) : null}
                                    </div>
                                    <div>
                                        <Label htmlFor="driver_id">
                                            Conductor
                                        </Label>
                                        <select
                                            id="driver_id"
                                            className={SELECT}
                                            value={form.data.driver_id}
                                            onChange={(e) =>
                                                form.setData(
                                                    'driver_id',
                                                    e.target.value,
                                                )
                                            }
                                        >
                                            <option value="">
                                                Elige un conductor
                                            </option>
                                            {conductores.map((c) => (
                                                <option key={c.id} value={c.id}>
                                                    {c.nombres} {c.apellidos}
                                                </option>
                                            ))}
                                        </select>
                                        {form.errors.driver_id ? (
                                            <p className="text-destructive-strong">
                                                {form.errors.driver_id}
                                            </p>
                                        ) : null}
                                    </div>
                                </>
                            ) : (
                                <>
                                    <div>
                                        <Label htmlFor="transportista_ruc">
                                            RUC del transportista
                                        </Label>
                                        <Input
                                            id="transportista_ruc"
                                            value={form.data.transportista_ruc}
                                            onChange={(e) =>
                                                form.setData(
                                                    'transportista_ruc',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        {form.errors.transportista_ruc ? (
                                            <p className="text-destructive-strong">
                                                {form.errors.transportista_ruc}
                                            </p>
                                        ) : null}
                                    </div>
                                    <div>
                                        <Label htmlFor="transportista_razon">
                                            Razón social del transportista
                                        </Label>
                                        <Input
                                            id="transportista_razon"
                                            value={
                                                form.data.transportista_razon
                                            }
                                            onChange={(e) =>
                                                form.setData(
                                                    'transportista_razon',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        {form.errors.transportista_razon ? (
                                            <p className="text-destructive-strong">
                                                {
                                                    form.errors
                                                        .transportista_razon
                                                }
                                            </p>
                                        ) : null}
                                    </div>
                                </>
                            )}
                            <div className="sm:col-span-2">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    Guardar transporte
                                </Button>
                                <span className="text-muted-foreground ml-3">
                                    Solo mientras la guía no se haya enviado a
                                    SUNAT.
                                </span>
                            </div>
                        </form>
                    ) : (
                        <dl className="grid gap-3 sm:grid-cols-2">
                            <Dato
                                titulo="Traslado"
                                valor={guia.fecha_traslado}
                            />
                            <Dato
                                titulo="Modalidad"
                                valor={
                                    guia.modalidad === '02'
                                        ? 'Transporte privado'
                                        : 'Transporte público'
                                }
                            />
                            <Dato titulo="Vehículo" valor={guia.vehiculo} />
                            <Dato titulo="Conductor" valor={guia.conductor} />
                            <Dato
                                titulo="Transportista"
                                valor={guia.transportista_razon}
                            />
                            <Dato
                                titulo="RUC transportista"
                                valor={guia.transportista_ruc}
                            />
                        </dl>
                    )}
                </Card>
            </div>
        </RoleLayout>
    );
}
