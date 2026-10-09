<?php

namespace App\Http\Controllers\Vendedor;

use App\Actions\Cotizaciones\CotizarDeficiencia;
use App\Actions\Tecnico\GuardarEvidencia;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Vendedor\Concerns\AcotaPorSede;
use App\Models\Deficiency;
use App\Models\Evidencia;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\ServiceOrder;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DeficiencyController extends Controller
{
    use AcotaPorSede;

    public function store(Team $current_team, ServiceOrder $service_order, Request $request, GuardarEvidencia $guardarEvidencia): RedirectResponse
    {
        $this->asegurarSede($service_order->sede_id);
        $datos = $request->validate([
            'equipment_id' => ['nullable', 'integer', 'exists:equipment,id'],
            'componente' => ['required', 'string', 'max:150'], 'condicion' => ['required', 'string', 'max:255'],
            'nota' => ['nullable', 'string', 'max:500'], 'accion_recomendada' => ['nullable', 'string', 'max:255'],
            'repuesto_sugerido' => ['nullable', 'string', 'max:255'], 'requiere_autorizacion' => ['nullable', 'boolean'],
            'foto' => ['required', 'image', 'max:15360'],
        ]);
        DB::transaction(function () use ($service_order, $request, $datos, $guardarEvidencia): void {
            $orden = ServiceOrder::query()->lockForUpdate()->findOrFail($service_order->id);
            if (in_array($orden->estado, ServiceOrder::ESTADOS_CERRADOS_AL_TALLER, true)) {
                throw ValidationException::withMessages(['componente' => 'Esta orden ya terminó: no se agregan hallazgos.']);
            }
            $equipmentId = $datos['equipment_id'] ?? null;
            if ($equipmentId && ! $orden->equipments()->whereKey($equipmentId)->exists()) {
                throw ValidationException::withMessages(['equipment_id' => 'El equipo debe pertenecer a esta orden.']);
            }
            $deficiency = $orden->deficiencies()->create([
                'equipment_id' => $equipmentId, 'componente' => $datos['componente'], 'condicion' => $datos['condicion'], 'nota' => $datos['nota'] ?? null, 'accion_recomendada' => $datos['accion_recomendada'] ?? null, 'repuesto_sugerido' => $datos['repuesto_sugerido'] ?? null, 'requiere_autorizacion' => $datos['requiere_autorizacion'] ?? false, 'estado' => 'detectada', 'reported_by_user_id' => $request->user()->id,
            ]);
            $evidencia = $guardarEvidencia->archivo($orden, $request->file('foto'), 'deficiencia', $request->user(), $equipmentId, null, $deficiency);
            $deficiency->update(['foto_path' => $evidencia->path]);
            $orden->events()->create(['tipo' => 'deficiencia_detectada', 'user_id' => $request->user()->id, 'payload' => [
                'deficiency_id' => $deficiency->id, 'origen' => 'mostrador', 'componente' => $deficiency->componente,
                'mensaje' => 'Hallazgo registrado por vendedor; diagnóstico pendiente del técnico.',
            ]]);
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Hallazgo registrado para revisión técnica.']);

        return back();
    }

    public function cotizar(Team $current_team, Deficiency $deficiency, Request $request, CotizarDeficiencia $action): RedirectResponse
    {
        $datos = $request->validate([
            'quote_id' => ['nullable', 'integer', 'exists:quotes,id'],
            'product_id' => ['nullable', 'required_without:service_id', 'integer', 'exists:products,id'],
            'service_id' => ['nullable', 'required_without:product_id', 'integer', 'exists:services,id'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'precio_unitario' => ['nullable', 'numeric', 'gt:0'],
        ]);
        abort_if(! empty($datos['product_id']) && ! empty($datos['service_id']), 422);
        $action->handle($deficiency, $datos, $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Adicional cotizado. Confirma con el cliente antes de autorizar.']);

        return back();
    }

    public function index(Team $current_team, Request $request): Response
    {
        $estado = $request->string('estado')->toString();

        $deficiencies = Deficiency::query()
            ->with(['serviceOrder.client', 'authorization', 'equipment'])
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
                'nota' => $deficiency->nota,
                'accion_recomendada' => $deficiency->accion_recomendada,
                'repuesto_sugerido' => $deficiency->repuesto_sugerido,
                'equipo' => $deficiency->equipment?->numero_serie,
                // ponytail: una consulta por fila; la página trae 15 como máximo.
                'foto_url' => ($evidenciaId = $deficiency->foto_path ? Evidencia::query()->where('path', $deficiency->foto_path)->value('id') : null)
                    ? route('evidencias.show', [$current_team, $evidenciaId])
                    : null,
                'estado' => $deficiency->estado,
                'pendiente_autorizacion' => $deficiency->estado === 'resuelta' && $deficiency->requiere_autorizacion && ! $deficiency->authorization,
                'requiere_autorizacion' => $deficiency->requiere_autorizacion,
                'authorization' => $deficiency->authorization ? [
                    'autorizado_por' => $deficiency->authorization->autorizado_por,
                    'canal' => $deficiency->authorization->canal,
                    'fecha' => $deficiency->authorization->fecha?->format('d/m/Y'),
                    'observacion' => $deficiency->authorization->observacion,
                    'importe' => $deficiency->authorization->importe !== null ? (float) $deficiency->authorization->importe : null,
                ] : null,
                'cotizacion_linea' => ($linea = QuoteItem::query()->with('quote', 'product', 'service')->where('deficiency_id', $deficiency->id)->first()) ? ['id' => $linea->quote_id, 'numero' => $linea->quote->numero, 'nombre' => $linea->product->nombre ?? $linea->service?->nombre, 'cantidad' => (int) $linea->cantidad, 'importe' => (float) $linea->subtotal] : null,
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
