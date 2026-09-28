<?php

namespace App\Http\Controllers\TecnicoPlanta;

use App\Actions\TecnicoPlanta\QuickRegisterEquipment;
use App\Actions\TecnicoPlanta\ReceiveServiceOrder;
use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\ServiceOrder;
use App\Models\Team;
use App\Services\Inventory\StickerPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class ReceptionController extends Controller
{
    /**
     * Lista de recepciones en Planta (§18).
     */
    public function index(Request $request, Team $current_team): Response
    {
        $search = trim((string) $request->input('search', ''));
        $filter = $request->string('filter', 'pendientes')->toString(); // pendientes | recibidas | todas

        $query = ServiceOrder::query()
            ->accessibleToTechnician($request->user())
            ->where(function ($q) {
                $q->where('departamento_tecnico', 'planta')
                    ->orWhereNull('departamento_tecnico');
            })
            ->with([
                'client:id,nombre_comercial,razon_social,telefono,numero_documento',
                'sede:id,nombre',
                'equipments:id,numero_serie,tipo_agente,capacidad,marca',
            ])
            ->latest('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('codigo', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($cq) use ($search) {
                        $cq->where('nombre_comercial', 'like', "%{$search}%")
                            ->orWhere('razon_social', 'like', "%{$search}%")
                            ->orWhere('numero_documento', 'like', "%{$search}%");
                    })
                    ->orWhereHas('equipments', function ($eq) use ($search) {
                        $eq->where('numero_serie', 'like', "%{$search}%");
                    });
            });
        }

        match ($filter) {
            'pendientes' => $query->where('estado', 'pendiente_recepcion'),
            'recibidas' => $query->whereIn('estado', ['recibido_planta', 'en_revision', 'en_proceso']),
            default => null,
        };

        $orders = $query->paginate(15)->through(function (ServiceOrder $order) {
            return [
                'id' => $order->id,
                'codigo' => $order->codigo,
                'cliente' => $order->client->nombre_comercial ?: $order->client->razon_social,
                'cliente_doc' => $order->client->numero_documento,
                'telefono' => $order->client->telefono,
                'sede' => $order->sede?->nombre,
                'tipo_servicio' => $order->tipo_servicio,
                'fecha' => $order->fecha->toDateString(),
                'prioridad' => $order->prioridad,
                'estado' => $order->estado,
                'equipos_count' => $order->equipments->count(),
            ];
        });

        $counts = [
            'pendientes' => ServiceOrder::where(fn ($q) => $q->where('departamento_tecnico', 'planta')->orWhereNull('departamento_tecnico'))
                ->where('estado', 'pendiente_recepcion')->count(),
            'recibidas' => ServiceOrder::where(fn ($q) => $q->where('departamento_tecnico', 'planta')->orWhereNull('departamento_tecnico'))
                ->whereIn('estado', ['recibido_planta', 'en_revision', 'en_proceso'])->count(),
        ];

        return Inertia::render('tecnico-planta/recepciones/index', [
            'orders' => $orders,
            'counts' => $counts,
            'filters' => [
                'search' => $search,
                'filter' => $filter,
            ],
        ]);
    }

    /**
     * Vista de detalle de recepción / escaneo de equipos para una orden.
     */
    public function show(Team $current_team, ServiceOrder $service_order): Response
    {
        $service_order->load([
            'client',
            'sede',
            'tecnico',
            'equipments',
            'events' => fn ($q) => $q->latest('id'),
        ]);

        return Inertia::render('tecnico-planta/recepciones/show', [
            'asignacion' => $service_order->asignacionPara(request()->user()),
            'order' => [
                'id' => $service_order->id,
                'codigo' => $service_order->codigo,
                'cliente' => [
                    'id' => $service_order->client->id,
                    'nombre' => $service_order->client->nombre_comercial ?: $service_order->client->razon_social,
                    'documento' => $service_order->client->numero_documento,
                    'telefono' => $service_order->client->telefono,
                    'direccion' => $service_order->client->direccion_fiscal,
                ],
                'sede' => $service_order->sede?->nombre,
                'tipo_servicio' => $service_order->tipo_servicio,
                'fecha' => $service_order->fecha->toDateString(),
                'prioridad' => $service_order->prioridad,
                'estado' => $service_order->estado,
                'observaciones' => $service_order->observaciones,
                'equipments' => $service_order->equipments->map(fn (Equipment $eq) => [
                    'id' => $eq->id,
                    'numero_serie' => $eq->numero_serie,
                    'tipo_agente' => $eq->tipo_agente,
                    'capacidad' => $eq->capacidad,
                    'marca' => $eq->marca,
                    'serie_fabricante' => $eq->serie_fabricante,
                    'anio_fabricacion' => $eq->anio_fabricacion,
                    'notas' => $eq->notas,
                    'recibido' => (bool) ($eq->pivot->recibido ?? true),
                ]),
                'eventos' => $service_order->events->map(fn ($ev) => [
                    'id' => $ev->id,
                    'tipo' => $ev->tipo,
                    'payload' => $ev->payload,
                    'created_at' => $ev->created_at?->toIso8601String(),
                ]),
            ],
        ]);
    }

    /**
     * Confirma la recepción de la orden en Planta (§18).
     */
    public function confirmReception(
        Request $request,
        Team $current_team,
        ServiceOrder $service_order,
        ReceiveServiceOrder $action
    ): RedirectResponse {
        $validated = $request->validate([
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'equipos_recibidos_count' => ['nullable', 'integer', 'min:0'],
            'diferencias' => ['nullable', 'string', 'max:500'],
        ]);

        $action->execute(
            $service_order,
            $request->user(),
            $validated['observaciones'] ?? null,
            (int) ($validated['equipos_recibidos_count'] ?? $service_order->equipments()->count()),
            $validated['diferencias'] ?? null
        );

        return back()->with('success', 'Orden recibida en Planta correctamente.');
    }

    /**
     * Búsqueda rápida de equipo por código de barras (Caso A).
     */
    public function searchEquipment(Request $request): JsonResponse
    {
        $code = trim((string) $request->input('code', ''));

        if ($code === '') {
            return response()->json(['found' => false]);
        }

        $equipment = Equipment::with('client:id,nombre_comercial,razon_social')
            ->where('numero_serie', $code)
            ->first();

        if (! $equipment) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'equipment' => [
                'id' => $equipment->id,
                'numero_serie' => $equipment->numero_serie,
                'cliente' => $equipment->client->nombre_comercial ?: $equipment->client->razon_social,
                'tipo_agente' => $equipment->tipo_agente,
                'capacidad' => $equipment->capacidad,
                'marca' => $equipment->marca,
                'serie_fabricante' => $equipment->serie_fabricante,
                'anio_fabricacion' => $equipment->anio_fabricacion,
                'notas' => $equipment->notas,
            ],
        ]);
    }

    /**
     * Alta Técnica Rápida (Caso A o B) (§18).
     */
    public function storeEquipment(
        Request $request,
        Team $current_team,
        ServiceOrder $service_order,
        QuickRegisterEquipment $action
    ): RedirectResponse {
        $validated = $request->validate([
            'numero_serie' => ['nullable', 'string', 'max:100'],
            'tipo_agente' => ['nullable', 'string', 'max:100'],
            'capacidad' => ['nullable', 'string', 'max:100'],
            'marca' => ['nullable', 'string', 'max:100'],
            'serie_fabricante' => ['nullable', 'string', 'max:100'],
            'anio_fabricacion' => ['nullable', 'string', 'max:100'],
            'ubicacion_actual' => ['nullable', 'string', 'max:255'],
            'notas' => ['nullable', 'string', 'max:500'],
            'observaciones_recepcion' => ['nullable', 'string', 'max:500'],
        ]);

        $equipment = $action->execute($service_order, [...$validated, 'recibido' => true], $request->user());

        return back()->with('success', sprintf('Equipo %s registrado/vinculado exitosamente.', $equipment->numero_serie));
    }

    /**
     * Imprime stickers con código de barras en formato PDF (grilla 2x2) para los equipos de la orden (§18, §84.9).
     */
    public function printStickers(
        Team $current_team,
        ServiceOrder $service_order,
        StickerPdfService $stickerPdfService
    ): HttpResponse {
        $equipments = $service_order->equipments()->get();

        $pdf = $stickerPdfService->generateForEquipments($equipments, $service_order);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="stickers-%s.pdf"', $service_order->codigo),
        ]);
    }
}
