<?php

use App\Actions\Certificates\EmitirCertificadosDeVenta;
use App\Actions\TecnicoPlanta\ExecuteAndCloseServiceOrder;
use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Equipment;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\User;
use Database\Seeders\CertificateTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed([RolesAndPermissionsSeeder::class, CertificateTypeSeeder::class]);
    $this->user = User::factory()->create();
    $this->user->assignRole('Vendedor');
});

/** Orden de recarga cotizada junto con un extintor nuevo por vender. */
function ordenConExtintorPorVender(User $user, array $equipo = []): array
{
    $service = Service::factory()->create(['nombre' => 'RECARGA DE EXTINTOR CO2', 'certificate_type_id' => CertificateType::where('codigo', 'operatividad_garantia')->sole()->id]);
    $quote = Quote::factory()->create(['vendedor_id' => $user->id, 'destino' => 'local_cliente', 'referencia' => 'SEDE: Chacarero']);
    QuoteItem::factory()->create(['quote_id' => $quote->id, 'service_id' => $service->id, 'product_id' => null, 'cantidad' => 1]);
    $nuevo = Product::factory()->create(['serializado' => true, 'nombre' => 'Extintor PQS ABC 12 kg']);
    QuoteItem::factory()->create(['quote_id' => $quote->id, 'product_id' => $nuevo->id, 'service_id' => null, 'cantidad' => 1]);
    $order = ServiceOrder::factory()->create(['client_id' => $quote->client_id, 'quote_id' => $quote->id, 'sale_id' => null, 'service_id' => $service->id, 'estado' => 'datos_completos']);
    $recarga = Equipment::factory()->create(['client_id' => $quote->client_id, 'estado' => 'en_servicio', 'tipo_agente' => 'CO2', 'capacidad' => '10 lb', ...$equipo]);
    $order->equipments()->attach($recarga, ['recibido' => true]);

    return [$order, $recarga, $nuevo];
}

test('con un extintor nuevo por vender el certificado espera y sale uno solo al cobrar', function () {
    [$order, $recarga, $producto] = ordenConExtintorPorVender($this->user);

    app(ExecuteAndCloseServiceOrder::class)->advanceState($order, 'listo_certificado', $this->user);
    expect(Certificate::where('service_order_id', $order->id)->count())->toBe(0)
        ->and($order->fresh()->extintoresNuevosPorVender())->toBe(1);

    // El cobro de la orden: la venta lleva la recarga y el extintor nuevo escaneado.
    $sale = Sale::factory()->create(['vendedor_id' => $this->user->id, 'client_id' => $order->client_id, 'sede_id' => $order->sede_id, 'estado' => 'confirmada', 'destino' => 'local_cliente', 'referencia' => 'SEDE: Chacarero']);
    $nuevo = Equipment::factory()->create(['client_id' => $order->client_id, 'product_id' => $producto->id]);
    SaleItem::factory()->create(['sale_id' => $sale->id, 'tipo_linea' => 'unidad_nueva', 'product_id' => $producto->id, 'equipment_id' => $nuevo->id, 'cantidad' => 1]);
    $order->update(['sale_id' => $sale->id]);

    app(EmitirCertificadosDeVenta::class)->automaticos($sale->fresh());

    $operatividad = Certificate::whereHas('certificateType', fn ($q) => $q->where('codigo', 'operatividad_garantia'))->sole();
    expect($operatividad->service_order_id)->toBe($order->id)
        ->and($operatividad->sale_id)->toBe($sale->id)
        ->and($operatividad->certificateUnits()->pluck('equipment_id')->sort()->values()->all())->toBe(collect([$recarga->id, $nuevo->id])->sort()->values()->all())
        ->and($operatividad->tipo_atencion)->toBe('nuevo / recarga');
});

test('si la orden ya tenia su certificado, el extintor vendido se suma al mismo numero', function () {
    [$order, $recarga, $producto] = ordenConExtintorPorVender($this->user);
    // Orden cerrada antes de este cambio: su certificado ya salió solo con la recarga.
    $order->quote->items()->whereNotNull('product_id')->delete();
    app(ExecuteAndCloseServiceOrder::class)->advanceState($order, 'listo_certificado', $this->user);
    $original = Certificate::where('service_order_id', $order->id)->whereHas('certificateType', fn ($q) => $q->where('codigo', 'operatividad_garantia'))->sole();

    $sale = Sale::factory()->create(['vendedor_id' => $this->user->id, 'client_id' => $order->client_id, 'sede_id' => $order->sede_id, 'estado' => 'confirmada', 'destino' => 'local_cliente', 'referencia' => 'SEDE: Chacarero']);
    $nuevo = Equipment::factory()->create(['client_id' => $order->client_id, 'product_id' => $producto->id]);
    SaleItem::factory()->create(['sale_id' => $sale->id, 'tipo_linea' => 'unidad_nueva', 'product_id' => $producto->id, 'equipment_id' => $nuevo->id, 'cantidad' => 1]);
    $order->update(['sale_id' => $sale->id]);

    app(EmitirCertificadosDeVenta::class)->automaticos($sale->fresh());

    $operatividad = Certificate::whereHas('certificateType', fn ($q) => $q->where('codigo', 'operatividad_garantia'))->where('estado', 'vigente')->sole();
    expect($operatividad->id)->toBe($original->id)
        ->and($operatividad->numero)->toBe($original->numero)
        ->and($operatividad->certificateUnits()->count())->toBe(2);
});

test('no se cierra la orden si al extintor le falta el año de fabricacion', function () {
    [$order, $recarga] = ordenConExtintorPorVender($this->user, ['anio_fabricacion' => 'No legible / Pendiente de verificar']);

    expect(fn () => app(ExecuteAndCloseServiceOrder::class)->advanceState($order, 'listo_certificado', $this->user))
        ->toThrow(InvalidArgumentException::class, 'Falta el año de fabricación de '.$recarga->numero_serie);
    expect(Certificate::count())->toBe(0);
});

test('un extintor con todos sus datos no tiene nada pendiente', function () {
    $completo = Equipment::factory()->create(['tipo_agente' => 'PQS ABC', 'capacidad' => '6 kg', 'marca' => 'BRUCE', 'anio_fabricacion' => '2024', 'serie_fabricante' => 'SF-1']);
    $incompleto = Equipment::factory()->create(['marca' => 'No legible / Pendiente de verificar', 'anio_fabricacion' => (string) (today()->year + 1)]);

    expect($completo->datosQueFaltan())->toBe([])
        ->and($incompleto->datosQueFaltan())->toBe(['la marca', 'el año de fabricación'])
        ->and(Equipment::faltantesParaCertificar([$incompleto]))->toStartWith('Falta la marca y el año de fabricación de '.$incompleto->numero_serie);
});
