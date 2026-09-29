<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tercera forma normal del histórico de entrenamiento: en la tabla plana el
 * nombre dependía del documento del cliente, la fecha y el cliente del
 * comprobante, y la categoría del producto (no de la línea). Se separa en
 * cliente, producto, comprobante y línea, cada dato en su tabla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ml_clientes_historicos', function (Blueprint $table): void {
            $table->string('documento', 11)->primary();
            $table->string('nombre');
        });

        Schema::create('ml_productos_historicos', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre')->unique();
            $table->string('categoria', 30)->index();
        });

        Schema::create('ml_comprobantes_historicos', function (Blueprint $table): void {
            $table->string('comprobante', 20)->primary();
            $table->char('tipo_doc', 1);
            $table->date('fecha');
            $table->string('documento_cliente', 11);
            $table->string('archivo_origen', 60);
            $table->foreign('documento_cliente')->references('documento')->on('ml_clientes_historicos')->cascadeOnDelete();
            $table->index(['documento_cliente', 'fecha']);
        });

        Schema::create('ml_lineas_historicas', function (Blueprint $table): void {
            $table->id();
            $table->string('comprobante', 20);
            $table->foreignId('ml_producto_id')->constrained('ml_productos_historicos')->restrictOnDelete();
            $table->decimal('cantidad', 10, 2);
            $table->decimal('total', 12, 2);
            $table->foreign('comprobante')->references('comprobante')->on('ml_comprobantes_historicos')->cascadeOnDelete();
        });

        // Datos existentes: el nombre más reciente de cada cliente.
        DB::statement(<<<'SQL'
            INSERT INTO ml_clientes_historicos (documento, nombre)
            SELECT v.documento_cliente, MAX(v.nombre_cliente)
            FROM ml_ventas_historicas v
            JOIN (SELECT documento_cliente, MAX(fecha) AS fecha FROM ml_ventas_historicas GROUP BY documento_cliente) u
              ON u.documento_cliente = v.documento_cliente AND u.fecha = v.fecha
            GROUP BY v.documento_cliente
        SQL);
        DB::statement('INSERT INTO ml_productos_historicos (nombre, categoria) SELECT producto_original, MIN(categoria) FROM ml_ventas_historicas GROUP BY producto_original');
        DB::statement('INSERT INTO ml_comprobantes_historicos (comprobante, tipo_doc, fecha, documento_cliente, archivo_origen) SELECT comprobante, MIN(tipo_doc), MIN(fecha), MIN(documento_cliente), MIN(archivo_origen) FROM ml_ventas_historicas GROUP BY comprobante');
        DB::statement('INSERT INTO ml_lineas_historicas (comprobante, ml_producto_id, cantidad, total) SELECT v.comprobante, p.id, v.cantidad, v.total FROM ml_ventas_historicas v JOIN ml_productos_historicos p ON p.nombre = v.producto_original ORDER BY v.id');

        Schema::drop('ml_ventas_historicas');
    }

    public function down(): void
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

        DB::statement(<<<'SQL'
            INSERT INTO ml_ventas_historicas (fecha, tipo_doc, comprobante, documento_cliente, nombre_cliente, categoria, producto_original, cantidad, total, archivo_origen)
            SELECT c.fecha, c.tipo_doc, c.comprobante, c.documento_cliente, k.nombre, p.categoria, p.nombre, l.cantidad, l.total, c.archivo_origen
            FROM ml_lineas_historicas l
            JOIN ml_comprobantes_historicos c ON c.comprobante = l.comprobante
            JOIN ml_clientes_historicos k ON k.documento = c.documento_cliente
            JOIN ml_productos_historicos p ON p.id = l.ml_producto_id
            ORDER BY l.id
        SQL);

        Schema::drop('ml_lineas_historicas');
        Schema::drop('ml_comprobantes_historicos');
        Schema::drop('ml_productos_historicos');
        Schema::drop('ml_clientes_historicos');
    }
};
