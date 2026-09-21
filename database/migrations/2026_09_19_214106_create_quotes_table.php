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
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();
            $table->foreignId('vendedor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('sede_id')->nullable()->constrained('sedes')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->date('fecha');
            $table->date('vigencia_hasta');
            $table->string('condicion_pago_propuesta')->nullable();
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('igv', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->text('observaciones')->nullable();
            $table->enum('estado', [
                'borrador', 'emitida', 'enviada', 'aceptada', 'rechazada', 'vencida', 'convertida', 'anulada',
            ])->default('borrador');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
