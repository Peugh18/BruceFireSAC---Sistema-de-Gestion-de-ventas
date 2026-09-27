import { router, usePage } from '@inertiajs/react';
import { Hand, UserCheck } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import tecnicoCampo from '@/routes/tecnico-campo/ordenes';
import tecnicoPlanta from '@/routes/tecnico-planta/ordenes';
import type { Team } from '@/types';

export type AsignacionOrden = {
    orden_id: number;
    area: 'planta' | 'campo';
    tecnico: string | null;
    es_mia: boolean;
};

/**
 * Franja que dice quién tiene la orden. Si nadie la tiene, el técnico la
 * toma con un clic y queda registrado en la bitácora para ventas.
 */
export default function TomarOrden({
    asignacion,
}: {
    asignacion: AsignacionOrden;
}) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug =
        currentTeam?.slug ??
        (typeof window !== 'undefined'
            ? window.location.pathname.split('/')[1]
            : '');
    const [tomando, setTomando] = useState(false);

    if (asignacion.tecnico) {
        return (
            <div className="flex items-center gap-2 rounded-[12px] border border-emerald-500/20 bg-emerald-500/5 px-4 py-2.5 text-[12.5px]">
                <UserCheck className="size-4 text-emerald-600 dark:text-emerald-400" />
                <span className="text-foreground">
                    {asignacion.es_mia
                        ? 'Esta orden está a tu cargo.'
                        : `A cargo de ${asignacion.tecnico}.`}
                </span>
            </div>
        );
    }

    function tomar() {
        const rutas =
            asignacion.area === 'campo' ? tecnicoCampo : tecnicoPlanta;

        setTomando(true);
        router.post(
            rutas.tomar.url({
                current_team: teamSlug,
                service_order: asignacion.orden_id,
            }),
            {},
            { preserveScroll: true, onFinish: () => setTomando(false) },
        );
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 rounded-[12px] border border-amber-500/30 bg-amber-500/5 px-4 py-3 text-[12.5px]">
            <div>
                <p className="text-foreground font-bold">
                    Nadie tiene esta orden todavía
                </p>
                <p className="text-muted-foreground">
                    Tómala para que ventas sepa que tú la estás atendiendo.
                </p>
            </div>
            <Button
                type="button"
                onClick={tomar}
                disabled={tomando}
                className="h-9 rounded-[9px] font-bold shadow-none"
            >
                <Hand className="size-4" />
                {tomando ? 'Tomando…' : 'Tomar esta orden'}
            </Button>
        </div>
    );
}
