import * as Dialog from '@radix-ui/react-dialog';
import { router, usePage } from '@inertiajs/react';
import { Mic, RotateCcw, Send, Square, Volume2, X } from 'lucide-react';
import {
    FormEvent,
    Fragment,
    ReactNode,
    useEffect,
    useRef,
    useState,
} from 'react';

import ChispaAvatar from '@/components/chispa-avatar';
import ChispaSprite, { type ChispaEstado } from '@/components/chispa-sprite';
import { leerCookie } from '@/lib/cookies';
import { asistente } from '@/routes';
import { precio as confirmarPrecio, valorar } from '@/routes/asistente';

/** Borrador que preparó Chispa: se abre la pantalla llenada o se confirma. */
type Accion = {
    tipo: 'abrir' | 'confirmar';
    etiqueta: string;
    url: string;
    titulo: string;
    subtitulo: string | null;
    lineas: { texto: string; monto: number | null; cantidad?: number }[];
    total: number | null;
    avisos: string[];
    hecha?: boolean;
};

type Mensaje = {
    role: 'user' | 'assistant';
    content: string;
    /** Registro de la respuesta, para marcar si sirvió. */
    id?: number;
    util?: boolean;
    acciones?: Accion[];
};

/** Reconocimiento de voz del navegador (Chrome y Edge). */
type Reconocimiento = {
    lang: string;
    interimResults: boolean;
    continuous: boolean;
    start: () => void;
    stop: () => void;
    onresult:
        | ((e: {
              results: ArrayLike<ArrayLike<{ transcript: string }>>;
          }) => void)
        | null;
    onend: (() => void) | null;
    onerror: (() => void) | null;
};

function crearReconocimiento(): Reconocimiento | null {
    if (typeof window === 'undefined') return null;
    const w = window as unknown as {
        SpeechRecognition?: new () => Reconocimiento;
        webkitSpeechRecognition?: new () => Reconocimiento;
    };
    const Clase = w.SpeechRecognition ?? w.webkitSpeechRecognition;
    return Clase ? new Clase() : null;
}

function soles(monto: number) {
    return `S/ ${monto.toFixed(2)}`;
}

/** Lee en voz alta, sin los asteriscos de las negritas. */
function hablar(texto: string) {
    if (typeof window === 'undefined' || !window.speechSynthesis) return;
    window.speechSynthesis.cancel();
    const voz = new SpeechSynthesisUtterance(texto.replaceAll('**', ''));
    voz.lang = 'es-PE';
    window.speechSynthesis.speak(voz);
}

const CLAVE = 'chispa-conversacion';
/** El globo "¿Te ayudo?" se muestra solo una vez por sesión; después, al pasar el mouse. */
const CLAVE_GLOBO = 'chispa-globo-visto';

