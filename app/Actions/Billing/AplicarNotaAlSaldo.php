<?php

namespace App\Actions\Billing;

use App\Models\ElectronicDocument;
use App\Models\Installment;
use App\Models\Sale;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * V4/S11: una nota aceptada por SUNAT cambia lo que el cliente debe, sin
 * reescribir las cuotas pactadas:
 * - una nota de débito agrega una cuota nueva ligada a la nota;
 * - una nota de crédito que no anula la venta rebaja el saldo de las cuotas
 *   pendientes (desde la última) en `monto_acreditado`.
 * La nota guarda cuándo se aplicó (no se aplica dos veces) y la auditoría
 * guarda qué cuotas tocó.
 */
class AplicarNotaAlSaldo
{
    public function handle(ElectronicDocument $nota): void
    {
        if (! in_array($nota->tipo, ['nota_credito', 'nota_debito'], true)
            || ! in_array($nota->sunat_estado, ['aceptado', 'observado'], true)
            || $nota->saldo_aplicado_at !== null) {
            return;
        }

        DB::transaction(function () use ($nota): void {
            $nota = ElectronicDocument::query()->lockForUpdate()->findOrFail($nota->id);
            $sale = Sale::query()->lockForUpdate()->findOrFail($nota->sale_id);

            if ($nota->saldo_aplicado_at !== null) {
                return;
            }

            // Una venta anulada ya no tiene saldo; la anulación devolvió lo cobrado.
            $cambios = $sale->estado === 'confirmada'
                ? ($nota->tipo === 'nota_debito' ? $this->agregarCuota($sale, $nota) : $this->rebajarCuotas($sale, $nota))
                : [];

            $nota->forceFill(['saldo_aplicado_at' => now()])->save();

            AuditLogger::log(
                action: 'cobranza.nota_aplicada',
                entity: $sale,
                newValues: ['nota' => "{$nota->serie}-{$nota->correlativo}", 'tipo' => $nota->tipo, 'importe' => (float) $nota->importe, 'cuotas' => $cambios],
                userId: is_int($usuario = auth()->id()) ? $usuario : null,
            );
        });
    }

    /**
     * @return list<array{cuota: int, monto: float}>
     */
    protected function agregarCuota(Sale $sale, ElectronicDocument $nota): array
    {
        $cuota = $sale->installments()->create([
            'numero_cuota' => (int) $sale->installments()->max('numero_cuota') + 1,
            'fecha_vencimiento' => today(),
            'monto' => round((float) $nota->importe, 2),
            'estado' => 'pendiente',
            'electronic_document_id' => $nota->id,
        ]);

        return [['cuota' => $cuota->numero_cuota, 'monto' => (float) $cuota->monto]];
    }

    /**
     * @return list<array{cuota: int, monto: float}>
     */
    protected function rebajarCuotas(Sale $sale, ElectronicDocument $nota): array
    {
        $porRebajar = round((float) $nota->importe, 2);
        $cambios = [];

        $cuotas = $sale->installments()->with('payments')->lockForUpdate()->orderByDesc('fecha_vencimiento')->orderByDesc('numero_cuota')->get();

        foreach ($cuotas as $cuota) {
            /** @var Installment $cuota */
            $rebaja = min($cuota->saldo(), $porRebajar);

            if ($rebaja <= 0) {
                continue;
            }

            $cuota->update(['monto_acreditado' => round((float) $cuota->monto_acreditado + $rebaja, 2)]);
            $cuota->recalcularEstado();
            $porRebajar = round($porRebajar - $rebaja, 2);
            $cambios[] = ['cuota' => $cuota->numero_cuota, 'monto' => -$rebaja];

            if ($porRebajar <= 0) {
                break;
            }
        }

        // ponytail: lo que excede el saldo (venta ya pagada) es un saldo a
        // favor del cliente; solo queda en la auditoría, se devuelve a mano.
        if ($porRebajar > 0) {
            $cambios[] = ['cuota' => 0, 'monto' => -$porRebajar];
        }

        return $cambios;
    }
}
