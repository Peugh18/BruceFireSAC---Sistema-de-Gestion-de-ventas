import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FileText, Lock, MessageCircle } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

import AlertError from '@/components/alert-error';
import BarraOrdenCampo from '@/components/barra-orden-campo';
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
import entregas from '@/routes/tecnico-campo/entregas';
import type { Team } from '@/types';

type Order = {
    id: number;
    codigo: string;
    estado: string;
    referencia?: string | null;
    events?: { id: number; payload: Record<string, unknown> | null }[];
    client: {
        razon_social: string;
        nombre_comercial?: string | null;
        telefono?: string | null;
    };
    equipments: {
        id: number;
        numero_serie: string;
        tipo_agente?: string | null;
        capacidad?: string | null;
    }[];
    certificates: { id: number; numero: string }[];
};

type Props = {
    asignacion: AsignacionOrden;
    order: Order;
    conversacion: ConversacionProps;
    evidencias: EvidenciaListada[];
    cobro: { cobrada: boolean; credito: boolean };
    lista: boolean;
};

/** Entrega: marcar cada extintor con foto de salida y firma de quien recibe. */
export default function EntregaShow({
    asignacion,
    order,
    conversacion,
    evidencias,
    cobro,
    lista,
}: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const equipo = currentTeam?.slug ?? '';
    const [paso, setPaso] = useState<'marcar' | 'firma'>('marcar');
    const form = useForm({
        receptor_nombre: '',
        receptor_dni: '',
        observaciones_entrega: '',
        conformidad_aceptada: false,
        cerrar_orden: true,
        firma: null as string | null,
        entregados: [] as number[],
    });
    const cliente = order.client.nombre_comercial || order.client.razon_social;
    const lugar = (order.referencia ?? '').replace(/^SEDE:\s*/i, '') || '—';
    const total = order.equipments.length;
    const marcados = form.data.entregados.length;
    const fotos = evidencias.filter((e) => e.tipo === 'foto').length;
    const entrega = order.events?.find(
        (e) => e.payload?.accion === 'entrega_final_realizada',
    )?.payload as { receptor_nombre?: string } | undefined;
    const volver = entregas.index.url(equipo);
    const marcar = (id: number, marcado: boolean) =>
        form.setData(
            'entregados',
            marcado
                ? [...form.data.entregados, id]
                : form.data.entregados.filter((otro) => otro !== id),
        );
    const confirmar = () =>
        form.post(
            entregas.confirm.url({
                current_team: equipo,
                service_order: order.id,
            }),
            {
                preserveScroll: true,
                onError: (errores) =>
                    toast.error(
                        Object.values(errores)[0] ??
                            'No se pudo registrar la entrega.',
                    ),
            },
        );

    if (entrega) {
        const certificados = order.certificates.map((c) => c.numero).join(', ');

        return (
            <TecnicoCampoLayout>
                <Head title={`Entrega ${order.codigo}`} />
                <BarraOrdenCampo
                    ordenId={order.id}
                    codigo={order.codigo}
                    conversacion={conversacion}
                />
                <TrabajoListo
                    codigo={order.codigo}
                    titulo="Entrega lista"
                    filas={[
                        ['Lugar', lugar],
                        ['Entregados', `${total} de ${total}`],
                        ['Recibió', entrega.receptor_nombre ?? '—'],
                    ]}
                />
                <div className="space-y-2.5">
                    <a
                        href={enlaceWhatsApp(
                            order.client.telefono,
                            `Bruce Fire entregó ${total} extintores a ${cliente}. Orden ${order.codigo}.${certificados ? ` Certificado ${certificados}.` : ''}`,
                        )}
                        target="_blank"
                        rel="noreferrer"
                        className={botonPrincipal}
                    >
                        <MessageCircle
                            className="size-[18px]"
                            aria-hidden="true"
                        />
                        Enviar certificado
                    </a>
                    <a
                        href={entregas.pdf.url({
                            current_team: equipo,
                            service_order: order.id,
                        })}
                        target="_blank"
                        rel="noreferrer"
                        className={botonSecundario}
                    >
                        <FileText className="size-[18px]" aria-hidden="true" />
                        Ver acta de entrega
                    </a>
                    <Link href={volver} className={botonSecundario}>
                        Siguiente entrega
                    </Link>
                </div>
            </TecnicoCampoLayout>
        );
    }

    if (!lista || !cobro.cobrada) {
        return (
            <TecnicoCampoLayout>
                <Head title={`Entrega ${order.codigo}`} />
                <BarraOrdenCampo
                    ordenId={order.id}
                    codigo={order.codigo}
                    conversacion={conversacion}
                />
                <CabeceraPaso
                    etiqueta={order.codigo}
                    titulo={cliente}
                    volver={volver}
                />
                <div className={`${tarjeta} mt-5 flex items-start gap-3 p-4`}>
                    <Lock
                        className="text-primary-strong mt-0.5 size-5 shrink-0"
                        aria-hidden="true"
                    />
                    <div className="space-y-1 text-sm">
                        <p className="font-bold">
                            {!lista
                                ? 'Planta todavía no la termina'
                                : 'Falta cobrar'}
                        </p>
                        <p className="text-muted-foreground">
                            {!lista
                                ? `Estado: ${etiquetaEstadoOrden(order.estado).toLowerCase()}.`
                                : 'El vendedor la cobra primero. Cuando esté cobrada o a crédito, aquí podrás entregarla.'}
                        </p>
                    </div>
                </div>
            </TecnicoCampoLayout>
        );
    }

    return (
        <TecnicoCampoLayout ocultarMenu>
            <Head title={`Entrega ${order.codigo}`} />
            <div className="space-y-4">
                {paso === 'marcar' ? (
                    <>
                        <CabeceraPaso
                            etiqueta="Entrega"
                            titulo="Marca cada uno"
                            volver={volver}
                            paso={1}
                            total={2}
                            extra={
                                <Contador>{`${marcados}/${total}`}</Contador>
                            }
                        />
                        {!asignacion.tecnico ? (
                            <TomarOrden asignacion={asignacion} />
                        ) : null}
                        <p className="text-muted-foreground px-1 text-sm font-semibold">
                            {cliente} · {lugar}
                            {cobro.credito ? ' · Crédito' : ' · Cobrada'}
                        </p>
                        <div
                            className={`${tarjeta} divide-border divide-y overflow-hidden`}
                        >
                            {order.equipments.map((eq) => {
                                const marcado = form.data.entregados.includes(
                                    eq.id,
                                );

                                return (
                                    <label
                                        key={eq.id}
                                        className={`flex min-h-14 cursor-pointer items-center gap-3 px-4 ${marcado ? '' : 'bg-primary/5'}`}
                                    >
                                        <input
                                            type="checkbox"
                                            checked={marcado}
                                            onChange={(e) =>
                                                marcar(eq.id, e.target.checked)
                                            }
                                            className="accent-primary size-[22px]"
                                        />
                                        <span className="flex-1 font-mono text-sm font-bold">
                                            {eq.numero_serie}
                                        </span>
                                        <span className="text-muted-foreground text-xs">
                                            {[eq.tipo_agente, eq.capacidad]
                                                .filter(Boolean)
                                                .join(' ')}
                                        </span>
                                    </label>
                                );
                            })}
                        </div>
                        <section className={`${tarjeta} p-4`}>
                            <SubirEvidencia
                                ordenId={order.id}
                                etapa="entrega"
                                titulo="Foto de salida"
                                evidencias={evidencias}
                            />
                        </section>
                        <PiePaso>
                            <button
                                type="button"
                                disabled={marcados < total || fotos === 0}
                                onClick={() => setPaso('firma')}
                                className={botonPrincipal}
                            >
                                {marcados < total
                                    ? `Faltan ${total - marcados} por marcar`
                                    : fotos === 0
                                      ? 'Falta la foto de salida'
                                      : 'Continuar'}
                            </button>
                        </PiePaso>
                    </>
                ) : (
                    <>
                        <CabeceraPaso
                            etiqueta="Entrega"
                            titulo="¿Quién recibe?"
                            volver={() => setPaso('marcar')}
                            paso={2}
                            total={2}
                        />
                        <AlertError errors={Object.values(form.errors)} />
                        <section className={`${tarjeta} space-y-4 p-4`}>
                            <div className="space-y-1.5">
                                <label
                                    htmlFor="receptor"
                                    className="text-sm font-semibold"
                                >
                                    Nombre
                                </label>
                                <input
                                    id="receptor"
                                    autoComplete="name"
                                    value={form.data.receptor_nombre}
                                    onChange={(e) =>
                                        form.setData(
                                            'receptor_nombre',
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
                                checked={form.data.conformidad_aceptada}
                                onChange={(e) =>
                                    form.setData(
                                        'conformidad_aceptada',
                                        e.target.checked,
                                    )
                                }
                                className="accent-primary size-[22px]"
                            />
                            Recibo {total}{' '}
                            {total === 1 ? 'extintor' : 'extintores'} conforme
                        </label>
                        <PiePaso>
                            <button
                                type="button"
                                disabled={
                                    form.processing ||
                                    !form.data.firma ||
                                    !form.data.conformidad_aceptada ||
                                    form.data.receptor_nombre.trim().length < 3
                                }
                                onClick={confirmar}
                                className={botonPrincipal}
                            >
                                Confirmar entrega
                            </button>
                        </PiePaso>
                    </>
                )}
            </div>
        </TecnicoCampoLayout>
    );
}
