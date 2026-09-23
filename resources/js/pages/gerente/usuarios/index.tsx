import { router, usePage } from '@inertiajs/react';
import { CheckCircle2, ShieldQuestion, Users } from 'lucide-react';
import { useState } from 'react';

import GerenteLayout from '@/layouts/gerente-layout';

type UsuarioItem = {
    id: number;
    name: string;
    email: string;
    role: string | null;
    sede_id: number | null;
};

type SedeOption = { id: number; nombre: string };

type PageProps = {
    currentTeam: { slug: string };
    usuarios: UsuarioItem[];
    roles: string[];
    sedes: SedeOption[];
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
    const { currentTeam, usuarios, roles, sedes, matrizPermisos, flash } =
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

    const handleSedeChange = (user: UsuarioItem, value: string) => {
        setPendingUserId(user.id);
        router.patch(
            `/${currentTeam.slug}/gerente/usuarios/${user.id}/sede`,
            { sede_id: value === '' ? null : Number(value) },
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
                    <h1 className="text-foreground font-['Oswald',sans-serif] text-2xl font-bold tracking-wide uppercase">
                        Usuarios y Roles
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">
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

                <div className="border-border flex gap-2 border-b">
                    <button
                        type="button"
                        onClick={() => setTab('usuarios')}
                        className={`flex items-center gap-1.5 border-b-2 px-3 py-2 text-xs font-semibold transition-colors ${
                            tab === 'usuarios'
                                ? 'border-primary text-foreground'
                                : 'text-muted-foreground hover:text-foreground border-transparent'
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
                                : 'text-muted-foreground hover:text-foreground border-transparent'
                        }`}
                    >
                        <ShieldQuestion className="size-3.5" />
                        Matriz de Permisos (solo lectura)
                    </button>
                </div>

                {tab === 'usuarios' && (
                    <div className="border-border bg-card overflow-hidden rounded-xl border shadow-xs">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead className="border-border bg-muted/40 text-muted-foreground border-b text-[11px] font-semibold tracking-wider uppercase">
                                    <tr>
                                        <th className="px-4 py-3">Nombre</th>
                                        <th className="px-4 py-3">Email</th>
                                        <th className="px-4 py-3">
                                            Rol Actual
                                        </th>
                                        <th className="px-4 py-3">
                                            Cambiar Rol
                                        </th>
                                        <th className="px-4 py-3">Sede</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-border divide-y">
                                    {usuarios.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={5}
                                                className="text-muted-foreground py-8 text-center"
                                            >
                                                No hay usuarios en este team
                                                todavía.
                                            </td>
                                        </tr>
                                    ) : (
                                        usuarios.map((u) => (
                                            <tr
                                                key={u.id}
                                                className="hover:bg-muted/40 transition-colors"
                                            >
                                                <td className="text-foreground px-4 py-3 font-semibold">
                                                    {u.name}
                                                </td>
                                                <td className="text-muted-foreground px-4 py-3">
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
                                                        className="border-border bg-muted/40 focus:border-primary w-full max-w-[180px] rounded-lg border px-3 py-1.5 text-xs focus:outline-none disabled:opacity-50"
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
                                                <td className="px-4 py-3">
                                                    <select
                                                        value={u.sede_id ?? ''}
                                                        disabled={
                                                            pendingUserId ===
                                                            u.id
                                                        }
                                                        onChange={(e) =>
                                                            handleSedeChange(
                                                                u,
                                                                e.target.value,
                                                            )
                                                        }
                                                        className="border-border bg-muted/40 focus:border-primary w-full max-w-[200px] rounded-lg border px-3 py-1.5 text-xs focus:outline-none disabled:opacity-50"
                                                    >
                                                        <option value="">
                                                            Todas las sedes
                                                        </option>
                                                        {sedes.map((sede) => (
                                                            <option
                                                                key={sede.id}
                                                                value={sede.id}
                                                            >
                                                                {sede.nombre}
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
                                    className="border-border bg-card rounded-xl border p-4 shadow-xs"
                                >
                                    <h3 className="text-foreground font-['Oswald',sans-serif] text-sm font-bold tracking-wide uppercase">
                                        {roleLabel(role)}
                                    </h3>
                                    <div className="mt-2.5 flex flex-wrap gap-1.5">
                                        {permisos.map((permiso) => (
                                            <span
                                                key={permiso}
                                                title={permiso}
                                                className="bg-background text-foreground/80 rounded-full px-2 py-0.5 text-[10.5px] font-semibold"
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
