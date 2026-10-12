import { router, useForm, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    Edit2,
    Package,
    Plus,
    Power,
    Search,
    Inbox,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    CategoriaSelect,
    type CategoriaItem,
} from '@/components/categoria-select';
import GerenteLayout from '@/layouts/gerente-layout';
import { toast } from 'sonner';
import ProductosRoutes from '@/routes/gerente/productos';
import { useConfirmarAccion } from '@/hooks/use-confirmar-accion';
import { NativeSelect } from '@/components/ui/native-select';
import { Empty } from '@/components/ui/empty';
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
    siguienteCodigo: string;
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
const emptyProduct = {
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
};
const productControl =
    'border-border bg-background w-full min-h-11 rounded-lg border px-3 py-2 text-sm focus-visible:outline-2 focus-visible:outline-primary focus-visible:outline-offset-2 disabled:bg-muted disabled:opacity-60 aria-invalid:border-red-600';

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
        siguienteCodigo,
        flash,
    } = usePage<PageProps>().props;
    const [buscar, setBuscar] = useState(filters.buscar || '');
    const [modalOpen, setModalOpen] = useState(false);
    const [editingProduct, setEditingProduct] = useState<ProductItem | null>(
        null,
    );
    const form = useForm(emptyProduct);
    const [advancedOpen, setAdvancedOpen] = useState(false);
    const productTrigger = useRef<HTMLElement | null>(null);
    const inventoryControl = form.data.serializado
        ? 'serie'
        : form.data.controla_lote
          ? 'lote'
          : 'cantidad';

    useEffect(() => {
        if (!modalOpen || !form.isDirty) return;
        const warnBeforeLeaving = (event: BeforeUnloadEvent) => {
            event.preventDefault();
        };
        window.addEventListener('beforeunload', warnBeforeLeaving);
        return () =>
            window.removeEventListener('beforeunload', warnBeforeLeaving);
    }, [modalOpen, form.isDirty]);

    const openCreateModal = () => {
        productTrigger.current = document.activeElement as HTMLElement;
        setEditingProduct(null);
        const nuevo = { ...emptyProduct, codigo: siguienteCodigo };
        form.setDefaults(nuevo);
        form.setData(nuevo);
        form.clearErrors();
        setAdvancedOpen(false);
        setModalOpen(true);
    };
    const openEditModal = (product: ProductItem) => {
        productTrigger.current = document.activeElement as HTMLElement;
        setEditingProduct(product);
        const data = {
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
        };
        form.setDefaults(data);
        form.setData(data);
        form.clearErrors();
        setAdvancedOpen(false);
        setModalOpen(true);
    };
    const closeModal = () => {
        setModalOpen(false);
        setEditingProduct(null);
        form.reset();
        form.clearErrors();
    };
    const requestCloseModal = async () => {
        if (form.processing) return;
        if (
            form.isDirty &&
            !(await pedirConfirmacion(
                'Hay cambios sin guardar. ¿Quieres descartarlos y cerrar el formulario?',
            ))
        )
            return;
        closeModal();
    };
    const showProductErrors = (errors: Record<string, string>) => {
        const extraFields = [
            'descripcion',
            'codigo_barras',
            'peso_kg',
            'unidad_compra',
            'factor_compra',
            'activo',
        ];
        if (Object.keys(errors).some((key) => extraFields.includes(key)))
            setAdvancedOpen(true);
        requestAnimationFrame(() =>
            requestAnimationFrame(() => {
                const key = Object.keys(errors)[0];
                const id =
                    key === 'categoria'
                        ? 'categoria-select'
                        : key === 'controla_lote'
                          ? 'producto-serializado'
                          : `producto-${key}`;
                const field = document.getElementById(id);
                field?.focus();
                field?.scrollIntoView({ block: 'nearest' });
            }),
        );
    };
    const handleSubmit = (event: React.FormEvent) => {
        event.preventDefault();
        const options = {
            onError: showProductErrors,
            onSuccess: () => closeModal(),
        };
        if (editingProduct) {
            form.put(
                ProductosRoutes.update.url({
                    current_team: currentTeam.slug,
                    producto: editingProduct.id,
                }),
                options,
            );
        } else {
            form.post(
                ProductosRoutes.store.url({ current_team: currentTeam.slug }),
                options,
            );
        }
    };
    const productField = (
        name:
            | 'nombre'
            | 'codigo'
            | 'precio_venta'
            | 'capacidad'
            | 'stock_minimo'
            | 'codigo_barras'
            | 'peso_kg'
            | 'unidad_compra'
            | 'factor_compra',
        label: string,
        options: React.InputHTMLAttributes<HTMLInputElement> = {},
        hint?: string,
    ) => (
        <div>
            <label
                htmlFor={`producto-${name}`}
                className="mb-1.5 block font-medium"
            >
                {label}
                {options.required ? ' *' : ''}
            </label>
            <input
                {...options}
                id={`producto-${name}`}
                name={name}
                autoComplete="off"
                value={form.data[name]}
                onChange={(event) =>
                    form.setData(
                        name,
                        ['codigo', 'unidad_compra'].includes(name)
                            ? event.target.value.toUpperCase()
                            : event.target.value,
                    )
                }
                className={productControl}
                aria-invalid={!!form.errors[name]}
                aria-describedby={
                    form.errors[name]
                        ? `producto-${name}-error`
                        : hint
                          ? `producto-${name}-ayuda`
                          : undefined
                }
            />
            {hint && (
                <p
                    id={`producto-${name}-ayuda`}
                    className="text-muted-foreground mt-1.5 text-xs"
                >
                    {hint}
                </p>
            )}
            {form.errors[name] && (
                <p
                    id={`producto-${name}-error`}
                    role="alert"
                    className="mt-1 text-sm text-red-700 dark:text-red-300"
                >
                    {form.errors[name]}
                </p>
            )}
        </div>
    );
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
    const handleToggleStatus = async (product: ProductItem) => {
        if (
            !(await pedirConfirmacion(
                product.activo
                    ? `¿Desactivar "${product.nombre}"? Dejará de estar disponible para nuevas ventas. Su stock e historial se conservan.`
                    : `¿Activar "${product.nombre}" para nuevas ventas?`,
            ))
        )
            return;
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
                        <Table className="w-full text-left text-xs">
                            <TableHeader className="border-border bg-muted/40 text-muted-foreground border-b text-[11px] font-semibold tracking-wider uppercase">
                                <TableRow>
                                    <TableHead>Código</TableHead>
                                    <TableHead>
                                        Producto / Descripción
                                    </TableHead>
                                    <TableHead>U.M.</TableHead>
                                    <TableHead className="text-right">
                                        Precio Venta
                                    </TableHead>
                                    <TableHead className="text-center">
                                        Stock Actual
                                    </TableHead>
                                    <TableHead className="text-center">
                                        Stock Mínimo
                                    </TableHead>
                                    <TableHead className="text-center">
                                        Tipo
                                    </TableHead>
                                    <TableHead className="text-center">
                                        Estado
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Acciones
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody className="divide-border divide-y">
                                {productos.data.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={9}>
                                            <Empty
                                                icon={<Inbox />}
                                                title="No se encontraron productos con los criterios seleccionados."
                                            />
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    productos.data.map((p) => {
                                        const isBajoMinimo =
                                            p.stock_minimo !== null &&
                                            p.stock_minimo > 0 &&
                                            p.stock_disponible <=
                                                p.stock_minimo;
                                        return (
                                            <TableRow
                                                key={p.id}
                                                className="hover:bg-muted/40 transition-colors"
                                            >
                                                <TableCell className="text-foreground px-4 py-3 font-mono font-bold">
                                                    {p.codigo}
                                                    {p.codigo_barras ? (
                                                        <div className="text-muted-foreground text-[10.5px] font-normal">
                                                            ▮▮ {p.codigo_barras}
                                                        </div>
                                                    ) : null}
                                                </TableCell>
                                                <TableCell className="px-4 py-3">
                                                    <div className="text-foreground font-medium">
                                                        {p.nombre}
                                                    </div>
                                                    {p.descripcion && (
                                                        <div className="text-muted-foreground max-w-xs truncate text-[11px]">
                                                            {p.descripcion}
                                                        </div>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-foreground/80 px-4 py-3 font-mono">
                                                    {p.unidad_medida === 'NIU'
                                                        ? 'Unidad'
                                                        : p.unidad_medida}
                                                </TableCell>
                                                <TableCell className="text-foreground px-4 py-3 text-right font-mono font-semibold">
                                                    {formatCurrency(
                                                        p.precio_venta,
                                                    )}
                                                </TableCell>
                                                <TableCell className="px-4 py-3 text-center">
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
                                                </TableCell>
                                                <TableCell className="text-foreground/80 px-4 py-3 text-center font-mono">
                                                    {p.stock_minimo !== null
                                                        ? p.stock_minimo
                                                        : '—'}
                                                </TableCell>
                                                <TableCell className="px-4 py-3 text-center">
                                                    <span className="border-border bg-muted/40 text-muted-foreground rounded-md border px-2 py-0.5 text-[10px] font-medium">
                                                        {p.serializado
                                                            ? 'Por serie'
                                                            : p.controla_lote
                                                              ? 'Por lote'
                                                              : 'Por cantidad'}
                                                    </span>
                                                </TableCell>
                                                <TableCell className="px-4 py-3 text-center">
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
                                                </TableCell>
                                                <TableCell className="px-4 py-3 text-right">
                                                    <div className="flex items-center justify-end gap-1.5">
                                                        <button
                                                            aria-label="Editar producto y stock mínimo"
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
                                                            aria-label={
                                                                p.activo
                                                                    ? 'Desactivar producto'
                                                                    : 'Activar producto'
                                                            }
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
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })
                                )}
                            </TableBody>
                        </Table>
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
                <Dialog
                    open={modalOpen}
                    onOpenChange={(open) => {
                        if (!open) void requestCloseModal();
                    }}
                >
                    <DialogContent
                        className="border-border bg-card flex max-h-[90dvh] flex-col gap-0 overflow-hidden p-0 sm:max-w-3xl"
                        onOpenAutoFocus={(event) => {
                            event.preventDefault();
                            document.getElementById('producto-nombre')?.focus();
                        }}
                        onCloseAutoFocus={(event) => {
                            event.preventDefault();
                            productTrigger.current?.focus();
                        }}
                    >
                        <DialogHeader className="border-border shrink-0 border-b px-6 py-5 text-left">
                            <DialogTitle className="pr-8 text-xl">
                                {editingProduct
                                    ? 'Editar producto'
                                    : 'Registrar producto'}
                            </DialogTitle>
                            <DialogDescription>
                                Empieza por el nombre y el precio. Los campos
                                con * son obligatorios.
                            </DialogDescription>
                        </DialogHeader>
                        <form
                            onSubmit={handleSubmit}
                            onInvalidCapture={(event) => {
                                const field = event.target as HTMLInputElement;
                                if (field.closest('details')?.open === false) {
                                    event.preventDefault();
                                    setAdvancedOpen(true);
                                    const element = event.currentTarget;
                                    requestAnimationFrame(() => {
                                        const invalid =
                                            element.querySelector<HTMLInputElement>(
                                                'input:invalid, select:invalid, textarea:invalid',
                                            );
                                        invalid?.focus();
                                        invalid?.reportValidity();
                                    });
                                }
                            }}
                            className="flex min-h-0 flex-1 flex-col"
                        >
                            <div className="min-h-0 flex-1 space-y-6 overflow-y-auto overscroll-contain px-6 py-5 text-sm">
                                {Object.keys(form.errors).length > 0 && (
                                    <p
                                        role="alert"
                                        className="rounded-lg border border-red-200 bg-red-50 p-3 text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200"
                                    >
                                        {Object.values(form.errors)[0]} Tus
                                        datos se mantienen; corrige el campo
                                        indicado.
                                    </p>
                                )}
                                {!editingProduct && (
                                    <section
                                        aria-labelledby="producto-inicio-titulo"
                                        className="space-y-3 rounded-lg border p-4"
                                    >
                                        <h3
                                            id="producto-inicio-titulo"
                                            className="font-semibold"
                                        >
                                            ¿Qué artículo vas a vender o
                                            utilizar?
                                        </h3>
                                        <p className="text-muted-foreground text-sm">
                                            Aquí registras el modelo del
                                            catálogo. Cada extintor físico se
                                            identifica después en Almacén; los
                                            equipos que trae un cliente se
                                            registran desde su orden de
                                            servicio.
                                        </p>
                                        <div className="grid gap-2 sm:grid-cols-3">
                                            {[
                                                {
                                                    value: 'serie',
                                                    label: 'Extintor nuevo',
                                                    hint: 'Cada unidad lleva su identificación.',
                                                },
                                                {
                                                    value: 'cantidad',
                                                    label: 'Repuesto o accesorio',
                                                    hint: 'Se cuentan unidades iguales.',
                                                },
                                                {
                                                    value: 'lote',
                                                    label: 'Producto por lote',
                                                    hint: 'Se registra lote y vencimiento.',
                                                },
                                            ].map((profile) => (
                                                <button
                                                    key={profile.value}
                                                    type="button"
                                                    disabled={form.processing}
                                                    aria-pressed={
                                                        inventoryControl ===
                                                        profile.value
                                                    }
                                                    className="border-border aria-pressed:border-primary aria-pressed:bg-primary/5 min-h-11 rounded-lg border p-3 text-left transition hover:brightness-95"
                                                    onClick={() => {
                                                        const category =
                                                            profile.value ===
                                                            'serie'
                                                                ? categorias.find(
                                                                      (item) =>
                                                                          item.activo &&
                                                                          item.genera_alertas_vencimiento &&
                                                                          item.nombre
                                                                              .toLowerCase()
                                                                              .includes(
                                                                                  'extint',
                                                                              ),
                                                                  )
                                                                : categorias.find(
                                                                      (item) =>
                                                                          item.activo &&
                                                                          item.nombre
                                                                              .toLowerCase()
                                                                              .includes(
                                                                                  profile.value ===
                                                                                      'cantidad'
                                                                                      ? 'repuesto'
                                                                                      : 'consum',
                                                                              ),
                                                                  );
                                                        form.setData(
                                                            (data) => ({
                                                                ...data,
                                                                categoria:
                                                                    category?.clave ??
                                                                    '',
                                                                serializado:
                                                                    profile.value ===
                                                                    'serie',
                                                                controla_lote:
                                                                    profile.value ===
                                                                    'lote',
                                                            }),
                                                        );
                                                    }}
                                                >
                                                    <span className="block font-medium">
                                                        {profile.label}
                                                    </span>
                                                    <span className="text-muted-foreground mt-1 block text-xs">
                                                        {profile.hint}
                                                    </span>
                                                </button>
                                            ))}
                                        </div>
                                        <p className="text-muted-foreground text-xs">
                                            Estas opciones preparan el control
                                            de inventario. Puedes ajustar
                                            categoría y control antes de
                                            guardar; conservan el nombre, el
                                            precio y el tratamiento del IGV.
                                        </p>
                                    </section>
                                )}
                                <section
                                    aria-labelledby="producto-datos-titulo"
                                    className="space-y-4"
                                >
                                    <h3
                                        id="producto-datos-titulo"
                                        className="font-semibold"
                                    >
                                        Datos del producto
                                    </h3>
                                    {productField(
                                        'nombre',
                                        'Nombre del producto',
                                        {
                                            required: true,
                                            maxLength: 255,
                                            placeholder:
                                                'Ej. Extintor PQS ABC de 6 kg',
                                        },
                                    )}
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        {productField(
                                            'codigo',
                                            'Código interno',
                                            {
                                                required: true,
                                                maxLength: 50,
                                                placeholder: 'Ej. EXT-PQS-6KG',
                                            },
                                            'Tu código para identificar el producto en el catálogo.',
                                        )}
                                        <div>
                                            <CategoriaSelect
                                                value={form.data.categoria}
                                                onChange={(clave) =>
                                                    form.setData(
                                                        'categoria',
                                                        clave,
                                                    )
                                                }
                                                categorias={categorias}
                                            />
                                            {form.errors.categoria && (
                                                <p
                                                    role="alert"
                                                    className="mt-1 text-sm text-red-700 dark:text-red-300"
                                                >
                                                    {form.errors.categoria}
                                                </p>
                                            )}
                                        </div>
                                        {productField(
                                            'precio_venta',
                                            'Precio de venta (S/)',
                                            {
                                                type: 'number',
                                                min: 0,
                                                step: '0.01',
                                                inputMode: 'decimal',
                                                required: true,
                                                placeholder: 'Ej. 120.00',
                                            },
                                            form.data.tipo_afectacion_igv ===
                                                '10'
                                                ? 'Precio final al cliente, con IGV incluido.'
                                                : 'Precio final al cliente.',
                                        )}
                                        <div>
                                            <label
                                                htmlFor="producto-unidad_medida"
                                                className="mb-1.5 block font-medium"
                                            >
                                                Unidad de venta *
                                            </label>
                                            <NativeSelect
                                                id="producto-unidad_medida"
                                                name="unidad_medida"
                                                value={form.data.unidad_medida}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'unidad_medida',
                                                        e.target.value,
                                                    )
                                                }
                                                className={productControl}
                                                aria-invalid={
                                                    !!form.errors.unidad_medida
                                                }
                                                aria-describedby={
                                                    form.errors.unidad_medida
                                                        ? 'producto-unidad-error'
                                                        : 'producto-unidad-ayuda'
                                                }
                                            >
                                                {unidadesMedida.map((u) => (
                                                    <option
                                                        key={u.codigo}
                                                        value={u.codigo}
                                                    >
                                                        {u.nombre}
                                                    </option>
                                                ))}
                                            </NativeSelect>
                                            <p
                                                id="producto-unidad-ayuda"
                                                className="text-muted-foreground mt-1.5 text-xs"
                                            >
                                                Cómo se vende y se cuenta:
                                                unidad, par, caja o kilogramo.
                                            </p>
                                            {form.errors.unidad_medida && (
                                                <p
                                                    id="producto-unidad-error"
                                                    role="alert"
                                                    className="mt-1 text-sm text-red-700 dark:text-red-300"
                                                >
                                                    {form.errors.unidad_medida}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                    <div>
                                        <label
                                            htmlFor="producto-tipo_afectacion_igv"
                                            className="mb-1.5 block font-medium"
                                        >
                                            Tratamiento del IGV *
                                        </label>
                                        <NativeSelect
                                            id="producto-tipo_afectacion_igv"
                                            name="tipo_afectacion_igv"
                                            required
                                            value={
                                                form.data.tipo_afectacion_igv
                                            }
                                            onChange={(e) =>
                                                form.setData(
                                                    'tipo_afectacion_igv',
                                                    e.target.value,
                                                )
                                            }
                                            className={productControl}
                                            aria-invalid={
                                                !!form.errors
                                                    .tipo_afectacion_igv
                                            }
                                            aria-describedby="producto-igv-ayuda"
                                        >
                                            <option value="" disabled>
                                                Selecciona el tratamiento
                                            </option>
                                            <option value="10">
                                                Gravado: el precio incluye IGV
                                                de 18%
                                            </option>
                                            <option value="20">
                                                Exonerado
                                            </option>
                                            <option value="30">Inafecto</option>
                                        </NativeSelect>
                                        <p
                                            id="producto-igv-ayuda"
                                            className="text-muted-foreground mt-1.5 text-xs"
                                        >
                                            Se usará al facturar este producto.
                                            Cambia el valor según su
                                            clasificación tributaria.
                                        </p>
                                        {form.errors.tipo_afectacion_igv && (
                                            <p
                                                role="alert"
                                                className="mt-1 text-sm text-red-700 dark:text-red-300"
                                            >
                                                {
                                                    form.errors
                                                        .tipo_afectacion_igv
                                                }
                                            </p>
                                        )}
                                    </div>
                                </section>
                                {categorias.find(
                                    (c) => c.clave === form.data.categoria,
                                )?.genera_alertas_vencimiento && (
                                    <section
                                        aria-labelledby="producto-extintor-titulo"
                                        className="border-border space-y-4 border-t pt-5"
                                    >
                                        <div>
                                            <h3
                                                id="producto-extintor-titulo"
                                                className="font-semibold"
                                            >
                                                Datos del extintor
                                            </h3>
                                            <p className="text-muted-foreground mt-1 text-sm">
                                                El agente y la capacidad se
                                                copian al equipo vendido y a su
                                                certificado.
                                            </p>
                                        </div>
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div>
                                                <label
                                                    htmlFor="producto-agente"
                                                    className="mb-1.5 block font-medium"
                                                >
                                                    Agente extintor
                                                </label>
                                                <NativeSelect
                                                    id="producto-agente"
                                                    name="agente"
                                                    value={form.data.agente}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'agente',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className={productControl}
                                                    aria-invalid={
                                                        !!form.errors.agente
                                                    }
                                                >
                                                    <option value="">
                                                        Sin registrar
                                                    </option>
                                                    {agentes.map((a) => (
                                                        <option
                                                            key={a.valor}
                                                            value={a.valor}
                                                        >
                                                            {a.etiqueta}
                                                        </option>
                                                    ))}
                                                </NativeSelect>
                                                <p className="text-muted-foreground mt-1.5 text-xs">
                                                    Completa el agente para
                                                    poder emitir el certificado.
                                                </p>
                                                {form.errors.agente && (
                                                    <p
                                                        role="alert"
                                                        className="mt-1 text-sm text-red-700 dark:text-red-300"
                                                    >
                                                        {form.errors.agente}
                                                    </p>
                                                )}
                                            </div>
                                            {productField(
                                                'capacidad',
                                                'Capacidad',
                                                {
                                                    maxLength: 20,
                                                    placeholder: 'Ej. 6 kg',
                                                },
                                            )}
                                        </div>
                                    </section>
                                )}
                                <section
                                    aria-labelledby="producto-inventario-titulo"
                                    className="border-border space-y-4 border-t pt-5"
                                >
                                    <div>
                                        <h3
                                            id="producto-inventario-titulo"
                                            className="font-semibold"
                                        >
                                            Control de inventario
                                        </h3>
                                        <p className="text-muted-foreground mt-1 text-sm">
                                            Elige cómo identifica Almacén las
                                            existencias de este producto.
                                        </p>
                                    </div>
                                    <fieldset
                                        id="producto-serializado"
                                        className="space-y-2"
                                        tabIndex={-1}
                                    >
                                        <legend className="sr-only">
                                            Cómo se controla el inventario
                                        </legend>
                                        {[
                                            {
                                                value: 'cantidad',
                                                label: 'Por cantidad',
                                                hint: 'Se cuentan unidades, sin identificar cada una. Ej. válvulas o soportes.',
                                            },
                                            {
                                                value: 'serie',
                                                label: 'Por número de serie',
                                                hint: 'Se registra cada unidad por separado. Ej. extintores nuevos.',
                                            },
                                            {
                                                value: 'lote',
                                                label: 'Por lote y vencimiento',
                                                hint: 'Se agrupan unidades por lote y fecha de vencimiento. Ej. EPP.',
                                            },
                                        ].map((option) => (
                                            <label
                                                key={option.value}
                                                className="border-border hover:bg-muted/40 has-[:checked]:border-primary has-[:checked]:bg-primary/5 flex cursor-pointer items-start gap-3 rounded-lg border p-3"
                                            >
                                                <input
                                                    type="radio"
                                                    name="control_inventario"
                                                    value={option.value}
                                                    checked={
                                                        inventoryControl ===
                                                        option.value
                                                    }
                                                    onChange={() =>
                                                        form.setData(
                                                            (data) => ({
                                                                ...data,
                                                                serializado:
                                                                    option.value ===
                                                                    'serie',
                                                                controla_lote:
                                                                    option.value ===
                                                                    'lote',
                                                            }),
                                                        )
                                                    }
                                                    className="accent-primary mt-1 size-4 shrink-0"
                                                />
                                                <span>
                                                    <span className="block font-medium">
                                                        {option.label}
                                                    </span>
                                                    <span className="text-muted-foreground mt-0.5 block text-xs">
                                                        {option.hint}
                                                    </span>
                                                </span>
                                            </label>
                                        ))}
                                        {(form.errors.serializado ||
                                            form.errors.controla_lote) && (
                                            <p
                                                role="alert"
                                                className="text-sm text-red-700 dark:text-red-300"
                                            >
                                                {form.errors.serializado ||
                                                    form.errors.controla_lote}
                                            </p>
                                        )}
                                    </fieldset>
                                    {productField(
                                        'stock_minimo',
                                        'Avisar cuando queden',
                                        {
                                            type: 'number',
                                            min: 0,
                                            step: 1,
                                            inputMode: 'numeric',
                                            placeholder: 'Ej. 5',
                                        },
                                        'Cantidad mínima disponible. Déjalo vacío si no necesitas una alerta.',
                                    )}
                                    <p className="bg-muted/50 text-muted-foreground rounded-lg p-3 text-xs">
                                        <strong className="text-foreground block">
                                            Así se ingresará al almacén
                                        </strong>
                                        {inventoryControl === 'serie'
                                            ? 'Al recibirlo, Almacén identifica cada unidad por separado. El código del artículo no es la serie del fabricante.'
                                            : inventoryControl === 'lote'
                                              ? 'Al recibirlo, Almacén registra cantidad, lote y vencimiento.'
                                              : 'Almacén registra la cantidad recibida; no necesitas una serie por unidad.'}
                                        <span className="mt-2 block">
                                            Registrar el producto no agrega
                                            stock. Las existencias se ingresan
                                            desde Recepciones de Almacén.
                                        </span>
                                        {form.data.unidad_compra &&
                                            !form.data.serializado && (
                                                <span className="mt-2 block">
                                                    1 {form.data.unidad_compra}{' '}
                                                    equivale a{' '}
                                                    {form.data.factor_compra ||
                                                        '—'}{' '}
                                                    unidades de venta. La
                                                    equivalencia se aplica al
                                                    recibir.
                                                </span>
                                            )}
                                    </p>
                                </section>
                                <details
                                    open={advancedOpen}
                                    onToggle={(event) =>
                                        setAdvancedOpen(
                                            event.currentTarget.open,
                                        )
                                    }
                                    className="border-border rounded-lg border"
                                >
                                    <summary className="focus-visible:outline-primary cursor-pointer px-4 py-3 font-medium focus-visible:outline-2">
                                        Datos adicionales{' '}
                                        <span className="text-muted-foreground ml-1 font-normal">
                                            (opcionales)
                                        </span>
                                    </summary>
                                    <div className="border-border space-y-4 border-t p-4">
                                        <div>
                                            <label
                                                htmlFor="producto-descripcion"
                                                className="mb-1.5 block font-medium"
                                            >
                                                Descripción
                                            </label>
                                            <textarea
                                                id="producto-descripcion"
                                                name="descripcion"
                                                rows={3}
                                                maxLength={1000}
                                                value={form.data.descripcion}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'descripcion',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Ej. Incluye soporte y manómetro"
                                                className={productControl}
                                                aria-invalid={
                                                    !!form.errors.descripcion
                                                }
                                            />
                                            {form.errors.descripcion && (
                                                <p
                                                    role="alert"
                                                    className="mt-1 text-sm text-red-700 dark:text-red-300"
                                                >
                                                    {form.errors.descripcion}
                                                </p>
                                            )}
                                        </div>
                                        {productField(
                                            'codigo_barras',
                                            'Código de barras del fabricante',
                                            {
                                                maxLength: 64,
                                                placeholder:
                                                    'Escanea o escribe el código',
                                            },
                                            'Opcional. Se usa para buscar el producto al recibirlo y venderlo.',
                                        )}
                                        {productField(
                                            'peso_kg',
                                            'Peso de cada unidad (kg)',
                                            {
                                                type: 'number',
                                                min: 0,
                                                max: 99999,
                                                step: '0.001',
                                                inputMode: 'decimal',
                                                placeholder: 'Ej. 8.500',
                                            },
                                            'Peso del producto completo para la guía de remisión; puede ser distinto a la capacidad del extintor.',
                                        )}
                                        {(!form.data.serializado ||
                                            form.errors.unidad_compra ||
                                            form.errors.factor_compra) && (
                                            <div className="grid gap-4 sm:grid-cols-2">
                                                {productField(
                                                    'unidad_compra',
                                                    'Presentación de compra',
                                                    {
                                                        maxLength: 10,
                                                        placeholder: 'Ej. CAJA',
                                                    },
                                                    'Si compras y vendes por la misma unidad, déjalo vacío.',
                                                )}
                                                {productField(
                                                    'factor_compra',
                                                    'Unidades por presentación',
                                                    {
                                                        type: 'number',
                                                        min: 1,
                                                        max: 100000,
                                                        step: 1,
                                                        inputMode: 'numeric',
                                                        disabled:
                                                            !form.data
                                                                .unidad_compra,
                                                        placeholder: 'Ej. 100',
                                                    },
                                                    'Ej. una caja contiene 100 unidades. Almacén contará las cajas al recibir.',
                                                )}
                                            </div>
                                        )}
                                        <label className="bg-muted/40 flex cursor-pointer items-start gap-3 rounded-lg p-3">
                                            <input
                                                name="activo"
                                                type="checkbox"
                                                checked={form.data.activo}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'activo',
                                                        e.target.checked,
                                                    )
                                                }
                                                className="accent-primary mt-1 size-4"
                                            />
                                            <span>
                                                <span className="block font-medium">
                                                    Disponible para vender
                                                </span>
                                                <span className="text-muted-foreground block text-xs">
                                                    Al desactivarlo, el producto
                                                    conserva su stock e
                                                    historial.
                                                </span>
                                            </span>
                                        </label>
                                        {form.errors.activo && (
                                            <p
                                                role="alert"
                                                className="text-sm text-red-700 dark:text-red-300"
                                            >
                                                {form.errors.activo}
                                            </p>
                                        )}
                                    </div>
                                </details>
                            </div>
                            <div className="border-border bg-card flex shrink-0 items-center justify-end gap-3 border-t px-6 py-4">
                                <button
                                    type="button"
                                    onClick={() => void requestCloseModal()}
                                    disabled={form.processing}
                                    className="border-border hover:bg-muted/50 focus-visible:outline-primary min-h-11 rounded-lg border px-4 text-sm font-medium focus-visible:outline-2 disabled:opacity-50"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    disabled={form.processing}
                                    className="bg-primary text-primary-foreground hover:bg-primary/90 focus-visible:outline-primary min-h-11 rounded-lg px-5 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-50"
                                >
                                    {form.processing
                                        ? 'Guardando…'
                                        : editingProduct
                                          ? 'Guardar cambios'
                                          : 'Registrar producto'}
                                </button>
                            </div>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </GerenteLayout>
    );
}
