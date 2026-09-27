import { RotateCcw, Send, X } from 'lucide-react';
import {
    FormEvent,
    Fragment,
    ReactNode,
    useEffect,
    useRef,
    useState,
} from 'react';

import ChispaAvatar from '@/components/chispa-avatar';
import { asistente } from '@/routes';

type Mensaje = { role: 'user' | 'assistant'; content: string };

const CLAVE = 'chispa-conversacion';
/** El globo "¿Te ayudo?" se muestra solo una vez por sesión; después, al pasar el mouse. */
const CLAVE_GLOBO = 'chispa-globo-visto';

const SUGERENCIAS: Record<string, string[]> = {
    vendedor: [
        '¿Cómo hago una venta a crédito?',
        '¿Cómo corrijo una factura antes de enviarla?',
        '¿Cómo armo los certificados de una venta?',
    ],
    gerente: ['¿Cómo creo un servicio?', '¿Cómo asigno la sede a un usuario?'],
    almacen: ['¿Cómo registro una recepción?', '¿Cómo ajusto el stock?'],
    tecnico: ['¿Cómo registro una deficiencia?', '¿Qué hago en una entrega?'],
};

function leerCookie(nombre: string) {
    const valor = document.cookie
        .split('; ')
        .find((c) => c.startsWith(`${nombre}=`))
        ?.split('=')[1];

    return valor ? decodeURIComponent(valor) : '';
}

/**
 * Muestra el texto del asistente con **negritas** y saltos de línea.
 */
