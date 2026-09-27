<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cómo pagó el cliente una venta al contado (efectivo, Yape, transferencia…).
     * No va a SUNAT: sirve para registrar el cobro solo al confirmar y que la
     * caja cuadre.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('medio_pago', 20)->nullable()->after('condicion_pago');
            $table->string('numero_operacion', 60)->nullable()->after('medio_pago');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['medio_pago', 'numero_operacion']);
        });
    }
};
