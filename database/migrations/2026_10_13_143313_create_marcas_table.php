<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marcas de extintor que se eligen en los formularios, como los agentes: se
 * agregan, renombran o quitan desde un modal. Arranca con las marcas que ya
 * tienen los extintores registrados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marcas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 60)->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        $existentes = DB::table('equipment')
            ->whereNotNull('marca')
            ->pluck('marca')
            ->map(fn (string $marca): string => preg_replace('/\s+/', ' ', trim($marca)) ?? '')
            ->reject(fn (string $marca): bool => mb_strlen($marca) < 2 || str_starts_with($marca, 'No legible'))
            ->unique(fn (string $marca): string => mb_strtolower($marca))
            ->map(fn (string $marca): string => mb_substr($marca, 0, 60))
            ->values();

        DB::table('marcas')->insert($existentes->map(fn (string $marca) => [
            'nombre' => $marca,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('marcas');
    }
};
