import { router, useForm } from '@inertiajs/react';
import {
    Building2,
    Car,
    Edit3,
    Mail,
    MapPin,
    Phone,
    Plus,
    Trash2,
    UserRound,
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

type CurrentTeam = {
    slug: string;
};

type Client = {
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

type ClientSite = {
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

type Vehicle = {
    id: number;
    placa: string;
    marca: string | null;
    modelo: string | null;
    descripcion: string | null;
    estado: 'activo' | 'inactivo';
};

type Props = {
    currentTeam: CurrentTeam;
    client: Client;
    sites: ClientSite[];
    vehicles: Vehicle[];
    cotizaciones: unknown[];
    ventas: unknown[];
    servicios: unknown[];
    certificados: unknown[];
    comprobantes: unknown[];
    cobranzas: unknown[];
    historial: unknown[];
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

type SiteType =
    | 'oficina'
    | 'tienda'
    | 'planta'
    | 'almacen'
    | 'local'
    | 'sucursal'
    | 'otra';

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

type TabKey =
    | 'resumen'
    | 'sedes'
    | 'vehiculos'
    | 'cotizaciones'
    | 'ventas'
    | 'servicios'
    | 'certificados'
    | 'comprobantes'
    | 'cobranzas'
    | 'historial';

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

function clientToForm(client: Client): ClientFormData {
    return {
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
    };
}

function siteToForm(site: ClientSite): SiteFormData {
    return {
        tipo: site.tipo,
        nombre: site.nombre,
        direccion: site.direccion,
        ubigeo: site.ubigeo ?? '',
        referencia: site.referencia ?? '',
        contacto: site.contacto ?? '',
        telefono: site.telefono ?? '',
        email: site.email ?? '',
        estado: site.estado,
    };
}

function vehicleToForm(vehicle: Vehicle): VehicleFormData {
    return {
        placa: vehicle.placa,
        marca: vehicle.marca ?? '',
        modelo: vehicle.modelo ?? '',
        descripcion: vehicle.descripcion ?? '',
        estado: vehicle.estado,
    };
}

function initials(value: string) {
    return value
        .split(/\s+/u)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

function isSunatHealthy(client: Client) {
    return (
        client.estado_contribuyente === 'ACTIVO' &&
        client.condicion_domicilio === 'HABIDO'
    );
}

function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return (
        <p className="mt-1 text-[11px] font-semibold text-destructive">
            {message}
        </p>
    );
}

function StatusBadge({ active }: { active: boolean }) {
    return (
        <Badge
            className={[
                'rounded-full border-transparent px-2.5 py-1 text-[10.5px] font-bold',
                active
                    ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20'
                    : 'bg-destructive/10 text-destructive border border-destructive/20',
            ].join(' ')}
        >
            {active ? 'Activo' : 'Inactivo'}
        </Badge>
    );
}

function ClientFormFields({
    data,
    errors,
    setData,
}: {
    data: ClientFormData;
    errors: Partial<Record<keyof ClientFormData, string>>;
    setData: <K extends keyof ClientFormData>(
        key: K,
        value: ClientFormData[K],
    ) => void;
}) {
    return (
        <div className="grid gap-3 sm:grid-cols-2">
            <div>
                <Label className="text-[11px] font-bold text-foreground/80 uppercase">
                    Tipo doc.
                </Label>
                <select
                    value={data.tipo_documento}
                    onChange={(event) =>
                        setData(
                            'tipo_documento',
                            event.target.value as 'ruc' | 'dni',
                        )
                    }
                    className="mt-1 h-9 w-full rounded-[8px] border border-border bg-card px-3 text-[13px] outline-none"
                >
                    <option value="ruc">RUC</option>
                    <option value="dni">DNI</option>
                </select>
                <FieldError message={errors.tipo_documento} />
            </div>

            <div>
                <Label className="text-[11px] font-bold text-foreground/80 uppercase">
                    Numero
                </Label>
                <Input
                    value={data.numero_documento}
                    onChange={(event) =>
                        setData('numero_documento', event.target.value)
                    }
                    className="mt-1 h-9 rounded-[8px] border-border bg-card text-[13px]"
                />
                <FieldError message={errors.numero_documento} />
            </div>

            <div className="sm:col-span-2">
                <Label className="text-[11px] font-bold text-foreground/80 uppercase">
                    Razon social
                </Label>
                <Input
                    value={data.razon_social}
                    onChange={(event) =>
                        setData('razon_social', event.target.value)
                    }
                    className="mt-1 h-9 rounded-[8px] border-border bg-card text-[13px]"
                />
                <FieldError message={errors.razon_social} />
            </div>

            <div>
                <Label className="text-[11px] font-bold text-foreground/80 uppercase">
                    Nombre comercial
                </Label>
                <Input
                    value={data.nombre_comercial}
                    onChange={(event) =>
                        setData('nombre_comercial', event.target.value)
                    }
                    className="mt-1 h-9 rounded-[8px] border-border bg-card text-[13px]"
                />
                <FieldError message={errors.nombre_comercial} />
            </div>

            <div>
                <Label className="text-[11px] font-bold text-foreground/80 uppercase">
                    Email
                </Label>
                <Input
                    type="email"
                    value={data.email}
                    onChange={(event) => setData('email', event.target.value)}
                    className="mt-1 h-9 rounded-[8px] border-border bg-card text-[13px]"
                />
                <FieldError message={errors.email} />
            </div>

            <div>
                <Label className="text-[11px] font-bold text-foreground/80 uppercase">
                    Telefono
                </Label>
                <Input
                    value={data.telefono}
                    onChange={(event) =>
                        setData('telefono', event.target.value)
                    }
                    className="mt-1 h-9 rounded-[8px] border-border bg-card text-[13px]"
                />
                <FieldError message={errors.telefono} />
            </div>

            <div>
                <Label className="text-[11px] font-bold text-foreground/80 uppercase">
                    WhatsApp
                </Label>
                <Input
                    value={data.whatsapp}
                    onChange={(event) =>
                        setData('whatsapp', event.target.value)
                    }
                    className="mt-1 h-9 rounded-[8px] border-border bg-card text-[13px]"
                />
                <FieldError message={errors.whatsapp} />
            </div>

            <div className="sm:col-span-2">
                <Label className="text-[11px] font-bold text-foreground/80 uppercase">
                    Direccion fiscal
                </Label>
                <Input
                    value={data.direccion_fiscal}
                    onChange={(event) =>
                        setData('direccion_fiscal', event.target.value)
                    }
                    className="mt-1 h-9 rounded-[8px] border-border bg-card text-[13px]"
                />
                <FieldError message={errors.direccion_fiscal} />
            </div>

            <div>
                <Label className="text-[11px] font-bold text-foreground/80 uppercase">
                    Estado SUNAT
                </Label>
                <Input
                    value={data.estado_contribuyente}
                    onChange={(event) =>
                        setData('estado_contribuyente', event.target.value)
                    }
                    className="mt-1 h-9 rounded-[8px] border-border bg-card text-[13px]"
                />
                <FieldError message={errors.estado_contribuyente} />
            </div>

            <div>
                <Label className="text-[11px] font-bold text-foreground/80 uppercase">
                    Condicion
                </Label>
                <Input
                    value={data.condicion_domicilio}
                    onChange={(event) =>
                        setData('condicion_domicilio', event.target.value)
                    }
                    className="mt-1 h-9 rounded-[8px] border-border bg-card text-[13px]"
                />
                <FieldError message={errors.condicion_domicilio} />
            </div>
        </div>
    );
}

function SiteFormFields({
    data,
    errors,
    setData,
}: {
    data: SiteFormData;
    errors: Partial<Record<keyof SiteFormData, string>>;
    setData: <K extends keyof SiteFormData>(
        key: K,
        value: SiteFormData[K],
    ) => void;
}) {
    return (
        <div className="grid gap-3 sm:grid-cols-2">
            <div>
                <Label className="text-[11px] font-bold text-foreground/80 uppercase">
                    Tipo
                </Label>
                <select
                    value={data.tipo}
                    onChange={(event) =>
                        setData('tipo', event.target.value as SiteType)
                    }
                    className="mt-1 h-9 w-full rounded-[8px] border border-border bg-card px-3 text-[13px] outline-none"
                >
                    {siteTypes.map((type) => (
                        <option key={type} value={type}>
                            {type}
                        </option>
                    ))}
                </select>
                <FieldError message={errors.tipo} />
            </div>

            <div>
                <Label className="text-[11px] font-bold text-foreground/80 uppercase">
                    Estado
                </Label>
                <select
                    value={data.estado}
                    onChange={(event) =>
                        setData(
                            'estado',
                            event.target.value as 'activo' | 'inactivo',
                        )
                    }
                    className="mt-1 h-9 w-full rounded-[8px] border border-border bg-card px-3 text-[13px] outline-none"
                >
                    <option value="activo">Activo</option>
                    <option value="inactivo">Inactivo</option>
                </select>
                <FieldError message={errors.estado} />
            </div>

            <div className="sm:col-span-2">
                <Label className="text-[11px] font-bold text-foreground/80 uppercase">
                    Nombre
                </Label>
                <Input
                    value={data.nombre}
                    onChange={(event) => setData('nombre', event.target.value)}
                    className="mt-1 h-9 rounded-[8px] border-border bg-card text-[13px]"
                />
                <FieldError message={errors.nombre} />
            </div>

            <div className="sm:col-span-2">
                <Label className="text-[11px] font-bold text-foreground/80 uppercase">
                    Direccion
                </Label>
                <Input
                    value={data.direccion}
                    onChange={(event) =>
                        setData('direccion', event.target.value)
                    }
                    className="mt-1 h-9 rounded-[8px] border-border bg-card text-[13px]"
                />
                <FieldError message={errors.direccion} />
            </div>

            <div>
                <Label className="text-[11px] font-bold text-foreground/80 uppercase">
                    Contacto
                </Label>
                <Input
                    value={data.contacto}
                    onChange={(event) =>
                        setData('contacto', event.target.value)
                    }
                    className="mt-1 h-9 rounded-[8px] border-border bg-card text-[13px]"
                />
                <FieldError message={errors.contacto} />
            </div>

            <div>
                <Label className="text-[11px] font-bold text-foreground/80 uppercase">
                    Telefono
                </Label>
                <Input
                    value={data.telefono}
                    onChange={(event) =>
                        setData('telefono', event.target.value)
                    }
                    className="mt-1 h-9 rounded-[8px] border-border bg-card text-[13px]"
                />
                <FieldError message={errors.telefono} />
            </div>

            <div>
                <Label className="text-[11px] font-bold text-foreground/80 uppercase">
                    Email
                </Label>
                <Input
                    type="email"
                    value={data.email}
                    onChange={(event) => setData('email', event.target.value)}
                    className="mt-1 h-9 rounded-[8px] border-border bg-card text-[13px]"
                />
                <FieldError message={errors.email} />
            </div>

            <div>
                <Label className="text-[11px] font-bold text-foreground/80 uppercase">
                    Ubigeo
                </Label>
                <Input
                    value={data.ubigeo}
                    onChange={(event) => setData('ubigeo', event.target.value)}
                    className="mt-1 h-9 rounded-[8px] border-border bg-card text-[13px]"
                />
                <FieldError message={errors.ubigeo} />
            </div>

            <div className="sm:col-span-2">
                <Label className="text-[11px] font-bold text-foreground/80 uppercase">
                    Referencia
                </Label>
                <Input
                    value={data.referencia}
                    onChange={(event) =>
                        setData('referencia', event.target.value)
                    }
                    className="mt-1 h-9 rounded-[8px] border-border bg-card text-[13px]"
                />
                <FieldError message={errors.referencia} />
            </div>
        </div>
    );
}

function VehicleFormFields({
    data,
    errors,
    setData,
}: {
    data: VehicleFormData;
    errors: Partial<Record<keyof VehicleFormData, string>>;
    setData: <K extends keyof VehicleFormData>(
        key: K,
        value: VehicleFormData[K],
    ) => void;
}) {
    return (
        <div className="grid gap-3 sm:grid-cols-2">
            <div>
                <Label className="text-[11px] font-bold text-foreground/80 uppercase">
                    Placa
                </Label>
                <Input
                    value={data.placa}
                    onChange={(event) => setData('placa', event.target.value)}
                    className="mt-1 h-9 rounded-[8px] border-border bg-card text-[13px]"
                />
                <FieldError message={errors.placa} />
            </div>

            <div>
                <Label className="text-[11px] font-bold text-foreground/80 uppercase">
                    Estado
                </Label>
                <select
                    value={data.estado}
                    onChange={(event) =>
                        setData(
                            'estado',
                            event.target.value as 'activo' | 'inactivo',
                        )
                    }
                    className="mt-1 h-9 w-full rounded-[8px] border border-border bg-card px-3 text-[13px] outline-none"
                >
                    <option value="activo">Activo</option>
                    <option value="inactivo">Inactivo</option>
                </select>
                <FieldError message={errors.estado} />
            </div>

            <div>
                <Label className="text-[11px] font-bold text-foreground/80 uppercase">
                    Marca
                </Label>
                <Input
                    value={data.marca}
                    onChange={(event) => setData('marca', event.target.value)}
                    className="mt-1 h-9 rounded-[8px] border-border bg-card text-[13px]"
                />
                <FieldError message={errors.marca} />
            </div>

            <div>
                <Label className="text-[11px] font-bold text-foreground/80 uppercase">
                    Modelo
                </Label>
                <Input
                    value={data.modelo}
                    onChange={(event) => setData('modelo', event.target.value)}
                    className="mt-1 h-9 rounded-[8px] border-border bg-card text-[13px]"
                />
                <FieldError message={errors.modelo} />
            </div>

            <div className="sm:col-span-2">
                <Label className="text-[11px] font-bold text-foreground/80 uppercase">
                    Descripcion
                </Label>
                <Input
                    value={data.descripcion}
                    onChange={(event) =>
                        setData('descripcion', event.target.value)
                    }
                    className="mt-1 h-9 rounded-[8px] border-border bg-card text-[13px]"
                />
                <FieldError message={errors.descripcion} />
            </div>
        </div>
    );
}

function SoonCard({ title }: { title: string }) {
    return (
        <Card className="items-center rounded-[16px] border-border bg-card px-5 py-12 text-center shadow-none">
            <div className="font-['Oswald',sans-serif] text-[20px] font-semibold text-foreground uppercase">
                {title}
            </div>
            <div className="mt-1 text-[13px] text-muted-foreground">Proximamente</div>
        </Card>
    );
}

export default function ClientesShow({
    currentTeam,
    client,
    sites,
    vehicles,
}: Props) {
    const [activeTab, setActiveTab] = useState<TabKey>('resumen');
    const [editClientOpen, setEditClientOpen] = useState(false);
    const [siteDialogOpen, setSiteDialogOpen] = useState(false);
    const [vehicleDialogOpen, setVehicleDialogOpen] = useState(false);
    const [editingSite, setEditingSite] = useState<ClientSite | null>(null);
    const [editingVehicle, setEditingVehicle] = useState<Vehicle | null>(null);

    const clientForm = useForm<ClientFormData>(clientToForm(client));
    const siteForm = useForm<SiteFormData>(emptySiteForm);
    const vehicleForm = useForm<VehicleFormData>(emptyVehicleForm);
    const healthy = isSunatHealthy(client);

    const routeArgs = { current_team: currentTeam.slug, client: client.id };

    const tabs: { key: TabKey; label: string }[] = [
        { key: 'resumen', label: 'Resumen' },
        { key: 'sedes', label: 'Sedes' },
        { key: 'vehiculos', label: 'Vehiculos' },
        { key: 'cotizaciones', label: 'Cotizaciones' },
        { key: 'ventas', label: 'Ventas' },
        { key: 'servicios', label: 'Servicios' },
        { key: 'certificados', label: 'Certificados' },
        { key: 'comprobantes', label: 'Comprobantes' },
        { key: 'cobranzas', label: 'Cobranzas' },
        { key: 'historial', label: 'Historial' },
    ];

    const submitClient = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        clientForm.put(clientes.update.url(routeArgs), {
            preserveScroll: true,
            onSuccess: () => setEditClientOpen(false),
        });
    };

    const openCreateSite = () => {
        setEditingSite(null);
        siteForm.reset();
        siteForm.clearErrors();
        setSiteDialogOpen(true);
    };

    const openEditSite = (site: ClientSite) => {
        setEditingSite(site);
        siteForm.setData(siteToForm(site));
        siteForm.clearErrors();
        setSiteDialogOpen(true);
    };

    const submitSite = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setSiteDialogOpen(false);
                setEditingSite(null);
                siteForm.reset();
            },
        };

        if (editingSite) {
            siteForm.put(
                sitesRoutes.update.url({ ...routeArgs, site: editingSite.id }),
                options,
            );
            return;
        }

        siteForm.post(sitesRoutes.store.url(routeArgs), options);
    };

    const deleteSite = (site: ClientSite) => {
        router.delete(
            sitesRoutes.destroy.url({ ...routeArgs, site: site.id }),
            {
                preserveScroll: true,
            },
        );
    };

    const openCreateVehicle = () => {
        setEditingVehicle(null);
        vehicleForm.reset();
        vehicleForm.clearErrors();
        setVehicleDialogOpen(true);
    };

    const openEditVehicle = (vehicle: Vehicle) => {
        setEditingVehicle(vehicle);
        vehicleForm.setData(vehicleToForm(vehicle));
        vehicleForm.clearErrors();
        setVehicleDialogOpen(true);
    };

    const submitVehicle = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setVehicleDialogOpen(false);
                setEditingVehicle(null);
                vehicleForm.reset();
            },
        };

        if (editingVehicle) {
            vehicleForm.put(
                vehiclesRoutes.update.url({
                    ...routeArgs,
                    vehicle: editingVehicle.id,
                }),
                options,
            );
            return;
        }

        vehicleForm.post(vehiclesRoutes.store.url(routeArgs), options);
    };

    const deleteVehicle = (vehicle: Vehicle) => {
        router.delete(
            vehiclesRoutes.destroy.url({ ...routeArgs, vehicle: vehicle.id }),
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <VendedorLayout title="Clientes">
            <div className="flex flex-col gap-4">
                <Card className="rounded-[16px] border-border bg-card p-5 shadow-none">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div className="flex min-w-0 items-start gap-4">
                            <div className="flex size-14 shrink-0 items-center justify-center rounded-[14px] bg-card text-[16px] font-bold text-white">
                                {initials(client.razon_social)}
                            </div>
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <h1 className="font-['Oswald',sans-serif] text-[28px] leading-tight font-semibold text-foreground uppercase">
                                        {client.razon_social}
                                    </h1>
                                    <StatusBadge active={client.activo} />
                                    <Badge
                                        className={[
                                            'rounded-full border-transparent px-2.5 py-1 text-[10.5px] font-bold',
                                            healthy
                                                ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20'
                                                : 'bg-destructive/10 text-destructive border border-destructive/20',
                                        ].join(' ')}
                                    >
                                        {client.estado_contribuyente ?? '-'} ·{' '}
                                        {client.condicion_domicilio ?? '-'}
                                    </Badge>
                                </div>
                                <div className="mt-1 font-['IBM_Plex_Mono',monospace] text-[12px] text-muted-foreground">
                                    {client.codigo_interno} ·{' '}
                                    {client.tipo_documento.toUpperCase()}{' '}
                                    {client.numero_documento}
                                </div>
                                <div className="mt-3 grid gap-2 text-[12.5px] text-foreground/80 sm:grid-cols-3">
                                    <span className="flex items-center gap-1.5">
                                        <Phone className="size-3.5 text-muted-foreground" />
                                        {client.telefono ??
                                            client.whatsapp ??
                                            '-'}
                                    </span>
                                    <span className="flex items-center gap-1.5">
                                        <Mail className="size-3.5 text-muted-foreground" />
                                        {client.email ?? '-'}
                                    </span>
                                    <span className="flex items-center gap-1.5">
                                        <MapPin className="size-3.5 text-muted-foreground" />
                                        {client.distrito ??
                                            client.provincia ??
                                            '-'}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <Button
                            type="button"
                            onClick={() => setEditClientOpen(true)}
                            className="h-10 rounded-[9px] bg-primary px-4 text-[13px] font-bold text-white shadow-none hover:bg-primary/90"
                        >
                            <Edit3 className="size-3.5" />
                            Editar
                        </Button>
                    </div>
                </Card>

                <div className="flex gap-1.5 overflow-x-auto rounded-[12px] border border-border bg-card p-1.5">
                    {tabs.map((tab) => (
                        <button
                            key={tab.key}
                            type="button"
                            onClick={() => setActiveTab(tab.key)}
                            className={[
                                'h-9 shrink-0 rounded-[9px] px-3 text-[12.5px] font-bold transition-colors',
                                activeTab === tab.key
                                    ? 'bg-foreground text-background'
                                    : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                            ].join(' ')}
                        >
                            {tab.label}
                        </button>
                    ))}
                </div>

                {activeTab === 'resumen' && (
                    <div className="grid gap-4 lg:grid-cols-3">
                        <Card className="rounded-[16px] border-border bg-card p-5 shadow-none lg:col-span-2">
                            <h2 className="font-['Oswald',sans-serif] text-[18px] font-semibold text-foreground uppercase">
                                Datos comerciales
                            </h2>
                            <div className="mt-4 grid gap-3 text-[13px] sm:grid-cols-2">
                                {[
                                    [
                                        'Nombre comercial',
                                        client.nombre_comercial ?? '-',
                                    ],
                                    [
                                        'Direccion fiscal',
                                        client.direccion_fiscal ?? '-',
                                    ],
                                    [
                                        'Departamento',
                                        client.departamento ?? '-',
                                    ],
                                    ['Provincia', client.provincia ?? '-'],
                                    ['Distrito', client.distrito ?? '-'],
                                    ['Ubigeo', client.ubigeo ?? '-'],
                                ].map(([label, value]) => (
                                    <div
                                        key={label}
                                        className="rounded-[10px] border border-border bg-muted/40 p-3"
                                    >
                                        <div className="font-['IBM_Plex_Mono',monospace] text-[9.5px] font-bold tracking-[0.05em] text-muted-foreground uppercase">
                                            {label}
                                        </div>
                                        <div className="mt-1 font-semibold text-foreground">
                                            {value}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </Card>

                        <Card className="rounded-[16px] border-border bg-card p-5 shadow-none">
                            <h2 className="font-['Oswald',sans-serif] text-[18px] font-semibold text-foreground uppercase">
                                Resumen
                            </h2>
                            <div className="mt-4 space-y-3">
                                <div className="flex items-center justify-between rounded-[10px] bg-muted/40 p-3">
                                    <span className="text-[12px] font-bold text-muted-foreground uppercase">
                                        Sedes
                                    </span>
                                    <span className="font-['Oswald',sans-serif] text-[20px] font-semibold">
                                        {sites.length}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between rounded-[10px] bg-muted/40 p-3">
                                    <span className="text-[12px] font-bold text-muted-foreground uppercase">
                                        Vehiculos
                                    </span>
                                    <span className="font-['Oswald',sans-serif] text-[20px] font-semibold">
                                        {vehicles.length}
                                    </span>
                                </div>
                            </div>
                        </Card>
                    </div>
                )}

                {activeTab === 'sedes' && (
                    <Card className="gap-0 rounded-[16px] border-border bg-card p-5 shadow-none">
                        <div className="mb-4 flex items-center justify-between gap-3">
                            <h2 className="font-['Oswald',sans-serif] text-[18px] font-semibold text-foreground uppercase">
                                Sedes
                            </h2>
                            <Button
                                onClick={openCreateSite}
                                className="h-10 rounded-[9px] bg-primary text-[13px] font-bold text-white shadow-none hover:bg-primary/90"
                            >
                                <Plus className="size-3.5" />
                                Agregar sede
                            </Button>
                        </div>
                        <div className="grid gap-3">
                            {sites.map((site) => (
                                <div
                                    key={site.id}
                                    className="flex flex-col gap-3 rounded-[12px] border border-border p-4 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div className="flex gap-3">
                                        <div className="flex size-10 shrink-0 items-center justify-center rounded-[10px] bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20">
                                            <Building2 className="size-5" />
                                        </div>
                                        <div>
                                            <div className="font-bold text-foreground">
                                                {site.nombre}
                                            </div>
                                            <div className="text-[12.5px] text-foreground/80">
                                                {site.direccion}
                                            </div>
                                            <div className="mt-1 text-[11px] text-muted-foreground">
                                                {site.tipo} ·{' '}
                                                {site.contacto ??
                                                    'Sin contacto'}
                                            </div>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <StatusBadge
                                            active={site.estado === 'activo'}
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            onClick={() => openEditSite(site)}
                                            className="size-8 rounded-[8px] border-border bg-card text-foreground/80 shadow-none"
                                        >
                                            <Edit3 className="size-3.5" />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            onClick={() => deleteSite(site)}
                                            className="size-8 rounded-[8px] border-border bg-card text-destructive shadow-none"
                                        >
                                            <Trash2 className="size-3.5" />
                                        </Button>
                                    </div>
                                </div>
                            ))}
                            {sites.length === 0 && <SoonCard title="Sedes" />}
                        </div>
                    </Card>
                )}

                {activeTab === 'vehiculos' && (
                    <Card className="gap-0 rounded-[16px] border-border bg-card p-5 shadow-none">
                        <div className="mb-4 flex items-center justify-between gap-3">
                            <h2 className="font-['Oswald',sans-serif] text-[18px] font-semibold text-foreground uppercase">
                                Vehiculos
                            </h2>
                            <Button
                                onClick={openCreateVehicle}
                                className="h-10 rounded-[9px] bg-primary text-[13px] font-bold text-white shadow-none hover:bg-primary/90"
                            >
                                <Plus className="size-3.5" />
                                Agregar vehiculo
                            </Button>
                        </div>
                        <div className="grid gap-3">
                            {vehicles.map((vehicle) => (
                                <div
                                    key={vehicle.id}
                                    className="flex flex-col gap-3 rounded-[12px] border border-border p-4 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div className="flex gap-3">
                                        <div className="flex size-10 shrink-0 items-center justify-center rounded-[10px] bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                            <Car className="size-5" />
                                        </div>
                                        <div>
                                            <div className="font-bold text-foreground">
                                                {vehicle.placa}
                                            </div>
                                            <div className="text-[12.5px] text-foreground/80">
                                                {[vehicle.marca, vehicle.modelo]
                                                    .filter(Boolean)
                                                    .join(' ') || '-'}
                                            </div>
                                            <div className="mt-1 text-[11px] text-muted-foreground">
                                                {vehicle.descripcion ??
                                                    'Sin descripcion'}
                                            </div>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <StatusBadge
                                            active={vehicle.estado === 'activo'}
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            onClick={() =>
                                                openEditVehicle(vehicle)
                                            }
                                            className="size-8 rounded-[8px] border-border bg-card text-foreground/80 shadow-none"
                                        >
                                            <Edit3 className="size-3.5" />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            onClick={() =>
                                                deleteVehicle(vehicle)
                                            }
                                            className="size-8 rounded-[8px] border-border bg-card text-destructive shadow-none"
                                        >
                                            <Trash2 className="size-3.5" />
                                        </Button>
                                    </div>
                                </div>
                            ))}
                            {vehicles.length === 0 && (
                                <SoonCard title="Vehiculos" />
                            )}
                        </div>
                    </Card>
                )}

                {activeTab !== 'resumen' &&
                    activeTab !== 'sedes' &&
                    activeTab !== 'vehiculos' && (
                        <SoonCard
                            title={
                                tabs.find((tab) => tab.key === activeTab)
                                    ?.label ?? 'Modulo'
                            }
                        />
                    )}
            </div>

            <Dialog open={editClientOpen} onOpenChange={setEditClientOpen}>
                <DialogContent className="max-h-[88vh] overflow-y-auto rounded-[16px] border-border bg-card sm:max-w-2xl">
                    <DialogHeader>
                        <div className="flex items-center gap-3">
                            <div className="flex size-10 items-center justify-center rounded-[11px] bg-destructive/10 text-primary">
                                <UserRound className="size-5" />
                            </div>
                            <DialogTitle className="font-['Oswald',sans-serif] text-[20px] font-semibold text-foreground uppercase">
                                Editar cliente
                            </DialogTitle>
                        </div>
                    </DialogHeader>
                    <form onSubmit={submitClient} className="space-y-4">
                        <ClientFormFields
                            data={clientForm.data}
                            errors={clientForm.errors}
                            setData={clientForm.setData}
                        />
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setEditClientOpen(false)}
                                className="rounded-[9px] border-border bg-card text-foreground/80 shadow-none"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={clientForm.processing}
                                className="rounded-[9px] bg-primary font-bold text-white shadow-none hover:bg-primary/90"
                            >
                                Guardar cambios
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={siteDialogOpen} onOpenChange={setSiteDialogOpen}>
                <DialogContent className="max-h-[88vh] overflow-y-auto rounded-[16px] border-border bg-card sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle className="font-['Oswald',sans-serif] text-[20px] font-semibold text-foreground uppercase">
                            {editingSite ? 'Editar sede' : 'Agregar sede'}
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submitSite} className="space-y-4">
                        <SiteFormFields
                            data={siteForm.data}
                            errors={siteForm.errors}
                            setData={siteForm.setData}
                        />
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setSiteDialogOpen(false)}
                                className="rounded-[9px] border-border bg-card text-foreground/80 shadow-none"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={siteForm.processing}
                                className="rounded-[9px] bg-primary font-bold text-white shadow-none hover:bg-primary/90"
                            >
                                Guardar sede
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={vehicleDialogOpen}
                onOpenChange={setVehicleDialogOpen}
            >
                <DialogContent className="max-h-[88vh] overflow-y-auto rounded-[16px] border-border bg-card sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle className="font-['Oswald',sans-serif] text-[20px] font-semibold text-foreground uppercase">
                            {editingVehicle
                                ? 'Editar vehiculo'
                                : 'Agregar vehiculo'}
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submitVehicle} className="space-y-4">
                        <VehicleFormFields
                            data={vehicleForm.data}
                            errors={vehicleForm.errors}
                            setData={vehicleForm.setData}
                        />
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setVehicleDialogOpen(false)}
                                className="rounded-[9px] border-border bg-card text-foreground/80 shadow-none"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={vehicleForm.processing}
                                className="rounded-[9px] bg-primary font-bold text-white shadow-none hover:bg-primary/90"
                            >
                                Guardar vehiculo
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </VendedorLayout>
    );
}
