import { router, useForm, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    Edit2,
    MapPin,
    Plus,
    Power,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

import UbigeoPicker, { type UbigeoOption } from '@/components/ubigeo-picker';
import GerenteLayout from '@/layouts/gerente-layout';
import SedesRoutes from '@/routes/gerente/sedes';

type SedeTipo = 'tienda' | 'almacen' | 'mixta';

type SedeItem = {
    id: number;
    nombre: string;
    tipo: SedeTipo;
    ciudad: string | null;
    ubigeo: string | null;
    direccion: string | null;
    cod_establecimiento_anexo: string;
    ubicacion: UbigeoOption | null;
    almacen_id: number | null;
    almacen_nombre: string | null;
    usuarios_count: number;
    tiendas: string[];
    trabajadores: { nombre: string; rol: string | null }[];
    activo: boolean;
};

const ROL_CORTO: Record<string, string> = {
    Vendedor: 'Vendedor',
    Almacen: 'Almacén',
    TecnicoPlanta: 'Téc. planta',
    TecnicoCampo: 'Téc. campo',
    Gerente: 'Gerente',
};

type AlmacenOption = { id: number; nombre: string };

type PageProps = {
    currentTeam: { slug: string };
    sedes: SedeItem[];
    almacenes: AlmacenOption[];
    kpis: { total: number; activas: number };
    flash?: { success?: string; error?: string };
    [key: string]: unknown;
};

const TIPO_LABELS: Record<SedeTipo, string> = {
    tienda: 'Tienda',
    almacen: 'Almacén',
    mixta: 'Mixta',
};

const TIPO_DESCRIPCION: Record<SedeTipo, string> = {
    tienda: 'Vende y saca stock de un almacén.',
    almacen: 'Guarda stock y abastece a las tiendas.',
    mixta: 'Vende y guarda su propio stock.',
};

type SedeFormData = {
    nombre: string;
    tipo: SedeTipo;
    ubigeo: string;
    direccion: string;
    cod_establecimiento_anexo: string;
    almacen_id: string;
    activo: boolean;
};

const EMPTY_FORM: SedeFormData = {
    nombre: '',
    tipo: 'mixta',
    ubigeo: '',
    direccion: '',
    cod_establecimiento_anexo: '0000',
    almacen_id: '',
    activo: true,
};

export default function SedesIndex() {
    const { currentTeam, sedes, almacenes, kpis, flash } =
        usePage<PageProps>().props;

    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<SedeItem | null>(null);
    const [ubicacion, setUbicacion] = useState<UbigeoOption | null>(null);
    const form = useForm<SedeFormData>(EMPTY_FORM);

    const almacenesElegibles = almacenes.filter((a) => a.id !== editing?.id);

    const openCreate = () => {
        setEditing(null);
        setUbicacion(null);
        form.clearErrors();
        form.setData(EMPTY_FORM);
        setModalOpen(true);
    };

    const openEdit = (sede: SedeItem) => {
        setEditing(sede);
        setUbicacion(sede.ubicacion);
        form.clearErrors();
        form.setData({
            nombre: sede.nombre,
            tipo: sede.tipo,
            ubigeo: sede.ubigeo ?? '',
            direccion: sede.direccion ?? '',
            cod_establecimiento_anexo: sede.cod_establecimiento_anexo,
            almacen_id: sede.almacen_id ? String(sede.almacen_id) : '',
            activo: sede.activo,
        });
        setModalOpen(true);
    };

    const closeModal = () => {
        setModalOpen(false);
        setEditing(null);
        form.reset();
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const options = { onSuccess: () => closeModal() };

        if (editing) {
            form.put(
                SedesRoutes.update.url({
                    current_team: currentTeam.slug,
                    sede: editing.id,
                }),
                {
                    ...options,
                    onError: (errors) =>
                        toast.error(
                            Object.values(errors)[0] ??
                                'No se pudo completar la acción.',
                        ),
                },
            );
        } else {
            form.post(
                SedesRoutes.store.url({ current_team: currentTeam.slug }),
                {
                    ...options,
                    onError: (errors) =>
                        toast.error(
                            Object.values(errors)[0] ??
                                'No se pudo completar la acción.',
                        ),
                },
            );
        }
    };

    const handleToggleStatus = (sede: SedeItem) => {
        router.patch(
            SedesRoutes.toggleStatus.url({
                current_team: currentTeam.slug,
                sede: sede.id,
            }),
            {},
            {
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ??
                            'No se pudo completar la acción.',
                    ),
                preserveScroll: true,
            },
        );
    };

    return (
        <GerenteLayout title="Sedes">
            <div className="space-y-6">
                {flash?.success && (
                    <div className="flex items-center gap-2.5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        <CheckCircle2 className="text-success-strong size-4 shrink-0" />
                        <span>{flash.success}</span>
                    </div>
                )}
                {flash?.error && (
                    <div className="flex items-center gap-2.5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        <AlertTriangle className="text-primary-strong size-4 shrink-0" />
                        <span>{flash.error}</span>
                    </div>
                )}

                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div>
                        <h1 className="text-foreground font-['Oswald',sans-serif] text-2xl font-bold tracking-wide uppercase">
                            Sedes
                        </h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Tiendas, almacenes y sedes mixtas de la empresa.
                            Cada tienda indica de qué almacén saca su stock.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={openCreate}
                        className="bg-primary text-primary-foreground hover:bg-primary/90 flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-xs font-bold transition-colors"
                    >
                        <Plus className="size-4" />
                        Nueva Sede
                    </button>
                </div>

                <div className="grid grid-cols-2 gap-4 md:max-w-md">
                    <div className="border-border bg-card rounded-xl border p-4 shadow-xs">
                        <p className="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">
                            Total de sedes
                        </p>
                        <p className="text-foreground mt-1 text-2xl font-bold">
                            {kpis.total}
                        </p>
                    </div>
                    <div className="border-border bg-card rounded-xl border p-4 shadow-xs">
                        <p className="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">
                            Activas
                        </p>
                        <p className="text-foreground mt-1 text-2xl font-bold">
                            {kpis.activas}
                        </p>
                    </div>
                </div>

                <div className="border-border bg-card overflow-hidden rounded-xl border shadow-xs">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="border-border bg-muted/40 text-muted-foreground border-b text-[11px] font-semibold tracking-wider uppercase">
                                <tr>
                                    <th className="px-4 py-3">Sede</th>
                                    <th className="px-4 py-3">Tipo</th>
                                    <th className="px-4 py-3">Ciudad</th>
                                    <th className="px-4 py-3">
                                        Almacén de stock
                                    </th>
                                    <th className="px-4 py-3">Trabajadores</th>
                                    <th className="px-4 py-3">Estado</th>
                                    <th className="px-4 py-3 text-right">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-border divide-y">
                                {sedes.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="text-muted-foreground py-8 text-center"
                                        >
                                            Todavía no hay sedes registradas.
                                        </td>
                                    </tr>
                                ) : (
                                    sedes.map((sede) => (
                                        <tr
                                            key={sede.id}
                                            className="hover:bg-muted/40 transition-colors"
                                        >
                                            <td className="text-foreground px-4 py-3 font-semibold">
                                                <span className="flex items-center gap-1.5">
                                                    <MapPin className="text-primary-strong size-3.5" />
                                                    {sede.nombre}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="inline-flex rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-bold text-zinc-700 uppercase">
                                                    {TIPO_LABELS[sede.tipo]}
                                                </span>
                                            </td>
                                            <td className="text-muted-foreground px-4 py-3">
                                                {sede.ciudad ?? '—'}
                                            </td>
                                            <td className="text-muted-foreground px-4 py-3">
                                                {sede.tipo === 'tienda' ? (
                                                    <span>
                                                        Del almacén{' '}
                                                        <b className="text-foreground">
                                                            {sede.almacen_nombre ??
                                                                '—'}
                                                        </b>
                                                    </span>
                                                ) : (
                                                    <span>
                                                        Propio
                                                        {sede.tiendas.length >
                                                        0 ? (
                                                            <span className="block text-[11px]">
                                                                Abastece y
                                                                atiende a:{' '}
                                                                {sede.tiendas.join(
                                                                    ', ',
                                                                )}
                                                            </span>
                                                        ) : null}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="text-muted-foreground px-4 py-3">
                                                {sede.trabajadores.length ===
                                                0 ? (
                                                    <span>Nadie asignado</span>
                                                ) : (
                                                    <ul className="space-y-0.5 text-[11.5px]">
                                                        {sede.trabajadores.map(
                                                            (trabajador) => (
                                                                <li
                                                                    key={
                                                                        trabajador.nombre
                                                                    }
                                                                >
                                                                    <span className="text-foreground">
                                                                        {
                                                                            trabajador.nombre
                                                                        }
                                                                    </span>{' '}
                                                                    ·{' '}
                                                                    {ROL_CORTO[
                                                                        trabajador.rol ??
                                                                            ''
                                                                    ] ??
                                                                        'Sin rol'}
                                                                </li>
                                                            ),
                                                        )}
                                                    </ul>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <span
                                                    className={`inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold uppercase ${
                                                        sede.activo
                                                            ? 'bg-emerald-100 text-emerald-800'
                                                            : 'bg-zinc-200 text-zinc-600'
                                                    }`}
                                                >
                                                    {sede.activo
                                                        ? 'Activa'
                                                        : 'Inactiva'}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex justify-end gap-1.5">
                                                    <button
                                                        aria-label="Editar sede"
                                                        type="button"
                                                        onClick={() =>
                                                            openEdit(sede)
                                                        }
                                                        title="Editar"
                                                        className="text-muted-foreground hover:bg-muted rounded-md p-1.5"
                                                    >
                                                        <Edit2 className="size-3.5" />
                                                    </button>
                                                    <button
                                                        aria-label={
                                                            sede.activo
                                                                ? 'Desactivar sede'
                                                                : 'Activar sede'
                                                        }
                                                        type="button"
                                                        onClick={() =>
                                                            handleToggleStatus(
                                                                sede,
                                                            )
                                                        }
                                                        title={
                                                            sede.activo
                                                                ? 'Desactivar'
                                                                : 'Activar'
                                                        }
                                                        className="text-muted-foreground hover:bg-muted rounded-md p-1.5"
                                                    >
                                                        <Power className="size-3.5" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {modalOpen && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                        <div className="border-border bg-card w-full max-w-lg rounded-xl border p-6 shadow-xl">
                            <div className="border-border flex items-center justify-between border-b pb-3">
                                <h3 className="text-foreground font-['Oswald',sans-serif] text-lg font-bold uppercase">
                                    {editing ? 'Editar Sede' : 'Nueva Sede'}
                                </h3>
                                <button
                                    type="button"
                                    onClick={closeModal}
                                    aria-label="Cerrar"
                                    className="text-muted-foreground hover:bg-background rounded-md p-1"
                                >
                                    <X className="size-4" />
                                </button>
                            </div>

                            <form
                                onSubmit={handleSubmit}
                                className="mt-4 space-y-4 text-xs"
                            >
                                <div>
                                    <label className="text-foreground/80 block font-semibold">
                                        Nombre *
                                    </label>
                                    <input
                                        aria-label="Nombre *"
                                        type="text"
                                        required
                                        value={form.data.nombre}
                                        onChange={(e) =>
                                            form.setData(
                                                'nombre',
                                                e.target.value,
                                            )
                                        }
                                        className="border-border focus:border-primary mt-1 w-full rounded-lg border px-3 py-2 focus:outline-none"
                                    />
                                    {form.errors.nombre && (
                                        <p className="mt-1 text-red-600">
                                            {form.errors.nombre}
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <label className="text-foreground/80 block font-semibold">
                                        Tipo *
                                    </label>
                                    <select
                                        aria-label="Tipo *"
                                        value={form.data.tipo}
                                        onChange={(e) =>
                                            form.setData(
                                                'tipo',
                                                e.target.value as SedeTipo,
                                            )
                                        }
                                        className="border-border focus:border-primary mt-1 w-full rounded-lg border px-3 py-2 focus:outline-none"
                                    >
                                        {(
                                            Object.keys(
                                                TIPO_LABELS,
                                            ) as SedeTipo[]
                                        ).map((tipo) => (
                                            <option key={tipo} value={tipo}>
                                                {TIPO_LABELS[tipo]}
                                            </option>
                                        ))}
                                    </select>
                                    <p className="text-muted-foreground mt-1">
                                        {TIPO_DESCRIPCION[form.data.tipo]}
                                    </p>
                                    {form.errors.tipo && (
                                        <p className="mt-1 text-red-600">
                                            {form.errors.tipo}
                                        </p>
                                    )}
                                </div>

                                {form.data.tipo === 'tienda' && (
                                    <div>
                                        <label className="text-foreground/80 block font-semibold">
                                            Almacén del que saca stock *
                                        </label>
                                        <select
                                            aria-label="Almacén del que saca stock *"
                                            required
                                            value={form.data.almacen_id}
                                            onChange={(e) =>
                                                form.setData(
                                                    'almacen_id',
                                                    e.target.value,
                                                )
                                            }
                                            className="border-border focus:border-primary mt-1 w-full rounded-lg border px-3 py-2 focus:outline-none"
                                        >
                                            <option value="" disabled>
                                                Seleccionar almacén
                                            </option>
                                            {almacenesElegibles.map((a) => (
                                                <option key={a.id} value={a.id}>
                                                    {a.nombre}
                                                </option>
                                            ))}
                                        </select>
                                        {form.errors.almacen_id && (
                                            <p className="mt-1 text-red-600">
                                                {form.errors.almacen_id}
                                            </p>
                                        )}
                                    </div>
                                )}

                                <div>
                                    <label className="text-foreground/80 block font-semibold">
                                        Ubicación (distrito)
                                    </label>
                                    <UbigeoPicker
                                        value={ubicacion}
                                        onChange={(ubigeo) => {
                                            setUbicacion(ubigeo);
                                            form.setData(
                                                'ubigeo',
                                                ubigeo?.codigo ?? '',
                                            );
                                        }}
                                        error={form.errors.ubigeo}
                                    />
                                </div>

                                <div>
                                    <label
                                        htmlFor="sede-direccion"
                                        className="text-foreground/80 block font-semibold"
                                    >
                                        Dirección (para las guías de remisión)
                                    </label>
                                    <input
                                        id="sede-direccion"
                                        type="text"
                                        maxLength={255}
                                        value={form.data.direccion}
                                        onChange={(e) =>
                                            form.setData(
                                                'direccion',
                                                e.target.value,
                                            )
                                        }
                                        className="border-border focus:border-primary mt-1 w-full rounded-lg border px-3 py-2 focus:outline-none"
                                    />
                                    {form.errors.direccion && (
                                        <p className="mt-1 text-red-600">
                                            {form.errors.direccion}
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <label
                                        htmlFor="sede-anexo"
                                        className="text-foreground/80 block font-semibold"
                                    >
                                        Código de establecimiento anexo SUNAT
                                    </label>
                                    <input
                                        id="sede-anexo"
                                        type="text"
                                        inputMode="numeric"
                                        maxLength={4}
                                        value={
                                            form.data.cod_establecimiento_anexo
                                        }
                                        onChange={(e) =>
                                            form.setData(
                                                'cod_establecimiento_anexo',
                                                e.target.value,
                                            )
                                        }
                                        className="border-border focus:border-primary mt-1 w-full rounded-lg border px-3 py-2 focus:outline-none"
                                    />
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        0000 es la sede principal.
                                    </p>
                                    {form.errors.cod_establecimiento_anexo && (
                                        <p className="mt-1 text-red-600">
                                            {
                                                form.errors
                                                    .cod_establecimiento_anexo
                                            }
                                        </p>
                                    )}
                                </div>

                                <div className="border-border flex justify-end gap-2 border-t pt-4">
                                    <button
                                        type="button"
                                        onClick={closeModal}
                                        className="border-border text-foreground/80 hover:bg-muted rounded-lg border px-4 py-2 font-semibold"
                                    >
                                        Cancelar
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={form.processing}
                                        className="bg-primary text-primary-foreground hover:bg-primary/90 rounded-lg px-4 py-2 font-bold disabled:opacity-50"
                                    >
                                        {editing
                                            ? 'Guardar cambios'
                                            : 'Crear sede'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}
            </div>
        </GerenteLayout>
    );
}
