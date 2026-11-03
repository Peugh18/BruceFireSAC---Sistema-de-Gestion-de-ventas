import type { ReactNode } from 'react';

type PageHeaderProps = {
    title: string;
    description?: ReactNode;
    actions?: ReactNode;
};

/**
 * Estilo único del título de pantalla. Las pantallas con un encabezado
 * propio (íconos, saludos) lo usan en su h1 para verse igual que el resto.
 */
export const pageTitleClass =
    "text-foreground font-['Oswald',sans-serif] text-[22px] leading-tight font-semibold text-balance";

/**
 * Encabezado de pantalla de los paneles por rol: un solo h1 con el mismo
 * nombre del menú, una línea de ayuda y la acción principal a la derecha.
 */
export function PageHeader({ title, description, actions }: PageHeaderProps) {
    return (
        <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
            <div className="min-w-0">
                <h1 className={pageTitleClass}>{title}</h1>
                {description && (
                    <p className="text-muted-foreground mt-1 max-w-[70ch] text-[12.5px] text-pretty">
                        {description}
                    </p>
                )}
            </div>
            {actions && (
                <div className="flex shrink-0 flex-wrap items-center gap-2">
                    {actions}
                </div>
            )}
        </div>
    );
}
