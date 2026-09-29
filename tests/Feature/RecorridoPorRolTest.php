<?php

use App\Models\Certificate;
use App\Models\Client;
use App\Models\ElectronicDocument;
use App\Models\Equipment;
use App\Models\Quote;
use App\Models\Reception;
use App\Models\Sale;
use App\Models\Sede;
use App\Models\ServiceOrder;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Rutas;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * Pantallas que cada rol abre: su propia sección y las comunes. El Gerente
 * además supervisa Almacén.
 *
 * @var array<string, list<string>>
 */
const SECCIONES_POR_ROL = [
    'Vendedor' => ['vendedor'],
    'Gerente' => ['gerente', 'almacen'],
    'Almacen' => ['almacen'],
    'TecnicoPlanta' => ['tecnico-planta'],
    'TecnicoCampo' => ['tecnico-campo'],
];

test('cada rol abre sus pantallas sin errores y ninguna de otro rol', function () {
    $sede = Sede::factory()->mixta()->create();
    $client = Client::factory()->create();
    $sale = Sale::factory()->create(['client_id' => $client->id, 'sede_id' => $sede->id, 'estado' => 'confirmada']);
    $modelos = [
        'client' => $client,
        'sale' => $sale,
        'quote' => Quote::factory()->create(['client_id' => $client->id, 'sede_id' => $sede->id]),
        'service_order' => ServiceOrder::factory()->create(['client_id' => $client->id, 'sede_id' => $sede->id]),
        'certificate' => Certificate::factory()->create(['client_id' => $client->id, 'sale_id' => $sale->id]),
        'equipment' => Equipment::factory()->create(['client_id' => $client->id]),
        'electronic_document' => ElectronicDocument::factory()->create(['sale_id' => $sale->id]),
        'reception' => Reception::create(['proveedor' => 'PROVEEDOR SAC', 'fecha' => today(), 'sede_almacen_id' => $sede->id]),
    ];

    $rutas = collect(Rutas::getRoutes()->getRoutes())
        ->filter(fn (Route $ruta) => in_array('GET', $ruta->methods(), true) && str_starts_with($ruta->uri(), '{current_team}/'))
        // Descargas (PDF, Word, XML, Excel): se prueban en sus propios módulos.
        ->reject(fn (Route $ruta) => preg_match('/pdf|word|xml|cdr|export|descargar|stickers|constancia|imprimir/', $ruta->uri()) === 1)
        ->reject(fn (Route $ruta) => collect($ruta->parameterNames())->diff(['current_team', ...array_keys($modelos)])->isNotEmpty());

    expect($rutas->count())->toBeGreaterThan(40);

    $fallas = [];
    foreach (SECCIONES_POR_ROL as $rol => $secciones) {
        $usuario = User::factory()->create(['sede_id' => $sede->id]);
        $usuario->assignRole($rol);

        foreach ($rutas as $ruta) {
            $parametros = ['current_team' => $usuario->currentTeam->slug];
            foreach ($ruta->parameterNames() as $nombre) {
                $parametros[$nombre] ??= $modelos[$nombre]->getRouteKey();
            }

            $url = route($ruta->getName(), $parametros);
            $seccion = explode('/', $ruta->uri())[1];
            $codigo = $this->actingAs($usuario)->get($url)->getStatusCode();
            $esDeOtroRol = in_array($seccion, ['vendedor', 'gerente', 'almacen', 'tecnico-planta', 'tecnico-campo'], true) && ! in_array($seccion, $secciones, true);

            if ($codigo >= 500) {
                $fallas[] = "{$rol} {$ruta->uri()}: error {$codigo}";
            } elseif ($esDeOtroRol && $codigo === 200) {
                $fallas[] = "{$rol} {$ruta->uri()}: abre una pantalla de otro rol";
            }
        }
    }

    expect($fallas)->toBe([]);
});
