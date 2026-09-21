import { Head, router, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowDownRight,
    ArrowUpRight,
    Barcode,
    Boxes,
    Building2,
    Calendar,
    Clock,
    Eye,
    Filter,
    History,
    Layers,
    Package,
    RotateCcw,
    Scan,
    ScanBarcode,
    Search,
    Shield,
    Tag,
    User,
    Wrench,
    X,
} from 'lucide-react';
import { FormEvent, useState } from 'react';

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

export type UnitMovement = {
    id: number;
    tipo: string;
    cantidad: number;
    fecha: string;
    sede: string;
    usuario: string;
    observacion: string | null;
    numero_serie?: string | null;
};

export type UnitResult = {
    id: number;
    numero_serie: string;
    estado: 'disponible' | 'reservado' | 'vendido' | 'baja' | string;
    marca: string | null;
    anio_fabricacion: number | null;
    fecha_ingreso: string | null;
    sede: {
        id: number;
        nombre: string;
        tipo: string;
        ciudad: string | null;
    };
    producto: {
        id: number;
        codigo: string;
        nombre: string;
        unidad_medida: string;
        serializado: boolean;
    };
    movimientos: UnitMovement[];
};

export type ProductResult = {
    id: number;
    codigo: string;
    nombre: string;
    serializado: boolean;
    unidad_medida: string;
    stock_total: number;
    stock_por_sede: Record<number, number>;
    movimientos: UnitMovement[];
};

