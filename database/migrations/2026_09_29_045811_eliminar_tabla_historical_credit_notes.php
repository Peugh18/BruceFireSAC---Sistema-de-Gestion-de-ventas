<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La tabla de notas de crédito históricas era del import viejo que metía el
 * histórico dentro de las ventas. El histórico ahora vive aparte, solo para
 * la predicción (sin notas de crédito), y esta tabla quedó vacía y sin uso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('historical_credit_notes');
    }

    public function down(): void
    {
        Schema::create('historical_credit_notes', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->string('serie');
            $table->string('nro_documento');
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('cliente_nombre_original');
            $table->string('producto_original');
            $table->string('producto_normalizado');
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->decimal('cantidad', 10, 2);
            $table->decimal('precio_unitario', 10, 2);
            $table->decimal('total', 10, 2);
            $table->string('archivo_origen');
            $table->timestamps();

            $table->index('fecha');
            $table->index(['serie', 'nro_documento']);
        });
    }
};
