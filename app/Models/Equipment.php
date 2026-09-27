<?php

namespace App\Models;

use Database\Factories\EquipmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $client_id
 * @property int $product_id
 * @property string $numero_serie
 * @property Carbon $fecha_venta
 * @property string|null $ubicacion_actual
 * @property string $estado
 * @property Carbon|null $proxima_fecha_atencion
 * @property Carbon|null $proxima_prueba_hidrostatica
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Client $client
 * @property-read Product $product
 */
#[Fillable([
    'client_id', 'product_id', 'numero_serie', 'numero_cliente', 'fecha_venta', 'ubicacion_actual',
    'estado', 'proxima_fecha_atencion', 'proxima_prueba_hidrostatica',
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
            'proxima_fecha_atencion' => 'date',
            'proxima_prueba_hidrostatica' => 'date',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function serviceOrders(): BelongsToMany
    {
        return $this->belongsToMany(ServiceOrder::class, 'service_order_equipment')
            ->withPivot(['recibido', 'observaciones'])
            ->withTimestamps();
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(TechnicalChecklist::class);
    }
}
