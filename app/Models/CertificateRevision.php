<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Corrección de un certificado ya emitido: quién, por qué y qué cambió.
 *
 * @property int $id
 * @property int $certificate_id
 * @property int $numero_revision
 * @property int|null $user_id
 * @property string $motivo
 * @property array<string, mixed> $antes
 * @property array<string, mixed> $despues
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Certificate $certificate
 * @property-read User|null $user
 */
#[Fillable(['certificate_id', 'numero_revision', 'user_id', 'motivo', 'antes', 'despues'])]
class CertificateRevision extends Model
{
    protected function casts(): array
    {
        return [
            'numero_revision' => 'integer',
            'antes' => 'array',
            'despues' => 'array',
        ];
    }

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
