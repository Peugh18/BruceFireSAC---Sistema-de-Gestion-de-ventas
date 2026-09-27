import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name.startsWith('publico/'):
                // Páginas públicas (verificación de certificados por QR): sin
                // sesión ni sidebar.
                return null;
            case name.startsWith('errors/'):
                // Página de error propia, sin marca del starter-kit ni
                // sidebar genérico — debe verse igual sin sesión iniciada.
                return null;
            case name.startsWith('vendedor/'):
                // Las páginas de vendedor se envuelven a sí mismas con
                // VendedorLayout (sidebar propio) — no usan el AppLayout
                // genérico, para no duplicar el sidebar.
                return null;
            case name.startsWith('almacen/'):
                // Igual que vendedor/: AlmacenLayout ya trae su propio
                // sidebar, no debe envolverse con el AppLayout genérico.
                return null;
            case name.startsWith('tecnico-planta/'):
            case name.startsWith('tecnico-campo/'):
                // Layouts mobile-first propios: no envolver con AppLayout genérico.
                return null;
            case name.startsWith('gerente/'):
                // GerenteLayout ya trae su propio sidebar de escritorio, no duplicar AppLayout.
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
            case name.startsWith('teams/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        // Rojo de la marca; aparece solo si la visita tarda más de 250 ms.
        color: '#D20404',
        delay: 250,
        showSpinner: false,
    },
});

// This will set light / dark mode on load...
initializeTheme();
