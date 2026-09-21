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
        Schema::create('deficiencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained('service_orders')->cascadeOnDelete();
            $table->foreignId('equipment_id')->nullable()->constrained('equipment')->nullOnDelete();
            $table->string('componente');
            $table->string('condicion');
            $table->string('foto_path')->nullable();
            $table->text('nota')->nullable();
            $table->string('accion_recomendada')->nullable();
            $table->string('repuesto_sugerido')->nullable();
            $table->boolean('requiere_autorizacion')->default(false);
            $table->enum('estado', [
                'detectada',
                'esperando_autorizacion',
                'autorizada',
                'rechazada',
                'en_correccion',
                'resuelta',
            ])->default('detectada');
            $table->text('resolucion')->nullable();
            $table->foreignId('reported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deficiencies');
    }
};
