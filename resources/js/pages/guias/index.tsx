import { Head, Link, router, usePage } from '@inertiajs/react';
import { toast } from 'sonner';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import RoleLayout from '@/layouts/role-layout';
import guias from '@/routes/guias';
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

const ESTADOS: Record<Guia['estado_sunat'], string> = {
    borrador: 'Sin enviar',
    enviada: 'En proceso en SUNAT',
    aceptada: 'Aceptada por SUNAT',
    rechazada: 'Rechazada',
};

export default function GuiasIndex({
    guias: lista,
}: {
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
    const { currentTeam, flash } = usePage<{
        currentTeam?: Team | null;
        flash?: { success?: string; error?: string };
    }>().props;
    const teamSlug = currentTeam?.slug ?? '';
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
            <div className="mx-auto max-w-5xl space-y-4 p-4">
                <h1 className="text-xl font-bold">Guías de remisión</h1>
                <p className="text-muted-foreground text-sm">
                    Crea la guía desde una venta, una orden de recojo o entrega,
                    o un traslado entre sedes. La mercadería solo puede salir
                    con la guía lista para trasladar (CDR aceptado por SUNAT).
                </p>
                {flash?.success ? (
                    <p role="status" className="text-sm text-emerald-700">
                        {flash.success}
                    </p>
                ) : null}
                {flash?.error ? (
                    <p role="alert" className="text-destructive-strong text-sm">
                        {flash.error}
                    </p>
                ) : null}
                <Card className="divide-border divide-y p-0">
                    {lista.data.length === 0 ? (
                        <p className="text-muted-foreground p-6 text-sm">
                            Todavía no hay guías de remisión.
                        </p>
                    ) : (
                        lista.data.map((guia) => (
                            <div
                                key={guia.id}
                                className="flex flex-wrap items-center gap-3 p-4 text-sm"
                            >
                                <div className="min-w-48 flex-1">
                                    <Link
                                        href={guia.detalle_url}
                                        className="font-mono font-bold underline"
                                    >
                                        {guia.numero}
                                    </Link>
                                    <p className="text-sm font-medium">
                                        {guia.origen}
                                        {guia.comprobante_relacionado
                                            ? ` · ${guia.comprobante_relacionado}`
                                            : ''}
                                    </p>
                                    <p className="text-muted-foreground">
                                        {guia.destinatario} · {guia.motivo} ·
                                        traslado {guia.fecha_traslado} ·{' '}
                                        {guia.sede}
                                    </p>
                                    {guia.sunat_mensaje?.startsWith(
                                        'SIMULADO',
                                    ) ? (
                                        <p className="text-warning-strong font-medium">
                                            {guia.sunat_mensaje}
                                        </p>
                                    ) : null}
                                    {guia.estado_sunat === 'rechazada' &&
                                    guia.sunat_mensaje ? (
                                        <p className="text-destructive-strong">
                                            {guia.sunat_mensaje}
                                        </p>
                                    ) : null}
                                </div>
                                <Badge
                                    variant={
                                        guia.lista_para_trasladar
                                            ? 'default'
                                            : 'outline'
                                    }
                                >
                                    {guia.lista_para_trasladar
                                        ? 'Lista para trasladar'
                                        : ESTADOS[guia.estado_sunat]}
                                </Badge>
                                {guia.estado_sunat === 'aceptada' &&
                                !guia.lista_para_trasladar ? (
                                    <span
                                        role="status"
                                        className="text-destructive-strong"
                                    >
                                        CDR no disponible: despacho bloqueado.
                                    </span>
                                ) : null}
                                {guia.estado_sunat === 'borrador' ||
                                guia.estado_sunat === 'rechazada' ? (
                                    <Button
                                        size="sm"
                                        onClick={() => accion(guia, 'enviar')}
                                    >
                                        Enviar a SUNAT
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
                            </div>
                        ))
                    )}
                </Card>
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
                                <Link href={lista.prev_page_url} preserveScroll>
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
                                <Link href={lista.next_page_url} preserveScroll>
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
            </div>
        </RoleLayout>
    );
}
