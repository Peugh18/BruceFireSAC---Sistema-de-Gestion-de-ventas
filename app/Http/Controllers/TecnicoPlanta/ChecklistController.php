<?php

namespace App\Http\Controllers\TecnicoPlanta;

use App\Actions\Tecnico\ProcessChecklist;
use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\ServiceOrder;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ChecklistController extends Controller
{
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
            'items.*.foto' => ['nullable', 'image', 'max:15360'],
            'equipo_no_apto' => ['sometimes', 'boolean'],
            'observaciones' => ['required_if:equipo_no_apto,1,true', 'nullable', 'string', 'max:1000'],
        ]);

        $checklist = $action->execute(
            $service_order,
            $equipment,
            $request->user(),
            [
                'origen' => 'planta',
                'items' => $validated['items'],
                'observaciones' => $validated['observaciones'] ?? null,
                'equipo_no_apto' => (bool) ($validated['equipo_no_apto'] ?? false),
            ]
        );

        $msg = ! empty($validated['equipo_no_apto'])
            ? 'Extintor declarado no apto. Sus certificados de PH y operatividad quedaron anulados.'
            : ($checklist->resultado_general === 'observado'
                ? 'Checklist registrado con observaciones. Se generaron las deficiencias correspondientes.'
                : 'Checklist registrado exitosamente: Todos los componentes conformes.');

        // Vuelve a la pantalla de la orden: ella sola abre el paso que sigue.
        return redirect()
            ->route('tecnico-planta.ordenes.show', ['current_team' => $current_team, 'service_order' => $service_order])
            ->with('success', $msg);
    }
}
