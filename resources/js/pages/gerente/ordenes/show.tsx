import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

import EntradaSalida, {
    type EntradaSalidaProps,
} from '@/components/entrada-salida';
import { pageTitleClass } from '@/components/page-header';
import PreciosCampo, { type PrecioCampo } from '@/components/precios-campo';
import { Button } from '@/components/ui/button';
import GerenteLayout from '@/layouts/gerente-layout';
import { etiquetaEstadoOrden } from '@/lib/estados-orden';
import OrdenesRoutes from '@/routes/gerente/ordenes';
import type { Team } from '@/types';

type Props = {
    orden: {
        id: number;
        codigo: string;
        cliente: string;
        documento: string | null;
        estado: string;
        tecnico: string | null;
        cobrada: boolean;
        servicios: string;
    };
    entradaSalida: EntradaSalidaProps;
    preciosCampo: PrecioCampo[];
};

/** Orden para el gerente: entrada, salida y el precio que dejó campo. */
export default function OrdenGerente({
    orden,
    entradaSalida,
    preciosCampo,
}: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const equipo = currentTeam?.slug ?? '';

    return (
        <GerenteLayout title={`Orden ${orden.codigo}`}>
            <Head title={`Orden ${orden.codigo}`} />
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-5">
                <div className="flex flex-wrap items-center gap-3">
                    <Button
                        asChild
                        variant="outline"
                        size="icon"
                        className="size-11 rounded-2xl"
                    >
                        <Link
                            href={OrdenesRoutes.index.url(equipo)}
                            aria-label="Volver a órdenes"
                        >
                            <ArrowLeft />
                        </Link>
                    </Button>
                    <div className="min-w-0 flex-1">
                        <p className="text-primary-strong text-xs font-bold tracking-wider uppercase">
                            {orden.codigo} · {orden.cliente}
                        </p>
                        <h1 className={pageTitleClass}>Entrada y salida</h1>
                    </div>
                    <span className="bg-muted rounded-full px-3 py-1 text-xs font-bold">
                        {etiquetaEstadoOrden(orden.estado)}
                    </span>
                    <span
                        className={`rounded-full px-3 py-1 text-xs font-bold ${orden.cobrada ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200' : 'bg-primary/10 text-primary-strong'}`}
                    >
                        {orden.cobrada ? 'Cobrada' : 'Sin cobrar'}
                    </span>
                </div>

                <dl className="border-border bg-card grid gap-4 rounded-3xl border p-5 text-sm sm:grid-cols-3">
                    <div>
                        <dt className="text-muted-foreground text-xs">
                            Documento
                        </dt>
                        <dd className="font-semibold">
                            {orden.documento ?? '—'}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground text-xs">
                            Servicio
                        </dt>
                        <dd className="font-semibold">
                            {orden.servicios || '—'}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground text-xs">
                            Responsable
                        </dt>
                        <dd className="font-semibold">
                            {orden.tecnico ?? 'Sin asignar'}
                        </dd>
                    </div>
                </dl>

                <PreciosCampo precios={preciosCampo} />

                <EntradaSalida
                    entrada={entradaSalida.entrada}
                    salida={entradaSalida.salida}
                />
                {!entradaSalida.entrada && !entradaSalida.salida ? (
                    <p className="text-muted-foreground border-border bg-card rounded-3xl border p-5 text-sm">
                        Esta orden todavía no tiene recojo ni entrega de campo.
                    </p>
                ) : null}
            </div>
        </GerenteLayout>
    );
}
