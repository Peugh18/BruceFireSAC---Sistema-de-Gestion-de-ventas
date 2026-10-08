import { Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Award, Copy, Loader2, Plus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

import type { ClientFicha } from '@/components/client-picker';
import ReferenciaField from '@/components/referencia-field';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import VendedorLayout from '@/layouts/vendedor-layout';
import clientes from '@/routes/vendedor/clientes';
import ventas from '@/routes/vendedor/ventas';
import type { Team } from '@/types';

type Columna = {
    clave: string;
    titulo: string;
    tipo?: 'texto' | 'numero' | 'resultado';
    unidad?: string | null;
    obligatorio?: boolean;
    minimo?: number | null;
    maximo?: number | null;
};

type PuntoChecklist = {
    texto: string;
    unidad?: string | null;
    minimo?: number | null;
    maximo?: number | null;
};

type Fila = Record<string, string>;
type Prueba = { estado: 'C' | 'NC' | 'NA'; valor: string };

type Props = {
    sale: {
        id: number;
        numero_interno: string;
        destino: 'local_cliente' | 'vehiculo';
        referencia: string | null;
        client: {
            id: number;
            razon_social: string;
            direccion_fiscal: string | null;
        };
    };
    tipo: {
        codigo: string;
        nombre: string;
        columnas: Columna[];
        checklist: PuntoChecklist[];
    };
    atenciones: string[];
    existente: {
        numero: string;
        revision: number;
        referencia: string | null;
        direccion: string | null;
        tipo_atencion: string | null;
        filas: Fila[];
        pruebas: { estado?: string; valor?: string | null }[];
        observaciones: string | null;
    } | null;
};

const RESULTADOS = [
    { valor: 'operativo', texto: 'Operativo' },
    { valor: 'observado', texto: 'Observado' },
    { valor: 'no_operativo', texto: 'No operativo' },
];

function fueraDeRango(
    valor: string,
    minimo?: number | null,
    maximo?: number | null,
) {
    if (valor.trim() === '' || Number.isNaN(Number(valor.replace(',', '.')))) {
        return false;
    }

    const numero = Number(valor.replace(',', '.'));

    return (
        (minimo != null && numero < minimo) ||
        (maximo != null && numero > maximo)
    );
}

function rango(
    minimo?: number | null,
    maximo?: number | null,
    unidad?: string | null,
) {
    const u = unidad ? ` ${unidad}` : '';
    if (minimo != null && maximo != null)
        return `entre ${minimo} y ${maximo}${u}`;
    if (minimo != null) return `mínimo ${minimo}${u}`;
    if (maximo != null) return `máximo ${maximo}${u}`;

    return null;
}

/**
 * Certificado de un servicio (luces de emergencia, detección, lámina…): las
 * columnas de la tabla y el checklist salen de la configuración del tipo, así
 * que sirve también para los tipos nuevos que cree el Gerente.
 */
export default function CertificadoServicio({
    sale,
    tipo,
    atenciones,
    existente,
}: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug = currentTeam?.slug ?? '';

    const filaVacia = (): Fila =>
        Object.fromEntries(
            tipo.columnas.map((c) => [
                c.clave,
                c.tipo === 'resultado' ? 'operativo' : '',
            ]),
        );

    const [filas, setFilas] = useState<Fila[]>(
        existente?.filas.length ? existente.filas : [filaVacia()],
    );
    const [pruebas, setPruebas] = useState<Prueba[]>(
        tipo.checklist.map((_, i) => ({
            estado: (existente?.pruebas[i]?.estado as Prueba['estado']) ?? 'C',
            valor: existente?.pruebas[i]?.valor ?? '',
        })),
    );
    const [referencia, setReferencia] = useState(
        existente?.referencia ?? sale.referencia ?? '',
    );
    const [direccion, setDireccion] = useState(existente?.direccion ?? '');
    const [atencion, setAtencion] = useState(
        existente?.tipo_atencion ?? 'mantenimiento',
    );
    const [observaciones, setObservaciones] = useState(
        existente?.observaciones ?? '',
    );
    const [ficha, setFicha] = useState<ClientFicha | null>(null);
    const [enviando, setEnviando] = useState(false);
    const [errores, setErrores] = useState<Record<string, string>>({});

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

    const cambiarCelda = (i: number, clave: string, valor: string) =>
        setFilas(filas.map((f, j) => (j === i ? { ...f, [clave]: valor } : f)));

    const cambiarPrueba = (i: number, cambio: Partial<Prueba>) =>
        setPruebas(pruebas.map((p, j) => (j === i ? { ...p, ...cambio } : p)));

    const guardar = () => {
        setEnviando(true);
        setErrores({});
        router.post(
            ventas.certificadoServicio.store.url({
                current_team: teamSlug,
                sale: sale.id,
                tipo: tipo.codigo,
            }),
            {
                referencia,
                direccion,
                tipo_atencion: atencion,
                filas,
                pruebas,
                observaciones,
            },
            {
                onError: (e) => {
                    setErrores(e);
                    toast.error(
                        Object.values(e)[0] ??
                            'Revisa los datos del certificado.',
                    );
                },
                onFinish: () => setEnviando(false),
            },
        );
    };

    return (
        <VendedorLayout title={tipo.nombre}>
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
                            {tipo.nombre}
                        </h1>
                        <p className="text-muted-foreground text-[12.5px]">
                            {sale.client.razon_social}
                            {existente
                                ? ` · corrige ${existente.numero} (queda como revisión ${existente.revision + 1})`
                                : ' · se emite con un número nuevo'}
                        </p>
                    </div>
                </div>

                <Card className="border-border bg-card gap-4 rounded-[16px] p-5 shadow-none">
                    <div className="grid gap-4 lg:grid-cols-2">
                        <ReferenciaField
                            cliente={ficha}
                            destino={sale.destino}
                            value={referencia}
                            onChange={(valor) => {
                                setReferencia(valor);
                                const sede = ficha?.sedes.find(
                                    (s) => `SEDE: ${s.nombre}` === valor,
                                );
                                if (sede?.direccion)
                                    setDireccion(sede.direccion);
                            }}
                        />
                        <div className="grid gap-3">
                            <label className="text-foreground/80 text-[11px] font-bold uppercase">
                                Dirección del servicio
                                <Input
                                    value={direccion}
                                    onChange={(e) =>
                                        setDireccion(e.target.value)
                                    }
                                    placeholder={
                                        sale.client.direccion_fiscal ??
                                        'Dirección fiscal del cliente'
                                    }
                                    className="mt-1 h-10 rounded-[9px] text-[13px] normal-case"
                                />
                            </label>
                            <div>
                                <div className="text-foreground/80 text-[11px] font-bold uppercase">
                                    Tipo de atención
                                </div>
                                <div className="bg-muted mt-1 flex rounded-[9px] p-[3px]">
                                    {atenciones.map((a) => (
                                        <button
                                            key={a}
                                            type="button"
                                            onClick={() => setAtencion(a)}
                                            className={`flex-1 rounded-[7px] px-3 py-1.5 text-xs font-bold capitalize ${atencion === a ? 'bg-card text-foreground shadow-[0_1px_2px_rgba(0,0,0,0.06)]' : 'text-muted-foreground'}`}
                                        >
                                            {a}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>
                </Card>

                <Card className="border-border bg-card gap-3 rounded-[16px] p-5 shadow-none">
                    <div className="flex items-center justify-between">
                        <h2 className="font-['Oswald',sans-serif] text-[17px] font-semibold uppercase">
                            Equipos o ambientes ({filas.length})
                        </h2>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setFilas([...filas, filaVacia()])}
                            className="rounded-[8px] shadow-none"
                        >
                            <Plus className="size-3.5" />
                            Agregar fila
                        </Button>
                    </div>
                    <div className="border-border overflow-x-auto rounded-[10px] border">
                        <table className="w-full min-w-[720px] text-[12.5px]">
                            <thead className="bg-muted/50 text-muted-foreground text-left text-[10px] font-bold uppercase">
                                <tr>
                                    {tipo.columnas.map((c) => (
                                        <th key={c.clave} className="px-2 py-2">
                                            {c.titulo}
                                            {c.unidad ? ` (${c.unidad})` : ''}
                                            {c.obligatorio ? (
                                                <span className="text-destructive-strong">
                                                    {' '}
                                                    *
                                                </span>
                                            ) : null}
                                        </th>
                                    ))}
                                    <th className="w-16" />
                                </tr>
                            </thead>
                            <tbody>
                                {filas.map((fila, i) => (
                                    <tr
                                        key={i}
                                        className="border-border border-t align-top"
                                    >
                                        {tipo.columnas.map((c) => {
                                            const error =
                                                errores[
                                                    `filas.${i}.${c.clave}`
                                                ];
                                            const valor = fila[c.clave] ?? '';
                                            const aviso =
                                                c.tipo === 'numero' &&
                                                fueraDeRango(
                                                    valor,
                                                    c.minimo,
                                                    c.maximo,
                                                );

                                            return (
                                                <td
                                                    key={c.clave}
                                                    className="px-2 py-1.5"
                                                >
                                                    {c.tipo === 'resultado' ? (
                                                        <select
                                                            aria-label={`Resultado de ${c.clave}`}
                                                            value={
                                                                valor ||
                                                                'operativo'
                                                            }
                                                            onChange={(e) =>
                                                                cambiarCelda(
                                                                    i,
                                                                    c.clave,
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="border-border bg-card h-8 w-full rounded-[7px] border px-2 text-[12px]"
                                                        >
                                                            {RESULTADOS.map(
                                                                (r) => (
                                                                    <option
                                                                        key={
                                                                            r.valor
                                                                        }
                                                                        value={
                                                                            r.valor
                                                                        }
                                                                    >
                                                                        {
                                                                            r.texto
                                                                        }
                                                                    </option>
                                                                ),
                                                            )}
                                                        </select>
                                                    ) : (
                                                        <Input
                                                            value={valor}
                                                            inputMode={
                                                                c.tipo ===
                                                                'numero'
                                                                    ? 'decimal'
                                                                    : undefined
                                                            }
                                                            placeholder={
                                                                c.clave ===
                                                                'item'
                                                                    ? String(
                                                                          i + 1,
                                                                      ).padStart(
                                                                          2,
                                                                          '0',
                                                                      )
                                                                    : ''
                                                            }
                                                            onChange={(e) =>
                                                                cambiarCelda(
                                                                    i,
                                                                    c.clave,
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            className={`h-8 rounded-[7px] text-[12px] ${error ? 'border-destructive' : aviso ? 'border-amber-500' : ''}`}
                                                        />
                                                    )}
                                                    {aviso ? (
                                                        <div className="text-warning-strong mt-0.5 text-[10.5px]">
                                                            Fuera de rango (
                                                            {rango(
                                                                c.minimo,
                                                                c.maximo,
                                                                c.unidad,
                                                            )}
                                                            )
                                                        </div>
                                                    ) : null}
                                                    {error ? (
                                                        <div
                                                            className="text-destructive-strong mt-0.5 text-[10.5px]"
                                                            role="alert"
                                                        >
                                                            {error}
                                                        </div>
                                                    ) : null}
                                                </td>
                                            );
                                        })}
                                        <td className="px-2 py-1.5">
                                            <div className="flex gap-1">
                                                <button
                                                    type="button"
                                                    title="Duplicar fila"
                                                    onClick={() =>
                                                        setFilas([
                                                            ...filas.slice(
                                                                0,
                                                                i + 1,
                                                            ),
                                                            {
                                                                ...fila,
                                                                item: '',
                                                            },
                                                            ...filas.slice(
                                                                i + 1,
                                                            ),
                                                        ])
                                                    }
                                                    className="border-border hover:bg-muted flex size-7 items-center justify-center rounded-[7px] border"
                                                >
                                                    <Copy className="size-3" />
                                                </button>
                                                <button
                                                    type="button"
                                                    title="Quitar fila"
                                                    disabled={
                                                        filas.length === 1
                                                    }
                                                    onClick={() =>
                                                        setFilas(
                                                            filas.filter(
                                                                (_, j) =>
                                                                    j !== i,
                                                            ),
                                                        )
                                                    }
                                                    className="border-border text-destructive-strong hover:bg-muted flex size-7 items-center justify-center rounded-[7px] border disabled:opacity-40"
                                                >
                                                    <Trash2 className="size-3" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>

                {tipo.checklist.length > 0 ? (
                    <Card className="border-border bg-card gap-3 rounded-[16px] p-5 shadow-none">
                        <h2 className="font-['Oswald',sans-serif] text-[17px] font-semibold uppercase">
                            Pruebas y verificaciones
                        </h2>
                        <p className="text-muted-foreground text-[12px]">
                            C = conforme · NC = no conforme · NA = no aplica. Si
                            un valor medido sale del rango, el certificado lo
                            marca NC solo.
                        </p>
                        <div className="divide-border border-border divide-y rounded-[10px] border">
                            {tipo.checklist.map((punto, i) => {
                                const aviso = fueraDeRango(
                                    pruebas[i].valor,
                                    punto.minimo,
                                    punto.maximo,
                                );
                                const textoRango = rango(
                                    punto.minimo,
                                    punto.maximo,
                                    punto.unidad,
                                );

                                return (
                                    <div
                                        key={i}
                                        className="flex flex-wrap items-center gap-3 px-3 py-2"
                                    >
                                        <span className="min-w-[220px] flex-1 text-[12.5px]">
                                            {punto.texto}
                                            {textoRango ? (
                                                <span className="text-muted-foreground">
                                                    {' '}
                                                    · {textoRango}
                                                </span>
                                            ) : null}
                                        </span>
                                        {punto.unidad ? (
                                            <div className="flex items-center gap-1">
                                                <Input
                                                    value={pruebas[i].valor}
                                                    inputMode="decimal"
                                                    onChange={(e) =>
                                                        cambiarPrueba(i, {
                                                            valor: e.target
                                                                .value,
                                                        })
                                                    }
                                                    className={`h-8 w-20 rounded-[7px] text-[12px] ${aviso ? 'border-amber-500' : ''}`}
                                                />
                                                <span className="text-muted-foreground text-[11.5px]">
                                                    {punto.unidad}
                                                </span>
                                            </div>
                                        ) : null}
                                        <div className="bg-muted flex rounded-[8px] p-[2px]">
                                            {(['C', 'NC', 'NA'] as const).map(
                                                (estado) => (
                                                    <button
                                                        key={estado}
                                                        type="button"
                                                        onClick={() =>
                                                            cambiarPrueba(i, {
                                                                estado,
                                                            })
                                                        }
                                                        className={`w-10 rounded-[6px] py-1 text-[11.5px] font-bold ${pruebas[i].estado === estado ? (estado === 'NC' ? 'bg-destructive text-white' : estado === 'C' ? 'bg-emerald-600 text-white' : 'bg-card text-foreground') : 'text-muted-foreground'}`}
                                                    >
                                                        {estado}
                                                    </button>
                                                ),
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </Card>
                ) : null}

                <Card className="border-border bg-card gap-2 rounded-[16px] p-5 shadow-none">
                    <label className="text-foreground/80 text-[11px] font-bold uppercase">
                        Observaciones (salen en el certificado)
                        <textarea
                            value={observaciones}
                            maxLength={1000}
                            onChange={(e) => setObservaciones(e.target.value)}
                            className="border-border bg-card focus-visible:border-ring focus-visible:ring-ring/50 mt-1 min-h-[70px] w-full rounded-[9px] border px-3 py-2 text-[13px] font-normal normal-case outline-none focus-visible:ring-[3px]"
                        />
                    </label>
                </Card>

                <div className="flex justify-end">
                    <Button
                        type="button"
                        disabled={enviando}
                        onClick={guardar}
                        className="bg-primary hover:bg-primary/90 rounded-[9px] px-5 font-bold text-white shadow-none"
                    >
                        {enviando ? (
                            <Loader2 className="size-4 animate-spin" />
                        ) : (
                            <Award className="size-4" />
                        )}
                        {existente
                            ? 'Guardar corrección'
                            : 'Emitir certificado'}
                    </Button>
                </div>
            </div>
        </VendedorLayout>
    );
}
