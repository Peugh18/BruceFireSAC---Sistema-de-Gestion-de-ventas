<?php

namespace App\Http\Controllers\Vendedor;

use App\Actions\Certificates\EmitirCertificadoDeServicio;
use App\Actions\Certificates\EmitirCertificadosDeVenta;
use App\Enums\EquipmentType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Vendedor\Concerns\ValidaVentaVisible;
use App\Http\Requests\Sales\EmitirCertificadosRequest;
use App\Models\Certificate;
use App\Models\CertificateUnit;
use App\Models\CompanySetting;
use App\Models\Equipment;
use App\Models\Sale;
use App\Models\Team;
use App\Services\Certificates\CertificadosDeVenta;
use App\Services\Certificates\PruebasHidrostaticas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SaleCertificateController extends Controller
{
    use ValidaVentaVisible;

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
            'unidades' => CertificadosDeVenta::equipos($sale)
                ->map(fn (Equipment $equipo) => [
                    'equipment_id' => $equipo->id,
                    'codigo_interno' => $equipo->numero_serie,
                    'serie_fabricante' => $equipo->serie_fabricante,
                    'producto' => $equipo->product?->nombre,
                    'capacidad' => $equipo->capacidad,
                    'marca' => $equipo->marca,
                    'numero_cliente' => $equipo->numero_cliente,
                    'agente' => $equipo->tipo_agente,
                    'agente_conocido' => EquipmentType::esConocido($equipo->tipo_agente),
                    'presion_ph_sugerida' => EquipmentType::esConocido($equipo->tipo_agente)
                        ? EquipmentType::fromDescription($equipo->tipo_agente)->presionPruebaHidrostatica()
                        : null,
                    'fecha_ultima_ph' => $pruebas->get($equipo->id)?->fecha_ultima_ph?->toDateString(),
                    'presion_ph' => $pruebas->get($equipo->id)?->presion_ph,
                    'tiempo_ph' => $pruebas->get($equipo->id)?->tiempo_ph,
                    'ph_reutilizable' => ($ph = $pruebas->get($equipo->id)?->certificate) ? [
                        'id' => $ph->id,
                        'numero' => $ph->numero,
                        'fecha_vigencia_hasta' => $ph->fecha_vigencia_hasta?->toDateString(),
                    ] : null,
                ])
                ->values(),
            'tiposSugeridos' => EmitirCertificadosDeVenta::tiposPorDestino($sale->destino),
            'instructor' => CompanySetting::current()->instructor_capacitacion,
            'emitidos' => CertificadosDeVenta::deLaVenta($sale, true),
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
        return PruebasHidrostaticas::vigentes($sale->client_id, array_values(CertificadosDeVenta::equipos($sale)->map(fn (Equipment $equipo): int => $equipo->id)->all()));
    }

    /**
     * Cómo están armados hoy los certificados vigentes de la venta (grupos,
     * orden y numeración del cliente), para ajustarlos en vez de empezar de cero.
     *
     * @return list<array{destino: string, referencia: string|null, direccion: string|null, tipos: list<string>, unidades: list<array{equipment_id: int, numero_cliente: string|null}>, capacitacion: array<string, mixed>|null}>
     */
    protected function gruposActuales(Sale $sale): array
    {
        return array_values(CertificadosDeVenta::propios($sale)
            ->with(['certificateType', 'certificateUnits', 'participants'])
            // Solo los de extintores y capacitación se arman aquí; los de servicio
            // (luces, detección, plantillas por bloques) tienen su propio formulario.
            ->whereHas('certificateType', fn ($query) => $query->whereIn('codigo', array_diff(EmitirCertificadoDeServicio::TIPOS_DE_EXTINTORES, ['prueba_hidrostatica'])))
            ->where('client_id', $sale->client_id)
            ->whereIn('estado', ['vigente', 'vencido'])
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Certificate $certificate) => $certificate->referencia.'|'.$certificate->direccion)
            ->map(function ($certificados) use ($sale) {
                $conUnidades = $certificados->first(fn (Certificate $certificate) => $certificate->certificateUnits->isNotEmpty());
                $capacitacion = $certificados->first(fn (Certificate $certificate) => $certificate->certificateType->codigo === 'capacitacion');

                $destino = $conUnidades?->datos['destino'] ?? ($capacitacion ? 'local_cliente' : $sale->destino);

                return [
                    'destino' => $destino,
                    'referencia' => $certificados->first()->referencia,
                    'direccion' => $certificados->first()->direccion,
                    'tipos' => $conUnidades?->datos['tipos'] ?? ($conUnidades?->service_order_id
                        ? EmitirCertificadosDeVenta::tiposPorDestino($destino)
                        : $certificados->map(fn (Certificate $certificate) => $certificate->certificateType->codigo)->unique()->values()->all()),
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
            ->all());
    }

    public function store(Team $current_team, Sale $sale, EmitirCertificadosRequest $request, EmitirCertificadosDeVenta $emitir): RedirectResponse
    {
        $this->assertSedeAccess($request, $sale);
        $certificados = DB::transaction(function () use ($sale, $request, $emitir) {
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);
            $this->completarDatos($sale, $request->validated('grupos'));

            return $emitir->handle(
                $sale,
                $request->validated('grupos'),
                $request->validated('motivo') ?: EmitirCertificadosDeVenta::MOTIVO_AJUSTE,
                $request->user()->id,
            );
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$certificados->count()} certificado(s) listos."]);

        return redirect()->route('vendedor.ventas.show', [
            'current_team' => $current_team,
            'sale' => $sale,
        ]);
    }

    /**
     * El certificado sale completo: cada extintor con su serie de fábrica y la
     * dirección del cliente. Lo que se llenó en la pantalla se guarda; si aún
     * falta algo, no se emite y se dice qué falta.
     *
     * @param  array<int, array<string, mixed>>  $grupos
     */
    protected function completarDatos(Sale $sale, array $grupos): void
    {
        $faltan = [];
        $permitidos = CertificadosDeVenta::equipos($sale)->keyBy('id');

        foreach ($grupos as $index => $grupo) {
            if (array_diff($grupo['tipos'], ['capacitacion']) === []) {
                continue;
            }

            foreach ($grupo['unidades'] as $unidad) {
                $equipo = $permitidos->get((int) $unidad['equipment_id']);
                if (! $equipo) {
                    throw ValidationException::withMessages(["grupos.{$index}.unidades" => 'Hay extintores que no pertenecen a esta venta o aún no están aptos.']);
                }
                $equipo = Equipment::query()->lockForUpdate()->findOrFail($equipo->id);
                if (filled($unidad['serie_fabricante'] ?? null)) {
                    $equipo->update(['serie_fabricante' => trim($unidad['serie_fabricante'])]);
                } elseif ($equipo && (blank($equipo->serie_fabricante) || str_starts_with((string) $equipo->serie_fabricante, 'No legible'))) {
                    $faltan["grupos.{$index}.unidades"][] = "Falta la serie de fábrica de {$equipo->numero_serie}.";
                }
            }

            if (blank($grupo['direccion'] ?? null) && blank($sale->client->direccion_fiscal)) {
                $faltan["grupos.{$index}.direccion"][] = 'Falta la dirección del cliente.';
            }
        }

        if ($faltan !== []) {
            throw ValidationException::withMessages(array_map(fn (array $mensajes) => implode(' ', $mensajes), $faltan));
        }
    }
}
