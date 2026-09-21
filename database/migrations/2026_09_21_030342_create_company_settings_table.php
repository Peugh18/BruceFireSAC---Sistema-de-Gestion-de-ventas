<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fila única (singleton) con los datos de la empresa que se imprimen en
     * comprobantes y certificados: razón social, dirección fiscal, logo y
     * leyenda de pie de página. Editable desde Gerente sin tocar .env.
     */
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('razon_social');
            $table->string('nombre_comercial')->nullable();
            $table->string('ruc', 11);
            $table->string('direccion')->nullable();
            $table->string('ubigeo', 6)->nullable();
            $table->string('departamento')->nullable();
            $table->string('provincia')->nullable();
            $table->string('distrito')->nullable();
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();
            $table->string('logo_path')->nullable();
            $table->text('leyenda_pie')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
