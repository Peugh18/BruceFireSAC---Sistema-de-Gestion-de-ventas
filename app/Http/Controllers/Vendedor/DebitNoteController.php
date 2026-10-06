<?php

namespace App\Http\Controllers\Vendedor;

use App\Actions\Billing\EmitElectronicDocument;
use App\Actions\Billing\IssueDebitNote;
use App\Actions\Billing\NotaPorAprobar;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Vendedor\Concerns\AcotaPorSede;
use App\Http\Requests\Billing\StoreDebitNoteRequest;
use App\Models\ElectronicDocument;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Throwable;

class DebitNoteController extends Controller
{
    use AcotaPorSede;

    /**
     * Un vendedor solo la pide: queda por aprobar hasta que el Gerente la
     * revise. Si quien la emite también es Gerente, sale directo.
     */
    public function store(Team $current_team, StoreDebitNoteRequest $request, IssueDebitNote $action, EmitElectronicDocument $emit, NotaPorAprobar $notaPorAprobar): RedirectResponse
    {
        $original = ElectronicDocument::findOrFail($request->integer('electronic_document_id'));
        $this->asegurarVenta($original->sale);

        if (! $request->user()->hasRole('Gerente')) {
            $notaPorAprobar->solicitar(
                $original,
                'nota_debito',
                $request->string('motivo_catalogo')->toString(),
                $request->string('detalle')->toString(),
                (float) $request->input('importe'),
                $request->user()->id,
            );

            return back()->with('success', 'Nota de débito enviada al Gerente. Se emitirá cuando la apruebe.');
        }

        $nota = $action->handle(
            $original,
            $request->string('motivo_catalogo')->toString(),
            $request->string('detalle')->toString(),
            (float) $request->input('importe'),
        );

        try {
            $emit->sendDocument($nota);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', "Nota de débito {$nota->serie}-{$nota->correlativo} registrada pero pendiente de envío a SUNAT.");
        }

        return back()->with('success', "Nota de débito {$nota->serie}-{$nota->correlativo} emitida.");
    }
}
