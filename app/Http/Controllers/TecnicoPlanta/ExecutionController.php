<?php

namespace App\Http\Controllers\TecnicoPlanta;

use App\Actions\TecnicoPlanta\ExecuteAndCloseServiceOrder;
use App\Http\Controllers\Controller;
use App\Models\Deficiency;
use App\Models\Product;
use App\Models\ServiceOrder;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExecutionController extends Controller
{
    /**
     * Consume un repuesto contra el inventario real de Almacén (§85, Fase 5).
     */
    public function consumeSpare(
        Request $request,
        Team $current_team,
        ServiceOrder $service_order,
        Deficiency $deficiency,
        ExecuteAndCloseServiceOrder $action
    ): RedirectResponse {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'cantidad' => ['required', 'integer', 'min:1', 'max:99999'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ]);

        $product = Product::findOrFail($validated['product_id']);

        $action->consumeSparePart(
            $service_order,
            $deficiency,
            $product,
            (int) $validated['cantidad'],
            $request->user(),
            $validated['observacion'] ?? null
        );

        return back()->with('success', sprintf('Repuesto %s consumido de Almacén correctamente.', $product->nombre));
    }

    /**
     * Avanza el estado de la orden en Planta hacia el cierre técnico y emisión de certificados.
     */
    public function advance(
        Request $request,
        Team $current_team,
        ServiceOrder $service_order,
        ExecuteAndCloseServiceOrder $action
    ): RedirectResponse {
        $validated = $request->validate([
            'target_state' => ['required', 'in:en_proceso,trabajo_terminado,pendiente_datos,datos_completos,listo_certificado,listo_entrega'],
            'ph_realizada' => ['nullable', 'boolean'],
            'ph_equipos' => ['sometimes', 'array'],
            'ph_equipos.*' => ['integer'],
            // Lo que faltaba para el certificado, llenado en el aviso del cierre.
            'series' => ['sometimes', 'array'],
            'series.*' => ['nullable', 'string', 'max:100'],
            'direccion' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $extra = [
                'ph_realizada' => (bool) ($validated['ph_realizada'] ?? false),
                ...(array_key_exists('ph_equipos', $validated) ? ['ph_equipos' => $validated['ph_equipos']] : []),
            ];

            // Terminar y certificar en un solo paso: el trabajo se marca como
            // terminado (con sus mismas validaciones) y enseguida se emiten
            // los certificados; si algo falla no queda a medias.
            DB::transaction(function () use ($action, $service_order, $validated, $request, $extra): void {
                foreach ($validated['series'] ?? [] as $equipoId => $serie) {
                    if (filled($serie)) {
                        $service_order->equipments()->whereKey((int) $equipoId)->first()?->update(['serie_fabricante' => trim($serie)]);
                    }
                }

                if (filled($validated['direccion'] ?? null)) {
                    $service_order->client->update(['direccion_fiscal' => trim($validated['direccion'])]);
                }

                // Con todo revisado y reparado, la orden puede seguir en
                // recibido_planta o autorizado: primero pasa a en_proceso.
                if ($validated['target_state'] === 'listo_certificado' && in_array($service_order->estado, ['recibido_planta', 'autorizado'], true)) {
                    $action->advanceState($service_order, 'en_proceso', $request->user());
                    $service_order->refresh();
                }

                if ($validated['target_state'] === 'listo_certificado' && $service_order->estado === 'en_proceso') {
                    $action->advanceState($service_order, 'trabajo_terminado', $request->user());
                    $service_order->refresh();
                }

                $action->advanceState($service_order, $validated['target_state'], $request->user(), $extra);
            });
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['target_state' => $exception->getMessage()]);
        }

        $msg = $validated['target_state'] === 'listo_certificado'
            ? '¡Trabajo de taller finalizado y Certificados emitidos automáticamente!'
            : sprintf('Estado actualizado a "%s".', $validated['target_state']);

        return back()->with('success', $msg);
    }
}
