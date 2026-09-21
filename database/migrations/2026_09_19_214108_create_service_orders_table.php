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
        Schema::create('service_orders', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('sede_id')->nullable()->constrained('sedes')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained('quotes')->nullOnDelete();
            // Sin FK todavía: la tabla "sales" se crea recién en la Fase 4.
            $table->unsignedBigInteger('sale_id')->nullable();
            $table->string('tipo_servicio');
            $table->date('fecha');
            $table->foreignId('tecnico_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('departamento_tecnico', ['planta', 'campo'])->nullable();
            $table->string('prioridad')->default('normal');
            $table->text('observaciones')->nullable();
            // Estado fino de 13 pasos (Documento Maestro §16.2). El indicador de
            // 4 puntos del mockup se deriva de este valor, no se guarda aparte.
            $table->enum('estado', [
                'pendiente_recepcion', 'recibido_planta', 'en_revision', 'esperando_autorizacion',
                'autorizado', 'en_proceso', 'trabajo_terminado', 'pendiente_datos', 'datos_completos',
                'listo_certificado', 'listo_entrega', 'entregado', 'cerrado',
            ])->default('pendiente_recepcion');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_orders');
    }
};
