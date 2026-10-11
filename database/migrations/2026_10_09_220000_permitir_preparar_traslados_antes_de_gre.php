<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE inventory_transfers DROP CHECK inventory_transfers_estado_valido, ADD CONSTRAINT inventory_transfers_estado_valido CHECK (estado IN ('pendiente_guia', 'en_transito', 'recibido'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE inventory_transfers DROP CHECK inventory_transfers_estado_valido, ADD CONSTRAINT inventory_transfers_estado_valido CHECK (estado IN ('en_transito', 'recibido'))");
        }
    }
};
