<?php

namespace App\Console\Commands;

use App\Actions\Sales\DescartarVentaSinComprobante;
use App\Models\Sale;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ventas:descartar-borradores {--dias=7 : Antigüedad mínima en días}')]
#[Description('Descarta las ventas en borrador olvidadas y devuelve sus unidades al stock')]
class DescartarBorradoresViejos extends Command
{
    /**
     * Un borrador reserva sus unidades como vendidas; si nadie lo confirma,
     * esas unidades quedan bloqueadas. Se descartan los que llevan más de N
     * días sin tocarse.
     */
    public function handle(DescartarVentaSinComprobante $descartar): void
    {
        $dias = max(1, (int) $this->option('dias'));

        $borradores = Sale::query()
            ->where('estado', 'borrador')
            ->where('updated_at', '<', now()->subDays($dias))
            ->get();

        foreach ($borradores as $sale) {
            $descartar->handle($sale, "por borrador sin confirmar más de {$dias} días");
        }

        $this->info("{$borradores->count()} borrador(es) descartado(s).");
    }
}
