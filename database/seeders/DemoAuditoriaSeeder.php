<?php

namespace Database\Seeders;

use App\Actions\Almacen\TransferInventory;
use App\Models\Client;
use App\Models\Deficiency;
use App\Models\Driver;
use App\Models\ElectronicDocument;
use App\Models\Equipment;
use App\Models\Installment;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Sede;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\TransportVehicle;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Datos de demostración para auditar el sistema por botones: deja cada flujo
 * en varios estados (cotizaciones, órdenes de taller y campo, ventas a
 * crédito, comprobantes pendientes y rechazados, alertas y un traslado).
 * Es idempotente y nunca corre en producción. Se ejecuta después de
 * DatabaseSeeder: `php artisan db:seed --class=DemoAuditoriaSeeder`.
 */
class DemoAuditoriaSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            return;
        }

        $vendedor = User::where('email', 'vendedor@brucefire.pe')->first();
        $almacen = User::where('email', 'almacen@brucefire.pe')->first();
        $tienda = Sede::where('nombre', 'Tienda Trujillo Centro')->first();
        $central = Sede::where('nombre', 'Almacén Central Trujillo')->first();

        if (! $vendedor || ! $almacen || ! $tienda || ! $central) {
            $this->command->warn('Falta DatabaseSeeder: no se crearon los datos de auditoría.');

            return;
        }

        if (Client::where('nombre_comercial', 'DEMO AUDITORÍA')->exists()) {
            $this->command->info('Los datos de auditoría ya existen.');

            return;
        }

        $empresa = Client::factory()->create(['razon_social' => 'FERRETERÍA DEMO AUDITORÍA S.A.C.', 'nombre_comercial' => 'DEMO AUDITORÍA']);
        $persona = Client::factory()->create(['tipo_documento' => 'dni', 'numero_documento' => '45678912', 'razon_social' => 'MARÍA DEMO LÓPEZ', 'nombre_comercial' => 'MARÍA DEMO LÓPEZ']);

        $producto = Product::where('serializado', true)->first() ?? Product::factory()->create(['nombre' => 'Extintor PQS ABC 6 Kg']);

        $ultimaCotizacion = null;

        // Cotizaciones en cada estado.
        foreach (['borrador', 'enviada', 'aceptada'] as $indice => $estado) {
            $cotizacion = Quote::factory()->create([
                'vendedor_id' => $vendedor->id, 'client_id' => $empresa->id, 'sede_id' => $tienda->id,
                'estado' => $estado, 'subtotal' => 84.75, 'igv' => 15.25, 'total' => 100,
                'numero' => 'COT-DEMO-'.($indice + 1),
            ]);
            $ultimaCotizacion = $cotizacion;
            QuoteItem::factory()->create(['quote_id' => $cotizacion->id, 'product_id' => $producto->id, 'cantidad' => 1, 'precio_unitario' => 100, 'subtotal' => 100]);
        }

        // Órdenes de servicio: una por etapa, para taller y campo.
        $servicios = [];
        foreach (['Inspección técnica', 'Instalación de extintores', 'Mantenimiento en sitio', 'Recarga PQS 6 kg'] as $i => $nombre) {
            $servicios[$nombre] = Service::firstOrCreate(['nombre' => $nombre], Service::factory()->make(['nombre' => $nombre, 'codigo' => 'SRV-DEMO-'.($i + 1)])->toArray());
        }
        // Recarga de CO2 para que «Ofrecer recarga» de las alertas tenga servicio.
        Service::firstOrCreate(['nombre' => 'Recarga CO2 5 lb'], Service::factory()->make(['nombre' => 'Recarga CO2 5 lb', 'codigo' => 'SRV-DEMO-CO2', 'agente' => 'co2', 'capacidad' => '5 lb', 'precio_venta' => 45])->toArray());

        $etapas = [
            ['pendiente_recepcion', 'campo', 'Recarga PQS 6 kg'],
            ['pendiente_recepcion', 'campo', 'Inspección técnica'],
            ['pendiente_recepcion', 'campo', 'Instalación de extintores'],
            ['pendiente_recepcion', 'campo', 'Mantenimiento en sitio'],
            ['recibido_planta', 'planta', 'Recarga PQS 6 kg'],
            ['esperando_autorizacion', 'planta', 'Recarga PQS 6 kg'],
            ['autorizado', 'planta', 'Recarga PQS 6 kg'],
            ['listo_entrega', 'campo', 'Recarga PQS 6 kg'],
            ['entregado', 'campo', 'Recarga PQS 6 kg'],
        ];
        foreach ($etapas as $indice => [$estado, $departamento, $servicio]) {
            $orden = ServiceOrder::factory()->create([
                'codigo' => 'OT-DEMO-'.($indice + 1),
                'client_id' => ($indice % 2 === 0 ? $empresa : $persona)->id,
                'service_id' => $servicios[$servicio]->id,
                'sede_id' => $tienda->id, 'estado' => $estado, 'departamento_tecnico' => $departamento,
                'observaciones' => 'Orden de demostración para auditoría.',
            ]);
            $equipo = Equipment::factory()->create(['product_id' => $producto->id,
                'client_id' => $orden->client_id, 'numero_serie' => 'BF-EQ-9'.str_pad((string) ($indice + 1), 5, '0', STR_PAD_LEFT),
                'tipo_agente' => 'PQS ABC', 'capacidad' => '6 kg', 'marca' => 'Badger', 'ubicacion_actual' => 'Local del cliente',
            ]);
            $orden->equipments()->attach($equipo, ['recibido' => ! in_array($estado, ['pendiente_recepcion'], true)]);

            if ($estado === 'esperando_autorizacion') {
                Deficiency::factory()->create([
                    'service_order_id' => $orden->id, 'equipment_id' => $equipo->id, 'componente' => 'Válvula',
                    'nota' => 'Cambio de válvula necesario', 'estado' => 'esperando_autorizacion',
                ]);
            }
        }

        // Alertas: extintores que vencen pronto.
        foreach ([5, 20] as $dias) {
            $alerta = Equipment::factory()->create(['product_id' => null, 'client_id' => $empresa->id, 'tipo_agente' => 'CO2', 'capacidad' => '5 lb', 'proxima_fecha_atencion' => now()->addDays($dias)]);
            // Sin una venta, orden o cotización de la sede, el vendedor no la vería.
            $alerta->quotes()->attach($ultimaCotizacion->id);
        }

        $numeroVenta = 0;

        // Venta a crédito con una cuota vencida y otra por vencer.
        $credito = Sale::factory()->create(['numero_interno' => 'VTA-DEMO-'.++$numeroVenta,
            'client_id' => $empresa->id, 'sede_id' => $tienda->id, 'vendedor_id' => $vendedor->id, 'estado' => 'confirmada',
            'condicion_pago' => 'credito', 'comprobante_tipo' => 'factura', 'fecha' => now()->subDays(45),
            'subtotal' => 169.49, 'igv' => 30.51, 'total' => 200,
        ]);
        SaleItem::factory()->create(['sale_id' => $credito->id, 'product_id' => $producto->id, 'cantidad' => 1, 'precio_unitario' => 200, 'subtotal' => 200]);
        Installment::factory()->create(['sale_id' => $credito->id, 'numero_cuota' => 1, 'monto' => 100, 'fecha_vencimiento' => now()->subDays(15)]);
        Installment::factory()->create(['sale_id' => $credito->id, 'numero_cuota' => 2, 'monto' => 100, 'fecha_vencimiento' => now()->addDays(15)]);

        // Comprobantes en cada estado fiscal.
        foreach (['pendiente' => 'F001', 'rechazado' => 'F001', 'observado' => 'B001'] as $estado => $serie) {
            $venta = Sale::factory()->create(['numero_interno' => 'VTA-DEMO-'.++$numeroVenta, 'client_id' => $persona->id, 'sede_id' => $tienda->id, 'vendedor_id' => $vendedor->id, 'estado' => 'confirmada', 'comprobante_tipo' => $serie === 'F001' ? 'factura' : 'boleta', 'subtotal' => 42.37, 'igv' => 7.63, 'total' => 50]);
            ElectronicDocument::factory()->create(['sale_id' => $venta->id, 'tipo' => $serie === 'F001' ? 'factura' : 'boleta', 'serie' => $serie, 'sunat_estado' => $estado, 'sunat_mensaje' => $estado === 'rechazado' ? 'Rechazo simulado de demostración' : null]);
        }
        Sale::factory()->create(['numero_interno' => 'VTA-DEMO-'.++$numeroVenta, 'client_id' => $persona->id, 'sede_id' => $tienda->id, 'vendedor_id' => $vendedor->id, 'estado' => 'borrador', 'comprobante_tipo' => 'nota_venta']);

        // Transporte propio para poder completar las guías de remisión.
        TransportVehicle::factory()->create(['placa' => 'DEM-123']);
        Driver::factory()->create(['nombres' => 'Conductor', 'apellidos' => 'Demo']);

        // Un traslado entre sedes con una unidad disponible.
        $unidad = InventoryUnit::factory()->create(['product_id' => $producto->id, 'sede_almacen_id' => $central->id, 'estado' => 'disponible']);
        $destino = Sede::firstOrCreate(['nombre' => 'Almacén Chimbote (demo)'], ['tipo' => 'almacen', 'ubigeo' => '021801', 'activo' => true]);
        app(TransferInventory::class)->handle($central->id, ['destination_sede_id' => $destino->id, 'serials' => [$unidad->numero_serie], 'observation' => 'Traslado de demostración'], $almacen);

        $this->command->info('Datos de auditoría creados.');
    }
}
