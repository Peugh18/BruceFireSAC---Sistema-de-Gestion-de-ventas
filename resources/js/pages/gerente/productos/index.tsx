import { router, useForm, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    Edit2,
    Package,
    Plus,
    Power,
    Search,
    Trash2,
    X,
} from 'lucide-react';
import { useState } from 'react';
import {
    CategoriaSelect,
    type CategoriaItem,
} from '@/components/categoria-select';
import GerenteLayout from '@/layouts/gerente-layout';
import { toast } from 'sonner';
import ProductosRoutes from '@/routes/gerente/productos';
import { useConfirmarAccion } from '@/hooks/use-confirmar-accion';
type ProductItem = {
    id: number;
    codigo: string;
    codigo_barras: string | null;
    categoria: string | null;
    agente: string | null;
    capacidad: string | null;
    peso_kg: string | null;
    nombre: string;
    descripcion: string | null;
    unidad_medida: string;
    precio_venta: number;
    aplica_igv: boolean;
    tipo_afectacion_igv: string | null;
    igv_requiere_revision: boolean;
    serializado: boolean;
    controla_lote: boolean;
    unidad_compra: string | null;
    factor_compra: number;
    stock_minimo: number | null;
    activo: boolean;
    stock_disponible: number;
};
type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};
type PaginatedProducts = {
    data: ProductItem[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    total: number;
};
type ProductFilters = {
    buscar: string;
    estado: string;
    bajo_minimo: boolean;
};
type ProductKpis = {
    totalProductos: number;
    totalActivos: number;
    totalBajoMinimo: number;
};
type PageProps = {
    currentTeam: { slug: string };
    productos: PaginatedProducts;
    filters: ProductFilters;
    kpis: ProductKpis;
    categorias: CategoriaItem[];
    unidadesMedida: { codigo: string; nombre: string }[];
    agentes: { valor: string; etiqueta: string }[];
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
export default function ProductosIndex() {
    const { pedirConfirmacion, dialogoConfirmacion } = useConfirmarAccion();
    const {
        currentTeam,
        productos,
        filters,
        kpis,
        categorias,
        unidadesMedida,
        agentes,
        flash,
    } = usePage<PageProps>().props;
    const [buscar, setBuscar] = useState(filters.buscar || '');
    const [modalOpen, setModalOpen] = useState(false);
    const [editingProduct, setEditingProduct] = useState<ProductItem | null>(
        null,
    );
    const form = useForm({
        codigo: '',
        codigo_barras: '',
        categoria: '',
        agente: '',
        capacidad: '',
        peso_kg: '',
        nombre: '',
        descripcion: '',
        unidad_medida: 'NIU',
        precio_venta: '',
        aplica_igv: true,
        tipo_afectacion_igv: '10',
        serializado: false,
        controla_lote: false,
        unidad_compra: '',
        factor_compra: '1',
        stock_minimo: '',
        activo: true,
    });
    const openCreateModal = () => {
        setEditingProduct(null);
        form.reset();
        form.setData({
            codigo: '',
            codigo_barras: '',
            categoria: '',
            agente: '',
            capacidad: '',
            peso_kg: '',
            nombre: '',
            descripcion: '',
            unidad_medida: 'NIU',
            precio_venta: '',
            aplica_igv: true,
            tipo_afectacion_igv: '10',
            serializado: false,
            controla_lote: false,
            unidad_compra: '',
            factor_compra: '1',
            stock_minimo: '',
            activo: true,
        });
        setModalOpen(true);
    };
    const openEditModal = (product: ProductItem) => {
        setEditingProduct(product);
        form.setData({
            codigo: product.codigo,
            codigo_barras: product.codigo_barras ?? '',
            categoria: product.categoria ?? '',
            agente: product.agente ?? '',
            capacidad: product.capacidad ?? '',
            peso_kg: product.peso_kg ?? '',
            nombre: product.nombre,
            descripcion: product.descripcion || '',
            unidad_medida: product.unidad_medida,
            precio_venta: String(product.precio_venta),
            aplica_igv: product.aplica_igv,
            tipo_afectacion_igv: product.igv_requiere_revision
                ? ''
                : (product.tipo_afectacion_igv ?? '10'),
            serializado: product.serializado,
            controla_lote: product.controla_lote,
            unidad_compra: product.unidad_compra ?? '',
            factor_compra: String(product.factor_compra || 1),
            stock_minimo:
                product.stock_minimo !== null
                    ? String(product.stock_minimo)
                    : '',
            activo: product.activo,
        });
        setModalOpen(true);
    };
    const closeModal = () => {
        setModalOpen(false);
        setEditingProduct(null);
        form.reset();
    };
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingProduct) {
            form.put(
                ProductosRoutes.update.url({
                    current_team: currentTeam.slug,
                    producto: editingProduct.id,
                }),
                {
                    onError: (errors) =>
                        toast.error(
                            Object.values(errors)[0] ??
                                'No se pudo completar la acción.',
                        ),
                    onSuccess: () => closeModal(),
                },
            );
        } else {
            form.post(
                ProductosRoutes.store.url({ current_team: currentTeam.slug }),
                {
                    onError: (errors) =>
                        toast.error(
                            Object.values(errors)[0] ??
                                'No se pudo completar la acción.',
                        ),
                    onSuccess: () => closeModal(),
                },
            );
        }
    };
    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            ProductosRoutes.index.url({ current_team: currentTeam.slug }),
            {
                buscar,
                estado: filters.estado,
                bajo_minimo: filters.bajo_minimo ? '1' : undefined,
            },
            { preserveState: true },
        );
    };
    const handleFilterEstado = (estado: string) => {
        router.get(
            ProductosRoutes.index.url({ current_team: currentTeam.slug }),
            {
                buscar,
                estado,
                bajo_minimo: filters.bajo_minimo ? '1' : undefined,
            },
            { preserveState: true },
        );
    };
    const handleToggleBajoMinimo = () => {
        router.get(
            ProductosRoutes.index.url({ current_team: currentTeam.slug }),
            {
                buscar,
                estado: filters.estado,
                bajo_minimo: !filters.bajo_minimo ? '1' : undefined,
            },
            { preserveState: true },
        );
    };
    const handleToggleStatus = (product: ProductItem) => {
        router.patch(
            ProductosRoutes.toggleStatus.url({
                current_team: currentTeam.slug,
                producto: product.id,
            }),
            {},
            {
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ??
                            'No se pudo completar la acción.',
                    ),
                preserveScroll: true,
            },
        );
    };
    const handleDelete = async (product: ProductItem) => {
        if (
            await pedirConfirmacion(
                `¿Desea eliminar el producto "${product.nombre}"? Si tiene historial registrado, la operación será rechazada conforme a las reglas del sistema.`,
            )
        ) {
            router.delete(
                ProductosRoutes.destroy.url({
                    current_team: currentTeam.slug,
                    producto: product.id,
                }),
                {
                    onError: (errors) =>
                        toast.error(
                            Object.values(errors)[0] ??
                                'No se pudo completar la acción.',
                        ),
                    preserveScroll: true,
                },
            );
        }
    };
    return (
        <GerenteLayout title="Catálogo de Productos">
            <div className="space-y-6">
                {dialogoConfirmacion}
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
                            Catálogo de Productos & Repuestos
                        </h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Administración de existencias, precios de venta y
                            umbrales de stock mínimo.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={openCreateModal}
                        className="bg-primary hover:bg-primary/90 inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold text-white shadow-xs transition-colors"
                    >
                        <Plus className="size-4" />
                        <span>Nuevo Producto</span>
                    </button>
                </div>
                {/* KPI Cards */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div className="border-border bg-card rounded-xl border p-4 shadow-xs">
                        <div className="text-muted-foreground flex items-center justify-between text-xs">
                            <span className="font-medium uppercase">
                                Total en Catálogo
                            </span>
                            <Package className="text-muted-foreground size-4" />
                        </div>
                        <div className="text-foreground mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold">
                            {kpis.totalProductos}
                        </div>
                    </div>
                    <div className="border-border bg-card rounded-xl border p-4 shadow-xs">
                        <div className="text-muted-foreground flex items-center justify-between text-xs">
                            <span className="font-medium uppercase">
                                Productos Activos
                            </span>
                            <CheckCircle2 className="text-success-strong size-4" />
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-emerald-700">
                            {kpis.totalActivos}
                        </div>
                    </div>
                    <div className="border-border bg-card rounded-xl border p-4 shadow-xs">
                        <div className="text-muted-foreground flex items-center justify-between text-xs">
                            <span className="font-medium uppercase">
                                Bajo Stock Mínimo
                            </span>
                            <AlertTriangle className="text-primary-strong size-4" />
                        </div>
                        <div className="text-primary-strong mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold">
                            {kpis.totalBajoMinimo}
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
                                aria-label="Buscar productos"
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
                    <div className="flex flex-wrap items-center gap-2">
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
                        <button
                            type="button"
                            onClick={handleToggleBajoMinimo}
                            className={`flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold transition-colors ${
                                filters.bajo_minimo
                                    ? 'border-primary bg-primary text-white'
                                    : 'border-border bg-card text-foreground/80 hover:bg-background'
                            }`}
                        >
                            <AlertTriangle className="size-3.5" />
                            <span>Solo bajo mínimo</span>
                        </button>
                    </div>
                </div>
                {/* Tabla de Productos */}
                <div className="border-border bg-card overflow-hidden rounded-xl border shadow-xs">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="border-border bg-muted/40 text-muted-foreground border-b text-[11px] font-semibold tracking-wider uppercase">
                                <tr>
                                    <th className="px-4 py-3">Código</th>
                                    <th className="px-4 py-3">
                                        Producto / Descripción
                                    </th>
                                    <th className="px-4 py-3">U.M.</th>
                                    <th className="px-4 py-3 text-right">
                                        Precio Venta
                                    </th>
                                    <th className="px-4 py-3 text-center">
                                        Stock Actual
                                    </th>
                                    <th className="px-4 py-3 text-center">
                                        Stock Mínimo
                                    </th>
                                    <th className="px-4 py-3 text-center">
                                        Tipo
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
                                {productos.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={9}
                                            className="text-muted-foreground py-8 text-center"
                                        >
                                            No se encontraron productos con los
                                            criterios seleccionados.
                                        </td>
                                    </tr>
                                ) : (
                                    productos.data.map((p) => {
                                        const isBajoMinimo =
                                            p.stock_minimo !== null &&
                                            p.stock_minimo > 0 &&
                                            p.stock_disponible <=
                                                p.stock_minimo;
                                        return (
                                            <tr
                                                key={p.id}
                                                className="hover:bg-muted/40 transition-colors"
                                            >
                                                <td className="text-foreground px-4 py-3 font-mono font-bold">
                                                    {p.codigo}
                                                    {p.codigo_barras ? (
                                                        <div className="text-muted-foreground text-[10.5px] font-normal">
                                                            ▮▮ {p.codigo_barras}
                                                        </div>
                                                    ) : null}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="text-foreground font-medium">
                                                        {p.nombre}
                                                    </div>
                                                    {p.descripcion && (
                                                        <div className="text-muted-foreground max-w-xs truncate text-[11px]">
                                                            {p.descripcion}
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="text-foreground/80 px-4 py-3 font-mono">
                                                    {p.unidad_medida}
                                                </td>
                                                <td className="text-foreground px-4 py-3 text-right font-mono font-semibold">
                                                    {formatCurrency(
                                                        p.precio_venta,
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-center">
                                                    <span
                                                        className={`inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 font-mono text-[11px] font-bold ${
                                                            isBajoMinimo
                                                                ? 'text-primary-strong bg-red-100'
                                                                : 'bg-background text-foreground'
                                                        }`}
                                                    >
                                                        {isBajoMinimo && (
                                                            <AlertTriangle className="text-primary-strong size-3" />
                                                        )}
                                                        {p.stock_disponible}
                                                    </span>
                                                </td>
                                                <td className="text-foreground/80 px-4 py-3 text-center font-mono">
                                                    {p.stock_minimo !== null
                                                        ? p.stock_minimo
                                                        : '—'}
                                                </td>
                                                <td className="px-4 py-3 text-center">
                                                    <span className="border-border bg-muted/40 text-muted-foreground rounded-md border px-2 py-0.5 text-[10px] font-medium">
                                                        {p.serializado
                                                            ? 'Serializado'
                                                            : 'A Granel'}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 text-center">
                                                    <span
                                                        className={`inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold uppercase ${
                                                            p.activo
                                                                ? 'bg-emerald-100 text-emerald-800'
                                                                : 'bg-zinc-100 text-zinc-600'
                                                        }`}
                                                    >
                                                        {p.activo
                                                            ? 'Activo'
                                                            : 'Inactivo'}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <div className="flex items-center justify-end gap-1.5">
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                openEditModal(p)
                                                            }
                                                            className="text-foreground/80 hover:bg-background rounded-md p-1.5 transition-colors"
                                                            title="Editar producto y stock mínimo"
                                                        >
                                                            <Edit2 className="size-3.5" />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                handleToggleStatus(
                                                                    p,
                                                                )
                                                            }
                                                            className={`rounded-md p-1.5 transition-colors ${
                                                                p.activo
                                                                    ? 'text-warning-strong hover:bg-amber-50'
                                                                    : 'text-success-strong hover:bg-emerald-50'
                                                            }`}
                                                            title={
                                                                p.activo
                                                                    ? 'Desactivar producto'
                                                                    : 'Activar producto'
                                                            }
                                                        >
                                                            <Power className="size-3.5" />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                handleDelete(p)
                                                            }
                                                            className="rounded-md p-1.5 text-red-600 transition-colors hover:bg-red-50"
                                                            title="Eliminar producto"
                                                        >
                                                            <Trash2 className="size-3.5" />
                                                        </button>
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
                    {productos.links && productos.links.length > 3 && (
                        <div className="border-border bg-muted/40 text-muted-foreground flex items-center justify-between border-t px-4 py-3 text-xs">
                            <div>Total: {productos.total} productos</div>
                            <div className="flex items-center gap-1">
                                {productos.links.map((link, i) => {
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
                {/* Modal Crear / Editar Producto */}
                {modalOpen && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                        <div className="border-border bg-card w-full max-w-lg rounded-xl border p-6 shadow-xl">
                            <div className="border-border flex items-center justify-between border-b pb-3">
                                <h3 className="text-foreground font-['Oswald',sans-serif] text-lg font-bold uppercase">
                                    {editingProduct
                                        ? 'Editar Producto / Stock Mínimo'
                                        : 'Nuevo Producto'}
                                </h3>
                                <button
                                    type="button"
                                    onClick={closeModal}
                                    aria-label="Cerrar"
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
                                            Código de barras del fabricante
                                        </label>
                                        <input
                                            aria-label="Código de barras del fabricante"
                                            type="text"
                                            value={form.data.codigo_barras}
                                            onChange={(e) =>
                                                form.setData(
                                                    'codigo_barras',
                                                    e.target.value.trim(),
                                                )
                                            }
                                            placeholder="Escanéalo aquí (EAN)"
                                            className="border-border focus:border-primary mt-1 w-full rounded-lg border px-3 py-2 font-mono focus:outline-none"
                                        />
                                        <p className="text-muted-foreground mt-1">
                                            Para EPP y repuestos que ya traen
                                            código: luego se escanea al recibir
                                            y al vender.
                                        </p>
                                        {form.errors.codigo_barras && (
                                            <p className="mt-1 text-red-600">
                                                {form.errors.codigo_barras}
                                            </p>
                                        )}
                                    </div>
                                    <CategoriaSelect
                                        value={form.data.categoria}
                                        onChange={(clave) =>
                                            form.setData('categoria', clave)
                                        }
                                        categorias={categorias}
                                    />
                                </div>
                                {categorias.find(
                                    (c) => c.clave === form.data.categoria,
                                )?.genera_alertas_vencimiento ? (
                                    <div className="grid grid-cols-2 gap-3">
                                        <div>
                                            <label
                                                htmlFor="producto-agente"
                                                className="text-foreground/80 block font-semibold"
                                            >
                                                Agente extintor
                                            </label>
                                            <select
                                                id="producto-agente"
                                                value={form.data.agente}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'agente',
                                                        e.target.value,
                                                    )
                                                }
                                                className="border-border focus:border-primary mt-1 w-full rounded-lg border px-3 py-2 focus:outline-none"
                                            >
                                                <option value="">
                                                    Sin registrar
                                                </option>
                                                {agentes.map((agente) => (
                                                    <option
                                                        key={agente.valor}
                                                        value={agente.valor}
                                                    >
                                                        {agente.etiqueta}
                                                    </option>
                                                ))}
                                            </select>
                                            <p className="text-muted-foreground mt-1">
                                                Sin agente no se emite el
                                                certificado del extintor.
                                            </p>
                                            {form.errors.agente && (
                                                <p className="mt-1 text-red-600">
                                                    {form.errors.agente}
                                                </p>
                                            )}
                                        </div>
                                        <div>
                                            <label
                                                htmlFor="producto-capacidad"
                                                className="text-foreground/80 block font-semibold"
                                            >
                                                Capacidad
                                            </label>
                                            <input
                                                id="producto-capacidad"
                                                type="text"
                                                maxLength={20}
                                                value={form.data.capacidad}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'capacidad',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Ej. 6 kg"
                                                className="border-border focus:border-primary mt-1 w-full rounded-lg border px-3 py-2 focus:outline-none"
                                            />
                                            {form.errors.capacidad && (
                                                <p className="mt-1 text-red-600">
                                                    {form.errors.capacidad}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                ) : null}
                                <div>
                                    <label
                                        htmlFor="producto-peso"
                                        className="text-foreground/80 block font-semibold"
                                    >
                                        Peso unitario (kg)
                                    </label>
                                    <input
                                        id="producto-peso"
                                        type="number"
                                        step="0.001"
                                        min="0"
                                        value={form.data.peso_kg}
                                        onChange={(e) =>
                                            form.setData(
                                                'peso_kg',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Para el peso de la guía de remisión"
                                        className="border-border focus:border-primary mt-1 w-full rounded-lg border px-3 py-2 focus:outline-none"
                                    />
                                    {form.errors.peso_kg && (
                                        <p className="mt-1 text-red-600">
                                            {form.errors.peso_kg}
                                        </p>
                                    )}
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="text-foreground/80 block font-semibold">
                                            Código *
                                        </label>
                                        <input
                                            aria-label="Código *"
                                            type="text"
                                            required
                                            value={form.data.codigo}
                                            onChange={(e) =>
                                                form.setData(
                                                    'codigo',
                                                    e.target.value.toUpperCase(),
                                                )
                                            }
                                            placeholder="EXT-PQS-6KG"
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
                                        <select
                                            aria-label="U.M. *"
                                            value={form.data.unidad_medida}
                                            onChange={(e) =>
                                                form.setData(
                                                    'unidad_medida',
                                                    e.target.value,
                                                )
                                            }
                                            className="border-border focus:border-primary mt-1 w-full rounded-lg border px-3 py-2 focus:outline-none"
                                        >
                                            {unidadesMedida.map((u) => (
                                                <option
                                                    key={u.codigo}
                                                    value={u.codigo}
                                                >
                                                    {u.codigo} ({u.nombre})
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <label className="text-foreground/80 block font-semibold">
                                        Nombre del Producto *
                                    </label>
                                    <input
                                        aria-label="Nombre del Producto *"
                                        type="text"
                                        required
                                        value={form.data.nombre}
                                        onChange={(e) =>
                                            form.setData(
                                                'nombre',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Extintor PQS 6kg con manómetro"
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
                                        aria-label="Descripción"
                                        rows={2}
                                        value={form.data.descripcion}
                                        onChange={(e) =>
                                            form.setData(
                                                'descripcion',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Detalles técnicos, agente extintor, norma NTP..."
                                        className="border-border focus:border-primary mt-1 w-full rounded-lg border px-3 py-2 focus:outline-none"
                                    />
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="text-foreground/80 block font-semibold">
                                            Precio Venta (S/) *
                                        </label>
                                        <input
                                            aria-label="Precio Venta (S/) *"
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
                                            placeholder="120.00"
                                            className="border-border focus:border-primary mt-1 w-full rounded-lg border px-3 py-2 font-mono focus:outline-none"
                                        />
                                        {form.errors.precio_venta && (
                                            <p className="mt-1 text-red-600">
                                                {form.errors.precio_venta}
                                            </p>
                                        )}
                                    </div>
                                    <div>
                                        <label className="text-foreground/80 block font-semibold">
                                            Stock Mínimo (Alerta Almacén)
                                        </label>
                                        <input
                                            aria-label="Stock Mínimo (Alerta Almacén)"
                                            type="number"
                                            min="0"
                                            value={form.data.stock_minimo}
                                            onChange={(e) =>
                                                form.setData(
                                                    'stock_minimo',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="5"
                                            className="border-border focus:border-primary mt-1 w-full rounded-lg border px-3 py-2 font-mono focus:outline-none"
                                        />
                                        <p className="text-muted-foreground mt-0.5 text-[10px]">
                                            Alerta a Almacén si stock disponible
                                            es menor o igual.
                                        </p>
                                    </div>
                                </div>
                                {!form.data.serializado && (
                                    <div className="grid grid-cols-2 gap-3">
                                        <div>
                                            <label className="text-foreground/80 block font-semibold">
                                                Se compra por (opcional)
                                            </label>
                                            <input
                                                aria-label="Se compra por (opcional)"
                                                type="text"
                                                maxLength={10}
                                                value={form.data.unidad_compra}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'unidad_compra',
                                                        e.target.value.toUpperCase(),
                                                    )
                                                }
                                                placeholder="CAJA"
                                                className="border-border focus:border-primary mt-1 w-full rounded-lg border px-3 py-2 focus:outline-none"
                                            />
                                        </div>
                                        <div>
                                            <label className="text-foreground/80 block font-semibold">
                                                ¿Cuántas trae cada una?
                                            </label>
                                            <input
                                                aria-label="¿Cuántas trae cada una?"
                                                type="number"
                                                min="1"
                                                disabled={
                                                    !form.data.unidad_compra
                                                }
                                                value={form.data.factor_compra}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'factor_compra',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="100"
                                                className="border-border focus:border-primary mt-1 w-full rounded-lg border px-3 py-2 font-mono focus:outline-none disabled:opacity-50"
                                            />
                                            <p className="text-muted-foreground mt-0.5 text-[10px]">
                                                Ej.: 1 CAJA = 100 UND. En
                                                Recepciones se cuentan cajas y
                                                el stock sube en unidades.
                                            </p>
                                        </div>
                                    </div>
                                )}
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
                                            checked={form.data.serializado}
                                            onChange={(e) =>
                                                form.setData((datos) => ({
                                                    ...datos,
                                                    serializado:
                                                        e.target.checked,
                                                    controla_lote: e.target
                                                        .checked
                                                        ? false
                                                        : datos.controla_lote,
                                                }))
                                            }
                                            className="border-border text-primary-strong rounded"
                                        />
                                        <span className="text-foreground/80">
                                            Control por Serie (Serializado)
                                        </span>
                                    </label>
                                    <label className="flex cursor-pointer items-center gap-2">
                                        <input
                                            type="checkbox"
                                            checked={form.data.controla_lote}
                                            disabled={form.data.serializado}
                                            onChange={(e) =>
                                                form.setData(
                                                    'controla_lote',
                                                    e.target.checked,
                                                )
                                            }
                                            className="border-border text-primary-strong rounded"
                                        />
                                        <span className="text-foreground/80">
                                            Lote y vencimiento (EPP)
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
                                        {editingProduct
                                            ? 'Guardar Cambios'
                                            : 'Crear Producto'}
                                    </button>
                                    {Object.values(form.errors)[0] ? (
                                        <p
                                            className="text-destructive-strong text-[11px] font-semibold"
                                            role="alert"
                                        >
                                            {Object.values(form.errors)[0]}
                                        </p>
                                    ) : null}
                                </div>
                            </form>
                        </div>
                    </div>
                )}
            </div>
        </GerenteLayout>
    );
}
