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
    tipo_afectacion_igv: string | null;
    igv_requiere_revision: boolean;
    certificate_type_id: number | null;
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
    tiposCertificado: { id: number; nombre: string }[];
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
    const { currentTeam, servicios, filters, kpis, tiposCertificado, flash } =
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
        tipo_afectacion_igv: '10',
        certificate_type_id: '',
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
            tipo_afectacion_igv: '10',
            certificate_type_id: '',
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
            tipo_afectacion_igv: service.igv_requiere_revision
                ? ''
                : (service.tipo_afectacion_igv ?? '10'),
            certificate_type_id: service.certificate_type_id
                ? String(service.certificate_type_id)
                : '',
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
                        <CheckCircle2 className="text-success-strong size-4 shrink-0" />
                        <span>{flash.success}</span>
                    </div>
                )}
                {flash?.error && (
                    <div className="flex items-center gap-2.5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        <AlertTriangle className="text-primary-strong size-4 shrink-0" />
                        <span>{flash.error}</span>
                    </div>
                )}
                {/* Cabecera y Botón Nuevo */}
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div>
                        <h1 className="text-foreground font-['Oswald',sans-serif] text-2xl font-bold tracking-wide uppercase">
                            Catálogo de Servicios Técnicos
                        </h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Mantenimiento, recarga, pruebas hidrostáticas e
                            inspecciones técnicas.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={openCreateModal}
                        className="bg-primary hover:bg-primary/90 inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold text-white shadow-xs transition-colors"
                    >
                        <Plus className="size-4" />
                        <span>Nuevo Servicio</span>
                    </button>
                </div>
                {/* KPI Cards */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div className="border-border bg-card rounded-xl border p-4 shadow-xs">
                        <div className="text-muted-foreground flex items-center justify-between text-xs">
                            <span className="font-medium uppercase">
                                Total de Servicios
                            </span>
                            <Wrench className="text-muted-foreground size-4" />
                        </div>
                        <div className="text-foreground mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold">
                            {kpis.totalServicios}
                        </div>
                    </div>
                    <div className="border-border bg-card rounded-xl border p-4 shadow-xs">
                        <div className="text-muted-foreground flex items-center justify-between text-xs">
                            <span className="font-medium uppercase">
                                Servicios Activos
                            </span>
                            <CheckCircle2 className="text-success-strong size-4" />
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-emerald-700">
                            {kpis.totalActivos}
                        </div>
                    </div>
                </div>
                {/* Barra de Filtros y Búsqueda */}
                <div className="border-border bg-card flex flex-col gap-3 rounded-xl border p-4 shadow-xs md:flex-row md:items-center md:justify-between">
                    <form
                        onSubmit={handleSearch}
                        className="flex flex-1 items-center gap-2"
                    >
                        <div className="relative flex-1">
                            <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                            <input
                                type="text"
                                value={buscar}
                                onChange={(e) => setBuscar(e.target.value)}
                                placeholder="Buscar por código o nombre..."
                                className="border-border bg-muted/40 text-foreground focus:border-primary w-full rounded-lg border py-2 pr-4 pl-9 text-sm focus:outline-none"
                            />
                        </div>
                        <button
                            type="submit"
                            className="border-border bg-card text-foreground/80 hover:bg-background rounded-lg border px-3.5 py-2 text-xs font-semibold"
                        >
                            Buscar
                        </button>
                    </form>
                    <div className="border-border bg-muted/40 inline-flex rounded-lg border p-0.5 text-xs font-medium">
                        <button
                            type="button"
                            onClick={() => handleFilterEstado('todos')}
                            className={`rounded-md px-3 py-1.5 transition-colors ${
                                filters.estado === 'todos'
                                    ? 'bg-card text-foreground font-bold shadow-xs'
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
                                    ? 'bg-card text-foreground font-bold shadow-xs'
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
                                    ? 'bg-card text-foreground font-bold shadow-xs'
                                    : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            Inactivos
                        </button>
                    </div>
                </div>
                {/* Tabla de Servicios */}
                <div className="border-border bg-card overflow-hidden rounded-xl border shadow-xs">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="border-border bg-muted/40 text-muted-foreground border-b text-[11px] font-semibold tracking-wider uppercase">
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
                            <tbody className="divide-border divide-y">
                                {servicios.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="text-muted-foreground py-8 text-center"
                                        >
                                            No se encontraron servicios técnicos
                                            registrados.
                                        </td>
                                    </tr>
                                ) : (
                                    servicios.data.map((s) => (
                                        <tr
                                            key={s.id}
                                            className="hover:bg-muted/40 transition-colors"
                                        >
                                            <td className="text-foreground px-4 py-3 font-mono font-bold">
                                                {s.codigo}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="text-foreground font-medium">
                                                    {s.nombre}
                                                </div>
                                                {s.descripcion && (
                                                    <div className="text-muted-foreground max-w-xs truncate text-[11px]">
                                                        {s.descripcion}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="text-foreground/80 px-4 py-3 font-mono">
                                                {s.unidad_medida}
                                            </td>
                                            <td className="text-foreground px-4 py-3 text-right font-mono font-semibold">
                                                {formatCurrency(s.precio_venta)}
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                <span className="border-border bg-muted/40 text-muted-foreground rounded-md border px-2 py-0.5 text-[10px] font-medium">
                                                    {s.igv_requiere_revision
                                                        ? 'Elegir afectación'
                                                        : s.tipo_afectacion_igv ===
                                                            '20'
                                                          ? 'Exonerado'
                                                          : s.tipo_afectacion_igv ===
                                                              '30'
                                                            ? 'Inafecto'
                                                            : 'Gravado (18%)'}
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
                                                        className="text-foreground/80 hover:bg-background rounded-md p-1.5 transition-colors"
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
                                                                ? 'text-warning-strong hover:bg-amber-50'
                                                                : 'text-success-strong hover:bg-emerald-50'
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
                        <div className="border-border bg-muted/40 text-muted-foreground flex items-center justify-between border-t px-4 py-3 text-xs">
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
                                                    ? 'bg-foreground text-background font-bold shadow-xs'
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
                        <div className="border-border bg-card w-full max-w-lg rounded-xl border p-6 shadow-xl">
                            <div className="border-border flex items-center justify-between border-b pb-3">
                                <h3 className="text-foreground font-['Oswald',sans-serif] text-lg font-bold uppercase">
                                    {editingService
                                        ? 'Editar Servicio'
                                        : 'Nuevo Servicio'}
                                </h3>
                                <button
                                    type="button"
                                    onClick={closeModal}
                                    className="text-muted-foreground hover:bg-background rounded-md p-1"
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
                                        <label className="text-foreground/80 block font-semibold">
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
                                            className="border-border focus:border-primary mt-1 w-full rounded-lg border px-3 py-2 font-mono uppercase focus:outline-none"
                                        />
                                        {form.errors.codigo && (
                                            <p className="mt-1 text-red-600">
                                                {form.errors.codigo}
                                            </p>
                                        )}
                                    </div>
                                    <div>
                                        <label className="text-foreground/80 block font-semibold">
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
                                            className="border-border focus:border-primary mt-1 w-full rounded-lg border px-3 py-2 font-mono focus:outline-none"
                                        />
                                    </div>
                                </div>
                                <div>
                                    <label className="text-foreground/80 block font-semibold">
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
                                        className="border-border focus:border-primary mt-1 w-full rounded-lg border px-3 py-2 focus:outline-none"
                                    />
                                    {form.errors.nombre && (
                                        <p className="mt-1 text-red-600">
                                            {form.errors.nombre}
                                        </p>
                                    )}
                                </div>
                                <div>
                                    <label className="text-foreground/80 block font-semibold">
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
                                        className="border-border focus:border-primary mt-1 w-full rounded-lg border px-3 py-2 focus:outline-none"
                                    />
                                </div>
                                <div>
                                    <label className="text-foreground/80 block font-semibold">
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
                                        className="border-border focus:border-primary mt-1 w-full rounded-lg border px-3 py-2 font-mono focus:outline-none"
                                    />
                                    {form.errors.precio_venta && (
                                        <p className="mt-1 text-red-600">
                                            {form.errors.precio_venta}
                                        </p>
                                    )}
                                </div>
                                <label className="flex flex-col gap-1">
                                    Certificado que emite
                                    <select
                                        value={form.data.certificate_type_id}
                                        onChange={(e) =>
                                            form.setData(
                                                'certificate_type_id',
                                                e.target.value,
                                            )
                                        }
                                        className="border-border rounded border p-2"
                                    >
                                        <option value="">Ninguno</option>
                                        {tiposCertificado.map((tipo) => (
                                            <option key={tipo.id} value={tipo.id}>
                                                {tipo.nombre}
                                            </option>
                                        ))}
                                    </select>
                                    {form.errors.certificate_type_id && (
                                        <span className="text-red-600">
                                            {form.errors.certificate_type_id}
                                        </span>
                                    )}
                                </label>
                                <div className="flex items-center gap-6 pt-1">
                                    <label className="flex flex-col gap-1">
                                        Afectación del IGV
                                        <select
                                            value={
                                                form.data.tipo_afectacion_igv
                                            }
                                            required
                                            onChange={(e) =>
                                                form.setData(
                                                    'tipo_afectacion_igv',
                                                    e.target.value,
                                                )
                                            }
                                            className="border-border rounded border p-2"
                                        >
                                            <option value="" disabled>
                                                Elige la afectación
                                            </option>
                                            <option value="10">
                                                Gravado (IGV incluido: 18%)
                                            </option>
                                            <option value="20">
                                                Exonerado
                                            </option>
                                            <option value="30">Inafecto</option>
                                        </select>
                                        {form.errors.tipo_afectacion_igv && (
                                            <span className="text-red-600">
                                                {
                                                    form.errors
                                                        .tipo_afectacion_igv
                                                }
                                            </span>
                                        )}
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
                                            className="border-border text-primary-strong rounded"
                                        />
                                        <span className="text-foreground/80">
                                            Activo
                                        </span>
                                    </label>
                                </div>
                                <div className="border-border flex justify-end gap-2 border-t pt-3">
                                    <button
                                        type="button"
                                        onClick={closeModal}
                                        className="border-border bg-card text-foreground/80 hover:bg-background rounded-lg border px-4 py-2 text-xs font-semibold"
                                    >
                                        Cancelar
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={form.processing}
                                        className="bg-primary hover:bg-primary/90 rounded-lg px-4 py-2 text-xs font-semibold text-white disabled:opacity-50"
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
