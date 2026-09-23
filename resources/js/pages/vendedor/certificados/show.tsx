import { Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Award, CalendarDays, Hash, QrCode } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import VendedorLayout from '@/layouts/vendedor-layout';
import certificados from '@/routes/vendedor/certificados';
import type { Team } from '@/types';

type Unit = {
    id: number;
    numero_serie_snapshot?: string;
    marca_snapshot?: string;
    capacidad_snapshot?: string;
    agente_extintor_snapshot?: string;
};

type Certificate = {
    id: number;
    numero: string;
    tipo: string | null;
    tipo_codigo: string | null;
    cliente: string | null;
    fecha_emision: string | null;
    fecha_vigencia_hasta: string | null;
    estado: string;
    qr_token: string;
    certificate_units?: Unit[];
    unidades: string[];
};

type Props = { certificate: Certificate };

function badgeClass(estado: string) {
    return estado?.toLowerCase() === 'vigente'
        ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20'
        : 'bg-destructive/10 text-destructive border border-destructive/20';
}

export default function CertificadosShow({ certificate }: Props) {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;
    const teamSlug =
        currentTeam?.slug ??
        (typeof window !== 'undefined'
            ? window.location.pathname.split('/')[1]
            : '');
    const units: Unit[] = certificate.certificate_units?.length
        ? certificate.certificate_units
        : certificate.unidades.map((unit, index) => ({
              id: index,
              numero_serie_snapshot: unit,
          }));

    return (
        <VendedorLayout title="Detalle certificado">
            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap items-center gap-3">
                    <Button
                        asChild
                        variant="outline"
                        className="h-9 rounded-[9px] border-border bg-card text-foreground/80 shadow-none"
                    >
                        <Link href={certificados.index(teamSlug)}>
                            <ArrowLeft className="size-4" />
                            Certificados
                        </Link>
                    </Button>
                    <div>
                        <h1 className="font-['Oswald',sans-serif] text-[24px] font-semibold tracking-[0.02em] text-foreground uppercase">
                            {certificate.numero}
                        </h1>
                        <p className="text-[12.5px] text-muted-foreground">
                            {certificate.cliente ?? 'Cliente sin datos'}
                        </p>
                    </div>
                    <div className="flex-1" />
                    <Badge
                        className={`rounded-full border-transparent px-3 py-1 text-[10.5px] font-bold capitalize ${badgeClass(certificate.estado)}`}
                    >
                        {certificate.estado}
                    </Badge>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card className="gap-1 rounded-[14px] border-border bg-card p-4 shadow-none">
                        <Award className="size-5 text-primary" />
                        <div className="text-[11px] font-bold text-muted-foreground uppercase">
                            Tipo
                        </div>
                        <div className="text-[13px] font-bold">
                            {certificate.tipo ?? certificate.tipo_codigo ?? '-'}
                        </div>
                    </Card>
                    <Card className="gap-1 rounded-[14px] border-border bg-card p-4 shadow-none">
                        <CalendarDays className="size-5 text-emerald-600 dark:text-emerald-400" />
                        <div className="text-[11px] font-bold text-muted-foreground uppercase">
                            Emisión
                        </div>
                        <div className="font-['IBM_Plex_Mono',monospace] text-[13px] font-bold">
                            {certificate.fecha_emision ?? '-'}
                        </div>
                    </Card>
                    <Card className="gap-1 rounded-[14px] border-border bg-card p-4 shadow-none">
                        <CalendarDays className="size-5 text-amber-600 dark:text-amber-400" />
                        <div className="text-[11px] font-bold text-muted-foreground uppercase">
                            Vigencia
                        </div>
                        <div className="font-['IBM_Plex_Mono',monospace] text-[13px] font-bold">
                            {certificate.fecha_vigencia_hasta ?? '-'}
                        </div>
                    </Card>
                    <Card className="gap-1 rounded-[14px] border-border bg-card p-4 shadow-none">
                        <QrCode className="size-5 text-blue-600 dark:text-blue-400" />
                        <div className="text-[11px] font-bold text-muted-foreground uppercase">
                            QR token
                        </div>
                        <div className="truncate font-['IBM_Plex_Mono',monospace] text-[12px] font-bold">
                            {certificate.qr_token}
                        </div>
                    </Card>
                </div>

                <Card className="gap-0 rounded-[16px] border-border bg-card p-5 shadow-none">
                    <h2 className="mb-3 font-['Oswald',sans-serif] text-[18px] font-semibold uppercase">
                        Unidades certificadas
                    </h2>
                    <div className="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                        {units.map((unit) => (
                            <div
                                key={unit.id}
                                className="rounded-[10px] border border-border bg-muted/40 p-3"
                            >
                                <div className="mb-2 flex items-center gap-2 font-['IBM_Plex_Mono',monospace] text-[12px] font-bold text-foreground">
                                    <Hash className="size-3.5 text-primary" />
                                    {unit.numero_serie_snapshot ?? '-'}
                                </div>
                                <div className="text-[11.5px] text-muted-foreground">
                                    {unit.marca_snapshot ??
                                        'Marca no registrada'}
                                </div>
                                <div className="text-[11.5px] text-muted-foreground">
                                    {unit.capacidad_snapshot ?? '-'} ·{' '}
                                    {unit.agente_extintor_snapshot ?? '-'}
                                </div>
                            </div>
                        ))}
                    </div>
                </Card>
            </div>
        </VendedorLayout>
    );
}
