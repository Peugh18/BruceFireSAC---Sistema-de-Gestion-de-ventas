<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Las ventas de extintores con serie se grababan en el Kardex con +1. Las
 * salidas restan (traslados, ajustes y consumos ya lo hacían): se corrige
 * el signo de las ya grabadas para que el saldo cuadre.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('inventory_movements')
            ->where('tipo', 'salida_venta')
            ->whereNotNull('inventory_unit_id')
            ->where('cantidad', '>', 0)
            ->update(['cantidad' => DB::raw('-cantidad')]);
    }

    public function down(): void
    {
        DB::table('inventory_movements')
            ->where('tipo', 'salida_venta')
            ->whereNotNull('inventory_unit_id')
            ->where('cantidad', '<', 0)
            ->update(['cantidad' => DB::raw('-cantidad')]);
    }
};
