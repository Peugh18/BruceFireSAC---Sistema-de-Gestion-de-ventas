import { router, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, Check, CheckCircle2, X } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import GerenteLayout from '@/layouts/gerente-layout';
import notas from '@/routes/gerente/notas';

type Solicitud = {
    id: number;
    tipo: 'nota_credito' | 'nota_debito';
    motivo: string;
    detalle: string;
    importe: number;
    comprobante: string;
    total_comprobante: number;
    cliente: string;
    solicitante: string;
    fecha: string | null;
};

type PageProps = {
    currentTeam: { slug: string };
    solicitudes: Solicitud[];
    flash?: { success?: string; error?: string };
    errors: Record<string, string>;
    [key: string]: unknown;
};

const TIPO: Record<Solicitud['tipo'], string> = {
    nota_credito: 'Nota de crédito',
    nota_debito: 'Nota de débito',
};

function soles(monto: number) {
    return new Intl.NumberFormat('es-PE', {
        style: 'currency',
        currency: 'PEN',
    }).format(monto);
}

function FilaSolicitud({
    solicitud,
    teamSlug,
}: {
    solicitud: Solicitud;
    teamSlug: string;
}) {
    const [rechazando, setRechazando] = useState(false);
    const [aprobando, setAprobando] = useState(false);
    const form = useForm({ motivo_rechazo: '' });
    const args = { current_team: teamSlug, note_request: solicitud.id };

    const aprobar = () => {
        setAprobando(true);
        router.post(
            notas.aprobar.url(args),
            {},
            {
                preserveScroll: true,
                onFinish: () => setAprobando(false),
            },
        );
    };

    const rechazar = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(notas.rechazar.url(args), { preserveScroll: true });
    };

    return (
        <li className="border-border bg-card rounded-xl border p-4">
            <div className="flex flex-wrap items-start gap-3">
                <div className="min-w-0 flex-1">
                    <p className="text-foreground text-sm font-semibold">
                        {TIPO[solicitud.tipo]} de {soles(solicitud.importe)}{' '}
                        sobre {solicitud.comprobante}
                    </p>
                    <p className="text-muted-foreground mt-0.5 text-xs">
                        {solicitud.cliente} · comprobante por{' '}
                        {soles(solicitud.total_comprobante)}
                    </p>
                    <p className="mt-2 text-xs">
                        <span className="font-semibold">Motivo:</span>{' '}
                        {solicitud.motivo}
                    </p>
                    <p className="text-xs">
                        <span className="font-semibold">Detalle:</span>{' '}
                        {solicitud.detalle}
                    </p>
                    <p className="text-muted-foreground mt-1 text-[11px]">
                        Pedida por {solicitud.solicitante}
                        {solicitud.fecha ? ` el ${solicitud.fecha}` : ''}
                    </p>
                </div>
                <div className="flex gap-2">
                    <Button
                        type="button"
                        onClick={aprobar}
                        disabled={aprobando}
                        className="h-9"
                    >
                        <Check className="size-4" />
                        {aprobando ? 'Emitiendo…' : 'Aprobar y emitir'}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setRechazando((v) => !v)}
                        className="h-9"
                    >
                        <X className="size-4" />
                        Rechazar
                    </Button>
                </div>
            </div>

            {rechazando && (
                <form onSubmit={rechazar} className="mt-3 space-y-2">
                    <label
                        htmlFor={`motivo-rechazo-${solicitud.id}`}
                        className="block text-xs font-semibold"
                    >
                        ¿Por qué la rechazas? El vendedor lo verá.
                    </label>
                    <textarea
                        id={`motivo-rechazo-${solicitud.id}`}
                        required
                        rows={2}
                        maxLength={500}
                        value={form.data.motivo_rechazo}
                        onChange={(e) =>
                            form.setData('motivo_rechazo', e.target.value)
                        }
                        className="border-border bg-background w-full rounded-lg border px-3 py-2 text-sm"
                    />
                    {form.errors.motivo_rechazo && (
                        <p className="text-destructive text-xs">
                            {form.errors.motivo_rechazo}
                        </p>
                    )}
                    <Button
                        type="submit"
                        variant="destructive"
                        disabled={form.processing}
                        className="h-9"
                    >
                        Confirmar rechazo
                    </Button>
                </form>
            )}
        </li>
    );
}

export default function NotasPorAprobar() {
    const { currentTeam, solicitudes, flash, errors } =
        usePage<PageProps>().props;
    const errorGeneral = flash?.error ?? errors.solicitud;

    return (
        <GerenteLayout title="Notas por aprobar">
            <div className="space-y-5">
                <div>
                    <h1 className="text-foreground font-['Oswald',sans-serif] text-2xl font-bold tracking-wide uppercase">
                        Notas por aprobar
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Notas de crédito y débito que pidieron los vendedores.
                        Al aprobarlas se emiten y se envían a SUNAT.
                    </p>
                </div>

                {flash?.success && (
                    <div
                        role="status"
                        className="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-xs font-medium text-emerald-800"
                    >
                        <CheckCircle2 className="size-4" />
                        {flash.success}
                    </div>
                )}
                {errorGeneral && (
                    <div
                        role="alert"
                        className="flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-xs font-medium text-amber-800"
                    >
                        <AlertTriangle className="size-4" />
                        {errorGeneral}
                    </div>
                )}
                {Object.entries(errors)
                    .filter(
                        ([campo]) =>
                            !['solicitud', 'motivo_rechazo'].includes(campo),
                    )
                    .map(([campo, mensaje]) => (
                        <p
                            key={campo}
                            role="alert"
                            className="text-destructive text-xs"
                        >
                            {mensaje}
                        </p>
                    ))}

                {solicitudes.length === 0 ? (
                    <p className="border-border text-muted-foreground rounded-xl border border-dashed p-8 text-center text-sm">
                        No hay notas esperando tu aprobación.
                    </p>
                ) : (
                    <ul className="space-y-3">
                        {solicitudes.map((solicitud) => (
                            <FilaSolicitud
                                key={solicitud.id}
                                solicitud={solicitud}
                                teamSlug={currentTeam.slug}
                            />
                        ))}
                    </ul>
                )}
            </div>
        </GerenteLayout>
    );
}
