import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Eye, Plus, RotateCcw, Wrench } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { toast } from 'sonner';

import ServiceOrderController from '@/actions/App/Http/Controllers/Vendedor/ServiceOrderController';
import CatalogPicker from '@/components/catalog-picker';
import ClientPicker, { type ClientFicha } from '@/components/client-picker';
import ReferenciaField from '@/components/referencia-field';
import { PageHeader } from '@/components/page-header';
import { ServiciosTabs } from '@/components/servicios-tabs';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import VendedorLayout from '@/layouts/vendedor-layout';
import type { Team } from '@/types';
import { fechaCorta, fechaLocal } from '@/lib/utils';
import OrdenesServicioRoutes from '@/routes/vendedor/ordenes-servicio';

export type ServiceOrderItem = {
    id: number;
    codigo: string;
    cliente: string;
    tecnico?: string | null;
    tipo_servicio: string;
    fecha: string;
    estado: string;
    coarse_label: string;
    departamento_tecnico?: string | null;
    prioridad: string;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type TecnicoOption = {
    id: number;
    name: string;
    departamento: 'planta' | 'campo';
};

export type Props = {
    orders: {
        data: ServiceOrderItem[];
        links: PaginationLink[];
        from?: number | null;
        to?: number | null;
        total: number;
    };
    filters: {
        estado?: string;
    };
    tecnicos: TecnicoOption[];
    // Catálogo de servicios activos: atajos para elegir el tipo de servicio.
    services?: ServicioOption[];
};

type ServicioOption = {
    id: number;
    nombre: string;
    precio_venta?: number | string | null;
};

const TABS = [
    { id: '', label: 'Todas' },
    { id: 'en_camino', label: 'En camino' },
    { id: 'en_proceso', label: 'En proceso' },
    { id: 'completadas', label: 'Completadas' },
    { id: 'anuladas', label: 'Anuladas' },
] as const;

function getCoarseStep(coarseLabel: string): number {
    switch (coarseLabel?.toLowerCase()) {
        case 'asignada':
            return 1;
        case 'en_proceso':
            return 2;
        case 'completada':
            return 3;
        case 'cerrada':
            return 4;
        default:
            return 1;
    }
}

function getBadgeConfig(
    estado: string,
    coarseLabel: string,
    tecnico?: string | null,
): { label: string; badgeClass: string } {
    switch (coarseLabel?.toLowerCase()) {
        case 'asignada':
            if (!tecnico || tecnico === 'Por asignar') {
                return {
                    label: 'Por asignar',
                    badgeClass:
                        'bg-amber-500/10 text-warning-strong border border-amber-500/20',
                };
            }
            return {
                label: 'Asignada',
                badgeClass:
                    'bg-amber-500/10 text-warning-strong border border-amber-500/20',
            };
        case 'en_proceso':
            return {
                label: 'En proceso',
                badgeClass:
                    'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20',
            };
        case 'completada':
            return {
                label: 'Completada',
                badgeClass:
                    'bg-emerald-500/10 text-success-strong border border-emerald-500/20',
            };
        case 'cerrada':
            return {
                label: 'Cerrada',
                badgeClass: 'bg-muted text-muted-foreground',
            };
        default:
            return {
                label: estado || 'Pendiente',
                badgeClass: 'bg-muted text-muted-foreground',
            };
    }
}

export default function ServiceOrdersIndex({
    orders,
    filters,
    tecnicos,
    services = [],
}: Props) {
    const { currentTeam, sidebarCounts } = usePage<{
        currentTeam?: Team | null;
        sidebarCounts?: { deficiencias?: number } | null;
    }>().props;
    const teamSlug =
        currentTeam?.slug ||
        (typeof window !== 'undefined'
            ? window.location.pathname.split('/')[1]
            : '');

    const [dialogOpen, setDialogOpen] = useState(false);
    const [cliente, setCliente] = useState<ClientFicha | null>(null);
    const [destino, setDestino] = useState<'local_cliente' | 'vehiculo'>(
        'local_cliente',
    );
    const currentEstado = filters?.estado || '';

    // Form para crear nueva orden
    const createForm = useForm({
        client_id: '',
        service_id: '',
        tipo_servicio: '',
        fecha: fechaLocal(),
        departamento_tecnico: 'planta' as 'planta' | 'campo',
        tecnico_id: '',
        referencia: '',
        prioridad: 'normal',
        observaciones: '',
    });

    const tecnicosDelArea = tecnicos.filter(
        (tecnico) =>
            tecnico.departamento === createForm.data.departamento_tecnico,
    );

    const submitCreate = (e: FormEvent) => {
        e.preventDefault();
        createForm.post(ServiceOrderController.store.url(teamSlug), {
            onError: (errors) =>
                toast.error(
                    Object.values(errors)[0] ??
                        'No se pudo completar la acción.',
                ),
            preserveScroll: true,
            onSuccess: () => {
                setDialogOpen(false);
                setCliente(null);
                createForm.reset();
            },
        });
    };

    const handleFilterChange = (tabId: string) => {
        router.get(
            ServiceOrderController.index.url(teamSlug, {
                query: {
                    estado: tabId || undefined,
                },
            }),
            {},
            {
                preserveScroll: true,
                preserveState: true,
            },
        );
    };

    return (
        <VendedorLayout title="Servicios">
            <Head title="Órdenes de servicio" />

            <div className="flex flex-col gap-4">
                <ServiciosTabs
                    teamSlug={teamSlug}
                    activa="ordenes"
                    deficienciasPendientes={sidebarCounts?.deficiencias}
                />

                {/* Header toolbar */}
                <PageHeader
                    title="Órdenes de servicio"
                    description="Recargas, mantenimientos e inspecciones de tus clientes, en planta o en campo."
                    actions={
                        <Button
                            type="button"
                            onClick={() => setDialogOpen(true)}
                            className="bg-primary hover:bg-primary/90 h-9 rounded-[9px] px-3.5 text-[12.5px] font-bold text-white shadow-none"
                        >
                            <Plus className="mr-1.5 size-4" />
                            <span>Nueva orden</span>
                        </Button>
                    }
                />

                {/* Filter Tabs */}
                <div className="bg-muted flex w-fit max-w-full overflow-x-auto rounded-[9px] p-[3px]">
                    {TABS.map((tab) => {
                        const active = currentEstado === tab.id;
                        return (
                            <button
                                key={tab.id}
                                type="button"
                                aria-pressed={active}
                                onClick={() => handleFilterChange(tab.id)}
                                className={`cursor-pointer rounded-[7px] px-3.5 py-1.5 text-xs font-bold transition-all ${
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

                {/* Table Card */}
                <Card className="border-border bg-card rounded-[16px] p-5 shadow-none">
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-[12.5px]">
                            <thead>
                                <tr className="border-border border-b">
                                    <th className="text-muted-foreground px-2.5 py-2.5 text-left font-mono text-[9.5px] font-semibold tracking-[0.05em] uppercase">
                                        N° Orden
                                    </th>
                                    <th className="text-muted-foreground px-2.5 py-2.5 text-left font-mono text-[9.5px] font-semibold tracking-[0.05em] uppercase">
                                        Cliente
                                    </th>
                                    <th className="text-muted-foreground px-2.5 py-2.5 text-left font-mono text-[9.5px] font-semibold tracking-[0.05em] uppercase">
                                        Servicio
                                    </th>
                                    <th className="text-muted-foreground px-2.5 py-2.5 text-left font-mono text-[9.5px] font-semibold tracking-[0.05em] uppercase">
                                        Técnico
                                    </th>
                                    <th className="text-muted-foreground px-2.5 py-2.5 text-center font-mono text-[9.5px] font-semibold tracking-[0.05em] uppercase">
                                        Avance
                                    </th>
                                    <th className="text-muted-foreground px-2.5 py-2.5 text-left font-mono text-[9.5px] font-semibold tracking-[0.05em] uppercase">
                                        Estado
                                    </th>
                                    <th className="text-muted-foreground px-2.5 py-2.5 text-right font-mono text-[9.5px] font-semibold tracking-[0.05em] uppercase">
                                        Acción
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {orders.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="text-muted-foreground px-4 py-12 text-center"
                                        >
                                            <div className="flex flex-col items-center justify-center gap-2">
                                                <div className="bg-muted/50 flex size-10 items-center justify-center rounded-full">
                                                    <Wrench className="text-muted-foreground size-5" />
                                                </div>
                                                <p className="text-foreground text-xs font-semibold">
                                                    No se encontraron órdenes de
                                                    servicio
                                                </p>
                                                <p className="text-muted-foreground max-w-sm text-[11px]">
                                                    {currentEstado
                                                        ? 'No hay registros que coincidan con el estado seleccionado.'
                                                        : 'Aún no se han emitido órdenes de servicio en esta sede.'}
                                                </p>
                                                {currentEstado ? (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            router.get(
                                                                OrdenesServicioRoutes.index.url(
                                                                    {
                                                                        current_team:
                                                                            teamSlug,
                                                                    },
                                                                ),
                                                            )
                                                        }
                                                        className="border-border bg-card text-foreground hover:bg-muted mt-2 inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold shadow-xs transition-colors"
                                                    >
                                                        <RotateCcw className="size-3" />
                                                        <span>
                                                            Ver todas las
                                                            órdenes
                                                        </span>
                                                    </button>
                                                ) : (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        onClick={() =>
                                                            setDialogOpen(true)
                                                        }
                                                        className="bg-primary hover:bg-primary/90 mt-2 h-8 gap-1.5 rounded-[8px] text-xs font-bold text-white shadow-xs"
                                                    >
                                                        <Plus className="size-3.5" />
                                                        <span>
                                                            Crear primera orden
                                                        </span>
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    orders.data.map((order) => {
                                        const step = getCoarseStep(
                                            order.coarse_label,
                                        );
                                        const badgeConfig = getBadgeConfig(
                                            order.estado,
                                            order.coarse_label,
                                            order.tecnico,
                                        );

                                        return (
                                            <tr
                                                key={order.id}
                                                className="border-border hover:bg-muted/40 border-b transition-colors"
                                            >
                                                <td className="text-foreground px-2.5 py-3.5 font-mono text-xs font-bold">
                                                    <Link
                                                        href={ServiceOrderController.show.url(
                                                            {
                                                                current_team:
                                                                    teamSlug,
                                                                service_order:
                                                                    order.id,
                                                            },
                                                        )}
                                                        className="hover:text-primary-strong hover:underline"
                                                    >
                                                        {order.codigo}
                                                    </Link>
                                                </td>

                                                <td className="text-foreground px-2.5 py-3.5 font-semibold">
                                                    {order.cliente}
                                                </td>

                                                <td className="text-foreground/80 px-2.5 py-3.5">
                                                    <span className="line-clamp-1">
                                                        {order.tipo_servicio}
                                                    </span>
                                                    <span className="text-muted-foreground font-mono text-[10px]">
                                                        {fechaCorta(
                                                            order.fecha,
                                                        )}
                                                    </span>
                                                </td>

                                                <td className="text-foreground/80 px-2.5 py-3.5 text-[12px]">
                                                    <div>
                                                        {order.tecnico ||
                                                            'Por asignar'}
                                                    </div>
                                                    {order.departamento_tecnico && (
                                                        <div className="text-muted-foreground font-mono text-[10px] capitalize">
                                                            (
                                                            {
                                                                order.departamento_tecnico
                                                            }
                                                            )
                                                        </div>
                                                    )}
                                                </td>

                                                {/* 4-point progress indicator */}
                                                <td className="px-2.5 py-3.5 text-center">
                                                    <div
                                                        className="inline-flex items-center gap-1.5"
                                                        title={`Etapa ${step} de 4: ${order.coarse_label}`}
                                                    >
                                                        {[1, 2, 3, 4].map(
                                                            (s) => {
                                                                let dotClass =
                                                                    'bg-muted/40';
                                                                if (s < step) {
                                                                    dotClass =
                                                                        'bg-emerald-600';
                                                                } else if (
                                                                    s === step
                                                                ) {
                                                                    dotClass =
                                                                        step ===
                                                                        4
                                                                            ? 'bg-emerald-600'
                                                                            : 'bg-blue-600 ring-2 ring-blue-200';
                                                                }
                                                                return (
                                                                    <span
                                                                        key={s}
                                                                        className={`size-2.5 rounded-full transition-all ${dotClass}`}
                                                                    />
                                                                );
                                                            },
                                                        )}
                                                    </div>
                                                </td>

                                                <td className="px-2.5 py-3.5">
                                                    <Badge
                                                        className={`rounded-full border-none px-2.5 py-0.5 text-[10.5px] font-bold ${badgeConfig.badgeClass}`}
                                                    >
                                                        {badgeConfig.label}
                                                    </Badge>
                                                </td>

                                                <td className="px-2.5 py-3.5 text-right">
                                                    <Button
                                                        asChild
                                                        variant="outline"
                                                        size="icon"
                                                        className="border-border bg-card text-foreground/80 hover:bg-background size-7 rounded-[7px] shadow-none"
                                                    >
                                                        <Link
                                                            href={ServiceOrderController.show.url(
                                                                {
                                                                    current_team:
                                                                        teamSlug,
                                                                    service_order:
                                                                        order.id,
                                                                },
                                                            )}
                                                            aria-label={`Ver orden ${order.codigo}`}
                                                        >
                                                            <Eye className="size-3.5" />
                                                        </Link>
                                                    </Button>
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {orders.links && orders.links.length > 3 && (
                        <div className="border-border text-muted-foreground mt-4 flex flex-wrap items-center justify-between gap-2 border-t pt-3 text-[11.5px]">
                            <span>
                                Mostrando {orders.from ?? 0}-{orders.to ?? 0} de{' '}
                                {orders.total} órdenes
                            </span>
                            <div className="flex gap-1">
                                {orders.links.map((link, idx) =>
                                    link.url ? (
                                        <Link
                                            key={idx}
                                            href={link.url}
                                            preserveScroll
                                            className={`inline-flex h-7 min-w-[28px] items-center justify-center rounded-[6px] px-2 font-mono text-xs ${
                                                link.active
                                                    ? 'bg-foreground text-background font-bold shadow-xs'
                                                    : 'border-border bg-card text-foreground/80 hover:bg-background border'
                                            }`}
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                        />
                                    ) : (
                                        <span
                                            key={idx}
                                            className="text-muted-foreground inline-flex h-7 min-w-[28px] items-center justify-center rounded-[6px] border border-transparent px-2 font-mono text-xs opacity-60"
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                        />
                                    ),
                                )}
                            </div>
                        </div>
                    )}
                </Card>
            </div>

            {/* Dialog: Nueva Orden de Servicio */}
            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="border-border bg-card max-h-[90vh] overflow-y-auto rounded-[16px] sm:max-w-xl">
                    <DialogHeader>
                        <div className="flex items-center gap-2.5">
                            <div className="bg-destructive/10 text-primary-strong flex size-9 items-center justify-center rounded-[10px]">
                                <Wrench className="size-5" />
                            </div>
                            <div>
                                <DialogTitle className="text-foreground font-['Oswald',sans-serif] text-[18px] font-semibold">
                                    Nueva orden de servicio
                                </DialogTitle>
                                <DialogDescription className="text-muted-foreground text-xs">
                                    Para el taller de planta o un servicio en
                                    campo. Toda el área la ve; el responsable es
                                    opcional.
                                </DialogDescription>
                            </div>
                        </div>
                    </DialogHeader>

                    <form onSubmit={submitCreate} className="mt-3 space-y-4">
                        <ClientPicker
                            teamSlug={teamSlug}
                            value={cliente}
                            onChange={(ficha) => {
                                setCliente(ficha);
                                createForm.setData((data) => ({
                                    ...data,
                                    client_id: ficha ? String(ficha.id) : '',
                                    referencia: '',
                                }));
                            }}
                            error={createForm.errors.client_id}
                        />

                        <div>
                            <Label className="text-foreground/80 text-[11px] font-bold uppercase">
                                Tipo de servicio *
                            </Label>
                            {createForm.data.tipo_servicio ? (
                                <div className="border-primary/40 bg-destructive/5 mt-1 flex items-center justify-between rounded-[9px] border px-3 py-2">
                                    <span className="text-foreground text-[13px] font-bold">
                                        {createForm.data.tipo_servicio}
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            createForm.setData((data) => ({
                                                ...data,
                                                service_id: '',
                                                tipo_servicio: '',
                                            }))
                                        }
                                        className="text-muted-foreground hover:text-foreground text-[11.5px] font-semibold"
                                    >
                                        Cambiar
                                    </button>
                                </div>
                            ) : (
                                <div className="mt-1">
                                    <CatalogPicker
                                        teamSlug={teamSlug}
                                        soloServicios
                                        onPick={(servicio) =>
                                            createForm.setData((data) => ({
                                                ...data,
                                                service_id: String(servicio.id),
                                                tipo_servicio: servicio.nombre,
                                            }))
                                        }
                                        placeholder="Busca el servicio: recarga, prueba hidrostática, instalación..."
                                    />
                                    {services.length > 0 && (
                                        <div className="mt-2 flex flex-wrap gap-1.5">
                                            {services.map((servicio) => (
                                                <button
                                                    key={servicio.id}
                                                    type="button"
                                                    onClick={() =>
                                                        createForm.setData(
                                                            (data) => ({
                                                                ...data,
                                                                service_id:
                                                                    String(
                                                                        servicio.id,
                                                                    ),
                                                                tipo_servicio:
                                                                    servicio.nombre,
                                                            }),
                                                        )
                                                    }
                                                    className="border-border bg-card text-foreground/80 hover:border-primary/40 rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                                                >
                                                    {servicio.nombre}
                                                </button>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            )}
                            {/* El backend valida la clave "service_id"
                                (StoreServiceOrderRequest), no "tipo_servicio". */}
                            {createForm.errors.service_id && (
                                <p
                                    role="alert"
                                    className="text-destructive-strong mt-1 text-[11px]"
                                >
                                    {createForm.errors.service_id}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2">
                            <div>
                                <Label className="text-foreground/80 text-[11px] font-bold uppercase">
                                    Área
                                </Label>
                                <div className="bg-muted mt-1 flex rounded-[9px] p-[3px]">
                                    {(
                                        [
                                            ['planta', 'Planta (taller)'],
                                            ['campo', 'Campo (in situ)'],
                                        ] as const
                                    ).map(([valor, texto]) => (
                                        <button
                                            key={valor}
                                            type="button"
                                            onClick={() =>
                                                createForm.setData((data) => ({
                                                    ...data,
                                                    departamento_tecnico: valor,
                                                    tecnico_id: '',
                                                }))
                                            }
                                            className={`flex-1 rounded-[7px] px-2 py-1.5 text-xs font-bold transition-all ${createForm.data.departamento_tecnico === valor ? 'bg-card text-foreground shadow-[0_1px_2px_rgba(0,0,0,0.06)]' : 'text-muted-foreground hover:text-foreground'}`}
                                        >
                                            {texto}
                                        </button>
                                    ))}
                                </div>
                            </div>
                            <div>
                                <Label className="text-foreground/80 text-[11px] font-bold uppercase">
                                    Responsable
                                </Label>
                                <select
                                    aria-label="Responsable"
                                    value={createForm.data.tecnico_id}
                                    onChange={(e) =>
                                        createForm.setData(
                                            'tecnico_id',
                                            e.target.value,
                                        )
                                    }
                                    className="border-border bg-card text-foreground focus-visible:border-ring focus-visible:ring-ring/50 mt-1 h-9 w-full rounded-[8px] border px-3 text-[13px] outline-none focus-visible:ring-[3px]"
                                >
                                    <option value="">
                                        Todo el área (sin asignar)
                                    </option>
                                    {tecnicosDelArea.map((tecnico) => (
                                        <option
                                            key={tecnico.id}
                                            value={tecnico.id}
                                        >
                                            {tecnico.name}
                                        </option>
                                    ))}
                                </select>
                                {createForm.errors.tecnico_id && (
                                    <p className="text-destructive-strong mt-1 text-[11px]">
                                        {createForm.errors.tecnico_id}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-[150px_minmax(0,1fr)]">
                            <div>
                                <Label className="text-foreground/80 text-[11px] font-bold uppercase">
                                    Para
                                </Label>
                                <div className="bg-muted mt-1 flex rounded-[9px] p-[3px]">
                                    {(
                                        [
                                            ['local_cliente', 'Local'],
                                            ['vehiculo', 'Vehículo'],
                                        ] as const
                                    ).map(([valor, texto]) => (
                                        <button
                                            key={valor}
                                            type="button"
                                            onClick={() => {
                                                setDestino(valor);
                                                createForm.setData(
                                                    'referencia',
                                                    '',
                                                );
                                            }}
                                            className={`flex-1 rounded-[7px] px-2 py-1.5 text-xs font-bold transition-all ${destino === valor ? 'bg-card text-foreground shadow-[0_1px_2px_rgba(0,0,0,0.06)]' : 'text-muted-foreground hover:text-foreground'}`}
                                        >
                                            {texto}
                                        </button>
                                    ))}
                                </div>
                            </div>
                            <ReferenciaField
                                cliente={cliente}
                                destino={destino}
                                value={createForm.data.referencia}
                                onChange={(valor) =>
                                    createForm.setData('referencia', valor)
                                }
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <Label className="text-foreground/80 text-[11px] font-bold uppercase">
                                    Fecha programada *
                                </Label>
                                <Input
                                    type="date"
                                    required
                                    value={createForm.data.fecha}
                                    onChange={(e) =>
                                        createForm.setData(
                                            'fecha',
                                            e.target.value,
                                        )
                                    }
                                    className="border-border bg-card mt-1 h-9 rounded-[8px] text-[13px]"
                                />
                            </div>
                            <div>
                                <Label className="text-foreground/80 text-[11px] font-bold uppercase">
                                    Prioridad
                                </Label>
                                <select
                                    aria-label="Prioridad"
                                    value={createForm.data.prioridad}
                                    onChange={(e) =>
                                        createForm.setData(
                                            'prioridad',
                                            e.target.value,
                                        )
                                    }
                                    className="border-border bg-card text-foreground focus-visible:border-ring focus-visible:ring-ring/50 mt-1 h-9 w-full rounded-[8px] border px-3 text-[13px] outline-none focus-visible:ring-[3px]"
                                >
                                    <option value="normal">Normal</option>
                                    <option value="alta">Alta</option>
                                    <option value="urgente">Urgente</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <Label className="text-foreground/80 text-[11px] font-bold uppercase">
                                Instrucciones para el técnico
                            </Label>
                            <textarea
                                aria-label="Instrucciones para el técnico"
                                value={createForm.data.observaciones}
                                onChange={(e) =>
                                    createForm.setData(
                                        'observaciones',
                                        e.target.value,
                                    )
                                }
                                placeholder="Ej. 7 extintores PQS 6 kg enumerados del 1 al 7, recoger en recepción."
                                className="border-border bg-card focus-visible:border-ring focus-visible:ring-ring/50 mt-1 min-h-[60px] w-full rounded-[8px] border px-3 py-2 text-[13px] outline-none focus-visible:ring-[3px]"
                            />
                        </div>

                        <DialogFooter className="pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setDialogOpen(false)}
                                className="border-border rounded-[8px] text-xs font-semibold"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={createForm.processing}
                                className="bg-primary hover:bg-primary/90 rounded-[8px] text-xs font-bold text-white"
                            >
                                Crear orden
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </VendedorLayout>
    );
}
