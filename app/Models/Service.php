<?php

namespace App\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property string|null $descripcion
 * @property string $unidad_medida
 * @property float $precio_venta
 * @property bool $aplica_igv
 * @property int|null $certificate_type_id
 * @property bool $activo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, QuoteItem> $quoteItems
 * @property-read Collection<int, SaleItem> $saleItems
 */
#[Fillable([
    'codigo',
    'nombre',
    'descripcion',
    'unidad_medida',
    'precio_venta',
    'aplica_igv',
    'certificate_type_id',
    'activo',
])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'precio_venta' => 'decimal:2',
            'aplica_igv' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    /**
     * Certificado que genera este servicio (null si no genera ninguno).
     *
     * @return BelongsTo<CertificateType, $this>
     */
    public function certificateType(): BelongsTo
    {
        return $this->belongsTo(CertificateType::class);
    }

    /**
     * @return HasMany<QuoteItem, $this>
     */
    public function quoteItems(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
    }

    /**
     * @return HasMany<SaleItem, $this>
     */
    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function esServicio(): bool
    {
        return true;
    }
}
