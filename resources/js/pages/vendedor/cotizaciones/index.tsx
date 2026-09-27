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
import { Cargando, FilasCargando } from '@/components/cargando';
import { Button } from '@/components/ui/button';
import { useRecargando } from '@/hooks/use-recargando';
import VendedorLayout from '@/layouts/vendedor-layout';
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
    enlace_pdf: string;
    venta: { id: number; numero: string } | null;
};

/**
 * Mensaje listo para WhatsApp con el enlace al PDF de la cotización. Si el
 * cliente no tiene número guardado, WhatsApp deja elegir el contacto.
 */
function enlaceWhatsapp(quote: QuoteItem): string {
    const monto = Number(quote.total).toLocaleString('es-PE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
    const vence = new Date(
        `${quote.vigencia_hasta}T00:00:00`,
    ).toLocaleDateString('es-PE');
    const texto = `Hola ${quote.cliente}, le saludamos de Extintores Bruce Fire. Le enviamos la cotización ${quote.numero} por S/ ${monto}, válida hasta el ${vence}. Puede verla aquí: ${quote.enlace_pdf}`;

    return `https://wa.me/${quote.whatsapp ?? ''}?text=${encodeURIComponent(texto)}`;
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

function formatCurrency(amount: number | string): string {
    const num = typeof amount === 'string' ? parseFloat(amount) : amount;
    if (isNaN(num)) return 'S/ 0.00';
    return `S/ ${num.toLocaleString('es-PE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

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
                    'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 border border-amber-500/20',
                dotClass: 'bg-amber-600',
            };
        case 'aceptada':
            return {
                label: 'Aceptada',
                badgeClass:
                    'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 border border-emerald-500/20',
                dotClass: 'bg-emerald-600',
            };
        case 'rechazada':
            return {
                label: 'Rechazada',
                badgeClass:
                    'bg-destructive/10 text-destructive border border-destructive/20 border border-destructive/20',
                dotClass: 'bg-destructive',
            };
        case 'vencida':
            return {
                label: 'Vencida',
                badgeClass:
                    'bg-destructive/10 text-destructive border border-destructive/20 border border-destructive/20',
                dotClass: 'bg-destructive',
            };
        case 'convertida':
            return {
                label: 'Vendida',
                badgeClass:
                    'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20 border border-blue-500/20',
                dotClass: 'bg-blue-600',
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
                {/* KPI Cards Row */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {/* Activas */}
                    <div className="flex items-center gap-3.5 rounded-[14px] border border-border bg-card p-[16px_18px] shadow-xs">
                        <div className="flex size-[42px] shrink-0 items-center justify-center rounded-[11px] bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20">
                            <FileText className="size-5 stroke-[2]" />
                        </div>
                        <div>
                            <div className="text-[11px] font-semibold tracking-[0.03em] text-muted-foreground uppercase">
                                Activas
                            </div>
                            <div className="font-['Oswald',sans-serif] text-[22px] font-semibold text-foreground">
                                {kpis?.activas ?? 0}
                            </div>
                        </div>
                    </div>

                    {/* Por vencer (7 días) */}
                    <div className="flex items-center gap-3.5 rounded-[14px] border border-border bg-card p-[16px_18px] shadow-xs">
                        <div className="flex size-[42px] shrink-0 items-center justify-center rounded-[11px] bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                            <Clock className="size-5 stroke-[2]" />
                        </div>
                        <div>
                            <div className="text-[11px] font-semibold tracking-[0.03em] text-muted-foreground uppercase">
                                Por vencer (7 días)
                            </div>
                            <div className="font-['Oswald',sans-serif] text-[22px] font-semibold text-foreground">
                                {kpis?.por_vencer ?? 0}
                            </div>
                        </div>
                    </div>

                    {/* Aceptadas este mes */}
                    <div className="flex items-center gap-3.5 rounded-[14px] border border-border bg-card p-[16px_18px] shadow-xs">
                        <div className="flex size-[42px] shrink-0 items-center justify-center rounded-[11px] bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                            <CheckCircle2 className="size-5 stroke-[2]" />
                        </div>
                        <div>
                            <div className="text-[11px] font-semibold tracking-[0.03em] text-muted-foreground uppercase">
                                Aceptadas este mes
                            </div>
                            <div className="font-['Oswald',sans-serif] text-[22px] font-semibold text-foreground">
                                {kpis?.aceptadas_este_mes ?? 0}
                            </div>
                        </div>
                    </div>

                    {/* Vencidas */}
                    <div className="flex items-center gap-3.5 rounded-[14px] border border-border bg-card p-[16px_18px] shadow-xs">
                        <div className="flex size-[42px] shrink-0 items-center justify-center rounded-[11px] bg-destructive/10 text-destructive border border-destructive/20">
                            <AlertTriangle className="size-5 stroke-[2]" />
                        </div>
                        <div>
                            <div className="text-[11px] font-semibold tracking-[0.03em] text-muted-foreground uppercase">
                                Vencidas
                            </div>
                            <div className="font-['Oswald',sans-serif] text-[22px] font-semibold text-foreground">
                                {kpis?.vencidas ?? 0}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Main Content Card */}
                <div className="rounded-[16px] border border-border bg-card p-5 shadow-xs">
                    {/* Toolbar */}
                    <div className="mb-3.5 flex flex-wrap items-center justify-between gap-2.5">
                        {/* Status Filter Tabs */}
                        <div className="flex rounded-[9px] bg-muted p-[3px]">
                            {FILTER_TABS.map((tab) => {
                                const active = isTabActive(tab.value);
                                return (
                                    <button
                                        key={tab.label}
                                        type="button"
                                        onClick={() =>
                                            handleFilterChange(tab.value)
                                        }
                                        className={`cursor-pointer rounded-[7px] px-3.5 py-1.5 text-xs font-bold whitespace-nowrap transition-all ${
                                            active
                                                ? 'bg-card text-foreground shadow-[0_1px_2px_rgba(0,0,0,0.06)]'
                                                : 'text-muted-foreground hover:text-foreground'
                                        }`}
                                    >
                                        {tab.label}
                                    </button>
                                );
                            })}
                        </div>

                        {/* Actions Right */}
                        <div className="flex items-center gap-2">
                            <Button
                                asChild
                                className="flex h-10 cursor-pointer items-center gap-1.5 rounded-[9px] bg-primary px-4 text-[13px] font-bold text-white shadow-xs transition-colors hover:bg-primary/90"
                            >
                                <Link
                                    href={QuoteController.create.url({
                                        current_team: teamSlug,
                                    })}
                                >
                                    <Plus className="size-3.5 stroke-[2.5]" />
                                    <span>Nueva cotización</span>
                                </Link>
                            </Button>
                        </div>
                    </div>

                    {/* Table */}
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-[12.5px]">
                            <thead>
                                <tr className="border-b border-border">
                                    <th className="px-2.5 py-2.5 text-left font-mono text-[9.5px] font-semibold tracking-[0.05em] text-muted-foreground uppercase">
                                        Nº Cotización
                                    </th>
                                    <th className="px-2.5 py-2.5 text-left font-mono text-[9.5px] font-semibold tracking-[0.05em] text-muted-foreground uppercase">
                                        Cliente
                                    </th>
                                    <th className="px-2.5 py-2.5 text-left font-mono text-[9.5px] font-semibold tracking-[0.05em] text-muted-foreground uppercase">
                                        Monto
                                    </th>
                                    <th className="px-2.5 py-2.5 text-left font-mono text-[9.5px] font-semibold tracking-[0.05em] text-muted-foreground uppercase">
                                        Vigencia
                                    </th>
                                    <th className="px-2.5 py-2.5 text-left font-mono text-[9.5px] font-semibold tracking-[0.05em] text-muted-foreground uppercase">
                                        Estado
                                    </th>
                                    <th className="px-2.5 py-2.5 text-left font-mono text-[9.5px] font-semibold tracking-[0.05em] text-muted-foreground uppercase">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {recargando ? (
                                    <FilasCargando
                                        columnas={6}
                                        filas={quotes.data.length}
                                    />
                                ) : quotes.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-4 py-12 text-center text-muted-foreground"
                                        >
                                            <div className="flex flex-col items-center justify-center gap-2">
                                                <Inbox className="size-9 text-muted-foreground" />
                                                <p className="text-sm font-medium text-foreground">
                                                    No se encontraron
                                                    cotizaciones
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {currentFilter
                                                        ? 'No hay registros para el filtro seleccionado.'
                                                        : 'Aún no hay cotizaciones registradas.'}
                                                </p>
                                                {currentFilter ? (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            handleFilterChange(
                                                                '',
                                                            )
                                                        }
                                                        className="mt-2 cursor-pointer text-xs font-semibold text-primary hover:underline"
                                                    >
                                                        Limpiar filtros
                                                    </button>
                                                ) : null}
                                            </div>
                                        </td>
                                    </tr>
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
                                            <tr
                                                key={quote.id}
                                                className="border-b border-border transition-colors hover:bg-muted/40"
                                            >
                                                {/* Nº Cotización */}
                                                <td className="px-2.5 py-3.5 font-mono text-xs font-medium text-foreground">
                                                    {quote.numero}
                                                </td>

                                                {/* Cliente */}
                                                <td className="px-2.5 py-3.5 font-medium text-foreground">
                                                    {quote.cliente}
                                                </td>

                                                {/* Monto */}
                                                <td className="px-2.5 py-3.5 font-bold text-foreground">
                                                    {formatCurrency(
                                                        quote.total,
                                                    )}
                                                </td>

                                                {/* Vigencia */}
                                                <td className="px-2.5 py-3.5">
                                                    <span
                                                        className={`text-xs ${
                                                            vigencia.isExpired
                                                                ? 'font-medium text-destructive'
                                                                : vigencia.isUrgent
                                                                  ? 'font-medium text-amber-600 dark:text-amber-400'
                                                                  : 'text-foreground/80'
                                                        }`}
                                                    >
                                                        {vigencia.label}
                                                    </span>
                                                    {vigencia.subLabel ? (
                                                        <div className="font-mono text-[10px] text-muted-foreground">
                                                            {vigencia.subLabel}
                                                        </div>
                                                    ) : null}
                                                </td>

                                                {/* Estado */}
                                                <td className="px-2.5 py-3.5">
                                                    <span
                                                        className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[10.5px] font-bold ${badgeConfig.badgeClass}`}
                                                    >
                                                        <span
                                                            className={`size-1.5 rounded-full ${badgeConfig.dotClass}`}
                                                        />
                                                        {badgeConfig.label}
                                                    </span>
                                                </td>

                                                {/* Acciones */}
                                                <td className="px-2.5 py-3.5">
                                                    <div className="flex items-center gap-1.5">
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
                                                            className="flex h-7 items-center gap-1 rounded-[7px] border border-border bg-card px-2 text-[11px] font-bold text-foreground/80 shadow-xs transition-colors hover:border-primary/60 hover:text-primary"
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
                                                            ) && (
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
                                                                            : 'El cliente no tiene celular guardado: elige el contacto en WhatsApp'
                                                                    }
                                                                    className="flex h-7 items-center gap-1.5 rounded-[7px] bg-[#25D366] px-2.5 text-[11px] font-bold text-white shadow-xs transition-colors hover:bg-[#1ebe5a]"
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
                                                                    className="flex h-7 cursor-pointer items-center gap-1 rounded-[7px] bg-emerald-600 px-2.5 text-[11px] font-bold text-white shadow-xs transition-colors hover:bg-emerald-700 disabled:opacity-50"
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
                                                                    className="flex h-7 cursor-pointer items-center gap-1 rounded-[7px] border border-border bg-card px-2.5 text-[11px] font-bold text-destructive shadow-xs transition-colors hover:bg-destructive/10 disabled:opacity-50"
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
                                                                className="flex h-7 cursor-pointer items-center gap-1.5 rounded-[7px] bg-primary px-2.5 text-[11px] font-bold text-primary-foreground shadow-xs transition-colors hover:bg-primary/90"
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
                                                                className="flex h-7 items-center gap-1.5 rounded-[7px] bg-primary px-2.5 text-[11px] font-bold text-primary-foreground"
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
                                                                className="flex h-7 items-center gap-1.5 rounded-[7px] border border-border bg-card px-2.5 text-[11px] font-bold text-foreground/80 shadow-xs transition-colors hover:border-primary/60 hover:text-primary"
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
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {quotes.links && quotes.links.length > 3 && (
                        <div className="mt-4 flex flex-col items-center justify-between gap-3 border-t border-border pt-3.5 sm:flex-row">
                            <div className="text-xs text-muted-foreground">
                                Mostrando{' '}
                                <span className="font-semibold text-foreground">
                                    {quotes.from ?? 0}
                                </span>{' '}
                                a{' '}
                                <span className="font-semibold text-foreground">
                                    {quotes.to ?? 0}
                                </span>{' '}
                                de{' '}
                                <span className="font-semibold text-foreground">
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
                                                className="inline-flex h-8 min-w-[32px] items-center justify-center rounded-[7px] border border-border bg-muted/40 px-2.5 text-xs text-muted-foreground opacity-60"
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
                                            className={`inline-flex h-8 min-w-[32px] items-center justify-center rounded-[7px] px-2.5 text-xs font-semibold transition-colors ${
                                                link.active
                                                    ? 'bg-foreground text-background'
                                                    : 'border border-border bg-card text-foreground/80 hover:bg-background hover:text-foreground'
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
        </VendedorLayout>
    );
}

CotizacionesIndex.layout = (page: React.ReactNode) => page;
