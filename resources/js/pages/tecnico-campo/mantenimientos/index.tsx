import { Head, Link, router, usePage } from '@inertiajs/react';
import { Wrench } from 'lucide-react';
import { useState } from 'react';

import TecnicoCampoLayout from '@/layouts/tecnico-campo-layout';
import mantenimientos from '@/routes/tecnico-campo/mantenimientos';
import type { Team } from '@/types';

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

    const buscar = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            mantenimientos.index.url(equipo),
            { q: texto || undefined },
            { preserveState: true },
        );
    };

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
                    className="border-border bg-card min-h-[44px] flex-1 rounded-[10px] border px-3 text-xs"
                />
                <button
                    type="submit"
                    className="min-h-[44px] rounded-[10px] bg-sky-600 px-4 text-xs font-bold text-white"
                >
                    Buscar
                </button>
            </form>

            {lista.data.length === 0 ? (
                <p className="text-muted-foreground rounded-[14px] border border-dashed p-6 text-center text-xs">
                    No hay mantenimientos en sitio asignados.
                </p>
            ) : (
                <ul className="space-y-2.5">
                    {lista.data.map((orden) => (
                        <li key={orden.id}>
                            <Link
                                href={mantenimientos.show.url({
                                    current_team: equipo,
                                    service_order: orden.id,
                                })}
                                className="border-border bg-card flex min-h-[64px] items-center gap-3 rounded-[14px] border p-3.5 shadow-xs"
                            >
                                <Wrench className="size-5 shrink-0 text-sky-600 dark:text-sky-400" />
                                <div className="min-w-0 flex-1">
                                    <p className="text-foreground truncate text-sm font-black">
                                        {orden.client.razon_social}
                                    </p>
                                    <p className="text-muted-foreground text-[11px]">
                                        <span className="font-mono font-bold">
                                            {orden.codigo}
                                        </span>{' '}
                                        · {orden.equipments.length} extintor(es)
                                    </p>
                                </div>
                                <span className="rounded-full bg-sky-500/10 px-2 py-0.5 text-[10px] font-black text-sky-800 dark:text-sky-300">
                                    {orden.estado.replace(/_/g, ' ')}
                                </span>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </TecnicoCampoLayout>
    );
}
