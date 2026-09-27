export type ChispaPose =
    | 'saludo'
    | 'corre'
    | 'sentado'
    | 'piensa'
    | 'busca'
    | 'busto';

/** `auto` sigue el tema del sistema; `claro` fuerza la versión con contorno (sobre fondo blanco). */
export type ChispaTema = 'auto' | 'claro' | 'oscuro';

type Props = {
    /** Pose del personaje. Por debajo de 28 px siempre se usa el busto. */
    pose?: ChispaPose;
    /** Alto en píxeles. */
    size?: number;
    /** Activa la animación propia de la pose (se apaga con prefers-reduced-motion). */
    animado?: boolean;
    tema?: ChispaTema;
    className?: string;
    /** Texto accesible; sin él, el avatar es decorativo. */
    titulo?: string;
};

/** Proporción ancho/alto de cada pose (el mismo lienzo en ambos temas). */
const PROPORCION: Record<ChispaPose, number> = {
    saludo: 337 / 440,
    corre: 350 / 440,
    sentado: 404 / 440,
    piensa: 277 / 440,
    busca: 371 / 440,
    busto: 344 / 440,
};

/**
 * Chispa, la mascota de Bruce Fire: un extintor con carita, manitos y patitas.
 * Las poses son WebP con fondo transparente en public/brand/chispa: la versión
 * clara lleva contorno grafito y la oscura va sin contorno.
 */
export default function ChispaAvatar({
    pose = 'saludo',
    size = 32,
    animado = false,
    tema = 'auto',
    className = '',
    titulo,
}: Props) {
    const poseFinal: ChispaPose = size < 28 ? 'busto' : pose;
    const ancho = Math.round(size * PROPORCION[poseFinal]);
    const imagen = (version: 'claro' | 'oscuro', visibilidad: string) => (
        <img
            src={`/brand/chispa/${version}/${poseFinal}.webp`}
            width={ancho}
            height={size}
            alt=""
            decoding="async"
            draggable={false}
            className={`absolute inset-0 size-full ${visibilidad}`}
        />
    );

    return (
        <span
            role={titulo ? 'img' : undefined}
            aria-label={titulo}
            aria-hidden={titulo ? undefined : true}
            className={[
                'chispa relative inline-block shrink-0 select-none',
                animado ? `chispa-anim-${poseFinal}` : '',
                className,
            ]
                .filter(Boolean)
                .join(' ')}
            style={{ width: ancho, height: size }}
        >
            {tema === 'oscuro'
                ? null
                : imagen('claro', tema === 'auto' ? 'dark:hidden' : '')}
            {tema === 'claro'
                ? null
                : imagen('oscuro', tema === 'auto' ? 'hidden dark:block' : '')}
        </span>
    );
}
