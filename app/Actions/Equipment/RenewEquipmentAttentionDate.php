<?php

namespace App\Actions\Equipment;

use App\Models\Equipment;
use Illuminate\Support\Collection;

class RenewEquipmentAttentionDate
{
    /** @param Collection<int, Equipment> $equipments */
    public function execute(Collection $equipments, bool $phRealizada = false): void
    {
        if ($equipments->isEmpty()) {
            return;
        }

        $payload = [
            'proxima_fecha_atencion' => now()->addYear()->toDateString(),
            'estado' => 'activo',
        ];

        if ($phRealizada) {
            $payload['proxima_prueba_hidrostatica'] = now()->addYears(5)->toDateString();
        }

        Equipment::whereKey($equipments->pluck('id'))->update($payload);
    }
}
