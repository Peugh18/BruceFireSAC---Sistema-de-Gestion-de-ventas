import { router, usePage } from '@inertiajs/react';
import { CheckCircle2, Search, ShieldQuestion, Users } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
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
import { NativeSelect } from '@/components/ui/native-select';
import { TabsBf, propsPanel } from '@/components/ui/tabs-bf';
import GerenteLayout from '@/layouts/gerente-layout';
import UsuariosRoutes from '@/routes/gerente/usuarios';
import EditarUsuarioDialog from './editar-usuario-dialog';
import NuevoUsuarioDialog from './nuevo-usuario-dialog';

type UsuarioItem = {
    id: number;
    name: string;
    email: string;
    role: string | null;
    sede_id: number | null;
    sede_nombre: string | null;
    activo: boolean;
};
type SedeOption = { id: number; nombre: string; tipo: string };
type PageProps = {
    auth: { user: { id: number } };
    currentTeam: { slug: string };
    usuarios: UsuarioItem[];
    roles: string[];
    sedes: SedeOption[];
    tiposDeSedePorRol: Record<string, string[]>;
    matrizPermisos: Record<string, string[]>;
    filters: { buscar: string };
    flash?: { success?: string; error?: string };
    [key: string]: unknown;
};
type CambioPendiente = {
    usuario: UsuarioItem;
    anterior: string;
    nuevo: string;
} & (
    | { tipo: 'rol'; valor: string }
    | { tipo: 'sede'; valor: number | null }
    | { tipo: 'estado'; valor: boolean }
);

