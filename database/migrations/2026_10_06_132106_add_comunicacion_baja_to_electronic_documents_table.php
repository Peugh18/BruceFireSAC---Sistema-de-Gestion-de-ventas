<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Comunicación de baja (S7): el comprobante queda "baja_pendiente" con el
 * ticket de SUNAT hasta que se acepta (anulado) o se rechaza (vuelve a su
 * estado anterior).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('electronic_documents', function (Blueprint $table) {
            $table->enum('sunat_estado', ['por_enviar', 'pendiente', 'aceptado', 'observado', 'rechazado', 'excepcion', 'baja_pendiente', 'anulado'])
                ->default('pendiente')
                ->change();
            $table->string('baja_nombre')->nullable()->after('enviado_at');
            $table->string('baja_ticket')->nullable()->after('baja_nombre');
            $table->string('baja_motivo', 100)->nullable()->after('baja_ticket');
            $table->text('baja_mensaje')->nullable()->after('baja_motivo');
            $table->string('baja_estado_previo')->nullable()->after('baja_mensaje');
        });
    }

    public function down(): void
    {
        // Una baja a medio camino vuelve a su estado anterior para caber en el enum.
        DB::table('electronic_documents')
            ->where('sunat_estado', 'baja_pendiente')
            ->update(['sunat_estado' => DB::raw("COALESCE(baja_estado_previo, 'aceptado')")]);

        Schema::table('electronic_documents', function (Blueprint $table) {
            $table->dropColumn(['baja_nombre', 'baja_ticket', 'baja_motivo', 'baja_mensaje', 'baja_estado_previo']);
            $table->enum('sunat_estado', ['por_enviar', 'pendiente', 'aceptado', 'observado', 'rechazado', 'excepcion', 'anulado'])
                ->default('pendiente')
                ->change();
        });
    }
};
