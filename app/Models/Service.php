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
 * @property string|null $categoria
 * @property string $unidad_medida
 * @property string|null $agente
 * @property string|null $capacidad
 * @property float $precio_venta
 * @property Carbon|null $igv_revisado_at
 * @property string|null $tipo_afectacion_igv
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
    'categoria',
    'unidad_medida',
    'agente',
    'capacidad',
    'precio_venta',
    'aplica_igv', 'igv_revisado_at', 'tipo_afectacion_igv',
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
            'igv_revisado_at' => 'datetime',
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

    /**
     * Capacidad escrita en un texto ("RECARGA PQS 6 KG" → "6 kg"), o null.
     */
    public static function capacidadDelTexto(?string $texto): ?string
    {
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(kg|kgs|lb|lbs|lt|lts|l|gal)\b/i', (string) $texto, $partes) !== 1) {
            return null;
        }

        return str_replace(',', '.', $partes[1]).' '.mb_strtolower($partes[2]);
    }

    /**
     * Capacidad comparable: "6 KG", "6kg" y "6,0 kgs" son la misma.
     */
    public static function normalizarCapacidad(?string $capacidad): ?string
    {
        $capacidad = self::capacidadDelTexto($capacidad);

        if ($capacidad === null) {
            return null;
        }

        [$numero, $unidad] = explode(' ', $capacidad);
        $unidad = match ($unidad) {
            'kgs' => 'kg',
            'lbs' => 'lb',
            'lt', 'lts' => 'l',
            default => $unidad,
        };

        return ((float) $numero).$unidad;
    }
}
