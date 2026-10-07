import { Head, Link, usePage } from "@inertiajs/react";
import {
    AlertTriangle,
    ArrowRight,
    Boxes,
    Building2,
    Calendar,
    History,
    PackageCheck,
    ScanBarcode,
    TrendingDown,
    Truck,
    User,
} from "lucide-react";

import { Card } from "@/components/ui/card";
import AlmacenLayout from "@/layouts/almacen-layout";
import recepcionesRutas from "@/routes/almacen/recepciones";
import type { Team } from "@/types";

export type SedeStock = {
    sede_id: number;
    nombre: string;
    tipo: string;
    ciudad: string | null;
    unidades_disponibles: number;
};

export type StockKpis = {
    total_disponible: number;
    por_sede: SedeStock[];
};

export type MovimientoReciente = {
    id: number;
    fecha: string | null;
    tipo: string;
    cantidad: number;
    producto: {
        id: number;
        codigo: string;
        nombre: string;
        unidad_medida: string;
        serializado: boolean;
    };
    unidad_serie?: string | null;
    sede?: string | null;
    usuario?: string | null;
    observacion?: string | null;
};

export type ProductoBajoMinimo = {
    id: number;
    codigo: string;
    nombre: string;
    unidad_medida: string;
    stock_minimo: number;
    stock_disponible: number;
    diferencia: number;
};

export type AlmacenDashboardProps = {
    stock: StockKpis;
    recepciones_hoy: number;
    movimientos_recientes: MovimientoReciente[];
    productos_bajo_minimo: ProductoBajoMinimo[];
    lotes_por_vencer: LotePorVencer[];
};

export type LotePorVencer = {
    id: number;
    producto: string;
    unidad_medida: string;
    lote: string;
    sede: string;
    fecha_vencimiento: string | null;
    vencido: boolean;
    saldo: number;
};

function formatNumber(value: number): string {
    return new Intl.NumberFormat("es-PE").format(value);
}

