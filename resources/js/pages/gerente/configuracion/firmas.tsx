import { router, useForm, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Camera,
    CheckCircle2,
    FileText,
    PenLine,
    Stamp,
    Trash2,
    UserPlus,
} from 'lucide-react';
import { useRef, useState } from 'react';

import { ConfiguracionTabs } from '@/components/configuracion-tabs';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import GerenteLayout from '@/layouts/gerente-layout';
import firmas from '@/routes/gerente/configuracion/firmas';
import type { Team } from '@/types';

type Firmante = {
    id: number;
    nombre: string;
    cargo: string;
    cip: string | null;
    activo: boolean;
    firma_url: string | null;
    sello_url: string | null;
    tipos: string[];
};

type Tipo = {
    codigo: string;
    nombre: string;
    firmantes: string[];
    sin_firma: boolean;
};

type Props = { firmantes: Firmante[]; tipos: Tipo[] };

const etiqueta = 'text-[11px] font-bold text-foreground/80 uppercase';

function useTeamSlug(): string {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;

    return (
        currentTeam?.slug ??
        (typeof window !== 'undefined'
            ? window.location.pathname.split('/')[1]
            : '')
    );
}

export default function FirmasYSellos({ firmantes, tipos }: Props) {
    const teamSlug = useTeamSlug();
    const sinFirma = firmantes.filter((f) => f.activo && !f.firma_url).length;

    return (
        <GerenteLayout title="Firmas y sellos">
            <div className="flex flex-col gap-5">
                <ConfiguracionTabs
                    teamSlug={teamSlug}
                    activa="firmas"
                    firmasPendientes={sinFirma}
                />
                <div className="flex items-center gap-3">
                    <div className="flex size-10 items-center justify-center rounded-[11px] bg-destructive/10 text-primary">
                        <PenLine className="size-5" />
                    </div>
                    <div>
                        <h1 className="font-['Oswald',sans-serif] text-[20px] font-semibold text-foreground uppercase">
                            Firmas y sellos
                        </h1>
                        <p className="text-[12px] text-muted-foreground">
                            Quién firma cada certificado. Los cambios se ven en
                            todos los certificados, también en los ya emitidos.
                        </p>
                    </div>
                </div>

                <Card className="flex-row items-start gap-3 rounded-[16px] border-border bg-card p-4 shadow-none">
                    <Camera className="mt-0.5 size-5 shrink-0 text-primary" />
                    <div className="text-[12.5px] leading-relaxed">
                        <p className="font-bold">Cómo subir una firma</p>
                        <p className="text-muted-foreground">
                            Firma con lapicero azul o negro sobre una hoja
                            blanca, tómale una foto con el celular con buena luz
                            y súbela. El sistema quita el fondo y la recorta
                            solo. Para el sello, igual: sella una hoja blanca y
                            súbela.
                        </p>
                        {sinFirma > 0 ? (
                            <p className="mt-1.5 inline-flex items-center gap-1.5 font-bold text-amber-600 dark:text-amber-400">
                                <AlertTriangle className="size-3.5" />
                                {sinFirma === 1
                                    ? 'Falta subir 1 firma.'
                                    : `Faltan subir ${sinFirma} firmas.`}
                            </p>
                        ) : null}
                    </div>
                </Card>

                <div className="grid gap-4 lg:grid-cols-2">
                    {firmantes.map((firmante) => (
                        <TarjetaFirmante
                            key={firmante.id}
                            firmante={firmante}
                            tipos={tipos}
                            teamSlug={teamSlug}
                        />
                    ))}
                </div>

                <NuevoFirmante teamSlug={teamSlug} />

                <Card className="gap-3 rounded-[16px] border-border bg-card p-5 shadow-none">
                    <div className="flex items-center gap-2">
                        <FileText className="size-4 text-primary" />
                        <h2 className="font-['Oswald',sans-serif] text-[16px] font-semibold uppercase">
                            Cómo queda cada certificado
                        </h2>
                    </div>
                    <div className="flex flex-col divide-y divide-border">
                        {tipos.map((tipo) => (
                            <div
                                key={tipo.codigo}
                                className="flex flex-wrap items-center justify-between gap-2 py-2.5 text-[12.5px]"
                            >
                                <div className="min-w-0">
                                    <p className="font-bold">{tipo.nombre}</p>
                                    <p className="text-muted-foreground">
                                        {tipo.firmantes.length > 0
                                            ? `Firman: ${tipo.firmantes.join(', ')}`
                                            : 'Nadie firma este certificado todavía.'}
                                    </p>
                                </div>
                                <div className="flex items-center gap-2">
                                    {tipo.sin_firma ? (
                                        <span className="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2 py-0.5 text-[11px] font-bold text-amber-700 dark:text-amber-400">
                                            <AlertTriangle className="size-3" />
                                            Falta firma
                                        </span>
                                    ) : (
                                        <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2 py-0.5 text-[11px] font-bold text-emerald-700 dark:text-emerald-400">
                                            <CheckCircle2 className="size-3" />
                                            Completo
                                        </span>
                                    )}
                                    <a
                                        href={firmas.vistaPrevia.url({
                                            current_team: teamSlug,
                                            tipo: tipo.codigo,
                                        })}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="rounded-[8px] border border-border px-2.5 py-1 text-[11.5px] font-bold transition-colors hover:border-primary/60 hover:text-primary"
                                    >
                                        Ver ejemplo
                                    </a>
                                </div>
                            </div>
                        ))}
                    </div>
                </Card>
            </div>
        </GerenteLayout>
    );
}

