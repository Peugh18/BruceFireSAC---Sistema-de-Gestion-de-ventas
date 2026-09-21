<?php

namespace App\Http\Controllers\Vendedor;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleItemScanController extends Controller
{
    public function resolve(Request $request, InventoryLookupController $inventoryLookupController): JsonResponse
    {
        return $inventoryLookupController->bySerial($request);
    }
}
