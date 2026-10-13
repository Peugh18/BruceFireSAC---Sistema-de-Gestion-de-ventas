import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Check,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Clock,
    FileText,
    Inbox,
    Plus,
    MessageCircle,
    Receipt,
    RotateCcw,
    ShoppingCart,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

import QuoteController from '@/actions/App/Http/Controllers/Vendedor/QuoteController';
import FiltroSelect from '@/components/filtro-select';
import { Cargando, FilasCargando } from '@/components/cargando';
import { PageHeader } from '@/components/page-header';
import WhatsappNumeroDialog from '@/components/whatsapp-numero-dialog';
import { Button } from '@/components/ui/button';
import { Empty } from '@/components/ui/empty';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useRecargando } from '@/hooks/use-recargando';
import VendedorLayout from '@/layouts/vendedor-layout';
import { Pasos, pasosCotizacion } from '@/components/progreso';
import { soles } from '@/lib/utils';
import ordenesServicio from '@/routes/vendedor/ordenes-servicio';
import ventas from '@/routes/vendedor/ventas';
import type { Team } from '@/types';

export type QuoteItem = {
    id: number;
    numero: string;
    cliente: string;
    total: number | string;
    vigencia_hasta: string;
    estado: string;
    whatsapp: string | null;
    client_id: number;
    puede_guardar_numero: boolean;
    enlace_pdf: string;
    venta: { id: number; numero: string } | null;
    /** Tiene un servicio (recarga, mantenimiento): solo así va al taller. */
    tiene_servicio: boolean;
    orden: { id: number; codigo: string } | null;
};

/**
 * Mensaje listo para WhatsApp con el enlace al PDF de la cotización. Si el
 * cliente no tiene número guardado (solo CLIENTES VARIOS), WhatsApp deja
 * elegir el contacto.
 */
