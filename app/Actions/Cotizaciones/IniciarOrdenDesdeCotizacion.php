<?php

namespace App\Actions\Cotizaciones;

use App\Models\Quote;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Services\ServiceOrders\ServiceOrderNumberGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * D-10: una orden de servicio nace de una cotización aceptada (la excepción es
 * el recojo de campo, que se cotiza después en planta). La orden copia las
 * líneas y los extintores cotizados: nadie vuelve a escribirlos.
 */
class IniciarOrdenDesdeCotizacion
{
    public function __construct(private ServiceOrderNumberGenerator $numeracion) {}

    /**
     * @param  array{departamento_tecnico?: ?string, fecha?: mixed, prioridad?: ?string, tecnico_id?: ?int, observaciones?: ?string}  $opciones
     */
    public function handle(Quote $quote, User $user, array $opciones = []): ServiceOrder
    {
        return DB::transaction(function () use ($quote, $user, $opciones): ServiceOrder {
            $quote = Quote::query()->lockForUpdate()->findOrFail($quote->id);
            if ($quote->sale()->exists()) {
                throw ValidationException::withMessages(['quote_id' => "La cotización {$quote->numero} ya tiene venta."]);
            }
            $existente = $quote->serviceOrders()->where('estado', '!=', 'anulada')->first();
            if ($existente) {
                return $existente;
            }
            TransitionQuoteState::exigirAceptada($quote, 'abrir la orden de servicio');

            $servicios = $quote->items()->with('service')->whereNotNull('service_id')->orderBy('id')->get();
            if ($servicios->isEmpty()) {
                throw ValidationException::withMessages(['quote_id' => 'La cotización debe incluir un servicio para abrir una orden.']);
            }

            $orden = ServiceOrder::create([
                'codigo' => $this->numeracion->next(),
                'client_id' => $quote->client_id,
                'sede_id' => $quote->sede_id,
                'vehicle_id' => $quote->vehicle_id,
                'referencia' => $quote->referencia,
                'quote_id' => $quote->id,
                'service_id' => $servicios->first()->service_id,
                'fecha' => $opciones['fecha'] ?? today(),
                'departamento_tecnico' => $opciones['departamento_tecnico'] ?? 'planta',
                'prioridad' => $opciones['prioridad'] ?? 'normal',
                'tecnico_id' => $opciones['tecnico_id'] ?? null,
                'estado' => 'pendiente_recepcion',
                'observaciones' => $opciones['observaciones'] ?? 'Trabajo cotizado en '.$quote->numero.'; pendiente de venta al terminar.',
            ]);
            foreach ($servicios as $item) {
                $orden->lines()->create([
                    'service_id' => $item->service_id,
                    'cantidad' => (int) $item->cantidad,
                    'quote_item_id' => $item->id,
                ]);
            }
            $this->asignarEquiposCotizados($orden, $quote);
            $orden->events()->create(['tipo' => 'creada', 'user_id' => $user->id, 'payload' => [
                'mensaje' => 'Orden iniciada desde la cotización '.$quote->numero.' aceptada por '.$quote->aceptada_por.'.',
                'quote_id' => $quote->id,
            ]]);

            return $orden;
        });
    }

    private function asignarEquiposCotizados(ServiceOrder $orden, Quote $quote): void
    {
        $orden->load('lines.service');
        $posicion = 1;
        $asignados = [];

        foreach ($quote->equipments()->get() as $equipo) {
            $linea = $orden->lines->first(fn ($linea): bool => $linea->service
                && ($asignados[$linea->id] ?? 0) < $linea->cantidad
                && $linea->service->incompatibilidadConEquipo($equipo) === null) ?? $orden->lines->first();

            if (! $linea) {
                continue;
            }

            $asignados[$linea->id] = ($asignados[$linea->id] ?? 0) + 1;
            $orden->equipments()->attach($equipo->id, [
                'service_order_line_id' => $linea->id,
                'posicion' => $posicion++,
                'recibido' => false,
            ]);
        }
    }
}
