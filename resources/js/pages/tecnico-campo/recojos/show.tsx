import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FileText, MessageCircle } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

import AlertError from '@/components/alert-error';
import BarraOrdenCampo from '@/components/barra-orden-campo';
import ConteoRecojo, { type GrupoRecojo } from '@/components/conteo-recojo';
import type { ConversacionProps } from '@/components/conversacion-orden';
import FirmaCanvas from '@/components/firma-canvas';
import {
    botonPrincipal,
    botonSecundario,
    CabeceraPaso,
    Contador,
    enlaceWhatsApp,
    PiePaso,
    tarjeta,
    TrabajoListo,
} from '@/components/paso-campo';
import SubirEvidencia, {
    type EvidenciaListada,
} from '@/components/subir-evidencia';
import TomarOrden, { type AsignacionOrden } from '@/components/tomar-orden';
import TecnicoCampoLayout from '@/layouts/tecnico-campo-layout';
import { etiquetaEstadoOrden } from '@/lib/estados-orden';
import recojos from '@/routes/tecnico-campo/recojos';
import type { Team } from '@/types';

interface CustodyEventItem {
    id: number;
    eslabon: string;
    payload: Record<string, unknown>;
}

type Tarifa = {
    tipo: string;
    capacidad: string;
    precio: number;
    service_id: number;
};

interface Props {
    asignacion: AsignacionOrden;
    order: {
        id: number;
        codigo: string;
        cliente: {
            nombre: string;
            documento: string;
            telefono: string | null;
            comprobante: string;
        };
        lugar: string | null;
        estado: string;
    };
    custodyEvents: CustodyEventItem[];
    conversacion: ConversacionProps;
    evidencias: EvidenciaListada[];
    cantidadEsperada: number;
    conPrecio: boolean;
    tarifas: Tarifa[];
    /** Lo último que se le dejó a este cliente por tipo y capacidad. */
    ultimosPrecios: { tipo: string; capacidad: string; precio: number }[];
}

