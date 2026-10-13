import { useEffect, useId, useRef } from 'react';
import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

const CAMPOS = 'input, select, textarea';

/**
 * Mensaje de error de un campo. Además de mostrarse, marca el campo que lo
 * precede como inválido (aria-invalid) y lo enlaza con el mensaje
 * (aria-describedby), para que un lector de pantalla sepa cuál falló.
 */
export default function InputError({
    message,
    className = '',
    ...props
}: HTMLAttributes<HTMLParagraphElement> & { message?: string }) {
    const idGenerado = useId();
    const id = props.id ?? idGenerado;
    const parrafo = useRef<HTMLParagraphElement>(null);

    useEffect(() => {
        const anterior = parrafo.current?.previousElementSibling;
        const campo = anterior?.matches(CAMPOS)
            ? anterior
            : anterior?.querySelector(CAMPOS);

        if (!campo || !message) {
            return;
        }

        campo.setAttribute('aria-invalid', 'true');
        campo.setAttribute('aria-describedby', id);

        return () => {
            campo.removeAttribute('aria-invalid');
            campo.removeAttribute('aria-describedby');
        };
    }, [message, id]);

    return message ? (
        <p
            ref={parrafo}
            role="alert"
            {...props}
            id={id}
            className={cn('text-destructive-strong text-sm', className)}
        >
            {message}
        </p>
    ) : null;
}
