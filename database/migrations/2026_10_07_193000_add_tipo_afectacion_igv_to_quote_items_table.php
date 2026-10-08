<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las cotizaciones calculaban el IGV con una regla distinta a las ventas:
 * asumían que TODAS las líneas gravan (PrecioConIgv::totales) mientras las
 * ventas solo gravan la afectación '10' (PrecioConIgv::totalesConAfectacion).
 * Resultado: una cotización con líneas exoneradas mostraba un IGV inventado y,
 * al convertirla en venta, los totales no coincidían con lo cotizado.
 *
 * Esta migración guarda la afectación de cada línea de cotización para que la
 * cotización calcule sus totales con exactamente la misma regla que la venta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_items', function (Blueprint $table) {
            $table->string('tipo_afectacion_igv', 2)->default('10')->after('subtotal')
                ->comment('Catálogo SUNAT 07: 10=Gravado, 20=Exonerado, 30=Inafecto. Copiada del catálogo al cotizar.');
        });
    }

    public function down(): void
    {
        Schema::table('quote_items', function (Blueprint $table) {
            $table->dropColumn('tipo_afectacion_igv');
        });
    }
};
