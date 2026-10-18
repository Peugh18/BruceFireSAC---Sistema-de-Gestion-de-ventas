import { Link } from '@inertiajs/react';
import { AlertTriangle, ArrowUp, X } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import FirmasRoutes from '@/routes/gerente/configuracion/firmas';

/** Persona que firma: se registra una sola vez en Firmas y sellos. */
export type Firmante = {
    id: number;
    nombre: string;
    /** Cargo habitual: se sugiere al agregarla a un certificado. */
    cargo: string;
    cip: string | null;
    firma_url: string | null;
    sello_url: string | null;
};

/** Su papel en este certificado. */
export type FirmaDelCertificado = { id: number; cargo: string };

/**
 * Quién firma este certificado, con qué cargo y en qué orden. Las personas,
 * sus fotos y su CIP se manejan solo en Firmas y sellos.
 */
export default function FirmasDelCertificado({
    disponibles,
    elegidos,
    maximo,
    equipo,
    error,
    onChange,
}: {
    disponibles: Firmante[];
    elegidos: FirmaDelCertificado[];
    maximo: number;
    equipo: string;
    error?: string;
    onChange: (firmas: FirmaDelCertificado[]) => void;
}) {
    const porId = new Map(disponibles.map((f) => [f.id, f]));
    const restantes = disponibles.filter(
        (f) => !elegidos.some((e) => e.id === f.id),
    );
    const sinFoto = elegidos.filter((e) => !porId.get(e.id)?.firma_url);
    const directorio = FirmasRoutes.index.url({ current_team: equipo });

    const subir = (i: number) => {
        if (i === 0) return;
        const firmas = elegidos.slice();
        [firmas[i - 1], firmas[i]] = [firmas[i], firmas[i - 1]];
        onChange(firmas);
    };
    const cambiarCargo = (i: number, cargo: string) =>
        onChange(elegidos.map((e, j) => (j === i ? { ...e, cargo } : e)));

    return (
        <div className="grid gap-2.5">
            {sinFoto.length > 0 ? (
                <p className="flex items-start gap-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:bg-amber-950/60 dark:text-amber-200">
                    <AlertTriangle
                        className="mt-0.5 size-3.5 shrink-0"
                        aria-hidden="true"
                    />
                    <span>
                        {sinFoto.length === 1
                            ? 'Falta la foto de 1 firma.'
                            : `Falta la foto de ${sinFoto.length} firmas.`}{' '}
                        <Link
                            href={`${directorio}#firmante-${sinFoto[0].id}`}
                            className="font-semibold underline underline-offset-2"
                        >
                            Subirla en Firmas y sellos
                        </Link>
                    </span>
                </p>
            ) : (
                <p className="text-muted-foreground text-xs">
                    Firman de izquierda a derecha. El sello de la empresa va al
                    centro.
                </p>
            )}

            <ol className="grid gap-2">
                {elegidos.map((firma, i) => {
                    const persona = porId.get(firma.id);
                    if (!persona) return null;
                    return (
                        <li
                            key={firma.id}
                            className="border-border grid gap-2 rounded-lg border p-2.5"
                        >
                            <div className="grid grid-cols-[72px_minmax(0,1fr)_auto] items-center gap-2.5">
                                <span className="border-border flex h-10 w-[72px] items-center justify-center overflow-hidden rounded-md border bg-white">
                                    {persona.firma_url ? (
                                        <img
                                            src={persona.firma_url}
                                            alt={`Firma de ${persona.nombre}`}
                                            className="max-h-9 max-w-[64px] object-contain"
                                        />
                                    ) : (
                                        <span className="text-[11px] text-neutral-500">
                                            Sin foto
                                        </span>
                                    )}
                                </span>
                                <span className="min-w-0">
                                    <span className="text-foreground block text-sm leading-tight font-semibold">
                                        {persona.nombre}
                                    </span>
                                    {persona.cip && (
                                        <span className="text-muted-foreground block text-xs">
                                            CIP {persona.cip}
                                        </span>
                                    )}
                                </span>
                                <span className="flex">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label={`Mover a ${persona.nombre} a la izquierda`}
                                        disabled={i === 0}
                                        onClick={() => subir(i)}
                                    >
                                        <ArrowUp className="size-4" />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label={`Quitar a ${persona.nombre}`}
                                        onClick={() =>
                                            onChange(
                                                elegidos.filter(
                                                    (e) => e.id !== firma.id,
                                                ),
                                            )
                                        }
                                    >
                                        <X className="size-4" />
                                    </Button>
                                </span>
                            </div>
                            <div className="grid grid-cols-[72px_minmax(0,1fr)] items-center gap-2.5">
                                <label
                                    htmlFor={`firma-como-${firma.id}`}
                                    className="text-muted-foreground text-xs"
                                >
                                    Firma como
                                </label>
                                <Input
                                    id={`firma-como-${firma.id}`}
                                    className="min-h-9"
                                    value={firma.cargo}
                                    maxLength={120}
                                    placeholder={persona.cargo}
                                    onChange={(ev) =>
                                        cambiarCargo(i, ev.target.value)
                                    }
                                />
                            </div>
                        </li>
                    );
                })}
            </ol>
            {error && (
                <p role="alert" className="text-destructive-strong text-xs">
                    {error}
                </p>
            )}

            {elegidos.length < maximo && restantes.length > 0 && (
                <div>
                    <label htmlFor="agregar-firmante" className="sr-only">
                        Agregar quien firma
                    </label>
                    <NativeSelect
                        id="agregar-firmante"
                        value=""
                        onChange={(ev) => {
                            const persona = porId.get(Number(ev.target.value));
                            if (persona) {
                                onChange([
                                    ...elegidos,
                                    { id: persona.id, cargo: persona.cargo },
                                ]);
                            }
                        }}
                        className="min-h-9 w-full"
                    >
                        <option value="">Agregar quien firma…</option>
                        {restantes.map((f) => (
                            <option key={f.id} value={f.id}>
                                {f.nombre}
                            </option>
                        ))}
                    </NativeSelect>
                </div>
            )}
            <Link
                href={directorio}
                className="text-primary-strong justify-self-start text-xs font-semibold hover:underline"
            >
                Registrar a una persona nueva o cambiar su foto
            </Link>
        </div>
    );
}
