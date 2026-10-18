<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sedes', function (Blueprint $table) {
            $table->enum('tipo', ['tienda', 'almacen', 'mixta', 'taller'])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('sedes')->where('tipo', 'taller')->exists()) {
            throw new RuntimeException('Reasigna los talleres antes de retirar este tipo de sede.');
        }

        Schema::table('sedes', function (Blueprint $table) {
            $table->enum('tipo', ['tienda', 'almacen', 'mixta'])->change();
        });
    }
};
