<?php

namespace App\Models;

use Database\Factories\DriverFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Conductor de la empresa para las guías de remisión (DNI y licencia).
 *
 * @property int $id
 * @property string $dni
 * @property string $nombres
 * @property string $apellidos
 * @property string $licencia
 * @property bool $activo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['dni', 'nombres', 'apellidos', 'licencia', 'activo'])]
class Driver extends Model
{
    /** @use HasFactory<DriverFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
