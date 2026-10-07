<?php

namespace App\Console\Commands;

use App\Models\Quote;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('quotes:expire')]
#[Description('Marca como vencidas las cotizaciones vigentes cuya fecha ya pasó')]
class ExpireQuotes extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // X6: solo vencen los estados que Quote::TRANSITIONS deja pasar a
        // "vencida"; una aceptada espera su conversión a venta.
        $vencibles = array_keys(array_filter(Quote::TRANSITIONS, fn (array $destinos) => in_array('vencida', $destinos, true)));

        $count = Quote::whereIn('estado', $vencibles)
            ->where('vigencia_hasta', '<', now()->toDateString())
            ->update(['estado' => 'vencida']);

        $this->info("{$count} cotización(es) marcadas como vencidas.");
    }
}
