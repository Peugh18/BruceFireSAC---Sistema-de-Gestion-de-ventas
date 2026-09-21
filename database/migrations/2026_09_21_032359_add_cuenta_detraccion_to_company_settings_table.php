<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuenta del Banco de la Nación para depósitos de detracción (SPOT).
     * Obligatoria en la representación impresa de comprobantes que tengan
     * detracción (Art. 8 RCP, leyenda "Operación sujeta al Sistema de Pago
     * de Obligaciones Tributarias" + cuenta de depósito).
     */
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('cuenta_detraccion')->nullable()->after('leyenda_pie');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('cuenta_detraccion');
        });
    }
};
