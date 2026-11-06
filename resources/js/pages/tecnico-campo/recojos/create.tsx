import { Head, useForm, usePage } from '@inertiajs/react';
import { Building2, MapPin, Plus } from 'lucide-react';
import { useMemo, useState, type FormEvent, type MouseEvent } from 'react';

import AlertError from '@/components/alert-error';
import ClientCreateDialog, {
    type RutasAltaCliente,
} from '@/components/client-create-dialog';
import ClientPicker, { type ClientFicha } from '@/components/client-picker';
import NuevoLocalCampo from '@/components/nuevo-local-campo';
import {
    botonPrincipal,
    botonSecundario,
    CabeceraPaso,
    PiePaso,
    tarjeta,
} from '@/components/paso-campo';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
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

type Lugar = {
    clave: string;
    nombre: string;
    direccion: string;
    valor: string;
};

/** Nuevo recojo, pasos 1 y 2: de quién son los extintores y de dónde salen. */
export default function IniciarRecojo({ sedes }: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const equipo = currentTeam?.slug ?? '';
    const [paso, setPaso] = useState<1 | 2>(1);
    const [cliente, setCliente] = useState<ClientFicha | null>(null);
    const [altaAbierta, setAltaAbierta] = useState(false);
    const [documentoInicial, setDocumentoInicial] = useState('');
    const form = useForm({
        client_id: null as number | null,
        sede_id: sedes.length === 1 ? String(sedes[0].id) : '',
        referencia: '',
    });
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
        // Se propone la tienda donde el cliente compra: ahí lo cobra el vendedor.
        const habitual = (value as { sede_habitual?: number | null } | null)
            ?.sede_habitual;
        form.setData((data) => ({
            ...data,
            client_id: value?.id ?? null,
            referencia: '',
            sede_id:
                habitual && sedes.some((s) => s.id === habitual)
                    ? String(habitual)
                    : data.sede_id,
        }));
    };
    // El alta de campo usa la misma pantalla y acción que Clientes del vendedor.
    const rutasAlta: RutasAltaCliente = {
        guardar: clientes.store.url(equipo),
        buscar: endpoints.search,
        consultar: (numero) =>
            documentos.lookup.url(equipo, {
                query: { numero_documento: numero },
            }),
    };
    const traerFicha = async (id: number) => {
        const ficha = await fetch(endpoints.ficha(id));

        return ficha.ok ? ((await ficha.json()) as ClientFicha) : null;
    };

    // El local elegido viaja como "SEDE: nombre": de ahí salen la dirección
    // de la constancia, de la entrega y del certificado.
    const lugares: Lugar[] = [
        ...(cliente?.sedes ?? []).map((sede) => ({
            clave: `sede-${sede.id}`,
            nombre: sede.nombre,
            direccion: sede.direccion ?? '',
            valor: `SEDE: ${sede.nombre}`,
        })),
        ...(cliente?.direccion_fiscal
            ? [
                  {
                      clave: 'fiscal',
                      nombre: 'Dirección fiscal',
                      direccion: cliente.direccion_fiscal,
                      valor: `Dirección fiscal: ${cliente.direccion_fiscal}`,
                  },
              ]
            : []),
    ];

    const submit = (event: FormEvent | MouseEvent) => {
        event.preventDefault();
        form.post(recojos.iniciar.url(equipo));
    };

    return (
        <TecnicoCampoLayout ocultarMenu>
            <Head title="Nuevo recojo" />
            <ClientCreateDialog
                open={altaAbierta}
                onOpenChange={setAltaAbierta}
                teamSlug={equipo}
                initialDocumento={documentoInicial}
                rutas={rutasAlta}
                onCreated={(creado) =>
                    void traerFicha(creado.id).then(elegirCliente)
                }
            />
            <form onSubmit={submit} className="space-y-5">
                {paso === 1 ? (
                    <>
                        <CabeceraPaso
                            etiqueta="Recojo"
                            titulo="Cliente"
                            volver={recojos.index.url(equipo)}
                            paso={1}
                            total={4}
                        />
                        <section className={`${tarjeta} space-y-3 p-4`}>
                            <ClientPicker
                                teamSlug={equipo}
                                value={cliente}
                                onChange={elegirCliente}
                                endpoints={endpoints}
                                autoFocus
                                onAddClient={(term) => {
                                    setDocumentoInicial(
                                        /^\d{8}$|^\d{11}$/.test(term)
                                            ? term
                                            : '',
                                    );
                                    setAltaAbierta(true);
                                }}
                            />
                        </section>
                        {!cliente ? (
                            <button
                                type="button"
                                onClick={() => {
                                    setDocumentoInicial('');
                                    setAltaAbierta(true);
                                }}
                                className={botonSecundario}
                            >
                                <Plus
                                    className="size-[18px]"
                                    aria-hidden="true"
                                />
                                Agregar cliente
                            </button>
                        ) : null}
                        <PiePaso>
                            <button
                                type="button"
                                disabled={!cliente}
                                onClick={() => setPaso(2)}
                                className={botonPrincipal}
                            >
                                Continuar
                            </button>
                        </PiePaso>
                    </>
                ) : (
                    <>
                        <CabeceraPaso
                            etiqueta="Recojo"
                            titulo="¿Dónde recoges?"
                            volver={() => setPaso(1)}
                            paso={2}
                            total={4}
                        />
                        <AlertError errors={Object.values(form.errors)} />
                        <p className="text-muted-foreground text-sm font-semibold">
                            {cliente?.razon_social}
                        </p>
                        {lugares.length > 0 ? (
                            <div
                                role="radiogroup"
                                aria-label="Lugar del recojo"
                                className={`${tarjeta} divide-border divide-y overflow-hidden`}
                            >
                                {lugares.map((lugar) => {
                                    const activo =
                                        form.data.referencia === lugar.valor;
                                    const Icono =
                                        lugar.clave === 'fiscal'
                                            ? Building2
                                            : MapPin;

                                    return (
                                        <button
                                            key={lugar.clave}
                                            type="button"
                                            role="radio"
                                            aria-checked={activo}
                                            onClick={() =>
                                                form.setData(
                                                    'referencia',
                                                    lugar.valor,
                                                )
                                            }
                                            className={`focus-visible:ring-ring flex min-h-18 w-full items-center gap-3.5 px-4 py-3 text-left focus-visible:ring-2 focus-visible:outline-none focus-visible:ring-inset ${activo ? 'bg-primary/5' : ''}`}
                                        >
                                            <Icono
                                                className={`size-5 shrink-0 ${activo ? 'text-primary-strong' : 'text-muted-foreground'}`}
                                                aria-hidden="true"
                                            />
                                            <span className="min-w-0 flex-1">
                                                <span className="block text-sm font-bold">
                                                    {lugar.nombre}
                                                </span>
                                                <span className="text-muted-foreground block text-xs">
                                                    {lugar.direccion ||
                                                        'Sin dirección'}
                                                </span>
                                            </span>
                                            <span
                                                className={`size-[22px] shrink-0 rounded-full ${activo ? 'border-primary border-[7px]' : 'border-2 border-neutral-400'}`}
                                                aria-hidden="true"
                                            />
                                        </button>
                                    );
                                })}
                            </div>
                        ) : null}
                        {cliente ? (
                            <NuevoLocalCampo
                                teamSlug={equipo}
                                clienteId={cliente.id}
                                onCreado={async (local) => {
                                    const ficha = await traerFicha(cliente.id);
                                    if (ficha) {
                                        setCliente(ficha);
                                    }
                                    form.setData(
                                        'referencia',
                                        `SEDE: ${local.nombre}`,
                                    );
                                }}
                            />
                        ) : null}
                        {sedes.length > 1 ? (
                            <div className={`${tarjeta} space-y-1.5 p-4`}>
                                <Label htmlFor="sede">Tienda que cobra</Label>
                                <NativeSelect
                                    id="sede"
                                    required
                                    value={form.data.sede_id}
                                    onChange={(e) =>
                                        form.setData('sede_id', e.target.value)
                                    }
                                    className="min-h-11 w-full text-base"
                                >
                                    <option value="">Elige la sede</option>
                                    {sedes.map((sede) => (
                                        <option key={sede.id} value={sede.id}>
                                            {sede.nombre}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </div>
                        ) : null}
                        <PiePaso>
                            <button
                                type="button"
                                onClick={submit}
                                disabled={
                                    !form.data.referencia ||
                                    !form.data.sede_id ||
                                    form.processing
                                }
                                className={botonPrincipal}
                            >
                                Continuar
                            </button>
                        </PiePaso>
                    </>
                )}
            </form>
        </TecnicoCampoLayout>
    );
}
