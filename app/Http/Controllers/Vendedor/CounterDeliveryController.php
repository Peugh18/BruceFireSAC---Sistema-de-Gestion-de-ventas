<?php

namespace App\Http\Controllers\Vendedor;

use App\Actions\Equipment\RenewEquipmentAttentionDate;
use App\Http\Controllers\Controller;
use App\Models\ServiceOrder;
use App\Models\Team;
use App\Services\Reports\ActaConformidadPdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CounterDeliveryController extends Controller
{
    public function show(Team $current_team, ServiceOrder $service_order, Request $request): Response
    {
        $this->assertAccess($service_order, $request);
        $service_order->load(['client', 'equipments', 'events' => fn ($query) => $query->latest()]);

        return Inertia::render('vendedor/ordenes-servicio/entrega-mostrador', [
            'order' => $service_order,
            'delivery' => $this->deliveryEvent($service_order)?->payload,
            'isPaid' => $service_order->sale_id !== null,
        ]);
    }

    public function store(Team $current_team, ServiceOrder $service_order, Request $request, RenewEquipmentAttentionDate $renew): RedirectResponse
    {
        $this->assertAccess($service_order, $request);

        $validated = $request->validate([
            'receptor_nombre' => ['required', 'string', 'max:150'],
            'receptor_dni' => ['required', 'digits:8'],
            'conformidad_aceptada' => ['required', 'accepted'],
        ]);

        DB::transaction(function () use ($service_order, $request, $validated, $renew): void {
            // Bloqueada y releída (M6): un doble POST no puede crear dos
            // eventos de entrega ni cerrar dos veces la orden.
            $service_order = ServiceOrder::query()->lockForUpdate()->findOrFail($service_order->id);

            // Con el certificado emitido la orden ya se puede entregar.
            abort_unless(in_array($service_order->estado, ServiceOrder::ESTADOS_PARA_ENTREGAR, true), 422, 'La orden todavía no está lista para entregar.');
            abort_if($this->deliveryEvent($service_order) !== null, 422, 'Esta orden ya se entregó.');

            $service_order->events()->create([
                'tipo' => 'entrega_registrada',
                'user_id' => $request->user()->id,
                'payload' => [
                    'accion' => 'entrega_final_realizada',
                    'eslabon_custodia' => 'entrega_mostrador',
                    'responsable_nombre' => $request->user()->name,
                    ...$validated,
                ],
            ]);
            $service_order->update(['estado' => 'cerrado']);

            // Las fechas de los extintores se renuevan una sola vez: si el taller
            // ya las renovó al emitir el certificado, la entrega no las mueve.
            if (! $service_order->events()->where('tipo', 'trabajo_completado')->exists()) {
                $phRealizada = $service_order->certificates()->whereHas('certificateType', fn ($q) => $q->where('codigo', 'prueba_hidrostatica'))->exists();
                $renew->execute($service_order->equipments()->get(), $phRealizada);
            }
        });

        return back()->with('success', 'Entrega conforme registrada. Ya puedes descargar el acta.');
    }

    public function pdf(Team $current_team, ServiceOrder $service_order, Request $request, ActaConformidadPdfService $pdfService): HttpResponse
    {
        $this->assertAccess($service_order, $request);
        abort_unless($this->deliveryEvent($service_order), 404);

        return $pdfService->generate($service_order)->stream("acta-conformidad-{$service_order->codigo}.pdf");
    }

    protected function assertAccess(ServiceOrder $serviceOrder, Request $request): void
    {
        $sedeId = $request->user()->sedeRestringidaId();
        abort_if($sedeId !== null && (int) $serviceOrder->sede_id !== $sedeId, 404);
    }

    protected function deliveryEvent(ServiceOrder $serviceOrder): mixed
    {
        return $serviceOrder->events()->where('payload->accion', 'entrega_final_realizada')->where('payload->conformidad_aceptada', true)->latest()->first();
    }
}
