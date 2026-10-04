import { Plus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { fechaLocal } from '@/lib/utils';

export type Cuota = { fecha_vencimiento: string; monto: number };

function sumarDias(fecha: string, dias: number) {
    const d = new Date(`${fecha}T00:00:00`);
    d.setDate(d.getDate() + dias);

    return fechaLocal(d);
}

function redondear(valor: number) {
    return Math.round(valor * 100) / 100;
}

/**
 * Reparte el total en cuotas iguales a lo largo del plazo; la última
 * absorbe los céntimos para que la suma sea exacta.
 */
export function generarCuotas(
    total: number,
    fecha: string,
    plazoDias: number,
    numero: number,
): Cuota[] {
    const n = Math.max(1, Math.min(36, Math.floor(numero)));
    const base = redondear(total / n);

    return Array.from({ length: n }, (_, i) => ({
        fecha_vencimiento: sumarDias(
            fecha,
            Math.max(1, Math.round((plazoDias * (i + 1)) / n)),
        ),
        monto: i === n - 1 ? redondear(total - base * (n - 1)) : base,
    }));
}

/**
 * Venta a crédito: plazo, número de cuotas y cada cuota (fecha y monto)
 * editables; se pueden agregar o quitar cuotas. Van en la factura a SUNAT y
 * en Cobranzas.
 */
export default function CreditoDialog({
    open,
    onOpenChange,
    total,
    fecha,
    cuotas,
    onSave,
    onCancel,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    total: number;
    fecha: string;
    cuotas: Cuota[];
    onSave: (cuotas: Cuota[]) => void;
    onCancel?: () => void;
}) {
    const [plazo, setPlazo] = useState(30);
    const [numero, setNumero] = useState(1);
    const [filas, setFilas] = useState<Cuota[]>([]);

    useEffect(() => {
        if (!open) {
            return;
        }

        setFilas(
            cuotas.length > 0 ? cuotas : generarCuotas(total, fecha, 30, 1),
        );
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const suma = redondear(
        filas.reduce((s, c) => s + (Number(c.monto) || 0), 0),
    );
    const diferencia = redondear(total - suma);
    const fechasValidas = filas.every((c) => c.fecha_vencimiento > fecha);
    const valido =
        filas.length > 0 &&
        Math.abs(diferencia) < 0.01 &&
        fechasValidas &&
        filas.every((c) => Number(c.monto) > 0);

    const actualizar = (index: number, cambio: Partial<Cuota>) =>
        setFilas(filas.map((c, i) => (i === index ? { ...c, ...cambio } : c)));

    return (
        <Dialog
            open={open}
            onOpenChange={(abierto) => {
                if (!abierto) {
                    onCancel?.();
                }
                onOpenChange(abierto);
            }}
        >
            <DialogContent className="border-border bg-card max-h-[88vh] overflow-y-auto rounded-[16px] sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle className="font-['Oswald',sans-serif] text-[20px] font-semibold uppercase">
                        Venta a crédito
                    </DialogTitle>
                    <DialogDescription className="text-[12.5px]">
                        Total S/ {total.toFixed(2)}. Las cuotas salen en la
                        factura y en Cobranzas.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid grid-cols-[1fr_1fr_auto] items-end gap-2">
                    <label className="text-foreground/80 text-[11px] font-bold uppercase">
                        Plazo (días)
                        <Input
                            type="number"
                            min={1}
                            value={plazo}
                            onChange={(e) => setPlazo(Number(e.target.value))}
                            className="mt-1 h-9 rounded-[8px] text-[13px]"
                        />
                    </label>
                    <label className="text-foreground/80 text-[11px] font-bold uppercase">
                        N° de cuotas
                        <Input
                            type="number"
                            min={1}
                            max={36}
                            value={numero}
                            onChange={(e) => setNumero(Number(e.target.value))}
                            className="mt-1 h-9 rounded-[8px] text-[13px]"
                        />
                    </label>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() =>
                            setFilas(generarCuotas(total, fecha, plazo, numero))
                        }
                        className="h-9 rounded-[8px] shadow-none"
                    >
                        Calcular
                    </Button>
                </div>
                <div className="flex flex-wrap gap-1.5">
                    {[7, 15, 30, 45, 60].map((dias) => (
                        <button
                            key={dias}
                            type="button"
                            onClick={() => {
                                setPlazo(dias);
                                setFilas(
                                    generarCuotas(total, fecha, dias, numero),
                                );
                            }}
                            className={`rounded-full border px-2.5 py-0.5 text-[11.5px] font-bold ${plazo === dias ? 'border-primary bg-destructive/10 text-primary-strong' : 'border-border text-muted-foreground hover:text-foreground'}`}
                        >
                            {dias} días
                        </button>
                    ))}
                </div>

                <div className="border-border overflow-hidden rounded-[10px] border">
                    <table className="w-full text-[12.5px]">
                        <thead className="bg-muted/50">
                            <tr className="text-muted-foreground text-left text-[10px] font-bold uppercase">
                                <th className="px-2.5 py-2">Cuota</th>
                                <th className="px-2.5 py-2">Vencimiento</th>
                                <th className="px-2.5 py-2">Monto S/</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {filas.map((cuota, index) => (
                                <tr
                                    key={index}
                                    className="border-border border-t"
                                >
                                    <td className="px-2.5 py-1.5 font-bold">
                                        {index + 1}
                                    </td>
                                    <td className="px-2.5 py-1.5">
                                        <Input
                                            type="date"
                                            value={cuota.fecha_vencimiento}
                                            min={sumarDias(fecha, 1)}
                                            onChange={(e) =>
                                                actualizar(index, {
                                                    fecha_vencimiento:
                                                        e.target.value,
                                                })
                                            }
                                            className="h-8 rounded-[7px] text-[12px]"
                                        />
                                    </td>
                                    <td className="px-2.5 py-1.5">
                                        <Input
                                            type="number"
                                            min={0}
                                            step="0.01"
                                            value={cuota.monto}
                                            onChange={(e) =>
                                                actualizar(index, {
                                                    monto: Number(
                                                        e.target.value,
                                                    ),
                                                })
                                            }
                                            className="h-8 w-28 rounded-[7px] text-[12px]"
                                        />
                                    </td>
                                    <td className="px-2 py-1.5">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            disabled={filas.length === 1}
                                            onClick={() =>
                                                setFilas(
                                                    filas.filter(
                                                        (_, i) => i !== index,
                                                    ),
                                                )
                                            }
                                            className="text-destructive-strong size-7 rounded-[7px] shadow-none"
                                        >
                                            <Trash2 className="size-3.5" />
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="flex items-center justify-between gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => {
                            const ultima =
                                filas.at(-1)?.fecha_vencimiento ?? fecha;
                            setFilas([
                                ...filas,
                                {
                                    fecha_vencimiento: sumarDias(ultima, 30),
                                    monto: diferencia > 0 ? diferencia : 0,
                                },
                            ]);
                        }}
                        className="rounded-[8px] shadow-none"
                    >
                        <Plus className="size-3.5" />
                        Agregar cuota
                    </Button>
                    <div
                        className={`text-[12px] font-semibold ${Math.abs(diferencia) < 0.01 ? 'text-success-strong' : 'text-destructive-strong'}`}
                    >
                        Suma S/ {suma.toFixed(2)}
                        {Math.abs(diferencia) >= 0.01
                            ? ` · faltan S/ ${diferencia.toFixed(2)}`
                            : ' · cuadra con el total'}
                    </div>
                </div>
                {!fechasValidas ? (
                    <p className="text-destructive-strong text-[11.5px] font-semibold">
                        Cada cuota debe vencer después de la fecha de la venta.
                    </p>
                ) : null}

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => {
                            onCancel?.();
                            onOpenChange(false);
                        }}
                        className="rounded-[9px] shadow-none"
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="button"
                        disabled={!valido}
                        onClick={() => {
                            onSave(
                                filas.map((c) => ({
                                    ...c,
                                    monto: redondear(Number(c.monto)),
                                })),
                            );
                            onOpenChange(false);
                        }}
                        className="bg-primary hover:bg-primary/90 rounded-[9px] font-bold text-white shadow-none"
                    >
                        Guardar crédito
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
