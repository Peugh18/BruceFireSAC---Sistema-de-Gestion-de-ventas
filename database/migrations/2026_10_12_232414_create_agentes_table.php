<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agentes extintores que se eligen en los formularios. Se administran desde
 * el sistema (agregar, renombrar, quitar). Cada uno pertenece a un tipo base
 * (CO2, PQS, agua…) que decide las reglas del certificado y de la prueba
 * hidrostática, así un agente nuevo no rompe esas reglas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agentes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 60)->unique();
            $table->string('tipo_base', 30);
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        $iniciales = [
            ['PQS ABC', 'pqs'],
            ['PQS BC', 'pqs_bc'],
            ['CO2', 'co2'],
            ['Agua presurizada', 'agua'],
            ['Espuma AFFF', 'espuma'],
            ['Acetato de potasio (K)', 'acetato_potasio'],
            ['Agente limpio', 'agente_limpio'],
            ['Otro', 'otro'],
        ];

        DB::table('agentes')->insert(array_map(fn (array $agente, int $orden) => [
            'nombre' => $agente[0],
            'tipo_base' => $agente[1],
            'activo' => true,
            'orden' => $orden,
            'created_at' => now(),
            'updated_at' => now(),
        ], $iniciales, array_keys($iniciales)));
    }

    public function down(): void
    {
        Schema::dropIfExists('agentes');
    }
};
