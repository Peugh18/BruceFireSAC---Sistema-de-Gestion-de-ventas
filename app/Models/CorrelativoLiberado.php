<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Número de una serie que quedó libre (su comprobante se corrigió antes de
 * llegar a SUNAT) y se reutiliza para no dejar huecos.
 *
 * @property int $id
 * @property int $document_series_id
 * @property int $correlativo
 */
#[Fillable(['document_series_id', 'correlativo'])]
class CorrelativoLiberado extends Model
{
    protected $table = 'correlativos_liberados';

    /**
     * @return BelongsTo<DocumentSeries, $this>
     */
    public function documentSeries(): BelongsTo
    {
        return $this->belongsTo(DocumentSeries::class);
    }
}
