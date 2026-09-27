import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    Calendar,
    CheckCircle2,
    Clock,
    Eye,
    Filter,
    Plus,
    Search,
    Wrench,
} from 'lucide-react';
import { FormEvent, useState } from 'react';

import ServiceOrderController from '@/actions/App/Http/Controllers/Vendedor/ServiceOrderController';
import CatalogPicker from '@/components/catalog-picker';
import ClientPicker, { type ClientFicha } from '@/components/client-picker';
import ReferenciaField from '@/components/referencia-field';
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

export type ServiceOrderItem = {
    id: number;
    codigo: string;
    cliente: string;
    tecnico?: string | null;
    tipo_servicio: string;
    fecha: string;
    estado: string;
    coarse_label: 'asignada' | 'en_proceso' | 'completada' | 'cerrada' | string;
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
};

const TABS = [
    { id: '', label: 'Todas' },
    { id: 'en_camino', label: 'En camino' },
    { id: 'en_proceso', label: 'En proceso' },
    { id: 'completadas', label: 'Completadas' },
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
                        'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20',
                };
            }
            return {
                label: 'Asignada',
                badgeClass:
                    'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20',
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
                    'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20',
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
        tipo_servicio: '',
        fecha: new Date().toISOString().split('T')[0],
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
        <VendedorLayout title="Órdenes de Servicio">
            <Head title="Órdenes de Servicio" />

            <div className="flex flex-col gap-4">
                <ServiciosTabs
                    teamSlug={teamSlug}
                    activa="ordenes"
                    deficienciasPendientes={sidebarCounts?.deficiencias}
                />

                {/* Header toolbar */}
                <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <div>
                        <h2 className="font-['Oswald',sans-serif] text-[22px] font-semibold text-foreground">
                            Órdenes de Servicio
                        </h2>
                        <p className="text-[12.5px] text-muted-foreground">
                            Órdenes generadas a partir de tus ventas y
                            cotizaciones con servicio incluido.
                        </p>
                    </div>

                    <Button
                        type="button"
                        onClick={() => setDialogOpen(true)}
                        className="h-9 rounded-[9px] bg-primary px-3.5 text-[12.5px] font-bold text-white shadow-none hover:bg-primary/90"
                    >
                        <Plus className="mr-1.5 size-4" />
                        <span>Nueva orden</span>
                    </Button>
                </div>

                {/* Filter Tabs */}
                <div className="flex w-fit rounded-[9px] bg-muted p-[3px]">
                    {TABS.map((tab) => {
                        const active = currentEstado === tab.id;
                        return (
                            <button
                                key={tab.id}
                                type="button"
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
                <Card className="rounded-[16px] border-border bg-card p-5 shadow-none">
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-[12.5px]">
                            <thead>
                                <tr className="border-b border-border">
                                    <th className="px-2.5 py-2.5 text-left font-mono text-[9.5px] font-semibold tracking-[0.05em] text-muted-foreground uppercase">
                                        N° Orden
                                    </th>
                                    <th className="px-2.5 py-2.5 text-left font-mono text-[9.5px] font-semibold tracking-[0.05em] text-muted-foreground uppercase">
                                        Cliente
                                    </th>
                                    <th className="px-2.5 py-2.5 text-left font-mono text-[9.5px] font-semibold tracking-[0.05em] text-muted-foreground uppercase">
                                        Servicio
                                    </th>
                                    <th className="px-2.5 py-2.5 text-left font-mono text-[9.5px] font-semibold tracking-[0.05em] text-muted-foreground uppercase">
                                        Técnico
                                    </th>
                                    <th className="px-2.5 py-2.5 text-center font-mono text-[9.5px] font-semibold tracking-[0.05em] text-muted-foreground uppercase">
                                        Progreso (4 pts)
                                    </th>
                                    <th className="px-2.5 py-2.5 text-left font-mono text-[9.5px] font-semibold tracking-[0.05em] text-muted-foreground uppercase">
                                        Estado
                                    </th>
                                    <th className="px-2.5 py-2.5 text-right font-mono text-[9.5px] font-semibold tracking-[0.05em] text-muted-foreground uppercase">
                                        Acción
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {orders.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="px-4 py-12 text-center text-muted-foreground"
                                        >
                                            <div className="flex flex-col items-center justify-center gap-2">
                                                <Wrench className="size-8 text-muted-foreground" />
                                                <p className="text-xs font-semibold text-foreground">
                                                    No se encontraron órdenes de
                                                    servicio
                                                </p>
                                                <p className="text-[11px] text-muted-foreground">
                                                    {currentEstado
                                                        ? 'No hay registros para este filtro.'
                                                        : 'Aún no se han registrado órdenes de servicio.'}
                                                </p>
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
                                                className="border-b border-border transition-colors hover:bg-muted/40"
                                            >
                                                <td className="px-2.5 py-3.5 font-mono text-xs font-bold text-foreground">
                                                    <Link
                                                        href={ServiceOrderController.show.url(
                                                            {
                                                                current_team:
                                                                    teamSlug,
                                                                service_order:
                                                                    order.id,
                                                            },
                                                        )}
                                                        className="hover:text-primary hover:underline"
                                                    >
                                                        {order.codigo}
                                                    </Link>
                                                </td>

                                                <td className="px-2.5 py-3.5 font-semibold text-foreground">
                                                    {order.cliente}
                                                </td>

                                                <td className="px-2.5 py-3.5 text-foreground/80">
                                                    <span className="line-clamp-1">
                                                        {order.tipo_servicio}
                                                    </span>
                                                    <span className="font-mono text-[10px] text-muted-foreground">
                                                        {order.fecha}
                                                    </span>
                                                </td>

                                                <td className="px-2.5 py-3.5 text-[12px] text-foreground/80">
                                                    <div>
                                                        {order.tecnico ||
                                                            'Por asignar'}
                                                    </div>
                                                    {order.departamento_tecnico && (
                                                        <div className="font-mono text-[10px] text-muted-foreground capitalize">
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
                                                        className="size-7 rounded-[7px] border-border bg-card text-foreground/80 shadow-none hover:bg-background"
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
                        <div className="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-border pt-3 text-[11.5px] text-muted-foreground">
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
                                                    ? 'bg-card font-bold text-white'
                                                    : 'border border-border bg-card text-foreground/80 hover:bg-background'
                                            }`}
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                        />
                                    ) : (
                                        <span
                                            key={idx}
                                            className="inline-flex h-7 min-w-[28px] items-center justify-center rounded-[6px] border border-transparent px-2 font-mono text-xs text-muted-foreground opacity-60"
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
                <DialogContent className="max-h-[90vh] overflow-y-auto rounded-[16px] border-border bg-card sm:max-w-xl">
                    <DialogHeader>
                        <div className="flex items-center gap-2.5">
                            <div className="flex size-9 items-center justify-center rounded-[10px] bg-destructive/10 text-primary">
                                <Wrench className="size-5" />
                            </div>
                            <div>
                                <DialogTitle className="font-['Oswald',sans-serif] text-[18px] font-semibold text-foreground">
                                    Nueva orden de servicio
                                </DialogTitle>
                                <DialogDescription className="text-xs text-muted-foreground">
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
                            <Label className="text-[11px] font-bold text-foreground/80 uppercase">
                                Tipo de servicio *
                            </Label>
                            {createForm.data.tipo_servicio ? (
                                <div className="mt-1 flex items-center justify-between rounded-[9px] border border-primary/40 bg-destructive/5 px-3 py-2">
                                    <span className="text-[13px] font-bold text-foreground">
                                        {createForm.data.tipo_servicio}
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            createForm.setData(
                                                'tipo_servicio',
                                                '',
                                            )
                                        }
                                        className="text-[11.5px] font-semibold text-muted-foreground hover:text-foreground"
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
                                            createForm.setData(
                                                'tipo_servicio',
                                                servicio.nombre,
                                            )
                                        }
                                        placeholder="Busca el servicio: recarga, prueba hidrostática, instalación..."
                                    />
                                </div>
                            )}
                            {createForm.errors.tipo_servicio && (
                                <p className="mt-1 text-[11px] text-destructive">
                                    {createForm.errors.tipo_servicio}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2">
                            <div>
                                <Label className="text-[11px] font-bold text-foreground/80 uppercase">
                                    Área
                                </Label>
                                <div className="mt-1 flex rounded-[9px] bg-muted p-[3px]">
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
                                <Label className="text-[11px] font-bold text-foreground/80 uppercase">
                                    Responsable
                                </Label>
                                <select
                                    value={createForm.data.tecnico_id}
                                    onChange={(e) =>
                                        createForm.setData(
                                            'tecnico_id',
                                            e.target.value,
                                        )
                                    }
                                    className="mt-1 h-9 w-full rounded-[8px] border border-border bg-card px-3 text-[13px] text-foreground outline-none"
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
                                    <p className="mt-1 text-[11px] text-destructive">
                                        {createForm.errors.tecnico_id}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-[150px_minmax(0,1fr)]">
                            <div>
                                <Label className="text-[11px] font-bold text-foreground/80 uppercase">
                                    Para
                                </Label>
                                <div className="mt-1 flex rounded-[9px] bg-muted p-[3px]">
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
                                <Label className="text-[11px] font-bold text-foreground/80 uppercase">
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
                                    className="mt-1 h-9 rounded-[8px] border-border bg-card text-[13px]"
                                />
                            </div>
                            <div>
                                <Label className="text-[11px] font-bold text-foreground/80 uppercase">
                                    Prioridad
                                </Label>
                                <select
                                    value={createForm.data.prioridad}
                                    onChange={(e) =>
                                        createForm.setData(
                                            'prioridad',
                                            e.target.value,
                                        )
                                    }
                                    className="mt-1 h-9 w-full rounded-[8px] border border-border bg-card px-3 text-[13px] text-foreground outline-none"
                                >
                                    <option value="normal">Normal</option>
                                    <option value="alta">Alta</option>
                                    <option value="urgente">Urgente</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <Label className="text-[11px] font-bold text-foreground/80 uppercase">
                                Instrucciones para el técnico
                            </Label>
                            <textarea
                                value={createForm.data.observaciones}
                                onChange={(e) =>
                                    createForm.setData(
                                        'observaciones',
                                        e.target.value,
                                    )
                                }
                                placeholder="Ej. 7 extintores PQS 6 kg enumerados del 1 al 7, recoger en recepción."
                                className="mt-1 min-h-[60px] w-full rounded-[8px] border border-border bg-card px-3 py-2 text-[13px] outline-none"
                            />
                        </div>

                        <DialogFooter className="pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setDialogOpen(false)}
                                className="rounded-[8px] border-border text-xs font-semibold"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={createForm.processing}
                                className="rounded-[8px] bg-primary text-xs font-bold text-white hover:bg-primary/90"
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
