<?php

namespace App\Http\Controllers\Gerente;

use App\Actions\Billing\EmitElectronicDocument;
use App\Actions\Billing\NotaPorAprobar;
use App\Http\Controllers\Controller;
use App\Models\NoteRequest;
use App\Models\Team;
use App\Services\Billing\GreenterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Notas de crédito y débito que pidieron los vendedores (V2/S17).
 */
class NoteRequestController extends Controller
{
    public function index(Team $current_team): Response
    {
        $solicitudes = NoteRequest::query()
            ->where('estado', 'por_aprobar')
            ->with('original.sale.client', 'solicitante:id,name')
            ->oldest()
            ->get()
            ->map(fn (NoteRequest $solicitud) => [
                'id' => $solicitud->id,
                'tipo' => $solicitud->tipo,
                'motivo' => $solicitud->tipo === 'nota_credito'
                    ? GreenterService::descripcionMotivoCredito($solicitud->motivo_catalogo)
                    : GreenterService::descripcionMotivoDebito($solicitud->motivo_catalogo),
                'detalle' => $solicitud->detalle,
                'importe' => (float) $solicitud->importe,
                'comprobante' => "{$solicitud->original->serie}-{$solicitud->original->correlativo}",
                'total_comprobante' => (float) $solicitud->original->sale->total,
                'cliente' => $solicitud->original->sale->client->razon_social,
                'solicitante' => $solicitud->solicitante->name,
                'fecha' => $solicitud->created_at?->format('d/m/Y H:i'),
            ]);

        return Inertia::render('gerente/notas/index', [
            'solicitudes' => $solicitudes,
        ]);
    }

    public function aprobar(Team $current_team, NoteRequest $note_request, Request $request, NotaPorAprobar $notaPorAprobar, EmitElectronicDocument $emit): RedirectResponse
    {
        $nota = $notaPorAprobar->aprobar($note_request, $request->user()->id);
        $numero = "{$nota->serie}-{$nota->correlativo}";

        try {
            $nota = $emit->sendDocument($nota);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', "Nota {$numero} aprobada, pero SUNAT no respondió: queda pendiente de envío y se puede reenviar.");
        }

        if ($nota->sunat_estado === 'rechazado') {
            return back()->with('error', "SUNAT rechazó la nota {$numero}: {$nota->sunat_mensaje}");
        }

        return back()->with('success', "Nota {$numero} aprobada y emitida.");
    }

    public function rechazar(Team $current_team, NoteRequest $note_request, Request $request, NotaPorAprobar $notaPorAprobar): RedirectResponse
    {
        $datos = $request->validate(
            ['motivo_rechazo' => ['required', 'string', 'max:500']],
            ['motivo_rechazo.required' => 'Escribe por qué rechazas la nota: el vendedor lo verá.'],
        );

        $notaPorAprobar->rechazar($note_request, $request->user()->id, $datos['motivo_rechazo']);

        return back()->with('success', 'Solicitud rechazada.');
    }
}
