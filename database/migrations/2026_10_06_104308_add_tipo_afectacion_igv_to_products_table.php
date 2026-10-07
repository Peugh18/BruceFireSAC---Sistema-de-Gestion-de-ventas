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
        Schema::table('products', function (Blueprint $table) {
            $table->string('tipo_afectacion_igv', 2)->default('10')->after('aplica_igv')->comment('Catálogo SUNAT 07: 10=Gravado, 20=Exonerado, 30=Inafecto');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('tipo_afectacion_igv');
        });
    }
};
