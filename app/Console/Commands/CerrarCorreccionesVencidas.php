<?php

namespace App\Console\Commands;

use App\Actions\Sales\CorregirVenta;
use App\Models\Sale;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ventas:cerrar-correcciones {--dias=7 : Días que espera una corrección}')]
#[Description('Cierra como anulación las ventas en corrección que nadie rehízo')]
class CerrarCorreccionesVencidas extends Command
{
    /**
     * Una venta en corrección conserva sus certificados vigentes. Si no se
     * rehace en N días, nadie la va a rehacer: se anulan como en una anulación.
     */
    public function handle(CorregirVenta $corregir): void
    {
        $dias = max(1, (int) $this->option('dias'));

        $ventas = Sale::query()
            ->whereNotNull('correccion_desde')
            ->where('correccion_desde', '<', now()->subDays($dias))
            ->get();

        foreach ($ventas as $sale) {
            $corregir->cerrar($sale, "Venta {$sale->numero_interno} anulada: no se rehízo en {$dias} días.");
        }

        $this->info("{$ventas->count()} corrección(es) cerrada(s).");
    }
}
