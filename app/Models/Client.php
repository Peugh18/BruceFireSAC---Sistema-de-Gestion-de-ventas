<?php

namespace App\Models;

use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $codigo_interno
 * @property string $tipo_documento
 * @property string $numero_documento
 * @property string $razon_social
 * @property string|null $nombre_comercial
 * @property string|null $telefono
 * @property string|null $whatsapp
 * @property string|null $email
 * @property string|null $direccion_fiscal
 * @property string|null $departamento
 * @property string|null $provincia
 * @property string|null $distrito
 * @property string|null $ubigeo
 * @property string|null $estado_contribuyente
 * @property string|null $condicion_domicilio
 * @property Carbon|null $consultado_at
 * @property bool $activo
 * @property string|null $observaciones
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, ClientSite> $sites
 * @property-read Collection<int, Vehicle> $vehicles
 */
#[Fillable(['codigo_interno', 'tipo_documento', 'numero_documento', 'razon_social', 'nombre_comercial', 'telefono', 'whatsapp', 'email', 'direccion_fiscal', 'departamento', 'provincia', 'distrito', 'ubigeo', 'estado_contribuyente', 'condicion_domicilio', 'consultado_at', 'activo', 'observaciones'])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    public const TIPO_DOCUMENTO_VARIOS = 'varios';

    /**
     * Cliente genérico para boletas a consumidores que no dan su DNI.
     * SUNAT solo lo acepta en boletas de hasta S/ 700.00.
     */
    public static function clientesVarios(): self
    {
        return self::query()->firstOrCreate(
            ['tipo_documento' => self::TIPO_DOCUMENTO_VARIOS, 'numero_documento' => '00000000'],
            [
                'codigo_interno' => 'CLI-VARIOS',
                'razon_social' => 'CLIENTES VARIOS',
                'direccion_fiscal' => '-',
                'activo' => true,
            ],
        );
    }

    public function esClientesVarios(): bool
    {
        return $this->tipo_documento === self::TIPO_DOCUMENTO_VARIOS;
    }

    public function tieneRuc(): bool
    {
        return $this->tipo_documento === 'ruc';
    }

    /**
     * @return HasMany<ClientSite, $this>
     */
    public function sites(): HasMany
    {
        return $this->hasMany(ClientSite::class);
    }

    /**
     * @return HasMany<Vehicle, $this>
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    /**
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * @return HasOne<ClientRetentionScore, $this>
     */
    public function retentionScore(): HasOne
    {
        return $this->hasOne(ClientRetentionScore::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'consultado_at' => 'datetime',
        ];
    }
}
