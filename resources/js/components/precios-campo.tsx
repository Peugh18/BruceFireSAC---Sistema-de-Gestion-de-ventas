export type PrecioCampo = {
    id: number;
    servicio: string | null;
    cantidad: number;
    precio_lista: number | null;
    precio: number | null;
    estado: string | null;
};

const soles = (valor: number) =>
    valor.toLocaleString('es-PE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

const ETIQUETAS: Record<string, string> = {
    rebajado: 'Rebajado',
    sin_lista: 'Sin precio de lista',
};

/** El precio que campo dejó al cliente en el recojo: es el que se cobra. */
export default function PreciosCampo({ precios }: { precios: PrecioCampo[] }) {
    if (precios.length === 0) {
        return null;
    }

    const total = precios.reduce(
        (suma, p) => suma + p.cantidad * (p.precio ?? 0),
        0,
    );

    return (
        <section
            aria-label="Precio que dejó campo"
            className="border-border bg-card overflow-hidden rounded-3xl border"
        >
            <div className="flex flex-wrap items-center justify-between gap-2 px-5 py-4">
                <div>
                    <h2 className="font-bold">Precio que dejó campo</h2>
                    <p className="text-muted-foreground text-xs">
                        El cliente lo firmó en el recojo. Es el que se cobra.
                    </p>
                </div>
                <span className="font-['Oswald',sans-serif] text-2xl font-semibold">
                    S/ {soles(total)}
                </span>
            </div>
            <ul className="divide-border border-border divide-y border-t text-sm">
                {precios.map((p) => (
                    <li
                        key={p.id}
                        className="flex flex-wrap items-center justify-between gap-2 px-5 py-3"
                    >
                        <span className="font-semibold">
                            {p.servicio} × {p.cantidad}
                        </span>
                        <span className="flex items-center gap-2">
                            {p.precio_lista !== null &&
                            p.precio !== p.precio_lista ? (
                                <span className="text-muted-foreground text-xs">
                                    lista <s>{soles(p.precio_lista)}</s>
                                </span>
                            ) : null}
                            <span className="font-bold tabular-nums">
                                c/u {soles(p.precio ?? 0)}
                            </span>
                            {p.estado && ETIQUETAS[p.estado] ? (
                                <span className="bg-muted rounded-full px-2 py-0.5 text-xs font-bold">
                                    {ETIQUETAS[p.estado]}
                                </span>
                            ) : null}
                        </span>
                    </li>
                ))}
            </ul>
        </section>
    );
}
