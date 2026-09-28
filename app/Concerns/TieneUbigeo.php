<?php

namespace App\Concerns;

use App\Models\Ubigeo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo que guarda solo el código de ubigeo: departamento, provincia y
 * distrito se leen del catálogo del INEI (tercera forma normal).
 *
 * @property-read Ubigeo|null $ubicacion
 * @property-read string|null $departamento
 * @property-read string|null $provincia
 * @property-read string|null $distrito
 */
trait TieneUbigeo
{
    /**
     * @return BelongsTo<Ubigeo, $this>
     */
    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(Ubigeo::class, 'ubigeo', 'codigo');
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function departamento(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->ubicacion?->departamento);
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function provincia(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->ubicacion?->provincia);
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function distrito(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->ubicacion?->distrito);
    }
}
