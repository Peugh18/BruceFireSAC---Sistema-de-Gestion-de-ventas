import { Link, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

import { dashboard } from '@/routes/gerente';
import GerenteLayout from '@/layouts/gerente-layout';

type Props = {
    tipo: string;
    titulo: string;
    mes: string;
    sede: number | null;
    rows: Array<{
        titulo: string;
        cliente: string;
        estado: string;
        fecha: string;
        url: string;
    }>;
};

export default function DashboardDetalle({ titulo, mes, sede, rows }: Props) {
    const { currentTeam } = usePage<{ currentTeam?: { slug: string } | null }>()
        .props;
    const volver = dashboard.url(currentTeam?.slug ?? '', {
        query: { mes, ...(sede ? { sede } : {}) },
    });

    return (
        <GerenteLayout title="Detalle de indicador">
            <div className="mx-auto max-w-5xl space-y-6">
                <header>
                    <Link
                        href={volver}
                        className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-sm font-medium"
                    >
                        <ArrowLeft className="size-4" aria-hidden="true" />
                        Volver al resumen
                    </Link>
                    <h1 className="text-foreground mt-2 text-2xl font-bold sm:text-3xl">
                        {titulo}
                    </h1>
                </header>
                <section className="border-border bg-card overflow-hidden rounded-2xl border">
                    <ul className="divide-border divide-y">
                        {rows.length === 0 ? (
                            <li className="text-muted-foreground p-5 text-sm">
                                No hay elementos para este indicador.
                            </li>
                        ) : (
                            rows.map((row) => (
                                <li key={`${row.titulo}-${row.fecha}`}>
                                    <Link
                                        href={row.url}
                                        className="hover:bg-muted block p-4 focus-visible:outline-2 focus-visible:outline-offset-[-2px]"
                                    >
                                        <div className="flex items-center justify-between gap-3">
                                            <span className="text-foreground font-semibold">
                                                {row.titulo}
                                            </span>
                                            <span className="text-muted-foreground text-sm">
                                                {row.fecha}
                                            </span>
                                        </div>
                                        <div className="text-muted-foreground mt-1 flex justify-between gap-3 text-sm">
                                            <span>{row.cliente}</span>
                                            <span className="capitalize">
                                                {row.estado}
                                            </span>
                                        </div>
                                    </Link>
                                </li>
                            ))
                        )}
                    </ul>
                </section>
            </div>
        </GerenteLayout>
    );
}
