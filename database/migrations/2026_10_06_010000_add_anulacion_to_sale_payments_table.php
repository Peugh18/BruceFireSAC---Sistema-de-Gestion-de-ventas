<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un cobro anulado ya no se borra: queda tachado con quién lo anuló y por
 * qué, y deja de contar en saldos y en la caja. También se guarda quién
 * registró cada cobro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_payments', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('numero_operacion')->constrained('users')->nullOnDelete();
            $table->softDeletes()->after('updated_at');
            $table->foreignId('anulado_por')->nullable()->after('deleted_at')->constrained('users')->nullOnDelete();
            $table->string('anulado_motivo', 500)->nullable()->after('anulado_por');
        });
    }

    public function down(): void
    {
        Schema::table('sale_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('anulado_por');
            $table->dropColumn('anulado_motivo');
            $table->dropSoftDeletes();
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
