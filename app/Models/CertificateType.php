<?php

namespace App\Models;

use App\Services\Certificates\CertificateDocumentData;
use Database\Factories\CertificateTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Plantilla configurable de un certificado: qué datos se llenan (columnas de la
 * tabla y checklist), sus textos, quién firma y cómo se numera.
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property string $familia operatividad | diploma | instalacion | externo
 * @property string $orientacion vertical | horizontal (hoja A4)
 * @property string|null $titulo
 * @property string|null $subtitulo
 * @property string|null $norma
 * @property string|null $declaracion
 * @property string|null $responsabilidad
 * @property string|null $prefijo
 * @property int $digitos
 * @property bool $incluye_anio
 * @property list<array{clave: string, titulo: string, tipo?: string, unidad?: string|null, obligatorio?: bool}>|null $columnas
 * @property list<array{texto: string, unidad?: string|null, minimo?: float|null, maximo?: float|null}>|null $checklist
 * @property list<array{titulo: string, texto: string}>|null $bloques
 * @property list<string>|null $logos
 * @property int $fotos_minimas
 * @property bool $requiere_cip
 * @property bool $activo
 * @property int $version
 * @property array<string, array{x: int|float, y: int|float}>|null $diseno
 * @property int $vigencia_meses
 * @property string $generado_por_rol
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Certificate> $certificates
 * @property-read Collection<int, Signer> $signers
 * @property-read CertificateTypeSigner $pivot cuando se carga desde una persona
 */
#[Fillable([
    'codigo', 'nombre', 'vigencia_meses', 'generado_por_rol',
    'familia', 'orientacion', 'titulo', 'subtitulo', 'norma', 'declaracion', 'responsabilidad',
    'prefijo', 'digitos', 'incluye_anio', 'columnas', 'checklist', 'bloques', 'logos',
    'fotos_minimas', 'requiere_cip', 'activo', 'version', 'diseno',
])]
class CertificateType extends Model
{
    /** @use HasFactory<CertificateTypeFactory> */
    use HasFactory;

    /**
     * Campos que forman parte de la plantilla (se guardan en cada versión).
     */
    public const CAMPOS_PLANTILLA = [
        'nombre', 'familia', 'orientacion', 'titulo', 'subtitulo', 'norma', 'declaracion', 'responsabilidad',
        'prefijo', 'digitos', 'incluye_anio', 'columnas', 'checklist', 'bloques', 'logos',
        'fotos_minimas', 'requiere_cip', 'vigencia_meses', 'diseno',
    ];

    protected function casts(): array
    {
        return [
            'diseno' => 'array',
            'vigencia_meses' => 'integer',
            'digitos' => 'integer',
            'incluye_anio' => 'boolean',
            'columnas' => 'array',
            'checklist' => 'array',
            'bloques' => 'array',
            'logos' => 'array',
            'fotos_minimas' => 'integer',
            'requiere_cip' => 'boolean',
            'activo' => 'boolean',
            'version' => 'integer',
        ];
    }

    /**
     * @return HasMany<Certificate, $this>
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    /**
     * @return BelongsToMany<Signer, $this, CertificateTypeSigner>
     */
    public function signers(): BelongsToMany
    {
        return $this->belongsToMany(Signer::class)->using(CertificateTypeSigner::class)->withPivot('orden', 'cargo')->withTimestamps()->orderByPivot('orden');
    }

    /**
     * @return HasMany<CertificateTypeVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(CertificateTypeVersion::class);
    }

    /**
     * @return HasMany<Service, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    /**
     * Configuración actual de la plantilla, con sus firmantes.
     *
     * @return array<string, mixed>
     */
    public function configuracionActual(): array
    {
        return [
            ...$this->only(self::CAMPOS_PLANTILLA),
            'firmantes' => $this->signers->map(fn (Signer $signer) => [
                'id' => $signer->id,
                'nombre' => $signer->nombre,
                // El cargo con que firma este certificado; si no se eligió, el habitual.
                'cargo' => $signer->pivot->cargo ?: $signer->cargo,
                'cip' => $signer->cip,
                'firma' => app(CertificateDocumentData::class)->imagenDelFirmante($signer),
            ])->values()->all(),
        ];
    }
}
