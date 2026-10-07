import { Link, router, usePage } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowLeft,
    ArrowUp,
    Award,
    ListOrdered,
    Plus,
    Trash2,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

import { CargaLarga, Cargando } from '@/components/cargando';
import type { ClientFicha } from '@/components/client-picker';
import ReferenciaField from '@/components/referencia-field';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import VendedorLayout from '@/layouts/vendedor-layout';
import clientes from '@/routes/vendedor/clientes';
import ventas from '@/routes/vendedor/ventas';
import type { Team } from '@/types';

type Unidad = {
    equipment_id: number;
    codigo_interno: string;
    serie_fabricante: string | null;
    producto: string | null;
    capacidad: string | null;
    marca: string | null;
    numero_cliente: string | null;
    agente: string | null;
    agente_conocido: boolean;
    fecha_ultima_ph: string | null;
    presion_ph: string | null;
    tiempo_ph: string | null;
    presion_ph_sugerida: string | null;
};

type Emitido = {
    id: number;
    numero: string;
    tipo: string;
    tipo_codigo: string;
    estado: string;
    referencia: string | null;
    unidades: number;
};

type GrupoActual = {
    referencia: string | null;
    direccion: string | null;
    tipos: string[];
    unidades: { equipment_id: number; numero_cliente: string | null }[];
    capacitacion: {
        curso?: string | null;
        horas?: number | null;
        instructor?: string | null;
        modo?: ModoCapacitacion;
        fotos?: string[];
        participantes?: Participante[];
    } | null;
};

type ModoCapacitacion = 'normal' | 'con_fotos' | 'por_trabajador';

type Participante = {
    id?: number;
    nombres: string;
    dni: string;
    cargo: string;
};

type Props = {
    sale: {
        id: number;
        numero_interno: string;
        estado: string;
        destino: 'local_cliente' | 'vehiculo';
        referencia: string | null;
        client: {
            id: number;
            razon_social: string;
            direccion_fiscal: string | null;
        };
    };
    unidades: Unidad[];
    tiposSugeridos: string[];
    instructor: string | null;
    emitidos: Emitido[];
    gruposActuales: GrupoActual[];
};

type Grupo = {
    id: number;
    destino: 'local_cliente' | 'vehiculo';
    referencia: string;
    direccion: string;
    tipos: string[];
    curso: string;
    horas: number;
    instructor: string;
    modo: ModoCapacitacion;
    fotos: File[];
    fotosExistentes: number;
    participantes: Participante[];
};

const TIPOS = [
    { codigo: 'operatividad_garantia', nombre: 'Operatividad y Garantía' },
    { codigo: 'prueba_hidrostatica', nombre: 'Prueba Hidrostática' },
    { codigo: 'capacitacion', nombre: 'Capacitación' },
];

function tiposPorDestino(destino: string) {
    return destino === 'vehiculo'
        ? ['operatividad_garantia', 'prueba_hidrostatica']
        : ['operatividad_garantia', 'capacitacion'];
}

let secuencia = 1;

// La P.H. se certifica solo con lo que registró el técnico (C2): nada se
// llena solo, salvo la presión que corresponde al agente como sugerencia.
function datosDePrueba(u: Unidad) {
    return {
        ph_fecha: u.fecha_ultima_ph ?? '',
        ph_presion: u.presion_ph ?? '',
        ph_tiempo: u.tiempo_ph ?? '',
        ph_resultado: u.fecha_ultima_ph ? 'aprobado' : '',
    };
}

