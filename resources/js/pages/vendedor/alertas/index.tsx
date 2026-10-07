import { Head, Link, usePage } from '@inertiajs/react';
import {
    CheckCircle2,
    FilePlus2,
    MessageSquare,
    RotateCcw,
    Search,
} from 'lucide-react';
import { useState } from 'react';
import { router } from '@inertiajs/react';

import PorVencerEmpresas, {
    filtrarPorPlazo,
    type Empresa,
    type PlazoEmpresa,
} from '@/components/por-vencer-empresas';
import { RecompraBadge, type Recompra } from '@/components/recompra-badge';
import { Badge } from '@/components/ui/badge';
import { PageHeader } from '@/components/page-header';
import { Card } from '@/components/ui/card';
import VendedorLayout from '@/layouts/vendedor-layout';
import rutasAlertas from '@/routes/vendedor/alertas';
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
    recompra: Recompra | null;
    /** X8: último contacto registrado (30 días), para no ofrecer dos veces. */
    contactado?: {
        fecha: string;
        usuario: string | null;
        nota: string | null;
    } | null;
    tipo_alerta?:
        | 'recarga_anual'
        | 'prueba_hidrostatica'
        | 'recarga_y_ph'
        | 'descargado_uso';
    motivo_alerta?: string;
};

export type Props = {
    alerts: {
        vencidas: AlertItem[];
        esta_semana: AlertItem[];
        este_mes: AlertItem[];
    };
    empresas: Empresa[];
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

export default function AlertasIndex({ alerts, empresas }: Props) {
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
    const [vista, setVista] = useState<'empresas' | 'avisos'>('empresas');
    const [plazo, setPlazo] = useState<PlazoEmpresa>('todas');

    const q = search.trim().toLowerCase();
    const empresasFiltradas = filtrarPorPlazo(empresas, plazo).filter(
        (empresa) =>
            !q ||
            empresa.cliente.toLowerCase().includes(q) ||
            empresa.numero_documento.includes(q) ||
            empresa.equipos.some((equipo) =>
                (equipo.numero_serie ?? equipo.equipo)
                    .toLowerCase()
                    .includes(q),
            ),
    );
    const plazos = [
        { id: 'todas', label: 'Todas', count: empresas.length },
        {
            id: 'vencidas',
            label: 'Con vencidos',
            count: filtrarPorPlazo(empresas, 'vencidas').length,
        },
        {
            id: '30',
            label: 'Próximos 30 días',
            count: filtrarPorPlazo(empresas, '30').length,
        },
        {
            id: '90',
            label: 'Próximos 3 meses',
            count: filtrarPorPlazo(empresas, '90').length,
        },
    ] as const;

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
        const busqueda = search.toLowerCase();
        currentList = currentList.filter(
            (a) =>
                a.cliente?.toLowerCase().includes(busqueda) ||
                a.equipo?.toLowerCase().includes(busqueda) ||
                a.numero_serie?.toLowerCase().includes(busqueda),
        );
    }

    const tabs = [
        { id: 'todas', label: 'Todas', count: todas.length },
        { id: 'vencidas', label: 'Vencidas', count: vencidas.length },
        { id: 'esta_semana', label: 'Esta semana', count: estaSemana.length },
        { id: 'este_mes', label: 'Este mes', count: esteMes.length },
    ] as const;

    return (
        <VendedorLayout title="Por vencer">
            <Head title="Por vencer" />

            <div className="flex flex-col gap-4">
                {/* Header title */}
                <PageHeader
                    title="Por vencer"
                    description="Extintores de tus clientes por empresa, del vencimiento más próximo al más lejano. Ofrece la recarga o el mantenimiento antes de que venzan."
                    actions={
                        <div className="border-border bg-card focus-within:border-ring focus-within:ring-ring/50 flex h-9 w-[220px] items-center gap-2 rounded-[9px] border px-3 text-[12.5px] focus-within:ring-[3px]">
                            <Search className="text-muted-foreground size-3.5" />
                            <input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Empresa, RUC o serie..."
                                className="text-foreground placeholder:text-muted-foreground w-full bg-transparent outline-none"
                            />
                        </div>
                    }
                />

                <div className="flex flex-wrap items-center gap-2.5">
                    <div className="border-border flex rounded-[10px] border p-[3px]">
                        {(
                            [
                                { id: 'empresas', label: 'Por empresa' },
                                { id: 'avisos', label: 'Avisos por extintor' },
                            ] as const
                        ).map((opcion) => (
                            <button
                                key={opcion.id}
                                type="button"
                                onClick={() => setVista(opcion.id)}
                                className={`rounded-[7px] px-3.5 py-1.5 text-xs font-bold transition-all ${
                                    vista === opcion.id
                                        ? 'bg-foreground text-background'
                                        : 'text-muted-foreground hover:text-foreground'
                                }`}
                            >
                                {opcion.label}
                            </button>
                        ))}
                    </div>
                    {vista === 'empresas' ? (
                        <div className="bg-muted flex w-fit max-w-full overflow-x-auto rounded-[9px] p-[3px]">
                            {plazos.map((opcion) => (
                                <button
                                    key={opcion.id}
                                    type="button"
                                    onClick={() => setPlazo(opcion.id)}
                                    className={`cursor-pointer rounded-[7px] px-3.5 py-1.5 text-xs font-bold whitespace-nowrap transition-all ${
                                        plazo === opcion.id
                                            ? 'bg-card text-foreground shadow-[0_1px_2px_rgba(0,0,0,0.06)]'
                                            : 'text-muted-foreground hover:text-foreground'
                                    }`}
                                >
                                    {opcion.label} ({opcion.count})
                                </button>
                            ))}
                        </div>
                    ) : null}
                </div>

                {vista === 'empresas' ? (
                    <PorVencerEmpresas
                        empresas={empresasFiltradas}
                        teamSlug={teamSlug}
                    />
                ) : (
                    <>
                        {/* Filter Tabs */}
                        <div className="bg-muted flex w-fit max-w-full overflow-x-auto rounded-[9px] p-[3px]">
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
                                    <CheckCircle2 className="text-success-strong size-10" />
                                    <p className="text-foreground text-sm font-bold">
                                        No hay alertas en esta categoría
                                    </p>
                                    <p className="text-muted-foreground text-xs">
                                        {search
                                            ? 'No se encontraron resultados para tu búsqueda.'
                                            : 'Todos los equipos de tus clientes en este segmento están al día.'}
                                    </p>
                                    {search && (
                                        <button
                                            type="button"
                                            onClick={() => setSearch('')}
                                            className="border-border bg-card text-foreground hover:bg-muted mt-2 inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold shadow-xs transition-colors"
                                        >
                                            <RotateCcw className="size-3" />
                                            <span>Limpiar búsqueda</span>
                                        </button>
                                    )}
                                </Card>
                            ) : (
                                currentList.map((item, idx) => {
                                    const isVencida =
                                        item.segmento === 'vencidas' ||
                                        item.dias < 0;
                                    const isEstaSemana =
                                        item.segmento === 'esta_semana' ||
                                        (item.dias >= 0 && item.dias <= 7);
                                    const phone =
                                        item.whatsapp || item.telefono;
                                    const phoneClean = phone
                                        ? phone.replace(/\D/g, '')
                                        : null;

                                    const waText = encodeURIComponent(
                                        item.tipo_alerta === 'descargado_uso'
                                            ? `Hola, le saludamos de Bruce Fire. Su extintor (${item.equipo}${item.numero_serie ? ` S/N ${item.numero_serie}` : ''}) se encuentra descargado/usado. Por ser equipo de un solo uso, requiere recarga inmediata para asegurar su operatividad. ¿Desea coordinar el servicio de recarga?`
                                            : item.tipo_alerta ===
                                                'prueba_hidrostatica'
                                              ? `Hola, le saludamos de Bruce Fire. Le recordamos que su equipo (${item.equipo}) cumple su ciclo obligatorio de 5 años para la Prueba Hidrostática (PH - NTP 350.043) el ${item.fecha}. ¿Desea coordinar la prueba hidrostática?`
                                              : item.tipo_alerta ===
                                                  'recarga_y_ph'
                                                ? `Hola, le saludamos de Bruce Fire. Le recordamos que su equipo (${item.equipo}) tiene vencida tanto su recarga anual como su Prueba Hidrostática obligatoria (ciclo 5 años). ¿Desea coordinar el servicio completo en planta?`
                                                : `Hola, le saludamos de Bruce Fire. Le recordamos que su equipo (${item.equipo}) está próximo a su recarga anual obligatoria el ${item.fecha}. ¿Desea coordinar el servicio de recarga/mantenimiento?`,
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
                                                        ? 'bg-destructive/10 text-destructive-strong border-destructive/20 border'
                                                        : isEstaSemana
                                                          ? 'text-warning-strong border border-amber-500/20 bg-amber-500/10'
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
                                                                ? 'bg-destructive/10 text-destructive-strong border-destructive/20 border'
                                                                : isEstaSemana
                                                                  ? 'text-warning-strong border border-amber-500/20 bg-amber-500/10'
                                                                  : 'bg-muted text-muted-foreground'
                                                        }`}
                                                    >
                                                        {item.tipo_alerta ===
                                                        'descargado_uso'
                                                            ? 'Descargado / Usado'
                                                            : isVencida
                                                              ? `Vencido hace ${Math.abs(item.dias)} día(s)`
                                                              : item.dias === 0
                                                                ? 'Vence hoy'
                                                                : `Vence en ${item.dias} días`}
                                                    </Badge>
                                                    {item.tipo_alerta ===
                                                        'prueba_hidrostatica' && (
                                                        <Badge
                                                            title="Prueba Hidrostática periódica cada 5 años (Norma Técnica Peruana NTP 350.043)"
                                                            className="rounded-full border border-purple-500/30 bg-purple-500/10 px-2.5 py-0.5 text-[10.5px] font-bold text-purple-700 dark:text-purple-300"
                                                        >
                                                            P.H. (5 años)
                                                        </Badge>
                                                    )}
                                                    {item.tipo_alerta ===
                                                        'recarga_y_ph' && (
                                                        <Badge
                                                            title="Requiere tanto recarga anual (1 año) como Prueba Hidrostática (5 años)"
                                                            className="rounded-full border border-red-500/30 bg-red-500/10 px-2.5 py-0.5 text-[10.5px] font-bold text-red-700 dark:text-red-300"
                                                        >
                                                            Recarga (1 año) +
                                                            P.H. (5 años)
                                                        </Badge>
                                                    )}
                                                    {item.tipo_alerta ===
                                                        'descargado_uso' && (
                                                        <Badge
                                                            title="Extintor de un solo uso: al percutarse pierde presión y debe recargarse de inmediato"
                                                            className="border-destructive/40 bg-destructive/15 text-destructive-strong rounded-full border px-2.5 py-0.5 text-[10.5px] font-bold"
                                                        >
                                                            Un solo uso ·
                                                            Recarga obligatoria
                                                        </Badge>
                                                    )}
                                                    {item.recompra && (
                                                        <RecompraBadge
                                                            recompra={
                                                                item.recompra
                                                            }
                                                        />
                                                    )}
                                                    {item.origen ===
                                                        'estimado_historico' && (
                                                        <Badge
                                                            title="No hay un equipo con número de serie registrado para este cliente — la fecha se estima desde su historial de compras."
                                                            className="rounded-full border-none bg-sky-500/10 px-2.5 py-0.5 text-[10.5px] font-bold text-sky-600 dark:text-sky-400"
                                                        >
                                                            Estimado por
                                                            historial
                                                        </Badge>
                                                    )}
                                                </div>

                                                {item.contactado && (
                                                    <div className="text-success-strong mt-1 text-[12px] font-semibold">
                                                        Contactado el{' '}
                                                        {item.contactado.fecha}
                                                        {item.contactado
                                                            .usuario &&
                                                            ` por ${item.contactado.usuario}`}
                                                        {item.contactado.nota &&
                                                            `: ${item.contactado.nota}`}
                                                    </div>
                                                )}

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
                                                        className="text-success-strong inline-flex h-9 items-center gap-1.5 rounded-[9px] border border-emerald-500/20 bg-emerald-500/10 px-3.5 text-[12px] font-bold transition-colors hover:bg-emerald-500/10 dark:text-emerald-400"
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
                                                    disabled={
                                                        !item.equipment_id
                                                    }
                                                    onClick={() =>
                                                        item.equipment_id &&
                                                        router.post(
                                                            rutasAlertas.ofrecerRecarga.url(
                                                                teamSlug,
                                                            ),
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

                                                {!item.contactado && (
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            const nota =
                                                                window.prompt(
                                                                    'Nota corta del contacto (opcional)',
                                                                );

                                                            if (nota === null) {
                                                                return;
                                                            }

                                                            router.post(
                                                                rutasAlertas.contactado.url(
                                                                    teamSlug,
                                                                ),
                                                                {
                                                                    client_id:
                                                                        item.client_id,
                                                                    equipment_id:
                                                                        item.equipment_id,
                                                                    nota,
                                                                },
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            );
                                                        }}
                                                        className="border-border bg-card text-foreground/80 hover:bg-background inline-flex h-9 items-center gap-1 rounded-[9px] border px-3 text-[12px] font-semibold"
                                                    >
                                                        Marcar contactado
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                    );
                                })
                            )}
                        </div>
                    </>
                )}
            </div>
        </VendedorLayout>
    );
}
