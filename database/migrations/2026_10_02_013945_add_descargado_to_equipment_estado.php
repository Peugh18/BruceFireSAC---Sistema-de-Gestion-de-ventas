<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('equipment', function (Blueprint $table): void {
            $table->enum('estado', ['activo', 'en_servicio', 'operativo', 'baja', 'descargado', 'usado'])->default('activo')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table): void {
            $table->enum('estado', ['activo', 'en_servicio', 'operativo', 'baja'])->default('activo')->change();
        });
    }
};
