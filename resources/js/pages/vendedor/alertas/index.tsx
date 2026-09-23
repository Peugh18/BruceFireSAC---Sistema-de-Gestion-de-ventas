import { Head, Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Bell,
    CheckCircle2,
    Clock,
    FilePlus2,
    MessageSquare,
    Search,
    Send,
} from 'lucide-react';
import { useState } from 'react';

import QuoteController from '@/actions/App/Http/Controllers/Vendedor/QuoteController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import VendedorLayout from '@/layouts/vendedor-layout';
import type { Team } from '@/types';

export type AlertItem = {
    client_id: number;
    cliente: string;
    equipment_id: number;
    equipo: string;
    numero_serie: string;
    fecha: string;
    dias: number;
    segmento: 'vencidas' | 'esta_semana' | 'este_mes';
    cantidad: number;
    telefono?: string;
    whatsapp?: string;
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
                        <h2 className="font-['Oswald',sans-serif] text-[22px] font-semibold text-foreground">
                            Extintores de tus clientes por vencer
                        </h2>
                        <p className="text-[12.5px] text-muted-foreground">
                            Ordenado por urgencia — aprovecha para ofrecer la
                            recarga o mantenimiento antes de que venza.
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <div className="flex h-9 w-[220px] items-center gap-2 rounded-[9px] border border-border bg-card px-3 text-[12.5px]">
                            <Search className="size-3.5 text-muted-foreground" />
                            <input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Filtrar cliente o equipo..."
                                className="w-full bg-transparent text-foreground outline-none placeholder:text-muted-foreground"
                            />
                        </div>
                    </div>
                </div>

                {/* Filter Tabs */}
                <div className="flex w-fit rounded-[9px] bg-muted p-[3px]">
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
                        <Card className="flex flex-col items-center justify-center gap-2 rounded-[16px] border-border bg-card p-12 text-center shadow-none">
                            <CheckCircle2 className="size-10 text-emerald-600 dark:text-emerald-400" />
                            <p className="text-sm font-bold text-foreground">
                                No hay alertas en esta categoría
                            </p>
                            <p className="text-xs text-muted-foreground">
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
                                    key={`${item.equipment_id}-${item.fecha}-${idx}`}
                                    className={`flex flex-col gap-3 rounded-[14px] border border-border bg-card p-4 shadow-none transition-all sm:flex-row sm:items-center sm:gap-4 ${
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
                                                ? 'bg-destructive/10 text-destructive border border-destructive/20'
                                                : isEstaSemana
                                                  ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20'
                                                  : 'bg-muted text-foreground/80'
                                        }`}
                                    >
                                        {initials(item.cliente)}
                                    </div>

                                    {/* Details */}
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="text-[13.5px] font-bold text-foreground">
                                                {item.cliente}
                                            </span>
                                            <Badge
                                                className={`rounded-full border-none px-2.5 py-0.5 text-[10.5px] font-bold ${
                                                    isVencida
                                                        ? 'bg-destructive/10 text-destructive border border-destructive/20'
                                                        : isEstaSemana
                                                          ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20'
                                                          : 'bg-muted text-muted-foreground'
                                                }`}
                                            >
                                                {isVencida
                                                    ? `Vencido hace ${Math.abs(item.dias)} día(s)`
                                                    : item.dias === 0
                                                      ? 'Vence hoy'
                                                      : `Vence en ${item.dias} días`}
                                            </Badge>
                                        </div>

                                        <div className="mt-1 text-[12px] text-muted-foreground">
                                            {item.cantidad} extintor(es){' '}
                                            <b className="font-semibold text-foreground/80">
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
                                                className="inline-flex h-9 items-center gap-1.5 rounded-[9px] border border-emerald-500/20 bg-emerald-500/10 px-3.5 text-[12px] font-bold text-emerald-600 dark:text-emerald-400 transition-colors hover:bg-emerald-500/10"
                                            >
                                                <MessageSquare className="size-3.5" />
                                                <span>WhatsApp</span>
                                            </a>
                                        ) : (
                                            <Link
                                                href={`/${teamSlug}/vendedor/clientes`}
                                                className="inline-flex h-9 items-center gap-1 rounded-[9px] border border-border bg-card px-3 text-[12px] font-semibold text-foreground/80 hover:bg-background"
                                            >
                                                <span>Ver cliente</span>
                                            </Link>
                                        )}

                                        <Link
                                            href={QuoteController.index.url(
                                                teamSlug,
                                            )}
                                            className="inline-flex h-9 items-center gap-1.5 rounded-[9px] bg-primary px-3.5 text-[12px] font-bold text-white shadow-none transition-colors hover:bg-primary/90"
                                        >
                                            <FilePlus2 className="size-3.5" />
                                            <span>Crear cotización</span>
                                        </Link>
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
