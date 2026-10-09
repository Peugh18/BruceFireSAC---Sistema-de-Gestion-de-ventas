<?php

namespace Tests\Support;

use App\Actions\Billing\CreateDispatchGuide;
use App\Models\DispatchGuide;
use App\Models\InventoryTransfer;
use App\Models\User;
use App\Services\Billing\GuiaRemisionPrefill;
use Illuminate\Support\Facades\Storage;

class TransferGuide
{
    public static function accepted(InventoryTransfer $traslado, User $user): DispatchGuide
    {
        $guia = app(CreateDispatchGuide::class)->handle([
            ...app(GuiaRemisionPrefill::class)->desdeTraslado($traslado),
            'fecha_traslado' => today()->toDateString(), 'modalidad' => '01', 'peso_bruto' => 1,
            'transportista_ruc' => '20600000001', 'transportista_razon' => 'Transportista de prueba',
        ], $user);
        $path = "testing/cdr-traslado-{$guia->id}.zip";
        Storage::disk('local')->put($path, 'cdr fixture');
        $guia->update(['estado_sunat' => DispatchGuide::ACEPTADA, 'cdr_path' => $path]);

        return $guia;
    }
}
