<?php

use App\Models\Deficiency;
use App\Models\DeficiencyAuthorization;
use App\Models\ServiceOrderEvent;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('autorizar deficiencia crea autorizacion, cambia estado y registra evento', function () {
    $user = vendedorUser();
    $deficiency = Deficiency::factory()->create(['estado' => 'esperando_autorizacion']);

    $this->actingAs($user)
        ->post(route('vendedor.deficiencias.autorizar', ['current_team' => $user->currentTeam, 'deficiency' => $deficiency]), [
            'autorizado' => true,
            'autorizado_por' => 'Carlos Cliente',
            'canal' => 'whatsapp',
            'fecha' => now()->toDateString(),
            'observacion' => 'Aprobado por WhatsApp.',
        ])
        ->assertSessionHasNoErrors();

    expect($deficiency->refresh()->estado)->toBe('autorizada')
        ->and(DeficiencyAuthorization::where('deficiency_id', $deficiency->id)->exists())->toBeTrue()
        ->and(ServiceOrderEvent::where('service_order_id', $deficiency->service_order_id)->where('tipo', 'autorizacion_registrada')->exists())->toBeTrue();
});

test('rechazar deficiencia solo cambia estado y registra evento', function () {
    $user = vendedorUser();
    $deficiency = Deficiency::factory()->create(['estado' => 'esperando_autorizacion']);

    $this->actingAs($user)
        ->post(route('vendedor.deficiencias.autorizar', ['current_team' => $user->currentTeam, 'deficiency' => $deficiency]), [
            'autorizado' => false,
            'autorizado_por' => 'Carlos Cliente',
            'canal' => 'presencial',
            'fecha' => now()->toDateString(),
        ])
        ->assertSessionHasNoErrors();

    expect($deficiency->refresh()->estado)->toBe('rechazada')
        ->and(DeficiencyAuthorization::where('deficiency_id', $deficiency->id)->exists())->toBeFalse()
        ->and(ServiceOrderEvent::where('service_order_id', $deficiency->service_order_id)->where('tipo', 'autorizacion_registrada')->exists())->toBeTrue();
});

test('autorizar deficiencia que no espera autorizacion falla', function () {
    $user = vendedorUser();
    $deficiency = Deficiency::factory()->create(['estado' => 'detectada']);

    $this->actingAs($user)
        ->post(route('vendedor.deficiencias.autorizar', ['current_team' => $user->currentTeam, 'deficiency' => $deficiency]), [
            'autorizado' => true,
            'autorizado_por' => 'Carlos Cliente',
            'canal' => 'whatsapp',
            'fecha' => now()->toDateString(),
        ])
        ->assertSessionHasErrors('deficiency');
});
