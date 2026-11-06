<?php

use App\Actions\Cotizaciones\IniciarOrdenDesdeCotizacion;
use App\Enums\TeamRole;
use App\Models\Client;
use App\Models\Equipment;
use App\Models\Evidencia;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->team = Team::factory()->create();
    $this->campo = User::factory()->create(['current_team_id' => $this->team->id]);
    $this->team->members()->attach($this->campo, ['role' => TeamRole::Admin->value]);
    $this->campo->assignRole('TecnicoCampo');
});

test('una inspeccion o un mantenimiento no se manda a campo', function (string $servicio) {
    $quote = Quote::factory()->create(['estado' => 'aceptada']);
    QuoteItem::factory()->create(['quote_id' => $quote->id, 'service_id' => Service::factory()->create(['nombre' => $servicio])->id, 'product_id' => null]);

    expect(fn () => app(IniciarOrdenDesdeCotizacion::class)->handle($quote, $this->campo, ['departamento_tecnico' => 'campo']))
        ->toThrow(ValidationException::class, 'Campo solo hace recojos, entregas e instalaciones');
    expect(ServiceOrder::count())->toBe(0);
})->with(['Inspección de extintores', 'Mantenimiento anual']);

test('una instalacion cotizada va a campo y una recarga a planta', function (string $servicio, string $area) {
    $quote = Quote::factory()->create(['estado' => 'aceptada']);
    QuoteItem::factory()->create(['quote_id' => $quote->id, 'service_id' => Service::factory()->create(['nombre' => $servicio, 'certificate_type_id' => null])->id, 'product_id' => null]);

    expect(app(IniciarOrdenDesdeCotizacion::class)->handle($quote, $this->campo)->departamento_tecnico)->toBe($area);
})->with([['Instalación de extintores', 'campo'], ['Recarga de extintor PQS 6 kg', 'planta']]);

test('en el recojo se registra un extintor nuevo con sus datos y su foto', function () {
    Storage::fake('local');
    $order = ServiceOrder::factory()->create([
        'service_id' => Service::factory()->create(['nombre' => 'Recojo de extintores'])->id,
        'estado' => 'pendiente_recepcion',
        'departamento_tecnico' => 'campo',
    ]);

    $this->actingAs($this->campo)
        ->post(route('tecnico-campo.recojos.equipos.store', ['current_team' => $this->team, 'service_order' => $order]), [
            'tipo_agente' => 'PQS ABC', 'capacidad' => '6 kg', 'marca' => 'Amerex', 'anio_fabricacion' => 2022,
            'foto' => UploadedFile::fake()->image('extintor.jpg'),
        ])
        ->assertSessionHasNoErrors();

    $equipo = $order->equipments()->sole();
    expect($equipo->marca)->toBe('Amerex')
        ->and((string) $equipo->anio_fabricacion)->toBe('2022')
        ->and(Evidencia::where('etapa', 'recojo')->where('equipment_id', $equipo->id)->exists())->toBeTrue();

    $this->get(route('tecnico-campo.recojos.show', ['current_team' => $this->team, 'service_order' => $order]))
        ->assertInertia(fn (Assert $page) => $page->where('order.equipments.0.faltan', ['la serie de fábrica']));
});

test('la entrega pide marcar cada extintor y una foto', function () {
    $order = ServiceOrder::factory()->create(['departamento_tecnico' => 'campo', 'estado' => 'listo_entrega']);
    $equipos = Equipment::factory()->count(2)->create(['client_id' => $order->client_id]);
    $order->equipments()->attach($equipos->modelKeys());
    $ruta = route('tecnico-campo.entregas.confirm', ['current_team' => $this->team, 'service_order' => $order]);
    $datos = ['receptor_nombre' => 'Rosa Díaz', 'conformidad_aceptada' => true, 'firma' => FIRMA_PNG];

    $this->actingAs($this->campo)->post($ruta, [...$datos, 'entregados' => [$equipos[0]->id]])
        ->assertSessionHasErrors(['entregados' => 'Marca cada extintor que entregas. Falta: '.$equipos[1]->numero_serie.'.']);

    $this->actingAs($this->campo)->post($ruta, [...$datos, 'entregados' => $equipos->modelKeys()])
        ->assertSessionHasErrors(['fotos' => 'Toma al menos una foto de la entrega.']);

    $this->actingAs($this->campo)->post($ruta, [...$datos, ...prepararEntrega($order)])->assertSessionHasNoErrors();
    expect($order->fresh()->estado)->toBe('cerrado');
});

test('la instalacion rechaza una manija por encima de 1.53 m', function () {
    $order = ServiceOrder::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'service_id' => Service::factory()->create(['nombre' => 'Instalación de extintores'])->id,
        'departamento_tecnico' => 'campo',
        'estado' => 'pendiente_recepcion',
    ]);

    $this->actingAs($this->campo)
        ->post(route('tecnico-campo.instalaciones.store', ['current_team' => $this->team, 'service_order' => $order]), [
            'area' => 'Almacén', 'ubicacion_instalada' => 'Pared norte', 'conformidad_nombre' => 'Jefe de planta',
            'conformidad_aceptada' => true, 'firma' => FIRMA_PNG,
            'equipos' => [['tipo_agente' => 'PQS ABC', 'capacidad' => '6 kg', 'altura_m' => 1.8, 'senalizacion' => true, 'acceso_libre' => true]],
        ])
        ->assertSessionHasErrors(['equipos.0.altura_m' => 'La manija debe quedar a 1.53 m o menos del piso (NTP 350.043).']);
});
