import { Form, Head } from '@inertiajs/react';
import { ArrowRight, LockKeyhole, Mail } from 'lucide-react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import PasskeyVerify from '@/components/passkey-verify';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    return (
        <>
            <Head title="Iniciar sesión" />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="bf-login-form flex flex-col gap-[18px]"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-[18px]">
                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    Correo electrónico
                                </Label>
                                <div className="relative">
                                    <Mail
                                        className="text-muted-foreground pointer-events-none absolute top-1/2 left-3.5 z-10 size-[18px] -translate-y-1/2"
                                        aria-hidden="true"
                                    />
                                    <Input
                                        id="email"
                                        type="email"
                                        name="email"
                                        required
                                        autoFocus
                                        autoComplete="email"
                                        placeholder="nombre@brucefire.pe"
                                        className="bg-card h-12 rounded-xl pl-11 text-[15px] shadow-none"
                                        aria-invalid={!!errors.email}
                                        aria-describedby={
                                            errors.email
                                                ? 'email-error'
                                                : undefined
                                        }
                                    />
                                </div>
                                <InputError
                                    id="email-error"
                                    message={errors.email}
                                />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-center">
                                    <Label htmlFor="password">Contraseña</Label>
                                    {canResetPassword && (
                                        <TextLink
                                            href={request()}
                                            className="text-primary ml-auto text-[13.5px] dark:text-[#ff948a]"
                                        >
                                            ¿Olvidaste tu contraseña?
                                        </TextLink>
                                    )}
                                </div>
                                <div className="relative">
                                    <LockKeyhole
                                        className="text-muted-foreground pointer-events-none absolute top-1/2 left-3.5 z-10 size-[18px] -translate-y-1/2"
                                        aria-hidden="true"
                                    />
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        required
                                        autoComplete="current-password"
                                        className="bg-card h-12 rounded-xl pl-11 text-[15px] shadow-none"
                                        aria-invalid={!!errors.password}
                                        aria-describedby={
                                            errors.password
                                                ? 'password-error'
                                                : undefined
                                        }
                                    />
                                </div>
                                <InputError
                                    id="password-error"
                                    message={errors.password}
                                />
                            </div>

                            <div className="flex items-center space-x-3">
                                <Checkbox id="remember" name="remember" />
                                <Label
                                    htmlFor="remember"
                                    className="text-muted-foreground text-sm font-normal"
                                >
                                    Recordarme en este equipo
                                </Label>
                            </div>

                            <Button
                                type="submit"
                                className="h-[50px] w-full gap-2 rounded-xl text-[15.5px] font-bold"
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                Iniciar sesión
                                <ArrowRight
                                    className="size-[18px]"
                                    aria-hidden="true"
                                />
                            </Button>
                        </div>
                    </>
                )}
            </Form>

            <div className="bf-login-passkey">
                <PasskeyVerify
                    label="Entrar con passkey"
                    separator="o"
                    separatorPosition="before"
                    className="bg-card h-12 rounded-xl text-[15px] font-semibold shadow-none"
                />
            </div>
            <p className="text-muted-foreground text-[13.5px] leading-relaxed">
                ¿No puedes entrar? Pide ayuda al gerente: él crea y reactiva las
                cuentas de todas las sedes.
            </p>

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}
        </>
    );
}

Login.layout = {
    title: 'Inicia sesión',
    description: 'Entra con el correo y la contraseña que te dio la empresa.',
};
