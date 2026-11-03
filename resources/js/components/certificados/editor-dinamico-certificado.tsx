import { Link, useForm, usePage } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ExternalLink,
    Lock,
    Plus,
    Save,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import GerenteLayout from '@/layouts/gerente-layout';
import CertificadosRoutes from '@/routes/gerente/configuracion/certificados';
import type { Team } from '@/types';
import {
    bloquesCertificado,
    composicionInicial,
} from './composicion-certificado';
import type {
    BloqueCertificado,
    EditorDinamicoProps,
    RelacionCertificado,
} from './composicion-certificado';
import FirmasDelCertificado from './firmas-del-certificado';
import HojaDinamicaCertificado from './hoja-dinamica-certificado';

/** Bloques que revisa una persona: no van en un certificado de producto. */
const BLOQUES_REVISADOS: BloqueCertificado['tipo'][] = [
    'pruebas',
    'resultado',
    'diploma',
];

function Campo({
    etiqueta,
    children,
}: {
    etiqueta: string;
    children: ReactNode;
}) {
    return (
        <label className="grid min-w-0 gap-1.5 text-sm font-medium *:w-full *:min-w-0">
            {etiqueta}
            {children}
        </label>
    );
}

export default function EditorDinamicoCertificado({
    certificado,
    bases,
    maximoColumnas,
    maximoFirmantes,
    firmantesDisponibles,
    productosDisponibles = [],
    tiposDeServicioDisponibles = [],
    productoInicial = null,
}: EditorDinamicoProps) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const equipo = currentTeam?.slug ?? '';
    const form = useForm({
        base_id: '',
        nombre: certificado?.nombre ?? '',
        titulo: certificado?.titulo ?? 'CERTIFICADO',
        subtitulo: certificado?.subtitulo ?? '',
        norma: certificado?.norma ?? '',
        declaracion:
            certificado?.declaracion ??
            (productoInicial
                ? 'Mediante el presente se certifica la garantía de los productos detallados, vendidos en la fecha de emisión.'
                : 'Mediante el presente se certifica la atención descrita, de acuerdo con las pruebas y observaciones registradas.'),
        responsabilidad: certificado?.responsabilidad ?? '',
        prefijo: certificado?.prefijo ?? '',
        vigencia_meses: certificado?.vigencia_meses ?? 12,
        familia:
            certificado?.familia === 'diploma' ? 'diploma' : 'operatividad',
        orientacion: certificado?.orientacion ?? 'vertical',
        // Desde un producto: sin resultado, porque su certificado sale solo con la venta.
        composicion:
            !certificado && productoInicial
                ? composicionInicial(certificado)
                      .filter(
                          (bloque) => !BLOQUES_REVISADOS.includes(bloque.tipo),
                      )
                      .map((bloque) =>
                          bloque.tipo === 'tabla'
                              ? { ...bloque, titulo: 'Productos' }
                              : bloque,
                      )
                : composicionInicial(certificado),
        columnas: certificado?.columnas ?? [
            {
                titulo: 'Descripción',
                tipo: 'texto' as const,
                obligatorio: true,
            },
        ],
        checklist: certificado?.checklist ?? [],
        firmantes: certificado?.firmantes ?? [],
        product_ids:
            certificado?.product_ids ??
            (productoInicial ? [productoInicial] : []),
        tipo_de_servicio_ids: certificado?.tipo_de_servicio_ids ?? [],
    });
    const d = form.data;
    const errores = form.errors as Record<string, string | undefined>;
    const conProductos = d.product_ids.length > 0;
    const bloquesRevisados = d.composicion.filter((bloque) =>
        BLOQUES_REVISADOS.includes(bloque.tipo),
    );
    const [nuevoBloque, setNuevoBloque] =
        useState<BloqueCertificado['tipo']>('texto');
    const tieneTabla = d.composicion.some((b) => b.tipo === 'tabla');
    const tienePruebas = d.composicion.some((b) => b.tipo === 'pruebas');
    const tieneDiploma = d.composicion.some((b) => b.tipo === 'diploma');
    const maximo = maximoColumnas[d.orientacion];

    function guardar(evento: FormEvent) {
        evento.preventDefault();
        form.transform((datos) => ({
            ...datos,
            ...(certificado ? {} : { base_id: datos.base_id || undefined }),
            columnas: tieneTabla ? datos.columnas : [],
            checklist: tienePruebas ? datos.checklist : [],
        }));
        if (certificado) {
            form.put(
                CertificadosRoutes.update.url({
                    current_team: equipo,
                    certificado: certificado.id,
                }),
                { preserveScroll: true },
            );
        } else {
            form.post(CertificadosRoutes.store.url({ current_team: equipo }), {
                preserveScroll: true,
            });
        }
    }

    function copiarBase(id: string) {
        if (!id) {
            form.setData('base_id', '');
            return;
        }
        const base = bases.find((b) => b.id === Number(id));
        if (!base) return;
        form.setData((actual) => ({
            ...actual,
            base_id: id,
            titulo: base.titulo ?? 'CERTIFICADO',
            subtitulo: base.subtitulo ?? '',
            norma: base.norma ?? '',
            declaracion: base.declaracion ?? '',
            responsabilidad: base.responsabilidad ?? '',
            vigencia_meses: base.vigencia_meses,
            familia: base.familia === 'diploma' ? 'diploma' : 'operatividad',
            orientacion: base.orientacion,
            composicion: composicionInicial(base),
            columnas: base.columnas,
            checklist: base.checklist,
            firmantes: base.firmantes,
        }));
    }

    function moverBloque(indice: number, destino: number) {
        const bloques = d.composicion.slice();
        [bloques[indice], bloques[destino]] = [
            bloques[destino],
            bloques[indice],
        ];
        form.setData('composicion', bloques);
    }

    function cambiarBloque(indice: number, cambio: Partial<BloqueCertificado>) {
        form.setData(
            'composicion',
            d.composicion.map((b, i) =>
                i === indice ? { ...b, ...cambio } : b,
            ),
        );
    }

    return (
        <GerenteLayout
            title={
                certificado
                    ? `Editar ${certificado.nombre}`
                    : 'Nuevo certificado'
            }
        >
            <form onSubmit={guardar} className="flex flex-col gap-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <Link
                            href={CertificadosRoutes.index.url({
                                current_team: equipo,
                            })}
                            className="text-muted-foreground text-sm underline underline-offset-4"
                        >
                            Certificados
                        </Link>
                        <h1 className="text-foreground mt-1 text-xl font-semibold">
                            {certificado
                                ? 'Editar plantilla'
                                : 'Crear certificado por bloques'}
                        </h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Elige el contenido y relaciónalo con los servicios o
                            productos que certifica.
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        {certificado?.vista_previa && (
                            <Button asChild variant="outline">
                                <a
                                    href={certificado.vista_previa}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <ExternalLink className="size-4" />
                                    Ver PDF guardado
                                </a>
                            </Button>
                        )}
                        <Button type="submit" disabled={form.processing}>
                            <Save className="size-4" />
                            {form.processing
                                ? 'Guardando…'
                                : 'Guardar plantilla'}
                        </Button>
                    </div>
                </div>
                {Object.keys(errores).length > 0 && (
                    <div
                        role="alert"
                        className="border-destructive/40 bg-destructive/5 text-destructive-strong rounded-lg border p-3 text-sm"
                    >
                        <p className="font-semibold">Revisa estos datos:</p>
                        <ul className="mt-1 list-inside list-disc">
                            {[...new Set(Object.values(errores))].map((m) => (
                                <li key={m}>{m}</li>
                            ))}
                        </ul>
                    </div>
                )}
                <div className="grid items-start gap-5 xl:grid-cols-[390px_minmax(0,1fr)]">
                    <div className="grid min-w-0 gap-3">
                        <details
                            open
                            className="border-border bg-card rounded-xl border p-4"
                        >
                            <summary className="cursor-pointer text-sm font-semibold">
                                Plantilla y formato
                            </summary>
                            <div className="mt-4 grid gap-3">
                                {!certificado && bases.length > 0 && (
                                    <Campo etiqueta="Empezar desde una plantilla (opcional)">
                                        <NativeSelect
                                            value={d.base_id}
                                            onChange={(e) =>
                                                copiarBase(e.target.value)
                                            }
                                        >
                                            <option value="">Desde cero</option>
                                            {bases.map((b) => (
                                                <option key={b.id} value={b.id}>
                                                    {b.nombre}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    </Campo>
                                )}
                                <Campo etiqueta="Nombre de la plantilla">
                                    <Input
                                        value={d.nombre}
                                        onChange={(e) =>
                                            form.setData(
                                                'nombre',
                                                e.target.value,
                                            )
                                        }
                                        maxLength={120}
                                        required
                                        placeholder="Ej. Garantía de mangueras"
                                    />
                                </Campo>
                                <div className="grid grid-cols-2 gap-3">
                                    <Campo etiqueta="Numeración">
                                        <Input
                                            value={d.prefijo}
                                            onChange={(e) =>
                                                form.setData(
                                                    'prefijo',
                                                    e.target.value.toUpperCase(),
                                                )
                                            }
                                            maxLength={6}
                                            pattern="[A-Z]+(-[A-Z]+)*"
                                            required
                                            placeholder="GM"
                                        />
                                    </Campo>
                                    <Campo etiqueta="Vigencia (meses)">
                                        <Input
                                            type="number"
                                            min={0}
                                            max={120}
                                            value={d.vigencia_meses}
                                            onChange={(e) =>
                                                form.setData(
                                                    'vigencia_meses',
                                                    Number(e.target.value),
                                                )
                                            }
                                            required
                                        />
                                    </Campo>
                                </div>
                                <Campo etiqueta="Tipo de contenido">
                                    <NativeSelect
                                        value={d.familia}
                                        onChange={(e) => {
                                            const esDiploma =
                                                e.target.value === 'diploma';
                                            form.setData((actual) => ({
                                                ...actual,
                                                familia: e.target.value,
                                                orientacion: esDiploma
                                                    ? 'horizontal'
                                                    : actual.orientacion,
                                                composicion: esDiploma
                                                    ? [{ tipo: 'diploma' }]
                                                    : composicionInicial(),
                                                subtitulo: esDiploma
                                                    ? 'DE CAPACITACIÓN'
                                                    : actual.subtitulo,
                                            }));
                                        }}
                                    >
                                        <option value="operatividad">
                                            Informe o garantía
                                        </option>
                                        <option value="diploma">
                                            Diploma de capacitación
                                        </option>
                                    </NativeSelect>
                                </Campo>
                                <Campo etiqueta="Orientación">
                                    <NativeSelect
                                        value={d.orientacion}
                                        onChange={(e) =>
                                            form.setData(
                                                'orientacion',
                                                e.target.value as
                                                    | 'vertical'
                                                    | 'horizontal',
                                            )
                                        }
                                        disabled={tieneDiploma}
                                    >
                                        <option value="vertical">
                                            A4 vertical
                                        </option>
                                        <option value="horizontal">
                                            A4 horizontal
                                        </option>
                                    </NativeSelect>
                                </Campo>
                                <Campo etiqueta="Título">
                                    <Input
                                        value={d.titulo}
                                        onChange={(e) =>
                                            form.setData(
                                                'titulo',
                                                e.target.value,
                                            )
                                        }
                                        maxLength={120}
                                        required
                                    />
                                </Campo>
                                <Campo etiqueta="Subtítulo">
                                    <Input
                                        value={d.subtitulo}
                                        onChange={(e) =>
                                            form.setData(
                                                'subtitulo',
                                                e.target.value,
                                            )
                                        }
                                        maxLength={160}
                                        placeholder="DE OPERATIVIDAD Y GARANTÍA"
                                    />
                                </Campo>
                                <Campo etiqueta="Norma o referencia técnica">
                                    <Textarea
                                        value={d.norma}
                                        onChange={(e) =>
                                            form.setData(
                                                'norma',
                                                e.target.value,
                                            )
                                        }
                                        maxLength={500}
                                        rows={2}
                                    />
                                </Campo>
                            </div>
                        </details>
                        <details
                            open
                            className="border-border bg-card rounded-xl border p-4"
                        >
                            <summary className="cursor-pointer text-sm font-semibold">
                                Bloques de contenido · {d.composicion.length}
                            </summary>
                            <div className="mt-4 grid gap-3">
                                <p className="text-muted-foreground text-xs">
                                    Se imprimen en este orden. Usa textos para
                                    especificaciones, garantía o mantenimiento.
                                </p>
                                <ol className="grid gap-3">
                                    {d.composicion.map((b, i) => (
                                        <li
                                            key={i}
                                            className="border-border rounded-lg border p-3"
                                        >
                                            <div className="mb-3 flex items-start justify-between gap-2">
                                                <span className="text-sm font-semibold">
                                                    {i + 1}.{' '}
                                                    {bloquesCertificado[b.tipo]}
                                                </span>
                                                <div className="flex shrink-0 gap-1">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Subir bloque ${i + 1}`}
                                                        disabled={i === 0}
                                                        onClick={() =>
                                                            moverBloque(
                                                                i,
                                                                i - 1,
                                                            )
                                                        }
                                                    >
                                                        <ArrowUp className="size-4" />
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Bajar bloque ${i + 1}`}
                                                        disabled={
                                                            i ===
                                                            d.composicion
                                                                .length -
                                                                1
                                                        }
                                                        onClick={() =>
                                                            moverBloque(
                                                                i,
                                                                i + 1,
                                                            )
                                                        }
                                                    >
                                                        <ArrowDown className="size-4" />
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Quitar bloque ${i + 1}`}
                                                        onClick={() =>
                                                            form.setData(
                                                                'composicion',
                                                                d.composicion.filter(
                                                                    (_, j) =>
                                                                        j !== i,
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                </div>
                                            </div>
                                            {b.tipo !== 'diploma' && (
                                                <Campo etiqueta="Título del bloque">
                                                    <Input
                                                        value={b.titulo ?? ''}
                                                        placeholder={
                                                            bloquesCertificado[
                                                                b.tipo
                                                            ]
                                                        }
                                                        onChange={(e) =>
                                                            cambiarBloque(i, {
                                                                titulo: e.target
                                                                    .value,
                                                            })
                                                        }
                                                        maxLength={120}
                                                    />
                                                </Campo>
                                            )}
                                            {b.tipo === 'texto' && (
                                                <div className="mt-3">
                                                    <Campo etiqueta="Contenido">
                                                        <Textarea
                                                            value={
                                                                b.texto ?? ''
                                                            }
                                                            onChange={(e) =>
                                                                cambiarBloque(
                                                                    i,
                                                                    {
                                                                        texto: e
                                                                            .target
                                                                            .value,
                                                                    },
                                                                )
                                                            }
                                                            rows={5}
                                                            maxLength={4000}
                                                            required
                                                        />
                                                    </Campo>
                                                </div>
                                            )}
                                            {(b.tipo === 'declaracion' ||
                                                b.tipo === 'diploma') && (
                                                <div className="mt-3">
                                                    <Campo etiqueta="Texto de certificación">
                                                        <Textarea
                                                            value={
                                                                d.declaracion
                                                            }
                                                            onChange={(e) =>
                                                                form.setData(
                                                                    'declaracion',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            rows={4}
                                                            maxLength={2000}
                                                        />
                                                    </Campo>
                                                </div>
                                            )}
                                            {b.tipo === 'responsabilidad' && (
                                                <div className="mt-3">
                                                    <Campo etiqueta="Texto final">
                                                        <Textarea
                                                            value={
                                                                d.responsabilidad
                                                            }
                                                            onChange={(e) =>
                                                                form.setData(
                                                                    'responsabilidad',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            rows={4}
                                                            maxLength={2000}
                                                        />
                                                    </Campo>
                                                </div>
                                            )}
                                            {b.tipo === 'datos' && (
                                                <p className="text-muted-foreground mt-2 text-xs">
                                                    El cliente, su dirección y
                                                    la atención se completan
                                                    desde la venta u orden.
                                                </p>
                                            )}
                                            {b.tipo === 'diploma' && (
                                                <p className="text-muted-foreground mt-2 text-xs">
                                                    El curso, duración, fecha e
                                                    instructor se completan al
                                                    emitir. El cliente de la
                                                    venta es el destinatario.
                                                </p>
                                            )}
                                            {b.tipo === 'resultado' && (
                                                <p className="text-muted-foreground mt-2 text-xs">
                                                    Resultado calculado a partir
                                                    de los equipos y pruebas
                                                    registrados.
                                                </p>
                                            )}
                                        </li>
                                    ))}
                                </ol>
                                <div className="flex items-end gap-2">
                                    <div className="min-w-0 flex-1">
                                        <Campo etiqueta="Agregar bloque">
                                            <NativeSelect
                                                value={nuevoBloque}
                                                onChange={(e) =>
                                                    setNuevoBloque(
                                                        e.target
                                                            .value as BloqueCertificado['tipo'],
                                                    )
                                                }
                                            >
                                                {Object.entries(
                                                    bloquesCertificado,
                                                ).map(([tipo, nombre]) => (
                                                    <option
                                                        key={tipo}
                                                        value={tipo}
                                                        disabled={
                                                            (tipo !== 'texto' &&
                                                                d.composicion.some(
                                                                    (b) =>
                                                                        b.tipo ===
                                                                        tipo,
                                                                )) ||
                                                            (tipo ===
                                                                'diploma' &&
                                                                d.familia !==
                                                                    'diploma') ||
                                                            (conProductos &&
                                                                BLOQUES_REVISADOS.includes(
                                                                    tipo as BloqueCertificado['tipo'],
                                                                ))
                                                        }
                                                    >
                                                        {nombre}
                                                    </option>
                                                ))}
                                            </NativeSelect>
                                        </Campo>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        aria-label="Agregar bloque elegido"
                                        disabled={
                                            d.composicion.length >= 20 ||
                                            (nuevoBloque !== 'texto' &&
                                                d.composicion.some(
                                                    (b) =>
                                                        b.tipo === nuevoBloque,
                                                )) ||
                                            (nuevoBloque === 'diploma' &&
                                                d.familia !== 'diploma') ||
                                            (conProductos &&
                                                BLOQUES_REVISADOS.includes(
                                                    nuevoBloque,
                                                ))
                                        }
                                        onClick={() =>
                                            form.setData('composicion', [
                                                ...d.composicion,
                                                { tipo: nuevoBloque },
                                            ])
                                        }
                                    >
                                        <Plus className="size-4" />
                                    </Button>
                                </div>
                            </div>
                        </details>
                        {tieneTabla && (
                            <details
                                open
                                className="border-border bg-card rounded-xl border p-4"
                            >
                                <summary className="cursor-pointer text-sm font-semibold">
                                    Columnas de la tabla · {d.columnas.length}/
                                    {maximo}
                                </summary>
                                {d.product_ids.length > 0 && (
                                    <p className="text-muted-foreground mt-3 text-sm">
                                        Las columnas «Producto» y «Cantidad» se
                                        llenan solas con la venta. Las demás las
                                        completa Almacén una vez en la ficha
                                        técnica de cada producto.
                                    </p>
                                )}
                                <div className="mt-4 grid gap-3">
                                    {d.columnas.map((c, i) => (
                                        <div
                                            key={i}
                                            className="border-border grid gap-2 rounded-lg border p-3"
                                        >
                                            <div className="flex items-end gap-2">
                                                <div className="min-w-0 flex-1">
                                                    <Campo
                                                        etiqueta={`Columna ${i + 1}`}
                                                    >
                                                        <Input
                                                            value={c.titulo}
                                                            onChange={(e) =>
                                                                form.setData(
                                                                    'columnas',
                                                                    d.columnas.map(
                                                                        (
                                                                            x,
                                                                            j,
                                                                        ) =>
                                                                            i ===
                                                                            j
                                                                                ? {
                                                                                      ...x,
                                                                                      titulo: e
                                                                                          .target
                                                                                          .value,
                                                                                  }
                                                                                : x,
                                                                    ),
                                                                )
                                                            }
                                                            maxLength={40}
                                                            required
                                                        />
                                                    </Campo>
                                                </div>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`Subir columna ${i + 1}`}
                                                    disabled={i === 0}
                                                    onClick={() => {
                                                        const cols =
                                                            d.columnas.slice();
                                                        [cols[i - 1], cols[i]] =
                                                            [
                                                                cols[i],
                                                                cols[i - 1],
                                                            ];
                                                        form.setData(
                                                            'columnas',
                                                            cols,
                                                        );
                                                    }}
                                                >
                                                    <ArrowUp className="size-4" />
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`Quitar columna ${i + 1}`}
                                                    onClick={() =>
                                                        form.setData(
                                                            'columnas',
                                                            d.columnas.filter(
                                                                (_, j) =>
                                                                    i !== j,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            </div>
                                            <div className="grid grid-cols-2 gap-2">
                                                <Campo etiqueta="Tipo">
                                                    <NativeSelect
                                                        value={c.tipo}
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'columnas',
                                                                d.columnas.map(
                                                                    (x, j) =>
                                                                        i === j
                                                                            ? {
                                                                                  ...x,
                                                                                  tipo: e
                                                                                      .target
                                                                                      .value as
                                                                                      | 'texto'
                                                                                      | 'numero',
                                                                              }
                                                                            : x,
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        <option value="texto">
                                                            Texto
                                                        </option>
                                                        <option value="numero">
                                                            Número
                                                        </option>
                                                    </NativeSelect>
                                                </Campo>
                                                <Campo etiqueta="Unidad">
                                                    <Input
                                                        value={c.unidad ?? ''}
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'columnas',
                                                                d.columnas.map(
                                                                    (x, j) =>
                                                                        i === j
                                                                            ? {
                                                                                  ...x,
                                                                                  unidad: e
                                                                                      .target
                                                                                      .value,
                                                                              }
                                                                            : x,
                                                                ),
                                                            )
                                                        }
                                                        maxLength={10}
                                                        placeholder="kg, m, bar…"
                                                    />
                                                </Campo>
                                            </div>
                                            <label className="flex min-h-9 items-center gap-2 text-sm">
                                                <input
                                                    type="checkbox"
                                                    checked={
                                                        c.obligatorio ?? false
                                                    }
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'columnas',
                                                            d.columnas.map(
                                                                (x, j) =>
                                                                    i === j
                                                                        ? {
                                                                              ...x,
                                                                              obligatorio:
                                                                                  e
                                                                                      .target
                                                                                      .checked,
                                                                          }
                                                                        : x,
                                                            ),
                                                        )
                                                    }
                                                />
                                                Obligatoria al emitir
                                            </label>
                                        </div>
                                    ))}
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={d.columnas.length >= maximo}
                                        onClick={() =>
                                            form.setData('columnas', [
                                                ...d.columnas,
                                                {
                                                    titulo: '',
                                                    tipo: 'texto',
                                                    obligatorio: false,
                                                },
                                            ])
                                        }
                                    >
                                        <Plus className="size-4" />
                                        Agregar columna
                                    </Button>
                                    {d.columnas.length > maximo && (
                                        <p
                                            role="alert"
                                            className="text-destructive-strong text-sm"
                                        >
                                            Hay más columnas de las que permite
                                            esta orientación.
                                        </p>
                                    )}
                                </div>
                            </details>
                        )}
                        {tienePruebas && (
                            <details
                                open
                                className="border-border bg-card rounded-xl border p-4"
                            >
                                <summary className="cursor-pointer text-sm font-semibold">
                                    Pruebas y mediciones
                                </summary>
                                <div className="mt-4 grid gap-3">
                                    <p className="text-muted-foreground text-xs">
                                        Los valores y el cumplimiento los
                                        registra el técnico. Define límites solo
                                        cuando correspondan a la norma
                                        aplicable.
                                    </p>
                                    {d.checklist.map((p, i) => (
                                        <div
                                            key={i}
                                            className="border-border grid gap-2 rounded-lg border p-3"
                                        >
                                            <div className="flex items-end gap-2">
                                                <div className="min-w-0 flex-1">
                                                    <Campo
                                                        etiqueta={`Prueba ${i + 1}`}
                                                    >
                                                        <Input
                                                            value={p.texto}
                                                            onChange={(e) =>
                                                                form.setData(
                                                                    'checklist',
                                                                    d.checklist.map(
                                                                        (
                                                                            x,
                                                                            j,
                                                                        ) =>
                                                                            i ===
                                                                            j
                                                                                ? {
                                                                                      ...x,
                                                                                      texto: e
                                                                                          .target
                                                                                          .value,
                                                                                  }
                                                                                : x,
                                                                    ),
                                                                )
                                                            }
                                                            maxLength={120}
                                                            required
                                                        />
                                                    </Campo>
                                                </div>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`Quitar prueba ${i + 1}`}
                                                    onClick={() =>
                                                        form.setData(
                                                            'checklist',
                                                            d.checklist.filter(
                                                                (_, j) =>
                                                                    i !== j,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            </div>
                                            <div className="grid grid-cols-3 gap-2">
                                                <Campo etiqueta="Unidad">
                                                    <Input
                                                        value={p.unidad ?? ''}
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'checklist',
                                                                d.checklist.map(
                                                                    (x, j) =>
                                                                        i === j
                                                                            ? {
                                                                                  ...x,
                                                                                  unidad: e
                                                                                      .target
                                                                                      .value,
                                                                              }
                                                                            : x,
                                                                ),
                                                            )
                                                        }
                                                        maxLength={10}
                                                    />
                                                </Campo>
                                                {(
                                                    [
                                                        'minimo',
                                                        'maximo',
                                                    ] as const
                                                ).map((limite) => (
                                                    <Campo
                                                        key={limite}
                                                        etiqueta={
                                                            limite === 'minimo'
                                                                ? 'Mínimo'
                                                                : 'Máximo'
                                                        }
                                                    >
                                                        <Input
                                                            type="number"
                                                            step="any"
                                                            value={
                                                                p[limite] ?? ''
                                                            }
                                                            onChange={(e) =>
                                                                form.setData(
                                                                    'checklist',
                                                                    d.checklist.map(
                                                                        (
                                                                            x,
                                                                            j,
                                                                        ) =>
                                                                            i ===
                                                                            j
                                                                                ? {
                                                                                      ...x,
                                                                                      [limite]:
                                                                                          e
                                                                                              .target
                                                                                              .value ===
                                                                                          ''
                                                                                              ? null
                                                                                              : Number(
                                                                                                    e
                                                                                                        .target
                                                                                                        .value,
                                                                                                ),
                                                                                  }
                                                                                : x,
                                                                    ),
                                                                )
                                                            }
                                                        />
                                                    </Campo>
                                                ))}
                                            </div>
                                        </div>
                                    ))}
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={d.checklist.length >= 20}
                                        onClick={() =>
                                            form.setData('checklist', [
                                                ...d.checklist,
                                                { texto: '', unidad: '' },
                                            ])
                                        }
                                    >
                                        <Plus className="size-4" />
                                        Agregar prueba
                                    </Button>
                                </div>
                            </details>
                        )}
                        <details
                            open
                            className="border-border bg-card rounded-xl border p-4"
                        >
                            <summary className="cursor-pointer text-sm font-semibold">
                                Servicios y productos relacionados
                            </summary>
                            <div className="mt-4 grid gap-4">
                                <p className="text-muted-foreground text-xs">
                                    La venta habilita este certificado cuando
                                    incluye uno de estos servicios o productos.
                                    La asignación del tipo de servicio se aplica
                                    a todos sus servicios.
                                </p>
                                {conProductos &&
                                    bloquesRevisados.length > 0 && (
                                        <p
                                            role="alert"
                                            className="text-destructive-strong text-sm dark:text-red-300"
                                        >
                                            El certificado de un producto sale
                                            solo con la venta: quita los bloques
                                            de pruebas y de resultado.
                                        </p>
                                    )}
                                <Relaciones
                                    titulo="Tipos de servicio"
                                    opciones={tiposDeServicioDisponibles}
                                    seleccionados={d.tipo_de_servicio_ids}
                                    plantillaId={certificado?.id}
                                    onChange={(ids) =>
                                        form.setData(
                                            'tipo_de_servicio_ids',
                                            ids,
                                        )
                                    }
                                />
                                <Relaciones
                                    titulo="Productos"
                                    opciones={productosDisponibles}
                                    seleccionados={d.product_ids}
                                    plantillaId={certificado?.id}
                                    onChange={(ids) =>
                                        form.setData('product_ids', ids)
                                    }
                                />
                            </div>
                        </details>
                        <details
                            open
                            className="border-border bg-card rounded-xl border p-4"
                        >
                            <summary className="cursor-pointer text-sm font-semibold">
                                Firmantes y cargos
                            </summary>
                            <div className="mt-4">
                                <FirmasDelCertificado
                                    disponibles={firmantesDisponibles}
                                    elegidos={d.firmantes}
                                    maximo={maximoFirmantes}
                                    equipo={equipo}
                                    error={errores.firmantes}
                                    onChange={(firmas) =>
                                        form.setData('firmantes', firmas)
                                    }
                                />
                            </div>
                        </details>
                    </div>
                    <section
                        aria-label="Vista previa de la plantilla"
                        className="border-border bg-muted/40 min-w-0 rounded-xl border p-3 sm:p-5 xl:sticky xl:top-4"
                    >
                        <div className="mb-4 flex items-start gap-2">
                            <Lock className="text-muted-foreground mt-0.5 size-4 shrink-0" />
                            <div>
                                <p className="text-sm font-semibold">
                                    Marco común protegido
                                </p>
                                <p className="text-muted-foreground mt-1 text-xs">
                                    Marca, ASNEEX, número, QR, sello y
                                    posiciones de firmas. Los datos de esta
                                    vista son ejemplos.
                                </p>
                            </div>
                        </div>
                        <HojaDinamicaCertificado
                            datos={{
                                ...d,
                                firmantes: d.firmantes.flatMap((f) => {
                                    const persona = firmantesDisponibles.find(
                                        (p) => p.id === f.id,
                                    );
                                    return persona
                                        ? [
                                              {
                                                  nombre: persona.nombre,
                                                  cargo: f.cargo,
                                                  cip: persona.cip,
                                                  firma_url: persona.firma_url,
                                              },
                                          ]
                                        : [];
                                }),
                            }}
                        />
                        <p className="text-muted-foreground mt-4 text-xs">
                            Las tablas extensas continúan en más hojas al
                            generar el documento. Guardar cambios crea una nueva
                            versión; los certificados emitidos conservan la
                            suya.
                        </p>
                    </section>
                </div>
                <div className="flex justify-end">
                    <Button type="submit" disabled={form.processing}>
                        <Save className="size-4" />
                        {form.processing ? 'Guardando…' : 'Guardar plantilla'}
                    </Button>
                </div>
            </form>
        </GerenteLayout>
    );
}

function Relaciones({
    titulo,
    opciones,
    seleccionados,
    plantillaId,
    onChange,
}: {
    titulo: string;
    opciones: RelacionCertificado[];
    seleccionados: number[];
    plantillaId?: number;
    onChange: (ids: number[]) => void;
}) {
    const [busqueda, setBusqueda] = useState('');
    const visibles = opciones.filter((o) =>
        o.nombre.toLocaleLowerCase().includes(busqueda.toLocaleLowerCase()),
    );
    return (
        <fieldset className="grid min-w-0 gap-2">
            <legend className="mb-2 text-sm font-semibold">
                {titulo} · {seleccionados.length} seleccionados
            </legend>
            <Input
                aria-label={`Buscar ${titulo.toLowerCase()}`}
                value={busqueda}
                onChange={(e) => setBusqueda(e.target.value)}
                placeholder={`Buscar ${titulo.toLowerCase()}…`}
            />
            {opciones.length === 0 ? (
                <p className="text-muted-foreground text-xs">
                    No hay {titulo.toLowerCase()} activos para relacionar.
                </p>
            ) : (
                <div className="max-h-56 overflow-y-auto">
                    {visibles.length === 0 && (
                        <p className="text-muted-foreground py-2 text-xs">
                            No hay coincidencias.
                        </p>
                    )}
                    {visibles.map((o) => {
                        const ocupado =
                            o.certificate_type_id !== null &&
                            o.certificate_type_id !== plantillaId;
                        return (
                            <label
                                key={o.id}
                                className="border-border flex min-h-11 items-center gap-2 border-b py-2 text-sm"
                            >
                                <input
                                    type="checkbox"
                                    checked={seleccionados.includes(o.id)}
                                    disabled={ocupado}
                                    onChange={(e) =>
                                        onChange(
                                            e.target.checked
                                                ? [...seleccionados, o.id]
                                                : seleccionados.filter(
                                                      (id) => id !== o.id,
                                                  ),
                                        )
                                    }
                                />
                                <span className="min-w-0 break-words">
                                    {o.nombre}
                                    {ocupado && (
                                        <span className="text-muted-foreground block text-xs">
                                            Ya tiene otra plantilla asignada
                                        </span>
                                    )}
                                </span>
                            </label>
                        );
                    })}
                </div>
            )}
        </fieldset>
    );
}
