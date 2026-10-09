<?php

namespace App\Actions\Billing;

use App\Models\DispatchGuide;
use App\Models\InventoryTransfer;
use App\Models\User;
use App\Services\Billing\GuiaRemisionPrefill;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateDispatchGuide
{
    public const SERIE = 'T001';

    public function __construct(protected ReserveNextCorrelativo $reserveNextCorrelativo) {}

    /**
     * Crea la guía en borrador con su número (serie T001) y sus ítems.
     *
     * @param  array<string, mixed>  $datos  campos de la guía, más `items`
     */
    public function handle(array $datos, User $user): DispatchGuide
    {
        return DB::transaction(function () use ($datos, $user): DispatchGuide {
            if (! empty($datos['inventory_transfer_id'])) {
                $traslado = InventoryTransfer::query()->whereKey($datos['inventory_transfer_id'])->lockForUpdate()->firstOrFail();
                abort_if($user->almacenRestringidoId() !== null && $user->almacenRestringidoId() !== $traslado->origen_sede_id, 403);
                if ($traslado->estado !== InventoryTransfer::PENDIENTE_GUIA || DispatchGuide::query()->where('inventory_transfer_id', $traslado->id)->whereIn('estado_sunat', [DispatchGuide::BORRADOR, DispatchGuide::ENVIADA, DispatchGuide::ACEPTADA])->exists()) {
                    throw ValidationException::withMessages(['traslado' => 'El traslado ya tiene una guía vigente o ya fue despachado.']);
                }
                $datos = [...$datos, ...app(GuiaRemisionPrefill::class)->desdeTraslado($traslado)];
                $datos['sale_id'] = null;
                $datos['service_order_id'] = null;
            }
            $items = $datos['items'];
            unset($datos['items']);

            $serie = (string) config('billing.series.guia_remision', self::SERIE);

            $guia = DispatchGuide::create([
                ...$datos,
                'user_id' => $user->id,
                'serie' => $serie,
                'correlativo' => $this->reserveNextCorrelativo->handle('guia_remision', $serie),
                'fecha_emision' => now(),
                'estado_sunat' => DispatchGuide::BORRADOR,
            ]);

            $guia->items()->createMany($items);

            return $guia->load('items');
        });
    }
}
