<?php

namespace App\Console\Commands;

use App\Services\SaludDelSistema;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sistema:verificar')]
#[Description('Revisa que los datos cuadren entre tablas, que haya respaldo reciente y que no haya comprobantes atrasados; el Gerente lo ve en su panel')]
class VerificarSaludDelSistema extends Command
{
    public function handle(SaludDelSistema $salud): int
    {
        $revision = $salud->revisar();

        if ($revision['problemas'] === []) {
            $this->info('Los datos cuadran: no se encontraron problemas.');
        }

        foreach ($revision['problemas'] as $problema) {
            $this->warn("{$problema['descripcion']}: {$problema['cantidad']}");
        }

        $revision['respaldo']['ok'] ? $this->info($revision['respaldo']['detalle']) : $this->warn($revision['respaldo']['detalle']);

        return self::SUCCESS;
    }
}
