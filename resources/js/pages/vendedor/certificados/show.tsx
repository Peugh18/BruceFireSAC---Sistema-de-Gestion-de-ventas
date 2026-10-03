import { Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    ExternalLink,
    FileDown,
    FileText,
    History,
    PencilLine,
    Printer,
    Receipt,
    User,
} from 'lucide-react';
import { useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import VendedorLayout from '@/layouts/vendedor-layout';
import certificados from '@/routes/vendedor/certificados';
import clientes from '@/routes/vendedor/clientes';
import ventas from '@/routes/vendedor/ventas';
import type { Team } from '@/types';

type Certificate = {
    id: number;
    numero: string;
    revision: number;
    tipo: string | null;
    tipo_codigo: string | null;
    es_diploma: boolean;
    referencia: string | null;
    cliente: {
        id: number;
        razon_social: string;
        numero_documento: string | null;
    } | null;
    venta: {
        id: number;
        numero: string | null;
        comprobante: string | null;
    } | null;
    fecha_emision: string | null;
    fecha_vigencia_hasta: string | null;
    estado: string;
    verificar_url: string;
    corregir_url: string | null;
    unidades: {
        id: number;
        numero_cliente: string | null;
        serie: string | null;
        marca: string | null;
        capacidad: string | null;
        agente: string | null;
    }[];
    participants: {
        id: number;
        numero: string;
        nombres: string;
        dni: string | null;
        cargo: string | null;
    }[];
    revisiones: {
        numero: number;
        motivo: string;
        usuario: string | null;
        fecha: string | null;
    }[];
};

type Props = { certificate: Certificate };

function badgeClass(estado: string) {
    return estado?.toLowerCase() === 'vigente'
        ? 'bg-emerald-500/10 text-success-strong border border-emerald-500/20'
        : 'bg-destructive/10 text-destructive-strong border border-destructive/20';
}

function Dato({
    etiqueta,
    children,
}: {
    etiqueta: string;
    children: React.ReactNode;
}) {
    return (
        <div className="flex items-start justify-between gap-3 py-2 text-[12.5px]">
            <span className="text-muted-foreground shrink-0">{etiqueta}</span>
            <span className="text-right font-semibold">{children}</span>
        </div>
    );
}

export default function CertificadosShow({ certificate }: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug =
        currentTeam?.slug ??
        (typeof window !== 'undefined'
            ? window.location.pathname.split('/')[1]
            : '');
    const [participante, setParticipante] = useState<number | null>(null);

    const ruta = { current_team: teamSlug, certificate: certificate.id };
    const conParticipante = participante
        ? { participant: participante }
        : undefined;
    const pdfUrl = certificados.pdf.url(ruta, { query: conParticipante });
    const vistaUrl = certificados.pdf.url(ruta, {
        query: { inline: 1, ...conParticipante },
    });
    const wordUrl = certificados.word.url(ruta, { query: conParticipante });

    return (
        <VendedorLayout title="Detalle certificado">
            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap items-center gap-3">
                    <Button
                        asChild
                        variant="outline"
                        className="border-border bg-card text-foreground/80 h-9 rounded-[9px] shadow-none"
                    >
                        <Link href={certificados.index(teamSlug)}>
                            <ArrowLeft className="size-4" />
                            Certificados
                        </Link>
                    </Button>
                    <div className="min-w-0">
                        <h1 className="text-foreground font-['Oswald',sans-serif] text-[24px] font-semibold tracking-[0.02em] uppercase">
                            {certificate.numero}
                        </h1>
                        <p className="text-muted-foreground text-[12.5px]">
                            {certificate.tipo ?? certificate.tipo_codigo}
                            {certificate.revision > 0
                                ? ` · Revisión ${certificate.revision}`
                                : ''}
                        </p>
                    </div>
                    <div className="flex-1" />
                    <Badge
                        className={`rounded-full border-transparent px-3 py-1 text-[10.5px] font-bold capitalize ${badgeClass(certificate.estado)}`}
                    >
                        {certificate.estado}
                    </Badge>
                </div>

                <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_340px]">
                    <Card className="border-border bg-card gap-3 overflow-hidden rounded-[16px] p-3 shadow-none">
                        {certificate.participants.length > 0 ? (
                            <div className="flex flex-wrap items-center gap-1.5 px-1">
                                <span className="text-muted-foreground text-[11.5px] font-bold uppercase">
                                    Ver:
                                </span>
                                <button
                                    type="button"
                                    onClick={() => setParticipante(null)}
                                    className={`rounded-full border px-2.5 py-1 text-[11.5px] font-bold ${participante === null ? 'border-primary bg-primary text-primary-foreground' : 'border-border text-muted-foreground'}`}
                                >
                                    Todos
                                </button>
                                {certificate.participants.map((p) => (
                                    <button
                                        key={p.id}
                                        type="button"
                                        onClick={() => setParticipante(p.id)}
                                        className={`rounded-full border px-2.5 py-1 text-[11.5px] font-bold ${participante === p.id ? 'border-primary bg-primary text-primary-foreground' : 'border-border text-muted-foreground'}`}
                                    >
                                        {p.nombres}
                                    </button>
                                ))}
                            </div>
                        ) : null}
                        <iframe
                            key={vistaUrl}
                            src={`${vistaUrl}#navpanes=0&view=FitH`}
                            title={`Vista previa de ${certificate.numero}`}
                            className={`bg-muted w-full rounded-[10px] border-0 ${certificate.es_diploma ? 'aspect-[297/210]' : 'h-[78vh] min-h-[520px]'}`}
                        />
                    </Card>

                    <div className="flex flex-col gap-4">
                        <Card className="border-border bg-card gap-2 rounded-[16px] p-4 shadow-none">
                            <div className="grid grid-cols-2 gap-2">
                                <Button
                                    asChild
                                    className="h-9 rounded-[9px] font-bold shadow-none"
                                >
                                    <a href={pdfUrl}>
                                        <FileDown className="size-4" />
                                        PDF
                                    </a>
                                </Button>
                                <Button
                                    asChild
                                    variant="outline"
                                    className="h-9 rounded-[9px] shadow-none"
                                >
                                    <a
                                        href={vistaUrl}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <Printer className="size-4" />
                                        Imprimir
                                    </a>
                                </Button>
                                <Button
                                    asChild
                                    variant="outline"
                                    className="h-9 rounded-[9px] shadow-none"
                                >
                                    <a href={wordUrl}>
                                        <FileText className="size-4 text-[#2b579a]" />
                                        Word
                                    </a>
                                </Button>
                                <Button
                                    asChild
                                    variant="outline"
                                    className="h-9 rounded-[9px] shadow-none"
                                >
                                    <a
                                        href={certificate.verificar_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <ExternalLink className="size-4" />
                                        Ver QR
                                    </a>
                                </Button>
                            </div>
                            {certificate.corregir_url ? (
                                <Button
                                    asChild
                                    variant="outline"
                                    className="h-9 rounded-[9px] shadow-none"
                                >
                                    <Link href={certificate.corregir_url}>
                                        <PencilLine className="size-4" />
                                        Corregir datos
                                    </Link>
                                </Button>
                            ) : null}
                            {certificate.corregir_url ? (
                                <p className="text-muted-foreground text-[11px]">
                                    Al corregir se mantiene el mismo número y
                                    queda registrada la revisión.
                                </p>
                            ) : null}
                        </Card>

                        <Card className="border-border bg-card divide-border gap-0 divide-y rounded-[16px] px-4 py-2 shadow-none">
                            <Dato etiqueta="Cliente">
                                {certificate.cliente ? (
                                    <Link
                                        href={clientes.show.url({
                                            current_team: teamSlug,
                                            client: certificate.cliente.id,
                                        })}
                                        className="hover:text-primary-strong inline-flex items-center gap-1 hover:underline"
                                    >
                                        <User className="size-3.5" />
                                        {certificate.cliente.razon_social}
                                    </Link>
                                ) : (
                                    '-'
                                )}
                            </Dato>
                            {certificate.cliente?.numero_documento ? (
                                <Dato etiqueta="RUC / DNI">
                                    {certificate.cliente.numero_documento}
                                </Dato>
                            ) : null}
                            <Dato etiqueta="Venta">
                                {certificate.venta ? (
                                    <Link
                                        href={ventas.show.url({
                                            current_team: teamSlug,
                                            sale: certificate.venta.id,
                                        })}
                                        className="hover:text-primary-strong inline-flex items-center gap-1 hover:underline"
                                    >
                                        <Receipt className="size-3.5" />
                                        {certificate.venta.numero}
                                        {certificate.venta.comprobante
                                            ? ` · ${certificate.venta.comprobante}`
                                            : ''}
                                    </Link>
                                ) : (
                                    'Orden de servicio'
                                )}
                            </Dato>
                            {certificate.referencia ? (
                                <Dato etiqueta="Referencia">
                                    {certificate.referencia}
                                </Dato>
                            ) : null}
                            <Dato etiqueta="Emisión">
                                {certificate.fecha_emision ?? '-'}
                            </Dato>
                            <Dato etiqueta="Vigente hasta">
                                {certificate.fecha_vigencia_hasta ?? '-'}
                            </Dato>
                        </Card>

                        {certificate.unidades.length > 0 ? (
                            <Card className="border-border bg-card gap-2 rounded-[16px] p-4 shadow-none">
                                <h2 className="text-[12px] font-bold uppercase">
                                    Extintores ({certificate.unidades.length})
                                </h2>
                                <div className="flex max-h-[260px] flex-col gap-1.5 overflow-y-auto">
                                    {certificate.unidades.map((unidad) => (
                                        <div
                                            key={unidad.id}
                                            className="bg-muted/50 flex items-center justify-between gap-2 rounded-[8px] px-2.5 py-1.5 text-[11.5px]"
                                        >
                                            <span className="font-['IBM_Plex_Mono',monospace] font-bold">
                                                {unidad.numero_cliente
                                                    ? `${unidad.numero_cliente} · `
                                                    : ''}
                                                {unidad.serie ?? '-'}
                                            </span>
                                            <span className="text-muted-foreground truncate">
                                                {[
                                                    unidad.capacidad,
                                                    unidad.agente,
                                                    unidad.marca,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' · ')}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </Card>
                        ) : null}

                        {certificate.revisiones.length > 0 ? (
                            <Card className="border-border bg-card gap-2 rounded-[16px] p-4 shadow-none">
                                <h2 className="inline-flex items-center gap-1.5 text-[12px] font-bold uppercase">
                                    <History className="size-3.5" />
                                    Correcciones
                                </h2>
                                {certificate.revisiones.map((revision) => (
                                    <div
                                        key={revision.numero}
                                        className="text-[11.5px]"
                                    >
                                        <span className="font-bold">
                                            Rev. {revision.numero}
                                        </span>{' '}
                                        · {revision.motivo}
                                        <div className="text-muted-foreground">
                                            {revision.fecha}
                                            {revision.usuario
                                                ? ` · ${revision.usuario}`
                                                : ''}
                                        </div>
                                    </div>
                                ))}
                            </Card>
                        ) : null}
                    </div>
                </div>
            </div>
        </VendedorLayout>
    );
}
