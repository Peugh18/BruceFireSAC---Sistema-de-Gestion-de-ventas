<?php

namespace App\Actions\Equipment;

use App\Models\Equipment;
use App\Services\Certificates\CertificateDateCalculator;
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
            $payload['fecha_ultima_ph'] = today()->toDateString();
            $payload['proxima_prueba_hidrostatica'] = CertificateDateCalculator::proximaPruebaHidrostatica(today())->toDateString();
        }

        Equipment::whereKey($equipments->pluck('id'))->update($payload);
    }
}
