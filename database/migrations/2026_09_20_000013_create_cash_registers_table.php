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
        Schema::create('cash_registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendedor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sede_id')->nullable()->constrained('sedes')->nullOnDelete();
            $table->dateTime('fecha_apertura');
            $table->decimal('monto_apertura', 10, 2);
            $table->dateTime('fecha_cierre')->nullable();
            $table->decimal('monto_contado_cierre', 10, 2)->nullable();
            $table->decimal('monto_esperado_calculado', 10, 2)->nullable();
            $table->decimal('diferencia', 10, 2)->nullable();
            $table->text('observacion')->nullable();
            $table->enum('estado', ['abierto', 'cerrado'])->default('abierto');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_registers');
    }
};