function roleLabel(role: string | null): string {
    if (!role) {
        return 'Sin rol asignado';
    }

    const etiquetas: Record<string, string> = {
        Almacen: 'Almacén',
        TecnicoPlanta: 'Técnico de planta',
        TecnicoCampo: 'Técnico de campo',
    };

    return etiquetas[role] ?? role.replace(/([a-z])([A-Z])/g, '$1 $2');
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
    const {
        auth,
        currentTeam,
        usuarios,
        roles,
        sedes,
        tiposDeSedePorRol,
        matrizPermisos,
        filters,
        flash,
    } = usePage<PageProps>().props;
    const [tab, setTab] = useState<'usuarios' | 'matriz'>('usuarios');
    const [buscar, setBuscar] = useState(filters.buscar);
    const [cambio, setCambio] = useState<CambioPendiente | null>(null);
    const [editando, setEditando] = useState<UsuarioItem | null>(null);
    const [guardando, setGuardando] = useState(false);
    const [error, setError] = useState('');
    const sedesPara = (rol: string | null) => {
        const tipos = rol ? tiposDeSedePorRol[rol] : undefined;
        return tipos
            ? sedes.filter((sede) => tipos.includes(sede.tipo))
            : sedes;
    };
    const nombreSede = (usuario: UsuarioItem) =>
        usuario.sede_nombre ??
        (usuario.role === 'Gerente' ? 'Todas las sedes' : 'Sin sede asignada');
    const preparar = (pendiente: CambioPendiente) => {
        setError('');
        setCambio(pendiente);
    };
    const confirmar = () => {
        if (!cambio || guardando) {
            return;
        }
        const params = {
            current_team: currentTeam.slug,
            user: cambio.usuario.id,
        };
        const url =
            cambio.tipo === 'rol'
                ? UsuariosRoutes.updateRole.url(params)
                : cambio.tipo === 'sede'
                  ? UsuariosRoutes.updateSede.url(params)
                  : UsuariosRoutes.updateStatus.url(params);
        const data =
            cambio.tipo === 'rol'
                ? { role: cambio.valor }
                : cambio.tipo === 'sede'
                  ? { sede_id: cambio.valor }
                  : { activo: cambio.valor };
        setGuardando(true);
        setError('');
        router.patch(url, data, {
            preserveScroll: true,
            onSuccess: () => setCambio(null),
            onError: (errors) => {
                const message =
                    Object.values(errors)[0] ?? 'No se pudo guardar el cambio.';
                setError(message);
                toast.error(message);
            },
            onFinish: () => setGuardando(false),
        });
    };
    const filtrar = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            UsuariosRoutes.index.url({ current_team: currentTeam.slug }),
            { buscar: buscar.trim() },
            { preserveScroll: true },
        );
    };

    return (
        <GerenteLayout title="Usuarios y Roles">
            <div className="space-y-6 pb-24 sm:pb-0">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-foreground font-['Oswald',sans-serif] text-2xl font-bold tracking-wide uppercase">
                            Usuarios y roles
                        </h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Administra los datos, el acceso y la sede de tu
                            equipo. Los cambios de acceso requieren
                            confirmación.
                        </p>
                    </div>
                    <NuevoUsuarioDialog
                        teamSlug={currentTeam.slug}
                        roles={roles}
                        sedesPara={sedesPara}
                        roleLabel={roleLabel}
                    />
                </header>
                {flash?.success && (
                    <p
                        role="status"
                        className="border-border bg-muted text-foreground flex items-center gap-2 rounded-xl border p-3 text-sm"
                    >
                        <CheckCircle2
                            className="size-4 shrink-0"
                            aria-hidden="true"
                        />
                        {flash.success}
                    </p>
                )}
                <TabsBf<'usuarios' | 'matriz'>
                    pestanas={[
                        { id: 'usuarios', titulo: 'Usuarios', icono: Users },
                        {
                            id: 'matriz',
                            titulo: 'Matriz de permisos (solo lectura)',
                            icono: ShieldQuestion,
                        },
                    ]}
                    activa={tab}
                    onCambiar={setTab}
                    etiqueta="Vistas de usuarios y permisos"
                    idBase="usuarios"
                />
                <div {...propsPanel('usuarios', tab)}>
                    {tab === 'usuarios' ? (
                        <div className="space-y-4">
                            <form
                                onSubmit={filtrar}
                                className="border-border bg-card flex flex-wrap items-end gap-3 rounded-xl border p-4"
                            >
                                <div className="min-w-0 flex-1 basis-56 space-y-1.5">
                                    <Label htmlFor="buscar-usuarios">
                                        Buscar por nombre o correo
                                    </Label>
                                    <Input
                                        id="buscar-usuarios"
                                        type="search"
                                        value={buscar}
                                        onChange={(event) =>
                                            setBuscar(event.target.value)
                                        }
                                        placeholder="Nombre o correo electrónico"
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    variant="secondary"
                                    className="min-h-11"
                                >
                                    <Search aria-hidden="true" />
                                    Buscar
                                </Button>
                                {filters.buscar && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="min-h-11"
                                        onClick={() =>
                                            router.get(
                                                UsuariosRoutes.index.url({
                                                    current_team:
                                                        currentTeam.slug,
                                                }),
                                            )
                                        }
                                    >
                                        Limpiar
                                    </Button>
                                )}
                            </form>
                            <p
                                role="status"
                                className="text-muted-foreground text-sm"
                            >
                                {usuarios.length}{' '}
                                {usuarios.length === 1
                                    ? 'trabajador'
                                    : 'trabajadores'}
                                {filters.buscar
                                    ? usuarios.length === 1
                                        ? ' encontrado'
                                        : ' encontrados'
                                    : ' en el equipo'}
                            </p>
                            {usuarios.length === 0 ? (
                                <div className="border-border bg-card rounded-xl border p-6 text-center">
                                    <h2 className="text-foreground font-semibold">
                                        {filters.buscar
                                            ? 'No hay coincidencias con esta búsqueda'
                                            : 'No hay trabajadores registrados'}
                                    </h2>
                                    <p className="text-muted-foreground mt-2 text-sm">
                                        {filters.buscar
                                            ? 'Prueba otro nombre o correo, o limpia el filtro.'
                                            : 'Agrega un trabajador para asignarle su rol y su sede.'}
                                    </p>
                                </div>
                            ) : (
                                <ul
                                    className="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
                                    aria-label="Trabajadores del equipo"
                                >
                                    {usuarios.map((usuario) => {
                                        const propia =
                                            usuario.id === auth.user.id;
                                        return (
                                            <li
                                                key={usuario.id}
                                                className="border-border bg-card min-w-0 space-y-4 rounded-xl border p-4 shadow-xs"
                                            >
                                                <div className="flex items-start justify-between gap-2">
                                                    <div className="min-w-0">
                                                        <h2 className="text-foreground font-semibold wrap-anywhere">
                                                            {usuario.name}
                                                            {propia
                                                                ? ' (tú)'
                                                                : ''}
                                                        </h2>
                                                        <p className="text-muted-foreground mt-1 text-sm wrap-anywhere">
                                                            {usuario.email}
                                                        </p>
                                                    </div>
                                                    <span className="bg-muted text-foreground shrink-0 rounded-full px-2 py-1 text-xs font-semibold">
                                                        {usuario.activo
                                                            ? 'Activa'
                                                            : 'Desactivada'}
                                                    </span>
                                                </div>
                                                <div className="space-y-1.5">
                                                    <Label
                                                        htmlFor={`rol-usuario-${usuario.id}`}
                                                    >
                                                        Rol
                                                    </Label>
                                                    <NativeSelect
                                                        id={`rol-usuario-${usuario.id}`}
                                                        value={
                                                            usuario.role ?? ''
                                                        }
                                                        className="border-input bg-background text-foreground focus-visible:ring-ring min-h-11 w-full min-w-0 rounded-xl border px-3 text-sm focus-visible:ring-2"
                                                        onChange={(e) => {
                                                            if (
                                                                e.target
                                                                    .value !==
                                                                usuario.role
                                                            ) {
                                                                preparar({
                                                                    tipo: 'rol',
                                                                    usuario,
                                                                    anterior:
                                                                        roleLabel(
                                                                            usuario.role,
                                                                        ),
                                                                    nuevo: roleLabel(
                                                                        e.target
                                                                            .value,
                                                                    ),
                                                                    valor: e
                                                                        .target
                                                                        .value,
                                                                });
                                                            }
                                                        }}
                                                    >
                                                        <option
                                                            value=""
                                                            disabled
                                                        >
                                                            Sin rol asignado
                                                        </option>
                                                        {roles.map((role) => (
                                                            <option
                                                                key={role}
                                                                value={role}
                                                            >
                                                                {roleLabel(
                                                                    role,
                                                                )}
                                                            </option>
                                                        ))}
                                                    </NativeSelect>
                                                </div>
                                                <div className="space-y-1.5">
                                                    <Label
                                                        htmlFor={`sede-usuario-${usuario.id}`}
                                                    >
                                                        Sede
                                                    </Label>
                                                    <NativeSelect
                                                        id={`sede-usuario-${usuario.id}`}
                                                        value={
                                                            usuario.sede_id ??
                                                            ''
                                                        }
                                                        className="border-input bg-background text-foreground focus-visible:ring-ring min-h-11 w-full min-w-0 rounded-xl border px-3 text-sm focus-visible:ring-2"
                                                        onChange={(e) => {
                                                            const valor =
                                                                e.target
                                                                    .value ===
                                                                ''
                                                                    ? null
                                                                    : Number(
                                                                          e
                                                                              .target
                                                                              .value,
                                                                      );
                                                            if (
                                                                valor !==
                                                                usuario.sede_id
                                                            ) {
                                                                preparar({
                                                                    tipo: 'sede',
                                                                    usuario,
                                                                    anterior:
                                                                        nombreSede(
                                                                            usuario,
                                                                        ),
                                                                    nuevo:
                                                                        sedes.find(
                                                                            (
                                                                                sede,
                                                                            ) =>
                                                                                sede.id ===
                                                                                valor,
                                                                        )
                                                                            ?.nombre ??
                                                                        'Todas las sedes',
                                                                    valor,
                                                                });
                                                            }
                                                        }}
                                                    >
                                                        <option
                                                            value=""
                                                            disabled={
                                                                usuario.role !==
                                                                'Gerente'
                                                            }
                                                        >
                                                            {usuario.role ===
                                                            'Gerente'
                                                                ? 'Todas las sedes'
                                                                : 'Sin sede asignada'}
                                                        </option>
                                                        {usuario.sede_id &&
                                                            !sedesPara(
                                                                usuario.role,
                                                            ).some(
                                                                (sede) =>
                                                                    sede.id ===
                                                                    usuario.sede_id,
                                                            ) && (
                                                                <option
                                                                    value={
                                                                        usuario.sede_id
                                                                    }
                                                                >
                                                                    {nombreSede(
                                                                        usuario,
                                                                    )}{' '}
                                                                    (no
                                                                    disponible)
                                                                </option>
                                                            )}
                                                        {sedesPara(
                                                            usuario.role,
                                                        ).map((sede) => (
                                                            <option
                                                                key={sede.id}
                                                                value={sede.id}
                                                            >
                                                                {sede.nombre}
                                                            </option>
                                                        ))}
                                                    </NativeSelect>
                                                    {!usuario.sede_id &&
                                                        usuario.role !==
                                                            'Gerente' && (
                                                            <p className="text-destructive-strong text-xs">
                                                                Necesita una
                                                                sede para
                                                                trabajar.
                                                            </p>
                                                        )}
                                                </div>
                                                <div className="flex flex-wrap gap-2">
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        className="min-h-11 flex-1"
                                                        aria-label={`Editar datos de ${usuario.name}`}
                                                        onClick={() =>
                                                            setEditando(usuario)
                                                        }
                                                    >
                                                        Editar datos
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        className="min-h-11 flex-1"
                                                        disabled={
                                                            propia &&
                                                            usuario.activo
                                                        }
                                                        aria-label={`${usuario.activo ? 'Desactivar' : 'Activar'} cuenta de ${usuario.name}`}
                                                        onClick={() =>
                                                            preparar({
                                                                tipo: 'estado',
                                                                usuario,
                                                                anterior:
                                                                    usuario.activo
                                                                        ? 'Activa'
                                                                        : 'Desactivada',
                                                                nuevo: usuario.activo
                                                                    ? 'Desactivada'
                                                                    : 'Activa',
                                                                valor: !usuario.activo,
                                                            })
                                                        }
                                                    >
                                                        {usuario.activo
                                                            ? 'Desactivar'
                                                            : 'Activar'}
                                                    </Button>
                                                </div>
                                                {propia && (
                                                    <p className="text-muted-foreground text-xs">
                                                        No puedes desactivar tu
                                                        propia cuenta.
                                                    </p>
                                                )}
                                            </li>
                                        );
                                    })}
                                </ul>
                            )}
                        </div>
                    ) : (
                        <div className="space-y-4">
                            {Object.entries(matrizPermisos).map(
                                ([role, permisos]) => (
                                    <section
                                        key={role}
                                        className="border-border bg-card rounded-xl border p-4"
                                    >
                                        <h2 className="text-foreground font-semibold">
                                            {roleLabel(role)}
                                        </h2>
                                        <ul className="mt-3 flex flex-wrap gap-2">
                                            {permisos.map((permiso) => (
                                                <li
                                                    key={permiso}
                                                    title={permiso}
                                                    className="bg-muted text-foreground rounded-full px-2 py-1 text-xs"
                                                >
                                                    {humanizePermission(
                                                        permiso,
                                                    )}
                                                </li>
                                            ))}
                                        </ul>
                                    </section>
                                ),
                            )}
                        </div>
                    )}
                </div>
            </div>
            <Dialog
                open={cambio !== null}
                onOpenChange={(open) => {
                    if (!open && !guardando) {
                        setCambio(null);
                    }
                }}
            >
                <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            Confirmar cambio de{' '}
                            {cambio?.tipo === 'rol'
                                ? 'rol'
                                : cambio?.tipo === 'sede'
                                  ? 'sede'
                                  : 'estado'}
                        </DialogTitle>
                        <DialogDescription>
                            Revisa el cambio para {cambio?.usuario.name} antes
                            de guardarlo.
                        </DialogDescription>
                    </DialogHeader>
                    {cambio && (
                        <>
                            <dl className="min-w-0 space-y-3 text-sm">
                                <div>
                                    <dt className="text-muted-foreground">
                                        Trabajador
                                    </dt>
                                    <dd className="font-semibold wrap-anywhere">
                                        {cambio.usuario.name}
                                    </dd>
                                    <dd className="text-muted-foreground wrap-anywhere">
                                        {cambio.usuario.email}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Valor anterior
                                    </dt>
                                    <dd className="font-semibold wrap-anywhere">
                                        {cambio.anterior}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Valor nuevo
                                    </dt>
                                    <dd className="font-semibold wrap-anywhere">
                                        {cambio.nuevo}
                                    </dd>
                                </div>
                            </dl>
                            {cambio.usuario.id === auth.user.id && (
                                <p className="bg-muted text-foreground rounded-lg p-3 text-sm">
                                    Esta es tu propia cuenta. El cambio afectará
                                    tu acceso. No puedes quitarte el rol de
                                    Gerente ni desactivarte.
                                </p>
                            )}
                            {cambio.tipo === 'estado' && !cambio.valor && (
                                <p className="text-muted-foreground text-sm">
                                    No podrá iniciar sesión y sus sesiones
                                    abiertas dejarán de permitirle trabajar. Su
                                    historial se conserva.
                                </p>
                            )}
                        </>
                    )}
                    {error && (
                        <p
                            role="alert"
                            className="text-destructive-strong text-sm"
                        >
                            {error}
                        </p>
                    )}
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            className="min-h-11"
                            disabled={guardando}
                            onClick={() => setCambio(null)}
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="button"
                            className="min-h-11"
                            disabled={guardando}
                            onClick={confirmar}
                        >
                            {guardando ? 'Guardando…' : 'Confirmar cambio'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
            {editando && (
                <EditarUsuarioDialog
                    key={editando.id}
                    usuario={editando}
                    teamSlug={currentTeam.slug}
                    propiaCuenta={editando.id === auth.user.id}
                    onCerrar={() => setEditando(null)}
                />
            )}
        </GerenteLayout>
    );
}
