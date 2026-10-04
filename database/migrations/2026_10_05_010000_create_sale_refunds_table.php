<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Devoluciones de dinero al cliente (venta anulada o editada a un monto
 * menor). Se registran aparte de los cobros, el día en que se devuelve, así
 * la caja de ese turno descuenta el efectivo que sale y los cobros quedan
 * como se hicieron.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->restrictOnDelete();
            $table->string('forma_pago', 30);
            $table->decimal('monto', 12, 2);
            $table->string('motivo', 255);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('fecha');
            $table->timestamps();

            $table->index(['forma_pago', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_refunds');
    }
};
