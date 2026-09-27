<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Correlativo de un tipo de certificado (por año si su formato lo lleva).
 *
 * @property int $id
 * @property int $certificate_type_id
 * @property int $anio 0 = serie continua sin año
 * @property int $ultimo_numero
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['certificate_type_id', 'anio', 'ultimo_numero'])]
class CertificateSequence extends Model
{
    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'ultimo_numero' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CertificateType, $this>
     */
    public function certificateType(): BelongsTo
    {
        return $this->belongsTo(CertificateType::class);
    }
}
