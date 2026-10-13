<?php

namespace App\Models;

use App\Enums\EquipmentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Agente extintor de la lista del sistema. El nombre es lo que se guarda en el
 * extintor y se imprime en el certificado; el tipo base decide las reglas.
 *
 * @property int $id
 * @property string $nombre
 * @property EquipmentType $tipo_base
 * @property bool $activo
 * @property int $orden
 */
#[Fillable(['nombre', 'tipo_base', 'activo', 'orden'])]
class Agente extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['tipo_base' => EquipmentType::class, 'activo' => 'boolean'];
    }

    /**
     * Todos los agentes (activos y quitados), una sola consulta por pedido.
     *
     * @return Collection<int, self>
     */
    public static function lista(): Collection
    {
        return once(fn () => Schema::hasTable('agentes')
            ? static::query()->orderBy('orden')->orderBy('nombre')->get()
            : new Collection);
    }

    /**
     * Nombres que se ofrecen en los formularios.
     *
     * @return list<string>
     */
    public static function nombresActivos(): array
    {
        return static::lista()->where('activo', true)->pluck('nombre')->values()->all();
    }

    /** Tipo base de un agente por su nombre exacto (sin importar mayúsculas). */
    public static function tipoBaseDe(?string $nombre): ?EquipmentType
    {
        $nombre = mb_strtolower(trim((string) $nombre));

        return $nombre === '' ? null : static::lista()->first(fn (self $agente) => mb_strtolower($agente->nombre) === $nombre)?->tipo_base;
    }
}
