<?php

namespace App\Services\Inventory;

use App\Models\InventorySequence;
use Illuminate\Support\Facades\DB;

class InventorySequenceGenerator
{
    /**
     * Genera el siguiente código de serie interno en formato BF-EQ-{secuencial_6_digitos}
     * garantizado único y correlativo mediante bloqueo transaccional de secuencia en BD.
     */
    public function nextEquipmentSerial(): string
    {
        return DB::transaction(function () {
            $seq = InventorySequence::query()
                ->where('nombre', 'equipment_serial')
                ->lockForUpdate()
                ->first();

            if (! $seq) {
                $seq = InventorySequence::create([
                    'nombre' => 'equipment_serial',
                    'prefijo' => 'BF-EQ',
                    'correlativo_actual' => 0,
                ]);
            }

            $seq->increment('correlativo_actual');
            $next = $seq->refresh()->correlativo_actual;

            return sprintf('%s-%06d', $seq->prefijo, $next);
        });
    }
}
