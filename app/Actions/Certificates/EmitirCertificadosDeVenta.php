<?php

namespace App\Actions\Certificates;

use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\CompanySetting;
use App\Models\Equipment;
use App\Models\Sale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmitirCertificadosDeVenta
{
    public const MOTIVO_AJUSTE = 'Ajuste de orden, numeración o agrupación de los extintores.';

    public function __construct(
        protected IssueCertificate $issueCertificate,
        protected SyncCertificateTraining $syncTraining,
    ) {}

    /**
     * Tipos de certificado que corresponden por regla de negocio según el
     * destino de los equipos.
     *
     * @return list<string>
     */
    public static function tiposPorDestino(string $destino): array
    {
        return $destino === 'vehiculo'
            ? ['operatividad_garantia', 'prueba_hidrostatica']
            : ['operatividad_garantia', 'capacitacion'];
    }

    /**
     * Certificados que salen solos al confirmar la venta: un grupo con todos
     * los extintores en el orden en que se escanearon y los tipos que tocan
     * según el destino. Si la venta no tiene extintores, no emite nada.
     *
     * @return Collection<int, Certificate>
     */
    public function automaticos(Sale $sale): Collection
    {
        $sale->loadMissing('items.equipment');
        $equipos = $sale->items->sortBy('id')->pluck('equipment')->filter()->values();
        $tipos = array_values(array_filter(
            self::tiposPorDestino((string) $sale->destino),
            fn (string $codigo) => CertificateType::query()->where('codigo', $codigo)->exists(),
        ));

        if ($equipos->isEmpty() || $tipos === []) {
            return collect();
        }

        return $this->handle($sale, [[
            'referencia' => $sale->referencia,
            'tipos' => $tipos,
            'unidades' => $equipos->map(fn (Equipment $equipo) => [
                'equipment_id' => $equipo->id,
                'numero_cliente' => $equipo->numero_cliente,
            ])->all(),
        ]]);
    }

    /**
     * Emite o ajusta los certificados de una venta armados en grupos: cada
     * grupo es un local o vehículo con sus extintores (en el orden y con la
     * numeración del cliente) y los tipos de certificado que lleva.
     *
     * Si la venta ya tenía certificados, se corrigen los mismos (conservan su
     * número y quedan con una revisión); solo un certificado que antes no
     * existía pide número nuevo, y los que sobran se anulan.
     *
     * @param  list<array{
     *     referencia?: string|null,
     *     direccion?: string|null,
     *     tipos: list<string>,
     *     unidades: list<array{equipment_id: int, numero_cliente?: string|null}>,
     *     capacitacion?: array<string, mixed>|null
     * }>  $grupos
     * @return Collection<int, Certificate>
     */
    public function handle(Sale $sale, array $grupos, string $motivo = self::MOTIVO_AJUSTE, ?int $userId = null): Collection
    {
        if ($sale->estado !== 'confirmada') {
            throw ValidationException::withMessages([
                'grupos' => 'Solo se emiten certificados de una venta confirmada.',
            ]);
        }

        $sale->loadMissing('client', 'items');
        $equiposDeLaVenta = $sale->items->pluck('equipment_id')->filter()->all();

        foreach ($grupos as $index => $grupo) {
            $ajenos = array_diff(array_column($grupo['unidades'] ?? [], 'equipment_id'), $equiposDeLaVenta);

            if ($ajenos !== []) {
                throw ValidationException::withMessages([
                    "grupos.{$index}.unidades" => 'Hay extintores que no pertenecen a esta venta.',
                ]);
            }

            $conUnidades = array_diff($grupo['tipos'], ['capacitacion']);

            if ($conUnidades !== [] && ($grupo['unidades'] ?? []) === []) {
                throw ValidationException::withMessages([
                    "grupos.{$index}.unidades" => 'Asigna al menos un extintor a este grupo.',
                ]);
            }
        }

        $tipos = CertificateType::query()->get()->keyBy('codigo');
        $company = CompanySetting::current();

        return DB::transaction(function () use ($sale, $grupos, $tipos, $company, $motivo, $userId) {
            $anteriores = Certificate::query()
                ->with('certificateType')
                ->where('sale_id', $sale->id)
                ->whereIn('estado', ['vigente', 'vencido'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->groupBy(fn (Certificate $certificate) => $certificate->certificateType->codigo);

            $emitidos = collect();

            foreach ($grupos as $grupo) {
                $equipos = Equipment::query()
                    ->whereIn('id', array_column($grupo['unidades'] ?? [], 'equipment_id'))
                    ->get()
                    ->keyBy('id');

                foreach (array_unique($grupo['tipos']) as $codigo) {
                    $unidades = $codigo === 'capacitacion'
                        ? []
                        : array_map(fn (array $unidad) => $this->unidad(
                            $unidad,
                            $equipos->get($unidad['equipment_id']),
                            $codigo,
                        ), $grupo['unidades']);

                    $extra = [
                        'referencia' => $grupo['referencia'] ?? $sale->referencia,
                        'direccion' => $grupo['direccion'] ?? null,
                        'tipo_atencion' => 'venta',
                        'datos' => $codigo === 'capacitacion' ? [
                            'curso' => $grupo['capacitacion']['curso'] ?? 'USO Y MANEJO DE EXTINTORES',
                            'horas' => (int) ($grupo['capacitacion']['horas'] ?? 4),
                            'instructor' => $grupo['capacitacion']['instructor'] ?? $company->instructor_capacitacion,
                            'modo' => $grupo['capacitacion']['modo'] ?? 'normal',
                            'fotos' => [],
                        ] : null,
                    ];

                    $anterior = $anteriores->get($codigo)?->shift();

                    if ($codigo === 'capacitacion' && $anterior) {
                        $extra['datos']['fotos'] = $anterior->datos['fotos'] ?? [];
                    }

                    $certificate = $anterior
                        ? $this->issueCertificate->corregir($anterior, $unidades, $extra, $motivo, $userId)
                        : $this->issueCertificate->handle($tipos[$codigo], $sale->client, $unidades, saleId: $sale->id, extra: $extra);

                    if ($codigo === 'capacitacion') {
                        $this->syncTraining->handle($certificate, $grupo['capacitacion'] ?? []);
                    }

                    $emitidos->push($certificate->refresh());
                }
            }

            $anteriores->flatten(1)->each(fn (Certificate $sobrante) => $sobrante->update([
                'estado' => 'anulado',
                'anulado_motivo' => 'Ya no corresponde: se reorganizaron los certificados de la venta.',
                'anulado_at' => now(),
                'anulado_por' => $userId,
            ]));

            return $emitidos;
        });
    }

    /**
     * @param  array{equipment_id: int, numero_cliente?: string|null}  $unidad
     * @return array<string, mixed>
     */
    protected function unidad(array $unidad, ?Equipment $equipo, string $codigo): array
    {
        $esCo2 = str_contains(strtoupper((string) $equipo?->tipo_agente), 'CO2');

        return [
            'equipment_id' => $unidad['equipment_id'],
            'numero_cliente' => $unidad['numero_cliente'] ?? null,
            'fecha_ultima_ph' => $codigo === 'prueba_hidrostatica' ? now()->toDateString() : null,
            'fecha_ultima_recarga' => now()->toDateString(),
            'presion_ph' => $codigo === 'prueba_hidrostatica' ? ($esCo2 ? '3000 PSI' : '600 PSI') : null,
            'tiempo_ph' => $codigo === 'prueba_hidrostatica' ? '60 SEG' : null,
            'presion_trabajo' => $codigo === 'prueba_hidrostatica' ? ($esCo2 ? '850 PSI' : '195 PSI') : null,
        ];
    }
}
