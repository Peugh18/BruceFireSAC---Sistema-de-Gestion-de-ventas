import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Truck } from 'lucide-react';
import { useMemo, useState, type FormEvent } from 'react';
import AlertError from '@/components/alert-error';
import ClientPicker, { type ClientFicha } from '@/components/client-picker';
import ReferenciaField from '@/components/referencia-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import TecnicoCampoLayout from '@/layouts/tecnico-campo-layout';
import clientes from '@/routes/tecnico-campo/clientes';
import documentos from '@/routes/tecnico-campo/documentos';
import recojos from '@/routes/tecnico-campo/recojos';
import type { Team } from '@/types';

type Props = {
    sedes: {
        id: number;
        nombre: string;
        almacen: { id: number; nombre: string } | null;
    }[];
};

export default function IniciarRecojo({ sedes }: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const equipo = currentTeam?.slug ?? '';
    const [cliente, setCliente] = useState<ClientFicha | null>(null);
    const [nuevo, setNuevo] = useState(false);
    const [consultando, setConsultando] = useState(false);
    const [mensajeConsulta, setMensajeConsulta] = useState('');
    const form = useForm({
        client_id: null as number | null,
        cliente: {
            razon_social: '',
            telefono: '',
            tipo_documento: '',
            numero_documento: '',
        },
        sede_id: sedes.length === 1 ? String(sedes[0].id) : '',
        referencia: '',
        cantidad: '1',
        prioridad: 'normal',
        observaciones: '',
        precio_acordado: '',
    });
    const [precioAcordado, setPrecioAcordado] = useState(false);
    const endpoints = useMemo(
        () => ({
            search: (search: string) =>
                clientes.search.url(equipo, { query: { search } }),
            ficha: (clientId: number) =>
                clientes.ficha.url({ current_team: equipo, client: clientId }),
        }),
        [equipo],
    );
    const elegirCliente = (value: ClientFicha | null) => {
        setCliente(value);
        form.setData('client_id', value?.id ?? null);
        setNuevo(false);
    };
    const consultarDocumento = async () => {
        const numero = form.data.cliente.numero_documento;
        if (!/^\d{8}$|^\d{11}$/.test(numero)) {
            setMensajeConsulta(
                'Escribe un DNI de 8 dígitos o RUC de 11 dígitos.',
            );
            return;
        }
        setConsultando(true);
        setMensajeConsulta('');
        try {
            const response = await fetch(endpoints.search(numero));
            if (!response.ok) {
                throw new Error('búsqueda');
            }
            const registrados = (await response.json()) as ClientFicha[];
            const existente = registrados.find(
                (item) => item.numero_documento === numero,
            );
            if (existente) {
                const ficha = await fetch(endpoints.ficha(existente.id));
                if (!ficha.ok) {
                    throw new Error('ficha');
                }
                elegirCliente((await ficha.json()) as ClientFicha);
                setMensajeConsulta(
                    'Cliente existente seleccionado. Se reutilizan sus datos.',
                );
                return;
            }
            const consulta = await fetch(
                documentos.lookup.url(equipo, {
                    query: { numero_documento: numero },
                }),
            );
            const resultado = await consulta.json();
            if (!consulta.ok) {
                setMensajeConsulta(
                    resultado.message ??
                        'Consulta no disponible. Puedes completar los datos manualmente.',
                );
                return;
            }
            form.setData('cliente', {
                ...form.data.cliente,
                razon_social:
                    resultado.razon_social ?? form.data.cliente.razon_social,
            });
            setMensajeConsulta(
                'Nombre consultado. Confirma el teléfono y lugar de recojo.',
            );
        } catch {
            setMensajeConsulta(
                'No se pudo completar la consulta. Revisa la conexión antes de registrar al cliente.',
            );
        } finally {
            setConsultando(false);
        }
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            cliente: data.client_id
                ? null
                : {
                      ...data.cliente,
                      tipo_documento: data.cliente.tipo_documento || null,
                      numero_documento: data.cliente.numero_documento || null,
                  },
            precio_acordado: precioAcordado ? data.precio_acordado : null,
        }));
        form.post(recojos.iniciar.url(equipo));
    };
    return (
        <TecnicoCampoLayout>
            <Head title="Iniciar recojo" />
            <div className="space-y-5">
                <div className="flex items-center gap-3">
                    <Button
                        asChild
                        variant="outline"
                        size="icon"
                        className="size-11 rounded-2xl"
                    >
                        <Link
                            href={recojos.index.url(equipo)}
                            aria-label="Volver a recojos"
                        >
                            <ArrowLeft />
                        </Link>
                    </Button>
                    <div>
                        <p className="text-primary-strong text-xs font-bold uppercase">
                            Campo
                        </p>
                        <h1 className="text-2xl font-bold">Iniciar recojo</h1>
                    </div>
                </div>
                <p className="text-muted-foreground text-sm">
                    Identifica al cliente y el lugar. Después registra sus
                    extintores, fotos y conformidad.
                </p>
                <form onSubmit={submit} className="space-y-4">
                    <AlertError errors={Object.values(form.errors)} />
                    <section className="border-border bg-card space-y-4 rounded-3xl border p-4">
                        <h2 className="font-semibold">
                            ¿De quién son los extintores?
                        </h2>
                        <ClientPicker
                            teamSlug={equipo}
                            value={cliente}
                            onChange={elegirCliente}
                            endpoints={endpoints}
                            onAddClient={(term) => {
                                setNuevo(true);
                                form.setData('cliente', {
                                    ...form.data.cliente,
                                    razon_social: /^\d+$/.test(term)
                                        ? ''
                                        : term,
                                    numero_documento: /^\d{8}$|^\d{11}$/.test(
                                        term,
                                    )
                                        ? term
                                        : '',
                                    tipo_documento:
                                        term.length === 8
                                            ? 'dni'
                                            : term.length === 11
                                              ? 'ruc'
                                              : '',
                                });
                            }}
                        />
                        {cliente?.telefono && (
                            <p className="text-muted-foreground text-sm">
                                Teléfono: {cliente.telefono}
                            </p>
                        )}
                        {nuevo && !cliente && (
                            <div className="space-y-3">
                                <p className="text-muted-foreground text-sm">
                                    Cliente nuevo. Si todavía no tienes su
                                    documento, lo completan después en la misma
                                    ficha.
                                </p>
                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div>
                                        <Label htmlFor="tipo-documento">
                                            Documento
                                        </Label>
                                        <NativeSelect
                                            id="tipo-documento"
                                            className="mt-1 min-h-11 w-full text-base"
                                            value={
                                                form.data.cliente.tipo_documento
                                            }
                                            onChange={(e) =>
                                                form.setData('cliente', {
                                                    ...form.data.cliente,
                                                    tipo_documento:
                                                        e.target.value,
                                                    numero_documento: '',
                                                })
                                            }
                                        >
                                            <option value="">Pendiente</option>
                                            <option value="dni">DNI</option>
                                            <option value="ruc">RUC</option>
                                        </NativeSelect>
                                    </div>
                                    {form.data.cliente.tipo_documento && (
                                        <div>
                                            <Label htmlFor="documento">
                                                Número
                                            </Label>
                                            <Input
                                                id="documento"
                                                inputMode="numeric"
                                                maxLength={
                                                    form.data.cliente
                                                        .tipo_documento ===
                                                    'dni'
                                                        ? 8
                                                        : 11
                                                }
                                                className="mt-1 min-h-11 text-base"
                                                value={
                                                    form.data.cliente
                                                        .numero_documento
                                                }
                                                onChange={(e) =>
                                                    form.setData('cliente', {
                                                        ...form.data.cliente,
                                                        numero_documento:
                                                            e.target.value.replace(
                                                                /\D/g,
                                                                '',
                                                            ),
                                                    })
                                                }
                                            />
                                        </div>
                                    )}
                                </div>
                                {form.data.cliente.tipo_documento && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        loading={consultando}
                                        onClick={() =>
                                            void consultarDocumento()
                                        }
                                    >
                                        Buscar DNI / RUC
                                    </Button>
                                )}
                                <div>
                                    <Label htmlFor="cliente-nombre">
                                        Nombre o razón social
                                    </Label>
                                    <Input
                                        id="cliente-nombre"
                                        required
                                        className="mt-1 min-h-11 text-base"
                                        value={form.data.cliente.razon_social}
                                        onChange={(e) =>
                                            form.setData('cliente', {
                                                ...form.data.cliente,
                                                razon_social: e.target.value,
                                            })
                                        }
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="cliente-telefono">
                                        Teléfono
                                    </Label>
                                    <Input
                                        id="cliente-telefono"
                                        type="tel"
                                        required
                                        className="mt-1 min-h-11 text-base"
                                        value={form.data.cliente.telefono}
                                        onChange={(e) =>
                                            form.setData('cliente', {
                                                ...form.data.cliente,
                                                telefono: e.target.value,
                                            })
                                        }
                                    />
                                </div>
                            </div>
                        )}
                        {mensajeConsulta && (
                            <p
                                role="status"
                                className="text-muted-foreground text-sm"
                            >
                                {mensajeConsulta}
                            </p>
                        )}
                    </section>
                    <section className="border-border bg-card space-y-4 rounded-3xl border p-4">
                        <h2 className="font-semibold">Lugar y trabajo</h2>
                        <ReferenciaField
                            label="Lugar / referencia / puesto"
                            placeholder="Ej. Mercado Central · puesto 24, pasillo B"
                            required
                            cliente={cliente}
                            destino="local_cliente"
                            value={form.data.referencia}
                            onChange={(value) =>
                                form.setData('referencia', value)
                            }
                            error={form.errors.referencia}
                        />
                        <p className="text-muted-foreground text-xs">
                            Indica empresa, local o mercado y puesto. Aquí se
                            devolverán los equipos.
                        </p>
                        {sedes.length === 1 ? (
                            <div className="border-border bg-muted/40 rounded-2xl border p-3">
                                <p className="text-muted-foreground text-xs font-semibold">
                                    Equipo responsable
                                </p>
                                <p className="mt-1 font-semibold">
                                    {sedes[0].nombre}
                                </p>
                                {sedes[0].almacen && (
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        Planta lo recibirá en{' '}
                                        {sedes[0].almacen.nombre}. El recojo y
                                        la cotización quedan en el mismo equipo.
                                    </p>
                                )}
                            </div>
                        ) : (
                            <div>
                                <Label htmlFor="sede">Equipo responsable</Label>
                                <NativeSelect
                                    id="sede"
                                    required
                                    value={form.data.sede_id}
                                    onChange={(e) =>
                                        form.setData('sede_id', e.target.value)
                                    }
                                    className="mt-1 min-h-11 w-full text-base"
                                >
                                    <option value="">Seleccionar equipo</option>
                                    {sedes.map((sede) => (
                                        <option key={sede.id} value={sede.id}>
                                            {sede.nombre}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </div>
                        )}
                        <div className="border-border bg-muted/40 rounded-2xl border p-3">
                            <p className="text-foreground text-sm font-semibold">
                                Recojo de extintores
                            </p>
                            <p className="text-muted-foreground mt-1 text-xs">
                                En Planta se identifica cada equipo. El vendedor
                                define los servicios y cantidades al preparar la
                                cotización.
                            </p>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <Label htmlFor="cantidad">
                                    Extintores a recoger
                                </Label>
                                <Input
                                    id="cantidad"
                                    type="number"
                                    min={1}
                                    max={100}
                                    required
                                    className="mt-1 min-h-11 text-base"
                                    value={form.data.cantidad}
                                    onChange={(e) =>
                                        form.setData('cantidad', e.target.value)
                                    }
                                />
                            </div>
                            <div>
                                <Label htmlFor="prioridad">Prioridad</Label>
                                <NativeSelect
                                    id="prioridad"
                                    value={form.data.prioridad}
                                    onChange={(e) =>
                                        form.setData(
                                            'prioridad',
                                            e.target.value,
                                        )
                                    }
                                    className="mt-1 min-h-11 w-full text-base"
                                >
                                    <option value="normal">Normal</option>
                                    <option value="alta">Alta</option>
                                    <option value="urgente">Urgente</option>
                                </NativeSelect>
                            </div>
                        </div>
                    </section>
                    <section className="border-border bg-card space-y-4 rounded-3xl border p-4">
                        <label className="flex min-h-11 items-center gap-3 text-sm font-semibold">
                            <input
                                type="checkbox"
                                checked={precioAcordado}
                                onChange={(e) =>
                                    setPrecioAcordado(e.target.checked)
                                }
                                className="size-5 accent-current"
                            />
                            Ya se acordó un precio con el cliente
                        </label>
                        {precioAcordado && (
                            <div>
                                <Label htmlFor="precio-acordado">
                                    Importe acordado (S/)
                                </Label>
                                <Input
                                    id="precio-acordado"
                                    required
                                    type="number"
                                    inputMode="decimal"
                                    min="0.01"
                                    step="0.01"
                                    className="mt-1 min-h-11 text-base"
                                    value={form.data.precio_acordado}
                                    onChange={(e) =>
                                        form.setData(
                                            'precio_acordado',
                                            e.target.value,
                                        )
                                    }
                                />
                                <p className="text-muted-foreground mt-1 text-xs">
                                    El vendedor de la sede revisará el acuerdo
                                    al cotizar.
                                </p>
                            </div>
                        )}
                        <div>
                            <Label htmlFor="observaciones">
                                Observaciones del recojo o acuerdo
                            </Label>
                            <Textarea
                                id="observaciones"
                                value={form.data.observaciones}
                                onChange={(e) =>
                                    form.setData(
                                        'observaciones',
                                        e.target.value,
                                    )
                                }
                                className="mt-1 text-base"
                            />
                        </div>
                    </section>
                    <Button
                        type="submit"
                        disabled={!cliente && !nuevo}
                        loading={form.processing}
                        className="min-h-12 w-full rounded-2xl font-semibold"
                    >
                        <Truck />
                        Continuar con equipos y fotos
                        <ArrowRight />
                    </Button>
                    <p className="text-muted-foreground text-center text-xs">
                        El recojo se confirma en el siguiente paso. Planta y
                        Vendedor reutilizan estos datos.
                    </p>
                </form>
            </div>
        </TecnicoCampoLayout>
    );
}
