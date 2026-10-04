<?php

namespace App\Http\Controllers\Vendedor\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Filtro "Desde / Hasta" de los listados (Ventas, Comprobantes SUNAT).
 */
trait FiltraPorFechas
{
    /**
     * Rango del filtro; sin fechas, el día de hoy. Si vienen al revés se
     * ordenan, y nunca abarca más de un año.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    protected function rangoDeFechas(Request $request): array
    {
        $leer = function (string $campo) use ($request): ?CarbonInterface {
            $valor = $request->string($campo)->toString();

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) !== 1) {
                return null;
            }

            try {
                return Carbon::createFromFormat('Y-m-d', $valor)->startOfDay();
            } catch (Throwable) {
                return null;
            }
        };

        $desde = $leer('desde') ?? today();
        $hasta = $leer('hasta') ?? $desde->copy();

        if ($hasta->lt($desde)) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        if ($desde->diffInDays($hasta) > 366) {
            $desde = $hasta->copy()->subYear();
        }

        return [$desde, $hasta];
    }
}
