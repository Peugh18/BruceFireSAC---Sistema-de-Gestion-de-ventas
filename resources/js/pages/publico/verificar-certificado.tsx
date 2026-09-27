import { Head } from '@inertiajs/react';
import {
    Ban,
    CalendarX,
    FileText,
    Mail,
    Phone,
    RefreshCcw,
    SearchX,
    ShieldCheck,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';

type Estado =
    | 'vigente'
    | 'sin_vencimiento'
    | 'vencido'
    | 'reemplazado'
    | 'anulado';

type Certificado = {
    numero: string;
    tipo: string | null;
    estado: Estado;
    fecha_emision: string | null;
    fecha_vencimiento: string | null;
    referencia: string | null;
    revision: number;
    revision_fecha: string | null;
    cliente: {
        razon_social: string | null;
        documento_tipo: string | null;
        documento: string | null;
        direccion: string | null;
    };
    participante: {
        nombres: string;
        dni: string | null;
        cargo: string | null;
        personal_de: string | null;
    } | null;
    equipos: {
        serie: string;
        capacidad: string | null;
        marca: string | null;
    }[];
    pdf_url: string;
};

type Props = {
    certificado: Certificado | null;
    empresa: {
        razon_social: string | null;
        ruc: string | null;
        telefono: string | null;
        email: string | null;
    };
    consultadoEl: string;
};

type Aviso = {
    icono: LucideIcon;
    titulo: string;
    tono: string;
};

const AVISOS: Record<Estado | 'no_encontrado', Aviso> = {
    vigente: {
        icono: ShieldCheck,
        titulo: 'Certificado válido y vigente',
        tono: 'border-emerald-600/25 bg-emerald-600/10 text-emerald-700 dark:text-emerald-400',
    },
    sin_vencimiento: {
        icono: ShieldCheck,
        titulo: 'Certificado válido',
        tono: 'border-emerald-600/25 bg-emerald-600/10 text-emerald-700 dark:text-emerald-400',
    },
    vencido: {
        icono: CalendarX,
        titulo: 'Certificado vencido',
        tono: 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-400',
    },
    reemplazado: {
        icono: RefreshCcw,
        titulo: 'Certificado reemplazado',
        tono: 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-400',
    },
    anulado: {
        icono: Ban,
        titulo: 'Certificado anulado',
        tono: 'border-red-600/25 bg-red-600/10 text-red-700 dark:text-red-400',
    },
    no_encontrado: {
        icono: SearchX,
        titulo: 'No encontramos este certificado',
        tono: 'border-red-600/25 bg-red-600/10 text-red-700 dark:text-red-400',
    },
};

function detalleDelAviso(certificado: Certificado | null, empresa: string) {
    if (!certificado) {
        return `${empresa} no tiene registrado ningún certificado con este código. Si aparece impreso en un documento, podría no ser auténtico: comuníquese con nosotros.`;
    }

    switch (certificado.estado) {
        case 'vigente':
            return `Vigente hasta el ${certificado.fecha_vencimiento}. Emitido por ${empresa}`;
        case 'sin_vencimiento':
            return `Emitido el ${certificado.fecha_emision} por ${empresa}`;
        case 'vencido':
            return `Venció el ${certificado.fecha_vencimiento}. El servicio debe renovarse para que vuelva a tener validez.`;
        case 'reemplazado':
            return 'Existe un certificado más reciente para estos equipos. Solicite al cliente el certificado vigente.';
        case 'anulado':
            return 'Este certificado fue anulado y no tiene validez.';
    }
}

function Dato({ etiqueta, valor }: { etiqueta: string; valor: string | null }) {
    if (!valor) {
        return null;
    }

    return (
        <div className="grid grid-cols-[112px_1fr] gap-3 py-2">
            <dt className="text-muted-foreground text-[12.5px]">{etiqueta}</dt>
            <dd className="text-foreground text-[13.5px] font-semibold">
                {valor}
            </dd>
        </div>
    );
}

/**
 * Página pública a la que lleva el QR del certificado. La abren los
 * inspectores desde el celular para comprobar autenticidad y vigencia.
 */
export default function VerificarCertificado({
    certificado,
    empresa,
    consultadoEl,
}: Props) {
    const [todosLosEquipos, setTodosLosEquipos] = useState(false);
    const nombreEmpresa = empresa.razon_social ?? 'BRUCE FIRE S.A.C.';
    const aviso = AVISOS[certificado?.estado ?? 'no_encontrado'];
    const Icono = aviso.icono;
    const equipos = certificado?.equipos ?? [];
    const equiposVisibles = todosLosEquipos ? equipos : equipos.slice(0, 8);

    return (
        <>
            <Head
                title={
                    certificado
                        ? `Verificación ${certificado.numero}`
                        : 'Verificación de certificado'
                }
            />
            <main className="bg-background text-foreground min-h-dvh px-4 py-6">
                <div className="mx-auto flex w-full max-w-md flex-col gap-4">
                    <header className="flex items-center gap-3">
                        <img
                            src="/brand/logo-icon.png"
                            alt=""
                            className="size-10"
                        />
                        <div>
                            <div className="font-['Oswald',sans-serif] text-[15px] font-bold tracking-[0.06em] uppercase">
                                Bruce Fire
                            </div>
                            <div className="text-muted-foreground text-[12px]">
                                Verificación de certificados
                            </div>
                        </div>
                    </header>

                    <section
                        className={`rounded-[16px] border p-4 ${aviso.tono}`}
                        role="status"
                    >
                        <div className="flex items-start gap-3">
                            <Icono className="mt-0.5 size-7 shrink-0" />
                            <div>
                                <h1 className="text-[18px] leading-tight font-bold">
                                    {aviso.titulo}
                                </h1>
                                <p className="text-foreground/80 mt-1 text-[13.5px] leading-relaxed">
                                    {detalleDelAviso(
                                        certificado,
                                        nombreEmpresa,
                                    )}
                                </p>
                            </div>
                        </div>
                    </section>

                    {certificado ? (
                        <>
                            <section className="border-border bg-card rounded-[16px] border p-4">
                                <div className="text-muted-foreground text-[11px] font-bold tracking-[0.08em] uppercase">
                                    N.° de certificado
                                </div>
                                <div className="font-['IBM_Plex_Mono',monospace] text-[22px] font-bold">
                                    {certificado.numero}
                                </div>
                                <dl className="divide-border mt-2 divide-y">
                                    <Dato
                                        etiqueta="Tipo"
                                        valor={certificado.tipo}
                                    />
                                    <Dato
                                        etiqueta="Revisión"
                                        valor={
                                            certificado.revision > 0
                                                ? `${certificado.revision} · ${certificado.revision_fecha}`
                                                : 'Original'
                                        }
                                    />
                                    <Dato
                                        etiqueta="Trabajador"
                                        valor={
                                            certificado.participante?.nombres ??
                                            null
                                        }
                                    />
                                    <Dato
                                        etiqueta="DNI"
                                        valor={
                                            certificado.participante?.dni ??
                                            null
                                        }
                                    />
                                    <Dato
                                        etiqueta="Personal de"
                                        valor={
                                            certificado.participante
                                                ?.personal_de ?? null
                                        }
                                    />
                                    <Dato
                                        etiqueta="Cliente"
                                        valor={certificado.cliente.razon_social}
                                    />
                                    <Dato
                                        etiqueta={
                                            certificado.cliente
                                                .documento_tipo ?? 'Documento'
                                        }
                                        valor={certificado.cliente.documento}
                                    />
                                    <Dato
                                        etiqueta="Dirección"
                                        valor={certificado.cliente.direccion}
                                    />
                                    <Dato
                                        etiqueta="Referencia"
                                        valor={certificado.referencia}
                                    />
                                    <Dato
                                        etiqueta="Emitido"
                                        valor={certificado.fecha_emision}
                                    />
                                    <Dato
                                        etiqueta="Vence"
                                        valor={certificado.fecha_vencimiento}
                                    />
                                </dl>
                            </section>

                            {equipos.length > 0 ? (
                                <section className="border-border bg-card rounded-[16px] border p-4">
                                    <h2 className="text-[13px] font-bold">
                                        Equipos certificados ({equipos.length})
                                    </h2>
                                    <ul className="divide-border mt-2 divide-y">
                                        {equiposVisibles.map(
                                            (equipo, indice) => (
                                                <li
                                                    key={`${equipo.serie}-${indice}`}
                                                    className="flex items-center justify-between gap-3 py-2 text-[13px]"
                                                >
                                                    <span className="font-['IBM_Plex_Mono',monospace] font-semibold">
                                                        {equipo.serie || '—'}
                                                    </span>
                                                    <span className="text-muted-foreground text-right">
                                                        {[
                                                            equipo.capacidad,
                                                            equipo.marca,
                                                        ]
                                                            .filter(Boolean)
                                                            .join(' · ')}
                                                    </span>
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                    {equipos.length > 8 && !todosLosEquipos ? (
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setTodosLosEquipos(true)
                                            }
                                            className="text-primary mt-1 text-[13px] font-semibold"
                                        >
                                            Ver los {equipos.length} equipos
                                        </button>
                                    ) : null}
                                </section>
                            ) : null}

                            <a
                                href={certificado.pdf_url}
                                target="_blank"
                                rel="noopener"
                                className="bg-primary text-primary-foreground flex h-12 items-center justify-center gap-2 rounded-[12px] text-[14px] font-bold transition-transform duration-150 ease-out active:scale-[0.97]"
                            >
                                <FileText className="size-4" />
                                Ver certificado original (PDF)
                            </a>
                            <p className="text-muted-foreground text-center text-[12px]">
                                Compare el PDF con el documento impreso: deben
                                coincidir el número, el cliente y los equipos.
                            </p>
                        </>
                    ) : null}

                    <footer className="border-border text-muted-foreground mt-2 border-t pt-4 text-[12.5px]">
                        <div className="text-foreground font-semibold">
                            {nombreEmpresa}
                            {empresa.ruc ? ` · RUC ${empresa.ruc}` : ''}
                        </div>
                        <div className="mt-2 flex flex-col gap-1.5">
                            {empresa.telefono ? (
                                <a
                                    href={`tel:${empresa.telefono.replace(/[^\d+]/g, '')}`}
                                    className="inline-flex items-center gap-2"
                                >
                                    <Phone className="size-3.5" />
                                    {empresa.telefono}
                                </a>
                            ) : null}
                            {empresa.email ? (
                                <a
                                    href={`mailto:${empresa.email}`}
                                    className="inline-flex items-center gap-2"
                                >
                                    <Mail className="size-3.5" />
                                    {empresa.email}
                                </a>
                            ) : null}
                        </div>
                        <div className="mt-3 text-[11.5px]">
                            Consultado el {consultadoEl}
                        </div>
                    </footer>
                </div>
            </main>
        </>
    );
}
