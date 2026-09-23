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

import GerenteLayout from '@/layouts/gerente-layout';

type SedeTipo = 'tienda' | 'almacen' | 'mixta';

type SedeItem = {
    id: number;
    nombre: string;
    tipo: SedeTipo;
    ciudad: string | null;
    ubigeo: string | null;
    almacen_id: number | null;
    almacen_nombre: string | null;
    usuarios_count: number;
    activo: boolean;
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
    ciudad: string;
    ubigeo: string;
    almacen_id: string;
    activo: boolean;
};

const EMPTY_FORM: SedeFormData = {
    nombre: '',
    tipo: 'mixta',
    ciudad: '',
    ubigeo: '',
    almacen_id: '',
    activo: true,
};

export default function SedesIndex() {
    const { currentTeam, sedes, almacenes, kpis, flash } =
        usePage<PageProps>().props;

    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<SedeItem | null>(null);
    const form = useForm<SedeFormData>(EMPTY_FORM);

    const almacenesElegibles = almacenes.filter((a) => a.id !== editing?.id);

    const openCreate = () => {
        setEditing(null);
        form.clearErrors();
        form.setData(EMPTY_FORM);
        setModalOpen(true);
    };

    const openEdit = (sede: SedeItem) => {
        setEditing(sede);
        form.clearErrors();
        form.setData({
            nombre: sede.nombre,
            tipo: sede.tipo,
            ciudad: sede.ciudad ?? '',
            ubigeo: sede.ubigeo ?? '',
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
                `/${currentTeam.slug}/gerente/sedes/${editing.id}`,
                options,
            );
        } else {
            form.post(`/${currentTeam.slug}/gerente/sedes`, options);
        }
    };

    const handleToggleStatus = (sede: SedeItem) => {
        router.patch(
            `/${currentTeam.slug}/gerente/sedes/${sede.id}/toggle-status`,
            {},
            { preserveScroll: true },
        );
    };

    return (
        <GerenteLayout title="Sedes">
            <div className="space-y-6">
                {flash?.success && (
                    <div className="flex items-center gap-2.5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        <CheckCircle2 className="size-4 shrink-0 text-emerald-600" />
                        <span>{flash.success}</span>
                    </div>
                )}
                {flash?.error && (
                    <div className="flex items-center gap-2.5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        <AlertTriangle className="text-primary size-4 shrink-0" />
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
                                                    <MapPin className="text-primary size-3.5" />
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
                                                {sede.tipo === 'tienda'
                                                    ? (sede.almacen_nombre ??
                                                      '—')
                                                    : 'Propio'}
                                            </td>
                                            <td className="text-muted-foreground px-4 py-3">
                                                {sede.usuarios_count}
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
                                </div>

                                {form.data.tipo === 'tienda' && (
                                    <div>
                                        <label className="text-foreground/80 block font-semibold">
                                            Almacén del que saca stock *
                                        </label>
                                        <select
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

                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="text-foreground/80 block font-semibold">
                                            Ciudad
                                        </label>
                                        <input
                                            type="text"
                                            value={form.data.ciudad}
                                            onChange={(e) =>
                                                form.setData(
                                                    'ciudad',
                                                    e.target.value,
                                                )
                                            }
                                            className="border-border focus:border-primary mt-1 w-full rounded-lg border px-3 py-2 focus:outline-none"
                                        />
                                    </div>
                                    <div>
                                        <label className="text-foreground/80 block font-semibold">
                                            Ubigeo
                                        </label>
                                        <input
                                            type="text"
                                            maxLength={6}
                                            value={form.data.ubigeo}
                                            onChange={(e) =>
                                                form.setData(
                                                    'ubigeo',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="150101"
                                            className="border-border focus:border-primary mt-1 w-full rounded-lg border px-3 py-2 font-mono focus:outline-none"
                                        />
                                        {form.errors.ubigeo && (
                                            <p className="mt-1 text-red-600">
                                                {form.errors.ubigeo}
                                            </p>
                                        )}
                                    </div>
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
