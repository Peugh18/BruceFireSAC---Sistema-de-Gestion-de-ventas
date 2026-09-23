import { router, usePage } from '@inertiajs/react';
import { CheckCircle2, ShieldQuestion, Users } from 'lucide-react';
import { useState } from 'react';

import GerenteLayout from '@/layouts/gerente-layout';

type UsuarioItem = {
    id: number;
    name: string;
    email: string;
    role: string | null;
};

type PageProps = {
    currentTeam: { slug: string };
    usuarios: UsuarioItem[];
    roles: string[];
    matrizPermisos: Record<string, string[]>;
    flash?: { success?: string; error?: string };
    [key: string]: unknown;
};

function roleLabel(role: string | null): string {
    if (!role) {
        return 'Sin rol asignado';
    }

    return role.replace(/([a-z])([A-Z])/g, '$1 $2');
}

const MODULE_LABELS: Record<string, string> = {
    dashboard: 'Dashboard',
    clients: 'Clientes',
    client_sites: 'Sedes del Cliente',
    vehicles: 'Vehículos',
    sedes: 'Sedes',
    inventory: 'Inventario',
    products: 'Productos',
    services: 'Servicios',
    quotes: 'Cotizaciones',
    sales: 'Ventas',
    certificates: 'Certificados',
    billing: 'Facturación',
    guias_remision: 'Guías de Remisión',
    collections: 'Cobranzas',
    cashregister: 'Caja',
    alerts: 'Alertas',
    service_orders: 'Órdenes de Servicio',
    deficiencies: 'Deficiencias',
    communication: 'Comunicación',
    equipment: 'Equipos',
    reports: 'Reportes',
    audit: 'Auditoría',
    roles_permissions: 'Roles y Permisos',
};

const ACTION_LABELS: Record<string, string> = {
    view: 'Ver',
    view_own: 'Ver lo propio',
    view_total: 'Ver todo',
    view_all: 'Ver todo',
    view_history: 'Ver historial',
    create: 'Crear',
    update: 'Editar',
    manage: 'Gestionar',
    receive: 'Recibir',
    adjust: 'Ajustar',
    print_stickers: 'Imprimir stickers',
    lookup: 'Consultar',
    convert: 'Convertir',
    scan_units: 'Escanear unidades',
    print: 'Imprimir',
    generate: 'Generar',
    resend: 'Reenviar',
    download: 'Descargar',
    void: 'Anular',
    credit_note: 'Nota de crédito',
    register_payment: 'Registrar pago',
    open: 'Abrir',
    close: 'Cerrar',
    assign: 'Asignar',
    execute: 'Ejecutar',
    authorize: 'Autorizar',
    resolve: 'Resolver',
    create_event: 'Registrar evento',
    toggle_status: 'Activar/Desactivar',
    export: 'Exportar',
};

function humanizePermission(permiso: string): string {
    const [module, ...actionParts] = permiso.split('.');
    const action = actionParts.join('.');
    const moduleLabel = MODULE_LABELS[module] ?? module;
    const actionLabel = ACTION_LABELS[action] ?? action.replaceAll('_', ' ');

    return `${moduleLabel}: ${actionLabel}`;
}

