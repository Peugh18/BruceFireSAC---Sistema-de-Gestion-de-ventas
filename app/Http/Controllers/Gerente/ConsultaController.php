<?php

namespace App\Http\Controllers\Gerente;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Sale;
use App\Models\ServiceOrder;
use App\Models\Team;
use App\Services\Tecnico\PasoDelTaller;
use Inertia\Inertia;
use Inertia\Response;

class ConsultaController extends Controller
{
    public function venta(Team $current_team, Sale $sale): Response
    {
        $sale->load('client', 'items.product', 'items.service', 'electronicDocuments');

        return Inertia::render('gerente/consulta/ficha', [
            'titulo' => 'Venta '.$sale->numero_interno,
            'campos' => ['Cliente' => $sale->client->razon_social, 'Fecha' => $sale->fecha->format('d/m/Y'), 'Estado' => ucfirst($sale->estado), 'Total' => 'S/ '.number_format((float) $sale->total, 2)],
            'filas' => $sale->items->map(fn ($item) => ['descripcion' => $item->product->nombre ?? $item->service->nombre ?? 'Concepto de venta', 'detalle' => $item->cantidad.' unidades · S/ '.number_format((float) $item->subtotal, 2)])->values(),
            'enlaces' => [['titulo' => 'Ver cliente', 'url' => route('gerente.consulta.cliente', ['current_team' => $current_team, 'client' => $sale->client])], ['titulo' => 'Revisar cobranza', 'url' => route('gerente.cobranzas.index', ['current_team' => $current_team, 'buscar' => $sale->client->numero_documento])]],
        ]);
    }

    public function orden(Team $current_team, ServiceOrder $service_order): Response
    {
        $service_order->load('client', 'equipments', 'checklists', 'deficiencies', 'tecnico');
        $paso = PasoDelTaller::de($service_order);

        return Inertia::render('gerente/consulta/ficha', [
            'titulo' => 'Orden '.$service_order->codigo,
            'campos' => ['Cliente' => $service_order->client->razon_social, 'Paso' => ucfirst($paso['clave']), 'Responsable' => $service_order->tecnico->name ?? 'Sin asignar', 'Estado' => ucfirst(str_replace('_', ' ', $service_order->estado))],
            'filas' => $service_order->equipments->map(fn ($equipo) => ['descripcion' => $equipo->numero_serie, 'detalle' => trim(($equipo->tipo_agente ?? '').' '.($equipo->capacidad ?? ''))])->values(),
            'enlaces' => [['titulo' => 'Ver cliente', 'url' => route('gerente.consulta.cliente', ['current_team' => $current_team, 'client' => $service_order->client])]],
        ]);
    }

    public function cliente(Team $current_team, Client $client): Response
    {
        return Inertia::render('gerente/consulta/ficha', [
            'titulo' => $client->razon_social,
            'campos' => ['Documento' => $client->numero_documento, 'Dirección' => $client->direccion_fiscal ?? 'Sin registrar'],
            'filas' => [],
            'enlaces' => [['titulo' => 'Revisar cobranza', 'url' => route('gerente.cobranzas.index', ['current_team' => $current_team, 'buscar' => $client->numero_documento])]],
        ]);
    }
}
