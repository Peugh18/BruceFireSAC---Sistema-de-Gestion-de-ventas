<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega "varios" para el cliente genérico CLIENTES VARIOS de las boletas
     * a consumidores que no se identifican.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->enum('tipo_documento', ['dni', 'ruc', 'varios'])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('clients')->where('tipo_documento', 'varios')->delete();

        Schema::table('clients', function (Blueprint $table) {
            $table->enum('tipo_documento', ['dni', 'ruc'])->change();
        });
    }
};
