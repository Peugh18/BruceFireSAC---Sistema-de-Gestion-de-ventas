<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase D (§33): un solo registro para las fotos, audios, archivos y firmas
 * de una orden. El archivo vive en el disco privado y se sirve con sesión.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained('service_orders')->cascadeOnDelete();
            $table->foreignId('equipment_id')->nullable()->constrained('equipment')->nullOnDelete();
            $table->foreignId('service_order_event_id')->nullable()->constrained('service_order_events')->nullOnDelete();
            $table->foreignId('deficiency_id')->nullable()->constrained('deficiencies')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('tipo', ['foto', 'audio', 'archivo', 'firma']);
            $table->string('etapa', 30);
            $table->string('path');
            $table->string('nombre_original')->nullable();
            $table->string('mime', 100);
            $table->unsignedInteger('tamano')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['service_order_id', 'etapa']);
        });

        Schema::table('service_order_events', function (Blueprint $table) {
            $table->foreignId('equipment_id')->nullable()->after('user_id')->constrained('equipment')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('service_order_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('equipment_id');
        });

        Schema::dropIfExists('evidencias');
    }
};
