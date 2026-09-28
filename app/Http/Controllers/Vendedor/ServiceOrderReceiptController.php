<?php

namespace App\Http\Controllers\Vendedor;

use App\Http\Controllers\Controller;
use App\Models\ServiceOrder;
use App\Models\Team;
use App\Services\Reports\ServiceOrderReceiptPdfService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ServiceOrderReceiptController extends Controller
{
    public function __invoke(Team $current_team, ServiceOrder $service_order, Request $request, ServiceOrderReceiptPdfService $service): Response
    {
        $sedeId = $request->user()->sedeRestringidaId();
        abort_if($sedeId !== null && (int) $service_order->sede_id !== $sedeId, 404);

        return $service->generate($service_order)->stream("constancia-recepcion-{$service_order->codigo}.pdf");
    }
}
