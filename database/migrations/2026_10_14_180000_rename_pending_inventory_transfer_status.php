<?php

use App\Models\InventoryTransfer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE inventory_transfers DROP CHECK inventory_transfers_estado_valido');
        }

        DB::table('inventory_transfers')
            ->where('estado', 'pendiente_guia')
            ->update(['estado' => InventoryTransfer::PENDIENTE_DESPACHO]);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE inventory_transfers ADD CONSTRAINT inventory_transfers_estado_valido CHECK (estado IN ('pendiente_despacho', 'en_transito', 'recibido'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE inventory_transfers DROP CHECK inventory_transfers_estado_valido');
        }

        DB::table('inventory_transfers')
            ->where('estado', InventoryTransfer::PENDIENTE_DESPACHO)
            ->update(['estado' => 'pendiente_guia']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE inventory_transfers ADD CONSTRAINT inventory_transfers_estado_valido CHECK (estado IN ('pendiente_guia', 'en_transito', 'recibido'))");
        }
    }
};
