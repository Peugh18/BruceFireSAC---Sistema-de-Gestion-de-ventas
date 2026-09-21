<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuentas bancarias de la empresa que se imprimen en los comprobantes
     * (sección "Cuentas Bancarias" del PDF). Pueden ser varias (BCP, BBVA,
     * etc.), por eso es tabla aparte y no columnas sueltas en
     * company_settings.
     */
    public function up(): void
    {
        Schema::create('company_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('banco');
            $table->string('titular');
            $table->string('numero_cuenta');
            $table->string('cci')->nullable();
            $table->string('moneda', 3)->default('PEN');
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_bank_accounts');
    }
};
