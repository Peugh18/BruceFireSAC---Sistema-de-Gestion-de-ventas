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
import { useBusquedaDinamica } from '@/hooks/use-busqueda-dinamica';
import GerenteLayout from '@/layouts/gerente-layout';
import { toast } from 'sonner';
import ProductosRoutes from '@/routes/gerente/productos';
import { useConfirmarAccion } from '@/hooks/use-confirmar-accion';
import { NativeSelect } from '@/components/ui/native-select';
import { Empty } from '@/components/ui/empty';
import OpcionesAgente from '@/components/opciones-agente';
import BotonAgentes from '@/components/agentes-modal';
import CodigoSunatInput from '@/components/codigo-sunat-input';
type ProductItem = {
    id: number;
    codigo: string;
    codigo_barras: string | null;
    codigo_sunat: string | null;
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
    codigo_sunat: '',
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
        siguienteCodigo,
        flash,
    } = usePage<PageProps>().props;
    const [buscar, setBuscar] = useState(filters.buscar || '');
    const [modalOpen, setModalOpen] = useState(false);
    const [editingProduct, setEditingProduct] = useState<ProductItem | null>(
        null,
    );
    const form = useForm(emptyProduct);
    const [, setAdvancedOpen] = useState(false);
    const productTrigger = useRef<HTMLElement | null>(null);
    const esExtintor = form.data.serializado;
    const precioFinal = Number(form.data.precio_venta) || 0;
    const valorSinIgv =
        form.data.tipo_afectacion_igv === '10'
            ? precioFinal / 1.18
            : precioFinal;

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
            codigo_sunat: product.codigo_sunat ?? '',
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
    const igvCampo = (
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
                value={form.data.tipo_afectacion_igv}
                onChange={(e) =>
                    form.setData((data) => ({
                        ...data,
                        tipo_afectacion_igv: e.target.value,
                        aplica_igv: e.target.value === '10',
                    }))
                }
                className={productControl}
                aria-invalid={!!form.errors.tipo_afectacion_igv}
            >
                <option value="" disabled>
                    Selecciona el tratamiento
                </option>
                <option value="10">Gravado · IGV 18 %</option>
                <option value="20">Exonerado</option>
                <option value="30">Inafecto</option>
            </NativeSelect>
            {form.errors.tipo_afectacion_igv && (
                <p
                    role="alert"
                    className="text-destructive-strong mt-1 text-sm dark:text-red-300"
                >
                    {form.errors.tipo_afectacion_igv}
                </p>
            )}
        </div>
    );
    const productField = (
        name:
            | 'nombre'
            | 'precio_venta'
            | 'capacidad'
            | 'stock_minimo'
            | 'codigo_barras'
            | 'codigo_sunat'
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
                        name === 'unidad_compra'
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
                    className="text-destructive-strong mt-1 text-sm dark:text-red-300"
                >
                    {form.errors[name]}
                </p>
            )}
        </div>
    );
    const aplicarBusqueda = () => {
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
    const handleSearch = useBusquedaDinamica(buscar, aplicarBusqueda);
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
                    <div className="text-success-strong flex items-center gap-2.5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm">
                        <CheckCircle2 className="text-success-strong size-4 shrink-0" />
                        <span>{flash.success}</span>
                    </div>
                )}
                {flash?.error && (
                    <div className="text-destructive-strong flex items-center gap-2.5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm">
                        <AlertTriangle className="text-primary-strong size-4 shrink-0" />
                        <span>{flash.error}</span>
                    </div>
                )}
                {/* Cabecera y Botón Nuevo */}
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div>
                        <h1 className="text-foreground font-['Oswald',sans-serif] text-2xl font-bold tracking-wide uppercase">
                            Productos
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
                        <div className="text-success-strong mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold">
                            {kpis.totalActivos}
                        </div>
                    </div>
                    <div className="border-border bg-card rounded-xl border p-4 shadow-xs">
                        <div className="text-muted-foreground flex items-center justify-between text-xs">
                            <span className="font-medium uppercase">
                                Por reponer
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
                            <span>Por reponer</span>
                        </button>
                    </div>
                </div>
                {/* Tabla de Productos */}
                <div className="border-border bg-card overflow-hidden rounded-xl border shadow-xs">
                    <div className="overflow-x-auto">
                        <Table className="w-full text-left text-xs">
                            <TableHeader className="border-border bg-muted/40 text-muted-foreground border-b text-xs font-semibold tracking-wider uppercase">
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
                                                        <div className="text-muted-foreground text-xs font-normal">
                                                            ▮▮ {p.codigo_barras}
                                                        </div>
                                                    ) : !p.serializado ? (
                                                        <div className="text-warning-strong text-xs font-normal">
                                                            Sin código de barras
                                                        </div>
                                                    ) : null}
                                                </TableCell>
                                                <TableCell className="px-4 py-3">
                                                    <div className="text-foreground font-medium">
                                                        {p.nombre}
                                                    </div>
                                                    {p.descripcion && (
                                                        <div className="text-muted-foreground max-w-xs truncate text-xs">
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
                                                        className={`inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 font-mono text-xs font-bold ${
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
                                                    <span className="border-border bg-muted/40 text-muted-foreground rounded-md border px-2 py-0.5 text-xs font-medium">
                                                        {p.serializado
                                                            ? 'Por serie'
                                                            : p.controla_lote
                                                              ? 'Por lote'
                                                              : 'Por cantidad'}
                                                    </span>
                                                </TableCell>
                                                <TableCell className="px-4 py-3 text-center">
                                                    <span
                                                        className={`inline-flex rounded-full px-2 py-0.5 text-xs font-bold uppercase ${
                                                            p.activo
                                                                ? 'text-success-strong bg-emerald-100'
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
                        <DialogHeader className="border-border shrink-0 border-b px-6 py-4 text-left">
                            <DialogTitle className="pr-8 text-xl">
                                {editingProduct
                                    ? 'Editar producto'
                                    : 'Registrar producto'}
                            </DialogTitle>
                            <DialogDescription>
                                Los campos con * son obligatorios.
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
                            <div className="min-h-0 flex-1 space-y-4 overflow-y-auto overscroll-contain px-4 py-4 text-sm sm:px-6">
                                {Object.keys(form.errors).length > 0 && (
                                    <p
                                        role="alert"
                                        className="text-destructive-strong rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-900 dark:bg-red-950 dark:text-red-200"
                                    >
                                        {Object.values(form.errors)[0]} Tus
                                        datos se mantienen; corrige el campo
                                        indicado.
                                    </p>
                                )}

                                <fieldset>
                                    <legend className="mb-1.5 font-medium">
                                        ¿Qué vas a registrar? *
                                    </legend>
                                    <div className="bg-muted grid grid-cols-2 gap-1 rounded-lg p-1">
                                        {[
                                            {
                                                extintor: true,
                                                label: 'Extintor',
                                            },
                                            {
                                                extintor: false,
                                                label: 'Otro producto',
                                            },
                                        ].map((opcion) => (
                                            <button
                                                key={opcion.label}
                                                type="button"
                                                disabled={form.processing}
                                                aria-pressed={
                                                    esExtintor ===
                                                    opcion.extintor
                                                }
                                                className="text-muted-foreground aria-pressed:bg-background aria-pressed:text-foreground focus-visible:outline-primary min-h-10 rounded-md px-3 font-medium transition focus-visible:outline-2 aria-pressed:shadow-sm"
                                                onClick={() => {
                                                    const categoria =
                                                        categorias.find(
                                                            (item) =>
                                                                item.activo &&
                                                                (opcion.extintor
                                                                    ? item.nombre
                                                                          .toLowerCase()
                                                                          .includes(
                                                                              'extint',
                                                                          )
                                                                    : item.clave ===
                                                                      'repuesto'),
                                                        );
                                                    form.setData((data) => ({
                                                        ...data,
                                                        categoria:
                                                            categoria?.clave ??
                                                            '',
                                                        serializado:
                                                            opcion.extintor,
                                                        controla_lote: false,
                                                    }));
                                                }}
                                            >
                                                {opcion.label}
                                            </button>
                                        ))}
                                    </div>
                                    <p className="text-muted-foreground mt-1.5 text-xs">
                                        {esExtintor
                                            ? 'Almacén le pone su sticker BF-EQ al recibirlo. Código SUNAT 46191601 (se llena solo).'
                                            : editingProduct?.codigo_barras
                                              ? `Código de barras ${editingProduct.codigo_barras}, ligado por Almacén.`
                                              : 'Sin código de barras todavía: Almacén lo liga al escanearlo en la primera recepción.'}
                                    </p>
                                </fieldset>

                                {productField('nombre', 'Nombre del producto', {
                                    required: true,
                                    maxLength: 255,
                                    placeholder: esExtintor
                                        ? 'Ej. Extintor PQS ABC de 6 kg'
                                        : 'Ej. Soporte metálico para extintor',
                                })}

                                <div className="grid gap-4 sm:grid-cols-3">
                                    {productField(
                                        'precio_venta',
                                        'Precio final (S/)',
                                        {
                                            type: 'number',
                                            min: 0,
                                            step: '0.01',
                                            inputMode: 'decimal',
                                            required: true,
                                            placeholder: '118.00',
                                        },
                                        form.data.tipo_afectacion_igv === '10'
                                            ? `Sin IGV S/ ${valorSinIgv.toFixed(2)} + IGV S/ ${(precioFinal - valorSinIgv).toFixed(2)}`
                                            : 'Sin IGV: es el precio que paga el cliente.',
                                    )}
                                    <div>
                                        <label
                                            htmlFor="producto-unidad_medida"
                                            className="mb-1.5 block font-medium"
                                        >
                                            Unidad *
                                        </label>
                                        <NativeSelect
                                            id="producto-unidad_medida"
                                            required
                                            value={form.data.unidad_medida}
                                            onChange={(event) =>
                                                form.setData(
                                                    'unidad_medida',
                                                    event.target.value,
                                                )
                                            }
                                            className={productControl}
                                            aria-invalid={
                                                !!form.errors.unidad_medida
                                            }
                                        >
                                            {unidadesMedida.map((unidad) => (
                                                <option
                                                    key={unidad.codigo}
                                                    value={unidad.codigo}
                                                >
                                                    {unidad.codigo} ·{' '}
                                                    {unidad.nombre}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                        {form.errors.unidad_medida && (
                                            <p
                                                role="alert"
                                                className="text-destructive-strong mt-1 text-sm dark:text-red-300"
                                            >
                                                {form.errors.unidad_medida}
                                            </p>
                                        )}
                                    </div>
                                    {igvCampo}
                                </div>

                                {esExtintor ? (
                                    <div className="grid gap-4 sm:grid-cols-3">
                                        <div>
                                            <label
                                                htmlFor="producto-agente"
                                                className="mb-1.5 block font-medium"
                                            >
                                                Agente
                                            </label>
                                            <div className="flex gap-1.5">
                                                <NativeSelect
                                                    id="producto-agente"
                                                    name="agente"
                                                    value={form.data.agente}
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'agente',
                                                            event.target.value,
                                                        )
                                                    }
                                                    className={`${productControl} min-w-0 flex-1`}
                                                    aria-invalid={
                                                        !!form.errors.agente
                                                    }
                                                >
                                                    <OpcionesAgente vacia="Elige" />
                                                </NativeSelect>
                                                <BotonAgentes />
                                            </div>
                                            {form.errors.agente && (
                                                <p
                                                    role="alert"
                                                    className="text-destructive-strong mt-1 text-sm dark:text-red-300"
                                                >
                                                    {form.errors.agente}
                                                </p>
                                            )}
                                        </div>
                                        {productField(
                                            'capacidad',
                                            'Capacidad',
                                            {
                                                placeholder: '6 kg',
                                            },
                                        )}
                                        {productField(
                                            'stock_minimo',
                                            'Avisar cuando queden',
                                            {
                                                type: 'number',
                                                min: 0,
                                                step: 1,
                                                inputMode: 'numeric',
                                                placeholder: '5',
                                            },
                                        )}
                                    </div>
                                ) : (
                                    <div className="grid gap-4 sm:grid-cols-3">
                                        <CategoriaSelect
                                            value={form.data.categoria}
                                            onChange={(clave) =>
                                                form.setData('categoria', clave)
                                            }
                                            categorias={categorias}
                                        />
                                        <CodigoSunatInput
                                            id="producto-codigo_sunat"
                                            tipo="producto"
                                            teamSlug={currentTeam.slug}
                                            value={form.data.codigo_sunat}
                                            onChange={(codigo) =>
                                                form.setData(
                                                    'codigo_sunat',
                                                    codigo,
                                                )
                                            }
                                            error={form.errors.codigo_sunat}
                                        />
                                        {productField(
                                            'stock_minimo',
                                            'Avisar cuando queden',
                                            {
                                                type: 'number',
                                                min: 0,
                                                step: 1,
                                                inputMode: 'numeric',
                                                placeholder: '5',
                                            },
                                        )}
                                    </div>
                                )}
                                <p className="text-muted-foreground text-xs">
                                    El stock entra cuando Almacén recibe la
                                    mercadería.
                                </p>
                            </div>
                            <div className="border-border bg-card flex shrink-0 flex-wrap items-center justify-between gap-3 border-t px-4 py-3 sm:px-6">
                                <label
                                    htmlFor="producto-activo"
                                    className="flex min-h-11 cursor-pointer items-center gap-2"
                                >
                                    <input
                                        id="producto-activo"
                                        name="activo"
                                        type="checkbox"
                                        checked={form.data.activo}
                                        onChange={(event) =>
                                            form.setData(
                                                'activo',
                                                event.target.checked,
                                            )
                                        }
                                        className="accent-primary size-4"
                                    />
                                    <span className="font-medium">
                                        Disponible para vender
                                    </span>
                                </label>
                                <div className="flex gap-3">
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
                            </div>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </GerenteLayout>
    );
}
