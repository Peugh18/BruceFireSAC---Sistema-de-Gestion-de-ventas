<?php

namespace App\Models;

use App\Enums\EquipmentType;
use App\Models\Concerns\AuditaElCatalogo;
use App\Models\Concerns\TieneCodigoCorrelativo;
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
 * @property string|null $categoria clave de product_categories (FK real sobre product_categories.clave); NULL = sin categoría
 * @property string $unidad_medida
 * @property string|null $agente
 * @property string|null $capacidad
 * @property string|null $codigo_detraccion
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
 * @property-read ProductCategory|null $categoriaDelCatalogo
 * @property int|null $tipo_de_servicio_id
 * @property string|null $estado_detraccion
 * @property-read TipoDeServicio|null $tipoDeServicio
 */
#[Fillable([
    'tipo_de_servicio_id',
    'codigo',
    'nombre',
    'descripcion',
    'categoria',
    'unidad_medida',
    'agente',
    'capacidad',
    'precio_venta', 'codigo_detraccion', 'estado_detraccion',
    'aplica_igv', 'igv_revisado_at', 'tipo_afectacion_igv',
    'certificate_type_id',
    'activo',
])]
class Service extends Model
{
    public const PREFIJO_CODIGO = 'SRV';

    public const AUDITORIA_PREFIJO = 'servicio';

    /** @use HasFactory<ServiceFactory> */
    use AuditaElCatalogo, HasFactory, TieneCodigoCorrelativo;

    protected static function booted(): void
    {
        // El tipo define el certificado y la detracción: el servicio los toma de él.
        static::saving(function (Service $servicio): void {
            if ($servicio->tipo_de_servicio_id !== null && $servicio->isDirty('tipo_de_servicio_id')) {
                $tipo = TipoDeServicio::query()->find($servicio->tipo_de_servicio_id);
                $servicio->codigo_detraccion = $tipo?->codigo_detraccion;
                $servicio->estado_detraccion = $tipo?->estado_detraccion;
                $servicio->certificate_type_id = $tipo?->certificate_type_id;
            }
        });
    }

    /** @return BelongsTo<TipoDeServicio, $this> */
    public function tipoDeServicio(): BelongsTo
    {
        return $this->belongsTo(TipoDeServicio::class);
    }

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

    /**
     * Categoría del catálogo (product_categories). La columna `categoria`
     * guarda su `clave`, que no cambia al renombrar la categoría: con la FK
     * real de la migración 2026_10_08_040000 la base impide borrar o
     * renombrar la clave de una categoría en uso.
     *
     * @return BelongsTo<ProductCategory, $this>
     */
    public function categoriaDelCatalogo(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'categoria', 'clave');
    }

    public function codigoDetraccion(): ?string
    {
        // Revisado como no sujeto: nunca se deduce un código por su nombre o categoría.
        if ($this->estado_detraccion === 'no_sujeto') {
            return null;
        }

        if ($this->codigo_detraccion !== null) {
            return $this->codigo_detraccion;
        }

        if ($this->estado_detraccion !== null) {
            return null;
        }

        if (preg_match('/recarga.*(?:mantenimiento|reparaci)|(?:mantenimiento|reparaci).*recarga/iu', $this->nombre) === 1) {
            return null;
        }

        return config('billing.detraccion.por_categoria.'.($this->categoria ?? ''));
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

    public function incompatibilidadConEquipo(Equipment $equipment): ?string
    {
        $agenteServicio = EquipmentType::deAgente($this->agente, $this->nombre);
        $agenteEquipo = EquipmentType::fromDescription($equipment->tipo_agente);

        if ($agenteServicio !== EquipmentType::Otro && $agenteEquipo !== EquipmentType::Otro && $agenteServicio !== $agenteEquipo) {
            return 'El agente del extintor no corresponde al servicio seleccionado.';
        }

        $capacidadServicio = self::normalizarCapacidad($this->capacidad ?: $this->nombre);
        $capacidadEquipo = self::normalizarCapacidad($equipment->capacidad);

        if ($capacidadServicio !== null && $capacidadEquipo !== null && $capacidadServicio !== $capacidadEquipo) {
            return 'La capacidad del extintor no corresponde al servicio seleccionado.';
        }

        return null;
    }

    /** Código de producto SUNAT (catálogo 25) que va en el XML, si aplica. */
    public function codigoSunat(): ?string
    {
        return $this->tipoDeServicio?->codigo_sunat;
    }
}
