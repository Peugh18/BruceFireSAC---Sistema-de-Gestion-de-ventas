import { usePasskeyRegister } from '@laravel/passkeys/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    onSuccess: () => void;
};

export default function PasskeyRegistration({ onSuccess }: Props) {
    const [name, setName] = useState(() => {
        const ua = navigator.userAgent;

        const browser = [
            { pattern: /Edg|Edge/, name: 'Edge' },
            { pattern: /OPR|Opera|OPiOS/, name: 'Opera' },
            { pattern: /Firefox|FxiOS/, name: 'Firefox' },
            { pattern: /Chrome|CriOS/, name: 'Chrome' },
            { pattern: /Safari/, name: 'Safari' },
        ].find(({ pattern }) => pattern.test(ua))?.name;

        const os = [
            { pattern: /iPhone/, name: 'iPhone' },
            { pattern: /iPad|Macintosh(?=.*Mobile)/, name: 'iPad' },
            { pattern: /Android/, name: 'Android' },
            { pattern: /Mac/, name: 'Mac' },
            { pattern: /Windows/, name: 'Windows' },
        ].find(({ pattern }) => pattern.test(ua))?.name;

        return [browser, os].filter(Boolean).join(' en ') || '';
    });

    const [showForm, setShowForm] = useState(false);
    const { register, isLoading, error, errorInstance, isSupported } =
        usePasskeyRegister({
            onSuccess: () => {
                setName('');
                setShowForm(false);
                onSuccess();
            },
        });

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!name.trim()) {
            return;
        }

        await register(name);
    };

    const handleCancel = () => {
        setShowForm(false);
        setName('');
    };

    if (!isSupported || !window.isSecureContext) {
        return (
            <div className="text-muted-foreground text-sm">
                Las llaves necesitan un navegador compatible y una conexión
                HTTPS (o localhost para pruebas). Usa tu contraseña para entrar
                desde este navegador.
            </div>
        );
    }

    if (!showForm) {
        return (
            <Button variant="outline" onClick={() => setShowForm(true)}>
                Añadir llave de acceso
            </Button>
        );
    }

    return (
        <form
            onSubmit={handleSubmit}
            className="border-border bg-muted/50 space-y-4 rounded-lg border p-4"
        >
            <div className="grid gap-2">
                <Label htmlFor="passkey-name">
                    Nombre de la llave de acceso
                </Label>
                <Input
                    id="passkey-name"
                    type="text"
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    placeholder="Ej. Mi laptop, mi celular"
                    className="border-foreground/20 mt-1 block w-full"
                    autoFocus
                />
                <p className="text-muted-foreground text-xs">
                    El nombre te ayuda a reconocer esta llave de acceso.
                </p>
            </div>

            <p className="text-muted-foreground text-xs">
                Al registrar, tu dispositivo pedirá huella, reconocimiento
                facial, PIN o una llave física. El nombre de arriba solo
                identifica la llave.
            </p>
            {error && (
                <InputError
                    message={
                        errorInstance?.name === 'UserCancelledError'
                            ? 'No se completó la verificación del dispositivo: se canceló o venció el tiempo. Vuelve a intentarlo y confirma con tu PIN, huella o llave física.'
                            : errorInstance?.name === 'InvalidDomainError'
                              ? 'Este dominio no admite el registro de llaves. Usa la dirección HTTPS oficial de Bruce Fire o localhost en pruebas.'
                              : errorInstance?.name === 'PasskeyExistsError'
                                ? 'Este dispositivo ya tiene una llave registrada para esta cuenta.'
                                : errorInstance?.name === 'NotSupportedError'
                                  ? 'Tu navegador o dispositivo no permite registrar esta llave. Puedes seguir usando tu contraseña.'
                                  : 'No se pudo registrar la llave de acceso. Inténtalo de nuevo; si persiste, usa tu contraseña y comunica el problema.'
                    }
                />
            )}

            <div className="flex gap-2">
                <Button type="submit" disabled={isLoading || !name.trim()}>
                    {isLoading ? 'Registrando…' : 'Registrar llave de acceso'}
                </Button>
                <Button type="button" variant="ghost" onClick={handleCancel}>
                    Cancelar
                </Button>
            </div>
        </form>
    );
}
