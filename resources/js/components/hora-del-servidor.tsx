import { usePage } from '@inertiajs/react';
import { CalendarClock } from 'lucide-react';
import { useEffect, useState } from 'react';

/**
 * Fecha y hora del servidor (Lima), no la de la computadora: así todos ven el
 * mismo día aunque un equipo tenga la hora mal puesta.
 */
export function HoraDelServidor() {
    const { servidor } = usePage<{ servidor?: { ahora: string } | null }>()
        .props;
    const [desfase, setDesfase] = useState(0);
    const [ahora, setAhora] = useState(() => new Date());

    useEffect(() => {
        if (servidor?.ahora) {
            setDesfase(new Date(servidor.ahora).getTime() - Date.now());
        }
    }, [servidor?.ahora]);

    useEffect(() => {
        const reloj = window.setInterval(() => setAhora(new Date()), 30_000);

        return () => window.clearInterval(reloj);
    }, []);

    if (!servidor) {
        return null;
    }

    const hora = new Date(ahora.getTime() + desfase);
    const texto = hora.toLocaleString('es-PE', {
        timeZone: 'America/Lima',
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });

    return (
        <span
            className="text-muted-foreground hidden items-center gap-1.5 text-xs font-medium tabular-nums md:inline-flex"
            title="Fecha y hora del sistema (Lima)"
        >
            <CalendarClock className="size-4 shrink-0" aria-hidden="true" />
            <time dateTime={hora.toISOString()}>{texto}</time>
        </span>
    );
}