export type Props = {
    sedes: SedeOption[];
    filters: {
        search: string;
        sede_id: number | null;
    };
    unitResult: UnitResult | null;
    productResult: ProductResult | null;
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

function getEstadoBadge(estado: string) {
    switch (estado) {
        case 'disponible':
            return (
                <span className="inline-flex items-center gap-1 rounded-[6px] border border-[#86EFAC] bg-[#F0FDF4] px-2.5 py-1 text-xs font-bold text-[#16A34A]">
                    <span className="size-2 rounded-full bg-[#16A34A]" />
                    Disponible en Stock
                </span>
            );
        case 'reservado':
            return (
                <span className="inline-flex items-center gap-1 rounded-[6px] border border-[#FDE68A] bg-[#FEF3C7] px-2.5 py-1 text-xs font-bold text-[#D97706]">
                    <span className="size-2 rounded-full bg-[#D97706]" />
                    Reservado
                </span>
            );
        case 'vendido':
            return (
                <span className="inline-flex items-center gap-1 rounded-[6px] border border-[#BFDBFE] bg-[#EFF6FF] px-2.5 py-1 text-xs font-bold text-[#2563EB]">
                    <span className="size-2 rounded-full bg-[#2563EB]" />
                    Vendido (Equipo de Cliente)
                </span>
            );
        case 'baja':
            return (
                <span className="inline-flex items-center gap-1 rounded-[6px] border border-[#FCA5A5] bg-[#FEF2F2] px-2.5 py-1 text-xs font-bold text-[#DC2626]">
                    <span className="size-2 rounded-full bg-[#DC2626]" />
                    Dado de Baja
                </span>
            );
        default:
            return (
                <span className="inline-flex items-center gap-1 rounded-[6px] border border-[#D5D3CE] bg-[#F9F8F6] px-2.5 py-1 text-xs font-bold text-[#6B6965]">
                    {estado}
                </span>
            );
    }
}

export default function ConsultaIndex({ sedes, filters, unitResult, productResult }: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug = currentTeam?.slug ?? '';

    const [search, setSearch] = useState(filters.search || '');
    const [sedeId, setSedeId] = useState(filters.sede_id ? String(filters.sede_id) : '');

    const handleSearch = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            `/${teamSlug}/almacen/consulta`,
            {
                search: search.trim(),
                sede_id: sedeId || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    };

    const handleClear = () => {
        setSearch('');
        setSedeId('');
        router.get(`/${teamSlug}/almacen/consulta`, {}, { preserveState: true });
    };

    return (
        <AlmacenLayout title="Consulta Rápida por Serie o Código">
            <Head title="Consulta Rápida - Almacén" />

            <div className="flex flex-col gap-6 max-w-5xl mx-auto">
                {/* Title Header */}
                <div>
                    <h1 className="text-xl font-bold tracking-tight text-[#201F1D]">
                        Consulta Rápida (§84.12)
                    </h1>
                    <p className="text-xs text-[#6B6965]">
                        Escaneo y auditoría de unidades serializadas o productos a granel. 100% modo lectura.
                    </p>
                </div>

                {/* Scanner / Search Input Box */}
                <Card className="rounded-[14px] border border-[#E4E1DC] bg-white p-5 shadow-sm">
                    <form onSubmit={handleSearch} className="flex flex-col md:flex-row gap-3">
                        <div className="relative flex-1">
                            <ScanBarcode className="absolute left-3.5 top-1/2 size-5 -translate-y-1/2 text-[#A8201A]" />
                            <Input
                                autoFocus
                                type="text"
                                placeholder="Escanee el sticker de código de barras o digite BF-EQ-XXXXXX / código..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="h-11 pl-11 pr-10 text-sm font-mono tracking-wide placeholder:font-sans placeholder:text-xs"
                            />
                            {search && (
                                <button
                                    type="button"
                                    onClick={() => setSearch('')}
                                    className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                >
                                    <X className="size-4" />
                                </button>
                            )}
                        </div>

                        <div className="w-full md:w-56">
                            <select
                                value={sedeId}
                                onChange={(e) => setSedeId(e.target.value)}
                                className="h-11 w-full rounded-[9px] border border-[#D5D3CE] bg-white px-3 text-xs font-medium text-[#201F1D] focus:border-[#E31E24] focus:outline-none"
                            >
                                <option value="">Todas las sedes</option>
                                {sedes.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.nombre} ({s.tipo})
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="flex gap-2">
                            <Button
                                type="submit"
                                className="h-11 gap-2 bg-[#E31E24] px-5 text-xs font-bold text-white hover:bg-[#C0181D]"
                            >
                                <Search className="size-4" />
                                <span>Consultar</span>
                            </Button>

                            {(search || sedeId) && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={handleClear}
                                    className="h-11 text-xs"
                                >
                                    Limpiar
                                </Button>
                            )}
                        </div>
                    </form>
                </Card>

                {/* RESULTADO: UNIDAD SERIALIZADA */}
                {unitResult && (
                    <div className="space-y-6">
                        <Card className="rounded-[14px] border-2 border-[#E4E1DC] bg-white p-6 shadow-sm">
                            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-[#E4E1DC] pb-4 mb-5">
                                <div className="flex items-center gap-3">
                                    <div className="flex size-11 items-center justify-center rounded-[10px] bg-[#F4EFEB] text-[#A8201A]">
                                        <Barcode className="size-6" />
                                    </div>
                                    <div>
                                        <span className="text-[10px] font-bold uppercase tracking-wider text-[#6B6965]">
                                            Unidad Física Serializada
                                        </span>
                                        <div className="font-mono text-2xl font-black text-[#201F1D] tracking-wider">
                                            {unitResult.numero_serie}
                                        </div>
                                    </div>
                                </div>

                                <div className="flex items-center gap-2">
                                    {getEstadoBadge(unitResult.estado)}
                                </div>
                            </div>

                            {/* Detalles de la Unidad */}
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-4">
                                <div className="rounded-[9px] bg-[#FAF9F7] p-3 border border-[#E4E1DC]">
                                    <span className="text-[10.5px] font-semibold text-[#6B6965] uppercase">
                                        Producto
                                    </span>
                                    <div className="font-bold text-xs text-[#201F1D] mt-0.5">
                                        [{unitResult.producto.codigo}] {unitResult.producto.nombre}
                                    </div>
                                </div>

                                <div className="rounded-[9px] bg-[#FAF9F7] p-3 border border-[#E4E1DC]">
                                    <span className="text-[10.5px] font-semibold text-[#6B6965] uppercase">
                                        Ubicación Actual
                                    </span>
                                    <div className="font-bold text-xs text-[#201F1D] mt-0.5 flex items-center gap-1">
                                        <Building2 className="size-3.5 text-[#A8201A]" />
                                        <span>{unitResult.sede.nombre} ({unitResult.sede.tipo})</span>
                                    </div>
                                </div>

                                <div className="rounded-[9px] bg-[#FAF9F7] p-3 border border-[#E4E1DC]">
                                    <span className="text-[10.5px] font-semibold text-[#6B6965] uppercase">
                                        Marca & Año
                                    </span>
                                    <div className="font-bold text-xs text-[#201F1D] mt-0.5">
                                        {unitResult.marca || '—'} / {unitResult.anio_fabricacion || '—'}
                                    </div>
                                </div>

                                <div className="rounded-[9px] bg-[#FAF9F7] p-3 border border-[#E4E1DC]">
                                    <span className="text-[10.5px] font-semibold text-[#6B6965] uppercase">
                                        Fecha de Ingreso
                                    </span>
                                    <div className="font-bold text-xs text-[#201F1D] mt-0.5">
                                        {unitResult.fecha_ingreso || '—'}
                                    </div>
                                </div>
                            </div>

                            {/* Auditoría Kardex: Últimos 5 Movimientos */}
                            <div className="mt-6">
                                <div className="flex items-center gap-2 border-b border-[#E4E1DC] pb-2 mb-3 text-xs font-bold text-[#201F1D]">
                                    <History className="size-4 text-[#A8201A]" />
                                    <span>Historial de Kardex de esta Unidad (Últimos 5 movimientos)</span>
                                </div>

                                {unitResult.movimientos.length === 0 ? (
                                    <p className="text-xs text-[#6B6965] py-2">
                                        No hay movimientos registrados para esta unidad.
                                    </p>
                                ) : (
                                    <div className="divide-y divide-[#E4E1DC] rounded-[9px] border border-[#E4E1DC] overflow-hidden text-xs">
                                        {unitResult.movimientos.map((m) => (
                                            <div
                                                key={m.id}
                                                className="flex flex-wrap items-center justify-between gap-3 p-3 bg-white hover:bg-[#FAF9F7]"
                                            >
                                                <div className="flex items-center gap-3">
                                                    <span
                                                        className={`inline-flex items-center gap-1 rounded-[5px] px-2 py-0.5 text-[10.5px] font-black ${
                                                            m.cantidad > 0
                                                                ? 'bg-[#F0FDF4] text-[#16A34A]'
                                                                : 'bg-[#FEF2F2] text-[#DC2626]'
                                                        }`}
                                                    >
                                                        {m.tipo.toUpperCase()}
                                                    </span>
                                                    <span className="font-mono text-[11px] text-[#6B6965]">
                                                        {formatDate(m.fecha)}
                                                    </span>
                                                    <span className="text-[#201F1D]">
                                                        en <b>{m.sede}</b>
                                                    </span>
                                                </div>

                                                <div className="flex items-center gap-3 text-right">
                                                    <span className="text-[11px] text-[#6B6965] max-w-xs truncate">
                                                        {m.observacion || 'Sin observación'}
                                                    </span>
                                                    <span className="text-[10.5px] font-semibold text-[#9CA3AF]">
                                                        Por {m.usuario}
                                                    </span>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>

                            {/* Banner Informativo de frontera de rol */}
                            <div className="mt-5 rounded-[8px] border border-[#E4E1DC] bg-[#FAF9F7] p-3 text-[11.5px] text-[#6B6965] flex items-center gap-2">
                                <Shield className="size-4 text-[#A8201A] shrink-0" />
                                <span>
                                    <b>Modo Consulta Auditora:</b> Esta pantalla es estrictamente informativa.
                                    Para despachos y ventas a clientes, las operaciones se canalizan a través del rol Vendedor.
                                </span>
                            </div>
                        </Card>
                    </div>
                )}

                {/* RESULTADO: PRODUCTO A GRANEL */}
                {!unitResult && productResult && (
                    <Card className="rounded-[14px] border border-[#E4E1DC] bg-white p-6 shadow-sm">
                        <div className="flex items-center justify-between border-b border-[#E4E1DC] pb-4 mb-4">
                            <div className="flex items-center gap-3">
                                <div className="flex size-10 items-center justify-center rounded-[9px] bg-[#EFF6FF] text-[#2563EB]">
                                    <Package className="size-5" />
                                </div>
                                <div>
                                    <span className="text-[10px] font-bold uppercase tracking-wider text-[#6B6965]">
                                        Producto / Repuesto a Granel
                                    </span>
                                    <h2 className="text-lg font-black text-[#201F1D]">
                                        [{productResult.codigo}] {productResult.nombre}
                                    </h2>
                                </div>
                            </div>
                            <Badge variant="outline" className="text-xs">
                                Stock Total: {productResult.stock_total} {productResult.unidad_medida}
                            </Badge>
                        </div>

                        {/* Stock por Sede */}
                        <div className="space-y-2 mb-6">
                            <span className="text-xs font-bold text-[#201F1D]">Disponibilidad por Sede:</span>
                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                {sedes.map((s) => (
                                    <div
                                        key={s.id}
                                        className="rounded-[9px] border border-[#E4E1DC] bg-[#FAF9F7] p-3 text-center"
                                    >
                                        <div className="text-[11px] font-semibold text-[#6B6965] truncate">{s.nombre}</div>
                                        <div className="text-base font-black text-[#201F1D] mt-1">
                                            {productResult.stock_por_sede[s.id] ?? 0}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Últimos movimientos del producto */}
                        <div>
                            <span className="text-xs font-bold text-[#201F1D] block mb-2">Últimos Movimientos en Kardex:</span>
                            <div className="divide-y divide-[#E4E1DC] rounded-[9px] border border-[#E4E1DC] overflow-hidden text-xs">
                                {productResult.movimientos.map((m) => (
                                    <div key={m.id} className="flex items-center justify-between p-2.5 bg-white">
                                        <div className="flex items-center gap-2">
                                            <span className="font-mono text-[11px] text-[#6B6965]">
                                                {formatDate(m.fecha)}
                                            </span>
                                            <Badge variant="secondary" className="text-[10px]">
                                                {m.tipo}
                                            </Badge>
                                            <span>en {m.sede}</span>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <span className="font-bold">
                                                {m.cantidad > 0 ? `+${m.cantidad}` : m.cantidad}
                                            </span>
                                            <span className="text-gray-400 text-[10.5px]">{m.usuario}</span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </Card>
                )}

                {/* EMPTY STATE */}
                {!unitResult && !productResult && (
                    <Card className="rounded-[14px] border border-dashed border-[#D5D3CE] bg-[#FAF9F7] p-12 text-center">
                        <div className="mx-auto flex size-14 items-center justify-center rounded-full bg-white shadow-sm border border-[#E4E1DC] text-[#A8201A] mb-3">
                            <Scan className="size-7" />
                        </div>
                        <h3 className="text-base font-bold text-[#201F1D]">
                            {filters.search ? 'No se encontraron registros' : 'Listo para escanear o consultar'}
                        </h3>
                        <p className="mt-1 text-xs text-[#6B6965] max-w-sm mx-auto">
                            {filters.search
                                ? `No existe ninguna unidad física ni producto que coincida con "${filters.search}". Verifique el número de serie.`
                                : 'Apunte el lector de código de barras a la etiqueta del equipo o ingrese el correlativo BF-EQ-XXXXXX en el campo superior.'}
                        </p>
                    </Card>
                )}
            </div>
        </AlmacenLayout>
    );
}
