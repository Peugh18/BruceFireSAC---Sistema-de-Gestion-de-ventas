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

test('el logo del comprobante no se acepta demasiado chico', function () {
    $user = gerenteUser();

    $this->actingAs($user)
        ->post(route('gerente.configuracion.empresa.update', ['current_team' => $user->currentTeam]), [
            'razon_social' => 'BRUCE FIRE S.A.C.',
            'ruc' => '20616376528',
            'logo' => UploadedFile::fake()->image('logo.png', 80, 40),
        ])
        ->assertSessionHasErrors('logo');

    expect(CompanySetting::current()->logo_path)->toBeNull();
});

test('el logo se dibuja dentro de la caja del comprobante sin deformarse', function () {
    Storage::disk('public')->put('company/ancho.png', UploadedFile::fake()->image('ancho.png', 1200, 300)->getContent());
    Storage::disk('public')->put('company/cuadrado.png', UploadedFile::fake()->image('cuadrado.png', 900, 900)->getContent());
    Storage::disk('public')->put('company/tira.png', UploadedFile::fake()->image('tira.png', 1800, 200)->getContent());
    $company = CompanySetting::current();

    $company->update(['logo_path' => 'company/ancho.png']);
    expect(array_diff_key($company->logoParaPdf(), ['src' => true]))->toBe(['ancho' => 190, 'alto' => 48]);

    $company->update(['logo_path' => 'company/cuadrado.png']);
    expect(array_diff_key($company->logoParaPdf(), ['src' => true]))->toBe(['ancho' => 80, 'alto' => 80]);

    // Muy alargado: crece un poco más a lo ancho para no quedar como una tira.
    $company->update(['logo_path' => 'company/tira.png']);
    expect(array_diff_key($company->logoParaPdf(), ['src' => true]))->toBe(['ancho' => 230, 'alto' => 26]);
});

test('gerente elige el color y los textos del comprobante y ve la vista previa', function () {
    $user = gerenteUser();

    $this->actingAs($user)
        ->post(route('gerente.configuracion.empresa.update', ['current_team' => $user->currentTeam]), [
            'razon_social' => 'BRUCE FIRE S.A.C.',
            'ruc' => '20616376528',
            'color_marca' => '#1f4e79',
            'sitio_web' => 'www.extintoresbrucefire.com',
            'mensaje_agradecimiento' => 'Gracias por confiar en nosotros',
            'condiciones_comprobante' => 'Garantía de 1 año.',
        ])
        ->assertSessionHasNoErrors();

    $company = CompanySetting::current();

    expect($company->colorMarca())->toBe('#1F4E79')
        ->and($company->colorTextoSobreMarca())->toBe('#FFFFFF')
        ->and($company->mensaje_agradecimiento)->toBe('Gracias por confiar en nosotros');

    $this->actingAs($user)
        ->get(route('gerente.configuracion.empresa.vista-previa', ['current_team' => $user->currentTeam]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $this->actingAs($user)
        ->post(route('gerente.configuracion.empresa.update', ['current_team' => $user->currentTeam]), [
            'razon_social' => 'BRUCE FIRE S.A.C.',
            'ruc' => '20616376528',
            'color_marca' => 'rojo',
        ])
        ->assertSessionHasErrors('color_marca');
});

test('un color de marca claro lleva el texto oscuro encima', function () {
    $company = CompanySetting::current();
    $company->update(['color_marca' => '#FFD54F']);

    expect($company->colorTextoSobreMarca())->toBe('#1A1A1A')
        ->and($company->colorMarcaSuave())->toBe('#FFFCF1');
});
