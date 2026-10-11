<?php

use App\Enums\TeamRole;
use App\Models\Deficiency;
use App\Models\Equipment;
use App\Models\ServiceOrder;
use App\Models\Team;
use App\Models\TechnicalChecklist;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');
    $this->team = Team::factory()->create();
    $this->user = User::factory()->create(['current_team_id' => $this->team->id]);
    $this->team->members()->attach($this->user, ['role' => TeamRole::Admin->value]);
    $this->user->assignRole('TecnicoCampo');
    $this->order = ServiceOrder::factory()->create(['departamento_tecnico' => 'campo', 'estado' => 'en_proceso']);
    $this->equipment = Equipment::factory()->create(['client_id' => $this->order->client_id]);
    $this->order->equipments()->attach($this->equipment->id, ['recibido' => true]);
    $this->url = fn (string $area) => route("tecnico-campo.{$area}.checklist.store", [
        'current_team' => $this->team, 'service_order' => $this->order, 'equipment' => $this->equipment,
    ]);
});

test('campo conserva el estado al guardar el formulario con foto vacia', function (string $area) {
    $this->actingAs($this->user)->post(($this->url)($area), [
        'items' => ['valvula' => ['estado' => 'conforme', 'foto' => null, 'requiere_autorizacion' => false]],
        'observaciones' => 'Formulario real con clave foto vacía',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $checklist = TechnicalChecklist::where('service_order_id', $this->order->id)->sole();
    expect($checklist->resultado_general)->toBe('conforme')
        ->and($checklist->items[0]['estado'])->toBe('conforme');
})->with(['inspecciones', 'mantenimientos']);

test('campo conserva condicion autorizacion y foto de un hallazgo', function (string $area) {
    $this->actingAs($this->user)->post(($this->url)($area), [
        'items' => ['valvula' => [
            'estado' => 'observado', 'condicion' => 'Fuga visible',
            'requiere_autorizacion' => true, 'foto' => UploadedFile::fake()->image('valvula.png'),
        ]],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $deficiency = Deficiency::where('service_order_id', $this->order->id)->sole();
    expect($deficiency->estado)->toBe('esperando_autorizacion')
        ->and($deficiency->nota)->toContain('Fuga visible')
        ->and($this->order->evidencias()->where('tipo', 'foto')->count())->toBe(1);
})->with(['inspecciones', 'mantenimientos']);

test('campo rechaza un item sin estado sin guardar un checklist', function (string $area) {
    $this->actingAs($this->user)->post(($this->url)($area), [
        'items' => ['valvula' => ['foto' => null]],
    ])->assertRedirect()->assertSessionHasErrors('items.valvula.estado');

    expect($this->order->checklists()->count())->toBe(0);
})->with(['inspecciones', 'mantenimientos']);
