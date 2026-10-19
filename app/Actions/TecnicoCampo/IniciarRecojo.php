<?php

namespace App\Actions\TecnicoCampo;

use App\Models\Client;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Services\ServiceOrders\ServiceOrderNumberGenerator;
use App\Support\DatosPeru;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
                    'cantidad' => (int) $data['cantidad'],
                ]);
            }

            $order->events()->create([
                'tipo' => 'creada',
                'user_id' => $tecnico->id,
                'payload' => [
                    'origen' => 'campo',
                    'accion' => 'recojo_iniciado_en_campo',
                    'referencia' => $data['referencia'],
                    'cantidad_esperada' => $data['cantidad'],
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
        if (filled($data['client_id'] ?? null)) {
            $client = Client::query()->lockForUpdate()->findOrFail((int) $data['client_id']);
            if (! $client->activo || $client->esClientesVarios()) {
                throw ValidationException::withMessages(['client_id' => 'Selecciona un cliente activo con identidad propia.']);
            }

            return $client;
        }

        /** @var array{razon_social: string, telefono: string, tipo_documento?: string|null, numero_documento?: string|null} $cliente */
        $cliente = $data['cliente'];
        $documento = DatosPeru::digitos($cliente['numero_documento'] ?? null);

        if ($documento !== null) {
            $existente = Client::query()
                ->where('numero_documento', $documento)
                ->lockForUpdate()
                ->first();

            if ($existente !== null) {
                if (! $existente->activo || $existente->esClientesVarios()) {
                    throw ValidationException::withMessages(['cliente.numero_documento' => 'Ese documento está asociado a un registro que no se puede usar para un recojo. Busca al cliente correcto.']);
                }

                return $existente;
            }
        }

        $mismoContacto = $this->clientWithSameNameAndPhone($cliente['razon_social'], $cliente['telefono']);
        if ($mismoContacto !== null) {
            if (filled($mismoContacto->numero_documento) && $documento !== null && $mismoContacto->numero_documento !== $documento) {
                throw ValidationException::withMessages(['cliente.numero_documento' => 'El nombre y teléfono coinciden con un cliente que tiene otro documento. Busca y selecciona el registro correcto.']);
            }

            if ($documento !== null && $mismoContacto->numero_documento === null) {
                $mismoContacto->update([
                    'tipo_documento' => $cliente['tipo_documento'] ?? null,
                    'numero_documento' => $documento,
                ]);
            }

            return $mismoContacto;
        }

        $client = Client::create([
            'codigo_interno' => 'CLI-TMP-'.Str::uuid()->toString(),
            'tipo_documento' => $documento === null ? null : ($cliente['tipo_documento'] ?? null),
            'numero_documento' => $documento,
            'razon_social' => $cliente['razon_social'],
            'telefono' => $cliente['telefono'],
            'activo' => true,
        ]);
        $client->update(['codigo_interno' => 'CLI-'.str_pad((string) $client->id, 4, '0', STR_PAD_LEFT)]);

        return $client;
    }

    private function clientWithSameNameAndPhone(string $razonSocial, string $telefono): ?Client
    {
        $telefono = DatosPeru::telefono($telefono) ?? '';
        $candidatos = Client::query()
            ->whereRaw('LOWER(razon_social) = ?', [mb_strtolower(trim($razonSocial))])
            ->where('activo', true)
            ->where(function ($query): void {
                $query->whereNull('tipo_documento')->orWhere('tipo_documento', '!=', Client::TIPO_DOCUMENTO_VARIOS);
            })
            ->get(['id', 'telefono']);
        $id = $candidatos
            ->first(fn (Client $client): bool => DatosPeru::telefono($client->telefono) === $telefono)
            ?->id;

        return $id === null
            ? null
            : Client::query()->lockForUpdate()->whereKey($id)->first();
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
