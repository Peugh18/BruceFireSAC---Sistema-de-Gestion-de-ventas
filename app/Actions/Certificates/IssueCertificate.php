<?php

namespace App\Actions\Certificates;

use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\CertificateUnit;
use App\Models\Client;
use App\Services\Certificates\CertificateDateCalculator;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IssueCertificate
{
    /**
     * Emite un nuevo certificado con sus unidades técnicas asociadas.
     *
     * @param  array<int, array{
     *     numero_serie?: string,
     *     numero_serie_snapshot?: string,
     *     fecha_ultima_ph?: string|null,
     *     fecha_ultima_recarga?: string|null,
     *     equipment_id?: int|null
     * }>  $unidades
     */
    public function handle(
        CertificateType $tipo,
        Client $client,
        array $unidades,
        ?int $saleId = null,
        ?int $serviceOrderId = null
    ): Certificate {
        return DB::transaction(function () use ($tipo, $client, $unidades, $saleId, $serviceOrderId) {
            $prefijo = $this->determinarPrefijo($tipo->codigo);
            $year = now()->year;
            $contador = Certificate::where('certificate_type_id', $tipo->id)->count() + 1;
            $numero = sprintf('%s-%s-%04d', $prefijo, $year, $contador);

            while (Certificate::where('numero', $numero)->exists()) {
                $contador++;
                $numero = sprintf('%s-%s-%04d', $prefijo, $year, $contador);
            }

            $fechaEmision = now()->toDateString();
            $fechaVigenciaHasta = $this->calcularVigenciaHasta($tipo, $unidades);

            $certificate = Certificate::create([
                'numero' => $numero,
                'certificate_type_id' => $tipo->id,
                'client_id' => $client->id,
                'fecha_emision' => $fechaEmision,
                'fecha_vigencia_hasta' => $fechaVigenciaHasta->toDateString(),
                'estado' => 'vigente',
                'qr_token' => (string) Str::uuid(),
                'sale_id' => $saleId,
                'service_order_id' => $serviceOrderId,
            ]);

            foreach ($unidades as $u) {
                CertificateUnit::create([
                    'certificate_id' => $certificate->id,
                    'equipment_id' => $u['equipment_id'] ?? null,
                    'numero_serie_snapshot' => $u['numero_serie'] ?? ($u['numero_serie_snapshot'] ?? ''),
                    'fecha_ultima_ph' => ! empty($u['fecha_ultima_ph']) ? Carbon::parse($u['fecha_ultima_ph']) : null,
                    'fecha_ultima_recarga' => ! empty($u['fecha_ultima_recarga']) ? Carbon::parse($u['fecha_ultima_recarga']) : null,
                ]);
            }

            return $certificate->load('certificateUnits');
        });
    }

    protected function determinarPrefijo(string $codigo): string
    {
        $parts = explode('_', $codigo);
        if (count($parts) >= 2) {
            return strtoupper(substr($parts[0], 0, 1).substr($parts[1], 0, 1));
        }

        return strtoupper(substr($codigo, 0, 2));
    }

    /**
     * @param  array<int, array{fecha_ultima_ph?: string|null, fecha_ultima_recarga?: string|null}>  $unidades
     */
    protected function calcularVigenciaHasta(CertificateType $tipo, array $unidades): CarbonInterface
    {
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
