<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Se retira la IA de recompra: sus puntuaciones y el registro de sus
 * entrenamientos. El historial del sistema anterior (ml_*_historicos) se
 * queda: lo usa la lista de clientes antiguos por registrar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('client_retention_scores');
        Schema::dropIfExists('ml_entrenamientos');
    }

    public function down(): void
    {
        Schema::create('client_retention_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->unique()->constrained('clients')->cascadeOnDelete();
            $table->decimal('probabilidad', 5, 4);
            $table->string('categoria', 20);
            $table->unsignedInteger('recencia_dias');
            $table->unsignedInteger('frecuencia_compras');
            $table->decimal('monto_total', 12, 2);
            $table->decimal('ticket_promedio', 12, 2);
            $table->unsignedInteger('antiguedad_dias');
            $table->unsignedInteger('diversidad_productos');
            $table->boolean('compro_recarga')->default(false);
            $table->json('factores_json')->nullable();
            $table->timestamp('scored_at');
            $table->timestamps();
            $table->index('probabilidad');
            $table->index('categoria');
        });

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
};
