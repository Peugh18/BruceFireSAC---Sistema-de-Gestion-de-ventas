import * as React from 'react';

import { Input } from '@/components/ui/input';

/** Solo números; quita el +51 cuando pegan el número completo. */
const telefono = (v: string) => {
    const digitos = v.replace(/\D/g, '');

    return (
        digitos.length > 9 && digitos.startsWith('51')
            ? digitos.slice(2)
            : digitos
    ).slice(0, 9);
};

const soloNumeros = (largo: number) => (v: string) =>
    v.replace(/\D/g, '').slice(0, largo);

/**
 * Campo que no deja escribir lo que no corresponde (un teléfono solo acepta
 * números, una placa solo letras y números) y avisa al salir del campo si el
 * dato quedó incompleto. Se usa igual que Input; el valor que llega a
 * onChange ya viene limpio.
 */
const TIPOS = {
    celular: {
        limpiar: telefono,
        valido: /^9\d{8}$/,
        aviso: 'Un celular tiene 9 números y empieza con 9.',
        inputMode: 'numeric',
        autoComplete: 'tel-national',
        placeholder: '987 654 321',
    },
    telefono: {
        // Celular (9) o fijo con código de ciudad (8, o 9 con el 0).
        limpiar: telefono,
        valido: /^(9\d{8}|0?[1-8]\d{7})$/,
        aviso: 'Escribe un celular de 9 números (empieza con 9) o un fijo con su código.',
        inputMode: 'tel',
        autoComplete: 'tel-national',
        placeholder: '987 654 321',
    },
    dni: {
        limpiar: soloNumeros(8),
        valido: /^\d{8}$/,
        aviso: 'El DNI tiene 8 números.',
        inputMode: 'numeric',
        autoComplete: 'off',
        placeholder: '8 números',
    },
    ruc: {
        limpiar: soloNumeros(11),
        valido: /^\d{11}$/,
        aviso: 'El RUC tiene 11 números.',
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
        valido: /^(?:[A-Z0-9]-?){5}[A-Z0-9]$/,
        aviso: 'La placa tiene 6 letras o números, por ejemplo ABC-123.',
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
        valido: /^[A-Z]\d{8}$/,
        aviso: 'La licencia tiene una letra y 8 números, por ejemplo Q12345678.',
        inputMode: 'text',
        autoComplete: 'off',
        placeholder: 'Q12345678',
    },
    cuenta: {
        limpiar: soloNumeros(20),
        valido: /^\d{8,20}$/,
        aviso: 'Escribe solo números (entre 8 y 20).',
        inputMode: 'numeric',
        autoComplete: 'off',
        placeholder: 'Solo números',
    },
    cci: {
        limpiar: soloNumeros(20),
        valido: /^\d{20}$/,
        aviso: 'El CCI tiene 20 números.',
        inputMode: 'numeric',
        autoComplete: 'off',
        placeholder: '20 números',
    },
} as const;

export type TipoInputPeru = keyof typeof TIPOS;

function InputPeru({
    tipo,
    onChange,
    onBlur,
    ...props
}: Omit<React.ComponentProps<typeof Input>, 'type'> & {
    tipo: TipoInputPeru;
}) {
    const regla = TIPOS[tipo];
    const idAviso = React.useId();
    const [tocado, setTocado] = React.useState(false);
    const valor = typeof props.value === 'string' ? props.value : '';
    const incompleto = tocado && valor !== '' && !regla.valido.test(valor);

    return (
        <>
            <Input
                inputMode={regla.inputMode}
                autoComplete={regla.autoComplete}
                placeholder={regla.placeholder}
                aria-invalid={incompleto || props['aria-invalid']}
                aria-describedby={
                    incompleto ? idAviso : props['aria-describedby']
                }
                {...props}
                onChange={(event) => {
                    event.target.value = regla.limpiar(event.target.value);
                    onChange?.(event);
                }}
                onBlur={(event) => {
                    setTocado(true);
                    onBlur?.(event);
                }}
            />
            {incompleto ? (
                <p
                    id={idAviso}
                    role="alert"
                    className="text-destructive-strong mt-1 text-xs"
                >
                    {regla.aviso}
                </p>
            ) : null}
        </>
    );
}

export { InputPeru };
