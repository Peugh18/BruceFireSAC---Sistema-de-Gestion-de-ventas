<?php

namespace App\Models;

use Database\Factories\ElectronicDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $sale_id
 * @property string $tipo
 * @property string $serie
 * @property int $correlativo
 * @property int|null $cpe_afectado_id
 * @property string|null $motivo_catalogo
 * @property string|null $xml_path
 * @property string|null $cdr_path
 * @property string|null $pdf_path
 * @property string $sunat_estado
 * @property string|null $sunat_codigo_respuesta
 * @property string|null $sunat_mensaje
 * @property Carbon|null $enviado_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Sale $sale
 * @property-read ElectronicDocument|null $cpeAfectado
 */
#[Fillable([
    'sale_id', 'tipo', 'serie', 'correlativo', 'cpe_afectado_id', 'motivo_catalogo',
    'xml_path', 'cdr_path', 'pdf_path', 'sunat_estado', 'sunat_codigo_respuesta',
    'sunat_mensaje', 'enviado_at',
])]
class ElectronicDocument extends Model
{
    /** @use HasFactory<ElectronicDocumentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'correlativo' => 'integer',
            'enviado_at' => 'datetime',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function cpeAfectado(): BelongsTo
    {
        return $this->belongsTo(self::class, 'cpe_afectado_id');
    }
}
