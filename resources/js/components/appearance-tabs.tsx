import type { LucideIcon } from 'lucide-react';
import { Monitor, Moon, Sun } from 'lucide-react';
import type { HTMLAttributes } from 'react';

import { TabsBf } from '@/components/ui/tabs-bf';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';

/**
 * Selector de tema (claro / oscuro / sistema) de la pantalla de apariencia.
 * Usa la barra de pestañas unificada de la aplicación.
 */
export default function AppearanceToggleTab({
    className = '',
    ...props
}: HTMLAttributes<HTMLDivElement>) {
    const { appearance, updateAppearance } = useAppearance();

    const pestanas: { id: Appearance; icono: LucideIcon; titulo: string }[] = [
        { id: 'light', icono: Sun, titulo: 'Claro' },
        { id: 'dark', icono: Moon, titulo: 'Oscuro' },
        { id: 'system', icono: Monitor, titulo: 'Sistema' },
    ];

    return (
        <TabsBf
            pestanas={pestanas}
            activa={appearance}
            onCambiar={(tema) => updateAppearance(tema)}
            etiqueta="Tema de la interfaz"
            idBase="apariencia"
            className={className}
            resto={props}
        />
    );
}
