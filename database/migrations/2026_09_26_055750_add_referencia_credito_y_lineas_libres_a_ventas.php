<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - Referencia (placa o sede/oficina) impresa en comprobantes,
     *   cotizaciones, órdenes y certificados.
     * - Crédito con plazo y cuotas libres ("credito"); "credito_30" queda
     *   por compatibilidad con las ventas anteriores.
     * - Líneas de venta de producto sin serie y de servicio suelto.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->enum('condicion_pago', ['contado', 'credito', 'credito_30'])->change();
            $table->string('referencia', 150)->nullable()->after('destino');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->enum('tipo_linea', ['unidad_nueva', 'recarga_servicio', 'producto', 'servicio'])->change();
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->string('referencia', 150)->nullable()->after('vehicle_id');
        });

        Schema::table('service_orders', function (Blueprint $table) {
            $table->string('referencia', 150)->nullable()->after('vehicle_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropColumn('referencia');
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn('referencia');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->enum('tipo_linea', ['unidad_nueva', 'recarga_servicio'])->change();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('referencia');
            $table->enum('condicion_pago', ['contado', 'credito_30'])->change();
        });
    }
};
