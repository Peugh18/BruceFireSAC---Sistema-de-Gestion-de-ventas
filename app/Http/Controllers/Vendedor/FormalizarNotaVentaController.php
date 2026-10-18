<?php

namespace App\Http\Controllers\Vendedor;

use App\Actions\Billing\FormalizarNotaVenta;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\FormalizarNotaVentaRequest;
use App\Models\Sale;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class FormalizarNotaVentaController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Team $current_team, Sale $sale, FormalizarNotaVentaRequest $request, FormalizarNotaVenta $formalizar): RedirectResponse
    {
        abort_unless(Sale::query()->visiblePara($request->user())->whereKey($sale->id)->exists(), 404);
        $formalizar->handle($sale, $request->validated('comprobante_tipo'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Nota de venta formalizada. Revisa el estado de envío del comprobante SUNAT.']);

        return redirect()->route('vendedor.ventas.show', ['current_team' => $current_team, 'sale' => $sale]);
    }
}
