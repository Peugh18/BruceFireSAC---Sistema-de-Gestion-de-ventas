<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Arqueo ciego (E-011): cada conteo del cajón queda guardado con su detalle
 * por billete y moneda. El primero no se cambia; si el Gerente autoriza un
 * reconteo, el anterior se marca como anulado y los dos quedan a la vista.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_register_counts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cash_register_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            // Lo declarado: la suma de las líneas cuando se contó por billetes y monedas.
            $table->decimal('monto', 10, 2);
            $table->foreignId('anulado_por_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('anulado_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cash_register_count_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cash_register_count_id')->constrained()->cascadeOnDelete();
            $table->decimal('denominacion', 6, 2);
            $table->unsignedInteger('cantidad');
            $table->unique(['cash_register_count_id', 'denominacion'], 'conteo_denominacion_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_register_count_lines');
        Schema::dropIfExists('cash_register_counts');
    }
};
