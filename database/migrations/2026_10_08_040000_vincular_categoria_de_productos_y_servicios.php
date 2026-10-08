<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La categoría de productos y servicios se guardaba como una cadena (la
 * `clave` de product_categories) sin integridad referencial: borrar o
 * renombrar la clave de una categoría dejaba a los productos colgando de un
 * nombre que ya no existe. Se cierra con una clave foránea REAL sobre
 * product_categories.clave (columna única), sin convertir la columna a un id:
 * así no se pierde ningún dato, no cambia lo que escribe el código y el
 * catálogo sigue siendo el de product_categories.
 *
 * Con la FK, la base misma garantiza que no se puede borrar una categoría en
 * uso ni renombrar su clave mientras alguien la use (renombrar el `nombre`
 * visible sigue libre: los productos guardan la `clave`).
 */
return new class extends Migration
{
    public function up(): void
    {
        // La cadena vacía quería decir "sin categoría", igual que NULL; la FK
        // solo acepta claves reales, así que se unifica en NULL.
        DB::table('products')->where('categoria', '')->update(['categoria' => null]);
        DB::table('services')->where('categoria', '')->update(['categoria' => null]);

        // Ningún dato se pierde: una clave que todavía no esté en el catálogo
        // (productos anteriores a la tabla de categorías) se conserva creando
        // su categoría, como ya hizo la migración 2026_10_07_100000.
        $claves = DB::table('product_categories')->pluck('clave')->map(fn ($clave) => (string) $clave)->all();
        foreach (['products', 'services'] as $tabla) {
            $huerfanas = DB::table($tabla)->whereNotNull('categoria')->where('categoria', '!=', '')
                ->distinct()->pluck('categoria')
                ->map(fn ($clave) => (string) $clave)
                ->reject(fn (string $clave) => in_array($clave, $claves, true));

            foreach ($huerfanas as $clave) {
                DB::table('product_categories')->insert([
                    'clave' => $clave,
                    'nombre' => ucfirst($clave),
                    'genera_alertas_vencimiento' => false,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $claves[] = $clave;
            }
        }

        foreach (['products', 'services'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->foreign('categoria')->references('clave')->on('product_categories')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Solo se sueltan las claves foráneas: las categorías que se crearon
        // aquí para conservar claves huérfanas se quedan, porque soltarlas sí
        // que perdería dato.
        foreach (['services', 'products'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropForeign(['categoria']);
            });
        }
    }
};
