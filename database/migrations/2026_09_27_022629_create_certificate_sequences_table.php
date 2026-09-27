<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Correlativo por tipo de certificado (y por año, si el formato lo lleva).
     * Se incrementa con bloqueo: un número nunca se repite ni se reutiliza.
     */
    public function up(): void
    {
        Schema::create('certificate_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certificate_type_id')->constrained('certificate_types')->cascadeOnDelete();
            // 0 = serie continua sin año (por ejemplo LM-0006540).
            $table->unsignedSmallInteger('anio')->default(0);
            $table->unsignedBigInteger('ultimo_numero')->default(0);
            $table->timestamps();

            $table->unique(['certificate_type_id', 'anio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_sequences');
    }
};
