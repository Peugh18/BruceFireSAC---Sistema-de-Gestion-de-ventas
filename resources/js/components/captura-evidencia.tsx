import { Camera, Mic, Square } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface FotoProps {
    onFoto: (archivo: File | null) => void;
    archivo?: File | null;
    etiqueta?: string;
}

/** Abre la cámara trasera del celular; en escritorio abre el selector de archivos. */
export function BotonFoto({
    onFoto,
    archivo,
    etiqueta = 'Tomar foto',
}: FotoProps) {
    const entrada = useRef<HTMLInputElement>(null);

    return (
        <>
            <input
                ref={entrada}
                type="file"
                accept="image/*"
                capture="environment"
                className="sr-only"
                aria-label={etiqueta}
                onChange={(e) => {
                    onFoto(e.target.files?.[0] ?? null);
                    e.target.value = '';
                }}
            />
            <button
                type="button"
                onClick={() => entrada.current?.click()}
                className="inline-flex min-h-[44px] items-center gap-1.5 rounded-xl border border-neutral-300 px-3 text-xs font-bold text-neutral-800 hover:bg-neutral-50 dark:border-neutral-600 dark:text-neutral-100"
            >
                <Camera className="h-4 w-4" />
                {archivo ? 'Cambiar foto' : etiqueta}
            </button>
        </>
    );
}

interface AudioProps {
    onAudio: (archivo: File | null) => void;
    archivo?: File | null;
}

/** Graba una nota de voz con MediaRecorder, sin librerías. */
export function GrabadoraAudio({ onAudio, archivo }: AudioProps) {
    const grabador = useRef<MediaRecorder | null>(null);
    const [grabando, setGrabando] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(
        () => () => {
            grabador.current?.stream.getTracks().forEach((t) => t.stop());
        },
        [],
    );

    const empezar = async () => {
        setError(null);

        try {
            const flujo = await navigator.mediaDevices.getUserMedia({
                audio: true,
            });
            const partes: Blob[] = [];
            const rec = new MediaRecorder(flujo);
            rec.ondataavailable = (e) => partes.push(e.data);
            rec.onstop = () => {
                flujo.getTracks().forEach((t) => t.stop());
                const tipo = rec.mimeType || 'audio/webm';
                const extension = tipo.includes('mp4') ? 'm4a' : 'webm';
                onAudio(
                    new File(partes, `nota-de-voz.${extension}`, {
                        type: tipo,
                    }),
                );
            };
            rec.start();
            grabador.current = rec;
            setGrabando(true);
        } catch {
            setError('No se pudo usar el micrófono. Revisa el permiso.');
        }
    };

    const parar = () => {
        grabador.current?.stop();
        setGrabando(false);
    };

    return (
        <div className="inline-flex flex-col gap-1">
            <button
                type="button"
                onClick={grabando ? parar : empezar}
                className={`inline-flex min-h-[44px] items-center gap-1.5 rounded-xl border px-3 text-xs font-bold ${
                    grabando
                        ? 'border-red-600 bg-red-600 text-white'
                        : 'border-neutral-300 text-neutral-800 hover:bg-neutral-50 dark:border-neutral-600 dark:text-neutral-100'
                }`}
            >
                {grabando ? (
                    <Square className="h-4 w-4" />
                ) : (
                    <Mic className="h-4 w-4" />
                )}
                {grabando
                    ? 'Detener grabación'
                    : archivo
                      ? 'Grabar de nuevo'
                      : 'Grabar nota de voz'}
            </button>
            {error && (
                <span
                    className="text-[11px] font-semibold text-red-600"
                    role="alert"
                >
                    {error}
                </span>
            )}
        </div>
    );
}
