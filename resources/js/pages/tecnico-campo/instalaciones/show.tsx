import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Check, ChevronRight, Minus, Plus, MessageCircle } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

import AlertError from '@/components/alert-error';
import BarraOrdenCampo from '@/components/barra-orden-campo';
import type { ConversacionProps } from '@/components/conversacion-orden';
import FirmaCanvas from '@/components/firma-canvas';
import MedicionesCertificadoCampo, {
    iniciarMediciones,
    type FormularioCertificadoCampo,
} from '@/components/mediciones-certificado-campo';
import OpcionesAgente from '@/components/opciones-agente';
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
import { NativeSelect } from '@/components/ui/native-select';
import TecnicoCampoLayout from '@/layouts/tecnico-campo-layout';
import instalaciones from '@/routes/tecnico-campo/instalaciones';
import type { Team } from '@/types';

type Equipment = {
    id: number;
    numero_serie: string;
    tipo_agente?: string | null;
    capacidad?: string | null;
    marca?: string | null;
    ubicacion_actual?: string | null;
};

type Order = {
    id: number;
    codigo: string;
    estado: string;
    referencia?: string | null;
    events?: {
        payload: { accion?: string; conformidad_nombre?: string } | null;
    }[];
    client: {
        razon_social: string;
        nombre_comercial?: string | null;
        telefono?: string | null;
    };
    equipments: Equipment[];
    certificates: { id: number; numero: string }[];
};

type Props = {
    formulariosCertificado: FormularioCertificadoCampo[];
    asignacion: AsignacionOrden;
    order: Order;
    unidadesVendidas: Equipment[];
    evidencias: EvidenciaListada[];
    conversacion: ConversacionProps;
};

type Punto = {
    equipment_id: number | null;
    numero_serie: string;
    tipo_agente: string;
    capacidad: string;
    marca: string;
    ubicacion_actual: string;
    altura_m: string;
    senalizacion: boolean;
    acceso_libre: boolean;
};

const ALTURA_MAXIMA = 1.53;
const FINALIZADA = [
    'listo_certificado',
    'listo_entrega',
    'entregado',
    'cerrado',
    'anulada',
];

const punto = (eq?: Equipment): Punto => ({
    equipment_id: eq?.id ?? null,
    numero_serie: eq?.numero_serie ?? '',
    tipo_agente: eq?.tipo_agente ?? '',
    capacidad: eq?.capacidad ?? '',
    marca: eq?.marca || 'Bruce Fire',
    ubicacion_actual: eq?.ubicacion_actual ?? '',
    altura_m: '1.50',
    senalizacion: true,
    acceso_libre: true,
});

function SiNo({
    etiqueta,
    valor,
    onChange,
}: {
    etiqueta: string;
    valor: boolean;
    onChange: (v: boolean) => void;
}) {
    const estilo = (activo: boolean, si: boolean) =>
        `min-h-11 rounded-xl text-sm font-semibold focus-visible:ring-ring focus-visible:ring-2 focus-visible:outline-none ${activo ? (si ? 'bg-emerald-700 text-white' : 'bg-foreground text-background') : 'border-border bg-card border'}`;

    return (
        <div className="flex min-h-16 items-center justify-between gap-3 py-2 pr-2 pl-4">
            <span className="text-sm font-semibold">{etiqueta}</span>
            <div
                role="group"
                aria-label={etiqueta}
                className="grid w-[150px] grid-cols-2 gap-1.5"
            >
                <button
                    type="button"
                    aria-pressed={valor}
                    onClick={() => onChange(true)}
                    className={estilo(valor, true)}
                >
                    Sí
                </button>
                <button
                    type="button"
                    aria-pressed={!valor}
                    onClick={() => onChange(false)}
                    className={estilo(!valor, false)}
                >
                    No
                </button>
            </div>
        </div>
    );
}

