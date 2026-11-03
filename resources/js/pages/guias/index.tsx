import { PageHeader } from '@/components/page-header';
import { Head, Link, router, usePage } from '@inertiajs/react';
import PestanasSunat from '@/components/pestanas-sunat';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import RoleLayout from '@/layouts/role-layout';
import guias from '@/routes/guias';
import ventas from '@/routes/vendedor/ventas';
import type { Team } from '@/types';

type Guia = {
    id: number;
    detalle_url: string;
    numero: string;
    origen: string;
    comprobante_relacionado: string | null;
    fecha_traslado: string;
    motivo: string;
    destinatario: string;
    sede: string;
    estado_sunat: 'borrador' | 'enviada' | 'aceptada' | 'rechazada';
    lista_para_trasladar: boolean;
    sunat_mensaje: string | null;
};

/** Los tres pasos de una guía; la rechazada se corta en el segundo. */
function pasosDe(guia: Guia) {
    const hechos =
        guia.estado_sunat === 'aceptada'
            ? 3
            : guia.estado_sunat === 'enviada' ||
                guia.estado_sunat === 'rechazada'
              ? 2
              : 1;

    return ['Creada', 'En SUNAT', 'Aceptada'].map((nombre, indice) => ({
        nombre:
            indice === 1 && guia.estado_sunat === 'rechazada'
                ? 'Rechazada'
                : nombre,
        hecho: indice < hechos,
        fallo: indice === 1 && guia.estado_sunat === 'rechazada',
    }));
}

/** Qué le toca hacer al vendedor con la guía, en una línea. */
function siguientePaso(guia: Guia): { texto: string; tono: string } {
    if (guia.estado_sunat === 'rechazada') {
        return {
            texto: guia.sunat_mensaje
                ? `SUNAT la rechazó: ${guia.sunat_mensaje}`
                : 'SUNAT la rechazó: corrígela y vuelve a enviarla.',
            tono: 'text-destructive-strong',
        };
    }
    if (guia.estado_sunat === 'borrador') {
        return {
            texto: 'Falta enviarla. Revisa placa y conductor antes de enviar.',
            tono: 'text-warning-strong',
        };
    }
    if (guia.estado_sunat === 'enviada') {
        return {
            texto: 'SUNAT la está revisando. Consulta el CDR en unos minutos.',
            tono: 'text-warning-strong',
        };
    }

    return guia.lista_para_trasladar
        ? {
              texto: 'Lista para trasladar: imprímela y entrégala al conductor.',
              tono: 'text-success-strong',
          }
        : {
              texto: 'Aceptada sin CDR todavía: el despacho sigue bloqueado.',
              tono: 'text-destructive-strong',
          };
}

