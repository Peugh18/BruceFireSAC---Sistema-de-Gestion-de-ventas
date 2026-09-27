import { Building2, Truck } from 'lucide-react';

import type { ClientFicha } from '@/components/client-picker';
import { Input } from '@/components/ui/input';

/**
 * Referencia corta que distingue a dónde va lo vendido dentro del mismo
 * cliente: "PLACA: AVR-833" o "SEDE: Planta Chimbote". Sale impresa en
 * comprobantes, cotizaciones, órdenes y certificados.
 */
export default function ReferenciaField({
    cliente,
    destino,
    value,
    onChange,
    error,
}: {
    cliente: ClientFicha | null;
    destino: 'local_cliente' | 'vehiculo';
    value: string;
    onChange: (value: string) => void;
    error?: string;
}) {
    const esVehiculo = destino === 'vehiculo';
    const opciones = esVehiculo
        ? (cliente?.vehiculos ?? []).map((v) => ({
              key: `v-${v.id}`,
              texto: `PLACA: ${v.placa}`,
              etiqueta: v.placa,
          }))
        : (cliente?.sedes ?? []).map((s) => ({
              key: `s-${s.id}`,
              texto: `SEDE: ${s.nombre}`,
              etiqueta: s.nombre,
          }));

    return (
        <div>
            <div className="text-[11px] font-bold text-foreground/80 uppercase">
                Referencia{' '}
                <span className="font-normal text-muted-foreground normal-case">
                    ({esVehiculo ? 'placa' : 'sede u oficina del cliente'}, sale
                    impresa en el comprobante)
                </span>
            </div>
            {opciones.length > 0 ? (
                <div className="mt-1 flex flex-wrap gap-1.5">
                    {opciones.map((opcion) => (
                        <button
                            key={opcion.key}
                            type="button"
                            onClick={() =>
                                onChange(
                                    value === opcion.texto ? '' : opcion.texto,
                                )
                            }
                            className={`inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-[11.5px] font-bold transition-colors ${value === opcion.texto ? 'border-primary bg-destructive/10 text-primary' : 'border-border text-muted-foreground hover:text-foreground'}`}
                        >
                            {esVehiculo ? (
                                <Truck className="size-3" />
                            ) : (
                                <Building2 className="size-3" />
                            )}
                            {opcion.etiqueta}
                        </button>
                    ))}
                </div>
            ) : null}
            <Input
                value={value}
                maxLength={150}
                onChange={(event) => onChange(event.target.value)}
                placeholder={
                    esVehiculo
                        ? 'Ej. PLACA: AVR-833'
                        : 'Ej. SEDE: Oficina Chimbote (opcional)'
                }
                className="mt-1.5 h-10 rounded-[9px] border-border bg-card text-[13px]"
            />
            {error ? (
                <p className="mt-1 text-[11px] font-semibold text-destructive">
                    {error}
                </p>
            ) : null}
        </div>
    );
}
