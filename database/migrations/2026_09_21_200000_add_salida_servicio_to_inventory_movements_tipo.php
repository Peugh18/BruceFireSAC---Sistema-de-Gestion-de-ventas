<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Amplia el enum inventory_movements.tipo con 'salida_servicio' (§85, Fase 5):
     * el consumo de repuestos en una reparacion tecnica es un movimiento distinto
     * de una venta (salida_venta) y no debe mezclarse con ella en el Kardex.
     *
     * MySQL enforza el enum via CHECK/ENUM nativo y necesita el ALTER explicito.
     * SQLite no enforza el CHECK generado por Schema::enum() en runtime, asi que
     * no requiere ningun cambio de esquema ahi.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE inventory_movements MODIFY tipo ENUM('ingreso', 'salida_venta', 'salida_servicio', 'ajuste', 'traslado')");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("UPDATE inventory_movements SET tipo = 'ajuste' WHERE tipo = 'salida_servicio'");
            DB::statement("ALTER TABLE inventory_movements MODIFY tipo ENUM('ingreso', 'salida_venta', 'ajuste', 'traslado')");
        }
    }
};
