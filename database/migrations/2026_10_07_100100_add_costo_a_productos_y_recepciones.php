<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Costo de compra: cada ítem de la recepción lleva su costo unitario (sin
 * IGV) y el producto guarda el costo promedio. Con eso el inventario se
 * valoriza al costo y no al precio de venta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reception_items', function (Blueprint $table) {
            $table->decimal('costo_unitario', 12, 4)->nullable()->after('cantidad_conforme');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('costo_promedio', 12, 4)->nullable()->after('precio_venta');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('costo_promedio');
        });

        Schema::table('reception_items', function (Blueprint $table) {
            $table->dropColumn('costo_unitario');
        });
    }
};
