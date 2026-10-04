<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Firma del diseño con el que se dibujó el PDF (plantilla + datos de la
     * empresa). Si cambia, el PDF se vuelve a dibujar al descargarlo; no
     * depende de las fechas de los archivos, que no son confiables.
     */
    public function up(): void
    {
        Schema::table('electronic_documents', function (Blueprint $table) {
            $table->string('pdf_firma', 64)->nullable()->after('pdf_path');
        });
    }

    public function down(): void
    {
        Schema::table('electronic_documents', function (Blueprint $table) {
            $table->dropColumn('pdf_firma');
        });
    }
};
