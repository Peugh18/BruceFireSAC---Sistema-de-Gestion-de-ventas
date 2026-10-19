import { Link, useForm, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowUp,
    Check,
    ChevronDown,
    ExternalLink,
    Lock,
    Plus,
    RefreshCw,
    X,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';

import FirmasDelCertificado from '@/components/certificados/firmas-del-certificado';
import type {
    FirmaDelCertificado,
    Firmante,
} from '@/components/certificados/firmas-del-certificado';
import HojaCertificado from '@/components/certificados/hoja-certificado';
import type {
    DisenoCertificado,
    ZonaCertificado,
} from '@/components/certificados/hoja-certificado';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import GerenteLayout from '@/layouts/gerente-layout';
import CertificadosRoutes from '@/routes/gerente/configuracion/certificados';
import type { Team } from '@/types';

type Columna = {
    clave?: string;
    titulo: string;
    tipo: 'texto' | 'numero';
    unidad?: string | null;
    obligatorio?: boolean;
};
type Prueba = {
    texto: string;
    unidad?: string | null;
    minimo?: number | null;
    maximo?: number | null;
};
type Orientacion = 'vertical' | 'horizontal';
type Formato = {
    id: number;
    nombre: string;
    titulo: string | null;
    subtitulo: string | null;
    norma: string | null;
    declaracion: string | null;
    responsabilidad: string | null;
    prefijo: string;
    vigencia_meses: number;
    orientacion: Orientacion;
    familia: string;
    diseno?: DisenoCertificado | null;
    columnas: Columna[];
    tiene_item: boolean;
    tiene_resultado: boolean;
    checklist: Prueba[];
    firmantes: FirmaDelCertificado[];
};
type Certificado = Formato & { formato_fijo: boolean; vista_previa: string };

const campo = 'min-h-10';

function Etiqueta({
    htmlFor,
    children,
}: {
    htmlFor: string;
    children: ReactNode;
}) {
    return (
        <label
            htmlFor={htmlFor}
            className="text-foreground mb-1.5 block text-xs font-semibold"
        >
            {children}
        </label>
    );
}

function MensajeError({ mensaje }: { mensaje?: string }) {
    return mensaje ? (
        <p role="alert" className="text-destructive-strong mt-1 text-xs">
            {mensaje}
        </p>
    ) : null;
}

/**
 * Crear o cambiar un certificado: a la izquierda sus partes; a la derecha la
 * hoja como va quedando. Hacer clic en una parte de la hoja abre su sección.
 */
export default function CertificadoEditor({
    certificado,
    bases,
    maximoColumnas,
    maximoFirmantes,
    firmantesDisponibles,
}: {
    certificado: Certificado | null;
    bases: Formato[];
    maximoColumnas: Record<Orientacion, number>;
    maximoFirmantes: number;
    firmantesDisponibles: Firmante[];
}) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const equipo = currentTeam?.slug ?? '';
    const inicial = certificado ?? bases[0];
    const formatoFijo = certificado?.formato_fijo ?? false;

    const form = useForm({
        base_id: certificado ? '' : String(bases[0]?.id ?? ''),
        nombre: certificado?.nombre ?? '',
        titulo: inicial?.titulo ?? 'CERTIFICADO DE OPERATIVIDAD',
        subtitulo: inicial?.subtitulo ?? '',
        norma: inicial?.norma ?? '',
        declaracion: inicial?.declaracion ?? '',
        responsabilidad: inicial?.responsabilidad ?? '',
        prefijo: certificado?.prefijo ?? '',
        vigencia_meses: inicial?.vigencia_meses ?? 12,
        orientacion: (inicial?.orientacion ?? 'vertical') as Orientacion,
        columnas: (inicial?.columnas ?? []) as Columna[],
        checklist: (inicial?.checklist ?? []) as Prueba[],
        firmantes: inicial?.firmantes ?? [],
        diseno: inicial?.diseno ?? ({} as DisenoCertificado),
    });
    const [formato, setFormato] = useState<Formato | undefined>(inicial);
    const [abierta, setAbierta] = useState<ZonaCertificado | null>(
        'encabezado',
    );
    const d = form.data;
    const e = form.errors as Record<string, string | undefined>;
    const maximo = maximoColumnas[d.orientacion];
    const pasaColumnas = !formatoFijo && d.columnas.length > maximo;
    const faltas = [
        !d.nombre.trim() && {
            texto: 'Falta el nombre',
            zona: 'encabezado' as const,
        },
        !d.prefijo && {
            texto: 'Falta el prefijo del número',
            zona: 'encabezado' as const,
        },
        pasaColumnas && {
            texto: 'La tabla no entra en la hoja',
            zona: 'tabla' as const,
        },
        d.firmantes.length === 0 && {
            texto: 'Falta quién firma',
            zona: 'firmas' as const,
        },
    ].filter(Boolean) as { texto: string; zona: ZonaCertificado }[];

    const elegirBase = (id: string) => {
        const base = bases.find((b) => String(b.id) === id);
        if (!base) return;
        setFormato(base);
        form.setData((actual) => ({
            ...actual,
            base_id: id,
            titulo: base.titulo ?? actual.titulo,
            subtitulo: base.subtitulo ?? '',
            norma: base.norma ?? '',
            declaracion: base.declaracion ?? '',
            responsabilidad: base.responsabilidad ?? '',
            vigencia_meses: base.vigencia_meses,
            orientacion: base.orientacion,
            columnas: base.columnas,
            checklist: base.checklist,
            firmantes: base.firmantes,
            diseno: base.diseno ?? {},
        }));
    };
    const cambiarColumna = (i: number, cambio: Partial<Columna>) =>
        form.setData(
            'columnas',
            d.columnas.map((c, j) => (j === i ? { ...c, ...cambio } : c)),
        );
    const subirColumna = (i: number) => {
        if (i === 0) return;
        const nuevas = d.columnas.slice();
        [nuevas[i - 1], nuevas[i]] = [nuevas[i], nuevas[i - 1]];
        form.setData('columnas', nuevas);
    };
    const cambiarPrueba = (i: number, texto: string) =>
        form.setData(
            'checklist',
            d.checklist.map((p, j) => (j === i ? { ...p, texto } : p)),
        );

    const guardar = (evento: FormEvent) => {
        evento.preventDefault();
        if (faltas.length > 0) {
            setAbierta(faltas[0].zona);
            return;
        }
        form.transform((datos) => {
            const { base_id, columnas, checklist, orientacion, ...textos } =
                datos;
            return {
                ...textos,
                ...(certificado ? {} : { base_id }),
                ...(formatoFijo ? {} : { columnas, checklist, orientacion }),
            };
        });
        if (certificado) {
            form.put(
                CertificadosRoutes.update.url({
                    current_team: equipo,
                    certificado: certificado.id,
                }),
            );
        } else {
            form.post(CertificadosRoutes.store.url({ current_team: equipo }));
        }
    };

    const secciones: {
        id: ZonaCertificado;
        nombre: string;
        resumen: string;
        estado: 'ok' | 'falta' | 'auto';
        oculta?: boolean;
    }[] = [
        {
            id: 'encabezado',
            nombre: 'Encabezado',
            resumen: !d.prefijo
                ? 'Falta el prefijo del número'
                : `${d.titulo} · ${d.prefijo}`,
            estado: d.prefijo && d.nombre.trim() ? 'ok' : 'falta',
        },
        {
            id: 'datos',
            nombre: 'Cliente y servicio',
            resumen: 'Se llenan solos desde la orden',
            estado: 'auto',
        },
        {
            id: 'tabla',
            nombre: 'Tabla de equipos',
            resumen: `${d.columnas.length} columnas · ${d.columnas.map((c) => c.titulo).join(', ')}`,
            estado: pasaColumnas || d.columnas.length === 0 ? 'falta' : 'ok',
            oculta: formatoFijo,
        },
        {
            id: 'pruebas',
            nombre: 'Pruebas realizadas',
            resumen: `${d.checklist.length} pruebas`,
            estado: d.checklist.length > 0 ? 'ok' : 'falta',
            oculta: formatoFijo,
        },
        {
            id: 'cierre',
            nombre: 'Textos finales',
            resumen: 'Texto de certificación y nota final',
            estado: 'ok',
        },
        {
            id: 'firmas',
            nombre: 'Firmas y sello',
            resumen:
                d.firmantes.length === 0
                    ? 'Falta quién firma'
                    : d.firmantes
                          .map(
                              (firma) =>
                                  firmantesDisponibles.find(
                                      (f) => f.id === firma.id,
                                  )?.nombre,
                          )
                          .filter(Boolean)
                          .join(', '),
            estado:
                d.firmantes.length > 0 &&
                d.firmantes.every(
                    (firma) =>
                        firmantesDisponibles.find((f) => f.id === firma.id)
                            ?.firma_url,
                )
                    ? 'ok'
                    : 'falta',
        },
    ];

    const contenido: Record<ZonaCertificado, ReactNode> = {
        encabezado: (
            <div className="grid gap-3">
                {!certificado && (
                    <div>
                        <Etiqueta htmlFor="base">Copiar formato de</Etiqueta>
                        <NativeSelect
                            id="base"
                            value={d.base_id}
                            onChange={(ev) => elegirBase(ev.target.value)}
                            className={`${campo} w-full`}
                        >
                            {bases.map((b) => (
                                <option key={b.id} value={b.id}>
                                    {b.nombre}
                                </option>
                            ))}
                        </NativeSelect>
                        <p className="text-muted-foreground mt-1 text-xs">
                            Trae su tabla, sus pruebas y sus firmantes.
                        </p>
                        <MensajeError mensaje={e.base_id} />
                    </div>
                )}
                <div>
                    <Etiqueta htmlFor="titulo">Título</Etiqueta>
                    <Input
                        id="titulo"
                        className={campo}
                        value={d.titulo}
                        maxLength={120}
                        onChange={(ev) =>
                            form.setData('titulo', ev.target.value)
                        }
                        aria-invalid={!!e.titulo}
                    />
                    <MensajeError mensaje={e.titulo} />
                </div>
                <div>
                    <Etiqueta htmlFor="subtitulo">Subtítulo en rojo</Etiqueta>
                    <Input
                        id="subtitulo"
                        className={campo}
                        value={d.subtitulo}
                        maxLength={160}
                        onChange={(ev) =>
                            form.setData('subtitulo', ev.target.value)
                        }
                    />
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <Etiqueta htmlFor="prefijo">
                            Prefijo del número
                        </Etiqueta>
                        <Input
                            id="prefijo"
                            className={`${campo} font-mono`}
                            value={d.prefijo}
                            maxLength={6}
                            placeholder="GCI"
                            onChange={(ev) =>
                                form.setData(
                                    'prefijo',
                                    ev.target.value
                                        .toUpperCase()
                                        .replace(/[^A-Z]/g, ''),
                                )
                            }
                            aria-invalid={!d.prefijo || !!e.prefijo}
                            aria-describedby="prefijo-ayuda"
                        />
                        <p
                            id="prefijo-ayuda"
                            className="text-muted-foreground mt-1 text-xs"
                        >
                            {d.prefijo
                                ? `Sale como ${d.prefijo}-0000001`
                                : 'De 2 a 6 letras, por ejemplo GCI.'}
                        </p>
                        <MensajeError mensaje={e.prefijo} />
                    </div>
                    <div>
                        <Etiqueta htmlFor="vigencia">Vigencia</Etiqueta>
                        <NativeSelect
                            id="vigencia"
                            value={String(d.vigencia_meses)}
                            onChange={(ev) =>
                                form.setData(
                                    'vigencia_meses',
                                    Number(ev.target.value),
                                )
                            }
                            className={`${campo} w-full`}
                        >
                            {[6, 12, 24, 36].map((m) => (
                                <option key={m} value={m}>
                                    {m} meses
                                </option>
                            ))}
                            <option value={0}>No vence</option>
                        </NativeSelect>
                    </div>
                </div>
                <div>
                    <Etiqueta htmlFor="norma">Norma</Etiqueta>
                    <Input
                        id="norma"
                        className={campo}
                        value={d.norma}
                        maxLength={500}
                        onChange={(ev) =>
                            form.setData('norma', ev.target.value)
                        }
                    />
                </div>
            </div>
        ),
        datos: (
            <div>
                <p className="text-muted-foreground mb-2 text-sm">
                    No se escriben aquí: salen de la orden de servicio al emitir
                    el certificado.
                </p>
                <ul className="text-muted-foreground grid grid-cols-2 gap-x-3 gap-y-1 text-sm">
                    {[
                        'Razón social',
                        'Fecha de emisión',
                        'RUC',
                        'Vencimiento',
                        'Nombre comercial',
                        'Vigencia',
                        'Dirección',
                        'Orden de servicio',
                    ].map((x) => (
                        <li key={x}>{x}</li>
                    ))}
                </ul>
            </div>
        ),
        tabla: (
            <div className="grid gap-2">
                <div className="flex items-center gap-2">
                    <div className="bg-muted h-1.5 flex-1 overflow-hidden rounded-full">
                        <div
                            className={`h-full ${pasaColumnas ? 'bg-amber-500' : 'bg-foreground'}`}
                            style={{
                                width: `${Math.min(100, (d.columnas.length / maximo) * 100)}%`,
                            }}
                        />
                    </div>
                    <span className="text-muted-foreground text-xs whitespace-nowrap">
                        {d.columnas.length} de {maximo} en hoja {d.orientacion}
                    </span>
                </div>
                {formato?.tiene_item && (
                    <p className="text-muted-foreground flex items-center gap-2 px-1 text-sm">
                        <Lock className="size-3.5" aria-hidden="true" /> Ítem ·
                        se numera solo
                    </p>
                )}
                {d.columnas.map((c, i) => (
                    <div
                        key={c.clave ?? `nueva-${i}`}
                        className="grid grid-cols-[minmax(0,1fr)_104px_56px_auto] items-center gap-1.5"
                    >
                        <Input
                            aria-label={`Nombre de la columna ${i + 1}`}
                            className="min-h-9"
                            value={c.titulo}
                            maxLength={40}
                            onChange={(ev) =>
                                cambiarColumna(i, { titulo: ev.target.value })
                            }
                            aria-invalid={!!e[`columnas.${i}.titulo`]}
                        />
                        <NativeSelect
                            aria-label={`Tipo de dato de ${c.titulo || 'la columna'}`}
                            value={c.tipo}
                            onChange={(ev) =>
                                cambiarColumna(i, {
                                    tipo: ev.target.value as Columna['tipo'],
                                })
                            }
                            className="min-h-9 w-full"
                        >
                            <option value="texto">Texto</option>
                            <option value="numero">Número</option>
                        </NativeSelect>
                        <Input
                            aria-label={`Unidad de ${c.titulo || 'la columna'}`}
                            className="min-h-9"
                            value={c.unidad ?? ''}
                            placeholder="—"
                            maxLength={10}
                            onChange={(ev) =>
                                cambiarColumna(i, { unidad: ev.target.value })
                            }
                        />
                        <span className="flex">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label={`Mover ${c.titulo} a la izquierda`}
                                disabled={i === 0}
                                onClick={() => subirColumna(i)}
                            >
                                <ArrowUp className="size-4" />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label={`Quitar ${c.titulo}`}
                                onClick={() =>
                                    form.setData(
                                        'columnas',
                                        d.columnas.filter((_, j) => j !== i),
                                    )
                                }
                            >
                                <X className="size-4" />
                            </Button>
                        </span>
                    </div>
                ))}
                {formato?.tiene_resultado && (
                    <p className="text-muted-foreground flex items-center gap-2 px-1 text-sm">
                        <Lock className="size-3.5" aria-hidden="true" />{' '}
                        Resultado · Operativo, con observación o no operativo
                    </p>
                )}
                <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    className="justify-self-start"
                    onClick={() =>
                        form.setData('columnas', [
                            ...d.columnas,
                            { titulo: '', tipo: 'texto' },
                        ])
                    }
                >
                    <Plus className="size-4" /> Agregar columna
                </Button>
                <MensajeError mensaje={e.columnas} />
            </div>
        ),
        pruebas: (
            <div className="grid gap-1.5">
                <p className="text-muted-foreground mb-1 text-xs">
                    El técnico marca C, NC o N/A en cada una.
                </p>
                {d.checklist.map((p, i) => (
                    <div
                        key={i}
                        className="grid grid-cols-[28px_minmax(0,1fr)_auto] items-center gap-1.5"
                    >
                        <span className="text-primary-strong text-center text-sm font-bold">
                            {String(i + 1).padStart(2, '0')}
                        </span>
                        <Input
                            aria-label={`Prueba ${i + 1}`}
                            className="min-h-9"
                            value={p.texto}
                            maxLength={120}
                            onChange={(ev) => cambiarPrueba(i, ev.target.value)}
                            aria-invalid={!!e[`checklist.${i}.texto`]}
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label={`Quitar prueba ${i + 1}`}
                            onClick={() =>
                                form.setData(
                                    'checklist',
                                    d.checklist.filter((_, j) => j !== i),
                                )
                            }
                        >
                            <X className="size-4" />
                        </Button>
                    </div>
                ))}
                <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    className="justify-self-start"
                    onClick={() =>
                        form.setData('checklist', [
                            ...d.checklist,
                            { texto: '' },
                        ])
                    }
                >
                    <Plus className="size-4" /> Agregar prueba
                </Button>
            </div>
        ),
        cierre: (
            <div className="grid gap-3">
                <div>
                    <Etiqueta htmlFor="declaracion">
                        Texto de certificación
                    </Etiqueta>
                    <Textarea
                        id="declaracion"
                        rows={3}
                        value={d.declaracion}
                        maxLength={2000}
                        onChange={(ev) =>
                            form.setData('declaracion', ev.target.value)
                        }
                    />
                </div>
                <div>
                    <Etiqueta htmlFor="responsabilidad">Nota final</Etiqueta>
                    <Textarea
                        id="responsabilidad"
                        rows={2}
                        value={d.responsabilidad}
                        maxLength={2000}
                        onChange={(ev) =>
                            form.setData('responsabilidad', ev.target.value)
                        }
                    />
                </div>
            </div>
        ),
        firmas: (
            <FirmasDelCertificado
                disponibles={firmantesDisponibles}
                elegidos={d.firmantes}
                maximo={maximoFirmantes}
                equipo={equipo}
                error={e.firmantes}
                onChange={(firmas) => form.setData('firmantes', firmas)}
            />
        ),
    };

    return (
        <GerenteLayout
            title={certificado ? 'Editar certificado' : 'Nuevo certificado'}
        >
            <form
                onSubmit={guardar}
                className="-mb-26 flex flex-col gap-3 lg:h-[calc(100dvh-76px-3rem)]"
            >
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="min-w-0 flex-1">
                        <label htmlFor="nombre" className="sr-only">
                            Nombre del certificado
                        </label>
                        <input
                            id="nombre"
                            value={d.nombre}
                            maxLength={120}
                            placeholder="Nombre del certificado, por ejemplo Operatividad de gabinetes"
                            onChange={(ev) =>
                                form.setData('nombre', ev.target.value)
                            }
                            aria-invalid={!d.nombre.trim() || !!e.nombre}
                            className="text-foreground placeholder:text-muted-foreground hover:border-border focus-visible:border-ring w-full max-w-xl rounded-lg border border-transparent bg-transparent px-2 py-1.5 text-lg font-semibold focus-visible:outline-none"
                        />
                        <MensajeError mensaje={e.nombre} />
                    </div>
                    <div className="flex items-center gap-2">
                        {faltas.length > 0 ? (
                            <button
                                type="button"
                                onClick={() => setAbierta(faltas[0].zona)}
                                className="inline-flex min-h-9 items-center gap-1.5 rounded-lg bg-amber-100 px-3 text-sm font-semibold text-amber-900 dark:bg-amber-950 dark:text-amber-200"
                            >
                                <AlertTriangle
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                {faltas[0].texto}
                            </button>
                        ) : (
                            <span className="text-success-strong inline-flex items-center gap-1.5 text-sm font-semibold">
                                <Check className="size-4" aria-hidden="true" />{' '}
                                Listo para guardar
                            </span>
                        )}
                        <Button asChild variant="ghost">
                            <Link
                                href={CertificadosRoutes.index.url({
                                    current_team: equipo,
                                })}
                            >
                                Cancelar
                            </Link>
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing
                                ? 'Guardando…'
                                : certificado
                                  ? 'Guardar cambios'
                                  : 'Crear certificado'}
                        </Button>
                    </div>
                </div>

                <div className="border-border bg-card flex min-h-0 flex-1 flex-col overflow-hidden rounded-2xl border lg:flex-row">
                    <aside
                        aria-label="Partes del certificado"
                        className="border-border min-h-0 shrink-0 overflow-y-auto border-b lg:w-[380px] lg:border-r lg:border-b-0"
                    >
                        {formatoFijo && (
                            <p className="bg-muted/60 text-muted-foreground border-border border-b px-4 py-2.5 text-xs">
                                Formato fijo: cambias sus textos y quién firma;
                                su tabla y sus pruebas son propias.
                            </p>
                        )}
                        {secciones
                            .filter((s) => !s.oculta)
                            .map((s) => {
                                const estaAbierta = abierta === s.id;
                                return (
                                    <div
                                        key={s.id}
                                        className="border-border border-b last:border-b-0"
                                    >
                                        <button
                                            type="button"
                                            aria-expanded={estaAbierta}
                                            onClick={() =>
                                                setAbierta(
                                                    estaAbierta ? null : s.id,
                                                )
                                            }
                                            className={`hover:bg-muted/60 grid w-full grid-cols-[24px_minmax(0,1fr)_16px] items-center gap-3 px-4 py-3 text-left ${estaAbierta ? 'bg-muted/40' : ''}`}
                                        >
                                            <span
                                                aria-label={
                                                    s.estado === 'ok'
                                                        ? 'Completo'
                                                        : s.estado === 'auto'
                                                          ? 'Automático'
                                                          : 'Falta completar'
                                                }
                                                className={`flex size-6 items-center justify-center rounded-full ${s.estado === 'ok' ? 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300' : s.estado === 'auto' ? 'bg-muted text-muted-foreground' : 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200'}`}
                                            >
                                                {s.estado === 'ok' ? (
                                                    <Check className="size-3.5" />
                                                ) : s.estado === 'auto' ? (
                                                    <RefreshCw className="size-3.5" />
                                                ) : (
                                                    <AlertTriangle className="size-3.5" />
                                                )}
                                            </span>
                                            <span className="min-w-0">
                                                <span className="text-foreground block text-sm font-semibold">
                                                    {s.nombre}
                                                </span>
                                                <span className="text-muted-foreground block truncate text-xs">
                                                    {s.resumen}
                                                </span>
                                            </span>
                                            <ChevronDown
                                                className={`text-muted-foreground size-4 transition-transform ${estaAbierta ? 'rotate-180' : ''}`}
                                                aria-hidden="true"
                                            />
                                        </button>
                                        {estaAbierta && (
                                            <div className="px-4 pt-1 pb-4">
                                                {contenido[s.id]}
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                    </aside>

                    <section
                        aria-label="Vista previa"
                        className="bg-muted/50 flex min-h-[520px] min-w-0 flex-1 flex-col"
                    >
                        <div className="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5">
                            {!formatoFijo ? (
                                <div
                                    role="radiogroup"
                                    aria-label="Orientación de la hoja"
                                    className="border-border bg-card flex gap-0.5 rounded-lg border p-0.5"
                                >
                                    {(['vertical', 'horizontal'] as const).map(
                                        (o) => (
                                            <button
                                                key={o}
                                                type="button"
                                                role="radio"
                                                aria-checked={
                                                    d.orientacion === o
                                                }
                                                onClick={() =>
                                                    form.setData(
                                                        'orientacion',
                                                        o,
                                                    )
                                                }
                                                className="aria-checked:bg-foreground aria-checked:text-background text-muted-foreground inline-flex min-h-8 items-center gap-1.5 rounded-md px-3 text-sm font-semibold capitalize"
                                            >
                                                <span
                                                    aria-hidden="true"
                                                    className={`rounded-[2px] border-[1.5px] border-current ${o === 'vertical' ? 'h-3 w-2.5' : 'h-2.5 w-3'}`}
                                                />
                                                {o}
                                            </button>
                                        ),
                                    )}
                                </div>
                            ) : (
                                <span className="text-muted-foreground text-sm">
                                    Vista previa editable
                                </span>
                            )}
                            {!formatoFijo && (
                                <p className="text-muted-foreground hidden text-xs md:block">
                                    Haz clic en una parte de la hoja para
                                    editarla
                                </p>
                            )}
                            {certificado && (
                                <a
                                    href={certificado.vista_previa}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="text-primary-strong inline-flex min-h-8 items-center gap-1.5 text-sm font-semibold hover:underline"
                                >
                                    <ExternalLink
                                        className="size-4"
                                        aria-hidden="true"
                                    />{' '}
                                    Ver PDF real
                                </a>
                            )}
                        </div>
                        <div className="bg-card flex flex-wrap items-center gap-3 border-b p-3 text-xs">
                            <span>
                                Escribe sobre el texto. Mueve el bloque elegido:
                            </span>
                            {(['x', 'y'] as const).map((eje) => (
                                <label
                                    key={eje}
                                    className="flex items-center gap-2"
                                >
                                    {eje === 'x' ? 'Horizontal' : 'Vertical'}{' '}
                                    (pt)
                                    <Input
                                        type="number"
                                        min={-24}
                                        max={24}
                                        step={1}
                                        className="min-h-10 w-20"
                                        disabled={!abierta}
                                        value={
                                            abierta
                                                ? (d.diseno[abierta]?.[eje] ??
                                                  0)
                                                : 0
                                        }
                                        onChange={(evento) => {
                                            if (abierta) {
                                                const posicion = d.diseno[
                                                    abierta
                                                ] ?? { x: 0, y: 0 };
                                                form.setData('diseno', {
                                                    ...d.diseno,
                                                    [abierta]: {
                                                        ...posicion,
                                                        [eje]: Math.max(
                                                            -24,
                                                            Math.min(
                                                                24,
                                                                Number(
                                                                    evento
                                                                        .target
                                                                        .value,
                                                                ),
                                                            ),
                                                        ),
                                                    },
                                                });
                                            }
                                        }}
                                    />
                                </label>
                            ))}
                            <Button
                                type="button"
                                variant="outline"
                                disabled={!abierta}
                                onClick={() =>
                                    abierta &&
                                    form.setData('diseno', {
                                        ...d.diseno,
                                        [abierta]: { x: 0, y: 0 },
                                    })
                                }
                            >
                                Restablecer bloque
                            </Button>
                            <MensajeError
                                mensaje={
                                    Object.entries(e).find(([clave]) =>
                                        clave.startsWith('diseno'),
                                    )?.[1]
                                }
                            />
                        </div>
                        <div className="relative mx-4 mb-4 min-h-0 flex-1">
                            <HojaCertificado
                                zonaActiva={abierta}
                                onElegirZona={setAbierta}
                                onCambiarTexto={(campo, valor) =>
                                    form.setData(campo, valor)
                                }
                                onCambiarColumna={
                                    formatoFijo
                                        ? undefined
                                        : (indice, titulo) =>
                                              cambiarColumna(indice, { titulo })
                                }
                                onCambiarPrueba={
                                    formatoFijo ? undefined : cambiarPrueba
                                }
                                onMover={(zona, posicion) =>
                                    form.setData('diseno', {
                                        ...d.diseno,
                                        [zona]: posicion,
                                    })
                                }
                                datos={{
                                    familia: formato?.familia,
                                    diseno: d.diseno,
                                    titulo: d.titulo,
                                    subtitulo: d.subtitulo,
                                    norma: d.norma,
                                    declaracion: d.declaracion,
                                    responsabilidad: d.responsabilidad,
                                    prefijo: d.prefijo,
                                    vigenciaMeses: d.vigencia_meses,
                                    orientacion:
                                        formato?.familia === 'diploma'
                                            ? 'horizontal'
                                            : d.orientacion,
                                    columnas: d.columnas,
                                    tieneResultado:
                                        formato?.tiene_resultado ?? true,
                                    pruebas: d.checklist.map((p) => p.texto),
                                    firmantes: d.firmantes.flatMap((firma) => {
                                        const persona =
                                            firmantesDisponibles.find(
                                                (f) => f.id === firma.id,
                                            );
                                        // En la hoja sale el cargo de este certificado.
                                        return persona
                                            ? [
                                                  {
                                                      ...persona,
                                                      cargo:
                                                          firma.cargo ||
                                                          persona.cargo,
                                                  },
                                              ]
                                            : [];
                                    }),
                                }}
                            />
                        </div>
                    </section>
                </div>
            </form>
        </GerenteLayout>
    );
}
