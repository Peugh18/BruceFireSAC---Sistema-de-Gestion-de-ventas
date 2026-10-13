import * as React from 'react';

import { Input } from '@/components/ui/input';

/** Vacío vale (el correo suele ser opcional); si hay algo, debe tener @ y un dominio. */
export const correoValido = (correo: string) =>
    correo === '' || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo);

/**
 * Campo de correo: quita los espacios al escribir y, al salir del campo, avisa
 * si falta la @ o el dominio. Se usa igual que Input; onChange ya recibe el
 * valor limpio.
 */
function InputCorreo({
    onChange,
    onBlur,
    ...props
}: Omit<React.ComponentProps<typeof Input>, 'type'>) {
    const idAviso = React.useId();
    const [tocado, setTocado] = React.useState(false);
    const valor = typeof props.value === 'string' ? props.value : '';
    const incorrecto = tocado && !correoValido(valor);

    return (
        <>
            <Input
                type="email"
                inputMode="email"
                autoComplete="off"
                placeholder="nombre@empresa.com"
                aria-invalid={incorrecto || props['aria-invalid']}
                aria-describedby={
                    incorrecto ? idAviso : props['aria-describedby']
                }
                {...props}
                onChange={(event) => {
                    event.target.value = event.target.value.trim();
                    setTocado(false);
                    onChange?.(event);
                }}
                onBlur={(event) => {
                    setTocado(true);
                    onBlur?.(event);
                }}
            />
            {incorrecto ? (
                <p
                    id={idAviso}
                    role="alert"
                    className="text-destructive-strong mt-1 text-xs"
                >
                    Escribe un correo con @, por ejemplo nombre@empresa.com.
                </p>
            ) : null}
        </>
    );
}

export { InputCorreo };
