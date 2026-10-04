import { Link, router } from '@inertiajs/react';
import { Building2, ChevronDown, MessageSquare } from 'lucide-react';
import { useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import WhatsappNumeroDialog from '@/components/whatsapp-numero-dialog';
import { fechaCorta } from '@/lib/utils';
import clientes from '@/routes/vendedor/clientes';

export type EquipoEmpresa = {
    equipment_id: number | null;
    numero_serie: string | null;
    equipo: string;
    capacidad: string | null;
    ubicacion: string | null;
    estado: 'vencido' | 'descargado' | 'por_vencer' | 'al_dia';
    recarga: string | null;
    prueba_hidrostatica: string | null;
    proxima: string | null;
    dias: number | null;
    /** Cuántos extintores representa la línea (1 si tiene serie). */
    cantidad: number;
    /** Sin serie: la fecha se estima al año de la compra. */
    estimado: boolean;
};

export type Empresa = {
    client_id: number;
    cliente: string;
    numero_documento: string;
    whatsapp: string | null;
    extintores: number;
    vencidos: number;
    por_vencer: number;
    estimados: number;
    proximo_vencimiento: string | null;
    dias: number | null;
    equipos: EquipoEmpresa[];
};

export type PlazoEmpresa = 'todas' | 'vencidas' | '30' | '90';

const ESTADO: Record<
    EquipoEmpresa['estado'],
    { texto: string; clase: string }
> = {
    vencido: {
        texto: 'Vencido',
        clase: 'bg-destructive/10 text-destructive-strong',
    },
    descargado: {
        texto: 'Usado / descargado',
        clase: 'bg-destructive/10 text-destructive-strong',
    },
    por_vencer: {
        texto: 'Vence pronto',
        clase: 'bg-amber-500/10 text-warning-strong',
    },
    al_dia: {
        texto: 'Al día',
        clase: 'bg-emerald-500/10 text-success-strong',
    },
};

/** "en 45 días", "hoy", "hace 3 días". */
function cuando(dias: number | null) {
    if (dias === null) return 'sin fecha';
    if (dias === 0) return 'hoy';
    if (dias < 0) return `venció hace ${Math.abs(dias)} día(s)`;
    if (dias < 60) return `en ${dias} día(s)`;
    return `en ${Math.round(dias / 30)} meses`;
}

/** Empresas que entran en el plazo elegido según su próximo vencimiento. */
export function filtrarPorPlazo(empresas: Empresa[], plazo: PlazoEmpresa) {
    if (plazo === 'todas') return empresas;

    return empresas.filter((empresa) => {
        if (empresa.dias === null) return false;
        if (plazo === 'vencidas') return empresa.vencidos > 0;

        return empresa.dias <= Number(plazo);
    });
}

function mensajeWhatsapp(empresa: Empresa, numero = empresa.whatsapp ?? '') {
    const vencidos = empresa.vencidos;
    const fecha = empresa.proximo_vencimiento
        ? fechaCorta(empresa.proximo_vencimiento)
        : '';
    const texto =
        vencidos > 0
            ? `Hola ${empresa.cliente}, le saludamos de Extintores Bruce Fire. ${vencidos} de sus ${empresa.extintores} extintores ya necesitan recarga o mantenimiento. ¿Le agendamos la atención?`
            : `Hola ${empresa.cliente}, le saludamos de Extintores Bruce Fire. Sus extintores tienen su próxima recarga o mantenimiento el ${fecha}. ¿Le agendamos la atención con anticipación?`;

    return `https://wa.me/${numero}?text=${encodeURIComponent(texto)}`;
}

/**
 * Extintores agrupados por empresa: una tarjeta por cliente con su resumen y,
 * al abrirla, cada extintor con su serie y sus fechas.
 */
export default function PorVencerEmpresas({
    empresas,
    teamSlug,
}: {
    empresas: Empresa[];
    teamSlug: string;
}) {
    const [abierta, setAbierta] = useState<number | null>(null);
    const [pidiendoNumero, setPidiendoNumero] = useState<Empresa | null>(null);

    if (empresas.length === 0) {
        return (
            <Card className="border-border bg-card items-center gap-2 rounded-[16px] p-10 text-center shadow-none">
                <Building2 className="text-muted-foreground size-8" />
                <p className="text-foreground text-sm font-bold">
                    No hay empresas en este plazo
                </p>
                <p className="text-muted-foreground text-xs">
                    Prueba con «Todas» para ver el parque completo de tus
                    clientes.
                </p>
            </Card>
        );
    }

    return (
        <div className="flex flex-col gap-2.5">
            {pidiendoNumero ? (
                <WhatsappNumeroDialog
                    open
                    onOpenChange={(abierto) => {
                        if (!abierto) {
                            setPidiendoNumero(null);
                        }
                    }}
                    teamSlug={teamSlug}
                    clientId={pidiendoNumero.client_id}
                    cliente={pidiendoNumero.cliente}
                    enlace={(numero) => mensajeWhatsapp(pidiendoNumero, numero)}
                    onGuardado={() => router.reload({ only: ['empresas'] })}
                />
            ) : null}
            {empresas.map((empresa) => {
                const estaAbierta = abierta === empresa.client_id;
                const urgente = empresa.vencidos > 0;

                return (
                    <Card
                        key={empresa.client_id}
                        className={`border-border bg-card gap-0 overflow-hidden rounded-[14px] p-0 shadow-none ${urgente ? 'border-l-destructive border-l-4' : empresa.por_vencer > 0 ? 'border-l-4 border-l-amber-500' : ''}`}
                    >
                        <div className="flex flex-col gap-3 p-4 md:flex-row md:items-center">
                            <button
                                type="button"
                                onClick={() =>
                                    setAbierta(
                                        estaAbierta ? null : empresa.client_id,
                                    )
                                }
                                className="flex min-w-0 flex-1 items-center gap-3 text-left"
                                aria-expanded={estaAbierta}
                            >
                                <div className="bg-muted text-foreground/80 flex size-10 shrink-0 items-center justify-center rounded-[11px]">
                                    <Building2 className="size-5" />
                                </div>
                                <div className="min-w-0">
                                    <div className="text-foreground truncate text-[14px] font-bold">
                                        {empresa.cliente}
                                    </div>
                                    <div className="text-muted-foreground font-['IBM_Plex_Mono',monospace] text-[11px]">
                                        {empresa.numero_documento} ·{' '}
                                        {empresa.extintores} extintor(es)
                                        {empresa.estimados > 0
                                            ? ` · ${empresa.estimados} sin serie`
                                            : ''}
                                    </div>
                                </div>
                                <ChevronDown
                                    className={`text-muted-foreground ml-auto size-4 shrink-0 transition-transform ${estaAbierta ? 'rotate-180' : ''}`}
                                />
                            </button>

                            <div className="flex flex-wrap items-center gap-2 md:justify-end">
                                {empresa.vencidos > 0 ? (
                                    <Badge className="bg-destructive/10 text-destructive-strong rounded-full border-transparent">
                                        {empresa.vencidos} vencido(s)
                                    </Badge>
                                ) : null}
                                {empresa.por_vencer > 0 ? (
                                    <Badge className="text-warning-strong rounded-full border-transparent bg-amber-500/10">
                                        {empresa.por_vencer} vence(n) en 30 días
                                    </Badge>
                                ) : null}
                                <div className="text-right">
                                    <div className="text-muted-foreground text-[10.5px] font-bold uppercase">
                                        Próximo vencimiento
                                    </div>
                                    <div className="text-foreground text-[12.5px] font-bold">
                                        {empresa.proximo_vencimiento
                                            ? fechaCorta(
                                                  empresa.proximo_vencimiento,
                                              )
                                            : '—'}{' '}
                                        <span className="text-muted-foreground font-normal">
                                            ({cuando(empresa.dias)})
                                        </span>
                                    </div>
                                </div>
                                {empresa.whatsapp ? (
                                    <a
                                        href={mensajeWhatsapp(empresa)}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        title="Ofrecer la recarga por WhatsApp"
                                        className="flex h-8 items-center gap-1.5 rounded-[8px] bg-green-700 px-3 text-[11.5px] font-bold text-white transition-colors hover:bg-green-800"
                                    >
                                        <MessageSquare className="size-3.5" />
                                        WhatsApp
                                    </a>
                                ) : (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setPidiendoNumero(empresa)
                                        }
                                        title="El cliente no tiene celular: agrégalo y se abre WhatsApp"
                                        className="flex h-8 items-center gap-1.5 rounded-[8px] border border-green-700 bg-green-700/5 px-3 text-[11.5px] font-bold text-green-800 transition-colors hover:bg-green-700/10 dark:text-green-400"
                                    >
                                        <MessageSquare className="size-3.5" />
                                        Agregar número
                                    </button>
                                )}
                                <Link
                                    href={clientes.show.url({
                                        current_team: teamSlug,
                                        client: empresa.client_id,
                                    })}
                                    className="border-border text-foreground/80 hover:text-foreground flex h-8 items-center rounded-[8px] border px-3 text-[11.5px] font-bold"
                                >
                                    Ver cliente
                                </Link>
                            </div>
                        </div>

                        {estaAbierta ? (
                            <div className="border-border overflow-x-auto border-t">
                                <table className="w-full text-[12px]">
                                    <thead>
                                        <tr className="bg-muted/50 text-muted-foreground text-left text-[10.5px] uppercase">
                                            <th className="px-4 py-2">Serie</th>
                                            <th className="px-3 py-2">
                                                Extintor
                                            </th>
                                            <th className="px-3 py-2">
                                                Ubicación
                                            </th>
                                            <th className="px-3 py-2">
                                                Recarga
                                            </th>
                                            <th className="px-3 py-2">
                                                P. hidrostática
                                            </th>
                                            <th className="px-3 py-2">
                                                Estado
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {empresa.equipos.map((equipo, i) => (
                                            <tr
                                                key={
                                                    equipo.equipment_id ??
                                                    `estimado-${i}`
                                                }
                                                className="border-border border-t"
                                            >
                                                <td className="px-4 py-2 font-['IBM_Plex_Mono',monospace] text-[11.5px]">
                                                    {equipo.numero_serie ?? (
                                                        <span
                                                            className="text-muted-foreground font-sans"
                                                            title="Vendido sin número de serie: la recarga se calcula al año de la compra"
                                                        >
                                                            Sin serie
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {equipo.cantidad > 1 ? (
                                                        <b>
                                                            {equipo.cantidad}{' '}
                                                            ×{' '}
                                                        </b>
                                                    ) : null}
                                                    {equipo.equipo}
                                                    {equipo.capacidad
                                                        ? ` · ${equipo.capacidad}`
                                                        : ''}
                                                    {equipo.estimado ? (
                                                        <span className="ml-1.5 rounded-full bg-sky-500/10 px-1.5 py-0.5 text-[10px] font-bold text-sky-700 dark:text-sky-400">
                                                            estimado
                                                        </span>
                                                    ) : null}
                                                </td>
                                                <td className="text-muted-foreground px-3 py-2">
                                                    {equipo.ubicacion ?? '—'}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {equipo.recarga
                                                        ? fechaCorta(
                                                              equipo.recarga,
                                                          )
                                                        : '—'}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {equipo.prueba_hidrostatica
                                                        ? fechaCorta(
                                                              equipo.prueba_hidrostatica,
                                                          )
                                                        : '—'}
                                                </td>
                                                <td className="px-3 py-2">
                                                    <span
                                                        className={`rounded-full px-2 py-0.5 text-[10.5px] font-bold ${ESTADO[equipo.estado].clase}`}
                                                    >
                                                        {
                                                            ESTADO[
                                                                equipo.estado
                                                            ].texto
                                                        }
                                                    </span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : null}
                    </Card>
                );
            })}
        </div>
    );
}
