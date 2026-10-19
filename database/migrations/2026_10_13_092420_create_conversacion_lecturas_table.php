<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hasta dónde leyó cada persona el chat de cada orden: así el Inicio marca
 * las órdenes con mensajes nuevos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversacion_lecturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_order_id')->constrained()->cascadeOnDelete();
            $table->timestamp('leido_hasta');
            $table->timestamps();

            $table->unique(['user_id', 'service_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversacion_lecturas');
    }
};
