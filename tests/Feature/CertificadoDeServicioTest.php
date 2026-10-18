<?php

use App\Actions\Certificates\EmitirCertificadoDeServicio;
use App\Actions\Certificates\IssueCertificate;
use App\Models\CertificateType;
use App\Models\Client;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\User;
use App\Services\Certificates\CertificateDocumentData;
use App\Services\Certificates\CertificatePdfService;
use Database\Seeders\CertificateTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(CertificateTypeSeeder::class);
    $this->luces = CertificateType::where('codigo', 'luces_emergencia')->firstOrFail();
});

test('un cliente de recojo con documento pendiente no puede emitir certificados', function () {
    $cliente = Client::factory()->create(['numero_documento' => '']);
    expect(fn () => app(IssueCertificate::class)->handle($this->luces, $cliente, []))
        ->toThrow(ValidationException::class, 'Completa el DNI/RUC del cliente antes de emitir el certificado.');
    expect($cliente->certificates()->count())->toBe(0);
});

if (! function_exists('vendedorUser')) {
    function vendedorUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Vendedor');

        return $user;
    }
}

function ventaDeServicio(?User $vendedor = null): Sale
{
    $sale = Sale::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'estado' => 'confirmada',
        'vendedor_id' => $vendedor?->id ?? User::factory()->create()->id,
    ]);
    $service = Service::factory()->create(['certificate_type_id' => CertificateType::where('codigo', 'luces_emergencia')->firstOrFail()->id]);
    SaleItem::factory()->forService($service)->create(['sale_id' => $sale->id]);

    return $sale;
}

function filaDeLuz(array $cambios = []): array
{
    return [
        'ubicacion' => 'Pasadizo 2do piso',
        'marca' => 'OPALUX',
        'modelo' => 'LED-200',
        'potencia' => '4',
        'autonomia' => '2',
        'resultado' => 'operativo',
        ...$cambios,
    ];
}

test('los tipos de servicio con formulario excluyen los de extintores y capacitacion', function () {
    $codigos = EmitirCertificadoDeServicio::tiposDeServicio()->pluck('codigo')->all();

    expect($codigos)->toContain('luces_emergencia', 'informe_deteccion', 'lamina_seguridad')
        ->not->toContain('operatividad_garantia', 'prueba_hidrostatica', 'capacitacion');
});

test('emite el certificado de luces con sus filas y pruebas y el pdf las pinta', function () {
    $sale = ventaDeServicio();

    $certificado = app(EmitirCertificadoDeServicio::class)->handle($sale, $this->luces, [
        'referencia' => 'SEDE: Tienda Centro',
        'tipo_atencion' => 'instalación',
        'filas' => [filaDeLuz(), filaDeLuz(['ubicacion' => 'Escalera', 'autonomia' => '1', 'resultado' => 'observado'])],
        'pruebas' => [['estado' => 'C'], ['estado' => 'C', 'valor' => '15']],
        'observaciones' => 'Se reemplazó una batería.',
    ]);

    expect($certificado->referencia)->toBe('SEDE: Tienda Centro')
        ->and($certificado->tipo_atencion)->toBe('instalación')
        ->and($certificado->datos['filas'][0]['item'])->toBe('01')
        ->and($certificado->datos['filas'][1]['item'])->toBe('02')
        ->and($certificado->datos['pruebas'])->toHaveCount(count($this->luces->checklist));

    $datos = app(CertificateDocumentData::class)->desde($certificado->fresh());

    // La activación tiene máximo 10 s: 15 s queda NC aunque se marcó C.
    expect($datos['pruebas'][1]['estado'])->toBe('NC')
        ->and($datos['resultado'])->toBe('observado')
        ->and($datos['observaciones'])->toBe('Se reemplazó una batería.');

    expect(app(CertificatePdfService::class)->generate($certificado->fresh())->output())->toStartWith('%PDF');
});

test('valida los obligatorios y los numeros segun las columnas del tipo', function () {
    $sale = ventaDeServicio();

    expect(fn () => app(EmitirCertificadoDeServicio::class)->handle($sale, $this->luces, [
        'filas' => [filaDeLuz(['marca' => '', 'autonomia' => 'dos horas'])],
    ]))->toThrow(function (ValidationException $e) {
        expect($e->errors())->toHaveKeys(['filas.0.marca', 'filas.0.autonomia']);
    });
});

test('volver a guardar corrige el mismo certificado con una revision', function () {
    $sale = ventaDeServicio();
    $emitir = app(EmitirCertificadoDeServicio::class);

    $primero = $emitir->handle($sale, $this->luces, ['filas' => [filaDeLuz()]]);
    $corregido = $emitir->handle($sale, $this->luces, ['filas' => [filaDeLuz(), filaDeLuz(['ubicacion' => 'Almacén'])]]);

    expect($corregido->id)->toBe($primero->id)
        ->and($corregido->numero)->toBe($primero->numero)
        ->and($corregido->revision)->toBe($primero->revision + 1)
        ->and($corregido->datos['filas'])->toHaveCount(2);
});

test('no se usa el formulario para certificados de extintores', function () {
    $sale = ventaDeServicio();
    $operatividad = CertificateType::where('codigo', 'operatividad_garantia')->firstOrFail();

    expect(fn () => app(EmitirCertificadoDeServicio::class)->handle($sale, $operatividad, ['filas' => [filaDeLuz()]]))
        ->toThrow(ValidationException::class);
});

test('el vendedor abre el formulario y emite desde la venta', function () {
    $vendedor = vendedorUser();
    $sale = ventaDeServicio($vendedor);
    $ruta = ['current_team' => $vendedor->currentTeam, 'sale' => $sale, 'tipo' => 'luces_emergencia'];

    $this->actingAs($vendedor)
        ->get(route('vendedor.ventas.certificado-servicio.create', $ruta))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('vendedor/ventas/certificado-servicio')
            ->where('tipo.codigo', 'luces_emergencia')
            ->has('tipo.columnas', count($this->luces->columnas))
            ->where('existente', null));

    $this->actingAs($vendedor)
        ->post(route('vendedor.ventas.certificado-servicio.store', $ruta), [
            'tipo_atencion' => 'mantenimiento',
            'filas' => [filaDeLuz()],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('vendedor.ventas.show', ['current_team' => $vendedor->currentTeam, 'sale' => $sale]));

    $this->actingAs($vendedor)
        ->get(route('vendedor.ventas.certificado-servicio.create', [...$ruta, 'tipo' => 'operatividad_garantia']))
        ->assertNotFound();
});
