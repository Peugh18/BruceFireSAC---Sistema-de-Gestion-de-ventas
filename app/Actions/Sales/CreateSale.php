<?php

namespace App\Actions\Sales;

use App\Actions\Cotizaciones\TransitionQuoteState;
use App\Models\Client;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\Service;
use App\Models\Vehicle;
use App\Services\AuditLogger;
use App\Services\Billing\AfectacionIgv;
use App\Services\Billing\PrecioConIgv;
use App\Services\NumeracionInterna;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateSale
{
    public function __construct(
        protected ProcessSaleItem $processSaleItem,
        protected ValidarComprobanteCliente $validarComprobanteCliente,
        protected LiberarBorrador $liberarBorrador,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $items
     */
    public function handle(array $data, array $items, int $vendedorId): Sale
    {
        $iniciadoAt = $data['iniciado_at'] ?? null;
        unset($data['iniciado_at']);

        return DB::transaction(function () use ($data, $items, $vendedorId, $iniciadoAt) {
            // Los precios ya incluyen IGV: el subtotal de cada línea es lo que
            // paga el cliente y la base/IGV se separan de ahí.
            $lineas = $this->mapearLineas($items);

            ['subtotal' => $subtotal, 'igv' => $igv, 'total' => $total] = PrecioConIgv::totalesConAfectacion($lineas);

            $this->validarVehiculo($data);
            $this->validarCatalogoActivo($lineas);

            $this->validarComprobanteCliente->handle(
                Client::query()->findOrFail($data['client_id']),
                (string) ($data['comprobante_tipo'] ?? ''),
                $total,
            );

            $sale = Sale::create([
                ...$data,
                'numero_interno' => app(NumeracionInterna::class)->siguiente('VTA', 'sales', 'numero_interno'),
                'registro_iniciado_at' => $this->inicioDelRegistro($iniciadoAt),
                'vendedor_id' => $vendedorId,
                'subtotal' => $subtotal,
                'igv' => $igv,
                'total' => $total,
                'estado' => 'borrador',
            ]);

            foreach ($lineas as $linea) {
                $this->processSaleItem->handle($sale, $linea);
            }

            if (! empty($data['quote_id'])) {
                $this->convertirCotizacion((int) $data['quote_id'], (int) $sale->client_id, (int) $sale->sede_id);
            }

            if ($sale->esCredito()) {
                $this->crearCuotas($sale, $data['cuotas'] ?? []);
            }

            AuditLogger::log(
                action: 'venta.creada',
                entity: $sale,
                newValues: [
                    'numero_interno' => $sale->numero_interno,
                    'total' => $total,
                    'comprobante_tipo' => $sale->comprobante_tipo,
                ],
                userId: $vendedorId
            );

            return $sale->load('items.product', 'items.service', 'items.equipment', 'items.inventoryUnit');
        });
    }

    /**
     * Vuelve a llenar una venta en borrador con los datos corregidos: libera
     * lo que tenía reservado y procesa las líneas de nuevo. Conserva su
     * número, su vendedor y su cotización de origen.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $items
     */
    public function actualizar(Sale $sale, array $data, array $items, int $userId): Sale
    {
        return DB::transaction(function () use ($sale, $data, $items, $userId) {
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);
            $antes = $sale->only(['client_id', 'comprobante_tipo', 'condicion_pago', 'total']);
            unset($data['iniciado_at']);

            $lineas = $this->mapearLineas($items);

            ['subtotal' => $subtotal, 'igv' => $igv, 'total' => $total] = PrecioConIgv::totalesConAfectacion($lineas);

            $this->validarComprobanteCliente->handle(
                Client::query()->findOrFail($data['client_id']),
                (string) ($data['comprobante_tipo'] ?? ''),
                $total,
            );

            $this->validarVehiculo($data);

            $this->liberarBorrador->handle($sale);

            $sale->update([
                ...collect($data)->except(['quote_id', 'vendedor_id', 'cuotas'])->all(),
                'subtotal' => $subtotal,
                'igv' => $igv,
                'total' => $total,
            ]);

            foreach ($lineas as $linea) {
                $this->processSaleItem->handle($sale, $linea);
            }

            if ($sale->esCredito()) {
                $this->crearCuotas($sale, $data['cuotas'] ?? []);
            }

            AuditLogger::log(
                action: 'venta.borrador_editado',
                entity: $sale,
                oldValues: $antes,
                newValues: $sale->only(['client_id', 'comprobante_tipo', 'condicion_pago', 'total']),
                userId: $userId
            );

            return $sale->refresh();
        });
    }

    /**
     * Mapeo común de las líneas del formulario a líneas de venta (se usa
     * tanto al crear como al actualizar un borrador): descuento por defecto,
     * afectación de IGV del catálogo y subtotal con descuento.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function mapearLineas(array $items): array
    {
        return array_map(function (array $item) {
            $descuento = $item['descuento'] ?? 0;

            $catalogo = ! empty($item['service_id']) ? Service::findOrFail((int) $item['service_id']) : Product::findOrFail((int) $item['product_id']);
            $afectacion = AfectacionIgv::codigo($catalogo);

            return [...$item, 'tipo_afectacion_igv' => $afectacion, 'descuento' => $descuento, 'subtotal' => round(($item['cantidad'] * $item['precio_unitario']) - $descuento, 2)];
        }, $items);
    }

    /**
     * Cuotas de una venta a crédito: plazo y cantidad libres, pero la suma
     * debe ser exactamente el total (SUNAT lo valida en la factura).
     *
     * @param  list<array{fecha_vencimiento: string, monto: float|int|string}>  $cuotas
     */
    protected function crearCuotas(Sale $sale, array $cuotas): void
    {
        if ($cuotas === []) {
            $cuotas = [['fecha_vencimiento' => $sale->fecha->copy()->addDays(30)->toDateString(), 'monto' => $sale->total]];
        }

        $suma = round(array_sum(array_map(fn (array $cuota) => (float) $cuota['monto'], $cuotas)), 2);

        if (abs($suma - (float) $sale->total) > 0.009) {
            throw ValidationException::withMessages([
                'cuotas' => 'La suma de las cuotas (S/ '.number_format($suma, 2).') debe ser igual al total de la venta (S/ '.number_format((float) $sale->total, 2).').',
            ]);
        }

        usort($cuotas, fn (array $a, array $b) => strcmp($a['fecha_vencimiento'], $b['fecha_vencimiento']));

        foreach (array_values($cuotas) as $index => $cuota) {
            $sale->installments()->create([
                'numero_cuota' => $index + 1,
                'fecha_vencimiento' => $cuota['fecha_vencimiento'],
                'monto' => round((float) $cuota['monto'], 2),
                'estado' => 'pendiente',
            ]);
        }
    }

    /**
     * Una venta solo puede nacer de una cotización aceptada del mismo
     * cliente; al crearse la venta, la cotización pasa a convertida.
     */
    protected function convertirCotizacion(int $quoteId, int $clientId, int $sedeId): void
    {
        $quote = Quote::query()->lockForUpdate()->findOrFail($quoteId);

        if ($quote->sede_id !== null && (int) $quote->sede_id !== $sedeId) {
            throw ValidationException::withMessages([
                'quote_id' => "La cotización {$quote->numero} es de otra sede.",
            ]);
        }

        if ((int) $quote->client_id !== $clientId) {
            throw ValidationException::withMessages([
                'quote_id' => "La cotización {$quote->numero} pertenece a otro cliente.",
            ]);
        }

        app(TransitionQuoteState::class)->convertToSale($quote);
    }

    /**
     * X7: el registro empieza cuando se abrió el formulario. Una hora del
     * navegador vacía, futura o de hace más de 12 horas no se cree: se usa
     * la de ahora.
     */
    protected function inicioDelRegistro(mixed $iniciadoAt): CarbonInterface
    {
        $ahora = now();

        try {
            $inicio = $iniciadoAt ? Carbon::parse((string) $iniciadoAt)->setTimezone($ahora->getTimezone()) : null;
        } catch (\Throwable) {
            $inicio = null;
        }

        return $inicio && $inicio->lte($ahora) && $inicio->gte($ahora->copy()->subHours(12)) ? $inicio : $ahora;
    }

    /**
     * La placa de la venta debe ser de un vehículo del mismo cliente.
     *
     * @param  array<string, mixed>  $data
     */
    protected function validarVehiculo(array $data): void
    {
        if (empty($data['vehicle_id'])) {
            return;
        }

        $delCliente = Vehicle::query()
            ->whereKey((int) $data['vehicle_id'])
            ->where('client_id', (int) $data['client_id'])
            ->exists();

        if (! $delCliente) {
            throw ValidationException::withMessages([
                'vehicle_id' => 'El vehículo elegido no es de este cliente.',
            ]);
        }
    }

    /**
     * Una venta nueva no lleva productos ni servicios dados de baja.
     *
     * @param  list<array<string, mixed>>  $lineas
     */
    protected function validarCatalogoActivo(array $lineas): void
    {
        $productos = array_filter(array_map(fn (array $linea) => $linea['product_id'] ?? null, $lineas));
        $servicios = array_filter(array_map(fn (array $linea) => $linea['service_id'] ?? null, $lineas));

        $inactivo = Product::query()->whereKey($productos)->where('activo', false)->value('nombre')
            ?? Service::query()->whereKey($servicios)->where('activo', false)->value('nombre');

        if ($inactivo !== null) {
            throw ValidationException::withMessages([
                'items' => "{$inactivo} está dado de baja: ya no se vende.",
            ]);
        }
    }
}
