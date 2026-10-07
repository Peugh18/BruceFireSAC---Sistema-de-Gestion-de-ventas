<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Categorías del catálogo como datos (antes eran 6 fijas en el código) y
 * categoría también en los servicios. La marca "genera alertas de
 * vencimiento" reemplaza la búsqueda por el nombre "extintor".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 40)->unique();
            $table->string('nombre', 80);
            $table->boolean('genera_alertas_vencimiento')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->string('categoria', 40)->nullable()->after('descripcion');
        });

        $categorias = [
            'extintor' => ['Extintor', true],
            'epp' => ['EPP (seguridad personal)', false],
            'senalizacion' => ['Señalización', false],
            'repuesto' => ['Repuesto / componente', false],
            'accesorio' => ['Accesorio (gabinete, soporte)', false],
            'otro' => ['Otro', false],
        ];

        // Cualquier otro valor que ya tengan los productos también se conserva.
        $extras = DB::table('products')->whereNotNull('categoria')->where('categoria', '!=', '')
            ->distinct()->pluck('categoria')->reject(fn ($clave) => isset($categorias[$clave]));
        foreach ($extras as $clave) {
            $categorias[(string) $clave] = [ucfirst((string) $clave), false];
        }

        foreach ($categorias as $clave => [$nombre, $alertas]) {
            DB::table('product_categories')->insert([
                'clave' => $clave,
                'nombre' => $nombre,
                'genera_alertas_vencimiento' => $alertas,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('categoria');
        });

        Schema::dropIfExists('product_categories');
    }
};