const SUGERENCIAS: Record<string, string[]> = {
    vendedor: [
        'Hazme una boleta al DNI 40405210 con un extintor PQS de 6 kg',
        '¿Cuánto me debe ANAHI?',
        '¿Cómo hago una venta a crédito?',
    ],
    gerente: [
        '¿Qué tengo que atender hoy?',
        '¿Quién me debe más?',
        '¿Cuánto vendimos este mes?',
    ],
    almacen: [
        'Llegaron 10 guantes de seguridad de Extintores del Norte',
        '¿Qué productos están bajo el mínimo?',
        '¿Cuánto stock hay de PQS 6 kg?',
    ],
    tecnico: [
        '¿Qué necesito para cerrar una entrega?',
        '¿Cómo registro una deficiencia?',
    ],
};

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
    const { url, props } = usePage();
    // Cada usuario tiene su conversación: al cambiar de cuenta no se ve la del otro.
    const clave = `${CLAVE}-${props.auth?.user?.id ?? 'anonimo'}`;
    const enOrden =
        url.includes('/ordenes-servicio/') ||
        url.includes('/tecnico-planta/ordenes/');
    const [abierto, setAbierto] = useState(false);
    const [mensajes, setMensajes] = useState<Mensaje[]>(() => {
        try {
            return JSON.parse(
                sessionStorage.getItem(clave) ?? '[]',
            ) as Mensaje[];
        } catch {
            return [];
        }
    });
    const [texto, setTexto] = useState('');
    const [pensando, setPensando] = useState(false);
    const [gesto, setGesto] = useState<ChispaEstado>('reposo');
    const [globo, setGlobo] = useState(false);
    const [escuchando, setEscuchando] = useState(false);
    const [dictado, setDictado] = useState('');
    const [conVoz, setConVoz] = useState(false);
    const reconocimiento = useRef<Reconocimiento | null>(null);
    const finRef = useRef<HTMLDivElement>(null);

    useEffect(() => setConVoz(crearReconocimiento() !== null), []);
    /** En celular los técnicos tienen una barra inferior fija: Chispa va encima. */
    const conBarraInferior = rol === 'tecnico';

    useEffect(() => {
        if (gesto === 'reposo' || pensando) return;
        const temporizador = setTimeout(() => setGesto('reposo'), 1800);
        return () => clearTimeout(temporizador);
    }, [gesto, pensando]);

    const cambiarAbierto = (valor: boolean) => {
        setAbierto(valor);
        if (valor && !pensando) setGesto('saludo');
    };

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
            sessionStorage.setItem(clave, JSON.stringify(mensajes.slice(-20)));
        } catch {
            // Sin almacenamiento disponible: la conversación dura solo en memoria.
        }
        finRef.current?.scrollIntoView({
            behavior: window.matchMedia('(prefers-reduced-motion: reduce)')
                .matches
                ? 'instant'
                : 'smooth',
        });
    }, [mensajes, abierto, clave]);

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
        setGesto('reposo');

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
                id?: number;
                acciones?: Accion[];
            };
            const respuestaValida =
                response.ok &&
                typeof data.respuesta === 'string' &&
                data.respuesta.trim().length > 0;
            setGesto(respuestaValida ? 'revisando' : 'error');

            setMensajes([
                ...historial,
                {
                    role: 'assistant',
                    id: respuestaValida ? data.id : undefined,
                    acciones: respuestaValida ? data.acciones : undefined,
                    content:
                        (respuestaValida ? data.respuesta : undefined) ??
                        (response.status === 429
                            ? 'Hiciste muchas preguntas seguidas. Espera un minuto y vuelve a intentar.'
                            : 'No pude responder ahora. Intenta de nuevo.'),
                },
            ]);
        } catch {
            setGesto('error');
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

    const marcar = async (indice: number, util: boolean) => {
        const id = mensajes[indice]?.id;

        if (!id) {
            return;
        }

        setMensajes((actuales) =>
            actuales.map((m, i) => (i === indice ? { ...m, util } : m)),
        );

        try {
            await fetch(valorar.url(id), {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': leerCookie('XSRF-TOKEN'),
                },
                body: JSON.stringify({ util }),
            });
        } catch {
            // Si no hay conexión, la marca queda solo en pantalla.
        }
    };

    const escuchar = () => {
        if (escuchando) {
            reconocimiento.current?.stop();
            return;
        }
        const r = crearReconocimiento();
        if (!r) return;
        let final = '';
        r.lang = 'es-PE';
        r.interimResults = true;
        r.continuous = false;
        r.onresult = (e) => {
            final = Array.from(e.results)
                .map((res) => res[0]?.transcript ?? '')
                .join(' ');
            setDictado(final);
        };
        r.onerror = () => setEscuchando(false);
        r.onend = () => {
            setEscuchando(false);
            setDictado('');
            if (final.trim()) void preguntar(final);
        };
        reconocimiento.current = r;
        setDictado('');
        setEscuchando(true);
        r.start();
    };

    const usarAccion = async (indice: number, accion: Accion) => {
        if (accion.tipo === 'abrir') {
            setAbierto(false);
            router.visit(accion.url);
            return;
        }
        try {
            const response = await fetch(confirmarPrecio.url(), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': leerCookie('XSRF-TOKEN'),
                },
            });
            const data = (await response.json()) as {
                mensaje?: string;
                message?: string;
            };
            setMensajes((actuales) => [
                ...actuales.map((m, i) =>
                    i === indice
                        ? {
                              ...m,
                              acciones: m.acciones?.map((a) =>
                                  a === accion
                                      ? { ...a, hecha: response.ok }
                                      : a,
                              ),
                          }
                        : m,
                ),
                {
                    role: 'assistant' as const,
                    content:
                        data.mensaje ??
                        data.message ??
                        'No pude aplicar el cambio. Pídemelo de nuevo.',
                },
            ]);
        } catch {
            setMensajes((actuales) => [
                ...actuales,
                {
                    role: 'assistant',
                    content: 'No hay conexión. Intenta de nuevo en un momento.',
                },
            ]);
        }
    };

    const enviar = (event: FormEvent) => {
        event.preventDefault();
        void preguntar(texto);
    };

    return (
        <Dialog.Root open={abierto} onOpenChange={cambiarAbierto}>
            <Dialog.Portal>
                <Dialog.Overlay className="fixed inset-0 z-50 bg-black/20" />
                <Dialog.Content
                    className={`border-border bg-card fixed right-4 ${enOrden ? 'bottom-48' : 'bottom-24'} z-50 flex max-h-[min(560px,calc(100dvh-7rem))] w-[min(380px,calc(100vw-2rem))] flex-col overflow-hidden rounded-2xl border shadow-2xl ${conBarraInferior ? `${enOrden ? 'max-md:bottom-[calc(14rem+env(safe-area-inset-bottom))]' : 'max-md:bottom-[calc(9rem+env(safe-area-inset-bottom))]'} max-md:max-h-[calc(100dvh-15rem)]` : ''}`}
                >
                    <div className="border-border flex items-center gap-2.5 border-b px-4 py-4">
                        <span className="bg-muted flex size-10 shrink-0 items-center justify-center rounded-xl">
                            <ChispaAvatar size={32} pose="busto" />
                        </span>
                        <div className="flex-1">
                            <Dialog.Title className="text-[15px] font-semibold">
                                Chispa
                            </Dialog.Title>
                            <Dialog.Description className="text-muted-foreground text-xs">
                                Háblame o escríbeme: consulto y te dejo todo
                                listo
                            </Dialog.Description>
                        </div>
                        {mensajes.length > 0 ? (
                            <button
                                type="button"
                                onClick={() => setMensajes([])}
                                aria-label="Nueva conversación"
                                className="hover:bg-muted flex size-9 items-center justify-center rounded-lg"
                            >
                                <RotateCcw className="size-4" />
                            </button>
                        ) : null}
                        <button
                            type="button"
                            onClick={() => setAbierto(false)}
                            aria-label="Cerrar ayuda de Chispa"
                            className="hover:bg-muted flex size-9 items-center justify-center rounded-lg"
                        >
                            <X className="size-4" />
                        </button>
                    </div>

                    <div
                        role="log"
                        aria-label="Conversación con Chispa"
                        className="flex-1 space-y-2.5 overflow-y-auto p-4 text-[13px]"
                    >
                        {mensajes.length === 0 ? (
                            <div className="space-y-2">
                                <div className="flex items-center gap-3">
                                    <ChispaSprite
                                        size={72}
                                        estado={pensando ? 'pensando' : gesto}
                                        repetir={gesto === 'reposo'}
                                        className="shrink-0"
                                    />
                                    <p className="text-muted-foreground">
                                        ¡Hola! Soy Chispa. Dime qué necesitas y
                                        te dejo la pantalla llenada para que la
                                        revises. Por ejemplo:
                                    </p>
                                </div>
                                {SUGERENCIAS[rol].map((s) => (
                                    <button
                                        key={s}
                                        type="button"
                                        onClick={() => void preguntar(s)}
                                        className="border-border hover:bg-muted/50 block w-full rounded-lg border px-3 py-2 text-left text-[12.5px]"
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
                                    className={`max-w-[88%] rounded-2xl px-3 py-2 leading-relaxed ${m.role === 'user' ? 'bg-primary text-white' : 'bg-muted text-foreground'}`}
                                >
                                    <Formato texto={m.content} />
                                    {m.acciones?.map((a) => (
                                        <div
                                            key={a.url + a.titulo}
                                            className="border-border bg-card mt-2 space-y-1.5 rounded-xl border p-3"
                                        >
                                            <p className="text-primary-strong text-[11px] font-bold tracking-wide uppercase">
                                                {a.titulo}
                                            </p>
                                            {a.subtitulo ? (
                                                <p className="font-semibold">
                                                    {a.subtitulo}
                                                </p>
                                            ) : null}
                                            {a.lineas.map((l) => (
                                                <div
                                                    key={l.texto}
                                                    className="flex justify-between gap-2"
                                                >
                                                    <span>{l.texto}</span>
                                                    <b>
                                                        {l.monto !== null
                                                            ? soles(l.monto)
                                                            : (l.cantidad ??
                                                              '')}
                                                    </b>
                                                </div>
                                            ))}
                                            {a.total !== null ? (
                                                <div className="border-border flex justify-between border-t pt-1.5 font-bold">
                                                    <span>Total</span>
                                                    <span>
                                                        {soles(a.total)}
                                                    </span>
                                                </div>
                                            ) : null}
                                            {a.avisos.map((aviso) => (
                                                <p
                                                    key={aviso}
                                                    className="text-warning-strong text-[12px]"
                                                >
                                                    {aviso}
                                                </p>
                                            ))}
                                            <button
                                                type="button"
                                                disabled={a.hecha}
                                                onClick={() =>
                                                    void usarAccion(i, a)
                                                }
                                                className="bg-primary min-h-11 w-full rounded-lg px-3 text-[13px] font-bold text-white transition hover:brightness-95 disabled:opacity-50"
                                            >
                                                {a.hecha ? 'Hecho' : a.etiqueta}
                                            </button>
                                        </div>
                                    ))}
                                    {m.role === 'assistant' ? (
                                        <button
                                            type="button"
                                            onClick={() => hablar(m.content)}
                                            aria-label="Escuchar la respuesta"
                                            className="text-muted-foreground hover:text-foreground mt-1 inline-flex min-h-9 items-center gap-1 rounded-md px-1 text-[12px] font-medium"
                                        >
                                            <Volume2 className="size-3.5" />
                                            Escuchar
                                        </button>
                                    ) : null}
                                    {m.role === 'assistant' && m.id ? (
                                        <div className="mt-1.5 flex items-center gap-1 text-[12px]">
                                            {m.util === undefined ? (
                                                <>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            void marcar(i, true)
                                                        }
                                                        className="text-muted-foreground hover:text-foreground min-h-9 rounded-md px-2 font-medium underline-offset-2 hover:underline"
                                                    >
                                                        Me sirvió
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            void marcar(
                                                                i,
                                                                false,
                                                            )
                                                        }
                                                        className="text-muted-foreground hover:text-foreground min-h-9 rounded-md px-2 font-medium underline-offset-2 hover:underline"
                                                    >
                                                        No me sirvió
                                                    </button>
                                                </>
                                            ) : (
                                                <span className="text-muted-foreground px-2">
                                                    {m.util
                                                        ? 'Gracias. Me alegra haber ayudado.'
                                                        : 'Gracias. El Gerente verá tu duda para mejorar la ayuda.'}
                                                </span>
                                            )}
                                        </div>
                                    ) : null}
                                </div>
                            </div>
                        ))}
                        {pensando ||
                        gesto === 'revisando' ||
                        gesto === 'error' ? (
                            <div
                                className="text-muted-foreground flex items-center gap-2 text-[12px]"
                                role="status"
                            >
                                <ChispaSprite
                                    size={64}
                                    estado={pensando ? 'pensando' : gesto}
                                    repetir={pensando}
                                />
                                {pensando
                                    ? 'Chispa está pensando…'
                                    : gesto === 'revisando'
                                      ? 'Respuesta lista'
                                      : 'No pude responder ahora'}
                            </div>
                        ) : null}
                        {escuchando ? (
                            <div
                                role="status"
                                className="border-primary/30 bg-primary/5 rounded-xl border p-3 text-center"
                            >
                                <p className="font-bold">Te escucho…</p>
                                <p className="text-muted-foreground mt-1">
                                    {dictado || 'Habla con normalidad.'}
                                </p>
                            </div>
                        ) : null}
                        <div ref={finRef} />
                    </div>

                    <form
                        onSubmit={enviar}
                        className="border-border flex gap-2 border-t p-2.5"
                    >
                        <input
                            aria-label="Tu pregunta para Chispa"
                            value={texto}
                            onChange={(e) => setTexto(e.target.value)}
                            maxLength={500}
                            placeholder="Escribe o habla…"
                            className="border-border bg-muted/40 focus-visible:border-ring focus-visible:ring-ring/50 h-10 min-w-0 flex-1 rounded-lg border px-3 text-[13px] outline-none focus-visible:ring-[3px]"
                        />
                        {conVoz ? (
                            <button
                                type="button"
                                onClick={escuchar}
                                disabled={pensando}
                                aria-label={
                                    escuchando
                                        ? 'Dejar de escuchar'
                                        : 'Hablar con Chispa'
                                }
                                aria-pressed={escuchando}
                                className={`flex size-10 items-center justify-center rounded-lg border transition disabled:opacity-50 ${escuchando ? 'bg-primary border-primary text-white' : 'border-border hover:bg-muted'}`}
                            >
                                {escuchando ? (
                                    <Square className="size-4" />
                                ) : (
                                    <Mic className="size-4" />
                                )}
                            </button>
                        ) : null}
                        <button
                            type="submit"
                            disabled={pensando || !texto.trim()}
                            className="bg-primary flex size-10 items-center justify-center rounded-lg text-white transition hover:brightness-95 disabled:opacity-50"
                            aria-label="Enviar pregunta"
                        >
                            <Send className="size-4" />
                        </button>
                    </form>
                </Dialog.Content>
            </Dialog.Portal>
            <Dialog.Trigger asChild>
                <button
                    type="button"
                    aria-label="Abrir la ayuda de Chispa"
                    data-globo={globo && !abierto ? '' : undefined}
                    className={`bf-chispa-launcher chispa-boton border-border bg-card fixed right-5 ${enOrden ? 'bottom-28' : 'bottom-5'} z-40 flex size-16 items-center justify-center rounded-2xl border shadow-lg max-md:right-3 max-md:size-11 ${conBarraInferior ? 'max-md:bottom-[calc(9rem+env(safe-area-inset-bottom))]' : ''}`}
                >
                    <span
                        aria-hidden
                        className="chispa-globo border-border bg-card text-foreground absolute right-[calc(100%+10px)] bottom-4 rounded-xl border px-3 py-2 text-xs font-medium whitespace-nowrap"
                    >
                        ¿Te ayudo?
                    </span>
                    <ChispaSprite
                        estado={pensando ? 'pensando' : gesto}
                        repetir={gesto === 'reposo' || pensando}
                    />
                </button>
            </Dialog.Trigger>
        </Dialog.Root>
    );
}
