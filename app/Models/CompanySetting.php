<?php

namespace App\Models;

use Database\Factories\CompanySettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $razon_social
 * @property string|null $nombre_comercial
 * @property string $ruc
 * @property string|null $direccion
 * @property string|null $ubigeo
 * @property string|null $departamento
 * @property string|null $provincia
 * @property string|null $distrito
 * @property string|null $telefono
 * @property string|null $email
 * @property string|null $logo_path
 * @property string|null $leyenda_pie
 * @property string|null $cuenta_detraccion
 */
#[Fillable([
    'razon_social', 'nombre_comercial', 'ruc', 'direccion', 'ubigeo', 'departamento',
    'distrito', 'provincia', 'telefono', 'email', 'logo_path', 'leyenda_pie', 'cuenta_detraccion',
    'instructor_capacitacion',
])]
class CompanySetting extends Model
{
    /** @use HasFactory<CompanySettingFactory> */
    use HasFactory;

    /**
     * Configuración de empresa como fila única (singleton). Se crea con los
     * valores de config/billing.php la primera vez que se necesita; a partir
     * de ahí esta fila en base de datos es la fuente de verdad editable
     * desde Gerente, no el .env.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'razon_social' => config('billing.company.razon_social', 'BRUCE FIRE S.A.C.'),
            'nombre_comercial' => config('billing.company.nombre_comercial', 'BRUCE FIRE'),
            'ruc' => (string) config('billing.company.ruc'),
            'direccion' => config('billing.company.direccion'),
            'ubigeo' => config('billing.company.ubigeo'),
            'departamento' => config('billing.company.departamento'),
            'provincia' => config('billing.company.provincia'),
            'distrito' => config('billing.company.distrito'),
            'instructor_capacitacion' => 'Edgar Guevara Cabrera',
        ]);
    }

    public function logoUrl(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        return Storage::disk('public')->url($this->logo_path);
    }
}
