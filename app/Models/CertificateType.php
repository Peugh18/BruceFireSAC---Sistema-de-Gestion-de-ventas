<?php

namespace App\Models;

use Database\Factories\CertificateTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property int $vigencia_meses
 * @property string $generado_por_rol
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Certificate> $certificates
 */
#[Fillable(['codigo', 'nombre', 'vigencia_meses', 'generado_por_rol'])]
class CertificateType extends Model
{
    /** @use HasFactory<CertificateTypeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'vigencia_meses' => 'integer',
        ];
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }
}
