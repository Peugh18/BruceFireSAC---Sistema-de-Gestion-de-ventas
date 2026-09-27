<?php

namespace App\Models;

use Database\Factories\SignerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Persona que firma certificados (administrador, ingeniero con CIP, técnico,
 * instructor), con su firma escaneada y su sello.
 *
 * @property int $id
 * @property string $nombre
 * @property string $cargo
 * @property string|null $cip
 * @property string|null $especialidad
 * @property string|null $firma_path
 * @property string|null $sello_path
 * @property bool $activo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, CertificateType> $certificateTypes
 */
#[Fillable(['nombre', 'cargo', 'cip', 'especialidad', 'firma_path', 'sello_path', 'activo'])]
class Signer extends Model
{
    /** @use HasFactory<SignerFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function certificateTypes(): BelongsToMany
    {
        return $this->belongsToMany(CertificateType::class)->withPivot('orden')->withTimestamps();
    }
}
