<?php

namespace App\Http\Controllers\Vendedor;

use App\Actions\Billing\EmitElectronicDocument;
use App\Actions\Billing\IssueCreditNote;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StoreCreditNoteRequest;
use App\Models\ElectronicDocument;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Throwable;

class CreditNoteController extends Controller
{
    public function store(Team $current_team, StoreCreditNoteRequest $request, IssueCreditNote $action, EmitElectronicDocument $emit): RedirectResponse
    {
        $original = ElectronicDocument::findOrFail($request->integer('electronic_document_id'));

        $nota = $action->handle(
            $original,
            $request->string('motivo_catalogo')->toString(),
            $request->string('detalle')->toString(),
            (float) $request->input('importe'),
        );

        $pagos = $original->sale->payments()->exists() && $original->sale->fresh()->estado === 'anulada';
        $aviso = $pagos ? ' La venta tiene pagos registrados: devuelva el dinero al cliente de forma manual.' : '';

        try {
            $emit->sendDocument($nota);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', "Nota de crédito {$nota->serie}-{$nota->correlativo} registrada pero pendiente de envío a SUNAT.{$aviso}");
        }

        return back()->with('success', "Nota de crédito {$nota->serie}-{$nota->correlativo} emitida.{$aviso}");
    }
}
