import { Link, router, useForm, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Camera,
    PenLine,
    Stamp,
    Trash2,
    UserPlus,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';

import { ConfiguracionTabs } from '@/components/configuracion-tabs';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import GerenteLayout from '@/layouts/gerente-layout';
import CertificadosRoutes from '@/routes/gerente/configuracion/certificados';
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
    tipos: { id: number; nombre: string; cargo: string }[];
};

type Props = { firmantes: Firmante[] };

const etiqueta = 'text-xs font-bold text-foreground/80 uppercase';

function useTeamSlug(): string {
    const { currentTeam } = usePage<{ currentTeam?: Team | null }>().props;

    return (
        currentTeam?.slug ??
        (typeof window !== 'undefined'
            ? window.location.pathname.split('/')[1]
            : '')
    );
}

export default function FirmasYSellos({ firmantes }: Props) {
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
                    <div className="bg-destructive/10 text-primary-strong flex size-10 items-center justify-center rounded-lg">
                        <PenLine className="size-5" />
                    </div>
                    <div>
                        <h1 className="text-foreground font-['Oswald',sans-serif] text-[20px] font-semibold uppercase">
                            Firmas y sellos
                        </h1>
                        <p className="text-muted-foreground text-[12px]">
                            Las personas que firman y sus fotos. Quién firma
                            cada certificado se elige en el editor del
                            certificado.
                        </p>
                    </div>
                </div>

                <Card className="border-border bg-card flex-row items-start gap-3 rounded-2xl p-4 shadow-none">
                    <Camera className="text-primary-strong mt-0.5 size-5 shrink-0" />
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
                            <p className="text-warning-strong mt-1.5 inline-flex items-center gap-1.5 font-bold">
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
                            teamSlug={teamSlug}
                        />
                    ))}
                </div>

                <NuevoFirmante teamSlug={teamSlug} />
            </div>
        </GerenteLayout>
    );
}

