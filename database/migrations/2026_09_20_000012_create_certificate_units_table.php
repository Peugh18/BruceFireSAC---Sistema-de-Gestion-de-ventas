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
        Schema::create('certificate_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certificate_id')->constrained('certificates')->cascadeOnDelete();
            // Sin FK: la tabla equipment la crea otro módulo en paralelo
            $table->unsignedBigInteger('equipment_id')->nullable();
            $table->string('numero_serie_snapshot');
            $table->date('fecha_ultima_ph')->nullable();
            $table->date('fecha_ultima_recarga')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificate_units');
    }
};
