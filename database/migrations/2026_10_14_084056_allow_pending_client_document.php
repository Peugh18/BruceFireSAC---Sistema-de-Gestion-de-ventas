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
        Schema::table('clients', function (Blueprint $table) {
            // Campo puede abrir el recojo con nombre y teléfono reales cuando
            // el DNI/RUC no está disponible. El documento se completa luego
            // en el mismo cliente; no se usa un valor fiscal inventado.
            $table->enum('tipo_documento', ['dni', 'ruc', 'varios'])->nullable()->change();
            $table->string('numero_documento')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->enum('tipo_documento', ['dni', 'ruc', 'varios'])->nullable(false)->change();
            $table->string('numero_documento')->nullable(false)->change();
        });
    }
};
