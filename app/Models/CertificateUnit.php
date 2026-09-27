<?php

namespace App\Models;

use Database\Factories\CertificateUnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $certificate_id
 * @property int|null $equipment_id
 * @property string $numero_serie_snapshot
 * @property Carbon|null $fecha_ultima_ph
 * @property Carbon|null $fecha_ultima_recarga
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Certificate $certificate
 */
#[Fillable([
    'certificate_id',
    'equipment_id',
    'orden',
    'numero_cliente',
    'numero_serie_snapshot',
    'capacidad',
    'marca',
    'tipo_agente',
    'anio_fabricacion',
    'fecha_ultima_ph',
    'fecha_ultima_recarga',
    'presion_ph',
    'tiempo_ph',
    'presion_trabajo',
])]
class CertificateUnit extends Model
{
    /** @use HasFactory<CertificateUnitFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'equipment_id' => 'integer',
            'fecha_ultima_ph' => 'date',
            'fecha_ultima_recarga' => 'date',
        ];
    }

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class, 'certificate_id');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }
}
