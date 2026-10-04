import { useEffect, useState } from 'react';

import { fechaCorta } from '@/lib/utils';

export type Rango = { desde: string; hasta: string };

/** Fecha local "YYYY-MM-DD" desplazada unos días desde otra. */
function moverDias(fecha: string, dias: number) {
    const d = new Date(`${fecha}T00:00:00`);
    d.setDate(d.getDate() + dias);

    return [
        d.getFullYear(),
        String(d.getMonth() + 1).padStart(2, '0'),
        String(d.getDate()).padStart(2, '0'),
    ].join('-');
}

/** Atajos del filtro, calculados desde "hoy" del servidor (hora de Lima). */
function atajos(hoy: string) {
    const d = new Date(`${hoy}T00:00:00`);
    const diaSemana = (d.getDay() + 6) % 7; // lunes = 0

    return [
        { label: 'Hoy', desde: hoy, hasta: hoy },
        { label: 'Ayer', desde: moverDias(hoy, -1), hasta: moverDias(hoy, -1) },
        { label: 'Esta semana', desde: moverDias(hoy, -diaSemana), hasta: hoy },
        { label: 'Este mes', desde: `${hoy.slice(0, 8)}01`, hasta: hoy },
    ];
}

const FECHA = /^\d{4}-\d{2}-\d{2}$/;

/** "hoy", "el 03/10/2026" o "del 01/10/2026 al 03/10/2026". */
export function describirRango({ desde, hasta }: Rango, hoy: string) {
    if (desde === hoy && hasta === hoy) {
        return 'hoy';
    }

    return desde === hasta
        ? `el ${fechaCorta(desde)}`
        : `del ${fechaCorta(desde)} al ${fechaCorta(hasta)}`;
}

/**
 * Filtro "Desde / Hasta" con atajos. Se aplica en cuanto se elige una fecha.
 */
export default function RangoFechas({
    rango,
    hoy,
    onCambiar,
}: {
    rango: Rango;
    hoy: string;
    onCambiar: (rango: Rango) => void;
}) {
    const [desde, setDesde] = useState(rango.desde);
    const [hasta, setHasta] = useState(rango.hasta);

    useEffect(() => {
        setDesde(rango.desde);
        setHasta(rango.hasta);
    }, [rango.desde, rango.hasta]);

    const elegir = (campo: 'desde' | 'hasta', valor: string) => {
        if (campo === 'desde') {
            setDesde(valor);
        } else {
            setHasta(valor);
        }

        const siguiente = { desde, hasta, [campo]: valor };

        if (FECHA.test(siguiente.desde) && FECHA.test(siguiente.hasta)) {
            onCambiar(siguiente);
        }
    };

    const campo =
        'border-border bg-card h-9 rounded-[9px] border px-2.5 text-[13px] font-normal normal-case';

    return (
        <div className="flex flex-wrap items-end gap-2">
            <label className="text-foreground/80 flex flex-col gap-1 text-[11px] font-bold uppercase">
                Desde
                <input
                    type="date"
                    value={desde}
                    max={hoy}
                    onChange={(event) => elegir('desde', event.target.value)}
                    className={campo}
                />
            </label>
            <label className="text-foreground/80 flex flex-col gap-1 text-[11px] font-bold uppercase">
                Hasta
                <input
                    type="date"
                    value={hasta}
                    max={hoy}
                    onChange={(event) => elegir('hasta', event.target.value)}
                    className={campo}
                />
            </label>
            <div className="bg-muted flex max-w-full overflow-x-auto rounded-[9px] p-[3px]">
                {atajos(hoy).map((atajo) => {
                    const activo =
                        rango.desde === atajo.desde &&
                        rango.hasta === atajo.hasta;

                    return (
                        <button
                            key={atajo.label}
                            type="button"
                            onClick={() =>
                                onCambiar({
                                    desde: atajo.desde,
                                    hasta: atajo.hasta,
                                })
                            }
                            className={`rounded-[7px] px-3 py-1.5 text-xs font-bold whitespace-nowrap transition-all ${
                                activo
                                    ? 'bg-card text-foreground shadow-[0_1px_2px_rgba(0,0,0,0.06)]'
                                    : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            {atajo.label}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
