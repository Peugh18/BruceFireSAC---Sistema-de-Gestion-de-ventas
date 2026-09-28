<?php

namespace App\Actions\Certificates;

use App\Models\Certificate;
use App\Models\CertificateRevision;
use App\Models\CertificateType;
use App\Models\CertificateTypeVersion;
use App\Models\CertificateUnit;
use App\Models\Client;
use App\Models\Equipment;
use App\Models\ServiceOrder;
use App\Services\AuditLogger;
use App\Services\Certificates\CertificateDateCalculator;
use App\Services\Certificates\CertificateNumberGenerator;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IssueCertificate
{
    public function __construct(protected CertificateNumberGenerator $numeros) {}

    /**
     * Emite un nuevo certificado con sus unidades técnicas asociadas.
     *
     * @param  array<int, array{
     *     numero_serie?: string,
     *     numero_serie_snapshot?: string,
     *     fecha_ultima_ph?: string|null,
     *     fecha_ultima_recarga?: string|null,
     *     equipment_id?: int|null,
     *     numero_cliente?: string|null,
     *     presion_ph?: string|null,
     *     tiempo_ph?: string|null,
     *     presion_trabajo?: string|null
     * }>  $unidades
     * @param  array{referencia?: string|null, direccion?: string|null, tipo_atencion?: string|null, datos?: array<string, mixed>|null}  $extra
     */
    public function handle(
        CertificateType $tipo,
        Client $client,
        array $unidades,
        ?int $saleId = null,
        ?int $serviceOrderId = null,
        array $extra = [],
    ): Certificate {
        return DB::transaction(function () use ($tipo, $client, $unidades, $saleId, $serviceOrderId, $extra) {
            $numero = $this->numeros->siguiente($tipo);

            $fechaEmision = now()->toDateString();
            $fechaVigenciaHasta = $this->calcularVigenciaHasta($tipo, $unidades);

            $certificate = Certificate::create([
                'numero' => $numero,
                'certificate_type_id' => $tipo->id,
                'certificate_type_version_id' => $this->versionVigente($tipo)->id,
                'client_id' => $client->id,
                'fecha_emision' => $fechaEmision,
                'fecha_vigencia_hasta' => $fechaVigenciaHasta?->toDateString(),
                'referencia' => $extra['referencia'] ?? ($serviceOrderId ? ServiceOrder::find($serviceOrderId)?->referencia : null),
                'direccion' => $extra['direccion'] ?? $client->direccion_fiscal,
                'tipo_atencion' => $extra['tipo_atencion'] ?? ($serviceOrderId ? 'recarga' : null),
                'datos' => $extra['datos'] ?? null,
                'estado' => 'vigente',
                'qr_token' => (string) Str::uuid(),
                'sale_id' => $saleId,
                'service_order_id' => $serviceOrderId,
            ]);

            $this->crearUnidades($certificate, $unidades);

            AuditLogger::log(
                action: 'certificado.emitido',
                entity: $certificate,
                newValues: [
                    'numero' => $numero,
                    'tipo' => $tipo->codigo,
                    'client_id' => $client->id,
                    'vigencia_hasta' => $certificate->fecha_vigencia_hasta?->toDateString(),
                ]
            );

            return $certificate->load('certificateUnits');
        });
    }

    /**
     * Corrige un certificado ya emitido sin cambiarle el número: reemplaza sus
     * extintores (orden, numeración del cliente, grupo), su referencia y su
     * dirección, y deja una revisión con el motivo y lo que cambió. Si no
     * cambió nada, no crea revisión.
     *
     * @param  array<int, array<string, mixed>>  $unidades
     * @param  array{referencia?: string|null, direccion?: string|null, datos?: array<string, mixed>|null}  $extra
     */
    public function corregir(Certificate $certificate, array $unidades, array $extra, string $motivo, ?int $userId = null): Certificate
    {
        return DB::transaction(function () use ($certificate, $unidades, $extra, $motivo, $userId) {
            $antes = $this->resumen($certificate->load('certificateUnits'));

            $certificate->update([
                'referencia' => $extra['referencia'] ?? $certificate->referencia,
                'direccion' => $extra['direccion'] ?? $certificate->direccion,
                'datos' => array_key_exists('datos', $extra) ? $extra['datos'] : $certificate->datos,
            ]);

            $certificate->certificateUnits()->delete();
            $this->crearUnidades($certificate, $unidades);

            $despues = $this->resumen($certificate->load('certificateUnits'));

            if ($this->normalizar($antes) === $this->normalizar($despues)) {
                return $certificate;
            }

            $certificate->increment('revision');

            CertificateRevision::create([
                'certificate_id' => $certificate->id,
                'numero_revision' => $certificate->revision,
                'user_id' => $userId,
                'motivo' => $motivo,
                'antes' => $antes,
                'despues' => $despues,
            ]);

            AuditLogger::log(
                action: 'certificado.corregido',
                entity: $certificate,
                newValues: ['numero' => $certificate->numero, 'revision' => $certificate->revision, 'motivo' => $motivo],
                userId: $userId,
            );

            return $certificate->refresh()->load('certificateUnits');
        });
    }

    /**
     * Lo que se compara y se guarda en cada revisión.
     *
     * @return array{referencia: string|null, direccion: string|null, datos: array<string, mixed>|null, unidades: list<array{orden: int|null, numero_cliente: string|null, serie: string|null}>}
     */
    protected function resumen(Certificate $certificate): array
    {
        return [
            'referencia' => $certificate->referencia,
            'direccion' => $certificate->direccion,
            'datos' => $certificate->datos,
            'unidades' => $certificate->certificateUnits
                ->sortBy('orden')
                ->map(fn (CertificateUnit $unit) => [
                    'orden' => $unit->orden,
                    'numero_cliente' => $unit->numero_cliente,
                    'serie' => $unit->numero_serie_snapshot,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Los datos guardados como JSON vuelven de MySQL con otro orden de claves
     * y los números como texto: se ordenan y se pasan a texto para comparar
     * solo el contenido.
     */
    protected function normalizar(mixed $valor): mixed
    {
        if (! is_array($valor)) {
            return $valor === null ? null : (string) $valor;
        }

        $normalizado = array_map(fn (mixed $item) => $this->normalizar($item), $valor);

        if (! array_is_list($normalizado)) {
            ksort($normalizado);
        }

        return $normalizado;
    }

    /**
     * @param  array<int, array<string, mixed>>  $unidades
     */
    protected function crearUnidades(Certificate $certificate, array $unidades): void
    {
        $equipos = Equipment::query()
            ->whereIn('id', array_filter(array_column($unidades, 'equipment_id')))
            ->get()
            ->keyBy('id');

        foreach (array_values($unidades) as $indice => $u) {
            $equipo = $equipos->get($u['equipment_id'] ?? 0);
            $numeroCliente = $u['numero_cliente'] ?? $equipo?->numero_cliente;

            if ($equipo && $numeroCliente !== $equipo->numero_cliente) {
                $equipo->update(['numero_cliente' => $numeroCliente]);
            }

            CertificateUnit::create([
                'certificate_id' => $certificate->id,
                'equipment_id' => $equipo?->id,
                'orden' => $indice + 1,
                'numero_cliente' => $numeroCliente,
                'numero_serie_snapshot' => $u['numero_serie'] ?? ($u['numero_serie_snapshot'] ?? ($equipo?->serie_fabricante ?: $equipo?->numero_serie ?? '')),
                'capacidad' => $u['capacidad'] ?? $equipo?->capacidad,
                'marca' => $u['marca'] ?? $equipo?->marca,
                'tipo_agente' => $u['tipo_agente'] ?? ($equipo?->tipo_agente ?: 'PQS-ABC'),
                'anio_fabricacion' => $u['anio_fabricacion'] ?? $equipo?->anio_fabricacion,
                'fecha_ultima_ph' => ! empty($u['fecha_ultima_ph']) ? Carbon::parse($u['fecha_ultima_ph']) : null,
                'fecha_ultima_recarga' => ! empty($u['fecha_ultima_recarga']) ? Carbon::parse($u['fecha_ultima_recarga']) : null,
                'presion_ph' => $u['presion_ph'] ?? null,
                'tiempo_ph' => $u['tiempo_ph'] ?? null,
                'presion_trabajo' => $u['presion_trabajo'] ?? null,
            ]);
        }
    }

    /**
     * Versión de la plantilla con la que se emite: si la configuración actual
     * todavía no tiene su foto, se guarda ahora.
     */
    protected function versionVigente(CertificateType $tipo): CertificateTypeVersion
    {
        return CertificateTypeVersion::firstOrCreate(
            ['certificate_type_id' => $tipo->id, 'version' => $tipo->version ?? 1],
            ['configuracion' => $tipo->loadMissing('signers')->configuracionActual()],
        );
    }

    /**
     * @param  array<int, array{fecha_ultima_ph?: string|null, fecha_ultima_recarga?: string|null}>  $unidades
     */
    protected function calcularVigenciaHasta(CertificateType $tipo, array $unidades): ?CarbonInterface
    {
        if ($tipo->vigencia_meses <= 0) {
            return null;
        }

        if ($tipo->codigo === 'prueba_hidrostatica') {
            $fechaBase = null;
            foreach ($unidades as $u) {
                if (! empty($u['fecha_ultima_ph'])) {
                    $fechaBase = Carbon::parse($u['fecha_ultima_ph']);
                    break;
                }
            }

            return CertificateDateCalculator::proximaPruebaHidrostatica($fechaBase ?? now());
        }

        $fechaBase = null;
        foreach ($unidades as $u) {
            if (! empty($u['fecha_ultima_recarga'])) {
                $fechaBase = Carbon::parse($u['fecha_ultima_recarga']);
                break;
            }
        }

        return CertificateDateCalculator::proximaOperatividad($fechaBase ?? now());
    }
}
