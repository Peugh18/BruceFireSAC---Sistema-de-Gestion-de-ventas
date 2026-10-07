<?php

namespace App\Http\Controllers\Vendedor;

use App\Actions\Cotizaciones\CreateQuote;
use App\Enums\EquipmentType;
use App\Http\Controllers\Controller;
use App\Models\AlertContact;
use App\Models\Equipment;
use App\Models\Service;
use App\Models\Team;
use App\Services\Avisos\ExtintoresPorVencer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AlertController extends Controller
{
    /**
     * Cotización de recarga para los extintores elegidos (X9): cada uno lleva
     * el servicio de recarga de su agente y su capacidad. La cotización guarda
     * el extintor de la alerta de origen (X7: clientes recuperados).
     */
    public function offerRecharge(Team $current_team, Request $request, CreateQuote $createQuote): RedirectResponse
    {
        $data = $request->validate(['client_id' => ['required', 'integer', 'exists:clients,id'], 'equipment_ids' => ['required', 'array', 'min:1'], 'equipment_ids.*' => ['integer', 'distinct', 'exists:equipment,id']]);
        $equipments = Equipment::query()->with('product:id,agente,capacidad')->where('client_id', $data['client_id'])->whereIn('id', $data['equipment_ids'])->get();
        if ($equipments->count() !== count($data['equipment_ids'])) {
            throw ValidationException::withMessages(['equipment_ids' => 'Todos los extintores deben pertenecer al cliente.']);
        }

        $lineas = $this->lineasDeRecarga($equipments);

        $quote = $createQuote->handle([
            'client_id' => $data['client_id'],
            'sede_id' => $request->user()->sedeRestringidaId(),
            'fecha' => today(),
            'vigencia_hasta' => today()->addDays(15),
            'condicion_pago_propuesta' => 'Contado',
            'referencia' => 'Recarga de '.$equipments->pluck('numero_serie')->join(', '),
            'origen_alerta_equipment_id' => $equipments->first()?->id,
        ], $lineas, $request->user()->id);
        $quote->equipments()->sync($equipments->modelKeys());

        return redirect()->route('vendedor.cotizaciones.index', ['current_team' => $current_team])->with('success', 'Cotización de recarga creada.');
    }

    /**
     * X8: deja constancia de que ya se contactó al cliente por la alerta.
     */
    public function contactado(Team $current_team, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'equipment_id' => ['nullable', 'integer', 'exists:equipment,id'],
            'nota' => ['nullable', 'string', 'max:255'],
        ]);

        // El cliente se comparte entre sedes, pero el equipo debe ser visible
        // para el usuario y del mismo cliente.
        if (! empty($data['equipment_id'])) {
            abort_unless(Equipment::query()->visiblePara($request->user())->whereKey($data['equipment_id'])->where('client_id', $data['client_id'])->exists(), 404);
        }

        AlertContact::create([...$data, 'user_id' => $request->user()->id, 'fecha' => today()]);

        return back()->with('success', 'Contacto registrado: no se volverá a sugerir por 30 días.');
    }

    public function index(Team $current_team, Request $request, ExtintoresPorVencer $porVencer): Response
    {
        return Inertia::render('vendedor/alertas/index', [
            'alerts' => $porVencer->segmentos(today(), user: $request->user()),
            'empresas' => $porVencer->porEmpresa(today(), user: $request->user()),
            'hoy' => today()->toDateString(),
        ]);
    }

    /**
     * Una línea por servicio de recarga, con tantas unidades como extintores
     * de ese agente y capacidad.
     *
     * @param  Collection<int, Equipment>  $equipments
     * @return list<array{service_id: int, cantidad: int, precio_unitario: float}>
     */
    protected function lineasDeRecarga(Collection $equipments): array
    {
        $servicios = Service::query()->where('activo', true)->whereNotNull('agente')->whereNotNull('capacidad')->get();
        $lineas = [];
        $sinServicio = [];

        foreach ($equipments as $equipment) {
            $agente = EquipmentType::fromDescription($equipment->tipo_agente ?: $equipment->product->agente);
            $capacidad = Service::normalizarCapacidad($equipment->capacidad ?: $equipment->product->capacidad);
            $service = $servicios->first(fn (Service $service) => $service->agente === $agente->value
                && Service::normalizarCapacidad($service->capacidad) === $capacidad);

            if ($agente === EquipmentType::Otro || $capacidad === null || ! $service) {
                $sinServicio[] = trim("{$equipment->numero_serie} (".($agente === EquipmentType::Otro ? 'agente sin registrar' : $agente->etiqueta()).' '.($capacidad === null ? 'capacidad sin registrar' : (string) ($equipment->capacidad ?: $equipment->product->capacidad)).')');

                continue;
            }

            $lineas[$service->id] ??= ['service_id' => $service->id, 'cantidad' => 0, 'precio_unitario' => (float) $service->precio_venta];
            $lineas[$service->id]['cantidad']++;
        }

        if ($sinServicio !== []) {
            throw ValidationException::withMessages([
                'service' => 'No hay un servicio de recarga activo para: '.implode(', ', $sinServicio).'. Pide al Gerente que registre en Servicios el agente y la capacidad de la recarga.',
            ]);
        }

        return array_values($lineas);
    }
}
