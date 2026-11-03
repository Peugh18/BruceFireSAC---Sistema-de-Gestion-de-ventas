<?php

namespace App\Http\Controllers\Vendedor\Concerns;

use App\Models\Sale;
use Illuminate\Http\Request;

trait ValidaVentaVisible
{
    protected function assertSedeAccess(Request $request, Sale $sale): void
    {
        // Cada vendedor corrige solo sus ventas; el Gerente, las de todos.
        abort_unless(Sale::query()->visiblePara($request->user())->whereKey($sale->id)->exists(), 404);
    }
}
