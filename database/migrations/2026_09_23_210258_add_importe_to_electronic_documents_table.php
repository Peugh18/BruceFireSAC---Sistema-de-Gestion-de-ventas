<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las notas de crédito y débito guardan su importe total con IGV para poder
     * construir el XML y controlar que no superen el total de la venta.
     */
    public function up(): void
    {
        Schema::table('electronic_documents', function (Blueprint $table) {
            $table->decimal('importe', 12, 2)->nullable()->after('motivo_catalogo');
        });
    }

    public function down(): void
    {
        Schema::table('electronic_documents', function (Blueprint $table) {
            $table->dropColumn('importe');
        });
    }
};
