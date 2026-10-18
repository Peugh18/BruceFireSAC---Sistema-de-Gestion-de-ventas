export type FormularioCertificadoCampo = {
    id: number;
    codigo: string;
    nombre: string;
    version: number;
    mediciones?: {
        filas: Record<string, string>[];
        pruebas: { estado: string; valor: string | null }[];
    } | null;
    columnas: {
        clave: string;
        titulo: string;
        tipo?: string;
        unidad?: string | null;
        obligatorio?: boolean;
    }[];
    checklist: { texto: string; unidad?: string | null }[] | null;
};

export type MedicionCertificadoCampo = {
    tipo_id: number;
    version: number;
    filas: Record<string, string>[];
    pruebas: { estado: string; valor: string }[];
};

export function iniciarMediciones(
    tipos: FormularioCertificadoCampo[],
): MedicionCertificadoCampo[] {
    return tipos.map((tipo) => ({
        tipo_id: tipo.id,
        version: tipo.version,
        filas: tipo.mediciones?.filas ?? [
            Object.fromEntries(
                tipo.columnas.map((columna) => [columna.clave, '']),
            ),
        ],
        pruebas:
            tipo.mediciones?.pruebas.map((p) => ({
                estado: p.estado,
                valor: p.valor ?? '',
            })) ??
            (tipo.checklist ?? []).map(() => ({ estado: '', valor: '' })),
    }));
}

