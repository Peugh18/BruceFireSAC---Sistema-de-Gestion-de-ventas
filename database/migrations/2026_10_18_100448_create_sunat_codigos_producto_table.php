<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogo 25 de SUNAT (código de producto, UNSPSC v14_0801). Se llena con
     * `php artisan sunat:importar-codigos-producto` desde el archivo oficial.
     */
    public function up(): void
    {
        Schema::create('sunat_codigos_producto', function (Blueprint $table) {
            $table->char('codigo', 8)->primary();
            $table->string('descripcion', 255)->index();
            // Clase UNSPSC (6 dígitos + 00): agrupa productos parecidos y también se busca por ella.
            $table->string('clase', 160)->default('');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sunat_codigos_producto');
    }
};
