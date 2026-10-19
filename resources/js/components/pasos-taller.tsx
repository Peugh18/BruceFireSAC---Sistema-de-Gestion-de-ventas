export type PasoTaller = {
    numero: number;
    clave: 'recibir' | 'revisar' | 'hallazgos' | 'reparar' | 'cierre';
    detalle: string;
    completa: boolean;
};

export const NOMBRES_PASOS = [
    'Recibir',
    'Revisar',
    'Hallazgos',
    'Reparar',
    'Cierre',
] as const;

/** Paso en que espera al vendedor (Hallazgos) va en ámbar; el resto en rojo. */
export const tonoPaso = (paso: PasoTaller) =>
    paso.completa
        ? 'text-success-strong'
        : paso.clave === 'hallazgos'
          ? 'text-warning-strong'
          : 'text-destructive-strong';

/** Barra de 5 tramos con el texto "Paso X de 5 · Nombre — lo que falta". */
export function BarraPasos({ paso }: { paso: PasoTaller }) {
    return (
        <div className="space-y-1.5">
            <div className="grid grid-cols-5 gap-1" aria-hidden="true">
                {NOMBRES_PASOS.map((nombre, i) => (
                    <span
                        key={nombre}
                        className={`h-1.5 rounded-full ${
                            i + 1 < paso.numero || paso.completa
                                ? 'bg-success-strong'
                                : i + 1 === paso.numero
                                  ? paso.clave === 'hallazgos'
                                      ? 'bg-warning-strong'
                                      : 'bg-primary'
                                  : 'bg-muted'
                        }`}
                    />
                ))}
            </div>
            <p className={`text-xs font-semibold ${tonoPaso(paso)}`}>
                Paso {paso.numero} de 5 · {NOMBRES_PASOS[paso.numero - 1]} —{' '}
                {paso.detalle}
            </p>
        </div>
    );
}
