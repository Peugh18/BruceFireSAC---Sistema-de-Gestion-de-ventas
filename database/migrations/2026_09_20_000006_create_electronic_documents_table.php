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
        Schema::create('electronic_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->enum('tipo', ['factura', 'boleta', 'nota_credito', 'nota_debito']);
            $table->string('serie');
            $table->unsignedBigInteger('correlativo');
            $table->foreignId('cpe_afectado_id')->nullable()->constrained('electronic_documents')->nullOnDelete();
            $table->string('motivo_catalogo')->nullable();
            $table->string('xml_path')->nullable();
            $table->string('cdr_path')->nullable();
            $table->string('pdf_path')->nullable();
            $table->enum('sunat_estado', ['pendiente', 'aceptado', 'observado', 'rechazado', 'excepcion'])->default('pendiente');
            $table->string('sunat_codigo_respuesta')->nullable();
            $table->text('sunat_mensaje')->nullable();
            $table->timestamp('enviado_at')->nullable();
            $table->timestamps();

            $table->unique(['tipo', 'serie', 'correlativo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('electronic_documents');
    }
};