function TarjetaFirmante({
    firmante,
    teamSlug,
}: {
    firmante: Firmante;
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

    return (
        <Card
            id={`firmante-${firmante.id}`}
            className={`border-border bg-card target:ring-primary scroll-mt-24 gap-3 rounded-2xl p-4 shadow-none target:ring-2 ${firmante.activo ? '' : 'opacity-60'}`}
        >
            <div className="flex gap-3">
                <div className="flex min-w-0 flex-1 flex-col gap-1.5">
                    <div className="border-border relative flex h-24 items-center justify-center overflow-hidden rounded-lg border border-dashed bg-white">
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
                            <span className="text-warning-strong flex items-center gap-1.5 text-[12px] font-bold">
                                <AlertTriangle className="size-3.5" /> Falta la
                                firma
                            </span>
                        )}
                    </div>
                    <div className="flex gap-1.5">
                        <input
                            aria-label="Subir firma"
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
                            className="h-8 flex-1 rounded-lg text-[12px] shadow-none"
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
                                className="text-destructive-strong size-8 rounded-lg shadow-none"
                                aria-label="Quitar firma"
                            >
                                <Trash2 className="size-3.5" />
                            </Button>
                        ) : null}
                    </div>
                </div>

                <div className="flex w-24 shrink-0 flex-col gap-1.5">
                    <div className="border-border flex h-24 items-center justify-center overflow-hidden rounded-lg border border-dashed bg-white">
                        {guardando === 'sello' ? (
                            <Spinner />
                        ) : firmante.sello_url ? (
                            <img
                                src={firmante.sello_url}
                                alt={`Sello de ${firmante.nombre}`}
                                className="max-h-20 max-w-[90%] object-contain"
                            />
                        ) : (
                            <span className="px-1 text-center text-xs text-neutral-500">
                                Sello propio (opcional)
                            </span>
                        )}
                    </div>
                    <input
                        aria-label="Subir sello"
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
                            className="text-destructive-strong h-8 rounded-lg text-[12px] shadow-none"
                        >
                            Quitar
                        </Button>
                    ) : (
                        <Button
                            type="button"
                            variant="outline"
                            disabled={guardando !== null}
                            onClick={() => inputSello.current?.click()}
                            className="h-8 rounded-lg text-[12px] shadow-none"
                        >
                            <Stamp className="size-3.5" /> Sello
                        </Button>
                    )}
                </div>
            </div>

            {errores.firma || errores.sello || errores.imagen ? (
                <p className="text-destructive-strong text-xs" role="alert">
                    {errores.firma ?? errores.sello ?? errores.imagen}
                </p>
            ) : null}

            <div className="grid gap-2 sm:grid-cols-[1fr_1fr_90px]">
                <div>
                    <Label
                        htmlFor={`gerente-configuracion-firmas-nombre-${firmante.id}`}
                        className={etiqueta}
                    >
                        Nombre
                    </Label>
                    <Input
                        id={`gerente-configuracion-firmas-nombre-${firmante.id}`}
                        value={datos.nombre}
                        onChange={(e) =>
                            setDatos({ ...datos, nombre: e.target.value })
                        }
                    />
                </div>
                <div>
                    <Label
                        htmlFor={`gerente-configuracion-firmas-cargo-${firmante.id}`}
                        className={etiqueta}
                    >
                        Cargo habitual
                    </Label>
                    <Input
                        id={`gerente-configuracion-firmas-cargo-${firmante.id}`}
                        value={datos.cargo}
                        onChange={(e) =>
                            setDatos({ ...datos, cargo: e.target.value })
                        }
                    />
                </div>
                <div>
                    <Label
                        htmlFor={`gerente-configuracion-firmas-cip-${firmante.id}`}
                        className={etiqueta}
                    >
                        CIP
                    </Label>
                    <Input
                        id={`gerente-configuracion-firmas-cip-${firmante.id}`}
                        value={datos.cip}
                        onChange={(e) =>
                            setDatos({ ...datos, cip: e.target.value })
                        }
                        placeholder="—"
                    />
                </div>
            </div>
            {errores.nombre || errores.cargo ? (
                <p className="text-destructive-strong text-xs" role="alert">
                    {errores.nombre ?? errores.cargo}
                </p>
            ) : null}

            <div>
                <p className={`${etiqueta} mb-1.5`}>Firma en</p>
                {firmante.tipos.length > 0 ? (
                    <div className="flex flex-wrap gap-1.5">
                        {firmante.tipos.map((tipo) => (
                            <Link
                                key={tipo.id}
                                href={CertificadosRoutes.edit.url({
                                    current_team: teamSlug,
                                    certificado: tipo.id,
                                })}
                                className="border-border text-muted-foreground hover:border-primary/60 hover:text-primary-strong rounded-full border px-2.5 py-1 text-xs font-bold transition-colors"
                            >
                                {tipo.nombre}
                                <span className="font-normal">
                                    {' '}
                                    · {tipo.cargo}
                                </span>
                            </Link>
                        ))}
                    </div>
                ) : (
                    <p className="text-muted-foreground text-xs">
                        Todavía no firma ningún certificado. Agrégalo desde el
                        editor del certificado.
                    </p>
                )}
            </div>

            <div className="border-border flex items-center justify-between gap-2 border-t pt-3">
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
                        className="h-8 rounded-lg px-3 text-[12px] font-bold shadow-none"
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
            onError: (errors) =>
                toast.error(
                    Object.values(errors)[0] ??
                        'No se pudo completar la acción.',
                ),
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    return (
        <Card className="border-border bg-card gap-3 rounded-2xl p-5 shadow-none">
            <div className="flex items-center gap-2">
                <UserPlus className="text-primary-strong size-4" />
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
                        aria-label="Nombre"
                        placeholder="Nombre (ej. Ing. Juan Pérez)"
                        value={form.data.nombre}
                        onChange={(e) => form.setData('nombre', e.target.value)}
                    />
                    {form.errors.nombre ? (
                        <p className="text-destructive-strong mt-1 text-xs">
                            {form.errors.nombre}
                        </p>
                    ) : null}
                </div>
                <div>
                    <Input
                        aria-label="Cargo"
                        placeholder="Cargo habitual (ej. Ingeniero)"
                        value={form.data.cargo}
                        onChange={(e) => form.setData('cargo', e.target.value)}
                    />
                    {form.errors.cargo ? (
                        <p className="text-destructive-strong mt-1 text-xs">
                            {form.errors.cargo}
                        </p>
                    ) : null}
                </div>
                <Input
                    aria-label="CIP"
                    placeholder="CIP (opcional)"
                    value={form.data.cip}
                    onChange={(e) => form.setData('cip', e.target.value)}
                />
                <Button
                    type="submit"
                    loading={form.processing}
                    className="h-10 rounded-lg px-4 font-bold shadow-none"
                >
                    Agregar
                </Button>
            </form>
            <p className="text-muted-foreground text-xs">
                Después de agregarlo, sube su firma y marca en qué certificados
                firma.
            </p>
        </Card>
    );
}
