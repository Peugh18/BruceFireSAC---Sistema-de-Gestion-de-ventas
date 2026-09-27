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
 * @property int $revision
 * @property string|null $anulado_motivo
 * @property Carbon|null $anulado_at
 * @property int|null $anulado_por
 * @property int|null $certificate_type_version_id
 * @property string $qr_token
 * @property int|null $sale_id
 * @property int|null $service_order_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CertificateType $certificateType
 * @property-read Client $client
 * @property-read ServiceOrder|null $serviceOrder
 * @property-read Sale|null $sale
 * @property-read Collection<int, CertificateUnit> $certificateUnits
 * @property-read Collection<int, CertificateParticipant> $participants
 * @property-read Collection<int, CertificateRevision> $revisions
 * @property-read CertificateTypeVersion|null $typeVersion
 */
#[Fillable([
    'numero',
    'certificate_type_id',
    'client_id',
    'referencia',
    'direccion',
    'tipo_atencion',
    'datos',
    'fecha_emision',
    'fecha_vigencia_hasta',
    'estado',
    'revision',
    'anulado_motivo',
    'anulado_at',
    'anulado_por',
    'certificate_type_version_id',
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
            'datos' => 'array',
            'fecha_emision' => 'date',
            'fecha_vigencia_hasta' => 'date',
            'sale_id' => 'integer',
            'revision' => 'integer',
            'anulado_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CertificateType, $this>
     */
    public function certificateType(): BelongsTo
    {
        return $this->belongsTo(CertificateType::class, 'certificate_type_id');
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /**
     * @return BelongsTo<ServiceOrder, $this>
     */
    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class, 'service_order_id');
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return HasMany<CertificateUnit, $this>
     */
    public function certificateUnits(): HasMany
    {
        return $this->hasMany(CertificateUnit::class, 'certificate_id');
    }

    /**
     * @return HasMany<CertificateParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(CertificateParticipant::class)->orderBy('orden');
    }

    /**
     * @return HasMany<CertificateRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(CertificateRevision::class)->orderBy('numero_revision');
    }

    /**
     * @return BelongsTo<CertificateTypeVersion, $this>
     */
    public function typeVersion(): BelongsTo
    {
        return $this->belongsTo(CertificateTypeVersion::class, 'certificate_type_version_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }
}
