<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| P-006: el Gerente da por revisada la diferencia de un cierre de caja y deja
| su comentario; queda quién y cuándo.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_registers', function (Blueprint $table) {
            $table->foreignId('revisada_por')->nullable()->after('observacion')->constrained('users')->nullOnDelete();
            $table->timestamp('revisada_at')->nullable()->after('revisada_por');
            $table->string('revision_nota', 500)->nullable()->after('revisada_at');
        });
    }

    public function down(): void
    {
        Schema::table('cash_registers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revisada_por');
            $table->dropColumn(['revisada_at', 'revision_nota']);
        });
    }
};
