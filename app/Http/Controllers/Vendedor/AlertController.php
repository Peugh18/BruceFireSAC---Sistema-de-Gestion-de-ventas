<?php

namespace App\Http\Controllers\Vendedor;

use App\Actions\Cotizaciones\CreateQuote;
use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\Service;
use App\Models\Team;
use App\Services\Avisos\ExtintoresPorVencer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AlertController extends Controller
{
    public function offerRecharge(Team $current_team, Request $request, CreateQuote $createQuote): RedirectResponse
    {
        $data = $request->validate(['client_id' => ['required', 'integer', 'exists:clients,id'], 'equipment_ids' => ['required', 'array', 'min:1'], 'equipment_ids.*' => ['integer', 'distinct', 'exists:equipment,id']]);
        $equipments = Equipment::query()->where('client_id', $data['client_id'])->whereIn('id', $data['equipment_ids'])->get();
        if ($equipments->count() !== count($data['equipment_ids'])) {
            throw ValidationException::withMessages(['equipment_ids' => 'Todos los extintores deben pertenecer al cliente.']);
        }
        $service = Service::query()->where('activo', true)->where('nombre', 'like', '%recarga%')->first();
        if (! $service) {
            throw ValidationException::withMessages(['service' => 'Configura un servicio de recarga activo en el catálogo.']);
        }
        $quote = $createQuote->handle(['client_id' => $data['client_id'], 'sede_id' => $request->user()->sedeRestringidaId(), 'fecha' => today(), 'vigencia_hasta' => today()->addDays(15), 'condicion_pago_propuesta' => 'Contado', 'referencia' => 'Recarga de '.$equipments->pluck('numero_serie')->join(', ')], [['service_id' => $service->id, 'cantidad' => $equipments->count(), 'precio_unitario' => (float) $service->precio_venta]], $request->user()->id);
        $quote->equipments()->sync($equipments->modelKeys());

        return redirect()->route('vendedor.cotizaciones.index', ['current_team' => $current_team])->with('success', 'Cotización de recarga creada.');
    }

    public function index(Team $current_team, ExtintoresPorVencer $porVencer): Response
    {
        return Inertia::render('vendedor/alertas/index', [
            'alerts' => $porVencer->segmentos(today()),
        ]);
    }
}
