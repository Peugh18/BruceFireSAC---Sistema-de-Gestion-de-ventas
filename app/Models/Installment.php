<?php

namespace App\Models;

use Database\Factories\InstallmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $sale_id
 * @property int $numero_cuota
 * @property Carbon $fecha_vencimiento
 * @property float $monto
 * @property float $monto_acreditado
 * @property string $estado
 * @property int|null $electronic_document_id
 * @property int|null $deficiency_authorization_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Sale $sale
 */
#[Fillable(['sale_id', 'numero_cuota', 'fecha_vencimiento', 'monto', 'monto_acreditado', 'estado', 'electronic_document_id', 'deficiency_authorization_id'])]
class Installment extends Model
{
    /** @use HasFactory<InstallmentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'fecha_vencimiento' => 'date',
            'monto' => 'decimal:2',
            'monto_acreditado' => 'decimal:2',
        ];
    }

    /**
     * Lo que falta cobrar (V4/S11): el monto de la cuota, menos lo que le
     * rebajaron las notas de crédito aceptadas, menos lo pagado. Usa los
     * pagos ya cargados si los hay.
     */
    public function saldo(): float
    {
        $pagado = $this->relationLoaded('payments') ? $this->payments->sum('monto') : $this->payments()->sum('monto');

        return max(0, round((float) $this->monto - (float) $this->monto_acreditado - (float) $pagado, 2));
    }

    /**
     * Estado según su saldo conciliado (pagos y notas de crédito). Una cuota
     * vencida que no se terminó de pagar es "vencido", aunque tenga un pago
     * parcial: el pago parcial se ve en su saldo y en sus cobros, y el estado
     * dice si la fecha ya pasó. Si no, "parcial" tapaba el vencimiento y una
     * cuota atrasada con un pago nunca aparecía como vencida.
     */
    public function recalcularEstado(): void
    {
        $pagado = (float) $this->payments()->sum('monto');
        $cubierto = $pagado + (float) $this->monto_acreditado;

        $this->update(['estado' => match (true) {
            $cubierto >= (float) $this->monto - 0.001 => 'pagado',
            $this->fecha_vencimiento->isBefore(today()) => 'vencido',
            $cubierto > 0 => 'parcial',
            default => 'pendiente',
        }]);
    }

    /**
     * Cuota del comprobante original (no una nota de débito ni un adicional):
     * solo esas van en el XML de la factura.
     */
    public function esDelComprobante(): bool
    {
        return $this->electronic_document_id === null && $this->deficiency_authorization_id === null;
    }

    /**
     * Pasa a "vencido" las cuotas pendientes o parciales cuya fecha ya pasó
     * (una cuota con un pago parcial también vence). Lo corre la tarea de cada
     * noche (alerts:recompute); abrir una pantalla no cambia datos.
     */
    public static function marcarVencidas(): int
    {
        return self::query()
            ->whereIn('estado', ['pendiente', 'parcial'])
            ->whereDate('fecha_vencimiento', '<', today())
            ->update(['estado' => 'vencido']);
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return HasMany<SalePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    /**
     * Cobros anulados de la cuota (quedan en el historial).
     *
     * @return HasMany<SalePayment, $this>
     */
    public function paymentsAnulados(): HasMany
    {
        return $this->hasMany(SalePayment::class)->onlyTrashed();
    }
}
