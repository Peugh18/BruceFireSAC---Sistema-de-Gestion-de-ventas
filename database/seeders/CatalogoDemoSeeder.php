<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Service;
use App\Models\TipoDeServicio;
use Illuminate\Database\Seeder;

/**
 * Catálogo de demostración con los productos y servicios que más vende Bruce
 * Fire, con el precio promedio de su histórico (CODEPLEX, ene 2025 – set 2026).
 * Solo catálogo: sin clientes, ventas ni stock. Idempotente; nunca en producción.
 * Se ejecuta después de DatabaseSeeder: `php artisan db:seed --class=CatalogoDemoSeeder`.
 */
class CatalogoDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            return;
        }

        $this->sembrar();
    }

    /** También lo usa `sistema:capacitacion` en el servidor de capacitación. */
    public function sembrar(): void
    {
        // [nombre, clave de categoría, agente, capacidad, precio, serializado]
        $productos = [
            ['Extintor PQS ABC 6 kg', 'extintor', 'PQS ABC', '6 kg', 70.00, true],
            ['Extintor PQS ABC 9 kg', 'extintor', 'PQS ABC', '9 kg', 86.70, true],
            ['Extintor PQS ABC 12 kg', 'extintor', 'PQS ABC', '12 kg', 100.00, true],
            ['Extintor PQS ABC 4 kg', 'extintor', 'PQS ABC', '4 kg', 70.00, true],
            ['Extintor CO2 10 lb', 'extintor', 'CO2', '10 lb', 320.00, true],
            ['Extintor de acetato de potasio K 6 L', 'extintor', 'Acetato de potasio (K)', '6 L', 190.00, true],
            ['Señal de vinil', 'senalizacion', null, null, 6.20, false],
            ['Señal fotoluminiscente 20 × 30 cm en PVC', 'senalizacion', null, null, 12.80, false],
            ['Cono de PVC 28" con doble cinta reflectiva', 'senalizacion', null, null, 33.10, false],
            ['Cinta reflectiva roja y blanca 3M', 'senalizacion', null, null, 32.20, false],
            ['Tarjeta de inspección plastificada', 'senalizacion', null, null, 2.70, false],
            ['Lentes de seguridad', 'epp', null, null, 6.00, false],
            ['Guantes de seguridad', 'epp', null, null, 7.90, false],
            ['Filtro 3M 7093 para polvo', 'epp', null, null, 19.00, false],
            ['Botiquín de lona equipado', 'otro', null, null, 64.10, false],
            ['Kit antiderrame', 'otro', null, null, 95.60, false],
            ['Válvula para extintor', 'repuesto', null, null, 11.50, false],
            ['Manómetro para extintor', 'repuesto', null, null, 5.30, false],
            ['Gabinete para extintor', 'accesorio', null, null, 85.00, false],
        ];

        foreach ($productos as [$nombre, $categoria, $agente, $capacidad, $precio, $serializado]) {
            Product::query()->firstOrCreate(['nombre' => $nombre], [
                'categoria' => $categoria,
                'agente' => $agente,
                'capacidad' => $capacidad,
                'unidad_medida' => 'NIU',
                'precio_venta' => $precio,
                'aplica_igv' => true,
                'tipo_afectacion_igv' => '10',
                'serializado' => $serializado,
                'stock_minimo' => $serializado ? 5 : 10,
                'activo' => true,
            ]);
        }

        $tipo = fn (string $nombre): ?int => TipoDeServicio::query()->where('nombre', $nombre)->value('id');

        // [nombre, tipo de servicio, agente, capacidad, precio]
        $servicios = [
            ['Recarga y mantenimiento de extintor PQS ABC 6 kg', 'Recarga de extintor', 'PQS ABC', '6 kg', 35.00],
            ['Recarga y mantenimiento de extintor PQS ABC 9 kg', 'Recarga de extintor', 'PQS ABC', '9 kg', 49.00],
            ['Recarga y mantenimiento de extintor PQS ABC 12 kg', 'Recarga de extintor', 'PQS ABC', '12 kg', 59.00],
            ['Recarga y mantenimiento de extintor PQS ABC 4 kg', 'Recarga de extintor', 'PQS ABC', '4 kg', 31.00],
            ['Recarga y mantenimiento de extintor PQS ABC 2 kg', 'Recarga de extintor', 'PQS ABC', '2 kg', 20.50],
            ['Recarga y mantenimiento de extintor CO2 10 lb', 'Recarga de extintor', 'CO2', '10 lb', 69.00],
            ['Recarga y mantenimiento de extintor de acetato K 6 L', 'Recarga de extintor', 'Acetato de potasio (K)', '6 L', 78.00],
            ['Mantenimiento de extintor CO2 10 lb', 'Mantenimiento de extintor', 'CO2', '10 lb', 35.50],
            ['Mantenimiento de extintor PQS ABC 20 lb', 'Mantenimiento de extintor', 'PQS ABC', '20 lb', 52.60],
            ['Prueba hidrostática de extintor', 'Prueba hidrostática', null, null, 40.00],
            ['Inspección de extintores en sitio', 'Inspección', null, null, 150.00],
            ['Capacitación en uso de extintores', 'Capacitación', null, null, 300.00],
        ];

        foreach ($servicios as [$nombre, $tipoDeServicio, $agente, $capacidad, $precio]) {
            Service::query()->firstOrCreate(['nombre' => $nombre], [
                'tipo_de_servicio_id' => $tipo($tipoDeServicio),
                'agente' => $agente,
                'capacidad' => $capacidad,
                'unidad_medida' => 'ZZ',
                'precio_venta' => $precio,
                'aplica_igv' => true,
                'tipo_afectacion_igv' => '10',
                'activo' => true,
            ]);
        }
    }
}
