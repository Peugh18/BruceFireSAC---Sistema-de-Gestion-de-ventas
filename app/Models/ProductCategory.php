<?php

namespace App\Models;

use Closure;
use Database\Factories\ProductCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Categoría del catálogo de productos y servicios. Productos y servicios
 * guardan su `clave`, que no cambia al renombrar la categoría, y desde la
 * migración 2026_10_08_040000 esa clave es una clave foránea real: la base
 * no deja borrar una categoría en uso ni renombrar su clave mientras la use
 * alguien (renombrar el `nombre` visible sí sigue libre).
 *
 * @property int $id
 * @property string $clave
 * @property string $nombre
 * @property bool $genera_alertas_vencimiento
 * @property bool $activo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['clave', 'nombre', 'genera_alertas_vencimiento', 'activo'])]
class ProductCategory extends Model
{
    /** @use HasFactory<ProductCategoryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'genera_alertas_vencimiento' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    /**
     * Claves de las categorías cuyos productos se avisan en Por vencer.
     *
     * @return array<int, string>
     */
    public static function clavesConAlertaDeVencimiento(): array
    {
        return self::query()->where('genera_alertas_vencimiento', true)->pluck('clave')->all();
    }

    /**
     * Todas las categorías con cuántos productos y servicios usa cada una,
     * para el modal "Gestionar" y los select.
     *
     * @return array<int, array{id: int, clave: string, nombre: string, genera_alertas_vencimiento: bool, activo: bool, usos: int}>
     */
    public static function conUsos(): array
    {
        $productos = Product::query()->whereNotNull('categoria')->selectRaw('categoria, COUNT(*) as total')->groupBy('categoria')->pluck('total', 'categoria');
        $servicios = Service::query()->whereNotNull('categoria')->selectRaw('categoria, COUNT(*) as total')->groupBy('categoria')->pluck('total', 'categoria');

        return self::query()->orderBy('nombre')->get()->map(fn (self $categoria): array => [
            'id' => $categoria->id,
            'clave' => $categoria->clave,
            'nombre' => $categoria->nombre,
            'genera_alertas_vencimiento' => $categoria->genera_alertas_vencimiento,
            'activo' => $categoria->activo,
            'usos' => (int) ($productos[$categoria->clave] ?? 0) + (int) ($servicios[$categoria->clave] ?? 0),
        ])->values()->all();
    }

    /**
     * Regla de validación de la categoría de un producto o servicio: tiene
     * que existir y estar activa (la que ya tiene puesta se puede conservar).
     */
    public static function regla(?string $actual = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($actual): void {
            if ($value === null || $value === '' || $value === $actual) {
                return;
            }

            if (! self::query()->where('clave', $value)->where('activo', true)->exists()) {
                $fail('La categoría elegida no existe o está desactivada.');
            }
        };
    }

    /**
     * Cuántos productos y servicios usan esta categoría.
     */
    public function usos(): int
    {
        return Product::query()->where('categoria', $this->clave)->count()
            + Service::query()->where('categoria', $this->clave)->count();
    }
}
