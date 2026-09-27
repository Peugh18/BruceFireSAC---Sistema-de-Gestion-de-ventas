<?php

namespace App\Http\Controllers\Vendedor;

use App\Http\Controllers\Controller;
use App\Models\Deficiency;
use App\Models\Team;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeficiencyController extends Controller
{
    public function index(Team $current_team, Request $request): Response
    {
        $estado = $request->string('estado')->toString();

        $deficiencies = Deficiency::query()
            ->with(['serviceOrder.client', 'authorization'])
            ->whereHas('serviceOrder', fn ($query) => $query->when(
                $request->user()->sedeRestringidaId(),
                fn ($query, $sedeId) => $query->where('sede_id', $sedeId)
            ))
            ->when($estado !== '' && $estado !== 'todas', fn ($query) => $query->where('estado', $estado))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Deficiency $deficiency) => [
                'id' => $deficiency->id,
                'service_order_id' => $deficiency->service_order_id,
                'orden' => $deficiency->serviceOrder->codigo,
                'cliente' => $deficiency->serviceOrder->client->razon_social,
                'componente' => $deficiency->componente,
                'condicion' => $deficiency->condicion,
                'estado' => $deficiency->estado,
                'requiere_autorizacion' => $deficiency->requiere_autorizacion,
                'authorization' => $deficiency->authorization ? [
                    'autorizado_por' => $deficiency->authorization->autorizado_por,
                    'canal' => $deficiency->authorization->canal,
                    'fecha' => $deficiency->authorization->fecha?->format('d/m/Y'),
                    'observacion' => $deficiency->authorization->observacion,
                ] : null,
            ]);

        return Inertia::render('vendedor/deficiencias/index', [
            'deficiencies' => $deficiencies,
            'filters' => ['estado' => $estado],
        ]);
    }
}
