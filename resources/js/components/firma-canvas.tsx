import { Eraser } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import type { PointerEvent } from 'react';

interface Props {
    /** Recibe la firma como imagen PNG (data URL) o null si se borró. */
    onChange: (firma: string | null) => void;
    etiqueta?: string;
}

/**
 * Lienzo para firmar con el dedo o el lápiz. Sin librerías: dibuja con
 * eventos de puntero y entrega la firma como PNG.
 */
export default function FirmaCanvas({
    onChange,
    etiqueta = 'Firma del cliente',
}: Props) {
    const lienzo = useRef<HTMLCanvasElement>(null);
    const dibujando = useRef(false);
    const largoTrazo = useRef(0);
    const ultimo = useRef({ x: 0, y: 0 });
    const [firmado, setFirmado] = useState(false);

    const preparar = useCallback(() => {
        const el = lienzo.current;

        if (!el) {
            return;
        }

        const escala = window.devicePixelRatio || 1;
        el.width = el.clientWidth * escala;
        el.height = el.clientHeight * escala;
        const ctx = el.getContext('2d');

        if (ctx) {
            ctx.scale(escala, escala);
            ctx.lineWidth = 2.5;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.strokeStyle = '#111827';
        }
    }, []);

    useEffect(() => {
        preparar();
    }, [preparar]);

    const punto = (e: PointerEvent<HTMLCanvasElement>) => {
        const caja = e.currentTarget.getBoundingClientRect();

        return { x: e.clientX - caja.left, y: e.clientY - caja.top };
    };

    const empezar = (e: PointerEvent<HTMLCanvasElement>) => {
        e.currentTarget.setPointerCapture(e.pointerId);
        dibujando.current = true;
        const ctx = e.currentTarget.getContext('2d');
        const { x, y } = punto(e);
        ultimo.current = { x, y };
        ctx?.beginPath();
        ctx?.moveTo(x, y);
    };

    const mover = (e: PointerEvent<HTMLCanvasElement>) => {
        if (!dibujando.current) {
            return;
        }

        const ctx = e.currentTarget.getContext('2d');
        const { x, y } = punto(e);
        ctx?.lineTo(x, y);
        ctx?.stroke();
        largoTrazo.current += Math.hypot(
            x - ultimo.current.x,
            y - ultimo.current.y,
        );
        ultimo.current = { x, y };
    };

    const terminar = () => {
        if (!dibujando.current) {
            return;
        }

        dibujando.current = false;

        // Un toque suelto no es una firma: pide un trazo de largo razonable.
        if (largoTrazo.current < 40) {
            return;
        }

        setFirmado(true);
        onChange(lienzo.current?.toDataURL('image/png') ?? null);
    };

    const borrar = () => {
        const el = lienzo.current;
        el?.getContext('2d')?.clearRect(0, 0, el.width, el.height);
        largoTrazo.current = 0;
        setFirmado(false);
        onChange(null);
    };

    return (
        <div className="space-y-1.5">
            <div className="flex items-center justify-between">
                <span className="text-xs font-bold text-neutral-900 dark:text-neutral-100">
                    {etiqueta} *
                </span>
                <button
                    type="button"
                    onClick={borrar}
                    disabled={!firmado}
                    className="inline-flex min-h-[44px] items-center gap-1 rounded-lg px-3 text-xs font-semibold text-neutral-600 hover:text-neutral-900 disabled:opacity-40 dark:text-neutral-300"
                >
                    <Eraser className="h-4 w-4" />
                    Borrar y firmar de nuevo
                </button>
            </div>
            <canvas
                ref={lienzo}
                role="img"
                aria-label="Espacio para firmar con el dedo"
                onPointerDown={empezar}
                onPointerMove={mover}
                onPointerUp={terminar}
                onPointerCancel={terminar}
                style={{ touchAction: 'none' }}
                className="h-40 w-full rounded-xl border-2 border-dashed border-neutral-400 bg-white dark:border-neutral-500"
            />
            {!firmado && (
                <p className="text-xs text-neutral-500">
                    Firma dentro del recuadro con el dedo.
                </p>
            )}
        </div>
    );
}
