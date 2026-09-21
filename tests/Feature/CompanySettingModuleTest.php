<?php

use App\Models\CompanyBankAccount;
use App\Models\CompanySetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');
});

function gerenteUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('Gerente');

    return $user;
}

test('company setting current creates a default row from config on first access', function () {
    config(['billing.company.razon_social' => 'BRUCE FIRE S.A.C.', 'billing.company.ruc' => '20600000001']);

    $company = CompanySetting::current();

    expect($company->razon_social)->toBe('BRUCE FIRE S.A.C.')
        ->and($company->ruc)->toBe('20600000001')
        ->and(CompanySetting::count())->toBe(1);

    $again = CompanySetting::current();
    expect($again->id)->toBe($company->id);
});

test('gerente puede ver y actualizar los datos de la empresa incluyendo el logo', function () {
    $user = gerenteUser();
    CompanySetting::current();

    $this->actingAs($user)
        ->get(route('gerente.configuracion.empresa.edit', ['current_team' => $user->currentTeam]))
        ->assertOk();

    $logo = UploadedFile::fake()->image('logo.png', 200, 200);

    $this->actingAs($user)
        ->post(route('gerente.configuracion.empresa.update', ['current_team' => $user->currentTeam]), [
            'razon_social' => 'BRUCE FIRE & SAFETY S.A.C.',
            'ruc' => '20616376528',
            'direccion' => 'CAL. FELIPE PARDO Y ALIAGA 207',
            'logo' => $logo,
        ])
        ->assertRedirect();

    $company = CompanySetting::current();

    expect($company->razon_social)->toBe('BRUCE FIRE & SAFETY S.A.C.')
        ->and($company->ruc)->toBe('20616376528')
        ->and($company->logo_path)->not->toBeNull();

    Storage::disk('public')->assertExists($company->logo_path);
});

test('gerente puede agregar y eliminar cuentas bancarias', function () {
    $user = gerenteUser();

    $this->actingAs($user)
        ->post(route('gerente.configuracion.cuentas-bancarias.store', ['current_team' => $user->currentTeam]), [
            'banco' => 'BCP',
            'titular' => 'Bruce Fire',
            'numero_cuenta' => '570-8014716000',
            'cci' => '002-570-00801471600008',
            'moneda' => 'PEN',
        ])
        ->assertRedirect();

    $account = CompanyBankAccount::sole();
    expect($account->banco)->toBe('BCP');

    $this->actingAs($user)
        ->delete(route('gerente.configuracion.cuentas-bancarias.destroy', [
            'current_team' => $user->currentTeam,
            'cuenta_bancaria' => $account->id,
        ]))
        ->assertRedirect();

    expect(CompanyBankAccount::count())->toBe(0);
});

test('vendedor no puede acceder a la configuración de empresa', function () {
    $vendedor = vendedorUser();

    $this->actingAs($vendedor)
        ->get(route('gerente.configuracion.empresa.edit', ['current_team' => $vendedor->currentTeam]))
        ->assertForbidden();
});
