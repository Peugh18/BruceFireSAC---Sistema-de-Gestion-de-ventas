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
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();
            $table->foreignId('certificate_type_id')->constrained('certificate_types')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->date('fecha_emision');
            $table->date('fecha_vigencia_hasta');
            $table->enum('estado', ['vigente', 'vencido', 'reemplazado', 'anulado'])->default('vigente');
            $table->string('qr_token')->unique();
            // Sin FK: la tabla sales la crea otro módulo en paralelo
            $table->unsignedBigInteger('sale_id')->nullable();
            $table->foreignId('service_order_id')->nullable()->constrained('service_orders')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
