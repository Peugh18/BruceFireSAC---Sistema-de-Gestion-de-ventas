<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Capacidades de extintor que se tocan en los formularios (6 kg, 10 lb, 6 L):
 * como agentes y marcas, se agregan o quitan desde un modal (30 lb, 50 kg…).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capacidades', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 20)->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        $iniciales = ['1 kg', '2 kg', '4 kg', '6 kg', '9 kg', '12 kg', '5 lb', '10 lb', '15 lb', '20 lb', '6 L', '9 L'];
        DB::table('capacidades')->insert(array_map(fn (string $nombre) => [
            'nombre' => $nombre,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $iniciales));
    }

    public function down(): void
    {
        Schema::dropIfExists('capacidades');
    }
};
