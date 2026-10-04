<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una orden de servicio se puede anular (el cliente se arrepiente) antes de
 * que el taller emita el certificado.
 */
return new class extends Migration
{
    private const ESTADOS = [
        'pendiente_recepcion', 'recibido_planta', 'en_revision', 'esperando_autorizacion',
        'autorizado', 'en_proceso', 'trabajo_terminado', 'pendiente_datos', 'datos_completos',
        'listo_certificado', 'listo_entrega', 'entregado', 'cerrado',
    ];

    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->enum('estado', [...self::ESTADOS, 'anulada'])->default('pendiente_recepcion')->change();
        });
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->enum('estado', self::ESTADOS)->default('pendiente_recepcion')->change();
        });
    }
};
