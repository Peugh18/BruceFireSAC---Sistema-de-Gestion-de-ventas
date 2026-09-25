<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Envío diferido a SUNAT: la factura/boleta queda "por enviar" unas horas
     * para poder corregirla sin nota de crédito. Los números de un comprobante
     * que nunca llegó a SUNAT se liberan y se reutilizan.
     */
    public function up(): void
    {
        Schema::table('electronic_documents', function (Blueprint $table) {
            $table->enum('sunat_estado', ['por_enviar', 'pendiente', 'aceptado', 'observado', 'rechazado', 'excepcion'])
                ->default('pendiente')
                ->change();
            $table->timestamp('enviar_desde')->nullable()->after('sunat_estado');
            $table->date('fecha_emision')->nullable()->after('correlativo');
        });

        Schema::table('document_series', function (Blueprint $table) {
            $table->json('correlativos_liberados')->nullable()->after('correlativo_actual');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_series', function (Blueprint $table) {
            $table->dropColumn('correlativos_liberados');
        });

        DB::table('electronic_documents')->where('sunat_estado', 'por_enviar')->update(['sunat_estado' => 'pendiente']);

        Schema::table('electronic_documents', function (Blueprint $table) {
            $table->dropColumn(['enviar_desde', 'fecha_emision']);
            $table->enum('sunat_estado', ['pendiente', 'aceptado', 'observado', 'rechazado', 'excepcion'])
                ->default('pendiente')
                ->change();
        });
    }
};
