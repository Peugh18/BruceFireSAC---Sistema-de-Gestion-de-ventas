<?php

namespace App\Models;

use Database\Factories\CashRegisterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property int $vendedor_id
 * @property int|null $sede_id
 * @property Carbon $fecha_apertura
 * @property float $monto_apertura
 * @property Carbon|null $fecha_cierre
 * @property float|null $monto_contado_cierre
 * @property float|null $monto_esperado_calculado
 * @property-read float|null $diferencia
 * @property string|null $observacion
 * @property string $estado
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $vendedor
 * @property-read Sede|null $sede
 */
#[Fillable([
    'vendedor_id',
    'sede_id',
    'fecha_apertura',
    'monto_apertura',
    'fecha_cierre',
    'monto_contado_cierre',
    'monto_esperado_calculado',
    'observacion',
    'estado',
])]
class CashRegister extends Model
{
    /** @use HasFactory<CashRegisterFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'fecha_apertura' => 'datetime',
            'fecha_cierre' => 'datetime',
            'monto_apertura' => 'decimal:2',
            'monto_contado_cierre' => 'decimal:2',
            'monto_esperado_calculado' => 'decimal:2',
            'diferencia' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    /**
     * @return BelongsTo<Sede, $this>
     */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function estaAbierto(): bool
    {
        return $this->estado === 'abierto';
    }

    /**
     * Lo que entró al turno por forma de pago (V7): los cobros registrados en
     * este turno, menos las devoluciones y los cobros anulados en este turno.
     * Anular después un cobro de un turno cerrado no cambia ese turno: la
     * reversión cae en el turno donde se anuló.
     *
     * @return array<string, float>
     */
    public function movimientosPorFormaDePago(): array
    {
        $porForma = fn ($query) => $query->selectRaw('forma_pago, SUM(monto) as total')->groupBy('forma_pago')->pluck('total', 'forma_pago');

        $cobros = $porForma(SalePayment::withTrashed()->where('cash_register_id', $this->id));
        $anulados = $porForma(SalePayment::onlyTrashed()->where('anulacion_cash_register_id', $this->id));
        $devoluciones = $porForma(SaleRefund::query()->where('cash_register_id', $this->id));

        return $cobros->keys()->merge($anulados->keys())->merge($devoluciones->keys())->unique()
            ->mapWithKeys(fn ($forma) => [(string) $forma => round((float) $cobros->get($forma, 0) - (float) $anulados->get($forma, 0) - (float) $devoluciones->get($forma, 0), 2)])
            ->all();
    }

    /**
     * El turno abierto del vendedor, si tiene uno.
     */
    public static function abiertaDe(int $vendedorId): ?self
    {
        return self::query()
            ->where('vendedor_id', $vendedorId)
            ->where('estado', 'abierto')
            ->latest('fecha_apertura')
            ->first();
    }

    /**
     * El efectivo que entra tiene que caer en un turno: sin caja abierta no
     * se cobra en efectivo (la pantalla ya lo avisa; esto lo asegura).
     */
    public static function exigirAbiertaParaEfectivo(int $vendedorId, string $campo = 'medio_pago'): void
    {
        if (! self::abiertaDe($vendedorId)) {
            throw ValidationException::withMessages([
                $campo => 'Para cobrar en efectivo abre primero tu turno en Caja, o elige otro medio de pago.',
            ]);
        }
    }

    /**
     * Anular una venta cobrada en efectivo devuelve ese dinero: sale de la
     * caja abierta del vendedor, o no quedaría registrado en ningún arqueo.
     */
    public static function exigirAbiertaParaDevolverEfectivo(Sale $sale, string $campo = 'caja'): void
    {
        $efectivo = (float) $sale->payments()->where('forma_pago', 'efectivo')->sum('monto')
            - (float) $sale->refunds()->where('forma_pago', 'efectivo')->sum('monto');

        if (round($efectivo, 2) > 0 && ! self::abiertaDe((int) $sale->vendedor_id)) {
            throw ValidationException::withMessages([
                $campo => 'Esta venta se cobró en efectivo: abre la caja del vendedor para registrar la devolución y luego anúlala.',
            ]);
        }
    }
}
