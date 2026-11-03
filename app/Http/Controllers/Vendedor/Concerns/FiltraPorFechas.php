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
     * Rango del filtro; sin fechas, el mes en curso hasta hoy, y con solo la
     * inicial, desde esa fecha hasta hoy. Si vienen al revés se ordenan, y
     * nunca abarca más de un año.
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

        $hasta = $leer('hasta');
        $desde = $leer('desde') ?? $hasta?->copy() ?? today()->startOfMonth();
        $hasta ??= today();

        if ($hasta->lt($desde)) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        if ($desde->diffInDays($hasta) > 366) {
            $desde = $hasta->copy()->subYear();
        }

        return [$desde, $hasta];
    }
}
