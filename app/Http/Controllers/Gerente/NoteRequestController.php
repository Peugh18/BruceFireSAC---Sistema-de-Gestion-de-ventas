<?php

namespace App\Http\Controllers\Gerente;

use App\Actions\Billing\EmitElectronicDocument;
use App\Actions\Billing\NotaPorAprobar;
use App\Http\Controllers\Controller;
use App\Models\NoteRequest;
use App\Models\Team;
use App\Services\Billing\DatosEmision;
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
                'lineas' => $this->describirLineas($solicitud),
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

    /**
     * Qué líneas del comprobante pidió ajustar el vendedor, para que el
     * Gerente apruebe sabiendo exactamente a qué se aplica la nota.
     *
     * @return list<string>
     */
    protected function describirLineas(NoteRequest $solicitud): array
    {
        $pedidas = $solicitud->nota_datos['lineas'] ?? [];
        if ($pedidas === []) {
            return [];
        }

        try {
            $originales = app(DatosEmision::class)->recuperar($solicitud->original)['lineas'] ?? [];
        } catch (Throwable) {
            $originales = [];
        }

        return array_values(array_map(function (array $pedida) use ($originales): string {
            $nombre = $originales[$pedida['indice']]['nombre'] ?? 'Línea '.($pedida['indice'] + 1);

            return match (true) {
                isset($pedida['descripcion']) => $nombre.' → «'.$pedida['descripcion'].'»',
                isset($pedida['importe']) => $nombre.': descuento de S/ '.number_format((float) $pedida['importe'], 2),
                isset($pedida['cantidad']) => $nombre.': devuelve '.(float) $pedida['cantidad'].' u.',
                default => $nombre,
            };
        }, $pedidas));
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
