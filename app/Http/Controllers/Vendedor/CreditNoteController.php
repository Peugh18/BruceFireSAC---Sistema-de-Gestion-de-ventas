<?php

namespace App\Http\Controllers\Vendedor;

use App\Actions\Billing\EmitElectronicDocument;
use App\Actions\Billing\IssueCreditNote;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Vendedor\Concerns\AcotaPorSede;
use App\Http\Requests\Billing\StoreCreditNoteRequest;
use App\Models\ElectronicDocument;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Throwable;

class CreditNoteController extends Controller
{
    use AcotaPorSede;

    public function store(Team $current_team, StoreCreditNoteRequest $request, IssueCreditNote $action, EmitElectronicDocument $emit): RedirectResponse
    {
        $original = ElectronicDocument::findOrFail($request->integer('electronic_document_id'));
        $this->asegurarSede($original->sale?->sede_id);

        $nota = $action->handle(
            $original,
            $request->string('motivo_catalogo')->toString(),
            $request->string('detalle')->toString(),
            (float) $request->input('importe'),
        );

        try {
            $nota = $emit->sendDocument($nota);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', "Nota de crédito {$nota->serie}-{$nota->correlativo} registrada pero pendiente de envío a SUNAT. La venta se anula cuando SUNAT la acepte.");
        }

        if ($nota->sunat_estado === 'rechazado') {
            return back()->with('error', "SUNAT rechazó la nota de crédito {$nota->serie}-{$nota->correlativo}: la venta sigue vigente. Puedes emitir otra.");
        }

        $anulada = $original->sale->fresh()->estado === 'anulada';
        $aviso = $anulada && $original->sale->payments()->whereNotNull('installment_id')->exists()
            ? ' La venta tenía cuotas cobradas: devuelve ese dinero al cliente.'
            : '';

        return back()->with('success', "Nota de crédito {$nota->serie}-{$nota->correlativo} emitida.".($anulada ? ' La venta quedó anulada.' : '').$aviso);
    }
}
