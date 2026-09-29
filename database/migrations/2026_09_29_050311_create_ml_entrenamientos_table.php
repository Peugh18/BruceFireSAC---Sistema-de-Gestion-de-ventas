<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de entrenamientos del modelo de recompra: qué modelo quedó y qué
 * tan bien acertó en la prueba, para seguir su precisión mes a mes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ml_entrenamientos', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('entrenado_at');
            $table->string('modelo', 30);
            $table->decimal('roc_auc', 5, 4);
            $table->decimal('accuracy', 5, 4);
            $table->decimal('precision', 5, 4);
            $table->decimal('recall', 5, 4);
            $table->unsignedSmallInteger('top_100_volvieron')->nullable();
            $table->unsignedInteger('muestras');
            $table->unsignedInteger('clientes')->nullable();
            $table->json('cortes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ml_entrenamientos');
    }
};
