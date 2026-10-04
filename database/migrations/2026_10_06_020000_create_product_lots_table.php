<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lotes con fecha de vencimiento para EPP y consumibles (guantes, filtros,
 * mascarillas): el stock de un producto con lote se lleva por lote en cada
 * almacén y se vende primero lo que vence primero. Además, un producto puede
 * comprarse por caja (unidad_compra + factor_compra) y venderse por unidad
 * o par.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('controla_lote')->default(false)->after('serializado');
            $table->string('unidad_compra', 10)->nullable()->after('unidad_medida');
            $table->unsignedInteger('factor_compra')->default(1)->after('unidad_compra');
        });

        Schema::create('product_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('sede_id')->constrained('sedes')->cascadeOnDelete();
            $table->string('lote', 50);
            $table->date('fecha_vencimiento')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'sede_id', 'lote']);
            $table->index('fecha_vencimiento');
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreignId('product_lot_id')->nullable()->after('inventory_unit_id')->constrained('product_lots')->nullOnDelete();
        });

        Schema::table('reception_items', function (Blueprint $table) {
            $table->string('lote', 50)->nullable()->after('cantidad_conforme');
            $table->date('fecha_vencimiento')->nullable()->after('lote');
            $table->foreignId('product_lot_id')->nullable()->after('fecha_vencimiento')->constrained('product_lots')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reception_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_lot_id');
            $table->dropColumn(['lote', 'fecha_vencimiento']);
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_lot_id');
        });

        Schema::dropIfExists('product_lots');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['controla_lote', 'unidad_compra', 'factor_compra']);
        });
    }
};
