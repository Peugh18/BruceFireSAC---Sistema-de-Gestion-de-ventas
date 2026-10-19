import { Link } from '@inertiajs/react';

export type PaginasOrdenes = {
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export default function PaginacionOrdenes({
    paginas,
}: {
    paginas: PaginasOrdenes;
}) {
    if (paginas.last_page <= 1) {
        return null;
    }
    return (
        <nav
            aria-label="Páginas de servicios"
            className="mt-4 flex items-center justify-between gap-3"
        >
            {paginas.prev_page_url ? (
                <Link
                    href={paginas.prev_page_url}
                    className="border-border bg-card focus-visible:ring-ring flex min-h-11 items-center rounded-xl border px-4 text-sm focus-visible:ring-2"
                >
                    Anterior
                </Link>
            ) : (
                <span />
            )}
            <span className="text-muted-foreground text-sm">
                {paginas.current_page} de {paginas.last_page}
            </span>
            {paginas.next_page_url ? (
                <Link
                    href={paginas.next_page_url}
                    className="border-border bg-card focus-visible:ring-ring flex min-h-11 items-center rounded-xl border px-4 text-sm focus-visible:ring-2"
                >
                    Siguiente
                </Link>
            ) : (
                <span />
            )}
        </nav>
    );
}
