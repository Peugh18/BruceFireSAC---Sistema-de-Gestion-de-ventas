import { useEffect, useId, useState } from 'react';
import { codigosProducto } from '@/routes/gerente/sunat';

type Codigo = { codigo: string; descripcion: string; clase: string };

/**
 * Código de producto SUNAT (catálogo 25, UNSPSC). Se busca por nombre o por
 * código en el catálogo oficial; no hace falta saberse los 8 dígitos.
 */
export default function CodigoSunatInput({
    id,
    teamSlug,
    value,
    onChange,
    error,
    tipo,
    label = 'Código SUNAT',
}: {
    id: string;
    teamSlug: string;
    value: string;
    onChange: (codigo: string) => void;
    error?: string;
    /** Solo bienes (segmentos 10–60) o solo servicios (70–94). */
    tipo: 'producto' | 'servicio';
    label?: string;
}) {
    const listaId = useId();
    const [texto, setTexto] = useState(value);
    const [resultados, setResultados] = useState<Codigo[]>([]);
    const [elegido, setElegido] = useState<Codigo | null>(null);

    // Solo un código completo llega de afuera (editar, sugerencia); lo que se
    // está escribiendo no se pisa.
    useEffect(() => {
        if (value) setTexto(value);
    }, [value]);

    useEffect(() => {
        const termino = texto.trim();
        if (termino.length < 2) {
            setResultados([]);
            return;
        }

        const controller = new AbortController();
        const espera = window.setTimeout(async () => {
            try {
                const respuesta = await fetch(
                    codigosProducto.url(teamSlug, {
                        query: { buscar: termino, tipo },
                    }),
                    { signal: controller.signal },
                );
                const { codigos } = (await respuesta.json()) as {
                    codigos: Codigo[];
                };
                setResultados(codigos);
                setElegido(
                    codigos.find((codigo) => codigo.codigo === termino) ?? null,
                );
            } catch {
                // Búsqueda cancelada o sin conexión: se sigue escribiendo.
            }
        }, 300);

        return () => {
            controller.abort();
            window.clearTimeout(espera);
        };
    }, [texto, teamSlug, tipo]);

    return (
        <div>
            <label htmlFor={id} className="mb-1.5 block font-medium">
                {label}
            </label>
            <input
                id={id}
                list={listaId}
                value={texto}
                autoComplete="off"
                placeholder={
                    tipo === 'servicio'
                        ? 'Busca: mantenimiento extintores, capacitación…'
                        : 'Busca: luces, detector, 4619…'
                }
                onChange={(event) => {
                    const nuevo = event.target.value;
                    setTexto(nuevo);
                    onChange(/^\d{8}$/.test(nuevo.trim()) ? nuevo.trim() : '');
                }}
                className="border-border bg-background focus-visible:outline-primary min-h-11 w-full rounded-lg border px-3 py-2 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 aria-invalid:border-red-600"
                aria-invalid={!!error}
                aria-describedby={`${id}-ayuda`}
            />
            <datalist id={listaId}>
                {resultados.map((codigo) => (
                    <option key={codigo.codigo} value={codigo.codigo}>
                        {codigo.clase && codigo.clase !== codigo.descripcion
                            ? `${codigo.descripcion} · ${codigo.clase}`
                            : codigo.descripcion}
                    </option>
                ))}
            </datalist>
            <p
                id={`${id}-ayuda`}
                className="text-muted-foreground mt-1 text-xs"
            >
                {elegido
                    ? `${elegido.descripcion}${elegido.clase && elegido.clase !== elegido.descripcion ? ` · ${elegido.clase}` : ''}`
                    : 'Opcional. Clasificación de SUNAT que va en la factura.'}
            </p>
            {error && (
                <p
                    role="alert"
                    className="text-destructive-strong mt-1 text-xs dark:text-red-300"
                >
                    {error}
                </p>
            )}
        </div>
    );
}
