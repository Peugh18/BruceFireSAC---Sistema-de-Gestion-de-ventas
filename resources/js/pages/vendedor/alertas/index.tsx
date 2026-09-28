import { Head, Link, usePage } from '@inertiajs/react';
import { CheckCircle2, FilePlus2, MessageSquare, Search } from 'lucide-react';
import { useState } from 'react';
import { router } from '@inertiajs/react';

import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import VendedorLayout from '@/layouts/vendedor-layout';
import type { Team } from '@/types';

export type AlertItem = {
    client_id: number;
    cliente: string;
    equipment_id: number | null;
    equipo: string;
    numero_serie: string | null;
    fecha: string;
    dias: number;
    segmento: 'vencidas' | 'esta_semana' | 'este_mes';
    cantidad: number;
    telefono?: string;
    whatsapp?: string;
    origen: 'equipo_registrado' | 'estimado_historico';
};

export type Props = {
    alerts: {
        vencidas: AlertItem[];
        esta_semana: AlertItem[];
        este_mes: AlertItem[];
    };
};

function initials(value: string | undefined): string {
    if (!value) return 'CL';
    return value
        .split(/\s+/u)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

export default function AlertasIndex({ alerts }: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug =
        currentTeam?.slug ||
        (typeof window !== 'undefined'
            ? window.location.pathname.split('/')[1]
            : '');

    const [activeTab, setActiveTab] = useState<
        'todas' | 'vencidas' | 'esta_semana' | 'este_mes'
    >('todas');
    const [search, setSearch] = useState('');

    const vencidas = alerts?.vencidas ?? [];
    const estaSemana = alerts?.esta_semana ?? [];
    const esteMes = alerts?.este_mes ?? [];
    const todas = [...vencidas, ...estaSemana, ...esteMes];

    let currentList: AlertItem[] = [];
    if (activeTab === 'todas') currentList = todas;
    else if (activeTab === 'vencidas') currentList = vencidas;
    else if (activeTab === 'esta_semana') currentList = estaSemana;
    else if (activeTab === 'este_mes') currentList = esteMes;

    if (search.trim()) {
        const q = search.toLowerCase();
        currentList = currentList.filter(
            (a) =>
                a.cliente?.toLowerCase().includes(q) ||
                a.equipo?.toLowerCase().includes(q) ||
                a.numero_serie?.toLowerCase().includes(q),
        );
    }

    const tabs = [
        { id: 'todas', label: 'Todas', count: todas.length },
        { id: 'vencidas', label: 'Vencidas', count: vencidas.length },
        { id: 'esta_semana', label: 'Esta semana', count: estaSemana.length },
        { id: 'este_mes', label: 'Este mes', count: esteMes.length },
    ] as const;

    return (
        <VendedorLayout title="Alertas de Vencimiento">
            <Head title="Alertas de Vencimiento" />

            <div className="flex flex-col gap-4">
                {/* Header title */}
                <div className="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                    <div>
                        <h2 className="text-foreground font-['Oswald',sans-serif] text-[22px] font-semibold">
                            Extintores de tus clientes por vencer
                        </h2>
                        <p className="text-muted-foreground text-[12.5px]">
                            Ordenado por urgencia — aprovecha para ofrecer la
                            recarga o mantenimiento antes de que venza.
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <div className="border-border bg-card flex h-9 w-[220px] items-center gap-2 rounded-[9px] border px-3 text-[12.5px]">
                            <Search className="text-muted-foreground size-3.5" />
                            <input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Filtrar cliente o equipo..."
                                className="text-foreground placeholder:text-muted-foreground w-full bg-transparent outline-none"
                            />
                        </div>
                    </div>
                </div>

                {/* Filter Tabs */}
                <div className="bg-muted flex w-fit rounded-[9px] p-[3px]">
                    {tabs.map((tab) => {
                        const active = activeTab === tab.id;
                        return (
                            <button
                                key={tab.id}
                                type="button"
                                onClick={() => setActiveTab(tab.id)}
                                className={`cursor-pointer rounded-[7px] px-3.5 py-1.5 text-xs font-bold transition-all ${
                                    active
                                        ? 'bg-card text-foreground shadow-[0_1px_2px_rgba(0,0,0,0.06)]'
                                        : 'text-muted-foreground hover:text-foreground'
                                }`}
                            >
                                {tab.label} ({tab.count})
                            </button>
                        );
                    })}
                </div>

                {/* List of Alerts */}
                <div className="flex flex-col gap-3">
                    {currentList.length === 0 ? (
                        <Card className="border-border bg-card flex flex-col items-center justify-center gap-2 rounded-[16px] p-12 text-center shadow-none">
                            <CheckCircle2 className="size-10 text-emerald-600 dark:text-emerald-400" />
                            <p className="text-foreground text-sm font-bold">
                                No hay alertas en esta categoría
                            </p>
                            <p className="text-muted-foreground text-xs">
                                {search
                                    ? 'No se encontraron resultados para tu búsqueda.'
                                    : 'Todos los equipos de tus clientes en este segmento están al día.'}
                            </p>
                        </Card>
                    ) : (
                        currentList.map((item, idx) => {
                            const isVencida =
                                item.segmento === 'vencidas' || item.dias < 0;
                            const isEstaSemana =
                                item.segmento === 'esta_semana' ||
                                (item.dias >= 0 && item.dias <= 7);
                            const phone = item.whatsapp || item.telefono;
                            const phoneClean = phone
                                ? phone.replace(/\D/g, '')
                                : null;

                            const waText = encodeURIComponent(
                                `Hola, le saludamos de Bruce Fire. Le recordamos que su equipo (${item.equipo}) está próximo a vencer el ${item.fecha}. ¿Desea coordinar el servicio de recarga/mantenimiento?`,
                            );

                            return (
                                <div
                                    key={`${item.equipment_id ?? 'hist'}-${item.client_id}-${item.fecha}-${idx}`}
                                    className={`border-border bg-card flex flex-col gap-3 rounded-[14px] border p-4 shadow-none transition-all sm:flex-row sm:items-center sm:gap-4 ${
                                        isVencida
                                            ? 'border-l-[5px] border-l-[#B91C1C]'
                                            : isEstaSemana
                                              ? 'border-l-[5px] border-l-[#B45309]'
                                              : 'border-l-[5px] border-l-[#E4E1DC]'
                                    }`}
                                >
                                    {/* Client avatar */}
                                    <div
                                        className={`flex size-10 shrink-0 items-center justify-center rounded-full text-xs font-bold ${
                                            isVencida
                                                ? 'bg-destructive/10 text-destructive border-destructive/20 border'
                                                : isEstaSemana
                                                  ? 'border border-amber-500/20 bg-amber-500/10 text-amber-600 dark:text-amber-400'
                                                  : 'bg-muted text-foreground/80'
                                        }`}
                                    >
                                        {initials(item.cliente)}
                                    </div>

                                    {/* Details */}
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="text-foreground text-[13.5px] font-bold">
                                                {item.cliente}
                                            </span>
                                            <Badge
                                                className={`rounded-full border-none px-2.5 py-0.5 text-[10.5px] font-bold ${
                                                    isVencida
                                                        ? 'bg-destructive/10 text-destructive border-destructive/20 border'
                                                        : isEstaSemana
                                                          ? 'border border-amber-500/20 bg-amber-500/10 text-amber-600 dark:text-amber-400'
                                                          : 'bg-muted text-muted-foreground'
                                                }`}
                                            >
                                                {isVencida
                                                    ? `Vencido hace ${Math.abs(item.dias)} día(s)`
                                                    : item.dias === 0
                                                      ? 'Vence hoy'
                                                      : `Vence en ${item.dias} días`}
                                            </Badge>
                                            {item.origen ===
                                                'estimado_historico' && (
                                                <Badge
                                                    title="No hay un equipo con número de serie registrado para este cliente — la fecha se estima desde su historial de compras."
                                                    className="rounded-full border-none bg-sky-500/10 px-2.5 py-0.5 text-[10.5px] font-bold text-sky-600 dark:text-sky-400"
                                                >
                                                    Estimado por historial
                                                </Badge>
                                            )}
                                        </div>

                                        <div className="text-muted-foreground mt-1 text-[12px]">
                                            {item.cantidad} extintor(es){' '}
                                            <b className="text-foreground/80 font-semibold">
                                                {item.equipo}
                                            </b>
                                            {item.numero_serie
                                                ? ` — S/N: ${item.numero_serie}`
                                                : ''}
                                            {' — '}
                                            Fecha programada:{' '}
                                            <span className="font-mono">
                                                {item.fecha}
                                            </span>
                                        </div>
                                    </div>

                                    {/* Actions */}
                                    <div className="flex flex-wrap items-center gap-2 sm:self-center">
                                        {phoneClean ? (
                                            <a
                                                href={`https://wa.me/${phoneClean}?text=${waText}`}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="inline-flex h-9 items-center gap-1.5 rounded-[9px] border border-emerald-500/20 bg-emerald-500/10 px-3.5 text-[12px] font-bold text-emerald-600 transition-colors hover:bg-emerald-500/10 dark:text-emerald-400"
                                            >
                                                <MessageSquare className="size-3.5" />
                                                <span>WhatsApp</span>
                                            </a>
                                        ) : (
                                            <Link
                                                href={`/${teamSlug}/vendedor/clientes`}
                                                className="border-border bg-card text-foreground/80 hover:bg-background inline-flex h-9 items-center gap-1 rounded-[9px] border px-3 text-[12px] font-semibold"
                                            >
                                                <span>Ver cliente</span>
                                            </Link>
                                        )}

                                        <button
                                            type="button"
                                            disabled={!item.equipment_id}
                                            onClick={() =>
                                                item.equipment_id &&
                                                router.post(
                                                    `/${teamSlug}/vendedor/alertas/ofrecer-recarga`,
                                                    {
                                                        client_id:
                                                            item.client_id,
                                                        equipment_ids: [
                                                            item.equipment_id,
                                                        ],
                                                    },
                                                )
                                            }
                                            className="bg-primary hover:bg-primary/90 inline-flex h-9 items-center gap-1.5 rounded-[9px] px-3.5 text-[12px] font-bold text-white shadow-none transition-colors"
                                        >
                                            <FilePlus2 className="size-3.5" />
                                            <span>Ofrecer recarga</span>
                                        </button>
                                    </div>
                                </div>
                            );
                        })
                    )}
                </div>
            </div>
        </VendedorLayout>
    );
}
