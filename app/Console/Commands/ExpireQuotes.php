<?php

namespace App\Console\Commands;

use App\Models\Quote;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('quotes:expire')]
#[Description('Marca como vencidas las cotizaciones enviadas cuya vigencia ya pasó')]
class ExpireQuotes extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $count = Quote::where('estado', 'enviada')
            ->where('vigencia_hasta', '<', now()->toDateString())
            ->update(['estado' => 'vencida']);

        $this->info("{$count} cotización(es) marcadas como vencidas.");
    }
}
