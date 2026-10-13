import { Head, Link, router, usePage } from '@inertiajs/react';
import { Wrench, Inbox } from 'lucide-react';
import { useState } from 'react';

import { useBusquedaDinamica } from '@/hooks/use-busqueda-dinamica';
import TecnicoCampoLayout from '@/layouts/tecnico-campo-layout';
import mantenimientos from '@/routes/tecnico-campo/mantenimientos';
import type { Team } from '@/types';
import { etiquetaEstadoOrden } from '@/lib/estados-orden';
import { Empty } from '@/components/ui/empty';

type Orden = {
    id: number;
    codigo: string;
    estado: string;
    client: { razon_social: string; direccion_fiscal?: string | null };
    equipments: unknown[];
};

type Props = {
    mantenimientos: { data: Orden[] };
    search: string | null;
};

export default function MantenimientosIndex({
    mantenimientos: lista,
    search,
}: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const equipo = currentTeam?.slug ?? '';
    const [texto, setTexto] = useState(search ?? '');

    const aplicarBusqueda = () => {
        router.get(
            mantenimientos.index.url(equipo),
            { q: texto || undefined },
            { preserveState: true },
        );
    };
    const buscar = useBusquedaDinamica(texto, aplicarBusqueda);

    return (
        <TecnicoCampoLayout title="Mantenimiento en sitio">
            <Head title="Mantenimiento en sitio - Técnico de Campo" />

            <form onSubmit={buscar} className="mb-4 flex gap-2">
                <label htmlFor="buscar-mantenimiento" className="sr-only">
                    Buscar por código o cliente
                </label>
                <input
                    id="buscar-mantenimiento"
                    value={texto}
                    onChange={(e) => setTexto(e.target.value)}
                    placeholder="Código o cliente"
                    className="border-border bg-card min-h-[44px] flex-1 rounded-lg border px-3 text-xs"
                />
                <button
                    type="submit"
                    className="bg-primary min-h-[44px] rounded-lg px-4 text-xs font-bold text-white transition hover:brightness-95"
                >
                    Buscar
                </button>
            </form>

            {lista.data.length === 0 ? (
                <Empty
                    icon={<Inbox />}
                    title="No hay mantenimientos en sitio asignados."
                />
            ) : (
                <ul className="space-y-2.5">
                    {lista.data.map((orden) => (
                        <li key={orden.id}>
                            <Link
                                href={mantenimientos.show.url({
                                    current_team: equipo,
                                    service_order: orden.id,
                                })}
                                className="border-border bg-card hover:bg-muted/60 flex min-h-[64px] items-center gap-3 rounded-2xl border p-3.5 shadow-xs transition-colors"
                            >
                                <Wrench className="text-foreground size-5 shrink-0" />
                                <div className="min-w-0 flex-1">
                                    <p className="text-foreground truncate text-sm font-black">
                                        {orden.client.razon_social}
                                    </p>
                                    <p className="text-muted-foreground text-xs">
                                        <span className="font-mono font-bold">
                                            {orden.codigo}
                                        </span>{' '}
                                        · {orden.equipments.length} extintor(es)
                                    </p>
                                </div>
                                <span className="bg-muted text-foreground rounded-full px-2 py-0.5 text-xs font-black">
                                    {etiquetaEstadoOrden(orden.estado)}
                                </span>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </TecnicoCampoLayout>
    );
}
