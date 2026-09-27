<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $tipo_comprobante
 * @property string $serie
 * @property int $correlativo_actual
 * @property list<int>|null $correlativos_liberados
 * @property int|null $sede_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Sede|null $sede
 */
#[Fillable(['tipo_comprobante', 'serie', 'correlativo_actual', 'correlativos_liberados', 'sede_id'])]
class DocumentSeries extends Model
{
    protected $table = 'document_series';

    protected function casts(): array
    {
        return [
            'correlativo_actual' => 'integer',
            'correlativos_liberados' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Sede, $this>
     */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }
}
