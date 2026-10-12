import { router } from '@inertiajs/react';
import { Inbox, MessageCircleQuestion } from 'lucide-react';

import { Empty } from '@/components/ui/empty';
import GerenteLayout from '@/layouts/gerente-layout';

type Pregunta = {
    id: number;
    fecha: string | null;
    rol: string;
    pagina: string | null;
    pregunta: string;
    respuesta: string;
    motivo: string;
};

type Props = {
    preguntas: {
        data: Pregunta[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    resumen: {
        total: number;
        utiles: number;
        no_utiles: number;
        sin_respuesta: number;
    };
};

const CIFRAS: { clave: keyof Props['resumen']; texto: string }[] = [
    { clave: 'total', texto: 'Preguntas hechas' },
    { clave: 'utiles', texto: 'Le sirvieron' },
    { clave: 'no_utiles', texto: 'No le sirvieron' },
    { clave: 'sin_respuesta', texto: 'Sin respuesta en el manual' },
];

export default function ChispaIndex({ preguntas, resumen }: Props) {
    return (
        <GerenteLayout title="Chispa">
            <div className="space-y-6">
                <div>
                    <h1 className="text-foreground font-['Oswald',sans-serif] text-2xl font-bold tracking-wide uppercase">
                        Preguntas a Chispa
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Lo que Chispa no supo responder o no le sirvió al
                        usuario. De aquí salen las secciones que faltan en los
                        manuales. Se guardan 90 días y sin claves ni tarjetas.
                    </p>
                </div>

                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    {CIFRAS.map(({ clave, texto }) => (
                        <div
                            key={clave}
                            className="border-border bg-card rounded-xl border p-4 shadow-xs"
                        >
                            <div className="text-muted-foreground text-xs font-medium uppercase">
                                {texto}
                            </div>
                            <div className="text-foreground mt-2 font-['IBM_Plex_Mono',monospace] text-2xl font-bold">
                                {resumen[clave]}
                            </div>
                        </div>
                    ))}
                </div>

                {preguntas.data.length === 0 ? (
                    <Empty
                        icon={<Inbox />}
                        title="Chispa respondió todo lo que le preguntaron."
                    />
                ) : (
                    <ul className="space-y-3">
                        {preguntas.data.map((p) => (
                            <li
                                key={p.id}
                                className="border-border bg-card rounded-xl border p-4 shadow-xs"
                            >
                                <div className="text-muted-foreground flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                                    <span className="bg-muted text-warning-strong rounded-full px-2 py-0.5 font-semibold">
                                        {p.motivo}
                                    </span>
                                    <span>{p.rol}</span>
                                    {p.pagina ? <span>{p.pagina}</span> : null}
                                    <span>{p.fecha}</span>
                                </div>
                                <p className="text-foreground mt-2 flex gap-2 text-sm font-medium">
                                    <MessageCircleQuestion className="mt-0.5 size-4 shrink-0" />
                                    {p.pregunta}
                                </p>
                                <p className="text-muted-foreground mt-2 text-sm whitespace-pre-line">
                                    {p.respuesta}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}

                {preguntas.links.length > 3 && (
                    <div className="flex flex-wrap items-center gap-1">
                        {preguntas.links.map((link, i) => (
                            <button
                                key={i}
                                type="button"
                                disabled={!link.url}
                                onClick={() =>
                                    link.url &&
                                    router.get(
                                        link.url,
                                        {},
                                        { preserveState: true },
                                    )
                                }
                                dangerouslySetInnerHTML={{ __html: link.label }}
                                className={`min-h-9 rounded-md px-3 text-xs transition-colors disabled:opacity-40 ${
                                    link.active
                                        ? 'bg-foreground text-background font-bold'
                                        : 'text-foreground/80 hover:bg-muted/40'
                                }`}
                            />
                        ))}
                    </div>
                )}
            </div>
        </GerenteLayout>
    );
}
