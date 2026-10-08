import { AlertCircleIcon } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

/**
 * Bloque de errores de validación devueltos por el backend.
 * Se renderiza con role="alert" para que los lectores de pantalla lo anuncien.
 */
export default function AlertError({
    errors,
    title,
}: {
    errors: string[];
    title?: string;
}) {
    const mensajes = Array.from(new Set(errors.filter(Boolean)));

    if (mensajes.length === 0) {
        return null;
    }

    return (
        <Alert variant="destructive" role="alert">
            <AlertCircleIcon />
            <AlertTitle>
                {title ?? 'No se pudo completar la acción.'}
            </AlertTitle>
            <AlertDescription>
                <ul className="list-inside list-disc text-sm">
                    {mensajes.map((error, index) => (
                        <li key={index}>{error}</li>
                    ))}
                </ul>
            </AlertDescription>
        </Alert>
    );
}
