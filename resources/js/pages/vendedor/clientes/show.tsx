import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    AlertTriangle,
    Award,
    Building2,
    Calendar,
    Car,
    CheckCircle2,
    Clock,
    CreditCard,
    Edit3,
    ExternalLink,
    Eye,
    FileCheck2,
    FileDown,
    FileText,
    History,
    Mail,
    MapPin,
    MessageSquare,
    Phone,
    Plus,
    RefreshCw,
    Search,
    ShieldAlert,
    ShieldCheck,
    ShoppingCart,
    Trash2,
    Truck,
    UserRound,
    Wallet,
    Wrench,
} from 'lucide-react';
import { FormEvent, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import VendedorLayout from '@/layouts/vendedor-layout';
import clientes from '@/routes/vendedor/clientes';
import sitesRoutes from '@/routes/vendedor/clientes/sites';
import vehiclesRoutes from '@/routes/vendedor/clientes/vehiculos';
import type { Team } from '@/types';

// ==================== TIPOS CONTRATO BRIEF ====================

export type Client = {
    id: number;
    codigo_interno: string;
    tipo_documento: 'ruc' | 'dni' | string;
    numero_documento: string;
    razon_social: string;
    nombre_comercial: string | null;
    telefono: string | null;
    whatsapp: string | null;
    email: string | null;
    direccion_fiscal: string | null;
    departamento: string | null;
    provincia: string | null;
    distrito: string | null;
    ubigeo: string | null;
    estado_contribuyente: string | null;
    condicion_domicilio: string | null;
    activo: boolean;
    observaciones: string | null;
};

export type ClientSite = {
    id: number;
    tipo: SiteType;
    nombre: string;
    direccion: string;
    ubigeo: string | null;
    referencia: string | null;
    contacto: string | null;
    telefono: string | null;
    email: string | null;
    estado: 'activo' | 'inactivo';
};

export type Vehicle = {
    id: number;
    placa: string;
    marca: string | null;
    modelo: string | null;
    descripcion: string | null;
    estado: 'activo' | 'inactivo';
};

export type ResumenData = {
    total_comprado: number;
    deuda_pendiente: number;
    cuotas_vencidas: number;
    extintores_activos: number;
    por_vencer_30_dias: number;
    ultima_compra: string | null;
    cotizaciones_abiertas: number;
};

export type CotizacionItem = {
    id: number;
    numero: string;
    fecha: string;
    total: number;
    estado: string;
    vigencia_hasta: string | null;
    venta?: { id: number; numero: string } | null;
};

export type VentaItem = {
    id: number;
    numero_interno: string;
    fecha: string;
    total: number;
    estado: string;
    comprobante: string | null;
    sunat_estado: string | null;
    condicion_pago: string;
    medio_pago: string | null;
    saldo_pendiente: number;
};

export type ExtintorItem = {
    id: number;
    numero_serie: string;
    producto: string;
    capacidad: string | null;
    marca: string | null;
    estado: string;
    fecha_venta: string | null;
    proxima_fecha_atencion: string | null;
    proxima_prueba_hidrostatica: string | null;
    vencido: boolean;
};

export type CertificadoItem = {
    id: number;
    numero: string;
    tipo: string;
    fecha_emision: string;
    fecha_vigencia_hasta: string | null;
    estado: string;
    venta?: { id: number; numero: string } | null;
};

export type ServicioItem = {
    id: number;
    numero: string;
    servicio: string;
    area: 'campo' | 'planta';
    estado: string;
    estado_texto: string;
    tecnico: string | null;
    fecha: string;
};

export type CuotaItem = {
    id: number;
    venta: { id: number; numero: string };
    numero_cuota: number;
    monto: number;
    saldo: number;
    fecha_vencimiento: string;
    estado: string;
    dias_vencido: number;
};

export type PagoItem = {
    id: number;
    fecha: string;
    monto: number;
    forma_pago: string;
    numero_operacion: string | null;
    venta: { id: number; numero: string };
};

export type CobranzasData = {
    cuotas: CuotaItem[];
    pagos: PagoItem[];
};

export type HistorialItem = {
    fecha: string;
    texto: string;
    usuario: string | null;
};

export type SunatData = {
    verificado: boolean;
    estado: string | null;
    condicion: string | null;
    consultado_at: string | null;
};

export type Props = {
    client: Client;
    sites?: ClientSite[];
    vehicles?: Vehicle[];
    resumen?: ResumenData;
    cotizaciones?: CotizacionItem[];
    ventas?: VentaItem[];
    extintores?: ExtintorItem[];
    certificados?: CertificadoItem[];
    servicios?: ServicioItem[];
    cobranzas?: CobranzasData;
    historial?: HistorialItem[];
    sunat?: SunatData;
};

export type TabKey =
    'resumen' | 'compras' | 'extintores' | 'servicios' | 'cobranzas' | 'datos';

export type SiteType =
    'oficina' | 'tienda' | 'planta' | 'almacen' | 'local' | 'sucursal' | 'otra';

type SiteFormData = {
    tipo: SiteType;
    nombre: string;
    direccion: string;
    ubigeo: string;
    referencia: string;
    contacto: string;
    telefono: string;
    email: string;
    estado: 'activo' | 'inactivo';
};

type VehicleFormData = {
    placa: string;
    marca: string;
    modelo: string;
    descripcion: string;
    estado: 'activo' | 'inactivo';
};

type ClientFormData = {
    tipo_documento: 'ruc' | 'dni';
    numero_documento: string;
    razon_social: string;
    nombre_comercial: string;
    telefono: string;
    whatsapp: string;
    email: string;
    direccion_fiscal: string;
    estado_contribuyente: string;
    condicion_domicilio: string;
    activo: boolean;
    observaciones: string;
};

// ==================== HELPERS FORMATO ====================

function money(amount: number | null | undefined): string {
    return `S/ ${(amount ?? 0).toLocaleString('es-PE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

function initials(value: string | undefined): string {
    if (!value) return 'CL';
    return value
        .split(/\s+/u)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

const siteTypes: SiteType[] = [
    'oficina',
    'tienda',
    'planta',
    'almacen',
    'local',
    'sucursal',
    'otra',
];

const emptySiteForm: SiteFormData = {
    tipo: 'oficina',
    nombre: '',
    direccion: '',
    ubigeo: '',
    referencia: '',
    contacto: '',
    telefono: '',
    email: '',
    estado: 'activo',
};

const emptyVehicleForm: VehicleFormData = {
    placa: '',
    marca: '',
    modelo: '',
    descripcion: '',
    estado: 'activo',
};

export default function ClienteShow({
    client,
    sites = [],
    vehicles = [],
    resumen,
    cotizaciones = [],
    ventas = [],
    extintores = [],
    certificados = [],
    servicios = [],
    cobranzas = { cuotas: [], pagos: [] },
    historial = [],
    sunat,
}: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug = currentTeam?.slug ?? '';

    const [activeTab, setActiveTab] = useState<TabKey>('resumen');
    const [comprasSubtab, setComprasSubtab] = useState<
        'ventas' | 'cotizaciones'
    >('ventas');
    const [extintoresSubtab, setExtintoresSubtab] = useState<
        'extintores' | 'certificados'
    >('extintores');
    const [cobranzasSubtab, setCobranzasSubtab] = useState<'cuotas' | 'pagos'>(
        'cuotas',
    );
    const [verificandoSunat, setVerificandoSunat] = useState(false);

    // Modales de edición
    const [isEditDialogOpen, setIsEditDialogOpen] = useState(false);
    const [siteDialogOpen, setSiteDialogOpen] = useState(false);
    const [editingSiteId, setEditingSiteId] = useState<number | null>(null);
    const [vehicleDialogOpen, setVehicleDialogOpen] = useState(false);
    const [editingVehicleId, setEditingVehicleId] = useState<number | null>(
        null,
    );

    // Formularios
    const editForm = useForm<ClientFormData>({
        tipo_documento: client.tipo_documento === 'dni' ? 'dni' : 'ruc',
        numero_documento: client.numero_documento,
        razon_social: client.razon_social,
        nombre_comercial: client.nombre_comercial ?? '',
        telefono: client.telefono ?? '',
        whatsapp: client.whatsapp ?? '',
        email: client.email ?? '',
        direccion_fiscal: client.direccion_fiscal ?? '',
        estado_contribuyente: client.estado_contribuyente ?? '',
        condicion_domicilio: client.condicion_domicilio ?? '',
        activo: client.activo,
        observaciones: client.observaciones ?? '',
    });

    const siteForm = useForm<SiteFormData>(emptySiteForm);
    const vehicleForm = useForm<VehicleFormData>(emptyVehicleForm);

    // Consulta SUNAT
    const handleConsultarSunat = () => {
        setVerificandoSunat(true);
        router.post(
            `/${teamSlug}/vendedor/clientes/${client.id}/verificar-sunat`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setVerificandoSunat(false),
            },
        );
    };

    // Guardar Cliente
    const submitEditClient = (e: FormEvent) => {
        e.preventDefault();
        editForm.put(
            clientes.update.url({ current_team: teamSlug, client: client.id }),
            {
                preserveScroll: true,
                onSuccess: () => setIsEditDialogOpen(false),
            },
        );
    };

    // Guardar Sede
    const openCreateSite = () => {
        siteForm.reset();
        siteForm.setData(emptySiteForm);
        setEditingSiteId(null);
        setSiteDialogOpen(true);
    };

    const openEditSite = (site: ClientSite) => {
        siteForm.setData({
            tipo: site.tipo,
            nombre: site.nombre,
            direccion: site.direccion,
            ubigeo: site.ubigeo ?? '',
            referencia: site.referencia ?? '',
            contacto: site.contacto ?? '',
            telefono: site.telefono ?? '',
            email: site.email ?? '',
            estado: site.estado,
        });
        setEditingSiteId(site.id);
        setSiteDialogOpen(true);
    };

    const submitSite = (e: FormEvent) => {
        e.preventDefault();
        if (editingSiteId) {
            siteForm.put(
                sitesRoutes.update.url({
                    current_team: teamSlug,
                    client: client.id,
                    site: editingSiteId,
                }),
                {
                    preserveScroll: true,
                    onSuccess: () => setSiteDialogOpen(false),
                },
            );
        } else {
            siteForm.post(
                sitesRoutes.store.url({
                    current_team: teamSlug,
                    client: client.id,
                }),
                {
                    preserveScroll: true,
                    onSuccess: () => setSiteDialogOpen(false),
                },
            );
        }
    };

    const deleteSite = (siteId: number) => {
        if (!confirm('¿Eliminar esta sede?')) return;
        router.delete(
            sitesRoutes.destroy.url({
                current_team: teamSlug,
                client: client.id,
                site: siteId,
            }),
            {
                preserveScroll: true,
            },
        );
    };

    // Guardar Vehículo
    const openCreateVehicle = () => {
        vehicleForm.reset();
        vehicleForm.setData(emptyVehicleForm);
        setEditingVehicleId(null);
        setVehicleDialogOpen(true);
    };

    const openEditVehicle = (v: Vehicle) => {
        vehicleForm.setData({
            placa: v.placa,
            marca: v.marca ?? '',
            modelo: v.modelo ?? '',
            descripcion: v.descripcion ?? '',
            estado: v.estado,
        });
        setEditingVehicleId(v.id);
        setVehicleDialogOpen(true);
    };

    const submitVehicle = (e: FormEvent) => {
        e.preventDefault();
        if (editingVehicleId) {
            vehicleForm.put(
                vehiclesRoutes.update.url({
                    current_team: teamSlug,
                    client: client.id,
                    vehicle: editingVehicleId,
                }),
                {
                    preserveScroll: true,
                    onSuccess: () => setVehicleDialogOpen(false),
                },
            );
        } else {
            vehicleForm.post(
                vehiclesRoutes.store.url({
                    current_team: teamSlug,
                    client: client.id,
                }),
                {
                    preserveScroll: true,
                    onSuccess: () => setVehicleDialogOpen(false),
                },
            );
        }
    };

    const deleteVehicle = (vehicleId: number) => {
        if (!confirm('¿Eliminar este vehículo?')) return;
        router.delete(
            vehiclesRoutes.destroy.url({
                current_team: teamSlug,
                client: client.id,
                vehicle: vehicleId,
            }),
            {
                preserveScroll: true,
            },
        );
    };

    // Estado SUNAT render
    const esDni = client.tipo_documento?.toLowerCase() === 'dni';
    const esVarios =
        client.numero_documento === '00000000' ||
        client.razon_social?.toUpperCase().includes('VARIOS');

    const renderSunatHeaderBadge = () => {
        if (esDni) {
            return (
                <Badge
                    variant="outline"
                    className="border-border text-muted-foreground text-xs font-semibold"
                >
                    No aplica (DNI)
                </Badge>
            );
        }
        if (esVarios) {
            return (
                <Badge
                    variant="outline"
                    className="border-border text-muted-foreground text-xs font-semibold"
                >
                    No aplica
                </Badge>
            );
        }

        const estadoContribuyente =
            sunat?.estado || client.estado_contribuyente;
        const condicionDomicilio =
            sunat?.condicion || client.condicion_domicilio;
        const isVerificado =
            sunat?.verificado ??
            Boolean(estadoContribuyente && condicionDomicilio);

        if (!isVerificado || (!estadoContribuyente && !condicionDomicilio)) {
            return (
                <Badge className="rounded-full border border-neutral-300 bg-neutral-100 px-3 py-1 text-xs font-bold text-neutral-600 shadow-none dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-400">
                    Sin verificar
                </Badge>
            );
        }

        const isActivo = estadoContribuyente?.toUpperCase() === 'ACTIVO';
        const isHabido = condicionDomicilio?.toUpperCase() === 'HABIDO';

        if (isActivo && isHabido) {
            return (
                <Badge className="rounded-full border border-emerald-500/20 bg-emerald-500/10 px-3 py-1 text-xs font-bold text-emerald-600 shadow-none dark:text-emerald-400">
                    <CheckCircle2 className="mr-1 inline size-3.5" />
                    Activo · Habido
                </Badge>
            );
        }

        return (
            <Badge className="rounded-full border border-destructive/20 bg-destructive/10 px-3 py-1 text-xs font-bold text-destructive shadow-none">
                <AlertTriangle className="mr-1 inline size-3.5" />
                {estadoContribuyente || 'No activo'} ·{' '}
                {condicionDomicilio || 'No habido'}
            </Badge>
        );
    };

    const tabs: { key: TabKey; label: string; icon: any; count?: number }[] = [
        { key: 'resumen', label: 'Resumen', icon: Building2 },
        {
            key: 'compras',
            label: 'Compras',
            icon: ShoppingCart,
            count: (ventas.length || 0) + (cotizaciones.length || 0),
        },
        {
            key: 'extintores',
            label: 'Extintores y Certificados',
            icon: Award,
            count: (extintores.length || 0) + (certificados.length || 0),
        },
        {
            key: 'servicios',
            label: 'Servicios',
            icon: Wrench,
            count: servicios.length || 0,
        },
        {
            key: 'cobranzas',
            label: 'Cobranzas',
            icon: CreditCard,
            count:
                cobranzas.cuotas?.filter((c) => c.estado === 'vencido')
                    .length || 0,
        },
        { key: 'datos', label: 'Datos y Sedes', icon: MapPin },
    ];

    return (
        <VendedorLayout title={`Cliente: ${client.razon_social}`}>
            <Head title={`Cliente: ${client.razon_social}`} />

            <div className="flex flex-col gap-6">
                {/* ================= CABECERA DEL CLIENTE ================= */}
                <Card className="rounded-[18px] border-border bg-card p-6 shadow-xs">
                    <div className="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                        {/* Identidad */}
                        <div className="flex items-start gap-4">
                            <div className="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary font-bold text-xl">
                                {initials(client.razon_social)}
                            </div>
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2.5">
                                    <h1 className="font-['Oswald',sans-serif] text-2xl font-bold tracking-wide text-foreground uppercase">
                                        {client.razon_social}
                                    </h1>
                                    {!client.activo && (
                                        <Badge className="bg-destructive/10 text-destructive border-destructive/20 text-xs">
                                            Inactivo
                                        </Badge>
                                    )}
                                </div>

                                <div className="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                    <span className="font-mono font-semibold text-foreground">
                                        {client.tipo_documento.toUpperCase()}:{' '}
                                        {client.numero_documento}
                                    </span>
                                    <span>&bull;</span>
                                    <span>Cód. {client.codigo_interno}</span>
                                    {client.nombre_comercial && (
                                        <>
                                            <span>&bull;</span>
                                            <span>
                                                Comercial:{' '}
                                                <b className="text-foreground">
                                                    {client.nombre_comercial}
                                                </b>
                                            </span>
                                        </>
                                    )}
                                </div>

                                <div className="mt-3 flex flex-wrap items-center gap-2">
                                    {renderSunatHeaderBadge()}

                                    {!esDni && !esVarios && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={handleConsultarSunat}
                                            disabled={verificandoSunat}
                                            className="h-7 gap-1.5 rounded-full border-border bg-card px-3 text-[11px] font-bold text-foreground/80 hover:bg-accent"
                                        >
                                            <RefreshCw
                                                className={`size-3 ${verificandoSunat ? 'animate-spin' : ''}`}
                                            />
                                            <span>
                                                {verificandoSunat
                                                    ? 'Consultando...'
                                                    : 'Consultar SUNAT'}
                                            </span>
                                        </Button>
                                    )}

                                    {sunat?.consultado_at && (
                                        <span className="text-[11px] text-muted-foreground">
                                            Consultado el {sunat.consultado_at}
                                        </span>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Botones de acción rápida */}
                        <div className="flex flex-wrap items-center gap-2.5 self-start lg:self-center">
                            <Button
                                asChild
                                variant="outline"
                                className="h-9 rounded-xl border-border bg-card text-xs font-semibold"
                            >
                                <Link
                                    href={`/${teamSlug}/vendedor/cotizaciones/nueva?client_id=${client.id}`}
                                >
                                    <Plus className="mr-1 size-3.5" />
                                    Cotizar
                                </Link>
                            </Button>
                            <Button
                                asChild
                                className="h-9 rounded-xl bg-primary text-xs font-bold text-primary-foreground hover:bg-primary/90"
                            >
                                <Link
                                    href={`/${teamSlug}/vendedor/ventas/nueva?client_id=${client.id}`}
                                >
                                    <ShoppingCart className="mr-1 size-3.5" />
                                    Nueva venta
                                </Link>
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsEditDialogOpen(true)}
                                className="h-9 rounded-xl border-border bg-card text-xs font-semibold text-foreground/80"
                            >
                                <Edit3 className="mr-1 size-3.5" />
                                Editar cliente
                            </Button>
                        </div>
                    </div>

                    {/* Datos de contacto rápidos */}
                    <div className="mt-5 grid grid-cols-1 gap-3 border-t border-border pt-4 sm:grid-cols-2 lg:grid-cols-4 text-xs">
                        <div className="flex items-center gap-2 text-muted-foreground">
                            <Phone className="size-3.5 shrink-0 text-primary" />
                            <span className="truncate">
                                {client.telefono ||
                                    client.whatsapp ||
                                    'Sin teléfono'}
                            </span>
                        </div>
                        <div className="flex items-center gap-2 text-muted-foreground">
                            <Mail className="size-3.5 shrink-0 text-primary" />
                            <span className="truncate">
                                {client.email || 'Sin correo'}
                            </span>
                        </div>
                        <div className="flex items-center gap-2 text-muted-foreground col-span-1 sm:col-span-2">
                            <MapPin className="size-3.5 shrink-0 text-primary" />
                            <span className="truncate">
                                {client.direccion_fiscal ||
                                    'Sin dirección fiscal registrada'}
                            </span>
                        </div>
                    </div>
                </Card>

                {/* ================= BARRA DE PESTAÑAS (6 PESTAÑAS) ================= */}
                <div className="flex gap-1.5 overflow-x-auto border-b border-border pb-1">
                    {tabs.map((tab) => {
                        const Icon = tab.icon;
                        const isActive = activeTab === tab.key;
                        return (
                            <button
                                key={tab.key}
                                type="button"
                                onClick={() => setActiveTab(tab.key)}
                                className={`flex items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-bold transition-all whitespace-nowrap ${
                                    isActive
                                        ? 'bg-primary text-primary-foreground shadow-sm'
                                        : 'text-muted-foreground hover:bg-accent hover:text-foreground'
                                }`}
                            >
                                <Icon className="size-4" />
                                <span>{tab.label}</span>
                                {tab.count !== undefined && tab.count > 0 && (
                                    <span
                                        className={`rounded-full px-1.5 py-0.2 text-[10px] font-extrabold ${
                                            isActive
                                                ? 'bg-primary-foreground/20 text-primary-foreground'
                                                : 'bg-muted text-muted-foreground'
                                        }`}
                                    >
                                        {tab.count}
                                    </span>
                                )}
                            </button>
                        );
                    })}
                </div>

                {/* ================= CONTENIDO DE CADA PESTAÑA ================= */}

                {/* 1. RESUMEN */}
                {activeTab === 'resumen' && (
                    <div className="space-y-6">
                        {/* KPI Cards */}
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                            <Card className="rounded-2xl border-border bg-card p-4 shadow-none">
                                <div className="text-[11px] font-bold text-muted-foreground uppercase">
                                    Total comprado
                                </div>
                                <div className="mt-1 text-xl font-bold font-['Oswald',sans-serif] text-foreground">
                                    {money(resumen?.total_comprado)}
                                </div>
                                <div className="mt-0.5 text-[11px] text-muted-foreground">
                                    Última compra:{' '}
                                    {resumen?.ultima_compra || 'Sin compras'}
                                </div>
                            </Card>

                            <Card className="rounded-2xl border-border bg-card p-4 shadow-none">
                                <div className="text-[11px] font-bold text-muted-foreground uppercase">
                                    Deuda pendiente
                                </div>
                                <div
                                    className={`mt-1 text-xl font-bold font-['Oswald',sans-serif] ${
                                        (resumen?.deuda_pendiente ?? 0) > 0
                                            ? 'text-amber-600 dark:text-amber-400'
                                            : 'text-foreground'
                                    }`}
                                >
                                    {money(resumen?.deuda_pendiente)}
                                </div>
                                <div className="mt-0.5 text-[11px] text-muted-foreground">
                                    {resumen?.cuotas_vencidas &&
                                    resumen.cuotas_vencidas > 0 ? (
                                        <span className="font-bold text-destructive">
                                            {resumen.cuotas_vencidas} cuota(s)
                                            vencida(s)
                                        </span>
                                    ) : (
                                        'Al día con pagos'
                                    )}
                                </div>
                            </Card>

                            <Card className="rounded-2xl border-border bg-card p-4 shadow-none">
                                <div className="text-[11px] font-bold text-muted-foreground uppercase">
                                    Extintores activos
                                </div>
                                <div className="mt-1 text-xl font-bold font-['Oswald',sans-serif] text-foreground">
                                    {resumen?.extintores_activos ??
                                        extintores.length}
                                </div>
                                <div className="mt-0.5 text-[11px] text-muted-foreground">
                                    {(resumen?.por_vencer_30_dias ?? 0) > 0 ? (
                                        <span className="font-bold text-amber-600 dark:text-amber-400">
                                            {resumen?.por_vencer_30_dias} por
                                            vencer en 30d
                                        </span>
                                    ) : (
                                        'Sin vencimientos urgentes'
                                    )}
                                </div>
                            </Card>

                            <Card className="rounded-2xl border-border bg-card p-4 shadow-none">
                                <div className="text-[11px] font-bold text-muted-foreground uppercase">
                                    Cotizaciones abiertas
                                </div>
                                <div className="mt-1 text-xl font-bold font-['Oswald',sans-serif] text-foreground">
                                    {resumen?.cotizaciones_abiertas ??
                                        cotizaciones.filter((c) =>
                                            [
                                                'emitida',
                                                'enviada',
                                                'borrador',
                                            ].includes(c.estado),
                                        ).length}
                                </div>
                                <div className="mt-0.5 text-[11px] text-muted-foreground">
                                    En negociación
                                </div>
                            </Card>
                        </div>

                        {/* Overview: Últimas ventas y extintores próximos */}
                        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                            {/* Panel Últimas Ventas */}
                            <Card className="rounded-2xl border-border bg-card p-5 shadow-none">
                                <div className="flex items-center justify-between pb-3 border-b border-border">
                                    <div className="flex items-center gap-2 font-bold text-sm text-foreground">
                                        <ShoppingCart className="size-4 text-primary" />
                                        <span>Últimas ventas</span>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setActiveTab('compras');
                                            setComprasSubtab('ventas');
                                        }}
                                        className="text-xs font-bold text-primary hover:underline"
                                    >
                                        Ver todas &rarr;
                                    </button>
                                </div>

                                {ventas.length === 0 ? (
                                    <div className="py-8 text-center text-xs text-muted-foreground">
                                        No hay ventas registradas para este
                                        cliente aún.
                                    </div>
                                ) : (
                                    <div className="divide-y divide-border">
                                        {ventas.slice(0, 4).map((v) => (
                                            <div
                                                key={v.id}
                                                className="flex items-center justify-between py-3 text-xs"
                                            >
                                                <div>
                                                    <Link
                                                        href={`/${teamSlug}/vendedor/ventas/${v.id}`}
                                                        className="font-bold text-foreground hover:text-primary"
                                                    >
                                                        {v.numero_interno}
                                                    </Link>
                                                    <div className="text-[11px] text-muted-foreground">
                                                        {v.fecha} ·{' '}
                                                        {v.comprobante ||
                                                            'Nota de venta'}
                                                    </div>
                                                </div>
                                                <div className="text-right">
                                                    <span className="font-bold text-foreground">
                                                        {money(v.total)}
                                                    </span>
                                                    <div className="text-[10.5px]">
                                                        {v.saldo_pendiente >
                                                        0 ? (
                                                            <span className="text-amber-600 font-semibold">
                                                                Saldo:{' '}
                                                                {money(
                                                                    v.saldo_pendiente,
                                                                )}
                                                            </span>
                                                        ) : (
                                                            <span className="text-emerald-600 font-semibold">
                                                                Cancelado
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </Card>

                            {/* Panel Extintores por Vencer */}
                            <Card className="rounded-2xl border-border bg-card p-5 shadow-none">
                                <div className="flex items-center justify-between pb-3 border-b border-border">
                                    <div className="flex items-center gap-2 font-bold text-sm text-foreground">
                                        <Award className="size-4 text-primary" />
                                        <span>Extintores y Mantenimiento</span>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setActiveTab('extintores');
                                            setExtintoresSubtab('extintores');
                                        }}
                                        className="text-xs font-bold text-primary hover:underline"
                                    >
                                        Ver parque &rarr;
                                    </button>
                                </div>

                                {extintores.length === 0 ? (
                                    <div className="py-8 text-center text-xs text-muted-foreground">
                                        No hay extintores vinculados a este
                                        cliente.
                                    </div>
                                ) : (
                                    <div className="divide-y divide-border">
                                        {extintores.slice(0, 4).map((ext) => (
                                            <div
                                                key={ext.id}
                                                className="flex items-center justify-between py-3 text-xs"
                                            >
                                                <div>
                                                    <span className="font-mono font-bold text-foreground">
                                                        {ext.numero_serie}
                                                    </span>
                                                    <div className="text-[11px] text-muted-foreground">
                                                        {ext.producto} ·{' '}
                                                        {ext.capacidad}
                                                    </div>
                                                </div>
                                                <div className="text-right">
                                                    {ext.vencido ? (
                                                        <Badge className="border-destructive/20 bg-destructive/10 text-destructive text-[10px]">
                                                            Vencido
                                                        </Badge>
                                                    ) : (
                                                        <Badge className="border-emerald-500/20 bg-emerald-500/10 text-emerald-600 text-[10px]">
                                                            Al día
                                                        </Badge>
                                                    )}
                                                    <div className="mt-0.5 text-[10.5px] text-muted-foreground">
                                                        Próx:{' '}
                                                        {ext.proxima_fecha_atencion ||
                                                            'N/A'}
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </Card>
                        </div>
                    </div>
                )}

                {/* 2. COMPRAS (Ventas + Cotizaciones) */}
                {activeTab === 'compras' && (
                    <Card className="rounded-2xl border-border bg-card p-6 shadow-none">
                        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border pb-4">
                            <div className="flex gap-2">
                                <button
                                    type="button"
                                    onClick={() => setComprasSubtab('ventas')}
                                    className={`rounded-xl px-3.5 py-1.5 text-xs font-bold transition-colors ${
                                        comprasSubtab === 'ventas'
                                            ? 'bg-primary text-primary-foreground'
                                            : 'bg-muted text-muted-foreground hover:text-foreground'
                                    }`}
                                >
                                    Ventas ({ventas.length})
                                </button>
                                <button
                                    type="button"
                                    onClick={() =>
                                        setComprasSubtab('cotizaciones')
                                    }
                                    className={`rounded-xl px-3.5 py-1.5 text-xs font-bold transition-colors ${
                                        comprasSubtab === 'cotizaciones'
                                            ? 'bg-primary text-primary-foreground'
                                            : 'bg-muted text-muted-foreground hover:text-foreground'
                                    }`}
                                >
                                    Cotizaciones ({cotizaciones.length})
                                </button>
                            </div>

                            <Button
                                asChild
                                size="sm"
                                className="h-8 rounded-xl bg-primary text-xs font-bold"
                            >
                                {comprasSubtab === 'ventas' ? (
                                    <Link
                                        href={`/${teamSlug}/vendedor/ventas/nueva?client_id=${client.id}`}
                                    >
                                        <Plus className="mr-1 size-3.5" />
                                        Nueva venta
                                    </Link>
                                ) : (
                                    <Link
                                        href={`/${teamSlug}/vendedor/cotizaciones/nueva?client_id=${client.id}`}
                                    >
                                        <Plus className="mr-1 size-3.5" />
                                        Nueva cotización
                                    </Link>
                                )}
                            </Button>
                        </div>

                        {comprasSubtab === 'ventas' ? (
                            <div className="mt-4 overflow-x-auto">
                                <table className="w-full text-left text-xs">
                                    <thead>
                                        <tr className="border-b border-border text-[11px] font-bold text-muted-foreground uppercase">
                                            <th className="py-2.5">
                                                N.º Venta
                                            </th>
                                            <th className="py-2.5">Fecha</th>
                                            <th className="py-2.5">
                                                Comprobante
                                            </th>
                                            <th className="py-2.5">SUNAT</th>
                                            <th className="py-2.5">
                                                Condición
                                            </th>
                                            <th className="py-2.5">Total</th>
                                            <th className="py-2.5">Saldo</th>
                                            <th className="py-2.5 text-right">
                                                Acción
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {ventas.length === 0 ? (
                                            <tr>
                                                <td
                                                    colSpan={8}
                                                    className="py-8 text-center text-muted-foreground"
                                                >
                                                    No hay ventas registradas.
                                                </td>
                                            </tr>
                                        ) : (
                                            ventas.map((v) => (
                                                <tr
                                                    key={v.id}
                                                    className="hover:bg-muted/30"
                                                >
                                                    <td className="py-3 font-bold font-mono text-foreground">
                                                        <Link
                                                            href={`/${teamSlug}/vendedor/ventas/${v.id}`}
                                                            className="hover:text-primary hover:underline"
                                                        >
                                                            {v.numero_interno}
                                                        </Link>
                                                    </td>
                                                    <td className="py-3 text-muted-foreground">
                                                        {v.fecha}
                                                    </td>
                                                    <td className="py-3 font-medium">
                                                        {v.comprobante ||
                                                            'Nota de venta'}
                                                    </td>
                                                    <td className="py-3">
                                                        {v.sunat_estado ? (
                                                            <Badge
                                                                className={`text-[10px] ${
                                                                    v.sunat_estado ===
                                                                    'aceptado'
                                                                        ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20'
                                                                        : 'bg-amber-500/10 text-amber-600 border-amber-500/20'
                                                                }`}
                                                            >
                                                                {v.sunat_estado}
                                                            </Badge>
                                                        ) : (
                                                            <span className="text-muted-foreground">
                                                                -
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="py-3 capitalize text-muted-foreground">
                                                        {v.condicion_pago}
                                                        {v.medio_pago
                                                            ? ` (${v.medio_pago})`
                                                            : ''}
                                                    </td>
                                                    <td className="py-3 font-bold">
                                                        {money(v.total)}
                                                    </td>
                                                    <td className="py-3">
                                                        {v.saldo_pendiente >
                                                        0 ? (
                                                            <span className="font-bold text-amber-600">
                                                                {money(
                                                                    v.saldo_pendiente,
                                                                )}
                                                            </span>
                                                        ) : (
                                                            <span className="text-emerald-600 font-semibold">
                                                                Pagado
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="py-3 text-right">
                                                        <Button
                                                            asChild
                                                            variant="outline"
                                                            size="sm"
                                                            className="h-7 text-xs"
                                                        >
                                                            <Link
                                                                href={`/${teamSlug}/vendedor/ventas/${v.id}`}
                                                            >
                                                                Ver venta
                                                            </Link>
                                                        </Button>
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <div className="mt-4 overflow-x-auto">
                                <table className="w-full text-left text-xs">
                                    <thead>
                                        <tr className="border-b border-border text-[11px] font-bold text-muted-foreground uppercase">
                                            <th className="py-2.5">
                                                N.º Cotización
                                            </th>
                                            <th className="py-2.5">Fecha</th>
                                            <th className="py-2.5">Vigencia</th>
                                            <th className="py-2.5">Total</th>
                                            <th className="py-2.5">Estado</th>
                                            <th className="py-2.5">
                                                Venta Vinculada
                                            </th>
                                            <th className="py-2.5 text-right">
                                                Acción
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {cotizaciones.length === 0 ? (
                                            <tr>
                                                <td
                                                    colSpan={7}
                                                    className="py-8 text-center text-muted-foreground"
                                                >
                                                    No hay cotizaciones
                                                    registradas.
                                                </td>
                                            </tr>
                                        ) : (
                                            cotizaciones.map((c) => (
                                                <tr
                                                    key={c.id}
                                                    className="hover:bg-muted/30"
                                                >
                                                    <td className="py-3 font-bold font-mono text-foreground">
                                                        {c.numero}
                                                    </td>
                                                    <td className="py-3 text-muted-foreground">
                                                        {c.fecha}
                                                    </td>
                                                    <td className="py-3 text-muted-foreground">
                                                        {c.vigencia_hasta ||
                                                            '-'}
                                                    </td>
                                                    <td className="py-3 font-bold">
                                                        {money(c.total)}
                                                    </td>
                                                    <td className="py-3">
                                                        <Badge
                                                            variant="outline"
                                                            className="text-[10px] capitalize"
                                                        >
                                                            {c.estado}
                                                        </Badge>
                                                    </td>
                                                    <td className="py-3">
                                                        {c.venta ? (
                                                            <Link
                                                                href={`/${teamSlug}/vendedor/ventas/${c.venta.id}`}
                                                                className="font-mono text-primary font-bold hover:underline"
                                                            >
                                                                {c.venta.numero}
                                                            </Link>
                                                        ) : (
                                                            <span className="text-muted-foreground">
                                                                -
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="py-3 text-right">
                                                        <Button
                                                            asChild
                                                            variant="outline"
                                                            size="sm"
                                                            className="h-7 text-xs"
                                                        >
                                                            <a
                                                                href={`/${teamSlug}/vendedor/cotizaciones/${c.id}/pdf`}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                            >
                                                                PDF
                                                            </a>
                                                        </Button>
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Card>
                )}

                {/* 3. EXTINTORES Y CERTIFICADOS */}
                {activeTab === 'extintores' && (
                    <Card className="rounded-2xl border-border bg-card p-6 shadow-none">
                        <div className="flex gap-2 border-b border-border pb-4">
                            <button
                                type="button"
                                onClick={() =>
                                    setExtintoresSubtab('extintores')
                                }
                                className={`rounded-xl px-3.5 py-1.5 text-xs font-bold transition-colors ${
                                    extintoresSubtab === 'extintores'
                                        ? 'bg-primary text-primary-foreground'
                                        : 'bg-muted text-muted-foreground hover:text-foreground'
                                }`}
                            >
                                Parque de extintores ({extintores.length})
                            </button>
                            <button
                                type="button"
                                onClick={() =>
                                    setExtintoresSubtab('certificados')
                                }
                                className={`rounded-xl px-3.5 py-1.5 text-xs font-bold transition-colors ${
                                    extintoresSubtab === 'certificados'
                                        ? 'bg-primary text-primary-foreground'
                                        : 'bg-muted text-muted-foreground hover:text-foreground'
                                }`}
                            >
                                Certificados emitidos ({certificados.length})
                            </button>
                        </div>

                        {extintoresSubtab === 'extintores' ? (
                            <div className="mt-4 overflow-x-auto">
                                <table className="w-full text-left text-xs">
                                    <thead>
                                        <tr className="border-b border-border text-[11px] font-bold text-muted-foreground uppercase">
                                            <th className="py-2.5">
                                                Serie BF-EQ
                                            </th>
                                            <th className="py-2.5">Producto</th>
                                            <th className="py-2.5">
                                                Capacidad / Marca
                                            </th>
                                            <th className="py-2.5">F. Venta</th>
                                            <th className="py-2.5">
                                                Próx. Recarga
                                            </th>
                                            <th className="py-2.5">
                                                Próx. P. Hidrostática
                                            </th>
                                            <th className="py-2.5 text-right">
                                                Semáforo
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {extintores.length === 0 ? (
                                            <tr>
                                                <td
                                                    colSpan={7}
                                                    className="py-8 text-center text-muted-foreground"
                                                >
                                                    No hay extintores
                                                    registrados para este
                                                    cliente.
                                                </td>
                                            </tr>
                                        ) : (
                                            extintores.map((e) => (
                                                <tr
                                                    key={e.id}
                                                    className="hover:bg-muted/30"
                                                >
                                                    <td className="py-3 font-mono font-bold text-foreground">
                                                        {e.numero_serie}
                                                    </td>
                                                    <td className="py-3 font-medium">
                                                        {e.producto}
                                                    </td>
                                                    <td className="py-3 text-muted-foreground">
                                                        {[e.capacidad, e.marca]
                                                            .filter(Boolean)
                                                            .join(' · ') || '-'}
                                                    </td>
                                                    <td className="py-3 text-muted-foreground">
                                                        {e.fecha_venta || '-'}
                                                    </td>
                                                    <td className="py-3 font-semibold">
                                                        {e.proxima_fecha_atencion ||
                                                            '-'}
                                                    </td>
                                                    <td className="py-3 text-muted-foreground">
                                                        {e.proxima_prueba_hidrostatica ||
                                                            '-'}
                                                    </td>
                                                    <td className="py-3 text-right">
                                                        {e.vencido ? (
                                                            <Badge className="border-destructive/20 bg-destructive/10 text-destructive text-[10.5px]">
                                                                Vencido
                                                            </Badge>
                                                        ) : (
                                                            <Badge className="border-emerald-500/20 bg-emerald-500/10 text-emerald-600 text-[10.5px]">
                                                                Al día
                                                            </Badge>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <div className="mt-4 overflow-x-auto">
                                <table className="w-full text-left text-xs">
                                    <thead>
                                        <tr className="border-b border-border text-[11px] font-bold text-muted-foreground uppercase">
                                            <th className="py-2.5">
                                                N.º Certificado
                                            </th>
                                            <th className="py-2.5">Tipo</th>
                                            <th className="py-2.5">Emisión</th>
                                            <th className="py-2.5">
                                                Vigencia hasta
                                            </th>
                                            <th className="py-2.5">Estado</th>
                                            <th className="py-2.5">Venta</th>
                                            <th className="py-2.5 text-right">
                                                PDF
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {certificados.length === 0 ? (
                                            <tr>
                                                <td
                                                    colSpan={7}
                                                    className="py-8 text-center text-muted-foreground"
                                                >
                                                    No hay certificados
                                                    emitidos.
                                                </td>
                                            </tr>
                                        ) : (
                                            certificados.map((c) => (
                                                <tr
                                                    key={c.id}
                                                    className="hover:bg-muted/30"
                                                >
                                                    <td className="py-3 font-mono font-bold text-foreground">
                                                        {c.numero}
                                                    </td>
                                                    <td className="py-3 font-medium">
                                                        {c.tipo}
                                                    </td>
                                                    <td className="py-3 text-muted-foreground">
                                                        {c.fecha_emision}
                                                    </td>
                                                    <td className="py-3 font-semibold">
                                                        {c.fecha_vigencia_hasta ||
                                                            '-'}
                                                    </td>
                                                    <td className="py-3">
                                                        <Badge
                                                            className={`text-[10px] ${
                                                                c.estado ===
                                                                'vigente'
                                                                    ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600'
                                                                    : 'border-destructive/20 bg-destructive/10 text-destructive'
                                                            }`}
                                                        >
                                                            {c.estado}
                                                        </Badge>
                                                    </td>
                                                    <td className="py-3">
                                                        {c.venta ? (
                                                            <Link
                                                                href={`/${teamSlug}/vendedor/ventas/${c.venta.id}`}
                                                                className="font-mono text-primary hover:underline"
                                                            >
                                                                {c.venta.numero}
                                                            </Link>
                                                        ) : (
                                                            '-'
                                                        )}
                                                    </td>
                                                    <td className="py-3 text-right">
                                                        <Button
                                                            asChild
                                                            variant="outline"
                                                            size="sm"
                                                            className="h-7 text-xs"
                                                        >
                                                            <a
                                                                href={`/${teamSlug}/vendedor/certificados/${c.id}/pdf`}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                            >
                                                                Descargar PDF
                                                            </a>
                                                        </Button>
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Card>
                )}

                {/* 4. SERVICIOS (Órdenes de servicio) */}
                {activeTab === 'servicios' && (
                    <Card className="rounded-2xl border-border bg-card p-6 shadow-none">
                        <div className="flex items-center justify-between border-b border-border pb-4">
                            <div>
                                <h3 className="text-base font-bold text-foreground">
                                    Órdenes de servicio
                                </h3>
                                <p className="text-xs text-muted-foreground">
                                    Historial técnico de recargas,
                                    mantenimientos e inspecciones
                                </p>
                            </div>
                            <Button
                                asChild
                                size="sm"
                                className="h-8 rounded-xl bg-primary text-xs font-bold"
                            >
                                <Link
                                    href={`/${teamSlug}/vendedor/ordenes-servicio`}
                                >
                                    Ver taller
                                </Link>
                            </Button>
                        </div>

                        <div className="mt-4 overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead>
                                    <tr className="border-b border-border text-[11px] font-bold text-muted-foreground uppercase">
                                        <th className="py-2.5">Código OS</th>
                                        <th className="py-2.5">Fecha</th>
                                        <th className="py-2.5">Servicio</th>
                                        <th className="py-2.5">Área</th>
                                        <th className="py-2.5">Estado</th>
                                        <th className="py-2.5">Técnico</th>
                                        <th className="py-2.5 text-right">
                                            Acción
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {servicios.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={7}
                                                className="py-8 text-center text-muted-foreground"
                                            >
                                                No hay órdenes de servicio
                                                registradas para este cliente.
                                            </td>
                                        </tr>
                                    ) : (
                                        servicios.map((s) => (
                                            <tr
                                                key={s.id}
                                                className="hover:bg-muted/30"
                                            >
                                                <td className="py-3 font-mono font-bold text-foreground">
                                                    {s.numero}
                                                </td>
                                                <td className="py-3 text-muted-foreground">
                                                    {s.fecha}
                                                </td>
                                                <td className="py-3 font-semibold">
                                                    {s.servicio}
                                                </td>
                                                <td className="py-3">
                                                    <Badge
                                                        className={`text-[10.5px] uppercase ${
                                                            s.area === 'planta'
                                                                ? 'bg-blue-500/10 text-blue-600 border-blue-500/20'
                                                                : 'bg-purple-500/10 text-purple-600 border-purple-500/20'
                                                        }`}
                                                    >
                                                        {s.area}
                                                    </Badge>
                                                </td>
                                                <td className="py-3">
                                                    <Badge
                                                        variant="outline"
                                                        className="text-[11px]"
                                                    >
                                                        {s.estado_texto ||
                                                            s.estado}
                                                    </Badge>
                                                </td>
                                                <td className="py-3 text-muted-foreground">
                                                    {s.tecnico || (
                                                        <span className="text-amber-600 font-semibold">
                                                            Por asignar
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="py-3 text-right">
                                                    <Button
                                                        asChild
                                                        variant="outline"
                                                        size="sm"
                                                        className="h-7 text-xs"
                                                    >
                                                        <Link
                                                            href={`/${teamSlug}/vendedor/comunicacion?orden=${s.id}`}
                                                        >
                                                            Seguimiento
                                                        </Link>
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </Card>
                )}

                {/* 5. COBRANZAS (Cuotas + Pagos) */}
                {activeTab === 'cobranzas' && (
                    <Card className="rounded-2xl border-border bg-card p-6 shadow-none">
                        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border pb-4">
                            <div className="flex gap-2">
                                <button
                                    type="button"
                                    onClick={() => setCobranzasSubtab('cuotas')}
                                    className={`rounded-xl px-3.5 py-1.5 text-xs font-bold transition-colors ${
                                        cobranzasSubtab === 'cuotas'
                                            ? 'bg-primary text-primary-foreground'
                                            : 'bg-muted text-muted-foreground hover:text-foreground'
                                    }`}
                                >
                                    Cuotas y saldos (
                                    {cobranzas.cuotas?.length || 0})
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setCobranzasSubtab('pagos')}
                                    className={`rounded-xl px-3.5 py-1.5 text-xs font-bold transition-colors ${
                                        cobranzasSubtab === 'pagos'
                                            ? 'bg-primary text-primary-foreground'
                                            : 'bg-muted text-muted-foreground hover:text-foreground'
                                    }`}
                                >
                                    Historial de pagos (
                                    {cobranzas.pagos?.length || 0})
                                </button>
                            </div>

                            <Button
                                asChild
                                size="sm"
                                variant="outline"
                                className="h-8 rounded-xl text-xs font-bold"
                            >
                                <Link href={`/${teamSlug}/vendedor/cobranzas`}>
                                    Ir a cobranzas generales
                                </Link>
                            </Button>
                        </div>

                        {cobranzasSubtab === 'cuotas' ? (
                            <div className="mt-4 overflow-x-auto">
                                <table className="w-full text-left text-xs">
                                    <thead>
                                        <tr className="border-b border-border text-[11px] font-bold text-muted-foreground uppercase">
                                            <th className="py-2.5">Venta</th>
                                            <th className="py-2.5">Cuota</th>
                                            <th className="py-2.5">Monto</th>
                                            <th className="py-2.5">Saldo</th>
                                            <th className="py-2.5">
                                                Vencimiento
                                            </th>
                                            <th className="py-2.5">Estado</th>
                                            <th className="py-2.5 text-right">
                                                Recordatorio
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {!cobranzas.cuotas ||
                                        cobranzas.cuotas.length === 0 ? (
                                            <tr>
                                                <td
                                                    colSpan={7}
                                                    className="py-8 text-center text-muted-foreground"
                                                >
                                                    No hay cuotas de crédito
                                                    registradas para este
                                                    cliente.
                                                </td>
                                            </tr>
                                        ) : (
                                            cobranzas.cuotas.map((cuota) => {
                                                const isVencido =
                                                    cuota.estado === 'vencido';
                                                const phoneClean = (
                                                    client.whatsapp ||
                                                    client.telefono ||
                                                    ''
                                                ).replace(/\D/g, '');
                                                const waText =
                                                    encodeURIComponent(
                                                        `Hola ${client.razon_social}, le recordamos su cuota ${cuota.numero_cuota} de ${money(cuota.monto)} con saldo pendiente de ${money(cuota.saldo)} que venció el ${cuota.fecha_vencimiento}. ¿Podría confirmarnos su fecha estimada de pago? Muchas gracias.`,
                                                    );

                                                return (
                                                    <tr
                                                        key={cuota.id}
                                                        className="hover:bg-muted/30"
                                                    >
                                                        <td className="py-3 font-bold font-mono text-foreground">
                                                            <Link
                                                                href={`/${teamSlug}/vendedor/ventas/${cuota.venta.id}`}
                                                                className="hover:text-primary hover:underline"
                                                            >
                                                                {
                                                                    cuota.venta
                                                                        .numero
                                                                }
                                                            </Link>
                                                        </td>
                                                        <td className="py-3 font-semibold">
                                                            Cuota{' '}
                                                            {cuota.numero_cuota}
                                                        </td>
                                                        <td className="py-3 text-muted-foreground">
                                                            {money(cuota.monto)}
                                                        </td>
                                                        <td className="py-3 font-bold text-foreground">
                                                            {money(cuota.saldo)}
                                                        </td>
                                                        <td className="py-3 font-semibold">
                                                            {
                                                                cuota.fecha_vencimiento
                                                            }
                                                            {isVencido &&
                                                                cuota.dias_vencido >
                                                                    0 && (
                                                                    <span className="block text-[10px] text-destructive">
                                                                        Hace{' '}
                                                                        {
                                                                            cuota.dias_vencido
                                                                        }{' '}
                                                                        día(s)
                                                                    </span>
                                                                )}
                                                        </td>
                                                        <td className="py-3">
                                                            <Badge
                                                                className={`text-[10px] ${
                                                                    isVencido
                                                                        ? 'border-destructive/20 bg-destructive/10 text-destructive'
                                                                        : 'border-amber-500/20 bg-amber-500/10 text-amber-600'
                                                                }`}
                                                            >
                                                                {cuota.estado}
                                                            </Badge>
                                                        </td>
                                                        <td className="py-3 text-right">
                                                            {phoneClean ? (
                                                                <a
                                                                    href={`https://wa.me/${phoneClean}?text=${waText}`}
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    className="inline-flex h-7 items-center gap-1 rounded-lg border border-emerald-500/20 bg-emerald-500/10 px-2.5 text-[11px] font-bold text-emerald-600 hover:bg-emerald-500/20 dark:text-emerald-400"
                                                                >
                                                                    <MessageSquare className="size-3" />
                                                                    WhatsApp
                                                                </a>
                                                            ) : (
                                                                <span className="text-[11px] text-muted-foreground">
                                                                    Sin teléfono
                                                                </span>
                                                            )}
                                                        </td>
                                                    </tr>
                                                );
                                            })
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <div className="mt-4 overflow-x-auto">
                                <table className="w-full text-left text-xs">
                                    <thead>
                                        <tr className="border-b border-border text-[11px] font-bold text-muted-foreground uppercase">
                                            <th className="py-2.5">Fecha</th>
                                            <th className="py-2.5">Venta</th>
                                            <th className="py-2.5">
                                                Monto Pagado
                                            </th>
                                            <th className="py-2.5">
                                                Forma de Pago
                                            </th>
                                            <th className="py-2.5">
                                                N.º Operación
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {!cobranzas.pagos ||
                                        cobranzas.pagos.length === 0 ? (
                                            <tr>
                                                <td
                                                    colSpan={5}
                                                    className="py-8 text-center text-muted-foreground"
                                                >
                                                    No hay pagos registrados.
                                                </td>
                                            </tr>
                                        ) : (
                                            cobranzas.pagos.map((pago) => (
                                                <tr
                                                    key={pago.id}
                                                    className="hover:bg-muted/30"
                                                >
                                                    <td className="py-3 text-muted-foreground">
                                                        {pago.fecha}
                                                    </td>
                                                    <td className="py-3 font-mono font-bold text-foreground">
                                                        <Link
                                                            href={`/${teamSlug}/vendedor/ventas/${pago.venta.id}`}
                                                            className="hover:text-primary hover:underline"
                                                        >
                                                            {pago.venta.numero}
                                                        </Link>
                                                    </td>
                                                    <td className="py-3 font-bold text-emerald-600">
                                                        {money(pago.monto)}
                                                    </td>
                                                    <td className="py-3 capitalize text-muted-foreground">
                                                        {pago.forma_pago}
                                                    </td>
                                                    <td className="py-3 font-mono text-muted-foreground">
                                                        {pago.numero_operacion ||
                                                            '-'}
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Card>
                )}

                {/* 6. DATOS Y SEDES (Comerciales, Sedes, Vehículos, Historial) */}
                {activeTab === 'datos' && (
                    <div className="space-y-6">
                        {/* Sedes del cliente */}
                        <Card className="rounded-2xl border-border bg-card p-6 shadow-none">
                            <div className="flex items-center justify-between border-b border-border pb-4">
                                <div>
                                    <h3 className="text-base font-bold text-foreground">
                                        Sedes y sucursales ({sites.length})
                                    </h3>
                                    <p className="text-xs text-muted-foreground">
                                        Puntos de entrega, locales y almacenes
                                        del cliente
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    size="sm"
                                    onClick={openCreateSite}
                                    className="h-8 rounded-xl bg-primary text-xs font-bold"
                                >
                                    <Plus className="mr-1 size-3.5" />
                                    Agregar sede
                                </Button>
                            </div>

                            <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                {sites.length === 0 ? (
                                    <p className="col-span-full py-6 text-center text-xs text-muted-foreground">
                                        No hay sedes registradas para este
                                        cliente.
                                    </p>
                                ) : (
                                    sites.map((s) => (
                                        <Card
                                            key={s.id}
                                            className="rounded-xl border border-border p-4 shadow-none"
                                        >
                                            <div className="flex items-start justify-between">
                                                <div>
                                                    <Badge
                                                        variant="outline"
                                                        className="text-[10px] uppercase"
                                                    >
                                                        {s.tipo}
                                                    </Badge>
                                                    <h4 className="mt-1 font-bold text-sm text-foreground">
                                                        {s.nombre}
                                                    </h4>
                                                </div>
                                                <div className="flex gap-1">
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            openEditSite(s)
                                                        }
                                                        className="p-1 text-muted-foreground hover:text-foreground"
                                                    >
                                                        <Edit3 className="size-3.5" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            deleteSite(s.id)
                                                        }
                                                        className="p-1 text-muted-foreground hover:text-destructive"
                                                    >
                                                        <Trash2 className="size-3.5" />
                                                    </button>
                                                </div>
                                            </div>
                                            <p className="mt-2 text-xs text-muted-foreground">
                                                {s.direccion}
                                            </p>
                                            {s.contacto && (
                                                <p className="mt-1 text-[11px] text-muted-foreground">
                                                    Contacto:{' '}
                                                    <span className="text-foreground">
                                                        {s.contacto}
                                                    </span>
                                                    {s.telefono
                                                        ? ` (${s.telefono})`
                                                        : ''}
                                                </p>
                                            )}
                                        </Card>
                                    ))
                                )}
                            </div>
                        </Card>

                        {/* Vehículos del cliente */}
                        <Card className="rounded-2xl border-border bg-card p-6 shadow-none">
                            <div className="flex items-center justify-between border-b border-border pb-4">
                                <div>
                                    <h3 className="text-base font-bold text-foreground">
                                        Vehículos registrados ({vehicles.length}
                                        )
                                    </h3>
                                    <p className="text-xs text-muted-foreground">
                                        Unidades móviles para extintores
                                        rodantes o vehiculares
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    size="sm"
                                    onClick={openCreateVehicle}
                                    className="h-8 rounded-xl bg-primary text-xs font-bold"
                                >
                                    <Plus className="mr-1 size-3.5" />
                                    Agregar vehículo
                                </Button>
                            </div>

                            <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                {vehicles.length === 0 ? (
                                    <p className="col-span-full py-6 text-center text-xs text-muted-foreground">
                                        No hay vehículos registrados para este
                                        cliente.
                                    </p>
                                ) : (
                                    vehicles.map((v) => (
                                        <Card
                                            key={v.id}
                                            className="rounded-xl border border-border p-4 shadow-none"
                                        >
                                            <div className="flex items-start justify-between">
                                                <div className="flex items-center gap-2">
                                                    <Car className="size-4 text-primary" />
                                                    <h4 className="font-mono font-bold text-base text-foreground">
                                                        {v.placa}
                                                    </h4>
                                                </div>
                                                <div className="flex gap-1">
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            openEditVehicle(v)
                                                        }
                                                        className="p-1 text-muted-foreground hover:text-foreground"
                                                    >
                                                        <Edit3 className="size-3.5" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            deleteVehicle(v.id)
                                                        }
                                                        className="p-1 text-muted-foreground hover:text-destructive"
                                                    >
                                                        <Trash2 className="size-3.5" />
                                                    </button>
                                                </div>
                                            </div>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {[v.marca, v.modelo]
                                                    .filter(Boolean)
                                                    .join(' ') ||
                                                    'Sin marca/modelo'}
                                            </p>
                                            {v.descripcion && (
                                                <p className="mt-0.5 text-[11px] text-muted-foreground">
                                                    {v.descripcion}
                                                </p>
                                            )}
                                        </Card>
                                    ))
                                )}
                            </div>
                        </Card>

                        {/* Historial en línea de tiempo */}
                        <Card className="rounded-2xl border-border bg-card p-6 shadow-none">
                            <div className="flex items-center gap-2 border-b border-border pb-3">
                                <History className="size-4 text-primary" />
                                <h3 className="text-base font-bold text-foreground">
                                    Historial de actividad
                                </h3>
                            </div>

                            <div className="mt-4">
                                {historial.length === 0 ? (
                                    <p className="py-6 text-center text-xs text-muted-foreground">
                                        Sin eventos de historial registrados.
                                    </p>
                                ) : (
                                    <div className="relative border-l border-border pl-4 space-y-4">
                                        {historial.map((h, idx) => (
                                            <div key={idx} className="relative">
                                                <span className="absolute -left-[21px] top-1.5 size-2.5 rounded-full bg-primary" />
                                                <div className="text-xs font-semibold text-foreground">
                                                    {h.texto}
                                                </div>
                                                <div className="text-[11px] text-muted-foreground">
                                                    {h.fecha}{' '}
                                                    {h.usuario
                                                        ? `· Por ${h.usuario}`
                                                        : ''}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </Card>
                    </div>
                )}
            </div>

            {/* ================= MODAL EDITAR CLIENTE ================= */}
            <Dialog open={isEditDialogOpen} onOpenChange={setIsEditDialogOpen}>
                <DialogContent className="max-w-lg rounded-2xl">
                    <DialogHeader>
                        <DialogTitle>Editar datos del cliente</DialogTitle>
                    </DialogHeader>
                    <form
                        onSubmit={submitEditClient}
                        className="space-y-3.5 text-xs"
                    >
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <Label>Tipo doc</Label>
                                <select
                                    value={editForm.data.tipo_documento}
                                    onChange={(e) =>
                                        editForm.setData(
                                            'tipo_documento',
                                            e.target.value as any,
                                        )
                                    }
                                    className="mt-1 h-9 w-full rounded-xl border border-border bg-card px-2.5 text-xs"
                                >
                                    <option value="ruc">RUC</option>
                                    <option value="dni">DNI</option>
                                </select>
                            </div>
                            <div>
                                <Label>Número doc</Label>
                                <Input
                                    value={editForm.data.numero_documento}
                                    onChange={(e) =>
                                        editForm.setData(
                                            'numero_documento',
                                            e.target.value,
                                        )
                                    }
                                    className="mt-1 h-9 text-xs font-mono"
                                />
                            </div>
                        </div>

                        <div>
                            <Label>Razón Social / Nombre Completo</Label>
                            <Input
                                value={editForm.data.razon_social}
                                onChange={(e) =>
                                    editForm.setData(
                                        'razon_social',
                                        e.target.value,
                                    )
                                }
                                className="mt-1 h-9 text-xs"
                            />
                        </div>

                        <div>
                            <Label>Nombre comercial (opcional)</Label>
                            <Input
                                value={editForm.data.nombre_comercial}
                                onChange={(e) =>
                                    editForm.setData(
                                        'nombre_comercial',
                                        e.target.value,
                                    )
                                }
                                className="mt-1 h-9 text-xs"
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <Label>Teléfono</Label>
                                <Input
                                    value={editForm.data.telefono}
                                    onChange={(e) =>
                                        editForm.setData(
                                            'telefono',
                                            e.target.value,
                                        )
                                    }
                                    className="mt-1 h-9 text-xs"
                                />
                            </div>
                            <div>
                                <Label>WhatsApp</Label>
                                <Input
                                    value={editForm.data.whatsapp}
                                    onChange={(e) =>
                                        editForm.setData(
                                            'whatsapp',
                                            e.target.value,
                                        )
                                    }
                                    className="mt-1 h-9 text-xs"
                                />
                            </div>
                        </div>

                        <div>
                            <Label>Correo electrónico</Label>
                            <Input
                                type="email"
                                value={editForm.data.email}
                                onChange={(e) =>
                                    editForm.setData('email', e.target.value)
                                }
                                className="mt-1 h-9 text-xs"
                            />
                        </div>

                        <div>
                            <Label>Dirección fiscal</Label>
                            <Input
                                value={editForm.data.direccion_fiscal}
                                onChange={(e) =>
                                    editForm.setData(
                                        'direccion_fiscal',
                                        e.target.value,
                                    )
                                }
                                className="mt-1 h-9 text-xs"
                            />
                        </div>

                        <div>
                            <Label>Observaciones internas</Label>
                            <Input
                                value={editForm.data.observaciones}
                                onChange={(e) =>
                                    editForm.setData(
                                        'observaciones',
                                        e.target.value,
                                    )
                                }
                                className="mt-1 h-9 text-xs"
                            />
                        </div>

                        <DialogFooter className="pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsEditDialogOpen(false)}
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={editForm.processing}
                                className="bg-primary text-white"
                            >
                                Guardar cambios
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* ================= MODAL AGREGAR / EDITAR SEDE ================= */}
            <Dialog open={siteDialogOpen} onOpenChange={setSiteDialogOpen}>
                <DialogContent className="max-w-md rounded-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            {editingSiteId ? 'Editar sede' : 'Nueva sede'}
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submitSite} className="space-y-3 text-xs">
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <Label>Tipo de sede</Label>
                                <select
                                    value={siteForm.data.tipo}
                                    onChange={(e) =>
                                        siteForm.setData(
                                            'tipo',
                                            e.target.value as any,
                                        )
                                    }
                                    className="mt-1 h-9 w-full rounded-xl border border-border bg-card px-2.5 text-xs capitalize"
                                >
                                    {siteTypes.map((t) => (
                                        <option key={t} value={t}>
                                            {t}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <Label>Nombre de la sede</Label>
                                <Input
                                    value={siteForm.data.nombre}
                                    onChange={(e) =>
                                        siteForm.setData(
                                            'nombre',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Ej: Local Miraflores"
                                    className="mt-1 h-9 text-xs"
                                    required
                                />
                            </div>
                        </div>

                        <div>
                            <Label>Dirección exacta</Label>
                            <Input
                                value={siteForm.data.direccion}
                                onChange={(e) =>
                                    siteForm.setData(
                                        'direccion',
                                        e.target.value,
                                    )
                                }
                                placeholder="Av. Principal 123..."
                                className="mt-1 h-9 text-xs"
                                required
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <Label>Contacto en sitio</Label>
                                <Input
                                    value={siteForm.data.contacto}
                                    onChange={(e) =>
                                        siteForm.setData(
                                            'contacto',
                                            e.target.value,
                                        )
                                    }
                                    className="mt-1 h-9 text-xs"
                                />
                            </div>
                            <div>
                                <Label>Teléfono contacto</Label>
                                <Input
                                    value={siteForm.data.telefono}
                                    onChange={(e) =>
                                        siteForm.setData(
                                            'telefono',
                                            e.target.value,
                                        )
                                    }
                                    className="mt-1 h-9 text-xs"
                                />
                            </div>
                        </div>

                        <DialogFooter className="pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setSiteDialogOpen(false)}
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={siteForm.processing}
                                className="bg-primary text-white"
                            >
                                Guardar sede
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* ================= MODAL AGREGAR / EDITAR VEHÍCULO ================= */}
            <Dialog
                open={vehicleDialogOpen}
                onOpenChange={setVehicleDialogOpen}
            >
                <DialogContent className="max-w-md rounded-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            {editingVehicleId
                                ? 'Editar vehículo'
                                : 'Nuevo vehículo'}
                        </DialogTitle>
                    </DialogHeader>
                    <form
                        onSubmit={submitVehicle}
                        className="space-y-3 text-xs"
                    >
                        <div>
                            <Label>Placa del vehículo</Label>
                            <Input
                                value={vehicleForm.data.placa}
                                onChange={(e) =>
                                    vehicleForm.setData(
                                        'placa',
                                        e.target.value.toUpperCase(),
                                    )
                                }
                                placeholder="Ej: ABC-123"
                                className="mt-1 h-9 text-xs font-mono uppercase"
                                required
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <Label>Marca</Label>
                                <Input
                                    value={vehicleForm.data.marca}
                                    onChange={(e) =>
                                        vehicleForm.setData(
                                            'marca',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Ej: Toyota"
                                    className="mt-1 h-9 text-xs"
                                />
                            </div>
                            <div>
                                <Label>Modelo</Label>
                                <Input
                                    value={vehicleForm.data.modelo}
                                    onChange={(e) =>
                                        vehicleForm.setData(
                                            'modelo',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Ej: Hilux"
                                    className="mt-1 h-9 text-xs"
                                />
                            </div>
                        </div>

                        <div>
                            <Label>Descripción / Observación</Label>
                            <Input
                                value={vehicleForm.data.descripcion}
                                onChange={(e) =>
                                    vehicleForm.setData(
                                        'descripcion',
                                        e.target.value,
                                    )
                                }
                                placeholder="Camioneta de supervisión, extintor en tolva..."
                                className="mt-1 h-9 text-xs"
                            />
                        </div>

                        <DialogFooter className="pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setVehicleDialogOpen(false)}
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={vehicleForm.processing}
                                className="bg-primary text-white"
                            >
                                Guardar vehículo
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </VendedorLayout>
    );
}
