<?php

use App\Enums\EquipmentType;
use App\Models\Agente;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->vendedor = User::factory()->create();
    $this->vendedor->assignRole('Vendedor');
});

test('se agrega un agente nuevo, sale en los formularios y hereda las reglas de su tipo', function () {
    $this->actingAs($this->vendedor)
        ->postJson(route('agentes.store'), ['nombre' => '  Halotrón  ', 'tipo_base' => 'agente_limpio'])
        ->assertCreated();

    expect(EquipmentType::etiquetas())->toContain('Halotrón')
        ->and(EquipmentType::fromDescription('Halotrón'))->toBe(EquipmentType::AgenteLimpio)
        ->and(EquipmentType::esConocido('Halotrón'))->toBeTrue();
});

test('renombrar y quitar un agente; quitar solo lo esconde de la lista', function () {
    $agente = Agente::where('nombre', 'Espuma AFFF')->sole();

    $this->actingAs($this->vendedor)
        ->patchJson(route('agentes.update', $agente), ['nombre' => 'Espuma AR-AFFF', 'tipo_base' => 'espuma'])
        ->assertOk();
    $this->deleteJson(route('agentes.destroy', $agente))->assertOk();

    expect($agente->fresh())->nombre->toBe('Espuma AR-AFFF')->activo->toBeFalse()
        ->and(EquipmentType::etiquetas())->not->toContain('Espuma AR-AFFF')
        ->and(EquipmentType::fromDescription('Espuma AR-AFFF'))->toBe(EquipmentType::Espuma);
});

test('no se repite un nombre y volver a agregar uno quitado lo reactiva', function () {
    $co2 = Agente::where('nombre', 'CO2')->sole();
    $this->actingAs($this->vendedor)->deleteJson(route('agentes.destroy', $co2))->assertOk();
    $this->postJson(route('agentes.store'), ['nombre' => 'co2', 'tipo_base' => 'co2'])->assertCreated();

    expect($co2->fresh()->activo)->toBeTrue()
        ->and(Agente::whereRaw('LOWER(nombre) = ?', ['co2'])->count())->toBe(1);

    $this->patchJson(route('agentes.update', Agente::where('nombre', 'PQS BC')->sole()), ['nombre' => 'CO2', 'tipo_base' => 'pqs_bc'])
        ->assertJsonValidationErrors('nombre');
});

test('el técnico de campo no administra agentes', function () {
    $campo = User::factory()->create();
    $campo->assignRole('TecnicoCampo');

    $this->actingAs($campo)->postJson(route('agentes.store'), ['nombre' => 'Nuevo', 'tipo_base' => 'otro'])->assertForbidden();
});

test('renombrar un agente personalizado conserva sus nombres anteriores y sus reglas', function () {
    $agente = Agente::create(['nombre' => 'Halotrón', 'tipo_base' => 'agente_limpio']);

    $this->actingAs($this->vendedor)->patchJson(route('agentes.update', $agente), ['nombre' => 'Halotrón I', 'tipo_base' => 'agente_limpio'])
        ->assertOk();
    $this->patchJson(route('agentes.update', $agente), ['nombre' => 'Halotrón II', 'tipo_base' => 'agente_limpio'])
        ->assertOk();

    expect($agente->fresh()->nombres_anteriores)->toBe(['Halotrón', 'Halotrón I'])
        ->and(EquipmentType::fromDescription('Halotrón'))->toBe(EquipmentType::AgenteLimpio)
        ->and(EquipmentType::fromDescription('Halotrón I'))->toBe(EquipmentType::AgenteLimpio)
        ->and(EquipmentType::esConocido('Halotrón'))->toBeTrue();
});

test('el tipo configurado prevalece sobre las palabras del nombre', function () {
    Agente::create(['nombre' => 'Alternativa a CO2', 'tipo_base' => 'pqs']);

    expect(EquipmentType::fromDescription('Alternativa a CO2'))->toBe(EquipmentType::Pqs)
        ->and(EquipmentType::fromDescription('Alternativa a CO2')->presionPruebaHidrostatica())->toBe('600 PSI');
});

test('los nombres anteriores no se pueden reasignar a otro agente', function () {
    $agente = Agente::create(['nombre' => 'Halotrón', 'tipo_base' => 'agente_limpio']);
    $this->actingAs($this->vendedor)->patchJson(route('agentes.update', $agente), ['nombre' => 'Halotrón I', 'tipo_base' => 'agente_limpio'])
        ->assertOk();

    $this->postJson(route('agentes.store'), ['nombre' => 'halotrón', 'tipo_base' => 'pqs'])
        ->assertJsonValidationErrors('nombre');
    $this->patchJson(route('agentes.update', Agente::where('nombre', 'PQS BC')->sole()), ['nombre' => 'Halotrón', 'tipo_base' => 'pqs_bc'])
        ->assertJsonValidationErrors('nombre');
});

test('agregar un agente existente no cambia su tipo ni el de sus equipos', function () {
    $co2 = Agente::where('nombre', 'CO2')->sole();

    $this->actingAs($this->vendedor)->postJson(route('agentes.store'), ['nombre' => 'CO2', 'tipo_base' => 'pqs'])
        ->assertJsonValidationErrors('nombre');
    $this->patchJson(route('agentes.update', $co2), ['nombre' => 'Dióxido de carbono', 'tipo_base' => 'pqs'])
        ->assertJsonValidationErrors('tipo_base');

    expect($co2->fresh()->tipo_base)->toBe(EquipmentType::Co2);
});
