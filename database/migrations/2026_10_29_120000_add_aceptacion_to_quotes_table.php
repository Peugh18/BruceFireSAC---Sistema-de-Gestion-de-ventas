<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Una cotización solo pasa a venta u orden con la aceptación del cliente
| registrada: quién, por qué canal y cuándo. La orden de compra es opcional
| (campo 54 del Anexo I, R.S. 114-2019): si existe, sale en la factura.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('aceptada_por', 120)->nullable()->after('estado');
            $table->string('aceptada_canal', 20)->nullable()->after('aceptada_por');
            $table->timestamp('aceptada_at')->nullable()->after('aceptada_canal');
            $table->string('orden_compra', 40)->nullable()->after('aceptada_at');
            $table->string('orden_compra_path')->nullable()->after('orden_compra');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn(['aceptada_por', 'aceptada_canal', 'aceptada_at', 'orden_compra', 'orden_compra_path']);
        });
    }
};
