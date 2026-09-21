<?php

namespace App\Http\Controllers\Vendedor;

use App\Actions\Billing\IssueCreditNote;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StoreCreditNoteRequest;
use App\Models\ElectronicDocument;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;

class CreditNoteController extends Controller
{
    public function store(Team $current_team, StoreCreditNoteRequest $request, IssueCreditNote $action): RedirectResponse
    {
        $original = ElectronicDocument::findOrFail($request->integer('electronic_document_id'));

        $action->handle(
            $original,
            $request->string('motivo_catalogo')->toString(),
            $request->string('detalle')->toString(),
            (float) $request->input('importe'),
        );

        return back();
    }
}
