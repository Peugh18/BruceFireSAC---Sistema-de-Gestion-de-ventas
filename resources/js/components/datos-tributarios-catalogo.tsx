import { NativeSelect } from '@/components/ui/native-select';

export type EstadoDetraccion = 'pendiente' | 'no_sujeto' | 'sujeto';
export type OpcionDetraccion = {
    codigo: string;
    nombre: string;
    porcentaje: number | null;
    habilitado: boolean;
};

export function ClasificacionDetraccion({
    id,
    estado,
    codigo,
    opciones,
    errores,
    onChange,
}: {
    id: string;
    estado: EstadoDetraccion;
    codigo: string;
    opciones: OpcionDetraccion[];
    errores: { estado_detraccion?: string; codigo_detraccion?: string };
    onChange: (estado: EstadoDetraccion, codigo: string) => void;
}) {
    // Una sola lista: el código del catálogo 54 (sujeto), "no lleva" o "por definir".
    const valor = estado === 'sujeto' ? codigo : estado;

    return (
        <div>
            <label htmlFor={id} className="mb-1 block text-xs font-medium">
                Detracción SUNAT
            </label>
            <NativeSelect
                id={id}
                value={valor}
                onChange={(event) => {
                    const elegido = event.target.value;
                    if (elegido === 'pendiente' || elegido === 'no_sujeto') {
                        onChange(elegido, '');
                    } else {
                        onChange('sujeto', elegido);
                    }
                }}
                className="min-h-11 w-full"
                aria-invalid={
                    !!errores.estado_detraccion || !!errores.codigo_detraccion
                }
                aria-describedby={`${id}-ayuda`}
            >
                <option value="pendiente">Por definir</option>
                <option value="no_sujeto">No lleva detracción</option>
                {opciones
                    .filter((opcion) => opcion.habilitado)
                    .map((opcion) => (
                        <option key={opcion.codigo} value={opcion.codigo}>
                            {`${opcion.codigo} · ${opcion.nombre} (${opcion.porcentaje} %)`}
                        </option>
                    ))}
            </NativeSelect>
            {(errores.estado_detraccion || errores.codigo_detraccion) && (
                <p
                    role="alert"
                    className="text-destructive-strong mt-1 text-xs"
                >
                    {errores.estado_detraccion ?? errores.codigo_detraccion}
                </p>
            )}
            <p
                id={`${id}-ayuda`}
                className="text-muted-foreground mt-1 text-xs"
            >
                Se aplica sola en facturas de más de S/ 700.
            </p>
        </div>
    );
}
