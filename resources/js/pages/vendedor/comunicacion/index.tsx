import { Head, Link, usePage } from '@inertiajs/react';
import {
    CheckCircle2,
    Clock,
    ExternalLink,
    Info,
    MessageCircle,
    Search,
    Wrench,
} from 'lucide-react';
import { useMemo, useState } from 'react';

import ServiceOrderController from '@/actions/App/Http/Controllers/Vendedor/ServiceOrderController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import VendedorLayout from '@/layouts/vendedor-layout';
import type { Team } from '@/types';

export type CommunicationEvent = {
    id: number;
    tipo: string;
    payload?: {
        mensaje?: string;
        resultado?: string;
        deficiency_id?: number;
        [key: string]: unknown;
    };
    created_at: string;
    user?: {
        name: string;
    };
};

export type CommunicationOrder = {
    id: number;
    codigo: string;
    cliente: string;
    estado: string;
    ultimo_evento?: CommunicationEvent | null;
    events?: CommunicationEvent[];
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Props = {
    orders: {
        data: CommunicationOrder[];
        links?: PaginationLink[];
        total?: number;
    };
};

export default function ComunicacionIndex({ orders }: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug =
        currentTeam?.slug ||
        (typeof window !== 'undefined'
            ? window.location.pathname.split('/')[1]
            : '');

    const orderList = orders?.data ?? [];
    const [selectedOrderId, setSelectedOrderId] = useState<number | null>(
        orderList[0]?.id ?? null,
    );
    const [search, setSearch] = useState('');

    const filteredOrders = useMemo(() => {
        if (!search.trim()) return orderList;
        const q = search.toLowerCase();
        return orderList.filter(
            (o) =>
                o.codigo.toLowerCase().includes(q) ||
                o.cliente.toLowerCase().includes(q) ||
                o.ultimo_evento?.payload?.mensaje?.toLowerCase().includes(q),
        );
    }, [orderList, search]);

    const selectedOrder = useMemo(() => {
        return (
            orderList.find((o) => o.id === selectedOrderId) ||
            orderList[0] ||
            null
        );
    }, [orderList, selectedOrderId]);

    return (
        <VendedorLayout title="Comunicación con Taller">
            <Head title="Comunicación con Taller" />

            <div className="flex h-[calc(100vh-125px)] flex-col gap-3">
                {/* Notice header */}
                <div className="flex flex-col justify-between gap-1 sm:flex-row sm:items-center">
                    <div>
                        <h2 className="font-['Oswald',sans-serif] text-[20px] font-semibold text-foreground">
                            Comunicación con Taller y Planta
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            Bitácora y registro cronológico de eventos para cada
                            orden de servicio activa.
                        </p>
                    </div>
                </div>

                {/* 2-Panel Chat Layout */}
                <Card className="flex flex-1 overflow-hidden rounded-[16px] border-border bg-card p-0 shadow-none">
                    {/* Panel Izquierdo: Lista de Órdenes / Conversaciones (340px) */}
                    <div className="flex w-full shrink-0 flex-col border-r border-border bg-card sm:w-[340px]">
                        {/* Search header */}
                        <div className="border-b border-border p-3">
                            <div className="flex h-9 items-center gap-2 rounded-[9px] border border-border bg-muted/40 px-3 text-[12.5px]">
                                <Search className="size-3.5 text-muted-foreground" />
                                <input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Buscar por orden o cliente..."
                                    className="w-full bg-transparent text-foreground outline-none placeholder:text-muted-foreground"
                                />
                            </div>
                        </div>

                        {/* Order list items */}
                        <div className="flex-1 divide-y divide-border overflow-y-auto">
                            {filteredOrders.length === 0 ? (
                                <div className="p-8 text-center text-xs text-muted-foreground">
                                    No hay órdenes registradas con eventos
                                    recientes.
                                </div>
                            ) : (
                                filteredOrders.map((order) => {
                                    const isSelected =
                                        selectedOrder?.id === order.id;
                                    const previewText =
                                        order.ultimo_evento?.payload?.mensaje ||
                                        (order.ultimo_evento?.payload?.resultado
                                            ? `Resultado: ${order.ultimo_evento.payload.resultado}`
                                            : order.ultimo_evento?.tipo
                                              ? `Evento: ${order.ultimo_evento.tipo.replace(/_/g, ' ')}`
                                              : 'Sin mensajes registrados');

                                    return (
                                        <div
                                            key={order.id}
                                            onClick={() =>
                                                setSelectedOrderId(order.id)
                                            }
                                            className={`cursor-pointer p-3.5 transition-colors ${
                                                isSelected
                                                    ? 'border-l-4 border-l-[#E31E24] bg-destructive/10'
                                                    : 'hover:bg-muted/40'
                                            }`}
                                        >
                                            <div className="flex items-center justify-between">
                                                <span className="font-mono text-[11px] font-bold text-muted-foreground">
                                                    {order.codigo}
                                                </span>
                                                <Badge
                                                    className={`rounded-full border-none px-2 py-0.5 text-[9.5px] font-bold capitalize ${
                                                        isSelected
                                                            ? 'bg-primary text-white'
                                                            : 'bg-muted text-muted-foreground'
                                                    }`}
                                                >
                                                    {order.estado.replace(
                                                        /_/g,
                                                        ' ',
                                                    )}
                                                </Badge>
                                            </div>

                                            <div className="mt-1 truncate text-[13px] font-bold text-foreground">
                                                {order.cliente}
                                            </div>

                                            <div className="mt-1 truncate text-[11.5px] text-muted-foreground">
                                                {previewText}
                                            </div>
                                        </div>
                                    );
                                })
                            )}
                        </div>
                    </div>

                    {/* Panel Derecho: Bitácora de Eventos (Solo Lectura) */}
                    <div className="flex min-w-0 flex-1 flex-col bg-background">
                        {selectedOrder ? (
                            <>
                                {/* Header del Panel Derecho */}
                                <div className="flex items-center justify-between border-b border-border bg-card px-6 py-3.5">
                                    <div className="min-w-0">
                                        <div className="flex items-center gap-2">
                                            <span className="font-mono font-bold text-foreground">
                                                {selectedOrder.codigo}
                                            </span>
                                            <span className="text-muted-foreground">
                                                &bull;
                                            </span>
                                            <span className="truncate font-semibold text-foreground">
                                                {selectedOrder.cliente}
                                            </span>
                                        </div>
                                        <div className="text-[11.5px] text-muted-foreground">
                                            Bitácora técnica de planta y taller
                                        </div>
                                    </div>

                                    <Button
                                        asChild
                                        variant="outline"
                                        size="sm"
                                        className="h-8 rounded-[8px] border-border bg-card text-xs font-semibold text-foreground/80 hover:bg-background"
                                    >
                                        <Link
                                            href={ServiceOrderController.show.url(
                                                {
                                                    current_team: teamSlug,
                                                    service_order:
                                                        selectedOrder.id,
                                                },
                                            )}
                                        >
                                            <span>Ver orden completa</span>
                                            <ExternalLink className="ml-1.5 size-3" />
                                        </Link>
                                    </Button>
                                </div>

                                {/* Body con Burbujas de Eventos (Solo lectura) */}
                                <div className="flex-1 space-y-4 overflow-y-auto p-6">
                                    {/* Burbuja inicial: Creación de la orden */}
                                    <div className="flex max-w-[80%] flex-col rounded-[12px] border border-border bg-card p-3.5 shadow-2xs">
                                        <div className="flex items-center justify-between gap-3 text-[11px] text-muted-foreground">
                                            <span className="font-bold text-foreground">
                                                Sistema Bruce Fire
                                            </span>
                                            <span>Inicio de orden</span>
                                        </div>
                                        <p className="mt-1 text-[13px] text-muted-foreground">
                                            Orden de servicio creada para el
                                            cliente{' '}
                                            <b>{selectedOrder.cliente}</b>.
                                        </p>
                                    </div>

                                    {/* Burbujas de los eventos reales */}
                                    {selectedOrder.ultimo_evento ? (
                                        <div
                                            className={`flex max-w-[80%] flex-col rounded-[12px] p-3.5 shadow-2xs ${
                                                selectedOrder.ultimo_evento
                                                    .tipo ===
                                                'autorizacion_registrada'
                                                    ? 'self-end rounded-br-xs bg-primary text-white'
                                                    : 'self-start rounded-bl-xs border border-border bg-card text-foreground'
                                            }`}
                                        >
                                            <div
                                                className={`flex items-center justify-between gap-3 text-[11px] ${
                                                    selectedOrder.ultimo_evento
                                                        .tipo ===
                                                    'autorizacion_registrada'
                                                        ? 'text-white/80'
                                                        : 'text-muted-foreground'
                                                }`}
                                            >
                                                <span className="font-bold capitalize">
                                                    {selectedOrder.ultimo_evento
                                                        .user?.name ||
                                                        selectedOrder.ultimo_evento.tipo.replace(
                                                            /_/g,
                                                            ' ',
                                                        )}
                                                </span>
                                                <span className="font-mono text-[10px]">
                                                    {new Date(
                                                        selectedOrder
                                                            .ultimo_evento
                                                            .created_at,
                                                    ).toLocaleTimeString(
                                                        'es-PE',
                                                        {
                                                            hour: '2-digit',
                                                            minute: '2-digit',
                                                        },
                                                    )}
                                                </span>
                                            </div>

                                            <div className="mt-1 text-[13px] leading-relaxed">
                                                {selectedOrder.ultimo_evento
                                                    .payload?.mensaje ||
                                                    (selectedOrder.ultimo_evento
                                                        .payload?.resultado
                                                        ? `Resultado de autorización: ${selectedOrder.ultimo_evento.payload.resultado}`
                                                        : `Evento registrado: ${selectedOrder.ultimo_evento.tipo.replace(/_/g, ' ')}`)}
                                            </div>

                                            <div
                                                className={`mt-1 font-mono text-[9.5px] ${
                                                    selectedOrder.ultimo_evento
                                                        .tipo ===
                                                    'autorizacion_registrada'
                                                        ? 'text-white/70'
                                                        : 'text-muted-foreground'
                                                }`}
                                            >
                                                {new Date(
                                                    selectedOrder.ultimo_evento
                                                        .created_at,
                                                ).toLocaleDateString('es-PE')}
                                            </div>
                                        </div>
                                    ) : null}

                                    {/* Indicador de estado actual */}
                                    <div className="my-2 flex items-center justify-center">
                                        <span className="rounded-full bg-muted/40 px-3 py-1 font-mono text-[10.5px] font-bold text-muted-foreground">
                                            Estado actual:{' '}
                                            {selectedOrder.estado
                                                .replace(/_/g, ' ')
                                                .toUpperCase()}
                                        </span>
                                    </div>
                                </div>

                                {/* Footer de Solo Lectura (sin input editable) */}
                                <div className="border-t border-border bg-card px-6 py-3 text-xs text-muted-foreground">
                                    <div className="flex items-center gap-2">
                                        <Info className="size-4 text-muted-foreground" />
                                        <span>
                                            <b>Bitácora de solo lectura:</b> Los
                                            eventos se generan automáticamente
                                            conforme los técnicos avanzan en
                                            Planta o Campo. Para autorizaciones,
                                            usa el módulo de Deficiencias y
                                            Adicionales.
                                        </span>
                                    </div>
                                </div>
                            </>
                        ) : (
                            <div className="flex flex-1 flex-col items-center justify-center gap-2 text-center text-muted-foreground">
                                <MessageCircle className="size-9 text-muted-foreground" />
                                <p className="text-sm font-semibold text-foreground">
                                    Selecciona una orden de servicio
                                </p>
                                <p className="text-xs">
                                    Haz clic en una orden del panel izquierdo
                                    para consultar su bitácora de eventos.
                                </p>
                            </div>
                        )}
                    </div>
                </Card>
            </div>
        </VendedorLayout>
    );
}
