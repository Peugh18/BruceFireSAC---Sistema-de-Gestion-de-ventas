/** Mensaje de validación de un campo del formulario, si lo tiene. */
export function fieldError(
    errors: Partial<Record<string, string>>,
    key: string,
) {
    return errors[key] ? (
        <p
            className="text-destructive-strong mt-1 text-xs font-semibold"
            role="alert"
        >
            {errors[key]}
        </p>
    ) : null;
}
