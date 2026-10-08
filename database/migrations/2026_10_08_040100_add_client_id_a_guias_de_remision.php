<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las guías de remisión solo guardaban el nombre y el RUC del destinatario
 * como snapshot: no se podía saber de qué cliente era cada guía. Se añade
 * clients.client_id con su clave foránea y se rellena desde la venta u orden
 * de servicio que sustenta cada guía. El snapshot de nombre/RUC se conserva
 * tal cual: es lo que va impreso y enviado a SUNAT, aunque el cliente cambie
 * después.
 *
 * Guías de traslado entre sedes (sin venta ni orden) quedan sin cliente: son
 * internas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispatch_guides', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('user_id')
                ->constrained('clients')->restrictOnDelete();
        });

        // Relleno desde el origen de cada guía (la venta o la orden).
        DB::table('dispatch_guides as guias')
            ->join('sales as ventas', 'ventas.id', '=', 'guias.sale_id')
            ->whereNull('guias.client_id')
            ->update(['guias.client_id' => DB::raw('ventas.client_id')]);

        DB::table('dispatch_guides as guias')
            ->join('service_orders as ordenes', 'ordenes.id', '=', 'guias.service_order_id')
            ->whereNull('guias.client_id')
            ->update(['guias.client_id' => DB::raw('ordenes.client_id')]);
    }

    public function down(): void
    {
        Schema::table('dispatch_guides', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropColumn('client_id');
        });
    }
};