function enlaceWhatsapp(quote: QuoteItem, numero = quote.whatsapp): string {
    const monto = Number(quote.total).toLocaleString('es-PE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
    const vence = new Date(
        `${quote.vigencia_hasta}T00:00:00`,
    ).toLocaleDateString('es-PE');
    const texto = `Hola ${quote.cliente}, le saludamos de Extintores Bruce Fire. Le enviamos la cotización ${quote.numero} por S/ ${monto}, válida hasta el ${vence}. Puede verla aquí: ${quote.enlace_pdf}`;

    return `https://wa.me/${numero ?? ''}?text=${encodeURIComponent(texto)}`;
}

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type PaginatedQuotes = {
    data: QuoteItem[];
    links: PaginationLink[];
    current_page?: number;
    first_page_url?: string;
    from?: number | null;
    last_page?: number;
    last_page_url?: string;
    next_page_url?: string | null;
    path?: string;
    per_page?: number;
    prev_page_url?: string | null;
    to?: number | null;
    total?: number;
    meta?: {
        current_page: number;
        from: number | null;
        last_page: number;
        links: PaginationLink[];
        path: string;
        per_page: number;
        to: number | null;
        total: number;
    };
};

export type Kpis = {
    activas: number;
    por_vencer: number;
    aceptadas_este_mes: number;
    vencidas: number;
};

export type Filters = {
    estado?: string;
};

export type CotizacionesIndexProps = {
    quotes: PaginatedQuotes;
    filters: Filters;
    kpis: Kpis;
};

const FILTER_TABS = [
    { label: 'Todas', value: '' },
    { label: 'Borrador', value: 'borrador' },
    { label: 'Enviada', value: 'enviada' },
    { label: 'Aceptadas', value: 'aceptada' },
    { label: 'Vendidas', value: 'convertida' },
    { label: 'Rechazadas', value: 'rechazada' },
    { label: 'Vencidas', value: 'vencida' },
    { label: 'Anuladas', value: 'anulada' },
];

function formatVigencia(dateStr: string): {
    label: string;
    subLabel?: string;
    isUrgent?: boolean;
    isExpired?: boolean;
} {
    if (!dateStr) return { label: '—' };

    const parts = dateStr.split('-');
    if (parts.length !== 3) return { label: dateStr };

    const targetDate = new Date(
        Number(parts[0]),
        Number(parts[1]) - 1,
        Number(parts[2]),
    );
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    targetDate.setHours(0, 0, 0, 0);

    const diffMs = targetDate.getTime() - today.getTime();
    const diffDays = Math.round(diffMs / (1000 * 60 * 60 * 24));

    let label: string;
    let isUrgent = false;
    let isExpired = false;

    if (diffDays === 0) {
        label = 'Vence hoy';
        isUrgent = true;
    } else if (diffDays === 1) {
        label = 'Vence mañana';
        isUrgent = true;
    } else if (diffDays > 1 && diffDays <= 7) {
        label = `Vence en ${diffDays} días`;
        isUrgent = true;
    } else if (diffDays > 7) {
        label = `Vence en ${diffDays} días`;
    } else if (diffDays === -1) {
        label = 'Venció ayer';
        isExpired = true;
    } else {
        label = `Venció hace ${Math.abs(diffDays)} días`;
        isExpired = true;
    }

    return {
        label,
        subLabel: `${parts[2]}/${parts[1]}/${parts[0]}`,
        isUrgent,
        isExpired,
    };
}

function getStatusBadgeConfig(estado: string): {
    label: string;
    badgeClass: string;
    dotClass: string;
} {
    switch (estado?.toLowerCase()) {
        case 'borrador':
            return {
                label: 'Borrador',
                badgeClass:
                    'bg-muted text-muted-foreground border border-border',
                dotClass: 'bg-muted-foreground',
            };
        case 'emitida':
        case 'pendiente':
        case 'enviada':
            return {
                label: 'Enviada',
                badgeClass:
                    'bg-amber-500/10 text-warning-strong border border-amber-500/20 border border-amber-500/20',
                dotClass: 'bg-amber-600',
            };
        case 'aceptada':
            return {
                label: 'Aceptada',
                badgeClass:
                    'bg-emerald-500/10 text-success-strong border border-emerald-500/20 border border-emerald-500/20',
                dotClass: 'bg-emerald-600',
            };
        case 'rechazada':
            return {
                label: 'Rechazada',
                badgeClass:
                    'bg-destructive/10 text-destructive-strong border border-destructive/20 border border-destructive/20',
                dotClass: 'bg-destructive',
            };
        case 'vencida':
            return {
                label: 'Vencida',
                badgeClass:
                    'bg-destructive/10 text-destructive-strong border border-destructive/20 border border-destructive/20',
                dotClass: 'bg-destructive',
            };
        case 'convertida':
            return {
                label: 'Vendida',
                badgeClass:
                    'bg-emerald-500/10 text-success-strong border border-emerald-500/20',
                dotClass: 'bg-success-strong',
            };
        case 'anulada':
            return {
                label: 'Anulada',
                badgeClass:
                    'bg-muted text-muted-foreground border border-border',
                dotClass: 'bg-muted-foreground',
            };
        default:
            return {
                label: estado || '—',
                badgeClass: 'bg-muted text-muted-foreground',
                dotClass: 'bg-muted-foreground',
            };
    }
}

export default function CotizacionesIndex({
    quotes,
    filters,
    kpis,
}: CotizacionesIndexProps) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug =
        currentTeam?.slug ||
        (typeof window !== 'undefined'
            ? window.location.pathname.split('/')[1]
            : '');

    const recargando = useRecargando();
    const [processingAction, setProcessingAction] = useState<{
        id: number;
        action: 'send' | 'accept' | 'reject';
    } | null>(null);

    const currentFilter = filters?.estado || '';

    const isTabActive = (tabValue: string) => {
        if (!tabValue) {
            return (
                !currentFilter ||
                currentFilter === '' ||
                currentFilter === 'todas'
            );
        }
        return currentFilter === tabValue;
    };

    const handleFilterChange = (tabValue: string) => {
        const searchParams = new URLSearchParams(
            typeof window !== 'undefined' ? window.location.search : '',
        );

        if (tabValue) {
            searchParams.set('estado', tabValue);
        } else {
            searchParams.delete('estado');
        }
        searchParams.delete('page');

        const search = searchParams.toString();
        const base = QuoteController.index.url({ current_team: teamSlug });
        const targetUrl = search ? `${base}?${search}` : base;

        router.get(
            targetUrl,
            {},
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const mostrarError = (errors: Record<string, string>) => {
        toast.error(
            Object.values(errors)[0] ?? 'No se pudo actualizar la cotización.',
        );
    };

    const [pidiendoNumero, setPidiendoNumero] = useState<QuoteItem | null>(
        null,
    );

    const handleSend = (quote: QuoteItem) => {
        setProcessingAction({ id: quote.id, action: 'send' });
        router.post(
            QuoteController.send.url({
                current_team: teamSlug,
                quote: quote.id,
            }),
            {},
            {
                preserveScroll: true,
                onError: mostrarError,
                onFinish: () => setProcessingAction(null),
            },
        );
    };

    const handleAccept = (quote: QuoteItem) => {
        setProcessingAction({ id: quote.id, action: 'accept' });
        router.post(
            QuoteController.accept.url({
                current_team: teamSlug,
                quote: quote.id,
            }),
            {},
            {
                preserveScroll: true,
                onError: mostrarError,
                onFinish: () => setProcessingAction(null),
            },
        );
    };

    const handleReject = (quote: QuoteItem) => {
        setProcessingAction({ id: quote.id, action: 'reject' });
        router.post(
            QuoteController.reject.url({
                current_team: teamSlug,
                quote: quote.id,
            }),
            {},
            {
                preserveScroll: true,
                onError: mostrarError,
                onFinish: () => setProcessingAction(null),
            },
        );
    };

    const renderPaginationLabel = (label: string) => {
        if (
            label.includes('&laquo;') ||
            label.toLowerCase().includes('previous')
        ) {
            return (
                <span className="flex items-center gap-1">
                    <ChevronLeft className="size-3.5" />
                    <span>Anterior</span>
                </span>
            );
        }
        if (label.includes('&raquo;') || label.toLowerCase().includes('next')) {
            return (
                <span className="flex items-center gap-1">
                    <span>Siguiente</span>
                    <ChevronRight className="size-3.5" />
                </span>
            );
        }
        return <span dangerouslySetInnerHTML={{ __html: label }} />;
    };

    return (
        <VendedorLayout title="Cotizaciones">
            <Head title="Cotizaciones" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    title="Cotizaciones"
                    description="Propuestas enviadas a tus clientes. Una cotización aceptada pasa a venta con un clic."
                    actions={
                        <Button
                            asChild
                            className="bg-primary hover:bg-primary/90 h-10 rounded-lg px-4 text-[13px] font-bold text-white shadow-none"
                        >
                            <Link
                                href={QuoteController.create.url({
                                    current_team: teamSlug,
                                })}
                            >
                                <Plus className="size-3.5" />
                                Nueva cotización
                            </Link>
                        </Button>
                    }
                />
                {/* KPI Cards Row */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {/* Activas */}
                    <div className="border-border bg-card flex items-center gap-3.5 rounded-2xl border p-[16px_18px] shadow-xs">
                        <div className="border-border bg-muted text-foreground flex size-[42px] shrink-0 items-center justify-center rounded-lg border">
                            <FileText className="size-5 stroke-[2]" />
                        </div>
                        <div>
                            <div className="text-muted-foreground text-xs font-semibold tracking-[0.03em] uppercase">
                                Activas
                            </div>
                            <div className="text-foreground font-['Oswald',sans-serif] text-[22px] font-semibold">
                                {kpis?.activas ?? 0}
                            </div>
                        </div>
                    </div>

                    {/* Por vencer (7 días) */}
                    <div className="border-border bg-card flex items-center gap-3.5 rounded-2xl border p-[16px_18px] shadow-xs">
                        <div className="text-warning-strong flex size-[42px] shrink-0 items-center justify-center rounded-lg border border-amber-500/20 bg-amber-500/10">
                            <Clock className="size-5 stroke-[2]" />
                        </div>
                        <div>
                            <div className="text-muted-foreground text-xs font-semibold tracking-[0.03em] uppercase">
                                Por vencer (7 días)
                            </div>
                            <div className="text-foreground font-['Oswald',sans-serif] text-[22px] font-semibold">
                                {kpis?.por_vencer ?? 0}
                            </div>
                        </div>
                    </div>

                    {/* Aceptadas este mes */}
                    <div className="border-border bg-card flex items-center gap-3.5 rounded-2xl border p-[16px_18px] shadow-xs">
                        <div className="text-success-strong flex size-[42px] shrink-0 items-center justify-center rounded-lg border border-emerald-500/20 bg-emerald-500/10">
                            <CheckCircle2 className="size-5 stroke-[2]" />
                        </div>
                        <div>
                            <div className="text-muted-foreground text-xs font-semibold tracking-[0.03em] uppercase">
                                Aceptadas este mes
                            </div>
                            <div className="text-foreground font-['Oswald',sans-serif] text-[22px] font-semibold">
                                {kpis?.aceptadas_este_mes ?? 0}
                            </div>
                        </div>
                    </div>

                    {/* Vencidas */}
                    <div className="border-border bg-card flex items-center gap-3.5 rounded-2xl border p-[16px_18px] shadow-xs">
                        <div className="bg-destructive/10 text-destructive-strong border-destructive/20 flex size-[42px] shrink-0 items-center justify-center rounded-lg border">
                            <AlertTriangle className="size-5 stroke-[2]" />
                        </div>
                        <div>
                            <div className="text-muted-foreground text-xs font-semibold tracking-[0.03em] uppercase">
                                Vencidas
                            </div>
                            <div className="text-foreground font-['Oswald',sans-serif] text-[22px] font-semibold">
                                {kpis?.vencidas ?? 0}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Main Content Card */}
                <div className="border-border bg-card rounded-2xl border p-5 shadow-xs">
                    {/* Toolbar */}
                    <div className="mb-3.5 flex flex-wrap items-center justify-between gap-2.5">
                        <FiltroSelect
                            etiqueta="Estado"
                            valor={
                                FILTER_TABS.find((tab) =>
                                    isTabActive(tab.value),
                                )?.value ?? ''
                            }
                            opciones={FILTER_TABS.map((tab) => ({
                                value: tab.value,
                                label: tab.label,
                            }))}
                            onCambiar={handleFilterChange}
                        />
                    </div>

                    {/* Table */}
                    <div className="overflow-x-auto">
                        <Table className="w-full border-collapse text-[12.5px]">
                            <TableHeader>
                                <TableRow className="border-border border-b">
                                    <TableHead className="text-left">
                                        Nº Cotización
                                    </TableHead>
                                    <TableHead className="text-left">
                                        Cliente
                                    </TableHead>
                                    <TableHead className="text-left">
                                        Monto
                                    </TableHead>
                                    <TableHead className="text-left">
                                        Vigencia
                                    </TableHead>
                                    <TableHead className="text-left">
                                        Estado
                                    </TableHead>
                                    <TableHead className="text-left">
                                        Acciones
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {recargando ? (
                                    <FilasCargando
                                        columnas={6}
                                        filas={quotes.data.length}
                                    />
                                ) : quotes.data.length === 0 ? (
                                    <TableRow>
                                        <TableCell
                                            colSpan={6}
                                            className="text-muted-foreground px-4 text-center"
                                        >
                                            <Empty
                                                icon={<Inbox />}
                                                title="No se encontraron cotizaciones"
                                                description={
                                                    currentFilter
                                                        ? 'No hay registros para el filtro seleccionado.'
                                                        : 'Aún no hay cotizaciones registradas.'
                                                }
                                            >
                                                {currentFilter ? (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            handleFilterChange(
                                                                '',
                                                            )
                                                        }
                                                        className="text-primary-strong mt-2 cursor-pointer text-xs font-semibold hover:underline"
                                                    >
                                                        Limpiar filtros
                                                    </button>
                                                ) : (
                                                    <Button
                                                        asChild
                                                        size="sm"
                                                        className="mt-2 rounded-xl"
                                                    >
                                                        <Link
                                                            href={QuoteController.create.url(
                                                                {
                                                                    current_team:
                                                                        teamSlug,
                                                                },
                                                            )}
                                                        >
                                                            <Plus className="size-3.5" />
                                                            Crear primera
                                                            cotización
                                                        </Link>
                                                    </Button>
                                                )}
                                            </Empty>
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    quotes.data.map((quote) => {
                                        const badgeConfig =
                                            getStatusBadgeConfig(quote.estado);
                                        const vigencia = formatVigencia(
                                            quote.vigencia_hasta,
                                        );
                                        const isSending =
                                            processingAction?.id === quote.id &&
                                            processingAction.action === 'send';
                                        const isAccepting =
                                            processingAction?.id === quote.id &&
                                            processingAction.action ===
                                                'accept';
                                        const isRejecting =
                                            processingAction?.id === quote.id &&
                                            processingAction.action ===
                                                'reject';
                                        const isRowProcessing =
                                            processingAction?.id === quote.id;

                                        const canSend =
                                            quote.estado === 'borrador' ||
                                            quote.estado === 'emitida';
                                        const canAcceptOrReject =
                                            quote.estado === 'enviada';
                                        const canConvert = [
                                            'borrador',
                                            'emitida',
                                            'enviada',
                                            'pendiente',
                                            'aceptada',
                                        ].includes(quote.estado);

                                        return (
                                            <TableRow
                                                key={quote.id}
                                                className="border-border hover:bg-muted/40 border-b transition-colors"
                                            >
                                                {/* Nº Cotización */}
                                                <TableCell className="text-foreground px-2.5 py-3.5 font-mono text-xs font-medium">
                                                    {quote.numero}
                                                </TableCell>

                                                {/* Cliente */}
                                                <TableCell className="text-foreground px-2.5 py-3.5 font-medium">
                                                    {quote.cliente}
                                                </TableCell>

                                                {/* Monto */}
                                                <TableCell className="text-foreground px-2.5 py-3.5 font-bold">
                                                    {soles(quote.total)}
                                                </TableCell>

                                                {/* Vigencia */}
                                                <TableCell className="px-2.5 py-3.5">
                                                    <span
                                                        className={`text-xs ${
                                                            vigencia.isExpired
                                                                ? 'text-destructive-strong font-medium'
                                                                : vigencia.isUrgent
                                                                  ? 'text-warning-strong font-medium'
                                                                  : 'text-foreground/80'
                                                        }`}
                                                    >
                                                        {vigencia.label}
                                                    </span>
                                                    {vigencia.subLabel ? (
                                                        <div className="text-muted-foreground font-mono text-xs">
                                                            {vigencia.subLabel}
                                                        </div>
                                                    ) : null}
                                                </TableCell>

                                                {/* Estado */}
                                                <TableCell className="px-2.5 py-3.5">
                                                    <span
                                                        className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-bold ${badgeConfig.badgeClass}`}
                                                    >
                                                        <span
                                                            className={`size-1.5 rounded-full ${badgeConfig.dotClass}`}
                                                        />
                                                        {badgeConfig.label}
                                                    </span>
                                                    {pasosCotizacion(
                                                        quote.estado,
                                                    ).length > 0 && (
                                                        <Pasos
                                                            label={`Avance de la cotización ${quote.numero}`}
                                                            variante="puntos"
                                                            pasos={pasosCotizacion(
                                                                quote.estado,
                                                            )}
                                                            className="mt-1.5"
                                                        />
                                                    )}
                                                </TableCell>

                                                {/* Acciones */}
                                                <TableCell className="px-2.5 py-3.5">
                                                    <div className="flex items-center gap-1.5">
                                                        {quote.orden ? (
                                                            <Button
                                                                asChild
                                                                variant="outline"
                                                                size="sm"
                                                            >
                                                                <Link
                                                                    href={ordenesServicio.show.url(
                                                                        {
                                                                            current_team:
                                                                                teamSlug,
                                                                            service_order:
                                                                                quote
                                                                                    .orden
                                                                                    .id,
                                                                        },
                                                                    )}
                                                                >
                                                                    Ver orden{' '}
                                                                    {
                                                                        quote
                                                                            .orden
                                                                            .codigo
                                                                    }
                                                                </Link>
                                                            </Button>
                                                        ) : (
                                                            !quote.venta &&
                                                            quote.tiene_servicio &&
                                                            [
                                                                'borrador',
                                                                'emitida',
                                                                'enviada',
                                                                'aceptada',
                                                            ].includes(
                                                                quote.estado,
                                                            ) && (
                                                                <Button
                                                                    type="button"
                                                                    variant="outline"
                                                                    size="sm"
                                                                    title="Manda los equipos al taller antes de cobrar la venta"
                                                                    onClick={() =>
                                                                        router.post(
                                                                            QuoteController.iniciarOrden.url(
                                                                                {
                                                                                    current_team:
                                                                                        teamSlug,
                                                                                    quote: quote.id,
                                                                                },
                                                                            ),
                                                                            {},
                                                                            {
                                                                                onError:
                                                                                    (
                                                                                        errores,
                                                                                    ) =>
                                                                                        toast.error(
                                                                                            Object.values(
                                                                                                errores,
                                                                                            )[0] ??
                                                                                                'No se pudo abrir la orden de taller.',
                                                                                        ),
                                                                            },
                                                                        )
                                                                    }
                                                                >
                                                                    Orden de
                                                                    taller
                                                                </Button>
                                                            )
                                                        )}
                                                        {!quote.venta &&
                                                            [
                                                                'borrador',
                                                                'emitida',
                                                                'enviada',
                                                                'aceptada',
                                                            ].includes(
                                                                quote.estado,
                                                            ) && (
                                                                <Link
                                                                    href={QuoteController.edit.url(
                                                                        {
                                                                            current_team:
                                                                                teamSlug,
                                                                            quote: quote.id,
                                                                        },
                                                                    )}
                                                                    className="border-border focus-visible:ring-ring hover:bg-muted/60 rounded-md border px-2 py-1 text-xs font-bold transition-colors focus-visible:ring-2"
                                                                >
                                                                    Editar
                                                                </Link>
                                                            )}
                                                        <a
                                                            href={QuoteController.pdf.url(
                                                                {
                                                                    current_team:
                                                                        teamSlug,
                                                                    quote: quote.id,
                                                                },
                                                            )}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            title="Ver o imprimir el PDF"
                                                            className="border-border bg-card text-foreground/80 hover:border-primary/60 hover:text-primary-strong flex h-7 items-center gap-1 rounded-md border px-2 text-xs font-bold shadow-xs transition-colors"
                                                        >
                                                            <FileText className="size-3" />
                                                            <span>PDF</span>
                                                        </a>
                                                        {!quote.venta &&
                                                            ![
                                                                'rechazada',
                                                                'vencida',
                                                                'anulada',
                                                                'convertida',
                                                            ].includes(
                                                                quote.estado,
                                                            ) &&
                                                            !quote.whatsapp &&
                                                            quote.puede_guardar_numero && (
                                                                <button
                                                                    type="button"
                                                                    disabled={
                                                                        isRowProcessing
                                                                    }
                                                                    onClick={() =>
                                                                        setPidiendoNumero(
                                                                            quote,
                                                                        )
                                                                    }
                                                                    title="El cliente no tiene celular: agrégalo y se envía por WhatsApp"
                                                                    className="text-success-strong flex h-7 items-center gap-1.5 rounded-md border border-green-700 bg-green-700/5 px-2.5 text-xs font-bold shadow-xs transition-colors hover:bg-green-700/10 dark:text-green-400"
                                                                >
                                                                    <MessageCircle className="size-3" />
                                                                    <span>
                                                                        Agregar
                                                                        número
                                                                    </span>
                                                                </button>
                                                            )}
                                                        {!quote.venta &&
                                                            ![
                                                                'rechazada',
                                                                'vencida',
                                                                'anulada',
                                                                'convertida',
                                                            ].includes(
                                                                quote.estado,
                                                            ) &&
                                                            (quote.whatsapp ||
                                                                !quote.puede_guardar_numero) && (
                                                                <a
                                                                    href={enlaceWhatsapp(
                                                                        quote,
                                                                    )}
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    onClick={() => {
                                                                        if (
                                                                            canSend
                                                                        ) {
                                                                            handleSend(
                                                                                quote,
                                                                            );
                                                                        }
                                                                    }}
                                                                    title={
                                                                        quote.whatsapp
                                                                            ? 'Enviar por WhatsApp al cliente'
                                                                            : 'Clientes varios: elige el contacto en WhatsApp'
                                                                    }
                                                                    className="flex h-7 items-center gap-1.5 rounded-md bg-green-700 px-2.5 text-xs font-bold text-white shadow-xs transition-colors hover:bg-green-800"
                                                                >
                                                                    {isSending ? (
                                                                        <Cargando className="size-3" />
                                                                    ) : (
                                                                        <MessageCircle className="size-3" />
                                                                    )}
                                                                    <span>
                                                                        WhatsApp
                                                                    </span>
                                                                </a>
                                                            )}

                                                        {canAcceptOrReject && (
                                                            <>
                                                                <Button
                                                                    type="button"
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        handleAccept(
                                                                            quote,
                                                                        )
                                                                    }
                                                                    disabled={
                                                                        isRowProcessing
                                                                    }
                                                                    className="flex h-7 cursor-pointer items-center gap-1 rounded-md bg-emerald-600 px-2.5 text-xs font-bold text-white shadow-xs transition-colors hover:bg-emerald-700 disabled:opacity-50"
                                                                >
                                                                    {isAccepting ? (
                                                                        <Cargando className="size-3" />
                                                                    ) : (
                                                                        <Check className="size-3 stroke-[2.5]" />
                                                                    )}
                                                                    <span>
                                                                        Aceptar
                                                                    </span>
                                                                </Button>
                                                                <Button
                                                                    type="button"
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        handleReject(
                                                                            quote,
                                                                        )
                                                                    }
                                                                    disabled={
                                                                        isRowProcessing
                                                                    }
                                                                    className="border-border bg-card text-destructive-strong hover:bg-destructive/10 flex h-7 cursor-pointer items-center gap-1 rounded-md border px-2.5 text-xs font-bold shadow-xs transition-colors disabled:opacity-50"
                                                                >
                                                                    {isRejecting ? (
                                                                        <Cargando className="size-3" />
                                                                    ) : (
                                                                        <X className="size-3 stroke-[2.5]" />
                                                                    )}
                                                                    <span>
                                                                        Rechazar
                                                                    </span>
                                                                </Button>
                                                            </>
                                                        )}

                                                        {canConvert && (
                                                            <Link
                                                                href={ventas.create.url(
                                                                    teamSlug,
                                                                    {
                                                                        query: {
                                                                            cotizacion:
                                                                                quote.id,
                                                                        },
                                                                    },
                                                                )}
                                                                className="bg-primary text-primary-foreground hover:bg-primary/90 flex h-7 shrink-0 cursor-pointer items-center gap-1.5 rounded-md px-2.5 text-xs font-bold whitespace-nowrap shadow-xs transition-colors"
                                                            >
                                                                <ShoppingCart className="size-3" />
                                                                <span>
                                                                    Pasar a
                                                                    venta
                                                                </span>
                                                            </Link>
                                                        )}

                                                        {quote.estado ===
                                                            'vencida' && (
                                                            <Link
                                                                href={QuoteController.create.url(
                                                                    teamSlug,
                                                                    {
                                                                        query: {
                                                                            renovar:
                                                                                quote.id,
                                                                        },
                                                                    },
                                                                )}
                                                                className="bg-primary text-primary-foreground flex h-7 items-center gap-1.5 rounded-md px-2.5 text-xs font-bold transition hover:brightness-95"
                                                            >
                                                                <RotateCcw className="size-3" />
                                                                Renovar
                                                            </Link>
                                                        )}

                                                        {quote.venta ? (
                                                            <Link
                                                                href={ventas.show.url(
                                                                    {
                                                                        current_team:
                                                                            teamSlug,
                                                                        sale: quote
                                                                            .venta
                                                                            .id,
                                                                    },
                                                                )}
                                                                className="border-border bg-card text-foreground/80 hover:border-primary/60 hover:text-primary-strong flex h-7 items-center gap-1.5 rounded-md border px-2.5 text-xs font-bold shadow-xs transition-colors"
                                                            >
                                                                <Receipt className="size-3" />
                                                                <span>
                                                                    Ver venta{' '}
                                                                    {
                                                                        quote
                                                                            .venta
                                                                            .numero
                                                                    }
                                                                </span>
                                                            </Link>
                                                        ) : null}
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })
                                )}
                            </TableBody>
                        </Table>
                    </div>

                    {/* Pagination */}
                    {quotes.links && quotes.links.length > 3 && (
                        <div className="border-border mt-4 flex flex-col items-center justify-between gap-3 border-t pt-3.5 sm:flex-row">
                            <div className="text-muted-foreground text-xs">
                                Mostrando{' '}
                                <span className="text-foreground font-semibold">
                                    {quotes.from ?? 0}
                                </span>{' '}
                                a{' '}
                                <span className="text-foreground font-semibold">
                                    {quotes.to ?? 0}
                                </span>{' '}
                                de{' '}
                                <span className="text-foreground font-semibold">
                                    {quotes.total ?? 0}
                                </span>{' '}
                                cotizaciones
                            </div>

                            <div className="flex flex-wrap items-center gap-1">
                                {quotes.links.map((link, index) => {
                                    if (!link.url) {
                                        return (
                                            <span
                                                key={index}
                                                className="border-border bg-muted/40 text-muted-foreground inline-flex h-8 min-w-[32px] items-center justify-center rounded-md border px-2.5 text-xs opacity-60"
                                            >
                                                {renderPaginationLabel(
                                                    link.label,
                                                )}
                                            </span>
                                        );
                                    }

                                    return (
                                        <Link
                                            key={index}
                                            href={link.url}
                                            preserveScroll
                                            preserveState
                                            className={`inline-flex h-8 min-w-[32px] items-center justify-center rounded-md px-2.5 text-xs font-semibold transition-colors ${
                                                link.active
                                                    ? 'bg-foreground text-background'
                                                    : 'border-border bg-card text-foreground/80 hover:bg-background hover:text-foreground border'
                                            }`}
                                        >
                                            {renderPaginationLabel(link.label)}
                                        </Link>
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </div>
            </div>
            {pidiendoNumero ? (
                <WhatsappNumeroDialog
                    open
                    onOpenChange={(abierto) => {
                        if (!abierto) {
                            setPidiendoNumero(null);
                        }
                    }}
                    teamSlug={teamSlug}
                    clientId={pidiendoNumero.client_id}
                    cliente={pidiendoNumero.cliente}
                    enlace={(numero) => enlaceWhatsapp(pidiendoNumero, numero)}
                    onGuardado={() => {
                        const quote = pidiendoNumero;

                        if (['borrador', 'emitida'].includes(quote.estado)) {
                            handleSend(quote);
                        } else {
                            router.reload({ only: ['quotes'] });
                        }
                    }}
                />
            ) : null}
        </VendedorLayout>
    );
}

CotizacionesIndex.layout = (page: React.ReactNode) => page;
