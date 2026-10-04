<?php

namespace App\Models;

use App\Concerns\TieneUbigeo;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
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
#[Fillable(['codigo_interno', 'tipo_documento', 'numero_documento', 'razon_social', 'nombre_comercial', 'telefono', 'whatsapp', 'email', 'direccion_fiscal', 'ubigeo', 'estado_contribuyente', 'condicion_domicilio', 'consultado_at', 'activo', 'observaciones'])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    use TieneUbigeo;

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

    /**
     * Busca por documento o código, o por todas las palabras del nombre en
     * cualquier orden ("jose urcia" encuentra "URCIA GUEVARA JOSE").
     *
     * @param  Builder<Client>  $query
     */
    public function scopeBuscar(Builder $query, string $search): void
    {
        $search = trim($search);

        if ($search === '') {
            return;
        }

        $words = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY);

        $query->where(function (Builder $query) use ($search, $words) {
            $query->where('numero_documento', 'like', "%{$search}%")
                ->orWhere('codigo_interno', 'like', "%{$search}%")
                ->orWhere(function (Builder $query) use ($words) {
                    foreach ($words as $word) {
                        $query->where(function (Builder $query) use ($word) {
                            $query->where('razon_social', 'like', "%{$word}%")
                                ->orWhere('nombre_comercial', 'like', "%{$word}%");
                        });
                    }
                });
        });
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
     * Dirección que se imprime en el comprobante, o null si no hay una de
     * verdad ("-" es el relleno de CLIENTES VARIOS).
     */
    public function direccionImprimible(): ?string
    {
        $direccion = trim((string) $this->direccion_fiscal);

        return $direccion === '' || trim($direccion, '-. ') === '' ? null : $direccion;
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
     * @return HasMany<Quote, $this>
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    /**
     * @return HasMany<Equipment, $this>
     */
    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class);
    }

    /**
     * @return HasMany<Certificate, $this>
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    /**
     * @return HasMany<ServiceOrder, $this>
     */
    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
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
