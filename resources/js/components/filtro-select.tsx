import { NativeSelect } from '@/components/ui/native-select';

/**
 * Filtro de una lista como desplegable corto («Estado: Todas»). Reemplaza las
 * filas largas de pestañas o botones, que ocupan todo el ancho y se cortan en
 * el celular.
 */
export default function FiltroSelect({
    etiqueta,
    valor,
    opciones,
    onCambiar,
    className = '',
}: {
    etiqueta: string;
    valor: string;
    opciones: readonly { value: string; label: string }[];
    onCambiar: (valor: string) => void;
    className?: string;
}) {
    return (
        <label
            className={`text-muted-foreground flex flex-col gap-1 text-xs ${className}`}
        >
            {etiqueta}
            <NativeSelect
                value={valor}
                onChange={(event) => onCambiar(event.target.value)}
                className="text-foreground h-11 min-w-[9rem] text-sm sm:h-9"
            >
                {opciones.map((opcion) => (
                    <option key={opcion.value} value={opcion.value}>
                        {opcion.label}
                    </option>
                ))}
            </NativeSelect>
        </label>
    );
}
