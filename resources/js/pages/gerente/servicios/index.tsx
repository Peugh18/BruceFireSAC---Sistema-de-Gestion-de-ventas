import { router, useForm, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    Edit2,
    Plus,
    Power,
    Search,
    Trash2,
    Wrench,
    X,
} from 'lucide-react';
import { useState } from 'react';

import GerenteLayout from '@/layouts/gerente-layout';

type ServiceItem = {
    id: number;
    codigo: string;
    nombre: string;
    descripcion: string | null;
    unidad_medida: string;
    precio_venta: number;
    aplica_igv: boolean;
    activo: boolean;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedServices = {
    data: ServiceItem[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    total: number;
};

type ServiceFilters = {
    buscar: string;
    estado: string;
};

type ServiceKpis = {
    totalServicios: number;
    totalActivos: number;
};

type PageProps = {
    currentTeam: { slug: string };
    servicios: PaginatedServices;
    filters: ServiceFilters;
    kpis: ServiceKpis;
    flash?: {
        success?: string;
        error?: string;
    };
    [key: string]: unknown;
};

function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('es-PE', {
        style: 'currency',
        currency: 'PEN',
        minimumFractionDigits: 2,
    }).format(amount);
}

export default function ServiciosIndex() {
    const { currentTeam, servicios, filters, kpis, flash } =
        usePage<PageProps>().props;

    const [buscar, setBuscar] = useState(filters.buscar || '');
    const [modalOpen, setModalOpen] = useState(false);
    const [editingService, setEditingService] = useState<ServiceItem | null>(
        null,
    );

    const form = useForm({
        codigo: '',
        nombre: '',
        descripcion: '',
        unidad_medida: 'ZZ',
        precio_venta: '',
        aplica_igv: true,
        activo: true,
    });

    const openCreateModal = () => {
        setEditingService(null);
        form.reset();
        form.setData({
            codigo: '',
            nombre: '',
            descripcion: '',
            unidad_medida: 'ZZ',
            precio_venta: '',
            aplica_igv: true,
            activo: true,
        });
        setModalOpen(true);
    };

    const openEditModal = (service: ServiceItem) => {
        setEditingService(service);
        form.setData({
            codigo: service.codigo,
            nombre: service.nombre,
            descripcion: service.descripcion || '',
            unidad_medida: service.unidad_medida,
            precio_venta: String(service.precio_venta),
            aplica_igv: service.aplica_igv,
            activo: service.activo,
        });
        setModalOpen(true);
    };

    const closeModal = () => {
        setModalOpen(false);
        setEditingService(null);
        form.reset();
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingService) {
            form.put(
                `/${currentTeam.slug}/gerente/servicios/${editingService.id}`,
                {
                    onSuccess: () => closeModal(),
                },
            );
        } else {
            form.post(`/${currentTeam.slug}/gerente/servicios`, {
                onSuccess: () => closeModal(),
            });
        }
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            `/${currentTeam.slug}/gerente/servicios`,
            {
                buscar,
                estado: filters.estado,
            },
            { preserveState: true },
        );
    };

    const handleFilterEstado = (estado: string) => {
        router.get(
            `/${currentTeam.slug}/gerente/servicios`,
            {
                buscar,
                estado,
            },
            { preserveState: true },
        );
    };

    const handleToggleStatus = (service: ServiceItem) => {
        router.patch(
            `/${currentTeam.slug}/gerente/servicios/${service.id}/toggle-status`,
            {},
            { preserveScroll: true },
        );
    };

    const handleDelete = (service: ServiceItem) => {
        if (
            confirm(
                `¿Desea eliminar el servicio "${service.nombre}"? Si tiene historial registrado, la operación será rechazada conforme a las reglas del sistema.`,
            )
        ) {
            router.delete(
                `/${currentTeam.slug}/gerente/servicios/${service.id}`,
                {
                    preserveScroll: true,
                },
            );
        }
    };

    return (
        <GerenteLayout title="Catálogo de Servicios">
            <div className="space-y-6">
                {/* Alertas Flash */}
                {flash?.success && (
                    <div className="flex items-center gap-2.5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        <CheckCircle2 className="size-4 shrink-0 text-emerald-600" />
                        <span>{flash.success}</span>
                    </div>
                )}
                {flash?.error && (
                    <div className="flex items-center gap-2.5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        <AlertTriangle className="size-4 shrink-0 text-primary" />
                        <span>{flash.error}</span>
                    </div>
                )}

                {/* Cabecera y Botón Nuevo */}
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div>
                        <h1 className="font-['Oswald',sans-serif] text-2xl font-bold tracking-wide text-foreground uppercase">
                            Catálogo de Servicios Técnicos
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Mantenimiento, recarga, pruebas hidrostáticas e
                            inspecciones técnicas.
                        </p>
                    </div>

                    <button
                        type="button"
                        onClick={openCreateModal}
                        className="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white shadow-xs transition-colors hover:bg-primary/90"
                    >
                        <Plus className="size-4" />
                        <span>Nuevo Servicio</span>
                    </button>
                </div>

                {/* KPI Cards */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between text-xs text-muted-foreground">
                            <span className="font-medium uppercase">
                                Total de Servicios
                            </span>
                            <Wrench className="size-4 text-muted-foreground" />
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-foreground">
                            {kpis.totalServicios}
                        </div>
                    </div>

                    <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between text-xs text-muted-foreground">
                            <span className="font-medium uppercase">
                                Servicios Activos
                            </span>
                            <CheckCircle2 className="size-4 text-emerald-600" />
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-emerald-700">
                            {kpis.totalActivos}
                        </div>
                    </div>
                </div>

                {/* Barra de Filtros y Búsqueda */}
                <div className="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 shadow-xs md:flex-row md:items-center md:justify-between">
                    <form
                        onSubmit={handleSearch}
                        className="flex flex-1 items-center gap-2"
                    >
                        <div className="relative flex-1">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <input
                                type="text"
                                value={buscar}
                                onChange={(e) => setBuscar(e.target.value)}
                                placeholder="Buscar por código o nombre..."
                                className="w-full rounded-lg border border-border bg-muted/40 py-2 pr-4 pl-9 text-sm text-foreground focus:border-primary focus:outline-none"
                            />
                        </div>
                        <button
                            type="submit"
                            className="rounded-lg border border-border bg-card px-3.5 py-2 text-xs font-semibold text-foreground/80 hover:bg-background"
                        >
                            Buscar
                        </button>
                    </form>

                    <div className="inline-flex rounded-lg border border-border bg-muted/40 p-0.5 text-xs font-medium">
                        <button
                            type="button"
                            onClick={() => handleFilterEstado('todos')}
                            className={`rounded-md px-3 py-1.5 transition-colors ${
                                filters.estado === 'todos'
                                    ? 'bg-card font-bold text-foreground shadow-xs'
                                    : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            Todos
                        </button>
                        <button
                            type="button"
                            onClick={() => handleFilterEstado('activos')}
                            className={`rounded-md px-3 py-1.5 transition-colors ${
                                filters.estado === 'activos'
                                    ? 'bg-card font-bold text-foreground shadow-xs'
                                    : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            Activos
                        </button>
                        <button
                            type="button"
                            onClick={() => handleFilterEstado('inactivos')}
                            className={`rounded-md px-3 py-1.5 transition-colors ${
                                filters.estado === 'inactivos'
                                    ? 'bg-card font-bold text-foreground shadow-xs'
                                    : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            Inactivos
                        </button>
                    </div>
                </div>

                {/* Tabla de Servicios */}
                <div className="overflow-hidden rounded-xl border border-border bg-card shadow-xs">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="border-b border-border bg-muted/40 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-3">Código</th>
                                    <th className="px-4 py-3">
                                        Servicio / Descripción
                                    </th>
                                    <th className="px-4 py-3">U.M.</th>
                                    <th className="px-4 py-3 text-right">
                                        Precio Venta
                                    </th>
                                    <th className="px-4 py-3 text-center">
                                        IGV
                                    </th>
                                    <th className="px-4 py-3 text-center">
                                        Estado
                                    </th>
                                    <th className="px-4 py-3 text-right">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {servicios.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="py-8 text-center text-muted-foreground"
                                        >
                                            No se encontraron servicios técnicos
                                            registrados.
                                        </td>
                                    </tr>
                                ) : (
                                    servicios.data.map((s) => (
                                        <tr
                                            key={s.id}
                                            className="transition-colors hover:bg-muted/40"
                                        >
                                            <td className="px-4 py-3 font-mono font-bold text-foreground">
                                                {s.codigo}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="font-medium text-foreground">
                                                    {s.nombre}
                                                </div>
                                                {s.descripcion && (
                                                    <div className="max-w-xs truncate text-[11px] text-muted-foreground">
                                                        {s.descripcion}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 font-mono text-foreground/80">
                                                {s.unidad_medida}
                                            </td>
                                            <td className="px-4 py-3 text-right font-mono font-semibold text-foreground">
                                                {formatCurrency(s.precio_venta)}
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                <span className="rounded-md border border-border bg-muted/40 px-2 py-0.5 text-[10px] font-medium text-muted-foreground">
                                                    {s.aplica_igv
                                                        ? 'Aplica (18%)'
                                                        : 'Exonerado'}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                <span
                                                    className={`inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold uppercase ${
                                                        s.activo
                                                            ? 'bg-emerald-100 text-emerald-800'
                                                            : 'bg-zinc-100 text-zinc-600'
                                                    }`}
                                                >
                                                    {s.activo
                                                        ? 'Activo'
                                                        : 'Inactivo'}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex items-center justify-end gap-1.5">
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            openEditModal(s)
                                                        }
                                                        className="rounded-md p-1.5 text-foreground/80 transition-colors hover:bg-background"
                                                        title="Editar servicio"
                                                    >
                                                        <Edit2 className="size-3.5" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            handleToggleStatus(
                                                                s,
                                                            )
                                                        }
                                                        className={`rounded-md p-1.5 transition-colors ${
                                                            s.activo
                                                                ? 'text-amber-600 hover:bg-amber-50'
                                                                : 'text-emerald-600 hover:bg-emerald-50'
                                                        }`}
                                                        title={
                                                            s.activo
                                                                ? 'Desactivar servicio'
                                                                : 'Activar servicio'
                                                        }
                                                    >
                                                        <Power className="size-3.5" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            handleDelete(s)
                                                        }
                                                        className="rounded-md p-1.5 text-red-600 transition-colors hover:bg-red-50"
                                                        title="Eliminar servicio"
                                                    >
                                                        <Trash2 className="size-3.5" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Paginación */}
                    {servicios.links && servicios.links.length > 3 && (
                        <div className="flex items-center justify-between border-t border-border bg-muted/40 px-4 py-3 text-xs text-muted-foreground">
                            <div>Total: {servicios.total} servicios</div>
                            <div className="flex items-center gap-1">
                                {servicios.links.map((link, i) => {
                                    if (!link.url) {
                                        return (
                                            <span
                                                key={i}
                                                dangerouslySetInnerHTML={{
                                                    __html: link.label,
                                                }}
                                                className="px-2.5 py-1 text-zinc-400"
                                            />
                                        );
                                    }
                                    return (
                                        <button
                                            key={i}
                                            type="button"
                                            onClick={() =>
                                                router.get(
                                                    link.url!,
                                                    {},
                                                    { preserveState: true },
                                                )
                                            }
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                            className={`rounded-md px-2.5 py-1 text-xs transition-colors ${
                                                link.active
                                                    ? 'bg-card font-bold text-white'
                                                    : 'text-foreground/80 hover:bg-muted/40'
                                            }`}
                                        />
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </div>

                {/* Modal Crear / Editar Servicio */}
                {modalOpen && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                        <div className="w-full max-w-lg rounded-xl border border-border bg-card p-6 shadow-xl">
                            <div className="flex items-center justify-between border-b border-border pb-3">
                                <h3 className="font-['Oswald',sans-serif] text-lg font-bold text-foreground uppercase">
                                    {editingService
                                        ? 'Editar Servicio'
                                        : 'Nuevo Servicio'}
                                </h3>
                                <button
                                    type="button"
                                    onClick={closeModal}
                                    className="rounded-md p-1 text-muted-foreground hover:bg-background"
                                >
                                    <X className="size-4" />
                                </button>
                            </div>

                            <form
                                onSubmit={handleSubmit}
                                className="mt-4 space-y-4 text-xs"
                            >
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="block font-semibold text-foreground/80">
                                            Código *
                                        </label>
                                        <input
                                            type="text"
                                            required
                                            value={form.data.codigo}
                                            onChange={(e) =>
                                                form.setData(
                                                    'codigo',
                                                    e.target.value.toUpperCase(),
                                                )
                                            }
                                            placeholder="SRV-REC-PQS"
                                            className="mt-1 w-full rounded-lg border border-border px-3 py-2 font-mono uppercase focus:border-primary focus:outline-none"
                                        />
                                        {form.errors.codigo && (
                                            <p className="mt-1 text-red-600">
                                                {form.errors.codigo}
                                            </p>
                                        )}
                                    </div>

                                    <div>
                                        <label className="block font-semibold text-foreground/80">
                                            U.M. *
                                        </label>
                                        <input
                                            type="text"
                                            required
                                            value={form.data.unidad_medida}
                                            onChange={(e) =>
                                                form.setData(
                                                    'unidad_medida',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="ZZ"
                                            className="mt-1 w-full rounded-lg border border-border px-3 py-2 font-mono focus:border-primary focus:outline-none"
                                        />
                                    </div>
                                </div>

                                <div>
                                    <label className="block font-semibold text-foreground/80">
                                        Nombre del Servicio *
                                    </label>
                                    <input
                                        type="text"
                                        required
                                        value={form.data.nombre}
                                        onChange={(e) =>
                                            form.setData(
                                                'nombre',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Recarga y Mantenimiento PQS 6kg"
                                        className="mt-1 w-full rounded-lg border border-border px-3 py-2 focus:border-primary focus:outline-none"
                                    />
                                    {form.errors.nombre && (
                                        <p className="mt-1 text-red-600">
                                            {form.errors.nombre}
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <label className="block font-semibold text-foreground/80">
                                        Descripción
                                    </label>
                                    <textarea
                                        rows={2}
                                        value={form.data.descripcion}
                                        onChange={(e) =>
                                            form.setData(
                                                'descripcion',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Detalle de procedimiento técnico, incluye certificado..."
                                        className="mt-1 w-full rounded-lg border border-border px-3 py-2 focus:border-primary focus:outline-none"
                                    />
                                </div>

                                <div>
                                    <label className="block font-semibold text-foreground/80">
                                        Precio Venta (S/) *
                                    </label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        required
                                        value={form.data.precio_venta}
                                        onChange={(e) =>
                                            form.setData(
                                                'precio_venta',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="45.00"
                                        className="mt-1 w-full rounded-lg border border-border px-3 py-2 font-mono focus:border-primary focus:outline-none"
                                    />
                                    {form.errors.precio_venta && (
                                        <p className="mt-1 text-red-600">
                                            {form.errors.precio_venta}
                                        </p>
                                    )}
                                </div>

                                <div className="flex items-center gap-6 pt-1">
                                    <label className="flex cursor-pointer items-center gap-2">
                                        <input
                                            type="checkbox"
                                            checked={form.data.aplica_igv}
                                            onChange={(e) =>
                                                form.setData(
                                                    'aplica_igv',
                                                    e.target.checked,
                                                )
                                            }
                                            className="rounded border-border text-primary"
                                        />
                                        <span className="text-foreground/80">
                                            Aplica IGV (18%)
                                        </span>
                                    </label>

                                    <label className="flex cursor-pointer items-center gap-2">
                                        <input
                                            type="checkbox"
                                            checked={form.data.activo}
                                            onChange={(e) =>
                                                form.setData(
                                                    'activo',
                                                    e.target.checked,
                                                )
                                            }
                                            className="rounded border-border text-primary"
                                        />
                                        <span className="text-foreground/80">
                                            Activo
                                        </span>
                                    </label>
                                </div>

                                <div className="flex justify-end gap-2 border-t border-border pt-3">
                                    <button
                                        type="button"
                                        onClick={closeModal}
                                        className="rounded-lg border border-border bg-card px-4 py-2 text-xs font-semibold text-foreground/80 hover:bg-background"
                                    >
                                        Cancelar
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={form.processing}
                                        className="rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-white hover:bg-primary/90 disabled:opacity-50"
                                    >
                                        {editingService
                                            ? 'Guardar Cambios'
                                            : 'Crear Servicio'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}
            </div>
        </GerenteLayout>
    );
}
