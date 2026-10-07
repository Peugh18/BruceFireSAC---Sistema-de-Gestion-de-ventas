import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, FileText, Plus } from 'lucide-react';
import { useState } from 'react';

import ChecklistExtintor from '@/components/checklist-extintor';
import ConversacionOrden, {
    type ConversacionProps,
} from '@/components/conversacion-orden';
import FirmaCanvas from '@/components/firma-canvas';
import SubirEvidencia, {
    type EvidenciaListada,
} from '@/components/subir-evidencia';
import TomarOrden, { type AsignacionOrden } from '@/components/tomar-orden';
import TecnicoCampoLayout from '@/layouts/tecnico-campo-layout';
import mantenimientos from '@/routes/tecnico-campo/mantenimientos';
import checklist from '@/routes/tecnico-campo/mantenimientos/checklist';
import equipos from '@/routes/tecnico-campo/mantenimientos/equipos';
import type { Team } from '@/types';

type Equipo = {
    id: number;
    numero_serie: string;
    tipo_agente: string | null;
    capacidad: string | null;
    checklists?: { resultado_general: string }[];
};

type Props = {
    asignacion: AsignacionOrden;
    order: {
        id: number;
        codigo: string;
        estado: string;
        client: {
            razon_social: string;
            telefono?: string | null;
            direccion_fiscal?: string | null;
        };
        equipments: Equipo[];
        certificates?: { id: number; numero: string }[];
    };
    elementosChecklist: Record<string, string>;
    evidencias: EvidenciaListada[];
    conversacion: ConversacionProps;
    finalizado: boolean;
};

