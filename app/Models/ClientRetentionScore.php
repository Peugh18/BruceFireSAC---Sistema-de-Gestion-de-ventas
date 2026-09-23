<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $client_id
 * @property float $probabilidad
 * @property string $categoria
 * @property int $recencia_dias
 * @property int $frecuencia_compras
 * @property float $monto_total
 * @property float $ticket_promedio
 * @property int $antiguedad_dias
 * @property int $diversidad_productos
 * @property bool $compro_recarga
 * @property array<string, mixed>|null $factores_json
 * @property Carbon $scored_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Client $client
 */
#[Fillable([
    'client_id',
    'probabilidad',
    'categoria',
    'recencia_dias',
    'frecuencia_compras',
    'monto_total',
    'ticket_promedio',
    'antiguedad_dias',
    'diversidad_productos',
    'compro_recarga',
    'factores_json',
    'scored_at',
])]
class ClientRetentionScore extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'probabilidad' => 'float',
            'monto_total' => 'decimal:2',
            'ticket_promedio' => 'decimal:2',
            'compro_recarga' => 'boolean',
            'factores_json' => 'array',
            'scored_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
