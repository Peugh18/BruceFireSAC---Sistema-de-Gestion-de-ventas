<?php

namespace App\Models;

use Database\Factories\SedeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nombre
 * @property string $tipo
 * @property string|null $ubigeo
 * @property string|null $ciudad
 * @property int|null $almacen_id
 * @property bool $activo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Sede|null $almacen
 * @property-read Collection<int, Sede> $tiendas
 */
#[Fillable(['nombre', 'tipo', 'ubigeo', 'ciudad', 'almacen_id', 'activo'])]
class Sede extends Model
{
    /** @use HasFactory<SedeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(self::class, 'almacen_id');
    }

    public function tiendas(): HasMany
    {
        return $this->hasMany(self::class, 'almacen_id');
    }

    public function esAlmacen(): bool
    {
        return in_array($this->tipo, ['almacen', 'mixta'], true);
    }
}
