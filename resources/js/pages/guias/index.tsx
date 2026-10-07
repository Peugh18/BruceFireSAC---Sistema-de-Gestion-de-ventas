import { Head, router, usePage } from '@inertiajs/react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import guias from '@/routes/guias';
import type { Team } from '@/types';

type Guia = {
    id: number;
    numero: string;
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
    aceptada: 'Lista para trasladar',
    rechazada: 'Rechazada',
};

export default function GuiasIndex({
    guias: lista,
}: {
    guias: { data: Guia[] };
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
            { preserveScroll: true },
        );

    return (
        <AppLayout breadcrumbs={[{ title: 'Guías de remisión', href: '#' }]}>
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
                                    <p className="font-mono font-bold">
                                        {guia.numero}
                                    </p>
                                    <p className="text-muted-foreground">
                                        {guia.destinatario} · {guia.motivo} ·
                                        traslado {guia.fecha_traslado} ·{' '}
                                        {guia.sede}
                                    </p>
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
                                    {ESTADOS[guia.estado_sunat]}
                                </Badge>
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
            </div>
        </AppLayout>
    );
}
