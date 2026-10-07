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
 * @property string|null $medio_pago
 * @property string|null $numero_operacion
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
    'destino', 'referencia', 'condicion_pago', 'medio_pago', 'numero_operacion', 'comprobante_tipo', 'subtotal', 'igv', 'total', 'estado', 'observaciones',
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
    /**
     * Cómo puede pagar el cliente al contado. No va a SUNAT (allí solo va
     * contado o crédito): sirve para registrar el cobro y cuadrar la caja.
     *
     * @var array<string, string>
     */
    public const MEDIOS_PAGO = [
        'efectivo' => 'Efectivo',
        'yape' => 'Yape',
        'plin' => 'Plin',
        'transferencia' => 'Transferencia',
        'pos' => 'Tarjeta',
        'deposito' => 'Depósito',
        'otro' => 'Otro',
    ];

    public function medioPagoTexto(): ?string
    {
        $method = $this->payments()->oldest('id')->value('forma_pago') ?? $this->medio_pago;

        return $method ? (self::MEDIOS_PAGO[$method] ?? $method) : null;
    }

    public function esCredito(): bool
    {
        return in_array($this->condicion_pago, ['credito', 'credito_30'], true);
    }

    /**
     * Plazo del crédito en días hasta la última cuota (ej. "CRÉDITO 07 DÍAS").
     */
    public function diasCredito(): ?int
    {
        if (! $this->esCredito()) {
            return null;
        }

        $ultima = $this->installments->max('fecha_vencimiento');

        return $ultima ? (int) $this->fecha->diffInDays($ultima) : 30;
    }

    public function esNotaVenta(): bool
    {
        return $this->comprobante_tipo === self::NOTA_VENTA;
    }

    /**
     * La factura o boleta más reciente de la venta (la vigente o la última
     * que SUNAT rechazó), sin contar notas de crédito o débito.
     */
    public function comprobanteElectronico(): ?ElectronicDocument
    {
        return $this->electronicDocuments
            ->whereIn('tipo', ['factura', 'boleta'])
            ->sortBy('id')
            ->last();
    }

    /**
     * Si todavía se puede editar todo (cliente, comprobante, productos,
     * precios, pago) con el mismo formulario de la venta: un borrador, una
     * nota de venta (es interna) o una factura/boleta que SUNAT aún no recibe
     * o que rechazó. Una aceptada solo se corrige con nota de crédito.
     */
    public function sePuedeEditar(): bool
    {
        if ($this->estado === 'borrador') {
            return true;
        }

        if ($this->estado !== 'confirmada') {
            return false;
        }

        if ($this->esNotaVenta()) {
            return true;
        }

        $documento = $this->comprobanteElectronico();

        return $documento !== null && (($documento->estaPorEnviar() && $documento->intento_envio_at === null) || $documento->fueRechazado());
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

    /**
     * @return BelongsTo<Quote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Sede, $this>
     */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    /**
     * @return HasMany<SaleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * @return HasMany<SalePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    /**
     * Dinero devuelto al cliente (venta anulada o rebajada).
     *
     * @return HasMany<SaleRefund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(SaleRefund::class);
    }

    /**
     * @return HasMany<Installment, $this>
     */
    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class);
    }

    /**
     * @return HasMany<ElectronicDocument, $this>
     */
    public function electronicDocuments(): HasMany
    {
        return $this->hasMany(ElectronicDocument::class);
    }

    /**
     * @return HasMany<Certificate, $this>
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    /**
     * Número del comprobante con que se vendió: la factura o boleta vigente
     * (la última que no fue rechazada) o la nota de venta interna.
     */
    public function numeroComprobante(): ?string
    {
        $documento = $this->electronicDocuments
            ->whereIn('tipo', ['factura', 'boleta'])
            ->reject(fn (ElectronicDocument $documento) => $documento->fueRechazado())
            ->sortBy('id')
            ->last();

        return $documento ? "{$documento->serie}-{$documento->correlativo}" : $this->numero_nota_venta;
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
                $item->tipo_afectacion_igv,
            ]))
            ->map(function (Collection $grupo) {
                /** @var SaleItem $primero */
                $primero = $grupo->first();

                $linea = new SaleItem([
                    'sale_id' => $this->id,
                    'product_id' => $primero->product_id,
                    'service_id' => $primero->service_id,
                    'tipo_linea' => $primero->tipo_linea,
                    'tipo_afectacion_igv' => $primero->tipo_afectacion_igv,
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
