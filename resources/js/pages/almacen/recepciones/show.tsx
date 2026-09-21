import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    AlertTriangle,
    ArrowLeft,
    Barcode,
    Building2,
    Calendar,
    Check,
    CheckCircle2,
    Edit3,
    FileText,
    Package,
    Plus,
    ScanBarcode,
    Truck,
    User,
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
            unidades_nuevas: [] as Array<{ marca: string; anio_fabricacion: number }>,
        })),
    });

    const updateLineItem = (
        index: number,
        field: 'cantidad' | 'cantidad_conforme' | 'observacion_item',
        value: string | number
    ) => {
        const nextItems = [...data.items];
        const prevConforme = reception.items[index].cantidad_conforme;
        const currentConforme = field === 'cantidad_conforme' ? Number(value) : nextItems[index].cantidad_conforme;
        const isSerializado = reception.items[index].producto.serializado;

        let unidadesNuevas = nextItems[index].unidades_nuevas;
        if (isSerializado && currentConforme > prevConforme) {
            const extraCount = currentConforme - prevConforme;
            unidadesNuevas = Array.from({ length: extraCount }, (_, i) => unidadesNuevas[i] || {
                marca: '',
                anio_fabricacion: current_year,
            });
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
        field: 'marca' | 'anio_fabricacion',
        val: string | number
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
        put(recepciones.update.url({ current_team: teamSlug, reception: reception.id }), {
            onSuccess: () => setIsEditing(false),
        });
    };

    return (
        <AlmacenLayout title={`Recepción #${reception.id} - ${reception.proveedor}`}>
            <Head title={`Recepción #${reception.id} - Almacén`} />

            <div className="flex flex-col gap-6 max-w-5xl mx-auto">
                {/* Top Action Bar */}
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Link
                        href={recepciones.index.url(teamSlug)}
                        className="inline-flex items-center gap-1.5 text-xs font-bold text-[#6B6965] hover:text-[#201F1D]"
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
                                className="inline-flex items-center gap-1.5 rounded-[9px] border border-[#E4E1DC] bg-white px-3.5 py-2 text-xs font-bold text-[#201F1D] hover:bg-[#F3F1ED]"
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
                            <span>{isEditing ? 'Cancelar Edición' : 'Editar Recepción'}</span>
                        </Button>
                    </div>
                </div>

                {/* Error Banner */}
                {Object.keys(errors).length > 0 && (
                    <div className="flex items-start gap-3 rounded-[12px] border border-[#F5C6C5] bg-[#FBEAE9] p-4 text-[13px] text-[#E31E24]">
                        <AlertCircle className="size-5 shrink-0 mt-0.5" />
                        <div>
                            <b>Hubo errores al actualizar la recepción:</b>
                            <ul className="mt-1 list-disc list-inside text-xs">
                                {Object.entries(errors).map(([key, msg]) => (
                                    <li key={key}>{msg}</li>
                                ))}
                            </ul>
                        </div>
                    </div>
                )}

                {/* FORM O DETALLE */}
                {isEditing ? (
                    <form onSubmit={handleSaveUpdate} className="flex flex-col gap-6">
                        <Card className="rounded-[16px] border-[#E7E4DE] bg-white p-6 shadow-none">
                            <div className="flex items-center justify-between border-b border-[#F1EFEC] pb-4">
                                <h2 className="text-[14px] font-bold text-[#201F1D]">
                                    Modificar Datos de Recepción #{reception.id}
                                </h2>
                                <span className="rounded-full bg-[#FEF6E9] border border-[#FCE1B6] px-2.5 py-0.5 text-[10.5px] font-bold text-[#B4690E]">
                                    Los cambios generarán movimientos compensatorios en Kardex
                                </span>
                            </div>

                            <div className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <div>
                                    <Label className="text-xs font-bold text-[#4A4742]">Proveedor</Label>
                                    <Input
                                        value={data.proveedor}
                                        onChange={(e) => setData('proveedor', e.target.value)}
                                        className="mt-1 h-9 text-xs"
                                        required
                                    />
                                </div>

                                <div>
                                    <Label className="text-xs font-bold text-[#4A4742]">Doc. Referencia</Label>
                                    <Input
                                        value={data.documento_referencia}
                                        onChange={(e) => setData('documento_referencia', e.target.value)}
                                        className="mt-1 h-9 text-xs"
                                    />
                                </div>

                                <div>
                                    <Label className="text-xs font-bold text-[#4A4742]">Fecha</Label>
                                    <Input
                                        type="date"
                                        value={data.fecha}
                                        onChange={(e) => setData('fecha', e.target.value)}
                                        className="mt-1 h-9 text-xs"
                                        required
                                    />
                                </div>

                                <div className="sm:col-span-2 lg:col-span-3">
                                    <Label className="text-xs font-bold text-[#4A4742]">Observación General</Label>
                                    <Input
                                        value={data.observacion}
                                        onChange={(e) => setData('observacion', e.target.value)}
                                        className="mt-1 h-9 text-xs"
                                    />
                                </div>
                            </div>
                        </Card>

                        <Card className="rounded-[16px] border-[#E7E4DE] bg-white p-6 shadow-none">
                            <h2 className="text-[14px] font-bold text-[#201F1D] border-b border-[#F1EFEC] pb-4">
                                Ajuste de Cantidades y Conformidad por Línea
                            </h2>

                            <div className="flex flex-col divide-y divide-[#F1EFEC] mt-3">
                                {data.items.map((it, idx) => {
                                    const orig = reception.items[idx];
                                    const diff = it.cantidad_conforme - orig.cantidad_conforme;
                                    const hasObs = it.cantidad_conforme < it.cantidad;

                                    return (
                                        <div key={it.id} className="py-4 flex flex-col gap-3">
                                            <div className="flex items-center justify-between">
                                                <div className="font-bold text-xs text-[#201F1D]">
                                                    {orig.producto.nombre} ({orig.producto.codigo})
                                                </div>
                                                {diff !== 0 && (
                                                    <span className="font-mono text-xs font-bold text-[#2563EB]">
                                                        Ajuste: {diff > 0 ? `+${diff}` : diff} unidades
                                                    </span>
                                                )}
                                            </div>

                                            <div className="grid gap-3 sm:grid-cols-3">
                                                <div>
                                                    <Label className="text-[11px] font-bold text-[#4A4742]">Cant. Total</Label>
                                                    <Input
                                                        type="number"
                                                        min="1"
                                                        value={it.cantidad}
                                                        onChange={(e) =>
                                                            updateLineItem(idx, 'cantidad', Number(e.target.value))
                                                        }
                                                        className="mt-1 h-8 text-xs"
                                                        required
                                                    />
                                                </div>

                                                <div>
                                                    <Label className="text-[11px] font-bold text-[#4A4742]">Cant. Conforme</Label>
                                                    <Input
                                                        type="number"
                                                        min="0"
                                                        max={it.cantidad}
                                                        value={it.cantidad_conforme}
                                                        onChange={(e) =>
                                                            updateLineItem(
                                                                idx,
                                                                'cantidad_conforme',
                                                                Number(e.target.value)
                                                            )
                                                        }
                                                        className="mt-1 h-8 text-xs"
                                                        required
                                                    />
                                                </div>

                                                <div>
                                                    <Label className="text-[11px] font-bold text-[#4A4742]">
                                                        Motivo no conforme (si aplica)
                                                    </Label>
                                                    <Input
                                                        value={it.observacion_item}
                                                        onChange={(e) =>
                                                            updateLineItem(idx, 'observacion_item', e.target.value)
                                                        }
                                                        placeholder="Motivo de observación..."
                                                        className="mt-1 h-8 text-xs"
                                                        required={hasObs}
                                                    />
                                                </div>
                                            </div>

                                            {/* Si aumentó conforme en serializado: capturar marca/año de las nuevas */}
                                            {diff > 0 && orig.producto.serializado && (
                                                <div className="rounded-lg bg-[#FAFAF8] border border-[#E7E4DE] p-3 mt-2">
                                                    <div className="text-[11px] font-bold text-[#201F1D] mb-2">
                                                        Datos de las {diff} nuevas unidades conformes a incorporar:
                                                    </div>
                                                    <div className="grid gap-2 sm:grid-cols-2">
                                                        {it.unidades_nuevas.map((u, uIdx) => (
                                                            <div key={uIdx} className="flex items-center gap-2">
                                                                <Input
                                                                    placeholder="Marca"
                                                                    value={u.marca}
                                                                    onChange={(e) =>
                                                                        updateUnidadNueva(
                                                                            idx,
                                                                            uIdx,
                                                                            'marca',
                                                                            e.target.value
                                                                        )
                                                                    }
                                                                    className="h-7 text-xs"
                                                                    required
                                                                />
                                                                <Input
                                                                    type="number"
                                                                    min="1990"
                                                                    max={current_year}
                                                                    placeholder="Año"
                                                                    value={u.anio_fabricacion}
                                                                    onChange={(e) =>
                                                                        updateUnidadNueva(
                                                                            idx,
                                                                            uIdx,
                                                                            'anio_fabricacion',
                                                                            Number(e.target.value)
                                                                        )
                                                                    }
                                                                    className="h-7 w-[80px] text-xs"
                                                                    required
                                                                />
                                                            </div>
                                                        ))}
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
                                className="h-9 gap-1.5 bg-[#E31E24] text-xs font-bold text-white hover:bg-[#C2171C]"
                            >
                                <Check className="size-4" />
                                <span>Guardar Corrección</span>
                            </Button>
                        </div>
                    </form>
                ) : (
                    <>
                        {/* Vista de solo lectura */}
                        <Card className="rounded-[16px] border-[#E7E4DE] bg-white p-6 shadow-none">
                            <div className="flex items-center justify-between border-b border-[#F1EFEC] pb-4">
                                <div className="flex items-center gap-2">
                                    <Truck className="size-4 text-[#E31E24]" />
                                    <h2 className="text-[14px] font-bold text-[#201F1D]">
                                        Información de Recepción
                                    </h2>
                                </div>
                                <span className="font-mono text-xs text-[#8A8680]">
                                    Documento #{reception.id}
                                </span>
                            </div>

                            <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4 text-xs">
                                <div>
                                    <span className="text-[#8A8680]">Proveedor:</span>
                                    <div className="font-bold text-[#201F1D] text-sm mt-0.5">
                                        {reception.proveedor}
                                    </div>
                                </div>

                                <div>
                                    <span className="text-[#8A8680]">Doc. Referencia:</span>
                                    <div className="font-mono font-bold text-[#201F1D] mt-0.5">
                                        {reception.documento_referencia || '—'}
                                    </div>
                                </div>

                                <div>
                                    <span className="text-[#8A8680]">Fecha de ingreso:</span>
                                    <div className="font-mono font-bold text-[#201F1D] mt-0.5">
                                        {reception.fecha}
                                    </div>
                                </div>

                                <div>
                                    <span className="text-[#8A8680]">Sede Almacén:</span>
                                    <div className="font-bold text-[#201F1D] mt-0.5">
                                        {reception.sede.nombre}
                                    </div>
                                </div>

                                {reception.observacion && (
                                    <div className="sm:col-span-2 lg:col-span-4 rounded-lg bg-[#FAFAF8] p-3 text-[#4A4742]">
                                        <span className="font-bold text-[#201F1D]">Observación: </span>
                                        {reception.observacion}
                                    </div>
                                )}
                            </div>
                        </Card>

                        {/* Líneas Recibidas */}
                        <Card className="rounded-[16px] border-[#E7E4DE] bg-white p-6 shadow-none">
                            <h2 className="text-[14px] font-bold text-[#201F1D] border-b border-[#F1EFEC] pb-4">
                                Líneas del Documento
                            </h2>

                            <div className="overflow-x-auto mt-3">
                                <table className="w-full text-left text-[12.5px]">
                                    <thead>
                                        <tr className="border-b border-[#E7E4DE] text-[11px] font-bold text-[#8A8680] uppercase tracking-wider">
                                            <th className="py-2 pr-3">Ítem</th>
                                            <th className="py-2 px-3 text-center">Tipo</th>
                                            <th className="py-2 px-3 text-center">Recibido</th>
                                            <th className="py-2 px-3 text-center">Conforme</th>
                                            <th className="py-2 px-3 text-center">Observado</th>
                                            <th className="py-2 pl-4">Motivo No Conforme</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-[#F1EFEC]">
                                        {reception.items.map((it) => {
                                            const noConforme = it.cantidad - it.cantidad_conforme;

                                            return (
                                                <tr key={it.id}>
                                                    <td className="py-3 pr-3">
                                                        <div className="font-bold text-[#201F1D]">
                                                            {it.producto.nombre}
                                                        </div>
                                                        <div className="font-mono text-[11px] text-[#8A8680]">
                                                            {it.producto.codigo}
                                                        </div>
                                                    </td>
                                                    <td className="py-3 px-3 text-center">
                                                        {it.producto.serializado ? (
                                                            <span className="inline-flex items-center gap-1 rounded-[6px] border border-[#C3E8D4] bg-[#E5F5EC] px-2 py-0.5 text-[10.5px] font-bold text-[#1E8E5A]">
                                                                <ScanBarcode className="size-3" />
                                                                Serializado
                                                            </span>
                                                        ) : (
                                                            <span className="inline-flex items-center gap-1 rounded-[6px] border border-[#E4E1DC] bg-[#F1EFEC] px-2 py-0.5 text-[10.5px] font-bold text-[#6B6965]">
                                                                No serializado
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="py-3 px-3 text-center font-mono font-bold text-[#201F1D]">
                                                        {it.cantidad}
                                                    </td>
                                                    <td className="py-3 px-3 text-center font-mono font-bold text-[#1E8E5A]">
                                                        {it.cantidad_conforme}
                                                    </td>
                                                    <td className="py-3 px-3 text-center font-mono font-bold">
                                                        {noConforme > 0 ? (
                                                            <span className="text-[#E31E24]">
                                                                {noConforme}
                                                            </span>
                                                        ) : (
                                                            <span className="text-[#8A8680]">0</span>
                                                        )}
                                                    </td>
                                                    <td className="py-3 pl-4 text-xs text-[#6B6965]">
                                                        {it.observacion_item || '—'}
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
                            <Card className="rounded-[16px] border-[#E7E4DE] bg-white p-6 shadow-none">
                                <div className="flex items-center justify-between border-b border-[#F1EFEC] pb-4">
                                    <div className="flex items-center gap-2">
                                        <ScanBarcode className="size-4 text-[#1E8E5A]" />
                                        <h2 className="text-[14px] font-bold text-[#201F1D]">
                                            Unidades Físicas Serializadas Generadas ({reception.unidades_serializadas.length})
                                        </h2>
                                    </div>
                                    <span className="text-xs text-[#8A8680]">
                                        Correlativo único BF-EQ-XXXXXX
                                    </span>
                                </div>

                                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 mt-4">
                                    {reception.unidades_serializadas.map((unit) => (
                                        <div
                                            key={unit.id}
                                            className="flex items-center justify-between rounded-xl border border-[#E7E4DE] bg-[#FAFAF8] p-3 text-xs"
                                        >
                                            <div>
                                                <div className="font-mono font-bold text-[#201F1D] text-sm flex items-center gap-1.5">
                                                    <Barcode className="size-4 text-[#E31E24]" />
                                                    {unit.numero_serie}
                                                </div>
                                                <div className="text-[11px] text-[#8A8680] mt-0.5">
                                                    Marca: <b className="text-[#4A4742]">{unit.marca || 'N/A'}</b> • Año:{' '}
                                                    <b className="text-[#4A4742]">{unit.anio_fabricacion || 'N/A'}</b>
                                                </div>
                                            </div>

                                            <span
                                                className={[
                                                    'rounded-full px-2 py-0.5 font-mono text-[10.5px] font-bold',
                                                    unit.estado === 'disponible'
                                                        ? 'bg-[#E5F5EC] text-[#1E8E5A]'
                                                        : 'bg-[#F1EFEC] text-[#6B6965]',
                                                ].join(' ')}
                                            >
                                                {unit.estado}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </Card>
                        )}
                    </>
                )}
            </div>
        </AlmacenLayout>
    );
}
