import { useEffect, useState } from 'react';

import FiltroSelect from '@/components/filtro-select';
import { Input } from '@/components/ui/input';
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
 * Filtro de periodo en una sola lista corta (Hoy, Ayer, Esta semana, Este
 * mes, Personalizado). Las fechas «Desde / Hasta» solo aparecen cuando se
 * elige Personalizado. Se aplica en cuanto se elige una fecha.
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
    const lista = atajos(hoy);
    const coincide = lista.find(
        (atajo) => atajo.desde === rango.desde && atajo.hasta === rango.hasta,
    );
    const [personalizado, setPersonalizado] = useState(!coincide);
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

    const valorPeriodo = personalizado
        ? 'Personalizado'
        : (coincide?.label ?? 'Personalizado');

    return (
        <div className="flex flex-wrap items-end gap-2">
            <FiltroSelect
                etiqueta="Periodo"
                valor={valorPeriodo}
                opciones={[
                    ...lista.map((atajo) => ({
                        value: atajo.label,
                        label: atajo.label,
                    })),
                    { value: 'Personalizado', label: 'Elegir fechas…' },
                ]}
                onCambiar={(valor) => {
                    const atajo = lista.find((a) => a.label === valor);

                    if (!atajo) {
                        setPersonalizado(true);

                        return;
                    }

                    setPersonalizado(false);
                    onCambiar({ desde: atajo.desde, hasta: atajo.hasta });
                }}
            />
            {valorPeriodo === 'Personalizado' ? (
                <>
                    <label className="text-muted-foreground flex flex-col gap-1 text-xs">
                        Desde
                        <Input
                            type="date"
                            value={desde}
                            max={hoy}
                            onChange={(event) =>
                                elegir('desde', event.target.value)
                            }
                            className="text-foreground h-9 w-auto text-sm"
                        />
                    </label>
                    <label className="text-muted-foreground flex flex-col gap-1 text-xs">
                        Hasta
                        <Input
                            type="date"
                            value={hasta}
                            max={hoy}
                            onChange={(event) =>
                                elegir('hasta', event.target.value)
                            }
                            className="text-foreground h-9 w-auto text-sm"
                        />
                    </label>
                </>
            ) : null}
        </div>
    );
}
