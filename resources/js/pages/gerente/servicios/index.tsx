import { router, useForm, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    Edit2,
    Plus,
    Power,
    Search,
    Wrench,
    Inbox,
} from 'lucide-react';
import { useRef, useState } from 'react';
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
import type { CategoriaItem } from '@/components/categoria-select';
import { useBusquedaDinamica } from '@/hooks/use-busqueda-dinamica';
import GerenteLayout from '@/layouts/gerente-layout';
import { toast } from 'sonner';
import ServiciosRoutes from '@/routes/gerente/servicios';
import { useConfirmarAccion } from '@/hooks/use-confirmar-accion';
import { NativeSelect } from '@/components/ui/native-select';
import {
    TiposDeServicioModal,
    type TipoDeServicioItem,
} from '@/components/tipos-de-servicio-modal';
import {
    type EstadoDetraccion,
    type OpcionDetraccion,
} from '@/components/datos-tributarios-catalogo';
import { Empty } from '@/components/ui/empty';
import OpcionesAgente from '@/components/opciones-agente';
import BotonAgentes from '@/components/agentes-modal';

const etiquetaUnidad = (codigo: string, nombre: string) => {
    if (codigo === 'ZZ') return 'Unidad de servicio';
    if (codigo === 'NIU') return 'Unidad';
    return nombre;
};

