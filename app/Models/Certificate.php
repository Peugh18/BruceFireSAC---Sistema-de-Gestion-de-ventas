<?php

namespace App\Models;

use Database\Factories\CertificateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $numero
 * @property int $certificate_type_id
 * @property int $client_id
 * @property Carbon $fecha_emision
 * @property Carbon $fecha_vigencia_hasta
 * @property string $estado
 * @property string $qr_token
 * @property int|null $sale_id
 * @property int|null $service_order_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CertificateType $certificateType
 * @property-read Client $client
 * @property-read ServiceOrder|null $serviceOrder
 * @property-read Collection<int, CertificateUnit> $certificateUnits
 */
#[Fillable([
    'numero',
    'certificate_type_id',
    'client_id',
    'fecha_emision',
    'fecha_vigencia_hasta',
    'estado',
    'qr_token',
    'sale_id',
    'service_order_id',
])]
class Certificate extends Model
{
    /** @use HasFactory<CertificateFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'date',
            'fecha_vigencia_hasta' => 'date',
            'sale_id' => 'integer',
        ];
    }

    public function certificateType(): BelongsTo
    {
        return $this->belongsTo(CertificateType::class, 'certificate_type_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class, 'service_order_id');
    }

    public function certificateUnits(): HasMany
    {
        return $this->hasMany(CertificateUnit::class, 'certificate_id');
    }
}
