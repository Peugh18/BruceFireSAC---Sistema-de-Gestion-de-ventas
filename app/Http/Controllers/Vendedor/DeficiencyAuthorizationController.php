<?php

namespace App\Http\Controllers\Vendedor;

use App\Actions\TecnicoPlanta\ExecuteAndCloseServiceOrder;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Vendedor\Concerns\AcotaPorSede;
use App\Http\Requests\Deficiencies\StoreDeficiencyAuthorizationRequest;
use App\Models\Deficiency;
use App\Models\Team;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class DeficiencyAuthorizationController extends Controller
{
    use AcotaPorSede;

    public function store(Team $current_team, Deficiency $deficiency, StoreDeficiencyAuthorizationRequest $request, ExecuteAndCloseServiceOrder $action): RedirectResponse
    {
        $this->asegurarSede($deficiency->serviceOrder?->sede_id);

        if ($deficiency->estado !== 'esperando_autorizacion') {
            throw ValidationException::withMessages([
                'deficiency' => 'Esta deficiencia no está esperando autorización.',
            ]);
        }

        if (! $request->boolean('autorizado')) {
            $deficiency->update(['estado' => 'rechazada']);
            $this->recordEvent($deficiency, $request->user()->id, false);
            $action->releaseFromAuthorizationHold($deficiency->serviceOrder, $request->user());

            AuditLogger::log(
                action: 'deficiencia.autorizacion',
                entity: $deficiency,
                newValues: ['resultado' => 'rechazada'],
                userId: $request->user()->id
            );

            return back();
        }

        $deficiency->authorization()->create([
            'autorizado_por' => $request->string('autorizado_por')->toString(),
            'canal' => $request->string('canal')->toString(),
            'fecha' => $request->date('fecha')->toDateString(),
            'observacion' => $request->input('observacion'),
            'cotizacion_adicional_id' => $request->input('cotizacion_adicional_id'),
            'vendedor_id' => $request->user()->id,
        ]);
        $deficiency->update(['estado' => 'autorizada']);
        if ($deficiency->serviceOrder->sale_id) {
            $deficiency->serviceOrder->events()->create(['tipo' => 'otro', 'user_id' => $request->user()->id, 'payload' => ['accion' => 'saldo_adicional_pendiente', 'deficiency_id' => $deficiency->id, 'mensaje' => 'La orden ya fue cobrada; el adicional autorizado queda como saldo pendiente por cobrar.']]);
        }
        $this->recordEvent($deficiency, $request->user()->id, true);
        $action->releaseFromAuthorizationHold($deficiency->serviceOrder, $request->user());

        AuditLogger::log(
            action: 'deficiencia.autorizacion',
            entity: $deficiency,
            newValues: [
                'resultado' => 'autorizada',
                'canal' => $request->string('canal')->toString(),
            ],
            userId: $request->user()->id
        );

        return back();
    }

    protected function recordEvent(Deficiency $deficiency, int $userId, bool $autorizado): void
    {
        $deficiency->serviceOrder->events()->create([
            'tipo' => 'autorizacion_registrada',
            'user_id' => $userId,
            'payload' => [
                'deficiency_id' => $deficiency->id,
                'resultado' => $autorizado ? 'autorizada' : 'rechazada',
            ],
        ]);
    }
}
