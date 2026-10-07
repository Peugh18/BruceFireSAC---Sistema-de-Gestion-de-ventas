<?php

namespace App\Models;

use Database\Factories\TransportVehicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Vehículo de la empresa para el transporte privado de las guías de
 * remisión (no confundir con Vehicle, que es el vehículo de un cliente).
 *
 * @property int $id
 * @property string $placa
 * @property string $categoria
 * @property string|null $descripcion
 * @property bool $activo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['placa', 'categoria', 'descripcion', 'activo'])]
class TransportVehicle extends Model
{
    /** @use HasFactory<TransportVehicleFactory> */
    use HasFactory;

    public const CATEGORIAS = ['M1' => 'M1 (auto, hasta 8 asientos)', 'L' => 'L (moto)', 'N' => 'N (camión)'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
