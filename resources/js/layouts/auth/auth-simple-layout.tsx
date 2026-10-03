import { Link } from '@inertiajs/react';
import { LockKeyhole, Package, Receipt, Wrench } from 'lucide-react';
import ChispaAvatar from '@/components/chispa-avatar';
import { ThemeToggle } from '@/components/theme-toggle';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="bf-auth-shell">
            <section className="bf-auth-brand" aria-label="Bruce Fire">
                <div
                    className="bf-auth-orbit bf-auth-orbit-outer"
                    aria-hidden="true"
                />
                <div
                    className="bf-auth-orbit bf-auth-orbit-inner"
                    aria-hidden="true"
                />
                <Link href={home()} className="relative z-10 w-fit">
                    <img
                        src="/brand/logo-blanco.svg"
                        alt="Extintores Bruce Fire"
                        className="bf-auth-logo"
                    />
                </Link>
                <div className="bf-auth-message">
                    <h2>Tu seguridad, nuestro compromiso.</h2>
                    <p>
                        El sistema de gestión de Extintores Bruce Fire: ventas,
                        servicios, almacén y certificados de tus sedes en un
                        solo lugar.
                    </p>
                    <ul>
                        <li>
                            <span>
                                <Receipt aria-hidden="true" />
                            </span>
                            Ventas con comprobante electrónico SUNAT
                        </li>
                        <li>
                            <span>
                                <Wrench aria-hidden="true" />
                            </span>
                            Órdenes de servicio y certificados
                        </li>
                        <li>
                            <span>
                                <Package aria-hidden="true" />
                            </span>
                            Stock de extintores por sede
                        </li>
                    </ul>
                </div>
                <div className="bf-auth-mascot">
                    <ChispaAvatar pose="saludo" size={300} tema="oscuro" />
                </div>
                <p className="bf-auth-footer">
                    <LockKeyhole
                        className="size-4 shrink-0"
                        aria-hidden="true"
                    />
                    Acceso solo para el personal de Bruce Fire · Trujillo, Perú
                </p>
            </section>
            <main className="bf-auth-main">
                <div className="flex justify-end">
                    <ThemeToggle showLabel />
                </div>
                <div className="bf-auth-form-area">
                    <div className="bf-auth-form-column">
                        <div className="bf-auth-heading">
                            <h1>{title}</h1>
                            <p>{description}</p>
                        </div>
                        {children}
                    </div>
                </div>
            </main>
        </div>
    );
}
