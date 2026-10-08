import { Head, useForm, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowDownRight,
    ArrowUpRight,
    Calendar,
    CheckCircle2,
    History,
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
import AjustesRoutes from '@/routes/almacen/ajustes';

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
    controla_lote: boolean;
    unidad_medida: string;
};

export type LoteOption = {
    id: number;
    product_id: number;
    sede_id: number;
    lote: string;
    fecha_vencimiento: string | null;
    vencido: boolean;
    saldo: number;
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
    lotes: LoteOption[];
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
    lotes,
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
        product_lot_id: '',
        lote: '',
        fecha_vencimiento: '',
        motivo: '',
        observacion: '',
    });

    const lotesDelProducto = lotes.filter(
        (l) =>
            String(l.product_id) === String(data.product_id) &&
            String(l.sede_id) === String(data.sede_id),
    );

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
        post(AjustesRoutes.store.url({ current_team: teamSlug }), {
            onSuccess: () => {
                reset(
                    'motivo',
                    'observacion',
                    'inventory_unit_id',
                    'cantidad',
                    'product_lot_id',
                    'lote',
                    'fecha_vencimiento',
                );
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
                        <h1 className="text-foreground text-xl font-bold tracking-tight">
                            Ajustes de Stock
                        </h1>
                        <p className="text-muted-foreground text-xs">
                            Aplicación directa con motivo obligatorio. Siempre
                            genera un movimiento de Kardex compensatorio.
                        </p>
                    </div>

                    <Button
                        type="button"
                        onClick={() => setShowForm(!showForm)}
                        className="bg-primary hover:bg-primary/90 h-9 gap-1.5 rounded-[9px] px-4 text-xs font-bold text-white"
                    >
                        <Plus className="size-4" />
                        <span>
                            {showForm ? 'Ocultar Formulario' : 'Nuevo Ajuste'}
                        </span>
                    </Button>
                </div>

                {/* KPI Cards */}
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <Card className="border-border bg-card rounded-[12px] border p-4 shadow-sm">
                        <div className="flex items-center gap-3">
                            <div className="bg-muted/30 text-destructive-strong flex size-10 items-center justify-center rounded-[9px]">
                                <RotateCcw className="size-5" />
                            </div>
                            <div>
                                <span className="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">
                                    Total Ajustes Históricos
                                </span>
                                <p className="text-foreground text-xl font-black">
                                    {kpis.total_ajustes}
                                </p>
                            </div>
                        </div>
                    </Card>

                    <Card className="border-border bg-card rounded-[12px] border p-4 shadow-sm">
                        <div className="flex items-center gap-3">
                            <div className="flex size-10 items-center justify-center rounded-[9px] bg-sky-500/10 text-blue-600 dark:text-blue-400">
                                <Calendar className="size-5" />
                            </div>
                            <div>
                                <span className="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">
                                    Ajustes Este Mes
                                </span>
                                <p className="text-foreground text-xl font-black">
                                    {kpis.ajustes_mes}
                                </p>
                            </div>
                        </div>
                    </Card>

                    <Card className="border-border bg-card rounded-[12px] border p-4 shadow-sm">
                        <div className="flex items-center gap-3">
                            <div className="bg-destructive/10 text-destructive-strong flex size-10 items-center justify-center rounded-[9px]">
                                <Trash2 className="size-5" />
                            </div>
                            <div>
                                <span className="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">
                                    Unidades Dadas de Baja
                                </span>
                                <p className="text-foreground text-xl font-black">
                                    {kpis.unidades_dadas_de_baja}
                                </p>
                            </div>
                        </div>
                    </Card>
                </div>

                {/* Formulario de Nuevo Ajuste (Desplegable) */}
                {showForm && (
                    <Card className="border-destructive/20/30 bg-card rounded-[14px] border-2 p-6 shadow-md transition-all">
                        <div className="border-border mb-5 flex items-center justify-between border-b pb-3">
                            <div className="text-foreground flex items-center gap-2 text-sm font-bold">
                                <ShieldAlert className="text-primary-strong size-5" />
                                <span>
                                    Registrar Ajuste en Kardex (Aplicación
                                    Directa)
                                </span>
                            </div>
                            <span className="text-muted-foreground text-xs">
                                Requiere motivo de mínimo 10 caracteres
                            </span>
                        </div>

                        {Object.keys(errors).length > 0 && (
                            <div className="border-destructive/20 bg-destructive/10 text-primary-strong mb-4 flex items-start gap-2.5 rounded-[9px] border p-3 text-xs">
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
                                    <Label className="text-foreground text-xs font-bold">
                                        Producto / Componente{' '}
                                        <span className="text-red-500">*</span>
                                    </Label>
                                    <select
                                        aria-label="Producto / Componente"
                                        value={data.product_id}
                                        onChange={(e) => {
                                            setData((d) => ({
                                                ...d,
                                                product_id: e.target.value,
                                                inventory_unit_id: '',
                                            }));
                                        }}
                                        className="border-border bg-card text-foreground focus:border-primary w-full rounded-[8px] border px-3 py-2 text-xs font-medium focus:outline-none"
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
                                    <Label className="text-foreground text-xs font-bold">
                                        Sede Almacén{' '}
                                        <span className="text-red-500">*</span>
                                    </Label>
                                    <select
                                        aria-label="Sede Almacén"
                                        value={data.sede_id}
                                        onChange={(e) => {
                                            setData((d) => ({
                                                ...d,
                                                sede_id: e.target.value,
                                                inventory_unit_id: '',
                                            }));
                                        }}
                                        className="border-border bg-card text-foreground focus:border-primary w-full rounded-[8px] border px-3 py-2 text-xs font-medium focus:outline-none"
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
                                    <Label className="text-foreground text-xs font-bold">
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
                                                    ? 'border-destructive/20 bg-destructive/10 text-destructive-strong'
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
                                                    ? 'text-success-strong border-emerald-500/20 bg-emerald-500/10'
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
                                        <Label className="text-foreground text-xs font-bold">
                                            Unidad Física (Código Serie){' '}
                                            {data.tipo_ajuste ===
                                                'decremento' && (
                                                <span className="text-red-500">
                                                    *
                                                </span>
                                            )}
                                        </Label>
                                        <select
                                            aria-label="Unidad de inventario"
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
                                            className="border-border bg-card text-foreground focus:border-primary w-full rounded-[8px] border px-3 py-2 text-xs font-medium focus:outline-none"
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
                                            <p className="text-destructive-strong text-[11px]">
                                                No hay unidades físicas
                                                registradas en esta sede.
                                            </p>
                                        )}
                                    </div>
                                ) : selectedProduct?.controla_lote &&
                                  data.tipo_ajuste === 'decremento' ? (
                                    <div className="space-y-1.5">
                                        <Label className="text-foreground text-xs font-bold">
                                            Lote
                                        </Label>
                                        <select
                                            aria-label="Lote"
                                            value={data.product_lot_id}
                                            onChange={(e) =>
                                                setData(
                                                    'product_lot_id',
                                                    e.target.value,
                                                )
                                            }
                                            className="border-border bg-card text-foreground focus:border-primary w-full rounded-[8px] border px-3 py-2 text-xs font-medium focus:outline-none"
                                        >
                                            <option value="">
                                                Lo que vence primero
                                            </option>
                                            {lotesDelProducto.map((l) => (
                                                <option key={l.id} value={l.id}>
                                                    {l.lote} · vence{' '}
                                                    {l.fecha_vencimiento
                                                        ? l.fecha_vencimiento
                                                              .split('-')
                                                              .reverse()
                                                              .join('/')
                                                        : '—'}{' '}
                                                    · {l.saldo} en stock
                                                    {l.vencido
                                                        ? ' (VENCIDO)'
                                                        : ''}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                ) : selectedProduct?.controla_lote ? (
                                    <div className="grid grid-cols-2 gap-2">
                                        <div className="space-y-1.5">
                                            <Label className="text-foreground text-xs font-bold">
                                                Lote{' '}
                                                <span className="text-red-500">
                                                    *
                                                </span>
                                            </Label>
                                            <Input
                                                value={data.lote}
                                                onChange={(e) =>
                                                    setData(
                                                        'lote',
                                                        e.target.value.toUpperCase(),
                                                    )
                                                }
                                                className="h-9 text-xs"
                                                required
                                            />
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label className="text-foreground text-xs font-bold">
                                                Vence
                                            </Label>
                                            <Input
                                                type="date"
                                                value={data.fecha_vencimiento}
                                                onChange={(e) =>
                                                    setData(
                                                        'fecha_vencimiento',
                                                        e.target.value,
                                                    )
                                                }
                                                className="h-9 text-xs"
                                            />
                                        </div>
                                    </div>
                                ) : (
                                    <div className="space-y-1.5">
                                        <Label className="text-foreground text-xs font-bold">
                                            Tipo de Producto
                                        </Label>
                                        <div className="border-border bg-muted/30 text-muted-foreground rounded-[8px] border border-dashed px-3 py-2 text-xs">
                                            Producto a granel (sin serie
                                            individual)
                                        </div>
                                    </div>
                                )}

                                {/* Cantidad */}
                                <div className="space-y-1.5">
                                    <Label className="text-foreground text-xs font-bold">
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
                                        <p className="text-muted-foreground text-[10px]">
                                            Fijada en 1 por ser unidad
                                            serializada puntual.
                                        </p>
                                    )}
                                </div>

                                {/* Motivo Principal (Obligatorio) */}
                                <div className="space-y-1.5 md:col-span-1">
                                    <Label className="text-foreground text-xs font-bold">
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
                                <Label className="text-foreground text-xs font-bold">
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

                            <div className="border-border flex justify-end gap-2 border-t pt-2">
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
                                    className="bg-primary hover:bg-primary/90 h-9 gap-1.5 text-xs font-bold text-white"
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
                <Card className="border-border bg-card overflow-hidden rounded-[12px] border shadow-sm">
                    <div className="border-border bg-muted/40 flex items-center justify-between border-b px-4 py-3">
                        <div className="text-foreground flex items-center gap-2 text-xs font-bold">
                            <History className="text-destructive-strong size-4" />
                            <span>
                                Historial de Ajustes de Stock Registrados
                            </span>
                        </div>
                        <span className="text-muted-foreground text-xs">
                            {ajustes.total} ajustes totales
                        </span>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="border-border bg-muted/30/50 text-muted-foreground border-b text-[11px] font-bold tracking-wider uppercase">
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
                            <tbody className="divide-border divide-y">
                                {ajustes.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="text-muted-foreground py-8 text-center text-xs"
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
                                                <td className="text-muted-foreground px-4 py-3 font-mono text-[11px]">
                                                    {formatDate(m.created_at)}
                                                </td>
                                                <td className="text-foreground px-4 py-3 font-semibold">
                                                    <div>
                                                        [{m.product.codigo}]{' '}
                                                        {m.product.nombre}
                                                    </div>
                                                    <div className="text-muted-foreground text-[10px]">
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
                                                <td className="text-foreground px-4 py-3">
                                                    {m.sede.nombre}
                                                </td>
                                                <td className="px-4 py-3 text-center">
                                                    <span
                                                        className={`inline-flex items-center gap-1 rounded-[6px] border px-2 py-0.5 text-[11px] font-black ${
                                                            isPositive
                                                                ? 'text-success-strong border-emerald-500/20 bg-emerald-500/10'
                                                                : 'border-destructive/20 bg-destructive/10 text-destructive-strong'
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
                                                        <span className="text-foreground font-bold">
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
                                                <td className="text-foreground max-w-xs px-4 py-3">
                                                    <p className="line-clamp-2 text-[11.5px]">
                                                        {m.observacion ?? '—'}
                                                    </p>
                                                </td>
                                                <td className="text-muted-foreground px-4 py-3">
                                                    <div className="flex items-center gap-1">
                                                        <User className="text-muted-foreground size-3" />
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
                        <div className="border-border bg-muted/40 flex items-center justify-between border-t px-4 py-3">
                            <span className="text-muted-foreground text-xs">
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
