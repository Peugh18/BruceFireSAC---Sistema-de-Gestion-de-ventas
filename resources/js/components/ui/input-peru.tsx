import * as React from 'react';

import { Input } from '@/components/ui/input';

/**
 * Campo que no deja escribir lo que no corresponde: un teléfono solo acepta
 * números, una placa solo letras y números, y cada uno corta en su largo.
 * Se usa igual que Input; el valor que llega a onChange ya viene limpio.
 */
/** Solo números; quita el +51 cuando pegan el número completo. */
const telefono = (v: string) => {
    const digitos = v.replace(/\D/g, '');

    return (
        digitos.length > 9 && digitos.startsWith('51')
            ? digitos.slice(2)
            : digitos
    ).slice(0, 9);
};

const TIPOS = {
    celular: {
        limpiar: telefono,
        inputMode: 'numeric',
        autoComplete: 'tel-national',
        placeholder: '987 654 321',
    },
    telefono: {
        // Celular (9) o fijo con código de ciudad (hasta 9 con el 0).
        limpiar: telefono,
        inputMode: 'tel',
        autoComplete: 'tel-national',
        placeholder: '987 654 321',
    },
    dni: {
        limpiar: (v: string) => v.replace(/\D/g, '').slice(0, 8),
        inputMode: 'numeric',
        autoComplete: 'off',
        placeholder: '8 números',
    },
    ruc: {
        limpiar: (v: string) => v.replace(/\D/g, '').slice(0, 11),
        inputMode: 'numeric',
        autoComplete: 'off',
        placeholder: '11 números',
    },
    placa: {
        limpiar: (v: string) =>
            v
                .toUpperCase()
                .replace(/[^A-Z0-9-]/g, '')
                .slice(0, 7),
        inputMode: 'text',
        autoComplete: 'off',
        placeholder: 'ABC-123',
    },
    licencia: {
        limpiar: (v: string) =>
            v
                .toUpperCase()
                .replace(/[^A-Z0-9]/g, '')
                .slice(0, 9),
        inputMode: 'text',
        autoComplete: 'off',
        placeholder: 'Q12345678',
    },
    cuenta: {
        limpiar: (v: string) => v.replace(/\D/g, '').slice(0, 20),
        inputMode: 'numeric',
        autoComplete: 'off',
        placeholder: 'Solo números',
    },
    cci: {
        limpiar: (v: string) => v.replace(/\D/g, '').slice(0, 20),
        inputMode: 'numeric',
        autoComplete: 'off',
        placeholder: '20 números',
    },
} as const;

export type TipoInputPeru = keyof typeof TIPOS;

function InputPeru({
    tipo,
    onChange,
    ...props
}: Omit<React.ComponentProps<typeof Input>, 'type'> & {
    tipo: TipoInputPeru;
}) {
    const regla = TIPOS[tipo];

    return (
        <Input
            inputMode={regla.inputMode}
            autoComplete={regla.autoComplete}
            placeholder={regla.placeholder}
            {...props}
            onChange={(event) => {
                event.target.value = regla.limpiar(event.target.value);
                onChange?.(event);
            }}
        />
    );
}

export { InputPeru };
