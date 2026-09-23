import { router, usePage } from '@inertiajs/react';
import { Activity, Filter, ShieldCheck } from 'lucide-react';
import { useState } from 'react';

import GerenteLayout from '@/layouts/gerente-layout';

type AuditLogItem = {
    id: number;
    accion: string;
    usuario: string;
    entidad: string | null;
    entidad_id: number | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    ip_address: string | null;
    fecha: string | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedLogs = {
    data: AuditLogItem[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    total: number;
};

type AuditFilters = {
    accion: string;
    user_id: string | null;
    fecha_desde: string | null;
    fecha_hasta: string | null;
};

type PageProps = {
    currentTeam: { slug: string };
    registros: PaginatedLogs;
    filters: AuditFilters;
    acciones: string[];
    usuarios: Array<{ id: number; name: string }>;
    kpis: { totalRegistros: number; registrosHoy: number };
    [key: string]: unknown;
};

function formatValues(values: Record<string, unknown> | null): string {
    if (!values) {
        return '—';
    }

    return Object.entries(values)
        .map(([key, value]) => `${key}: ${String(value)}`)
        .join(', ');
}

export default function AuditoriaIndex() {
    const { currentTeam, registros, filters, acciones, usuarios, kpis } =
        usePage<PageProps>().props;

    const [accion, setAccion] = useState(filters.accion || 'todas');
    const [userId, setUserId] = useState(filters.user_id || '');
    const [fechaDesde, setFechaDesde] = useState(filters.fecha_desde || '');
    const [fechaHasta, setFechaHasta] = useState(filters.fecha_hasta || '');

    const applyFilters = () => {
        router.get(
            `/${currentTeam.slug}/gerente/auditoria`,
            {
                accion,
                user_id: userId || undefined,
                fecha_desde: fechaDesde || undefined,
                fecha_hasta: fechaHasta || undefined,
            },
            { preserveState: true },
        );
    };

    const resetFilters = () => {
        setAccion('todas');
        setUserId('');
        setFechaDesde('');
        setFechaHasta('');
        router.get(
            `/${currentTeam.slug}/gerente/auditoria`,
            {},
            { preserveState: true },
        );
    };

    return (
        <GerenteLayout title="Auditoría">
            <div className="space-y-6">
                <div>
                    <h1 className="font-['Oswald',sans-serif] text-2xl font-bold tracking-wide text-foreground uppercase">
                        Auditoría del Sistema
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Trazabilidad de acciones sensibles: ventas, ajustes de
                        stock, autorizaciones, cierre de órdenes, certificados y
                        configuración.
                    </p>
                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between text-xs text-muted-foreground">
                            <span className="font-medium uppercase">
                                Registros Totales
                            </span>
                            <ShieldCheck className="size-4 text-blue-600" />
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-foreground">
                            {kpis.totalRegistros}
                        </div>
                        <p className="mt-0.5 text-[11px] text-muted-foreground">
                            Eventos auditados desde el inicio
                        </p>
                    </div>

                    <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between text-xs text-muted-foreground">
                            <span className="font-medium uppercase">
                                Registros Hoy
                            </span>
                            <Activity className="size-4 text-emerald-600" />
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-emerald-700">
                            {kpis.registrosHoy}
                        </div>
                        <p className="mt-0.5 text-[11px] text-muted-foreground">
                            Acciones sensibles registradas hoy
                        </p>
                    </div>
                </div>

                <div className="space-y-3 rounded-xl border border-border bg-card p-4 shadow-xs">
                    <div className="grid grid-cols-1 gap-3 text-xs sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label className="block font-semibold text-foreground/80">
                                Acción
                            </label>
                            <select
                                value={accion}
                                onChange={(e) => setAccion(e.target.value)}
                                className="mt-1 w-full rounded-lg border border-border bg-muted/40 px-3 py-2 text-xs focus:border-primary focus:outline-none"
                            >
                                <option value="todas">
                                    Todas las acciones
                                </option>
                                {acciones.map((a) => (
                                    <option key={a} value={a}>
                                        {a}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="block font-semibold text-foreground/80">
                                Usuario
                            </label>
                            <select
                                value={userId}
                                onChange={(e) => setUserId(e.target.value)}
                                className="mt-1 w-full rounded-lg border border-border bg-muted/40 px-3 py-2 text-xs focus:border-primary focus:outline-none"
                            >
                                <option value="">Todos los usuarios</option>
                                {usuarios.map((u) => (
                                    <option key={u.id} value={u.id}>
                                        {u.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="block font-semibold text-foreground/80">
                                Fecha Desde
                            </label>
                            <input
                                type="date"
                                value={fechaDesde}
                                onChange={(e) => setFechaDesde(e.target.value)}
                                className="mt-1 w-full rounded-lg border border-border bg-muted/40 px-3 py-1.5 text-xs focus:border-primary focus:outline-none"
                            />
                        </div>

                        <div>
                            <label className="block font-semibold text-foreground/80">
                                Fecha Hasta
                            </label>
                            <input
                                type="date"
                                value={fechaHasta}
                                onChange={(e) => setFechaHasta(e.target.value)}
                                className="mt-1 w-full rounded-lg border border-border bg-muted/40 px-3 py-1.5 text-xs focus:border-primary focus:outline-none"
                            />
                        </div>
                    </div>

                    <div className="flex items-center justify-end gap-2 border-t border-border pt-2">
                        <button
                            type="button"
                            onClick={resetFilters}
                            className="rounded-lg border border-border bg-card px-3 py-1.5 text-xs font-semibold text-muted-foreground hover:bg-background"
                        >
                            Limpiar
                        </button>
                        <button
                            type="button"
                            onClick={applyFilters}
                            className="inline-flex items-center gap-1.5 rounded-lg bg-card px-4 py-1.5 text-xs font-semibold text-white hover:bg-foreground/90"
                        >
                            <Filter className="size-3.5" />
                            <span>Aplicar Filtros</span>
                        </button>
                    </div>
                </div>

                <div className="overflow-hidden rounded-xl border border-border bg-card shadow-xs">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="border-b border-border bg-muted/40 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-3">Fecha</th>
                                    <th className="px-4 py-3">Acción</th>
                                    <th className="px-4 py-3">Usuario</th>
                                    <th className="px-4 py-3">Entidad</th>
                                    <th className="px-4 py-3">Detalle</th>
                                    <th className="px-4 py-3">IP</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {registros.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="py-8 text-center text-muted-foreground"
                                        >
                                            No se encontraron registros de
                                            auditoría con estos filtros.
                                        </td>
                                    </tr>
                                ) : (
                                    registros.data.map((r) => (
                                        <tr
                                            key={r.id}
                                            className="transition-colors hover:bg-muted/40"
                                        >
                                            <td className="px-4 py-3 font-mono text-[11px] text-foreground/80">
                                                {r.fecha}
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="inline-flex rounded-full bg-zinc-100 px-2 py-0.5 font-mono text-[10px] font-bold text-zinc-700 uppercase">
                                                    {r.accion}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 font-semibold text-foreground">
                                                {r.usuario}
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {r.entidad
                                                    ? `${r.entidad} #${r.entidad_id}`
                                                    : '—'}
                                            </td>
                                            <td className="max-w-xs truncate px-4 py-3 text-[11px] text-muted-foreground">
                                                {formatValues(r.new_values) !==
                                                '—'
                                                    ? formatValues(r.new_values)
                                                    : formatValues(
                                                          r.old_values,
                                                      )}
                                            </td>
                                            <td className="px-4 py-3 font-mono text-[11px] text-muted-foreground">
                                                {r.ip_address || '—'}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {registros.links && registros.links.length > 3 && (
                        <div className="flex items-center justify-between border-t border-border bg-muted/40 px-4 py-3 text-xs text-muted-foreground">
                            <div>Total: {registros.total} registros</div>
                            <div className="flex items-center gap-1">
                                {registros.links.map((link, i) => {
                                    if (!link.url) {
                                        return (
                                            <span
                                                key={i}
                                                dangerouslySetInnerHTML={{
                                                    __html: link.label,
                                                }}
                                                className="px-2.5 py-1 text-zinc-400"
                                            />
                                        );
                                    }
                                    return (
                                        <button
                                            key={i}
                                            type="button"
                                            onClick={() =>
                                                router.get(
                                                    link.url!,
                                                    {},
                                                    { preserveState: true },
                                                )
                                            }
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                            className={`rounded-md px-2.5 py-1 text-xs transition-colors ${
                                                link.active
                                                    ? 'bg-card font-bold text-white'
                                                    : 'text-foreground/80 hover:bg-muted/40'
                                            }`}
                                        />
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </GerenteLayout>
    );
}