export default function ArmarCertificados({
    sale,
    unidades,
    tiposSugeridos,
    instructor,
    emitidos,
    gruposActuales,
}: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug = currentTeam?.slug ?? '';

    const nuevoGrupo = (destino = sale.destino, referencia = ''): Grupo => ({
        id: secuencia++,
        destino,
        referencia,
        direccion: '',
        tipos:
            destino === sale.destino
                ? tiposSugeridos
                : tiposPorDestino(destino),
        curso: 'USO Y MANEJO DE EXTINTORES',
        horas: 4,
        instructor: instructor ?? '',
        modo: 'normal',
        fotos: [],
        fotosExistentes: 0,
        participantes: [],
    });

    // Si la venta ya tiene certificados (salen solos al confirmarla), se
    // parte de cómo están armados para solo ajustar orden, numeración o grupos.
    const [inicial] = useState(() => {
        if (gruposActuales.length === 0) {
            const grupo = nuevoGrupo(sale.destino, sale.referencia ?? '');

            return {
                grupos: [grupo],
                filas: unidades.map((u) => ({
                    ...u,
                    ...datosDePrueba(u),
                    grupo: grupo.id,
                    numero_cliente: u.numero_cliente ?? '',
                })),
            };
        }

        const armados = gruposActuales.map((actual) => {
            const destino = actual.tipos.includes('prueba_hidrostatica')
                ? 'vehiculo'
                : 'local_cliente';

            return {
                ...nuevoGrupo(destino, actual.referencia ?? ''),
                direccion: actual.direccion ?? '',
                tipos: actual.tipos,
                curso:
                    actual.capacitacion?.curso ?? 'USO Y MANEJO DE EXTINTORES',
                horas: actual.capacitacion?.horas ?? 4,
                instructor: actual.capacitacion?.instructor ?? instructor ?? '',
                modo: actual.capacitacion?.modo ?? 'normal',
                fotosExistentes: actual.capacitacion?.fotos?.length ?? 0,
                participantes: actual.capacitacion?.participantes ?? [],
            };
        });
        const ubicadas = gruposActuales.flatMap((actual, indice) =>
            actual.unidades.flatMap((u) => {
                const unidad = unidades.find(
                    (x) => x.equipment_id === u.equipment_id,
                );

                return unidad
                    ? [
                          {
                              ...unidad,
                              ...datosDePrueba(unidad),
                              grupo: armados[indice].id,
                              numero_cliente: u.numero_cliente ?? '',
                          },
                      ]
                    : [];
            }),
        );
        const sueltas = unidades
            .filter(
                (u) => !ubicadas.some((f) => f.equipment_id === u.equipment_id),
            )
            .map((u) => ({
                ...u,
                ...datosDePrueba(u),
                grupo: armados[0].id,
                numero_cliente: u.numero_cliente ?? '',
            }));

        return { grupos: armados, filas: [...ubicadas, ...sueltas] };
    });
    const [grupos, setGrupos] = useState<Grupo[]>(inicial.grupos);
    const [filas, setFilas] = useState(inicial.filas);
    const [motivo, setMotivo] = useState('');
    const [ficha, setFicha] = useState<ClientFicha | null>(null);
    const [enviando, setEnviando] = useState(false);

    useEffect(() => {
        void fetch(
            clientes.ficha.url({
                current_team: teamSlug,
                client: sale.client.id,
            }),
        )
            .then((r) => r.json())
            .then(setFicha);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const actualizarGrupo = (id: number, cambio: Partial<Grupo>) =>
        setGrupos(grupos.map((g) => (g.id === id ? { ...g, ...cambio } : g)));

    const mover = (index: number, delta: number) => {
        const destino = index + delta;
        if (destino < 0 || destino >= filas.length) return;
        const copia = [...filas];
        [copia[index], copia[destino]] = [copia[destino], copia[index]];
        setFilas(copia);
    };

    const numerar = (grupoId: number) => {
        let n = 0;
        setFilas(
            filas.map((f) =>
                f.grupo === grupoId
                    ? { ...f, numero_cliente: String(++n).padStart(2, '0') }
                    : f,
            ),
        );
    };

    const quitarGrupo = (id: number) => {
        const primero = grupos.find((g) => g.id !== id)!.id;
        setGrupos(grupos.filter((g) => g.id !== id));
        setFilas(
            filas.map((f) => (f.grupo === id ? { ...f, grupo: primero } : f)),
        );
    };

    const emitir = () => {
        setEnviando(true);
        router.post(
            ventas.certificados.store.url({
                current_team: teamSlug,
                sale: sale.id,
            }),
            {
                grupos: grupos.map((g) => ({
                    referencia: g.referencia || null,
                    direccion: g.direccion || null,
                    tipos: g.tipos,
                    unidades: filas
                        .filter((f) => f.grupo === g.id)
                        .map((f) => ({
                            equipment_id: f.equipment_id,
                            numero_cliente: f.numero_cliente || null,
                            ...(g.tipos.includes('prueba_hidrostatica')
                                ? {
                                      fecha_ultima_ph: f.ph_fecha || null,
                                      presion_ph: f.ph_presion || null,
                                      tiempo_ph: f.ph_tiempo || null,
                                      resultado_ph: f.ph_resultado || null,
                                  }
                                : {}),
                        })),
                    capacitacion: g.tipos.includes('capacitacion')
                        ? {
                              curso: g.curso,
                              horas: g.horas,
                              instructor: g.instructor,
                              modo: g.modo,
                              fotos: g.fotos,
                              participantes: g.participantes,
                          }
                        : null,
                })),
                motivo: motivo || null,
            },
            {
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ?? 'No se pudieron emitir.',
                    ),
                onFinish: () => setEnviando(false),
                forceFormData: true,
            },
        );
    };

    const vigentes = emitidos.filter((e) => e.estado === 'vigente');
    const pendientes = TIPOS.filter(
        (t) =>
            tiposSugeridos.includes(t.codigo) &&
            !vigentes.some((e) => e.tipo_codigo === t.codigo),
    );
    const sinAgente = unidades.filter((u) => !u.agente_conocido);

    const cambiarFila = (
        equipmentId: number,
        cambio: Partial<(typeof filas)[number]>,
    ) =>
        setFilas(
            filas.map((f) =>
                f.equipment_id === equipmentId ? { ...f, ...cambio } : f,
            ),
        );

    return (
        <VendedorLayout title="Armar certificados">
            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap items-center gap-3">
                    <Button
                        asChild
                        variant="outline"
                        className="border-border bg-card h-9 rounded-[9px] shadow-none"
                    >
                        <Link
                            href={ventas.show.url({
                                current_team: teamSlug,
                                sale: sale.id,
                            })}
                        >
                            <ArrowLeft className="size-4" />
                            {sale.numero_interno}
                        </Link>
                    </Button>
                    <div>
                        <h1 className="font-['Oswald',sans-serif] text-[22px] font-semibold uppercase">
                            Armar certificados
                        </h1>
                        <p className="text-muted-foreground text-[12.5px]">
                            {sale.client.razon_social}. Reparte los extintores
                            por local o vehículo, ordénalos y usa la numeración
                            del cliente.
                        </p>
                    </div>
                </div>

                {vigentes.length > 0 ? (
                    <Card className="gap-3 rounded-[14px] border-amber-500/40 bg-amber-500/5 p-4 text-[12.5px] shadow-none">
                        <p>
                            Estos certificados ya se emitieron al confirmar la
                            venta. Al guardar se corrigen{' '}
                            <b>los mismos, con el mismo número</b>, y queda
                            registrada una revisión con el motivo. Solo un
                            certificado nuevo recibe número nuevo.
                        </p>
                        <label className="text-foreground/80 text-[11px] font-bold uppercase">
                            Motivo del ajuste (opcional)
                            <Input
                                value={motivo}
                                maxLength={255}
                                onChange={(e) => setMotivo(e.target.value)}
                                placeholder="Ej. el cliente pidió su propia numeración"
                                className="bg-card mt-1 h-9 rounded-[9px] text-[13px] normal-case"
                            />
                        </label>
                    </Card>
                ) : null}

                {pendientes.length > 0 ? (
                    <Card className="gap-1 rounded-[14px] border-amber-500/40 bg-amber-500/5 p-4 text-[12.5px] shadow-none">
                        <p className="font-semibold">
                            Pendientes de datos técnicos:{' '}
                            {pendientes.map((t) => t.nombre).join(' y ')}.
                        </p>
                        <p className="text-muted-foreground">
                            No salen al cobrar. Márcalos en el grupo cuando se
                            hayan hecho y registra los datos reales (fecha,
                            presión, tiempo y resultado de la prueba
                            hidrostática).
                        </p>
                    </Card>
                ) : null}

                {sinAgente.length > 0 ? (
                    <Card className="border-destructive/40 bg-destructive/5 rounded-[14px] p-4 text-[12.5px] shadow-none">
                        Falta el agente extintor de{' '}
                        {sinAgente.map((u) => u.codigo_interno).join(', ')}.
                        Regístralo en el producto del catálogo o en el equipo:
                        sin él no se emite el certificado.
                    </Card>
                ) : null}

                {unidades.length === 0 ? (
                    <Card className="border-border text-muted-foreground rounded-[14px] p-4 text-[12.5px] shadow-none">
                        Esta venta no tiene extintores con serie: solo se puede
                        emitir el certificado de capacitación.
                    </Card>
                ) : null}

                {grupos.map((grupo, gIndex) => {
                    const filasDelGrupo = filas.filter(
                        (f) => f.grupo === grupo.id,
                    );

                    return (
                        <Card
                            key={grupo.id}
                            className="border-border bg-card gap-4 rounded-[16px] p-5 shadow-none"
                        >
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <h2 className="flex items-center gap-2 font-['Oswald',sans-serif] text-[17px] font-semibold uppercase">
                                    <Award className="text-primary-strong size-4" />
                                    Grupo {gIndex + 1}
                                    <span className="text-muted-foreground text-[12px] font-normal normal-case">
                                        {filasDelGrupo.length} extintor(es)
                                    </span>
                                </h2>
                                {grupos.length > 1 ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => quitarGrupo(grupo.id)}
                                        className="text-destructive-strong rounded-[8px] shadow-none"
                                    >
                                        <Trash2 className="size-3.5" />
                                        Quitar grupo
                                    </Button>
                                ) : null}
                            </div>

                            <div className="grid gap-4 lg:grid-cols-[170px_minmax(0,1fr)]">
                                <div>
                                    <div className="text-foreground/80 text-[11px] font-bold uppercase">
                                        Destino
                                    </div>
                                    <div className="bg-muted mt-1 flex rounded-[9px] p-[3px]">
                                        {(
                                            [
                                                ['local_cliente', 'Local'],
                                                ['vehiculo', 'Vehículo'],
                                            ] as const
                                        ).map(([valor, texto]) => (
                                            <button
                                                key={valor}
                                                type="button"
                                                onClick={() =>
                                                    actualizarGrupo(grupo.id, {
                                                        destino: valor,
                                                        referencia: '',
                                                        tipos: tiposPorDestino(
                                                            valor,
                                                        ),
                                                    })
                                                }
                                                className={`flex-1 rounded-[7px] px-2 py-1.5 text-xs font-bold ${grupo.destino === valor ? 'bg-card text-foreground shadow-[0_1px_2px_rgba(0,0,0,0.06)]' : 'text-muted-foreground'}`}
                                            >
                                                {texto}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                                <ReferenciaField
                                    cliente={ficha}
                                    destino={grupo.destino}
                                    value={grupo.referencia}
                                    onChange={(valor) => {
                                        const sede = ficha?.sedes.find(
                                            (s) =>
                                                `SEDE: ${s.nombre}` === valor,
                                        );
                                        actualizarGrupo(grupo.id, {
                                            referencia: valor,
                                            direccion:
                                                sede?.direccion ??
                                                grupo.direccion,
                                        });
                                    }}
                                />
                            </div>

                            <div>
                                <div className="text-foreground/80 text-[11px] font-bold uppercase">
                                    Dirección que sale en el certificado
                                </div>
                                <Input
                                    value={grupo.direccion}
                                    onChange={(e) =>
                                        actualizarGrupo(grupo.id, {
                                            direccion: e.target.value,
                                        })
                                    }
                                    placeholder={
                                        sale.client.direccion_fiscal ??
                                        'Dirección fiscal del cliente'
                                    }
                                    className="mt-1 h-9 rounded-[9px] text-[13px]"
                                />
                            </div>

                            <div>
                                <div className="text-foreground/80 text-[11px] font-bold uppercase">
                                    Certificados
                                </div>
                                <div className="mt-1 flex flex-wrap gap-2">
                                    {TIPOS.map((tipo) => (
                                        <label
                                            key={tipo.codigo}
                                            className="border-border flex cursor-pointer items-center gap-2 rounded-[9px] border px-3 py-1.5 text-[12.5px] font-semibold"
                                        >
                                            <input
                                                type="checkbox"
                                                className="accent-primary size-4"
                                                checked={grupo.tipos.includes(
                                                    tipo.codigo,
                                                )}
                                                onChange={() =>
                                                    actualizarGrupo(grupo.id, {
                                                        tipos: grupo.tipos.includes(
                                                            tipo.codigo,
                                                        )
                                                            ? grupo.tipos.filter(
                                                                  (t) =>
                                                                      t !==
                                                                      tipo.codigo,
                                                              )
                                                            : [
                                                                  ...grupo.tipos,
                                                                  tipo.codigo,
                                                              ],
                                                    })
                                                }
                                            />
                                            {tipo.nombre}
                                        </label>
                                    ))}
                                </div>
                            </div>

                            {grupo.tipos.includes('capacitacion') ? (
                                <div className="grid gap-3">
                                    <div className="grid gap-2 sm:grid-cols-3">
                                        {(
                                            [
                                                ['normal', 'Normal'],
                                                ['con_fotos', 'Con fotos'],
                                                [
                                                    'por_trabajador',
                                                    'Por trabajador',
                                                ],
                                            ] as const
                                        ).map(([valor, texto]) => (
                                            <button
                                                key={valor}
                                                type="button"
                                                onClick={() =>
                                                    actualizarGrupo(grupo.id, {
                                                        modo: valor,
                                                    })
                                                }
                                                className={`rounded-[9px] border px-3 py-2 text-[12px] font-semibold ${grupo.modo === valor ? 'border-primary bg-primary/10 text-primary-strong' : 'border-border'}`}
                                            >
                                                {texto}
                                            </button>
                                        ))}
                                    </div>
                                    <div className="grid gap-3 sm:grid-cols-[1fr_90px_1fr]">
                                        <label className="text-foreground/80 text-[11px] font-bold uppercase">
                                            Curso
                                            <Input
                                                value={grupo.curso}
                                                onChange={(e) =>
                                                    actualizarGrupo(grupo.id, {
                                                        curso: e.target.value,
                                                    })
                                                }
                                                className="mt-1 h-9 rounded-[9px] text-[13px] normal-case"
                                            />
                                        </label>
                                        <label className="text-foreground/80 text-[11px] font-bold uppercase">
                                            Horas
                                            <Input
                                                type="number"
                                                min={1}
                                                value={grupo.horas}
                                                onChange={(e) =>
                                                    actualizarGrupo(grupo.id, {
                                                        horas: Number(
                                                            e.target.value,
                                                        ),
                                                    })
                                                }
                                                className="mt-1 h-9 rounded-[9px] text-[13px]"
                                            />
                                        </label>
                                        <label className="text-foreground/80 text-[11px] font-bold uppercase">
                                            Instructor
                                            <Input
                                                value={grupo.instructor}
                                                onChange={(e) =>
                                                    actualizarGrupo(grupo.id, {
                                                        instructor:
                                                            e.target.value,
                                                    })
                                                }
                                                className="mt-1 h-9 rounded-[9px] text-[13px] normal-case"
                                            />
                                        </label>
                                    </div>

                                    {grupo.modo === 'con_fotos' ? (
                                        <label className="border-border rounded-[10px] border border-dashed p-3 text-[12px]">
                                            <span className="font-bold">
                                                Fotos de la capacitación
                                            </span>
                                            <span className="text-muted-foreground ml-2">
                                                1 a 3 JPG/PNG, máximo 5 MB cada
                                                una
                                            </span>
                                            <Input
                                                type="file"
                                                accept="image/jpeg,image/png"
                                                multiple
                                                onChange={(event) =>
                                                    actualizarGrupo(grupo.id, {
                                                        fotos: Array.from(
                                                            event.target
                                                                .files ?? [],
                                                        ).slice(0, 3),
                                                    })
                                                }
                                                className="mt-2"
                                            />
                                            {grupo.fotosExistentes > 0 &&
                                            grupo.fotos.length === 0 ? (
                                                <span className="text-muted-foreground mt-1 block">
                                                    Se conservarán{' '}
                                                    {grupo.fotosExistentes}{' '}
                                                    foto(s) actuales.
                                                </span>
                                            ) : null}
                                        </label>
                                    ) : null}

                                    {grupo.modo === 'por_trabajador' ? (
                                        <div className="border-border grid gap-2 rounded-[10px] border p-3">
                                            <div className="flex items-center justify-between gap-2">
                                                <div>
                                                    <div className="text-[12px] font-bold">
                                                        Trabajadores
                                                    </div>
                                                    <div className="text-muted-foreground text-[11px]">
                                                        Cada persona tendrá
                                                        número y QR propios.
                                                    </div>
                                                </div>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        actualizarGrupo(
                                                            grupo.id,
                                                            {
                                                                participantes: [
                                                                    ...grupo.participantes,
                                                                    {
                                                                        nombres:
                                                                            '',
                                                                        dni: '',
                                                                        cargo: '',
                                                                    },
                                                                ],
                                                            },
                                                        )
                                                    }
                                                >
                                                    <Plus className="size-3.5" />{' '}
                                                    Agregar
                                                </Button>
                                            </div>
                                            {grupo.participantes.map(
                                                (participante, indice) => (
                                                    <div
                                                        key={
                                                            participante.id ??
                                                            indice
                                                        }
                                                        className="grid gap-2 sm:grid-cols-[1fr_150px_150px_36px]"
                                                    >
                                                        <Input
                                                            value={
                                                                participante.nombres
                                                            }
                                                            placeholder="Nombres y apellidos"
                                                            onChange={(event) =>
                                                                actualizarGrupo(
                                                                    grupo.id,
                                                                    {
                                                                        participantes:
                                                                            grupo.participantes.map(
                                                                                (
                                                                                    item,
                                                                                    i,
                                                                                ) =>
                                                                                    i ===
                                                                                    indice
                                                                                        ? {
                                                                                              ...item,
                                                                                              nombres:
                                                                                                  event
                                                                                                      .target
                                                                                                      .value,
                                                                                          }
                                                                                        : item,
                                                                            ),
                                                                    },
                                                                )
                                                            }
                                                        />
                                                        <Input
                                                            value={
                                                                participante.dni
                                                            }
                                                            placeholder="DNI opcional"
                                                            onChange={(event) =>
                                                                actualizarGrupo(
                                                                    grupo.id,
                                                                    {
                                                                        participantes:
                                                                            grupo.participantes.map(
                                                                                (
                                                                                    item,
                                                                                    i,
                                                                                ) =>
                                                                                    i ===
                                                                                    indice
                                                                                        ? {
                                                                                              ...item,
                                                                                              dni: event
                                                                                                  .target
                                                                                                  .value,
                                                                                          }
                                                                                        : item,
                                                                            ),
                                                                    },
                                                                )
                                                            }
                                                        />
                                                        <Input
                                                            value={
                                                                participante.cargo
                                                            }
                                                            placeholder="Cargo opcional"
                                                            onChange={(event) =>
                                                                actualizarGrupo(
                                                                    grupo.id,
                                                                    {
                                                                        participantes:
                                                                            grupo.participantes.map(
                                                                                (
                                                                                    item,
                                                                                    i,
                                                                                ) =>
                                                                                    i ===
                                                                                    indice
                                                                                        ? {
                                                                                              ...item,
                                                                                              cargo: event
                                                                                                  .target
                                                                                                  .value,
                                                                                          }
                                                                                        : item,
                                                                            ),
                                                                    },
                                                                )
                                                            }
                                                        />
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="icon"
                                                            onClick={() =>
                                                                actualizarGrupo(
                                                                    grupo.id,
                                                                    {
                                                                        participantes:
                                                                            grupo.participantes.filter(
                                                                                (
                                                                                    _,
                                                                                    i,
                                                                                ) =>
                                                                                    i !==
                                                                                    indice,
                                                                            ),
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            <Trash2 className="size-3.5" />
                                                        </Button>
                                                    </div>
                                                ),
                                            )}
                                        </div>
                                    ) : null}
                                </div>
                            ) : null}

                            {filasDelGrupo.length > 0 ? (
                                <div className="border-border overflow-x-auto rounded-[10px] border">
                                    <table className="w-full min-w-[640px] text-[12.5px]">
                                        <thead className="bg-muted/50 text-muted-foreground text-left text-[10px] font-bold uppercase">
                                            <tr>
                                                <th className="px-2.5 py-2">
                                                    Orden
                                                </th>
                                                <th className="px-2.5 py-2">
                                                    N° del cliente
                                                </th>
                                                <th className="px-2.5 py-2">
                                                    Extintor
                                                </th>
                                                <th className="px-2.5 py-2">
                                                    Serie fab.
                                                </th>
                                                <th className="px-2.5 py-2">
                                                    Grupo
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {filas.map((fila, index) =>
                                                fila.grupo !==
                                                grupo.id ? null : (
                                                    <tr
                                                        key={fila.equipment_id}
                                                        className="border-border border-t"
                                                    >
                                                        <td className="px-2.5 py-1.5">
                                                            <div className="flex gap-1">
                                                                <button
                                                                    type="button"
                                                                    onClick={() =>
                                                                        mover(
                                                                            index,
                                                                            -1,
                                                                        )
                                                                    }
                                                                    className="border-border hover:bg-muted flex size-7 items-center justify-center rounded-[7px] border"
                                                                >
                                                                    <ArrowUp className="size-3" />
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    onClick={() =>
                                                                        mover(
                                                                            index,
                                                                            1,
                                                                        )
                                                                    }
                                                                    className="border-border hover:bg-muted flex size-7 items-center justify-center rounded-[7px] border"
                                                                >
                                                                    <ArrowDown className="size-3" />
                                                                </button>
                                                            </div>
                                                        </td>
                                                        <td className="px-2.5 py-1.5">
                                                            <Input
                                                                value={
                                                                    fila.numero_cliente
                                                                }
                                                                maxLength={40}
                                                                placeholder="Ej. 01"
                                                                onChange={(e) =>
                                                                    setFilas(
                                                                        filas.map(
                                                                            (
                                                                                f,
                                                                            ) =>
                                                                                f.equipment_id ===
                                                                                fila.equipment_id
                                                                                    ? {
                                                                                          ...f,
                                                                                          numero_cliente:
                                                                                              e
                                                                                                  .target
                                                                                                  .value,
                                                                                      }
                                                                                    : f,
                                                                        ),
                                                                    )
                                                                }
                                                                className="h-8 w-24 rounded-[7px] text-[12px]"
                                                            />
                                                        </td>
                                                        <td className="px-2.5 py-1.5">
                                                            <span className="block font-semibold">
                                                                {fila.producto}
                                                            </span>
                                                            <span className="text-muted-foreground font-['IBM_Plex_Mono',monospace] text-[11px]">
                                                                {
                                                                    fila.codigo_interno
                                                                }
                                                                {fila.capacidad
                                                                    ? ` · ${fila.capacidad}`
                                                                    : ''}
                                                                {fila.marca
                                                                    ? ` · ${fila.marca}`
                                                                    : ''}
                                                                {` · ${fila.agente_conocido ? fila.agente : 'Sin agente'}`}
                                                            </span>
                                                        </td>
                                                        <td className="px-2.5 py-1.5 font-['IBM_Plex_Mono',monospace] whitespace-nowrap tabular-nums">
                                                            {fila.serie_fabricante ??
                                                                '—'}
                                                        </td>
                                                        <td className="px-2.5 py-1.5">
                                                            <select
                                                                value={
                                                                    fila.grupo
                                                                }
                                                                onChange={(e) =>
                                                                    setFilas(
                                                                        filas.map(
                                                                            (
                                                                                f,
                                                                            ) =>
                                                                                f.equipment_id ===
                                                                                fila.equipment_id
                                                                                    ? {
                                                                                          ...f,
                                                                                          grupo: Number(
                                                                                              e
                                                                                                  .target
                                                                                                  .value,
                                                                                          ),
                                                                                      }
                                                                                    : f,
                                                                        ),
                                                                    )
                                                                }
                                                                className="border-border bg-card h-8 rounded-[7px] border px-2 text-[12px]"
                                                            >
                                                                {grupos.map(
                                                                    (g, i) => (
                                                                        <option
                                                                            key={
                                                                                g.id
                                                                            }
                                                                            value={
                                                                                g.id
                                                                            }
                                                                        >
                                                                            Grupo{' '}
                                                                            {i +
                                                                                1}
                                                                        </option>
                                                                    ),
                                                                )}
                                                            </select>
                                                        </td>
                                                    </tr>
                                                ),
                                            )}
                                        </tbody>
                                    </table>
                                    {grupo.tipos.includes(
                                        'prueba_hidrostatica',
                                    ) ? (
                                        <div className="border-border grid gap-2 border-t p-2">
                                            <div className="text-foreground/80 text-[11px] font-bold uppercase">
                                                Datos reales de la prueba
                                                hidrostática
                                            </div>
                                            {filasDelGrupo.map((fila) => (
                                                <div
                                                    key={fila.equipment_id}
                                                    className="grid items-end gap-2 sm:grid-cols-[120px_repeat(4,minmax(0,1fr))]"
                                                >
                                                    <span className="font-['IBM_Plex_Mono',monospace] text-[11px]">
                                                        {fila.codigo_interno}
                                                    </span>
                                                    <label className="text-muted-foreground text-[10px] font-bold uppercase">
                                                        Fecha
                                                        <Input
                                                            type="date"
                                                            value={fila.ph_fecha}
                                                            onChange={(e) =>
                                                                cambiarFila(
                                                                    fila.equipment_id,
                                                                    {
                                                                        ph_fecha:
                                                                            e
                                                                                .target
                                                                                .value,
                                                                    },
                                                                )
                                                            }
                                                            className="mt-1 h-8 rounded-[7px] text-[12px]"
                                                        />
                                                    </label>
                                                    <label className="text-muted-foreground text-[10px] font-bold uppercase">
                                                        Presión
                                                        <Input
                                                            value={
                                                                fila.ph_presion
                                                            }
                                                            maxLength={20}
                                                            placeholder={
                                                                fila.presion_ph_sugerida ??
                                                                'Ej. 600 PSI'
                                                            }
                                                            onChange={(e) =>
                                                                cambiarFila(
                                                                    fila.equipment_id,
                                                                    {
                                                                        ph_presion:
                                                                            e
                                                                                .target
                                                                                .value,
                                                                    },
                                                                )
                                                            }
                                                            className="mt-1 h-8 rounded-[7px] text-[12px]"
                                                        />
                                                    </label>
                                                    <label className="text-muted-foreground text-[10px] font-bold uppercase">
                                                        Tiempo
                                                        <Input
                                                            value={
                                                                fila.ph_tiempo
                                                            }
                                                            maxLength={20}
                                                            placeholder="Ej. 60 SEG"
                                                            onChange={(e) =>
                                                                cambiarFila(
                                                                    fila.equipment_id,
                                                                    {
                                                                        ph_tiempo:
                                                                            e
                                                                                .target
                                                                                .value,
                                                                    },
                                                                )
                                                            }
                                                            className="mt-1 h-8 rounded-[7px] text-[12px]"
                                                        />
                                                    </label>
                                                    <label className="text-muted-foreground text-[10px] font-bold uppercase">
                                                        Resultado
                                                        <select
                                                            value={
                                                                fila.ph_resultado
                                                            }
                                                            onChange={(e) =>
                                                                cambiarFila(
                                                                    fila.equipment_id,
                                                                    {
                                                                        ph_resultado:
                                                                            e
                                                                                .target
                                                                                .value,
                                                                    },
                                                                )
                                                            }
                                                            className="border-border bg-card mt-1 h-8 w-full rounded-[7px] border px-2 text-[12px] normal-case"
                                                        >
                                                            <option value="">
                                                                Elegir
                                                            </option>
                                                            <option value="aprobado">
                                                                Aprobado
                                                            </option>
                                                            <option value="desaprobado">
                                                                No aprobado
                                                            </option>
                                                        </select>
                                                    </label>
                                                </div>
                                            ))}
                                        </div>
                                    ) : null}
                                    <div className="border-border border-t p-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => numerar(grupo.id)}
                                            className="rounded-[8px] shadow-none"
                                        >
                                            <ListOrdered className="size-3.5" />
                                            Numerar 01, 02, 03… en este orden
                                        </Button>
                                    </div>
                                </div>
                            ) : null}
                        </Card>
                    );
                })}

                <div className="flex flex-wrap justify-between gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setGrupos([...grupos, nuevoGrupo()])}
                        className="rounded-[9px] shadow-none"
                    >
                        <Plus className="size-4" />
                        Otro local o vehículo
                    </Button>
                    <Button
                        type="button"
                        disabled={enviando}
                        onClick={emitir}
                        className="bg-primary hover:bg-primary/90 rounded-[9px] px-5 font-bold text-white shadow-none"
                    >
                        {enviando ? (
                            <Cargando className="size-4" />
                        ) : (
                            <Award className="size-4" />
                        )}
                        {vigentes.length > 0
                            ? 'Guardar ajustes'
                            : 'Emitir certificados'}
                    </Button>
                </div>
            </div>
            <CargaLarga
                activo={enviando}
                titulo={
                    vigentes.length > 0
                        ? 'Guardando los ajustes'
                        : 'Emitiendo los certificados'
                }
                detalle="Se arma el PDF de cada certificado"
            />
        </VendedorLayout>
    );
}
