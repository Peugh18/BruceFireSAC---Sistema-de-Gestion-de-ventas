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
        Schema::create('client_retention_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')
                ->unique()
                ->constrained('clients')
                ->cascadeOnDelete();
            $table->decimal('probabilidad', 5, 4);
            $table->string('categoria', 20); // alta, media, baja
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_retention_scores');
    }
};
