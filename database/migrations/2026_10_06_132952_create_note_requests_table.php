<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notas de crédito y débito que pide un vendedor: esperan la aprobación del
 * Gerente antes de tomar número y enviarse a SUNAT (V2/S17).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('electronic_document_id')->constrained('electronic_documents')->restrictOnDelete();
            $table->enum('tipo', ['nota_credito', 'nota_debito']);
            $table->string('motivo_catalogo', 2);
            $table->text('detalle');
            $table->decimal('importe', 12, 2);
            $table->enum('estado', ['por_aprobar', 'aprobada', 'rechazada'])->default('por_aprobar');
            $table->foreignId('solicitado_por')->constrained('users')->restrictOnDelete();
            $table->foreignId('revisado_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('revisado_at')->nullable();
            $table->text('motivo_rechazo')->nullable();
            $table->foreignId('nota_id')->nullable()->constrained('electronic_documents')->nullOnDelete();
            $table->timestamps();

            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_requests');
    }
};