function formatDate(isoString: string | null): string {
    if (!isoString) return "—";
    const date = new Date(isoString);
    return date.toLocaleString("es-PE", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
}

function getTipoMovimientoBadge(tipo: string): {
    label: string;
    className: string;
} {
    switch (tipo) {
        case "ingreso":
            return {
                label: "Ingreso",
                className:
                    "bg-emerald-500/10 text-success-strong border border-emerald-500/20 border-emerald-500/20",
            };
        case "salida_venta":
            return {
                label: "Venta",
                className:
                    "bg-destructive/10 text-primary-strong border-destructive/20",
            };
        case "salida_servicio":
            return {
                label: "Consumo Taller",
                className:
                    "bg-purple-500/10 text-purple-600 dark:text-purple-400 border-purple-500/20",
            };
        case "ajuste":
            return {
                label: "Ajuste",
                className:
                    "bg-amber-500/10 text-warning-strong border-amber-500/20",
            };
        case "traslado":
            return {
                label: "Traslado",
                className:
                    "bg-sky-500/10 text-blue-600 dark:text-blue-400 border-sky-500/20",
            };
        default:
            return {
                label: tipo,
                className: "bg-muted text-muted-foreground border-border",
            };
    }
}

export default function AlmacenDashboard({
    stock,
    recepciones_hoy,
    movimientos_recientes,
    productos_bajo_minimo,
    lotes_por_vencer,
}: AlmacenDashboardProps) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug =
        currentTeam?.slug ||
        (typeof window !== "undefined"
            ? window.location.pathname.split("/")[1]
            : "");

    return (
        <AlmacenLayout title="Dashboard de Almacén">
            <Head title="Dashboard Almacén" />

            <div className="flex flex-col gap-6">
                {/* Banner de alerta si hay productos bajo el stock mínimo de almacén */}
                {productos_bajo_minimo.length > 0 && (
                    <div className="flex items-center gap-3 rounded-[14px] bg-gradient-to-r from-[#B9151A] to-[#E31E24] px-5 py-3.5 text-white shadow-xs">
                        <AlertTriangle className="size-5 shrink-0 text-white" />
                        <span className="flex-1 text-[13px]">
                            Hay{" "}
                            <b>{productos_bajo_minimo.length} producto(s)</b>{" "}
                            con existencias bajo el nivel mínimo en el almacén.
                            Se requiere compra / reabastecimiento.
                        </span>
                        <Link
                            href={`/${teamSlug}/almacen/stock`}
                            className="inline-flex items-center gap-1.5 rounded-[8px] bg-white/20 px-3 py-1.5 text-xs font-bold text-white transition-colors hover:bg-white/30"
                        >
                            <span>Ver stock</span>
                            <ArrowRight className="size-3.5" />
                        </Link>
                    </div>
                )}

                {/* EPP y consumibles: lotes vencidos o por vencer (60 días) */}
                {lotes_por_vencer.length > 0 && (
                    <Card className="border-border bg-card rounded-[16px] p-5 shadow-none">
                        <div className="flex items-center justify-between">
                            <span className="text-foreground text-[13.5px] font-bold">
                                Lotes vencidos o por vencer
                            </span>
                            <Link
                                href={`/${teamSlug}/almacen/ajustes`}
                                className="text-primary-strong text-xs font-bold"
                            >
                                Dar de baja un vencido
                            </Link>
                        </div>
                        <p className="text-muted-foreground mt-1 text-[11.5px]">
                            Los vencidos no se venden; los próximos salen
                            primero en cada venta.
                        </p>
                        <div className="divide-border mt-3 flex flex-col divide-y">
                            {lotes_por_vencer.map((lote) => (
                                <div
                                    key={lote.id}
                                    className="flex flex-wrap items-center justify-between gap-2 py-2 text-xs"
                                >
                                    <div>
                                        <div className="text-foreground font-bold">
                                            {lote.producto}
                                        </div>
                                        <div className="text-muted-foreground text-[11px]">
                                            Lote {lote.lote} · {lote.saldo}{" "}
                                            {lote.unidad_medida} · {lote.sede}
                                        </div>
                                    </div>
                                    <span
                                        className={`rounded-full px-2 py-0.5 text-[10.5px] font-bold ${
                                            lote.vencido
                                                ? "bg-destructive/10 text-destructive-strong"
                                                : "text-warning-strong bg-amber-500/10"
                                        }`}
                                    >
                                        {lote.vencido ? "Vencido" : "Vence"}{" "}
                                        {lote.fecha_vencimiento
                                            ?.split("-")
                                            .reverse()
                                            .join("/")}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </Card>
                )}

                {/* 3-Column Top Grid: Unidades en Stock, Recepciones de Hoy, Alerta Reabastecimiento */}
                <div className="grid gap-4 md:grid-cols-3">
                    {/* 1. Unidades Disponibles */}
                    <Card className="border-border bg-card rounded-[16px] p-5 shadow-none">
                        <div className="flex items-center justify-between">
                            <span className="text-foreground text-[13.5px] font-bold">
                                Unidades disponibles
                            </span>
                            <div className="bg-muted text-foreground flex size-7 items-center justify-center rounded-[8px]">
                                <Boxes className="size-4" />
                            </div>
                        </div>

                        <div className="mt-4">
                            <div className="text-foreground font-['Oswald',sans-serif] text-[34px] leading-none font-semibold">
                                {formatNumber(stock.total_disponible)}
                            </div>
                            <div className="text-muted-foreground mt-2 text-[11.5px]">
                                Unidades físicas serializadas en almacenes
                            </div>
                        </div>

                        <div className="border-border mt-4 border-t pt-3">
                            <div className="text-muted-foreground text-[11px] font-bold tracking-wider uppercase">
                                Por almacén
                            </div>
                            <div className="mt-2 flex flex-col gap-1.5">
                                {stock.por_sede.map((sede) => (
                                    <div
                                        key={sede.sede_id}
                                        className="flex items-center justify-between text-[12px]"
                                    >
                                        <span className="text-foreground/80 flex items-center gap-1.5">
                                            <Building2 className="text-muted-foreground size-3.5" />
                                            {sede.nombre}
                                        </span>
                                        <span className="text-foreground font-bold">
                                            {formatNumber(
                                                sede.unidades_disponibles,
                                            )}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </Card>

                    {/* 2. Recepciones de Hoy */}
                    <Card className="border-border bg-card rounded-[16px] p-5 shadow-none">
                        <div className="flex items-center justify-between">
                            <span className="text-foreground text-[13.5px] font-bold">
                                Recepciones de hoy
                            </span>
                            <div className="text-success-strong flex size-7 items-center justify-center rounded-[8px] border border-emerald-500/20 bg-emerald-500/10">
                                <Truck className="size-4" />
                            </div>
                        </div>

                        <div className="mt-4">
                            <div className="text-foreground font-['Oswald',sans-serif] text-[34px] leading-none font-semibold">
                                {formatNumber(recepciones_hoy)}
                            </div>
                            <div className="text-muted-foreground mt-2 flex items-center gap-1.5 text-[11.5px]">
                                <span className="text-success-strong inline-flex items-center gap-1 rounded-[6px] bg-emerald-500/10 px-2 py-0.5 font-bold">
                                    <Calendar className="size-3" />
                                    <span>Hoy</span>
                                </span>
                                <span>Ingresos registrados al Kardex</span>
                            </div>
                        </div>

                        <div className="border-border mt-5 border-t pt-4">
                            <Link
                                href={recepcionesRutas.create.url({
                                    current_team: teamSlug,
                                })}
                                className="text-primary-strong inline-flex items-center gap-1.5 text-xs font-bold hover:underline"
                            >
                                <span>Registrar nueva recepción</span>
                                <ArrowRight className="size-3.5" />
                            </Link>
                        </div>
                    </Card>

                    {/* 3. Reabastecimiento de Almacén */}
                    <Card className="border-border bg-card rounded-[16px] p-5 shadow-none">
                        <div className="flex items-center justify-between">
                            <span className="text-foreground text-[13.5px] font-bold">
                                Bajo stock mínimo
                            </span>
                            <div className="bg-destructive/10 text-primary-strong flex size-7 items-center justify-center rounded-[8px]">
                                <TrendingDown className="size-4" />
                            </div>
                        </div>

                        <div className="mt-4">
                            <div className="text-primary-strong font-['Oswald',sans-serif] text-[34px] leading-none font-semibold">
                                {formatNumber(productos_bajo_minimo.length)}
                            </div>
                            <div className="text-muted-foreground mt-2 text-[11.5px]">
                                Productos que requieren reposición
                            </div>
                        </div>

                        <div className="border-border mt-5 border-t pt-4">
                            <Link
                                href={`/${teamSlug}/almacen/stock`}
                                className="text-foreground inline-flex items-center gap-1.5 text-xs font-bold hover:underline"
                            >
                                <span>Ver reporte de inventario</span>
                                <ArrowRight className="size-3.5" />
                            </Link>
                        </div>
                    </Card>
                </div>

                {/* Bottom Section: Movimientos Recientes & Productos Bajo Mínimo Table */}
                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Movimientos Recientes del Kardex (2 cols) */}
                    <div className="lg:col-span-2">
                        <Card className="border-border bg-card rounded-[16px] p-5 shadow-none">
                            <div className="border-border flex items-center justify-between border-b pb-4">
                                <div className="flex items-center gap-2">
                                    <History className="text-muted-foreground size-4" />
                                    <span className="text-foreground text-[14px] font-bold">
                                        Últimos 10 movimientos del Kardex
                                    </span>
                                </div>
                                <span className="text-muted-foreground text-[11.5px] font-medium">
                                    Tiempo real
                                </span>
                            </div>

                            {movimientos_recientes.length === 0 ? (
                                <div className="flex min-h-[220px] flex-col items-center justify-center text-center">
                                    <PackageCheck className="text-muted-foreground size-10" />
                                    <p className="text-muted-foreground mt-2 text-sm font-medium">
                                        Aún no se registran movimientos en el
                                        Kardex.
                                    </p>
                                </div>
                            ) : (
                                <div className="divide-border divide-y overflow-x-auto">
                                    {movimientos_recientes.map((mov) => {
                                        const badge = getTipoMovimientoBadge(
                                            mov.tipo,
                                        );
                                        return (
                                            <div
                                                key={mov.id}
                                                className="flex flex-col gap-2 py-3 text-[12.5px] sm:flex-row sm:items-center sm:justify-between"
                                            >
                                                <div className="flex min-w-0 items-start gap-3">
                                                    <span
                                                        className={`inline-flex shrink-0 items-center rounded-[6px] border px-2 py-0.5 text-[10.5px] font-bold ${badge.className}`}
                                                    >
                                                        {badge.label}
                                                    </span>

                                                    <div className="min-w-0">
                                                        <div className="text-foreground truncate font-bold">
                                                            {
                                                                mov.producto
                                                                    .nombre
                                                            }
                                                        </div>
                                                        <div className="text-muted-foreground flex flex-wrap items-center gap-2 text-[11px]">
                                                            <span>
                                                                Cód:{" "}
                                                                {
                                                                    mov.producto
                                                                        .codigo
                                                                }
                                                            </span>
                                                            {mov.unidad_serie && (
                                                                <span className="text-foreground flex items-center gap-1 font-mono">
                                                                    <ScanBarcode className="size-3" />
                                                                    {
                                                                        mov.unidad_serie
                                                                    }
                                                                </span>
                                                            )}
                                                            {mov.sede && (
                                                                <span className="flex items-center gap-1">
                                                                    <Building2 className="size-3" />
                                                                    {mov.sede}
                                                                </span>
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>

                                                <div className="flex shrink-0 items-center justify-between gap-1 text-[11.5px] sm:flex-col sm:items-end">
                                                    <span className="text-foreground font-mono font-bold">
                                                        {Number(mov.cantidad) <
                                                        0
                                                            ? `${mov.cantidad}`
                                                            : `+${mov.cantidad}`}{" "}
                                                        <span className="text-muted-foreground text-[10px] font-normal">
                                                            {
                                                                mov.producto
                                                                    .unidad_medida
                                                            }
                                                        </span>
                                                    </span>
                                                    <div className="text-muted-foreground flex items-center gap-1 text-[10.5px]">
                                                        {mov.usuario && (
                                                            <span className="flex items-center gap-0.5">
                                                                <User className="size-3" />
                                                                {mov.usuario}
                                                            </span>
                                                        )}
                                                        <span>•</span>
                                                        <span>
                                                            {formatDate(
                                                                mov.fecha,
                                                            )}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </Card>
                    </div>

                    {/* Lista detallada de productos bajo stock mínimo (1 col) */}
                    <div className="lg:col-span-1">
                        <Card className="border-border bg-card rounded-[16px] p-5 shadow-none">
                            <div className="border-border flex items-center justify-between border-b pb-4">
                                <span className="text-foreground text-[14px] font-bold">
                                    Stock bajo el mínimo
                                </span>
                                <span className="bg-destructive/10 text-primary-strong rounded-full px-2 py-0.5 text-[11px] font-bold">
                                    {productos_bajo_minimo.length}
                                </span>
                            </div>

                            {productos_bajo_minimo.length === 0 ? (
                                <div className="flex min-h-[220px] flex-col items-center justify-center text-center">
                                    <PackageCheck className="text-success-strong size-10 dark:text-emerald-400/40" />
                                    <p className="text-success-strong mt-2 text-sm font-medium">
                                        Todos los productos cumplen con el stock
                                        mínimo.
                                    </p>
                                </div>
                            ) : (
                                <div className="divide-border divide-y">
                                    {productos_bajo_minimo.map((prod) => (
                                        <div
                                            key={prod.id}
                                            className="py-3 text-[12px]"
                                        >
                                            <div className="flex items-center justify-between">
                                                <span className="text-foreground max-w-[180px] truncate font-bold">
                                                    {prod.nombre}
                                                </span>
                                                <span className="text-primary-strong font-mono text-[11px] font-bold">
                                                    Faltan {prod.diferencia}
                                                </span>
                                            </div>
                                            <div className="text-muted-foreground mt-1 flex items-center justify-between text-[11px]">
                                                <span>Cód: {prod.codigo}</span>
                                                <span>
                                                    Disp:{" "}
                                                    <b className="text-foreground">
                                                        {prod.stock_disponible}
                                                    </b>{" "}
                                                    / Mín: {prod.stock_minimo}
                                                </span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </Card>
                    </div>
                </div>
            </div>
        </AlmacenLayout>
    );
}