export default function UsuariosIndex() {
    const { currentTeam, usuarios, roles, matrizPermisos, flash } =
        usePage<PageProps>().props;
    const [tab, setTab] = useState<'usuarios' | 'matriz'>('usuarios');
    const [pendingUserId, setPendingUserId] = useState<number | null>(null);

    const handleRoleChange = (user: UsuarioItem, newRole: string) => {
        if (newRole === user.role) {
            return;
        }

        setPendingUserId(user.id);
        router.patch(
            `/${currentTeam.slug}/gerente/usuarios/${user.id}/rol`,
            { role: newRole },
            {
                preserveScroll: true,
                onFinish: () => setPendingUserId(null),
            },
        );
    };

    return (
        <GerenteLayout title="Usuarios y Roles">
            <div className="space-y-6">
                <div>
                    <h1 className="font-['Oswald',sans-serif] text-2xl font-bold tracking-wide text-foreground uppercase">
                        Usuarios y Roles
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Asignación de uno de los 5 roles fijos del sistema y
                        consulta de la matriz de permisos efectivos.
                    </p>
                </div>

                {flash?.success && (
                    <div className="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-xs font-medium text-emerald-800">
                        <CheckCircle2 className="size-4" />
                        {flash.success}
                    </div>
                )}

                <div className="flex gap-2 border-b border-border">
                    <button
                        type="button"
                        onClick={() => setTab('usuarios')}
                        className={`flex items-center gap-1.5 border-b-2 px-3 py-2 text-xs font-semibold transition-colors ${
                            tab === 'usuarios'
                                ? 'border-primary text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        <Users className="size-3.5" />
                        Usuarios del Team
                    </button>
                    <button
                        type="button"
                        onClick={() => setTab('matriz')}
                        className={`flex items-center gap-1.5 border-b-2 px-3 py-2 text-xs font-semibold transition-colors ${
                            tab === 'matriz'
                                ? 'border-primary text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        <ShieldQuestion className="size-3.5" />
                        Matriz de Permisos (solo lectura)
                    </button>
                </div>

                {tab === 'usuarios' && (
                    <div className="overflow-hidden rounded-xl border border-border bg-card shadow-xs">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead className="border-b border-border bg-muted/40 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                                    <tr>
                                        <th className="px-4 py-3">Nombre</th>
                                        <th className="px-4 py-3">Email</th>
                                        <th className="px-4 py-3">
                                            Rol Actual
                                        </th>
                                        <th className="px-4 py-3">
                                            Cambiar Rol
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {usuarios.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={4}
                                                className="py-8 text-center text-muted-foreground"
                                            >
                                                No hay usuarios en este team
                                                todavía.
                                            </td>
                                        </tr>
                                    ) : (
                                        usuarios.map((u) => (
                                            <tr
                                                key={u.id}
                                                className="transition-colors hover:bg-muted/40"
                                            >
                                                <td className="px-4 py-3 font-semibold text-foreground">
                                                    {u.name}
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {u.email}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <span
                                                        className={`inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold uppercase ${
                                                            u.role
                                                                ? 'bg-zinc-100 text-zinc-700'
                                                                : 'bg-amber-100 text-amber-800'
                                                        }`}
                                                    >
                                                        {roleLabel(u.role)}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <select
                                                        value={u.role ?? ''}
                                                        disabled={
                                                            pendingUserId ===
                                                            u.id
                                                        }
                                                        onChange={(e) =>
                                                            handleRoleChange(
                                                                u,
                                                                e.target.value,
                                                            )
                                                        }
                                                        className="w-full max-w-[180px] rounded-lg border border-border bg-muted/40 px-3 py-1.5 text-xs focus:border-primary focus:outline-none disabled:opacity-50"
                                                    >
                                                        <option
                                                            value=""
                                                            disabled
                                                        >
                                                            Seleccionar rol
                                                        </option>
                                                        {roles.map((r) => (
                                                            <option
                                                                key={r}
                                                                value={r}
                                                            >
                                                                {roleLabel(r)}
                                                            </option>
                                                        ))}
                                                    </select>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {tab === 'matriz' && (
                    <div className="space-y-4">
                        {Object.entries(matrizPermisos).map(
                            ([role, permisos]) => (
                                <div
                                    key={role}
                                    className="rounded-xl border border-border bg-card p-4 shadow-xs"
                                >
                                    <h3 className="font-['Oswald',sans-serif] text-sm font-bold tracking-wide text-foreground uppercase">
                                        {roleLabel(role)}
                                    </h3>
                                    <div className="mt-2.5 flex flex-wrap gap-1.5">
                                        {permisos.map((permiso) => (
                                            <span
                                                key={permiso}
                                                title={permiso}
                                                className="rounded-full bg-background px-2 py-0.5 text-[10.5px] font-semibold text-foreground/80"
                                            >
                                                {humanizePermission(permiso)}
                                            </span>
                                        ))}
                                    </div>
                                </div>
                            ),
                        )}
                    </div>
                )}
            </div>
        </GerenteLayout>
    );
}
