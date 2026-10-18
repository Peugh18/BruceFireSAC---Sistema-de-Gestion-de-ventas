<?php

namespace App\Actions\Billing;

use App\Models\DispatchGuide;
use App\Models\User;
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
                throw ValidationException::withMessages([
                    'traslado' => 'Los traslados internos se registran en Almacén y no generan GRE.',
                ]);
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
