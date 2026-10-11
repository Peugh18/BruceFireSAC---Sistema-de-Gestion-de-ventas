import { Head } from '@inertiajs/react';

import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import RoleLayout from '@/layouts/role-layout';

type Equipo = {
    id: number;
    numero_serie: string;
    tipo_agente: string | null;
    capacidad: string | null;
    estado: string;
    ubicacion_actual: string | null;
    proxima_fecha_atencion: string | null;
    proxima_prueba_hidrostatica: string | null;
    cliente: string;
};

type Orden = {
    id: number;
    codigo: string;
    estado: string;
    servicio: string | null;
    fecha: string | null;
    recibido: boolean;
};

function Dato({ titulo, valor }: { titulo: string; valor?: string | null }) {
    return (
        <div>
            <dt className="text-muted-foreground text-xs">{titulo}</dt>
            <dd className="font-medium">{valor || '—'}</dd>
        </div>
    );
}

export default function EquipoShow({
    equipment,
    historia,
}: {
    equipment: Equipo;
    historia: Orden[];
}) {
    return (
        <RoleLayout
            title={`Equipo ${equipment.numero_serie}`}
            breadcrumbs={[{ title: equipment.numero_serie, href: '#' }]}
        >
            <Head title={`Equipo ${equipment.numero_serie}`} />
            <div className="mx-auto max-w-4xl space-y-4 p-4">
                <h1 className="font-mono text-xl font-bold">
                    {equipment.numero_serie}
                </h1>

                <Card className="space-y-3 p-4 text-sm">
                    <h2 className="font-semibold">
                        Identificación y ubicación
                    </h2>
                    <dl className="grid gap-3 sm:grid-cols-2">
                        <Dato titulo="Cliente" valor={equipment.cliente} />
                        <Dato titulo="Estado" valor={equipment.estado} />
                        <Dato titulo="Agente" valor={equipment.tipo_agente} />
                        <Dato titulo="Capacidad" valor={equipment.capacidad} />
                        <Dato
                            titulo="Ubicación actual"
                            valor={equipment.ubicacion_actual}
                        />
                        <Dato
                            titulo="Próxima atención"
                            valor={equipment.proxima_fecha_atencion}
                        />
                        <Dato
                            titulo="Próxima prueba hidrostática"
                            valor={equipment.proxima_prueba_hidrostatica}
                        />
                    </dl>
                </Card>

                <Card className="space-y-3 p-4 text-sm">
                    <h2 className="font-semibold">
                        Historia en órdenes que puedes ver
                    </h2>
                    <ul className="divide-border divide-y">
                        {historia.map((orden) => (
                            <li
                                key={orden.id}
                                className="flex flex-wrap items-center gap-2 py-2"
                            >
                                <span className="font-mono font-bold">
                                    {orden.codigo}
                                </span>
                                <span className="min-w-40 flex-1">
                                    {orden.servicio ?? 'Sin servicio'} ·{' '}
                                    {orden.fecha}
                                </span>
                                <Badge variant="outline">{orden.estado}</Badge>
                                <span className="text-muted-foreground">
                                    {orden.recibido
                                        ? 'Recibido en planta'
                                        : 'Sin recibir'}
                                </span>
                            </li>
                        ))}
                    </ul>
                </Card>
            </div>
        </RoleLayout>
    );
}