type ServiceItem = {
    id: number;
    codigo: string;
    nombre: string;
    descripcion: string | null;
    categoria: string | null;
    unidad_medida: string;
    agente: string | null;
    capacidad: string | null;
    precio_venta: number;
    estado_detraccion: EstadoDetraccion;
    codigo_detraccion: string | null;
    aplica_igv: boolean;
    tipo_afectacion_igv: string | null;
    igv_requiere_revision: boolean;
    certificate_type_id: number | null;
    tipo_de_servicio_id: number | null;
    tipo: string | null;
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
    tiposDeServicio: TipoDeServicioItem[];
    detracciones: OpcionDetraccion[];
    categorias: CategoriaItem[];
    unidadesMedida: { codigo: string; nombre: string }[];
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
    const modalTrigger = useRef<HTMLElement | null>(null);
    const { pedirConfirmacion, dialogoConfirmacion } = useConfirmarAccion();
    const {
        currentTeam,
        servicios,
        filters,
        kpis,
        tiposCertificado,
        tiposDeServicio,
        detracciones,
        unidadesMedida,
        flash,
    } = usePage<PageProps>().props;
    const [buscar, setBuscar] = useState(filters.buscar || '');
    const [modalOpen, setModalOpen] = useState(false);
    const [editingService, setEditingService] = useState<ServiceItem | null>(
        null,
    );
    const form = useForm({
        tipo_de_servicio_id: '',
        codigo: '',
        nombre: '',
        descripcion: '',
        categoria: '',
        unidad_medida: 'ZZ',
        agente: '',
        capacidad: '',
        precio_venta: '',
        estado_detraccion: 'pendiente' as EstadoDetraccion,
        codigo_detraccion: '',
        aplica_igv: true,
        tipo_afectacion_igv: '10',
        certificate_type_id: '',
        activo: true,
    });
    const openCreateModal = () => {
        setEditingService(null);
        form.reset();
        form.clearErrors();
        form.setData({
            tipo_de_servicio_id: '',
            estado_detraccion: 'pendiente',
            codigo: '',
            nombre: '',
            descripcion: '',
            categoria: '',
            unidad_medida: 'ZZ',
            agente: '',
            capacidad: '',
            precio_venta: '',
            codigo_detraccion: '',
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
            tipo_de_servicio_id: service.tipo_de_servicio_id
                ? String(service.tipo_de_servicio_id)
                : '',
            codigo: service.codigo,
            nombre: service.nombre,
            descripcion: service.descripcion || '',
            categoria: service.categoria ?? '',
            unidad_medida: service.unidad_medida,
            agente: service.agente ?? '',
            capacidad: service.capacidad ?? '',
            precio_venta: String(service.precio_venta),
            estado_detraccion: service.estado_detraccion ?? 'pendiente',
            codigo_detraccion: service.codigo_detraccion ?? '',
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
        form.clearErrors();
    };
    const tipoElegido = tiposDeServicio.find(
        (tipo) => String(tipo.id) === form.data.tipo_de_servicio_id,
    );
    const cambiarTipoServicio = (id: string) => {
        const tipo = tiposDeServicio.find((t) => String(t.id) === id);
        form.setData((data) => ({
            ...data,
            tipo_de_servicio_id: id,
            estado_detraccion: tipo?.estado_detraccion ?? 'pendiente',
            codigo_detraccion: tipo?.codigo_detraccion ?? '',
            agente: tipo?.pide_extintor ? data.agente : '',
            capacidad: tipo?.pide_extintor ? data.capacidad : '',
        }));
    };
    const precio = Number(form.data.precio_venta) || 0;
    const valorSinIgv = precio / 1.18;
    const reglaElegida = detracciones.find(
        (opcion) => opcion.codigo === tipoElegido?.codigo_detraccion,
    );
    /** La detracción de cada tipo se ve ya en la lista, antes de elegirlo. */
    const detraccionDelTipo = (tipo: TipoDeServicioItem): string => {
        if (tipo.estado_detraccion === 'pendiente') {
            return 'detracción por definir';
        }
        if (tipo.estado_detraccion === 'no_sujeto') {
            return 'sin detracción';
        }
        const regla = detracciones.find(
            (opcion) => opcion.codigo === tipo.codigo_detraccion,
        );

        return `${tipo.codigo_detraccion}${regla?.porcentaje != null ? ` · ${regla.porcentaje} %` : ''}`;
    };
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            codigo_detraccion:
                data.estado_detraccion === 'sujeto'
                    ? data.codigo_detraccion || null
                    : null,
        }));
        if (editingService) {
            form.put(
                ServiciosRoutes.update.url({
                    current_team: currentTeam.slug,
                    servicio: editingService.id,
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
                ServiciosRoutes.store.url({ current_team: currentTeam.slug }),
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
    const aplicarBusqueda = () => {
        router.get(
            ServiciosRoutes.index.url({ current_team: currentTeam.slug }),
            {
                buscar,
                estado: filters.estado,
            },
            { preserveState: true },
        );
    };
    const handleSearch = useBusquedaDinamica(buscar, aplicarBusqueda);
    const handleFilterEstado = (estado: string) => {
        router.get(
            ServiciosRoutes.index.url({ current_team: currentTeam.slug }),
            {
                buscar,
                estado,
            },
            { preserveState: true },
        );
    };
    const handleToggleStatus = async (service: ServiceItem) => {
        if (
            !(await pedirConfirmacion(
                service.activo
                    ? `¿Desactivar "${service.nombre}"? Dejará de estar disponible para nuevas ventas. Su historial se conserva.`
                    : `¿Activar "${service.nombre}" para nuevas ventas?`,
            ))
        )
            return;
        router.patch(
            ServiciosRoutes.toggleStatus.url({
                current_team: currentTeam.slug,
                servicio: service.id,
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
        <GerenteLayout title="Catálogo de Servicios">
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
                        <div className="text-success-strong mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold">
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
                                aria-label="Buscar servicios"
                                type="text"
                                value={buscar}
                                onChange={(e) => setBuscar(e.target.value)}
                                placeholder="Buscar por código o nombre..."
                                className="border-border bg-muted/40 text-foreground focus:border-primary w-full rounded-lg border py-2 pr-4 pl-9 text-sm focus:outline-none"
                            />
                        </div>
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
                        <Table className="w-full text-left text-xs">
                            <TableHeader className="border-border bg-muted/40 text-muted-foreground border-b text-xs font-semibold tracking-wider uppercase">
                                <TableRow>
                                    <TableHead>Código</TableHead>
                                    <TableHead>
                                        Servicio / Descripción
                                    </TableHead>
                                    <TableHead>U.M.</TableHead>
                                    <TableHead className="text-right">
                                        Precio Venta
                                    </TableHead>
                                    <TableHead className="text-center">
                                        IGV
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
                                {servicios.data.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={7}>
                                            <Empty
                                                icon={<Inbox />}
                                                title={
                                                    filters.buscar
                                                        ? 'No hay coincidencias con esta búsqueda.'
                                                        : 'No se encontraron servicios técnicos registrados.'
                                                }
                                            >
                                                {filters.buscar ? (
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            setBuscar('');
                                                            router.get(
                                                                ServiciosRoutes.index.url(
                                                                    {
                                                                        current_team:
                                                                            currentTeam.slug,
                                                                    },
                                                                ),
                                                                {
                                                                    estado: filters.estado,
                                                                },
                                                                {
                                                                    preserveState: true,
                                                                },
                                                            );
                                                        }}
                                                        className="border-border text-foreground hover:bg-muted/50 rounded-lg border px-3 py-2 text-xs font-semibold"
                                                    >
                                                        Limpiar búsqueda
                                                    </button>
                                                ) : null}
                                            </Empty>
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    servicios.data.map((s) => (
                                        <TableRow
                                            key={s.id}
                                            className="hover:bg-muted/40 transition-colors"
                                        >
                                            <TableCell className="text-foreground px-4 py-3 font-mono font-bold">
                                                {s.codigo}
                                            </TableCell>
                                            <TableCell className="px-4 py-3">
                                                <div className="text-foreground font-medium">
                                                    {s.nombre}
                                                </div>
                                                <p className="text-muted-foreground mt-1 text-xs">
                                                    {s.estado_detraccion ===
                                                    'sujeto'
                                                        ? `Detracción ${s.codigo_detraccion ?? 'por revisar'}`
                                                        : s.estado_detraccion ===
                                                            'no_sujeto'
                                                          ? 'No lleva detracción'
                                                          : 'Detracción por definir'}
                                                </p>
                                                {s.descripcion && (
                                                    <div className="text-muted-foreground max-w-xs truncate text-xs">
                                                        {s.descripcion}
                                                    </div>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-foreground/80 px-4 py-3">
                                                {etiquetaUnidad(
                                                    s.unidad_medida,
                                                    unidadesMedida.find(
                                                        (u) =>
                                                            u.codigo ===
                                                            s.unidad_medida,
                                                    )?.nombre ??
                                                        s.unidad_medida,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-foreground px-4 py-3 text-right font-mono font-semibold">
                                                {formatCurrency(s.precio_venta)}
                                            </TableCell>
                                            <TableCell className="px-4 py-3 text-center">
                                                <span className="border-border bg-muted/40 text-muted-foreground rounded-md border px-2 py-0.5 text-xs font-medium">
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
                                            </TableCell>
                                            <TableCell className="px-4 py-3 text-center">
                                                <span
                                                    className={`inline-flex rounded-full px-2 py-0.5 text-xs font-bold uppercase ${
                                                        s.activo
                                                            ? 'text-success-strong bg-emerald-100'
                                                            : 'bg-zinc-100 text-zinc-600'
                                                    }`}
                                                >
                                                    {s.activo
                                                        ? 'Activo'
                                                        : 'Inactivo'}
                                                </span>
                                            </TableCell>
                                            <TableCell className="px-4 py-3 text-right">
                                                <div className="flex items-center justify-end gap-1.5">
                                                    <button
                                                        aria-label="Editar servicio"
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
                                                        aria-label={
                                                            s.activo
                                                                ? 'Desactivar servicio'
                                                                : 'Activar servicio'
                                                        }
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
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
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
                <Dialog
                    open={modalOpen}
                    onOpenChange={(open) => {
                        if (!open && !form.processing) closeModal();
                    }}
                >
                    <DialogContent
                        onOpenAutoFocus={() => {
                            modalTrigger.current =
                                document.activeElement as HTMLElement;
                        }}
                        onCloseAutoFocus={(event) => {
                            event.preventDefault();
                            modalTrigger.current?.focus();
                        }}
                        className="border-border bg-card grid max-h-[90dvh] w-[calc(100vw-2rem)] max-w-[calc(100vw-2rem)] grid-rows-[auto_minmax(0,1fr)_auto] overflow-hidden p-0 sm:max-w-2xl"
                    >
                        <DialogHeader className="border-border border-b px-6 py-4 text-left">
                            <DialogTitle>
                                {editingService
                                    ? 'Editar Servicio'
                                    : 'Nuevo Servicio'}
                            </DialogTitle>
                            <DialogDescription>
                                Los campos con * son obligatorios.
                            </DialogDescription>
                        </DialogHeader>
                        <form
                            onSubmit={handleSubmit}
                            className="flex min-h-0 flex-col"
                        >
                            <div className="min-h-0 flex-1 space-y-4 overflow-y-auto overscroll-contain px-4 py-4 text-sm sm:px-6">
                                {Object.keys(form.errors).length > 0 && (
                                    <p
                                        role="alert"
                                        className="text-destructive-strong rounded-lg border p-3"
                                    >
                                        {Object.values(form.errors)[0]} Tus
                                        datos se mantienen; corrige el campo
                                        indicado.
                                    </p>
                                )}
                                <div className="grid gap-4">
                                    <div>
                                        <div className="mb-1.5 flex items-center justify-between gap-2">
                                            <label
                                                htmlFor="servicio-tipo"
                                                className="block font-medium"
                                            >
                                                Tipo de servicio *
                                            </label>
                                            <TiposDeServicioModal
                                                equipo={currentTeam.slug}
                                                tipos={tiposDeServicio}
                                                certificados={tiposCertificado}
                                                detracciones={detracciones}
                                            />
                                        </div>
                                        <NativeSelect
                                            id="servicio-tipo"
                                            name="tipo_de_servicio_id"
                                            value={
                                                form.data.tipo_de_servicio_id
                                            }
                                            onChange={(event) =>
                                                cambiarTipoServicio(
                                                    event.target.value,
                                                )
                                            }
                                            required
                                            className="min-h-11 w-full"
                                            aria-invalid={
                                                !!form.errors
                                                    .tipo_de_servicio_id
                                            }
                                        >
                                            <option value="" disabled>
                                                Elige el tipo de servicio
                                            </option>
                                            {tiposDeServicio
                                                .filter(
                                                    (tipo) =>
                                                        tipo.activo ||
                                                        String(tipo.id) ===
                                                            form.data
                                                                .tipo_de_servicio_id,
                                                )
                                                .map((tipo) => (
                                                    <option
                                                        key={tipo.id}
                                                        value={tipo.id}
                                                    >
                                                        {`${tipo.nombre} · ${detraccionDelTipo(tipo)}`}
                                                    </option>
                                                ))}
                                        </NativeSelect>
                                        <p
                                            className={`mt-1.5 text-xs ${tipoElegido?.estado_detraccion === 'pendiente' ? 'text-warning-strong' : 'text-muted-foreground'}`}
                                        >
                                            {!tipoElegido
                                                ? 'El tipo trae la detracción, el certificado y el código SUNAT.'
                                                : [
                                                      tipoElegido.estado_detraccion ===
                                                      'pendiente'
                                                          ? 'Detracción por definir: elígela en Gestionar'
                                                          : tipoElegido.estado_detraccion ===
                                                              'no_sujeto'
                                                            ? 'No lleva detracción'
                                                            : `Detracción ${tipoElegido.codigo_detraccion} · ${tipoElegido.detraccion ?? reglaElegida?.nombre ?? ''}${reglaElegida?.porcentaje != null ? ` · ${reglaElegida.porcentaje} %` : ''} si pasa S/ 700`,
                                                      `Certificado: ${tipoElegido.certificado ?? 'no emite'}`,
                                                      tipoElegido.codigo_sunat
                                                          ? `SUNAT ${tipoElegido.codigo_sunat}`
                                                          : null,
                                                  ]
                                                      .filter(Boolean)
                                                      .join(' · ')}
                                        </p>
                                        {form.errors.tipo_de_servicio_id && (
                                            <p
                                                role="alert"
                                                className="text-destructive-strong mt-1 text-sm"
                                            >
                                                {
                                                    form.errors
                                                        .tipo_de_servicio_id
                                                }
                                            </p>
                                        )}
                                    </div>
                                    <div>
                                        <label
                                            htmlFor="servicio-nombre"
                                            className="mb-1.5 block font-medium"
                                        >
                                            Nombre del servicio *
                                        </label>
                                        <input
                                            id="servicio-nombre"
                                            name="nombre"
                                            type="text"
                                            required
                                            value={form.data.nombre}
                                            onChange={(event) =>
                                                form.setData(
                                                    'nombre',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Recarga PQS de 6 kg"
                                            className="min-h-11 w-full"
                                            aria-invalid={!!form.errors.nombre}
                                        />
                                        {form.errors.nombre && (
                                            <p
                                                role="alert"
                                                className="text-destructive-strong mt-1 text-sm"
                                            >
                                                {form.errors.nombre}
                                            </p>
                                        )}
                                    </div>
                                    <div className="grid gap-4 sm:grid-cols-3">
                                        <div>
                                            <label
                                                htmlFor="servicio-precio"
                                                className="mb-1.5 block font-medium"
                                            >
                                                Precio final (S/) *
                                            </label>
                                            <input
                                                id="servicio-precio"
                                                name="precio_venta"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                inputMode="decimal"
                                                required
                                                value={form.data.precio_venta}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'precio_venta',
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="45.00"
                                                className="min-h-11 w-full font-mono"
                                                aria-invalid={
                                                    !!form.errors.precio_venta
                                                }
                                                aria-describedby="servicio-precio-ayuda"
                                            />
                                            <p
                                                id="servicio-precio-ayuda"
                                                className="text-muted-foreground mt-1 text-xs"
                                            >
                                                {form.data
                                                    .tipo_afectacion_igv ===
                                                '10'
                                                    ? `Sin IGV S/ ${valorSinIgv.toFixed(2)} + IGV S/ ${(precio - valorSinIgv).toFixed(2)}`
                                                    : 'Sin IGV: es el precio que paga el cliente.'}
                                            </p>
                                            {form.errors.precio_venta && (
                                                <p
                                                    role="alert"
                                                    className="text-destructive-strong mt-1 text-sm"
                                                >
                                                    {form.errors.precio_venta}
                                                </p>
                                            )}
                                        </div>
                                        <div>
                                            <label
                                                htmlFor="servicio-unidad"
                                                className="mb-1.5 block font-medium"
                                            >
                                                Unidad *
                                            </label>
                                            <NativeSelect
                                                id="servicio-unidad"
                                                required
                                                value={form.data.unidad_medida}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'unidad_medida',
                                                        event.target.value,
                                                    )
                                                }
                                                className="min-h-11 w-full"
                                                aria-invalid={
                                                    !!form.errors.unidad_medida
                                                }
                                            >
                                                {unidadesMedida.map(
                                                    (unidad) => (
                                                        <option
                                                            key={unidad.codigo}
                                                            value={
                                                                unidad.codigo
                                                            }
                                                        >
                                                            {unidad.codigo} ·{' '}
                                                            {etiquetaUnidad(
                                                                unidad.codigo,
                                                                unidad.nombre,
                                                            )}
                                                        </option>
                                                    ),
                                                )}
                                            </NativeSelect>
                                            {form.errors.unidad_medida && (
                                                <p
                                                    role="alert"
                                                    className="text-destructive-strong mt-1 text-sm"
                                                >
                                                    {form.errors.unidad_medida}
                                                </p>
                                            )}
                                        </div>
                                        <div>
                                            <label
                                                htmlFor="servicio-igv"
                                                className="mb-1.5 block font-medium"
                                            >
                                                Tratamiento del IGV *
                                            </label>
                                            <NativeSelect
                                                id="servicio-igv"
                                                required
                                                value={
                                                    form.data
                                                        .tipo_afectacion_igv
                                                }
                                                onChange={(event) =>
                                                    form.setData((data) => ({
                                                        ...data,
                                                        tipo_afectacion_igv:
                                                            event.target.value,
                                                        aplica_igv:
                                                            event.target
                                                                .value === '10',
                                                    }))
                                                }
                                                className="min-h-11 w-full"
                                                aria-invalid={
                                                    !!form.errors
                                                        .tipo_afectacion_igv
                                                }
                                            >
                                                <option value="" disabled>
                                                    Selecciona el tratamiento
                                                </option>
                                                <option value="10">
                                                    Gravado · IGV 18 %
                                                </option>
                                                <option value="20">
                                                    Exonerado
                                                </option>
                                                <option value="30">
                                                    Inafecto
                                                </option>
                                            </NativeSelect>
                                            {form.errors
                                                .tipo_afectacion_igv && (
                                                <p
                                                    role="alert"
                                                    className="text-destructive-strong mt-1 text-sm"
                                                >
                                                    {
                                                        form.errors
                                                            .tipo_afectacion_igv
                                                    }
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                    {tipoElegido?.pide_extintor && (
                                        <div className="grid min-w-0 gap-4 sm:grid-cols-2">
                                            <div className="min-w-0">
                                                <label
                                                    htmlFor="servicio-agente"
                                                    className="mb-1.5 block font-medium"
                                                >
                                                    Agente del extintor
                                                </label>
                                                <div className="flex gap-1.5">
                                                    <NativeSelect
                                                        id="servicio-agente"
                                                        name="agente"
                                                        value={form.data.agente}
                                                        onChange={(event) =>
                                                            form.setData(
                                                                'agente',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        className="min-h-11 min-w-0 flex-1"
                                                    >
                                                        <OpcionesAgente vacia="Cualquier agente" />
                                                    </NativeSelect>
                                                    <BotonAgentes />
                                                </div>
                                                {form.errors.agente && (
                                                    <p
                                                        role="alert"
                                                        className="text-destructive-strong mt-1 text-sm"
                                                    >
                                                        {form.errors.agente}
                                                    </p>
                                                )}
                                            </div>
                                            <div className="min-w-0">
                                                <label
                                                    htmlFor="servicio-capacidad"
                                                    className="mb-1.5 block font-medium"
                                                >
                                                    Capacidad del extintor
                                                </label>
                                                <input
                                                    id="servicio-capacidad"
                                                    name="capacidad"
                                                    type="text"
                                                    value={form.data.capacidad}
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'capacidad',
                                                            event.target.value,
                                                        )
                                                    }
                                                    placeholder="6 kg"
                                                    className="min-h-11 w-full"
                                                    aria-invalid={
                                                        !!form.errors.capacidad
                                                    }
                                                />
                                                {form.errors.capacidad && (
                                                    <p
                                                        role="alert"
                                                        className="text-destructive-strong mt-1 text-sm"
                                                    >
                                                        {form.errors.capacidad}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </div>
                            <div className="border-border flex shrink-0 flex-col gap-2 border-t px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                                <label
                                    htmlFor="servicio-activo"
                                    className="flex min-h-11 cursor-pointer items-center gap-2 font-medium"
                                >
                                    <input
                                        id="servicio-activo"
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
                                    Disponible para nuevas ventas
                                </label>
                                <div className="flex flex-col-reverse gap-2 sm:flex-row">
                                    <button
                                        type="button"
                                        onClick={closeModal}
                                        className="border-border bg-card text-foreground/80 hover:bg-background min-h-11 w-full rounded-lg border px-4 text-sm font-semibold sm:w-auto"
                                    >
                                        Cancelar
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={form.processing}
                                        className="bg-primary hover:bg-primary/90 min-h-11 w-full rounded-lg px-4 text-sm font-semibold text-white disabled:opacity-50 sm:w-auto"
                                    >
                                        {editingService
                                            ? 'Guardar cambios'
                                            : 'Crear servicio'}
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
