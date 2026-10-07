<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * S5: cambia fecha_emision de DATE a DATETIME para registrar la hora de
     * emision del comprobante, obligatoria en el XML de SUNAT.
     */
    public function up(): void
    {
        Schema::table('electronic_documents', function (Blueprint $table) {
            $table->dateTime('fecha_emision')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('electronic_documents', function (Blueprint $table) {
            // Al revertir se pierde la hora; los datos de fecha siguen validos.
            $table->date('fecha_emision')->nullable()->change();
        });
    }
};
