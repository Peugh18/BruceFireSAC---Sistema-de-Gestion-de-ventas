<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Tipo de servicio (recarga, mantenimiento, capacitación...). Define una sola
 * vez el certificado, la detracción y el código SUNAT de sus servicios.
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $codigo_detraccion
 * @property string|null $estado_detraccion
 * @property int|null $certificate_type_id
 * @property string|null $codigo_sunat
 * @property bool $pide_extintor
 * @property string|null $atencion
 * @property bool $activo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CertificateType|null $certificateType
 */
#[Fillable(['nombre', 'codigo_detraccion', 'estado_detraccion', 'certificate_type_id', 'codigo_sunat', 'pide_extintor', 'atencion', 'activo'])]
class TipoDeServicio extends Model
{
    protected $table = 'tipos_de_servicio';

    protected function casts(): array
    {
        return ['pide_extintor' => 'boolean', 'activo' => 'boolean'];
    }

    protected static function booted(): void
    {
        // Lo que define el tipo llega a todos sus servicios.
        static::updated(function (TipoDeServicio $tipo): void {
            if ($tipo->wasChanged(['codigo_detraccion', 'estado_detraccion', 'certificate_type_id'])) {
                $tipo->services()->each(fn (Service $servicio) => $servicio->update([
                    'codigo_detraccion' => $tipo->codigo_detraccion,
                    'estado_detraccion' => $tipo->estado_detraccion,
                    'certificate_type_id' => $tipo->certificate_type_id,
                ]));
            }
        });
    }

    /** @return BelongsTo<CertificateType, $this> */
    public function certificateType(): BelongsTo
    {
        return $this->belongsTo(CertificateType::class);
    }

    /** @return HasMany<Service, $this> */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}
