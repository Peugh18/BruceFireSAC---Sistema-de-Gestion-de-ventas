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

    /**
     * Sede cuyo stock usa esta sede: una tienda saca del almacén asignado;
     * un almacén o una sede mixta usan el suyo propio.
     */
    public function almacenEfectivoId(): int
    {
        return $this->tipo === 'tienda' && $this->almacen_id ? (int) $this->almacen_id : $this->id;
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Sedes cuyas órdenes de servicio atiende el personal técnico de esta
     * sede: ella misma y las tiendas que dependen de ella (una tienda manda
     * sus extintores al taller de su almacén).
     *
     * @return list<int>
     */
    public static function idsAtendidosPor(int $sedeId): array
    {
        return [$sedeId, ...self::query()->where('almacen_id', $sedeId)->pluck('id')->map(fn ($id) => (int) $id)->all()];
    }

    /**
     * Sedes cuyo personal técnico puede atender una orden de esta sede: la
     * misma sede y, si es una tienda, su almacén.
     *
     * @return list<int>
     */
    public function idsQueAtienden(): array
    {
        return array_values(array_unique([$this->id, $this->almacenEfectivoId()]));
    }

    public function esAlmacen(): bool
    {
        return in_array($this->tipo, ['almacen', 'mixta'], true);
    }
}
