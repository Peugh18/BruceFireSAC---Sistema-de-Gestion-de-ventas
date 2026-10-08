<?php

namespace App\Http\Controllers\Vendedor;

use App\Actions\Certificates\EmitirCertificadosDeVenta;
use App\Enums\EquipmentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\EmitirCertificadosRequest;
use App\Models\Certificate;
use App\Models\CertificateUnit;
use App\Models\CompanySetting;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Team;
use App\Services\Certificates\CertificadosDeVenta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class SaleCertificateController extends Controller
{
    /**
     * Pantalla "Armar certificados": reparte los extintores de la venta en
     * grupos (un local o vehículo por grupo), con la numeración del cliente.
     */
    public function create(Team $current_team, Sale $sale, Request $request): Response
    {
        $this->assertSedeAccess($request, $sale);

        $sale->load('client', 'items.equipment', 'items.product', 'items.inventoryUnit');
        $pruebas = $this->pruebasRegistradas($sale);

        return Inertia::render('vendedor/ventas/certificados', [
            'sale' => [
                'id' => $sale->id,
                'numero_interno' => $sale->numero_interno,
                'estado' => $sale->estado,
                'destino' => $sale->destino,
                'referencia' => $sale->referencia,
                'client' => $sale->client->only(['id', 'tipo_documento', 'razon_social', 'numero_documento', 'direccion_fiscal']),
            ],
            'unidades' => $sale->items
                ->filter(fn (SaleItem $item) => $item->equipment !== null)
                ->map(fn (SaleItem $item) => [
                    'equipment_id' => $item->equipment->id,
                    'codigo_interno' => $item->equipment->numero_serie,
                    'serie_fabricante' => $item->equipment->serie_fabricante,
                    'producto' => $item->product?->nombre,
                    'capacidad' => $item->equipment->capacidad,
                    'marca' => $item->equipment->marca,
                    'numero_cliente' => $item->equipment->numero_cliente,
                    'agente' => $item->equipment->tipo_agente,
                    'agente_conocido' => EquipmentType::esConocido($item->equipment->tipo_agente),
                    'presion_ph_sugerida' => EquipmentType::esConocido($item->equipment->tipo_agente)
                        ? EquipmentType::fromDescription($item->equipment->tipo_agente)->presionPruebaHidrostatica()
                        : null,
                    'fecha_ultima_ph' => $pruebas->get($item->equipment->id)?->fecha_ultima_ph?->toDateString(),
                    'presion_ph' => $pruebas->get($item->equipment->id)?->presion_ph,
                    'tiempo_ph' => $pruebas->get($item->equipment->id)?->tiempo_ph,
                ])
                ->values(),
            'tiposSugeridos' => EmitirCertificadosDeVenta::tiposPorDestino($sale->destino),
            'instructor' => CompanySetting::current()->instructor_capacitacion,
            'emitidos' => CertificadosDeVenta::deLaVenta($sale),
            'gruposActuales' => $this->gruposActuales($sale),
        ]);
    }

    /**
     * Datos de la prueba hidrostática ya certificada de cada extintor.
     *
     * @return Collection<int, CertificateUnit>
     */
    protected function pruebasRegistradas(Sale $sale): Collection
    {
        return Certificate::query()
            ->with('certificateUnits')
            ->where('sale_id', $sale->id)
            ->whereIn('estado', ['vigente', 'vencido'])
            ->whereHas('certificateType', fn ($query) => $query->where('codigo', 'prueba_hidrostatica'))
            ->get()
            ->flatMap(fn (Certificate $certificate) => $certificate->certificateUnits)
            ->filter(fn (CertificateUnit $unit) => $unit->equipment_id !== null)
            ->keyBy('equipment_id');
    }

    /**
     * Cómo están armados hoy los certificados vigentes de la venta (grupos,
     * orden y numeración del cliente), para ajustarlos en vez de empezar de cero.
     *
     * @return list<array{referencia: string|null, direccion: string|null, tipos: list<string>, unidades: list<array{equipment_id: int, numero_cliente: string|null}>, capacitacion: array<string, mixed>|null}>
     */
    protected function gruposActuales(Sale $sale): array
    {
        return Certificate::query()
            ->with(['certificateType', 'certificateUnits', 'participants'])
            ->where('sale_id', $sale->id)
            ->whereIn('estado', ['vigente', 'vencido'])
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Certificate $certificate) => $certificate->referencia.'|'.$certificate->direccion)
            ->map(function ($certificados) {
                $conUnidades = $certificados->first(fn (Certificate $certificate) => $certificate->certificateUnits->isNotEmpty());
                $capacitacion = $certificados->first(fn (Certificate $certificate) => $certificate->certificateType->codigo === 'capacitacion');

                return [
                    'referencia' => $certificados->first()->referencia,
                    'direccion' => $certificados->first()->direccion,
                    'tipos' => $certificados->map(fn (Certificate $certificate) => $certificate->certificateType->codigo)->unique()->values()->all(),
                    'unidades' => $conUnidades
                        ? $conUnidades->certificateUnits->sortBy('orden')->filter(fn ($unit) => $unit->equipment_id !== null)
                            ->map(fn ($unit) => ['equipment_id' => $unit->equipment_id, 'numero_cliente' => $unit->numero_cliente])
                            ->values()->all()
                        : [],
                    'capacitacion' => $capacitacion ? [
                        ...($capacitacion->datos ?? []),
                        'participantes' => $capacitacion->participants->whereNull('anulado_at')->map(fn ($participant) => [
                            'id' => $participant->id,
                            'nombres' => $participant->nombres,
                            'dni' => $participant->dni,
                            'cargo' => $participant->cargo,
                        ])->values()->all(),
                    ] : null,
                ];
            })
            ->values()
            ->all();
    }

    public function store(Team $current_team, Sale $sale, EmitirCertificadosRequest $request, EmitirCertificadosDeVenta $emitir): RedirectResponse
    {
        $this->assertSedeAccess($request, $sale);

        $certificados = $emitir->handle(
            $sale,
            $request->validated('grupos'),
            $request->validated('motivo') ?: EmitirCertificadosDeVenta::MOTIVO_AJUSTE,
            $request->user()->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$certificados->count()} certificado(s) listos."]);

        return redirect()->route('vendedor.ventas.show', [
            'current_team' => $current_team,
            'sale' => $sale,
        ]);
    }

    protected function assertSedeAccess(Request $request, Sale $sale): void
    {
        // Cada vendedor corrige solo sus ventas; el Gerente, las de todos.
        abort_unless(Sale::query()->visiblePara($request->user())->whereKey($sale->id)->exists(), 404);
    }
}
