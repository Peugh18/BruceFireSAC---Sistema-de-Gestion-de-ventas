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
        Schema::create('document_series', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo_comprobante', ['factura', 'boleta', 'nota_credito', 'nota_debito', 'guia_remision']);
            $table->string('serie', 4);
            $table->unsignedBigInteger('correlativo_actual')->default(0);
            $table->foreignId('sede_id')->nullable()->constrained('sedes')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tipo_comprobante', 'serie']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_series');
    }
};
