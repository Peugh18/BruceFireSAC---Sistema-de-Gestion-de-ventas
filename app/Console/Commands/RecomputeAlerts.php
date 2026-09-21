<?php

namespace App\Console\Commands;

use App\Models\Certificate;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('alerts:recompute')]
#[Description('Marca como vencidos los certificados vigentes cuya fecha de vigencia ya pasó')]
class RecomputeAlerts extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $count = Certificate::query()
            ->where('estado', 'vigente')
            ->where('fecha_vigencia_hasta', '<', today()->toDateString())
            ->update(['estado' => 'vencido']);

        $this->info("{$count} certificado(s) marcados como vencidos.");
    }
}
