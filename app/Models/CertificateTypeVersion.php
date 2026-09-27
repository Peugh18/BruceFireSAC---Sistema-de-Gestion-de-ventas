<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Foto de la configuración de un tipo de certificado en una versión dada.
 *
 * @property int $id
 * @property int $certificate_type_id
 * @property int $version
 * @property array<string, mixed> $configuracion
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['certificate_type_id', 'version', 'configuracion'])]
class CertificateTypeVersion extends Model
{
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'configuracion' => 'array',
        ];
    }

    public function certificateType(): BelongsTo
    {
        return $this->belongsTo(CertificateType::class);
    }
}
