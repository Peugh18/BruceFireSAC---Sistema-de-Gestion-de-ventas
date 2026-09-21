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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('numero_interno')->unique();
            $table->foreignId('quote_id')->nullable()->constrained('quotes')->nullOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('sede_id')->nullable()->constrained('sedes')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('vendedor_id')->constrained('users')->cascadeOnDelete();
            $table->date('fecha');
            $table->enum('destino', ['local_cliente', 'vehiculo']);
            $table->enum('condicion_pago', ['contado', 'credito_30']);
            $table->enum('comprobante_tipo', ['factura', 'boleta']);
            $table->decimal('subtotal', 10, 2);
            $table->decimal('igv', 10, 2);
            $table->decimal('total', 10, 2);
            $table->enum('estado', ['borrador', 'confirmada', 'anulada'])->default('borrador');
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
