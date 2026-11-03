import { Card } from '@/components/ui/card';

export type DocumentoRelacionado = {
    tipo: string;
    numero: string;
    estado: string | null;
    url: string;
};

const ESTADOS: Record<string, string> = {
    por_enviar: 'Por enviar',
    pendiente: 'Pendiente',
    aceptado: 'Aceptado',
    aceptada: 'Aceptada',
    observado: 'Observado',
    rechazado: 'Rechazado',
    rechazada: 'Rechazada',
    borrador: 'Borrador',
    enviada: 'Enviada',
};

function etiquetaEstado(estado: string) {
    return ESTADOS[estado] ?? estado.replaceAll('_', ' ');
}

export function DocumentosRelacionados({
    documentos,
}: {
    documentos: DocumentoRelacionado[];
}) {
    if (documentos.length === 0) {
        return null;
    }

    return (
        <Card
            id="documentos-de-la-venta"
            className="border-border bg-card scroll-mt-4 gap-3 rounded-2xl p-5 shadow-none"
        >
            <h2 className="font-['Oswald',sans-serif] text-[18px] font-semibold uppercase">
                Documentos relacionados
            </h2>
            <ul className="divide-border divide-y">
                {documentos.map((documento) => (
                    <li
                        key={`${documento.tipo}-${documento.numero}`}
                        className="flex flex-wrap items-center gap-x-3 gap-y-1 py-2.5 text-[13px]"
                    >
                        <span className="text-muted-foreground w-36 shrink-0">
                            {documento.tipo}
                        </span>
                        <a
                            href={documento.url}
                            className="hover:text-primary-strong font-['IBM_Plex_Mono',monospace] font-bold hover:underline"
                        >
                            {documento.numero}
                        </a>
                        {documento.estado ? (
                            <span className="bg-muted ml-auto rounded-full px-2.5 py-0.5 text-xs font-bold">
                                {etiquetaEstado(documento.estado)}
                            </span>
                        ) : null}
                    </li>
                ))}
            </ul>
        </Card>
    );
}