/** Instalación: cada extintor (altura, señalización, acceso y foto), revisión y firma. */
export default function InstalacionShow({
    formulariosCertificado,
    asignacion,
    order,
    unidadesVendidas,
    evidencias,
    conversacion,
}: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const equipo = currentTeam?.slug ?? '';
    const volver = instalaciones.index.url(equipo);
    const cliente = order.client.nombre_comercial || order.client.razon_social;
    const lugar = (order.referencia ?? '').replace(/^SEDE:\s*/i, '');
    const iniciales = (
        unidadesVendidas.length > 0 ? unidadesVendidas : order.equipments
    ).map(punto);
    const conMediciones = formulariosCertificado.length > 0;
    const form = useForm({
        area: lugar || 'Local del cliente',
        ubicacion_instalada: '',
        pruebas: '',
        observaciones: '',
        conformidad_nombre: '',
        conformidad_aceptada: false,
        emitir_certificado: !conMediciones,
        certificados_servicio: iniciarMediciones(formulariosCertificado),
        tipo_certificado_codigo: 'operatividad_garantia',
        firma: null as string | null,
        equipos: iniciales.length > 0 || conMediciones ? iniciales : [punto()],
    });
    const puntos = form.data.equipos;
    const [vista, setVista] = useState<number | 'revision' | 'firma'>(
        puntos.length > 0 ? 0 : 'revision',
    );
    const registrada = order.events?.find(
        (e) => e.payload?.accion === 'instalacion_campo_realizada',
    )?.payload;

    const fotoDe = (p: Punto) =>
        evidencias.some(
            (e) =>
                e.etapa === 'despues' &&
                e.tipo === 'foto' &&
                (p.equipment_id === null || e.equipment_id === p.equipment_id),
        );
    const altura = (p: Punto) => parseFloat(p.altura_m);
    const falta = (p: Punto): string | null => {
        if (!p.tipo_agente || !p.capacidad) return 'Falta tipo o capacidad';
        if (!(altura(p) > 0) || altura(p) > ALTURA_MAXIMA)
            return 'Revisa la altura';
        if (p.ubicacion_actual.trim().length < 3) return 'Falta la ubicación';
        if (!fotoDe(p)) return 'Falta la foto';
        return null;
    };
    const completos = puntos.filter((p) => falta(p) === null).length;
    // Planta y el cliente ven cómo estaba el lugar antes de montar.
    const fotoAntes = evidencias.some(
        (e) => e.etapa === 'antes' && e.tipo === 'foto',
    );
    const cambiar = (i: number, cambios: Partial<Punto>) =>
        form.setData(
            'equipos',
            puntos.map((p, j) => (j === i ? { ...p, ...cambios } : p)),
        );
    // Lo que falta para confirmar, visible junto al botón (no se bloquea en silencio).
    const faltaCierre =
        form.data.conformidad_nombre.trim().length < 3
            ? 'Escribe quién da la conformidad.'
            : !form.data.firma
              ? 'Falta la firma del cliente.'
              : !form.data.conformidad_aceptada
                ? 'Marca la conformidad del cliente antes de finalizar la instalación.'
                : null;

    const confirmar = () => {
        form.transform((data) => ({
            ...data,
            ubicacion_instalada:
                [
                    ...new Set(
                        data.equipos
                            .map((p) => p.ubicacion_actual.trim())
                            .filter(Boolean),
                    ),
                ]
                    .join(', ')
                    .slice(0, 200) || data.area,
        }));
        form.post(
            instalaciones.store.url({
                current_team: equipo,
                service_order: order.id,
            }),
            {
                preserveScroll: true,
                onError: (errores) =>
                    toast.error(
                        Object.values(errores)[0] ??
                            'No se pudo registrar la instalación.',
                    ),
            },
        );
    };

    if (registrada || FINALIZADA.includes(order.estado)) {
        const certificados = order.certificates.map((c) => c.numero).join(', ');

        return (
            <TecnicoCampoLayout>
                <Head title={`Instalación ${order.codigo}`} />
                <BarraOrdenCampo
                    ordenId={order.id}
                    codigo={order.codigo}
                    conversacion={conversacion}
                />
                <TrabajoListo
                    codigo={order.codigo}
                    titulo="Instalación lista"
                    filas={[
                        ['Cliente', cliente],
                        ['Instalados', String(order.equipments.length)],
                        ['Conformidad', registrada?.conformidad_nombre ?? '—'],
                        ...(certificados
                            ? ([['Certificado', certificados]] as [
                                  string,
                                  string,
                              ][])
                            : []),
                    ]}
                />
                <div className="space-y-2.5">
                    <a
                        href={enlaceWhatsApp(
                            order.client.telefono,
                            `Bruce Fire instaló ${order.equipments.length} extintores en ${cliente}. Orden ${order.codigo}.${certificados ? ` Certificado ${certificados}.` : ''}`,
                        )}
                        target="_blank"
                        rel="noreferrer"
                        className={botonPrincipal}
                    >
                        <MessageCircle
                            className="size-[18px]"
                            aria-hidden="true"
                        />
                        Enviar acta
                    </a>
                    <Link href={volver} className={botonSecundario}>
                        Volver a instalaciones
                    </Link>
                </div>
            </TecnicoCampoLayout>
        );
    }

    return (
        <TecnicoCampoLayout ocultarMenu>
            <Head title={`Instalación ${order.codigo}`} />
            <div className="space-y-4">
                {typeof vista === 'number' && puntos[vista]
                    ? (() => {
                          const p = puntos[vista];
                          const i = vista;
                          const valorAltura = altura(p) || 0;
                          const mover = (delta: number) =>
                              cambiar(i, {
                                  altura_m: Math.min(
                                      ALTURA_MAXIMA,
                                      Math.max(0.1, valorAltura + delta),
                                  ).toFixed(2),
                              });

                          return (
                              <>
                                  <CabeceraPaso
                                      etiqueta="Instalación"
                                      titulo={
                                          p.numero_serie || `Extintor ${i + 1}`
                                      }
                                      volver={
                                          i === 0
                                              ? volver
                                              : () => setVista(i - 1)
                                      }
                                      paso={i + 1}
                                      total={puntos.length}
                                  />
                                  {!asignacion.tecnico ? (
                                      <TomarOrden asignacion={asignacion} />
                                  ) : null}
                                  {p.equipment_id ? (
                                      <p className="text-muted-foreground px-1 text-sm font-semibold">
                                          {[p.tipo_agente, p.capacidad]
                                              .filter(Boolean)
                                              .join(' ')}
                                      </p>
                                  ) : (
                                      <div
                                          className={`${tarjeta} grid grid-cols-2 gap-2 p-4`}
                                      >
                                          <NativeSelect
                                              aria-label="Tipo"
                                              value={p.tipo_agente}
                                              onChange={(e) =>
                                                  cambiar(i, {
                                                      tipo_agente:
                                                          e.target.value,
                                                  })
                                              }
                                              className="min-h-11 w-full text-base"
                                          >
                                              <OpcionesAgente vacia="Tipo" />
                                          </NativeSelect>
                                          <input
                                              aria-label="Capacidad"
                                              placeholder="6 kg"
                                              value={p.capacidad}
                                              onChange={(e) =>
                                                  cambiar(i, {
                                                      capacidad: e.target.value,
                                                  })
                                              }
                                              className="border-border bg-card rounded-xl border px-3"
                                          />
                                      </div>
                                  )}
                                  <div className={`${tarjeta} space-y-1.5 p-4`}>
                                      <label
                                          htmlFor="ubicacion"
                                          className="text-sm font-semibold"
                                      >
                                          Dónde quedó
                                      </label>
                                      <input
                                          id="ubicacion"
                                          placeholder="Pasillo 2.º piso"
                                          value={p.ubicacion_actual}
                                          onChange={(e) =>
                                              cambiar(i, {
                                                  ubicacion_actual:
                                                      e.target.value,
                                              })
                                          }
                                          className="border-border bg-card w-full rounded-xl border px-3.5"
                                      />
                                  </div>
                                  <div className={`${tarjeta} space-y-2.5 p-4`}>
                                      <div className="flex items-baseline justify-between">
                                          <span className="text-sm font-semibold">
                                              Altura de la manija
                                          </span>
                                          <span className="text-muted-foreground text-xs">
                                              máx. {ALTURA_MAXIMA} m
                                          </span>
                                      </div>
                                      <div className="flex items-center gap-2">
                                          <button
                                              type="button"
                                              onClick={() => mover(-0.05)}
                                              aria-label="Bajar altura"
                                              className="border-border bg-card focus-visible:ring-ring flex size-12 items-center justify-center rounded-xl border focus-visible:ring-2 focus-visible:outline-none"
                                          >
                                              <Minus
                                                  className="size-4"
                                                  aria-hidden="true"
                                              />
                                          </button>
                                          <label className="border-border flex h-12 flex-1 items-center justify-center gap-1 rounded-xl border">
                                              <span className="sr-only">
                                                  Altura en metros
                                              </span>
                                              <input
                                                  inputMode="decimal"
                                                  value={p.altura_m}
                                                  onChange={(e) =>
                                                      cambiar(i, {
                                                          altura_m:
                                                              e.target.value
                                                                  .replace(
                                                                      ',',
                                                                      '.',
                                                                  )
                                                                  .replace(
                                                                      /[^\d.]/g,
                                                                      '',
                                                                  ),
                                                      })
                                                  }
                                                  className="w-16 border-0 bg-transparent text-center text-xl font-bold tabular-nums focus:outline-none"
                                              />
                                              <span className="text-muted-foreground text-sm font-semibold">
                                                  m
                                              </span>
                                          </label>
                                          <button
                                              type="button"
                                              onClick={() => mover(0.05)}
                                              aria-label="Subir altura"
                                              className="bg-primary text-primary-foreground focus-visible:ring-ring flex size-12 items-center justify-center rounded-xl focus-visible:ring-2 focus-visible:outline-none"
                                          >
                                              <Plus
                                                  className="size-4"
                                                  aria-hidden="true"
                                              />
                                          </button>
                                      </div>
                                  </div>
                                  <div
                                      className={`${tarjeta} divide-border divide-y overflow-hidden`}
                                  >
                                      <SiNo
                                          etiqueta="Señalización"
                                          valor={p.senalizacion}
                                          onChange={(v) =>
                                              cambiar(i, { senalizacion: v })
                                          }
                                      />
                                      <SiNo
                                          etiqueta="Acceso libre"
                                          valor={p.acceso_libre}
                                          onChange={(v) =>
                                              cambiar(i, { acceso_libre: v })
                                          }
                                      />
                                  </div>
                                  <section className={`${tarjeta} p-4`}>
                                      <SubirEvidencia
                                          ordenId={order.id}
                                          etapa="despues"
                                          titulo="Foto instalado"
                                          evidencias={evidencias}
                                          equipoId={p.equipment_id ?? undefined}
                                      />
                                  </section>
                                  <PiePaso>
                                      <button
                                          type="button"
                                          disabled={falta(p) !== null}
                                          onClick={() =>
                                              setVista(
                                                  i + 1 < puntos.length
                                                      ? i + 1
                                                      : 'revision',
                                              )
                                          }
                                          className={botonPrincipal}
                                      >
                                          {falta(p) ??
                                              (i + 1 < puntos.length
                                                  ? 'Siguiente extintor'
                                                  : 'Revisar')}
                                      </button>
                                  </PiePaso>
                              </>
                          );
                      })()
                    : null}

                {vista === 'revision' ? (
                    <>
                        <CabeceraPaso
                            etiqueta={cliente}
                            titulo="Revisión"
                            volver={() =>
                                setVista(Math.max(0, puntos.length - 1))
                            }
                            extra={
                                puntos.length > 0 ? (
                                    <Contador>{`${completos}/${puntos.length}`}</Contador>
                                ) : undefined
                            }
                        />
                        {puntos.length > 0 ? (
                            <div
                                className={`${tarjeta} divide-border divide-y overflow-hidden`}
                            >
                                {puntos.map((p, i) => {
                                    const motivo = falta(p);

                                    return (
                                        <button
                                            key={`${p.equipment_id ?? 'nuevo'}-${i}`}
                                            type="button"
                                            onClick={() => setVista(i)}
                                            className={`focus-visible:ring-ring flex min-h-16 w-full items-center gap-3 px-4 text-left focus-visible:ring-2 focus-visible:outline-none focus-visible:ring-inset ${motivo ? 'bg-primary/5' : ''}`}
                                        >
                                            {motivo ? (
                                                <span
                                                    className="border-primary size-6 shrink-0 rounded-full border-2"
                                                    aria-hidden="true"
                                                />
                                            ) : (
                                                <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                                    <Check
                                                        className="size-3.5"
                                                        strokeWidth={3}
                                                        aria-hidden="true"
                                                    />
                                                </span>
                                            )}
                                            <span className="min-w-0 flex-1">
                                                <span className="block font-mono text-sm font-bold">
                                                    {p.numero_serie ||
                                                        `Extintor ${i + 1}`}
                                                </span>
                                                <span
                                                    className={`block text-xs ${motivo ? 'text-primary-strong font-semibold' : 'text-muted-foreground'}`}
                                                >
                                                    {motivo ??
                                                        `${p.ubicacion_actual} · ${p.altura_m} m`}
                                                </span>
                                            </span>
                                            <ChevronRight
                                                className="size-4 shrink-0"
                                                aria-hidden="true"
                                            />
                                        </button>
                                    );
                                })}
                            </div>
                        ) : null}
                        <section className={`${tarjeta} p-4`}>
                            <SubirEvidencia
                                ordenId={order.id}
                                etapa="antes"
                                titulo="Foto antes"
                                evidencias={evidencias}
                            />
                        </section>
                        {conMediciones ? (
                            <MedicionesCertificadoCampo
                                tipos={formulariosCertificado}
                                value={form.data.certificados_servicio}
                                onChange={(datos) =>
                                    form.setData('certificados_servicio', datos)
                                }
                                errors={form.errors as Record<string, string>}
                            />
                        ) : null}
                        <PiePaso>
                            <button
                                type="button"
                                disabled={
                                    completos < puntos.length || !fotoAntes
                                }
                                onClick={() => setVista('firma')}
                                className={botonPrincipal}
                            >
                                {fotoAntes
                                    ? 'Continuar a la firma'
                                    : 'Falta la foto antes'}
                            </button>
                        </PiePaso>
                    </>
                ) : null}

                {vista === 'firma' ? (
                    <>
                        <CabeceraPaso
                            etiqueta={`Instalación · ${puntos.length} ${puntos.length === 1 ? 'extintor' : 'extintores'}`}
                            titulo="¿Quién da conformidad?"
                            volver={() => setVista('revision')}
                        />
                        <AlertError errors={Object.values(form.errors)} />
                        <section className={`${tarjeta} space-y-4 p-4`}>
                            <div className="space-y-1.5">
                                <label
                                    htmlFor="conformidad"
                                    className="text-sm font-semibold"
                                >
                                    Nombre
                                </label>
                                <input
                                    id="conformidad"
                                    autoComplete="name"
                                    value={form.data.conformidad_nombre}
                                    onChange={(e) =>
                                        form.setData(
                                            'conformidad_nombre',
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
                            Conforme con la instalación
                        </label>
                        <PiePaso>
                            {faltaCierre ? (
                                <p
                                    role="alert"
                                    className="text-destructive-strong text-center text-sm font-semibold"
                                >
                                    {faltaCierre}
                                </p>
                            ) : null}
                            <button
                                type="button"
                                disabled={form.processing || faltaCierre !== null}
                                onClick={confirmar}
                                className={botonPrincipal}
                            >
                                Confirmar instalación
                            </button>
                        </PiePaso>
                    </>
                ) : null}
            </div>
        </TecnicoCampoLayout>
    );
}