function Formato({ texto }: { texto: string }) {
    return (
        <>
            {texto.split('\n').map((linea, i) => (
                <Fragment key={i}>
                    {linea
                        .split(/(\*\*[^*]+\*\*)/g)
                        .map((parte, j): ReactNode =>
                            parte.startsWith('**') && parte.endsWith('**') ? (
                                <b key={j}>{parte.slice(2, -2)}</b>
                            ) : (
                                <Fragment key={j}>
                                    {parte.replace(/^#+\s*/, '')}
                                </Fragment>
                            ),
                        )}
                    {i < texto.split('\n').length - 1 ? <br /> : null}
                </Fragment>
            ))}
        </>
    );
}

/**
 * Asistente "Chispa": botón flotante que responde paso a paso con el manual
 * de ayuda del rol. La conversación se guarda solo en esta pestaña.
 */
export default function ChispaWidget({
    rol = 'vendedor',
}: {
    rol?: keyof typeof SUGERENCIAS;
}) {
    const [abierto, setAbierto] = useState(false);
    const [mensajes, setMensajes] = useState<Mensaje[]>(() => {
        try {
            return JSON.parse(
                sessionStorage.getItem(CLAVE) ?? '[]',
            ) as Mensaje[];
        } catch {
            return [];
        }
    });
    const [texto, setTexto] = useState('');
    const [pensando, setPensando] = useState(false);
    const [globo, setGlobo] = useState(false);
    const finRef = useRef<HTMLDivElement>(null);
    /** En celular los técnicos tienen una barra inferior fija: Chispa va encima. */
    const conBarraInferior = rol === 'tecnico';

    useEffect(() => {
        try {
            if (sessionStorage.getItem(CLAVE_GLOBO) === '1') {
                return;
            }
        } catch {
            // Sin almacenamiento: el globo se muestra igual.
        }

        const mostrar = setTimeout(() => setGlobo(true), 900);
        const ocultar = setTimeout(() => {
            setGlobo(false);
            try {
                sessionStorage.setItem(CLAVE_GLOBO, '1');
            } catch {
                // Sin almacenamiento: volverá a mostrarse en la próxima página.
            }
        }, 6900);

        return () => {
            clearTimeout(mostrar);
            clearTimeout(ocultar);
        };
    }, []);

    useEffect(() => {
        try {
            sessionStorage.setItem(CLAVE, JSON.stringify(mensajes.slice(-20)));
        } catch {
            // Sin almacenamiento disponible: la conversación dura solo en memoria.
        }
        finRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [mensajes, abierto]);

    const preguntar = async (pregunta: string) => {
        const limpia = pregunta.trim();
        if (!limpia || pensando) return;

        const historial: Mensaje[] = [
            ...mensajes,
            { role: 'user', content: limpia },
        ];
        setMensajes(historial);
        setTexto('');
        setPensando(true);

        try {
            const response = await fetch(asistente.url(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': leerCookie('XSRF-TOKEN'),
                },
                body: JSON.stringify({
                    mensajes: historial.slice(-12),
                    pagina: document.title,
                }),
            });
            const data = (await response.json()) as {
                respuesta?: string;
                message?: string;
            };

            setMensajes([
                ...historial,
                {
                    role: 'assistant',
                    content:
                        data.respuesta ??
                        (response.status === 429
                            ? 'Hiciste muchas preguntas seguidas. Espera un minuto y vuelve a intentar.'
                            : 'No pude responder ahora. Intenta de nuevo.'),
                },
            ]);
        } catch {
            setMensajes([
                ...historial,
                {
                    role: 'assistant',
                    content: 'No hay conexión. Intenta de nuevo en un momento.',
                },
            ]);
        } finally {
            setPensando(false);
        }
    };

    const enviar = (event: FormEvent) => {
        event.preventDefault();
        void preguntar(texto);
    };

    return (
        <>
            {abierto ? (
                <div
                    className={`border-border bg-card fixed right-4 bottom-20 z-50 flex max-h-[min(560px,calc(100vh-7rem))] w-[min(380px,calc(100vw-2rem))] flex-col overflow-hidden rounded-[16px] border shadow-2xl ${conBarraInferior ? 'max-md:bottom-[calc(9rem+env(safe-area-inset-bottom))] max-md:max-h-[calc(100dvh-11rem)]' : ''}`}
                >
                    <div className="border-border bg-primary flex items-center gap-2.5 border-b px-4 py-3 text-white">
                        <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-white">
                            <ChispaAvatar size={32} pose="busto" tema="claro" />
                        </span>
                        <div className="flex-1">
                            <div className="text-[14px] font-bold">Chispa</div>
                            <div className="text-[11px] opacity-85">
                                Te explico cómo usar el sistema, paso a paso
                            </div>
                        </div>
                        {mensajes.length > 0 ? (
                            <button
                                type="button"
                                onClick={() => setMensajes([])}
                                title="Nueva conversación"
                                className="rounded-[7px] p-1 hover:bg-white/15"
                            >
                                <RotateCcw className="size-4" />
                            </button>
                        ) : null}
                        <button
                            type="button"
                            onClick={() => setAbierto(false)}
                            title="Cerrar"
                            className="rounded-[7px] p-1 hover:bg-white/15"
                        >
                            <X className="size-4" />
                        </button>
                    </div>

                    <div className="flex-1 space-y-2.5 overflow-y-auto p-3 text-[13px]">
                        {mensajes.length === 0 ? (
                            <div className="space-y-2">
                                <div className="flex items-center gap-3">
                                    <ChispaAvatar
                                        size={72}
                                        pose="saludo"
                                        animado
                                        className="shrink-0"
                                    />
                                    <p className="text-muted-foreground">
                                        ¡Hola! Soy Chispa. Pregúntame cómo hacer
                                        algo en el sistema. Por ejemplo:
                                    </p>
                                </div>
                                {SUGERENCIAS[rol].map((s) => (
                                    <button
                                        key={s}
                                        type="button"
                                        onClick={() => void preguntar(s)}
                                        className="border-border hover:bg-muted/50 block w-full rounded-[10px] border px-3 py-2 text-left text-[12.5px]"
                                    >
                                        {s}
                                    </button>
                                ))}
                            </div>
                        ) : null}
                        {mensajes.map((m, i) => (
                            <div
                                key={i}
                                className={
                                    m.role === 'user'
                                        ? 'flex justify-end'
                                        : 'flex items-end justify-start gap-1.5'
                                }
                            >
                                {m.role === 'assistant' ? (
                                    <ChispaAvatar
                                        size={24}
                                        className="mb-0.5 shrink-0"
                                    />
                                ) : null}
                                <div
                                    className={`max-w-[88%] rounded-[12px] px-3 py-2 leading-relaxed ${m.role === 'user' ? 'bg-primary text-white' : 'bg-muted text-foreground'}`}
                                >
                                    <Formato texto={m.content} />
                                </div>
                            </div>
                        ))}
                        {pensando ? (
                            <div
                                className="text-muted-foreground flex items-center gap-2 text-[12px]"
                                role="status"
                            >
                                <ChispaAvatar size={32} pose="piensa" animado />
                                Chispa está pensando…
                            </div>
                        ) : null}
                        <div ref={finRef} />
                    </div>

                    <form
                        onSubmit={enviar}
                        className="border-border flex gap-2 border-t p-2.5"
                    >
                        <input
                            value={texto}
                            onChange={(e) => setTexto(e.target.value)}
                            maxLength={500}
                            placeholder="Escribe tu duda…"
                            className="border-border bg-muted/40 h-10 min-w-0 flex-1 rounded-[9px] border px-3 text-[13px] outline-none"
                        />
                        <button
                            type="submit"
                            disabled={pensando || !texto.trim()}
                            className="bg-primary flex size-10 items-center justify-center rounded-[9px] text-white disabled:opacity-50"
                            title="Enviar"
                        >
                            <Send className="size-4" />
                        </button>
                    </form>
                </div>
            ) : null}

            {abierto ? (
                <button
                    type="button"
                    onClick={() => setAbierto(false)}
                    aria-label="Cerrar la ayuda de Chispa"
                    className={`bg-primary fixed right-4 bottom-4 z-50 flex size-12 items-center justify-center rounded-full text-white shadow-lg transition-transform duration-150 ease-out active:scale-[0.97] ${conBarraInferior ? 'max-md:bottom-[calc(5rem+env(safe-area-inset-bottom))]' : ''}`}
                >
                    <X className="size-5" />
                </button>
            ) : (
                <button
                    type="button"
                    onClick={() => setAbierto(true)}
                    aria-label="Abrir la ayuda de Chispa"
                    data-globo={globo ? '' : undefined}
                    className={`chispa-boton focus-visible:outline-primary fixed right-3 bottom-2 z-50 block origin-bottom-right rounded-[18px] transition-transform duration-150 ease-out focus-visible:outline-2 focus-visible:outline-offset-2 active:scale-[0.97] max-sm:scale-[0.82] max-sm:active:scale-[0.8] ${conBarraInferior ? 'max-md:bottom-[calc(4.5rem+env(safe-area-inset-bottom))]' : ''}`}
                >
                    <span
                        aria-hidden
                        className="chispa-globo border-border bg-card text-foreground absolute right-[calc(100%-6px)] bottom-16 rounded-[16px] rounded-br-[4px] border px-4 py-2 text-[14.5px] font-bold whitespace-nowrap shadow-lg"
                    >
                        ¿Te ayudo?
                    </span>
                    <ChispaAvatar pose="saludo" size={96} />
                </button>
            )}
        </>
    );
}
