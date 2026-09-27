<?php

namespace App\Services\Sunat;

use App\Models\Client;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RucLookupService
{
    /**
     * @return array{
     *     razon_social: string,
     *     direccion: ?string,
     *     estado_contribuyente: ?string,
     *     condicion_domicilio: ?string
     * }
     */
    public function lookup(string $numeroDocumento, bool $refresh = false): array
    {
        $client = Client::where('numero_documento', $numeroDocumento)->first();

        if ($client && ! $refresh) {
            return [
                'razon_social' => $client->razon_social,
                'direccion' => $client->direccion_fiscal,
                'estado_contribuyente' => $client->estado_contribuyente,
                'condicion_domicilio' => $client->condicion_domicilio,
            ];
        }

        $token = config('services.apisperu.token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('El token de APIsPeru no esta configurado.');
        }

        $endpoint = match (mb_strlen($numeroDocumento)) {
            8 => "https://dniruc.apisperu.com/api/v1/dni/{$numeroDocumento}",
            11 => "https://dniruc.apisperu.com/api/v1/ruc/{$numeroDocumento}",
            default => throw new RuntimeException('El documento debe tener 8 digitos para DNI o 11 digitos para RUC.'),
        };

        $data = Http::connectTimeout(3)
            ->timeout(8)
            ->get($endpoint, ['token' => $token])
            ->throw()
            ->json();

        if (! is_array($data)) {
            throw new RuntimeException('APIsPeru devolvio una respuesta invalida.');
        }

        return mb_strlen($numeroDocumento) === 11
            ? $this->mapRucResponse($data)
            : $this->mapDniResponse($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{razon_social: string, direccion: ?string, estado_contribuyente: ?string, condicion_domicilio: ?string}
     */
    protected function mapRucResponse(array $data): array
    {
        return [
            'razon_social' => (string) ($data['razonSocial'] ?? ''),
            'direccion' => $this->nullableString($data['direccion'] ?? null),
            'estado_contribuyente' => $this->nullableString($data['estado'] ?? null),
            'condicion_domicilio' => $this->nullableString($data['condicion'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{razon_social: string, direccion: ?string, estado_contribuyente: ?string, condicion_domicilio: ?string}
     */
    protected function mapDniResponse(array $data): array
    {
        $names = array_filter([
            $data['nombres'] ?? null,
            $data['apellidoPaterno'] ?? null,
            $data['apellidoMaterno'] ?? null,
        ]);

        return [
            'razon_social' => implode(' ', $names),
            'direccion' => null,
            'estado_contribuyente' => null,
            'condicion_domicilio' => null,
        ];
    }

    protected function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value) || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
