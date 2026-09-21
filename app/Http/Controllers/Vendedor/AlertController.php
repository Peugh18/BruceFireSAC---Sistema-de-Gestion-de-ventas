<?php

namespace App\Http\Controllers\Vendedor;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\Team;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AlertController extends Controller
{
    public function index(Team $current_team, Request $request): Response
    {
        $today = today();
        $rows = Equipment::query()
            ->with(['client', 'catalogItem'])
            ->where(fn ($query) => $query
                ->whereNotNull('proxima_fecha_atencion')
                ->orWhereNotNull('proxima_prueba_hidrostatica'))
            ->get()
            ->map(fn (Equipment $equipment) => $this->alertRow($equipment, $today))
            ->filter()
            ->groupBy(fn (array $row) => "{$row['client_id']}|{$row['fecha']}")
            ->map(fn ($group) => [
                ...$group->first(),
                'cantidad' => $group->count(),
            ])
            ->values();

        return Inertia::render('vendedor/alertas/index', [
            'alerts' => [
                'vencidas' => $rows->where('segmento', 'vencidas')->values(),
                'esta_semana' => $rows->where('segmento', 'esta_semana')->values(),
                'este_mes' => $rows->where('segmento', 'este_mes')->values(),
            ],
        ]);
    }

    protected function alertRow(Equipment $equipment, CarbonInterface $today): ?array
    {
        $fechaAtencion = $equipment->proxima_fecha_atencion;
        $fechaPrueba = $equipment->proxima_prueba_hidrostatica;
        $fecha = collect([$fechaAtencion, $fechaPrueba])
            ->filter()
            ->sortBy(fn (CarbonInterface $date) => abs($today->diffInDays($date, false)))
            ->first();

        if (! $fecha) {
            return null;
        }

        $dias = (int) $today->diffInDays($fecha, false);
        $segmento = match (true) {
            $dias < 0 => 'vencidas',
            $dias <= 7 => 'esta_semana',
            $dias <= 30 => 'este_mes',
            default => null,
        };

        if (! $segmento) {
            return null;
        }

        return [
            'client_id' => $equipment->client_id,
            'cliente' => $equipment->client->razon_social,
            'equipment_id' => $equipment->id,
            'equipo' => $equipment->catalogItem->nombre,
            'numero_serie' => $equipment->numero_serie,
            'fecha' => $fecha->toDateString(),
            'dias' => $dias,
            'segmento' => $segmento,
        ];
    }
}
