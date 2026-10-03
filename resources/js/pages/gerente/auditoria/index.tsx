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
                    <h1 className="text-foreground font-['Oswald',sans-serif] text-2xl font-bold tracking-wide uppercase">
                        Auditoría del Sistema
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Trazabilidad de acciones sensibles: ventas, ajustes de
                        stock, autorizaciones, cierre de órdenes, certificados y
                        configuración.
                    </p>
                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="border-border bg-card rounded-xl border p-4 shadow-xs">
                        <div className="text-muted-foreground flex items-center justify-between text-xs">
                            <span className="font-medium uppercase">
                                Registros Totales
                            </span>
                            <ShieldCheck className="size-4 text-blue-600" />
                        </div>
                        <div className="text-foreground mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold">
                            {kpis.totalRegistros}
                        </div>
                        <p className="text-muted-foreground mt-0.5 text-[11px]">
                            Eventos auditados desde el inicio
                        </p>
                    </div>

                    <div className="border-border bg-card rounded-xl border p-4 shadow-xs">
                        <div className="text-muted-foreground flex items-center justify-between text-xs">
                            <span className="font-medium uppercase">
                                Registros Hoy
                            </span>
                            <Activity className="text-success-strong size-4" />
                        </div>
                        <div className="mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold text-emerald-700">
                            {kpis.registrosHoy}
                        </div>
                        <p className="text-muted-foreground mt-0.5 text-[11px]">
                            Acciones sensibles registradas hoy
                        </p>
                    </div>
                </div>

                <div className="border-border bg-card space-y-3 rounded-xl border p-4 shadow-xs">
                    <div className="grid grid-cols-1 gap-3 text-xs sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label className="text-foreground/80 block font-semibold">
                                Acción
                            </label>
                            <select
                                value={accion}
                                onChange={(e) => setAccion(e.target.value)}
                                className="border-border bg-muted/40 focus:border-primary mt-1 w-full rounded-lg border px-3 py-2 text-xs focus:outline-none"
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
                            <label className="text-foreground/80 block font-semibold">
                                Usuario
                            </label>
                            <select
                                value={userId}
                                onChange={(e) => setUserId(e.target.value)}
                                className="border-border bg-muted/40 focus:border-primary mt-1 w-full rounded-lg border px-3 py-2 text-xs focus:outline-none"
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
                            <label className="text-foreground/80 block font-semibold">
                                Fecha Desde
                            </label>
                            <input
                                type="date"
                                value={fechaDesde}
                                onChange={(e) => setFechaDesde(e.target.value)}
                                className="border-border bg-muted/40 focus:border-primary mt-1 w-full rounded-lg border px-3 py-1.5 text-xs focus:outline-none"
                            />
                        </div>

                        <div>
                            <label className="text-foreground/80 block font-semibold">
                                Fecha Hasta
                            </label>
                            <input
                                type="date"
                                value={fechaHasta}
                                onChange={(e) => setFechaHasta(e.target.value)}
                                className="border-border bg-muted/40 focus:border-primary mt-1 w-full rounded-lg border px-3 py-1.5 text-xs focus:outline-none"
                            />
                        </div>
                    </div>

                    <div className="border-border flex items-center justify-end gap-2 border-t pt-2">
                        <button
                            type="button"
                            onClick={resetFilters}
                            className="border-border bg-card text-muted-foreground hover:bg-background rounded-lg border px-3 py-1.5 text-xs font-semibold"
                        >
                            Limpiar
                        </button>
                        <button
                            type="button"
                            onClick={applyFilters}
                            className="bg-foreground text-background hover:bg-foreground/90 inline-flex items-center gap-1.5 rounded-lg px-4 py-1.5 text-xs font-semibold shadow-xs transition-colors"
                        >
                            <Filter className="size-3.5" />
                            <span>Aplicar Filtros</span>
                        </button>
                    </div>
                </div>

                <div className="border-border bg-card overflow-hidden rounded-xl border shadow-xs">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="border-border bg-muted/40 text-muted-foreground border-b text-[11px] font-semibold tracking-wider uppercase">
                                <tr>
                                    <th className="px-4 py-3">Fecha</th>
                                    <th className="px-4 py-3">Acción</th>
                                    <th className="px-4 py-3">Usuario</th>
                                    <th className="px-4 py-3">Entidad</th>
                                    <th className="px-4 py-3">Detalle</th>
                                    <th className="px-4 py-3">IP</th>
                                </tr>
                            </thead>
                            <tbody className="divide-border divide-y">
                                {registros.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="text-muted-foreground py-8 text-center"
                                        >
                                            No se encontraron registros de
                                            auditoría con estos filtros.
                                        </td>
                                    </tr>
                                ) : (
                                    registros.data.map((r) => (
                                        <tr
                                            key={r.id}
                                            className="hover:bg-muted/40 transition-colors"
                                        >
                                            <td className="text-foreground/80 px-4 py-3 font-mono text-[11px]">
                                                {r.fecha}
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="inline-flex rounded-full bg-zinc-100 px-2 py-0.5 font-mono text-[10px] font-bold text-zinc-700 uppercase">
                                                    {r.accion}
                                                </span>
                                            </td>
                                            <td className="text-foreground px-4 py-3 font-semibold">
                                                {r.usuario}
                                            </td>
                                            <td className="text-muted-foreground px-4 py-3">
                                                {r.entidad
                                                    ? `${r.entidad} #${r.entidad_id}`
                                                    : '—'}
                                            </td>
                                            <td className="text-muted-foreground max-w-xs truncate px-4 py-3 text-[11px]">
                                                {formatValues(r.new_values) !==
                                                '—'
                                                    ? formatValues(r.new_values)
                                                    : formatValues(
                                                          r.old_values,
                                                      )}
                                            </td>
                                            <td className="text-muted-foreground px-4 py-3 font-mono text-[11px]">
                                                {r.ip_address || '—'}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {registros.links && registros.links.length > 3 && (
                        <div className="border-border bg-muted/40 text-muted-foreground flex items-center justify-between border-t px-4 py-3 text-xs">
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
                                                    ? 'bg-foreground text-background font-bold shadow-xs'
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
