<?php

namespace App\Actions\Clientes;

use App\Models\Client;
use App\Services\Sunat\RucLookupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateClient
{
    public function __construct(protected RucLookupService $sunat) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Client
    {
        $data = [...$data, ...self::estadoSunat($this->sunat, $data)];

        return DB::transaction(function () use ($data) {
            $client = Client::create([
                ...$data,
                'codigo_interno' => 'CLI-TMP-'.Str::uuid()->toString(),
            ]);

            $client->update([
                'codigo_interno' => 'CLI-'.str_pad((string) $client->id, 4, '0', STR_PAD_LEFT),
            ]);

            return $client;
        });
    }

    /**
     * Estado y condición ante SUNAT del RUC según la consulta que hizo el
     * servidor (nunca lo que escriba el vendedor). Sin consulta quedan vacíos
     * y la factura pide verificar el RUC.
     *
     * @param  array<string, mixed>  $data
     * @return array{estado_contribuyente: ?string, condicion_domicilio: ?string, consultado_at: mixed}
     */
    public static function estadoSunat(RucLookupService $sunat, array $data): array
    {
        $consulta = ($data['tipo_documento'] ?? null) === 'ruc'
            ? $sunat->consultaReciente((string) ($data['numero_documento'] ?? ''))
            : null;

        return [
            'estado_contribuyente' => $consulta['estado_contribuyente'] ?? null,
            'condicion_domicilio' => $consulta['condicion_domicilio'] ?? null,
            'consultado_at' => $consulta ? now() : null,
        ];
    }
}
