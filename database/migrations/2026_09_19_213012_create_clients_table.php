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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_interno')->unique();
            $table->enum('tipo_documento', ['dni', 'ruc']);
            $table->string('numero_documento');
            $table->string('razon_social');
            $table->string('nombre_comercial')->nullable();
            $table->string('telefono')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('email')->nullable();
            $table->string('direccion_fiscal')->nullable();
            $table->string('departamento')->nullable();
            $table->string('provincia')->nullable();
            $table->string('distrito')->nullable();
            $table->char('ubigeo', 6)->nullable();
            $table->string('estado_contribuyente')->nullable();
            $table->string('condicion_domicilio')->nullable();
            $table->timestamp('consultado_at')->nullable();
            $table->boolean('activo')->default(true);
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->unique(['tipo_documento', 'numero_documento']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