export default function MedicionesCertificadoCampo({
    tipos,
    value,
    onChange,
    errors = {},
    disabled = false,
}: {
    tipos: FormularioCertificadoCampo[];
    value: MedicionCertificadoCampo[];
    onChange: (value: MedicionCertificadoCampo[]) => void;
    errors?: Record<string, string | undefined>;
    disabled?: boolean;
}) {
    const actualizar = (
        indice: number,
        cambio: Partial<MedicionCertificadoCampo>,
    ) =>
        onChange(
            value.map((dato, i) =>
                i === indice ? { ...dato, ...cambio } : dato,
            ),
        );
    if (tipos.length === 0) return null;

    return (
        <section
            className="border-border bg-card space-y-4 rounded-2xl border p-4"
            aria-label="Mediciones para los certificados"
        >
            <h2 className="font-semibold">Mediciones del servicio</h2>
            <p className="text-muted-foreground text-sm">
                Estos datos pasan directamente al certificado. El cliente, la
                orden y la fecha se completan automáticamente.
            </p>
            {errors.certificados_servicio && (
                <p role="alert" className="text-destructive-strong text-sm">
                    {errors.certificados_servicio}
                </p>
            )}
            {tipos.map((tipo, i) => {
                const dato = value[i];
                if (!dato) return null;
                const prefijo = `certificados_servicio.${i}`;
                return (
                    <fieldset
                        key={tipo.id}
                        disabled={disabled}
                        className="border-border space-y-3 rounded-lg border p-3"
                    >
                        <legend className="px-1 font-semibold">
                            {tipo.nombre}
                        </legend>
                        {errors[`${prefijo}.version`] && (
                            <p role="alert" className="text-destructive-strong">
                                {errors[`${prefijo}.version`]}
                            </p>
                        )}
                        {dato.filas.map((fila, j) => (
                            <div
                                key={j}
                                className="border-border space-y-3 border-b pb-3"
                            >
                                <p className="text-sm font-semibold">
                                    Registro {j + 1}
                                </p>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {tipo.columnas
                                        .filter((c) => c.clave !== 'item')
                                        .map((columna) => {
                                            const clave = `${prefijo}.filas.${j}.${columna.clave}`;
                                            const cambiar = (valor: string) =>
                                                actualizar(i, {
                                                    filas: dato.filas.map(
                                                        (f, pos) =>
                                                            pos === j
                                                                ? {
                                                                      ...f,
                                                                      [columna.clave]:
                                                                          valor,
                                                                  }
                                                                : f,
                                                    ),
                                                });
                                            return (
                                                <div
                                                    key={columna.clave}
                                                    className="space-y-1"
                                                >
                                                    <label
                                                        htmlFor={clave}
                                                        className="text-sm"
                                                    >
                                                        {columna.titulo}
                                                        {columna.unidad
                                                            ? ` (${columna.unidad})`
                                                            : ''}
                                                        {columna.obligatorio
                                                            ? ' *'
                                                            : ''}
                                                    </label>
                                                    {columna.tipo ===
                                                    'resultado' ? (
                                                        <select
                                                            id={clave}
                                                            value={
                                                                fila[
                                                                    columna
                                                                        .clave
                                                                ] ?? ''
                                                            }
                                                            onChange={(e) =>
                                                                cambiar(
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            required={
                                                                columna.obligatorio
                                                            }
                                                            className="border-border bg-background min-h-11 w-full rounded-lg border p-2"
                                                        >
                                                            <option value="">
                                                                Selecciona el
                                                                resultado
                                                            </option>
                                                            <option value="operativo">
                                                                Operativo
                                                            </option>
                                                            <option value="observado">
                                                                Observado
                                                            </option>
                                                            <option value="no_operativo">
                                                                No operativo
                                                            </option>
                                                        </select>
                                                    ) : (
                                                        <input
                                                            id={clave}
                                                            value={
                                                                fila[
                                                                    columna
                                                                        .clave
                                                                ] ?? ''
                                                            }
                                                            onChange={(e) =>
                                                                cambiar(
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            inputMode={
                                                                columna.tipo ===
                                                                'numero'
                                                                    ? 'decimal'
                                                                    : 'text'
                                                            }
                                                            required={
                                                                columna.obligatorio
                                                            }
                                                            maxLength={1000}
                                                            aria-invalid={
                                                                !!errors[clave]
                                                            }
                                                            aria-describedby={
                                                                errors[clave]
                                                                    ? `${clave}-error`
                                                                    : undefined
                                                            }
                                                            className="border-border bg-background min-h-11 w-full rounded-lg border p-2"
                                                        />
                                                    )}
                                                    {errors[clave] && (
                                                        <p
                                                            id={`${clave}-error`}
                                                            className="text-destructive-strong text-sm"
                                                        >
                                                            {errors[clave]}
                                                        </p>
                                                    )}
                                                </div>
                                            );
                                        })}
                                </div>
                                {dato.filas.length > 1 && (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            actualizar(i, {
                                                filas: dato.filas.filter(
                                                    (_, pos) => pos !== j,
                                                ),
                                            })
                                        }
                                        className="text-destructive-strong min-h-11 text-sm"
                                    >
                                        Quitar registro {j + 1}
                                    </button>
                                )}
                            </div>
                        ))}
                        <button
                            type="button"
                            disabled={dato.filas.length >= 200}
                            onClick={() =>
                                actualizar(i, {
                                    filas: [
                                        ...dato.filas,
                                        Object.fromEntries(
                                            tipo.columnas.map((c) => [
                                                c.clave,
                                                '',
                                            ]),
                                        ),
                                    ],
                                })
                            }
                            className="border-border min-h-11 rounded-lg border px-3 text-sm"
                        >
                            Agregar registro
                        </button>
                        {(tipo.checklist ?? []).map((prueba, j) => {
                            const clave = `${prefijo}.pruebas.${j}`;
                            const cambiar = (
                                cambio: Partial<{
                                    estado: string;
                                    valor: string;
                                }>,
                            ) =>
                                actualizar(i, {
                                    pruebas: dato.pruebas.map((p, pos) =>
                                        pos === j ? { ...p, ...cambio } : p,
                                    ),
                                });
                            return (
                                <div key={j} className="space-y-1">
                                    <label
                                        htmlFor={`${clave}.estado`}
                                        className="text-sm"
                                    >
                                        {prueba.texto}
                                    </label>
                                    <select
                                        id={`${clave}.estado`}
                                        required
                                        value={dato.pruebas[j]?.estado ?? ''}
                                        onChange={(e) =>
                                            cambiar({ estado: e.target.value })
                                        }
                                        className="border-border bg-background min-h-11 w-full rounded-lg border p-2"
                                    >
                                        <option value="">Selecciona</option>
                                        <option value="C">Conforme</option>
                                        <option value="NC">No conforme</option>
                                        <option value="NA">No aplica</option>
                                    </select>
                                    {prueba.unidad && (
                                        <>
                                            <label
                                                htmlFor={`${clave}.valor`}
                                                className="text-sm"
                                            >
                                                Medición ({prueba.unidad})
                                            </label>
                                            <input
                                                id={`${clave}.valor`}
                                                inputMode="decimal"
                                                value={
                                                    dato.pruebas[j]?.valor ?? ''
                                                }
                                                onChange={(e) =>
                                                    cambiar({
                                                        valor: e.target.value,
                                                    })
                                                }
                                                className="border-border bg-background min-h-11 w-full rounded-lg border p-2"
                                            />
                                        </>
                                    )}
                                    {(errors[`${clave}.estado`] ||
                                        errors[`${clave}.valor`]) && (
                                        <p className="text-destructive-strong text-sm">
                                            {errors[`${clave}.estado`] ??
                                                errors[`${clave}.valor`]}
                                        </p>
                                    )}
                                </div>
                            );
                        })}
                    </fieldset>
                );
            })}
        </section>
    );
}