const soles = (valor: number) =>
    valor.toLocaleString('es-PE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
const clave = (tipo: string, capacidad: string) =>
    `${tipo}|${capacidad
        .toLowerCase()
        .replace(/\s+/g, '')
        .replace(/\.0+(?=[a-z])/, '')}`;

/** Recojo, pasos 3 a 5: conteo y foto de entrada, precio, firma del cliente. */
export default function RecojoShow({
    asignacion,
    order,
    custodyEvents,
    conversacion,
    evidencias,
    cantidadEsperada,
    conPrecio,
    tarifas,
    ultimosPrecios,
}: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const equipo = currentTeam?.slug ?? '';
    const pasos = conPrecio
        ? (['conteo', 'precio', 'firma'] as const)
        : (['conteo', 'firma'] as const);
    const [paso, setPaso] = useState<(typeof pasos)[number]>('conteo');
    const [conNota, setConNota] = useState(false);
    const form = useForm({
        contacto_nombre: '',
        contacto_telefono: order.cliente.telefono || '',
        observaciones: '',
        conformidad_cliente: false,
        firma: null as string | null,
        conteo: [] as GrupoRecojo[],
    });

    const recojo = custodyEvents.find((e) => e.eslabon === 'recojo_campo');
    const fotos = evidencias.filter((e) => e.tipo === 'foto').length;
    const total = form.data.conteo.reduce((s, g) => s + g.cantidad, 0);
    const lista = (g: GrupoRecojo) =>
        tarifas.find(
            (t) =>
                clave(t.tipo, t.capacidad) ===
                clave(g.tipo_agente, g.capacidad),
        )?.precio ?? null;
    const importe = form.data.conteo.reduce(
        (s, g) => s + g.cantidad * (parseFloat(g.precio) || 0),
        0,
    );
    // Sin precio de lista (30 lb, 50 kg…): lo último que se le dejó a este cliente.
    const ultimo = (g: GrupoRecojo) =>
        ultimosPrecios.find(
            (u) =>
                clave(u.tipo, u.capacidad) ===
                clave(g.tipo_agente, g.capacidad),
        )?.precio ?? null;
    // El total de lista solo se compara si todo tiene precio de lista.
    const importeLista = form.data.conteo.every((g) => lista(g) !== null)
        ? form.data.conteo.reduce((s, g) => s + g.cantidad * (lista(g) ?? 0), 0)
        : null;
    const indice = pasos.indexOf(paso as never);
    const numeroPaso = indice + 3;
    const totalPasos = pasos.length + 2;
    const cambiarPrecio = (i: number, precio: string) =>
        form.setData(
            'conteo',
            form.data.conteo.map((g, j) => (j === i ? { ...g, precio } : g)),
        );
    const irAPrecio = () => {
        // El precio de lista sale del catálogo; campo lo cambia si acordó otro.
        form.setData(
            'conteo',
            form.data.conteo.map((g) => {
                const sugerido = lista(g) ?? ultimo(g);

                return g.precio === '' && sugerido !== null
                    ? { ...g, precio: String(sugerido) }
                    : g;
            }),
        );
        setPaso('precio');
    };
    const confirmar = () =>
        form.post(
            recojos.store.url({
                current_team: equipo,
                service_order: order.id,
            }),
            {
                preserveScroll: true,
                onError: (errores) =>
                    toast.error(
                        Object.values(errores)[0] ??
                            'No se pudo registrar el recojo.',
                    ),
            },
        );

    if (recojo) {
        const payload = recojo.payload as {
            cantidad_equipos?: number;
            contacto_cliente?: string;
        };

        return (
            <TecnicoCampoLayout>
                <Head title={`Recojo ${order.codigo}`} />
                <BarraOrdenCampo
                    ordenId={order.id}
                    codigo={order.codigo}
                    conversacion={conversacion}
                />
                <TrabajoListo
                    codigo={order.codigo}
                    titulo="Recojo listo"
                    filas={[
                        ['Cliente', order.cliente.nombre],
                        [
                            'Lugar',
                            (order.lugar ?? '—').replace(/^SEDE:\s*/i, ''),
                        ],
                        ['Extintores', String(payload.cantidad_equipos ?? '—')],
                        ['Entregó', payload.contacto_cliente ?? '—'],
                    ]}
                />
                <div className="space-y-2.5">
                    <a
                        href={enlaceWhatsApp(
                            order.cliente.telefono,
                            `Bruce Fire recibió ${payload.cantidad_equipos ?? ''} extintores de ${order.cliente.nombre}. Orden ${order.codigo}.`,
                        )}
                        target="_blank"
                        rel="noreferrer"
                        className={botonPrincipal}
                    >
                        <MessageCircle
                            className="size-[18px]"
                            aria-hidden="true"
                        />
                        Enviar constancia
                    </a>
                    <a
                        href={recojos.constanciaRecepcion.url({
                            current_team: equipo,
                            service_order: order.id,
                        })}
                        target="_blank"
                        rel="noreferrer"
                        className={botonSecundario}
                    >
                        <FileText className="size-[18px]" aria-hidden="true" />
                        Ver constancia
                    </a>
                    <Link
                        href={recojos.index.url(equipo)}
                        className={botonSecundario}
                    >
                        Volver a recojos
                    </Link>
                </div>
            </TecnicoCampoLayout>
        );
    }

    if (order.estado !== 'pendiente_recepcion') {
        return (
            <TecnicoCampoLayout>
                <Head title={`Recojo ${order.codigo}`} />
                <CabeceraPaso
                    etiqueta={order.codigo}
                    titulo={order.cliente.nombre}
                    volver={recojos.index.url(equipo)}
                />
                <p
                    className={`${tarjeta} text-muted-foreground mt-5 p-4 text-sm`}
                >
                    Esta orden ya no admite recojos:{' '}
                    {etiquetaEstadoOrden(order.estado).toLowerCase()}.
                </p>
            </TecnicoCampoLayout>
        );
    }

    return (
        <TecnicoCampoLayout ocultarMenu>
            <Head title={`Recojo ${order.codigo}`} />
            <div className="space-y-4">
                {paso === 'conteo' ? (
                    <>
                        <CabeceraPaso
                            etiqueta="Recojo"
                            titulo="¿Cuántos llevas?"
                            volver={recojos.index.url(equipo)}
                            paso={numeroPaso}
                            total={totalPasos}
                            extra={<Contador>{total}</Contador>}
                        />
                        {!asignacion.tecnico ? (
                            <TomarOrden asignacion={asignacion} />
                        ) : null}
                        <ConteoRecojo
                            grupos={form.data.conteo}
                            onChange={(conteo) =>
                                form.setData('conteo', conteo)
                            }
                        />
                        {cantidadEsperada > 1 ? (
                            <p className="text-muted-foreground px-1 text-xs">
                                Se esperaban {cantidadEsperada}.
                            </p>
                        ) : null}
                        <section className={`${tarjeta} p-4`}>
                            <SubirEvidencia
                                ordenId={order.id}
                                etapa="recojo"
                                titulo="Foto de entrada"
                                evidencias={evidencias}
                            />
                        </section>
                        {conNota ? (
                            <textarea
                                aria-label="Nota del recojo"
                                value={form.data.observaciones}
                                onChange={(e) =>
                                    form.setData(
                                        'observaciones',
                                        e.target.value,
                                    )
                                }
                                placeholder="Algo que planta deba saber"
                                rows={2}
                                className={`${tarjeta} w-full p-3 text-base`}
                            />
                        ) : (
                            <button
                                type="button"
                                onClick={() => setConNota(true)}
                                className="text-muted-foreground min-h-11 px-1 text-sm font-semibold"
                            >
                                + Nota
                            </button>
                        )}
                        <PiePaso>
                            <button
                                type="button"
                                disabled={total === 0 || fotos === 0}
                                onClick={() =>
                                    conPrecio ? irAPrecio() : setPaso('firma')
                                }
                                className={botonPrincipal}
                            >
                                {total === 0
                                    ? 'Agrega lo que llevas'
                                    : fotos === 0
                                      ? 'Falta la foto de entrada'
                                      : 'Continuar'}
                            </button>
                        </PiePaso>
                    </>
                ) : null}

                {paso === 'precio' ? (
                    <>
                        <CabeceraPaso
                            etiqueta="Recojo"
                            titulo="¿Qué precio dejas?"
                            volver={() => setPaso('conteo')}
                            paso={numeroPaso}
                            total={totalPasos}
                        />
                        <div
                            className={`${tarjeta} divide-border divide-y overflow-hidden`}
                        >
                            {form.data.conteo.map((g, i) => {
                                const precioLista = lista(g);
                                const precioAnterior = ultimo(g);
                                const id = `precio-${i}`;

                                return (
                                    <div
                                        key={`${g.tipo_agente}${g.capacidad}`}
                                        className="space-y-2 px-4 py-3.5"
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="text-sm font-bold">
                                                Recarga {g.tipo_agente}{' '}
                                                {g.capacidad} × {g.cantidad}
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-2.5">
                                            <label
                                                htmlFor={id}
                                                className="text-muted-foreground text-xs"
                                            >
                                                c/u S/
                                            </label>
                                            <input
                                                id={id}
                                                inputMode="decimal"
                                                value={g.precio}
                                                onChange={(e) =>
                                                    cambiarPrecio(
                                                        i,
                                                        e.target.value.replace(
                                                            ',',
                                                            '.',
                                                        ),
                                                    )
                                                }
                                                className="border-border bg-card w-24 rounded-xl border px-3 text-base font-bold"
                                            />
                                            {precioLista === null ? (
                                                <span className="text-muted-foreground text-xs">
                                                    {precioAnterior !== null
                                                        ? `último S/ ${soles(precioAnterior)}`
                                                        : 'sin precio de lista'}
                                                </span>
                                            ) : parseFloat(g.precio) !==
                                              precioLista ? (
                                                <span className="text-muted-foreground text-xs">
                                                    lista{' '}
                                                    <s>{soles(precioLista)}</s>
                                                </span>
                                            ) : null}
                                            <span className="ml-auto text-sm font-bold tabular-nums">
                                                {soles(
                                                    g.cantidad *
                                                        (parseFloat(g.precio) ||
                                                            0),
                                                )}
                                            </span>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                        <div className={`${tarjeta} space-y-1 p-4`}>
                            <div className="flex items-baseline justify-between">
                                <span className="text-sm font-semibold">
                                    Total acordado
                                </span>
                                <span className="font-['Oswald',sans-serif] text-[26px] font-semibold">
                                    S/ {soles(importe)}
                                </span>
                            </div>
                            <div className="text-muted-foreground flex justify-between text-xs">
                                <span>
                                    {order.cliente.comprobante} ·{' '}
                                    {order.cliente.documento}
                                </span>
                                {importeLista !== null &&
                                importeLista !== importe ? (
                                    <span>lista S/ {soles(importeLista)}</span>
                                ) : null}
                            </div>
                        </div>
                        <PiePaso>
                            <button
                                type="button"
                                disabled={form.data.conteo.some(
                                    (g) => !(parseFloat(g.precio) > 0),
                                )}
                                onClick={() => setPaso('firma')}
                                className={botonPrincipal}
                            >
                                Continuar
                            </button>
                        </PiePaso>
                    </>
                ) : null}

                {paso === 'firma' ? (
                    <>
                        <CabeceraPaso
                            etiqueta="Recojo"
                            titulo="¿Quién te los entrega?"
                            volver={() =>
                                setPaso(conPrecio ? 'precio' : 'conteo')
                            }
                            paso={numeroPaso}
                            total={totalPasos}
                        />
                        <AlertError errors={Object.values(form.errors)} />
                        <section className={`${tarjeta} space-y-4 p-4`}>
                            <div className="space-y-1.5">
                                <label
                                    htmlFor="contacto"
                                    className="text-sm font-semibold"
                                >
                                    Nombre
                                </label>
                                <input
                                    id="contacto"
                                    autoComplete="name"
                                    value={form.data.contacto_nombre}
                                    onChange={(e) =>
                                        form.setData(
                                            'contacto_nombre',
                                            e.target.value,
                                        )
                                    }
                                    className="border-border bg-card w-full rounded-xl border px-3.5"
                                />
                            </div>
                            <FirmaCanvas
                                etiqueta="Firma"
                                onChange={(f) => form.setData('firma', f)}
                            />
                        </section>
                        <label
                            className={`${tarjeta} flex min-h-14 items-center gap-3 px-4 text-sm font-semibold`}
                        >
                            <input
                                type="checkbox"
                                checked={form.data.conformidad_cliente}
                                onChange={(e) =>
                                    form.setData(
                                        'conformidad_cliente',
                                        e.target.checked,
                                    )
                                }
                                className="accent-primary size-[22px]"
                            />
                            Entrego {total}{' '}
                            {total === 1 ? 'extintor' : 'extintores'}
                            {conPrecio
                                ? ` · acepto S/ ${soles(importe)}`
                                : ' a Bruce Fire'}
                        </label>
                        <PiePaso>
                            <button
                                type="button"
                                disabled={
                                    form.processing ||
                                    !form.data.firma ||
                                    !form.data.conformidad_cliente ||
                                    form.data.contacto_nombre.trim() === ''
                                }
                                onClick={confirmar}
                                className={botonPrincipal}
                            >
                                Confirmar recojo
                            </button>
                        </PiePaso>
                    </>
                ) : null}
            </div>
        </TecnicoCampoLayout>
    );
}
