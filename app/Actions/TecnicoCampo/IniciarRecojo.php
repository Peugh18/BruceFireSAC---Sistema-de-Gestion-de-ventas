<?php

namespace App\Actions\TecnicoCampo;

use App\Models\Client;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Services\ServiceOrders\ServiceOrderNumberGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IniciarRecojo
{
    public function __construct(private ServiceOrderNumberGenerator $numberGenerator) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, User $tecnico): ServiceOrder
    {
        return DB::transaction(function () use ($data, $tecnico): ServiceOrder {
            $client = $this->resolveClient($data);
            $service = isset($data['service_id']) ? Service::query()->findOrFail((int) $data['service_id']) : null;
            $observaciones = $this->observaciones($data);

            $order = ServiceOrder::create([
                'codigo' => $this->numberGenerator->next(),
                'client_id' => $client->id,
                'sede_id' => $data['sede_id'],
                'service_id' => $service?->id,
                'fecha' => now('America/Lima')->toDateString(),
                'tecnico_id' => $tecnico->id,
                'departamento_tecnico' => 'campo',
                'prioridad' => $data['prioridad'] ?? 'normal',
                'referencia' => $data['referencia'],
                'observaciones' => $observaciones,
                'estado' => 'pendiente_recepcion',
            ]);

            if ($service !== null) {
                $order->lines()->create([
                    'service_id' => $service->id,
                    'cantidad' => (int) ($data['cantidad'] ?? 1),
                ]);
            }

            $order->events()->create([
                'tipo' => 'creada',
                'user_id' => $tecnico->id,
                'payload' => [
                    'origen' => 'campo',
                    'accion' => 'recojo_iniciado_en_campo',
                    'referencia' => $data['referencia'],
                    'cantidad_esperada' => $data['cantidad'] ?? null,
                    'precio_acordado' => isset($data['precio_acordado']) ? (float) $data['precio_acordado'] : null,
                    'mensaje' => 'Recojo iniciado por Técnico de Campo. Pendiente de registrar equipos y evidencia de recojo.',
                ],
            ]);

            return $order;
        });
    }

    /** @param array<string, mixed> $data */
    private function resolveClient(array $data): Client
    {
        $client = Client::query()->lockForUpdate()->findOrFail((int) $data['client_id']);
        if (! $client->activo || $client->esClientesVarios()) {
            throw ValidationException::withMessages(['client_id' => 'Selecciona un cliente activo con identidad propia.']);
        }

        return $client;
    }

    /** @param array<string, mixed> $data */
    private function observaciones(array $data): ?string
    {
        $notas = trim((string) ($data['observaciones'] ?? ''));

        if (isset($data['precio_acordado'])) {
            $acuerdo = 'Precio acordado en campo: S/ '.number_format((float) $data['precio_acordado'], 2, '.', '').'.';
            $notas = $notas === '' ? $acuerdo : "{$notas}\n{$acuerdo}";
        }

        return $notas === '' ? null : $notas;
    }
}
