<?php

namespace App\Actions\Certificates;

use App\Enums\EquipmentType;
use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\CompanySetting;
use App\Models\Equipment;
use App\Models\Sale;
use App\Models\SaleItem;
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
     * Certificados que necesitan datos técnicos reales (fecha, presión,
     * resultado o la capacitación dada): no salen solos al cobrar (C2).
     */
    public const REQUIEREN_DATOS_TECNICOS = ['prueba_hidrostatica', 'capacitacion'];

    /**
     * Única regla de certificados de una venta: los tipos que corresponden
     * según el destino de los equipos. Los que piden datos técnicos quedan
     * pendientes hasta que se registren en "Armar certificados".
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
     * Lo único que sale solo al confirmar la venta: el certificado de
     * operatividad y garantía de los extintores NUEVOS, en el orden en que se
     * escanearon (C2). La prueba hidrostática y la capacitación quedan
     * pendientes de datos técnicos. Si algún extintor no tiene su agente, no
     * se emite nada y queda pendiente: la venta no se bloquea (C1).
     *
     * @return Collection<int, Certificate>
     */
    public function automaticos(Sale $sale): Collection
    {
        $sale->loadMissing('items.equipment');
        $equipos = $sale->items
            ->filter(fn (SaleItem $item) => $item->tipo_linea === 'unidad_nueva')
            ->sortBy('id')
            ->pluck('equipment')
            ->filter()
            ->values();
        $tipos = array_values(array_filter(
            array_diff(self::tiposPorDestino((string) $sale->destino), self::REQUIEREN_DATOS_TECNICOS),
            fn (string $codigo) => CertificateType::query()->where('codigo', $codigo)->exists(),
        ));

        if ($equipos->isEmpty() || $tipos === [] || $equipos->contains(fn (Equipment $equipo) => ! EquipmentType::esConocido($equipo->tipo_agente))) {
            return collect();
        }

        return $this->handle($sale, [[
            'referencia' => $sale->referencia,
            'tipos' => $tipos,
            'unidades' => array_values($equipos->map(fn (Equipment $equipo) => [
                'equipment_id' => $equipo->id,
                'numero_cliente' => $equipo->numero_cliente,
            ])->all()),
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
     *     unidades: list<array{equipment_id: int, numero_cliente?: string|null, fecha_ultima_ph?: string|null, presion_ph?: string|null, tiempo_ph?: string|null, resultado_ph?: string|null}>,
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
            $ajenos = array_diff(array_column($grupo['unidades'], 'equipment_id'), $equiposDeLaVenta);

            if ($ajenos !== []) {
                throw ValidationException::withMessages([
                    "grupos.{$index}.unidades" => 'Hay extintores que no pertenecen a esta venta.',
                ]);
            }

            $conUnidades = array_diff($grupo['tipos'], ['capacitacion']);

            if ($conUnidades !== [] && $grupo['unidades'] === []) {
                throw ValidationException::withMessages([
                    "grupos.{$index}.unidades" => 'Asigna al menos un extintor a este grupo.',
                ]);
            }
        }

        $grupos = $this->conDatosDePrueba($sale, $grupos);
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
     * La prueba hidrostática se certifica solo con la fecha, la presión, el
     * tiempo y el resultado que registró un técnico o el vendedor (C2). Si un
     * ajuste no los vuelve a enviar, se conservan los del certificado vigente.
     *
     * @param  list<array<string, mixed>>  $grupos
     * @return list<array<string, mixed>>
     */
    protected function conDatosDePrueba(Sale $sale, array $grupos): array
    {
        $vigentes = Certificate::query()
            ->with('certificateUnits')
            ->where('sale_id', $sale->id)
            ->whereIn('estado', ['vigente', 'vencido'])
            ->whereHas('certificateType', fn ($query) => $query->where('codigo', 'prueba_hidrostatica'))
            ->get()
            ->flatMap(fn (Certificate $certificate) => $certificate->certificateUnits)
            ->keyBy('equipment_id');

        foreach ($grupos as $index => $grupo) {
            if (! in_array('prueba_hidrostatica', $grupo['tipos'], true)) {
                continue;
            }

            foreach ($grupo['unidades'] ?? [] as $posicion => $unidad) {
                $anterior = $vigentes->get($unidad['equipment_id']);
                $unidad += array_filter([
                    'fecha_ultima_ph' => $anterior?->fecha_ultima_ph?->toDateString(),
                    'presion_ph' => $anterior?->presion_ph,
                    'tiempo_ph' => $anterior?->tiempo_ph,
                    'resultado_ph' => $anterior ? 'aprobado' : null,
                ]);

                $faltaAlgo = collect(['fecha_ultima_ph', 'presion_ph', 'tiempo_ph', 'resultado_ph'])
                    ->contains(fn (string $campo) => blank($unidad[$campo] ?? null));

                if ($faltaAlgo) {
                    throw ValidationException::withMessages([
                        "grupos.{$index}.unidades" => 'Para la prueba hidrostática registra de cada extintor la fecha, la presión, el tiempo y el resultado reales de la prueba.',
                    ]);
                }

                if ($unidad['resultado_ph'] !== 'aprobado') {
                    throw ValidationException::withMessages([
                        "grupos.{$index}.unidades" => 'Un extintor que no aprobó la prueba hidrostática no se certifica.',
                    ]);
                }

                $grupos[$index]['unidades'][$posicion] = $unidad;
            }
        }

        return $grupos;
    }

    /**
     * @param  array<string, mixed>  $unidad
     * @return array<string, mixed>
     */
    protected function unidad(array $unidad, ?Equipment $equipo, string $codigo): array
    {
        $esPrueba = $codigo === 'prueba_hidrostatica';

        return [
            'equipment_id' => $unidad['equipment_id'],
            'numero_cliente' => $unidad['numero_cliente'] ?? null,
            'fecha_ultima_ph' => $esPrueba ? $unidad['fecha_ultima_ph'] : null,
            'fecha_ultima_recarga' => now()->toDateString(),
            'presion_ph' => $esPrueba ? $unidad['presion_ph'] : null,
            'tiempo_ph' => $esPrueba ? $unidad['tiempo_ph'] : null,
            // La presión de trabajo es la nominal del agente real del extintor.
            'presion_trabajo' => $esPrueba ? EquipmentType::fromDescription($equipo?->tipo_agente)->presionTrabajo() : null,
        ];
    }
}
