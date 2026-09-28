<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Distrito del catálogo oficial del INEI (el mismo que usa SUNAT).
 *
 * @property string $codigo
 * @property string $departamento
 * @property string $provincia
 * @property string $distrito
 */
class Ubigeo extends Model
{
    protected $primaryKey = 'codigo';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    /**
     * Busca por código o por todas las palabras en distrito, provincia o
     * departamento ("trujillo la libertad").
     *
     * @param  Builder<Ubigeo>  $query
     */
    public function scopeBuscar(Builder $query, string $search): void
    {
        $words = preg_split('/\s+/', trim($search), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($words as $word) {
            $query->where(fn (Builder $query) => $query
                ->where('codigo', 'like', "{$word}%")
                ->orWhere('distrito', 'like', "%{$word}%")
                ->orWhere('provincia', 'like', "%{$word}%")
                ->orWhere('departamento', 'like', "%{$word}%"));
        }
    }

    /**
     * "TRUJILLO - TRUJILLO - LA LIBERTAD", como se lee en una dirección.
     */
    public function etiqueta(): string
    {
        return "{$this->distrito} - {$this->provincia} - {$this->departamento}";
    }

    /**
     * @return array{codigo: string, departamento: string, provincia: string, distrito: string, etiqueta: string}
     */
    public function paraFormulario(): array
    {
        return [
            'codigo' => $this->codigo,
            'departamento' => $this->departamento,
            'provincia' => $this->provincia,
            'distrito' => $this->distrito,
            'etiqueta' => $this->etiqueta(),
        ];
    }
}
