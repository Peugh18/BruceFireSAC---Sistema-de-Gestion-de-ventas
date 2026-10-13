import { FileCode2, FileText, ShieldCheck } from 'lucide-react';

import facturacion from '@/routes/vendedor/facturacion';

/**
 * Imprimir, XML y CDR de un comprobante (factura, boleta o nota). El CDR es
 * la constancia de SUNAT: solo existe cuando SUNAT ya lo aceptó.
 */
export default function ArchivosNota({
    teamSlug,
    id,
    aceptada,
}: {
    teamSlug: string;
    id: number;
    aceptada: boolean;
}) {
    const args = { current_team: teamSlug, electronic_document: id };
    const enlace =
        'border-border text-foreground hover:bg-muted inline-flex min-h-9 items-center gap-1.5 rounded-lg border px-3 text-xs font-semibold transition-colors';

    return (
        <div className="flex flex-wrap gap-1.5">
            <a
                href={facturacion.pdf.url(args)}
                target="_blank"
                rel="noreferrer"
                className={enlace}
            >
                <FileText className="size-3.5" />
                Imprimir
            </a>
            <a href={facturacion.xml.url(args)} className={enlace}>
                <FileCode2 className="size-3.5" />
                XML
            </a>
            {aceptada ? (
                <a href={facturacion.cdr.url(args)} className={enlace}>
                    <ShieldCheck className="size-3.5" />
                    CDR
                </a>
            ) : null}
        </div>
    );
}
