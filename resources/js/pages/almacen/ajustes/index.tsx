import { Head, useForm, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowDownRight,
    ArrowUpRight,
    Boxes,
    Building2,
    Calendar,
    CheckCircle2,
    Clock,
    FileText,
    History,
    Layers,
    Plus,
    RotateCcw,
    ShieldAlert,
    Trash2,
    User,
} from 'lucide-react';
import { FormEvent, useMemo, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AlmacenLayout from '@/layouts/almacen-layout';
import type { Team } from '@/types';

export type SedeOption = {
    id: number;
    nombre: string;
    tipo: string;
    ciudad: string | null;
};

export type ProductOption = {
    id: number;
    codigo: string;
    nombre: string;
    serializado: boolean;
    unidad_medida: string;
};

export type UnitOption = {
    id: number;
    product_id: number;
    sede_almacen_id: number;
    numero_serie: string;
    marca: string | null;
    estado: string;
};

export type AjusteMovement = {
    id: number;
    created_at: string;
    cantidad: number;
    observacion: string | null;
    product: {
        id: number;
        codigo: string;
        nombre: string;
        unidad_medida: string;
        serializado: boolean;
    };
    sede: {
        id: number;
        nombre: string;
    };
    user?: {
        id: number;
        name: string;
    } | null;
    inventory_unit?: {
        id: number;
        numero_serie: string;
        marca: string | null;
        estado: string;
    } | null;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Props = {
    ajustes: {
        data: AjusteMovement[];
        links: PaginationLink[];
        current_page: number;
        last_page: number;
        total: number;
    };
    sedes: SedeOption[];
    products: ProductOption[];
    units: UnitOption[];
    kpis: {
        total_ajustes: number;
        ajustes_mes: number;
        unidades_dadas_de_baja: number;
    };
};

function formatDate(isoString: string): string {
    const d = new Date(isoString);
    return d.toLocaleString('es-PE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function AjustesIndex({
    ajustes,
    sedes,
    products,
    units,
    kpis,
}: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug = currentTeam?.slug ?? '';

    const [showForm, setShowForm] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        product_id: products.length > 0 ? String(products[0].id) : '',
        sede_id: sedes.length > 0 ? String(sedes[0].id) : '',
        tipo_ajuste: 'decremento',
        cantidad: 1,
        inventory_unit_id: '',
        motivo: '',
        observacion: '',
    });

    const selectedProduct = useMemo(() => {
        return products.find((p) => String(p.id) === String(data.product_id));
    }, [products, data.product_id]);

    // Unidades filtradas para el producto y sede seleccionados
    const availableUnits = useMemo(() => {
        if (!selectedProduct?.serializado) return [];
        return units.filter(
            (u) =>
                String(u.product_id) === String(data.product_id) &&
                String(u.sede_almacen_id) === String(data.sede_id),
        );
    }, [units, selectedProduct, data.product_id, data.sede_id]);

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post(`/${teamSlug}/almacen/ajustes`, {
            onSuccess: () => {
                reset('motivo', 'observacion', 'inventory_unit_id', 'cantidad');
                setShowForm(false);
            },
        });
    };

    return (
        <AlmacenLayout title="Ajustes de Stock Autorizados">
            <Head title="Ajustes de Stock - Almacén" />

            <div className="mx-auto flex max-w-6xl flex-col gap-6">
                {/* Header Title */}
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-bold tracking-tight text-foreground">
                            Ajustes de Stock
                        </h1>
                        <p className="text-xs text-muted-foreground">
                            Aplicación directa con motivo obligatorio. Siempre
                            genera un movimiento de Kardex compensatorio.
                        </p>
                    </div>

                    <Button
                        type="button"
                        onClick={() => setShowForm(!showForm)}
                        className="h-9 gap-1.5 rounded-[9px] bg-primary px-4 text-xs font-bold text-white hover:bg-primary/90"
                    >
                        <Plus className="size-4" />
                        <span>
                            {showForm ? 'Ocultar Formulario' : 'Nuevo Ajuste'}
                        </span>
                    </Button>
                </div>

                {/* KPI Cards */}
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <Card className="rounded-[12px] border border-border bg-card p-4 shadow-sm">
                        <div className="flex items-center gap-3">
                            <div className="flex size-10 items-center justify-center rounded-[9px] bg-muted/30 text-destructive">
                                <RotateCcw className="size-5" />
                            </div>
                            <div>
                                <span className="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                                    Total Ajustes Históricos
                                </span>
                                <p className="text-xl font-black text-foreground">
                                    {kpis.total_ajustes}
                                </p>
                            </div>
                        </div>
                    </Card>

                    <Card className="rounded-[12px] border border-border bg-card p-4 shadow-sm">
                        <div className="flex items-center gap-3">
                            <div className="flex size-10 items-center justify-center rounded-[9px] bg-sky-500/10 text-blue-600 dark:text-blue-400">
                                <Calendar className="size-5" />
                            </div>
                            <div>
                                <span className="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                                    Ajustes Este Mes
                                </span>
                                <p className="text-xl font-black text-foreground">
                                    {kpis.ajustes_mes}
                                </p>
                            </div>
                        </div>
                    </Card>

                    <Card className="rounded-[12px] border border-border bg-card p-4 shadow-sm">
                        <div className="flex items-center gap-3">
                            <div className="flex size-10 items-center justify-center rounded-[9px] bg-destructive/10 text-destructive">
                                <Trash2 className="size-5" />
                            </div>
                            <div>
                                <span className="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                                    Unidades Dadas de Baja
                                </span>
                                <p className="text-xl font-black text-foreground">
                                    {kpis.unidades_dadas_de_baja}
                                </p>
                            </div>
                        </div>
                    </Card>
                </div>

                {/* Formulario de Nuevo Ajuste (Desplegable) */}
                {showForm && (
                    <Card className="rounded-[14px] border-2 border-destructive/20/30 bg-card p-6 shadow-md transition-all">
                        <div className="mb-5 flex items-center justify-between border-b border-border pb-3">
                            <div className="flex items-center gap-2 text-sm font-bold text-foreground">
                                <ShieldAlert className="size-5 text-primary" />
                                <span>
                                    Registrar Ajuste en Kardex (Aplicación
                                    Directa)
                                </span>
                            </div>
                            <span className="text-xs text-muted-foreground">
                                Requiere motivo de mínimo 10 caracteres
                            </span>
                        </div>

                        {Object.keys(errors).length > 0 && (
                            <div className="mb-4 flex items-start gap-2.5 rounded-[9px] border border-destructive/20 bg-destructive/10 p-3 text-xs text-primary">
                                <AlertCircle className="mt-0.5 size-4 shrink-0" />
                                <div>
                                    <p className="font-bold">
                                        No se pudo aplicar el ajuste:
                                    </p>
                                    <ul className="mt-1 list-inside list-disc">
                                        {Object.entries(errors).map(
                                            ([key, msg]) => (
                                                <li key={key}>{msg}</li>
                                            ),
                                        )}
                                    </ul>
                                </div>
                            </div>
                        )}

                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                {/* Producto */}
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-bold text-foreground">
                                        Producto / Componente{' '}
                                        <span className="text-red-500">*</span>
                                    </Label>
                                    <select
                                        value={data.product_id}
                                        onChange={(e) => {
                                            setData((d) => ({
                                                ...d,
                                                product_id: e.target.value,
                                                inventory_unit_id: '',
                                            }));
                                        }}
                                        className="w-full rounded-[8px] border border-border bg-card px-3 py-2 text-xs font-medium text-foreground focus:border-primary focus:outline-none"
                                        required
                                    >
                                        {products.map((p) => (
                                            <option key={p.id} value={p.id}>
                                                [{p.codigo}] {p.nombre}{' '}
                                                {p.serializado
                                                    ? '• (Serializado)'
                                                    : '• (A granel)'}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                {/* Sede */}
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-bold text-foreground">
                                        Sede Almacén{' '}
                                        <span className="text-red-500">*</span>
                                    </Label>
                                    <select
                                        value={data.sede_id}
                                        onChange={(e) => {
                                            setData((d) => ({
                                                ...d,
                                                sede_id: e.target.value,
                                                inventory_unit_id: '',
                                            }));
                                        }}
                                        className="w-full rounded-[8px] border border-border bg-card px-3 py-2 text-xs font-medium text-foreground focus:border-primary focus:outline-none"
                                        required
                                    >
                                        {sedes.map((s) => (
                                            <option key={s.id} value={s.id}>
                                                {s.nombre} ({s.tipo})
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                {/* Tipo de Ajuste */}
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-bold text-foreground">
                                        Tipo de Ajuste{' '}
                                        <span className="text-red-500">*</span>
                                    </Label>
                                    <div className="grid grid-cols-2 gap-2">
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setData(
                                                    'tipo_ajuste',
                                                    'decremento',
                                                )
                                            }
                                            className={`flex items-center justify-center gap-1.5 rounded-[8px] border py-2 text-xs font-bold transition-all ${
                                                data.tipo_ajuste ===
                                                'decremento'
                                                    ? 'border-destructive/20 bg-destructive/10 text-destructive'
                                                    : 'border-border bg-card text-muted-foreground hover:bg-background'
                                            }`}
                                        >
                                            <ArrowDownRight className="size-4" />
                                            <span>Baja (-)</span>
                                        </button>

                                        <button
                                            type="button"
                                            onClick={() =>
                                                setData(
                                                    'tipo_ajuste',
                                                    'incremento',
                                                )
                                            }
                                            className={`flex items-center justify-center gap-1.5 rounded-[8px] border py-2 text-xs font-bold transition-all ${
                                                data.tipo_ajuste ===
                                                'incremento'
                                                    ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                                    : 'border-border bg-card text-muted-foreground hover:bg-background'
                                            }`}
                                        >
                                            <ArrowUpRight className="size-4" />
                                            <span>Ingreso (+)</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                {/* Selector condicional de Unidad Serializada */}
                                {selectedProduct?.serializado ? (
                                    <div className="space-y-1.5">
                                        <Label className="text-xs font-bold text-foreground">
                                            Unidad Física (Código Serie){' '}
                                            {data.tipo_ajuste ===
                                                'decremento' && (
                                                <span className="text-red-500">
                                                    *
                                                </span>
                                            )}
                                        </Label>
                                        <select
                                            value={data.inventory_unit_id}
                                            onChange={(e) => {
                                                setData((d) => ({
                                                    ...d,
                                                    inventory_unit_id:
                                                        e.target.value,
                                                    cantidad: e.target.value
                                                        ? 1
                                                        : d.cantidad,
                                                }));
                                            }}
                                            className="w-full rounded-[8px] border border-border bg-card px-3 py-2 text-xs font-medium text-foreground focus:border-primary focus:outline-none"
                                            required={
                                                data.tipo_ajuste ===
                                                'decremento'
                                            }
                                        >
                                            <option value="">
                                                -- Seleccione unidad --
                                            </option>
                                            {availableUnits.map((u) => (
                                                <option key={u.id} value={u.id}>
                                                    {u.numero_serie}{' '}
                                                    {u.marca
                                                        ? `(${u.marca})`
                                                        : ''}{' '}
                                                    - [{u.estado}]
                                                </option>
                                            ))}
                                        </select>
                                        {availableUnits.length === 0 && (
                                            <p className="text-[11px] text-destructive">
                                                No hay unidades físicas
                                                registradas en esta sede.
                                            </p>
                                        )}
                                    </div>
                                ) : (
                                    <div className="space-y-1.5">
                                        <Label className="text-xs font-bold text-foreground">
                                            Tipo de Producto
                                        </Label>
                                        <div className="rounded-[8px] border border-dashed border-border bg-muted/30 px-3 py-2 text-xs text-muted-foreground">
                                            Producto a granel (sin serie
                                            individual)
                                        </div>
                                    </div>
                                )}

                                {/* Cantidad */}
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-bold text-foreground">
                                        Cantidad a Ajustar{' '}
                                        <span className="text-red-500">*</span>
                                    </Label>
                                    <Input
                                        type="number"
                                        min={1}
                                        value={data.cantidad}
                                        disabled={Boolean(
                                            data.inventory_unit_id,
                                        )}
                                        onChange={(e) =>
                                            setData(
                                                'cantidad',
                                                Number(e.target.value),
                                            )
                                        }
                                        className="h-9 text-xs font-bold"
                                        required
                                    />
                                    {Boolean(data.inventory_unit_id) && (
                                        <p className="text-[10px] text-muted-foreground">
                                            Fijada en 1 por ser unidad
                                            serializada puntual.
                                        </p>
                                    )}
                                </div>

                                {/* Motivo Principal (Obligatorio) */}
                                <div className="space-y-1.5 md:col-span-1">
                                    <Label className="text-xs font-bold text-foreground">
                                        Motivo de Auditoría (min. 10 caracteres){' '}
                                        <span className="text-red-500">*</span>
                                    </Label>
                                    <Input
                                        type="text"
                                        placeholder="Ej: Conteo físico semestral, daño en traslado"
                                        value={data.motivo}
                                        onChange={(e) =>
                                            setData('motivo', e.target.value)
                                        }
                                        className="h-9 text-xs"
                                        required
                                    />
                                </div>
                            </div>

                            {/* Observación detalle */}
                            <div className="space-y-1.5">
                                <Label className="text-xs font-bold text-foreground">
                                    Detalles Adicionales / Observación
                                    (opcional)
                                </Label>
                                <Input
                                    type="text"
                                    placeholder="Detalles sobre acta o reclamo a transporte..."
                                    value={data.observacion}
                                    onChange={(e) =>
                                        setData('observacion', e.target.value)
                                    }
                                    className="h-9 text-xs"
                                />
                            </div>

                            <div className="flex justify-end gap-2 border-t border-border pt-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setShowForm(false)}
                                    className="h-9 text-xs font-bold"
                                >
                                    Cancelar
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="h-9 gap-1.5 bg-primary text-xs font-bold text-white hover:bg-primary/90"
                                >
                                    <CheckCircle2 className="size-4" />
                                    <span>
                                        {processing
                                            ? 'Aplicando...'
                                            : 'Confirmar Ajuste en Kardex'}
                                    </span>
                                </Button>
                            </div>
                        </form>
                    </Card>
                )}

                {/* Tabla de Historial de Ajustes */}
                <Card className="overflow-hidden rounded-[12px] border border-border bg-card shadow-sm">
                    <div className="flex items-center justify-between border-b border-border bg-muted/40 px-4 py-3">
                        <div className="flex items-center gap-2 text-xs font-bold text-foreground">
                            <History className="size-4 text-destructive" />
                            <span>
                                Historial de Ajustes de Stock Registrados
                            </span>
                        </div>
                        <span className="text-xs text-muted-foreground">
                            {ajustes.total} ajustes totales
                        </span>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="border-b border-border bg-muted/30/50 text-[11px] font-bold tracking-wider text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-2.5">
                                        Fecha y Hora
                                    </th>
                                    <th className="px-4 py-2.5">Producto</th>
                                    <th className="px-4 py-2.5">Sede</th>
                                    <th className="px-4 py-2.5 text-center">
                                        Tipo & Cantidad
                                    </th>
                                    <th className="px-4 py-2.5">
                                        Unidad Serie
                                    </th>
                                    <th className="px-4 py-2.5">
                                        Motivo / Kardex
                                    </th>
                                    <th className="px-4 py-2.5">Usuario</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {ajustes.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="py-8 text-center text-xs text-muted-foreground"
                                        >
                                            No se han registrado ajustes de
                                            stock aún.
                                        </td>
                                    </tr>
                                ) : (
                                    ajustes.data.map((m) => {
                                        const isPositive = m.cantidad > 0;
                                        return (
                                            <tr
                                                key={m.id}
                                                className="hover:bg-muted/40"
                                            >
                                                <td className="px-4 py-3 font-mono text-[11px] text-muted-foreground">
                                                    {formatDate(m.created_at)}
                                                </td>
                                                <td className="px-4 py-3 font-semibold text-foreground">
                                                    <div>
                                                        [{m.product.codigo}]{' '}
                                                        {m.product.nombre}
                                                    </div>
                                                    <div className="text-[10px] text-muted-foreground">
                                                        {m.product.serializado
                                                            ? 'Serializado'
                                                            : 'A granel'}{' '}
                                                        (
                                                        {
                                                            m.product
                                                                .unidad_medida
                                                        }
                                                        )
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3 text-foreground">
                                                    {m.sede.nombre}
                                                </td>
                                                <td className="px-4 py-3 text-center">
                                                    <span
                                                        className={`inline-flex items-center gap-1 rounded-[6px] border px-2 py-0.5 text-[11px] font-black ${
                                                            isPositive
                                                                ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                                                : 'border-destructive/20 bg-destructive/10 text-destructive'
                                                        }`}
                                                    >
                                                        {isPositive ? (
                                                            <ArrowUpRight className="size-3" />
                                                        ) : (
                                                            <ArrowDownRight className="size-3" />
                                                        )}
                                                        {isPositive
                                                            ? `+${m.cantidad}`
                                                            : m.cantidad}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 font-mono text-xs">
                                                    {m.inventory_unit ? (
                                                        <span className="font-bold text-foreground">
                                                            {
                                                                m.inventory_unit
                                                                    .numero_serie
                                                            }
                                                            {m.inventory_unit
                                                                .estado ===
                                                                'baja' && (
                                                                <Badge
                                                                    variant="destructive"
                                                                    className="ml-1.5 px-1 py-0 text-[9px]"
                                                                >
                                                                    Baja
                                                                </Badge>
                                                            )}
                                                        </span>
                                                    ) : (
                                                        <span className="text-muted-foreground">
                                                            —
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="max-w-xs px-4 py-3 text-foreground">
                                                    <p className="line-clamp-2 text-[11.5px]">
                                                        {m.observacion ?? '—'}
                                                    </p>
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    <div className="flex items-center gap-1">
                                                        <User className="size-3 text-muted-foreground" />
                                                        <span>
                                                            {m.user?.name ??
                                                                'Sistema'}
                                                        </span>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Paginación */}
                    {ajustes.links.length > 3 && (
                        <div className="flex items-center justify-between border-t border-border bg-muted/40 px-4 py-3">
                            <span className="text-xs text-muted-foreground">
                                Página {ajustes.current_page} de{' '}
                                {ajustes.last_page}
                            </span>
                            <div className="flex items-center gap-1">
                                {ajustes.links.map((link, idx) => (
                                    <Button
                                        key={idx}
                                        variant={
                                            link.active ? 'default' : 'outline'
                                        }
                                        size="sm"
                                        disabled={!link.url}
                                        onClick={() =>
                                            link.url &&
                                            (window.location.href = link.url)
                                        }
                                        className="h-7 px-2.5 text-xs"
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </Card>
            </div>
        </AlmacenLayout>
    );
}
