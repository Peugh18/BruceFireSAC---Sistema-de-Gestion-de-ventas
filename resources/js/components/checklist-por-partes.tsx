import { useForm } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';
import { toast } from 'sonner';

import { BotonFoto } from '@/components/captura-evidencia';
import ExtintorPorPartes, {
    PARTES,
    type TipoExtintor,
} from '@/components/extintor-por-partes';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';

type Falla = {
    condicion: string;
    requiere_autorizacion: boolean;
    foto: File | null;
};

/**
 * Checklist de planta con el extintor dibujado por partes: todo empieza en
 * verde; la parte que se toca abre su hoja para decir qué tiene, con foto
 * obligatoria, y queda en rojo. Un solo botón guarda y pasa al siguiente.
 */
export default function ChecklistPorPartes({
    url,
    serie,
    tipo,
    textoBoton,
    onGuardado,
    renderAccion,
}: {
    url: string;
    serie: string;
    tipo: TipoExtintor;
    textoBoton: string;
    onGuardado: () => void;
    renderAccion?: (accion: ReactNode) => ReactNode;
}) {
    const partes = PARTES[tipo];
    const [fallas, setFallas] = useState<Record<string, Falla>>({});
    const [abierta, setAbierta] = useState<string | null>(null);
    const [borrador, setBorrador] = useState<Falla>({
        condicion: '',
        requiere_autorizacion: true,
        foto: null,
    });
    const [noApto, setNoApto] = useState(false);
    const [motivo, setMotivo] = useState('');
    const form = useForm({});
    const parte = partes.find((p) => p.clave === abierta);

    // Cada extintor empieza arriba: así se ve que cambió al siguiente.
    useEffect(() => {
        document
            .querySelector('main')
            ?.scrollTo({ top: 0, behavior: 'smooth' });
    }, []);
    const cuantas = Object.keys(fallas).length;

    const abrir = (clave: string) => {
        setAbierta(clave);
        setBorrador(
            fallas[clave] ?? {
                condicion: '',
                requiere_autorizacion: true,
                foto: null,
            },
        );
    };

    const marcarBien = () => {
        if (abierta) {
            const { [abierta]: _quitada, ...resto } = fallas;
            setFallas(resto);
        }
        setAbierta(null);
    };

    const guardarFalla = () => {
        if (abierta) {
            setFallas({ ...fallas, [abierta]: borrador });
        }
        setAbierta(null);
    };

    const guardar = () => {
        form.transform(() => ({
            items: Object.fromEntries(
                partes.map((p) => [
                    p.clave,
                    fallas[p.clave]
                        ? { estado: 'observado', ...fallas[p.clave] }
                        : { estado: 'conforme' },
                ]),
            ),
            equipo_no_apto: noApto,
            observaciones: noApto ? motivo : null,
        }));
        form.post(url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setFallas({});
                setNoApto(false);
                setMotivo('');
                onGuardado();
            },
            onError: (errores) =>
                toast.error(Object.values(errores)[0] ?? 'No se pudo guardar.'),
        });
    };

    const accionGuardar = (
        <button
            type="button"
            onClick={guardar}
            disabled={form.processing || (noApto && !motivo.trim())}
            className={`min-h-14 w-full rounded-2xl px-3 text-sm font-bold text-white disabled:opacity-50 ${cuantas > 0 || noApto ? 'bg-primary' : 'bg-green-700'}`}
        >
            {cuantas > 0
                ? `Guardar con ${cuantas} ${cuantas === 1 ? 'falla' : 'fallas'} y seguir`
                : textoBoton}
        </button>
    );

    return (
        <div className="space-y-3">
            <p className="text-muted-foreground text-sm">
                Todo empieza en verde. Toca la parte que esté mal.
            </p>

            <div className="border-border rounded-2xl border bg-white p-3">
                <ExtintorPorPartes
                    tipo={tipo}
                    malas={Object.fromEntries(
                        Object.keys(fallas).map((k) => [k, true]),
                    )}
                    onTocar={abrir}
                />
            </div>

            <div className="flex flex-wrap gap-2">
                {partes.map((p) => {
                    const mala = !!fallas[p.clave];

                    return (
                        <button
                            key={p.clave}
                            type="button"
                            onClick={() => abrir(p.clave)}
                            className={`inline-flex min-h-11 items-center gap-1.5 rounded-full border-2 px-3 text-sm font-bold ${
                                mala
                                    ? 'border-red-700 bg-red-500/10 text-red-700 dark:text-red-300'
                                    : 'border-green-700 bg-green-500/10 text-green-800 dark:text-green-300'
                            }`}
                        >
                            {mala ? null : <Check className="size-4" />}
                            {p.nombre} · {mala ? 'con falla' : 'bien'}
                        </button>
                    );
                })}
            </div>

            <label className="border-border flex min-h-11 items-start gap-3 rounded-xl border p-3 text-sm">
                <input
                    type="checkbox"
                    checked={noApto}
                    onChange={(e) => setNoApto(e.target.checked)}
                    className="mt-1 size-4"
                />
                <span>
                    <span className="font-semibold">
                        Extintor no apto para uso
                    </span>
                    <span className="text-muted-foreground block text-xs">
                        Lo da de baja y anula sus certificados de PH y
                        operatividad.
                    </span>
                </span>
            </label>
            {noApto ? (
                <input
                    aria-label="Motivo de que no sea apto"
                    value={motivo}
                    onChange={(e) => setMotivo(e.target.value)}
                    placeholder="¿Por qué no es apto?"
                    className="border-input bg-card min-h-11 w-full rounded-xl border px-3 text-sm"
                />
            ) : null}

            {renderAccion ? renderAccion(accionGuardar) : accionGuardar}

            <Sheet
                open={abierta !== null}
                onOpenChange={(abrirla) => !abrirla && setAbierta(null)}
            >
                <SheetContent
                    side="bottom"
                    className="mx-auto max-w-2xl gap-3 rounded-t-3xl p-4"
                >
                    <div>
                        <SheetDescription className="text-destructive-strong font-mono text-xs font-bold">
                            {serie}
                        </SheetDescription>
                        <SheetTitle className="text-xl font-bold">
                            {parte?.nombre}: ¿qué tiene?
                        </SheetTitle>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {parte?.opciones.map((opcion) => (
                            <button
                                key={opcion}
                                type="button"
                                aria-pressed={borrador.condicion === opcion}
                                onClick={() =>
                                    setBorrador({
                                        ...borrador,
                                        condicion: opcion,
                                    })
                                }
                                className={`min-h-11 rounded-full border px-4 text-sm font-semibold ${
                                    borrador.condicion === opcion
                                        ? 'bg-foreground text-background border-transparent'
                                        : 'border-border text-foreground'
                                }`}
                            >
                                {opcion}
                            </button>
                        ))}
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <BotonFoto
                            archivo={borrador.foto}
                            etiqueta="Tomar foto (obligatoria)"
                            onFoto={(foto) =>
                                setBorrador({ ...borrador, foto })
                            }
                        />
                        <span className="text-muted-foreground text-xs">
                            {borrador.foto
                                ? borrador.foto.name
                                : 'Sin foto no se guarda la falla.'}
                        </span>
                    </div>
                    <label className="border-border flex min-h-12 items-center gap-3 rounded-xl border px-3 text-sm">
                        <input
                            type="checkbox"
                            checked={borrador.requiere_autorizacion}
                            onChange={(e) =>
                                setBorrador({
                                    ...borrador,
                                    requiere_autorizacion: e.target.checked,
                                })
                            }
                            className="size-5"
                        />
                        <span>
                            <span className="block font-bold">
                                Pedir permiso al cliente
                            </span>
                            <span className="text-muted-foreground text-xs">
                                Le llega al vendedor; se repara cuando diga que
                                sí.
                            </span>
                        </span>
                    </label>
                    <div className="flex gap-2">
                        <button
                            type="button"
                            onClick={marcarBien}
                            className="border-border text-success-strong min-h-12 flex-1 rounded-xl border text-sm font-bold"
                        >
                            Está bien
                        </button>
                        <button
                            type="button"
                            onClick={guardarFalla}
                            disabled={!borrador.condicion || !borrador.foto}
                            className="bg-primary text-primary-foreground min-h-12 flex-[2] rounded-xl text-sm font-bold disabled:opacity-50"
                        >
                            Guardar falla
                        </button>
                    </div>
                </SheetContent>
            </Sheet>
        </div>
    );
}
