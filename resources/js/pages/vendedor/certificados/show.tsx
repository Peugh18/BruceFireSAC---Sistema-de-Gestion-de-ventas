import { Link, useForm, usePage } from '@inertiajs/react';
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
    Wrench,
} from 'lucide-react';
import { useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
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
    corregir_datos_url: string | null;
    direccion: string | null;
    unidades: {
        id: number;
        numero_cliente: string | null;
        serie: string | null;
        marca: string | null;
        capacidad: string | null;
        agente: string | null;
        anio_fabricacion: string | number | null;
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

type ErroresCorreccion = {
    motivo?: string;
    certificado?: string;
};

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

function FormularioCorreccion({
    certificate,
    onGuardado,
}: {
    certificate: Certificate;
    onGuardado: () => void;
}) {
    const form = useForm({
        direccion: certificate.direccion ?? '',
        motivo: '',
        unidades: certificate.unidades.map((unidad) => ({
            id: unidad.id,
            numero_serie: unidad.serie ?? '',
            marca: unidad.marca ?? '',
            capacidad: unidad.capacidad ?? '',
            tipo_agente: unidad.agente ?? '',
            anio_fabricacion: unidad.anio_fabricacion ?? '',
        })),
    });
    const errores = form.errors as ErroresCorreccion;

    const actualizarUnidad = (
        id: number,
        campo: keyof (typeof form.data.unidades)[number],
        valor: string,
    ) => {
        form.setData(
            'unidades',
            form.data.unidades.map((unidad) =>
                unidad.id === id ? { ...unidad, [campo]: valor } : unidad,
            ),
        );
    };

    const guardar = (evento: React.FormEvent<HTMLFormElement>) => {
        evento.preventDefault();

        form.patch(certificate.corregir_datos_url!, {
            preserveScroll: true,
            onSuccess: onGuardado,
        });
    };

    return (
        <form onSubmit={guardar} className="flex flex-col gap-4">
            <div className="grid gap-2">
                <Label htmlFor="correccion-direccion">Dirección</Label>
                <Input
                    id="correccion-direccion"
                    value={form.data.direccion}
                    onChange={(evento) =>
                        form.setData('direccion', evento.target.value)
                    }
                />
            </div>

            <fieldset className="flex flex-col gap-3">
                <legend className="text-sm font-semibold">Extintores</legend>
                {form.data.unidades.map((unidad, indice) => (
                    <div
                        key={unidad.id}
                        className="bg-muted/40 grid gap-3 rounded-xl border p-3 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <div className="sm:col-span-2 lg:col-span-3">
                            <p className="text-sm font-semibold">
                                Extintor {indice + 1}
                            </p>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor={`unidad-${unidad.id}-serie`}>
                                Serie
                            </Label>
                            <Input
                                id={`unidad-${unidad.id}-serie`}
                                value={unidad.numero_serie}
                                onChange={(evento) =>
                                    actualizarUnidad(
                                        unidad.id,
                                        'numero_serie',
                                        evento.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor={`unidad-${unidad.id}-marca`}>
                                Marca
                            </Label>
                            <Input
                                id={`unidad-${unidad.id}-marca`}
                                value={unidad.marca}
                                onChange={(evento) =>
                                    actualizarUnidad(
                                        unidad.id,
                                        'marca',
                                        evento.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor={`unidad-${unidad.id}-capacidad`}>
                                Capacidad
                            </Label>
                            <Input
                                id={`unidad-${unidad.id}-capacidad`}
                                value={unidad.capacidad}
                                onChange={(evento) =>
                                    actualizarUnidad(
                                        unidad.id,
                                        'capacidad',
                                        evento.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor={`unidad-${unidad.id}-agente`}>
                                Agente
                            </Label>
                            <Input
                                id={`unidad-${unidad.id}-agente`}
                                value={unidad.tipo_agente}
                                onChange={(evento) =>
                                    actualizarUnidad(
                                        unidad.id,
                                        'tipo_agente',
                                        evento.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label
                                htmlFor={`unidad-${unidad.id}-anio-fabricacion`}
                            >
                                Año de fabricación
                            </Label>
                            <Input
                                id={`unidad-${unidad.id}-anio-fabricacion`}
                                inputMode="numeric"
                                value={unidad.anio_fabricacion}
                                onChange={(evento) =>
                                    actualizarUnidad(
                                        unidad.id,
                                        'anio_fabricacion',
                                        evento.target.value,
                                    )
                                }
                            />
                        </div>
                    </div>
                ))}
            </fieldset>

            {errores.certificado ? (
                <p role="alert" className="text-destructive text-sm">
                    {errores.certificado}
                </p>
            ) : null}

            <div className="grid gap-2">
                <Label htmlFor="correccion-motivo">
                    ¿Qué se corrige? <span aria-hidden="true">*</span>
                </Label>
                <Textarea
                    id="correccion-motivo"
                    value={form.data.motivo}
                    onChange={(evento) =>
                        form.setData('motivo', evento.target.value)
                    }
                    required
                    aria-invalid={Boolean(errores.motivo)}
                    aria-describedby={
                        errores.motivo ? 'correccion-motivo-error' : undefined
                    }
                />
                {errores.motivo ? (
                    <p
                        id="correccion-motivo-error"
                        role="alert"
                        className="text-destructive text-sm"
                    >
                        {errores.motivo}
                    </p>
                ) : null}
            </div>

            <DialogFooter>
                <Button type="submit" disabled={form.processing}>
                    {form.processing ? 'Guardando…' : 'Guardar cambios'}
                </Button>
            </DialogFooter>
        </form>
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
    const [corrigiendoDatos, setCorrigiendoDatos] = useState(false);

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
                        className="border-border bg-card text-foreground/80 h-9 rounded-lg shadow-none"
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
                        className={`rounded-full border-transparent px-3 py-1 text-xs font-bold capitalize ${badgeClass(certificate.estado)}`}
                    >
                        {certificate.estado}
                    </Badge>
                </div>

                <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_340px]">
                    <Card className="border-border bg-card gap-3 overflow-hidden rounded-2xl p-3 shadow-none">
                        {certificate.participants.length > 0 ? (
                            <div className="flex flex-wrap items-center gap-1.5 px-1">
                                <span className="text-muted-foreground text-xs font-bold uppercase">
                                    Ver:
                                </span>
                                <button
                                    type="button"
                                    onClick={() => setParticipante(null)}
                                    className={`rounded-full border px-2.5 py-1 text-xs font-bold ${participante === null ? 'border-primary bg-primary text-primary-foreground' : 'border-border text-muted-foreground'}`}
                                >
                                    Todos
                                </button>
                                {certificate.participants.map((p) => (
                                    <button
                                        key={p.id}
                                        type="button"
                                        onClick={() => setParticipante(p.id)}
                                        className={`rounded-full border px-2.5 py-1 text-xs font-bold ${participante === p.id ? 'border-primary bg-primary text-primary-foreground' : 'border-border text-muted-foreground'}`}
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
                            className={`bg-muted w-full rounded-lg border-0 ${certificate.es_diploma ? 'aspect-[297/210]' : 'h-[78vh] min-h-[520px]'}`}
                        />
                    </Card>

                    <div className="flex flex-col gap-4">
                        <Card className="border-border bg-card gap-2 rounded-2xl p-4 shadow-none">
                            <div className="grid grid-cols-2 gap-2">
                                <Button
                                    asChild
                                    className="h-9 rounded-lg font-bold shadow-none"
                                >
                                    <a href={pdfUrl}>
                                        <FileDown className="size-4" />
                                        PDF
                                    </a>
                                </Button>
                                <Button
                                    asChild
                                    variant="outline"
                                    className="h-9 rounded-lg shadow-none"
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
                                    className="h-9 rounded-lg shadow-none"
                                >
                                    <a href={wordUrl}>
                                        <FileText className="size-4 text-[#2b579a]" />
                                        Word
                                    </a>
                                </Button>
                                <Button
                                    asChild
                                    variant="outline"
                                    className="h-9 rounded-lg shadow-none"
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
                                    className="h-9 rounded-lg shadow-none"
                                >
                                    <Link href={certificate.corregir_url}>
                                        <PencilLine className="size-4" />
                                        Corregir datos
                                    </Link>
                                </Button>
                            ) : null}
                            {certificate.corregir_datos_url ? (
                                <Button
                                    variant="outline"
                                    className="h-9 rounded-lg shadow-none"
                                    onClick={() => setCorrigiendoDatos(true)}
                                >
                                    <Wrench className="size-4" />
                                    Corregir datos
                                </Button>
                            ) : null}
                            {certificate.corregir_url ? (
                                <p className="text-muted-foreground text-xs">
                                    Al corregir se mantiene el mismo número y
                                    queda registrada la revisión.
                                </p>
                            ) : null}
                        </Card>

                        <Card className="border-border bg-card divide-border gap-0 divide-y rounded-2xl px-4 py-2 shadow-none">
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
                            <Card className="border-border bg-card gap-2 rounded-2xl p-4 shadow-none">
                                <h2 className="text-[12px] font-bold uppercase">
                                    Extintores ({certificate.unidades.length})
                                </h2>
                                <div className="flex max-h-[260px] flex-col gap-1.5 overflow-y-auto">
                                    {certificate.unidades.map((unidad) => (
                                        <div
                                            key={unidad.id}
                                            className="bg-muted/50 flex items-center justify-between gap-2 rounded-lg px-2.5 py-1.5 text-xs"
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
                            <Card className="border-border bg-card gap-2 rounded-2xl p-4 shadow-none">
                                <h2 className="inline-flex items-center gap-1.5 text-[12px] font-bold uppercase">
                                    <History className="size-3.5" />
                                    Correcciones
                                </h2>
                                {certificate.revisiones.map((revision) => (
                                    <div
                                        key={revision.numero}
                                        className="text-xs"
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

            {certificate.corregir_datos_url ? (
                <Dialog
                    open={corrigiendoDatos}
                    onOpenChange={setCorrigiendoDatos}
                >
                    <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                        <DialogHeader>
                            <DialogTitle>
                                Corregir datos del certificado
                            </DialogTitle>
                            <DialogDescription>
                                El certificado mantiene su número. Queda
                                guardado quién lo corrigió y por qué.
                            </DialogDescription>
                        </DialogHeader>
                        <FormularioCorreccion
                            certificate={certificate}
                            onGuardado={() => setCorrigiendoDatos(false)}
                        />
                    </DialogContent>
                </Dialog>
            ) : null}
        </VendedorLayout>
    );
}
