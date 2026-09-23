<?php

namespace App\Models;

use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
