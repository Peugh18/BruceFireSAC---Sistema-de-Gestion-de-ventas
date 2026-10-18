import { usePage } from '@inertiajs/react';

import BotonLista from '@/components/lista-administrable';
import { NativeSelect } from '@/components/ui/native-select';
import marcasRutas from '@/routes/marcas';

/** Botón para agregar, renombrar o quitar marcas, como el de agentes. */
export function BotonMarcas() {
    return (
        <BotonLista
            config={{
                titulo: 'Marcas',
                singular: 'marca',
                descripcion:
                    'Las que salen al registrar un extintor. Quitar una no cambia los extintores que ya la tienen.',
                ejemplo: 'Nombre, por ejemplo Badger',
                clave: 'marcas',
                prop: 'marcasExtintor',
                rutas: {
                    index: marcasRutas.index.url(),
                    store: marcasRutas.store.url(),
                    update: (id) => marcasRutas.update.url(id),
                    destroy: (id) => marcasRutas.destroy.url(id),
                },
            }}
        />
    );
}

/**
 * Selector de marca de la lista del sistema con su botón para administrarla.
 * Una marca guardada que ya no está en la lista se sigue mostrando.
 */
export default function CampoMarca({
    value,
    onChange,
    vacia = 'Sin marca / no legible',
    className = 'text-foreground h-11 min-w-0 flex-1 text-sm sm:h-9',
    id,
    disabled = false,
    required = false,
}: {
    disabled?: boolean;
    required?: boolean;
    value: string;
    onChange: (marca: string) => void;
    vacia?: string;
    className?: string;
    id?: string;
}) {
    const marcas = usePage().props.marcasExtintor ?? [];
    const opciones =
        value && !marcas.includes(value) ? [value, ...marcas] : marcas;

    return (
        <div className="flex gap-1.5">
            <NativeSelect
                id={id}
                aria-label="Marca"
                value={value}
                onChange={(e) => onChange(e.target.value)}
                disabled={disabled}
                required={required}
                className={className}
            >
                <option value="">{vacia}</option>
                {opciones.map((marca) => (
                    <option key={marca} value={marca}>
                        {marca}
                    </option>
                ))}
            </NativeSelect>
            {disabled ? null : <BotonMarcas />}
        </div>
    );
}
