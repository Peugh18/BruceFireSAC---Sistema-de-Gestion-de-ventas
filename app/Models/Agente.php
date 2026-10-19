<?php

namespace App\Models;

use App\Enums\EquipmentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Agente extintor de la lista del sistema. El nombre es lo que se guarda en el
 * extintor y se imprime en el certificado; el tipo base decide las reglas.
 *
 * @property int $id
 * @property string $nombre
 * @property EquipmentType $tipo_base
 * @property bool $activo
 * @property int $orden
 * @property list<string>|null $nombres_anteriores
 */
#[Fillable(['nombre', 'tipo_base', 'activo', 'orden', 'nombres_anteriores'])]
class Agente extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['tipo_base' => EquipmentType::class, 'activo' => 'boolean', 'nombres_anteriores' => 'array'];
    }

    /**
     * Todos los agentes (activos y quitados), una sola consulta por pedido.
     *
     * @return Collection<int, self>
     */
    public static function lista(): Collection
    {
        return once(fn (): Collection => Schema::hasTable('agentes')
            ? self::query()->orderBy('orden')->orderBy('nombre')->get()
            : new Collection);
    }

    /**
     * Nombres que se ofrecen en los formularios.
     *
     * @return list<string>
     */
    public static function nombresActivos(): array
    {
        return array_values(static::lista()->where('activo', true)->map(fn (self $agente): string => $agente->nombre)->all());
    }

    /** Reconoce también los nombres anteriores, sin cambiar lo impreso en los equipos. */
    public function coincideCon(string $nombre): bool
    {
        $nombre = Str::lower(Str::ascii(trim($nombre)));

        foreach ([$this->nombre, ...($this->nombres_anteriores ?? [])] as $conocido) {
            if (Str::lower(Str::ascii($conocido)) === $nombre) {
                return true;
            }
        }

        return false;
    }

    /** Tipo base de un agente por su nombre actual o anterior. */
    public static function tipoBaseDe(?string $nombre): ?EquipmentType
    {
        $nombre = mb_strtolower(trim((string) $nombre));

        return $nombre === '' ? null : static::lista()->first(fn (self $agente) => $agente->coincideCon($nombre))?->tipo_base;
    }
}
