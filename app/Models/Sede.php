<?php

namespace App\Models;

use App\Concerns\TieneUbigeo;
use Database\Factories\SedeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $nombre
 * @property string $tipo
 * @property string|null $ubigeo
 * @property-read string|null $ciudad
 * @property int|null $almacen_id
 * @property bool $activo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Sede|null $almacen
 * @property-read Collection<int, Sede> $tiendas
 */
#[Fillable(['nombre', 'tipo', 'ubigeo', 'almacen_id', 'activo'])]
class Sede extends Model
{
    /** @use HasFactory<SedeFactory> */
    use HasFactory;

    use TieneUbigeo;

    /**
     * @var list<string>
     */
    protected $appends = ['ciudad'];

    /**
     * @var list<string>
     */
    protected $hidden = ['ubicacion'];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    /**
     * Ciudad de la sede: la provincia de su ubigeo ("Trujillo").
     *
     * @return Attribute<string|null, never>
     */
    protected function ciudad(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->ubicacion ? Str::title(Str::lower($this->ubicacion->provincia)) : null);
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function almacen(): BelongsTo
    {
        return $this->belongsTo(self::class, 'almacen_id');
    }

    /**
     * @return HasMany<self, $this>
     */
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

    /**
     * @return HasMany<User, $this>
     */
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
