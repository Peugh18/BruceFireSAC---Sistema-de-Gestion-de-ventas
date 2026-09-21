<?php

namespace App\Http\Controllers\TecnicoPlanta;

use App\Actions\Tecnico\ProcessChecklist;
use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\ServiceOrder;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChecklistController extends Controller
{
    /**
     * Muestra la pantalla del checklist técnico digital para un equipo de la orden (§19).
     */
    public function create(
        Team $current_team,
        ServiceOrder $service_order,
        Equipment $equipment
    ): Response {
        $service_order->load(['client:id,nombre_comercial,razon_social,numero_documento', 'sede:id,nombre']);

        // Historial previo de checklists para este equipo
        $previousChecklists = $equipment->serviceOrders()
            ->with(['checklists' => fn ($q) => $q->where('equipment_id', $equipment->id)->latest('id')])
            ->get()
            ->pluck('checklists')
            ->flatten()
            ->take(3);

        $esCO2 = str_contains(strtoupper((string) $equipment->tipo_agente), 'CO2');

        // Configuración de elementos sugeridos según tipo (§19.1)
        $elementos = array_map(function ($key, $label) use ($esCO2) {
            $defaultEstado = ($esCO2 && $key === 'manometro') ? 'no_aplica' : 'conforme';

            return [
                'clave' => $key,
                'nombre' => $label,
                'default_estado' => $defaultEstado,
                'aplica' => ! ($esCO2 && $key === 'manometro'),
            ];
        }, array_keys(ProcessChecklist::ELEMENTOS), array_values(ProcessChecklist::ELEMENTOS));

        return Inertia::render('tecnico-planta/checklist/create', [
            'order' => [
                'id' => $service_order->id,
                'codigo' => $service_order->codigo,
                'cliente' => $service_order->client->nombre_comercial ?: $service_order->client->razon_social,
                'tipo_servicio' => $service_order->tipo_servicio,
                'estado' => $service_order->estado,
            ],
            'equipment' => [
                'id' => $equipment->id,
                'numero_serie' => $equipment->numero_serie,
                'tipo_agente' => $equipment->tipo_agente,
                'capacidad' => $equipment->capacidad,
                'marca' => $equipment->marca,
                'serie_fabricante' => $equipment->serie_fabricante,
                'anio_fabricacion' => $equipment->anio_fabricacion,
                'ubicacion_actual' => $equipment->ubicacion_actual,
            ],
            'elementos' => $elementos,
            'previousChecklists' => $previousChecklists,
        ]);
    }

    /**
     * Guarda el checklist técnico digital y dispara deficiencias si las hay (§19, §20).
     */
    public function store(
        Request $request,
        Team $current_team,
        ServiceOrder $service_order,
        Equipment $equipment,
        ProcessChecklist $action
    ): RedirectResponse {
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.estado' => ['required', 'in:conforme,observado,no_aplica'],
            'items.*.condicion' => ['nullable', 'string', 'max:255'],
            'items.*.nota' => ['nullable', 'string', 'max:500'],
            'items.*.accion_recomendada' => ['nullable', 'string', 'max:255'],
            'items.*.repuesto_sugerido' => ['nullable', 'string', 'max:255'],
            'items.*.requiere_autorizacion' => ['nullable', 'boolean'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ]);

        $checklist = $action->execute(
            $service_order,
            $equipment,
            $request->user(),
            [
                'origen' => 'planta',
                'items' => $validated['items'],
                'observaciones' => $validated['observaciones'] ?? null,
            ]
        );

        $msg = $checklist->resultado_general === 'observado'
            ? 'Checklist registrado con observaciones. Se generaron las deficiencias correspondientes.'
            : 'Checklist registrado exitosamente: Todos los componentes conformes.';

        return redirect()
            ->route('tecnico-planta.recepciones.show', [
                'current_team' => $current_team,
                'service_order' => $service_order,
            ])
            ->with('success', $msg);
    }
}
