<?php

use App\Models\Client;
use App\Models\User;
use App\Rules\CelularPeru;
use App\Rules\LicenciaPeru;
use App\Rules\PlacaPeru;
use App\Rules\TelefonoPeru;
use App\Support\DatosPeru;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Validator;

function pasa(object $regla, string $valor): bool
{
    return Validator::make(['x' => $valor], ['x' => [$regla]])->passes();
}

test('el celular tiene 9 dígitos y empieza con 9', function (string $valor, bool $valido) {
    expect(pasa(new CelularPeru, $valor))->toBe($valido);
})->with([
    ['987654321', true],
    ['887654321', false],
    ['98765432', false],
    ['9876543210', false],
    ['abc987654', false],
]);

test('el teléfono acepta celular o fijo con código de ciudad', function (string $valor, bool $valido) {
    expect(pasa(new TelefonoPeru, $valor))->toBe($valido);
})->with([
    ['987654321', true],
    ['12345678', true],
    ['044123456', true],
    ['44123456', true],
    ['1234567', false],
    ['999', false],
    ['abcdefghi', false],
]);

test('la placa tiene 6 letras o números, con o sin guion', function (string $valor, bool $valido) {
    expect(pasa(new PlacaPeru, $valor))->toBe($valido);
})->with([
    ['ABC-123', true],
    ['ABC123', true],
    ['A1B-234', true],
    ['1234-AB', true],
    ['AB-12', false],
    ['ABC--123', false],
    ['ABCDEFGH', false],
]);

test('la licencia tiene una letra y 8 números', function (string $valor, bool $valido) {
    expect(pasa(new LicenciaPeru, $valor))->toBe($valido);
})->with([
    ['Q12345678', true],
    ['12345678', false],
    ['QQ1234567', false],
    ['Q1234', false],
]);

test('lo que se escribe se limpia antes de validar', function () {
    expect(DatosPeru::telefono('+51 987 654 321'))->toBe('987654321')
        ->and(DatosPeru::telefono('(044) 123-456'))->toBe('044123456')
        ->and(DatosPeru::telefono(''))->toBeNull()
        ->and(DatosPeru::placa(' abc 123 '))->toBe('ABC123')
        ->and(DatosPeru::licencia('q-1234 5678'))->toBe('Q12345678');
});

describe('formularios', function () {
    beforeEach(function () {
        $this->seed(RolesAndPermissionsSeeder::class);
    });

    function datosCliente(array $cambios = []): array
    {
        return array_merge([
            'tipo_documento' => 'dni',
            'numero_documento' => '71234567',
            'razon_social' => 'JUAN PEREZ',
        ], $cambios);
    }

    test('un cliente no acepta un teléfono mal escrito', function () {
        $user = vendedorUser();

        $this->actingAs($user)
            ->postJson(route('vendedor.clientes.store', ['current_team' => $user->currentTeam]), datosCliente(['telefono' => '12345']))
            ->assertJsonValidationErrors('telefono');
    });

    test('un cliente guarda el celular limpio aunque lo escriban con espacios y +51', function () {
        $user = vendedorUser();

        $this->actingAs($user)
            ->postJson(route('vendedor.clientes.store', ['current_team' => $user->currentTeam]), datosCliente(['whatsapp' => '+51 987 654 321']))
            ->assertCreated();

        expect(Client::where('numero_documento', '71234567')->value('whatsapp'))->toBe('987654321');
    });

    test('el número de documento no acepta letras', function () {
        $user = vendedorUser();

        $this->actingAs($user)
            ->postJson(route('vendedor.clientes.store', ['current_team' => $user->currentTeam]), datosCliente(['numero_documento' => 'ABCDEFGH']))
            ->assertJsonValidationErrors('numero_documento');
    });

    test('el gerente no puede guardar un RUC de empresa con el dígito verificador mal', function () {
        $gerente = User::factory()->create();
        $gerente->assignRole('Gerente');

        $this->actingAs($gerente)
            ->post(route('gerente.configuracion.empresa.update', ['current_team' => $gerente->currentTeam]), [
                'razon_social' => 'BRUCE FIRE S.A.C.',
                'ruc' => '20616376529',
            ])
            ->assertSessionHasErrors('ruc');
    });
});
