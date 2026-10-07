<?php

namespace App\Http\Controllers\Vendedor;

use App\Http\Controllers\Controller;
use App\Models\Deficiency;
use App\Models\Quote;
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
                    'importe' => $deficiency->authorization->importe !== null ? (float) $deficiency->authorization->importe : null,
                ] : null,
                'orden_cobrada' => $deficiency->serviceOrder->sale_id !== null,
                // V6: cotizaciones vigentes del cliente para elegir la del adicional.
                'cotizaciones' => $deficiency->estado === 'esperando_autorizacion'
                    ? Quote::query()
                        ->where('client_id', $deficiency->serviceOrder->client_id)
                        ->where(fn ($query) => $query->whereNull('sede_id')->orWhere('sede_id', $deficiency->serviceOrder->sede_id))
                        ->whereIn('estado', ['borrador', 'emitida', 'enviada', 'aceptada'])
                        ->latest('id')
                        ->limit(20)
                        ->get(['id', 'numero', 'total'])
                        ->map(fn (Quote $quote) => ['id' => $quote->id, 'numero' => $quote->numero, 'total' => (float) $quote->total])
                    : [],
            ]);

        return Inertia::render('vendedor/deficiencias/index', [
            'deficiencies' => $deficiencies,
            'filters' => ['estado' => $estado],
        ]);
    }
}