function TarjetaFirmante({
    firmante,
    tipos,
    teamSlug,
}: {
    firmante: Firmante;
    tipos: Tipo[];
    teamSlug: string;
}) {
    const [datos, setDatos] = useState({
        nombre: firmante.nombre,
        cargo: firmante.cargo,
        cip: firmante.cip ?? '',
    });
    const [guardando, setGuardando] = useState<string | null>(null);
    const [errores, setErrores] = useState<Record<string, string>>({});
    const inputFirma = useRef<HTMLInputElement>(null);
    const inputSello = useRef<HTMLInputElement>(null);

    const cambiado =
        datos.nombre !== firmante.nombre ||
        datos.cargo !== firmante.cargo ||
        datos.cip !== (firmante.cip ?? '');

    function guardar(
        extra: Record<string, string | boolean | File | string[]>,
        motivo: string,
        conArchivo = false,
    ) {
        router.post(
            firmas.update.url({
                current_team: teamSlug,
                firmante: firmante.id,
            }),
            { ...datos, ...extra },
            {
                forceFormData: conArchivo,
                preserveScroll: true,
                onStart: () => setGuardando(motivo),
                onFinish: () => setGuardando(null),
                onSuccess: () => setErrores({}),
                onError: (e) => setErrores(e),
            },
        );
    }

    function subir(campo: 'firma' | 'sello', archivo: File | undefined) {
        if (archivo) {
            guardar({ [campo]: archivo }, campo, true);
        }
    }

    function alternarTipo(codigo: string) {
        const nuevos = firmante.tipos.includes(codigo)
            ? firmante.tipos.filter((t) => t !== codigo)
            : [...firmante.tipos, codigo];
        guardar({ tipos: nuevos }, 'tipos');
    }

    return (
        <Card
            className={`gap-3 rounded-[16px] border-border bg-card p-4 shadow-none ${firmante.activo ? '' : 'opacity-60'}`}
        >
            <div className="flex gap-3">
                <div className="flex min-w-0 flex-1 flex-col gap-1.5">
                    <div className="relative flex h-24 items-center justify-center overflow-hidden rounded-[10px] border border-dashed border-border bg-white">
                        {guardando === 'firma' ? (
                            <span className="flex items-center gap-2 text-[12px] text-neutral-500">
                                <Spinner /> Limpiando la foto…
                            </span>
                        ) : firmante.firma_url ? (
                            <img
                                src={firmante.firma_url}
                                alt={`Firma de ${firmante.nombre}`}
                                className="max-h-20 max-w-[90%] object-contain"
                            />
                        ) : (
                            <span className="flex items-center gap-1.5 text-[12px] font-bold text-amber-600">
                                <AlertTriangle className="size-3.5" /> Falta la
                                firma
                            </span>
                        )}
                    </div>
                    <div className="flex gap-1.5">
                        <input
                            ref={inputFirma}
                            type="file"
                            accept="image/*"
                            className="hidden"
                            onChange={(e) => {
                                subir('firma', e.target.files?.[0]);
                                e.target.value = '';
                            }}
                        />
                        <Button
                            type="button"
                            variant="outline"
                            disabled={guardando !== null}
                            onClick={() => inputFirma.current?.click()}
                            className="h-8 flex-1 rounded-[8px] text-[12px] shadow-none"
                        >
                            <Camera className="size-3.5" />
                            {firmante.firma_url
                                ? 'Cambiar firma'
                                : 'Subir firma'}
                        </Button>
                        {firmante.firma_url ? (
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                disabled={guardando !== null}
                                onClick={() =>
                                    guardar({ quitar_firma: true }, 'quitar')
                                }
                                className="size-8 rounded-[8px] text-destructive shadow-none"
                                aria-label="Quitar firma"
                            >
                                <Trash2 className="size-3.5" />
                            </Button>
                        ) : null}
                    </div>
                </div>

                <div className="flex w-24 shrink-0 flex-col gap-1.5">
                    <div className="flex h-24 items-center justify-center overflow-hidden rounded-[10px] border border-dashed border-border bg-white">
                        {guardando === 'sello' ? (
                            <Spinner />
                        ) : firmante.sello_url ? (
                            <img
                                src={firmante.sello_url}
                                alt={`Sello de ${firmante.nombre}`}
                                className="max-h-20 max-w-[90%] object-contain"
                            />
                        ) : (
                            <span className="px-1 text-center text-[10.5px] text-neutral-500">
                                Sello propio (opcional)
                            </span>
                        )}
                    </div>
                    <input
                        ref={inputSello}
                        type="file"
                        accept="image/*"
                        className="hidden"
                        onChange={(e) => {
                            subir('sello', e.target.files?.[0]);
                            e.target.value = '';
                        }}
                    />
                    {firmante.sello_url ? (
                        <Button
                            type="button"
                            variant="outline"
                            disabled={guardando !== null}
                            onClick={() =>
                                guardar({ quitar_sello: true }, 'quitar')
                            }
                            className="h-8 rounded-[8px] text-[12px] text-destructive shadow-none"
                        >
                            Quitar
                        </Button>
                    ) : (
                        <Button
                            type="button"
                            variant="outline"
                            disabled={guardando !== null}
                            onClick={() => inputSello.current?.click()}
                            className="h-8 rounded-[8px] text-[12px] shadow-none"
                        >
                            <Stamp className="size-3.5" /> Sello
                        </Button>
                    )}
                </div>
            </div>

            {errores.firma || errores.sello || errores.imagen ? (
                <p className="text-[11.5px] text-destructive">
                    {errores.firma ?? errores.sello ?? errores.imagen}
                </p>
            ) : null}

            <div className="grid gap-2 sm:grid-cols-[1fr_1fr_90px]">
                <div>
                    <Label className={etiqueta}>Nombre</Label>
                    <Input
                        value={datos.nombre}
                        onChange={(e) =>
                            setDatos({ ...datos, nombre: e.target.value })
                        }
                    />
                </div>
                <div>
                    <Label className={etiqueta}>Cargo</Label>
                    <Input
                        value={datos.cargo}
                        onChange={(e) =>
                            setDatos({ ...datos, cargo: e.target.value })
                        }
                    />
                </div>
                <div>
                    <Label className={etiqueta}>CIP</Label>
                    <Input
                        value={datos.cip}
                        onChange={(e) =>
                            setDatos({ ...datos, cip: e.target.value })
                        }
                        placeholder="—"
                    />
                </div>
            </div>
            {errores.nombre || errores.cargo ? (
                <p className="text-[11.5px] text-destructive">
                    {errores.nombre ?? errores.cargo}
                </p>
            ) : null}

            <div>
                <p className={`${etiqueta} mb-1.5`}>Firma en</p>
                <div className="flex flex-wrap gap-1.5">
                    {tipos.map((tipo) => {
                        const marcado = firmante.tipos.includes(tipo.codigo);

                        return (
                            <button
                                key={tipo.codigo}
                                type="button"
                                disabled={guardando !== null}
                                onClick={() => alternarTipo(tipo.codigo)}
                                aria-pressed={marcado}
                                className={`rounded-full border px-2.5 py-1 text-[11.5px] font-bold transition-colors disabled:opacity-60 ${
                                    marcado
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-border text-muted-foreground hover:border-primary/60 hover:text-primary'
                                }`}
                            >
                                {tipo.nombre}
                            </button>
                        );
                    })}
                </div>
            </div>

            <div className="flex items-center justify-between gap-2 border-t border-border pt-3">
                <label className="flex items-center gap-2 text-[12px]">
                    <input
                        type="checkbox"
                        checked={firmante.activo}
                        disabled={guardando !== null}
                        onChange={(e) =>
                            guardar({ activo: e.target.checked }, 'activo')
                        }
                        className="accent-primary"
                    />
                    Activo
                </label>
                {cambiado ? (
                    <Button
                        type="button"
                        disabled={guardando !== null}
                        onClick={() => guardar({}, 'datos')}
                        className="h-8 rounded-[8px] px-3 text-[12px] font-bold shadow-none"
                    >
                        {guardando === 'datos' ? 'Guardando…' : 'Guardar'}
                    </Button>
                ) : null}
            </div>
        </Card>
    );
}

