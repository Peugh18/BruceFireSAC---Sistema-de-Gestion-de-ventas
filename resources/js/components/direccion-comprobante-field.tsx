import type { ClientFicha } from '@/components/client-picker';

/** El local de la referencia "SEDE: Tienda Centro", si es del cliente. */
export function localDeReferencia(
    cliente: ClientFicha | null,
    referencia: string,
) {
    const nombre = /^\s*(SEDE|LOCAL)\s*:\s*(.+)$/iu.exec(referencia)?.[2];

    return nombre
        ? (cliente?.sedes ?? []).find(
              (s) =>
                  s.nombre.trim().toLowerCase() === nombre.trim().toLowerCase(),
          )
        : undefined;
}

/**
 * Qué dirección lleva el comprobante cuando se atiende un local del cliente:
 * la fiscal o la del local. El certificado y la guía ya usan la del local.
 */
export default function DireccionComprobanteField({
    cliente,
    referencia,
    value,
    onChange,
}: {
    cliente: ClientFicha | null;
    referencia: string;
    value: boolean;
    onChange: (value: boolean) => void;
}) {
    const local = localDeReferencia(cliente, referencia);

    if (!local?.direccion) {
        return null;
    }

    return (
        <label className="border-border bg-muted/40 flex min-h-11 items-start gap-2 rounded-lg border px-3 py-2 text-[13px]">
            <input
                type="checkbox"
                checked={value}
                onChange={(e) => onChange(e.target.checked)}
                className="mt-0.5 size-4"
            />
            <span>
                <span className="text-foreground font-medium">
                    El comprobante sale con la dirección de este local
                </span>
                <span className="text-muted-foreground block">
                    {value
                        ? local.direccion
                        : 'Si no lo marcas, sale la dirección fiscal.'}
                </span>
            </span>
        </label>
    );
}
