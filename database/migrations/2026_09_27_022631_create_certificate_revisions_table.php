<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historial de correcciones de un certificado ya emitido: quién, cuándo,
     * por qué (motivo obligatorio) y qué cambió (antes y después).
     */
    public function up(): void
    {
        Schema::create('certificate_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certificate_id')->constrained('certificates')->cascadeOnDelete();
            $table->unsignedInteger('numero_revision');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('motivo');
            $table->json('antes');
            $table->json('despues');
            $table->timestamps();

            $table->unique(['certificate_id', 'numero_revision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_revisions');
    }
};