function NuevoFirmante({ teamSlug }: { teamSlug: string }) {
    const form = useForm({ nombre: '', cargo: '', cip: '' });

    function agregar(event: React.FormEvent) {
        event.preventDefault();
        form.post(firmas.store.url({ current_team: teamSlug }), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    return (
        <Card className="gap-3 rounded-[16px] border-border bg-card p-5 shadow-none">
            <div className="flex items-center gap-2">
                <UserPlus className="size-4 text-primary" />
                <h2 className="font-['Oswald',sans-serif] text-[16px] font-semibold uppercase">
                    Agregar firmante
                </h2>
            </div>
            <form
                onSubmit={agregar}
                className="grid gap-2 md:grid-cols-[1fr_1fr_120px_auto]"
            >
                <div>
                    <Input
                        placeholder="Nombre (ej. Ing. Juan Pérez)"
                        value={form.data.nombre}
                        onChange={(e) => form.setData('nombre', e.target.value)}
                    />
                    {form.errors.nombre ? (
                        <p className="mt-1 text-[11.5px] text-destructive">
                            {form.errors.nombre}
                        </p>
                    ) : null}
                </div>
                <div>
                    <Input
                        placeholder="Cargo (ej. Ingeniero)"
                        value={form.data.cargo}
                        onChange={(e) => form.setData('cargo', e.target.value)}
                    />
                    {form.errors.cargo ? (
                        <p className="mt-1 text-[11.5px] text-destructive">
                            {form.errors.cargo}
                        </p>
                    ) : null}
                </div>
                <Input
                    placeholder="CIP (opcional)"
                    value={form.data.cip}
                    onChange={(e) => form.setData('cip', e.target.value)}
                />
                <Button
                    type="submit"
                    disabled={form.processing}
                    className="h-10 rounded-[9px] px-4 font-bold shadow-none"
                >
                    Agregar
                </Button>
            </form>
            <p className="text-[11.5px] text-muted-foreground">
                Después de agregarlo, sube su firma y marca en qué certificados
                firma.
            </p>
        </Card>
    );
}
