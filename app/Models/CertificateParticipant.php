<?php

namespace App\Models;

use Database\Factories\CertificateParticipantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $certificate_id
 * @property int $orden
 * @property string $nombres
 * @property string|null $dni
 * @property string|null $cargo
 * @property int $sufijo
 * @property string $qr_token
 * @property Carbon|null $anulado_at
 * @property-read Certificate $certificate
 */
#[Fillable(['certificate_id', 'orden', 'nombres', 'dni', 'cargo', 'sufijo', 'qr_token', 'anulado_at'])]
class CertificateParticipant extends Model
{
    /** @use HasFactory<CertificateParticipantFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['anulado_at' => 'datetime'];
    }

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
    }

    public function numero(): string
    {
        return $this->certificate->numero.'-'.str_pad((string) $this->sufijo, 2, '0', STR_PAD_LEFT);
    }
}
