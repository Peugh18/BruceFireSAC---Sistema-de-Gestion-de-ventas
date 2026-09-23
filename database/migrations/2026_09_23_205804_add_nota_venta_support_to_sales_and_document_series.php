<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las notas de venta son ventas internas sin comprobante SUNAT: el tipo de
     * comprobante deja de ser un enum cerrado y las series admiten "nota_venta".
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('comprobante_tipo')->change();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->string('numero_nota_venta')->nullable()->unique()->after('numero_interno');
        });

        Schema::table('document_series', function (Blueprint $table) {
            $table->string('tipo_comprobante')->change();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropUnique(['numero_nota_venta']);
            $table->dropColumn('numero_nota_venta');
        });
    }
};
