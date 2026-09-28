<?php

namespace App\Actions\Equipment;

use App\Models\Equipment;
use Illuminate\Support\Collection;

class RenewEquipmentAttentionDate
{
    /** @param Collection<int, Equipment> $equipments */
    public function execute(Collection $equipments): void
    {
        Equipment::whereKey($equipments->pluck('id'))->update(['proxima_fecha_atencion' => now()->addYear()->toDateString()]);
    }
}
