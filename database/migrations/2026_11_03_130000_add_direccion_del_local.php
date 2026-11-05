<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_sites', function (Blueprint $table) {
            // El cliente quiere sus comprobantes con la dirección de este local.
            $table->boolean('para_facturar')->default(false)->after('direccion');
        });
        foreach (['sales', 'quotes'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                // El comprobante sale con la dirección del local de la referencia, no la fiscal.
                $table->boolean('direccion_del_local')->default(false)->after('referencia');
            });
        }
    }

    public function down(): void
    {
        Schema::table('client_sites', fn (Blueprint $table) => $table->dropColumn('para_facturar'));
        foreach (['sales', 'quotes'] as $tabla) {
            Schema::table($tabla, fn (Blueprint $table) => $table->dropColumn('direccion_del_local'));
        }
    }
};
