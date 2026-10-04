<?php

namespace App\Models;

use Carbon\CarbonInterface;
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
     * Lo que entró al turno por forma de pago: los cobros de las ventas del
     * vendedor menos lo que se devolvió a clientes, desde la apertura hasta
     * el cierre (o hasta ahora si sigue abierto).
     *
     * @return array<string, float>
     */
    public function movimientosPorFormaDePago(?CarbonInterface $hasta = null): array
    {
        $rango = [$this->fecha_apertura, $hasta ?? $this->fecha_cierre ?? now()];
        $deSusVentas = fn ($query) => $query->where('vendedor_id', $this->vendedor_id);

        $cobros = SalePayment::query()
            ->whereHas('sale', $deSusVentas)
            ->whereBetween('created_at', $rango)
            ->selectRaw('forma_pago, SUM(monto) as total')
            ->groupBy('forma_pago')
            ->pluck('total', 'forma_pago');

        $devoluciones = SaleRefund::query()
            ->whereHas('sale', $deSusVentas)
            ->whereBetween('created_at', $rango)
            ->selectRaw('forma_pago, SUM(monto) as total')
            ->groupBy('forma_pago')
            ->pluck('total', 'forma_pago');

        return $cobros->keys()->merge($devoluciones->keys())->unique()
            ->mapWithKeys(fn ($forma) => [(string) $forma => round((float) $cobros->get($forma, 0) - (float) $devoluciones->get($forma, 0), 2)])
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
}
