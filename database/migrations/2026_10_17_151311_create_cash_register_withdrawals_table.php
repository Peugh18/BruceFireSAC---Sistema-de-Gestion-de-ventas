<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dinero en efectivo que sale de la caja durante el turno (gastos, pagos
     * menores) con su motivo; se resta del efectivo esperado al cerrar.
     */
    public function up(): void
    {
        Schema::create('cash_register_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_register_id')->constrained('cash_registers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->decimal('monto', 12, 2);
            $table->string('motivo', 255);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_register_withdrawals');
    }
};
