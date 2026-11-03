<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La ficha técnica del producto guarda los datos que pide su certificado
 * (los llena Almacén una vez). La venta anota qué certificados esperan una
 * ficha incompleta para emitirlos solos cuando se complete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('ficha_tecnica')->nullable();
        });
        Schema::table('sales', function (Blueprint $table) {
            $table->json('certificados_en_espera')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('certificados_en_espera');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('ficha_tecnica');
        });
    }
};
