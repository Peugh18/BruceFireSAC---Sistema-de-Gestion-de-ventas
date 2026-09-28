<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Histórico de facturas y boletas 2025-2026 (sistema anterior) usado solo para
 * entrenar la predicción de recompra. No se mezcla con ventas, stock, SUNAT
 * ni correlativos: se une al cliente del sistema por su número de documento,
 * sin llave foránea, porque muchos clientes históricos aún no están registrados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ml_ventas_historicas', function (Blueprint $table): void {
            $table->id();
            $table->date('fecha');
            $table->char('tipo_doc', 1);
            $table->string('comprobante', 20);
            $table->string('documento_cliente', 11)->index();
            $table->string('nombre_cliente');
            $table->string('categoria', 30)->index();
            $table->string('producto_original');
            $table->decimal('cantidad', 10, 2);
            $table->decimal('total', 12, 2);
            $table->string('archivo_origen', 60);
            $table->index(['documento_cliente', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ml_ventas_historicas');
    }
};
