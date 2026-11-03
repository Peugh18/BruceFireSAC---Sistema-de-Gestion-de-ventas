<?php

namespace App\Models;

use App\Enums\EquipmentType;
use Database\Factories\EquipmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $client_id
 * @property int|null $product_id
 * @property string $numero_serie
 * @property Carbon|null $fecha_venta
 * @property Carbon|null $fecha_ultima_ph
 * @property string|null $ubicacion_actual
 * @property string $estado
 * @property Carbon|null $proxima_fecha_atencion
 * @property Carbon|null $proxima_prueba_hidrostatica
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Client $client
 * @property-read Product|null $product
 */
#[Fillable([
    'client_id', 'product_id', 'numero_serie', 'numero_cliente', 'fecha_venta', 'ubicacion_actual',
    'estado', 'proxima_fecha_atencion', 'proxima_prueba_hidrostatica', 'fecha_ultima_ph',
    'tipo_agente', 'capacidad', 'marca', 'serie_fabricante', 'anio_fabricacion',
    'foto_general_path', 'foto_placa_path', 'notas',
])]
class Equipment extends Model
{
    /** @use HasFactory<EquipmentFactory> */
    use HasFactory;

    protected $table = 'equipment';

    protected function casts(): array
    {
        return [
            'fecha_venta' => 'date',
            'fecha_ultima_ph' => 'date',
            'proxima_fecha_atencion' => 'date',
            'proxima_prueba_hidrostatica' => 'date',
        ];
    }

    /**
     * Lo que le falta para salir en un certificado: un certificado no se
     * emite con datos pendientes ("No legible" o vacío); se pide completarlos.
     *
     * @return list<string>
     */
    public function datosQueFaltan(): array
    {
        $vacio = fn (mixed $valor) => blank($valor) || str_starts_with(trim((string) $valor), 'No legible');
        $anio = trim((string) $this->anio_fabricacion);

        return array_values(array_filter([
            $vacio($this->tipo_agente) || ! EquipmentType::esConocido($this->tipo_agente) ? 'el agente extintor' : null,
            $vacio($this->capacidad) ? 'la capacidad' : null,
            $vacio($this->marca) ? 'la marca' : null,
            $vacio($this->serie_fabricante) ? 'la serie de fábrica' : null,
            preg_match('/^\d{4}$/', $anio) !== 1 || (int) $anio > today()->year ? 'el año de fabricación' : null,
        ]));
    }

    /**
     * "Falta el año de fabricación de BF-EQ-000006." para los equipos
     * incompletos; null si todos se pueden certificar.
     *
     * @param  iterable<Equipment>  $equipos
     */
    public static function faltantesParaCertificar(iterable $equipos): ?string
    {
        $mensajes = [];
        foreach ($equipos as $equipo) {
            $faltan = $equipo->datosQueFaltan();
            if ($faltan !== []) {
                $lista = count($faltan) > 1 ? implode(', ', array_slice($faltan, 0, -1)).' y '.end($faltan) : $faltan[0];
                $mensajes[] = 'Falta '.$lista.' de '.$equipo->numero_serie.'.';
            }
        }

        return $mensajes === [] ? null : implode(' ', $mensajes).' Complétalo en la ficha del extintor para emitir el certificado.';
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsToMany<ServiceOrder, $this>
     */
    public function serviceOrders(): BelongsToMany
    {
        return $this->belongsToMany(ServiceOrder::class, 'service_order_equipment')
            ->withPivot(['service_order_line_id', 'recibido', 'observaciones', 'posicion'])
            ->withTimestamps();
    }

    /** @return BelongsToMany<Quote, $this> */
    public function quotes(): BelongsToMany
    {
        return $this->belongsToMany(Quote::class);
    }

    /**
     * @return HasMany<TechnicalChecklist, $this>
     */
    public function checklists(): HasMany
    {
        return $this->hasMany(TechnicalChecklist::class);
    }

    /**
     * @return HasMany<SaleItem, $this>
     */
    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * Equipos que el usuario puede ver: el Gerente, todos; los demás, los
     * vendidos, cotizados o atendidos en su sede. El cliente se comparte
     * entre sedes, pero sus equipos no.
     *
     * @param  Builder<Equipment>  $query
     */
    public function scopeVisiblePara(Builder $query, User $user): void
    {
        $sedeId = $user->sedeRestringidaId();

        if ($sedeId === null) {
            return;
        }

        $query->where(fn (Builder $query) => $query
            ->whereHas('saleItems.sale', fn (Builder $venta) => $venta->where('sede_id', $sedeId))
            ->orWhereHas('serviceOrders', fn (Builder $orden) => $orden->where('service_orders.sede_id', $sedeId))
            ->orWhereHas('quotes', fn (Builder $cotizacion) => $cotizacion->where('quotes.sede_id', $sedeId)));
    }
}
