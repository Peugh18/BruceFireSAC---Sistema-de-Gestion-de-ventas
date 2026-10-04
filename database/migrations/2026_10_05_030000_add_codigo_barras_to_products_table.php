<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Código de barras del fabricante (EAN/UPC): los EPP y repuestos ya lo traen
 * impreso, igual para todas las unidades del mismo modelo y talla. Con él se
 * escanea al recibir, cotizar y vender. Único: un código es un producto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('codigo_barras', 64)->nullable()->unique()->after('codigo');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['codigo_barras']);
            $table->dropColumn('codigo_barras');
        });
    }
};