export default function MantenimientoShow({
    asignacion,
    order,
    elementosChecklist,
    evidencias,
    conversacion,
    finalizado,
}: Props) {
    const { currentTeam, flash } = usePage<{
        currentTeam?: Team | null;
        flash?: { success?: string };
    }>().props;
    const equipo = currentTeam?.slug ?? '';
    const [revisando, setRevisando] = useState<number | null>(null);
    const [codigo, setCodigo] = useState('');

    const agregar = useForm({ numero_serie: '' });
    const cierre = useForm({
        conformidad_nombre: '',
        conformidad_aceptada: false,
        observaciones_generales: '',
        firma: null as string | null,
    });

    const args = { current_team: equipo, service_order: order.id };
    const revisados = order.equipments.filter(
        (e) => (e.checklists?.length ?? 0) > 0,
    ).length;
    const errores = cierre.errors as Record<string, string | undefined>;

    const escanear = (e: React.FormEvent) => {
        e.preventDefault();
        agregar.transform(() => ({ numero_serie: codigo.trim() }));
        agregar.post(equipos.store.url(args), {
            preserveScroll: true,
            onSuccess: () => setCodigo(''),
        });
    };

    const cerrar = (e: React.FormEvent) => {
        e.preventDefault();
        cierre.post(mantenimientos.complete.url(args), {
            preserveScroll: true,
        });
    };

    return (
        <TecnicoCampoLayout title={`Mantenimiento ${order.codigo}`}>
            <Head title={`Mantenimiento ${order.codigo} - Técnico de Campo`} />

            <div className="mb-4 flex items-center justify-between">
                <Link
                    href={mantenimientos.index.url(equipo)}
                    className="text-muted-foreground hover:text-foreground inline-flex min-h-[44px] items-center gap-1.5 text-xs font-bold"
                >
                    <ArrowLeft className="size-4" />
                    Volver a mantenimientos
                </Link>
                <span className="font-mono text-xs font-black text-sky-700 dark:text-sky-400">
                    {order.codigo}
                </span>
            </div>

            {flash?.success && (
                <div
                    className="mb-4 rounded-[10px] border border-emerald-500/30 bg-emerald-500/10 p-3 text-xs font-medium"
                    role="status"
                >
                    {flash.success}
                </div>
            )}

            <TomarOrden asignacion={asignacion} />

            <div className="space-y-5">
                <section className="border-border bg-card rounded-[14px] border p-4 shadow-xs">
                    <h1 className="text-foreground text-base font-black">
                        {order.client.razon_social}
                    </h1>
                    {order.client.direccion_fiscal && (
                        <p className="text-muted-foreground text-xs">
                            {order.client.direccion_fiscal}
                        </p>
                    )}
                </section>

                <section className="border-border bg-card space-y-3 rounded-[14px] border p-4 shadow-xs">
                    <h2 className="text-xs font-black tracking-wider uppercase">
                        1. Extintores revisados ({revisados}/
                        {order.equipments.length})
                    </h2>

                    {!finalizado && (
                        <form onSubmit={escanear} className="flex gap-2">
                            <label
                                htmlFor="codigo-extintor"
                                className="sr-only"
                            >
                                Código del extintor
                            </label>
                            <input
                                id="codigo-extintor"
                                required
                                value={codigo}
                                onChange={(e) => setCodigo(e.target.value)}
                                placeholder="Escanea o escribe BF-EQ-..."
                                className="border-border bg-card min-h-[44px] flex-1 rounded-[8px] border px-2 font-mono text-xs"
                            />
                            <button
                                type="submit"
                                disabled={agregar.processing}
                                className="inline-flex min-h-[44px] items-center gap-1 rounded-[8px] bg-sky-600 px-4 text-xs font-bold text-white disabled:opacity-50"
                            >
                                <Plus className="size-4" />
                                Agregar
                            </button>
                        </form>
                    )}
                    {(agregar.errors.numero_serie ||
                        errores.equipment_id ||
                        errores.estado) && (
                        <p
                            className="text-[11px] font-semibold text-red-600"
                            role="alert"
                        >
                            {agregar.errors.numero_serie ??
                                errores.equipment_id ??
                                errores.estado}
                        </p>
                    )}

                    <ul className="space-y-2">
                        {order.equipments.map((eq) => {
                            const resultado =
                                eq.checklists?.[0]?.resultado_general;

                            return (
                                <li
                                    key={eq.id}
                                    className="border-border rounded-[10px] border p-2.5"
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <div>
                                            <p className="font-mono text-xs font-black">
                                                {eq.numero_serie}
                                            </p>
                                            <p className="text-muted-foreground text-[11px]">
                                                {eq.tipo_agente} {eq.capacidad}
                                                {resultado &&
                                                    ` · ${resultado === 'conforme' ? 'Conforme' : 'Con observaciones'}`}
                                            </p>
                                        </div>
                                        {!finalizado && revisando !== eq.id && (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setRevisando(eq.id)
                                                }
                                                className="min-h-[44px] rounded-[8px] border border-sky-600 px-3 text-xs font-bold text-sky-800 dark:text-sky-300"
                                            >
                                                {resultado
                                                    ? 'Revisar de nuevo'
                                                    : 'Revisar'}
                                            </button>
                                        )}
                                    </div>
                                    {revisando === eq.id && (
                                        <ChecklistExtintor
                                            elementos={elementosChecklist}
                                            url={checklist.store.url({
                                                ...args,
                                                equipment: eq.id,
                                            })}
                                            serie={eq.numero_serie}
                                            esCo2={(eq.tipo_agente ?? '')
                                                .toUpperCase()
                                                .includes('CO2')}
                                            onCerrar={() => setRevisando(null)}
                                        />
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                </section>

                <section className="border-border bg-card space-y-3 rounded-[14px] border p-4 shadow-xs">
                    <h2 className="text-xs font-black tracking-wider uppercase">
                        2. Fotos del trabajo
                    </h2>
                    <SubirEvidencia
                        ordenId={order.id}
                        etapa="antes"
                        titulo="Fotos antes"
                        evidencias={evidencias}
                        equipos={conversacion.equipos}
                    />
                    <SubirEvidencia
                        ordenId={order.id}
                        etapa="despues"
                        titulo="Fotos después"
                        evidencias={evidencias}
                        equipos={conversacion.equipos}
                    />
                </section>

                {finalizado ? (
                    <section className="space-y-2 rounded-[14px] border border-emerald-500/30 bg-emerald-500/10 p-4">
                        <p className="flex items-center gap-2 text-xs font-black">
                            <CheckCircle2 className="size-4 text-emerald-700" />
                            Mantenimiento finalizado.
                        </p>
                        <a
                            href={mantenimientos.pdf.url(args)}
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex min-h-[44px] items-center gap-1.5 text-xs font-bold underline"
                        >
                            <FileText className="size-4" />
                            Ver acta en PDF
                        </a>
                    </section>
                ) : (
                    <form
                        onSubmit={cerrar}
                        className="bg-card space-y-3 rounded-[14px] border border-sky-500/30 p-4 shadow-sm"
                    >
                        <h2 className="text-xs font-black tracking-wider uppercase">
                            3. Conformidad y firma del cliente
                        </h2>
                        <label className="block text-[11px] font-bold">
                            Nombre de quien recibe *
                            <input
                                required
                                value={cierre.data.conformidad_nombre}
                                onChange={(e) =>
                                    cierre.setData(
                                        'conformidad_nombre',
                                        e.target.value,
                                    )
                                }
                                className="border-border bg-card mt-1 min-h-[44px] w-full rounded-[8px] border px-2 text-xs"
                            />
                        </label>
                        <label className="block text-[11px] font-bold">
                            Observaciones
                            <textarea
                                rows={2}
                                value={cierre.data.observaciones_generales}
                                onChange={(e) =>
                                    cierre.setData(
                                        'observaciones_generales',
                                        e.target.value,
                                    )
                                }
                                className="border-border bg-card mt-1 w-full rounded-[8px] border p-2 text-xs"
                            />
                        </label>
                        <FirmaCanvas
                            onChange={(f) => cierre.setData('firma', f)}
                        />
                        <label className="flex min-h-[44px] items-start gap-2.5 text-xs font-semibold">
                            <input
                                type="checkbox"
                                checked={cierre.data.conformidad_aceptada}
                                onChange={(e) =>
                                    cierre.setData(
                                        'conformidad_aceptada',
                                        e.target.checked,
                                    )
                                }
                                className="mt-0.5 size-4"
                            />
                            El cliente está conforme con el mantenimiento y los
                            extintores revisados.
                        </label>
                        {(errores.checklist ||
                            errores.fotos ||
                            errores.firma ||
                            errores.conformidad_nombre ||
                            errores.estado) && (
                            <p
                                className="text-[11px] font-semibold text-red-600"
                                role="alert"
                            >
                                {errores.checklist ??
                                    errores.fotos ??
                                    errores.firma ??
                                    errores.conformidad_nombre ??
                                    errores.estado}
                            </p>
                        )}
                        <button
                            type="submit"
                            disabled={
                                cierre.processing ||
                                !cierre.data.firma ||
                                !cierre.data.conformidad_aceptada
                            }
                            className="min-h-[48px] w-full rounded-[10px] bg-sky-600 text-xs font-bold text-white disabled:opacity-50"
                        >
                            Finalizar mantenimiento y generar acta
                        </button>
                    </form>
                )}

                <ConversacionOrden
                    ordenId={order.id}
                    conversacion={conversacion}
                />
            </div>
        </TecnicoCampoLayout>
    );
}
