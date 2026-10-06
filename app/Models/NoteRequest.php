<?php

namespace App\Models;

use Database\Factories\NoteRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Nota de crédito o débito que pidió un vendedor y espera al Gerente.
 *
 * @property int $id
 * @property int $electronic_document_id
 * @property string $tipo
 * @property string $motivo_catalogo
 * @property string $detalle
 * @property string $importe
 * @property string $estado
 * @property int $solicitado_por
 * @property int|null $revisado_por
 * @property Carbon|null $revisado_at
 * @property string|null $motivo_rechazo
 * @property int|null $nota_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ElectronicDocument $original
 * @property-read User $solicitante
 * @property-read ElectronicDocument|null $nota
 */
#[Fillable([
    'electronic_document_id', 'tipo', 'motivo_catalogo', 'detalle', 'importe', 'estado',
    'solicitado_por', 'revisado_por', 'revisado_at', 'motivo_rechazo', 'nota_id',
])]
class NoteRequest extends Model
{
    /** @use HasFactory<NoteRequestFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'importe' => 'decimal:2',
            'revisado_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ElectronicDocument, $this>
     */
    public function original(): BelongsTo
    {
        return $this->belongsTo(ElectronicDocument::class, 'electronic_document_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitado_por');
    }

    /**
     * @return BelongsTo<ElectronicDocument, $this>
     */
    public function nota(): BelongsTo
    {
        return $this->belongsTo(ElectronicDocument::class, 'nota_id');
    }
}
