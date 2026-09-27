import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    Barcode,
    Check,
    Edit3,
    ScanBarcode,
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

export type SedeData = {
    id: number;
    nombre: string;
    tipo: string;
    ciudad: string | null;
};

export type ReceptionItemData = {
    id: number;
    product_id: number;
    producto: {
        id: number;
        codigo: string;
        nombre: string;
        unidad_medida: string;
        serializado: boolean;
    };
    cantidad: number;
    cantidad_conforme: number;
    observacion_item: string | null;
};

export type SerializedUnitData = {
    id: number;
    numero_serie: string;
    capacidad: string | null;
    serie_fabricante: string | null;
    marca: string | null;
    anio_fabricacion: number | null;
    estado: string;
    product_id: number;
};

export type ReceptionDetails = {
    id: number;
    proveedor: string;
    documento_referencia: string | null;
    fecha: string;
    sede: SedeData;
    usuario: string | null;
    observacion: string | null;
    items: ReceptionItemData[];
    unidades_serializadas: SerializedUnitData[];
};

export type Props = {
    reception: ReceptionDetails;
    current_year: number;
};

export default function RecepcionesShow({ reception, current_year }: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug =
        currentTeam?.slug ||
        (typeof window !== 'undefined'
            ? window.location.pathname.split('/')[1]
            : '');

    const [isEditing, setIsEditing] = useState(false);

    const { data, setData, put, processing, errors } = useForm({
        proveedor: reception.proveedor,
        documento_referencia: reception.documento_referencia || '',
        fecha: reception.fecha,
        observacion: reception.observacion || '',
        items: reception.items.map((it) => ({
            id: it.id,
            cantidad: it.cantidad,
            cantidad_conforme: it.cantidad_conforme,
            observacion_item: it.observacion_item || '',
            unidades_nuevas: [] as Array<{
                capacidad: string;
                serie_fabricante: string;
                marca: string;
                anio_fabricacion: number;
            }>,
        })),
    });

    const updateLineItem = (
        index: number,
        field: 'cantidad' | 'cantidad_conforme' | 'observacion_item',
        value: string | number,
    ) => {
        const nextItems = [...data.items];
        const prevConforme = reception.items[index].cantidad_conforme;
        const currentConforme =
            field === 'cantidad_conforme'
                ? Number(value)
                : nextItems[index].cantidad_conforme;
        const isSerializado = reception.items[index].producto.serializado;

        let unidadesNuevas = nextItems[index].unidades_nuevas;
        if (isSerializado && currentConforme > prevConforme) {
            const extraCount = currentConforme - prevConforme;
            unidadesNuevas = Array.from(
                { length: extraCount },
                (_, i) =>
                    unidadesNuevas[i] || {
                        capacidad: '',
                        serie_fabricante: '',
                        marca: '',
                        anio_fabricacion: current_year,
                    },
            );
        } else {
            unidadesNuevas = [];
        }

        nextItems[index] = {
            ...nextItems[index],
            [field]: value,
            unidades_nuevas: unidadesNuevas,
        };
        setData('items', nextItems);
    };

    const updateUnidadNueva = (
        itemIndex: number,
        unitIndex: number,
        field: 'capacidad' | 'serie_fabricante' | 'marca' | 'anio_fabricacion',
        val: string | number,
    ) => {
        const nextItems = [...data.items];
        const nextUnits = [...nextItems[itemIndex].unidades_nuevas];
        nextUnits[unitIndex] = {
            ...nextUnits[unitIndex],
            [field]: val,
        };
        nextItems[itemIndex] = {
            ...nextItems[itemIndex],
            unidades_nuevas: nextUnits,
        };
        setData('items', nextItems);
    };

    const handleSaveUpdate = (e: FormEvent) => {
        e.preventDefault();
        put(
            recepciones.update.url({
                current_team: teamSlug,
                reception: reception.id,
            }),
            {
                onSuccess: () => setIsEditing(false),
            },
        );
    };

    return (
        <AlmacenLayout
            title={`Recepción #${reception.id} - ${reception.proveedor}`}
        >
            <Head title={`Recepción #${reception.id} - Almacén`} />

            <div className="mx-auto flex max-w-5xl flex-col gap-6">
                {/* Top Action Bar */}
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Link
                        href={recepciones.index.url(teamSlug)}
                        className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-xs font-bold"
                    >
                        <ArrowLeft className="size-4" />
                        <span>Volver a recepciones</span>
                    </Link>

                    <div className="flex items-center gap-2">
                        {reception.unidades_serializadas.length > 0 && (
                            <a
                                href={`/${teamSlug}/almacen/recepciones/${reception.id}/stickers`}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="border-border bg-card text-foreground hover:bg-background inline-flex items-center gap-1.5 rounded-[9px] border px-3.5 py-2 text-xs font-bold"
                            >
                                <Barcode className="size-4" />
                                <span>Imprimir Stickers (PDF)</span>
                            </a>
                        )}

                        <Button
                            type="button"
                            variant={isEditing ? 'outline' : 'default'}
                            onClick={() => setIsEditing(!isEditing)}
                            className="h-9 gap-1.5 text-xs font-bold"
                        >
                            <Edit3 className="size-3.5" />
                            <span>
                                {isEditing
                                    ? 'Cancelar Edición'
                                    : 'Editar Recepción'}
                            </span>
                        </Button>
                    </div>
                </div>

                {/* Error Banner */}
                {Object.keys(errors).length > 0 && (
                    <div className="border-destructive/20 bg-destructive/10 text-primary flex items-start gap-3 rounded-[12px] border p-4 text-[13px]">
                        <AlertCircle className="mt-0.5 size-5 shrink-0" />
                        <div>
                            <b>Hubo errores al actualizar la recepción:</b>
                            <ul className="mt-1 list-inside list-disc text-xs">
                                {Object.entries(errors).map(([key, msg]) => (
                                    <li key={key}>{msg}</li>
                                ))}
                            </ul>
                        </div>
                    </div>
                )}

                {/* FORM O DETALLE */}
                {isEditing ? (
                    <form
                        onSubmit={handleSaveUpdate}
                        className="flex flex-col gap-6"
                    >
                        <Card className="border-border bg-card rounded-[16px] p-6 shadow-none">
                            <div className="border-border flex items-center justify-between border-b pb-4">
                                <h2 className="text-foreground text-[14px] font-bold">
                                    Modificar Datos de Recepción #{reception.id}
                                </h2>
                                <span className="rounded-full border border-amber-500/20 bg-amber-500/10 px-2.5 py-0.5 text-[10.5px] font-bold text-amber-600 dark:text-amber-400">
                                    Los cambios generarán movimientos
                                    compensatorios en Kardex
                                </span>
                            </div>

                            <div className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <div>
                                    <Label className="text-foreground/80 text-xs font-bold">
                                        Proveedor
                                    </Label>
                                    <Input
                                        value={data.proveedor}
                                        onChange={(e) =>
                                            setData('proveedor', e.target.value)
                                        }
                                        className="mt-1 h-9 text-xs"
                                        required
                                    />
                                </div>

                                <div>
                                    <Label className="text-foreground/80 text-xs font-bold">
                                        Doc. Referencia
                                    </Label>
                                    <Input
                                        value={data.documento_referencia}
                                        onChange={(e) =>
                                            setData(
                                                'documento_referencia',
                                                e.target.value,
                                            )
                                        }
                                        className="mt-1 h-9 text-xs"
                                    />
                                </div>

                                <div>
                                    <Label className="text-foreground/80 text-xs font-bold">
                                        Fecha
                                    </Label>
                                    <Input
                                        type="date"
                                        value={data.fecha}
                                        onChange={(e) =>
                                            setData('fecha', e.target.value)
                                        }
                                        className="mt-1 h-9 text-xs"
                                        required
                                    />
                                </div>

                                <div className="sm:col-span-2 lg:col-span-3">
                                    <Label className="text-foreground/80 text-xs font-bold">
                                        Observación General
                                    </Label>
                                    <Input
                                        value={data.observacion}
                                        onChange={(e) =>
                                            setData(
                                                'observacion',
                                                e.target.value,
                                            )
                                        }
                                        className="mt-1 h-9 text-xs"
                                    />
                                </div>
                            </div>
                        </Card>

                        <Card className="border-border bg-card rounded-[16px] p-6 shadow-none">
                            <h2 className="border-border text-foreground border-b pb-4 text-[14px] font-bold">
                                Ajuste de Cantidades y Conformidad por Línea
                            </h2>

                            <div className="divide-border mt-3 flex flex-col divide-y">
                                {data.items.map((it, idx) => {
                                    const orig = reception.items[idx];
                                    const diff =
                                        it.cantidad_conforme -
                                        orig.cantidad_conforme;
                                    const hasObs =
                                        it.cantidad_conforme < it.cantidad;

                                    return (
                                        <div
                                            key={it.id}
                                            className="flex flex-col gap-3 py-4"
                                        >
                                            <div className="flex items-center justify-between">
                                                <div className="text-foreground text-xs font-bold">
                                                    {orig.producto.nombre} (
                                                    {orig.producto.codigo})
                                                </div>
                                                {diff !== 0 && (
                                                    <span className="font-mono text-xs font-bold text-blue-600 dark:text-blue-400">
                                                        Ajuste:{' '}
                                                        {diff > 0
                                                            ? `+${diff}`
                                                            : diff}{' '}
                                                        unidades
                                                    </span>
                                                )}
                                            </div>

                                            <div className="grid gap-3 sm:grid-cols-3">
                                                <div>
                                                    <Label className="text-foreground/80 text-[11px] font-bold">
                                                        Cant. Total
                                                    </Label>
                                                    <Input
                                                        type="number"
                                                        min="1"
                                                        value={it.cantidad}
                                                        onChange={(e) =>
                                                            updateLineItem(
                                                                idx,
                                                                'cantidad',
                                                                Number(
                                                                    e.target
                                                                        .value,
                                                                ),
                                                            )
                                                        }
                                                        className="mt-1 h-8 text-xs"
                                                        required
                                                    />
                                                </div>

                                                <div>
                                                    <Label className="text-foreground/80 text-[11px] font-bold">
                                                        Cant. Conforme
                                                    </Label>
                                                    <Input
                                                        type="number"
                                                        min="0"
                                                        max={it.cantidad}
                                                        value={
                                                            it.cantidad_conforme
                                                        }
                                                        onChange={(e) =>
                                                            updateLineItem(
                                                                idx,
                                                                'cantidad_conforme',
                                                                Number(
                                                                    e.target
                                                                        .value,
                                                                ),
                                                            )
                                                        }
                                                        className="mt-1 h-8 text-xs"
                                                        required
                                                    />
                                                </div>

                                                <div>
                                                    <Label className="text-foreground/80 text-[11px] font-bold">
                                                        Motivo no conforme (si
                                                        aplica)
                                                    </Label>
                                                    <Input
                                                        value={
                                                            it.observacion_item
                                                        }
                                                        onChange={(e) =>
                                                            updateLineItem(
                                                                idx,
                                                                'observacion_item',
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="Motivo de observación..."
                                                        className="mt-1 h-8 text-xs"
                                                        required={hasObs}
                                                    />
                                                </div>
                                            </div>

                                            {/* Si aumentó conforme en serializado: capturar marca/año de las nuevas */}
                                            {diff > 0 &&
                                                orig.producto.serializado && (
                                                    <div className="border-border bg-muted/40 mt-2 rounded-lg border p-3">
                                                        <div className="text-foreground mb-2 text-[11px] font-bold">
                                                            Datos de las {diff}{' '}
                                                            nuevas unidades
                                                            conformes a
                                                            incorporar:
                                                        </div>
                                                        <div className="grid gap-2">
                                                            {it.unidades_nuevas.map(
                                                                (u, uIdx) => (
                                                                    <div
                                                                        key={
                                                                            uIdx
                                                                        }
                                                                        className="flex items-center gap-2"
                                                                    >
                                                                        <Input
                                                                            placeholder="Capacidad"
                                                                            value={
                                                                                u.capacidad
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) =>
                                                                                updateUnidadNueva(
                                                                                    idx,
                                                                                    uIdx,
                                                                                    'capacidad',
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                                )
                                                                            }
                                                                            className="h-7 text-xs"
                                                                            required
                                                                        />
                                                                        <Input
                                                                            placeholder="N° de serie"
                                                                            value={
                                                                                u.serie_fabricante
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) =>
                                                                                updateUnidadNueva(
                                                                                    idx,
                                                                                    uIdx,
                                                                                    'serie_fabricante',
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                                )
                                                                            }
                                                                            className="h-7 text-xs"
                                                                        />
                                                                        <Input
                                                                            placeholder="Marca"
                                                                            value={
                                                                                u.marca
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) =>
                                                                                updateUnidadNueva(
                                                                                    idx,
                                                                                    uIdx,
                                                                                    'marca',
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                                )
                                                                            }
                                                                            className="h-7 text-xs"
                                                                            required
                                                                        />
                                                                        <Input
                                                                            type="number"
                                                                            min="1990"
                                                                            max={
                                                                                current_year
                                                                            }
                                                                            placeholder="Año"
                                                                            value={
                                                                                u.anio_fabricacion
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) =>
                                                                                updateUnidadNueva(
                                                                                    idx,
                                                                                    uIdx,
                                                                                    'anio_fabricacion',
                                                                                    Number(
                                                                                        e
                                                                                            .target
                                                                                            .value,
                                                                                    ),
                                                                                )
                                                                            }
                                                                            className="h-7 w-[80px] text-xs"
                                                                            required
                                                                        />
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
                        </Card>

                        <div className="flex justify-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsEditing(false)}
                                className="h-9 text-xs font-bold"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={processing}
                                className="bg-primary hover:bg-primary/90 h-9 gap-1.5 text-xs font-bold text-white"
                            >
                                <Check className="size-4" />
                                <span>Guardar Corrección</span>
                            </Button>
                        </div>
                    </form>
                ) : (
                    <>
                        {/* Vista de solo lectura */}
                        <Card className="border-border bg-card rounded-[16px] p-6 shadow-none">
                            <div className="border-border flex items-center justify-between border-b pb-4">
                                <div className="flex items-center gap-2">
                                    <Truck className="text-primary size-4" />
                                    <h2 className="text-foreground text-[14px] font-bold">
                                        Información de Recepción
                                    </h2>
                                </div>
                                <span className="text-muted-foreground font-mono text-xs">
                                    Documento #{reception.id}
                                </span>
                            </div>

                            <div className="mt-4 grid gap-4 text-xs sm:grid-cols-2 lg:grid-cols-4">
                                <div>
                                    <span className="text-muted-foreground">
                                        Proveedor:
                                    </span>
                                    <div className="text-foreground mt-0.5 text-sm font-bold">
                                        {reception.proveedor}
                                    </div>
                                </div>

                                <div>
                                    <span className="text-muted-foreground">
                                        Doc. Referencia:
                                    </span>
                                    <div className="text-foreground mt-0.5 font-mono font-bold">
                                        {reception.documento_referencia || '—'}
                                    </div>
                                </div>

                                <div>
                                    <span className="text-muted-foreground">
                                        Fecha de ingreso:
                                    </span>
                                    <div className="text-foreground mt-0.5 font-mono font-bold">
                                        {reception.fecha}
                                    </div>
                                </div>

                                <div>
                                    <span className="text-muted-foreground">
                                        Sede Almacén:
                                    </span>
                                    <div className="text-foreground mt-0.5 font-bold">
                                        {reception.sede.nombre}
                                    </div>
                                </div>

                                {reception.observacion && (
                                    <div className="bg-muted/40 text-foreground/80 rounded-lg p-3 sm:col-span-2 lg:col-span-4">
                                        <span className="text-foreground font-bold">
                                            Observación:{' '}
                                        </span>
                                        {reception.observacion}
                                    </div>
                                )}
                            </div>
                        </Card>

                        {/* Líneas Recibidas */}
                        <Card className="border-border bg-card rounded-[16px] p-6 shadow-none">
                            <h2 className="border-border text-foreground border-b pb-4 text-[14px] font-bold">
                                Líneas del Documento
                            </h2>

                            <div className="mt-3 overflow-x-auto">
                                <table className="w-full text-left text-[12.5px]">
                                    <thead>
                                        <tr className="border-border text-muted-foreground border-b text-[11px] font-bold tracking-wider uppercase">
                                            <th className="py-2 pr-3">Ítem</th>
                                            <th className="px-3 py-2 text-center">
                                                Tipo
                                            </th>
                                            <th className="px-3 py-2 text-center">
                                                Recibido
                                            </th>
                                            <th className="px-3 py-2 text-center">
                                                Conforme
                                            </th>
                                            <th className="px-3 py-2 text-center">
                                                Observado
                                            </th>
                                            <th className="py-2 pl-4">
                                                Motivo No Conforme
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-border divide-y">
                                        {reception.items.map((it) => {
                                            const noConforme =
                                                it.cantidad -
                                                it.cantidad_conforme;

                                            return (
                                                <tr key={it.id}>
                                                    <td className="py-3 pr-3">
                                                        <div className="text-foreground font-bold">
                                                            {it.producto.nombre}
                                                        </div>
                                                        <div className="text-muted-foreground font-mono text-[11px]">
                                                            {it.producto.codigo}
                                                        </div>
                                                    </td>
                                                    <td className="px-3 py-3 text-center">
                                                        {it.producto
                                                            .serializado ? (
                                                            <span className="inline-flex items-center gap-1 rounded-[6px] border border-emerald-500/20 bg-emerald-500/10 px-2 py-0.5 text-[10.5px] font-bold text-emerald-600 dark:text-emerald-400">
                                                                <ScanBarcode className="size-3" />
                                                                Serializado
                                                            </span>
                                                        ) : (
                                                            <span className="border-border bg-muted text-muted-foreground inline-flex items-center gap-1 rounded-[6px] border px-2 py-0.5 text-[10.5px] font-bold">
                                                                No serializado
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="text-foreground px-3 py-3 text-center font-mono font-bold">
                                                        {it.cantidad}
                                                    </td>
                                                    <td className="px-3 py-3 text-center font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                                        {it.cantidad_conforme}
                                                    </td>
                                                    <td className="px-3 py-3 text-center font-mono font-bold">
                                                        {noConforme > 0 ? (
                                                            <span className="text-primary">
                                                                {noConforme}
                                                            </span>
                                                        ) : (
                                                            <span className="text-muted-foreground">
                                                                0
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="text-muted-foreground py-3 pl-4 text-xs">
                                                        {it.observacion_item ||
                                                            '—'}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        </Card>

                        {/* Unidades Serializadas Físicas Incorporadas */}
                        {reception.unidades_serializadas.length > 0 && (
                            <Card className="border-border bg-card rounded-[16px] p-6 shadow-none">
                                <div className="border-border flex items-center justify-between border-b pb-4">
                                    <div className="flex items-center gap-2">
                                        <ScanBarcode className="size-4 text-emerald-600 dark:text-emerald-400" />
                                        <h2 className="text-foreground text-[14px] font-bold">
                                            Unidades Físicas Serializadas
                                            Generadas (
                                            {
                                                reception.unidades_serializadas
                                                    .length
                                            }
                                            )
                                        </h2>
                                    </div>
                                    <span className="text-muted-foreground text-xs">
                                        Correlativo único BF-EQ-XXXXXX
                                    </span>
                                </div>

                                <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                    {reception.unidades_serializadas.map(
                                        (unit) => (
                                            <div
                                                key={unit.id}
                                                className="border-border bg-muted/40 flex items-center justify-between rounded-xl border p-3 text-xs"
                                            >
                                                <div>
                                                    <div className="text-foreground flex items-center gap-1.5 font-mono text-sm font-bold">
                                                        <Barcode className="text-primary size-4" />
                                                        {unit.numero_serie}
                                                    </div>
                                                    <div className="text-muted-foreground mt-0.5 text-[11px]">
                                                        Cap.:{' '}
                                                        <b className="text-foreground/80">
                                                            {unit.capacidad ||
                                                                'N/A'}
                                                        </b>{' '}
                                                        • Serie:{' '}
                                                        <b className="text-foreground/80">
                                                            {unit.serie_fabricante ||
                                                                'N/A'}
                                                        </b>
                                                    </div>
                                                    <div className="text-muted-foreground text-[11px]">
                                                        Marca:{' '}
                                                        <b className="text-foreground/80">
                                                            {unit.marca ||
                                                                'N/A'}
                                                        </b>{' '}
                                                        • Año:{' '}
                                                        <b className="text-foreground/80">
                                                            {unit.anio_fabricacion ||
                                                                'N/A'}
                                                        </b>
                                                    </div>
                                                </div>

                                                <span
                                                    className={[
                                                        'rounded-full px-2 py-0.5 font-mono text-[10.5px] font-bold',
                                                        unit.estado ===
                                                        'disponible'
                                                            ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20'
                                                            : 'bg-muted text-muted-foreground',
                                                    ].join(' ')}
                                                >
                                                    {unit.estado}
                                                </span>
                                            </div>
                                        ),
                                    )}
                                </div>
                            </Card>
                        )}
                    </>
                )}
            </div>
        </AlmacenLayout>
    );
}