export default function GuiasIndex({
    guias: lista,
    estado = '',
    conteos = {},
}: {
    estado?: string;
    conteos?: Partial<Record<Guia['estado_sunat'], number>>;
    guias: {
        data: Guia[];
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}) {
    const { currentTeam, flash, auth } = usePage<{
        currentTeam?: Team | null;
        flash?: { success?: string; error?: string };
        auth?: { roles?: string[] };
    }>().props;
    const teamSlug = currentTeam?.slug ?? '';
    // Las guías nacen de una venta: el vendedor va directo a elegirla.
    const esVendedor = (auth?.roles ?? []).includes('Vendedor');
    const accion = (guia: Guia, tipo: 'enviar' | 'consultar') =>
        router.post(
            guias[tipo].url({ current_team: teamSlug, guide: guia.id }),
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

    return (
        <RoleLayout
            title="Guías de remisión"
            breadcrumbs={[{ title: 'Guías de remisión', href: '#' }]}
        >
            <Head title="Guías de remisión" />
            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <PageHeader
                        title="Guías de remisión"
                        description="La mercadería solo sale del local con la guía aceptada por SUNAT (con su CDR)."
                    />
                    {esVendedor ? (
                        <Button asChild>
                            <Link href={ventas.index.url(teamSlug)}>
                                Emitir guía desde una venta
                            </Link>
                        </Button>
                    ) : null}
                </div>
                <PestanasSunat actual="guias" />
                {flash?.success ? (
                    <p role="status" className="text-success-strong text-sm">
                        {flash.success}
                    </p>
                ) : null}
                {flash?.error ? (
                    <p role="alert" className="text-destructive-strong text-sm">
                        {flash.error}
                    </p>
                ) : null}
                <nav
                    aria-label="Filtrar guías por estado"
                    className="flex flex-wrap gap-2"
                >
                    {(
                        [
                            [
                                '',
                                'Todas',
                                Object.values(conteos).reduce(
                                    (a, b) => a + (b ?? 0),
                                    0,
                                ),
                            ],
                            ['rechazada', 'Rechazadas', conteos.rechazada ?? 0],
                            ['borrador', 'Sin enviar', conteos.borrador ?? 0],
                            ['enviada', 'En SUNAT', conteos.enviada ?? 0],
                            ['aceptada', 'Aceptadas', conteos.aceptada ?? 0],
                        ] as const
                    ).map(([valor, nombre, cuenta]) => (
                        <Link
                            key={nombre}
                            href={guias.index.url(teamSlug, {
                                query: valor ? { estado: valor } : {},
                            })}
                            preserveScroll
                            aria-current={estado === valor ? 'page' : undefined}
                            className={`flex min-h-10 items-center gap-1.5 rounded-full border px-3.5 text-[13px] font-semibold ${estado === valor ? 'bg-foreground text-background border-foreground' : 'border-border text-muted-foreground hover:text-foreground'}`}
                        >
                            {nombre}
                            <span className="tabular-nums">{cuenta}</span>
                        </Link>
                    ))}
                </nav>
                <Card className="divide-border divide-y p-0">
                    {lista.data.length === 0 ? (
                        <div className="flex flex-col items-start gap-3 p-6">
                            <div>
                                <p className="text-foreground text-sm font-semibold">
                                    Emite tu primera guía de remisión
                                </p>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    Abre la venta que vas a despachar y usa
                                    «Emitir guía»: los datos del cliente y los
                                    productos se llenan solos.
                                </p>
                            </div>
                            {esVendedor ? (
                                <Button asChild>
                                    <Link href={ventas.index.url(teamSlug)}>
                                        Elegir una venta
                                    </Link>
                                </Button>
                            ) : null}
                        </div>
                    ) : (
                        lista.data.map((guia) => {
                            const paso = siguientePaso(guia);

                            return (
                                <div
                                    key={guia.id}
                                    className="grid gap-x-6 gap-y-3 p-4 text-sm md:grid-cols-[minmax(0,1.1fr)_minmax(0,1.5fr)_minmax(0,2fr)_auto] md:items-center"
                                >
                                    <div className="min-w-0">
                                        <Link
                                            href={guia.detalle_url}
                                            className="text-foreground font-mono font-bold hover:underline"
                                        >
                                            {guia.numero}
                                        </Link>
                                        <p className="text-muted-foreground text-xs">
                                            {guia.origen}
                                            {guia.comprobante_relacionado
                                                ? ` · ${guia.comprobante_relacionado}`
                                                : ''}
                                        </p>
                                    </div>
                                    <div className="min-w-0">
                                        <p className="text-foreground truncate font-semibold">
                                            {guia.destinatario}
                                        </p>
                                        <p className="text-muted-foreground text-xs">
                                            {guia.motivo} · traslado{' '}
                                            {guia.fecha_traslado} · {guia.sede}
                                        </p>
                                    </div>
                                    <div className="min-w-0">
                                        <ol
                                            aria-label={`Avance de ${guia.numero}`}
                                            className="flex flex-wrap items-center gap-x-1.5 gap-y-1"
                                        >
                                            {pasosDe(guia).map((p, indice) => (
                                                <li
                                                    key={p.nombre}
                                                    className={`flex items-center gap-1.5 text-xs font-semibold ${p.fallo ? 'text-destructive-strong' : p.hecho ? 'text-success-strong' : 'text-muted-foreground'}`}
                                                >
                                                    <span
                                                        aria-hidden="true"
                                                        className={`size-2 rounded-full ${p.fallo ? 'bg-destructive' : p.hecho ? 'bg-emerald-500' : 'bg-muted-foreground/40'}`}
                                                    />
                                                    {p.nombre}
                                                    <span className="sr-only">
                                                        {p.hecho && !p.fallo
                                                            ? ' (hecho)'
                                                            : ' (pendiente)'}
                                                    </span>
                                                    {indice < 2 ? (
                                                        <span
                                                            aria-hidden="true"
                                                            className="bg-border ml-1 h-0.5 w-4 rounded"
                                                        />
                                                    ) : null}
                                                </li>
                                            ))}
                                        </ol>
                                        <p
                                            className={`mt-1 text-xs ${paso.tono}`}
                                        >
                                            {paso.texto}
                                        </p>
                                        {guia.sunat_mensaje?.startsWith(
                                            'SIMULADO',
                                        ) ? (
                                            <p className="text-warning-strong mt-1 text-xs font-medium">
                                                {guia.sunat_mensaje}
                                            </p>
                                        ) : null}
                                    </div>
                                    <div className="flex flex-wrap justify-start gap-2 md:justify-end">
                                        {guia.estado_sunat === 'borrador' ||
                                        guia.estado_sunat === 'rechazada' ? (
                                            <Button
                                                size="sm"
                                                onClick={() =>
                                                    accion(guia, 'enviar')
                                                }
                                            >
                                                {guia.estado_sunat ===
                                                'rechazada'
                                                    ? 'Reenviar a SUNAT'
                                                    : 'Enviar a SUNAT'}
                                            </Button>
                                        ) : null}
                                        {guia.estado_sunat === 'enviada' ? (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    accion(guia, 'consultar')
                                                }
                                            >
                                                Consultar CDR
                                            </Button>
                                        ) : null}
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <Link href={guia.detalle_url}>
                                                Ver
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                            );
                        })
                    )}
                </Card>
                {lista.total > 0 ? (
                    <nav
                        aria-label="Paginación de guías"
                        className="flex flex-wrap items-center justify-between gap-3 text-sm"
                    >
                        <p className="text-muted-foreground" aria-live="polite">
                            Mostrando {lista.from ?? 0}–{lista.to ?? 0} de{' '}
                            {lista.total} guías
                        </p>
                        <div className="flex items-center gap-3">
                            {lista.prev_page_url ? (
                                <Button asChild variant="outline">
                                    <Link
                                        href={lista.prev_page_url}
                                        preserveScroll
                                    >
                                        Anterior
                                    </Link>
                                </Button>
                            ) : (
                                <Button variant="outline" disabled>
                                    Anterior
                                </Button>
                            )}
                            <span>
                                Página {lista.current_page} de {lista.last_page}
                            </span>
                            {lista.next_page_url ? (
                                <Button asChild variant="outline">
                                    <Link
                                        href={lista.next_page_url}
                                        preserveScroll
                                    >
                                        Siguiente
                                    </Link>
                                </Button>
                            ) : (
                                <Button variant="outline" disabled>
                                    Siguiente
                                </Button>
                            )}
                        </div>
                    </nav>
                ) : null}
            </div>
        </RoleLayout>
    );
}
