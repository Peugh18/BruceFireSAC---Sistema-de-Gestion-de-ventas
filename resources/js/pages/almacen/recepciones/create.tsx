import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    AlertTriangle,
    ArrowLeft,
    Check,
    CheckCircle2,
    Copy,
    Package,
    Plus,
    ScanBarcode,
    Trash2,
    Truck,
} from 'lucide-react';
import { FormEvent, KeyboardEvent, useState } from 'react';

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
    codigo_barras?: string | null;
    nombre: string;
    unidad_medida: string;
    serializado: boolean;
    controla_lote: boolean;
    unidad_compra: string | null;
    factor_compra: number;
};

export type UnidadSerializadaForm = {
    capacidad: string;
    serie_fabricante: string;
    marca: string;
    anio_fabricacion: number;
};

export type ReceptionItemForm = {
    product_id: number;
    cantidad: number;
    cantidad_conforme: number;
    costo_unitario: string;
    observacion_item: string;
    lote: string;
    fecha_vencimiento: string;
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

    const newUnit = (): UnidadSerializadaForm => ({
        capacidad: '',
        serie_fabricante: '',
        marca: '',
        anio_fabricacion: current_year,
    });

    const copyFirstUnitToAll = (itemIndex: number) => {
        const next = [...data.items];
        const [first, ...rest] = next[itemIndex].unidades;
        if (!first) return;
        next[itemIndex] = {
            ...next[itemIndex],
            unidades: [
                first,
                ...rest.map((unit) => ({
                    ...unit,
                    capacidad: first.capacidad,
                    marca: first.marca,
                    anio_fabricacion: first.anio_fabricacion,
                })),
            ],
        };
        setData('items', next);
    };

    const addLine = () => {
        if (products.length === 0) return;
        const firstProd = products[0];
        setData('items', [
            ...data.items,
            {
                product_id: firstProd.id,
                cantidad: 1,
                cantidad_conforme: 1,
                costo_unitario: '',
                observacion_item: '',
                lote: '',
                fecha_vencimiento: '',
                unidades: firstProd.serializado ? [newUnit()] : [],
            },
        ]);
    };

    const [escaneo, setEscaneo] = useState('');
    const [avisoEscaneo, setAvisoEscaneo] = useState<string | null>(null);

    /**
     * Lector de barras: cada lectura suma una unidad del producto (o agrega
     * su línea). Sirve con el código del fabricante (EPP, repuestos) o el
     * código interno.
     */
    const alEscanear = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key !== 'Enter') return;
        event.preventDefault();

        const codigo = escaneo.trim();
        if (!codigo) return;

        const prod = products.find(
            (p) =>
                p.codigo_barras === codigo ||
                p.codigo.toUpperCase() === codigo.toUpperCase(),
        );
        setEscaneo('');

        if (!prod) {
            setAvisoEscaneo(
                `El código ${codigo} no está registrado en ningún producto: pídele al Gerente que lo agregue en Productos.`,
            );
            return;
        }

        setAvisoEscaneo(null);
        const index = data.items.findIndex((i) => i.product_id === prod.id);

        if (index === -1) {
            setData('items', [
                ...data.items,
                {
                    product_id: prod.id,
                    cantidad: 1,
                    cantidad_conforme: 1,
                    costo_unitario: '',
                    observacion_item: '',
                    lote: '',
                    fecha_vencimiento: '',
                    unidades: prod.serializado ? [newUnit()] : [],
                },
            ]);
            return;
        }

        const linea = data.items[index];
        const next = [...data.items];
        next[index] = {
            ...linea,
            cantidad: linea.cantidad + 1,
            cantidad_conforme: linea.cantidad_conforme + 1,
            unidades: prod.serializado
                ? [...linea.unidades, newUnit()]
                : linea.unidades,
        };
        setData('items', next);
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
                ? Array.from({ length: conforme }, () => newUnit())
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
                (_, i) => unidades[i] || newUnit(),
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
                (_, i) => unidades[i] || newUnit(),
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

    const updateLineLote = (
        index: number,
        campo: 'lote' | 'fecha_vencimiento',
        valor: string,
    ) => {
        const next = [...data.items];
        next[index] = { ...next[index], [campo]: valor };
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
                        className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-xs font-bold"
                    >
                        <ArrowLeft className="size-4" />
                        <span>Volver a recepciones</span>
                    </Link>

                    <Button
                        aria-label="Confirmar Recepción"
                        type="submit"
                        disabled={processing || data.items.length === 0}
                        className="bg-primary hover:bg-primary/90 h-10 gap-2 px-6 text-xs font-bold text-white"
                    >
                        <Check className="size-4" />
                        <span>Confirmar Recepción</span>
                    </Button>
                </div>

                {/* Error Banner General */}
                {Object.keys(errors).length > 0 && (
                    <div className="border-destructive/20 bg-destructive/10 text-primary-strong flex items-start gap-3 rounded-[12px] border p-4 text-[13px]">
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
                <Card className="border-border bg-card rounded-[16px] p-6 shadow-none">
                    <div className="border-border flex items-center gap-2 border-b pb-4">
                        <Truck className="text-primary-strong size-4" />
                        <h2 className="text-foreground text-[14px] font-bold">
                            Datos del Comprobante y Proveedor
                        </h2>
                    </div>

                    <div className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="sm:col-span-2">
                            <Label
                                htmlFor="recepcion-proveedor-razon-social"
                                className="text-foreground/80 text-xs font-bold"
                            >
                                Proveedor / Razón Social{' '}
                                <span className="text-primary-strong">*</span>
                            </Label>
                            <Input
                                id="recepcion-proveedor-razon-social"
                                value={data.proveedor}
                                onChange={(e) =>
                                    setData('proveedor', e.target.value)
                                }
                                placeholder="Ej. EXTINTORES INDUSTRIALES S.A.C."
                                className="mt-1 h-9 text-xs"
                                required
                            />
                            {errors.proveedor && (
                                <p className="text-primary-strong mt-1 text-[11px]">
                                    {errors.proveedor}
                                </p>
                            )}
                        </div>

                        <div>
                            <Label
                                htmlFor="almacen-recepciones-create-doc-referencia-guia-factura"
                                className="text-foreground/80 text-xs font-bold"
                            >
                                Doc. Referencia (Guía / Factura)
                            </Label>
                            <Input
                                id="almacen-recepciones-create-doc-referencia-guia-factura"
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
                            <Label
                                htmlFor="recepcion-fecha-de-recepcion"
                                className="text-foreground/80 text-xs font-bold"
                            >
                                Fecha de Recepción{' '}
                                <span className="text-primary-strong">*</span>
                            </Label>
                            <Input
                                id="recepcion-fecha-de-recepcion"
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
                                <p className="text-primary-strong mt-1 text-[11px]">
                                    {errors.fecha}
                                </p>
                            )}
                        </div>

                        <div className="sm:col-span-2">
                            <Label
                                htmlFor="recepcion-sede-de-almacen-destino"
                                className="text-foreground/80 text-xs font-bold"
                            >
                                Sede de Almacén Destino{' '}
                                <span className="text-primary-strong">*</span>
                            </Label>
                            <select
                                id="recepcion-sede-de-almacen-destino"
                                value={data.sede_almacen_id}
                                onChange={(e) =>
                                    setData(
                                        'sede_almacen_id',
                                        Number(e.target.value),
                                    )
                                }
                                className="border-border bg-card text-foreground focus:border-primary mt-1 h-9 w-full rounded-md border px-3 text-xs focus:outline-none"
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
                            <Label
                                htmlFor="almacen-recepciones-create-observacion-general-de-recepcion"
                                className="text-foreground/80 text-xs font-bold"
                            >
                                Observación General de Recepción
                            </Label>
                            <Input
                                id="almacen-recepciones-create-observacion-general-de-recepcion"
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
                <Card className="border-border bg-card rounded-[16px] p-6 shadow-none">
                    <div className="border-border flex items-center justify-between border-b pb-4">
                        <div className="flex items-center gap-2">
                            <Package className="text-primary-strong size-4" />
                            <h2 className="text-foreground text-[14px] font-bold">
                                Ítems y Unidades Recibidas
                            </h2>
                        </div>

                        <div className="flex flex-1 justify-end px-3">
                            <input
                                aria-label="Escanear código de barras"
                                value={escaneo}
                                onChange={(e) => setEscaneo(e.target.value)}
                                onKeyDown={alEscanear}
                                placeholder="Escanear código de barras..."
                                className="border-border bg-muted/40 h-8 w-full max-w-[260px] rounded-[8px] border px-3 font-mono text-[12px] outline-none"
                            />
                        </div>
                        <Button
                            aria-label="Agregar Ítem"
                            type="button"
                            onClick={addLine}
                            size="sm"
                            className="bg-foreground text-background hover:bg-foreground/90 h-8 gap-1.5"
                        >
                            <Plus className="size-3.5" />
                            <span>Agregar Ítem</span>
                        </Button>
                        {avisoEscaneo ? (
                            <p
                                className="text-destructive-strong text-[11px] font-semibold"
                                role="alert"
                            >
                                {avisoEscaneo}
                            </p>
                        ) : null}
                        {Object.values(errors)[0] ? (
                            <p
                                className="text-destructive-strong text-[11px] font-semibold"
                                role="alert"
                            >
                                {Object.values(errors)[0]}
                            </p>
                        ) : null}
                    </div>

                    {data.items.length === 0 ? (
                        <div className="flex min-h-[160px] flex-col items-center justify-center text-center">
                            <Package className="text-muted-foreground size-8" />
                            <p className="text-muted-foreground mt-2 text-xs font-medium">
                                Presiona "Agregar Ítem" para añadir los
                                productos recibidos en este lote.
                            </p>
                        </div>
                    ) : (
                        <div className="divide-border mt-3 flex flex-col divide-y">
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
                                                <span className="bg-foreground text-background flex size-6 items-center justify-center rounded-full text-[11px] font-bold shadow-xs">
                                                    {index + 1}
                                                </span>
                                                <span className="text-foreground text-xs font-bold">
                                                    Línea de Producto
                                                </span>
                                            </div>

                                            <button
                                                aria-label="Eliminar línea"
                                                type="button"
                                                onClick={() =>
                                                    removeLine(index)
                                                }
                                                className="text-muted-foreground hover:text-primary-strong p-1"
                                                title="Eliminar línea"
                                            >
                                                <Trash2 className="size-4" />
                                            </button>
                                        </div>

                                        <div className="grid gap-3 sm:grid-cols-4 lg:grid-cols-6">
                                            <div className="sm:col-span-2 lg:col-span-3">
                                                <Label
                                                    htmlFor={`recepcion-producto-${index}`}
                                                    className="text-foreground/80 text-[11px] font-bold"
                                                >
                                                    Producto{' '}
                                                    <span className="text-primary-strong">
                                                        *
                                                    </span>
                                                </Label>
                                                <select
                                                    aria-label="Producto"
                                                    id={`recepcion-producto-${index}`}
                                                    value={item.product_id}
                                                    onChange={(e) =>
                                                        updateLineProduct(
                                                            index,
                                                            Number(
                                                                e.target.value,
                                                            ),
                                                        )
                                                    }
                                                    className="border-border bg-card text-foreground focus:border-primary mt-1 h-9 w-full rounded-md border px-3 text-xs focus:outline-none"
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
                                                <Label
                                                    htmlFor={`recepcion-cant-recibida-${index}`}
                                                    className="text-foreground/80 text-[11px] font-bold"
                                                >
                                                    Cant. Recibida{' '}
                                                    <span className="text-primary-strong">
                                                        *
                                                    </span>
                                                </Label>
                                                <Input
                                                    id={`recepcion-cant-recibida-${index}`}
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
                                                <Label
                                                    htmlFor={`recepcion-cant-conforme-${index}`}
                                                    className="text-foreground/80 text-[11px] font-bold"
                                                >
                                                    Cant. Conforme{' '}
                                                    <span className="text-primary-strong">
                                                        *
                                                    </span>
                                                </Label>
                                                <Input
                                                    id={`recepcion-cant-conforme-${index}`}
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
                                                        <span className="border-destructive/20 bg-destructive/10 text-primary-strong inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-bold">
                                                            <AlertTriangle className="size-3" />
                                                            <span>
                                                                {item.cantidad -
                                                                    item.cantidad_conforme}{' '}
                                                                Observado
                                                            </span>
                                                        </span>
                                                    ) : (
                                                        <span className="text-success-strong inline-flex items-center gap-1 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2 py-0.5 text-[10px] font-bold">
                                                            <CheckCircle2 className="size-3" />
                                                            <span>
                                                                100% Conforme
                                                            </span>
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        </div>

                                        {/* EPP: se cuenta por caja y entra con su lote y vencimiento */}
                                        {prod &&
                                            !isSerializado &&
                                            (prod.controla_lote ||
                                                (prod.unidad_compra &&
                                                    prod.factor_compra >
                                                        1)) && (
                                                <div className="border-border bg-muted/40 grid gap-3 rounded-[10px] border p-3 sm:grid-cols-3">
                                                    {prod.unidad_compra &&
                                                        prod.factor_compra >
                                                            1 && (
                                                            <div>
                                                                <Label className="text-foreground/80 text-[11px] font-bold">
                                                                    {
                                                                        prod.unidad_compra
                                                                    }{' '}
                                                                    recibidas (×{' '}
                                                                    {
                                                                        prod.factor_compra
                                                                    }
                                                                    )
                                                                </Label>
                                                                <Input
                                                                    aria-label="Cantidad de cajas"
                                                                    type="number"
                                                                    min="1"
                                                                    placeholder="0"
                                                                    onChange={(
                                                                        e,
                                                                    ) => {
                                                                        const cajas =
                                                                            Number(
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                            );
                                                                        if (
                                                                            cajas >
                                                                            0
                                                                        ) {
                                                                            updateLineCantidad(
                                                                                index,
                                                                                cajas *
                                                                                    prod.factor_compra,
                                                                            );
                                                                        }
                                                                    }}
                                                                    className="mt-1 h-8 text-xs"
                                                                />
                                                                <p className="text-muted-foreground mt-0.5 text-[10px]">
                                                                    ={' '}
                                                                    {
                                                                        item.cantidad
                                                                    }{' '}
                                                                    {
                                                                        prod.unidad_medida
                                                                    }{' '}
                                                                    al stock
                                                                </p>
                                                            </div>
                                                        )}
                                                    {prod.controla_lote && (
                                                        <>
                                                            <div>
                                                                <Label
                                                                    htmlFor={`recepcion-lote-${index}`}
                                                                    className="text-foreground/80 text-[11px] font-bold"
                                                                >
                                                                    Lote{' '}
                                                                    <span className="text-primary-strong">
                                                                        *
                                                                    </span>
                                                                </Label>
                                                                <Input
                                                                    id={`recepcion-lote-${index}`}
                                                                    value={
                                                                        item.lote
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        updateLineLote(
                                                                            index,
                                                                            'lote',
                                                                            e.target.value.toUpperCase(),
                                                                        )
                                                                    }
                                                                    placeholder="Como figura en la caja"
                                                                    className="mt-1 h-8 text-xs"
                                                                    required
                                                                />
                                                            </div>
                                                            <div>
                                                                <Label
                                                                    htmlFor={`recepcion-vence-${index}`}
                                                                    className="text-foreground/80 text-[11px] font-bold"
                                                                >
                                                                    Vence{' '}
                                                                    <span className="text-primary-strong">
                                                                        *
                                                                    </span>
                                                                </Label>
                                                                <Input
                                                                    id={`recepcion-vence-${index}`}
                                                                    type="date"
                                                                    min={today}
                                                                    value={
                                                                        item.fecha_vencimiento
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        updateLineLote(
                                                                            index,
                                                                            'fecha_vencimiento',
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                    className="mt-1 h-8 text-xs"
                                                                    required
                                                                />
                                                            </div>
                                                        </>
                                                    )}
                                                </div>
                                            )}

                                        <div>
                                            <Label
                                                htmlFor={`recepcion-costo-${index}`}
                                                className="text-[11px] font-bold"
                                            >
                                                Costo de compra por unidad, sin
                                                IGV (S/):
                                            </Label>
                                            <Input
                                                id={`recepcion-costo-${index}`}
                                                type="number"
                                                min="0"
                                                step="0.0001"
                                                value={item.costo_unitario}
                                                onChange={(e) => {
                                                    const next = [
                                                        ...data.items,
                                                    ];
                                                    next[index] = {
                                                        ...next[index],
                                                        costo_unitario:
                                                            e.target.value,
                                                    };
                                                    setData('items', next);
                                                }}
                                                className="border-border bg-card mt-1 h-8 w-40 text-xs"
                                            />
                                            <p className="text-muted-foreground mt-1 text-[11px]">
                                                Si lo dejas vacío, el producto
                                                no se valoriza en el reporte de
                                                inventario.
                                            </p>
                                            {errors[
                                                `items.${index}.costo_unitario` as keyof typeof errors
                                            ] && (
                                                <p className="text-destructive-strong mt-1 text-xs">
                                                    {
                                                        errors[
                                                            `items.${index}.costo_unitario` as keyof typeof errors
                                                        ]
                                                    }
                                                </p>
                                            )}
                                        </div>

                                        {/* Motivo de no conformidad (obligatorio si cantidad_conforme < cantidad) */}
                                        {hasNoConforme && (
                                            <div className="rounded-[10px] border border-amber-500/20 bg-amber-500/10 p-3">
                                                <Label
                                                    htmlFor={`recepcion-motivo-no-conformidad-${index}`}
                                                    className="text-warning-strong text-[11px] font-bold"
                                                >
                                                    Motivo de no conformidad
                                                    (obligatorio para sustentar
                                                    reclamo al proveedor):
                                                </Label>
                                                <Input
                                                    id={`recepcion-motivo-no-conformidad-${index}`}
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
                                                    className="border-border bg-card mt-1 h-8 text-xs"
                                                    required
                                                />
                                            </div>
                                        )}

                                        {/* Sub-formulario para unidades serializadas */}
                                        {isSerializado &&
                                            item.cantidad_conforme > 0 && (
                                                <div className="border-border bg-muted/40 mt-1 rounded-[12px] border p-4">
                                                    <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                                                        <div className="text-foreground flex flex-wrap items-center gap-1.5 text-xs font-bold">
                                                            <ScanBarcode className="text-success-strong size-4" />
                                                            <span>
                                                                Captura de
                                                                Unidades Físicas
                                                                (
                                                                {
                                                                    item.cantidad_conforme
                                                                }{' '}
                                                                conformes)
                                                            </span>
                                                            <span className="text-muted-foreground text-[11px] font-normal">
                                                                — El código
                                                                interno
                                                                BF-EQ-XXXXXX se
                                                                genera
                                                                automáticamente.
                                                            </span>
                                                        </div>
                                                        {item.unidades.length >
                                                            1 && (
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                size="sm"
                                                                onClick={() =>
                                                                    copyFirstUnitToAll(
                                                                        index,
                                                                    )
                                                                }
                                                                className="h-7 text-[11px]"
                                                            >
                                                                <Copy className="size-3.5" />
                                                                Copiar fila 1 a
                                                                todas
                                                            </Button>
                                                        )}
                                                    </div>

                                                    <div className="border-border bg-card overflow-x-auto rounded-lg border">
                                                        <table className="w-full min-w-[560px] text-xs">
                                                            <thead>
                                                                <tr className="bg-primary text-primary-foreground text-left text-[10px] font-bold tracking-wide uppercase">
                                                                    <th className="w-12 px-2 py-2 text-center">
                                                                        Ítem
                                                                    </th>
                                                                    <th className="px-2 py-2">
                                                                        Capacidad
                                                                    </th>
                                                                    <th className="px-2 py-2">
                                                                        N° de
                                                                        serie
                                                                    </th>
                                                                    <th className="px-2 py-2">
                                                                        Marca
                                                                    </th>
                                                                    <th className="w-24 px-2 py-2">
                                                                        Año fab.
                                                                    </th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                {item.unidades.map(
                                                                    (
                                                                        unit,
                                                                        uIdx,
                                                                    ) => (
                                                                        <tr
                                                                            key={
                                                                                uIdx
                                                                            }
                                                                            className="border-border border-t"
                                                                        >
                                                                            <td className="text-muted-foreground px-2 py-1.5 text-center font-bold">
                                                                                {uIdx +
                                                                                    1}
                                                                            </td>
                                                                            <td className="px-2 py-1.5">
                                                                                <Input
                                                                                    aria-label="Capacidad"
                                                                                    placeholder="Ej. 6 kg"
                                                                                    value={
                                                                                        unit.capacidad
                                                                                    }
                                                                                    onChange={(
                                                                                        e,
                                                                                    ) =>
                                                                                        updateLineUnidad(
                                                                                            index,
                                                                                            uIdx,
                                                                                            'capacidad',
                                                                                            e
                                                                                                .target
                                                                                                .value,
                                                                                        )
                                                                                    }
                                                                                    className="h-7 text-[11px]"
                                                                                    required
                                                                                />
                                                                            </td>
                                                                            <td className="px-2 py-1.5">
                                                                                <Input
                                                                                    aria-label="Serie del fabricante"
                                                                                    placeholder="Serie del fabricante"
                                                                                    value={
                                                                                        unit.serie_fabricante
                                                                                    }
                                                                                    onChange={(
                                                                                        e,
                                                                                    ) =>
                                                                                        updateLineUnidad(
                                                                                            index,
                                                                                            uIdx,
                                                                                            'serie_fabricante',
                                                                                            e
                                                                                                .target
                                                                                                .value,
                                                                                        )
                                                                                    }
                                                                                    className="h-7 text-[11px]"
                                                                                />
                                                                            </td>
                                                                            <td className="px-2 py-1.5">
                                                                                <Input
                                                                                    aria-label="Marca"
                                                                                    placeholder="Ej. BADGER"
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
                                                                            </td>
                                                                            <td className="px-2 py-1.5">
                                                                                <Input
                                                                                    aria-label="Año de fabricación"
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
                                                                            </td>
                                                                        </tr>
                                                                    ),
                                                                )}
                                                            </tbody>
                                                        </table>
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
