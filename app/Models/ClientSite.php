<?php

namespace App\Models;

use App\Concerns\TieneUbigeo;
use Database\Factories\ClientSiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $client_id
 * @property string $tipo
 * @property string $nombre
 * @property string $direccion
 * @property string|null $ubigeo
 * @property string|null $referencia
 * @property string|null $contacto
 * @property string|null $telefono
 * @property string|null $email
 * @property string $estado
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Client $client
 */
#[Fillable(['client_id', 'tipo', 'nombre', 'direccion', 'ubigeo', 'referencia', 'contacto', 'telefono', 'email', 'estado'])]
class ClientSite extends Model
{
    /** @use HasFactory<ClientSiteFactory> */
    use HasFactory;

    use TieneUbigeo;

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
