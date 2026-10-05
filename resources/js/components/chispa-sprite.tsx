import { useEffect, useRef, useState } from 'react';

import ChispaAvatar from '@/components/chispa-avatar';

export type ChispaEstado =
    | 'reposo'
    | 'saludo'
    | 'pensando'
    | 'revisando'
    | 'error';

const SECUENCIAS = {
    reposo: { fila: 0, tiempos: [280, 110, 110, 140, 140, 320], quieto: 0 },
    saludo: { fila: 3, tiempos: [140, 140, 140, 280], quieto: 0 },
    pensando: { fila: 7, tiempos: [120, 120, 120, 120, 120, 220], quieto: 0 },
    revisando: { fila: 8, tiempos: [150, 150, 150, 150, 150, 280], quieto: 0 },
    error: {
        fila: 5,
        tiempos: [140, 140, 140, 140, 140, 140, 140, 240],
        quieto: 3,
    },
} satisfies Record<
    ChispaEstado,
    { fila: number; tiempos: number[]; quieto: number }
>;

const IMAGEN = '/brand/chispa/animado-v2.webp';

export default function ChispaSprite({
    estado = 'reposo',
    size = 80,
    repetir = true,
    className = '',
}: {
    estado?: ChispaEstado;
    size?: number;
    repetir?: boolean;
    className?: string;
}) {
    const elemento = useRef<HTMLSpanElement>(null);
    const [cargado, setCargado] = useState(false);
    const ancho = (size * 192) / 208;
    const secuencia = SECUENCIAS[estado];

    useEffect(() => {
        const imagen = new Image();
        imagen.onload = () => setCargado(true);
        imagen.src = IMAGEN;
        return () => {
            imagen.onload = null;
        };
    }, []);

    useEffect(() => {
        const nodo = elemento.current;
        if (!nodo || !cargado) return;
        const movimiento = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        );
        let cuadro = 0;
        let visible = false;
        let terminado = false;
        let temporizador: ReturnType<typeof setTimeout> | undefined;

        const mostrar = (indice: number) => {
            nodo.style.backgroundPosition = `-${indice * ancho}px -${secuencia.fila * size}px`;
        };
        const continuar = () => {
            clearTimeout(temporizador);
            if (movimiento.matches) {
                mostrar(secuencia.quieto);
                return;
            }
            mostrar(cuadro);
            if (!visible || document.hidden || terminado) return;
            temporizador = setTimeout(() => {
                if (cuadro === secuencia.tiempos.length - 1) {
                    if (!repetir) {
                        terminado = true;
                        return;
                    }
                    cuadro = 0;
                } else {
                    cuadro++;
                }
                continuar();
            }, secuencia.tiempos[cuadro]);
        };
        const observador = new IntersectionObserver(([entrada]) => {
            visible = entrada.isIntersecting;
            continuar();
        });
        observador.observe(nodo);
        movimiento.addEventListener('change', continuar);
        document.addEventListener('visibilitychange', continuar);
        continuar();
        return () => {
            clearTimeout(temporizador);
            observador.disconnect();
            movimiento.removeEventListener('change', continuar);
            document.removeEventListener('visibilitychange', continuar);
        };
    }, [ancho, cargado, repetir, secuencia, size]);

    return (
        <span
            ref={elemento}
            aria-hidden
            data-estado={estado}
            className={`chispa-sprite inline-block shrink-0 select-none ${className}`}
            style={{
                width: ancho,
                height: size,
                backgroundImage: cargado ? `url(${IMAGEN})` : undefined,
                backgroundSize: `${ancho * 8}px ${size * 11}px`,
                backgroundPosition: `0 -${secuencia.fila * size}px`,
                backgroundRepeat: 'no-repeat',
            }}
        >
            {!cargado ? (
                <ChispaAvatar size={Math.round(size * 0.7)} pose="busto" />
            ) : null}
        </span>
    );
}
