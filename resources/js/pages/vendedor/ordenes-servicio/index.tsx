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
import { FormEvent, useMemo, useState } from 'react';

import ServiceOrderController from '@/actions/App/Http/Controllers/Vendedor/ServiceOrderController';
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

export type ClientOption = {
    id: number;
    razon_social: string;
    numero_documento: string;
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
    clients: ClientOption[];
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
): { label: string; badgeClass: string } {
    switch (coarseLabel?.toLowerCase()) {
        case 'asignada':
            return {
                label: 'Asignada',
                badgeClass: 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20',
            };
        case 'en_proceso':
            return {
                label: 'En proceso',
                badgeClass: 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20',
            };
        case 'completada':
            return {
                label: 'Completada',
                badgeClass: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20',
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
    clients,
}: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug =
        currentTeam?.slug ||
        (typeof window !== 'undefined'
            ? window.location.pathname.split('/')[1]
            : '');

    const [dialogOpen, setDialogOpen] = useState(false);
    const [clientSearch, setClientSearch] = useState('');
    const currentEstado = filters?.estado || '';

    // Form para crear nueva orden
    const createForm = useForm({
        client_id: '',
        tipo_servicio: 'Recarga y mantenimiento de extintores',
        fecha: new Date().toISOString().split('T')[0],
        departamento_tecnico: 'planta',
        prioridad: 'normal',
        observaciones: '',
    });

    const selectedClient = clients.find(
        (client) => client.id === Number(createForm.data.client_id),
    );

    const filteredClients = useMemo(() => {
        const term = clientSearch.trim().toLowerCase();
        if (!term) return clients.slice(0, 10);

        return clients.filter(
            (client) =>
                client.razon_social.toLowerCase().includes(term) ||
                client.numero_documento.includes(term),
        );
    }, [clientSearch, clients]);

    const submitCreate = (e: FormEvent) => {
        e.preventDefault();
        createForm.post(ServiceOrderController.store.url(teamSlug), {
            preserveScroll: true,
            onSuccess: () => {
                setDialogOpen(false);
                setClientSearch('');
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
                <DialogContent className="rounded-[16px] border-border bg-card sm:max-w-lg">
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
                                    Registra una nueva orden para el taller de
                                    planta o servicio en campo.
                                </DialogDescription>
                            </div>
                        </div>
                    </DialogHeader>

                    <form onSubmit={submitCreate} className="mt-3 space-y-3.5">
                        <div>
                            <Label className="text-[11px] font-bold text-foreground/80 uppercase">
                                Cliente *
                            </Label>
                            <div className="mt-1 flex items-center gap-2 rounded-[8px] border border-border bg-card px-3">
                                <Search className="size-3.5 shrink-0 text-muted-foreground" />
                                <input
                                    value={
                                        selectedClient
                                            ? selectedClient.razon_social
                                            : clientSearch
                                    }
                                    onChange={(e) => {
                                        setClientSearch(e.target.value);
                                        createForm.setData('client_id', '');
                                    }}
                                    placeholder="Buscar por RUC, DNI o razón social..."
                                    className="h-9 min-w-0 flex-1 bg-transparent text-[13px] outline-none placeholder:text-muted-foreground"
                                />
                            </div>
                            {createForm.errors.client_id && (
                                <p className="mt-1 text-[11px] text-destructive">
                                    {createForm.errors.client_id}
                                </p>
                            )}

                            {!selectedClient && (
                                <div className="mt-1.5 max-h-[140px] overflow-y-auto rounded-[8px] border border-border">
                                    {filteredClients.length === 0 ? (
                                        <div className="px-3 py-3 text-[12px] text-muted-foreground">
                                            Sin coincidencias.
                                        </div>
                                    ) : (
                                        filteredClients.map((client) => (
                                            <button
                                                key={client.id}
                                                type="button"
                                                onClick={() => {
                                                    createForm.setData(
                                                        'client_id',
                                                        String(client.id),
                                                    );
                                                    setClientSearch('');
                                                }}
                                                className="flex w-full items-center justify-between gap-3 border-b border-border px-3 py-2 text-left last:border-b-0 hover:bg-muted/40"
                                            >
                                                <span>
                                                    <span className="block text-[12.5px] font-bold text-foreground">
                                                        {client.razon_social}
                                                    </span>
                                                    <span className="font-['IBM_Plex_Mono',monospace] text-[10.5px] text-muted-foreground">
                                                        {
                                                            client.numero_documento
                                                        }
                                                    </span>
                                                </span>
                                            </button>
                                        ))
                                    )}
                                </div>
                            )}

                            {selectedClient && (
                                <div className="mt-1.5 flex items-center justify-between rounded-[8px] bg-destructive/10 px-3 py-2">
                                    <span className="text-[12.5px] font-bold text-foreground">
                                        {selectedClient.razon_social}
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            createForm.setData('client_id', '')
                                        }
                                        className="text-[11px] font-semibold text-muted-foreground hover:text-foreground"
                                    >
                                        Cambiar
                                    </button>
                                </div>
                            )}
                        </div>

                        <div>
                            <Label className="text-[11px] font-bold text-foreground/80 uppercase">
                                Tipo de servicio *
                            </Label>
                            <Input
                                required
                                value={createForm.data.tipo_servicio}
                                onChange={(e) =>
                                    createForm.setData(
                                        'tipo_servicio',
                                        e.target.value,
                                    )
                                }
                                placeholder="Ej. Recarga PQS 6kg, Prueba hidrostática..."
                                className="mt-1 h-9 rounded-[8px] border-border bg-card text-[13px]"
                            />
                            {createForm.errors.tipo_servicio && (
                                <p className="mt-1 text-[11px] text-destructive">
                                    {createForm.errors.tipo_servicio}
                                </p>
                            )}
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
                                    Departamento
                                </Label>
                                <select
                                    value={createForm.data.departamento_tecnico}
                                    onChange={(e) =>
                                        createForm.setData(
                                            'departamento_tecnico',
                                            e.target.value,
                                        )
                                    }
                                    className="mt-1 h-9 w-full rounded-[8px] border border-border bg-card px-3 text-[13px] text-foreground outline-none"
                                >
                                    <option value="planta">
                                        Planta (Taller)
                                    </option>
                                    <option value="campo">
                                        Campo (In situ)
                                    </option>
                                </select>
                            </div>
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

                        <div>
                            <Label className="text-[11px] font-bold text-foreground/80 uppercase">
                                Observaciones / Instrucciones
                            </Label>
                            <Input
                                value={createForm.data.observaciones}
                                onChange={(e) =>
                                    createForm.setData(
                                        'observaciones',
                                        e.target.value,
                                    )
                                }
                                placeholder="Detalles para el técnico..."
                                className="mt-1 h-9 rounded-[8px] border-border bg-card text-[13px]"
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
