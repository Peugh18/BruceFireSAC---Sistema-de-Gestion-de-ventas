<?php

namespace App\Models;

use App\Services\Billing\DetraccionCalculator;
use Database\Factories\SalePaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property int $sale_id
 * @property int|null $installment_id
 * @property string $forma_pago
 * @property bool $es_detraccion
 * @property float $monto
 * @property string|null $numero_operacion
 * @property Carbon $fecha día de negocio del cobro (date); la hora real del cobro está en created_at (y la de su anulación en updated_at)
 * @property int|null $user_id
 * @property int|null $cash_register_id
 * @property int|null $anulacion_cash_register_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property int|null $anulado_por
 * @property string|null $anulado_motivo
 * @property-read Sale $sale
 * @property-read Installment|null $installment
 * @property-read User|null $user
 * @property-read User|null $anuladoPor
 */
#[Fillable(['sale_id', 'installment_id', 'forma_pago', 'monto', 'numero_operacion', 'fecha', 'user_id', 'cash_register_id', 'es_detraccion'])]
class SalePayment extends Model
{
    /** @use HasFactory<SalePaymentFactory> */
    use HasFactory;

    // Un cobro anulado queda tachado (deleted_at) y deja de contar en saldos
    // y caja, pero no se pierde.
    use SoftDeletes;

    /** La ruta del archivo no sale al navegador: se descarga por su ruta protegida. */
    protected $hidden = ['constancia_path'];

    protected $appends = ['tiene_constancia'];

    /** @return Attribute<bool, never> */
    protected function tieneConstancia(): Attribute
    {
        return Attribute::get(fn () => filled($this->getAttribute('constancia_path')));
    }

    protected static function booted(): void
    {
        static::creating(function (SalePayment $pago): void {
            $usuario = auth()->id();
            $pago->user_id ??= is_int($usuario) ? $usuario : null;

            if ($pago->es_detraccion) {
                $pago->cash_register_id = null;
            }

            // V7: el cobro queda en el turno abierto del vendedor de la venta.
            // Se liga con la fila de caja BLOQUEADA: si justo se está cerrando
            // el turno, espera el cierre y decide con el turno real (M3).
            if (! array_key_exists('cash_register_id', $pago->getAttributes())) {
                $pago->cash_register_id = CashRegister::abiertaDeBloqueada((int) Sale::query()->whereKey($pago->sale_id)->value('vendedor_id'))?->id;
            }
        });
    }

    /**
     * Anula el cobro: queda en el historial con quién y por qué.
     */
    public function anular(string $motivo, ?int $userId): void
    {
        // V7: la reversión se registra en el turno abierto de ahora; el turno
        // original (si ya cerró) no cambia. El efectivo que sale de un turno
        // cerrado exige un turno abierto.
        $vendedorId = (int) Sale::query()->whereKey($this->sale_id)->value('vendedor_id');
        $turno = CashRegister::abiertaDe($vendedorId);

        if ($this->forma_pago === 'efectivo' && $this->cash_register_id !== null && $turno === null) {
            throw ValidationException::withMessages([
                'motivo' => 'Este cobro fue en efectivo de un turno ya cerrado: abre la caja del vendedor para registrar la devolución.',
            ]);
        }

        $this->forceFill([
            'anulado_por' => $userId,
            'anulado_motivo' => $motivo,
            'anulacion_cash_register_id' => $this->cash_register_id === null ? null : $turno?->id,
        ])->save();
        $this->delete();
    }

    public static function validarDetraccion(Sale $sale, float $monto, string $forma, ?string $operacion, ?int $installmentId = null): void
    {
        if ($forma !== 'deposito' || trim((string) $operacion) === '') {
            throw ValidationException::withMessages(['numero_operacion' => 'Registra el número de constancia del depósito de detracción en el Banco de la Nación.']);
        }

        if ($sale->payments()->where('installment_id', $installmentId)->where('forma_pago', 'deposito')->where('numero_operacion', trim((string) $operacion))->exists()) {
            throw ValidationException::withMessages(['numero_operacion' => 'Esta constancia ya está registrada para este cobro.']);
        }

        $calculada = app(DetraccionCalculator::class)->paraVenta($sale);
        $acreditado = (float) $sale->payments()->where('es_detraccion', true)->sum('monto');
        $pendiente = round($calculada['monto'] - $acreditado, 2);
        if (! $calculada['aplica'] || $monto > $pendiente || $pendiente <= 0) {
            throw ValidationException::withMessages(['monto' => 'El depósito no puede superar la detracción pendiente de S/ '.number_format(max(0, $pendiente), 2).'.']);
        }
    }

    protected function casts(): array
    {
        return [
            'es_detraccion' => 'boolean',
            'monto' => 'decimal:2',
            'fecha' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo<Installment, $this>
     */
    public function installment(): BelongsTo
    {
        return $this->belongsTo(Installment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }
}
