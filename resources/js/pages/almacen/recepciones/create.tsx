import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    AlertTriangle,
    ArrowLeft,
    Check,
    CheckCircle2,
    Package,
    Plus,
    ScanBarcode,
    Trash2,
    Truck,
} from 'lucide-react';
import { FormEvent, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AlmacenLayout from '@/layouts/almacen-layout';
import recepciones from '@/routes/almacen/recepciones';
import type { Team } from '@/types';

export type SedeOption = {
    id: number;
    nombre: string;
    ciudad: string | null;
};

export type ProductOption = {
    id: number;
    codigo: string;
    nombre: string;
    unidad_medida: string;
    serializado: boolean;
};

export type UnidadSerializadaForm = {
    marca: string;
    anio_fabricacion: number;
};

export type ReceptionItemForm = {
    product_id: number;
    cantidad: number;
    cantidad_conforme: number;
    observacion_item: string;
    unidades: UnidadSerializadaForm[];
};

export type Props = {
    sedes: SedeOption[];
    products: ProductOption[];
    today: string;
    current_year: number;
};

export default function RecepcionesCreate({
    sedes,
    products,
    today,
    current_year,
}: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug =
        currentTeam?.slug ||
        (typeof window !== 'undefined'
            ? window.location.pathname.split('/')[1]
            : '');

    const { data, setData, post, processing, errors } = useForm({
        proveedor: '',
        documento_referencia: '',
        fecha: today,
        sede_almacen_id: sedes.length > 0 ? sedes[0].id : '',
        observacion: '',
        items: [] as ReceptionItemForm[],
    });

    const addLine = () => {
        if (products.length === 0) return;
        const firstProd = products[0];
        setData('items', [
            ...data.items,
            {
                product_id: firstProd.id,
                cantidad: 1,
                cantidad_conforme: 1,
                observacion_item: '',
                unidades: firstProd.serializado
                    ? [{ marca: '', anio_fabricacion: current_year }]
                    : [],
            },
        ]);
    };

    const removeLine = (index: number) => {
        const next = [...data.items];
        next.splice(index, 1);
        setData('items', next);
    };

    const updateLineProduct = (index: number, productId: number) => {
        const prod = products.find((p) => p.id === productId);
        const next = [...data.items];
        const conforme = next[index].cantidad_conforme;

        next[index] = {
            ...next[index],
            product_id: productId,
            unidades: prod?.serializado
                ? Array.from({ length: conforme }, () => ({
                      marca: '',
                      anio_fabricacion: current_year,
                  }))
                : [],
        };
        setData('items', next);
    };

    const updateLineCantidad = (index: number, cantidad: number) => {
        const next = [...data.items];
        const val = Math.max(1, cantidad);
        const prevConforme = next[index].cantidad_conforme;
        const newConforme = Math.min(prevConforme, val);
        const prod = products.find((p) => p.id === next[index].product_id);

        let unidades = next[index].unidades;
        if (prod?.serializado) {
            unidades = Array.from(
                { length: newConforme },
                (_, i) =>
                    unidades[i] || {
                        marca: '',
                        anio_fabricacion: current_year,
                    },
            );
        }

        next[index] = {
            ...next[index],
            cantidad: val,
            cantidad_conforme: newConforme,
            unidades,
        };
        setData('items', next);
    };

    const updateLineConforme = (index: number, conforme: number) => {
        const next = [...data.items];
        const val = Math.max(0, Math.min(conforme, next[index].cantidad));
        const prod = products.find((p) => p.id === next[index].product_id);

        let unidades = next[index].unidades;
        if (prod?.serializado) {
            unidades = Array.from(
                { length: val },
                (_, i) =>
                    unidades[i] || {
                        marca: '',
                        anio_fabricacion: current_year,
                    },
            );
        }

        next[index] = {
            ...next[index],
            cantidad_conforme: val,
            unidades,
        };
        setData('items', next);
    };

    const updateLineUnidad = (
        itemIndex: number,
        unitIndex: number,
        field: keyof UnidadSerializadaForm,
        val: string | number,
    ) => {
        const next = [...data.items];
        const unidades = [...next[itemIndex].unidades];
        unidades[unitIndex] = {
            ...unidades[unitIndex],
            [field]: val,
        };
        next[itemIndex] = {
            ...next[itemIndex],
            unidades,
        };
        setData('items', next);
    };

    const updateLineObs = (index: number, obs: string) => {
        const next = [...data.items];
        next[index] = {
            ...next[index],
            observacion_item: obs,
        };
        setData('items', next);
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post(recepciones.store.url(teamSlug));
    };

    return (
        <AlmacenLayout title="Nueva Recepción de Proveedor">
            <Head title="Nueva Recepción - Almacén" />

            <form
                onSubmit={handleSubmit}
                className="mx-auto flex max-w-5xl flex-col gap-6"
            >
                {/* Top Action Bar */}
                <div className="flex items-center justify-between">
                    <Link
                        href={recepciones.index.url(teamSlug)}
                        className="inline-flex items-center gap-1.5 text-xs font-bold text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="size-4" />
                        <span>Volver a recepciones</span>
                    </Link>

                    <Button
                        type="submit"
                        disabled={processing || data.items.length === 0}
                        className="h-10 gap-2 bg-primary px-6 text-xs font-bold text-white hover:bg-primary/90"
                    >
                        <Check className="size-4" />
                        <span>Confirmar Recepción</span>
                    </Button>
                </div>

                {/* Error Banner General */}
                {Object.keys(errors).length > 0 && (
                    <div className="flex items-start gap-3 rounded-[12px] border border-destructive/20 bg-destructive/10 p-4 text-[13px] text-primary">
                        <AlertCircle className="mt-0.5 size-5 shrink-0" />
                        <div>
                            <b>
                                Por favor revisa los errores en el formulario:
                            </b>
                            <ul className="mt-1 list-inside list-disc text-xs">
                                {Object.entries(errors).map(([key, msg]) => (
                                    <li key={key}>{msg}</li>
                                ))}
                            </ul>
                        </div>
                    </div>
                )}

                {/* Card 1: Datos de Cabecera */}
                <Card className="rounded-[16px] border-border bg-card p-6 shadow-none">
                    <div className="flex items-center gap-2 border-b border-border pb-4">
                        <Truck className="size-4 text-primary" />
                        <h2 className="text-[14px] font-bold text-foreground">
                            Datos del Comprobante y Proveedor
                        </h2>
                    </div>

                    <div className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="sm:col-span-2">
                            <Label className="text-xs font-bold text-foreground/80">
                                Proveedor / Razón Social{' '}
                                <span className="text-primary">*</span>
                            </Label>
                            <Input
                                value={data.proveedor}
                                onChange={(e) =>
                                    setData('proveedor', e.target.value)
                                }
                                placeholder="Ej. EXTINTORES INDUSTRIALES S.A.C."
                                className="mt-1 h-9 text-xs"
                                required
                            />
                            {errors.proveedor && (
                                <p className="mt-1 text-[11px] text-primary">
                                    {errors.proveedor}
                                </p>
                            )}
                        </div>

                        <div>
                            <Label className="text-xs font-bold text-foreground/80">
                                Doc. Referencia (Guía / Factura)
                            </Label>
                            <Input
                                value={data.documento_referencia}
                                onChange={(e) =>
                                    setData(
                                        'documento_referencia',
                                        e.target.value,
                                    )
                                }
                                placeholder="Ej. GR-001-004523"
                                className="mt-1 h-9 text-xs"
                            />
                        </div>

                        <div>
                            <Label className="text-xs font-bold text-foreground/80">
                                Fecha de Recepción{' '}
                                <span className="text-primary">*</span>
                            </Label>
                            <Input
                                type="date"
                                max={today}
                                value={data.fecha}
                                onChange={(e) =>
                                    setData('fecha', e.target.value)
                                }
                                className="mt-1 h-9 text-xs"
                                required
                            />
                            {errors.fecha && (
                                <p className="mt-1 text-[11px] text-primary">
                                    {errors.fecha}
                                </p>
                            )}
                        </div>

                        <div className="sm:col-span-2">
                            <Label className="text-xs font-bold text-foreground/80">
                                Sede de Almacén Destino{' '}
                                <span className="text-primary">*</span>
                            </Label>
                            <select
                                value={data.sede_almacen_id}
                                onChange={(e) =>
                                    setData(
                                        'sede_almacen_id',
                                        Number(e.target.value),
                                    )
                                }
                                className="mt-1 h-9 w-full rounded-md border border-border bg-card px-3 text-xs text-foreground focus:border-primary focus:outline-none"
                                required
                            >
                                {sedes.map((sede) => (
                                    <option key={sede.id} value={sede.id}>
                                        {sede.nombre} (
                                        {sede.ciudad || 'Principal'})
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="sm:col-span-2">
                            <Label className="text-xs font-bold text-foreground/80">
                                Observación General de Recepción
                            </Label>
                            <Input
                                value={data.observacion}
                                onChange={(e) =>
                                    setData('observacion', e.target.value)
                                }
                                placeholder="Notas del transporte, precintos, chofer, etc."
                                className="mt-1 h-9 text-xs"
                            />
                        </div>
                    </div>
                </Card>

                {/* Card 2: Líneas de Mercadería Recibida */}
                <Card className="rounded-[16px] border-border bg-card p-6 shadow-none">
                    <div className="flex items-center justify-between border-b border-border pb-4">
                        <div className="flex items-center gap-2">
                            <Package className="size-4 text-primary" />
                            <h2 className="text-[14px] font-bold text-foreground">
                                Ítems y Unidades Recibidas
                            </h2>
                        </div>

                        <Button
                            type="button"
                            onClick={addLine}
                            size="sm"
                            className="h-8 gap-1.5 bg-foreground text-background hover:bg-foreground/90"
                        >
                            <Plus className="size-3.5" />
                            <span>Agregar Ítem</span>
                        </Button>
                    </div>

                    {data.items.length === 0 ? (
                        <div className="flex min-h-[160px] flex-col items-center justify-center text-center">
                            <Package className="size-8 text-muted-foreground" />
                            <p className="mt-2 text-xs font-medium text-muted-foreground">
                                Presiona "Agregar Ítem" para añadir los
                                productos recibidos en este lote.
                            </p>
                        </div>
                    ) : (
                        <div className="mt-3 flex flex-col divide-y divide-border">
                            {data.items.map((item, index) => {
                                const prod = products.find(
                                    (p) => p.id === item.product_id,
                                );
                                const isSerializado =
                                    prod?.serializado ?? false;
                                const hasNoConforme =
                                    item.cantidad_conforme < item.cantidad;

                                return (
                                    <div
                                        key={index}
                                        className="flex flex-col gap-4 py-5"
                                    >
                                        <div className="flex flex-wrap items-start justify-between gap-3">
                                            <div className="flex items-center gap-2">
                                                <span className="flex size-6 items-center justify-center rounded-full bg-card text-[11px] font-bold text-white">
                                                    {index + 1}
                                                </span>
                                                <span className="text-xs font-bold text-foreground">
                                                    Línea de Producto
                                                </span>
                                            </div>

                                            <button
                                                type="button"
                                                onClick={() =>
                                                    removeLine(index)
                                                }
                                                className="p-1 text-muted-foreground hover:text-primary"
                                                title="Eliminar línea"
                                            >
                                                <Trash2 className="size-4" />
                                            </button>
                                        </div>

                                        <div className="grid gap-3 sm:grid-cols-4 lg:grid-cols-6">
                                            <div className="sm:col-span-2 lg:col-span-3">
                                                <Label className="text-[11px] font-bold text-foreground/80">
                                                    Producto{' '}
                                                    <span className="text-primary">
                                                        *
                                                    </span>
                                                </Label>
                                                <select
                                                    value={item.product_id}
                                                    onChange={(e) =>
                                                        updateLineProduct(
                                                            index,
                                                            Number(
                                                                e.target.value,
                                                            ),
                                                        )
                                                    }
                                                    className="mt-1 h-9 w-full rounded-md border border-border bg-card px-3 text-xs text-foreground focus:border-primary focus:outline-none"
                                                >
                                                    {products.map((p) => (
                                                        <option
                                                            key={p.id}
                                                            value={p.id}
                                                        >
                                                            {p.codigo} -{' '}
                                                            {p.nombre}{' '}
                                                            {p.serializado
                                                                ? '(Serializado)'
                                                                : ''}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>

                                            <div>
                                                <Label className="text-[11px] font-bold text-foreground/80">
                                                    Cant. Recibida{' '}
                                                    <span className="text-primary">
                                                        *
                                                    </span>
                                                </Label>
                                                <Input
                                                    type="number"
                                                    min="1"
                                                    value={item.cantidad}
                                                    onChange={(e) =>
                                                        updateLineCantidad(
                                                            index,
                                                            Number(
                                                                e.target.value,
                                                            ),
                                                        )
                                                    }
                                                    className="mt-1 h-9 text-xs"
                                                    required
                                                />
                                            </div>

                                            <div>
                                                <Label className="text-[11px] font-bold text-foreground/80">
                                                    Cant. Conforme{' '}
                                                    <span className="text-primary">
                                                        *
                                                    </span>
                                                </Label>
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    max={item.cantidad}
                                                    value={
                                                        item.cantidad_conforme
                                                    }
                                                    onChange={(e) =>
                                                        updateLineConforme(
                                                            index,
                                                            Number(
                                                                e.target.value,
                                                            ),
                                                        )
                                                    }
                                                    className="mt-1 h-9 text-xs"
                                                    required
                                                />
                                            </div>

                                            <div className="flex flex-col justify-end">
                                                <div className="flex h-9 items-center">
                                                    {hasNoConforme ? (
                                                        <span className="inline-flex items-center gap-1 rounded-full border border-destructive/20 bg-destructive/10 px-2 py-0.5 text-[10px] font-bold text-primary">
                                                            <AlertTriangle className="size-3" />
                                                            <span>
                                                                {item.cantidad -
                                                                    item.cantidad_conforme}{' '}
                                                                Observado
                                                            </span>
                                                        </span>
                                                    ) : (
                                                        <span className="inline-flex items-center gap-1 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2 py-0.5 text-[10px] font-bold text-emerald-600 dark:text-emerald-400">
                                                            <CheckCircle2 className="size-3" />
                                                            <span>
                                                                100% Conforme
                                                            </span>
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        </div>

                                        {/* Motivo de no conformidad (obligatorio si cantidad_conforme < cantidad) */}
                                        {hasNoConforme && (
                                            <div className="rounded-[10px] border border-amber-500/20 bg-amber-500/10 p-3">
                                                <Label className="text-[11px] font-bold text-amber-600 dark:text-amber-400">
                                                    Motivo de no conformidad
                                                    (obligatorio para sustentar
                                                    reclamo al proveedor):
                                                </Label>
                                                <Input
                                                    value={
                                                        item.observacion_item
                                                    }
                                                    onChange={(e) =>
                                                        updateLineObs(
                                                            index,
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="Ej. 2 unidades llegaron abolladas o sin precinto de fábrica."
                                                    className="mt-1 h-8 border-border bg-card text-xs"
                                                    required
                                                />
                                            </div>
                                        )}

                                        {/* Sub-formulario para unidades serializadas */}
                                        {isSerializado &&
                                            item.cantidad_conforme > 0 && (
                                                <div className="mt-1 rounded-[12px] border border-border bg-muted/40 p-4">
                                                    <div className="mb-3 flex items-center gap-1.5 text-xs font-bold text-foreground">
                                                        <ScanBarcode className="size-4 text-emerald-600 dark:text-emerald-400" />
                                                        <span>
                                                            Captura de Unidades
                                                            Físicas (
                                                            {
                                                                item.cantidad_conforme
                                                            }{' '}
                                                            conformes)
                                                        </span>
                                                        <span className="text-[11px] font-normal text-muted-foreground">
                                                            — Los números de
                                                            serie correlativos
                                                            BF-EQ-XXXXXX se
                                                            generan
                                                            automáticamente.
                                                        </span>
                                                    </div>

                                                    <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                                        {item.unidades.map(
                                                            (unit, uIdx) => (
                                                                <div
                                                                    key={uIdx}
                                                                    className="flex items-center gap-2 rounded-lg border border-border bg-card p-2 text-xs"
                                                                >
                                                                    <span className="flex size-5 shrink-0 items-center justify-center rounded-full bg-muted text-[10px] font-bold text-muted-foreground">
                                                                        {uIdx +
                                                                            1}
                                                                    </span>

                                                                    <div className="flex-1">
                                                                        <Input
                                                                            placeholder="Marca (ej. BADGER)"
                                                                            value={
                                                                                unit.marca
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) =>
                                                                                updateLineUnidad(
                                                                                    index,
                                                                                    uIdx,
                                                                                    'marca',
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                                )
                                                                            }
                                                                            className="h-7 text-[11px]"
                                                                            required
                                                                        />
                                                                    </div>

                                                                    <div className="w-[85px]">
                                                                        <Input
                                                                            type="number"
                                                                            min="1990"
                                                                            max={
                                                                                current_year
                                                                            }
                                                                            placeholder="Año"
                                                                            value={
                                                                                unit.anio_fabricacion
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) =>
                                                                                updateLineUnidad(
                                                                                    index,
                                                                                    uIdx,
                                                                                    'anio_fabricacion',
                                                                                    Number(
                                                                                        e
                                                                                            .target
                                                                                            .value,
                                                                                    ),
                                                                                )
                                                                            }
                                                                            className="h-7 text-[11px]"
                                                                            required
                                                                        />
                                                                    </div>
                                                                </div>
                                                            ),
                                                        )}
                                                    </div>
                                                </div>
                                            )}
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </Card>
            </form>
        </AlmacenLayout>
    );
}
