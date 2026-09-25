<?php

namespace App\Models;

use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;

/**
 * @property int $id
 * @property string $numero_interno
 * @property string|null $numero_nota_venta
 * @property int|null $quote_id
 * @property int $client_id
 * @property int|null $sede_id
 * @property int|null $vehicle_id
 * @property int $vendedor_id
 * @property Carbon $fecha
 * @property string $destino
 * @property string $condicion_pago
 * @property string $comprobante_tipo
 * @property float $subtotal
 * @property float $igv
 * @property float $total
 * @property string $estado
 * @property string|null $observaciones
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Quote|null $quote
 * @property-read Client $client
 * @property-read Sede|null $sede
 * @property-read Vehicle|null $vehicle
 * @property-read User $vendedor
 * @property-read Collection<int, SaleItem> $items
 * @property-read Collection<int, SalePayment> $payments
 * @property-read Collection<int, Installment> $installments
 * @property-read Collection<int, ElectronicDocument> $electronicDocuments
 */
#[Fillable([
    'numero_interno', 'numero_nota_venta', 'quote_id', 'client_id', 'sede_id', 'vehicle_id', 'vendedor_id', 'fecha',
    'destino', 'condicion_pago', 'comprobante_tipo', 'subtotal', 'igv', 'total', 'estado', 'observaciones',
])]
class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory;

    public const NOTA_VENTA = 'nota_venta';

    /**
     * Monto máximo de una boleta sin identificar al cliente (CLIENTES VARIOS).
     */
    public const LIMITE_BOLETA_SIN_IDENTIFICAR = 700.00;

    /**
     * Una nota de venta es una venta interna: no se envía a SUNAT.
     */
    public function esNotaVenta(): bool
    {
        return $this->comprobante_tipo === self::NOTA_VENTA;
    }

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'subtotal' => 'decimal:2',
            'igv' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class);
    }

    public function electronicDocuments(): HasMany
    {
        return $this->hasMany(ElectronicDocument::class);
    }

    /**
     * Líneas tal como salen en el comprobante (XML a SUNAT y PDF). Cada
     * unidad serializada se guarda como su propio ítem para el control
     * interno de series; al cliente se le muestra una sola línea por
     * producto/servicio y precio, con la cantidad y el total sumados.
     *
     * @return SupportCollection<int, SaleItem>
     */
    public function lineasComprobante(): SupportCollection
    {
        $this->loadMissing('items.product', 'items.service');

        return $this->items
            ->groupBy(fn (SaleItem $item) => implode('|', [
                $item->product_id,
                $item->service_id,
                $item->precio_unitario,
            ]))
            ->map(function (Collection $grupo) {
                /** @var SaleItem $primero */
                $primero = $grupo->first();

                $linea = new SaleItem([
                    'sale_id' => $this->id,
                    'product_id' => $primero->product_id,
                    'service_id' => $primero->service_id,
                    'tipo_linea' => $primero->tipo_linea,
                    'cantidad' => $grupo->sum('cantidad'),
                    'precio_unitario' => $primero->precio_unitario,
                    'descuento' => round($grupo->sum(fn (SaleItem $item) => (float) $item->descuento), 2),
                    'subtotal' => round($grupo->sum(fn (SaleItem $item) => (float) $item->subtotal), 2),
                ]);
                $linea->setRelation('product', $primero->product);
                $linea->setRelation('service', $primero->service);

                return $linea;
            })
            ->values()
            ->toBase();
    }
}
