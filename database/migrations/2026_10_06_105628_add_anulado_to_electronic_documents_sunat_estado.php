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
        Schema::table('electronic_documents', function (Blueprint $table) {
            $table->enum('sunat_estado', ['por_enviar', 'pendiente', 'aceptado', 'observado', 'rechazado', 'excepcion', 'anulado'])
                ->default('pendiente')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('electronic_documents', function (Blueprint $table) {
            $table->enum('sunat_estado', ['por_enviar', 'pendiente', 'aceptado', 'observado', 'rechazado', 'excepcion'])
                ->default('pendiente')
                ->change();
        });
    }
};
