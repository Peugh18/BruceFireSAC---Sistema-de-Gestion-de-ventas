<?php

namespace App\Actions\Certificates;

use App\Enums\EquipmentType;
use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\CertificateUnit;
use App\Models\CompanySetting;
use App\Models\Equipment;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\Certificates\CertificadosDeVenta;
use App\Services\Certificates\CertificateDateCalculator;
use App\Services\Certificates\PruebasHidrostaticas;
use App\Services\Certificates\TipoAtencionExtintor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmitirCertificadosDeVenta
{
    public const MOTIVO_AJUSTE = 'Ajuste de orden, numeración o agrupación de los extintores.';

    public function __construct(
        protected IssueCertificate $issueCertificate,
        protected SyncCertificateTraining $syncTraining,
        protected EmitirCertificadosDeProducto $certificadosDeProducto,
    ) {}

    /**
     * La prueba hidrostática necesita datos técnicos reales (fecha, presión,
     * tiempo y resultado): no sale sola al cobrar (C2). La capacitación sale
     * con el curso y las horas de la empresa; los participantes son opcionales.
     */
    public const REQUIEREN_DATOS_TECNICOS = ['prueba_hidrostatica'];

    /**
     * Datos de la capacitación: lo que se armó en la pantalla o, si no, el
     * curso, las horas y el instructor de la empresa.
     *
     * @param  array<string, mixed>|null  $capacitacion
     * @return array<string, mixed>
     */
    public static function datosDeCapacitacion(?array $capacitacion, CompanySetting $company): array
    {
        return [
            'curso' => $capacitacion['curso'] ?? 'USO Y MANEJO DE EXTINTORES',
            'horas' => (int) ($capacitacion['horas'] ?? 4),
            'instructor' => $capacitacion['instructor'] ?? $company->instructor_capacitacion,
            'modo' => $capacitacion['modo'] ?? 'normal',
            'fotos' => [],
        ];
    }

    /** Si la venta o la orden de taller ya dejó la capacitación del cliente. */
    public static function tieneCapacitacion(Sale $sale): bool
    {
        return CertificadosDeVenta::propios($sale)
            ->whereIn('estado', ['vigente', 'vencido'])
            ->whereHas('certificateType', fn ($query) => $query->where('codigo', 'capacitacion'))
            ->exists();
    }

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
     * se emite nada y queda pendiente: la venta no se bloquea (C1). También
     * salen los certificados de los productos con ficha técnica.
     *
     * @return Collection<int, Certificate>
     */
    public function automaticos(Sale $sale): Collection
    {
        if ($sale->estado !== 'confirmada') {
            return collect();
        }
        $this->certificadosDeProducto->handle($sale);
        $sale->loadMissing('items.equipment');
        $reutilizados = collect();
        if ($sale->destino === 'vehiculo') {
            $reutilizados = PruebasHidrostaticas::vigentes($sale->client_id, array_values($sale->items->map(fn (SaleItem $item) => $item->equipment_id)->filter()->all()))
                ->pluck('certificate')->unique('id')->values();
            $reutilizados->each(fn (Certificate $certificate) => $certificate->ventasReutilizadas()->syncWithoutDetaching([$sale->id]));
        }
        $equipos = $sale->items
            ->filter(fn (SaleItem $item) => $item->tipo_linea === 'unidad_nueva')
            ->sortBy('id')
            ->pluck('equipment')
            ->filter()
            ->values();
        $tipos = array_values(array_filter(
            array_diff(self::tiposPorDestino((string) $sale->destino), self::REQUIEREN_DATOS_TECNICOS, self::tieneCapacitacion($sale) ? ['capacitacion'] : []),
            fn (string $codigo) => CertificateType::query()->where('codigo', $codigo)->exists(),
        ));

        if ($equipos->isEmpty() || $tipos === [] || $equipos->contains(fn (Equipment $equipo) => ! EquipmentType::esConocido($equipo->tipo_agente))) {
            return $reutilizados;
        }

        return $reutilizados->concat($this->handle($sale, [[
            'referencia' => $sale->referencia,
            'destino' => $sale->destino,
            'automatico' => true,
            'tipos' => $tipos,
            'unidades' => array_values($equipos->map(fn (Equipment $equipo) => [
                'equipment_id' => $equipo->id,
                'numero_cliente' => $equipo->numero_cliente,
            ])->all()),
        ]]))->unique('id')->values();
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

        return DB::transaction(function () use ($sale, $grupos, $motivo, $userId) {
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);
            if ($sale->estado !== 'confirmada') {
                throw ValidationException::withMessages(['grupos' => 'Solo se emiten certificados de una venta confirmada.']);
            }
            $equipmentIds = array_values(array_unique(array_merge(...array_map(fn (array $grupo) => array_column($grupo['unidades'], 'equipment_id'), $grupos))));
            Equipment::query()->whereIn('id', $equipmentIds)->orderBy('id')->lockForUpdate()->get();
            $sale->load('client', 'items.service');
            $equiposDeLaVenta = array_values(CertificadosDeVenta::equipos($sale)->map(fn (Equipment $equipo): int => $equipo->id)->all());

            foreach ($grupos as $index => $grupo) {
                $ajenos = array_diff(array_column($grupo['unidades'], 'equipment_id'), $equiposDeLaVenta);

                if ($ajenos !== []) {
                    throw ValidationException::withMessages([
                        "grupos.{$index}.unidades" => 'Hay extintores que no pertenecen a esta venta.',
                    ]);
                }

                $conUnidades = array_diff($grupo['tipos'], ['capacitacion']);
                if ($conUnidades !== [] && Equipment::whereIn('id', array_column($grupo['unidades'], 'equipment_id'))->where('estado', 'baja')->exists()) {
                    throw ValidationException::withMessages([
                        "grupos.{$index}.unidades" => 'Un extintor dado de baja no se certifica.',
                    ]);
                }

                if ($conUnidades !== [] && $grupo['unidades'] === []) {
                    throw ValidationException::withMessages([
                        "grupos.{$index}.unidades" => 'Asigna al menos un extintor a este grupo.',
                    ]);
                }
            }

            $tipos = CertificateType::query()->get()->keyBy('codigo');
            $company = CompanySetting::current();
            $pruebas = PruebasHidrostaticas::vigentes($sale->client_id, $equiposDeLaVenta);
            $grupos = $this->conDatosDePrueba($grupos, $pruebas);
            $anteriores = CertificadosDeVenta::propios($sale)
                ->whereHas('certificateType', fn ($query) => $query->whereIn('codigo', ['operatividad_garantia', 'capacitacion']))
                ->with('certificateType')
                ->whereIn('estado', ['vigente', 'vencido'])
                ->when(collect($grupos)->contains(fn (array $grupo) => ! empty($grupo['automatico'])), fn ($query) => $query->whereNull('service_order_id'))
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
                    $unidadesDelGrupo = $grupo['unidades'];
                    if ($codigo === 'prueba_hidrostatica') {
                        foreach ($unidadesDelGrupo as $unidad) {
                            $original = $pruebas->get($unidad['equipment_id'])?->certificate;
                            if ($original) {
                                $original->ventasReutilizadas()->syncWithoutDetaching([$sale->id]);
                                $emitidos->push($original);
                            }
                        }
                        $unidadesDelGrupo = array_values(array_filter($unidadesDelGrupo, fn (array $unidad) => ! $pruebas->has($unidad['equipment_id'])));
                        if ($unidadesDelGrupo === []) {
                            continue;
                        }
                    }
                    $tipoAtencion = TipoAtencionExtintor::deVenta($sale, array_column($unidadesDelGrupo, 'equipment_id'));
                    $unidades = $codigo === 'capacitacion'
                        ? []
                        : array_map(fn (array $unidad) => $this->unidad(
                            $unidad,
                            $equipos->get($unidad['equipment_id']),
                            $codigo,
                            TipoAtencionExtintor::deVenta($sale, [$unidad['equipment_id']]),
                            CertificadosDeVenta::fechaRecarga($sale, $unidad['equipment_id']),
                        ), $unidadesDelGrupo);

                    $destino = $grupo['destino'] ?? (in_array('prueba_hidrostatica', $grupo['tipos'], true) ? 'vehiculo' : (in_array('capacitacion', $grupo['tipos'], true) ? 'local_cliente' : $sale->destino));
                    $extra = [
                        'referencia' => $grupo['referencia'] ?? $sale->referencia,
                        'direccion' => $grupo['direccion'] ?? null,
                        'tipo_atencion' => $tipoAtencion,
                        'datos' => $codigo === 'capacitacion' ? self::datosDeCapacitacion($grupo['capacitacion'] ?? null, $company) : ($codigo === 'operatividad_garantia' ? ['destino' => $destino, 'tipos' => ! empty($grupo['automatico']) ? self::tiposPorDestino((string) $destino) : array_values(array_unique($grupo['tipos']))] : null),
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

            return $emitidos->unique('id')->values();
        });
    }

    /**
     * La prueba hidrostática se certifica solo con la fecha, la presión, el
     * tiempo y el resultado que registró un técnico o el vendedor (C2). Si un
     * extintor tiene PH vigente, se vincula su certificado original sin copiarlo.
     *
     * @param  list<array<string, mixed>>  $grupos
     * @param  Collection<int, CertificateUnit>  $pruebas
     * @return list<array<string, mixed>>
     */
    protected function conDatosDePrueba(array $grupos, Collection $pruebas): array
    {
        foreach ($grupos as $index => $grupo) {
            if (! in_array('prueba_hidrostatica', $grupo['tipos'], true)) {
                continue;
            }

            foreach ($grupo['unidades'] ?? [] as $unidad) {
                if ($pruebas->has($unidad['equipment_id'])) {
                    continue;
                }
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

                $fecha = Carbon::parse($unidad['fecha_ultima_ph']);
                if ($fecha->isAfter(today()) || CertificateDateCalculator::proximaPruebaHidrostatica($fecha)->lessThanOrEqualTo(today())) {
                    throw ValidationException::withMessages([
                        "grupos.{$index}.unidades" => 'Registra una prueba hidrostática real que siga vigente según el intervalo configurado.',
                    ]);
                }
            }
        }

        return $grupos;
    }

    /**
     * @param  array<string, mixed>  $unidad
     * @return array<string, mixed>
     */
    protected function unidad(array $unidad, ?Equipment $equipo, string $codigo, string $tipoAtencion, ?string $fechaRecarga): array
    {
        $esPrueba = $codigo === 'prueba_hidrostatica';

        return [
            'equipment_id' => $unidad['equipment_id'],
            'numero_cliente' => $unidad['numero_cliente'] ?? null,
            'fecha_ultima_ph' => $esPrueba ? $unidad['fecha_ultima_ph'] : null,
            'fecha_ultima_recarga' => $tipoAtencion === 'recarga' ? $fechaRecarga : null,
            'presion_ph' => $esPrueba ? $unidad['presion_ph'] : null,
            'tiempo_ph' => $esPrueba ? $unidad['tiempo_ph'] : null,
            // La presión de trabajo es la nominal del agente real del extintor.
            'presion_trabajo' => $esPrueba ? EquipmentType::fromDescription($equipo?->tipo_agente)->presionTrabajo() : null,
        ];
    }
}
