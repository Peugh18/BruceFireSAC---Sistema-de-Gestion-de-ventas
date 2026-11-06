<?php

use App\Enums\TeamRole;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->team = Team::factory()->create();
    $this->planta = User::factory()->create(['current_team_id' => $this->team->id]);
    $this->team->members()->attach($this->planta, ['role' => TeamRole::Admin->value]);
    $this->planta->assignRole('TecnicoPlanta');

    Service::factory()->create(['nombre' => 'Recarga CO2 10 lb', 'agente' => 'CO2', 'capacidad' => '10 lb', 'precio_venta' => 69, 'activo' => true]);
    $quince = Service::factory()->create(['nombre' => 'Recarga CO2 15 lb', 'agente' => 'CO2', 'capacidad' => '15 lb', 'precio_venta' => 80, 'activo' => true]);

    // Campo contó 3 CO2 de 15 lb y dejó S/ 80 c/u.
    $this->orden = ServiceOrder::factory()->create(['estado' => 'recibido_planta', 'departamento_tecnico' => 'planta', 'tecnico_id' => null]);
    $this->linea = $this->orden->lines()->create(['service_id' => $quince->id, 'cantidad' => 3, 'precio_lista' => null, 'precio_unitario' => 80, 'precio_estado' => 'sin_lista']);
    $this->orden->events()->create(['tipo' => 'otro', 'user_id' => $this->planta->id, 'payload' => [
        'eslabon_custodia' => 'recojo_campo',
        'conteo' => [['tipo_agente' => 'CO2', 'capacidad' => '15 lb', 'cantidad' => 3, 'precio' => 80]],
    ]]);
    $this->ruta = route('tecnico-planta.recepciones.equipos.store', ['current_team' => $this->team, 'service_order' => $this->orden]);
    $this->datos = ['service_order_line_id' => $this->linea->id, 'tipo_agente' => 'CO2', 'capacidad' => '10 lb', 'marca' => 'Buckeye', 'serie_fabricante' => 'S-1', 'anio_fabricacion' => 2020];
});

test('planta no registra en silencio un extintor distinto a lo que conto campo', function () {
    $this->actingAs($this->planta)->post($this->ruta, $this->datos)
        ->assertSessionHasErrors(['corregir_conteo' => 'Campo contó CO2 15 lb y tú registras CO2 10 lb. Si es así, confirma y se corrige la orden.']);

    expect($this->orden->equipments()->count())->toBe(0)
        ->and($this->linea->fresh()->cantidad)->toBe(3);
});

test('si planta confirma, el extintor pasa a la recarga que le toca y el vendedor ve el aviso', function () {
    $this->actingAs($this->planta)->post($this->ruta, [...$this->datos, 'corregir_conteo' => true])->assertSessionHasNoErrors();

    $diez = $this->orden->lines()->whereHas('service', fn ($q) => $q->where('capacidad', '10 lb'))->sole();
    expect($this->linea->fresh()->cantidad)->toBe(2)
        ->and($diez->cantidad)->toBe(1)
        ->and((float) $diez->precio_unitario)->toBe(69.0)
        ->and($diez->precio_estado)->toBe('lista')
        ->and($this->orden->equipments()->sole()->pivot->service_order_line_id)->toBe($diez->id)
        ->and($this->orden->events()->where('payload->origen', 'planta')->value('payload')['mensaje'])
        ->toContain('era CO2 10 lb, no CO2 15 lb');
});

test('si coincide con lo que conto campo, se registra sin preguntar', function () {
    $this->actingAs($this->planta)->post($this->ruta, [...$this->datos, 'capacidad' => '15 lb'])->assertSessionHasNoErrors();

    expect($this->orden->equipments()->count())->toBe(1)->and($this->linea->fresh()->cantidad)->toBe(3);
});
